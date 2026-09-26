<?php require_once 'functions.php'; require_login();
$products = db()->query('SELECT * FROM products ORDER BY name')->fetchAll();
$whs = warehouses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'create') {
            $srcId = (int)($_POST['source_warehouse_id'] ?? 0);
            $dstId = (int)($_POST['dest_warehouse_id'] ?? 0);
            $pids = $_POST['product_id'] ?? [];
            $qtys = $_POST['quantity'] ?? [];
            if ($srcId <= 0 || $dstId <= 0) throw new Exception('Source and destination locations are required.');
            if ($srcId === $dstId) throw new Exception('Source and destination must be different.');
            $ref = next_reference('internal', 'INT');
            $lines = 0;
            db()->beginTransaction();
            foreach ($pids as $k => $pid) {
                $pid = (int)$pid; $qty = (int)($qtys[$k] ?? 0);
                if ($pid <= 0 || $qty <= 0) continue;
                db()->prepare("INSERT INTO stock_moves(reference,move_type,product_id,quantity,source_warehouse_id,dest_warehouse_id,status,created_by) VALUES(?,'internal',?,?,?,?,'draft',?)")
                    ->execute([$ref, $pid, $qty, $srcId, $dstId, $_SESSION['user_id']]);
                $lines++;
            }
            if ($lines === 0) throw new Exception('Add at least one product and quantity.');
            db()->commit();
            flash("Internal transfer $ref created as Draft.");
        } elseif ($act === 'validate') {
            $ref = $_POST['reference'];
            db()->beginTransaction();
            $lines = document_lines($ref);
            foreach ($lines as $l) {
                if ($l['status'] === 'done') continue;
                $available = get_stock((int)$l['product_id'], (int)$l['source_warehouse_id']);
                if ($available < (int)$l['quantity']) throw new Exception('Not enough stock for ' . $l['product_name'] . ' at source location.');
            }
            foreach ($lines as $l) {
                if ($l['status'] === 'done') continue;
                adjust_stock((int)$l['product_id'], (int)$l['source_warehouse_id'], -(int)$l['quantity']);
                adjust_stock((int)$l['product_id'], (int)$l['dest_warehouse_id'], (int)$l['quantity']);
            }
            db()->prepare("UPDATE stock_moves SET status='done', validated_at=NOW() WHERE reference=? AND move_type='internal'")->execute([$ref]);
            db()->commit();
            flash("Transfer $ref validated — stock unchanged in total, location updated.");
        } elseif ($act === 'cancel') {
            db()->prepare("UPDATE stock_moves SET status='cancelled' WHERE reference=? AND move_type='internal'")->execute([$_POST['reference']]);
            flash('Internal transfer cancelled.');
        }
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); flash($e->getMessage(), 'error'); }
    redirect('transfers.php');
}

$pageTitle = 'Internal Transfers';
$statusFilter = $_GET['status'] ?? '';
$whFilter = $_GET['warehouse'] ?? '';
$docs = documents('internal', ['status' => $statusFilter, 'warehouse' => $whFilter]);
require 'partials/header.php'; ?>
<div class="stocks-page">
  <h1 class="stocks-title">Internal Transfers</h1>
  <p class="small">Move stock inside the company (e.g. Main Warehouse → Production Floor). Every movement is logged in the ledger.</p>

  <div class="panel">
    <h2>New Internal Transfer</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create">
      <select name="source_warehouse_id" required><option value="">From location…</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>"><?=e($w['name'])?></option><?php endforeach; ?></select>
      <select name="dest_warehouse_id" required><option value="">To location…</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>"><?=e($w['name'])?></option><?php endforeach; ?></select>
      <div id="lines"><div class="line-row form-grid">
        <select name="product_id[]"><option value="">Product…</option><?php foreach ($products as $p): ?><option value="<?=$p['id']?>"><?=e($p['name'])?> (<?=e($p['sku'])?>)</option><?php endforeach; ?></select>
        <input type="number" min="1" name="quantity[]" placeholder="Qty to move">
      </div></div>
      <button type="button" onclick="addLine()">+ Add product line</button>
      <button type="submit">Create Transfer (Draft)</button>
    </form>
  </div>

  <div class="panel">
    <h2>Internal Transfers</h2>
    <form method="get" class="filter-bar">
      <select name="status" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach (['draft','waiting','ready','done','cancelled'] as $s): ?><option value="<?=$s?>" <?=$statusFilter===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select>
      <select name="warehouse" onchange="this.form.submit()"><option value="">All locations</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>" <?=$whFilter==$w['id']?'selected':''?>><?=e($w['name'])?></option><?php endforeach; ?></select>
    </form>
    <div class="table-wrap">
      <table><thead><tr><th>Reference</th><th>From</th><th>To</th><th>Lines</th><th>Total Qty</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><?=e($d['reference'])?></td>
          <td><?=e(warehouse_name((int)$d['source_warehouse_id']))?></td>
          <td><?=e(warehouse_name((int)$d['dest_warehouse_id']))?></td>
          <td><?=e($d['line_count'])?></td>
          <td><?=e($d['total_qty'])?></td>
          <td><?=status_badge($d['status'])?></td>
          <td><?=e($d['created_at'])?></td>
          <td class="actions">
            <?php if (in_array($d['status'], ['draft','waiting','ready'], true)): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="validate"><input type="hidden" name="reference" value="<?=e($d['reference'])?>"><button>Validate</button></form>
              <form method="post" style="display:inline" onsubmit="return confirm('Cancel this transfer?')"><input type="hidden" name="action" value="cancel"><input type="hidden" name="reference" value="<?=e($d['reference'])?>"><button>Cancel</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$docs): ?><tr><td colspan="8">No internal transfers yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<script>function addLine(){let r=document.querySelector('.line-row').cloneNode(true);r.querySelectorAll('input').forEach(x=>x.value='');document.getElementById('lines').appendChild(r)}</script>
<?php require 'partials/footer.php'; ?>
