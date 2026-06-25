<?php
// dashboard.php
session_start();
require_once 'database/connect.php';

// Force authentication gateway protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Security Check: If a regular user tries to sneak into the Admin dashboard, reroute them.
if ($_SESSION['role'] !== 'Admin') {
    header("Location: employee_dashboard.php");
    exit();
}

$admin_name = $_SESSION['name'];

// --- REAL-TIME DATABASE AGGREGATION QUERIES ---
try {
    // 1. Total Metrics Counters
    $total_projects = $conn->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $total_tasks = $conn->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $completed_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE status = 'Completed'")->fetchColumn();
    $pending_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE status = 'Pending'")->fetchColumn();
    $in_progress_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'")->fetchColumn();
    $active_employees = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Member'")->fetchColumn();

    // 2. Fetch Projects Summary for the Progress Track Table
    $project_stmt = $conn->query("SELECT project_name, status, end_date FROM projects ORDER BY created_at DESC LIMIT 5");
    $projects_list = $project_stmt->fetchAll();

    // 3. Fetch Recent Tasks Operations Trace
    $task_query = "
        SELECT t.task_name, p.project_name, u.full_name, t.status, t.deadline 
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.project_id
        LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
        LEFT JOIN users u ON ta.user_id = u.user_id
        ORDER BY t.task_id DESC LIMIT 5";
    $tasks_list = $conn->query($task_query)->fetchAll();

} catch (PDOException $e) {
    die("Database Query System Fault: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Core Orchestrator | NextGen AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --panel-bg: rgba(30, 41, 59, 0.45);
            --panel-hover: rgba(30, 41, 59, 0.65);
            --accent-primary: #6366f1;
            --accent-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --status-success: #10b981;
            --status-warning: #f59e0b;
            --status-danger: #ef4444;
            --status-info: #06b6d4;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-gradient); min-height: 100vh; color: var(--text-main); display: flex; }

        /* Left Control Panel Sidebar */
        .sidebar {
            width: 260px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 2rem 1.5rem;
            position: fixed;
            height: 100vh;
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(to right, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item a {
            display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1rem;
            color: var(--text-muted); text-decoration: none; border-radius: 12px;
            transition: all 0.3s; font-weight: 500;
        }
        .nav-item.active a, .nav-item a:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .nav-item.active a { background: var(--accent-primary); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        /* Main Workspace Interface Area */
        .main-content { margin-left: 260px; flex: 1; padding: 2rem 2.5rem; max-width: 1400px; }

        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;
            background: var(--panel-bg); backdrop-filter: blur(12px); padding: 1.25rem 2rem;
            border-radius: 20px; border: 1px solid var(--border-color);
        }

        .header-welcome h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; }
        .header-welcome p { font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem; }
        .header-meta-actions { display: flex; align-items: center; gap: 1.5rem; }

        .profile-badge {
            display: flex; align-items: center; gap: 0.75rem; background: rgba(255, 255, 255, 0.05);
            padding: 0.5rem 1rem; border-radius: 30px; border: 1px solid var(--border-color);
        }
        .profile-avatar {
            width: 32px; height: 32px; border-radius: 50%; background: var(--accent-gradient);
            display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.875rem;
        }

        /* AI Cognitive Section Graphics styling */
        .ai-suggestions-panel {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(168, 85, 247, 0.15) 100%);
            border: 1px solid rgba(168, 85, 247, 0.3); border-radius: 24px; padding: 1.7rem; margin-bottom: 2.5rem;
        }
        .ai-header-title {
            display: flex; align-items: center; gap: 0.5rem; font-size: 1.2rem; font-weight: 600; margin-bottom: 1.25rem;
            background: linear-gradient(to right, #a5b4fc, #e9d5ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .ai-insights-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; list-style: none; }
        .ai-insight-item {
            background: rgba(15, 23, 42, 0.4); padding: 1rem 1.25rem; border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05); display: flex; align-items: flex-start; gap: 0.75rem; font-size: 0.9rem;
        }
        .ai-insight-item i { color: #c084fc; margin-top: 0.15rem; }

        /* Stats Grid Matrix */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem; }
        .stat-card {
            background: var(--panel-bg); border: 1px solid var(--border-color); padding: 1.5rem;
            border-radius: 20px; display: flex; flex-direction: column; gap: 0.5rem; transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-icon-row { display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 1.25rem; }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { font-size: 0.875rem; color: var(--text-muted); }

        /* Data Layout Systems split layout */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .dashboard-panel { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 1.7rem; }
        .panel-header { margin-bottom: 1.25rem; }
        .panel-header h2 { font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }

        .custom-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.925rem; }
        .custom-table th { padding: 0.85rem 1rem; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
        .custom-table td { padding: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.04); }

        .status-pill { display: inline-flex; padding: 0.25rem 0.75rem; border-radius: 30px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-pill.active { background: rgba(6, 182, 212, 0.15); color: var(--status-info); }
        .status-pill.completed { background: rgba(16, 185, 129, 0.15); color: var(--status-success); }
        .status-pill.pending { background: rgba(245, 158, 11, 0.15); color: var(--status-warning); }

        .charts-row { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem; }
        .chart-container { position: relative; height: 220px; width: 100%; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-brain-circuit"></i> Admin Core</div>
        <nav>
            <ul class="nav-list">
                <li class="nav-item active"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="projects.php"><i class="fa-solid fa-folder-open"></i> Projects</a></li>
                <li class="nav-item"><a href="tasks.php"><i class="fa-solid fa-list-check"></i> Tasks</a></li>
                <li class="nav-item"><a href="team.php"><i class="fa-solid fa-users"></i> Team Members</a></li>
                <li class="nav-item"><a href="ai_assignment.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Assignment Matrix</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
                <li class="nav-item" style="margin-top: 3rem;"><a href="logout.php" style="color: var(--status-danger);"><i class="fa-solid fa-power-off"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        
        <header class="dashboard-header">
            <div class="header-welcome">
                <h1>Welcome, <?php echo htmlspecialchars($admin_name); ?> 👑</h1>
                <p>Enterprise Workspace Environment Monitor Console</p>
            </div>
            <div class="header-meta-actions">
                <div style="font-size: 0.875rem; text-align: right; color: var(--text-muted);">
                    <div style="color: var(--text-main); font-weight:600;" id="liveDate"></div>
                    <div id="liveTime"></div>
                </div>
                <div class="profile-badge">
                    <div class="profile-avatar">A</div>
                    <span>System Admin</span>
                </div>
            </div>
        </header>

        <section class="ai-suggestions-panel">
            <h3 class="ai-header-title"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Project Strategy Insights</h3>
            <ul class="ai-insights-list">
                <li class="ai-insight-item"><i class="fa-solid fa-triangle-exclamation"></i> <span><strong>Workload Warning:</strong> Ravi has too many active tasks allocated in the current sprint loop.</span></li>
                <li class="ai-insight-item"><i class="fa-solid fa-lightbulb"></i> <span><strong>Routing Proposal:</strong> Assign next incoming backend tasks to Kumar to optimize delivery rate profiles.</span></li>
                <li class="ai-insight-item"><i class="fa-solid fa-bolt"></i> <span><strong>Resource Slack Alert:</strong> The testing department node indicates clear capacity bandwidth.</span></li>
            </ul>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-row"><span>Total Projects</span><i class="fa-solid fa-folder" style="color: var(--status-info);"></i></div>
                <div class="stat-value"><?php echo $total_projects; ?></div>
                <div class="stat-label">Portfolios Managed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-row"><span>Total Tasks</span><i class="fa-solid fa-layer-group" style="color: var(--accent-primary);"></i></div>
                <div class="stat-value"><?php echo $total_tasks; ?></div>
                <div class="stat-label">Assigned Units</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-row"><span>Completed</span><i class="fa-solid fa-circle-check" style="color: var(--status-success);"></i></div>
                <div class="stat-value"><?php echo $completed_tasks; ?></div>
                <div class="stat-label">Production Settled</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-row"><span>Pending</span><i class="fa-solid fa-clock" style="color: var(--status-warning);"></i></div>
                <div class="stat-value"><?php echo $pending_tasks; ?></div>
                <div class="stat-label">Awaiting Dispatch</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-row"><span>Active Nodes</span><i class="fa-solid fa-users" style="color: #cbd5e1;"></i></div>
                <div class="stat-value"><?php echo $active_employees; ?></div>
                <div class="stat-label">Team Members</div>
            </div>
        </section>

        <section class="charts-row">
            <div class="dashboard-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-chart-pie" style="color: #a855f7;"></i> Task Matrix</h2></div>
                <div class="chart-container"><canvas id="taskPieChart"></canvas></div>
            </div>
            <div class="dashboard-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-chart-bar" style="color: #6366f1;"></i> Project Status Aggregation</h2></div>
                <div class="chart-container"><canvas id="projectBarChart"></canvas></div>
            </div>
        </section>

        <div class="dashboard-grid">
            <section class="dashboard-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-diagram-project" style="color: var(--status-info);"></i> Active Portfolios Progress</h2></div>
                <div class="data-table-wrapper">
                    <table class="custom-table">
                        <thead>
                            <tr><th>Project Identity Name</th><th>Status Flag</th><th>Target Milestone Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projects_list)): ?>
                                <tr><td colspan="3" style="color: var(--text-muted); text-align: center;">No registered operational projects recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($projects_list as $proj): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($proj['project_name']); ?></strong></td>
                                    <td><span class="status-pill <?php echo strtolower($proj['status']) === 'active' ? 'active' : 'pending'; ?>"><?php echo htmlspecialchars($proj['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($proj['end_date'] ?? 'No Date Specified'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-bullseye" style="color: var(--status-warning);"></i> Recent Operation Vectors</h2></div>
                <div class="data-table-wrapper">
                    <table class="custom-table">
                        <thead>
                            <tr><th>Task Trace</th><th>Node Target</th><th>Execution</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tasks_list)): ?>
                                <tr><td colspan="3" style="color: var(--text-muted); text-align: center;">No recorded task execution vectors.</td></tr>
                            <?php else: ?>
                                <?php foreach ($tasks_list as $tsk): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($tsk['task_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($tsk['full_name'] ?? 'Unassigned'); ?></td>
                                    <td><span class="status-pill <?php echo strtolower($tsk['status']) === 'completed' ? 'completed' : 'pending'; ?>"><?php echo htmlspecialchars($tsk['status']); ?></span></td>
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
        function updateClock() {
            const d = new Date();
            document.getElementById('liveDate').innerText = d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' });
            document.getElementById('liveTime').innerText = d.toLocaleTimeString('en-US');
        }
        setInterval(updateClock, 1000); updateClock();

        const chartConfigOptions = { plugins: { legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } } }, responsive: true, maintainAspectRatio: false };

        // Pie Analytics Render Block
        new Chart(document.getElementById('taskPieChart'), {
            type: 'pie',
            data: {
                labels: ['Completed', 'Pending', 'In Progress'],
                datasets: [{
                    data: [<?php echo $completed_tasks; ?>, <?php echo $pending_tasks; ?>, <?php echo $in_progress_tasks; ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#6366f1'],
                    borderWidth: 0
                }]
            },
            options: chartConfigOptions
        });

        // Bar Analytics Render Block
        new Chart(document.getElementById('projectBarChart'), {
            type: 'bar',
            data: {
                labels: ['Active Projects', 'Pending/Planning Frameworks'],
                datasets: [{
                    label: 'Portfolio Count Structural Volume',
                    data: [
                        <?php echo count(array_filter($projects_list, function($p) { return $p['status'] === 'Active'; })); ?>, 
                        <?php echo count(array_filter($projects_list, function($p) { return $p['status'] === 'Planning'; })); ?>
                    ],
                    backgroundColor: ['#6366f1', '#a855f7'], borderRadius: 6
                }]
            },
            options: {
                ...chartConfigOptions,
                scales: {
                    x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                    y: { ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });
    </script>
</body>
</html>