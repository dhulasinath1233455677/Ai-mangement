<?php
// tasks.php
session_start();
require_once 'connect.php';

// Auth Guard - Admin Clearance Required
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// --- HANDLE FORM SUBMISSIONS (CREATE TASK) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    $project_id = intval($_POST['project_id']);
    $task_name = trim($_POST['task_name']);
    $description = trim($_POST['description']);
    $complexity = $_POST['complexity'];
    $priority = $_POST['priority'];
    $required_skill = trim($_POST['required_skill']);
    $estimated_hours = intval($_POST['estimated_hours']);
    $deadline = $_POST['deadline'];

    if (!empty($task_name) && $project_id > 0) {
        try {
            $stmt = $conn->prepare("INSERT INTO tasks (project_id, task_name, description, complexity, priority, required_skill, estimated_hours, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$project_id, $task_name, $description, $complexity, $priority, $required_skill, $estimated_hours, $deadline]);
            $success_msg = "Task node cleanly generated and queued into the project repository.";
        } catch (PDOException $e) {
            $error_msg = "Database Operational Failure: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill out the Task Name and choose an Active Parent Project.";
    }
}

// --- FETCH PROJECTS DROPDOWN REFERENCE LIST ---
try {
    $projects_dropdown = $conn->query("SELECT project_id, project_name FROM projects ORDER BY project_name ASC")->fetchAll();
} catch (PDOException $e) {
    die("Dropdown Fetch Failure: " . $e->getMessage());
}

// --- FETCH TASK SYSTEM REGISTRY GRID ---
try {
    $tasks_query = "
        SELECT t.*, p.project_name 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.project_id 
        ORDER BY t.task_id DESC";
    $tasks = $conn->query($tasks_query)->fetchAll();
} catch (PDOException $e) {
    die("Registry Data Retrieval Failure: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Ecosystem Architecture | NextGen AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Shared Workspace Menu Layout Links */
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
        .nav-item a {
            display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1rem;
            color: var(--text-muted); text-decoration: none; border-radius: 12px; transition: all 0.3s;
        }
        .nav-item.active a, .nav-item a:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .nav-item.active a { background: var(--accent-primary); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        .main-content { margin-left: 260px; flex: 1; padding: 2rem 2.5rem; max-width: 1400px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }

        .btn-primary {
            background: var(--accent-gradient); color: white; border: none; padding: 0.75rem 1.5rem;
            border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3); transition: all 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(99, 102, 241, 0.4); }

        .alert { padding: 0.75rem 1.25rem; border-radius: 12px; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--status-success); color: #a7f3d0; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--status-danger); color: #fca5a5; }

        /* Structural Page Interface Layout Form Matrix Split */
        .workspace-split-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        .dashboard-panel { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 1.7rem; }
        
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .custom-table th { padding: 0.85rem 1rem; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 500; }
        .custom-table td { padding: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.04); }

        /* Design elements pills flags override layout options */
        .badge { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; text-transform: uppercase; }
        .badge.Hard { background: rgba(239, 68, 68, 0.15); color: var(--status-danger); }
        .badge.Medium { background: rgba(245, 158, 11, 0.15); color: var(--status-warning); }
        .badge.Easy { background: rgba(16, 185, 129, 0.15); color: var(--status-success); }

        .status-tag { display: inline-flex; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-tag.Pending { background: rgba(255, 255, 255, 0.05); color: var(--text-muted); }
        .status-tag.In_Progress { background: rgba(99, 102, 241, 0.15); color: #a5b4fc; }
        .status-tag.Completed { background: rgba(16, 185, 129, 0.15); color: var(--status-success); }

        .config-card { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 1.5rem; height: fit-content; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--text-muted); }
        .form-control { width: 100%; padding: 0.7rem 0.9rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 10px; color: white; outline: none; font-size: 0.9rem; }
        .form-control:focus { border-color: var(--accent-primary); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-brain-circuit"></i> Admin Core</div>
        <nav>
            <ul class="nav-list">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="projects.php"><i class="fa-solid fa-folder-open"></i> Projects</a></li>
                <li class="nav-item active"><a href="tasks.php"><i class="fa-solid fa-list-check"></i> Tasks</a></li>
                <li class="nav-item"><a href="team.php"><i class="fa-solid fa-users"></i> Team Members</a></li>
                <li class="nav-item"><a href="ai_assignment.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Assignment</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
                <li class="nav-item" style="margin-top: 3rem;"><a href="logout.php" style="color: var(--status-danger);"><i class="fa-solid fa-power-off"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 700;">Task Repository Matrix</h1>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Build discrete execution backlogs, mandate skills variables, and scope deadline limits.</p>
            </div>
        </header>

        <?php if(!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if(!empty($error_msg)): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="workspace-split-layout">
            
            <section class="dashboard-panel">
                <div class="data-table-wrapper">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Task Name</th>
                                <th>Parent Project</th>
                                <th>Complexity</th>
                                <th>Skill Mandatory</th>
                                <th>Target Date</th>
                                <th>State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tasks)): ?>
                                <tr><td colspan="6" style="color: var(--text-muted); text-align: center; padding: 3rem;">No active backlog tasks tracked. Scope elements on the right panel configuration block.</td></tr>
                            <?php else: ?>
                                <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($task['task_name']); ?></strong></td>
                                    <td style="color: var(--text-muted);"><?php echo htmlspecialchars($task['project_name'] ?? 'Detached Block'); ?></td>
                                    <td><span class="badge <?php echo $task['complexity']; ?>"><?php echo $task['complexity']; ?></span></td>
                                    <td><code style="color: #c084fc;"><?php echo htmlspecialchars($task['required_skill']); ?></code></td>
                                    <td><span style="font-size:0.85rem; font-weight:500;"><?php echo $task['deadline'] ?? 'Open-ended'; ?></span></td>
                                    <td><span class="status-tag <?php echo str_replace(' ', '_', $task['status']); ?>"><?php echo $task['status']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="config-card">
                <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-square-plus" style="color: var(--accent-primary);"></i> Append Task</h3>
                <form action="tasks.php" method="POST">
                    <input type="hidden" name="action" value="create_task">
                    
                    <div class="form-group">
                        <label class="form-label">Parent Project Target Portfolio</label>
                        <select name="project_id" class="form-control" required>
                            <option value="" disabled selected>-- Associate Project Context --</option>
                            <?php foreach($projects_dropdown as $p): ?>
                                <option value="<?php echo $p['project_id']; ?>"><?php echo htmlspecialchars($p['project_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Task Operational Name</label>
                        <input type="text" name="task_name" class="form-control" placeholder="e.g., Build Login Module" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Core Objective Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Break down delivery targets..."></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Complexity Vector</label>
                            <select name="complexity" class="form-control">
                                <option value="Easy">Easy</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Hard">Hard</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority Scale</label>
                            <select name="priority" class="form-control">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Required Skill Target Tag</label>
                        <input type="text" name="required_skill" class="form-control" placeholder="e.g., PHP" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Estimated Hours Scale</label>
                            <input type="number" name="estimated_hours" class="form-control" min="1" value="8">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target Deadline</label>
                            <input type="date" name="deadline" class="form-control">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 0.5rem;">Commit Task Node</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>