<?php
// projects.php
session_start();
require_once 'database/connect.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// --- HANDLE FORM SUBMISSIONS (CREATE & DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Create Project
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $name = trim($_POST['project_name']);
        $desc = trim($_POST['description']);
        $priority = $_POST['priority'];
        $status = $_POST['status'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $manager_id = $_SESSION['user_id']; // Default assigning creator as manager

        if (!empty($name)) {
            try {
                $stmt = $conn->prepare("INSERT INTO projects (project_name, description, manager_id, priority, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $desc, $manager_id, $priority, $status, $start_date, $end_date]);
                $success_msg = "Project environment created successfully.";
            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        } else {
            $error_msg = "Project name cannot be empty.";
        }
    }

    // 2. Delete Project
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $p_id = intval($_POST['project_id']);
        try {
            $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
            $stmt->execute([$p_id]);
            $success_msg = "Project removed from ecosystem tracking.";
        } catch (PDOException $e) {
            $error_msg = "Cannot delete project. Verify if active tasks are linked to it.";
        }
    }
}

// --- FETCH ALL ACTIVE PROJECTS ---
try {
    $stmt = $conn->query("SELECT p.*, u.full_name as manager_name FROM projects p LEFT JOIN users u ON p.manager_id = u.user_id ORDER BY p.project_id DESC");
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Query Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Portfolio Registry | NextGen AI</title>
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
            --status-info: #06b6d4;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-gradient); min-height: 100vh; color: var(--text-main); display: flex; }

        /* Navigation Layout Reuse */
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

        /* View Layout System components */
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

        /* Card Matrix Configurations */
        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .project-card {
            background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 20px;
            padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;
            position: relative; transition: all 0.3s;
        }
        .project-card:hover { transform: translateY(-4px); border-color: rgba(99, 102, 241, 0.4); }
        
        .card-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .priority-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.25rem 0.5rem; border-radius: 6px; }
        .priority-badge.High { background: rgba(239, 68, 68, 0.15); color: var(--status-danger); }
        .priority-badge.Medium { background: rgba(245, 158, 11, 0.15); color: var(--status-warning); }
        .priority-badge.Low { background: rgba(6, 182, 212, 0.15); color: var(--status-info); }

        .card-title { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-main); }
        .card-desc { font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem; min-height: 44px; }
        
        .card-timeline { font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 1rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem; margin-bottom: 1rem; }
        .card-actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
        .btn-delete { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.5rem; border-radius: 8px; transition: color 0.2s; }
        .btn-delete:hover { color: var(--status-danger); background: rgba(239, 68, 68, 0.1); }

        /* Modal Structure Graphic */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: #111827; border: 1px solid var(--border-color); border-radius: 24px; padding: 2rem; width: 100%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; }

        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--text-muted); }
        .form-control { width: 100%; padding: 0.75rem 1rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 10px; color: white; outline: none; }
        .form-control:focus { border-color: var(--accent-primary); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-brain-circuit"></i> Admin Core</div>
        <nav>
            <ul class="nav-list">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item active"><a href="projects.php"><i class="fa-solid fa-folder-open"></i> Projects</a></li>
                <li class="nav-item"><a href="tasks.php"><i class="fa-solid fa-list-check"></i> Tasks</a></li>
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
                <h1 style="font-size: 1.75rem; font-weight: 700;">Project Portfolios</h1>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Create, track and govern active development cycles.</p>
            </div>
            <button class="btn-primary" id="openModalBtn"><i class="fa-solid fa-plus"></i> Add New Project</button>
        </header>

        <?php if(!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if(!empty($error_msg)): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <section class="project-grid">
            <?php if (empty($projects)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class="fa-regular fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    No projects found. Tap "Add New Project" to initialize.
                </div>
            <?php else: ?>
                <?php foreach($projects as $project): ?>
                    <div class="project-card">
                        <div>
                            <div class="card-meta">
                                <span class="priority-badge <?php echo $project['priority']; ?>"><?php echo $project['priority']; ?></span>
                                <span style="font-size: 0.8rem; color: var(--accent-primary); font-weight: 600;"><?php echo $project['status']; ?></span>
                            </div>
                            <h3 class="card-title"><?php echo htmlspecialchars($project['project_name']); ?></h3>
                            <p class="card-desc"><?php echo htmlspecialchars($project['description'] ?? 'No summary data recorded.'); ?></p>
                        </div>
                        <div>
                            <div class="card-timeline">
                                <span><i class="fa-regular fa-calendar"></i> Start: <?php echo $project['start_date'] ?? 'N/A'; ?></span>
                                <span><i class="fa-solid fa-hourglass-end"></i> Due: <?php echo $project['end_date'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="card-actions">
                                <form action="projects.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this project? Tasks linked will drop context.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                    <button type="submit" class="btn-delete" title="Delete Portfolio Node"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <div class="modal" id="projectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-weight: 600;">Initialize Project Portfolio</h3>
                <button class="modal-close" id="closeModalBtn">&times;</button>
            </div>
            <form action="projects.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Project Identity Name</label>
                    <input type="text" name="project_name" class="form-control" placeholder="e.g., Risk Management System" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Scope Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Define core parameters..."></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Priority Vector</label>
                        <select name="priority" class="form-control">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Phase Status</label>
                        <select name="status" class="form-control">
                            <option value="Planning" selected>Planning</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date (Deadline)</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">Commit Portfolio to DB</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('projectModal');
        document.getElementById('openModalBtn').onclick = () => modal.style.display = 'flex';
        document.getElementById('closeModalBtn').onclick = () => modal.style.display = 'none';
        window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; }
    </script>
</body>
</html>