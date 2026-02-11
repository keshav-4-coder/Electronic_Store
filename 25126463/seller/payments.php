<?php
// seller/payments.php - Payment Verification Page
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

// Handle payment approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $order_id = intval($_POST['order_id']);
    
    try {
        // Verify this payment is for seller's products
        $verify_stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM PAYMENTS pay
            INNER JOIN ORDERS o ON pay.order_id = o.order_id
            INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
            INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
            WHERE pay.payment_id = ? AND p.seller_id = ?
        ");
        $verify_stmt->execute([$payment_id, $seller_id]);
        $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
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
            $success = "Payment approved successfully! Order #$order_id is now being processed.";
        } else {
            $error = "You don't have permission to verify this payment.";
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Payment approval error: " . $e->getMessage());
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
            // Verify this payment is for seller's products
            $verify_stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM PAYMENTS pay
                INNER JOIN ORDERS o ON pay.order_id = o.order_id
                INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
                INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
                WHERE pay.payment_id = ? AND p.seller_id = ?
            ");
            $verify_stmt->execute([$payment_id, $seller_id]);
            $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
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
            } else {
                $error = "You don't have permission to verify this payment.";
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Payment rejection error: " . $e->getMessage());
            $error = "Failed to reject payment: " . $e->getMessage();
        }
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';

// Fetch payments for orders containing seller's products
try {
    $query = "
        SELECT DISTINCT
            p.payment_id,
            p.order_id,
            p.payment_method,
            p.payment_status,
            p.payment_screenshot,
            p.transaction_id,
            p.amount,
            p.created_at,
            p.verified_at,
            p.rejection_reason,
            o.total_amount,
            o.order_date,
            o.status as order_status,
            u.full_name as customer_name,
            u.email as customer_email,
            u.phone_no as customer_phone,
            COUNT(DISTINCT oi.order_item_id) as item_count,
            SUM(CASE WHEN pr.seller_id = :seller_id THEN oi.quantity * oi.unit_price ELSE 0 END) as seller_amount
        FROM PAYMENTS p
        INNER JOIN ORDERS o ON p.order_id = o.order_id
        INNER JOIN USERS u ON o.user_id = u.user_id
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS pr ON oi.product_id = pr.product_id
        WHERE pr.seller_id = :seller_id
    ";
    
    $params = [':seller_id' => $seller_id];
    
    if ($filter === 'pending') {
        $query .= " AND p.payment_status = 'pending_verification'";
    } elseif ($filter === 'approved') {
        $query .= " AND p.payment_status = 'paid'";
    } elseif ($filter === 'rejected') {
        $query .= " AND p.payment_status = 'rejected'";
    }
    
    $query .= " GROUP BY p.payment_id ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get counts for badges
    $count_pending_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.payment_id) 
        FROM PAYMENTS p
        INNER JOIN ORDERS o ON p.order_id = o.order_id
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS pr ON oi.product_id = pr.product_id
        WHERE pr.seller_id = ? AND p.payment_status = 'pending_verification'
    ");
    $count_pending_stmt->execute([$seller_id]);
    $count_pending = $count_pending_stmt->fetchColumn();
    
    $count_approved_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.payment_id) 
        FROM PAYMENTS p
        INNER JOIN ORDERS o ON p.order_id = o.order_id
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS pr ON oi.product_id = pr.product_id
        WHERE pr.seller_id = ? AND p.payment_status = 'paid'
    ");
    $count_approved_stmt->execute([$seller_id]);
    $count_approved = $count_approved_stmt->fetchColumn();
    
    $count_rejected_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.payment_id) 
        FROM PAYMENTS p
        INNER JOIN ORDERS o ON p.order_id = o.order_id
        INNER JOIN ORDER_ITEMS oi ON o.order_id = oi.order_id
        INNER JOIN PRODUCTS pr ON oi.product_id = pr.product_id
        WHERE pr.seller_id = ? AND p.payment_status = 'rejected'
    ");
    $count_rejected_stmt->execute([$seller_id]);
    $count_rejected = $count_rejected_stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Payment fetch error: " . $e->getMessage());
    $payments = [];
    $count_pending = 0;
    $count_approved = 0;
    $count_rejected = 0;
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

$page_title = 'Payment Verification - Seller Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="payment-verification-page">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="page-title">
                        <i class="fas fa-file-invoice-dollar me-2"></i>
                        Payment Verification
                    </h2>
                    <p class="text-muted mb-0">Review and verify customer payments</p>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <a href="/25126463/seller/orders.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-shopping-cart me-2"></i>View Orders
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

        <!-- Filter Tabs -->
        <div class="card filter-tabs-card mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-fill border-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $filter === 'pending' ? 'active' : '' ?>" 
                           href="?filter=pending">
                            <i class="fas fa-clock me-2"></i>Pending Verification
                            <?php if ($count_pending > 0): ?>
                                <span class="badge bg-warning text-dark ms-2"><?= $count_pending ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter === 'approved' ? 'active' : '' ?>" 
                           href="?filter=approved">
                            <i class="fas fa-check-circle me-2"></i>Approved
                            <?php if ($count_approved > 0): ?>
                                <span class="badge bg-success ms-2"><?= $count_approved ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter === 'rejected' ? 'active' : '' ?>" 
                           href="?filter=rejected">
                            <i class="fas fa-times-circle me-2"></i>Rejected
                            <?php if ($count_rejected > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $count_rejected ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" 
                           href="?filter=all">
                            <i class="fas fa-list me-2"></i>All Payments
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Payments Grid -->
        <?php if (empty($payments)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-invoice-dollar fa-4x text-muted mb-3"></i>
                    <h5>No payments found</h5>
                    <p class="text-muted">No payments in this category yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($payments as $payment): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="card payment-card h-100">
                            <!-- Card Header -->
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shopping-bag me-2"></i>
                                        Order #<?= $payment['order_id'] ?>
                                    </h6>
                                    <span class="badge <?= getPaymentStatusBadge($payment['payment_status']) ?>">
                                        <?= ucwords(str_replace('_', ' ', $payment['payment_status'])) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body">
                                <!-- Customer Info -->
                                <div class="customer-info mb-3">
                                    <small class="text-muted d-block mb-1">Customer</small>
                                    <strong class="d-block"><?= htmlspecialchars($payment['customer_name']) ?></strong>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($payment['customer_email']) ?>
                                    </small>
                                    <?php if ($payment['customer_phone']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($payment['customer_phone']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <hr>

                                <!-- Payment Details -->
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Your Amount</small>
                                        <strong class="text-primary fs-5">Rs. <?= number_format($payment['seller_amount'], 2) ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Payment Method</small>
                                        <strong class="text-capitalize"><?= htmlspecialchars($payment['payment_method']) ?></strong>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Items</small>
                                        <strong><?= $payment['item_count'] ?> item(s)</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Total Order</small>
                                        <strong>Rs. <?= number_format($payment['total_amount'], 2) ?></strong>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Submitted On</small>
                                    <strong><?= date('M d, Y g:i A', strtotime($payment['created_at'])) ?></strong>
                                </div>

                                <?php if ($payment['verified_at']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Verified On</small>
                                        <strong><?= date('M d, Y g:i A', strtotime($payment['verified_at'])) ?></strong>
                                    </div>
                                <?php endif; ?>

                                <?php if ($payment['rejection_reason']): ?>
                                    <div class="alert alert-danger mb-3">
                                        <small class="d-block mb-1"><strong>Rejection Reason:</strong></small>
                                        <?= htmlspecialchars($payment['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Payment Screenshot -->
                                <?php if ($payment['payment_screenshot']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Payment Screenshot</small>
                                        <a href="/25126463/uploads/payments/<?= htmlspecialchars($payment['payment_screenshot']) ?>" 
                                           target="_blank"
                                           class="d-block">
                                            <img src="/25126463/uploads/payments/<?= htmlspecialchars($payment['payment_screenshot']) ?>" 
                                                 alt="Payment Screenshot" 
                                                 class="img-thumbnail w-100 payment-screenshot">
                                        </a>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-external-link-alt me-1"></i>Click to view full size
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Card Footer with Actions -->
                            <?php if ($payment['payment_status'] === 'pending_verification'): ?>
                                <div class="card-footer">
                                    <div class="d-grid gap-2">
                                        <button type="button" 
                                                class="btn btn-success" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#approveModal<?= $payment['payment_id'] ?>">
                                            <i class="fas fa-check me-2"></i>Approve Payment
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?= $payment['payment_id'] ?>">
                                            <i class="fas fa-times me-2"></i>Reject Payment
                                        </button>
                                        <a href="/25126463/seller/orders.php?view=<?= $payment['order_id'] ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-2"></i>View Order Details
                                        </a>
                                    </div>
                                </div>

                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?= $payment['payment_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content" style="background: #0c2531; color: #c0d4dd;">
                                            <div class="modal-header" style="border-bottom: 1px solid #1a3a4a;">
                                                <h5 class="modal-title" style="color: #40c4ff;">
                                                    <i class="fas fa-check-circle me-2"></i>Approve Payment
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to approve this payment?</p>
                                                <div class="alert alert-info">
                                                    <strong>Order #<?= $payment['order_id'] ?></strong><br>
                                                    Your Amount: Rs. <?= number_format($payment['seller_amount'], 2) ?><br>
                                                    Total Amount: Rs. <?= number_format($payment['total_amount'], 2) ?><br>
                                                    Customer: <?= htmlspecialchars($payment['customer_name']) ?>
                                                </div>
                                                <p class="text-muted small">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    The order will be marked as "Processing" and the customer will be notified.
                                                </p>
                                            </div>
                                            <div class="modal-footer" style="border-top: 1px solid #1a3a4a;">
                                                <form method="POST">
                                                    <input type="hidden" name="payment_id" value="<?= $payment['payment_id'] ?>">
                                                    <input type="hidden" name="order_id" value="<?= $payment['order_id'] ?>">
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
                                <div class="modal fade" id="rejectModal<?= $payment['payment_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content" style="background: #0c2531; color: #c0d4dd;">
                                            <div class="modal-header" style="border-bottom: 1px solid #1a3a4a;">
                                                <h5 class="modal-title" style="color: #40c4ff;">
                                                    <i class="fas fa-times-circle me-2"></i>Reject Payment
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <p>Please provide a reason for rejecting this payment:</p>
                                                    <div class="alert alert-warning">
                                                        <strong>Order #<?= $payment['order_id'] ?>< /strong><br>
                                                        Amount: Rs. <?= number_format($payment['seller_amount'], 2) ?><br>
                                                        Customer: <?= htmlspecialchars($payment['customer_name']) ?>
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
                                                    <input type="hidden" name="payment_id" value="<?= $payment['payment_id'] ?>">
                                                    <input type="hidden" name="order_id" value="<?= $payment['order_id'] ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="reject_payment" class="btn btn-danger">
                                                        <i class="fas fa-times me-2"></i>Reject Payment
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card-footer">
                                    <a href="/25126463/seller/orders.php?view=<?= $payment['order_id'] ?>" 
                                       class="btn btn-outline-primary w-100">
                                        <i class="fas fa-eye me-2"></i>View Order Details
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
.payment-verification-page {
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

.filter-tabs-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
    overflow: hidden;
}

.nav-tabs .nav-link {
    color: #c0d4dd;
    border: none;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    background-color: rgba(64, 196, 255, 0.1);
    color: #40c4ff;
}

.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #40c4ff 0%, #2196f3 100%);
    color: #0a1f2a;
    font-weight: 600;
}

.payment-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.payment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(64, 196, 255, 0.2);
    border-color: #40c4ff;
}

.payment-card .card-header {
    background-color: rgba(26, 58, 74, 0.5);
    border-bottom: 1px solid #1a3a4a;
    color: #c0d4dd;
    padding: 1rem 1.25rem;
}

.payment-card .card-body {
    color: #c0d4dd;
}

.payment-card .card-footer {
    background-color: rgba(26, 58, 74, 0.3);
    border-top: 1px solid #1a3a4a;
    padding: 1rem 1.25rem;
}

.customer-info {
    background-color: rgba(26, 58, 74, 0.3);
    padding: 0.75rem;
    border-radius: 8px;
}

.payment-screenshot {
    max-height: 200px;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.3s ease;
    border: 2px solid #1a3a4a;
}

.payment-screenshot:hover {
    transform: scale(1.05);
    border-color: #40c4ff;
}

.card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    color: #c0d4dd;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>