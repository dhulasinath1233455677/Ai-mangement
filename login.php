<?php
// login.php
session_start();
require_once 'connect.php';

// Route back into workspace matrices if session token elements persist
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            // Prepare statement to query our new users schema table cleanly
            $stmt = $conn->prepare("SELECT user_id, full_name, password, role FROM users WHERE email = :email LIMIT 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($user = $stmt->fetch()) {
                // Securely verify cryptographically encoded password vectors
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role']; // 'Admin' or 'Member'
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error_message = "Cryptographic credentials handshake mismatch.";
                }
            } else {
                $error_message = "Identity routing target not found in records.";
            }
        } catch (PDOException $e) {
            $error_message = "System Exception Intercepted: " . $e->getMessage();
        }
    } else {
        $error_message = "All operational fields must be complete.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway Authentication Matrix | NextGen AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #090d16;
            --panel-bg: rgba(17, 24, 39, 0.7);
            --accent-primary: #6366f1;
            --accent-purple: #a855f7;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* Responsive Split-Screen Layout Layout */
        .login-wrapper {
            display: flex;
            width: 100%;
        }

        /* Left Branding Panel Style Engine */
        .branding-panel {
            flex: 1.2;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 5rem;
            overflow: hidden;
        }

        /* Tech Mesh Layer Graphics */
        .branding-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(99, 102, 241, 0.15) 1px, transparent 1px);
            background-size: 24px 24px;
            opacity: 0.7;
        }

        .branding-panel::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(168, 85, 247, 0.2);
            filter: blur(100px);
            border-radius: 50%;
            top: 20%;
            right: -100px;
        }

        .branding-content {
            position: relative;
            z-index: 2;
            max-width: 520px;
        }

        .company-logo {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .branding-content h1 {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-bottom: 1.5rem;
        }

        .branding-content p {
            font-size: 1.125rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Right Interface Authentication Panel Layout */
        .form-panel {
            flex: 1;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem;
            border-left: 1px solid var(--border-color);
            position: relative;
        }

        .form-container {
            width: 100%;
            max-width: 400px;
        }

        .form-header {
            margin-bottom: 2.5rem;
        }

        .form-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 0.85rem 1.25rem;
            border-radius: 12px;
            font-size: 0.875rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Input Optimization Engines Layout Matrix */
        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .input-field-wrapper {
            position: relative;
        }

        .input-field-wrapper i.field-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: color 0.3s;
        }

        .form-control-input {
            width: 100%;
            padding: 0.875rem 1.25rem 0.875rem 3rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-control-input:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .form-control-input:focus ~ i.field-icon {
            color: var(--accent-primary);
        }

        .password-visibility-trigger {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            outline: none;
        }

        /* Options Utility Matrix Elements Layout */
        .utility-row {
            display: flex;
            justify-bin-items: space-between;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        .checkbox-label input {
            accent-color: var(--accent-primary);
            width: 1rem;
            height: 1rem;
        }

        .forgot-link {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn-matrix-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-purple) 100%);
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.25);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-matrix-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(99, 102, 241, 0.35);
        }

        /* Testing Framework Utilities Overlay Box */
        .hackathon-helper-box {
            margin-top: 2rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 1rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .hackathon-helper-box code { color: #f472b6; }

        /* Structural Responsiveness Adjustments Matrix Override */
        @media(max-width: 968px) {
            .branding-panel { display: none; }
            .form-panel { padding: 2rem; }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        
        <section class="branding-panel">
            <div class="branding-content">
                <div class="company-logo">
                    <i class="fa-solid fa-brain-circuit"></i> AI Project Manager
                </div>
                <h1>Manage Projects Efficiently with AI Collaboration.</h1>
                <p>Experience real-time automated vector task allocation, cross-department tracking dashboards, and intelligent resource balancing algorithms operating on optimized structures.</p>
            </div>
        </section>

        <main class="form-panel">
            <div class="form-container">
                <div class="form-header">
                    <h2>Welcome Back Workspace User</h2>
                    <p>Provide secure authentication node values below.</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert-error">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="off">
                    
                    <div class="input-group">
                        <label class="input-label" for="email">System Workspace Email</label>
                        <div class="input-field-wrapper">
                            <input type="email" id="email" name="email" class="form-control-input" placeholder="name@domain.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <i class="fa-solid fa-envelope field-icon"></i>
                        </div>
                    </div>

                    <div class="input-group">
                        <label class="input-label" for="password">Security Password Key</label>
                        <div class="input-field-wrapper">
                            <input type="password" id="password" name="password" class="form-control-input" placeholder="••••••••" required>
                            <i class="fa-solid fa-shield-halved field-icon"></i>
                            <button type="button" class="password-visibility-trigger" id="passViewControl" aria-label="Toggle password text layer">
                                <i class="fa-solid fa-eye" id="toggleGraphicNode"></i>
                            </button>
                        </div>
                    </div>

                    <div class="utility-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me" id="remember_me">
                            <span>Keep Node Context Saved</span>
                        </label>
                        <a href="#" class="forgot-link" onclick="alert('Password operations route to secure recovery models in Phase 3.')">Forgot Key?</a>
                    </div>

                    <button type="submit" class="btn-matrix-submit">
                        <span>Authorize Access Node</span>
                        <i class="fa-solid fa-terminal"></i>
                    </button>
                </form>

                <div class="hackathon-helper-box">
                    <strong>Verified Seed Matrix Database Access Accounts:</strong><br>
                    • Role [<code>Admin</code>]: <code>admin@gmail.com</code> / pass: <code>admin123</code><br>
                    • Role [<code>Member</code>]: <code>dhulasinath@gmail.com</code> / pass: <code>member123</code>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Smooth Interface Field Text Unmasking Control Script
        const passInputField = document.getElementById('password');
        const triggerNodeBtn = document.getElementById('passViewControl');
        const graphicalIconNode = document.getElementById('toggleGraphicNode');

        triggerNodeBtn.addEventListener('click', function() {
            const isMaskedText = passInputField.getAttribute('type') === 'password';
            passInputField.setAttribute('type', isMaskedText ? 'text' : 'password');
            graphicalIconNode.classList.toggle('fa-eye');
            graphicalIconNode.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>