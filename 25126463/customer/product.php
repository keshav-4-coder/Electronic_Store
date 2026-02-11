<?php
// customer/product.php - Individual Product Detail Page
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: category.php");
    exit();
}

// Fetch product details
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.category_name, u.full_name as seller_name, u.username as seller_username
        FROM PRODUCTS p
        LEFT JOIN CATEGORY c ON p.category_id = c.category_id
        LEFT JOIN USERS u ON p.seller_id = u.user_id
        WHERE p.product_id = ? AND p.is_active = 1
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: category.php");
        exit();
    }
} catch (Exception $e) {
    die("Error loading product: " . $e->getMessage());
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Validate quantity
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    if ($quantity > $product['stock']) {
        $_SESSION['error_message'] = "Only {$product['stock']} items available in stock.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $product_id);
        exit();
    }
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$product_id])) {
        $new_quantity = $_SESSION['cart'][$product_id] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($new_quantity > $product['stock']) {
            $_SESSION['error_message'] = "Cannot add more items. Maximum stock is {$product['stock']}.";
        } else {
            $_SESSION['cart'][$product_id] = $new_quantity;
            $_SESSION['success_message'] = "Product quantity updated in cart!";
        }
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
        $_SESSION['success_message'] = "Product added to cart!";
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $product_id);
    exit();
}

// Handle Add to Wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }

    if (!in_array($product_id, $_SESSION['wishlist'])) {
        $_SESSION['wishlist'][] = $product_id;
        $_SESSION['success_message'] = "Product added to wishlist!";
    } else {
        $_SESSION['info_message'] = "Product already in wishlist!";
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $product_id);
    exit();
}

// Get related products
try {
    $related_stmt = $conn->prepare("
        SELECT p.*, c.category_name 
        FROM PRODUCTS p
        LEFT JOIN CATEGORY c ON p.category_id = c.category_id
        WHERE p.category_id = ? AND p.product_id != ? AND p.is_active = 1
        ORDER BY RAND()
        LIMIT 4
    ");
    $related_stmt->execute([$product['category_id'], $product_id]);
    $related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $related_products = [];
}

$page_title = htmlspecialchars($product['product_name']) . " - Electronic Store";

// Handle image path
$raw_image = $product['product_image'] ?? '';
if (strpos($raw_image, 'http') === 0) {
    $product_image = $raw_image;
} elseif (!empty($raw_image)) {
    $product_image = "../uploads/products/" . $raw_image;
} else {
    $product_image = "https://via.placeholder.com/600x600/081822/40c4ff?text=No+Image";
}

// Check if in wishlist
$in_wishlist = isset($_SESSION['wishlist']) && in_array($product_id, $_SESSION['wishlist']);
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
        .product-detail-img {
            width: 100%;
            height: 500px;
            object-fit: contain;
            background-color: #0f2c3a;
            border-radius: 10px;
        }
        .related-product-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background-color: #081822;
        }
        .quantity-input {
            max-width: 120px;
        }
        .stock-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i><?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="category.php" class="text-decoration-none">Products</a></li>
            <li class="breadcrumb-item"><a href="category.php?category=<?php echo $product['category_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['product_name']); ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow">
                <img src="<?php echo htmlspecialchars($product_image); ?>" 
                     class="product-detail-img p-4" 
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow p-4">
                <span class="badge bg-primary mb-2" style="width: fit-content;">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </span>
                
                <h1 class="h2 mb-3"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                
                <?php if ($product['seller_name']): ?>
                    <p class="text-muted mb-3">
                        <i class="fas fa-store me-2"></i>Sold by: <?php echo htmlspecialchars($product['seller_name']); ?>
                    </p>
                <?php endif; ?>

                <div class="mb-4">
                    <h2 class="h3 text-primary mb-0">Rs. <?php echo number_format($product['price'], 2); ?></h2>
                </div>

                <div class="mb-4">
                    <?php if ($product['stock'] > 0): ?>
                        <span class="badge bg-success stock-badge">
                            <i class="fas fa-check-circle me-2"></i>In Stock (<?php echo $product['stock']; ?> available)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger stock-badge">
                            <i class="fas fa-times-circle me-2"></i>Out of Stock
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($product['description']): ?>
                    <div class="mb-4">
                        <h5 class="mb-3">Description</h5>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($product['stock'] > 0): ?>
                    <form method="POST" class="mb-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-auto">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control quantity-input" id="quantity" name="quantity" 
                                       value="1" min="1" max="<?php echo $product['stock']; ?>" required>
                            </div>
                            <div class="col">
                                <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <form method="POST">
                    <button type="submit" name="add_to_wishlist" class="btn btn-outline-primary w-100" <?php echo $in_wishlist ? 'disabled' : ''; ?>>
                        <i class="fas fa-heart me-2"></i><?php echo $in_wishlist ? 'Already in Wishlist' : 'Add to Wishlist'; ?>
                    </button>
                </form>

                <div class="mt-4 pt-4 border-top">
                    <p class="small text-muted mb-2">
                        <i class="fas fa-shield-alt me-2"></i>Secure payment options
                    </p>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-truck me-2"></i>Fast delivery available
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-undo me-2"></i>7-day return policy
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($related_products) > 0): ?>
        <div class="mt-5">
            <h3 class="mb-4">Related Products</h3>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach ($related_products as $rel_product): ?>
                    <?php
                    $rel_raw_image = $rel_product['product_image'] ?? '';
                    if (strpos($rel_raw_image, 'http') === 0) {
                        $rel_image = $rel_raw_image;
                    } elseif (!empty($rel_raw_image)) {
                        $rel_image = "../uploads/products/" . $rel_raw_image;
                    } else {
                        $rel_image = "https://via.placeholder.com/400x300/081822/40c4ff?text=No+Image";
                    }
                    
                    $rel_in_wishlist = isset($_SESSION['wishlist']) && in_array($rel_product['product_id'], $_SESSION['wishlist']);
                    ?>
                    <div class="col">
                        <div class="card product-card h-100 bg-dark border-0 shadow">
                            <a href="product.php?id=<?php echo $rel_product['product_id']; ?>">
                                <div class="position-relative">
                                    <img src="<?php echo htmlspecialchars($rel_image); ?>" 
                                         class="related-product-img card-img-top" 
                                         alt="<?php echo htmlspecialchars($rel_product['product_name']); ?>">
                                    <?php if ($rel_product['stock'] == 0): ?>
                                        <span class="position-absolute top-0 end-0 badge bg-danger m-2">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-subtitle text-muted mb-2"><?php echo htmlspecialchars($rel_product['category_name']); ?></h6>
                                <h5 class="card-title mb-3">
                                    <a href="product.php?id=<?php echo $rel_product['product_id']; ?>" class="text-decoration-none text-white">
                                        <?php echo htmlspecialchars($rel_product['product_name']); ?>
                                    </a>
                                </h5>
                                <div class="mt-auto">
                                    <span class="price d-block mb-3">Rs. <?php echo number_format($rel_product['price'], 2); ?></span>
                                    <div class="d-grid gap-2">
                                        <?php if ($rel_product['stock'] > 0): ?>
                                            <form method="POST" action="product.php?id=<?php echo $rel_product['product_id']; ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm w-100">
                                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                                </button>
                                            </form>
                                            <form method="POST" action="product.php?id=<?php echo $rel_product['product_id']; ?>">
                                                <button type="submit" name="add_to_wishlist" class="btn btn-outline-primary btn-sm w-100" <?php echo $rel_in_wishlist ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-heart me-2"></i><?php echo $rel_in_wishlist ? 'Wishlisted' : 'Wishlist'; ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm w-100" disabled>
                                                <i class="fas fa-times-circle me-2"></i>Out of Stock
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>