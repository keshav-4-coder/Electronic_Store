<?php
/**
 * admin/settings.php
 * Safe Admin Settings Page (No Dangerous Actions)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /25126463/auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';

$admin_name = $_SESSION['full_name'] ?? 'Admin';
$success_msg = '';
$error_msg = '';

/* ───────────────── Site Info (static for now) ───────────────── */
$site_settings = [
    'Site Name'      => 'Electronic Store',
    'Support Email'  => 'support@electronicstore.com',
    'Environment'    => 'Development',
    'Timezone'       => date_default_timezone_get(),
];

/* ───────────────── Handle Actions ───────────────── */
$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'toggle_maintenance':
            $flag = __DIR__ . '/../storage/maintenance.flag';

            if (file_exists($flag)) {
                unlink($flag);
                $success_msg = "Maintenance mode disabled.";
            } else {
                file_put_contents($flag, 'ON');
                $success_msg = "Maintenance mode enabled.";
            }
            break;

        case 'clear_cache':
            // Placeholder for future cache system
            $success_msg = "Cache cleared successfully (placeholder).";
            break;
    }
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

/* ───────────────── Table Statistics ───────────────── */
$table_stats = [];
try {
    $tables = ['USERS', 'CATEGORY', 'PRODUCTS', 'ORDERS'];
    foreach ($tables as $tbl) {
        $count = $conn->query("SELECT COUNT(*) FROM $tbl")->fetchColumn();
        $table_stats[$tbl] = $count;
    }
} catch (Exception $e) {
    $error_msg = "Unable to read table stats.";
}

$page_title = 'Admin Settings';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .admin-dashboard {
        background: #0f172a;
        min-height: 100vh;
        padding: 2rem 0;
        color: #f8fafc;
    }
    .page-title {
        color: #06b6d4;
        font-weight: 700;
    }
    .card {
        background: #1e293b;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
        margin-bottom: 1.5rem;
    }
    .card-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        font-weight: 600;
    }
    .stat-badge {
        background: rgba(6,182,212,0.15);
        color: #06b6d4;
        border-radius: 1rem;
        padding: .4em .9em;
        font-weight: 600;
        display: inline-block;
    }
</style>

<div class="admin-dashboard">
    <div class="container">

        <!-- HEADER -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <h2 class="page-title">
                    <i class="fas fa-cog me-2"></i>Admin Settings
                </h2>
                <p class="text-muted small">
                    Logged in as <?= htmlspecialchars($admin_name) ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="dashboard.php" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- SITE INFO -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Site Information
            </div>
            <div class="p-4">
                <div class="row">
                    <?php foreach ($site_settings as $label => $value): ?>
                        <div class="col-md-3 mb-3">
                            <div class="small text-muted"><?= $label ?></div>
                            <div><?= htmlspecialchars($value) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- DATABASE STATS -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-database me-2"></i>Database Overview
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <?php foreach ($table_stats as $table => $count): ?>
                        <div class="col-md-3 text-center">
                            <h4><?= $count ?></h4>
                            <span class="stat-badge"><?= $table ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ADMIN TOOLS -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-tools me-2"></i>Admin Utilities
            </div>
            <div class="p-4">
                <div class="row g-4">


                    <div class="col-md-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="clear_cache">
                            <button class="btn btn-outline-info w-100">
                                <i class="fas fa-broom me-2"></i>
                                Clear Cache
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
