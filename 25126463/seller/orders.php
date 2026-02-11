<?php
// seller/orders.php - Manage Orders
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
$success = null;
$error = null;

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    
    try {
        // Verify this order contains seller's products
        $verify_stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM ORDER_ITEMS oi
            INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
            WHERE oi.order_id = ? AND p.seller_id = ?
        ");
        $verify_stmt->execute([$order_id, $seller_id]);
        $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $update_stmt = $conn->prepare("UPDATE ORDERS SET status = ? WHERE order_id = ?");
            $update_stmt->execute([$new_status, $order_id]);
            $success = "Order status updated successfully!";
        }
    } catch (PDOException $e) {
        error_log("Order update error: " . $e->getMessage());
        $error = "Failed to update order status.";
    }
}

// Handle payment approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $order_id = intval($_POST['order_id']);
    
    try {
        $conn->beginTransaction();
        
        // Update payment status
        $update_payment = $conn->prepare("
            UPDATE PAYMENTS 
            SET payment_status = 'paid', 
                verified_at = NOW(),
                verified_by = ?
            WHERE payment_id = ?
        ");
        $update_payment->execute([$seller_id, $payment_id]);
        
        // Update order status
        $update_order = $conn->prepare("
            UPDATE ORDERS 
            SET status = 'processing'
            WHERE order_id = ?
        ");
        $update_order->execute([$order_id]);
        
        $conn->commit();
        $success = "Payment approved successfully! Order is now being processed.";
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Failed to approve payment: " . $e->getMessage();
    }
}

// Handle payment rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $order_id = intval($_POST['order_id']);
    $rejection_reason = trim($_POST['rejection_reason']);
    
    if (empty($rejection_reason)) {
        $error = "Please provide a reason for rejection.";
    } else {
        try {
            $conn->beginTransaction();
            
            // Update payment status
            $update_payment = $conn->prepare("
                UPDATE PAYMENTS 
                SET payment_status = 'rejected',
                    rejection_reason = ?,
                    verified_at = NOW(),
                    verified_by = ?
                WHERE payment_id = ?
            ");
            $update_payment->execute([$rejection_reason, $seller_id, $payment_id]);
            
            // Update order status
            $update_order = $conn->prepare("
                UPDATE ORDERS 
                SET status = 'cancelled'
                WHERE order_id = ?
            ");
            $update_order->execute([$order_id]);
            
            $conn->commit();
            $success = "Payment rejected. Customer has been notified.";
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to reject payment: " . $e->getMessage();
        }
    }
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filter by status
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

try {
    // Build query to get orders containing seller's products
    $where_clause = "";
    $params = [':seller_id' => $seller_id];
    
    if ($filter_status !== 'all') {
        $where_clause = "AND o.status = :status";
        $params[':status'] = $filter_status;
    }
    
    // Get total count
    $count_query = "
        SELECT COUNT(DISTINCT o.order_id) as total
        FROM ORDERS o
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE p.seller_id = :seller_id $where_clause
    ";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_orders / $per_page);
    
    // Get orders with seller's products INCLUDING PAYMENT INFO
    $orders_query = "
        SELECT DISTINCT 
            o.order_id,
            o.order_date,
            o.total_amount,
            o.status,
            o.shipping_address,
            u.full_name as customer_name,
            u.email as customer_email,
            COUNT(DISTINCT oi.order_item_id) as item_count,
            SUM(CASE WHEN p.seller_id = :seller_id THEN oi.quantity * oi.unit_price ELSE 0 END) as seller_amount,
            pay.payment_id,
            pay.payment_method,
            pay.payment_status,
            pay.payment_screenshot
        FROM ORDERS o
        INNER JOIN USERS u ON o.user_id = u.user_id
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        LEFT JOIN PAYMENTS pay ON o.order_id = pay.order_id
        WHERE p.seller_id = :seller_id $where_clause
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
        LIMIT :per_page OFFSET :offset
    ";
    
    $orders_stmt = $conn->prepare($orders_query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $orders_stmt->bindValue($key, $value);
    }
    $orders_stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $orders_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $orders_stmt->execute();
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Orders fetch error: " . $e->getMessage());
    $orders = [];
    $total_orders = 0;
    $total_pages = 0;
}

// Get order details if viewing specific order
$view_order = null;
$order_items = [];
$payment_info = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $order_id = (int)$_GET['view'];
    
    try {
        $order_stmt = $conn->prepare("
            SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone_no as customer_phone
            FROM ORDERS o
            INNER JOIN USERS u ON o.user_id = u.user_id
            WHERE o.order_id = ?
        ");
        $order_stmt->execute([$order_id]);
        $view_order = $order_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($view_order) {
            // Get payment information
            $payment_stmt = $conn->prepare("
                SELECT * FROM PAYMENTS WHERE order_id = ?
            ");
            $payment_stmt->execute([$order_id]);
            $payment_info = $payment_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get only seller's products in this order
            $items_stmt = $conn->prepare("
                SELECT oi.*, p.product_name, p.product_image, c.category_name
                FROM ORDER_ITEMS oi
                INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
                LEFT JOIN CATEGORY c ON p.category_id = c.category_id
                WHERE oi.order_id = ? AND p.seller_id = ?
            ");
            $items_stmt->execute([$order_id, $seller_id]);
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Order details error: " . $e->getMessage());
    }
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

function getPaymentStatusBadge($status) {
    $badges = [
        'pending' => 'bg-secondary',
        'pending_verification' => 'bg-warning text-dark',
        'paid' => 'bg-success',
        'failed' => 'bg-danger',
        'rejected' => 'bg-danger'
    ];
    return $badges[$status] ?? 'bg-secondary';
}

$page_title = 'Manage Orders - Seller Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="manage-orders-page">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="page-title">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Manage Orders
                    </h2>
                    <p class="text-muted mb-0">Orders containing your products</p>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <a href="/25126463/seller/payments.php" class="btn btn-warning me-2">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Payment Verification
                    </a>
                    <a href="/25126463/seller/dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($view_order): ?>
            <!-- Order Detail View -->
            <div class="mb-3">
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <!-- Payment Information Card -->
                    <?php if ($payment_info && $payment_info['payment_method'] !== 'cod'): ?>
                    <div class="card border-0 shadow mb-4">
                        <div class="card-header bg-transparent border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2"></i>Payment Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Payment Method</small>
                                    <span class="badge bg-info text-capitalize">
                                        <?= htmlspecialchars($payment_info['payment_method']) ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Payment Status</small>
                                    <span class="badge <?= getPaymentStatusBadge($payment_info['payment_status']) ?>">
                                        <?= ucwords(str_replace('_', ' ', $payment_info['payment_status'])) ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($payment_info['payment_screenshot']): ?>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-2">Payment Screenshot</small>
                                <a href="/25126463/uploads/payments/<?= htmlspecialchars($payment_info['payment_screenshot']) ?>" 
                                   target="_blank">
                                    <img src="/25126463/uploads/payments/<?= htmlspecialchars($payment_info['payment_screenshot']) ?>" 
                                         alt="Payment Screenshot" 
                                         class="img-thumbnail" 
                                         style="max-width: 400px; cursor: pointer;">
                                </a>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-external-link-alt me-1"></i>Click to view full size
                                </small>
                            </div>
                            <?php endif; ?>

                            <?php if ($payment_info['payment_status'] === 'pending_verification'): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Action Required:</strong> This payment needs verification. Please review the screenshot above.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="button" 
                                        class="btn btn-success" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#approveModal">
                                    <i class="fas fa-check me-2"></i>Approve Payment
                                </button>
                                <button type="button" 
                                        class="btn btn-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#rejectModal">
                                    <i class="fas fa-times me-2"></i>Reject Payment
                                </button>
                            </div>

                            <!-- Approve Modal -->
                            <div class="modal fade" id="approveModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content" style="background: #0c2531; color: #c0d4dd;">
                                        <div class="modal-header" style="border-bottom: 1px solid #1a3a4a;">
                                            <h5 class="modal-title" style="color: #40c4ff;">Approve Payment</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to approve this payment?</p>
                                            <div class="alert alert-info">
                                                <strong>Order #<?= $view_order['order_id'] ?></strong><br>
                                                Amount: Rs. <?= number_format($payment_info['amount'] ?? $view_order['total_amount'], 2) ?><br>
                                                Customer: <?= htmlspecialchars($view_order['customer_name']) ?>
                                            </div>
                                            <p class="text-muted small">
                                                The order will be marked as "Processing" and the customer will be notified.
                                            </p>
                                        </div>
                                        <div class="modal-footer" style="border-top: 1px solid #1a3a4a;">
                                            <form method="POST">
                                                <input type="hidden" name="payment_id" value="<?= $payment_info['payment_id'] ?>">
                                                <input type="hidden" name="order_id" value="<?= $view_order['order_id'] ?>">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="approve_payment" class="btn btn-success">
                                                    <i class="fas fa-check me-2"></i>Yes, Approve
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content" style="background: #0c2531; color: #c0d4dd;">
                                        <div class="modal-header" style="border-bottom: 1px solid #1a3a4a;">
                                            <h5 class="modal-title" style="color: #40c4ff;">Reject Payment</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <p>Please provide a reason for rejecting this payment:</p>
                                                <div class="alert alert-warning">
                                                    <strong>Order #<?= $view_order['order_id'] ?></strong><br>
                                                    Amount: Rs. <?= number_format($payment_info['amount'] ?? $view_order['total_amount'], 2) ?><br>
                                                    Customer: <?= htmlspecialchars($view_order['customer_name']) ?>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Rejection Reason *</label>
                                                    <textarea name="rejection_reason" 
                                                              class="form-control" 
                                                              rows="3" 
                                                              required 
                                                              placeholder="e.g., Payment amount doesn't match, unclear screenshot, invalid transaction ID, etc."
                                                              style="background: #081822; border: 1px solid #1a3a4a; color: #c0d4dd;"></textarea>
                                                </div>
                                                <p class="text-danger small">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    The order will be cancelled and the customer will be notified.
                                                </p>
                                            </div>
                                            <div class="modal-footer" style="border-top: 1px solid #1a3a4a;">
                                                <input type="hidden" name="payment_id" value="<?= $payment_info['payment_id'] ?>">
                                                <input type="hidden" name="order_id" value="<?= $view_order['order_id'] ?>">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="reject_payment" class="btn btn-danger">
                                                    <i class="fas fa-times me-2"></i>Reject Payment
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php elseif ($payment_info['payment_status'] === 'paid'): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Payment Verified</strong><br>
                                Verified on: <?= date('M d, Y g:i A', strtotime($payment_info['verified_at'])) ?>
                            </div>
                            <?php elseif ($payment_info['payment_status'] === 'rejected'): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle me-2"></i>
                                <strong>Payment Rejected</strong><br>
                                Reason: <?= htmlspecialchars($payment_info['rejection_reason']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow">
                        <div class="card-header bg-transparent border-bottom">
                            <h5 class="mb-0">Order #<?= $view_order['order_id'] ?></h5>
                            <small class="text-muted">
                                Placed on <?= date('F j, Y g:i A', strtotime($view_order['order_date'])) ?>
                            </small>
                        </div>
                        <div class="card-body">
                            <h6 class="mb-3">Your Products in This Order</h6>
                            <?php foreach ($order_items as $item): ?>
                                <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                                    <?php
                                    $item_image = !empty($item['product_image']) 
                                        ? "/25126463/uploads/products/" . htmlspecialchars($item['product_image'])
                                        : "https://via.placeholder.com/80x80/081822/40c4ff?text=No+Image";
                                    ?>
                                    <img src="<?= $item_image ?>" class="order-item-img" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['product_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($item['category_name']) ?></small>
                                        <div class="mt-2">
                                            <span class="text-muted">Quantity: <?= $item['quantity'] ?></span>
                                            <span class="mx-2">×</span>
                                            <span>Rs. <?= number_format($item['unit_price'], 2) ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <strong>Rs. <?= number_format($item['quantity'] * $item['unit_price'], 2) ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow mb-4">
                        <div class="card-header bg-transparent border-bottom">
                            <h5 class="mb-0">Customer Details</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Name:</strong> <?= htmlspecialchars($view_order['customer_name']) ?></p>
                            <p class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($view_order['customer_email']) ?></p>
                            <?php if ($view_order['customer_phone']): ?>
                                <p class="mb-2"><strong>Phone:</strong> <?= htmlspecialchars($view_order['customer_phone']) ?></p>
                            <?php endif; ?>
                            <hr>
                            <p class="mb-0"><strong>Shipping Address:</strong><br>
                                <?= nl2br(htmlspecialchars($view_order['shipping_address'])) ?>
                            </p>
                        </div>
                    </div>

                    <div class="card border-0 shadow">
                        <div class="card-header bg-transparent border-bottom">
                            <h5 class="mb-0">Update Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?= $view_order['order_id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label">Current Status</label>
                                    <p class="mb-0">
                                        <span class="badge <?= getStatusBadge($view_order['status']) ?>">
                                            <?= ucfirst($view_order['status']) ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <label for="status" class="form-label">New Status</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="pending" <?= $view_order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= $view_order['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="shipped" <?= $view_order['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="delivered" <?= $view_order['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="cancelled" <?= $view_order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Orders List View -->
            <!-- Filter -->
            <div class="card filter-card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Orders</option>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $filter_status === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="shipped" <?= $filter_status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="delivered" <?= $filter_status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card orders-table-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>
                        Orders (<?= number_format($total_orders) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>No orders found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover orders-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Your Amount</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary">#<?= $order['order_id'] ?></strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($order['customer_email']) ?></small>
                                                </div>
                                            </td>
                                            <td class="text-muted small">
                                                <?= date('M d, Y', strtotime($order['order_date'])) ?><br>
                                                <?= date('g:i A', strtotime($order['order_date'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $order['item_count'] ?> items</span>
                                            </td>
                                            <td class="text-primary fw-semibold">
                                                Rs. <?= number_format($order['seller_amount'], 2) ?>
                                            </td>
                                            <td>
                                                <?php if ($order['payment_method']): ?>
                                                    <div class="small">
                                                        <span class="badge bg-info text-capitalize mb-1">
                                                            <?= htmlspecialchars($order['payment_method']) ?>
                                                        </span>
                                                        <br>
                                                        <span class="badge <?= getPaymentStatusBadge($order['payment_status']) ?>">
                                                            <?php 
                                                            $payment_status_text = str_replace('_', ' ', $order['payment_status']);
                                                            echo ucwords($payment_status_text);
                                                            ?>
                                                        </span>
                                                        <?php if ($order['payment_status'] === 'pending_verification'): ?>
                                                            <br>
                                                            <a href="/25126463/seller/payments.php?filter=pending" 
                                                               class="badge bg-warning text-dark mt-1 text-decoration-none">
                                                                <i class="fas fa-exclamation-triangle"></i> Verify
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= getStatusBadge($order['status']) ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="?view=<?= $order['order_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-wrapper">
                                <nav>
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?><?= $filter_status !== 'all' ? '&status=' . $filter_status : '' ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= $filter_status !== 'all' ? '&status=' . $filter_status : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?><?= $filter_status !== 'all' ? '&status=' . $filter_status : '' ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
.manage-orders-page {
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

.filter-card, .orders-table-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
}

.orders-table-card .card-header {
    background-color: rgba(26, 58, 74, 0.5);
    border-bottom: 1px solid #1a3a4a;
    padding: 1.25rem 1.5rem;
}

.card-title {
    color: #40c4ff;
    font-size: 1.1rem;
    font-weight: 600;
}

.orders-table {
    color: #c0d4dd;
    margin-bottom: 0;
}

.orders-table thead th {
    background-color: rgba(26, 58, 74, 0.5);
    color: #40c4ff;
    border-bottom: 2px solid #1a3a4a;
    font-weight: 600;
    padding: 1rem;
    white-space: nowrap;
}

.orders-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #1a3a4a;
    vertical-align: middle;
}

.orders-table tbody tr:hover {
    background-color: rgba(64, 196, 255, 0.05);
}

.order-item-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 5px;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #627d8a;
}

.empty-state i {
    font-size: 4rem;
    color: #1a3a4a;
    margin-bottom: 1.5rem;
}

.pagination-wrapper {
    padding: 1.5rem;
    border-top: 1px solid #1a3a4a;
}

.pagination .page-link {
    background-color: #081822;
    border-color: #1a3a4a;
    color: #c0d4dd;
    margin: 0 0.25rem;
    border-radius: 8px;
}

.pagination .page-link:hover {
    background-color: rgba(64, 196, 255, 0.1);
    border-color: #40c4ff;
    color: #40c4ff;
}

.pagination .page-item.active .page-link {
    background-color: #40c4ff;
    border-color: #40c4ff;
    color: #0a1f2a;
}

.card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    color: #c0d4dd;
}

.card-header {
    background-color: rgba(26, 58, 74, 0.5);
    border-bottom: 1px solid #1a3a4a;
    color: #40c4ff;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>