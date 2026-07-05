<?php
// ============================================================
// admin/manage_users.php — User Management
// QuickResolve_18 – Smart Complaint Management System
// ============================================================

session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

// ── Handle user actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Approve user
    if (isset($_POST['approve_user'])) {
        $uid = (int)$_POST['user_id'];
        $conn->query("UPDATE users SET status='active' WHERE id=$uid AND role='user'");
        setFlash('success', 'User approved and activated successfully.');
    }

    // Block user
    if (isset($_POST['block_user'])) {
        $uid = (int)$_POST['user_id'];
        $conn->query("UPDATE users SET status='blocked' WHERE id=$uid AND role='user'");
        setFlash('warning', 'User has been blocked.');
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $uid = (int)$_POST['user_id'];
        $conn->query("DELETE FROM users WHERE id=$uid AND role='user'");
        setFlash('danger', 'User deleted.');
    }

    // Add department account
    if (isset($_POST['add_dept_user'])) {
        $name   = sanitize($conn, $_POST['name']);
        $email  = sanitize($conn, $_POST['email']);
        $pass   = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $deptId = (int)$_POST['dept_id'];

        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            setFlash('danger', 'Email already exists.');
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name,email,password,role,dept_id,status) VALUES(?,?,?,'department',?,'active')");
            $stmt->bind_param('sssi', $name, $email, $pass, $deptId);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Department user account created.');
        }
    }

    redirect(SITE_URL . '/admin/manage_users.php');
}

//  Fetch users
$filterStatus = sanitize($conn, $_GET['filter'] ?? '');
$where = "WHERE role='user'";
if ($filterStatus) $where .= " AND status='$filterStatus'";

$users = $conn->query("
    SELECT u.*, (SELECT COUNT(*) FROM complaints c WHERE c.user_id=u.id) AS complaint_count
    FROM users u
    $where
    ORDER BY u.created_at DESC
");

// Dept users list
$deptUsers = $conn->query("
    SELECT u.*, d.name AS dept_name
    FROM users u
    LEFT JOIN departments d ON u.dept_id = d.id
    WHERE u.role='department'
    ORDER BY u.name
");

// Departments for add-dept-user form
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

$pageTitle = 'Manage Users';
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
        <a href="archive.php"            class="sidebar-link"><i class="fas fa-archive"></i> Archive</a>
        <div class="sidebar-section-label">Management</div>
        <a href="manage_users.php"       class="sidebar-link active"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <div class="sidebar-section-label">Account</div>
        <a href="../logout.php"          class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <main class="qr-main-content">
        <div class="page-header">
            <h1><i class="fas fa-users me-2 text-primary"></i>Manage Users</h1>
            <p class="text-muted mb-0">Approve, block, and manage system user accounts</p>
        </div>

        <?php showFlash(); ?>

        <!-- Filter tabs -->
        <div class="d-flex gap-2 mb-4 flex-wrap">
            <a href="manage_users.php"                 class="btn btn-sm <?= !$filterStatus ? 'btn-primary' : 'btn-outline-secondary' ?>">All Users</a>
            <a href="manage_users.php?filter=pending"  class="btn btn-sm <?= $filterStatus==='pending'  ? 'btn-warning' : 'btn-outline-secondary' ?>">Pending Approval</a>
            <a href="manage_users.php?filter=active"   class="btn btn-sm <?= $filterStatus==='active'   ? 'btn-success' : 'btn-outline-secondary' ?>">Active</a>
            <a href="manage_users.php?filter=blocked"  class="btn btn-sm <?= $filterStatus==='blocked'  ? 'btn-danger'  : 'btn-outline-secondary' ?>">Blocked</a>
        </div>

        <!-- Users table -->
        <div class="qr-form-card mb-4">
            <h5 class="fw-bold mb-4"><i class="fas fa-user me-2 text-primary"></i>Regular Users</h5>
            <div class="table-responsive">
                <table class="qr-table">
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Email</th><th>Status</th><th>Complaints</th><th>Registered</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($users->num_rows > 0):
                        while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#3B5BDB,#6366F1);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.8rem">
                                        <?= strtoupper(substr($u['name'],0,1)) ?>
                                    </div>
                                    <span class="fw-semibold"><?= htmlspecialchars($u['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php
                                $statusMap = ['active'=>'success','pending'=>'warning','blocked'=>'danger'];
                                $statusColor = $statusMap[$u['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($u['status']) ?></span>
                            </td>
                            <td><span class="fw-semibold text-primary"><?= $u['complaint_count'] ?></span></td>
                            <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($u['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="approve_user" class="btn btn-success btn-sm"
                                                data-bs-toggle="tooltip" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($u['status'] === 'active'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="block_user" class="btn btn-warning btn-sm"
                                                data-bs-toggle="tooltip" title="Block User"
                                                data-confirm="Block this user?">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($u['status'] === 'blocked'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="approve_user" class="btn btn-success btn-sm"
                                                data-bs-toggle="tooltip" title="Unblock">
                                            <i class="fas fa-unlock"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm"
                                                data-confirm="Delete this user? This cannot be undone."
                                                data-bs-toggle="tooltip" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Department Users -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="qr-form-card">
                    <h5 class="fw-bold mb-4"><i class="fas fa-building me-2 text-purple"></i>Department Accounts</h5>
                    <div class="table-responsive">
                        <table class="qr-table">
                            <thead>
                                <tr><th>Name</th><th>Email</th><th>Department</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($du = $deptUsers->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($du['name']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($du['email']) ?></td>
                                <td><span class="badge badge-purple"><?= htmlspecialchars($du['dept_name'] ?? 'N/A') ?></span></td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Department User Form 
            <div class="col-lg-5">
                <div class="qr-form-card">
                    <h5 class="fw-bold mb-4"><i class="fas fa-plus-circle me-2 text-success"></i>Add Department Account</h5>
                    <form method="POST" action="manage_users.php" class="qr-validate" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" placeholder="Department Manager Name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="dept@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Set a strong password" required>
                        </div> -->
                        <!-- <div class="mb-4">
                            <label class="form-label">Assign Department</label>
                            <select class="form-select" name="dept_id" required>
                                <option value="">— Select —</option>
                                <?php while ($d = $departments->fetch_assoc()): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div> -->
                        <!--<button type="submit" name="add_dept_user" class="btn btn-success w-100">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                </div>
            </div> -->
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
