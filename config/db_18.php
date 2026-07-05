<?php
// ============================================================
// config/db_18.php — Database Connection Configuration
// QuickResolve_18 – Smart Complaint Management System
// ============================================================
// This file creates a MySQLi connection to the database.
// Include this file at the top of any page that needs DB access.
// ============================================================

// Database credentials (XAMPP defaults)
define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');              // Leave empty for XAMPP default
define('DB_NAME',     'database_18');
define('DB_CHARSET',  'utf8mb4');

// Site configuration
define('SITE_NAME',   'QuickResolve_18');
define('SITE_URL',    'http://localhost/QuickResolve_18');
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('UPLOAD_URL',  SITE_URL . '/uploads/');

// ── Create MySQLi connection ──────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection and stop execution on failure
if ($conn->connect_error) {
    // Show a friendly error page instead of raw PHP error
    die('
    <!DOCTYPE html>
    <html>
    <head><title>Connection Error – QuickResolve_18</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center p-5 bg-white rounded shadow">
        <h2 class="text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> Database Error</h2>
        <p class="text-muted">Could not connect to the database.<br>
        Please make sure XAMPP MySQL is running and the database <strong>database_18</strong> exists.</p>
        <code class="d-block mt-3 text-danger">Error: ' . $conn->connect_error . '</code>
        <a href="../index.php" class="btn btn-primary mt-4">Go to Homepage</a>
    </div>
    </body></html>
    ');
}

// Set character set for proper UTF-8 support
$conn->set_charset(DB_CHARSET);

// ── Helper: Sanitize input to prevent SQL injection ───────────
function sanitize($conn, $data) {
    // Trim whitespace and escape special characters
    return $conn->real_escape_string(trim($data));
}

// ── Helper: Flash message system (store message in session) ───
function setFlash($type, $message) {
    // type: success | danger | warning | info
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

// ── Helper: Display and clear flash message ───────────────────
function showFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="alert alert-' . $f['type'] . ' alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-' . ($f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'danger' ? 'times-circle' : 'info-circle')) . ' me-2"></i>
                ' . htmlspecialchars($f['msg']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['flash']); // Clear after display
    }
}

// ── Helper: Redirect shortcut ─────────────────────────────────
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

// ── Helper: Status badge HTML ─────────────────────────────────
function statusBadge($status) {
    // Returns Bootstrap badge with appropriate color per status
    $map = [
        'pending'     => ['warning',  'clock',        'Pending'],
        'assigned'    => ['primary',  'tag',          'Assigned'],
        'in_progress' => ['purple',   'spinner',      'In Progress'],
        'completed'   => ['success',  'check-circle', 'Completed'],
        'rejected'    => ['danger',   'times-circle', 'Rejected'],
    ];
    $s = $map[$status] ?? ['secondary', 'question', ucfirst($status)];
    $colorClass = $s[0] === 'purple' ? 'badge-purple' : 'bg-' . $s[0];
    return '<span class="badge ' . $colorClass . ' px-3 py-2">
                <i class="fas fa-' . $s[1] . ' me-1"></i>' . $s[2] . '
            </span>';
}

// ── Helper: Priority badge HTML ───────────────────────────────
function priorityBadge($priority) {
    $map = [
        'low'      => 'success',
        'medium'   => 'warning',
        'high'     => 'orange',
        'critical' => 'danger',
    ];
    $color = $map[$priority] ?? 'secondary';
    $colorClass = $color === 'orange' ? 'badge-orange' : 'bg-' . $color;
    return '<span class="badge ' . $colorClass . ' px-2 py-1">' . ucfirst($priority) . '</span>';
}
?>
