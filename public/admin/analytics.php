<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$flash = getFlashMessage();

// Get filters
$period = $_GET['period'] ?? 'month';
$statusFilter = $_GET['status'] ?? 'all';
$serviceFilter = $_GET['service'] ?? 'all';
$month = $_GET['month'] ?? date('Y-m');

$servicesList = getServices($pdo);

// Helper: linear regression prediction for next 3 months
function predictNextMonths($data, $months = 3) {
    $n = count($data);
    if ($n < 2) return array_fill(0, $months, 0);
    $x = range(0, $n - 1);
    $sumX = array_sum($x);
    $sumY = array_sum($data);
    $sumXY = 0;
    $sumX2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $sumXY += $x[$i] * $data[$i];
        $sumX2 += $x[$i] * $x[$i];
    }
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    $predictions = [];
    for ($i = 1; $i <= $months; $i++) {
        $predictions[] = round(max(0, $intercept + $slope * ($n + $i - 1)));
    }
    return $predictions;
}

// Build date condition
$dateCondition = '';
if ($period === 'day') $dateCondition = "appointment_date = CURDATE()";
elseif ($period === 'week') $dateCondition = "appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
elseif ($period === 'month') $dateCondition = "DATE_FORMAT(appointment_date, '%Y-%m') = '$month'";

$statusCondition = $statusFilter !== 'all' ? "AND status = '$statusFilter'" : "";
$serviceCondition = $serviceFilter !== 'all' ? "AND service_id = " . (int)$serviceFilter : "";

// 1. Stats cards
$stmt = $pdo->query("SELECT status, COUNT(*) as total FROM appointments WHERE 1=1 " . ($dateCondition ? "AND $dateCondition" : "") . " $statusCondition $serviceCondition GROUP BY status");
$stats = ['pending'=>0,'approved'=>0,'completed'=>0,'rejected'=>0,'no_show'=>0];
while ($row = $stmt->fetch()) $stats[$row['status']] = (int)$row['total'];

// 2. Top services (by selected period, exclude rejected/no_show)
$topServicesStmt = $pdo->query("SELECT s.name, COUNT(a.id) as total FROM services s LEFT JOIN appointments a ON s.id = a.service_id WHERE a.status NOT IN ('rejected','no_show') " . ($dateCondition ? "AND $dateCondition" : "") . " GROUP BY s.id ORDER BY total DESC LIMIT 5");
$topServices = $topServicesStmt->fetchAll();

// 3. Monthly historical data for the last 12 months (actual)
$histData = [];
$histLabels = [];
for ($i = 11; $i >= 0; $i--) {
    $histMonth = date('Y-m', strtotime("-$i months"));
    $histLabels[] = date('M Y', strtotime($histMonth));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = ? AND status NOT IN ('rejected','no_show') " . ($serviceFilter !== 'all' ? "AND service_id = ?" : ""));
    if ($serviceFilter !== 'all') {
        $stmt->execute([$histMonth, $serviceFilter]);
    } else {
        $stmt->execute([$histMonth]);
    }
    $histData[] = (int)$stmt->fetchColumn();
}

// 4. Prediction for next 3 months (based on last 12 months)
$predictions = predictNextMonths($histData, 3);
$futureLabels = [];
for ($i = 1; $i <= 3; $i++) {
    $futureLabels[] = date('M Y', strtotime("+$i months"));
}

// 5. Service demand prediction: which services are trending up?
$serviceTrend = [];
$allServices = getServices($pdo);
foreach ($allServices as $svc) {
    $last3 = 0;
    $prev3 = 0;
    for ($i = 0; $i < 3; $i++) {
        $month = date('Y-m', strtotime("-$i months"));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ? AND DATE_FORMAT(appointment_date, '%Y-%m') = ? AND status NOT IN ('rejected','no_show')");
        $stmt->execute([$svc['id'], $month]);
        $last3 += (int)$stmt->fetchColumn();
    }
    for ($i = 3; $i < 6; $i++) {
        $month = date('Y-m', strtotime("-$i months"));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ? AND DATE_FORMAT(appointment_date, '%Y-%m') = ? AND status NOT IN ('rejected','no_show')");
        $stmt->execute([$svc['id'], $month]);
        $prev3 += (int)$stmt->fetchColumn();
    }
    $change = $prev3 > 0 ? round((($last3 - $prev3) / $prev3) * 100, 1) : ($last3 > 0 ? 100 : 0);
    $serviceTrend[] = ['name' => $svc['name'], 'trend' => $change];
}
usort($serviceTrend, fn($a, $b) => $b['trend'] - $a['trend']);
$topTrending = array_slice($serviceTrend, 0, 5);

// 6. Peak days & times (global, ignoring filters for simplicity)
$peakDays = $pdo->query("SELECT DAYNAME(appointment_date) as day, COUNT(*) as total FROM appointments WHERE status NOT IN ('rejected','no_show') GROUP BY day ORDER BY total DESC")->fetchAll();
$peakTimes = $pdo->query("SELECT appointment_time, COUNT(*) as total FROM appointments WHERE status NOT IN ('rejected','no_show') GROUP BY appointment_time ORDER BY total DESC LIMIT 5")->fetchAll();

// 7. Month-over-month comparison (current vs previous month, respecting service filter)
$currentMonth = date('Y-m');
$prevMonth = date('Y-m', strtotime('-1 month'));
$compStmt = $pdo->prepare("SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as total FROM appointments WHERE appointment_date >= ? AND status NOT IN ('rejected','no_show') " . ($serviceFilter !== 'all' ? "AND service_id = ?" : "") . " GROUP BY month");
if ($serviceFilter !== 'all') {
    $compStmt->execute([date('Y-m-d', strtotime('-1 month')), $serviceFilter]);
} else {
    $compStmt->execute([date('Y-m-d', strtotime('-1 month'))]);
}
$compData = [];
while ($row = $compStmt->fetch()) $compData[$row['month']] = (int)$row['total'];
$currentCount = $compData[$currentMonth] ?? 0;
$prevCount = $compData[$prevMonth] ?? 0;
$percentChange = $prevCount > 0 ? round((($currentCount - $prevCount) / $prevCount) * 100, 1) : ($currentCount > 0 ? 100 : 0);
$trend = $currentCount > $prevCount ? 'Increase' : ($currentCount < $prevCount ? 'Decrease' : 'No change');

// 8. NEW: Service distribution pie chart data (current period, exclude rejected/no_show)
$servicesPie = [];
$stmt = $pdo->query("SELECT s.name, COUNT(a.id) as bookings FROM services s LEFT JOIN appointments a ON s.id = a.service_id WHERE a.status NOT IN ('rejected','no_show') " . ($dateCondition ? "AND $dateCondition" : "") . " GROUP BY s.id ORDER BY bookings DESC LIMIT 8");
while ($row = $stmt->fetch()) {
    if ($row['bookings'] > 0) {
        $servicesPie[] = ['name' => $row['name'], 'bookings' => (int)$row['bookings']];
    }
}
$pieLabels = array_column($servicesPie, 'name');
$pieData = array_column($servicesPie, 'bookings');
$totalPie = array_sum($pieData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include '../../includes/public/partials/nav-admin.php'; ?>

<div class="admin-container admin-layout">
    <main>
        <?php if ($flash): ?>
            <div class="<?php echo e($flash['type']); ?>-message"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="filter-form">
            <select name="period" id="period">
                <option value="day" <?php echo $period === 'day' ? 'selected' : ''; ?>>Last 24h</option>
                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 days</option>
                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This month</option>
            </select>
            <select name="status" id="statusFilter">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="no_show" <?php echo $statusFilter === 'no_show' ? 'selected' : ''; ?>>No‑Show</option>
            </select>
            <select name="service" id="serviceFilter">
                <option value="all" <?php echo $serviceFilter === 'all' ? 'selected' : ''; ?>>All services</option>
                <?php foreach ($servicesList as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $serviceFilter == $s['id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="month" id="monthPicker" value="<?php echo e($month); ?>">
            <button id="applyFilters">Apply</button>
            <a href="analytics.php" class="reset">Reset</a>
        </div>

        <div class="stats">
            <div class="stat-card"><h3>Pending</h3><p><?php echo $stats['pending']; ?></p></div>
            <div class="stat-card"><h3>Approved</h3><p><?php echo $stats['approved']; ?></p></div>
            <div class="stat-card"><h3>Completed</h3><p><?php echo $stats['completed']; ?></p></div>
            <div class="stat-card"><h3>Rejected</h3><p><?php echo $stats['rejected']; ?></p></div>
            <div class="stat-card"><h3>No‑Show</h3><p><?php echo $stats['no_show']; ?></p></div>
        </div>

        <div class="analytics-grid">
            <div class="card">
                <h3><i class="fas fa-chart-line"></i> Top Services (<?php echo $period === 'day' ? 'today' : ($period === 'week' ? 'last 7 days' : ($period === 'month' ? 'this month' : '')); ?>)</h3>
                <ul>
                    <?php foreach ($topServices as $s): ?>
                        <li><span><?php echo e($s['name']); ?></span><span><?php echo e($s['total']); ?> bookings</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card">
                <h3><i class="fas fa-chart-simple"></i> Trending Services (Last 3 months)</h3>
                <ul>
                    <?php foreach ($topTrending as $t): ?>
                        <li><span><?php echo e($t['name']); ?></span><span class="<?php echo $t['trend'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $t['trend'] >= 0 ? '+' : ''; ?><?php echo $t['trend']; ?>%</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card">
                <h3><i class="fas fa-calendar-alt"></i> Month-over-Month</h3>
                <p>Current: <strong><?php echo $currentCount; ?></strong></p>
                <p>Previous: <strong><?php echo $prevCount; ?></strong></p>
                <p>Change: <span class="<?php echo $percentChange >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $percentChange >= 0 ? '+' : ''; ?><?php echo $percentChange; ?>% (<?php echo $trend; ?>)</span></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-calendar-week"></i> Peak Day / Time</h3>
                <p><strong><?php echo $peakDays[0]['day'] ?? 'N/A'; ?></strong> – <?php echo $peakDays[0]['total'] ?? 0; ?> bookings</p>
                <p><strong><?php echo isset($peakTimes[0]) ? formatAppointmentTime($peakTimes[0]['appointment_time']) : 'N/A'; ?></strong> – <?php echo $peakTimes[0]['total'] ?? 0; ?> bookings</p>
            </div>
        </div>

        <!-- Historical + Prediction Chart -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-line"></i> Monthly Appointments & Prediction (<?php echo date('Y'); ?>)</h3>
            <canvas id="trendChart" style="max-height: 400px;"></canvas>
            <p class="text-sm text-gray-500 mt-2">Prediction based on linear trend of last 12 months. Dashed line = forecast.</p>
        </div>

        <!-- NEW: Service Distribution Pie Chart - FIXED: Always show, "No data" if empty -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-pie"></i> Service Distribution (<?php echo ucfirst(str_replace(['day','week','month'], ['Day', 'Week', 'Month'], $period)); ?>)</h3>
            <?php if (!empty($servicesPie)): ?>
            <canvas id="pieChart" style="max-height: 400px;"></canvas>
            <div class="text-center mt-2">
                <p class="text-sm text-gray-500">Total: <strong><?php echo $totalPie; ?></strong> bookings across <strong><?php echo count($servicesPie); ?></strong> services</p>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-chart-pie text-4xl text-gray-300 mb-4"></i>
                <p class="text-lg text-gray-500 mb-2">No service bookings for this period</p>
                <p class="text-sm text-gray-400"><?php echo $period; ?> filter returned 0 bookings (excl rejected/no_show)</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>



<script>
// Combine historical and predicted data
const histLabels = <?php echo json_encode($histLabels); ?>;
const histData = <?php echo json_encode($histData); ?>;
const futureLabels = <?php echo json_encode($futureLabels); ?>;
const predictions = <?php echo json_encode($predictions); ?>;

const allLabels = [...histLabels, ...futureLabels];
const allData = [...histData, ...predictions];

// Create dataset with dashed line for predictions
const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: allLabels,
        datasets: [
            {
                label: 'Actual Appointments',
                data: [...histData, ...Array(predictions.length).fill(null)],
                borderColor: '#1f816a',
                backgroundColor: 'rgba(31,129,106,0.1)',
                fill: false,
                tension: 0.3,
                pointBackgroundColor: '#1f816a',
                pointRadius: 4
            },
            {
                label: 'Predicted Appointments',
                data: [...Array(histData.length).fill(null), ...predictions],
                borderColor: '#f59e0b',
                borderDash: [5, 5],
                backgroundColor: 'transparent',
                fill: false,
                tension: 0.3,
                pointBackgroundColor: '#f59e0b',
                pointRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            tooltip: { mode: 'index', intersect: false },
            legend: { position: 'top' }
        },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Number of Appointments' } },
            x: { title: { display: true, text: 'Month' } }
        }
    }
});

// Apply filters
document.getElementById('applyFilters').addEventListener('click', function() {
    const period = document.getElementById('period').value;
    const status = document.getElementById('statusFilter').value;
    const service = document.getElementById('serviceFilter').value;
    const month = document.getElementById('monthPicker').value;
    let url = `analytics.php?period=${period}&status=${status}&service=${service}`;
    if (period === 'month' && month) url += `&month=${month}`;
    window.location.href = url;
});

// NEW: Service Pie Chart
<?php if (!empty($servicesPie)): ?>
const pieCtx = document.getElementById('pieChart')?.getContext('2d');
if (pieCtx) {
    new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($pieLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($pieData); ?>,
                backgroundColor: ['#1f816a', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#f97316', '#06b6d4'],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<style>
.text-success { color: #10b981; font-weight: 600; }
.text-danger { color: #ef4444; font-weight: 600; }
.text-sm { font-size: 0.75rem; }
.text-gray-500 { color: #6b7280; }
.mt-2 { margin-top: 0.5rem; }
</style>
</body>
</html>
