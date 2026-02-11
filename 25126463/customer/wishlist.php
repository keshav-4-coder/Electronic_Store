<?php
// customer/wishlist.php - Wishlist Page
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "My Wishlist - Electronic Store";

// Initialize wishlist if not exists
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Handle wishlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add to wishlist
    if (isset($_POST['add_to_wishlist'])) {
        $product_id = intval($_POST['product_id']);
        if (!in_array($product_id, $_SESSION['wishlist'])) {
            $_SESSION['wishlist'][] = $product_id;
            $_SESSION['success_message'] = "Product added to wishlist!";
        }
    }
    
    // Remove from wishlist
    if (isset($_POST['remove_from_wishlist'])) {
        $product_id = intval($_POST['product_id']);
        $key = array_search($product_id, $_SESSION['wishlist']);
        if ($key !== false) {
            unset($_SESSION['wishlist'][$key]);
            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']); // Reindex array
            $_SESSION['success_message'] = "Item removed from wishlist!";
        }
    }
    
    // Move to cart
    if (isset($_POST['move_to_cart'])) {
        $product_id = intval($_POST['product_id']);
        
        // Add to cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += 1;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }
        
        // Remove from wishlist
        $key = array_search($product_id, $_SESSION['wishlist']);
        if ($key !== false) {
            unset($_SESSION['wishlist'][$key]);
            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
        }
        
        $_SESSION['success_message'] = "Product moved to cart!";
    }
    
    // Clear wishlist
    if (isset($_POST['clear_wishlist'])) {
        $_SESSION['wishlist'] = [];
        $_SESSION['success_message'] = "Wishlist cleared!";
    }
    
    header("Location: wishlist.php");
    exit();
}

// Get wishlist products
$wishlist_products = [];

if (!empty($_SESSION['wishlist'])) {
    try {
        $product_ids = $_SESSION['wishlist'];
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        
        $stmt = $conn->prepare("
            SELECT p.*, c.category_name 
            FROM PRODUCTS p
            LEFT JOIN CATEGORY c ON p.category_id = c.category_id
            WHERE p.product_id IN ($placeholders) AND p.is_active = 1
        ");
        $stmt->execute($product_ids);
        $wishlist_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Error loading wishlist: " . $e->getMessage();
    }
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
    <link rel="stylesheet" href="../css/product.css">

    <style>
        .wishlist-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            background-color: #081822;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <h1 class="mb-4"><i class="fas fa-heart me-3"></i>My Wishlist</h1>

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

    <?php if (empty($wishlist_products)): ?>
        <div class="text-center py-5">
            <i class="fas fa-heart-broken fa-5x text-muted mb-4"></i>
            <h3>Your wishlist is empty</h3>
            <p class="text-muted mb-4">Save your favorite items for later!</p>
            <a href="category.php" class="btn btn-primary btn-lg">
                <i class="fas fa-shopping-bag me-2"></i>Browse Products
            </a>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Wishlist Items (<?php echo count($wishlist_products); ?>)</h5>
                <form method="POST" onsubmit="return confirm('Are you sure you want to clear your wishlist?');">
                    <button type="submit" name="clear_wishlist" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash me-2"></i>Clear Wishlist
                    </button>
                </form>
            </div>
            <div class="card-body p-0">
                <?php foreach ($wishlist_products as $product): ?>
                    <?php
                    $raw_image = $product['product_image'] ?? '';
                    if (strpos($raw_image, 'http') === 0) {
                        $image = $raw_image;
                    } elseif (!empty($raw_image)) {
                        $image = "../uploads/products/" . $raw_image;
                    } else {
                        $image = "https://via.placeholder.com/150x150/081822/40c4ff?text=No+Image";
                    }
                    ?>
                    <div class="p-4 border-bottom">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-2">
                                <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                    <img src="<?php echo htmlspecialchars($image); ?>" 
                                         class="wishlist-img" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                </a>
                            </div>
                            <div class="col-md-5">
                                <h5 class="mb-1">
                                    <a href="product.php?id=<?php echo $product['product_id']; ?>" 
                                       class="text-decoration-none text-white">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </a>
                                </h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                <?php if ($product['description']): ?>
                                    <p class="small text-muted mb-0">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <h4 class="text-primary mb-0">Rs. <?php echo number_format($product['price'], 2); ?></h4>
                                <?php if ($product['stock'] > 0): ?>
                                    <span class="badge bg-success mt-2">In Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger mt-2">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <div class="d-grid gap-2">
                                    <?php if ($product['stock'] > 0): ?>
                                        <form method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" name="move_to_cart" class="btn btn-primary w-100">
                                                <i class="fas fa-cart-plus me-2"></i>Move to Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-times-circle me-2"></i>Out of Stock
                                        </button>
                                    <?php endif; ?>
                                    
                                    <form method="POST">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <button type="submit" name="remove_from_wishlist" class="btn btn-outline-danger w-100">
                                            <i class="fas fa-trash me-2"></i>Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="category.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>