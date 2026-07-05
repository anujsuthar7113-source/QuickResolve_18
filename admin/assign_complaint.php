<?php
// ============================================================
// admin/assign_complaint.php — Assign Complaints to Departments
// QuickResolve_18 – Smart Complaint Management System
// ============================================================

session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

// ── Handle assignment form ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $cid    = (int)$_POST['complaint_id'];
    $deptId = (int)$_POST['dept_id'];
    $note   = sanitize($conn, $_POST['admin_note'] ?? '');
    $adminId = $_SESSION['user_id'];

    if ($cid && $deptId) {
        // Get old status for log
        $old = $conn->query("SELECT status FROM complaints WHERE id=$cid")->fetch_row()[0];

        // Update complaint with department and change status to assigned
        $stmt = $conn->prepare("UPDATE complaints SET dept_id=?, status='assigned', admin_note=? WHERE id=?");
        $stmt->bind_param('isi', $deptId, $note, $cid);
        $stmt->execute();
        $stmt->close();

        // Log the assignment
        $logNote = "Manually assigned to department by admin. " . ($note ? "Note: $note" : '');
        $log = $conn->prepare("INSERT INTO complaint_logs (complaint_id, changed_by, old_status, new_status, note) VALUES(?,?,'pending','assigned',?)");
        $log->bind_param('iis', $cid, $adminId, $logNote);
        $log->execute();
        $log->close();

        setFlash('success', "Complaint #$cid successfully assigned to department.");
        redirect(SITE_URL . '/admin/assign_complaint.php');
    }
}

// Fetch unassigned (pending, dept_id IS NULL) complaints
$unassigned = $conn->query("
    SELECT c.id, c.title, c.priority, c.status, c.description, c.created_at, u.name AS user_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE (c.dept_id IS NULL OR c.status = 'pending')
    ORDER BY
        CASE c.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
        c.created_at ASC
");

// If specific complaint ID passed, preload it
$preloadId = (int)($_GET['id'] ?? 0);

// Fetch departments for assignment dropdown
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

$pageTitle = 'Assign Complaints';
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
        <a href="assign_complaint.php"   class="sidebar-link active"><i class="fas fa-tags"></i> Assign Complaints</a>
        <a href="filter_complaints.php"  class="sidebar-link"><i class="fas fa-filter"></i> Filter & Search</a>
        <a href="archive.php"            class="sidebar-link"><i class="fas fa-archive"></i> Archive</a>
        <div class="sidebar-section-label">Management</div>
        <a href="manage_users.php"       class="sidebar-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <div class="sidebar-section-label">Account</div>
        <a href="../logout.php"          class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <main class="qr-main-content">
        <div class="page-header">
            <h1><i class="fas fa-tags me-2 text-primary"></i>Assign Complaints</h1>
            <p class="text-muted mb-0">Manually assign unrouted complaints to departments</p>
        </div>

        <?php showFlash(); ?>

        <?php if ($unassigned->num_rows === 0): ?>
        <div class="qr-form-card text-center py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
            <h5 class="fw-bold text-success">All caught up!</h5>
            <p class="text-muted">There are no unassigned complaints at the moment.</p>
            <a href="view_complaints.php" class="btn btn-primary">View All Complaints</a>
        </div>
        <?php else: ?>

        <div class="row g-4">
            <?php while ($c = $unassigned->fetch_assoc()): ?>
            <div class="col-lg-6">
                <div class="qr-form-card h-100" style="border-left: 4px solid var(--<?= $c['priority']==='critical'?'danger':($c['priority']==='high'?'orange':($c['priority']==='medium'?'warning':'success')) ?>)">

                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="fw-bold text-primary">#<?= $c['id'] ?></span>
                            <h6 class="fw-bold mb-1 mt-1"><?= htmlspecialchars($c['title']) ?></h6>
                            <div class="small text-muted">By <?= htmlspecialchars($c['user_name']) ?> · <?= date('d M Y', strtotime($c['created_at'])) ?></div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <?= priorityBadge($c['priority']) ?>
                            <?= statusBadge($c['status']) ?>
                        </div>
                    </div>

                    <p class="text-muted small mb-3">
                        <?= htmlspecialchars(substr($c['description'], 0, 150)) ?>…
                    </p>

                    <!-- Assignment form -->
                    <form method="POST" action="assign_complaint.php">
                        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                        <div class="row g-2">
                            <div class="col-sm-7">
                                <select class="form-select form-select-sm" name="dept_id" required>
                                    <option value="">— Select Department —</option>
                                    <?php
                                    $departments->data_seek(0);
                                    while ($d = $departments->fetch_assoc()):
                                    ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <button type="submit" name="assign" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-paper-plane me-1"></i>Assign
                                </button>
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control form-control-sm" name="admin_note"
                                       placeholder="Optional admin note...">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
