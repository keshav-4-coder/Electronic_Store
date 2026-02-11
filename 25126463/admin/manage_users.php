<?php
/**
 * admin/manage_users.php - User & Role Management
 * Styled to match dashboard & manage_categories appearance
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /25126463/auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$success_msg  = '';
$error_msg    = '';

// ── Handle Role Update ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $target_user_id = (int)($_POST['user_id'] ?? 0);
    $new_role       = trim($_POST['role'] ?? '');

    if ($target_user_id <= 0 || !in_array($new_role, ['admin', 'seller', 'buyer'])) {
        $error_msg = "Invalid request.";
    } elseif ($target_user_id === (int)$_SESSION['user_id'] && $new_role !== 'admin') {
        $error_msg = "You cannot demote yourself from admin role.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE USERS SET role = ? WHERE user_id = ?");
            $stmt->execute([$new_role, $target_user_id]);
            $success_msg = "Role updated successfully.";
        } catch (PDOException $e) {
            $error_msg = "Failed to update role: " . $e->getMessage();
        }
    }
}

// ── Handle User Deletion ────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    if ($delete_id === (int)$_SESSION['user_id']) {
        $error_msg = "You cannot delete your own account.";
    } elseif ($delete_id <= 0) {
        $error_msg = "Invalid user ID.";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM USERS WHERE user_id = ?");
            $stmt->execute([$delete_id]);
            $success_msg = "User deleted successfully.";
        } catch (PDOException $e) {
            $error_msg = "Cannot delete user (may have orders/products): " . $e->getMessage();
        }
    }
    header("Location: manage_users.php" . (isset($_GET['role']) || isset($_GET['search']) ? '?' . http_build_query($_GET) : ''));
    exit;
}

// ── Filtering & Search ──────────────────────────────────────────────────
$role_filter = trim($_GET['role'] ?? '');
$search      = trim($_GET['search'] ?? '');

$where  = ["1=1"];
$params = [];

if ($role_filter && in_array($role_filter, ['admin', 'seller', 'buyer'])) {
    $where[] = "role = ?";
    $params[] = $role_filter;
}

if ($search !== '') {
    $where[] = "(full_name LIKE ? OR email LIKE ? OR phone_no LIKE ?)";
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like]);
}

$sql = "SELECT user_id, full_name, email, phone_no, role, created_at, is_active 
        FROM USERS 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
    $users = [];
}

$page_title = 'Manage Users - Electronic Store';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .admin-dashboard { background: #0f172a; padding: 2rem 0; min-height: 100vh; color: #f8fafc; }
    .page-title { color: #06b6d4; font-weight: 700; letter-spacing: -0.5px; }

    .form-card, .table-card {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header {
        background: rgba(255,255,255,0.02);
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding: 1rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .form-control, .form-select, .form-control:focus, .form-select:focus {
        background: #0f172a;
        border: 1px solid #334155;
        color: #f8fafc;
    }
    .form-control::placeholder, .form-select option { color: #64748b; }

    .table { color: #cbd5e1; margin-bottom: 0; }
    .table thead th {
        border-bottom: 1px solid #334155;
        color: #94a3b8;
        font-weight: 500;
        font-size: 0.8rem;
        text-transform: uppercase;
        padding: 0.9rem 1.25rem;
    }
    .table td {
        border-bottom: 1px solid rgba(255,255,255,0.03);
        vertical-align: middle;
        padding: 0.9rem 1.25rem;
    }
    tr:hover { background: rgba(6,182,212,0.07); }

    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #06b6d4;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1rem;
    }

    .badge-role {
        padding: 0.35em 0.75em;
        border-radius: 1rem;
        font-size: 0.82rem;
        font-weight: 600;
    }
    .badge-admin  { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
    .badge-seller { background: rgba(6,182,212,0.15);  color: #06b6d4; border: 1px solid rgba(6,182,212,0.3); }
    .badge-buyer  { background: rgba(148,163,184,0.15); color: #cbd5e1; border: 1px solid rgba(148,163,184,0.3); }

    .btn-cyan { background: #06b6d4; border: none; color: white; }
    .btn-cyan:hover { background: #0891b2; }
</style>

<div class="admin-dashboard">
    <div class="container">

        <div class="row mb-4 align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <h2 class="page-title"><i class="fas fa-users-cog me-2"></i>Manage Users</h2>
                <p class="text-muted small">Welcome, <?= htmlspecialchars($admin_name) ?> • <?= count($users) ?> users</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="dashboard.php" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="form-card mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="fas fa-filter me-2"></i>Filter Users</span>
            </div>
            <div class="p-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control"
                               placeholder="Search by name, email or phone..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="admin"  <?= $role_filter === 'admin'  ? 'selected' : '' ?>>Admin</option>
                            <option value="seller" <?= $role_filter === 'seller' ? 'selected' : '' ?>>Seller</option>
                            <option value="buyer"  <?= $role_filter === 'buyer'  ? 'selected' : '' ?>>Buyer</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-cyan w-100">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-card">
            <div class="card-header">
                <span class="fw-bold"><i class="fas fa-users me-2"></i>All Users</span>
                <span class="text-muted small"><?= count($users) ?> record<?= count($users) === 1 ? '' : 's' ?></span>
            </div>

            <?php if (count($users) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?= strtoupper(substr($user['full_name'] ?? '', 0, 1)) ?: '?' ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($user['full_name'] ?? '—') ?></div>
                                        <small class="text-muted">ID: <?= $user['user_id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small"><?= htmlspecialchars($user['email'] ?? '—') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($user['phone_no'] ?? 'No phone') ?></div>
                            </td>
                            <td>
                                <span class="badge-role badge-<?= $user['role'] ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" class="d-inline-flex align-items-center gap-2">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <select name="role" class="form-select form-select-sm" style="width: 110px;">
                                        <option value="buyer"  <?= $user['role'] === 'buyer'  ? 'selected' : '' ?>>Buyer</option>
                                        <option value="seller" <?= $user['role'] === 'seller' ? 'selected' : '' ?>>Seller</option>
                                        <option value="admin"  <?= $user['role'] === 'admin'  ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn btn-sm btn-cyan" title="Update Role">
                                        <i class="fas fa-save"></i>
                                    </button>

                                    <?php if ($user['user_id'] !== (int)$_SESSION['user_id']): ?>
                                    <a href="?delete_id=<?= $user['user_id'] ?><?= $role_filter || $search ? '&' . http_build_query(['role' => $role_filter, 'search' => $search]) : '' ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this user permanently?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-5 text-center text-muted">
                <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                <h5>No users found</h5>
                <p class="small">Try adjusting the search or role filter.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => bootstrap.Alert.getOrCreateInstance(el).close());
}, 5000);
</script>