<?php
// admin/analytics.php — Analytics & Reports Dashboard

session_start();
require_once '../config/db_18.php';
require_once '../includes/auth_check.php';

requireRole('admin');

//  Fetch all analytics data

// Status counts for pie chart
$statusData = [];
$statusRes  = $conn->query("SELECT status, COUNT(*) cnt FROM complaints GROUP BY status");
while ($r = $statusRes->fetch_assoc()) $statusData[$r['status']] = $r['cnt'];

// Department-wise counts
$deptLabels = []; $deptTotal = []; $deptCompleted = [];
$deptRes = $conn->query("
    SELECT d.name,
           COUNT(c.id) AS total,
           SUM(c.status='completed') AS completed
    FROM departments d
    LEFT JOIN complaints c ON c.dept_id = d.id
    GROUP BY d.id ORDER BY total DESC
");
while ($r = $deptRes->fetch_assoc()) {
    $deptLabels[]    = $r['name'];
    $deptTotal[]     = (int)$r['total'];
    $deptCompleted[] = (int)$r['completed'];
}

// Priority distribution
$priorityData = [];
$prioRes = $conn->query("SELECT priority, COUNT(*) cnt FROM complaints GROUP BY priority ORDER BY FIELD(priority,'critical','high','medium','low')");
while ($r = $prioRes->fetch_assoc()) $priorityData[$r['priority']] = $r['cnt'];

// Monthly trend (last 6 months)
$monthLabels = []; $monthData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $count = $conn->query("SELECT COUNT(*) FROM complaints WHERE DATE_FORMAT(created_at,'%Y-%m')='$month'")->fetch_row()[0];
    $monthLabels[] = $label;
    $monthData[]   = (int)$count;
}

// Average rating per department
$ratingData = $conn->query("
    SELECT d.name, ROUND(AVG(f.rating),1) AS avg_rating, COUNT(f.id) AS feedback_count
    FROM departments d
    JOIN complaints c ON c.dept_id = d.id
    JOIN feedback f ON f.complaint_id = c.id
    GROUP BY d.id ORDER BY avg_rating DESC
");

// Overall stats
$totalComplaints = $conn->query("SELECT COUNT(*) FROM complaints")->fetch_row()[0];
$autoRouted      = $conn->query("SELECT COUNT(*) FROM complaints WHERE auto_assigned=1")->fetch_row()[0];
$avgRating       = $conn->query("SELECT ROUND(AVG(rating),1) FROM feedback")->fetch_row()[0] ?? 0;
$completionRate  = $totalComplaints ? round(($statusData['completed'] ?? 0) / $totalComplaints * 100) : 0;

$pageTitle = 'Analytics';
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
        <a href="analytics.php"          class="sidebar-link active"><i class="fas fa-chart-bar"></i> Analytics</a>
        <div class="sidebar-section-label">Complaints</div>
        <a href="view_complaints.php"    class="sidebar-link"><i class="fas fa-clipboard-list"></i> All Complaints</a>
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
        <div class="page-header">
            <h1><i class="fas fa-chart-bar me-2 text-primary"></i>Analytics & Reports</h1>
            <p class="text-muted mb-0">System-wide complaint and performance metrics</p>
        </div>

        <!-- KPI Cards Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="dash-card primary">
                    <div class="card-icon bg-primary-soft"><i class="fas fa-clipboard-list text-primary"></i></div>
                    <div class="card-num" data-count="<?= $totalComplaints ?>"><?= $totalComplaints ?></div>
                    <div class="card-label">Total Complaints</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="dash-card success">
                    <div class="card-icon bg-success-soft"><i class="fas fa-check-double text-success"></i></div>
                    <div class="card-num"><?= $completionRate ?>%</div>
                    <div class="card-label">Completion Rate</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="dash-card purple">
                    <div class="card-icon bg-purple-soft"><i class="fas fa-magic text-purple"></i></div>
                    <div class="card-num" data-count="<?= $autoRouted ?>"><?= $autoRouted ?></div>
                    <div class="card-label">Auto-Routed</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="dash-card warning">
                    <div class="card-icon bg-warning-soft"><i class="fas fa-star text-warning"></i></div>
                    <div class="card-num"><?= $avgRating ?: 'N/A' ?></div>
                    <div class="card-label">Avg. Rating</div>
                </div>
            </div>
        </div>

        <!-- Chart Row 1 -->
        <div class="row g-4 mb-4">
            <!-- Monthly Trend Line Chart -->
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-chart-line me-2 text-primary"></i>Monthly Complaint Trend (Last 6 Months)</div>
                    <canvas id="trendChart" height="90"></canvas>
                </div>
            </div>
            <!-- Priority Doughnut -->
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-flag me-2 text-warning"></i>Priority Distribution</div>
                    <canvas id="priorityChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Chart Row 2 -->
        <div class="row g-4 mb-4">
            <!-- Dept Stacked Bar -->
            <div class="col-lg-7">
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-building me-2 text-purple"></i>Department Performance (Total vs Completed)</div>
                    <canvas id="deptPerformChart" height="110"></canvas>
                </div>
            </div>
            <!-- Status Pie -->
            <div class="col-lg-5">
                <div class="chart-card">
                    <div class="chart-title"><i class="fas fa-chart-pie me-2 text-success"></i>Status Breakdown</div>
                    <canvas id="statusPieChart" height="180"></canvas>
                </div>
            </div>
        </div>

        <!-- Department Ratings Table -->
        <div class="qr-form-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-star me-2 text-warning"></i>Department Satisfaction Ratings</h5>
            <?php if ($ratingData->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="qr-table">
                    <thead>
                        <tr><th>Department</th><th>Avg Rating</th><th>Feedback Count</th><th>Visual</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($r = $ratingData->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                        <td>
                            <?php for ($i=1; $i<=5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $r['avg_rating'] ? 'text-warning' : 'text-muted' ?>" style="font-size:0.85rem"></i>
                            <?php endfor; ?>
                            <span class="ms-2 fw-bold"><?= $r['avg_rating'] ?>/5</span>
                        </td>
                        <td><?= $r['feedback_count'] ?> reviews</td>
                        <td style="width:200px">
                            <div class="progress" style="height:8px;border-radius:50px">
                                <div class="progress-bar bg-warning" style="width:<?= ($r['avg_rating']/5)*100 ?>%;border-radius:50px"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted text-center py-3">No feedback data available yet.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Chart.js configurations for all 4 charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    //  Monthly Trend Line Chart 
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Complaints',
                data: <?= json_encode($monthData) ?>,
                borderColor: '#3B5BDB',
                backgroundColor: 'rgba(59,91,219,0.10)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3B5BDB',
                pointRadius: 5,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 1200 },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#F1F5F9' } },
                x: { grid: { display: false } }
            }
        }
    });

    //  Priority Doughnut 
    new Chart(document.getElementById('priorityChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Critical','High','Medium','Low'],
            datasets: [{
                data: [
                    <?= $priorityData['critical'] ?? 0 ?>,
                    <?= $priorityData['high']     ?? 0 ?>,
                    <?= $priorityData['medium']   ?? 0 ?>,
                    <?= $priorityData['low']      ?? 0 ?>
                ],
                backgroundColor: ['#EF4444','#F97316','#F59E0B','#10B981'],
                borderWidth: 3, borderColor: '#fff', hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 1200, easing: 'easeOutBounce' },
            plugins: { legend: { position: 'bottom', labels: { padding: 14, font: { size: 11 } } } },
            cutout: '60%'
        }
    });

    // ── Department Performance Bar Chart
    new Chart(document.getElementById('deptPerformChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($deptLabels) ?>,
            datasets: [
                {
                    label: 'Total',
                    data: <?= json_encode($deptTotal) ?>,
                    backgroundColor: 'rgba(59,91,219,0.65)',
                    borderColor: '#3B5BDB',
                    borderWidth: 2,
                    borderRadius: 6,
                },
                {
                    label: 'Completed',
                    data: <?= json_encode($deptCompleted) ?>,
                    backgroundColor: 'rgba(16,185,129,0.65)',
                    borderColor: '#10B981',
                    borderWidth: 2,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            animation: { duration: 1200 },
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#F1F5F9' } },
                x: { grid: { display: false } }
            }
        }
    });

    // ── Status Pie Chart
    new Chart(document.getElementById('statusPieChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys(array_map(fn($k)=>ucfirst(str_replace('_',' ',$k)), $statusData))) ?>,
            datasets: [{
                data: <?= json_encode(array_values($statusData)) ?>,
                backgroundColor: ['#F59E0B','#3B5BDB','#8B5CF6','#10B981','#EF4444'],
                borderWidth: 3, borderColor: '#fff', hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 1400 },
            plugins: { legend: { position: 'bottom', labels: { padding: 14, font: { size: 11 } } } }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
