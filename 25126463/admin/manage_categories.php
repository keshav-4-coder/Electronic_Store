<?php
/**
 * admin/manage_categories.php - Category Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/dbconfig.php';

$success_msg = '';
$error_msg = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $category_image = trim($_POST['category_image']);
    
    if (empty($category_name)) {
        $error_msg = "Category name is required!";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO CATEGORY (category_name, description, category_image) VALUES (?, ?, ?)");
            $stmt->execute([$category_name, $description, $category_image]);
            $_SESSION['success_msg'] = "Category added successfully!";
            header("Location: manage_categories.php");
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error_msg = "Category name already exists!";
            } else {
                $error_msg = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $category_image = trim($_POST['category_image']);
    
    if (empty($category_name)) {
        $error_msg = "Category name is required!";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE CATEGORY SET category_name = ?, description = ?, category_image = ? WHERE category_id = ?");
            $stmt->execute([$category_name, $description, $category_image, $category_id]);
            $_SESSION['success_msg'] = "Category updated successfully!";
            header("Location: manage_categories.php");
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error_msg = "Category name already exists!";
            } else {
                $error_msg = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete Category
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    try {
        // Check if category has products
        $check = $conn->prepare("SELECT COUNT(*) as cnt FROM PRODUCTS WHERE category_id = ?");
        $check->execute([$delete_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($row['cnt'] > 0) {
            $_SESSION['error_msg'] = "Cannot delete! Category has {$row['cnt']} product(s).";
        } else {
            $stmt = $conn->prepare("DELETE FROM CATEGORY WHERE category_id = ?");
            $stmt->execute([$delete_id]);
            $_SESSION['success_msg'] = "Category deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: manage_categories.php");
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

// FETCH ALL CATEGORIES - Simple and direct
$categories = [];
try {
    $query = "SELECT c.*, COALESCE(COUNT(p.product_id), 0) as product_count 
              FROM CATEGORY c 
              LEFT JOIN PRODUCTS p ON c.category_id = p.category_id 
              GROUP BY c.category_id 
              ORDER BY c.category_name";
    $result = $conn->query($query);
    $categories = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}

// Get category for editing
$edit_category = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM CATEGORY WHERE category_id = ?");
        $stmt->execute([$edit_id]);
        $edit_category = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_msg = "Error loading category: " . $e->getMessage();
    }
}

$page_title = 'Manage Categories';
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
            max-width: 1400px;
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
        
        .table-card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .form-control, .form-control:focus {
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
        
        .category-img {
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
        
        .badge-count {
            background: rgba(6, 182, 212, 0.1);
            color: #06b6d4;
            border: 1px solid rgba(6, 182, 212, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
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
                <i class="fas fa-tags me-2"></i>Manage Categories
            </h3>
            <p class="text-muted small mb-0">Total: <?php echo count($categories); ?> categories</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Success/Error Messages -->
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
            <i class="fas fa-<?php echo $edit_category ? 'edit' : 'plus-circle'; ?> me-2 text-info"></i>
            <?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?>
        </h5>
        
        <form method="POST">
            <?php if ($edit_category): ?>
            <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="category_name" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_category['category_name'] ?? ''); ?>" 
                           required maxlength="100" placeholder="e.g., Mobile Phones">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Image URL</label>
                    <input type="text" name="category_image" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_category['category_image'] ?? ''); ?>" 
                           maxlength="255" placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" 
                              maxlength="500" placeholder="Brief description..."><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="<?php echo $edit_category ? 'edit_category' : 'add_category'; ?>" 
                            class="btn btn-cyan px-4 me-2">
                        <i class="fas fa-<?php echo $edit_category ? 'save' : 'plus'; ?> me-1"></i>
                        <?php echo $edit_category ? 'Update' : 'Add Category'; ?>
                    </button>
                    
                    <?php if ($edit_category): ?>
                    <a href="manage_categories.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Categories Table -->
    <div class="table-card">
        <div class="p-3 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-list me-2 text-info"></i>
                All Categories (<?php echo count($categories); ?>)
            </h6>
        </div>
        
        <?php if (count($categories) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="80">Image</th>
                        <th width="20%">Name</th>
                        <th width="35%">Description</th>
                        <th width="10%">Products</th>
                        <th width="12%">Created</th>
                        <th width="15%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <?php if (!empty($cat['category_image'])): ?>
                            <img src="<?php echo htmlspecialchars($cat['category_image']); ?>" 
                                 class="category-img" 
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
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </div>
                            <small class="text-muted">ID: <?php echo $cat['category_id']; ?></small>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php 
                                if (!empty($cat['description'])) {
                                    echo htmlspecialchars(substr($cat['description'], 0, 80));
                                    echo strlen($cat['description']) > 80 ? '...' : '';
                                } else {
                                    echo '<em>No description</em>';
                                }
                                ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge-count">
                                <?php echo $cat['product_count']; ?> 
                                <?php echo $cat['product_count'] == 1 ? 'item' : 'items'; ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('M d, Y', strtotime($cat['created_at'])); ?>
                            </small>
                        </td>
                        <td class="text-end">
                            <a href="?edit_id=<?php echo $cat['category_id']; ?>" 
                               class="btn btn-sm btn-outline-warning me-1">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?delete_id=<?php echo $cat['category_id']; ?>" 
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('<?php echo $cat['product_count'] > 0 ? 'This category has products. Delete anyway?' : 'Delete this category?'; ?>')">
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
            <i class="fas fa-tags"></i>
            <h5 class="mt-3 text-white">No Categories Yet</h5>
            <p>Create your first category above!</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide alerts after 5 seconds
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