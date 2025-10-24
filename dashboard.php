<?php
// dashboard.php — pastel tone version
$DB_HOST = 'localhost';
$DB_USER = 's67160332';
$DB_PASS = 'K7nfDXHe';
$DB_NAME = 's67160332';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) return [];
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  $res->free();
  return $rows;
}

$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
$kpis = fetch_all($mysqli, "
  SELECT
    (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT SUM(quantity) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

function nf($n) { return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW Dashboard (Pastel Theme)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
/* 🌈 Pastel Dashboard Theme */
body {
  background: linear-gradient(180deg, #fff7fa 0%, #f3f8ff 50%, #fffafc 100%);
  color: #333;
  font-family: 'Poppins', sans-serif;
}

.navbar {
  background: linear-gradient(90deg, #a8edea, #fed6e3);
  border: none;
  box-shadow: 0 2px 8px rgba(255, 182, 193, 0.3);
}
.navbar .navbar-brand { color: #333; font-weight: 600; }
.navbar .text-muted { color: #555 !important; }

.card {
  background: #ffffffcc;
  border-radius: 1rem;
  border: none;
  box-shadow: 0 6px 18px rgba(255, 182, 193, 0.2);
  transition: all 0.3s;
}
.card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(255, 182, 193, 0.35);
}

h2, h5 { color: #444; font-weight: 600; }
.kpi { font-size: 1.5rem; font-weight: 700; color: #ff6fa3; }
.sub { color: #999; font-size: 0.9rem; }

.btn-outline-secondary {
  border-color: #ffb6c1;
  color: #ff6fa3;
}
.btn-outline-secondary:hover {
  background: linear-gradient(90deg, #fbc2eb, #a6c1ee);
  color: #fff;
  border-color: transparent;
}

canvas { max-height: 360px; }

/* Responsive grid */
.grid { display: grid; gap: 1rem; grid-template-columns: repeat(12, 1fr); }
.col-12 { grid-column: span 12; }
.col-6 { grid-column: span 6; }
.col-4 { grid-column: span 4; }
.col-8 { grid-column: span 8; }
@media (max-width: 991px) {
  .col-6, .col-4, .col-8 { grid-column: span 12; }
}
</style>
</head>
<body class="p-3 p-md-4">
<nav class="navbar navbar-light mb-4">
  <div class="container d-flex justify-content-between align-items-center">
    <span class="navbar-brand">MyApp (Pastel)</span>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted small">Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
      <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">ยอดขาย (Retail DW) — Dashboard</h2>
    <span class="sub">แหล่งข้อมูล: MySQL (mysqli)</span>
  </div>

  <!-- KPI -->
  <div class="grid mb-3">
    <div class="card p-3 col-4" style="background: linear-gradient(135deg, #a8edea, #fed6e3);">
      <h5>ยอดขาย 30 วัน</h5>
      <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
    </div>
    <div class="card p-3 col-4" style="background: linear-gradient(135deg, #fdfbfb, #ebedee);">
      <h5>จำนวนชิ้นขาย 30 วัน</h5>
      <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div>
    </div>
    <div class="card p-3 col-4" style="background: linear-gradient(135deg, #cfd9df, #e2ebf0);">
      <h5>จำนวนผู้ซื้อ 30 วัน</h5>
      <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="grid">
    <div class="card p-3 col-8"><h5>ยอดขายรายเดือน (2 ปี)</h5><canvas id="chartMonthly"></canvas></div>
    <div class="card p-3 col-4"><h5>สัดส่วนยอดขายตามหมวด</h5><canvas id="chartCategory"></canvas></div>
    <div class="card p-3 col-6"><h5>Top 10 สินค้าขายดี</h5><canvas id="chartTopProducts"></canvas></div>
    <div class="card p-3 col-6"><h5>ยอดขายตามภูมิภาค</h5><canvas id="chartRegion"></canvas></div>
    <div class="card p-3 col-6"><h5>วิธีการชำระเงิน</h5><canvas id="chartPayment"></canvas></div>
    <div class="card p-3 col-6"><h5>ยอดขายรายชั่วโมง</h5><canvas id="chartHourly"></canvas></div>
    <div class="card p-3 col-12"><h5>ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5><canvas id="chartNewReturning"></canvas></div>
  </div>
</div>

<script>
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

// พาเลตสีพาสเทล
const pastel = ['#ffb6b9', '#fae3d9', '#bbded6', '#8ac6d1', '#dcedc1', '#ffd3b6', '#ffaaa5', '#a8edea', '#fbc2eb'];

const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });

// Monthly
(() => {
  const {labels, values} = toXY(monthly, 'ym', 'net_sales');
  new Chart(chartMonthly, {
    type: 'line',
    data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values, fill: true, tension: .3,
      backgroundColor: 'rgba(255,182,193,0.3)', borderColor: '#ff6fa3' }] },
    options: { plugins: { legend: { labels: { color: '#444' } } } }
  });
})();

// Category
(() => {
  const {labels, values} = toXY(category, 'category', 'net_sales');
  new Chart(chartCategory, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values, backgroundColor: pastel }] },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#444' } } } }
  });
})();

// Top products
(() => {
  const labels = topProducts.map(o => o.product_name);
  const qty = topProducts.map(o => parseInt(o.qty_sold));
  new Chart(chartTopProducts, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ชิ้นที่ขาย', data: qty, backgroundColor: pastel }] },
    options: { indexAxis: 'y', plugins: { legend: { labels: { color: '#444' } } } }
  });
})();

// Region
(() => {
  const {labels, values} = toXY(region, 'region', 'net_sales');
  new Chart(chartRegion, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values, backgroundColor: pastel }] },
    options: { plugins: { legend: { labels: { color: '#444' } } } }
  });
})();

// Payment
(() => {
  const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
  new Chart(chartPayment, {
    type: 'pie',
    data: { labels, datasets: [{ data: values, backgroundColor: pastel }] },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#444' } } } }
  });
})();

// Hourly
(() => {
  const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
  new Chart(chartHourly, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values, backgroundColor: '#a8edea' }] },
    options: { plugins: { legend: { labels: { color: '#444' } } } }
  });
})();

// New vs Returning
(() => {
  const labels = newReturning.map(o => o.date_key);
  const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
  const retC = newReturning.map(o => parseFloat(o.returning_sales));
  new Chart(chartNewReturning, {
    type: 'line',
    data: { labels,
      datasets: [
        { label: 'ลูกค้าใหม่ (฿)', data: newC, borderColor: '#ff8dc7', backgroundColor: 'rgba(255,182,193,0.2)', fill: true, tension: .3 },
        { label: 'ลูกค้าเดิม (฿)', data: retC, borderColor: '#8ac6d1', backgroundColor: 'rgba(138,198,209,0.2)', fill: true, tension: .3 }
      ]
    },
    options: { plugins: { legend: { labels: { color: '#444' } } } }
  });
})();
</script>
</body>
</html>
