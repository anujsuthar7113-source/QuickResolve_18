<?php

// admin/archive.php — Complaint Archive


session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

//  Manually archive completed complaints not yet archived
if (isset($_GET['archive_all'])) {
    $completed = $conn->query("
        SELECT c.* FROM complaints c
        LEFT JOIN archive a ON a.complaint_id = c.id
        WHERE c.status='completed' AND a.id IS NULL
    ");
    $count = 0;
    while ($c = $completed->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT IGNORE INTO archive (complaint_id,user_id,dept_id,title,description,priority,final_status) VALUES(?,?,?,?,?,?,'completed')");
        $stmt->bind_param('iiisss', $c['id'],$c['user_id'],$c['dept_id'],$c['title'],$c['description'],$c['priority']);
        $stmt->execute();
        $stmt->close();
        $count++;
    }
    setFlash('success', "$count completed complaints archived successfully.");
    redirect(SITE_URL . '/admin/archive.php');
}

// Fetch archived complaints
$archives = $conn->query("
    SELECT a.*, u.name AS user_name, d.name AS dept_name
    FROM archive a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN departments d ON a.dept_id = d.id
    ORDER BY a.archived_at DESC
");

// Count not yet archived
$pendingArchive = $conn->query("
    SELECT COUNT(*) FROM complaints c
    LEFT JOIN archive a ON a.complaint_id = c.id
    WHERE c.status='completed' AND a.id IS NULL
")->fetch_row()[0];

$pageTitle = 'Archive';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="dashboard-layout">
    <aside class="qr-sidebar">
        <div class="sidebar-user-card">
            <div class="d-flex align-items-center gap-2">
                <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#F59E0B,#EF4444);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700">A</div>
                <div>
                    <div class="text-white fw-semibold small"><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div style="color:rgba(255,255,255,0.45);font-size:0.75rem">Super Admin</div>
                </div>
            </div>
        </div>
        <div class="sidebar-section-label">Overview</div>
        <a href="dashboard.php"          class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="analytics.php"          class="sidebar-link"><i class="fas fa-chart-bar"></i> Analytics</a>
        <div class="sidebar-section-label">Complaints</div>
        <a href="view_complaints.php"    class="sidebar-link"><i class="fas fa-clipboard-list"></i> All Complaints</a>
        <a href="assign_complaint.php"   class="sidebar-link"><i class="fas fa-tags"></i> Assign Complaints</a>
        <a href="filter_complaints.php"  class="sidebar-link"><i class="fas fa-filter"></i> Filter & Search</a>
        <a href="archive.php"            class="sidebar-link active"><i class="fas fa-archive"></i> Archive</a>
        <div class="sidebar-section-label">Management</div>
        <a href="manage_users.php"       class="sidebar-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <div class="sidebar-section-label">Account</div>
        <a href="../logout.php"          class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <main class="qr-main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1><i class="fas fa-archive me-2 text-primary"></i>Complaint Archive</h1>
                <p class="text-muted mb-0"><?= $archives->num_rows ?> archived record(s)</p>
            </div>
            <?php if ($pendingArchive > 0): ?>
            <a href="archive.php?archive_all=1" class="btn btn-success">
                <i class="fas fa-archive me-2"></i>Archive <?= $pendingArchive ?> Completed
            </a>
            <?php endif; ?>
        </div>

        <?php showFlash(); ?>

        <?php if ($pendingArchive > 0): ?>
        <div class="alert alert-info auto-dismiss">
            <i class="fas fa-info-circle me-2"></i>
            There are <strong><?= $pendingArchive ?></strong> completed complaint(s) not yet in the archive.
            <a href="archive.php?archive_all=1" class="alert-link ms-2">Archive them now →</a>
        </div>
        <?php endif; ?>

        <div class="qr-form-card">
            <?php if ($archives->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="qr-table">
                    <thead>
                        <tr><th>Orig #</th><th>Title</th><th>User</th><th>Department</th><th>Priority</th><th>Status</th><th>Archived On</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($a = $archives->fetch_assoc()): ?>
                    <tr>
                        <td><span class="fw-semibold text-muted">#<?= $a['complaint_id'] ?></span></td>
                        <td class="fw-semibold small"><?= htmlspecialchars(substr($a['title'],0,50)) ?>…</td>
                        <td><?= htmlspecialchars($a['user_name'] ?? 'N/A') ?></td>
                        <td><?= $a['dept_name'] ? htmlspecialchars($a['dept_name']) : '<em class="text-muted">N/A</em>' ?></td>
                        <td><?= priorityBadge($a['priority']) ?></td>
                        <td><?= statusBadge($a['final_status']) ?></td>
                        <td class="text-muted small"><?= date('d M Y, h:i A', strtotime($a['archived_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-archive fa-3x text-muted opacity-40 mb-3 d-block"></i>
                <p class="text-muted">No archived complaints yet. Complete and archive complaints to see them here.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
