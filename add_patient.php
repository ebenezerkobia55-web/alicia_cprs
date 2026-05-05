<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Register Patient';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$errors  = [];
$success = '';
$regNo   = generateRegistrationNo(); // Pre-generate for display

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $firstName  = trim($_POST['first_name']  ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $surname    = trim($_POST['surname']     ?? '');
    $sex        = $_POST['sex']              ?? '';
    $dob        = $_POST['dob']              ?? '';
    $contact    = trim($_POST['contact']     ?? '');
    $address    = trim($_POST['address']     ?? '');
    $complaint  = trim($_POST['chief_complaint'] ?? '');

    // Validate
    if ($firstName  === '') $errors[] = 'First name is required.';
    if ($surname    === '') $errors[] = 'Surname is required.';
    if (!in_array($sex, ['Male', 'Female'])) $errors[] = 'Please select sex.';
    if ($dob === '') $errors[] = 'Date of birth is required.';
    elseif (strtotime($dob) > time()) $errors[] = 'Date of birth cannot be in the future.';

    if (empty($errors)) {
        try {
            $regNo = generateRegistrationNo(); // Regenerate inside transaction
            $db->beginTransaction();

            $stmt = $db->prepare(
                "INSERT INTO patients (registration_no, first_name, middle_name, surname, sex, dob, contact, address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$regNo, $firstName, $middleName ?: null, $surname, $sex, $dob, $contact ?: null, $address ?: null]);
            $patientId = $db->lastInsertId();

            // Auto-create first visit record
            $vstmt = $db->prepare(
                "INSERT INTO visits (patient_id, attendance_date, chief_complaint, recorded_by)
                 VALUES (?, CURDATE(), ?, ?)"
            );
            $vstmt->execute([$patientId, $complaint ?: null, $_SESSION['user_id']]);

            $db->commit();
            $success = "Patient registered successfully! Registration No: <strong>{$regNo}</strong>";
            // Reset for next patient
            $regNo = generateRegistrationNo();
            $firstName = $middleName = $surname = $sex = $dob = $contact = $address = $complaint = '';
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?> — <a href="/alicia_cprs/pages/patients.php">View all patients</a></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Patient Registration Form</span>
        <span class="reg-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <?= e($regNo) ?>
        </span>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <!-- Hidden reg number -->
            <input type="hidden" name="reg_no" value="<?= e($regNo) ?>">

            <div style="margin-bottom:22px">
                <div style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--gray-400);margin-bottom:14px">Personal Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="req">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               value="<?= e($firstName ?? '') ?>" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name <span style="color:var(--gray-400);font-weight:400">(optional)</span></label>
                        <input type="text" id="middle_name" name="middle_name"
                               value="<?= e($middleName ?? '') ?>" placeholder="Enter middle name">
                    </div>
                    <div class="form-group">
                        <label for="surname">Surname <span class="req">*</span></label>
                        <input type="text" id="surname" name="surname"
                               value="<?= e($surname ?? '') ?>" placeholder="Enter surname" required>
                    </div>
                    <div class="form-group">
                        <label for="sex">Sex <span class="req">*</span></label>
                        <select id="sex" name="sex" required>
                            <option value="">— Select —</option>
                            <option value="Male"   <?= ($sex ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($sex ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dob">Date of Birth <span class="req">*</span></label>
                        <input type="date" id="dob" name="dob"
                               value="<?= e($dob ?? '') ?>"
                               max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="age">Age (Auto-calculated)</label>
                        <input type="number" id="age" name="age" readonly
                               placeholder="Will auto-fill from DOB"
                               value="<?= ($dob ?? '') ? calculateAge($dob) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number</label>
                        <input type="tel" id="contact" name="contact"
                               value="<?= e($contact ?? '') ?>" placeholder="e.g. 09171234567">
                    </div>
                    <div class="form-group full">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2"
                                  placeholder="Street, Barangay, City/Municipality, Province"><?= e($address ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div style="border-top:1px solid var(--gray-100);padding-top:20px;margin-bottom:20px">
                <div style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--gray-400);margin-bottom:14px">Visit Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date of Attendance</label>
                        <input type="text" value="<?= date('F j, Y') ?>" readonly>
                        <span class="form-hint">Automatically set to today.</span>
                    </div>
                    <div class="form-group">
                        <label>Registration Number</label>
                        <input type="text" value="<?= e($regNo) ?>" readonly>
                        <span class="form-hint">Auto-generated. Format: AHC-YYYY-NNNNN</span>
                    </div>
                    <div class="form-group full">
                        <label for="chief_complaint">Chief Complaint / Reason for Visit</label>
                        <textarea id="chief_complaint" name="chief_complaint" rows="2"
                                  placeholder="Patient's main complaint or reason for visit..."><?= e($complaint ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Register Patient
                </button>
                <a href="/alicia_cprs/pages/patients.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
