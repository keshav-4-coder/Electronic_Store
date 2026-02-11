<?php
/**
 * auth/reset-password.php
 * Complete Password Reset Process
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user came from forgot-password.php
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: /25126463/auth/forgot-password.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

$error = null;
$success = null;
$user_id = $_SESSION['reset_user_id'];

// Fetch security question and hashed answer
try {
    $stmt = $conn->prepare("SELECT security_question, security_answer FROM USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        unset($_SESSION['reset_user_id'], $_SESSION['reset_username']);
        header("Location: /25126463/auth/forgot-password.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    $error = "A system error occurred.";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $security_answer = trim($_POST['security_answer'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($security_answer) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } 
    elseif (!password_verify($security_answer, $user['security_answer'])) {
        $error = "Incorrect security answer. Please try again.";
    } 
    elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } 
    elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } 
    else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            
            $update = $conn->prepare("UPDATE USERS SET password = ? WHERE user_id = ?");
            $update->execute([$hashed_password, $user_id]);
            
            // Clear reset session data
            unset($_SESSION['reset_user_id'], $_SESSION['reset_username']);
            
            $success = "Password reset successful! Redirecting to login...";
            
        } catch (PDOException $e) {
            error_log("Password Update Error: " . $e->getMessage());
            $error = "Could not reset password. Please try again.";
        }
    }
}

$page_title = 'Reset Password';
include __DIR__ . '/../includes/header.php';
?>

<div class="password-page-wrapper">
    <div class="password-page-container">
        <div class="card password-card">
                        
                        <div class="card-header auth-header">
                            <h3><i class="fas fa-key me-2"></i>Reset Your Password</h3>
                        </div>

                        <div class="card-body auth-body">
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <script>setTimeout(() => { window.location.href = '/25126463/auth/login.php?reset=success'; }, 2500);</script>
                            <?php endif; ?>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (!$success): ?>
                                <div class="text-center mb-4">
                                    <div class="reset-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <p class="text-muted mt-3">Resetting password for: <strong class="text-white"><?= htmlspecialchars($_SESSION['reset_username']) ?></strong></p>
                                </div>

                                <form method="POST" id="resetForm" class="auth-form">
                                    
                                    <div class="form-section-title">
                                        <i class="fas fa-user-shield"></i>
                                        Security Verification
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Your Security Question</label>
                                        <div class="security-question-display">
                                            <i class="fas fa-quote-left me-2"></i>
                                            <?= htmlspecialchars($user['security_question']) ?>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="security_answer" class="form-label">
                                            Security Answer <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="security_answer" 
                                               name="security_answer" 
                                               placeholder="Enter your answer"
                                               required 
                                               autocomplete="off"
                                               autofocus>
                                    </div>

                                    <div class="form-section-title mt-4">
                                        <i class="fas fa-lock"></i>
                                        Create New Password
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">
                                            New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="new_password" 
                                                   name="new_password" 
                                                   placeholder="Enter new password"
                                                   required>
                                            <span class="input-group-text" id="toggleNew" role="button">
                                                <i class="fas fa-eye-slash"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>

                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">
                                            Confirm New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="confirm_password" 
                                                   name="confirm_password" 
                                                   placeholder="Re-enter new password"
                                                   required>
                                            <span class="input-group-text" id="toggleConfirm" role="button">
                                                <i class="fas fa-eye-slash"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted" id="matchText"></small>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 mt-3" id="resetBtn">
                                        <i class="fas fa-check me-2"></i>Reset Password
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>

                        <div class="card-footer text-center">
                            <a href="/25126463/auth/login.php" class="link-secondary text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>

                    </div>
                </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggles
    function setupPasswordToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);
        
        if (toggle && input) {
            toggle.addEventListener('click', function() {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
    }
    
    setupPasswordToggle('toggleNew', 'new_password');
    setupPasswordToggle('toggleConfirm', 'confirm_password');

    // Password match validation
    const newPass = document.getElementById('new_password');
    const confirmPass = document.getElementById('confirm_password');
    const matchText = document.getElementById('matchText');
    
    if (confirmPass && matchText && newPass) {
        confirmPass.addEventListener('input', function() {
            if (this.value === '') {
                matchText.textContent = '';
                matchText.className = 'text-muted';
            } else if (this.value === newPass.value) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'text-success';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'text-danger';
            }
        });
    }

    // Form submission
    const form = document.getElementById('resetForm');
    const btn = document.getElementById('resetBtn');
    
    if (form && btn) {
        form.addEventListener('submit', function(e) {
            if (newPass && confirmPass && newPass.value !== confirmPass.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting...';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>