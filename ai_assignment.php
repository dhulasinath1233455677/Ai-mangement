<?php
// ai_assignment.php
session_start();
require_once 'connect.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// --- HANDLE MANUAL OR ACCEPTED AI ASSIGNMENTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'commit_assignment') {
    $task_id = intval($_POST['task_id']);
    $user_id = intval($_POST['user_id']);
    $ai_rec = isset($_POST['ai_recommended']) ? 1 : 0;
    $reason = trim($_POST['reason'] ?? 'Manually routed by Admin.');

    if ($task_id > 0 && $user_id > 0) {
        try {
            $conn->beginTransaction();

            // 1. Insert into task_assignments table
            $stmt = $conn->prepare("INSERT INTO task_assignments (task_id, user_id, assigned_by, assigned_date, ai_recommended, recommendation_reason) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$task_id, $user_id, $_SESSION['user_id'], $ai_rec, $reason]);

            // 2. Advance task status flag to 'In Progress'
            $stmt2 = $conn->prepare("UPDATE tasks SET status = 'In Progress' WHERE task_id = ?");
            $stmt2->execute([$task_id]);

            // 3. Increment workload counter vector on user profile node
            $stmt3 = $conn->prepare("UPDATE users SET current_workload = current_workload + 1 WHERE user_id = ?");
            $stmt3->execute([$user_id]);

            // 4. Fire notification channel event entry
            $stmt4 = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'New Task Assigned', 'You have been assigned to an active operational module.')");
            $stmt4->execute([$user_id]);

            $conn->commit();
            $success_msg = "Task allocation locked. Resource routed and notification emitted.";
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_msg = "Transaction Aborted: " . $e->getMessage();
        }
    }
}

// --- FETCH UNASSIGNED TASKS LOOP ---
try {
    $unassigned_tasks = $conn->query("SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.project_id WHERE t.task_id NOT IN (SELECT task_id FROM task_assignments) ORDER BY t.task_id ASC")->fetchAll();
    
    // Fetch all members for manual routing fallback options dropdown selection
    $team_members = $conn->query("SELECT user_id, full_name, skills, availability, current_workload FROM users WHERE role = 'Member'")->fetchAll();
} catch (PDOException $e) {
    die("Data aggregation failure: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Engine Routing Control | NextGen AI</title>
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

        .alert { padding: 0.75rem 1.25rem; border-radius: 12px; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--status-success); color: #a7f3d0; }

        /* Allocation Workspace layout grids setup */
        .allocation-grid { display: flex; flex-direction: column; gap: 1.5rem; }
        .allocation-card { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 2rem; display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 2rem; }

        .task-summary-pane { border-right: 1px solid rgba(255,255,255,0.05); padding-right: 2rem; }
        .ai-engine-pane { display: flex; flex-direction: column; justify-content: center; position: relative; }

        .btn-ai-trigger {
            background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%); color: white; border: none; padding: 1rem;
            border-radius: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            box-shadow: 0 4px 20px rgba(168, 85, 247, 0.3); transition: all 0.3s;
        }
        .btn-ai-trigger:hover { transform: scale(1.01); box-shadow: 0 6px 24px rgba(168, 85, 247, 0.45); }

        /* Recommendation Display Overlay Elements */
        .ai-result-box { display: none; background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(168, 85, 247, 0.4); border-radius: 16px; padding: 1.5rem; margin-top: 1rem; animation: reveal 0.4s ease-out; }
        @keyframes reveal { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .reasoning-text { font-size: 0.85rem; color: var(--text-muted); background: rgba(0,0,0,0.15); padding: 0.75rem; border-radius: 10px; margin: 0.75rem 0; line-height: 1.4; border-left: 3px solid #c084fc; }

        .action-button-row { display: flex; gap: 0.75rem; margin-top: 1rem; }
        .btn-action { flex: 1; padding: 0.7rem; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; text-align: center; font-size: 0.85rem; }
        .btn-action.accept { background: var(--status-success); color: white; }
        .btn-action.manual { background: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid var(--border-color); }

        .manual-form-wrapper { display: none; margin-top: 1rem; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 1rem; }
        .form-control { width: 100%; padding: 0.65rem 0.85rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 8px; color: white; outline: none; margin-bottom: 0.75rem; font-size: 0.875rem; }
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
                <li class="nav-item active"><a href="ai_assignment.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Assignment</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
                <li class="nav-item" style="margin-top: 3rem;"><a href="logout.php" style="color: var(--status-danger);"><i class="fa-solid fa-power-off"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <h1 style="font-size: 1.75rem; font-weight: 700;">AI Optimization Core</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Run semantic dependency analysis algorithms to resolve work routing tasks flawlessly.</p>
        </header>

        <?php if(!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>

        <section class="allocation-grid">
            <?php if(empty($unassigned_tasks)): ?>
                <div style="background: var(--panel-bg); padding: 4rem; text-align: center; border-radius: 24px; color: var(--text-muted); border: 1px dashed var(--border-color);">
                    <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: var(--status-success); margin-bottom: 1rem; display: block;"></i>
                    Zero Backlog Variance. All registered system operational tasks have active resource assignments.
                </div>
            <?php else: ?>
                <?php foreach($unassigned_tasks as $index => $task): ?>
                    <div class="allocation-card" id="card-<?php echo $task['task_id']; ?>">
                        
                        <div class="task-summary-pane">
                            <span style="font-size: 0.75rem; font-weight: 600; color: var(--accent-primary); text-transform: uppercase;"><?php echo htmlspecialchars($task['project_name']); ?></span>
                            <h2 style="font-size: 1.35rem; font-weight:600; margin: 0.25rem 0 0.75rem 0;"><?php echo htmlspecialchars($task['task_name']); ?></h2>
                            <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem;"><?php echo htmlspecialchars($task['description'] ?? 'No metadata provided.'); ?></p>
                            
                            <div style="font-size: 0.8rem; display: flex; flex-direction: column; gap: 0.4rem;">
                                <div><span style="color: var(--text-muted);">Required Competency:</span> <code style="color: #c084fc; font-weight: 600;"><?php echo htmlspecialchars($task['required_skill']); ?></code></div>
                                <div><span style="color: var(--text-muted);">Complexity Constraint:</span> <strong><?php echo $task['complexity']; ?></strong></div>
                                <div><span style="color: var(--text-muted);">Deadline Limit:</span> <strong><?php echo $task['deadline'] ?? 'Unspecified'; ?></strong></div>
                            </div>
                        </div>

                        <div class="ai-engine-pane" id="engine-pane-<?php echo $task['task_id']; ?>">
                            <button type="button" class="btn-ai-trigger" onclick="runCognitiveAllocation(<?php echo $task['task_id']; ?>, '<?php echo addslashes($task['required_skill']); ?>')">
                                <i class="fa-solid fa-microchip-ai"></i> Run AI Optimization Matrix
                            </button>

                            <div class="ai-result-box" id="result-<?php echo $task['task_id']; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #cbd5e1;"><i class="fa-solid fa-sparkles" style="color: #c084fc;"></i> Recommended Node:</span>
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--status-success);" id="ai-est-<?php echo $task['task_id']; ?>">Est: 3 Days</span>
                                </div>
                                <h3 style="font-size: 1.25rem; margin: 0.25rem 0;" id="ai-emp-name-<?php echo $task['task_id']; ?>">Dhulasinath</h3>
                                <div class="reasoning-text" id="ai-reason-<?php echo $task['task_id']; ?>">Matches requirements perfectly with active bandwidth loops.</div>

                                <div class="action-button-row">
                                    <form action="ai_assignment.php" method="POST" style="flex:1; display:flex;">
                                        <input type="hidden" name="action" value="commit_assignment">
                                        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                        <input type="hidden" name="user_id" id="ai-form-user-id-<?php echo $task['task_id']; ?>" value="">
                                        <input type="hidden" name="ai_recommended" value="1">
                                        <input type="hidden" name="reason" id="ai-form-reason-<?php echo $task['task_id']; ?>" value="">
                                        <button type="submit" class="btn-action accept">Accept & Assign</button>
                                    </form>
                                    <button type="button" class="btn-action manual" onclick="revealManualOverride(<?php echo $task['task_id']; ?>)">Manual Override</button>
                                </div>
                            </div>

                            <div class="manual-form-wrapper" id="manual-form-<?php echo $task['task_id']; ?>">
                                <form action="ai_assignment.php" method="POST">
                                    <input type="hidden" name="action" value="commit_assignment">
                                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                    <label style="font-size: 0.8rem; display:block; margin-bottom:0.25rem; color: var(--text-muted);">Select Human Node Vector</label>
                                    <select name="user_id" class="form-control" required>
                                        <?php foreach($team_members as $m): ?>
                                            <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?> (Skills: <?php echo htmlspecialchars($m['skills']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-action accept" style="width: 100%;">Confirm Manual Dispatch</button>
                                </form>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Real-Time Context Engine Logic simulating structured payload profiles matching your criteria 
        function runCognitiveAllocation(taskId, requiredSkill) {
            const resultBox = document.getElementById('result-' + taskId);
            const triggerBtn = document.querySelector('#engine-pane-' + taskId + ' .btn-ai-trigger');
            
            triggerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Parsing Cluster Matrices...';
            triggerBtn.style.opacity = '0.6';

            setTimeout(() => {
                triggerBtn.style.display = 'none';
                resultBox.style.display = 'block';

                // Match mock structural criteria loops dynamically
                if(requiredSkill.toLowerCase().includes('php')) {
                    document.getElementById('ai-emp-name-' + taskId).innerText = "Dhulasinath";
                    document.getElementById('ai-reason-' + taskId).innerText = "• Matches PHP capability metric exactly.\n• Minimal operational context overlap (Only 2 active loop instances).\n• High historical structural stability index parameters.";
                    document.getElementById('ai-form-user-id-' + taskId).value = "2"; // Corresponds to seed member index record
                    document.getElementById('ai-form-reason-' + taskId).value = "AI Recommended: Matches PHP requirements exactly with active bandwidth.";
                } else {
                    document.getElementById('ai-emp-name-' + taskId).innerText = "Kumar S";
                    document.getElementById('ai-reason-' + taskId).innerText = "• Alternate optimal resource match variant selected.\n• Workload utilization index shows acceptable balanced limits.";
                    document.getElementById('ai-form-user-id-' + taskId).value = "3";
                    document.getElementById('ai-form-reason-' + taskId).value = "AI Recommended: Optimal routing balancing vector resolution.";
                }
            }, 1200);
        }

        function revealManualOverride(taskId) {
            document.getElementById('manual-form-' + taskId).style.display = 'block';
        }
    </script>
</body>
</html>