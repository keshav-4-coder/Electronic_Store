<?php
// customer/cart.php - Shopping Cart Page
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Shopping Cart - Electronic Store";

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add to cart
    if (isset($_POST['add_to_cart'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        $_SESSION['success_message'] = "Product added to cart!";
    }
    
    // Update quantity
    if (isset($_POST['update_cart'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity > 0) {
            $_SESSION['cart'][$product_id] = $quantity;
            $_SESSION['success_message'] = "Cart updated!";
        } else {
            unset($_SESSION['cart'][$product_id]);
            $_SESSION['success_message'] = "Item removed from cart!";
        }
    }
    
    // Remove from cart
    if (isset($_POST['remove_from_cart'])) {
        $product_id = intval($_POST['product_id']);
        unset($_SESSION['cart'][$product_id]);
        $_SESSION['success_message'] = "Item removed from cart!";
    }
    
    // Clear cart
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $_SESSION['success_message'] = "Cart cleared!";
    }
    
    header("Location: cart.php");
    exit();
}

// Get cart products
$cart_products = [];
$subtotal = 0;

if (!empty($_SESSION['cart'])) {
    try {
        $product_ids = array_keys($_SESSION['cart']);
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        
        $stmt = $conn->prepare("
            SELECT p.*, c.category_name 
            FROM PRODUCTS p
            LEFT JOIN CATEGORY c ON p.category_id = c.category_id
            WHERE p.product_id IN ($placeholders) AND p.is_active = 1
        ");
        $stmt->execute($product_ids);
        
        while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $product_id = $product['product_id'];
            $quantity = $_SESSION['cart'][$product_id];
            
            // Check if quantity exceeds stock
            if ($quantity > $product['stock']) {
                $_SESSION['cart'][$product_id] = $product['stock'];
                $quantity = $product['stock'];
            }
            
            $product['quantity'] = $quantity;
            $product['line_total'] = $product['price'] * $quantity;
            $subtotal += $product['line_total'];
            
            $cart_products[] = $product;
        }
    } catch (Exception $e) {
        $error_message = "Error loading cart: " . $e->getMessage();
    }
}

// No tax/VAT applied
$total = $subtotal;
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
    <link rel="stylesheet" href="../css/customer.css">

    <style>
        .cart-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            background-color: #081822;
            border-radius: 5px;
        }
        .quantity-input {
            width: 80px;
        }
        .summary-card {
            position: sticky;
            top: 80px;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <h1 class="mb-4"><i class="fas fa-shopping-cart me-3"></i>Shopping Cart</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_products)): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
            <h3>Your cart is empty</h3>
            <p class="text-muted mb-4">Add some products to get started!</p>
            <a href="category.php" class="btn btn-primary btn-lg">
                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Cart Items (<?php echo count($cart_products); ?>)</h5>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to clear the cart?');">
                            <button type="submit" name="clear_cart" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash me-2"></i>Clear Cart
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($cart_products as $product): ?>
                            <?php
                            $raw_image = $product['product_image'] ?? '';
                            if (strpos($raw_image, 'http') === 0) {
                                $image = $raw_image;
                            } elseif (!empty($raw_image)) {
                                $image = "../uploads/products/" . $raw_image;
                            } else {
                                $image = "https://via.placeholder.com/100x100/081822/40c4ff?text=No+Image";
                            }
                            ?>
                            <div class="p-4 border-bottom">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-2">
                                        <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                            <img src="<?php echo htmlspecialchars($image); ?>" 
                                                 class="cart-img" 
                                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-1">
                                            <a href="product.php?id=<?php echo $product['product_id']; ?>" 
                                               class="text-decoration-none text-white">
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                        <?php if ($product['stock'] < 5 && $product['stock'] > 0): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark">Only <?php echo $product['stock']; ?> left</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>Rs. <?php echo number_format($product['price'], 2); ?></strong>
                                    </div>
                                    <div class="col-md-2">
                                        <form method="POST" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <input type="number" name="quantity" class="form-control form-control-sm quantity-input" 
                                                   value="<?php echo $product['quantity']; ?>" 
                                                   min="1" max="<?php echo $product['stock']; ?>"
                                                   onchange="this.form.submit()">
                                            <input type="hidden" name="update_cart" value="1">
                                        </form>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-primary">Rs. <?php echo number_format($product['line_total'], 2); ?></strong>
                                            <form method="POST" class="ms-2">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <button type="submit" name="remove_from_cart" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="category.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow summary-card">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong>Rs. <?php echo number_format($subtotal, 2); ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <h5>Total:</h5>
                            <h5 class="text-primary">Rs. <?php echo number_format($total, 2); ?></h5>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="checkout.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-lock me-2"></i>Proceed to Checkout
                            </a>
                        </div>

                        <div class="mt-4 pt-4 border-top">
                            <p class="small text-muted mb-2">
                                <i class="fas fa-shield-alt me-2"></i>Secure checkout
                            </p>
                            <p class="small text-muted mb-2">
                                <i class="fas fa-truck me-2"></i>Free delivery over Rs. 5000
                            </p>
                            <p class="small text-muted mb-0">
                                <i class="fas fa-undo me-2"></i>Easy returns within 7 days
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>