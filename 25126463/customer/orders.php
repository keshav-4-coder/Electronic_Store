<?php
// customer/orders.php - Customer Order History & Tracking
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "My Orders - Electronic Store";
$user_id = $_SESSION['user_id'];

// Get order ID if viewing details
$view_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = intval($_POST['order_id']);
    
    try {
        // Verify order belongs to user and can be cancelled
        $check_stmt = $conn->prepare("
            SELECT status FROM ORDERS 
            WHERE order_id = ? AND user_id = ? AND status IN ('pending', 'processing')
        ");
        $check_stmt->execute([$order_id, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $update_stmt = $conn->prepare("
                UPDATE ORDERS SET status = 'cancelled' WHERE order_id = ?
            ");
            $update_stmt->execute([$order_id]);
            
            // Restore stock
            $items_stmt = $conn->prepare("
                SELECT product_id, quantity FROM ORDER_ITEMS WHERE order_id = ?
            ");
            $items_stmt->execute([$order_id]);
            
            $restore_stmt = $conn->prepare("
                UPDATE PRODUCTS SET stock = stock + ? WHERE product_id = ?
            ");
            
            while ($item = $items_stmt->fetch(PDO::FETCH_ASSOC)) {
                $restore_stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $_SESSION['success_message'] = "Order cancelled successfully!";
        } else {
            $_SESSION['error_message'] = "Unable to cancel this order.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error cancelling order: " . $e->getMessage();
    }
    
    header("Location: orders.php");
    exit();
}

// Get all orders for user
try {
    $orders_stmt = $conn->prepare("
        SELECT o.*, 
               COUNT(oi.order_item_id) as item_count,
               p.payment_status, p.payment_method
        FROM ORDERS o
        LEFT JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        LEFT JOIN PAYMENTS p ON o.order_id = p.order_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ");
    $orders_stmt->execute([$user_id]);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

    // If viewing specific order, get details
    $order_details = null;
    $order_items = [];
    
    if ($view_order_id > 0) {
        $detail_stmt = $conn->prepare("
            SELECT o.*, p.payment_status, p.payment_method, p.transaction_id, p.payment_date
            FROM ORDERS o
            LEFT JOIN PAYMENTS p ON o.order_id = p.order_id
            WHERE o.order_id = ? AND o.user_id = ?
        ");
        $detail_stmt->execute([$view_order_id, $user_id]);
        $order_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order_details) {
            $items_stmt = $conn->prepare("
                SELECT oi.*, p.product_name, p.product_image, c.category_name
                FROM ORDER_ITEMS oi
                LEFT JOIN PRODUCTS p ON oi.product_id = p.product_id
                LEFT JOIN CATEGORY c ON p.category_id = c.category_id
                WHERE oi.order_id = ?
            ");
            $items_stmt->execute([$view_order_id]);
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Exception $e) {
    $error_message = "Error loading orders: " . $e->getMessage();
}

// Status badge helper function
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
        'pending' => 'bg-warning text-dark',
        'completed' => 'bg-success',
        'failed' => 'bg-danger',
        'refunded' => 'bg-info',
        'partially_refunded' => 'bg-secondary'
    ];
    return $badges[$status] ?? 'bg-secondary';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/header.css">

    <style>
        .order-card:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }
        .order-item-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            background-color: #081822;
            border-radius: 5px;
        }
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-cyan);
        }
        .timeline-item:last-child::before {
            display: none;
        }
        .timeline-marker {
            position: absolute;
            left: 0;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid var(--bg-card);
        }
        .timeline-marker.completed {
            background: #28a745;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($view_order_id > 0 && $order_details): ?>
        <!-- Order Details View -->
        <div class="mb-4">
            <a href="orders.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Orders
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-transparent border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Order #<?php echo $order_details['order_id']; ?></h5>
                            <span class="badge <?php echo getStatusBadge($order_details['status']); ?>">
                                <?php echo ucfirst($order_details['status']); ?>
                            </span>
                        </div>
                        <small class="text-muted">
                            Placed on <?php echo date('F j, Y g:i A', strtotime($order_details['order_date'])); ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Order Items</h6>
                        <?php foreach ($order_items as $item): ?>
                            <?php
                            $raw_image = $item['product_image'] ?? '';
                            if (strpos($raw_image, 'http') === 0) {
                                $image = $raw_image;
                            } elseif (!empty($raw_image)) {
                                $image = "../uploads/products/" . $raw_image;
                            } else {
                                $image = "https://via.placeholder.com/80x80/081822/40c4ff?text=No+Image";
                            }
                            ?>
                            <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     class="order-item-img" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['category_name']); ?></small>
                                    <div class="mt-2">
                                        <span class="text-muted">Quantity: <?php echo $item['quantity']; ?></span>
                                        <span class="mx-2">×</span>
                                        <span>Rs. <?php echo number_format($item['unit_price'], 2); ?></span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <strong>Rs. <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <strong>Rs. <?php echo number_format($order_details['total_amount'], 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between text-primary">
                                <h6>Total:</h6>
                                <h6>Rs. <?php echo number_format($order_details['total_amount'], 2); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order_details['shipping_address'])); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Order Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker completed"></div>
                                <h6>Order Placed</h6>
                                <small class="text-muted">
                                    <?php echo date('M j, Y g:i A', strtotime($order_details['order_date'])); ?>
                                </small>
                            </div>
                            
                            <?php if (in_array($order_details['status'], ['processing', 'shipped', 'delivered'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker completed"></div>
                                <h6>Processing</h6>
                            </div>
                            <?php endif; ?>

                            <?php if (in_array($order_details['status'], ['shipped', 'delivered'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker completed"></div>
                                <h6>Shipped</h6>
                            </div>
                            <?php endif; ?>

                            <?php if ($order_details['status'] == 'delivered'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker completed"></div>
                                <h6>Delivered</h6>
                            </div>
                            <?php endif; ?>

                            <?php if ($order_details['status'] == 'cancelled'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker" style="background: #dc3545;"></div>
                                <h6>Cancelled</h6>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong>Method:</strong> <?php echo strtoupper($order_details['payment_method'] ?? 'N/A'); ?>
                        </p>
                        <p class="mb-2">
                            <strong>Status:</strong> 
                            <span class="badge <?php echo getPaymentStatusBadge($order_details['payment_status'] ?? 'pending'); ?>">
                                <?php echo ucfirst($order_details['payment_status'] ?? 'pending'); ?>
                            </span>
                        </p>
                        <?php if (!empty($order_details['transaction_id'])): ?>
                            <p class="mb-0">
                                <strong>Transaction ID:</strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($order_details['transaction_id']); ?></small>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (in_array($order_details['status'], ['pending', 'processing'])): ?>
                    <form method="POST" class="mt-4" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                        <input type="hidden" name="order_id" value="<?php echo $order_details['order_id']; ?>">
                        <button type="submit" name="cancel_order" class="btn btn-danger w-100">
                            <i class="fas fa-times me-2"></i>Cancel Order
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Orders List View -->
        <h1 class="mb-4"><i class="fas fa-box me-3"></i>My Orders</h1>

        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fa-5x text-muted mb-4"></i>
                <h3>No orders yet</h3>
                <p class="text-muted mb-4">Start shopping to place your first order!</p>
                <a href="category.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-cart me-2"></i>Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 g-4">
                <?php foreach ($orders as $order): ?>
                    <div class="col">
                        <div class="card border-0 shadow order-card">
                            <div class="card-body">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-2">
                                        <h5 class="mb-1">Order #<?php echo $order['order_id']; ?></h5>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block mb-1">Items</small>
                                        <strong><?php echo $order['item_count']; ?></strong>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block mb-1">Total</small>
                                        <strong class="text-primary">Rs. <?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block mb-1">Status</small>
                                        <span class="badge <?php echo getStatusBadge($order['status']); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block mb-1">Payment</small>
                                        <span class="badge <?php echo getPaymentStatusBadge($order['payment_status'] ?? 'pending'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'] ?? 'pending')); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-primary btn-sm mb-1">
                                            View Details
                                        </a>
                                        <?php if (isset($order['payment_method']) && 
                                                  in_array($order['payment_method'], ['esewa', 'khalti']) && 
                                                  $order['payment_status'] === 'awaiting_confirmation'): ?>
                                            <a href="payment.php?order_id=<?php echo $order['order_id']; ?>&method=<?php echo $order['payment_method']; ?>" 
                                               class="btn btn-warning btn-sm">
                                                <i class="fas fa-upload me-1"></i>Pay Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>