<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Generate CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="project_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Project Name', 'Total Tasks', 'Completed Tasks', 'In Progress', 'In Review', 'To Do', 'Your Role']);
    
    if (is_admin()) {
        $stmt = $conn->prepare("
            SELECT p.name AS project_name,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS total_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS completed_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'in_progress' AND t.deleted_at IS NULL) AS in_progress_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'in_review' AND t.deleted_at IS NULL) AS in_review_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'todo' AND t.deleted_at IS NULL) AS todo_tasks,
                   'Admin' AS role
            FROM projects p
            WHERE p.deleted_at IS NULL
        ");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            SELECT p.name AS project_name,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS total_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS completed_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'in_progress' AND t.deleted_at IS NULL) AS in_progress_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'in_review' AND t.deleted_at IS NULL) AS in_review_tasks,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'todo' AND t.deleted_at IS NULL) AS todo_tasks,
                   IF(p.owner_id = ?, 'Owner', 'Member') AS role
            FROM projects p
            JOIN project_members pm ON pm.project_id = p.id
            WHERE pm.user_id = ? AND p.deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $user_id, $user_id);
        $stmt->execute();
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Fetch stats for the UI
if (is_admin()) {
    $stmt = $conn->prepare("
        SELECT p.name AS project_name,
               (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS total_tasks,
               (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS completed_tasks
        FROM projects p
        WHERE p.deleted_at IS NULL
    ");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("
        SELECT p.name AS project_name,
               (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS total_tasks,
               (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS completed_tasks
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.id
        WHERE pm.user_id = ? AND p.deleted_at IS NULL
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}
$analytics = $stmt->get_result();
$analytics_data = $analytics->fetch_all(MYSQLI_ASSOC);

// Fetch overall status counts for the donut chart
if (is_admin()) {
    $stmt_status = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM tasks 
        WHERE deleted_at IS NULL
        GROUP BY status
    ");
    $stmt_status->execute();
} else {
    $stmt_status = $conn->prepare("
        SELECT t.status, COUNT(*) as count 
        FROM tasks t
        JOIN project_members pm ON pm.project_id = t.project_id
        WHERE pm.user_id = ? AND t.deleted_at IS NULL
        GROUP BY t.status
    ");
    $stmt_status->bind_param('i', $user_id);
    $stmt_status->execute();
}
$status_res = $stmt_status->get_result();
$status_counts = ['todo' => 0, 'in_progress' => 0, 'in_review' => 0, 'done' => 0];
while ($row = $status_res->fetch_assoc()) {
    $status_counts[$row['status']] = (int)$row['count'];
}

$project_names = [];
$project_totals = [];
$project_completed = [];
foreach ($analytics_data as $row) {
    $project_names[] = $row['project_name'];
    $project_totals[] = $row['total_tasks'];
    $project_completed[] = $row['completed_tasks'];
}

$page_title = 'Reports & Analytics';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Reports & Analytics</h1>
    <a href="reports.php?export=csv" class="btn btn-primary"><i class="ph ph-download-simple"></i> Export to CSV</a>
</div>

<!-- Charts Section -->
<div style="display:flex; gap:24px; margin-bottom:24px; flex-wrap:wrap;">
    <!-- Donut Chart -->
    <div class="card" style="flex:1; min-width:300px;">
        <h2 style="font-size:1.1rem; margin-bottom:16px;">Overall Task Status</h2>
        <div style="position: relative; height:250px; width:100%; display:flex; justify-content:center;">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
    
    <!-- Bar Chart -->
    <div class="card" style="flex:2; min-width:400px;">
        <h2 style="font-size:1.1rem; margin-bottom:16px;">Project Completion Rates</h2>
        <div style="position: relative; height:250px; width:100%;">
            <canvas id="projectChart"></canvas>
        </div>
    </div>
</div>

<h2 style="font-size:1.25rem; margin-bottom:16px;">Detailed Analytics</h2>
<div class="card" style="padding:0; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse; text-align:left;">
        <thead>
            <tr style="background:var(--bg-color); border-bottom:1px solid var(--border-color);">
                <th style="padding:16px 24px; font-weight:600; color:var(--text-muted);">Project Name</th>
                <th style="padding:16px 24px; font-weight:600; color:var(--text-muted);">Total Tasks</th>
                <th style="padding:16px 24px; font-weight:600; color:var(--text-muted);">Completed Tasks</th>
                <th style="padding:16px 24px; font-weight:600; color:var(--text-muted);">Completion Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($analytics_data)): ?>
                <tr><td colspan="4" style="padding:24px; text-align:center; color:var(--text-muted);">No data available.</td></tr>
            <?php else: ?>
                <?php foreach ($analytics_data as $row): ?>
                    <?php 
                        $rate = $row['total_tasks'] > 0 ? round(($row['completed_tasks'] / $row['total_tasks']) * 100) : 0; 
                    ?>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:16px 24px; font-weight:500;"><?php echo h($row['project_name']); ?></td>
                        <td style="padding:16px 24px;"><?php echo (int)$row['total_tasks']; ?></td>
                        <td style="padding:16px 24px;"><?php echo (int)$row['completed_tasks']; ?></td>
                        <td style="padding:16px 24px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div class="progress-bar" style="width:100px; flex-shrink:0;">
                                    <div class="progress-fill fill-done" style="width:<?php echo $rate; ?>%;"></div>
                                </div>
                                <span style="font-size:0.85rem; font-weight:600;"><?php echo $rate; ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Data from PHP
    const statusCounts = <?php echo json_encode($status_counts); ?>;
    const projectNames = <?php echo json_encode($project_names); ?>;
    const projectTotals = <?php echo json_encode($project_totals); ?>;
    const projectCompleted = <?php echo json_encode($project_completed); ?>;

    // Status Donut Chart
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['To Do', 'In Progress', 'In Review', 'Done'],
            datasets: [{
                data: [
                    statusCounts.todo, 
                    statusCounts.in_progress, 
                    statusCounts.in_review, 
                    statusCounts.done
                ],
                backgroundColor: [
                    '#e2e8f0', // todo (gray)
                    '#FF9F43', // in_progress (orange)
                    '#4372ff', // in_review (blue)
                    '#249F6B'  // done (green)
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { font: { family: "'Inter', sans-serif" } }
                }
            },
            cutout: '70%'
        }
    });

    // Project Bar Chart
    const ctxProject = document.getElementById('projectChart').getContext('2d');
    new Chart(ctxProject, {
        type: 'bar',
        data: {
            labels: projectNames,
            datasets: [
                {
                    label: 'Completed Tasks',
                    data: projectCompleted,
                    backgroundColor: '#249F6B',
                    borderRadius: 4
                },
                {
                    label: 'Total Tasks',
                    data: projectTotals,
                    backgroundColor: '#e2e8f0',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, font: { family: "'Inter', sans-serif" } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    ticks: { font: { family: "'Inter', sans-serif" } },
                    grid: { display: false }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: "'Inter', sans-serif" } }
                }
            }
        }
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
