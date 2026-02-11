<?php
/**
 * admin/manage_orders.php - Professional Order Management
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

$success_msg = '';
$error_msg = '';

// Handle Update Order Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    
    try {
        $stmt = $conn->prepare("UPDATE ORDERS SET status = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$new_status, $order_id]);
        $success_msg = "Order #$order_id updated to " . ucfirst($new_status);
    } catch (PDOException $e) {
        $error_msg = "Error updating order: " . $e->getMessage();
    }
}

// Handle Delete Order
if (isset($_GET['delete_id'])) {
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM ORDER_ITEMS WHERE order_id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $stmt = $conn->prepare("DELETE FROM ORDERS WHERE order_id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $conn->commit();
        $success_msg = "Order deleted successfully!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Filters logic
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$where_conditions = ["1=1"];
$params = [];

if ($status_filter) { $where_conditions[] = "o.status = ?"; $params[] = $status_filter; }
if ($search) { $where_conditions[] = "(u.full_name LIKE ? OR o.order_id LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$where_clause = implode(" AND ", $where_conditions);

// Fetch orders
$stmt = $conn->prepare("
    SELECT o.*, u.full_name, u.email, COUNT(oi.order_item_id) as item_count
    FROM ORDERS o
    JOIN USERS u ON o.user_id = u.user_id
    LEFT JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
    WHERE $where_clause
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Order Statistics
$stats = $conn->query("SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    COALESCE(SUM(total_amount), 0) as total_revenue
    FROM ORDERS")->fetch(PDO::FETCH_ASSOC);

$page_title = 'Order Management - Admin';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .admin-page { background: #0f172a; padding: 2rem 0; min-height: 100vh; color: #f8fafc; }
    
    .stat-mini-card {
        background: #1e293b;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid rgba(255,255,255,0.05);
        text-align: center;
    }
    
    .filter-section {
        background: #1e293b;
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }

    .form-control, .form-select {
        background: #0f172a !t-important;
        border: 1px solid #334155;
        color: white;
    }

    .order-table-card {
        background: #1e293b;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
        overflow: hidden;
    }

    .table { color: #cbd5e1; margin-bottom: 0; }
    .table thead th { 
        background: rgba(255,255,255,0.02);
        color: #94a3b8; border-bottom: 1px solid #334155; 
        font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;
    }
    .table td { border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; padding: 1rem; }

    .status-select {
        background: #0f172a;
        color: #f8fafc;
        border: 1px solid #334155;
        font-size: 0.8rem;
        border-radius: 6px;
        padding: 4px 8px;
    }

    .text-cyan { color: #06b6d4; }
</style>

<div class="admin-page">
    <div class="container-fluid px-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0 text-cyan"><i class="fas fa-shopping-basket me-2"></i>Order Management</h3>
                <p class="text-muted small">Monitor and fulfill customer requests</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm px-3">
                <i class="fas fa-arrow-left me-2"></i>Dashboard
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-mini-card">
                    <div class="text-muted small uppercase mb-1">Total Sales</div>
                    <h4 class="fw-bold mb-0">Rs. <?= number_format($stats['total_revenue']) ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-mini-card">
                    <div class="text-muted small mb-1">Active Orders</div>
                    <h4 class="fw-bold mb-0 text-cyan"><?= $stats['total_orders'] ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-mini-card">
                    <div class="text-muted small mb-1">Awaiting Action</div>
                    <h4 class="fw-bold mb-0 text-warning"><?= $stats['pending'] ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-mini-card">
                    <div class="text-muted small mb-1">Successfully Delivered</div>
                    <h4 class="fw-bold mb-0 text-success"><?= $stats['delivered'] ?></h4>
                </div>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert bg-success text-white border-0 mb-4"><?= $success_msg ?></div>
        <?php endif; ?>

        <div class="filter-section shadow-sm">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search ID or Customer..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-cyan w-100 fw-bold">Apply Filter</button>
                </div>
            </form>
        </div>

        <div class="order-table-card shadow-lg">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Customer Info</th>
                            <th>Summary</th>
                            <th>Grand Total</th>
                            <th>Update Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No orders matches your criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-cyan">#<?= $order['order_id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($order['full_name']) ?></div>
                                        <div class="text-muted small"><?= $order['email'] ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-white border border-secondary px-2">
                                            <?= $order['item_count'] ?> items
                                        </span>
                                        <div class="text-muted" style="font-size: 11px; margin-top: 4px;">
                                            <?= date('M d, Y', strtotime($order['order_date'])) ?>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-white">Rs. <?= number_format($order['total_amount']) ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                                <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                                <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <a href="view_order.php?id=<?= $order['order_id'] ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?delete_id=<?= $order['order_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete this record?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>