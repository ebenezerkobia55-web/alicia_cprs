<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Access denied. Admins only.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$db = getDB();
$success = '';
$errors  = [];

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $uname    = trim($_POST['username']  ?? '');
    $fullname = trim($_POST['full_name'] ?? '');
    $role     = $_POST['role'] ?? 'nurse';
    $pass     = $_POST['password'] ?? '';
    $passConf = $_POST['password_confirm'] ?? '';

    if ($uname === '' || $fullname === '') $errors[] = 'Username and full name are required.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $passConf) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['admin','nurse','staff'])) $errors[] = 'Invalid role.';

    if (empty($errors)) {
        try {
            $s = $db->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)");
            $s->execute([$uname, password_hash($pass, PASSWORD_BCRYPT), $fullname, $role]);
            $success = "User '{$uname}' added successfully.";
        } catch (PDOException $e) {
            $errors[] = 'Username already exists.';
        }
    }
}

// Delete User
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId !== $_SESSION['user_id']) {
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
        $success = 'User deleted.';
    } else {
        $errors[] = 'You cannot delete your own account.';
    }
}

$users = $db->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY role, full_name")->fetchAll();
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($errors):  ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;align-items:start">
    <!-- Add User Form -->
    <div class="card">
        <div class="card-header"><span class="card-title">Add New User</span></div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="username" required placeholder="login username">
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" required placeholder="Display name">
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Role</label>
                    <select name="role">
                        <option value="nurse">Nurse</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Password <span class="req">*</span></label>
                    <input type="password" name="password" required placeholder="Min 8 characters">
                </div>
                <div class="form-group" style="margin-bottom:18px">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required placeholder="Repeat password">
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>

    <!-- User List -->
    <div class="card">
        <div class="card-header"><span class="card-title">System Users</span></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Name</th><th>Username</th><th>Role</th><th>Created</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?= e($u['full_name']) ?></strong></td>
                        <td style="color:var(--gray-500)"><?= e($u['username']) ?></td>
                        <td>
                            <span class="badge <?= $u['role']==='admin' ? 'badge-blue' : 'badge-green' ?>">
                                <?= ucfirst(e($u['role'])) ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <a href="?delete=<?= $u['id'] ?>" class="btn btn-danger btn-sm"
                                   data-confirm="Delete user '<?= e($u['username']) ?>'?">Delete</a>
                            <?php else: ?>
                                <span style="font-size:.78rem;color:var(--gray-400)">(you)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
