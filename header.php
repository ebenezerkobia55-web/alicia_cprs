<?php
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userName = $_SESSION['full_name'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — Alicia CPRS</title>
    <link rel="icon" href="/alicia_cprs/assets/logo.jpg" type="image/jpeg">
    <link rel="stylesheet" href="/alicia_cprs/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo">
                <img src="/alicia_cprs/assets/logo.jpg" alt="Clinic Logo" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="brand-initials" style="display:none">AHC</div>
            </div>
            <div class="brand-text">
                <span class="brand-name">Alicia</span>
                <span class="brand-sub">Homeopathic Clinic</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="/alicia_cprs/pages/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a href="/alicia_cprs/pages/patients.php" class="nav-item <?= $currentPage === 'patients' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Patients
            </a>
            <a href="/alicia_cprs/pages/add_patient.php" class="nav-item <?= $currentPage === 'add_patient' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                Register Patient
            </a>

            <div class="nav-section-label">Records</div>
            <a href="/alicia_cprs/pages/visits.php" class="nav-item <?= $currentPage === 'visits' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Visit Records
            </a>
            <a href="/alicia_cprs/pages/reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Reports
            </a>

            <?php if ($userRole === 'admin'): ?>
            <div class="nav-section-label">Admin</div>
            <a href="/alicia_cprs/pages/admin.php?tab=users" class="nav-item <?= $currentPage === 'admin' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                Admin Panel
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
            <div class="user-info">
                <span class="user-name"><?= e($userName) ?></span>
                <span class="user-role"><?= ucfirst(e($userRole)) ?></span>
            </div>
            <a href="/alicia_cprs/logout.php" class="logout-btn" title="Logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="topbar">
            <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-title">
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
                <span class="topbar-date"><?= date('l, F j, Y') ?></span>
            </div>
            <div class="topbar-actions">
                <a href="/alicia_cprs/pages/add_patient.php" class="btn btn-primary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New Patient
                </a>
            </div>
        </header>
        <div class="page-body">
