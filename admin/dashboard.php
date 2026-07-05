<?php
// ============================================================
// admin/dashboard.php — Admin Dashboard
// QuickResolve_18 – Smart Complaint Management System
// ============================================================

session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

// ── Fetch dashboard stats ─────────────────────────────────────
$totalComplaints  = $conn->query("SELECT COUNT(*) FROM complaints")->fetch_row()[0];
$pendingCount     = $conn->query("SELECT COUNT(*) FROM complaints WHERE status='pending'")->fetch_row()[0];
$inProgressCount  = $conn->query("SELECT COUNT(*) FROM complaints WHERE status IN('assigned','in_progress')")->fetch_row()[0];
$completedCount   = $conn->query("SELECT COUNT(*) FROM complaints WHERE status='completed'")->fetch_row()[0];
$totalUsers       = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0];
$pendingApprovals = $conn->query("SELECT COUNT(*) FROM users WHERE role='user' AND status='pending'")->fetch_row()[0];
$totalDepts       = $conn->query("SELECT COUNT(*) FROM departments")->fetch_row()[0];

// Recent 8 complaints for the table
$recent = $conn->query("
    SELECT c.id, c.title, c.priority, c.status, c.created_at, c.auto_assigned,
           u.name AS user_name, d.name AS dept_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.dept_id = d.id
    ORDER BY c.created_at DESC
    LIMIT 8
");

// Department-wise complaint count for chart
$deptStats = $conn->query("
    SELECT d.name, COUNT(c.id) AS total
    FROM departments d
    LEFT JOIN complaints c ON c.dept_id = d.id
    GROUP BY d.id, d.name
    ORDER BY total DESC
");
$deptLabels = [];
$deptData   = [];
while ($r = $deptStats->fetch_assoc()) {
    $deptLabels[] = $r['name'];
    $deptData[]   = (int)$r['total'];
}

// Status distribution for pie chart
$statusStats = $conn->query("SELECT status, COUNT(*) AS cnt FROM complaints GROUP BY status");
$pieLabels = [];
$pieData   = [];
while ($r = $statusStats->fetch_assoc()) {
    $pieLabels[] = ucfirst(str_replace('_', ' ', $r['status']));
    $pieData[]   = (int)$r['cnt'];
}

$pageTitle = 'Admin Dashboard';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="dashboard-layout">

    <!-- Admin Sidebar -->
    <aside class="qr-sidebar">
        <div class="sidebar-user-card">
            <div class="d-flex align-items-center gap-2">
                <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#F59E0B,#EF4444);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700">
                    A
                </div>
                <div>
                    <div class="text-white fw-semibold small"><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div style="color:rgba(255,255,255,0.45);font-size:0.75rem">Super Admin</div>
                </div>
            </div>
        </div>

        <div class="sidebar-section-label">Overview</div>
        <a href="dashboard.php"          class="sidebar-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="analytics.php"          class="sidebar-link"><i class="fas fa-chart-bar"></i> Analytics</a>

        <div class="sidebar-section-label">Complaints</div>
        <a href="view_complaints.php"    class="sidebar-link"><i class="fas fa-clipboard-list"></i> All Complaints</a>
        <a href="assign_complaint.php"   class="sidebar-link"><i class="fas fa-tags"></i> Assign Complaints</a>
        <a href="filter_complaints.php"  class="sidebar-link"><i class="fas fa-filter"></i> Filter & Search</a>
        <a href="archive.php"            class="sidebar-link"><i class="fas fa-archive"></i> Archive</a>

        <div class="sidebar-section-label">Management</div>
        <a href="manage_users.php"       class="sidebar-link"><i class="fas fa-users"></i> Manage Users
            <?php if ($pendingApprovals): ?>
            <span class="badge bg-danger ms-auto"><?= $pendingApprovals ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>

        <div class="sidebar-section-label">Account</div>
        <a href="../logout.php"          class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <!-- Main Content -->
    <main class="qr-main-content">

        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1>Admin Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>. Here's what's happening today.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($pendingApprovals): ?>
                <a href="manage_users.php?filter=pending" class="btn btn-warning btn-sm">
                    <i class="fas fa-bell me-1"></i><?= $pendingApprovals ?> Pending Approvals
                </a>
                <?php endif; ?>
                <a href="view_complaints.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye me-1"></i>View All
                </a>
            </div>
        </div>

        <?php showFlash(); ?>

        <!-- Stat Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="dash-card primary">
                    <div class="card-icon bg-primary-soft"><i class="fas fa-clipboard-list text-primary"></i></div>
                    <div class="card-num" data-count="<?= $totalComplaints ?>"><?= $totalComplaints ?></div>
                    <div class="card-label">Total</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="dash-card warning">
                    <div class="card-icon bg-warning-soft"><i class="fas fa-clock text-warning"></i></div>
                    <div class="card-num" data-count="<?= $pendingCount ?>"><?= $pendingCount ?></div>
                    <div class="card-label">Pending</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="dash-card purple">
                    <div class="card-icon bg-purple-soft"><i class="fas fa-spinner text-purple"></i></div>
                    <div class="card-num" data-count="<?= $inProgressCount ?>"><?= $inProgressCount ?></div>
                    <div class="card-label">In Progress</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="dash-card success">
                    <div class="card-icon bg-success-soft"><i class="fas fa-check-circle text-success"></i></div>
                    <div class="card-num" data-count="<?= $completedCount ?>"><?= $completedCount ?></div>
                    <div class="card-label">Completed</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="dash-card orange">
                    <div class="card-icon bg-orange-soft"><i class="fas fa-users text-orange"></i></div>
                    <div class="card-num" data-count="<?= $totalUsers ?>"><?= $totalUsers ?></div>
                    <div class="card-label">Users</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="dash-card danger">
                    <div class="card-icon bg-danger-soft"><i class="fas fa-building text-danger"></i></div>
                    <div class="card-num" data-count="<?= $totalDepts ?>"><?= $totalDepts ?></div>
                    <div class="card-label">Departments</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-chart-bar me-2 text-primary"></i>Complaints by Department</div>
                    <canvas id="deptChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-chart-pie me-2 text-purple"></i>Status Distribution</div>
                    <canvas id="statusPie" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Complaints Table -->
        <div class="qr-form-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-list me-2 text-primary"></i>Recent Complaints</h5>
                <a href="view_complaints.php" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="qr-table">
                    <thead>
                        <tr>
                            <th>#ID</th><th>Title</th><th>User</th><th>Department</th>
                            <th>Priority</th><th>Status</th><th>Routing</th><th>Date</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-semibold text-primary">#<?= $row['id'] ?></span></td>
                            <td><?= htmlspecialchars(substr($row['title'],0,40)) ?>…</td>
                            <td><?= htmlspecialchars($row['user_name']) ?></td>
                            <td><?= $row['dept_name'] ? htmlspecialchars($row['dept_name']) : '<em class="text-muted small">Unassigned</em>' ?></td>
                            <td><?= priorityBadge($row['priority']) ?></td>
                            <td><?= statusBadge($row['status']) ?></td>
                            <td>
                                <?php if ($row['auto_assigned']): ?>
                                <span class="badge badge-purple"><i class="fas fa-magic me-1"></i>Auto</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                            <td>
                                <a href="view_complaints.php?view=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- Chart.js configuration -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Bar Chart: Complaints by Department ──────────────────
    const deptCtx = document.getElementById('deptChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($deptLabels) ?>,
            datasets: [{
                label: 'Complaints',
                data: <?= json_encode($deptData) ?>,
                backgroundColor: [
                    'rgba(59,91,219,0.7)','rgba(99,102,241,0.7)','rgba(139,92,246,0.7)',
                    'rgba(16,185,129,0.7)','rgba(245,158,11,0.7)','rgba(239,68,68,0.7)'
                ],
                borderColor: [
                    '#3B5BDB','#6366F1','#8B5CF6','#10B981','#F59E0B','#EF4444'
                ],
                borderWidth: 2,
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 1200, easing: 'easeOutQuart' },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#F1F5F9' } },
                x: { grid: { display: false } }
            }
        }
    });

    // ── Pie Chart: Status Distribution ───────────────────────
    const pieCtx = document.getElementById('statusPie').getContext('2d');
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pieLabels) ?>,
            datasets: [{
                data: <?= json_encode($pieData) ?>,
                backgroundColor: ['#F59E0B','#3B5BDB','#8B5CF6','#10B981','#EF4444'],
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 1200, easing: 'easeOutBounce' },
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
