<?php
// customer/search.php - Product Search Page
session_start();
require_once '../config/dbconfig.php';
require_once '../includes/functions.php';

$page_title = "Search Products - Electronic Store";

// Get search parameters
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$products = [];
$total_products = 0;

if (!empty($search_query)) {
    try {
        // Build search query with named parameters
        $sql = "SELECT p.*, c.category_name FROM PRODUCTS p 
                LEFT JOIN CATEGORY c ON p.category_id = c.category_id 
                WHERE p.is_active = 1 
                AND (p.product_name LIKE :search1 OR p.description LIKE :search2)";
        
        $params = [
            ':search1' => "%{$search_query}%",
            ':search2' => "%{$search_query}%"
        ];
        
        // Add category filter
        if ($category_filter > 0) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = $category_filter;
        }
        
        // Add price filters
        if ($min_price > 0) {
            $sql .= " AND p.price >= :min_price";
            $params[':min_price'] = $min_price;
        }
        
        if ($max_price > 0) {
            $sql .= " AND p.price <= :max_price";
            $params[':max_price'] = $max_price;
        }
        
        // Get total count
        $count_sql = str_replace("p.*, c.category_name", "COUNT(*) as total", $sql);
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($params);
        $total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
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
                $sql .= " ORDER BY p.created_at DESC";
                break;
            case 'relevance':
            default:
                // Simple relevance: prioritize title matches over description matches
                $sql .= " ORDER BY 
                    CASE 
                        WHEN p.product_name LIKE :search_rel1 THEN 1
                        WHEN p.description LIKE :search_rel2 THEN 2
                        ELSE 3
                    END, p.product_name ASC";
                $params[':search_rel1'] = "%{$search_query}%";
                $params[':search_rel2'] = "%{$search_query}%";
                break;
        }
        
        // Add pagination with proper integer binding
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
        
        $total_pages = ceil($total_products / $per_page);
        
        // Get all categories for filter
        $cat_stmt = $conn->query("SELECT * FROM CATEGORY ORDER BY category_name");
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Error searching products: " . $e->getMessage();
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
        .product-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background-color: #081822;
        }
        .search-highlight {
            background-color: rgba(64, 196, 255, 0.2);
            padding: 0 2px;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Search Filters Sidebar -->
        <div class="col-lg-3">
            <div class="card border-0 shadow">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="search.php">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories ?? [] as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" 
                                            <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Price Range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="min_price" class="form-control" 
                                           placeholder="Min" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" 
                                           min="0" step="100">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="max_price" class="form-control" 
                                           placeholder="Max" value="<?php echo $max_price > 0 ? $max_price : ''; ?>" 
                                           min="0" step="100">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        
                        <?php if ($category_filter > 0 || $min_price > 0 || $max_price > 0): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>" 
                               class="btn btn-outline-secondary w-100 mt-2">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        <div class="col-lg-9">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($search_query)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-5x text-muted mb-4"></i>
                    <h3>Start Searching</h3>
                    <p class="text-muted">Enter a search term to find products</p>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1">Search Results</h2>
                        <p class="text-muted mb-0">
                            Found <?php echo $total_products; ?> result(s) for 
                            "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                        </p>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <label for="sort" class="text-nowrap mb-0 small">Sort:</label>
                        <form method="GET" id="sortForm">
                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                            <?php if ($category_filter > 0): ?>
                                <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                            <?php endif; ?>
                            <?php if ($min_price > 0): ?>
                                <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
                            <?php endif; ?>
                            <?php if ($max_price > 0): ?>
                                <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                            <?php endif; ?>
                            <select name="sort" id="sort" class="form-select form-select-sm" 
                                    style="width: auto;" onchange="this.form.submit()">
                                <option value="relevance" <?php echo $sort_by == 'relevance' ? 'selected' : ''; ?>>Relevance</option>
                                <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No products found</h4>
                        <p class="text-muted">Try adjusting your search or filters</p>
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
                                        <h6 class="card-subtitle text-muted mb-2">
                                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                        </h6>
                                        <h5 class="card-title mb-3">
                                            <a href="product.php?id=<?php echo $product['product_id']; ?>" 
                                               class="text-decoration-none text-white">
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
                        <nav aria-label="Search results pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>&category=<?php echo $category_filter; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort=<?php echo $sort_by; ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>&category=<?php echo $category_filter; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort=<?php echo $sort_by; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>&category=<?php echo $category_filter; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort=<?php echo $sort_by; ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>