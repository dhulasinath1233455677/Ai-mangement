<?php
// employee_dashboard.php
session_start();
require_once 'connect.php';

// Auth Guard - Verify user session exists
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Security Check: Enforce routing boundary if an Admin stumbles into the employee view
if ($_SESSION['role'] === 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$employee_name = $_SESSION['name'];
$success_msg = "";
$error_msg = "";

// --- HANDLE TASK STATUS MATRIX UPDATES ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $task_id = intval($_POST['task_id']);
    $new_status = $_POST['status'];

    if ($task_id > 0 && in_array($new_status, ['Pending', 'In Progress', 'Completed'])) {
        try {
            $conn->beginTransaction();

            // 1. Update status flag directly on the master task row element
            $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE task_id = ?");
            $stmt->execute([$new_status, $task_id]);

            // 2. Commit log into task_progress historical array table
            $stmt2 = $conn->prepare("INSERT INTO task_progress (task_id, user_id, progress_percentage, status, comments, updated_at) VALUES (?, ?, ?, ?, 'Status updated via employee terminal matrix view.', NOW())");
            
            // Map percentage values dynamically based on chosen state flags
            $pct = ($new_status === 'Completed') ? 100 : (($new_status === 'In Progress') ? 50 : 0);
            $stmt2->execute([$task_id, $user_id, $pct, $new_status]);

            // 3. If completed, safely reduce the employee's active workload score variable
            if ($new_status === 'Completed') {
                $stmt3 = $conn->prepare("UPDATE users SET current_workload = GREATEST(0, current_workload - 1) WHERE user_id = ?");
                $stmt3->execute([$user_id]);
            }

            $conn->commit();
            $success_msg = "Task operational pipeline status advanced successfully.";
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_msg = "Pipeline Sync Failure: " . $e->getMessage();
        }
    }
}

// --- FETCH NOTIFICATION STREAMS FOR THIS SPECIFIC USER ---
try {
    $notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY notification_id DESC LIMIT 3");
    $notif_stmt->execute([$user_id]);
    $notifications = $notif_stmt->fetchAll();
} catch (PDOException $e) {
    die("Notification layer fault: " . $e->getMessage());
}

// --- FETCH ASSIGNED WORKLIST DATA GRID ---
try {
    $worklist_query = "
        SELECT t.*, p.project_name, ta.assigned_date, ta.ai_recommended, ta.recommendation_reason
        FROM tasks t
        INNER JOIN task_assignments ta ON t.task_id = ta.task_id
        LEFT JOIN projects p ON t.project_id = p.project_id
        WHERE ta.user_id = ?
        ORDER BY t.task_id DESC";
    
    $stmt = $conn->prepare($worklist_query);
    $stmt->execute([$user_id]);
    $assigned_tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Workspace payload aggregation failure: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Production Node | NextGen AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0b1329 0%, #1a163a 100%);
            --panel-bg: rgba(30, 41, 59, 0.45);
            --accent-primary: #6366f1;
            --accent-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --status-success: #10b981;
            --status-warning: #f59e0b;
            --status-info: #06b6d4;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-gradient); min-height: 100vh; color: var(--text-main); display: flex; }

        /* Left Workspace Sidebar Menu System */
        .sidebar {
            width: 260px; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color); display: flex; flex-direction: column;
            padding: 2rem 1.5rem; position: fixed; height: 100vh;
        }
        .sidebar-brand {
            font-size: 1.5rem; font-weight: 700; background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 2.5rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item a { display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1rem; color: var(--text-muted); text-decoration: none; border-radius: 12px; transition: all 0.3s; font-weight: 500; }
        .nav-item.active a, .nav-item a:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .nav-item.active a { background: var(--accent-primary); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        .main-content { margin-left: 260px; flex: 1; padding: 2rem 2.5rem; max-width: 1400px; }
        
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;
            background: var(--panel-bg); backdrop-filter: blur(12px); padding: 1.25rem 2rem;
            border-radius: 20px; border: 1px solid var(--border-color);
        }
        .profile-badge { display: flex; align-items: center; gap: 0.75rem; background: rgba(255, 255, 255, 0.05); padding: 0.5rem 1rem; border-radius: 30px; border: 1px solid var(--border-color); }
        .profile-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-gradient); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.875rem; color: white; }

        /* Notification stream alert boxes design layout components */
        .notification-stream { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem; }
        .notif-pill { background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); padding: 0.85rem 1.25rem; border-radius: 14px; font-size: 0.875rem; display: flex; align-items: center; gap: 0.75rem; color: #cbd5e1; }

        .alert { padding: 0.75rem 1.25rem; border-radius: 12px; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--status-success); color: #a7f3d0; }

        /* Backlog Card Grid layout design template components */
        .backlog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem; }
        .task-node-card { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 22px; padding: 1.7rem; display: flex; flex-direction: column; justify-content: space-between; gap: 1.25rem; transition: border 0.3s; }
        .task-node-card:hover { border-color: rgba(99, 102, 241, 0.35); }

        .ai-allocation-indicator { background: rgba(168, 85, 247, 0.12); border: 1px solid rgba(168, 85, 247, 0.25); border-radius: 10px; padding: 0.6rem 0.85rem; font-size: 0.75rem; color: #d8b4fe; line-height: 1.4; }

        .badge { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; text-transform: uppercase; }
        .badge.Hard { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
        .badge.Medium { background: rgba(245, 158, 11, 0.15); color: #fde68a; }
        .badge.Easy { background: rgba(16, 185, 129, 0.15); color: #a7f3d0; }

        .status-pill { font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 20px; text-transform: capitalize; }
        .status-pill.Pending { background: rgba(255,255,255,0.05); color: var(--text-muted); }
        .status-pill.In_Progress { background: rgba(99, 102, 241, 0.15); color: #a5b4fc; }
        .status-pill.Completed { background: rgba(16, 185, 129, 0.15); color: var(--status-success); }

        .form-control-select { width: 100%; padding: 0.6rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 10px; color: white; outline: none; font-size: 0.85rem; cursor: pointer; }
        .btn-sync { background: var(--accent-primary); color: white; border: none; padding: 0.6rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-sync:hover { background: #4f46e5; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-brain-circuit"></i> Team Node</div>
        <nav>
            <ul class="nav-list">
                <li class="nav-item active"><a href="employee_dashboard.php"><i class="fa-solid fa-list-check"></i> My Assigned Tasks</a></li>
                <li class="nav-item"><a href="logout.php" style="color: #ef4444; margin-top: 5rem;"><i class="fa-solid fa-power-off"></i> Sign Out Node</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        
        <header class="dashboard-header">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700;">Workspace Node: <?php echo htmlspecialchars($employee_name); ?></h1>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Real-time task allocation buffer line stream.</p>
            </div>
            <div class="header-meta-actions">
                <div class="profile-badge">
                    <div class="profile-avatar"><?php echo strtoupper(substr($employee_name, 0, 1)); ?></div>
                    <span>Engineering Member</span>
                </div>
            </div>
        </header>

        <?php if(!empty($notifications)): ?>
        <section class="notification-stream">
            <?php foreach($notifications as $notif): ?>
                <div class="notif-pill">
                    <i class="fa-solid fa-circle-nodes" style="color: var(--status-info);"></i>
                    <span><strong><?php echo htmlspecialchars($notif['title']); ?>:</strong> <?php echo htmlspecialchars($notif['message']); ?></span>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if(!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>

        <section class="backlog-grid">
            <?php if(empty($assigned_tasks)): ?>
                <div style="grid-column: 1/-1; background: var(--panel-bg); border: 1px dashed var(--border-color); border-radius: 24px; padding: 4rem; text-align: center; color: var(--text-muted);">
                    <i class="fa-solid fa-gauge-simple" style="font-size: 3rem; margin-bottom: 1rem; display: block; color: var(--text-muted);"></i>
                    Zero active task payloads mapped to your engineer identifier index node.
                </div>
            <?php else: ?>
                <?php foreach($assigned_tasks as $task): ?>
                    <div class="task-node-card">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($task['project_name']); ?></span>
                                <span class="badge <?php echo $task['complexity']; ?>"><?php echo $task['complexity']; ?></span>
                            </div>
                            
                            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($task['task_name']); ?></h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 1rem;"><?php echo htmlspecialchars($task['description'] ?? 'No functional instructions assigned.'); ?></p>
                            
                            <div style="font-size: 0.8rem; display:flex; flex-direction:column; gap:0.25rem; margin-bottom:1rem; color: var(--text-muted);">
                                <div><i class="fa-regular fa-calendar-check"></i> Targets Timeline: <strong style="color: white;"><?php echo $task['deadline'] ?? 'Open Loop'; ?></strong></div>
                                <div><i class="fa-regular fa-clock"></i> Allocated Hours: <strong style="color: white;"><?php echo $task['estimated_hours']; ?> Hrs</strong></div>
                            </div>

                            <?php if($task['ai_recommended']): ?>
                                <div class="ai-allocation-indicator">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> <strong>Optimized Routing Vector Match:</strong><br>
                                    <?php echo htmlspecialchars($task['recommendation_reason']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="border-top: 1px solid rgba(255,255,255,0.04); padding-top: 1rem;">
                            <form action="employee_dashboard.php" method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                
                                <div style="flex: 1;">
                                    <select name="status" class="form-control-select">
                                        <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending Queue</option>
                                        <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress loop</option>
                                        <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Mark Completed</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-sync">Sync</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>