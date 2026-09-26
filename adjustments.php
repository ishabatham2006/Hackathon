<?php require_once 'functions.php'; require_login();
$products = db()->query('SELECT * FROM products ORDER BY name')->fetchAll();
$whs = warehouses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'create') {
            $pid = (int)($_POST['product_id'] ?? 0);
            $whId = (int)($_POST['warehouse_id'] ?? 0);
            $counted = (int)($_POST['counted_qty'] ?? -1);
            $notes = trim($_POST['notes'] ?? '');
            if ($pid <= 0 || $whId <= 0 || $counted < 0) throw new Exception('Select product/location and enter the counted quantity.');
            $recorded = get_stock($pid, $whId);
            $delta = $counted - $recorded;
            if ($delta === 0) throw new Exception('Counted quantity matches recorded stock — nothing to adjust.');
            $ref = next_reference('adjustment', 'ADJ');
            db()->beginTransaction();
            db()->prepare("INSERT INTO stock_moves(reference,move_type,product_id,quantity,source_warehouse_id,dest_warehouse_id,status,notes,created_by) VALUES(?,'adjustment',?,?,?,?,'draft',?,?)")
                ->execute([$ref, $pid, $delta, $whId, $whId, "Recorded: $recorded, Counted: $counted. $notes", $_SESSION['user_id']]);
            db()->commit();
            flash("Adjustment $ref created (Draft). Validate to apply the correction.");
        } elseif ($act === 'validate') {
            $ref = $_POST['reference'];
            db()->beginTransaction();
            $lines = document_lines($ref);
            foreach ($lines as $l) {
                if ($l['status'] === 'done') continue;
                adjust_stock((int)$l['product_id'], (int)$l['source_warehouse_id'], (int)$l['quantity']);
            }
            db()->prepare("UPDATE stock_moves SET status='done', validated_at=NOW() WHERE reference=? AND move_type='adjustment'")->execute([$ref]);
            db()->commit();
            flash("Adjustment $ref validated — system auto-updated and logged the adjustment.");
        } elseif ($act === 'cancel') {
            db()->prepare("UPDATE stock_moves SET status='cancelled' WHERE reference=? AND move_type='adjustment'")->execute([$_POST['reference']]);
            flash('Adjustment cancelled.');
        }
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); flash($e->getMessage(), 'error'); }
    redirect('adjustments.php');
}

$pageTitle = 'Stock Adjustments';
$statusFilter = $_GET['status'] ?? '';
$whFilter = $_GET['warehouse'] ?? '';
$docs = documents('adjustment', ['status' => $statusFilter, 'warehouse' => $whFilter]);
require 'partials/header.php'; ?>
<div class="stocks-page">
  <h1 class="stocks-title">Stock Adjustments</h1>
  <p class="small">Fix mismatches between recorded stock and physical count: select product/location, enter the counted quantity — the system computes the difference, and on Validate it auto-updates and logs the adjustment.</p>

  <div class="panel">
    <h2>New Adjustment</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create">
      <select name="product_id" required><option value="">Product…</option><?php foreach ($products as $p): ?><option value="<?=$p['id']?>"><?=e($p['name'])?> (<?=e($p['sku'])?>)</option><?php endforeach; ?></select>
      <select name="warehouse_id" required><option value="">Location…</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>"><?=e($w['name'])?></option><?php endforeach; ?></select>
      <input type="number" min="0" name="counted_qty" placeholder="Physical counted quantity" required>
      <input name="notes" placeholder="Notes (e.g. damaged, miscount)">
      <button>Create Adjustment (Draft)</button>
    </form>
  </div>

  <div class="panel">
    <h2>Adjustments</h2>
    <form method="get" class="filter-bar">
      <select name="status" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach (['draft','waiting','ready','done','cancelled'] as $s): ?><option value="<?=$s?>" <?=$statusFilter===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select>
      <select name="warehouse" onchange="this.form.submit()"><option value="">All locations</option><?php foreach ($whs as $w): ?><option value="<?=$w['id']?>" <?=$whFilter==$w['id']?'selected':''?>><?=e($w['name'])?></option><?php endforeach; ?></select>
    </form>
    <div class="table-wrap">
      <table><thead><tr><th>Reference</th><th>Location</th><th>Δ Qty</th><th>Notes</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d):
        $line = document_lines($d['reference'])[0] ?? null; ?>
        <tr>
          <td><?=e($d['reference'])?></td>
          <td><?=e(warehouse_name((int)$d['source_warehouse_id']))?></td>
          <td><?=($d['total_qty'] > 0 ? '+' : '').e($d['total_qty'])?></td>
          <td class="small"><?=e($line['notes'] ?? '')?></td>
          <td><?=status_badge($d['status'])?></td>
          <td><?=e($d['created_at'])?></td>
          <td class="actions">
            <?php if (in_array($d['status'], ['draft','waiting','ready'], true)): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="validate"><input type="hidden" name="reference" value="<?=e($d['reference'])?>"><button>Validate</button></form>
              <form method="post" style="display:inline" onsubmit="return confirm('Cancel this adjustment?')"><input type="hidden" name="action" value="cancel"><input type="hidden" name="reference" value="<?=e($d['reference'])?>"><button>Cancel</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$docs): ?><tr><td colspan="7">No adjustments yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php require 'partials/footer.php'; ?>
