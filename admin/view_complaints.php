<?php
// ============================================================
// admin/view_complaints.php — View & Manage All Complaints
// QuickResolve_18 – Smart Complaint Management System
// ============================================================

session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

// ── Handle inline actions ─────────────────────────────────────
// Quick status update from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $cid    = (int)$_POST['complaint_id'];
    $status = sanitize($conn, $_POST['status']);
    $note   = sanitize($conn, $_POST['admin_note'] ?? '');
    $valid  = ['pending','assigned','in_progress','completed','rejected'];

    if (in_array($status, $valid)) {
        // Get old status for log
        $old = $conn->query("SELECT status FROM complaints WHERE id=$cid")->fetch_row()[0];
        $conn->query("UPDATE complaints SET status='$status', admin_note='$note' WHERE id=$cid");

        // Log the change
        $adminId = $_SESSION['user_id'];
        $log = $conn->prepare("INSERT INTO complaint_logs (complaint_id,changed_by,old_status,new_status,note) VALUES(?,?,?,?,?)");
        $log->bind_param('iisss', $cid, $adminId, $old, $status, $note);
        $log->execute();
        $log->close();

        // Archive if completed
        if ($status === 'completed') {
            $c = $conn->query("SELECT * FROM complaints WHERE id=$cid")->fetch_assoc();
            $archStmt = $conn->prepare("INSERT IGNORE INTO archive (complaint_id,user_id,dept_id,title,description,priority,final_status) VALUES(?,?,?,?,?,?,?)");
            $archStmt->bind_param('iiissss', $c['id'],$c['user_id'],$c['dept_id'],$c['title'],$c['description'],$c['priority'],$status);
            $archStmt->execute();
            $archStmt->close();
        }

        setFlash('success', "Complaint #$cid status updated to " . ucfirst($status));
    }
    redirect(SITE_URL . '/admin/view_complaints.php');
}

// ── Filters ───────────────────────────────────────────────────
$filterStatus   = sanitize($conn, $_GET['status']   ?? '');
$filterPriority = sanitize($conn, $_GET['priority'] ?? '');
$filterDept     = (int)($_GET['dept'] ?? 0);
$search         = sanitize($conn, $_GET['q'] ?? '');

$where = 'WHERE 1=1';
if ($filterStatus)   $where .= " AND c.status='$filterStatus'";
if ($filterPriority) $where .= " AND c.priority='$filterPriority'";
if ($filterDept)     $where .= " AND c.dept_id=$filterDept";
if ($search)         $where .= " AND (c.title LIKE '%$search%' OR c.description LIKE '%$search%' OR u.name LIKE '%$search%')";

// Fetch all complaints with filters
$complaints = $conn->query("
    SELECT c.id, c.title, c.priority, c.status, c.auto_assigned, c.created_at,
           u.name AS user_name, d.name AS dept_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.dept_id = d.id
    $where
    ORDER BY c.created_at DESC
");

// Fetch departments for filter dropdown
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

$pageTitle = 'All Complaints';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="dashboard-layout">
    <!-- Sidebar -->
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
        <a href="view_complaints.php"    class="sidebar-link active"><i class="fas fa-clipboard-list"></i> All Complaints</a>
        <a href="assign_complaint.php"   class="sidebar-link"><i class="fas fa-tags"></i> Assign Complaints</a>
        <a href="filter_complaints.php"  class="sidebar-link"><i class="fas fa-filter"></i> Filter & Search</a>
        <a href="archive.php"            class="sidebar-link"><i class="fas fa-archive"></i> Archive</a>
        <div class="sidebar-section-label">Management</div>
        <a href="manage_users.php"       class="sidebar-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <div class="sidebar-section-label">Account</div>
        <a href="../logout.php"          class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <main class="qr-main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1><i class="fas fa-clipboard-list me-2 text-primary"></i>All Complaints</h1>
                <p class="text-muted mb-0"><?= $complaints->num_rows ?> complaint(s) found</p>
            </div>
            <a href="assign_complaint.php" class="btn btn-primary btn-sm">
                <i class="fas fa-tags me-1"></i>Assign Complaints
            </a>
        </div>

        <?php showFlash(); ?>

        <!-- Filters bar -->
        <form method="GET" class="qr-form-card mb-4 p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" name="q"
                           placeholder="Search title, desc, user..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','assigned','in_progress','completed','rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="priority">
                        <option value="">All Priorities</option>
                        <?php foreach (['low','medium','high','critical'] as $p): ?>
                        <option value="<?= $p ?>" <?= $filterPriority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="dept">
                        <option value="">All Departments</option>
                        <?php
                        $departments->data_seek(0);
                        while ($d = $departments->fetch_assoc()):
                        ?>
                        <option value="<?= $d['id'] ?>" <?= $filterDept===$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="view_complaints.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>

        <!-- Complaints table -->
        <div class="qr-form-card">
            <div class="table-responsive">
                <table class="qr-table">
                    <thead>
                        <tr>
                            <th>#ID</th><th>Title</th><th>User</th><th>Department</th>
                            <th>Priority</th><th>Status</th><th>Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($complaints->num_rows > 0):
                        while ($row = $complaints->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-semibold text-primary">#<?= $row['id'] ?></span></td>
                            <td>
                                <div class="fw-semibold small"><?= htmlspecialchars(substr($row['title'],0,45)) ?>…</div>
                                <?php if ($row['auto_assigned']): ?>
                                <span class="badge badge-purple" style="font-size:0.68rem"><i class="fas fa-magic me-1"></i>Auto-Routed</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['user_name']) ?></td>
                            <td><?= $row['dept_name'] ? htmlspecialchars($row['dept_name']) : '<em class="text-muted small">Unassigned</em>' ?></td>
                            <td><?= priorityBadge($row['priority']) ?></td>
                            <td><?= statusBadge($row['status']) ?></td>
                            <td class="text-muted small"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Quick status update modal trigger -->
                                    <button class="btn btn-outline-primary btn-sm"
                                            onclick="openStatusModal(<?= $row['id'] ?>, '<?= $row['status'] ?>')"
                                            data-bs-toggle="tooltip" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="assign_complaint.php?id=<?= $row['id'] ?>"
                                       class="btn btn-outline-success btn-sm"
                                       data-bs-toggle="tooltip" title="Assign Department">
                                        <i class="fas fa-tag"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No complaints found matching your filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Update Complaint Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="view_complaints.php">
                <div class="modal-body">
                    <input type="hidden" name="complaint_id" id="modalComplaintId">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-select" name="status" id="modalStatus">
                            <option value="pending">Pending</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Note (Optional)</label>
                        <textarea class="form-control" name="admin_note" rows="3"
                                  placeholder="Add a note about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openStatusModal(id, currentStatus) {
    document.getElementById('modalComplaintId').value = id;
    document.getElementById('modalStatus').value = currentStatus;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
