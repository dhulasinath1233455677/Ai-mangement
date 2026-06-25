<?php
// reports.php
session_start();
require_once 'connect.php';

// Auth Guard - Admin Clearance Required
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

try {
    // 1. Calculate Analytical Metrics
    $total_tasks = $conn->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $completed_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE status = 'Completed'")->fetchColumn();
    $pending_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE status = 'Pending'")->fetchColumn();
    $progress_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'")->fetchColumn();

    // Prevent divide-by-zero errors when calculating global completion percentage variables
    $completion_rate = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100, 1) : 0;

    // 2. Fetch Performance Matrix broken down per Employee Node
    $perf_query = "
        SELECT u.full_name, COUNT(ta.assignment_id) as allocated_count,
        SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as finished_count
        FROM users u
        LEFT JOIN task_assignments ta ON u.user_id = ta.user_id
        LEFT JOIN tasks t ON ta.task_id = t.task_id
        WHERE u.role = 'Member'
        GROUP BY u.user_id 
        ORDER BY finished_count DESC";
    $employee_perf = $conn->query($perf_query)->fetchAll();

} catch (PDOException $e) {
    die("Analytics Generation Engine Failure: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Analytics Core | NextGen AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --panel-bg: rgba(30, 41, 59, 0.45);
            --accent-primary: #6366f1;
            --accent-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --status-success: #10b981;
            --status-warning: #f59e0b;
            --status-danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-gradient); min-height: 100vh; color: var(--text-main); display: flex; }

        .sidebar {
            width: 260px; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color); display: flex; flex-direction: column;
            padding: 2rem 1.5rem; position: fixed; height: 100vh;
        }
        .sidebar-brand {
            font-size: 1.5rem; font-weight: 700; background: linear-gradient(to right, #6366f1, #a855f7);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 2.5rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item a { display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1rem; color: var(--text-muted); text-decoration: none; border-radius: 12px; transition: all 0.3s; }
        .nav-item.active a, .nav-item a:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .nav-item.active a { background: var(--accent-primary); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        .main-content { margin-left: 260px; flex: 1; padding: 2rem 2.5rem; max-width: 1400px; }
        .page-header { margin-bottom: 2.5rem; }

        /* Performance layout structures configuration matrix options */
        .analytics-matrix-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .dashboard-panel { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 1.7rem; }
        .panel-header { margin-bottom: 1.5rem; }
        .panel-header h2 { font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }

        .metric-highlight-box { display: flex; align-items: center; justify-content: space-between; background: rgba(15,23,42,0.3); border: 1px solid var(--border-color); padding: 1.25rem 1.75rem; border-radius: 16px; margin-bottom: 1.5rem; }
        .metric-big-num { font-size: 2.25rem; font-weight: 800; background: linear-gradient(to right, #a5b4fc, #e9d5ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .custom-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .custom-table th { padding: 0.85rem 1rem; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 500; }
        .custom-table td { padding: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.04); }

        .chart-container { position: relative; height: 260px; width: 100%; display: flex; justify-content: center; align-items: center; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-brain-circuit"></i> Admin Core</div>
        <nav>
            <ul class="nav-list">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="projects.php"><i class="fa-solid fa-folder-open"></i> Projects</a></li>
                <li class="nav-item"><a href="tasks.php"><i class="fa-solid fa-list-check"></i> Tasks</a></li>
                <li class="nav-item"><a href="team.php"><i class="fa-solid fa-users"></i> Team Members</a></li>
                <li class="nav-item"><a href="ai_assignment.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Assignment</a></li>
                <li class="nav-item active"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
                <li class="nav-item" style="margin-top: 3rem;"><a href="logout.php" style="color: var(--status-danger);"><i class="fa-solid fa-power-off"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <h1 style="font-size: 1.75rem; font-weight: 700;">Executive Analytics System</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Audit structural production variables, operational performance records, and deployment rates data layers.</p>
        </header>

        <section class="metric-highlight-box">
            <div>
                <h3 style="font-size: 0.95rem; font-weight: 500; color: var(--text-muted);">Global Task Completion Rate Matrix</h3>
                <p style="font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-top: 0.15rem;">Aggregated across all registered active workflow branches.</p>
            </div>
            <div class="metric-big-num"><?php echo $completion_rate; ?>%</div>
        </section>

        <div class="analytics-matrix-grid">
            
            <section class="dashboard-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-chart-pie" style="color: #6366f1;"></i> Status Volumetric Mix</h2></div>
                <div class="chart-container">
                    <canvas id="statusDonutChart"></canvas>
                </div>
            </section>

            <section class="dashboard-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-chart-line" style="color: #a855f7;"></i> Human Resource Velocity Output</h2></div>
                <div class="data-table-wrapper">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Employee Identifier Node</th>
                                <th>Assigned Vectors</th>
                                <th>Settled Tasks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employee_perf)): ?>
                                <tr><td colspan="3" style="color: var(--text-muted); text-align: center;">No human resource performance history discovered in system database records.</td></tr>
                            <?php else: ?>
                                <?php foreach($employee_perf as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                    <td><span style="font-weight: 600; color: var(--text-muted);"><?php echo $row['allocated_count']; ?> Tasks</span></td>
                                    <td><span style="font-weight: 600; color: var(--status-success);"><i class="fa-regular fa-circle-check"></i> <?php echo $row['finished_count']; ?> Closed</span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <script>
        const chartGlobalOptions = {
            plugins: { legend: { position: 'right', labels: { color: '#94a3b8', font: { family: 'Inter', size: 12 } } } },
            responsive: true,
            maintainAspectRatio: false
        };

        // Render Status Volumetric Mix Vector Donut Chart
        new Chart(document.getElementById('statusDonutChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'In Progress'],
                datasets: [{
                    data: [<?php echo $completed_tasks; ?>, <?php echo $pending_tasks; ?>, <?php echo $progress_tasks; ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#6366f1'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: chartGlobalOptions
        });
    </script>
</body>
</html>