<?php
// ============================================================
// Alicia CPRS - Database Configuration
// ============================================================

define('DB_HOST',    getenv('DB_HOST')    ?: '');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// Clinic Identity
define('CLINIC_NAME',  'Alicia Homeopathic Clinic');
define('CLINIC_SHORT', 'AHC');
define('SYSTEM_NAME',  'CPRS');
define('SYSTEM_YEAR',  date('Y'));

// Registration Number Format: AHC-YYYY-NNNNN
define('REG_PREFIX', 'AHC');

/**
 * Get PDO database connection
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host    = DB_HOST;
        $dbname  = DB_NAME;
        $charset = DB_CHARSET;

        if (empty($host) || empty($dbname)) {
            die(json_encode(['error' => 'Database environment variables are not configured. Please check Render environment settings.']));
        }

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed. Please contact support.']));
        }
    }
    return $pdo;
}

/**
 * Test database connection
 */
function testDBConnection(): bool {
    try {
        getDB();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate professional registration number
 * Format: AHC-YYYY-NNNNN (e.g. AHC-2025-00001)
 */
function generateRegistrationNo(): string {
    $db     = getDB();
    $year   = date('Y');
    $prefix = REG_PREFIX . '-' . $year . '-';

    $stmt = $db->prepare(
        "SELECT registration_no FROM patients 
         WHERE registration_no LIKE :prefix 
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastNum = (int) substr($last, -5);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }

    return $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
}

/**
 * Calculate precise age from DOB
 */
function calculateAge(string $dob): int {
    $birthDate = new DateTime($dob);
    $today     = new DateTime('today');
    return (int) $birthDate->diff($today)->y;
}

/**
 * Session-based authentication check
 */
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}

/**
 * Sanitize output
 */
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

/**
 * Write an audit log entry
 */
function auditLog(string $action, string $targetType = '', int $targetId = 0, string $details = ''): void {
    try {
        $db       = getDB();
        $userId   = $_SESSION['user_id']  ?? null;
        $username = $_SESSION['username'] ?? 'system';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $db->prepare(
            "INSERT INTO audit_log (user_id, username, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $username, $action, $targetType, $targetId ?: null, $details ?: null, $ip]);
    } catch (Exception $e) {
        // Silently fail — audit log should never break main app
    }
}

/**
 * Archive old audit logs to audit_log_archive table
 */
function archiveAuditLogs(int $yearsOld): int {
    try {
        $db = getDB();

        // Create archive table if not exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS audit_log_archive (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(255) NULL,
                action VARCHAR(255) NOT NULL,
                target_type VARCHAR(255) NULL,
                target_id INT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Calculate cutoff date
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$yearsOld} years"));

        // Move old logs to archive
        $stmt = $db->prepare("
            INSERT INTO audit_log_archive 
            (id, user_id, username, action, target_type, target_id, details, ip_address, created_at)
            SELECT id, user_id, username, action, target_type, target_id, details, ip_address, created_at
            FROM audit_log 
            WHERE created_at < ?
        ");
        $stmt->execute([$cutoffDate]);

        // Count moved records
        $movedCount = $db->query("SELECT ROW_COUNT()")->fetchColumn();

        // Delete from main table
        $deleteStmt = $db->prepare("DELETE FROM audit_log WHERE created_at < ?");
        $deleteStmt->execute([$cutoffDate]);

        // Log the archiving action
        auditLog('ARCHIVE_AUDIT_LOGS', '', 0, "Archived {$movedCount} audit log entries older than {$yearsOld} years");

        return (int) $movedCount;
    } catch (Exception $e) {
        auditLog('ARCHIVE_ERROR', '', 0, "Failed to archive audit logs: " . $e->getMessage());
        return 0;
    }
}
