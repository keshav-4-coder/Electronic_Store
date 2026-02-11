<?php
// seller/dashboard.php - Seller Dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /25126463/auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

$seller_id = $_SESSION['user_id'];

try {
    // Get seller information
    $seller_stmt = $conn->prepare("SELECT * FROM USERS WHERE user_id = ?");
    $seller_stmt->execute([$seller_id]);
    $seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total Products
    $products_stmt = $conn->prepare("SELECT COUNT(*) as total FROM PRODUCTS WHERE seller_id = ?");
    $products_stmt->execute([$seller_id]);
    $total_products = $products_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active Products
    $active_stmt = $conn->prepare("SELECT COUNT(*) as total FROM PRODUCTS WHERE seller_id = ? AND is_active = 1");
    $active_stmt->execute([$seller_id]);
    $active_products = $active_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Out of Stock Products
    $stock_stmt = $conn->prepare("SELECT COUNT(*) as total FROM PRODUCTS WHERE seller_id = ? AND stock = 0");
    $stock_stmt->execute([$seller_id]);
    $out_of_stock = $stock_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Orders containing seller's products
    $orders_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.order_id) as total
        FROM ORDERS o
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE p.seller_id = ?
    ");
    $orders_stmt->execute([$seller_id]);
    $total_orders = $orders_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending Orders
    $pending_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.order_id) as total
        FROM ORDERS o
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE p.seller_id = ? AND o.status IN ('pending', 'processing')
    ");
    $pending_stmt->execute([$seller_id]);
    $pending_orders = $pending_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Revenue from seller's products
    $revenue_stmt = $conn->prepare("
        SELECT COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total
        FROM ORDER_ITEMS oi
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        INNER JOIN ORDERS o ON oi.order_id = o.order_id
        WHERE p.seller_id = ? AND o.status NOT IN ('cancelled', 'returned')
    ");
    $revenue_stmt->execute([$seller_id]);
    $total_revenue = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Monthly Revenue (This Month)
    $monthly_stmt = $conn->prepare("
        SELECT COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total
        FROM ORDER_ITEMS oi
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        INNER JOIN ORDERS o ON oi.order_id = o.order_id
        WHERE p.seller_id = ? 
        AND o.status NOT IN ('cancelled', 'returned')
        AND MONTH(o.order_date) = MONTH(CURRENT_DATE())
        AND YEAR(o.order_date) = YEAR(CURRENT_DATE())
    ");
    $monthly_stmt->execute([$seller_id]);
    $monthly_revenue = $monthly_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent Orders
    $recent_orders_stmt = $conn->prepare("
        SELECT DISTINCT
            o.order_id,
            o.order_date,
            o.total_amount,
            o.status,
            u.full_name as customer_name,
            COUNT(DISTINCT oi.order_item_id) as item_count,
            SUM(CASE WHEN p.seller_id = ? THEN oi.quantity * oi.unit_price ELSE 0 END) as seller_amount
        FROM ORDERS o
        INNER JOIN USERS u ON o.user_id = u.user_id
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE p.seller_id = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $recent_orders_stmt->execute([$seller_id, $seller_id]);
    $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Selling Products
    $top_products_stmt = $conn->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.product_image,
            p.price,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue
        FROM PRODUCTS p
        LEFT JOIN ORDER_ITEMS oi ON p.product_id = oi.product_id
        LEFT JOIN ORDERS o ON oi.order_id = o.order_id AND o.status NOT IN ('cancelled', 'returned')
        WHERE p.seller_id = ?
        GROUP BY p.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $top_products_stmt->execute([$seller_id]);
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low Stock Products
    $low_stock_stmt = $conn->prepare("
        SELECT product_id, product_name, stock, product_image
        FROM PRODUCTS
        WHERE seller_id = ? AND stock > 0 AND stock < 10
        ORDER BY stock ASC
        LIMIT 5
    ");
    $low_stock_stmt->execute([$seller_id]);
    $low_stock_products = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Error loading dashboard data.");
}

function getStatusBadge($status) {
    $badges = [
        'pending' => 'bg-warning text-dark',
        'processing' => 'bg-info',
        'shipped' => 'bg-primary',
        'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
        'returned' => 'bg-secondary'
    ];
    return $badges[$status] ?? 'bg-secondary';
}

$page_title = 'Seller Dashboard - Electronic Store';
include __DIR__ . '/../includes/header.php';
?>

<div class="seller-dashboard">
    <div class="container-fluid">
        
        <!-- Welcome Header -->
        <div class="dashboard-header mb-4">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="dashboard-title">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Welcome back, <?= htmlspecialchars($seller['full_name']) ?>!
                    </h2>
                    <p class="text-muted mb-0">Here's what's happening with your store today</p>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <a href="/25126463/seller/add_products.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Product
                    </a>
                    <a href="/25126463/seller/products.php" class="btn btn-outline-primary">
                        <i class="fas fa-boxes me-2"></i>Manage Products
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($total_products) ?></h3>
                        <p>Total Products</p>
                        <small class="text-muted"><?= number_format($active_products) ?> active</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($total_orders) ?></h3>
                        <p>Total Orders</p>
                        <small class="text-warning"><?= number_format($pending_orders) ?> pending</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Rs. <?= number_format($total_revenue, 0) ?></h3>
                        <p>Total Revenue</p>
                        <small class="text-muted">All time earnings</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Rs. <?= number_format($monthly_revenue, 0) ?></h3>
                        <p>This Month</p>
                        <small class="text-muted"><?= date('F Y') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt me-2"></i>Recent Orders
                            </h5>
                            <a href="/25126463/seller/orders.php" class="btn btn-sm btn-outline-primary">
                                View All <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No orders yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Your Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <a href="/25126463/seller/orders.php?view=<?= $order['order_id'] ?>" class="text-primary fw-bold">
                                                        #<?= $order['order_id'] ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                <td class="text-muted small">
                                                    <?= date('M d, Y', strtotime($order['order_date'])) ?>
                                                </td>
                                                <td class="fw-bold">Rs. <?= number_format($order['seller_amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge <?= getStatusBadge($order['status']) ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Selling Products -->
                <div class="card dashboard-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-star me-2"></i>Top Selling Products
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($top_products)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No sales data yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($product['product_image']): ?>
                                                            <img src="/25126463/uploads/products/<?= htmlspecialchars($product['product_image']) ?>" 
                                                                 alt="Product" class="product-thumb">
                                                        <?php else: ?>
                                                            <div class="product-thumb-placeholder">
                                                                <i class="fas fa-image"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <span><?= htmlspecialchars($product['product_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td>Rs. <?= number_format($product['price'], 2) ?></td>
                                                <td><span class="badge bg-primary"><?= $product['total_sold'] ?> units</span></td>
                                                <td class="fw-bold text-success">Rs. <?= number_format($product['revenue'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Stats -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Quick Stats
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-stat">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Active Products</span>
                                <strong class="text-success"><?= $active_products ?></strong>
                            </div>
                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= $total_products > 0 ? ($active_products / $total_products * 100) : 0 ?>%"></div>
                            </div>
                        </div>

                        <div class="quick-stat">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Out of Stock</span>
                                <strong class="text-danger"><?= $out_of_stock ?></strong>
                            </div>
                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar bg-danger" style="width: <?= $total_products > 0 ? ($out_of_stock / $total_products * 100) : 0 ?>%"></div>
                            </div>
                        </div>

                        <div class="quick-stat">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Pending Orders</span>
                                <strong class="text-warning"><?= $pending_orders ?></strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-warning" style="width: <?= $total_orders > 0 ? ($pending_orders / $total_orders * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <?php if (!empty($low_stock_products)): ?>
                <div class="card dashboard-card alert-card">
                    <div class="card-header bg-warning bg-opacity-10 border-warning">
                        <h5 class="mb-0 text-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($low_stock_products as $product): ?>
                                <div class="list-group-item bg-transparent">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($product['product_image']): ?>
                                            <img src="/25126463/uploads/products/<?= htmlspecialchars($product['product_image']) ?>" 
                                                 alt="Product" class="product-thumb-sm">
                                        <?php else: ?>
                                            <div class="product-thumb-sm-placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <strong class="d-block"><?= htmlspecialchars($product['product_name']) ?></strong>
                                            <small class="text-danger">Only <?= $product['stock'] ?> left</small>
                                        </div>
                                        <a href="/25126463/seller/add_products.php?id=<?= $product['product_id'] ?>" 
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card dashboard-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="/25126463/seller/add_products.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Product
                            </a>
                            <a href="/25126463/seller/products.php" class="btn btn-outline-primary">
                                <i class="fas fa-boxes me-2"></i>Manage Products
                            </a>
                            <a href="/25126463/seller/orders.php" class="btn btn-outline-primary">
                                <i class="fas fa-shopping-cart me-2"></i>View Orders
                            </a>
                            <a href="/25126463/auth/my_account.php" class="btn btn-outline-primary">
                                <i class="fas fa-user me-2"></i>My Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.seller-dashboard {
    min-height: calc(100vh - 140px);
    padding: 2rem 0;
    background: linear-gradient(135deg, #0a1f2a 0%, #0c2531 50%, #081822 100%);
}

.dashboard-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #1a3a4a;
}

.dashboard-title {
    color: #40c4ff;
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

/* Stat Cards */
.stat-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(64, 196, 255, 0.2);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-primary .stat-icon {
    background: rgba(64, 196, 255, 0.2);
    color: #40c4ff;
}

.stat-success .stat-icon {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.stat-info .stat-icon {
    background: rgba(23, 162, 184, 0.2);
    color: #17a2b8;
}

.stat-warning .stat-icon {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.stat-content h3 {
    color: #c0d4dd;
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-content p {
    color: #627d8a;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

/* Dashboard Card */
.dashboard-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.dashboard-card .card-header {
    background-color: rgba(26, 58, 74, 0.5);
    border-bottom: 1px solid #1a3a4a;
    padding: 1rem 1.5rem;
}

.dashboard-card .card-header h5 {
    color: #40c4ff;
    font-size: 1.1rem;
    font-weight: 600;
}

.dashboard-card .table {
    color: #c0d4dd;
}

.dashboard-card .table thead th {
    background-color: rgba(26, 58, 74, 0.3);
    color: #40c4ff;
    border-bottom: 2px solid #1a3a4a;
    font-weight: 600;
    padding: 0.75rem 1rem;
}

.dashboard-card .table tbody td {
    border-bottom: 1px solid #1a3a4a;
    padding: 0.75rem 1rem;
    vertical-align: middle;
}

.dashboard-card .table tbody tr:hover {
    background-color: rgba(64, 196, 255, 0.05);
}

.product-thumb, .product-thumb-sm {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #1a3a4a;
}

.product-thumb-sm {
    width: 35px;
    height: 35px;
}

.product-thumb-placeholder, .product-thumb-sm-placeholder {
    width: 40px;
    height: 40px;
    background-color: #081822;
    border: 1px solid #1a3a4a;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #627d8a;
}

.product-thumb-sm-placeholder {
    width: 35px;
    height: 35px;
}

.alert-card {
    border-color: #ffc107 !important;
}

.quick-stat {
    padding: 0.5rem 0;
}

@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>