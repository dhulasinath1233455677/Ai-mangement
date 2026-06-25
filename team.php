<?php
// team.php
session_start();
require_once 'connect.php';

// Auth Guard - Only Admin can access employee configuration matrices
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// --- HANDLE FORM SUBMISSIONS (CREATE MEMBER) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'create_member') {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
    $dept = trim($_POST['department']);
    $skills = trim($_POST['skills']);
    $exp = intval($_POST['experience']);
    $availability = $_POST['availability'];

    if (!empty($name) && !empty($email)) {
        try {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, department, skills, experience, availability) VALUES (?, ?, ?, 'Member', ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $dept, $skills, $exp, $availability]);
            $success_msg = "New employee record securely committed to the system matrix.";
        } catch (PDOException $e) {
            $error_msg = "Registration conflict: Email might already exist in records.";
        }
    } else {
        $error_msg = "Name and Email fields are strictly required.";
    }
}

// --- FETCH ALL ACTIVE TEAM MEMBERS ---
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'Member' ORDER BY user_id DESC");
    $stmt->execute();
    $team_members = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Data collection failure: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Node Registry | NextGen AI</title>
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

        /* Shared Sidebar Menu Blueprint */
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

        /* Multi-Layout Systems Flex Engine */
        .workspace-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        .team-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; content-visibility: auto; }
        
        /* Modern Team Card Panel */
        .member-card {
            background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 20px;
            padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s;
        }
        .member-card:hover { border-color: rgba(99, 102, 241, 0.4); transform: translateY(-2px); }
        
        .member-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .avatar-placeholder { width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-weight: 600; color: #a5b4fc; border: 1px solid var(--border-color); }
        .member-info h3 { font-size: 1.05rem; font-weight: 600; }
        .member-info p { font-size: 0.75rem; color: var(--text-muted); }

        .skills-container { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 1rem 0; }
        .skill-badge { font-size: 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); padding: 0.2rem 0.5rem; border-radius: 6px; color: #cbd5e1; }

        .metric-row { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); padding: 0.5rem 0; border-top: 1px solid rgba(255,255,255,0.03); }
        .availability-pill { font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 30px; }
        .availability-pill.Available { background: rgba(16, 185, 129, 0.12); color: var(--status-success); }
        .availability-pill.Busy { background: rgba(245, 158, 11, 0.12); color: var(--status-warning); }
        .availability-pill.On_Leave { background: rgba(239, 68, 68, 0.12); color: var(--status-danger); }

        /* Configuration Side Panel */
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
                <li class="nav-item"><a href="tasks.php"><i class="fa-solid fa-list-check"></i> Tasks</a></li>
                <li class="nav-item active"><a href="team.php"><i class="fa-solid fa-users"></i> Team Members</a></li>
                <li class="nav-item"><a href="ai_assignment.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Assignment</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
                <li class="nav-item" style="margin-top: 3rem;"><a href="logout.php" style="color: var(--status-danger);"><i class="fa-solid fa-power-off"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 700;">Human Resource Grid</h1>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Manage workspace profiles, track operational loads, and audit skill matrix definitions.</p>
            </div>
        </header>

        <?php if(!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if(!empty($error_msg)): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="workspace-layout">
            
            <!-- Left Grid Pane: Team Profiles Output -->
            <section class="team-grid">
                <?php if(empty($team_members)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 4rem; color: var(--text-muted);">
                        <i class="fa-solid fa-user-slash" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        No operational human resource logs found in system records.
                    </div>
                <?php else: ?>
                    <?php foreach($team_members as $member): ?>
                        <div class="member-card">
                            <div>
                                <div class="member-header">
                                    <div class="avatar-placeholder"><?php echo strtoupper(substr($member['full_name'], 0, 1)); ?></div>
                                    <div class="member-info">
                                        <h3><?php echo htmlspecialchars($member['full_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($member['department'] ?? 'General Engineering'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="skills-container">
                                    <?php 
                                    $tags = explode(',', $member['skills'] ?? '');
                                    foreach($tags as $tag) {
                                        if(!empty(trim($tag))) {
                                            echo '<span class="skill-badge">' . htmlspecialchars(trim($tag)) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div>
                                <div class="metric-row">
                                    <span>Experience Metric</span>
                                    <span style="color: var(--text-main); font-weight: 500;"><?php echo $member['experience']; ?> Years</span>
                                </div>
                                <div class="metric-row">
                                    <span>Operational State</span>
                                    <span class="availability-pill <?php echo str_replace(' ', '_', $member['availability']); ?>"><?php echo $member['availability']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Right Pane: Onboard Resource Form Matrix Card -->
            <section class="config-card">
                <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-user-plus" style="color: var(--accent-primary);"></i> Onboard Node</h3>
                <form action="team.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="create_member">
                    
                    <div class="form-group">
                        <label class="form-label">Full Employee Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="e.g., Kumar S" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Workspace Access Email</label>
                        <input type="email" name="email" class="form-control" placeholder="username@company.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial System Account Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department / Unit Stack</label>
                        <input type="text" name="department" class="form-control" placeholder="e.g., Backend Engineering">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Skills Inventory Matrix (Comma Separated)</label>
                        <input type="text" name="skills" class="form-control" placeholder="e.g., Java, Spring, MySQL">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Experience Metric</label>
                            <input type="number" name="experience" class="form-control" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Availability Status</label>
                            <select name="availability" class="form-control">
                                <option value="Available" selected>Available</option>
                                <option value="Busy">Busy</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 0.5rem;">Commit Resource Node</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>