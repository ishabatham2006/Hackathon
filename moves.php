<?php require_once 'functions.php'; require_login();
$pageTitle = 'Move History';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$warehouse = $_GET['warehouse'] ?? '';
$category = $_GET['category'] ?? '';
$productId = $_GET['product'] ?? '';

$sql = "SELECT sm.*, p.name product_name, p.sku, p.uom, p.category_id, c.name category_name
        FROM stock_moves sm
        JOIN products p ON p.id = sm.product_id
        LEFT JOIN product_categories c ON c.id = p.category_id
        WHERE 1=1";
$params = [];
if ($type !== '') { $sql .= ' AND sm.move_type=?'; $params[] = $type; }
if ($status !== '') { $sql .= ' AND sm.status=?'; $params[] = $status; }
if ($warehouse !== '') { $sql .= ' AND (sm.source_warehouse_id=? OR sm.dest_warehouse_id=?)'; $params[] = $warehouse; $params[] = $warehouse; }
if ($category !== '') { $sql .= ' AND p.category_id=?'; $params[] = $category; }
if ($productId !== '') { $sql .= ' AND sm.product_id=?'; $params[] = $productId; }
$sql .= ' ORDER BY sm.created_at DESC LIMIT 300';
$q = db()->prepare($sql); $q->execute($params);
$rows = $q->fetchAll();

$whs = warehouses(); $cats = categories();
require 'partials/header.php'; ?>
<div class="stocks-page">
  <h1 class="stocks-title">Move History — Stock Ledger</h1>
  <p class="small">Every Receipt, Delivery, Internal Transfer, and Adjustment is logged here once validated (plus drafts in progress).</p>

  <form method="get" class="filter-bar panel">
    <select name="type"><option value="">Document type…</option>
      <option value="receipt" <?=$type==='receipt'?'selected':''?>>Receipts</option>
      <option value="delivery" <?=$type==='delivery'?'selected':''?>>Delivery</option>
      <option value="internal" <?=$type==='internal'?'selected':''?>>Internal</option>
      <option value="adjustment" <?=$type==='adjustment'?'selected':''?>>Adjustments</option>
    </select>
    <select name="status"><option value="">Status…</option><?php foreach (['draft','waiting','ready','done','cancelled'] as $s): ?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select>
    <select name="warehouse"><option value="">Warehouse / location…</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>" <?=$warehouse==$w['id']?'selected':''?>><?=e($w['name'])?></option><?php endforeach; ?></select>
    <select name="category"><option value="">Category…</option><?php foreach ($cats as $c): ?><option value="<?=$c['id']?>" <?=$category==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach; ?></select>
    <button type="submit">Apply Filters</button>
    <a class="btn" href="moves.php">Clear</a>
  </form>

  <div class="panel">
    <div class="table-wrap">
      <table class="doc-lines-table">
        <thead><tr><th>Reference</th><th>Type</th><th>Product</th><th>Category</th><th>Qty</th><th>From</th><th>To</th><th>Status</th><th>Created</th><th>Validated</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?=e($r['reference'])?></td>
            <td><?=e(ucfirst($r['move_type']))?></td>
            <td><?=e($r['product_name'])?> (<?=e($r['sku'])?>)</td>
            <td><?=e($r['category_name'] ?? '—')?></td>
            <td><?=($r['quantity'] > 0 ? '+' : '').e($r['quantity']).' '.e($r['uom'])?></td>
            <td><?=$r['source_warehouse_id'] ? e(warehouse_name((int)$r['source_warehouse_id'])) : '—'?></td>
            <td><?=$r['dest_warehouse_id'] ? e(warehouse_name((int)$r['dest_warehouse_id'])) : '—'?></td>
            <td><?=status_badge($r['status'])?></td>
            <td><?=e($r['created_at'])?></td>
            <td><?=e($r['validated_at'] ?? '—')?></td>
          </tr>
        <?php endforeach; if (!$rows): ?><tr><td colspan="10">No stock movements match these filters.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require 'partials/footer.php'; ?>
