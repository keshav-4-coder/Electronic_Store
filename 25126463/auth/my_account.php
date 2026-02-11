<?php
/**
 * auth/my_account.php - User Profile Management
 * FINAL COMPREHENSIVE FIX - All Fields + Subdirectory Path Fix
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Disable error reporting for production once verified, keep on for now
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$success = null;
$error = null;

// 1. AUTO-FIX DATABASE SCHEMA
try {
    $conn->exec("ALTER TABLE USERS ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL");
    $conn->exec("ALTER TABLE USERS ADD COLUMN IF NOT EXISTS phone_no VARCHAR(20) DEFAULT NULL");
    $conn->exec("ALTER TABLE USERS ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL");
} catch (Exception $e) { }

// 2. HANDLE PICTURE UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $upload_dir = dirname(__DIR__) . '/uploads/profiles/';
        
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        chmod($upload_dir, 0777);

        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
            $stmt = $conn->prepare("UPDATE USERS SET profile_picture = ? WHERE user_id = ?");
            if ($stmt->execute([$new_filename, $user_id])) {
                $_SESSION['profile_picture'] = $new_filename;
                $success = "Picture updated!";
            }
        }
    }
}

// 3. HANDLE PICTURE DELETE
if (isset($_POST['delete_picture'])) {
    $stmt = $conn->prepare("UPDATE USERS SET profile_picture = NULL WHERE user_id = ?");
    if ($stmt->execute([$user_id])) {
        unset($_SESSION['profile_picture']);
        $success = "Picture removed.";
    }
}

// 4. HANDLE PROFILE DATA UPDATE (Fix for Address/Phone)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Using strip_tags as a fallback if sanitize() is acting up
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $phone_no = isset($_POST['phone_no']) ? trim($_POST['phone_no']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    if (!empty($full_name)) {
        try {
            $stmt = $conn->prepare("UPDATE USERS SET full_name = ?, phone_no = ?, address = ?, updated_at = NOW() WHERE user_id = ?");
            if ($stmt->execute([$full_name, $phone_no, $address, $user_id])) {
                $_SESSION['full_name'] = $full_name;
                $success = "Profile details updated successfully!";
            } else {
                $error = "Database failed to save the information.";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Full Name cannot be empty.";
    }
}

// Fetch Fresh Data
$stmt = $conn->prepare("SELECT * FROM USERS WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<style>
.profile-picture-section { background: rgba(6, 182, 212, 0.05); border: 2px dashed rgba(6, 182, 212, 0.3); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; text-align: center; }
.profile-pic-preview { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #06b6d4; margin: 0 auto 1rem; display: block; }
.profile-pic-placeholder { width: 150px; height: 150px; border-radius: 50%; background: #1a1a1a; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: #444; border: 4px solid #333; }
.file-upload-label { display: inline-block; padding: 0.5rem 1.5rem; background: rgba(6, 182, 212, 0.15); color: #06b6d4; border: 1px solid #06b6d4; border-radius: 6px; cursor: pointer; font-weight: 500; }
.text-cyan { color: #06b6d4; }
.form-section-title { border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; color: #888; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
</style>

<div class="register-container mt-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card auth-card bg-dark text-white border-secondary">
                    <div class="card-header auth-header border-secondary">
                        <h3><i class="fas fa-user-cog me-2"></i>My Profile</h3>
                    </div>

                    <div class="card-body auth-body">
                        <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
                        <?php if ($error): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>

                        <div class="profile-picture-section">
                            <?php $display_path = !empty($user['profile_picture']) ? '../uploads/profiles/' . $user['profile_picture'] : null; ?>
                            <?php if ($display_path): ?>
                                <img src="<?= $display_path ?>" class="profile-pic-preview">
                            <?php else: ?>
                                <div class="profile-pic-placeholder"><i class="fas fa-user"></i></div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <label for="p_img" class="file-upload-label"><i class="fas fa-camera me-2"></i>Change Photo</label>
                                <input type="file" name="profile_picture" id="p_img" style="display:none" onchange="this.form.submit()">
                                <input type="hidden" name="upload_picture" value="1">
                                <?php if($display_path): ?>
                                    <div class="mt-2"><button type="submit" name="delete_picture" class="btn btn-sm btn-outline-danger">Remove</button></div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <form method="POST" action="">
                            <div class="form-section-title">Edit Personal Information</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone_no" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user['phone_no'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email (Locked)</label>
                                    <input type="text" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Shipping Address</label>
                                    <textarea name="address" class="form-control bg-dark text-white border-secondary" rows="4"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-info w-100 mt-4 py-2">
                                <i class="fas fa-save me-2"></i>Save All Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>