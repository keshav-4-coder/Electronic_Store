<?php
// customer/category.php - Product Listing & Category Browse Page
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

$page_title = "Browse Products - Electronic Store";

// Get filter parameters
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build SQL query
$sql = "SELECT p.*, c.category_name FROM PRODUCTS p 
        LEFT JOIN CATEGORY c ON p.category_id = c.category_id 
        WHERE p.is_active = 1";
$params = [];

if ($category_id > 0) {
    $sql .= " AND p.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if (!empty($search_query)) {
    $sql .= " AND (p.product_name LIKE :search1 OR p.description LIKE :search2)";
    $search_param = "%{$search_query}%";
    $params[':search1'] = $search_param;
    $params[':search2'] = $search_param;
}

// Apply sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.product_name ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM PRODUCTS p WHERE p.is_active = 1";
$count_params = [];

if ($category_id > 0) {
    $count_sql .= " AND p.category_id = :category_id";
    $count_params[':category_id'] = $category_id;
}

if (!empty($search_query)) {
    $count_sql .= " AND (p.product_name LIKE :search1 OR p.description LIKE :search2)";
    $count_params[':search1'] = $search_param;
    $count_params[':search2'] = $search_param;
}

try {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_products / $per_page);

    // Get products - Use LIMIT with bound integers
    $sql .= " LIMIT :per_page OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    
    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind LIMIT and OFFSET as integers
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all categories for filter
    $cat_stmt = $conn->query("SELECT * FROM CATEGORY ORDER BY category_name");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current category name if filtered
    $current_category = null;
    if ($category_id > 0) {
        foreach ($categories as $cat) {
            if ($cat['category_id'] == $category_id) {
                $current_category = $cat['category_name'];
                break;
            }
        }
    }

} catch (Exception $e) {
    die("Error loading products: " . $e->getMessage());
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
        .product-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background-color: #081822;
        }
        .category-filter {
            position: sticky;
            top: 80px;
        }
        .filter-card {
            background: var(--bg-card);
            border: 1px solid var(--border-cyan);
        }
        .category-item {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: var(--transition);
            cursor: pointer;
        }
        .category-item:hover, .category-item.active {
            background: rgba(64, 196, 255, 0.1);
            color: #40c4ff;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="category-filter">
                <div class="card filter-card border-0 shadow mb-4">
                    <div class="card-header bg-transparent border-bottom border-secondary">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Categories</h6>
                        <div class="list-group list-group-flush">
                            <a href="category.php" class="list-group-item list-group-item-action bg-transparent border-0 category-item <?php echo $category_id == 0 ? 'active' : ''; ?>">
                                All Products (<?php echo $total_products; ?>)
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="category.php?category=<?php echo $cat['category_id']; ?>" 
                                   class="list-group-item list-group-item-action bg-transparent border-0 category-item <?php echo $category_id == $cat['category_id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($category_id > 0 || !empty($search_query)): ?>
                    <a href="category.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1">
                        <?php 
                        if (!empty($search_query)) {
                            echo "Search Results for \"" . htmlspecialchars($search_query) . "\"";
                        } elseif ($current_category) {
                            echo htmlspecialchars($current_category);
                        } else {
                            echo "All Products";
                        }
                        ?>
                    </h2>
                    <p class="text-muted mb-0"><?php echo $total_products; ?> products found</p>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <label for="sort" class="text-nowrap mb-0 small">Sort by:</label>
                    <select id="sort" class="form-select form-select-sm" style="width: auto;">
                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                    </select>
                </div>
            </div>

            <?php if (count($products) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                    <h4>No products found</h4>
                    <p class="text-muted">Try adjusting your filters or search terms</p>
                    <a href="category.php" class="btn btn-primary mt-3">Browse All Products</a>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $raw_image = $product['product_image'] ?? '';
                        if (strpos($raw_image, 'http') === 0) {
                            $image = $raw_image;
                        } elseif (!empty($raw_image)) {
                            $image = "../uploads/products/" . $raw_image;
                        } else {
                            $image = "https://via.placeholder.com/400x300/081822/40c4ff?text=No+Image";
                        }
                        
                        $in_wishlist = isset($_SESSION['wishlist']) && in_array($product['product_id'], $_SESSION['wishlist']);
                        ?>
                        <div class="col">
                            <div class="card product-card h-100 bg-dark border-0 shadow">
                                <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($image); ?>" 
                                             class="product-img card-img-top" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                        <?php if ($product['stock'] == 0): ?>
                                            <span class="position-absolute top-0 end-0 badge bg-danger m-2">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-subtitle text-muted mb-2"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></h6>
                                    <h5 class="card-title mb-3">
                                        <a href="product.php?id=<?php echo $product['product_id']; ?>" class="text-decoration-none text-white">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </a>
                                    </h5>
                                    <div class="mt-auto">
                                        <span class="price d-block mb-3">Rs. <?php echo number_format($product['price'], 2); ?></span>
                                        <div class="d-grid gap-2">
                                            <?php if ($product['stock'] > 0): ?>
                                                <form method="POST" action="product.php?id=<?php echo $product['product_id']; ?>">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm w-100">
                                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                                    </button>
                                                </form>
                                                <form method="POST" action="product.php?id=<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="add_to_wishlist" class="btn btn-outline-primary btn-sm w-100" <?php echo $in_wishlist ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-heart me-2"></i><?php echo $in_wishlist ? 'Wishlisted' : 'Wishlist'; ?>
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

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Product pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle sort change
    document.getElementById('sort').addEventListener('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', this.value);
        url.searchParams.set('page', '1'); // Reset to page 1 on sort
        window.location.href = url.toString();
    });
</script>
</body>
</html>