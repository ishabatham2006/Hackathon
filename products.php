<?php require_once 'functions.php'; require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'create') {
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $catName = trim($_POST['new_category'] ?? '');
            $catId = (int)($_POST['category_id'] ?? 0);
            $uom = trim($_POST['uom'] ?? 'Units');
            $reorder = max(0, (int)($_POST['reorder_min'] ?? 0));
            $initStock = max(0, (int)($_POST['initial_stock'] ?? 0));
            $whId = (int)($_POST['warehouse_id'] ?? 0);
            if ($name === '' || $sku === '') throw new Exception('Name and SKU are required.');
            if ($catName !== '') {
                db()->prepare('INSERT IGNORE INTO product_categories(name) VALUES(?)')->execute([$catName]);
                $q = db()->prepare('SELECT id FROM product_categories WHERE name=?'); $q->execute([$catName]);
                $catId = (int)$q->fetchColumn();
            }
            db()->prepare('INSERT INTO products(name,sku,category_id,uom,reorder_min) VALUES(?,?,?,?,?)')
                ->execute([$name, $sku, $catId ?: null, $uom, $reorder]);
            $pid = (int)db()->lastInsertId();
            if ($initStock > 0 && $whId > 0) {
                adjust_stock($pid, $whId, $initStock);
                $ref = next_reference('adjustment', 'ADJ');
                db()->prepare("INSERT INTO stock_moves(reference,move_type,product_id,quantity,source_warehouse_id,dest_warehouse_id,status,notes,created_by,validated_at) VALUES(?,'adjustment',?,?,?,?,'done',?,?,NOW())")
                    ->execute([$ref, $pid, $initStock, $whId, $whId, 'Initial stock on product creation', $_SESSION['user_id']]);
            }
            flash('Product created.');
        } elseif ($act === 'update') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? ''); $sku = trim($_POST['sku'] ?? '');
            $catId = (int)($_POST['category_id'] ?? 0); $uom = trim($_POST['uom'] ?? 'Units');
            $reorder = max(0, (int)($_POST['reorder_min'] ?? 0));
            if ($name === '' || $sku === '') throw new Exception('Name and SKU are required.');
            db()->prepare('UPDATE products SET name=?,sku=?,category_id=?,uom=?,reorder_min=? WHERE id=?')
                ->execute([$name, $sku, $catId ?: null, $uom, $reorder, $id]);
            flash('Product updated.');
        } elseif ($act === 'delete') {
            db()->prepare('DELETE FROM products WHERE id=?')->execute([(int)$_POST['id']]);
            flash('Product deleted.');
        }
    } catch (Throwable $e) { flash($e->getMessage(), 'error'); }
    redirect('products.php');
}
$pageTitle = 'Products';
$filter = $_GET['filter'] ?? '';
$all = products_list();
if ($filter === 'low') $all = array_filter($all, fn($p) => (int)$p['total_stock'] <= (int)$p['reorder_min'] && (int)$p['total_stock'] > 0);
if ($filter === 'out') $all = array_filter($all, fn($p) => (int)$p['total_stock'] <= 0);
$cats = categories(); $whs = warehouses();
require 'partials/header.php'; ?>
<div class="stocks-page">
  <h1 class="stocks-title">Products</h1>

  <div class="panel">
    <h2>New Product</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create">
      <input name="name" placeholder="Product name" required>
      <input name="sku" placeholder="SKU / Code" required>
      <select name="category_id"><option value="">Category…</option><?php foreach ($cats as $c): ?><option value="<?=$c['id']?>"><?=e($c['name'])?></option><?php endforeach; ?></select>
      <input name="new_category" placeholder="…or new category name">
      <input name="uom" placeholder="Unit of Measure (e.g. Kg, Pieces)" value="Units" required>
      <input type="number" min="0" name="reorder_min" placeholder="Reorder point (min qty)" value="0">
      <input type="number" min="0" name="initial_stock" placeholder="Initial stock (optional)">
      <select name="warehouse_id"><option value="">Warehouse for initial stock…</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>"><?=e($w['name'])?></option><?php endforeach; ?></select>
      <button>Create Product</button>
    </form>
  </div>

  <div class="stocks-table-section panel">
    <h2>All Products</h2>
    <div class="filter-bar"><a class="btn" href="products.php">All</a> <a class="btn" href="products.php?filter=low">Low stock</a> <a class="btn" href="products.php?filter=out">Out of stock</a></div>
    <div class="table-wrap">
      <table class="stocks-table">
        <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>UoM</th><th>Reorder Min</th><th>Stock (all locations)</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($all as $p): ?>
          <tr>
            <td><?=e($p['sku'])?></td>
            <td><?=e($p['name'])?></td>
            <td><?=e($p['category_name'] ?? '—')?></td>
            <td><?=e($p['uom'])?></td>
            <td><?=e($p['reorder_min'])?></td>
            <td class="<?=((int)$p['total_stock'] <= 0 ? 'low' : ((int)$p['total_stock'] <= (int)$p['reorder_min'] ? 'medium' : 'high'))?>"><?=e($p['total_stock'])?></td>
            <td class="actions">
              <details><summary class="btn">Edit</summary>
                <form method="post" class="form-grid">
                  <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?=$p['id']?>">
                  <input name="name" value="<?=e($p['name'])?>" required>
                  <input name="sku" value="<?=e($p['sku'])?>" required>
                  <select name="category_id"><option value="">Category…</option><?php foreach ($cats as $c): ?><option value="<?=$c['id']?>" <?=$c['id']==$p['category_id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach; ?></select>
                  <input name="uom" value="<?=e($p['uom'])?>" required>
                  <input type="number" min="0" name="reorder_min" value="<?=e($p['reorder_min'])?>">
                  <button>Save</button>
                </form>
              </details>
              <form method="post" onsubmit="return confirm('Delete this product?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>"><button>Delete</button></form>
              <a class="btn" href="moves.php?product=<?=$p['id']?>">Stock by location</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require 'partials/footer.php'; ?>
