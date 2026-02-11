<?php
/**
 * auth/forgot-password.php
 * Password Recovery via Security Question
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /25126463/customer/index.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

$error = null;
$step = 1; // Step 1: Username, Step 2: Security Question
$username = '';
$security_question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        // Step 1: Verify username/email
        $username = trim($_POST['username'] ?? '');
        
        if (empty($username)) {
            $error = "Please enter your username or email.";
        } else {
            try {
                $stmt = $conn->prepare("SELECT user_id, username, security_question FROM USERS WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $_SESSION['reset_user_id'] = $user['user_id'];
                    $_SESSION['reset_username'] = $user['username'];
                    $security_question = $user['security_question'];
                    $step = 2;
                } else {
                    $error = "No account found with that username or email.";
                }
            } catch (PDOException $e) {
                error_log("Forgot Password Error: " . $e->getMessage());
                $error = "An error occurred. Please try again later.";
            }
        }
    }
}

$page_title = 'Forgot Password';
include __DIR__ . '/../includes/header.php';
?>

<div class="password-page-wrapper">
    <div class="password-page-container">
        <div class="card password-card">
                        
                        <div class="card-header auth-header">
                            <h3><i class="fas fa-unlock-alt me-2"></i>Forgot Password</h3>
                        </div>

                        <div class="card-body auth-body">
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($step === 1): ?>
                                <!-- Step 1: Enter Username -->
                                <div class="text-center mb-4">
                                    <i class="fas fa-user-lock" style="font-size: 3rem; color: #40c4ff;"></i>
                                    <p class="mt-3 text-muted">Enter your username or email to begin password recovery</p>
                                </div>

                                <form method="POST" class="auth-form">
                                    <input type="hidden" name="step" value="1">
                                    
                                    <div class="mb-4">
                                        <label for="username" class="form-label">
                                            Username or Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="username" 
                                               name="username" 
                                               placeholder="Enter your username or email"
                                               value="<?= htmlspecialchars($username) ?>"
                                               required 
                                               autofocus>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-arrow-right me-2"></i>Continue
                                    </button>
                                </form>

                            <?php else: ?>
                                <!-- Step 2: Security Question (handled by reset-password.php) -->
                                <script>
                                    window.location.href = '/25126463/auth/reset-password.php';
                                </script>
                            <?php endif; ?>

                        </div>

                        <div class="card-footer text-center">
                            <small class="text-muted">
                                Remember your password? 
                                <a href="/25126463/auth/login.php" class="link-primary">Sign in here</a>
                            </small>
                        </div>

                    </div>
                </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>