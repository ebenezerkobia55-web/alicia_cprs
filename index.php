<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /alicia_cprs/pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password_hash, full_name, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            session_regenerate_id(true);
            header('Location: /alicia_cprs/pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Alicia Homeopathic Clinic CPRS</title>
    <link rel="icon" href="/alicia_cprs/assets/logo.jpg" type="image/jpeg">
    <link rel="stylesheet" href="/alicia_cprs/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-header">
            <div class="login-logo">
                <img src="/alicia_cprs/assets/logo.jpg" alt="Clinic Logo"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="login-logo-initials" style="display:none">AHC</div>
            </div>
            <h1>Alicia Homeopathic Clinic</h1>
            <p>Patient Record System</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group" style="margin-bottom:16px">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= e($_POST['username'] ?? '') ?>"
                           autocomplete="username" required placeholder="Enter username">
                </div>
                <div class="form-group" style="margin-bottom:22px">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password" required placeholder="Enter password">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">
                    Sign In
                </button>
            </form>
        </div>
        <div class="login-footer">
            &copy; <?= date('Y') ?> Alicia Homeopathic Clinic &mdash; CPRS v1.0
        </div>
    </div>
</div>
<script src="/alicia_cprs/js/app.js"></script>
</body>
</html>
