<?php
// admin/manage_departments.php — Department Management
// QuickResolve_18 – Smart Complaint Management System

session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

//  Handle department actions 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add new department
    if (isset($_POST['add_dept'])) {
        $name  = sanitize($conn, $_POST['name']);
        $desc  = sanitize($conn, $_POST['description'] ?? '');
        $email = sanitize($conn, $_POST['email'] ?? '');

        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO departments (name, description, email) VALUES(?,?,?)");
            $stmt->bind_param('sss', $name, $desc, $email);
            $stmt->execute();
            $stmt->close();
            setFlash('success', "Department '$name' created successfully.");
        } else {
            setFlash('danger', 'Department name is required.');
        }
    }

    // Delete department
    if (isset($_POST['delete_dept'])) {
        $did = (int)$_POST['dept_id'];
        // Check if complaints are assigned
        $count = $conn->query("SELECT COUNT(*) FROM complaints WHERE dept_id=$did")->fetch_row()[0];
        if ($count > 0) {
            setFlash('danger', "Cannot delete — $count complaint(s) are assigned to this department.");
        } else {
            $conn->query("DELETE FROM departments WHERE id=$did");
            $conn->query("UPDATE users SET dept_id=NULL WHERE dept_id=$did");
            setFlash('success', 'Department deleted.');
        }
    }

    redirect(SITE_URL . '/admin/manage_departments.php');
}

// Fetch departments with complaint counts
$departments = $conn->query("
    SELECT d.*,
           COUNT(c.id) AS complaint_count,
           SUM(c.status='completed') AS completed_count,
           SUM(c.status IN('assigned','in_progress')) AS active_count
    FROM departments d
    LEFT JOIN complaints c ON c.dept_id = d.id
    GROUP BY d.id
    ORDER BY d.name
");

$pageTitle = 'Manage Departments';
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
        <a href="manage_users.php"       class="sidebar-link"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_departments.php" class="sidebar-link active"><i class="fas fa-building"></i> Departments</a>
        <div class="sidebar-section-label">Account</div>
        <a href="../logout.php"          class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <main class="qr-main-content">
        <div class="page-header">
            <h1><i class="fas fa-building me-2 text-primary"></i>Manage Departments</h1>
            <p class="text-muted mb-0">Create and manage departments that handle complaints</p>
        </div>

        <?php showFlash(); ?>

        <div class="row g-4">
            <!-- Departments list -->
            <div class="col-lg-8">
                <div class="row g-4">
                <?php while ($d = $departments->fetch_assoc()):
                    $icons = [
                        'Electrical'=>'fa-bolt','Plumbing'=>'fa-tint','Housekeeping'=>'fa-broom',
                        'IT Support'=>'fa-laptop','Maintenance'=>'fa-tools','Security'=>'fa-shield-alt'
                    ];
                    $icon = $icons[$d['name']] ?? 'fa-building';
                ?>
                <div class="col-md-6">
                    <div class="qr-form-card h-100">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="feature-icon-wrap bg-primary-soft mb-0">
                                    <i class="fas <?= $icon ?> text-primary"></i>
                                </div>s
                                <div>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($d['name']) ?></h6>
                                    <div class="small text-muted"><?= htmlspecialchars($d['email'] ?: 'No email') ?></div>
                                </div>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                                <button type="submit" name="delete_dept"
                                        class="btn btn-outline-danger btn-sm"
                                        data-confirm="Delete this department?"
                                        data-bs-toggle="tooltip" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($d['description'] ?: 'No description available.') ?></p>
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="fw-bold text-primary"><?= $d['complaint_count'] ?></div>
                                <div class="small text-muted">Total</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold" style="color:var(--purple)"><?= $d['active_count'] ?></div>
                                <div class="small text-muted">Active</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-success"><?= $d['completed_count'] ?></div>
                                <div class="small text-muted">Done</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                </div>
            </div>
            
            <!-- Add Department Form ORIGINAL-->
            <div class="col-lg-4">
                <div class="qr-form-card">
                    <h5 class="fw-bold mb-4"><i class="fas fa-plus-circle me-2 text-success"></i>Add New Department</h5>
                    <form method="POST" class="qr-validate" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name"
                                   placeholder="e.g., Landscaping" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"
                                      placeholder="What does this department handle?"></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Contact Email</label>
                            <input type="email" class="form-control" name="email"
                                   placeholder="dept@quickresolve.com">

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Set a strong password" required>
                        </div>
                        
                        </div>
                        <button type="submit" name="add_dept" class="btn btn-success w-100">
                            <i class="fas fa-plus me-2"></i>Create Department
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
