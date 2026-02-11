<?php
// seller/analytics.php - Seller Analytics & Reports
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

// Get time period filter
$period = isset($_GET['period']) ? $_GET['period'] : '30days';

// Calculate date range
$end_date = date('Y-m-d');
switch ($period) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_label = 'Last 7 Days';
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $period_label = 'Last 90 Days';
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $period_label = 'Last Year';
        break;
    case 'all':
    default:
        $start_date = '2020-01-01';
        $period_label = 'All Time';
        break;
}

try {
    // Total Revenue in Period
    $revenue_stmt = $conn->prepare("
        SELECT COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total
        FROM ORDER_ITEMS oi
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        INNER JOIN ORDERS o ON oi.order_id = o.order_id
        WHERE p.seller_id = ? 
        AND o.status NOT IN ('cancelled', 'returned')
        AND DATE(o.order_date) BETWEEN ? AND ?
    ");
    $revenue_stmt->execute([$seller_id, $start_date, $end_date]);
    $period_revenue = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Orders in Period
    $orders_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.order_id) as total
        FROM ORDERS o
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE p.seller_id = ?
        AND DATE(o.order_date) BETWEEN ? AND ?
    ");
    $orders_stmt->execute([$seller_id, $start_date, $end_date]);
    $period_orders = $orders_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Items Sold in Period
    $items_stmt = $conn->prepare("
        SELECT COALESCE(SUM(oi.quantity), 0) as total
        FROM ORDER_ITEMS oi
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        INNER JOIN ORDERS o ON oi.order_id = o.order_id
        WHERE p.seller_id = ?
        AND o.status NOT IN ('cancelled', 'returned')
        AND DATE(o.order_date) BETWEEN ? AND ?
    ");
    $items_stmt->execute([$seller_id, $start_date, $end_date]);
    $items_sold = $items_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Average Order Value
    $avg_order_value = $period_orders > 0 ? $period_revenue / $period_orders : 0;
    
    // Daily Sales Data (for chart)
    $daily_sales_stmt = $conn->prepare("
        SELECT 
            DATE(o.order_date) as sale_date,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as daily_revenue,
            COUNT(DISTINCT o.order_id) as daily_orders
        FROM ORDERS o
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE p.seller_id = ?
        AND o.status NOT IN ('cancelled', 'returned')
        AND DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY DATE(o.order_date)
        ORDER BY sale_date ASC
    ");
    $daily_sales_stmt->execute([$seller_id, $start_date, $end_date]);
    $daily_sales = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        LEFT JOIN ORDERS o ON oi.order_id = o.order_id 
            AND o.status NOT IN ('cancelled', 'returned')
            AND DATE(o.order_date) BETWEEN ? AND ?
        WHERE p.seller_id = ?
        GROUP BY p.product_id
        HAVING total_sold > 0
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $top_products_stmt->execute([$start_date, $end_date, $seller_id]);
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sales by Category
    $category_sales_stmt = $conn->prepare("
        SELECT 
            c.category_name,
            COALESCE(SUM(oi.quantity), 0) as items_sold,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue
        FROM CATEGORY c
        INNER JOIN PRODUCTS p ON c.category_id = p.category_id
        INNER JOIN ORDER_ITEMS oi ON p.product_id = oi.product_id
        INNER JOIN ORDERS o ON oi.order_id = o.order_id
        WHERE p.seller_id = ?
        AND o.status NOT IN ('cancelled', 'returned')
        AND DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY c.category_id
        ORDER BY revenue DESC
    ");
    $category_sales_stmt->execute([$seller_id, $start_date, $end_date]);
    $category_sales = $category_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Order Status Distribution
    $status_stmt = $conn->prepare("
        SELECT 
            o.status,
            COUNT(DISTINCT o.order_id) as count
        FROM ORDERS o
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE p.seller_id = ?
        AND DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY o.status
    ");
    $status_stmt->execute([$seller_id, $start_date, $end_date]);
    $order_status = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Analytics error: " . $e->getMessage());
    die("Error loading analytics data.");
}

$page_title = 'Analytics - Seller Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="analytics-page">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="page-title">
                        <i class="fas fa-chart-line me-2"></i>
                        Sales Analytics
                    </h2>
                    <p class="text-muted mb-0">Track your performance and insights</p>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <!-- Period Filter -->
                    <div class="btn-group" role="group">
                        <a href="?period=7days" class="btn btn-sm <?= $period === '7days' ? 'btn-primary' : 'btn-outline-primary' ?>">7 Days</a>
                        <a href="?period=30days" class="btn btn-sm <?= $period === '30days' ? 'btn-primary' : 'btn-outline-primary' ?>">30 Days</a>
                        <a href="?period=90days" class="btn btn-sm <?= $period === '90days' ? 'btn-primary' : 'btn-outline-primary' ?>">90 Days</a>
                        <a href="?period=year" class="btn btn-sm <?= $period === 'year' ? 'btn-primary' : 'btn-outline-primary' ?>">1 Year</a>
                        <a href="?period=all" class="btn btn-sm <?= $period === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All Time</a>
                    </div>
                    <a href="/25126463/seller/dashboard.php" class="btn btn-outline-primary btn-sm ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            Showing analytics for: <strong><?= $period_label ?></strong>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Rs. <?= number_format($period_revenue, 0) ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($period_orders) ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($items_sold) ?></h3>
                        <p>Items Sold</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Rs. <?= number_format($avg_order_value, 0) ?></h3>
                        <p>Avg Order Value</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sales Chart -->
            <div class="col-lg-8">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area me-2"></i>Sales Trend
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="100"></canvas>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="card dashboard-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>Top Selling Products
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($top_products)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No sales data for this period</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Units Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rank = 1; foreach ($top_products as $product): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?= $rank <= 3 ? 'warning' : 'secondary' ?>">#<?= $rank ?></span>
                                                </td>
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
                                                <td><span class="badge bg-primary"><?= $product['total_sold'] ?></span></td>
                                                <td class="fw-bold text-success">Rs. <?= number_format($product['revenue'], 2) ?></td>
                                            </tr>
                                        <?php $rank++; endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Charts -->
            <div class="col-lg-4">
                <!-- Order Status -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>Order Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($order_status)): ?>
                            <p class="text-center text-muted">No orders in this period</p>
                        <?php else: ?>
                            <canvas id="statusChart" height="200"></canvas>
                            <div class="mt-3">
                                <?php foreach ($order_status as $status): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-capitalize"><?= $status['status'] ?>:</span>
                                        <strong><?= $status['count'] ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category Sales -->
                <div class="card dashboard-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-th-large me-2"></i>Sales by Category
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($category_sales)): ?>
                            <p class="text-center text-muted">No category data</p>
                        <?php else: ?>
                            <?php foreach ($category_sales as $cat): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small><?= htmlspecialchars($cat['category_name']) ?></small>
                                        <small class="text-success">Rs. <?= number_format($cat['revenue'], 0) ?></small>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-primary" 
                                             style="width: <?= $period_revenue > 0 ? ($cat['revenue'] / $period_revenue * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.analytics-page {
    min-height: calc(100vh - 140px);
    padding: 2rem 0;
    background: linear-gradient(135deg, #0a1f2a 0%, #0c2531 50%, #081822 100%);
}

.page-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #1a3a4a;
}

.page-title {
    color: #40c4ff;
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.stat-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
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
    margin-bottom: 0;
}

.dashboard-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
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

.product-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #1a3a4a;
}

.product-thumb-placeholder {
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
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Trend Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesData = <?= json_encode($daily_sales) ?>;

new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: salesData.map(d => d.sale_date),
        datasets: [{
            label: 'Revenue (Rs.)',
            data: salesData.map(d => d.daily_revenue),
            borderColor: '#40c4ff',
            backgroundColor: 'rgba(64, 196, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: { color: '#c0d4dd' }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#627d8a' },
                grid: { color: '#1a3a4a' }
            },
            x: {
                ticks: { color: '#627d8a' },
                grid: { color: '#1a3a4a' }
            }
        }
    }
});

// Order Status Chart
<?php if (!empty($order_status)): ?>
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusData = <?= json_encode($order_status) ?>;

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
        datasets: [{
            data: statusData.map(s => s.count),
            backgroundColor: [
                'rgba(255, 193, 7, 0.8)',
                'rgba(23, 162, 184, 0.8)',
                'rgba(64, 196, 255, 0.8)',
                'rgba(40, 167, 69, 0.8)',
                'rgba(220, 53, 69, 0.8)',
                'rgba(108, 117, 125, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#c0d4dd' }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>