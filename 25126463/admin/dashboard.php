<?php
/**
 * admin/dashboard.php - Admin Dashboard Overview
 * Polished alignment and professional UI while maintaining original structure.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /25126463/auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// --- DATA FETCHING (Your original SQL logic preserved) ---
try {
    // User Statistics
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS");
    $total_users = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS WHERE role = 'buyer'");
    $total_buyers = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS WHERE role = 'seller'");
    $total_sellers = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS WHERE is_active = 0");
    $inactive_users = $stmt->fetchColumn();
    
    // Product & Order Statistics
    $stmt = $conn->query("SELECT COUNT(*) FROM PRODUCTS");
    $total_products = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM PRODUCTS WHERE is_active = 1");
    $active_products = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM PRODUCTS WHERE stock < 10 AND is_active = 1");
    $low_stock_products = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM ORDERS");
    $total_orders = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM ORDERS WHERE status = 'pending'");
    $pending_orders = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM ORDERS WHERE status = 'processing'");
    $processing_orders = $stmt->fetchColumn();

    // Revenue Statistics
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE status IN ('delivered', 'shipped', 'processing')");
    $total_revenue = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE DATE(order_date) = CURDATE() AND status IN ('delivered', 'shipped', 'processing')");
    $today_revenue = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) AND status IN ('delivered', 'shipped', 'processing')");
    $week_revenue = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE()) AND status IN ('delivered', 'shipped', 'processing')");
    $month_revenue = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM CATEGORY");
    $total_categories = $stmt->fetchColumn();

    // Tables Data
    $recent_users = $conn->query("SELECT user_id, full_name, email, role, created_at, is_active FROM USERS ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recent_orders = $conn->query("SELECT o.order_id, o.total_amount, o.status, o.order_date, u.full_name as customer_name FROM ORDERS o JOIN USERS u ON o.user_id = u.user_id ORDER BY o.order_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $low_stock_items = $conn->query("SELECT p.product_id, p.product_name, p.stock, p.price, c.category_name FROM PRODUCTS p LEFT JOIN CATEGORY c ON p.category_id = c.category_id WHERE p.stock < 10 AND p.is_active = 1 ORDER BY p.stock ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

$page_title = 'Admin Dashboard - Electronic Store';
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Dark Theme Pro Adjustments */
    .admin-dashboard { background: #0f172a; padding: 2rem 0; min-height: 100vh; color: #f8fafc; }
    .page-title { color: #06b6d4; font-weight: 700; letter-spacing: -0.5px; }
    
    .stat-card {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        transition: 0.3s;
    }
    .stat-card:hover { border-color: #06b6d4; transform: translateY(-3px); }
    .stat-icon {
        width: 50px; height: 50px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; margin-right: 1rem;
    }
    .stat-icon-primary { background: rgba(6, 182, 212, 0.1); color: #06b6d4; }
    .stat-icon-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-icon-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .stat-icon-info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    
    .stat-value { font-size: 1.5rem; font-weight: 700; margin-bottom: 0; line-height: 1; }
    .stat-label { color: #94a3b8; font-size: 0.85rem; margin-bottom: 4px; }

    .dashboard-card {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }
    .card-header { 
        background: rgba(255,255,255,0.02); 
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding: 1rem 1.25rem;
        display: flex; justify-content: space-between; align-items: center;
    }
    
    /* Table Fixes */
    .table { color: #cbd5e1; margin-bottom: 0; }
    .table thead th { border-bottom: 1px solid #334155; color: #94a3b8; font-weight: 500; font-size: 0.8rem; text-transform: uppercase; }
    .table td { border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; padding: 0.75rem 1.25rem; }
    
    .user-avatar {
        width: 32px; height: 32px; border-radius: 50%;
        background: #06b6d4; color: white;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 0.8rem;
    }

    .quick-action-btn {
        background: #334155;
        color: white; padding: 12px; border-radius: 8px;
        text-align: center; text-decoration: none;
        transition: 0.3s; display: flex; flex-direction: column; align-items: center;
    }
    .quick-action-btn:hover { background: #06b6d4; color: white; }
    .quick-action-btn i { font-size: 1.2rem; margin-bottom: 5px; }
</style>

<div class="admin-dashboard">
    <div class="container">
        
        <div class="row mb-4 align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <h2 class="page-title"><i class="fas fa-chart-pie me-2"></i>Admin Console</h2>
                <p class="text-muted small">Welcome, <?= htmlspecialchars($admin_name) ?></p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="btn-group">
                    <a href="manage_users.php" class="btn btn-outline-info btn-sm">Users</a>
                    <a href="manage_products.php" class="btn btn-outline-info btn-sm">Products</a>
                    <a href="manage_orders.php" class="btn btn-outline-info btn-sm">Orders</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-success"><i class="fas fa-dollar-sign"></i></div>
                    <div>
                        <p class="stat-label">Total Revenue</p>
                        <h3 class="stat-value">Rs.<?= number_format($total_revenue) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-info"><i class="fas fa-calendar-day"></i></div>
                    <div>
                        <p class="stat-label">Today's Revenue</p>
                        <h3 class="stat-value">Rs.<?= number_format($today_revenue) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-primary"><i class="fas fa-shopping-cart"></i></div>
                    <div>
                        <p class="stat-label">Total Orders</p>
                        <h3 class="stat-value"><?= $total_orders ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <p class="stat-label">Low Stock Items</p>
                        <h3 class="stat-value text-warning"><?= $low_stock_products ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <span class="fw-bold"><i class="fas fa-users me-2"></i>Recent Users</span>
                        <a href="manage_users.php" class="text-info small text-decoration-none">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td class="d-flex align-items-center">
                                        <div class="user-avatar me-2"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
                                        <div>
                                            <div class="small fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?= $user['email'] ?></div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-secondary small"><?= $user['role'] ?></span></td>
                                    <td class="small text-muted"><?= date('M d', strtotime($user['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <span class="fw-bold"><i class="fas fa-receipt me-2"></i>Recent Orders</span>
                        <a href="manage_orders.php" class="text-info small text-decoration-none">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td class="fw-bold text-info">#<?= $order['order_id'] ?></td>
                                    <td class="small"><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td class="small fw-bold">Rs.<?= number_format($order['total_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $order['status']=='pending'?'warning':'success' ?> small">
                                            <?= $order['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <span class="fw-bold"><i class="fas fa-warehouse me-2"></i>Inventory Warning</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="small text-muted"><?= $item['category_name'] ?></td>
                                    <td><span class="text-danger fw-bold"><?= $item['stock'] ?> Left</span></td>
                                    <td><a href="manage_products.php?edit=<?= $item['product_id'] ?>" class="btn btn-primary btn-sm">Restock</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-4 col-md-2">
                <a href="manage_users.php?add=1" class="quick-action-btn"><i class="fas fa-user-plus"></i><span class="small">Add User</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="manage_products.php?add=1" class="quick-action-btn"><i class="fas fa-box-open"></i><span class="small">Add Product</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="manage_categories.php" class="quick-action-btn"><i class="fas fa-tags"></i><span class="small">Category</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="manage_orders.php?status=pending" class="quick-action-btn"><i class="fas fa-clock"></i><span class="small">Pending</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="settings.php" class="quick-action-btn"><i class="fas fa-cog"></i><span class="small">Settings</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="../index.php" class="quick-action-btn"><i class="fas fa-eye"></i><span class="small">Live Site</span></a>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?><?php
/**
 * admin/dashboard.php - Admin Dashboard Overview
 * Polished alignment and professional UI while maintaining original structure.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /25126463/auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// --- DATA FETCHING (Your original SQL logic preserved) ---
try {
    // User Statistics
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS");
    $total_users = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS WHERE role = 'buyer'");
    $total_buyers = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS WHERE role = 'seller'");
    $total_sellers = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM USERS WHERE is_active = 0");
    $inactive_users = $stmt->fetchColumn();
    
    // Product & Order Statistics
    $stmt = $conn->query("SELECT COUNT(*) FROM PRODUCTS");
    $total_products = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM PRODUCTS WHERE is_active = 1");
    $active_products = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM PRODUCTS WHERE stock < 10 AND is_active = 1");
    $low_stock_products = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM ORDERS");
    $total_orders = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM ORDERS WHERE status = 'pending'");
    $pending_orders = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COUNT(*) FROM ORDERS WHERE status = 'processing'");
    $processing_orders = $stmt->fetchColumn();

    // Revenue Statistics
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE status IN ('delivered', 'shipped', 'processing')");
    $total_revenue = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE DATE(order_date) = CURDATE() AND status IN ('delivered', 'shipped', 'processing')");
    $today_revenue = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) AND status IN ('delivered', 'shipped', 'processing')");
    $week_revenue = $stmt->fetchColumn();
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM ORDERS WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE()) AND status IN ('delivered', 'shipped', 'processing')");
    $month_revenue = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM CATEGORY");
    $total_categories = $stmt->fetchColumn();

    // Tables Data
    $recent_users = $conn->query("SELECT user_id, full_name, email, role, created_at, is_active FROM USERS ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recent_orders = $conn->query("SELECT o.order_id, o.total_amount, o.status, o.order_date, u.full_name as customer_name FROM ORDERS o JOIN USERS u ON o.user_id = u.user_id ORDER BY o.order_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $low_stock_items = $conn->query("SELECT p.product_id, p.product_name, p.stock, p.price, c.category_name FROM PRODUCTS p LEFT JOIN CATEGORY c ON p.category_id = c.category_id WHERE p.stock < 10 AND p.is_active = 1 ORDER BY p.stock ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

$page_title = 'Admin Dashboard - Electronic Store';
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Dark Theme Pro Adjustments */
    .admin-dashboard { background: #0f172a; padding: 2rem 0; min-height: 100vh; color: #f8fafc; }
    .page-title { color: #06b6d4; font-weight: 700; letter-spacing: -0.5px; }
    
    .stat-card {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        transition: 0.3s;
    }
    .stat-card:hover { border-color: #06b6d4; transform: translateY(-3px); }
    .stat-icon {
        width: 50px; height: 50px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; margin-right: 1rem;
    }
    .stat-icon-primary { background: rgba(6, 182, 212, 0.1); color: #06b6d4; }
    .stat-icon-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-icon-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .stat-icon-info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    
    .stat-value { font-size: 1.5rem; font-weight: 700; margin-bottom: 0; line-height: 1; }
    .stat-label { color: #94a3b8; font-size: 0.85rem; margin-bottom: 4px; }

    .dashboard-card {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }
    .card-header { 
        background: rgba(255,255,255,0.02); 
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding: 1rem 1.25rem;
        display: flex; justify-content: space-between; align-items: center;
    }
    
    /* Table Fixes */
    .table { color: #cbd5e1; margin-bottom: 0; }
    .table thead th { border-bottom: 1px solid #334155; color: #94a3b8; font-weight: 500; font-size: 0.8rem; text-transform: uppercase; }
    .table td { border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; padding: 0.75rem 1.25rem; }
    
    .user-avatar {
        width: 32px; height: 32px; border-radius: 50%;
        background: #06b6d4; color: white;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 0.8rem;
    }

    .quick-action-btn {
        background: #334155;
        color: white; padding: 12px; border-radius: 8px;
        text-align: center; text-decoration: none;
        transition: 0.3s; display: flex; flex-direction: column; align-items: center;
    }
    .quick-action-btn:hover { background: #06b6d4; color: white; }
    .quick-action-btn i { font-size: 1.2rem; margin-bottom: 5px; }
</style>

<div class="admin-dashboard">
    <div class="container">
        
        <div class="row mb-4 align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <h2 class="page-title"><i class="fas fa-chart-pie me-2"></i>Admin Console</h2>
                <p class="text-muted small">Welcome, <?= htmlspecialchars($admin_name) ?></p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="btn-group">
                    <a href="manage_users.php" class="btn btn-outline-info btn-sm">Users</a>
                    <a href="manage_products.php" class="btn btn-outline-info btn-sm">Products</a>
                    <a href="manage_orders.php" class="btn btn-outline-info btn-sm">Orders</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-success"><i class="fas fa-dollar-sign"></i></div>
                    <div>
                        <p class="stat-label">Total Revenue</p>
                        <h3 class="stat-value">Rs.<?= number_format($total_revenue) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-info"><i class="fas fa-calendar-day"></i></div>
                    <div>
                        <p class="stat-label">Today's Revenue</p>
                        <h3 class="stat-value">Rs.<?= number_format($today_revenue) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-primary"><i class="fas fa-shopping-cart"></i></div>
                    <div>
                        <p class="stat-label">Total Orders</p>
                        <h3 class="stat-value"><?= $total_orders ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <p class="stat-label">Low Stock Items</p>
                        <h3 class="stat-value text-warning"><?= $low_stock_products ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <span class="fw-bold"><i class="fas fa-users me-2"></i>Recent Users</span>
                        <a href="manage_users.php" class="text-info small text-decoration-none">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td class="d-flex align-items-center">
                                        <div class="user-avatar me-2"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
                                        <div>
                                            <div class="small fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?= $user['email'] ?></div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-secondary small"><?= $user['role'] ?></span></td>
                                    <td class="small text-muted"><?= date('M d', strtotime($user['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <span class="fw-bold"><i class="fas fa-receipt me-2"></i>Recent Orders</span>
                        <a href="manage_orders.php" class="text-info small text-decoration-none">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td class="fw-bold text-info">#<?= $order['order_id'] ?></td>
                                    <td class="small"><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td class="small fw-bold">Rs.<?= number_format($order['total_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $order['status']=='pending'?'warning':'success' ?> small">
                                            <?= $order['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <span class="fw-bold"><i class="fas fa-warehouse me-2"></i>Inventory Warning</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="small text-muted"><?= $item['category_name'] ?></td>
                                    <td><span class="text-danger fw-bold"><?= $item['stock'] ?> Left</span></td>
                                    <td><a href="manage_products.php?edit=<?= $item['product_id'] ?>" class="btn btn-primary btn-sm">Restock</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-4 col-md-2">
                <a href="manage_users.php?add=1" class="quick-action-btn"><i class="fas fa-user-plus"></i><span class="small">Add User</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="manage_products.php?add=1" class="quick-action-btn"><i class="fas fa-box-open"></i><span class="small">Add Product</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="manage_categories.php" class="quick-action-btn"><i class="fas fa-tags"></i><span class="small">Category</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="manage_orders.php?status=pending" class="quick-action-btn"><i class="fas fa-clock"></i><span class="small">Pending</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="settings.php" class="quick-action-btn"><i class="fas fa-cog"></i><span class="small">Settings</span></a>
            </div>
            <div class="col-4 col-md-2">
                <a href="../index.php" class="quick-action-btn"><i class="fas fa-eye"></i><span class="small">Live Site</span></a>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>