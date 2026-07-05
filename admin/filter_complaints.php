<?php
// ============================================================
// admin/filter_complaints.php — Advanced Filter & Search
// QuickResolve_18 – Smart Complaint Management System
// ============================================================

session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

// ── Advanced filter logic ─────────────────────────────────────
$q        = sanitize($conn, $_GET['q']        ?? '');
$status   = sanitize($conn, $_GET['status']   ?? '');
$priority = sanitize($conn, $_GET['priority'] ?? '');
$deptId   = (int)($_GET['dept']  ?? 0);
$dateFrom = sanitize($conn, $_GET['from']     ?? '');
$dateTo   = sanitize($conn, $_GET['to']       ?? '');
$routing  = sanitize($conn, $_GET['routing']  ?? '');

$where = 'WHERE 1=1';
if ($q)        $where .= " AND (c.title LIKE '%$q%' OR c.description LIKE '%$q%' OR u.name LIKE '%$q%' OR u.email LIKE '%$q%')";
if ($status)   $where .= " AND c.status='$status'";
if ($priority) $where .= " AND c.priority='$priority'";
if ($deptId)   $where .= " AND c.dept_id=$deptId";
if ($dateFrom) $where .= " AND DATE(c.created_at) >= '$dateFrom'";
if ($dateTo)   $where .= " AND DATE(c.created_at) <= '$dateTo'";
if ($routing === 'auto')   $where .= " AND c.auto_assigned=1";
if ($routing === 'manual') $where .= " AND c.auto_assigned=0";

$searched = isset($_GET['q']) || isset($_GET['status']) || $deptId || $dateFrom;

$results = $searched ? $conn->query("
    SELECT c.id, c.title, c.priority, c.status, c.auto_assigned, c.created_at,
           u.name AS user_name, d.name AS dept_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.dept_id = d.id
    $where
    ORDER BY c.created_at DESC
") : null;

$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

$pageTitle = 'Filter Complaints';
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
        <a href="filter_complaints.php"  class="sidebar-link active"><i class="fas fa-filter"></i> Filter & Search</a>
        <a href="archive.php"            class="sidebar-link"><i class="fas fa-archive"></i> Archive</a>
        <div class="sidebar-section-label">Management</div>
        <a href="manage_users.php"       class="sidebar-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <div class="sidebar-section-label">Account</div>
        <a href="../logout.php"          class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <main class="qr-main-content">
        <div class="page-header">
            <h1><i class="fas fa-filter me-2 text-primary"></i>Advanced Filter & Search</h1>
            <p class="text-muted mb-0">Search and filter complaints by any combination of criteria</p>
        </div>

        <!-- Advanced filter form -->
        <div class="qr-form-card mb-4">
            <form method="GET" action="filter_complaints.php">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Keyword Search</label>
                        <input type="text" class="form-control" name="q"
                               placeholder="Search by title, description, user name or email..."
                               value="<?= htmlspecialchars($q) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending','assigned','in_progress','completed','rejected'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Priority</label>
                        <select class="form-select" name="priority">
                            <option value="">All Priorities</option>
                            <?php foreach (['critical','high','medium','low'] as $p): ?>
                            <option value="<?= $p ?>" <?= $priority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Department</label>
                        <select class="form-select" name="dept">
                            <option value="">All Departments</option>
                            <?php while ($d = $departments->fetch_assoc()): ?>
                            <option value="<?= $d['id'] ?>" <?= $deptId===$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Routing</label>
                        <select class="form-select" name="routing">
                            <option value="">All Routing Types</option>
                            <option value="auto"   <?= $routing==='auto'  ?'selected':'' ?>>Auto-Routed (Smart)</option>
                            <option value="manual" <?= $routing==='manual'?'selected':'' ?>>Manual (Admin)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date From</label>
                        <input type="date" class="form-control" name="from" value="<?= $dateFrom ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date To</label>
                        <input type="date" class="form-control" name="to" value="<?= $dateTo ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary px-5">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <a href="filter_complaints.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results -->
        <?php if ($searched && $results): ?>
        <div class="qr-form-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-list me-2 text-primary"></i>
                    Search Results <span class="badge bg-primary ms-2"><?= $results->num_rows ?></span>
                </h5>
            </div>
            <?php if ($results->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="qr-table">
                    <thead>
                        <tr><th>#ID</th><th>Title</th><th>User</th><th>Department</th><th>Priority</th><th>Status</th><th>Routing</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $results->fetch_assoc()): ?>
                    <tr>
                        <td><span class="fw-semibold text-primary">#<?= $row['id'] ?></span></td>
                        <td class="fw-semibold small"><?= htmlspecialchars(substr($row['title'],0,50)) ?>…</td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td><?= $row['dept_name'] ? htmlspecialchars($row['dept_name']) : '<em class="text-muted small">Unassigned</em>' ?></td>
                        <td><?= priorityBadge($row['priority']) ?></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td>
                            <?= $row['auto_assigned']
                                ? '<span class="badge badge-purple"><i class="fas fa-magic me-1"></i>Auto</span>'
                                : '<span class="badge bg-secondary">Manual</span>' ?>
                        </td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted opacity-50 mb-3 d-block"></i>
                <p class="text-muted">No complaints matched your search criteria.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif (!$searched): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-filter fa-3x opacity-30 mb-3 d-block"></i>
            <p>Use the filters above and click <strong>Search</strong> to find complaints.</p>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
