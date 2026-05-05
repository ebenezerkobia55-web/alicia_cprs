<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Patient Profile';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) { echo '<div class="alert alert-danger">Invalid patient ID.</div>'; require_once __DIR__ . '/../includes/footer.php'; exit; }

$patient = $db->prepare("SELECT *, TIMESTAMPDIFF(YEAR, dob, CURDATE()) AS age_calc FROM patients WHERE id = ?");
$patient->execute([$id]);
$p = $patient->fetch();

if (!$p) { echo '<div class="alert alert-danger">Patient not found.</div>'; require_once __DIR__ . '/../includes/footer.php'; exit; }

$visits = $db->prepare(
    "SELECT v.*, u.full_name AS recorded_by_name FROM visits v
     LEFT JOIN users u ON u.id = v.recorded_by
     WHERE v.patient_id = ?
     ORDER BY v.attendance_date DESC"
);
$visits->execute([$id]);
$visitList  = $visits->fetchAll();
$visitCount = count($visitList);

$pageTitle = e($p['surname']) . ', ' . e($p['first_name']);
?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-avatar"><?= strtoupper(substr($p['first_name'], 0, 1)) ?></div>
    <div style="flex:1">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:6px">
            <h2><?= e($p['surname']) ?>, <?= e($p['first_name']) ?> <?= e($p['middle_name'] ?? '') ?></h2>
            <span class="badge badge-<?= strtolower(e($p['sex'])) ?>"><?= e($p['sex']) ?></span>
            <span class="reg-badge"><?= e($p['registration_no']) ?></span>
        </div>
        <div class="profile-meta">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                DOB: <?= date('F j, Y', strtotime($p['dob'])) ?>
            </span>
            <span>Age: <strong><?= $p['age_calc'] ?></strong></span>
            <?php if ($p['contact']): ?>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <?= e($p['contact']) ?>
            </span>
            <?php endif; ?>
            <?php if ($p['address']): ?>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= e($p['address']) ?>
            </span>
            <?php endif; ?>
            <span>Registered: <?= date('M j, Y', strtotime($p['created_at'])) ?></span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0" class="no-print">
        <a href="/alicia_cprs/pages/add_visit.php?patient_id=<?= $id ?>" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Visit
        </a>
        <a href="/alicia_cprs/pages/edit_patient.php?id=<?= $id ?>" class="btn btn-outline">Edit</a>
        <button onclick="printPage()" class="btn btn-ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        </button>
    </div>
</div>

<!-- Visit History -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Visit History
            <span style="font-weight:400;color:var(--gray-400);font-size:.82rem;margin-left:8px"><?= $visitCount ?> visit(s)</span>
        </span>
    </div>
    <div class="table-wrap">
        <?php if (empty($visitList)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                <p>No visits recorded yet.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date of Visit</th>
                    <th>Chief Complaint</th>
                    <th>Notes</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = $visitCount; foreach ($visitList as $v): ?>
                <tr>
                    <td style="color:var(--gray-400)"><?= $i-- ?></td>
                    <td><?= date('F j, Y', strtotime($v['attendance_date'])) ?></td>
                    <td><?= e($v['chief_complaint'] ?? '—') ?></td>
                    <td><?= e($v['notes'] ?? '—') ?></td>
                    <td><?= e($v['recorded_by_name'] ?? 'System') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
