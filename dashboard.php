<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// ── Stats ──────────────────────────────────────────────────
$totalPatients = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalVisits   = $db->query("SELECT COUNT(*) FROM visits")->fetchColumn();
$todayVisits   = $db->query("SELECT COUNT(*) FROM visits WHERE attendance_date = CURDATE()")->fetchColumn();
$monthVisits   = $db->query("SELECT COUNT(*) FROM visits WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE())")->fetchColumn();

// ── Monthly chart data (current year) ──────────────────────
$chartStmt = $db->query(
    "SELECT MONTH(attendance_date) AS m, COUNT(*) AS total
     FROM visits
     WHERE YEAR(attendance_date) = YEAR(CURDATE())
     GROUP BY MONTH(attendance_date)
     ORDER BY m"
);
$monthData = array_fill(1, 12, 0);
foreach ($chartStmt->fetchAll() as $row) $monthData[$row['m']] = (int)$row['total'];

// ── Recent visits ───────────────────────────────────────────
$recentVisits = $db->query(
    "SELECT v.id, v.attendance_date, v.chief_complaint,
            p.registration_no, p.first_name, p.surname, p.sex
     FROM visits v
     JOIN patients p ON p.id = v.patient_id
     ORDER BY v.created_at DESC LIMIT 10"
)->fetchAll();

// ── Sex distribution ────────────────────────────────────────
$sexData = $db->query(
    "SELECT sex, COUNT(*) AS total FROM patients GROUP BY sex"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$maleCount   = $sexData['Male']   ?? 0;
$femaleCount = $sexData['Female'] ?? 0;
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($totalPatients) ?></div>
            <div class="stat-label">Total Patients</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($todayVisits) ?></div>
            <div class="stat-label">Today's Visits</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($monthVisits) ?></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($totalVisits) ?></div>
            <div class="stat-label">Total Visits</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Monthly Chart -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Monthly Visits — <?= date('Y') ?></span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Sex Distribution -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Patient Distribution</span>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:200px">
                <canvas id="sexChart"></canvas>
            </div>
            <div style="margin-top:16px;display:flex;gap:16px;justify-content:center">
                <span style="font-size:.8rem;color:var(--gray-500)">
                    <span style="color:#1a56db;font-weight:700"><?= number_format($maleCount) ?></span> Male
                </span>
                <span style="font-size:.8rem;color:var(--gray-500)">
                    <span style="color:#9333ea;font-weight:700"><?= number_format($femaleCount) ?></span> Female
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Visits -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Visits</span>
        <a href="/alicia_cprs/pages/visits.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrap">
        <?php if (empty($recentVisits)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                <p>No visit records yet.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Reg No.</th>
                    <th>Patient Name</th>
                    <th>Sex</th>
                    <th>Date</th>
                    <th>Chief Complaint</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentVisits as $v): ?>
                <tr>
                    <td><span class="reg-badge"><?= e($v['registration_no']) ?></span></td>
                    <td><strong><?= e($v['surname']) ?></strong>, <?= e($v['first_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower(e($v['sex'])) ?>"><?= e($v['sex']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($v['attendance_date'])) ?></td>
                    <td><?= e($v['chief_complaint'] ?? '—') ?></td>
                    <td>
                        <a href="/alicia_cprs/pages/patient_profile.php?id=<?= $v['patient_id'] ?? '' ?>"
                           class="btn btn-outline btn-sm">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
// Monthly Bar Chart
const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const monthValues = <?= json_encode(array_values($monthData)) ?>;

new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [{
            label: 'Visits',
            data: monthValues,
            backgroundColor: 'rgba(13,110,138,0.15)',
            borderColor: 'rgba(13,110,138,1)',
            borderWidth: 2,
            borderRadius: 6,
            hoverBackgroundColor: 'rgba(13,110,138,0.3)',
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { precision: 0 } },
            x: { grid: { display: false } }
        }
    }
});

// Doughnut sex chart
new Chart(document.getElementById('sexChart'), {
    type: 'doughnut',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?= $maleCount ?>, <?= $femaleCount ?>],
            backgroundColor: ['#1a56db', '#9333ea'],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } },
        cutout: '65%'
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
