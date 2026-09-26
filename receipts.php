<?php require_once 'functions.php'; require_login();
$products = db()->query('SELECT * FROM products ORDER BY name')->fetchAll();
$whs = warehouses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'create') {
            $supplier = trim($_POST['supplier'] ?? '');
            $whId = (int)($_POST['warehouse_id'] ?? 0);
            $pids = $_POST['product_id'] ?? [];
            $qtys = $_POST['quantity'] ?? [];
            if ($supplier === '' || $whId <= 0) throw new Exception('Supplier and destination warehouse are required.');
            $partnerId = get_or_create_partner($supplier, 'supplier');
            $ref = next_reference('receipt', 'RCPT');
            $lines = 0;
            db()->beginTransaction();
            foreach ($pids as $k => $pid) {
                $pid = (int)$pid; $qty = (int)($qtys[$k] ?? 0);
                if ($pid <= 0 || $qty <= 0) continue;
                db()->prepare("INSERT INTO stock_moves(reference,move_type,product_id,quantity,dest_warehouse_id,partner_id,status,created_by) VALUES(?,'receipt',?,?,?,?,'draft',?)")
                    ->execute([$ref, $pid, $qty, $whId, $partnerId, $_SESSION['user_id']]);
                $lines++;
            }
            if ($lines === 0) throw new Exception('Add at least one product and quantity.');
            db()->commit();
            flash("Receipt $ref created as Draft. Validate it once goods are physically received.");
        } elseif ($act === 'validate') {
            $ref = $_POST['reference'];
            db()->beginTransaction();
            $lines = document_lines($ref);
            foreach ($lines as $l) {
                if ($l['status'] === 'done') continue;
                adjust_stock((int)$l['product_id'], (int)$l['dest_warehouse_id'], (int)$l['quantity']);
            }
            db()->prepare("UPDATE stock_moves SET status='done', validated_at=NOW() WHERE reference=? AND move_type='receipt'")->execute([$ref]);
            db()->commit();
            flash("Receipt $ref validated — stock increased automatically.");
        } elseif ($act === 'cancel') {
            db()->prepare("UPDATE stock_moves SET status='cancelled' WHERE reference=? AND move_type='receipt'")->execute([$_POST['reference']]);
            flash('Receipt cancelled.');
        }
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); flash($e->getMessage(), 'error'); }
    redirect('receipts.php');
}

$pageTitle = 'Receipts';
$statusFilter = $_GET['status'] ?? '';
$whFilter = $_GET['warehouse'] ?? '';
$docs = documents('receipt', ['status' => $statusFilter, 'warehouse' => $whFilter]);
require 'partials/header.php'; ?>
<div class="stocks-page">
  <h1 class="stocks-title">Receipts (Incoming Stock)</h1>
  <p class="small">Create a receipt, add supplier &amp; products, input quantities received, then Validate → stock increases automatically.</p>

  <div class="panel">
    <h2>New Receipt</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create">
      <input name="supplier" placeholder="Supplier name" required>
      <select name="warehouse_id" required><option value="">Destination warehouse…</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>"><?=e($w['name'])?></option><?php endforeach; ?></select>
      <div id="lines"><div class="line-row form-grid">
        <select name="product_id[]"><option value="">Product…</option><?php foreach ($products as $p): ?><option value="<?=$p['id']?>"><?=e($p['name'])?> (<?=e($p['sku'])?>)</option><?php endforeach; ?></select>
        <input type="number" min="1" name="quantity[]" placeholder="Qty received">
      </div></div>
      <button type="button" onclick="addLine()">+ Add product line</button>
      <button type="submit">Create Receipt (Draft)</button>
    </form>
  </div>

  <div class="panel">
    <h2>Receipts</h2>
    <form method="get" class="filter-bar">
      <select name="status" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach (['draft','waiting','ready','done','cancelled'] as $s): ?><option value="<?=$s?>" <?=$statusFilter===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select>
      <select name="warehouse" onchange="this.form.submit()"><option value="">All warehouses</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>" <?=$whFilter==$w['id']?'selected':''?>><?=e($w['name'])?></option><?php endforeach; ?></select>
    </form>
    <div class="table-wrap">
      <table><thead><tr><th>Reference</th><th>Warehouse</th><th>Lines</th><th>Total Qty</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><?=e($d['reference'])?></td>
          <td><?=e(warehouse_name((int)$d['dest_warehouse_id']))?></td>
          <td><?=e($d['line_count'])?></td>
          <td><?=e($d['total_qty'])?></td>
          <td><?=status_badge($d['status'])?></td>
          <td><?=e($d['created_at'])?></td>
          <td class="actions">
            <?php if (in_array($d['status'], ['draft','waiting','ready'], true)): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="validate"><input type="hidden" name="reference" value="<?=e($d['reference'])?>"><button>Validate</button></form>
              <form method="post" style="display:inline" onsubmit="return confirm('Cancel this receipt?')"><input type="hidden" name="action" value="cancel"><input type="hidden" name="reference" value="<?=e($d['reference'])?>"><button>Cancel</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$docs): ?><tr><td colspan="7">No receipts yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<script>function addLine(){let r=document.querySelector('.line-row').cloneNode(true);r.querySelectorAll('input').forEach(x=>x.value='');document.getElementById('lines').appendChild(r)}</script>
<?php require 'partials/footer.php'; ?>
