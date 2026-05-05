<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Edit Patient';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$id      = (int)($_GET['id'] ?? 0);
$success = '';
$errors  = [];

$stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) { echo '<div class="alert alert-danger">Patient not found.</div>'; require_once __DIR__ . '/../includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName  = trim($_POST['first_name']  ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $surname    = trim($_POST['surname']     ?? '');
    $sex        = $_POST['sex']              ?? '';
    $dob        = $_POST['dob']              ?? '';
    $contact    = trim($_POST['contact']     ?? '');
    $address    = trim($_POST['address']     ?? '');

    if ($firstName === '') $errors[] = 'First name is required.';
    if ($surname   === '') $errors[] = 'Surname is required.';
    if (!in_array($sex, ['Male', 'Female'])) $errors[] = 'Please select sex.';
    if ($dob === '') $errors[] = 'Date of birth is required.';

    if (empty($errors)) {
        $upd = $db->prepare(
            "UPDATE patients SET first_name=?,middle_name=?,surname=?,sex=?,dob=?,contact=?,address=? WHERE id=?"
        );
        $upd->execute([$firstName, $middleName ?: null, $surname, $sex, $dob, $contact ?: null, $address ?: null, $id]);
        $success = 'Patient record updated successfully.';
        // Reload
        $stmt->execute([$id]); $p = $stmt->fetch();
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Edit Patient — <span class="reg-badge"><?= e($p['registration_no']) ?></span></span>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name">First Name <span class="req">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($p['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?= e($p['middle_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="surname">Surname <span class="req">*</span></label>
                    <input type="text" id="surname" name="surname" value="<?= e($p['surname']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="sex">Sex <span class="req">*</span></label>
                    <select id="sex" name="sex" required>
                        <option value="Male"   <?= $p['sex'] === 'Male'   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $p['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dob">Date of Birth <span class="req">*</span></label>
                    <input type="date" id="dob" name="dob" value="<?= e($p['dob']) ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" readonly value="<?= calculateAge($p['dob']) ?>">
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="tel" id="contact" name="contact" value="<?= e($p['contact'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?= e($p['address'] ?? '') ?></textarea>
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:8px">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/alicia_cprs/pages/patient_profile.php?id=<?= $id ?>" class="btn btn-outline">Back to Profile</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
