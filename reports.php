<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$reportType = $_GET['type']  ?? 'monthly';
$selYear    = (int)($_GET['year']  ?? date('Y'));
$selMonth   = (int)($_GET['month'] ?? date('n'));
$dateFrom   = $_GET['from']  ?? date('Y-m-01');
$dateTo     = $_GET['to']    ?? date('Y-m-d');

$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];

// ── Monthly Report ──────────────────────────────────────────
$monthlyData = [];
if ($reportType === 'monthly' || $reportType === 'yearly') {
    $sql = $reportType === 'yearly'
        ? "SELECT MONTH(attendance_date) AS m, COUNT(*) AS total FROM visits WHERE YEAR(attendance_date) = ? GROUP BY MONTH(attendance_date) ORDER BY m"
        : "SELECT DAY(attendance_date) AS m, COUNT(*) AS total FROM visits WHERE YEAR(attendance_date) = ? AND MONTH(attendance_date) = ? GROUP BY DAY(attendance_date) ORDER BY m";

    if ($reportType === 'yearly') {
        $s = $db->prepare($sql); $s->execute([$selYear]);
    } else {
        $s = $db->prepare($sql); $s->execute([$selYear, $selMonth]);
    }
    foreach ($s->fetchAll() as $row) $monthlyData[$row['m']] = $row['total'];
}

// ── Date Range Report ───────────────────────────────────────
$rangeData = [];
if ($reportType === 'range') {
    $s = $db->prepare(
        "SELECT v.attendance_date, p.registration_no, p.first_name, p.surname, p.sex,
                TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age
         FROM visits v
         JOIN patients p ON p.id = v.patient_id
         WHERE v.attendance_date BETWEEN ? AND ?
         ORDER BY v.attendance_date DESC"
    );
    $s->execute([$dateFrom, $dateTo]);
    $rangeData = $s->fetchAll();
}

// ── Summary totals ──────────────────────────────────────────
$totalAll     = $db->query("SELECT COUNT(*) FROM visits")->fetchColumn();
$totalMonth   = $db->prepare("SELECT COUNT(*) FROM visits WHERE MONTH(attendance_date)=? AND YEAR(attendance_date)=?");
$totalMonth->execute([$selMonth, $selYear]);
$monthTotal   = $totalMonth->fetchColumn();
?>

<!-- Report Type Selector -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" action="" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="margin:0">
                <label>Report Type</label>
                <select name="type" id="reportType" onchange="this.form.submit()">
                    <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Summary</option>
                    <option value="yearly"  <?= $reportType === 'yearly'  ? 'selected' : '' ?>>Yearly Overview</option>
                    <option value="range"   <?= $reportType === 'range'   ? 'selected' : '' ?>>Date Range</option>
                </select>
            </div>
            <?php if ($reportType === 'monthly'): ?>
                <div class="form-group" style="margin:0">
                    <label>Month</label>
                    <select name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === $selMonth ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if (in_array($reportType, ['monthly','yearly'])): ?>
                <div class="form-group" style="margin:0">
                    <label>Year</label>
                    <select name="year">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === $selYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($reportType === 'range'): ?>
                <div class="form-group" style="margin:0">
                    <label>From</label>
                    <input type="date" name="from" value="<?= e($dateFrom) ?>">
                </div>
                <div class="form-group" style="margin:0">
                    <label>To</label>
                    <input type="date" name="to" value="<?= e($dateTo) ?>">
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Generate</button>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($totalAll) ?></div>
            <div class="stat-label">Total All-Time Visits</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($monthTotal) ?></div>
            <div class="stat-label"><?= $monthNames[$selMonth] ?> <?= $selYear ?> Visits</div>
        </div>
    </div>
</div>

<!-- Report Output -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?php
            if ($reportType === 'monthly')     echo "Monthly Report — {$monthNames[$selMonth]} {$selYear}";
            elseif ($reportType === 'yearly')  echo "Yearly Overview — {$selYear}";
            else echo 'Date Range: ' . date('M j, Y', strtotime($dateFrom)) . ' to ' . date('M j, Y', strtotime($dateTo));
            ?>
        </span>
        <button onclick="printPage()" class="btn btn-outline btn-sm no-print">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Report
        </button>
    </div>

    <?php if ($reportType === 'yearly'): ?>
    <!-- Yearly chart + table -->
    <div class="card-body">
        <div class="chart-container" style="margin-bottom:20px">
            <canvas id="reportChart"></canvas>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Month</th><th>Total Visits</th><th>Share</th></tr></thead>
            <tbody>
            <?php $yearTotal = array_sum($monthlyData);
            for ($m = 1; $m <= 12; $m++):
                $cnt = $monthlyData[$m] ?? 0;
                $pct = $yearTotal > 0 ? round($cnt / $yearTotal * 100, 1) : 0;
            ?>
                <tr>
                    <td><?= $monthNames[$m] ?></td>
                    <td><strong><?= number_format($cnt) ?></strong></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="height:6px;width:<?= min($pct * 2, 100) ?>px;background:var(--primary);border-radius:3px;min-width:2px"></div>
                            <?= $pct ?>%
                        </div>
                    </td>
                </tr>
            <?php endfor; ?>
            <tr style="background:var(--gray-50);font-weight:600">
                <td>TOTAL</td><td><?= number_format($yearTotal) ?></td><td>100%</td>
            </tr>
            </tbody>
        </table>
    </div>
    <script>
    new Chart(document.getElementById('reportChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_values($monthNames)) ?>.slice(1),
            datasets: [{
                label: 'Visits',
                data: <?= json_encode(array_map(fn($m) => $monthlyData[$m] ?? 0, range(1,12))) ?>,
                backgroundColor: 'rgba(13,110,138,.15)',
                borderColor: 'rgba(13,110,138,1)',
                borderWidth: 2, borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }
        }
    });
    </script>

    <?php elseif ($reportType === 'monthly'): ?>
    <!-- Daily breakdown of selected month -->
    <div class="table-wrap">
        <table>
            <thead><tr><th>Day</th><th>Date</th><th>Total Visits</th></tr></thead>
            <tbody>
            <?php
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selMonth, $selYear);
            $monthSum = 0;
            for ($d = 1; $d <= $daysInMonth; $d++):
                $cnt = $monthlyData[$d] ?? 0;
                $monthSum += $cnt;
                $dateStr = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d);
            ?>
                <tr <?= $cnt === 0 ? 'style="color:var(--gray-400)"' : '' ?>>
                    <td><?= $d ?></td>
                    <td><?= date('l, M j', strtotime($dateStr)) ?></td>
                    <td><?= $cnt > 0 ? "<strong>{$cnt}</strong>" : '—' ?></td>
                </tr>
            <?php endfor; ?>
            <tr style="background:var(--gray-50);font-weight:600">
                <td colspan="2">TOTAL</td><td><?= number_format($monthSum) ?></td>
            </tr>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <!-- Date range detail -->
    <div class="table-wrap">
        <?php if (empty($rangeData)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <p>No visits found for this date range.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Reg No.</th><th>Patient</th><th>Sex</th><th>Age</th><th>Date of Visit</th></tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($rangeData as $r): ?>
                <tr>
                    <td style="color:var(--gray-400)"><?= $i++ ?></td>
                    <td><span class="reg-badge"><?= e($r['registration_no']) ?></span></td>
                    <td><?= e($r['surname']) ?>, <?= e($r['first_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower(e($r['sex'])) ?>"><?= e($r['sex']) ?></span></td>
                    <td><?= $r['age'] ?></td>
                    <td><?= date('M j, Y', strtotime($r['attendance_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="background:var(--gray-50);font-weight:600">
                <td colspan="5">TOTAL VISITS</td>
                <td><?= number_format(count($rangeData)) ?></td>
            </tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
