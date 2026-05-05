<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Visit Records';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$perPage  = 25;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$total = $db->prepare(
    "SELECT COUNT(*) FROM visits WHERE attendance_date BETWEEN ? AND ?"
);
$total->execute([$dateFrom, $dateTo]);
$totalCount = (int) $total->fetchColumn();

$stmt = $db->prepare(
    "SELECT v.*, p.registration_no, p.first_name, p.surname, p.sex,
            TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age_calc,
            u.full_name AS recorded_by_name
     FROM visits v
     JOIN patients p ON p.id = v.patient_id
     LEFT JOIN users u ON u.id = v.recorded_by
     WHERE v.attendance_date BETWEEN ? AND ?
     ORDER BY v.attendance_date DESC, v.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$dateFrom, $dateTo, $perPage, $offset]);
$visits = $stmt->fetchAll();
$totalPages = (int) ceil($totalCount / $perPage);
?>

<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" action="" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="margin:0">
                <label>From</label>
                <input type="date" name="from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label>To</label>
                <input type="date" name="to" value="<?= e($dateTo) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="/alicia_cprs/pages/visits.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            Visit Records
            <span style="font-weight:400;color:var(--gray-400);font-size:.82rem;margin-left:8px">
                <?= date('M j, Y', strtotime($dateFrom)) ?> – <?= date('M j, Y', strtotime($dateTo)) ?>
                &bull; <?= number_format($totalCount) ?> visit(s)
            </span>
        </span>
        <button onclick="printPage()" class="btn btn-outline btn-sm no-print">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
        </button>
    </div>
    <div class="table-wrap">
        <?php if (empty($visits)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                <p>No visits found for the selected date range.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reg No.</th>
                    <th>Patient Name</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>Date of Visit</th>
                    <th>Chief Complaint</th>
                    <th>Recorded By</th>
                    <th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
            <?php $i = ($page - 1) * $perPage + 1; foreach ($visits as $v): ?>
                <tr>
                    <td style="color:var(--gray-400)"><?= $i++ ?></td>
                    <td><span class="reg-badge"><?= e($v['registration_no']) ?></span></td>
                    <td><strong><?= e($v['surname']) ?></strong>, <?= e($v['first_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower(e($v['sex'])) ?>"><?= e($v['sex']) ?></span></td>
                    <td><?= e($v['age_calc']) ?></td>
                    <td><?= date('M j, Y', strtotime($v['attendance_date'])) ?></td>
                    <td><?= e($v['chief_complaint'] ?? '—') ?></td>
                    <td><?= e($v['recorded_by_name'] ?? 'System') ?></td>
                    <td class="no-print">
                        <a href="/alicia_cprs/pages/patient_profile.php?id=<?= $v['patient_id'] ?>"
                           class="btn btn-outline btn-sm">Profile</a>
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
            <a href="?from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?>&page=<?= $i ?>"
               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
