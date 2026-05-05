<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Admin Panel';
require_once __DIR__ . '/../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">⛔ Access denied. Admins only.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$db      = getDB();
$success = '';
$errors  = [];
$tab     = $_GET['tab'] ?? 'users';

// ══════════════════════════════════════════════
// ACTIONS
// ══════════════════════════════════════════════

// ── Delete Patient ──────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_patient') {
    $pid = (int)($_POST['patient_id'] ?? 0);
    if ($pid > 0) {
        $pat = $db->prepare("SELECT registration_no, first_name, surname FROM patients WHERE id = ?");
        $pat->execute([$pid]);
        $patData = $pat->fetch();
        if ($patData) {
            // Delete visits first
            $db->prepare("DELETE FROM visits WHERE patient_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM patients WHERE id = ?")->execute([$pid]);
            auditLog('DELETE_PATIENT', 'patient', $pid,
                "Deleted: {$patData['registration_no']} — {$patData['surname']}, {$patData['first_name']}");
            $success = "Patient <strong>{$patData['registration_no']} — {$patData['surname']}, {$patData['first_name']}</strong> and all their visit records have been permanently deleted.";
        } else {
            $errors[] = 'Patient not found.';
        }
    }
    $tab = 'patients';
}

// ── Delete Visit Record ─────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_visit') {
    $vid = (int)($_POST['visit_id'] ?? 0);
    if ($vid > 0) {
        $vis = $db->prepare("SELECT v.attendance_date, p.registration_no, p.surname FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id = ?");
        $vis->execute([$vid]);
        $visData = $vis->fetch();
        if ($visData) {
            $db->prepare("DELETE FROM visits WHERE id = ?")->execute([$vid]);
            auditLog('DELETE_VISIT', 'visit', $vid,
                "Deleted visit #{$vid} for {$visData['registration_no']} — {$visData['surname']} on {$visData['attendance_date']}");
            $success = "Visit record #<strong>{$vid}</strong> for <strong>{$visData['registration_no']}</strong> on <strong>" . date('M j, Y', strtotime($visData['attendance_date'])) . "</strong> has been deleted.";
        } else {
            $errors[] = 'Visit record not found.';
        }
    }
    $tab = 'visits';
}

// ── Change User Role ────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'change_role') {
    $uid     = (int)($_POST['user_id'] ?? 0);
    $newRole = $_POST['new_role'] ?? '';
    if ($uid === (int)$_SESSION['user_id']) {
        $errors[] = 'You cannot change your own role.';
    } elseif (!in_array($newRole, ['admin', 'nurse', 'staff'])) {
        $errors[] = 'Invalid role selected.';
    } else {
        $old = $db->prepare("SELECT username, role FROM users WHERE id = ?");
        $old->execute([$uid]);
        $oldData = $old->fetch();
        $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $uid]);
        auditLog('CHANGE_ROLE', 'user', $uid,
            "Changed {$oldData['username']} role from {$oldData['role']} to {$newRole}");
        $success = "Role for <strong>{$oldData['username']}</strong> changed from <strong>{$oldData['role']}</strong> to <strong>{$newRole}</strong>.";
    }
    $tab = 'users';
}

// ── Reset User Password ─────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $uid     = (int)($_POST['user_id'] ?? 0);
    $newPass = $_POST['new_password'] ?? '';
    $confPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($newPass !== $confPass) {
        $errors[] = 'Passwords do not match.';
    } else {
        $udata = $db->prepare("SELECT username FROM users WHERE id = ?");
        $udata->execute([$uid]);
        $urow = $udata->fetch();
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($newPass, PASSWORD_BCRYPT), $uid]);
        auditLog('RESET_PASSWORD', 'user', $uid, "Password reset for user: {$urow['username']}");
        $success = "Password for <strong>{$urow['username']}</strong> has been reset successfully.";
    }
    $tab = 'users';
}

// ── Add New User ────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $uname    = trim($_POST['username']  ?? '');
    $fullname = trim($_POST['full_name'] ?? '');
    $role     = $_POST['role']           ?? 'nurse';
    $pass     = $_POST['password']       ?? '';
    $passConf = $_POST['password_confirm'] ?? '';

    if ($uname === '' || $fullname === '') $errors[] = 'Username and full name are required.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $passConf) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['admin','nurse','staff'])) $errors[] = 'Invalid role.';

    if (empty($errors)) {
        try {
            $s = $db->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)");
            $s->execute([$uname, password_hash($pass, PASSWORD_BCRYPT), $fullname, $role]);
            $newId = $db->lastInsertId();
            auditLog('ADD_USER', 'user', (int)$newId, "Created user: {$uname} with role: {$role}");
            $success = "User <strong>{$uname}</strong> created successfully with role: <strong>{$role}</strong>.";
        } catch (PDOException $e) {
            $errors[] = 'Username already exists. Please choose a different username.';
        }
    }
    $tab = 'users';
}

// ── Delete User ─────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid === (int)$_SESSION['user_id']) {
        $errors[] = 'You cannot delete your own account.';
    } else {
        $udata = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
        $udata->execute([$uid]);
        $urow = $udata->fetch();
        if ($urow) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            auditLog('DELETE_USER', 'user', $uid, "Deleted user: {$urow['username']} ({$urow['full_name']})");
            $success = "User <strong>{$urow['username']}</strong> has been deleted.";
        }
    }
    $tab = 'users';
}

// ── Archive Audit Logs ──────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'archive_audit_logs') {
    $years = (int)($_POST['years_old'] ?? 2);
    if ($years < 1 || $years > 10) {
        $errors[] = 'Years must be between 1 and 10.';
    } else {
        $archivedCount = archiveAuditLogs($years);
        if ($archivedCount > 0) {
            $success = "Successfully archived <strong>{$archivedCount}</strong> audit log entries older than <strong>{$years} years</strong>.";
        } else {
            $success = "No audit log entries older than <strong>{$years} years</strong> were found to archive.";
        }
    }
    $tab = 'audit';
}

// ══════════════════════════════════════════════
// DATA FETCHING
// ══════════════════════════════════════════════

$users = $db->query(
    "SELECT id, username, full_name, role, created_at FROM users ORDER BY role, full_name"
)->fetchAll();

// Patient search for delete
$patSearch  = trim($_GET['pat_q'] ?? '');
$patients   = [];
if ($tab === 'patients') {
    if ($patSearch !== '') {
        $like = '%' . $patSearch . '%';
        $ps = $db->prepare(
            "SELECT p.id, p.registration_no, p.first_name, p.middle_name, p.surname, p.sex,
                    TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age,
                    (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) AS visit_count
             FROM patients p
             WHERE p.first_name LIKE ? OR p.surname LIKE ? OR p.registration_no LIKE ? OR p.contact LIKE ?
             ORDER BY p.surname LIMIT 50"
        );
        $ps->execute([$like,$like,$like,$like]);
    } else {
        $ps = $db->query(
            "SELECT p.id, p.registration_no, p.first_name, p.middle_name, p.surname, p.sex,
                    TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age,
                    (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) AS visit_count
             FROM patients p ORDER BY p.created_at DESC LIMIT 50"
        );
    }
    $patients = $ps->fetchAll();
}

// Visit search for delete
$visSearch = trim($_GET['vis_q'] ?? '');
$visDate   = $_GET['vis_date'] ?? '';
$visits    = [];
if ($tab === 'visits') {
    $sql = "SELECT v.id, v.attendance_date, v.chief_complaint, v.created_at,
                   p.registration_no, p.first_name, p.surname,
                   u.full_name AS recorded_by
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN users u ON u.id = v.recorded_by
            WHERE 1=1";
    $params = [];
    if ($visSearch !== '') {
        $sql .= " AND (p.surname LIKE ? OR p.first_name LIKE ? OR p.registration_no LIKE ?)";
        $like = '%'.$visSearch.'%';
        $params = array_merge($params, [$like,$like,$like]);
    }
    if ($visDate !== '') {
        $sql .= " AND v.attendance_date = ?";
        $params[] = $visDate;
    }
    $sql .= " ORDER BY v.attendance_date DESC, v.id DESC LIMIT 60";
    $vs = $db->prepare($sql);
    $vs->execute($params);
    $visits = $vs->fetchAll();
}

// Audit log
$auditLogs = [];
if ($tab === 'audit') {
    $auditLogs = $db->query(
        "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 200"
    )->fetchAll();
}

// Summary counts
$totalPatients = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalVisits   = $db->query("SELECT COUNT(*) FROM visits")->fetchColumn();
$totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalLogs     = $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<!-- Admin Summary Stats -->
<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div><div class="stat-value"><?= number_format($totalPatients) ?></div><div class="stat-label">Total Patients</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
        </div>
        <div><div class="stat-value"><?= number_format($totalVisits) ?></div><div class="stat-label">Total Visits</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
        </div>
        <div><div class="stat-value"><?= number_format($totalUsers) ?></div><div class="stat-label">System Users</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div><div class="stat-value"><?= number_format($totalLogs) ?></div><div class="stat-label">Audit Entries</div></div>
    </div>
</div>

<!-- Tab Navigation -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--gray-200);padding-bottom:0">
    <?php
    $tabs = [
        'users'    => ['icon'=>'👤', 'label'=>'Manage Users'],
        'patients' => ['icon'=>'🗑️', 'label'=>'Delete Patients'],
        'visits'   => ['icon'=>'📋', 'label'=>'Delete Visit Records'],
        'audit'    => ['icon'=>'🔍', 'label'=>'Audit Log'],
    ];
    foreach ($tabs as $key => $t):
    ?>
    <a href="?tab=<?= $key ?>"
       style="padding:10px 18px;text-decoration:none;font-size:.85rem;font-weight:500;
              border-bottom:2px solid <?= $tab===$key ? 'var(--primary)' : 'transparent' ?>;
              margin-bottom:-2px;
              color:<?= $tab===$key ? 'var(--primary)' : 'var(--gray-500)' ?>;
              transition:all .2s">
        <?= $t['icon'] ?> <?= $t['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ══════════════ TAB: USERS ══════════════ -->
<?php if ($tab === 'users'): ?>
<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:20px;align-items:start">

    <!-- Add User -->
    <div class="card">
        <div class="card-header"><span class="card-title">➕ Add New User</span></div>
        <div class="card-body">
            <form method="POST" action="?tab=users">
                <input type="hidden" name="action" value="add_user">
                <div class="form-group" style="margin-bottom:12px">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="username" required placeholder="e.g. nurse_ana">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" required placeholder="Display name">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>Role</label>
                    <select name="role">
                        <option value="nurse">Nurse</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>Password <span class="req">*</span></label>
                    <input type="password" name="password" required placeholder="Min 8 characters">
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required placeholder="Repeat password">
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>

    <!-- User List with Role Change, Password Reset, Delete -->
    <div class="card">
        <div class="card-header"><span class="card-title">👥 All Users</span></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Name</th><th>Username</th><th>Role</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $isSelf = $u['id'] === (int)$_SESSION['user_id'];
                ?>
                    <tr>
                        <td>
                            <strong><?= e($u['full_name']) ?></strong>
                            <?php if ($isSelf): ?>
                                <span style="font-size:.7rem;color:var(--gray-400);margin-left:4px">(you)</span>
                            <?php endif; ?>
                            <div style="font-size:.75rem;color:var(--gray-400)"><?= date('M j, Y', strtotime($u['created_at'])) ?></div>
                        </td>
                        <td style="color:var(--gray-500);font-size:.85rem"><?= e($u['username']) ?></td>
                        <td>
                            <!-- Change Role inline -->
                            <?php if (!$isSelf): ?>
                            <form method="POST" action="?tab=users" style="display:flex;gap:4px;align-items:center">
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="new_role" style="padding:4px 8px;font-size:.78rem;width:80px">
                                    <option value="admin"  <?= $u['role']==='admin'  ? 'selected':'' ?>>Admin</option>
                                    <option value="nurse"  <?= $u['role']==='nurse'  ? 'selected':'' ?>>Nurse</option>
                                    <option value="staff"  <?= $u['role']==='staff'  ? 'selected':'' ?>>Staff</option>
                                </select>
                                <button type="submit" class="btn btn-outline btn-sm"
                                        title="Save role change"
                                        data-confirm="Change role for <?= e($u['username']) ?>?">✓</button>
                            </form>
                            <?php else: ?>
                                <span class="badge badge-blue"><?= ucfirst(e($u['role'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isSelf): ?>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <!-- Reset Password -->
                                <button class="btn btn-outline btn-sm"
                                        onclick="showResetModal(<?= $u['id'] ?>, '<?= e($u['username']) ?>')">
                                    🔑 Reset Password
                                </button>
                                <!-- Delete User -->
                                <form method="POST" action="?tab=users" style="display:inline">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            data-confirm="⚠️ Permanently delete user '<?= e($u['username']) ?>'? This cannot be undone.">
                                        🗑 Delete
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                                <span style="font-size:.75rem;color:var(--gray-400)">Cannot modify own account</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Password Reset Modal -->
<div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:12px;padding:28px;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <h3 style="margin-bottom:4px;font-size:1rem">🔑 Reset Password</h3>
        <p id="resetModalSubtitle" style="font-size:.82rem;color:var(--gray-500);margin-bottom:18px"></p>
        <form method="POST" action="?tab=users">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <div class="form-group" style="margin-bottom:12px">
                <label>New Password <span class="req">*</span></label>
                <input type="password" name="new_password" required placeholder="Min 8 characters">
            </div>
            <div class="form-group" style="margin-bottom:18px">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Repeat new password">
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary">Reset Password</button>
                <button type="button" class="btn btn-outline" onclick="closeResetModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
function showResetModal(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetModalSubtitle').textContent = 'Setting new password for: ' + username;
    const m = document.getElementById('resetModal');
    m.style.display = 'flex';
}
function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
}
document.getElementById('resetModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
});
</script>

<!-- ══════════════ TAB: DELETE PATIENTS ══════════════ -->
<?php elseif ($tab === 'patients'): ?>

<div class="alert alert-danger" style="margin-bottom:20px">
    ⚠️ <strong>Warning:</strong> Deleting a patient is <strong>permanent and irreversible.</strong>
    All their visit records will also be deleted. This action is logged.
</div>

<!-- Search patients -->
<form method="GET" action="" style="display:flex;gap:10px;margin-bottom:20px">
    <input type="hidden" name="tab" value="patients">
    <div class="search-input-wrap" style="max-width:400px;flex:1">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="pat_q" value="<?= e($patSearch) ?>"
               placeholder="Search by name, reg no, or contact…">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($patSearch): ?><a href="?tab=patients" class="btn btn-outline">Clear</a><?php endif; ?>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            Patient Records
            <span style="font-weight:400;color:var(--gray-400);font-size:.82rem;margin-left:8px">
                <?= $patSearch ? 'Search results' : 'Most recent 50' ?>
            </span>
        </span>
    </div>
    <div class="table-wrap">
        <?php if (empty($patients)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <p>No patients found<?= $patSearch ? ' for that search' : '' ?>.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Reg No.</th>
                    <th>Full Name</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>Visits</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $p): ?>
                <tr>
                    <td><span class="reg-badge"><?= e($p['registration_no']) ?></span></td>
                    <td><strong><?= e($p['surname']) ?></strong>, <?= e($p['first_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower(e($p['sex'])) ?>"><?= e($p['sex']) ?></span></td>
                    <td><?= e($p['age']) ?></td>
                    <td>
                        <span style="font-weight:600;color:<?= $p['visit_count'] > 0 ? 'var(--primary)' : 'var(--gray-400)' ?>">
                            <?= $p['visit_count'] ?> visit<?= $p['visit_count'] != 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="?tab=patients&pat_q=<?= urlencode($patSearch) ?>" style="display:inline">
                            <input type="hidden" name="action" value="delete_patient">
                            <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                    data-confirm="⚠️ PERMANENTLY DELETE patient <?= e($p['registration_no']) ?> — <?= e($p['surname']) ?>, <?= e($p['first_name']) ?>?\n\nThis will also delete all <?= $p['visit_count'] ?> visit record(s). This CANNOT be undone.">
                                🗑 Delete Patient
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════ TAB: DELETE VISITS ══════════════ -->
<?php elseif ($tab === 'visits'): ?>

<div class="alert alert-info" style="margin-bottom:20px">
    ℹ️ Delete individual visit records only. The patient profile will remain. This action is logged.
</div>

<!-- Filter visits -->
<form method="GET" action="" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="tab" value="visits">
    <div class="search-input-wrap" style="max-width:300px;flex:1">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="vis_q" value="<?= e($visSearch) ?>"
               placeholder="Search patient name or reg no…">
    </div>
    <div class="form-group" style="margin:0">
        <label style="font-size:.78rem">Filter by Date</label>
        <input type="date" name="vis_date" value="<?= e($visDate) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($visSearch || $visDate): ?>
        <a href="?tab=visits" class="btn btn-outline">Clear</a>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            Visit Records
            <span style="font-weight:400;color:var(--gray-400);font-size:.82rem;margin-left:8px">
                <?= ($visSearch || $visDate) ? 'Filtered results' : 'Most recent 60' ?>
            </span>
        </span>
    </div>
    <div class="table-wrap">
        <?php if (empty($visits)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                <p>No visit records found.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Visit #</th>
                    <th>Reg No.</th>
                    <th>Patient</th>
                    <th>Date of Visit</th>
                    <th>Chief Complaint</th>
                    <th>Recorded By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($visits as $v): ?>
                <tr>
                    <td style="color:var(--gray-400);font-family:monospace">#<?= $v['id'] ?></td>
                    <td><span class="reg-badge"><?= e($v['registration_no']) ?></span></td>
                    <td><strong><?= e($v['surname']) ?></strong>, <?= e($v['first_name']) ?></td>
                    <td><?= date('M j, Y', strtotime($v['attendance_date'])) ?></td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= e($v['chief_complaint'] ?? '—') ?>
                    </td>
                    <td><?= e($v['recorded_by'] ?? 'System') ?></td>
                    <td>
                        <form method="POST" action="?tab=visits&vis_q=<?= urlencode($visSearch) ?>&vis_date=<?= urlencode($visDate) ?>" style="display:inline">
                            <input type="hidden" name="action" value="delete_visit">
                            <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                    data-confirm="Delete visit #<?= $v['id'] ?> for <?= e($v['registration_no']) ?> — <?= e($v['surname']) ?> on <?= date('M j, Y', strtotime($v['attendance_date'])) ?>?\n\nThis cannot be undone.">
                                🗑 Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════ TAB: AUDIT LOG ══════════════ -->
<?php elseif ($tab === 'audit'): ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">🔍 Audit Log
            <span style="font-weight:400;color:var(--gray-400);font-size:.82rem;margin-left:8px">Last 200 actions</span>
        </span>
        <button onclick="printPage()" class="btn btn-outline btn-sm no-print">Print Log</button>
    </div>
    
    <!-- Archive Form -->
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-100);background:var(--gray-50)">
        <form method="POST" action="?tab=audit" style="display:flex;gap:12px;align-items:flex-end">
            <input type="hidden" name="action" value="archive_audit_logs">
            <div class="form-group" style="margin:0">
                <label style="font-size:.8rem">Archive logs older than</label>
                <select name="years_old" style="padding:6px 10px;font-size:.85rem;width:120px">
                    <option value="1">1 year</option>
                    <option value="2" selected>2 years</option>
                    <option value="3">3 years</option>
                    <option value="5">5 years</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"
                    data-confirm="Archive audit logs older than the selected period? This will move them to an archive table and remove them from the main log.">
                📦 Archive Old Logs
            </button>
        </form>
        <p style="font-size:.75rem;color:var(--gray-500);margin-top:6px;margin-bottom:0">
            Archived logs are moved to a separate table for long-term storage. This helps maintain performance while preserving audit history.
        </p>
    </div>
    
    <div class="table-wrap">
        <?php if (empty($auditLogs)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <p>No audit entries yet. Actions will be logged here.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Date & Time</th><th>User</th><th>Action</th><th>Details</th><th>IP Address</th></tr>
            </thead>
            <tbody>
            <?php foreach ($auditLogs as $log):
                $actionColors = [
                    'DELETE_PATIENT'     => 'var(--danger)',
                    'DELETE_VISIT'       => '#e67e22',
                    'DELETE_USER'        => 'var(--danger)',
                    'ADD_USER'           => 'var(--success)',
                    'CHANGE_ROLE'        => 'var(--primary)',
                    'RESET_PASSWORD'     => '#8e44ad',
                    'ARCHIVE_AUDIT_LOGS' => '#27ae60',
                    'ARCHIVE_ERROR'      => 'var(--danger)',
                ];
                $color = $actionColors[$log['action']] ?? 'var(--gray-600)';
            ?>
                <tr>
                    <td style="white-space:nowrap;font-size:.8rem">
                        <?= date('M j, Y', strtotime($log['created_at'])) ?>
                        <div style="color:var(--gray-400)"><?= date('g:i A', strtotime($log['created_at'])) ?></div>
                    </td>
                    <td>
                        <strong><?= e($log['username'] ?? 'System') ?></strong>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:5px;
                                     padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;
                                     background:<?= $color ?>18;color:<?= $color ?>">
                            <?= e($log['action']) ?>
                        </span>
                    </td>
                    <td style="font-size:.82rem;color:var(--gray-600);max-width:280px">
                        <?= e($log['details'] ?? '—') ?>
                    </td>
                    <td style="font-size:.78rem;color:var(--gray-400);font-family:monospace">
                        <?= e($log['ip_address'] ?? '—') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
