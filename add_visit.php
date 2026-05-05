<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Add Visit Record';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$patientId = (int)($_GET['patient_id'] ?? 0);
$success = '';
$error   = '';

// Load patient
$patientStmt = $db->prepare("SELECT *, TIMESTAMPDIFF(YEAR, dob, CURDATE()) AS age_calc FROM patients WHERE id = ?");
$patientStmt->execute([$patientId]);
$p = $patientStmt->fetch();

if (!$p) {
    echo '<div class="alert alert-danger">Patient not found. <a href="/alicia_cprs/pages/patients.php">Go back</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaint = trim($_POST['chief_complaint'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    $stmt = $db->prepare(
        "INSERT INTO visits (patient_id, attendance_date, chief_complaint, notes, recorded_by)
         VALUES (?, CURDATE(), ?, ?, ?)"
    );
    $stmt->execute([$patientId, $complaint ?: null, $notes ?: null, $_SESSION['user_id']]);
    $success = 'Visit recorded successfully for ' . $p['first_name'] . ' ' . $p['surname'] . '.';
}
?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= e($success) ?> — 
        <a href="/alicia_cprs/pages/patient_profile.php?id=<?= $patientId ?>">View patient profile</a> |
        <a href="/alicia_cprs/pages/patients.php">Patient list</a>
    </div>
<?php endif; ?>

<!-- Patient Summary -->
<div class="profile-header" style="margin-bottom:20px">
    <div class="profile-avatar"><?= strtoupper(substr($p['first_name'], 0, 1)) ?></div>
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <strong><?= e($p['surname']) ?>, <?= e($p['first_name']) ?></strong>
            <span class="reg-badge"><?= e($p['registration_no']) ?></span>
        </div>
        <div style="font-size:.82rem;color:var(--gray-500)">
            <?= e($p['sex']) ?> &bull; Age <?= $p['age_calc'] ?> &bull; <?= e($p['contact'] ?? '—') ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">New Visit Record — <?= date('F j, Y') ?></span>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label>Date of Attendance</label>
                    <input type="text" value="<?= date('F j, Y') ?>" readonly>
                </div>
                <div class="form-group full">
                    <label for="chief_complaint">Chief Complaint / Reason for Visit</label>
                    <textarea id="chief_complaint" name="chief_complaint" rows="3"
                              placeholder="Describe the patient's main complaint…"></textarea>
                </div>
                <div class="form-group full">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes" rows="2"
                              placeholder="Optional additional notes…"></textarea>
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:8px">
                <button type="submit" class="btn btn-primary">Save Visit Record</button>
                <a href="/alicia_cprs/pages/patient_profile.php?id=<?= $patientId ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
