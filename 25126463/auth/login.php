<?php
/**
 * auth/login.php - Professional Login Page
 * Cyan/Teal Dark Theme - FIXED WITH PROFILE PICTURE SESSION
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /25126463/index.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

$error = null;
$success = $_GET['success'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            // UPDATED: Added profile_picture to the SELECT statement
            $stmt = $conn->prepare("
                SELECT user_id, full_name, username, email, password, role, is_active, profile_picture 
                FROM USERS 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_active'] != 1) {
                    $error = "Your account has been deactivated. Please contact support.";
                } else {
                    // Update last login
                    $update = $conn->prepare("UPDATE USERS SET last_login = NOW() WHERE user_id = ?");
                    $update->execute([$user['user_id']]);

                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // FIXED: Store profile picture in session for sidebar/header
                    $_SESSION['profile_picture'] = $user['profile_picture'];

                    // Handle remember me
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (86400 * 30), "/");
                    }

                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header("Location: /25126463/admin/dashboard.php");
                    } elseif ($user['role'] === 'seller') {
                        header("Location: /25126463/seller/dashboard.php");
                    } else {
                        header("Location: /25126463/index.php");
                    }
                    exit;
                }
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Login failed. Please try again later.";
        }
    }
}

$page_title = 'Login - Electronic Store';
include __DIR__ . '/../includes/header.php';
?>

<div class="login-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="card auth-card">
                    
                    <div class="card-header auth-header">
                        <h3><i class="fas fa-lock me-2"></i>Welcome Back</h3>
                    </div>

                    <div class="card-body auth-body">
                        
                        <?php if ($success === 'registered'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Registration successful! Please login.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php elseif ($success === 'password_changed'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Password changed! Please login again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="loginForm" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-1"></i>Username or Email
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <span class="input-group-text" id="togglePassword" style="cursor: pointer;">
                                        <i class="fas fa-eye-slash"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <a href="/25126463/auth/forget-password.php" class="link-primary text-decoration-none small">Forgot Password?</a>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2" id="loginBtn">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>

                        <div class="auth-divider"><span>OR</span></div>

                        <div class="text-center">
                            <p class="text-muted small mb-2">New to Electronic Store?</p>
                            <a href="/25126463/auth/register.php" class="btn btn-outline-cyan w-100">
                                <i class="fas fa-user-plus me-2"></i>Create New Account
                            </a>
                        </div>
                    </div>

                    <div class="card-footer text-center">
                        <small class="text-muted"><i class="fas fa-shield-alt me-1"></i> Secure Login</small>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="/25126463/index.php" class="text-decoration-none text-white-50">
                        <i class="fas fa-arrow-left me-2"></i>Continue as Guest
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
            btn.disabled = true;
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>