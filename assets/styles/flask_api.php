
<?php
/**
 * PHP replacement for selected Flask API routes.
 * News filtering is keyword-based, not the original ML model.
 *
 * Actions:
 * realtime-news
 * saved-news
 * clear-news
 * check-trends
 * refresh-missing
 */

require_once __DIR__ . '/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function api_json($payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function is_relevant_news_title(string $title): bool {
    $keywords = [
        'strike', 'protest', 'lockout', 'landslide', 'flood',
        'floods', 'street', 'virus', 'shutdown', 'agitation',
        'retailers', 'shopkeepers', 'traders', 'kirana',
        'vendor', 'hawker', 'businessmen', 'retail', 'store',
        'ban', 'curfew', 'crackdown', 'festival', 'inventory'
    ];

    $title = mb_strtolower($title, 'UTF-8');

    foreach ($keywords as $word) {
        if (mb_strpos($title, $word, 0, 'UTF-8') !== false) {
            return true;
        }
    }

    return false;
}

function fetch_url(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'StockSense-PHP/1.0'
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($body !== false && $status >= 200 && $status < 300)
            ? $body : null;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 20,
            'header' => "User-Agent: StockSense-PHP/1.0\r\n"
        ]
    ]);

    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // 1. Fetch and save relevant news
        case 'realtime-news':

            if (
                !defined('GNEWS_API_KEY') ||
                GNEWS_API_KEY === '' ||
                GNEWS_API_KEY === 'YOUR_GNEWS_API_KEY'
            ) {
                api_json([
                    'error' => 'GNews API key is not configured in config.php'
                ], 400);
            }

            $url = 'https://gnews.io/api/v4/top-headlines?lang=en&country=in&max=20&token='
                . rawurlencode(GNEWS_API_KEY);

            $raw = fetch_url($url);
            $data = $raw ? json_decode($raw, true) : null;

            if (!is_array($data) || !isset($data['articles'])) {
                api_json([
                    'error' => 'Unable to fetch GNews data. Check API key and internet connection.'
                ], 502);
            }

            $saved = [];

            $stmt = db()->prepare(
                'INSERT IGNORE INTO news_articles
                (title, description, url, image, published_at, source)
                VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($data['articles'] as $article) {
                $title = trim((string)($article['title'] ?? ''));

                if ($title === '' || !is_relevant_news_title($title)) {
                    continue;
                }

                $published = $article['publishedAt'] ?? null;

                if ($published) {
                    $ts = strtotime($published);
                    $published = $ts ? date('Y-m-d H:i:s', $ts) : null;
                }

                $stmt->execute([
                    $title,
                    $article['description'] ?? '',
                    $article['url'] ?? '',
                    $article['image'] ?? '',
                    $published,
                    $article['source']['name'] ?? ''
                ]);

                $saved[] = $article;
            }

            api_json([
                'source' => 'gnews',
                'filter' => 'keyword-based (not the original pickle ML model)',
                'count' => count($saved),
                'articles' => $saved
            ]);

        // 2. Show saved news
        case 'saved-news':

            api_json(
                db()->query(
                    'SELECT title, description, url, image, published_at, source
                     FROM news_articles
                     ORDER BY published_at DESC, id DESC
                     LIMIT 100'
                )->fetchAll()
            );

        // 3. Delete saved news
        case 'clear-news':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_json([
                    'error' => 'Use POST to clear saved news.'
                ], 405);
            }

            $count = db()->exec('DELETE FROM news_articles');

            api_json([
                'message' => 'Saved news cleared.',
                'deleted_count' => $count
            ]);

        // 4. Compare Google Trends with inventory
        case 'check-trends':

            $xml = fetch_url(
                'https://trends.google.com/trending/rss?geo=IN'
            );

            if (!$xml) {
                api_json([
                    'error' => 'Could not fetch Google Trends RSS.'
                ], 502);
            }

            libxml_use_internal_errors(true);
            $feed = simplexml_load_string($xml);
            libxml_clear_errors();

            if (!$feed) {
                api_json([
                    'error' => 'Google Trends RSS response could not be parsed.'
                ], 502);
            }

            $inventoryRows = db()->query(
                'SELECT name FROM stocks
                 UNION
                 SELECT name FROM clothing_items'
            )->fetchAll();

            $inventory = [];

            foreach ($inventoryRows as $row) {
                $inventory[
                    mb_strtolower(trim((string)$row['name']), 'UTF-8')
                ] = true;
            }

            $missing = [];

            foreach (($feed->channel->item ?? []) as $item) {
                $name = trim((string)$item->title);
                $key = mb_strtolower($name, 'UTF-8');

                if ($name !== '' && !isset($inventory[$key])) {
                    $missing[$key] = $name;
                }
            }

            $stmt = db()->prepare(
                'INSERT IGNORE INTO missing_products (name, checked_at)
                 VALUES (?, NOW())'
            );

            foreach ($missing as $name) {
                $stmt->execute([$name]);
            }

            api_json([
                'source' => 'Google Trends RSS (not the original PyTrends product-suggestion model)',
                'missing' => array_values($missing),
                'count' => count($missing)
            ]);

        // 5. Refresh missing products
        case 'refresh-missing':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_json([
                    'error' => 'Use POST to refresh missing products.'
                ], 405);
            }

            db()->exec(
                'DELETE mp FROM missing_products mp
                 WHERE EXISTS (
                     SELECT 1 FROM stocks s
                     WHERE LOWER(s.name) = LOWER(mp.name)
                 )
                 OR EXISTS (
                     SELECT 1 FROM clothing_items c
                     WHERE LOWER(c.name) = LOWER(mp.name)
                 )'
            );

            $items = db()->query(
                'SELECT name, checked_at
                 FROM missing_products
                 ORDER BY checked_at DESC, name'
            )->fetchAll();

            api_json([
                'source' => 'mysql inventory comparison',
                'missing' => $items
            ]);

        default:

            api_json([
                'error' => 'Unknown action. Use realtime-news, saved-news, clear-news, check-trends, or refresh-missing.'
            ], 400);
    }

} catch (Throwable $e) {
    api_json([
        'error' => 'API request failed.',
        'detail' => $e->getMessage()
    ], 500);
}