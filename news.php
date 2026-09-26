<?php require_once 'functions.php';
require_login();
$keywords = ['strike', 'protest', 'lockout', 'landslide', 'flood', 'streets', 'virus', 'shutdown', 'agitation', 'retailers', 'shopkeepers', 'traders', 'kirana', 'vendor', 'hawkers', 'businessmen', 'retail', 'store', 'ban', 'curfew', 'crackdown', 'festival', 'inventory'];
if (isset($_GET['fetch'])) {
    if (GNEWS_API_KEY === 'YOUR_GNEWS_API_KEY') {
        flash('Add your GNews API key in config.php to fetch live news.', 'error');
    } else {
        $url = 'https://gnews.io/api/v4/top-headlines?lang=en&country=in&max=20&token=' . urlencode(GNEWS_API_KEY);
        $ctx = stream_context_create(['http' => ['timeout' => 15]]);
        $raw = @file_get_contents($url, false, $ctx);
        $data = $raw ? json_decode($raw, true) : null;
        if (!empty($data['articles'])) {
            foreach ($data['articles'] as $a) {
                $title = (string)($a['title'] ?? '');
                $relevant = false;
                foreach ($keywords as $kw) {
                    if (str_contains(strtolower($title), $kw)) {
                        $relevant = true;
                        break;
                    }
                }
                if ($relevant) {
                    $q = db()->prepare('INSERT IGNORE INTO news_articles(title,description,url,image,published_at,source) VALUES(?,?,?,?,?,?)');
                    $q->execute([$title, $a['description'] ?? '', $a['url'] ?? '', $a['image'] ?? '', $a['publishedAt'] ?? null, $a['source']['name'] ?? '']);
                }
            }
            flash('News fetch completed. Keyword-based filtering used.');
        } else flash('News fetch failed. Check API key and internet.', 'error');
    }
    redirect('news.php');
}
if (isset($_GET['clear'])) {
    db()->exec('DELETE FROM news_articles');
    flash('Saved news cleared.');
    redirect('news.php');
}
$pageTitle = 'News';
$news = db()->query('SELECT * FROM news_articles ORDER BY published_at DESC,id DESC LIMIT 100')->fetchAll();
require 'partials/header.php'; ?><div class="news-wrapper">
    <div class="news-container panel">
        <h1 class="news-title">NEWS</h1>
        <p class="small">Live fetch uses GNews API. PHP uses the source project's important-word filter; Python pickle ML is not directly executable in PHP.</p><a class="btn refresh-item-btn" href="?fetch=1">Click here to Fetch Latest News</a> <a class="btn" href="?clear=1" onclick="return confirm('Clear saved news?')">Clear saved news</a><?php if (!$news): ?><p>No saved news found.</p><?php else: foreach ($news as $a): ?><article class="news-form">
                    <h3><?= e($a['title']) ?></h3>
                    <p><?= e($a['description']) ?></p><?php if ($a['url']): ?><a href="<?= e($a['url']) ?>" target="_blank" rel="noopener">Read more</a><?php endif; ?><p><em><?= e($a['published_at']) ?></em></p>
                </article><?php endforeach;
                                                                                                                                                                                                                                                                                                                                                                                                endif; ?>
    </div>
</div><?php require 'partials/footer.php'; ?>