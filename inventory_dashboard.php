<?php
require_once 'functions.php';
require_login();

$pageTitle = 'Inventory Dashboard';
$k = ims_kpis();
$whs = warehouses();
$cats = categories();

require 'partials/header.php';
?>

<div class="dashboard-page">
  <h1>Inventory Dashboard</h1>

  <!-- KPI CARDS -->
  <div class="metric-grid">
    <a class="kpi-link" href="products.php">
      <div class="metric-card">
        Total Products in Stock
        <strong><?=e($k['total_products'])?></strong>
      </div>
    </a>

    <a class="kpi-link" href="products.php?filter=low">
      <div class="metric-card">
        Low Stock Items
        <strong><?=e($k['low_stock'])?></strong>
      </div>
    </a>

    <a class="kpi-link" href="products.php?filter=out">
      <div class="metric-card">
        Out of Stock Items
        <strong><?=e($k['out_of_stock'])?></strong>
      </div>
    </a>

    <a class="kpi-link" href="receipts.php?status=draft">
      <div class="metric-card">
        Pending Receipts
        <strong><?=e($k['pending_receipts'])?></strong>
      </div>
    </a>

    <a class="kpi-link" href="deliveries.php?status=draft">
      <div class="metric-card">
        Pending Deliveries
        <strong><?=e($k['pending_deliveries'])?></strong>
      </div>
    </a>

    <a class="kpi-link" href="transfers.php?status=draft">
      <div class="metric-card">
        Internal Transfers Scheduled
        <strong><?=e($k['transfers_scheduled'])?></strong>
      </div>
    </a>
  </div>

  <!-- INVENTORY CHARTS -->
  <div class="dashboard-grid">

    <div class="panel">
      <h2>Inventory Overview</h2>
      <canvas id="stockChart"></canvas>
    </div>

    <div class="panel">
      <h2>Stock Status</h2>
      <canvas id="healthChart"></canvas>
    </div>

  </div>

  <!-- FIND OPERATIONS -->
  <div class="panel">
    <h2>Find Operations</h2>
    <p class="small">
      Jump straight into Move History filtered by document type,
      status, warehouse, or category.
    </p>

    <form method="get" action="moves.php" class="filter-bar">

      <select name="type">
        <option value="">Document type…</option>
        <option value="receipt">Receipts</option>
        <option value="delivery">Delivery</option>
        <option value="internal">Internal</option>
        <option value="adjustment">Adjustments</option>
      </select>

      <select name="status">
        <option value="">Status…</option>
        <option value="draft">Draft</option>
        <option value="waiting">Waiting</option>
        <option value="ready">Ready</option>
        <option value="done">Done</option>
        <option value="cancelled">Cancelled</option>
      </select>

      <select name="warehouse">
        <option value="">Warehouse / location…</option>
        <?php foreach ($whs as $w): ?>
          <option value="<?=$w['id']?>">
            <?=e($w['name'])?>
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Filter</button>
    </form>
  </div>

  <!-- QUICK ACTIONS AND LOW STOCK -->
  <div class="dashboard-grid">

    <div class="panel">
      <h2>Quick Actions</h2>
      <div class="actions">
        <a class="btn" href="receipts.php">New Receipt</a>
        <a class="btn" href="deliveries.php">New Delivery Order</a>
        <a class="btn" href="transfers.php">New Internal Transfer</a>
        <a class="btn" href="adjustments.php">New Stock Adjustment</a>
        <a class="btn" href="products.php">Manage Products</a>
        <a class="btn" href="warehouses.php">Manage Warehouses</a>
      </div>
    </div>

    <div class="panel">
      <h2>Low / Out of Stock</h2>

      <?php
      $low = low_stock_products();

      if (!$low):
      ?>
        <p class="recommend-empty">
          Everything is above the reorder point.
        </p>
      <?php else: ?>

        <?php foreach (array_slice($low, 0, 8) as $p): ?>
          <p class="notification-item">
            <?=e($p['name'])?>
            (<?=e($p['sku'])?>) —
            <?=e($p['total_stock'])?>
            <?=e($p['uom'])?>
            in stock, reorder at
            <?=e($p['reorder_min'])?>
          </p>
        <?php endforeach; ?>

      <?php endif; ?>

      <a class="btn" href="products.php">View all products</a>
    </div>

  </div>
</div>

<!-- CHART.JS GRAPHS -->
<script>
document.addEventListener('DOMContentLoaded', function () {

  const totalProducts = <?=json_encode((int)$k['total_products'])?>;
  const lowStock = <?=json_encode((int)$k['low_stock'])?>;
  const outOfStock = <?=json_encode((int)$k['out_of_stock'])?>;

  // Bar Chart
  new Chart(document.getElementById('stockChart'), {
    type: 'bar',
    data: {
      labels: ['Total Products', 'Low Stock', 'Out of Stock'],
      datasets: [{
        label: 'Number of Products',
        data: [totalProducts, lowStock, outOfStock],
        backgroundColor: [
          '#4e79a7',
          '#f2a541',
          '#e15759'
        ],
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0
          }
        }
      }
    }
  });

  // Doughnut Chart
  const inStock = Math.max(0, totalProducts - lowStock);

  new Chart(document.getElementById('healthChart'), {
    type: 'doughnut',
    data: {
      labels: ['In Stock', 'Low Stock', 'Out of Stock'],
      datasets: [{
        data: [
          inStock,
          lowStock,
          outOfStock
        ],
        backgroundColor: [
          '#59a14f',
          '#f2a541',
          '#e15759'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true
    }
  });

});
</script>

<?php require 'partials/footer.php'; ?>