<?php
// customer/checkout.php - Checkout with Shipping Cost Calculation
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please login to checkout";
    header("Location: ../auth/login.php");
    exit();
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details
try {
    $user_stmt = $conn->prepare("SELECT * FROM USERS WHERE user_id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error loading user data");
}

// Get cart items with details
$cart_items = [];
$subtotal = 0;

try {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $conn->prepare("SELECT * FROM PRODUCTS WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product && $product['stock'] >= $quantity) {
            $product['quantity'] = $quantity;
            $product['item_total'] = $product['price'] * $quantity;
            $cart_items[] = $product;
            $subtotal += $product['item_total'];
        }
    }
} catch (Exception $e) {
    die("Error loading cart items");
}

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $shipping_city = trim($_POST['shipping_city']);
    $shipping_phone = trim($_POST['shipping_phone']);
    $payment_method = $_POST['payment_method'];
    
    // Calculate shipping based on location
    $kathmandu_areas = ['kathmandu', 'bhaktapur', 'lalitpur', 'ktm'];
    $is_kathmandu = false;
    
    foreach ($kathmandu_areas as $area) {
        if (stripos($shipping_city, $area) !== false) {
            $is_kathmandu = true;
            break;
        }
    }
    
    $shipping_cost = $is_kathmandu ? 100 : 150;
    $total = $subtotal + $shipping_cost;
    
    // Validate payment method
    $allowed_methods = ['esewa', 'khalti', 'cod'];
    if (!in_array($payment_method, $allowed_methods)) {
        $_SESSION['error_message'] = "Invalid payment method";
        header("Location: checkout.php");
        exit();
    }
    
    // Start transaction
    try {
        $conn->beginTransaction();
        
        // Create order
        $order_stmt = $conn->prepare("
            INSERT INTO ORDERS (user_id, order_date, total_amount, status, shipping_address)
            VALUES (?, NOW(), ?, 'pending', ?)
        ");
        
        $full_address = $shipping_address . ", " . $shipping_city . "\nPhone: " . $shipping_phone;
        $order_stmt->execute([$user_id, $total, $full_address]);
        $order_id = $conn->lastInsertId();
        
        // Add order items
        foreach ($cart_items as $item) {
            // Check stock again
            $stock_check = $conn->prepare("SELECT stock FROM PRODUCTS WHERE product_id = ?");
            $stock_check->execute([$item['product_id']]);
            $current_stock = $stock_check->fetchColumn();
            
            if ($current_stock < $item['quantity']) {
                throw new Exception("Insufficient stock for " . $item['product_name']);
            }
            
            // Insert order item
            $item_stmt = $conn->prepare("
                INSERT INTO ORDER_ITEMS (order_id, product_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");
            $item_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);
            
            // Update stock
            $update_stock = $conn->prepare("
                UPDATE PRODUCTS 
                SET stock = stock - ? 
                WHERE product_id = ?
            ");
            $update_stock->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Create payment record with correct status
        // For COD: status = 'pending' (will be paid on delivery)
        // For eSewa/Khalti: status = 'pending' (will change to 'pending_verification' after screenshot upload)
        $payment_status = 'pending';
        
        $payment_stmt = $conn->prepare("
            INSERT INTO PAYMENTS (order_id, payment_method, payment_status, amount, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $payment_stmt->execute([$order_id, $payment_method, $payment_status, $total]);
        
        $conn->commit();
        
        // Clear cart
        unset($_SESSION['cart']);
        
        // Redirect to payment page
        header("Location: payment.php?order_id=" . $order_id . "&method=" . $payment_method);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Order failed: " . $e->getMessage();
        header("Location: checkout.php");
        exit();
    }
}

$page_title = "Checkout - Electronic Store";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow">
                <div class="card-header bg-transparent border-bottom">
                    <h4 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Checkout</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="checkoutForm">
                        <!-- Shipping Information -->
                        <div class="mb-4">
                            <h5 class="mb-3"><i class="fas fa-truck me-2"></i>Shipping Information</h5>
                            
                            <div class="mb-3">
                                <label for="shipping_address" class="form-label">Street Address *</label>
                                <input type="text" class="form-control" id="shipping_address" name="shipping_address" 
                                       value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="shipping_city" class="form-label">City/District *</label>
                                    <input type="text" class="form-control" id="shipping_city" name="shipping_city" 
                                           placeholder="e.g., Kathmandu, Pokhara, Chitwan" required>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Shipping: Rs. 100 (Kathmandu Valley) | Rs. 150 (Outside Valley)
                                    </small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="shipping_phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="shipping_phone" name="shipping_phone" 
                                           value="<?php echo htmlspecialchars($user['phone_no'] ?? ''); ?>" 
                                           pattern="[0-9]{10}" placeholder="98XXXXXXXX" required>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="mb-4">
                            <h5 class="mb-3"><i class="fas fa-list me-2"></i>Order Summary</h5>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($item['product_image']): ?>
                                                            <img src="../uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                                 alt="" style="width: 50px; height: 50px; object-fit: cover;" class="me-2 rounded">
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                                <td>Rs. <?php echo number_format($item['item_total'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <strong>Rs. <?php echo number_format($subtotal, 2); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2" id="shippingCostDisplay">
                                    <span>Shipping Cost:</span>
                                    <strong class="text-info">Calculate on city selection</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-3" id="totalDisplay">
                                    <h5>Total:</h5>
                                    <h5 class="text-primary">-</h5>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-4">
                            <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Payment Method</h5>
                            
                            <div class="payment-methods">
                                <div class="form-check payment-option mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="esewa" value="esewa" required>
                                    <label class="form-check-label d-flex align-items-center" for="esewa">
                                        <i class="fas fa-mobile-alt me-2 text-success fs-4"></i>
                                        <div>
                                            <strong>eSewa</strong>
                                            <small class="d-block text-muted">Pay via eSewa wallet - Scan QR & upload screenshot</small>
                                        </div>
                                    </label>
                                </div>

                                <div class="form-check payment-option mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="khalti" value="khalti" required>
                                    <label class="form-check-label d-flex align-items-center" for="khalti">
                                        <i class="fas fa-wallet me-2 text-primary fs-4"></i>
                                        <div>
                                            <strong>Khalti</strong>
                                            <small class="d-block text-muted">Pay via Khalti wallet - Scan QR & upload screenshot</small>
                                        </div>
                                    </label>
                                </div>

                                <div class="form-check payment-option mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="cod" value="cod" required>
                                    <label class="form-check-label d-flex align-items-center" for="cod">
                                        <i class="fas fa-money-bill-wave me-2 text-warning fs-4"></i>
                                        <div>
                                            <strong>Cash on Delivery</strong>
                                            <small class="d-block text-muted">Pay when you receive the order</small>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> For eSewa/Khalti payments, you'll be redirected to scan QR code and upload payment screenshot for verification.
                            </div>
                        </div>

                        <!-- Place Order Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" name="place_order" class="btn btn-primary btn-lg">
                                <i class="fas fa-check-circle me-2"></i>Place Order
                            </button>
                            <a href="cart.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Cart
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
.payment-option {
    padding: 1rem;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.payment-option:hover {
    border-color: #40c4ff;
    background-color: rgba(64, 196, 255, 0.05);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.payment-option .form-check-input:checked ~ .form-check-label {
    color: #0d6efd;
    font-weight: 500;
}

.payment-option .form-check-input:checked {
    background-color: #40c4ff;
    border-color: #40c4ff;
}

.payment-option label {
    cursor: pointer;
    width: 100%;
    margin-bottom: 0;
}

.table img {
    border: 1px solid #dee2e6;
}
</style>

<script>
// Calculate shipping and total based on city
document.getElementById('shipping_city').addEventListener('input', function() {
    const city = this.value.toLowerCase();
    const kathmanduAreas = ['kathmandu', 'bhaktapur', 'lalitpur', 'ktm'];
    
    let isKathmandu = false;
    for (let area of kathmanduAreas) {
        if (city.includes(area)) {
            isKathmandu = true;
            break;
        }
    }
    
    const subtotal = <?php echo $subtotal; ?>;
    const shippingCost = isKathmandu ? 100 : 150;
    const total = subtotal + shippingCost;
    
    document.getElementById('shippingCostDisplay').innerHTML = `
        <span>Shipping Cost:</span>
        <strong class="text-${isKathmandu ? 'success' : 'info'}">Rs. ${shippingCost.toFixed(2)}</strong>
    `;
    
    document.getElementById('totalDisplay').innerHTML = `
        <h5>Total:</h5>
        <h5 class="text-primary">Rs. ${total.toLocaleString('en-NP', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</h5>
    `;
});

// Trigger calculation on page load if city is pre-filled
window.addEventListener('load', function() {
    const cityInput = document.getElementById('shipping_city');
    if (cityInput.value) {
        cityInput.dispatchEvent(new Event('input'));
    }
});

// Form validation
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethod) {
        e.preventDefault();
        alert('Please select a payment method');
        return false;
    }
    
    const city = document.getElementById('shipping_city').value;
    if (!city || city.trim() === '') {
        e.preventDefault();
        alert('Please enter your city/district');
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>