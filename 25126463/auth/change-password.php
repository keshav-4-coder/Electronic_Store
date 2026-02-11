<?php
/**
 * auth/change-password.php
 * Secure Password Change with Security Question Validation
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure user is logged in
require_login();

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Fetch User Data
try {
    $stmt = $conn->prepare("SELECT security_question, security_answer FROM USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    $error = "A system error occurred. Please try again later.";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provided_answer = trim($_POST['security_answer'] ?? '');
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($provided_answer) || empty($new_pass) || empty($confirm_pass)) {
        $error = "Please fill in all security fields.";
    } 
    elseif (!password_verify($provided_answer, $user['security_answer'])) {
        $error = "The security answer provided does not match our records.";
    } 
    elseif (strlen($new_pass) < 6) {
        $error = "Your new password must be at least 6 characters long.";
    } 
    elseif ($new_pass !== $confirm_pass) {
        $error = "Confirmation password does not match.";
    } 
    else {
        try {
            $hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);
            
            $update = $conn->prepare("UPDATE USERS SET password = ? WHERE user_id = ?");
            $update->execute([$hashed_password, $user_id]);
            
            $success = "Password updated successfully! Redirecting...";
            log_activity($user_id, 'PASSWORD_CHANGE', 'User updated password via security question');
            
        } catch (PDOException $e) {
            error_log("Update Error: " . $e->getMessage());
            $error = "Could not update password. Please contact support.";
        }
    }
}

$page_title = 'Change Password';
include __DIR__ . '/../includes/header.php';
?>

<div class="password-page-wrapper">
    <div class="password-page-container">
        <div class="card password-card">
                        
                        <div class="card-header auth-header">
                            <h3><i class="fas fa-key me-2"></i>Change Password</h3>
                        </div>

                        <div class="card-body auth-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <script>setTimeout(() => { window.location.href = '/25126463/customer/index.php'; }, 2500);</script>
                            <?php endif; ?>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" id="changePasswordForm" class="auth-form">
                                
                                <div class="form-section-title">
                                    <i class="fas fa-user-shield"></i>
                                    Identity Verification
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
                                           name="security_answer" 
                                           id="security_answer" 
                                           class="form-control" 
                                           placeholder="Enter your answer" 
                                           required 
                                           autocomplete="off">
                                </div>

                                <div class="form-section-title mt-4">
                                    <i class="fas fa-lock"></i>
                                    New Password
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">
                                        New Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               name="new_password" 
                                               id="new_password" 
                                               class="form-control" 
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
                                               name="confirm_password" 
                                               id="confirm_password" 
                                               class="form-control" 
                                               placeholder="Re-enter new password"
                                               required>
                                        <span class="input-group-text" id="toggleConfirm" role="button">
                                            <i class="fas fa-eye-slash"></i>
                                        </span>
                                    </div>
                                    <small class="text-muted" id="matchText"></small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mt-3" id="changeBtn">
                                    <i class="fas fa-save me-2"></i>Update Password
                                </button>
                            </form>
                        </div>

                        <div class="card-footer text-center">
                            <a href="/25126463/index.php" class="link-secondary text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
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
    const form = document.getElementById('changePasswordForm');
    const btn = document.getElementById('changeBtn');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            if (newPass.value !== confirmPass.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>