<?php
// customer/payment.php - Payment Page with QR Code and Screenshot Upload
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$payment_method = isset($_GET['method']) ? $_GET['method'] : '';

if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}

// Get order details
try {
    $order_stmt = $conn->prepare("
        SELECT o.*, p.payment_id, p.payment_status, p.payment_screenshot
        FROM ORDERS o
        LEFT JOIN PAYMENTS p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $order_stmt->execute([$order_id, $user_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: orders.php");
        exit();
    }
    
    // Get order items
    $items_stmt = $conn->prepare("
        SELECT oi.*, p.product_name, p.product_image
        FROM ORDER_ITEMS oi
        INNER JOIN PRODUCTS p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error loading order");
}

// Handle payment screenshot upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_screenshot'])) {
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/25126463/uploads/payments/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $error = "Failed to create upload directory.";
            }
        }
        
        // Check if directory is writable
        if (!isset($error) && !is_writable($upload_dir)) {
            chmod($upload_dir, 0777);
        }
        
        if (!isset($error)) {
            $file_tmp = $_FILES['payment_screenshot']['tmp_name'];
            $file_name = $_FILES['payment_screenshot']['name'];
            $file_size = $_FILES['payment_screenshot']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_ext, $allowed_extensions)) {
                $error = "Only JPG, JPEG, and PNG images are allowed.";
            } elseif ($file_size > $max_file_size) {
                $error = "File size must be less than 5MB.";
            } else {
                $new_filename = 'payment_' . $order_id . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Set file permissions
                    chmod($upload_path, 0644);
                    
                    try {
                        $update_stmt = $conn->prepare("
                            UPDATE PAYMENTS 
                            SET payment_screenshot = ?, payment_status = 'pending_verification'
                            WHERE order_id = ?
                        ");
                        $update_stmt->execute([$new_filename, $order_id]);
                        
                        $success = "Payment screenshot uploaded successfully! We'll verify and confirm your payment within 24 hours.";
                        
                        // Refresh order data
                        $order_stmt->execute([$order_id, $user_id]);
                        $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
                        
                    } catch (PDOException $e) {
                        $error = "Failed to update payment status: " . $e->getMessage();
                    }
                } else {
                    $error = "Failed to upload screenshot. Please check directory permissions.";
                }
            }
        }
    } else {
        // Check for specific upload errors
        if (isset($_FILES['payment_screenshot'])) {
            switch ($_FILES['payment_screenshot']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = "File is too large. Maximum size is 5MB.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = "File was only partially uploaded. Please try again.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = "Please select a screenshot to upload.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error = "Server error: Missing temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error = "Failed to write file to disk.";
                    break;
                default:
                    $error = "Please select a screenshot to upload.";
            }
        } else {
            $error = "Please select a screenshot to upload.";
        }
    }
}

$page_title = "Payment - Order #" . $order_id;
include '../includes/header.php';

// Payment gateway details (DEMO – personal QR)
$esewa_qr  = "../images/esewa-qr.png";
$khalti_qr = "../images/khalti-qr.png";

?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Order Summary Card -->
            <div class="card border-0 shadow mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h4 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>Order #<?php echo $order_id; ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Order Date:</strong></p>
                            <p class="text-muted"><?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Payment Method:</strong></p>
                            <p class="text-muted text-capitalize"><?php echo str_replace('_', ' ', $payment_method); ?></p>
                        </div>
                    </div>

                    <h6 class="mb-3">Order Items:</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['product_image']): ?>
                                                <img src="../uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                     alt="" style="width: 40px; height: 40px; object-fit: cover;" class="me-2">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </td>
                                        <td>Qty: <?php echo $item['quantity']; ?></td>
                                        <td class="text-end">Rs. <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between">
                            <h5>Total Amount:</h5>
                            <h5 class="text-primary">Rs. <?php echo number_format($order['total_amount'], 2); ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($payment_method === 'cod'): ?>
                <!-- Cash on Delivery -->
                <div class="card border-0 shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-money-bill-wave fa-5x text-success mb-4"></i>
                        <h3 class="mb-3">Order Placed Successfully!</h3>
                        <p class="lead mb-4">Payment Method: Cash on Delivery</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Please keep <strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong> ready for payment upon delivery.
                        </div>
                        <p class="text-muted mb-4">
                            Your order will be delivered soon. You can pay in cash when the delivery person arrives.
                        </p>
                        <div class="d-grid gap-2 col-md-6 mx-auto">
                            <a href="orders.php" class="btn btn-primary">
                                <i class="fas fa-box me-2"></i>View My Orders
                            </a>
                            <a href="category.php" class="btn btn-outline-primary">
                                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>

            <?php elseif (in_array($payment_method, ['esewa', 'khalti'])): ?>
                <!-- eSewa/Khalti Payment with QR Code -->
                
                <?php if ($order['payment_status'] === 'paid' || $order['payment_status'] === 'pending_verification'): ?>
                    <!-- Payment Already Submitted -->
                    <div class="card border-0 shadow">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                            <h3 class="mb-3">Payment Screenshot Uploaded!</h3>
                            <p class="lead mb-4">
                                Status: <span class="badge bg-warning">Pending Verification</span>
                            </p>
                            <div class="alert alert-info">
                                <i class="fas fa-clock me-2"></i>
                                We're verifying your payment. This usually takes up to 24 hours.
                            </div>
                            
                            <?php if ($order['payment_screenshot']): ?>
                                <div class="mb-4">
                                    <p class="mb-2"><strong>Your uploaded screenshot:</strong></p>
                                    <img src="../uploads/payments/<?php echo htmlspecialchars($order['payment_screenshot']); ?>" 
                                         alt="Payment Screenshot" 
                                         class="img-thumbnail" 
                                         style="max-width: 300px;">
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 col-md-6 mx-auto">
                                <a href="orders.php" class="btn btn-primary">
                                    <i class="fas fa-box me-2"></i>View My Orders
                                </a>
                                <a href="category.php" class="btn btn-outline-primary">
                                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                                </a>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Show QR Code and Upload Form -->
                    <div class="card border-0 shadow">
                        <div class="card-header bg-transparent border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-qrcode me-2"></i>
                                Pay via <?php echo ucfirst($payment_method); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <div class="row">
                                <!-- QR Code Section -->
                                <div class="col-md-6 text-center mb-4">
                                    <h6 class="mb-3">Scan QR Code to Pay</h6>
                                    
                                    <!-- Display Personal QR Code -->
                                    <div class="qr-code-container mb-3">
                                        <?php
                                        // Use personal QR code images
                                        if ($payment_method === 'esewa') {
                                            $qr_image_path = $esewa_qr;
                                            $qr_file_check = __DIR__ . '/' . $esewa_qr;
                                        } else {
                                            $qr_image_path = $khalti_qr;
                                            $qr_file_check = __DIR__ . '/' . $khalti_qr;
                                        }
                                        ?>
                                        
                                        <?php if (file_exists($qr_file_check)): ?>
                                            <img src="<?php echo $qr_image_path; ?>" 
                                                 alt="<?php echo ucfirst($payment_method); ?> QR Code" 
                                                 class="img-fluid border rounded" 
                                                 style="max-width: 300px;">
                                        <?php else: ?>
                                            <div class="alert alert-danger">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                QR code image not found at: <?php echo $qr_file_check; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="alert alert-primary">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Amount to Pay: Rs. <?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </div>

                                    <div class="payment-instructions">
                                        <h6 class="mb-2">How to Pay:</h6>
                                        <ol class="text-start small">
                                            <li>Open your <?php echo ucfirst($payment_method); ?> app</li>
                                            <li>Scan the QR code above</li>
                                            <li>Enter amount: <strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></li>
                                            <li>Complete the payment</li>
                                            <li>Take a screenshot of the payment confirmation</li>
                                            <li>Upload the screenshot below</li>
                                        </ol>
                                        
                                        <div class="alert alert-warning mt-3">
                                            <small>
                                                <i class="fas fa-hand-point-right me-2"></i>
                                                <strong>Note:</strong> Please ensure you pay the exact amount shown above.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload Screenshot Section -->
                                <div class="col-md-6">
                                    <h6 class="mb-3">Upload Payment Screenshot</h6>
                                    
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="payment_screenshot" class="form-label">
                                                Payment Screenshot *
                                            </label>
                                            <input type="file" 
                                                   class="form-control" 
                                                   id="payment_screenshot" 
                                                   name="payment_screenshot" 
                                                   accept="image/jpeg,image/jpg,image/png" 
                                                   required>
                                            <div class="form-text">
                                                Upload a clear screenshot showing:
                                                <ul class="small mb-0 mt-2">
                                                    <li>Transaction ID</li>
                                                    <li>Amount paid (Rs. <?php echo number_format($order['total_amount'], 2); ?>)</li>
                                                    <li>Date and time of payment</li>
                                                    <li>Payment status (Success/Completed)</li>
                                                </ul>
                                            </div>
                                        </div>

                                        <div class="mb-3" id="imagePreview" style="display: none;">
                                            <label class="form-label">Preview:</label>
                                            <img id="preview" src="" alt="Preview" class="img-thumbnail" style="max-width: 100%;">
                                        </div>

                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Important:</strong> Your order will be processed only after payment verification (within 24 hours).
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" name="upload_screenshot" class="btn btn-primary btn-lg">
                                                <i class="fas fa-upload me-2"></i>Upload & Confirm Payment
                                            </button>
                                            <a href="orders.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-clock me-2"></i>I'll Upload Later
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<style>
.qr-code-container {
    padding: 1.5rem;
    background: white;
    border-radius: 8px;
    display: inline-block;
}

.payment-instructions ol {
    padding-left: 1.25rem;
}

.payment-instructions ol li {
    margin-bottom: 0.5rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find all alerts that aren't already being handled by Bootstrap
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    
    alerts.forEach(function(alert) {
        // Increase time to 6 seconds to ensure user reads it
        setTimeout(function() {
            try {
                // Check if the element still exists in the DOM before trying to close
                if (document.body.contains(alert)) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                }
            } catch (error) {
                // Silently fail if the alert was already closed by the user
            }
        }, 6000);
    });
});
</script>

<?php include '../includes/footer.php'; ?>