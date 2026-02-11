<?php
/**
 * auth/register.php - Professional Registration Page
 * Cyan/Teal Dark Theme
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $phone_no = trim($_POST['phone_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['buyer', 'seller']) ? $_POST['role'] : 'buyer';
    $sec_q = trim($_POST['security_question'] ?? '');
    $sec_a = trim($_POST['security_answer'] ?? '');

    // Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif (empty($sec_q) || empty($sec_a)) {
        $error = "Please select a security question and provide an answer.";
    } else {
        try {
            // Check if username or email already exists
            $check = $conn->prepare("SELECT COUNT(*) FROM USERS WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            
            if ($check->fetchColumn() > 0) {
                $error = "Username or email already taken.";
            } else {
                // Hash password and security answer
                $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
                $hashed_ans = password_hash($sec_a, PASSWORD_BCRYPT);

                // Insert new user
                $stmt = $conn->prepare("
                    INSERT INTO USERS 
                    (full_name, username, email, password, phone_no, address, role, security_question, security_answer)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $full_name, 
                    $username, 
                    $email, 
                    $hashed_pw,
                    $phone_no ?: null, 
                    $address ?: null, 
                    $role,
                    $sec_q, 
                    $hashed_ans
                ]);

                header("Location: /25126463/auth/login.php?success=registered");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "Registration failed. Please try again later.";
        }
    }
}

$page_title = 'Register - Electronic Store';
include __DIR__ . '/../includes/header.php';
?>

<div class="register-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card auth-card register-card">
                    
                    <!-- Card Header -->
                    <div class="card-header auth-header">
                        <h3><i class="fas fa-user-plus me-2"></i>Create Your Account</h3>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body auth-body">
                        
                        <!-- Error Message -->
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Registration Form -->
                        <form method="POST" id="registerForm" class="register-form" novalidate>
                            
                            <!-- Personal Information -->
                            <div class="form-section-title">
                                <i class="fas fa-user"></i>
                                Personal Information
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="full_name" class="form-label">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="full_name" 
                                           name="full_name" 
                                           placeholder="Enter your full name"
                                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                           required 
                                           autofocus>
                                </div>

                                <div class="col-md-6">
                                    <label for="username" class="form-label">
                                        Username <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           placeholder="Choose a username"
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                           required>
                                    <small class="text-muted">Alphanumeric, 4-20 characters</small>
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">
                                        Email <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="your@email.com"
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label for="phone_no" class="form-label">
                                        Phone Number <small class="text-muted">(optional)</small>
                                    </label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone_no" 
                                           name="phone_no" 
                                           placeholder="+977 98XXXXXXXX"
                                           value="<?= htmlspecialchars($_POST['phone_no'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label for="role" class="form-label">
                                        Account Type <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="buyer" <?= (!isset($_POST['role']) || $_POST['role'] === 'buyer') ? 'selected' : '' ?>>
                                            Customer
                                        </option>
                                        <!-- <option value="seller" <?= (isset($_POST['role']) && $_POST['role'] === 'seller') ? 'selected' : '' ?>>
                                            Seller
                                        </option> -->
                                    </select>
                                    <small class="text-muted">Default as customer to shop</small>
                                </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">
                                        Address <small class="text-muted">(optional)</small>
                                    </label>
                                    <textarea class="form-control" 
                                              id="address" 
                                              name="address" 
                                              rows="2" 
                                              placeholder="Enter your address"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Password Section -->
                            <div class="form-section-title mt-4">
                                <i class="fas fa-lock"></i>
                                Security
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">
                                        Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Create a strong password"
                                               required>
                                        <span class="input-group-text" id="togglePassword" role="button" tabindex="0">
                                            <i class="fas fa-eye-slash"></i>
                                        </span>
                                    </div>
                                    <div class="password-strength mt-2">
                                        <div class="password-strength-bar" id="strengthBar"></div>
                                    </div>
                                    <small class="text-muted" id="strengthText">Minimum 6 characters</small>
                                </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">
                                        Confirm Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               placeholder="Re-enter your password"
                                               required>
                                        <span class="input-group-text" id="toggleConfirm" role="button" tabindex="0">
                                            <i class="fas fa-eye-slash"></i>
                                        </span>
                                    </div>
                                    <small class="text-muted" id="matchText"></small>
                                </div>
                            </div>

                            <!-- Security Question -->
                            <div class="security-section mt-4">
                                <h6>
                                    <i class="fas fa-shield-alt"></i>
                                    Security Question (for password recovery)
                                </h6>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="security_question" class="form-label">
                                            Question <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="security_question" name="security_question" required>
                                            <option value="">Select a security question</option>
                                            <option value="What is your pet's name?">What is your pet's name?</option>
                                            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                                            <option value="What city were you born in?">What city were you born in?</option>
                                            <option value="What was your first school?">What was your first school?</option>
                                            <option value="What is your favorite book?">What is your favorite book?</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label for="security_answer" class="form-label">
                                            Answer <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="security_answer" 
                                               name="security_answer" 
                                               placeholder="Enter your answer"
                                               autocomplete="off"
                                               required>
                                        <small class="text-muted">This will be used to recover your account</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="terms-checkbox">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="terms" 
                                           required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="/25126463/customer/terms.php" target="_blank">Terms & Conditions</a> 
                                        and <a href="/25126463/customer/privacy.php" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-primary w-100 mt-4" id="registerBtn">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>

                        </form>
                    </div>

                    <!-- Card Footer -->
                    <div class="card-footer text-center">
                        <small class="text-muted">
                            Already have an account? 
                            <a href="/25126463/auth/login.php" class="link-primary">Sign in here</a>
                        </small>
                    </div>

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
    
    setupPasswordToggle('togglePassword', 'password');
    setupPasswordToggle('toggleConfirm', 'confirm_password');

    // Password strength indicator
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[@$!%*?&#]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength === 0) {
                strengthBar.style.width = '0%';
                strengthText.textContent = 'Minimum 6 characters';
                strengthText.className = 'text-muted';
            } else if (strength <= 2) {
                strengthBar.classList.add('password-strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.className = 'text-danger';
            } else if (strength <= 4) {
                strengthBar.classList.add('password-strength-medium');
                strengthText.textContent = 'Medium strength';
                strengthText.className = 'text-warning';
            } else {
                strengthBar.classList.add('password-strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.className = 'text-success';
            }
        });
    }

    // Password match indicator
    const confirmInput = document.getElementById('confirm_password');
    const matchText = document.getElementById('matchText');
    
    if (confirmInput && matchText && passwordInput) {
        confirmInput.addEventListener('input', function() {
            if (this.value === '') {
                matchText.textContent = '';
                matchText.className = 'text-muted';
            } else if (this.value === passwordInput.value) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'text-success';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'text-danger';
            }
        });
    }

    // Form validation
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms');

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (!terms.checked) {
                e.preventDefault();
                alert('Please agree to the Terms & Conditions');
                return false;
            }

            // Add loading state
            registerBtn.classList.add('btn-loading');
            registerBtn.disabled = true;
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>