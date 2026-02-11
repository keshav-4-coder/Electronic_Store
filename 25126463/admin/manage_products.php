<?php
/**
 * admin/manage_products.php - Product Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/dbconfig.php';

$success_msg = '';
$error_msg = '';

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_name)));
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $seller_id = !empty($_POST['seller_id']) ? intval($_POST['seller_id']) : null;
    $product_image = trim($_POST['product_image']);
    
    if (empty($product_name) || empty($category_id) || $price <= 0) {
        $error_msg = "Please fill in all required fields with valid data!";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO PRODUCTS (seller_id, category_id, product_name, slug, description, price, stock, product_image, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$seller_id, $category_id, $product_name, $slug, $description, $price, $stock, $product_image]);
            $_SESSION['success_msg'] = "Product '{$product_name}' added successfully!";
            header("Location: manage_products.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error adding product: " . $e->getMessage();
        }
    }
}

// Handle Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $product_id = intval($_POST['product_id']);
    $product_name = trim($_POST['product_name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_name)));
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $seller_id = !empty($_POST['seller_id']) ? intval($_POST['seller_id']) : null;
    $product_image = trim($_POST['product_image']);
    
    if (empty($product_name) || empty($category_id) || $price <= 0) {
        $error_msg = "Please fill in all required fields with valid data!";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE PRODUCTS 
                SET seller_id = ?, category_id = ?, product_name = ?, slug = ?, description = ?, 
                    price = ?, stock = ?, product_image = ?
                WHERE product_id = ?
            ");
            $stmt->execute([$seller_id, $category_id, $product_name, $slug, $description, $price, $stock, $product_image, $product_id]);
            $_SESSION['success_msg'] = "Product '{$product_name}' updated successfully!";
            header("Location: manage_products.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error updating product: " . $e->getMessage();
        }
    }
}

// Handle Delete Product
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    try {
        // Check if product has orders
        $check = $conn->prepare("SELECT COUNT(*) as cnt FROM ORDER_ITEMS WHERE product_id = ?");
        $check->execute([$delete_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($row['cnt'] > 0) {
            $_SESSION['error_msg'] = "Cannot delete! Product has {$row['cnt']} order(s). It will be deactivated instead.";
            // Deactivate instead
            $stmt = $conn->prepare("UPDATE PRODUCTS SET is_active = 0 WHERE product_id = ?");
            $stmt->execute([$delete_id]);
        } else {
            $name_stmt = $conn->prepare("SELECT product_name FROM PRODUCTS WHERE product_id = ?");
            $name_stmt->execute([$delete_id]);
            $product = $name_stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("DELETE FROM PRODUCTS WHERE product_id = ?");
            $stmt->execute([$delete_id]);
            $_SESSION['success_msg'] = "Product '" . ($product['product_name'] ?? 'Unknown') . "' deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: manage_products.php");
    exit;
}

// Handle Toggle Active Status
if (isset($_GET['toggle_id']) && is_numeric($_GET['toggle_id'])) {
    $toggle_id = intval($_GET['toggle_id']);
    try {
        $stmt = $conn->prepare("UPDATE PRODUCTS SET is_active = NOT is_active WHERE product_id = ?");
        $stmt->execute([$toggle_id]);
        $_SESSION['success_msg'] = "Product status updated!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: manage_products.php");
    exit;
}

// Get session messages
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where_conditions = ["1=1"];
$params = [];

if ($filter === 'active') $where_conditions[] = "p.is_active = 1";
elseif ($filter === 'inactive') $where_conditions[] = "p.is_active = 0";
elseif ($filter === 'low_stock') $where_conditions[] = "p.stock < 10";

if ($search) { 
    $where_conditions[] = "p.product_name LIKE ?"; 
    $params[] = "%$search%"; 
}

if ($category_filter) { 
    $where_conditions[] = "p.category_id = ?"; 
    $params[] = $category_filter; 
}

$where_clause = implode(" AND ", $where_conditions);

// Fetch products
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.category_name, u.full_name as seller_name
        FROM PRODUCTS p
        LEFT JOIN CATEGORY c ON p.category_id = c.category_id
        LEFT JOIN USERS u ON p.seller_id = u.user_id
        WHERE $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error loading products: " . $e->getMessage();
    $products = [];
}

// Get dropdown data
try {
    $categories = $conn->query("SELECT * FROM CATEGORY ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
    $sellers = $conn->query("SELECT user_id, full_name FROM USERS WHERE role = 'seller' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
    $sellers = [];
}

// Get product for editing
$edit_product = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM PRODUCTS WHERE product_id = ?");
        $stmt->execute([$_GET['edit_id']]);
        $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_product) {
            $error_msg = "Product not found!";
        }
    } catch (PDOException $e) {
        $error_msg = "Error loading product: " . $e->getMessage();
    }
}

$page_title = 'Manage Products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #0f172a;
            color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .admin-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            background: #1e293b;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .form-card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .filter-card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .table-card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .form-control, .form-select, .form-control:focus, .form-select:focus {
            background: #0f172a;
            border: 1px solid #334155;
            color: white;
        }
        
        .form-control::placeholder {
            color: #64748b;
        }
        
        .form-label {
            color: #94a3b8;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .table {
            color: #cbd5e1;
            margin-bottom: 0;
        }
        
        .table thead th {
            background: rgba(255,255,255,0.02);
            color: #94a3b8;
            border-bottom: 1px solid #334155;
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table td {
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: rgba(255,255,255,0.02);
        }
        
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #334155;
        }
        
        .img-placeholder {
            width: 60px;
            height: 60px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }
        
        .btn-cyan {
            background: #06b6d4;
            color: white;
            border: none;
        }
        
        .btn-cyan:hover {
            background: #0891b2;
            color: white;
        }
        
        .badge-stock {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="admin-container">
    
    <!-- Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-0 text-info">
                <i class="fas fa-box-open me-2"></i>Manage Products
            </h3>
            <p class="text-muted small mb-0">Total: <?php echo count($products); ?> products</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Messages -->
    <?php if ($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="form-card">
        <h5 class="mb-4">
            <i class="fas fa-<?php echo $edit_product ? 'edit' : 'plus-circle'; ?> me-2 text-info"></i>
            <?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?>
        </h5>
        
        <form method="POST">
            <?php if ($edit_product): ?>
            <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="product_name" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_product['product_name'] ?? ''); ?>" 
                           required placeholder="e.g., product name">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">Price (Rs.) *</label>
                    <input type="number" name="price" class="form-control" step="0.01"
                           value="<?php echo htmlspecialchars($edit_product['price'] ?? ''); ?>" 
                           required placeholder="0.00">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">Stock *</label>
                    <input type="number" name="stock" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_product['stock'] ?? '0'); ?>" 
                           required placeholder="0">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category *</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"
                            <?php echo ($edit_product && $edit_product['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Seller (Optional)</label>
                    <select name="seller_id" class="form-select">
                        <option value="">Admin (No Seller)</option>
                        <?php foreach ($sellers as $seller): ?>
                        <option value="<?php echo $seller['user_id']; ?>"
                            <?php echo ($edit_product && $edit_product['seller_id'] == $seller['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($seller['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">Image URL</label>
                    <input type="text" name="product_image" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_product['product_image'] ?? ''); ?>" 
                           placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" 
                              placeholder="Product description..."><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>" 
                            class="btn btn-cyan px-4 me-2">
                        <i class="fas fa-<?php echo $edit_product ? 'save' : 'plus'; ?> me-1"></i>
                        <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                    </button>
                    
                    <?php if ($edit_product): ?>
                    <a href="manage_products.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Search</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Product name..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>" 
                        <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Status</label>
                <select name="filter" class="form-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Products</option>
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>Low Stock (&lt; 10)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-info w-100">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="table-card">
        <div class="p-3 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-list me-2 text-info"></i>
                All Products (<?php echo count($products); ?>)
            </h6>
        </div>
        
        <?php if (count($products) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="80">Image</th>
                        <th width="25%">Product</th>
                        <th width="15%">Category</th>
                        <th width="10%">Price</th>
                        <th width="10%">Stock</th>
                        <th width="10%">Status</th>
                        <th width="15%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['product_image'])): ?>
                            <img src="<?php echo htmlspecialchars($p['product_image']); ?>" 
                                 class="product-img"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="img-placeholder" style="display:none;">
                                <i class="fas fa-image"></i>
                            </div>
                            <?php else: ?>
                            <div class="img-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-bold text-white">
                                <?php echo htmlspecialchars($p['product_name']); ?>
                            </div>
                            <small class="text-muted">
                                Seller: <?php echo htmlspecialchars($p['seller_name'] ?? 'Admin'); ?>
                            </small>
                        </td>
                        <td>
                            <span class="text-muted">
                                <?php echo htmlspecialchars($p['category_name'] ?? 'No Category'); ?>
                            </span>
                        </td>
                        <td class="fw-bold text-info">
                            Rs. <?php echo number_format($p['price'], 2); ?>
                        </td>
                        <td>
                            <?php if ($p['stock'] == 0): ?>
                                <span class="badge bg-danger badge-stock">Out of Stock</span>
                            <?php elseif ($p['stock'] < 10): ?>
                                <span class="badge bg-warning text-dark badge-stock"><?php echo $p['stock']; ?> left</span>
                            <?php else: ?>
                                <span class="text-muted"><?php echo $p['stock']; ?> units</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $p['is_active'] ? 'bg-success' : 'bg-secondary'; ?> badge-status">
                                <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="?edit_id=<?php echo $p['product_id']; ?>" 
                               class="btn btn-sm btn-outline-warning me-1">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?toggle_id=<?php echo $p['product_id']; ?>" 
                               class="btn btn-sm btn-outline-info me-1"
                               title="Toggle Status">
                                <i class="fas fa-eye<?php echo $p['is_active'] ? '-slash' : ''; ?>"></i>
                            </a>
                            <a href="?delete_id=<?php echo $p['product_id']; ?>" 
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this product?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h5 class="mt-3 text-white">No Products Found</h5>
            <p>Add your first product using the form above!</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide alerts
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

</body>
</html>