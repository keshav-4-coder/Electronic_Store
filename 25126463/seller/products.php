<?php
/**
 * seller/products.php - Manage Products
 * Cyan/Teal Dark Theme - Fixed Delete Functionality
 */

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

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    
    try {
        // Check if product belongs to seller
        $stmt = $conn->prepare("SELECT product_image FROM PRODUCTS WHERE product_id = ? AND seller_id = ?");
        $stmt->execute([$product_id, $seller_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Check if product is in any orders
            $check_orders = $conn->prepare("SELECT COUNT(*) FROM ORDER_ITEMS WHERE product_id = ?");
            $check_orders->execute([$product_id]);
            $order_count = $check_orders->fetchColumn();
            
            if ($order_count > 0) {
                // Product has orders - just deactivate it instead of deleting
                $stmt = $conn->prepare("UPDATE PRODUCTS SET is_active = 0 WHERE product_id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                $error = "This product has existing orders and cannot be deleted. It has been deactivated instead.";
            } else {
                // No orders - safe to delete
                // Delete product image first
                if ($product['product_image']) {
                    $image_path = __DIR__ . '/../uploads/products/' . $product['product_image'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                
                // Delete product
                $stmt = $conn->prepare("DELETE FROM PRODUCTS WHERE product_id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                $success = "Product deleted successfully!";
            }
        } else {
            $error = "Product not found or you don't have permission to delete it.";
        }
    } catch (PDOException $e) {
        error_log("Product deletion error: " . $e->getMessage());
        
        // Check if it's a foreign key constraint error
        if (strpos($e->getMessage(), 'foreign key constraint') !== false || 
            strpos($e->getMessage(), 'FOREIGN KEY') !== false ||
            $e->getCode() == '23000') {
            $error = "Cannot delete this product because it's part of existing orders. The product has been deactivated instead.";
            
            // Deactivate the product
            try {
                $stmt = $conn->prepare("UPDATE PRODUCTS SET is_active = 0 WHERE product_id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
            } catch (PDOException $e2) {
                error_log("Product deactivation error: " . $e2->getMessage());
            }
        } else {
            $error = "Failed to delete product. Please try again.";
        }
    }
}

// Handle toggle active status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $product_id = (int)$_GET['toggle'];
    
    try {
        $stmt = $conn->prepare("
            UPDATE PRODUCTS 
            SET is_active = NOT is_active 
            WHERE product_id = ? AND seller_id = ?
        ");
        $stmt->execute([$product_id, $seller_id]);
        $success = "Product status updated successfully!";
    } catch (PDOException $e) {
        error_log("Status toggle error: " . $e->getMessage());
        $error = "Failed to update status.";
    }
}

// Pagination and filtering
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$filter_category = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with named parameters
$where_conditions = ["seller_id = :seller_id"];
$params = [':seller_id' => $seller_id];

if ($filter_category > 0) {
    $where_conditions[] = "category_id = :category_id";
    $params[':category_id'] = $filter_category;
}

if ($filter_status === 'active') {
    $where_conditions[] = "is_active = 1";
} elseif ($filter_status === 'inactive') {
    $where_conditions[] = "is_active = 0";
}

if ($search) {
    $where_conditions[] = "(product_name LIKE :search1 OR description LIKE :search2)";
    $search_param = "%$search%";
    $params[':search1'] = $search_param;
    $params[':search2'] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

try {
    // Get total count
    $count_query = "SELECT COUNT(*) FROM PRODUCTS WHERE $where_clause";
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_products = $stmt->fetchColumn();
    $total_pages = ceil($total_products / $per_page);
    
    // Get products with LIMIT and OFFSET as integers
    $products_query = "
        SELECT p.*, c.category_name 
        FROM PRODUCTS p
        LEFT JOIN CATEGORY c ON p.category_id = c.category_id
        WHERE $where_clause
        ORDER BY p.created_at DESC
        LIMIT :per_page OFFSET :offset
    ";
    
    $stmt = $conn->prepare($products_query);
    
    // Bind all named parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind LIMIT and OFFSET as integers
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $stmt = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Products fetch error: " . $e->getMessage());
    $products = [];
    $categories = [];
    $total_products = 0;
    $total_pages = 0;
}

$page_title = 'Manage Products - Seller Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="manage-products-page">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="page-title">
                        <i class="fas fa-boxes me-2"></i>
                        Manage Products
                    </h2>
                    <p class="text-muted mb-0">View and manage your product listings</p>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <a href="/25126463/seller/add_products.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Product
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

        <!-- Filters -->
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label for="search" class="form-label">Search Products</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="search" 
                                   name="search" 
                                   value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search by name or description...">
                        </div>
                        <div class="col-lg-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" 
                                        <?= $filter_category == $cat['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All</option>
                                <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card products-table-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    Your Products (<?= number_format($total_products) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>No products found</p>
                        <a href="/25126463/seller/add_products.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Your First Product
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover products-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="product-cell">
                                                <?php if ($product['product_image']): ?>
                                                    <img src="/25126463/uploads/products/<?= htmlspecialchars($product['product_image']) ?>" 
                                                         alt="<?= htmlspecialchars($product['product_name']) ?>"
                                                         class="product-img">
                                                <?php else: ?>
                                                    <div class="product-img-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="product-details">
                                                    <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                                                    <?php if ($product['description']): ?>
                                                        <small class="text-muted d-block">
                                                            <?= htmlspecialchars(substr($product['description'], 0, 60)) ?>...
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($product['category_name']) ?>
                                            </span>
                                        </td>
                                        <td class="text-primary fw-semibold">
                                            Rs. <?= number_format($product['price'], 2) ?>
                                        </td>
                                        <td>
                                            <?php if ($product['stock'] == 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($product['stock'] < 10): ?>
                                                <span class="badge bg-warning text-dark"><?= $product['stock'] ?> units</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= $product['stock'] ?> units</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input status-toggle" 
                                                       type="checkbox" 
                                                       <?= $product['is_active'] ? 'checked' : '' ?>
                                                       data-product-id="<?= $product['product_id'] ?>">
                                            </div>
                                        </td>
                                        <td class="text-muted small">
                                            <?= date('M d, Y', strtotime($product['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="/25126463/seller/add_products.php?id=<?= $product['product_id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger delete-btn" 
                                                        data-product-id="<?= $product['product_id'] ?>"
                                                        data-product-name="<?= htmlspecialchars($product['product_name']) ?>"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper">
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?><?= $filter_category ? '&category=' . $filter_category : '' ?><?= $filter_status !== 'all' ? '&status=' . $filter_status : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= $filter_category ? '&category=' . $filter_category : '' ?><?= $filter_status !== 'all' ? '&status=' . $filter_status : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?><?= $filter_category ? '&category=' . $filter_category : '' ?><?= $filter_status !== 'all' ? '&status=' . $filter_status : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    Confirm Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="productNameToDelete"></strong>?</p>
                <div class="alert alert-info mb-2">
                    <i class="fas fa-info-circle me-1"></i>
                    <small>Note: If this product has existing orders, it will be deactivated instead of deleted.</small>
                </div>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    <small>This action cannot be undone.</small>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Product
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Manage Products Page Styles */
.manage-products-page {
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

/* Filter Card */
.filter-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

/* Products Table Card */
.products-table-card {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    border-radius: 12px;
}

.products-table-card .card-header {
    background-color: rgba(26, 58, 74, 0.5);
    border-bottom: 1px solid #1a3a4a;
    padding: 1.25rem 1.5rem;
}

.card-title {
    color: #40c4ff;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Products Table */
.products-table {
    color: #c0d4dd;
    margin-bottom: 0;
}

.products-table thead th {
    background-color: rgba(26, 58, 74, 0.5);
    color: #40c4ff;
    border-bottom: 2px solid #1a3a4a;
    font-weight: 600;
    padding: 1rem;
    white-space: nowrap;
}

.products-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #1a3a4a;
    vertical-align: middle;
}

.products-table tbody tr:hover {
    background-color: rgba(64, 196, 255, 0.05);
}

/* Product Cell */
.product-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.product-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #1a3a4a;
    flex-shrink: 0;
}

.product-img-placeholder {
    width: 60px;
    height: 60px;
    background-color: #081822;
    border: 1px solid #1a3a4a;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #627d8a;
    flex-shrink: 0;
}

.product-details strong {
    color: #c0d4dd;
    display: block;
    margin-bottom: 0.25rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.action-buttons .btn {
    padding: 0.375rem 0.75rem;
}

/* Status Toggle */
.status-toggle {
    cursor: pointer;
    width: 3rem;
    height: 1.5rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #627d8a;
}

.empty-state i {
    font-size: 4rem;
    color: #1a3a4a;
    margin-bottom: 1.5rem;
}

.empty-state p {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
}

/* Pagination */
.pagination-wrapper {
    padding: 1.5rem;
    border-top: 1px solid #1a3a4a;
}

.pagination .page-link {
    background-color: #081822;
    border-color: #1a3a4a;
    color: #c0d4dd;
    margin: 0 0.25rem;
    border-radius: 8px;
}

.pagination .page-link:hover {
    background-color: rgba(64, 196, 255, 0.1);
    border-color: #40c4ff;
    color: #40c4ff;
}

.pagination .page-item.active .page-link {
    background-color: #40c4ff;
    border-color: #40c4ff;
    color: #0a1f2a;
}

/* Modal Styles */
.modal-content {
    background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
    border: 1px solid #1a3a4a;
    color: #c0d4dd;
}

.modal-header {
    border-bottom: 1px solid #1a3a4a;
}

.modal-footer {
    border-top: 1px solid #1a3a4a;
}

.modal-title {
    color: #40c4ff;
}

.btn-close {
    filter: invert(1);
}

/* Responsive */
@media (max-width: 768px) {
    .product-cell {
        flex-direction: column;
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .products-table {
        font-size: 0.875rem;
    }
}
</style>

<!-- Bootstrap JS (Required for modals and dropdowns) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert && !alert.classList.contains('d-none')) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, 5000);
    });
    
    // Status toggle
    const statusToggles = document.querySelectorAll('.status-toggle');
    statusToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const productId = this.getAttribute('data-product-id');
            if (productId) {
                window.location.href = '?toggle=' + productId + '<?= $filter_category ? "&category=" . $filter_category : "" ?><?= $filter_status !== "all" ? "&status=" . $filter_status : "" ?><?= $search ? "&search=" . urlencode($search) : "" ?>';
            }
        });
    });
    
    // Delete confirmation modal
    const deleteModalEl = document.getElementById('deleteModal');
    if (deleteModalEl) {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        const deleteBtns = document.querySelectorAll('.delete-btn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const productNameSpan = document.getElementById('productNameToDelete');
        
        deleteBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.getAttribute('data-product-id');
                const productName = this.getAttribute('data-product-name');
                
                if (productId && productName && productNameSpan && confirmDeleteBtn) {
                    productNameSpan.textContent = productName;
                    confirmDeleteBtn.href = '?delete=' + productId + '<?= $filter_category ? "&category=" . $filter_category : "" ?><?= $filter_status !== "all" ? "&status=" . $filter_status : "" ?><?= $search ? "&search=" . urlencode($search) : "" ?>';
                    deleteModal.show();
                }
            });
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>