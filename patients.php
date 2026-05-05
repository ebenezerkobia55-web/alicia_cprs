<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Patients';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Search
$search = trim($_GET['q'] ?? '');
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

if ($search !== '') {
    $like = '%' . $search . '%';
    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM patients
         WHERE first_name LIKE ? OR middle_name LIKE ? OR surname LIKE ? OR contact LIKE ? OR registration_no LIKE ?"
    );
    $countStmt->execute([$like,$like,$like,$like,$like]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT p.*, TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age_calc,
                (SELECT MAX(v.attendance_date) FROM visits v WHERE v.patient_id = p.id) AS last_visit
         FROM patients p
         WHERE first_name LIKE ? OR middle_name LIKE ? OR surname LIKE ? OR contact LIKE ? OR registration_no LIKE ?
         ORDER BY p.surname, p.first_name
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$like,$like,$like,$like,$like,$perPage,$offset]);
} else {
    $total = (int) $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $stmt  = $db->prepare(
        "SELECT p.*, TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age_calc,
                (SELECT MAX(v.attendance_date) FROM visits v WHERE v.patient_id = p.id) AS last_visit
         FROM patients p
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$perPage, $offset]);
}
$patients  = $stmt->fetchAll();
$totalPages = (int) ceil($total / $perPage);
?>

<div class="search-bar no-print">
    <form method="GET" action="" style="display:flex;gap:10px;flex:1;align-items:center">
        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
                   placeholder="Search by name, contact, or registration no…">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="/alicia_cprs/pages/patients.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>
    <a href="/alicia_cprs/pages/add_patient.php" class="btn btn-accent">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Register New
    </a>
</div>

<?php if ($search): ?>
    <div class="alert alert-info">
        Found <strong><?= number_format($total) ?></strong> result(s) for "<strong><?= e($search) ?></strong>"
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?= $search ? 'Search Results' : 'All Patients' ?>
            <span style="font-weight:400;color:var(--gray-400);font-size:.82rem;margin-left:8px"><?= number_format($total) ?> records</span>
        </span>
        <button onclick="printPage()" class="btn btn-outline btn-sm no-print">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
        </button>
    </div>
    <div class="table-wrap">
        <?php if (empty($patients)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <p>No patients found<?= $search ? ' for that search' : '' ?>.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Reg No.</th>
                    <th>Full Name</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>DOB</th>
                    <th>Contact</th>
                    <th>Last Visit</th>
                    <th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $p): ?>
                <tr>
                    <td><span class="reg-badge"><?= e($p['registration_no']) ?></span></td>
                    <td>
                        <strong><?= e($p['surname']) ?></strong>, <?= e($p['first_name']) ?>
                        <?php if ($p['middle_name']): ?>
                            <?= e(substr($p['middle_name'], 0, 1)) ?>.
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= strtolower(e($p['sex'])) ?>"><?= e($p['sex']) ?></span></td>
                    <td><?= e($p['age_calc']) ?></td>
                    <td><?= date('M j, Y', strtotime($p['dob'])) ?></td>
                    <td><?= e($p['contact'] ?? '—') ?></td>
                    <td><?= $p['last_visit'] ? date('M j, Y', strtotime($p['last_visit'])) : '<span style="color:var(--gray-400)">—</span>' ?></td>
                    <td class="no-print" style="white-space:nowrap">
                        <a href="/alicia_cprs/pages/patient_profile.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">View</a>
                        <a href="/alicia_cprs/pages/add_visit.php?patient_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">+ Visit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination no-print">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?q=<?= urlencode($search) ?>&page=<?= $i ?>"
               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
