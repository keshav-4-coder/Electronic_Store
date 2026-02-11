<?php
/**
 * create_admin.php - Create Admin User Script
 * Run this file once to create an admin account
 * DELETE THIS FILE after creating the admin for security!
 */

// Database configuration
$host = 'localhost';
$dbname = '25126463';
$db_username = 'root';
$db_password = '';

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $phone_no = trim($_POST['phone_no'] ?? '');
        
        // Validate inputs
        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            $message = 'All fields are required!';
            $message_type = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters long!';
            $message_type = 'error';
        } else {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT user_id FROM USERS WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Username or email already exists!';
                $message_type = 'error';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                // Insert admin user
                $stmt = $conn->prepare("
                    INSERT INTO USERS (full_name, username, email, password, phone_no, role, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 'admin', 1, NOW())
                ");
                
                if ($stmt->execute([$full_name, $username, $email, $hashed_password, $phone_no])) {
                    $message = 'Admin user created successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to create admin user.';
                    $message_type = 'error';
                }
            }
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a1f2a 0%, #0c2531 50%, #081822 100%);
            color: #c0d4dd;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
            border: 1px solid #1a3a4a;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(64, 196, 255, 0.15);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header i {
            font-size: 60px;
            color: #40c4ff;
            margin-bottom: 15px;
        }
        
        h2 {
            color: #40c4ff;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #627d8a;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .alert.success {
            background-color: rgba(40, 167, 69, 0.15);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        
        .alert.error {
            background-color: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        .alert.info {
            background-color: rgba(23, 162, 184, 0.15);
            border: 1px solid rgba(23, 162, 184, 0.3);
            color: #17a2b8;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #c0d4dd;
            font-weight: 500;
            font-size: 14px;
        }
        
        label .required {
            color: #dc3545;
            margin-left: 3px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #627d8a;
            font-size: 16px;
        }
        
        input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 1px solid #1a3a4a;
            border-radius: 8px;
            background-color: #081822;
            color: #c0d4dd;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: #40c4ff;
            box-shadow: 0 0 0 3px rgba(64, 196, 255, 0.1);
            background-color: #0a1f2a;
        }
        
        input::placeholder {
            color: #627d8a;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #40c4ff, #00b7eb);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        button:hover {
            background: linear-gradient(135deg, #00b7eb, #0099cc);
            box-shadow: 0 4px 16px rgba(64, 196, 255, 0.4);
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .info-box {
            background-color: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 25px;
            font-size: 13px;
            color: #627d8a;
        }
        
        .info-box strong {
            color: #17a2b8;
            display: block;
            margin-bottom: 8px;
        }
        
        .info-box ul {
            margin-left: 20px;
            margin-top: 8px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #1a3a4a;
        }
        
        .login-link a {
            color: #40c4ff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: #00b7eb;
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-bar {
            height: 4px;
            background-color: #1a3a4a;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background-color: #dc3545; width: 33%; }
        .strength-medium { background-color: #ffc107; width: 66%; }
        .strength-strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-user-shield"></i>
            <h2>Create Admin User</h2>
            <p class="subtitle">Setup your administrator account</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($message_type === 'success'): ?>
            <div class="alert info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Next Steps:</strong><br>
                    1. Delete this file (create_admin.php) for security<br>
                    2. Login at: <a href="/25126463/auth/login.php" style="color: #40c4ff;">/25126463/auth/login.php</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="full_name" name="full_name" 
                               placeholder="Enter your full name" required
                               value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-at"></i>
                        <input type="text" id="username" name="username" 
                               placeholder="Choose a username" required
                               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" 
                               placeholder="admin@example.com" required
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter a strong password" required
                               minlength="6">
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill"></div>
                        </div>
                        <span id="strength-text" style="color: #627d8a; margin-top: 5px; display: block;"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone_no">Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="text" id="phone_no" name="phone_no" 
                               placeholder="Optional" 
                               value="<?= isset($_POST['phone_no']) ? htmlspecialchars($_POST['phone_no']) : '' ?>">
                    </div>
                </div>
                
                <button type="submit">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Admin Account</span>
                </button>
            </form>
            
            <div class="info-box">
                <strong><i class="fas fa-shield-alt"></i> Security Tips:</strong>
                <ul>
                    <li>Use a strong password (at least 6 characters)</li>
                    <li>Include uppercase, lowercase, numbers & symbols</li>
                    <li>Don't use common words or personal information</li>
                    <li>Delete this file after creating admin</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="login-link">
            <i class="fas fa-arrow-left"></i>
            <a href="/25126463/auth/login.php">Back to Login</a>
        </div>
    </div>
    
    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthFill = document.getElementById('strength-fill');
        const strengthText = document.getElementById('strength-text');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.length >= 10) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;
                
                strengthFill.className = 'strength-fill';
                
                if (strength < 3) {
                    strengthFill.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                    strengthText.style.color = '#dc3545';
                } else if (strength < 5) {
                    strengthFill.classList.add('strength-medium');
                    strengthText.textContent = 'Medium password';
                    strengthText.style.color = '#ffc107';
                } else {
                    strengthFill.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                    strengthText.style.color = '#28a745';
                }
                
                if (password.length === 0) {
                    strengthFill.style.width = '0%';
                    strengthText.textContent = '';
                }
            });
        }
        
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>