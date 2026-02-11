<?php
/**
 * seller/add_products.php - Add/Edit Product Page (Enhanced)
 * Features: Grouped Categories + Image URL Upload
 * Cyan/Teal Dark Theme
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
$error = null;
$success = null;
$edit_mode = false;
$product = null;

// Check if editing existing product
if (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $edit_mode = true;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM PRODUCTS WHERE product_id = ? AND seller_id = ?");
        $stmt->execute([$product_id, $seller_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            header("Location: /25126463/seller/dashboard.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Product fetch error: " . $e->getMessage());
        header("Location: /25126463/seller/dashboard.php");
        exit;
    }
}

// Fetch categories and organize them into groups
try {
    $stmt = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name");
    $all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Define category groups for better organization
    $category_groups = [
        'Electronics' => ['Mobile Phones', 'Tablets', 'Laptops', 'Desktop Computers', 'Cameras', 'Audio & Headphones', 'Televisions', 'Gaming', 'Smartwatches', 'E-readers'],
        'Major Appliances' => ['Refrigerators', 'Air Conditioners', 'Washing Machines', 'Dishwashers', 'Microwave Ovens', 'Water Purifiers', 'Geysers'],
        'Kitchen Appliances' => ['Kitchen Appliances', 'Electric Kettles', 'Toasters', 'Rice Cookers', 'Induction Cooktops'],
        'Home Appliances' => ['Vacuum Cleaners', 'Air Purifiers', 'Irons', 'Fans', 'Coolers'],
        'Personal Care' => ['Hair Care', 'Shavers & Trimmers', 'Health Monitors', 'Massagers', 'Electric Toothbrushes'],
        'Kitchen & Dining' => ['Bottles & Flasks', 'Cups & Mugs', 'Cookware', 'Dinner Sets', 'Kitchen Storage', 'Tiffin Boxes', 'Water Dispensers'],
        'Beauty & Cosmetics' => ['Makeup', 'Skincare', 'Hair Products', 'Fragrances', 'Beauty Tools', 'Nail Care', 'Bath & Body'],
        'Accessories' => ['Mobile Accessories', 'Chargers & Cables', 'Power Banks', 'Laptop Accessories', 'Computer Peripherals', 'Storage Devices', 'Cables & Adapters', 'Screen Guards'],
        'Smart Home' => ['Smart Home Devices', 'Voice Assistants', 'Security Cameras', 'Smart Locks'],
        'Office' => ['Printers', 'Scanners', 'Projectors', 'Office Equipment', 'Stationery'],
        'Others' => []
    ];
    
    // Organize categories into groups
    $grouped_categories = [];
    $assigned_categories = [];
    
    foreach ($category_groups as $group_name => $group_keywords) {
        $grouped_categories[$group_name] = [];
        foreach ($all_categories as $cat) {
            foreach ($group_keywords as $keyword) {
                if (stripos($cat['category_name'], $keyword) !== false || $cat['category_name'] === $keyword) {
                    $grouped_categories[$group_name][] = $cat;
                    $assigned_categories[] = $cat['category_id'];
                    break;
                }
            }
        }
    }
    
    // Add remaining categories to "Others"
    foreach ($all_categories as $cat) {
        if (!in_array($cat['category_id'], $assigned_categories)) {
            $grouped_categories['Others'][] = $cat;
        }
    }
    
    // Remove empty groups
    $grouped_categories = array_filter($grouped_categories);
    
} catch (PDOException $e) {
    error_log("Category fetch error: " . $e->getMessage());
    $grouped_categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_upload_method = $_POST['image_upload_method'] ?? 'file';
    $image_url = trim($_POST['image_url'] ?? '');
    
    // Validation
    if (empty($product_name)) {
        $error = "Product name is required.";
    } elseif ($category_id <= 0) {
        $error = "Please select a valid category.";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0.";
    } elseif ($stock < 0) {
        $error = "Stock cannot be negative.";
    } else {
        $image_filename = $edit_mode ? $product['product_image'] : null;
        
        // Handle image upload based on method
        if ($image_upload_method === 'url' && !empty($image_url)) {
            // Download image from URL
            $upload_dir = __DIR__ . '/../uploads/products/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Validate URL
            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                // Set headers to mimic browser request
                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                    ]
                ];
                $context = stream_context_create($opts);
                $image_data = @file_get_contents($image_url, false, $context);
                
                if ($image_data !== false) {
                    $file_ext = 'jpg'; // Default extension
                    
                    // Try to get extension from URL
                    $parsed_url = parse_url($image_url);
                    if (isset($parsed_url['path'])) {
                        $path_ext = strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION));
                        if (in_array($path_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $file_ext = $path_ext;
                        }
                    }
                    
                    // Detect mime type from data
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->buffer($image_data);
                    $mime_to_ext = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp'
                    ];
                    if (isset($mime_to_ext[$mime_type])) {
                        $file_ext = $mime_to_ext[$mime_type];
                    }
                    
                    $new_filename = uniqid('product_', true) . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (file_put_contents($upload_path, $image_data)) {
                        // Delete old image if editing
                        if ($edit_mode && $product['product_image'] && file_exists($upload_dir . $product['product_image'])) {
                            unlink($upload_dir . $product['product_image']);
                        }
                        $image_filename = $new_filename;
                    } else {
                        $error = "Failed to save image from URL.";
                    }
                } else {
                    $error = "Failed to download image from URL. Please check the URL or try uploading a file instead.";
                }
            } else {
                $error = "Invalid image URL provided.";
            }
        } elseif ($image_upload_method === 'file' && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            // Handle file upload
            $upload_dir = __DIR__ . '/../uploads/products/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_tmp = $_FILES['product_image']['tmp_name'];
            $file_name = $_FILES['product_image']['name'];
            $file_size = $_FILES['product_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_ext, $allowed_extensions)) {
                $error = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.";
            } elseif ($file_size > $max_file_size) {
                $error = "File size must be less than 5MB.";
            } else {
                $new_filename = uniqid('product_', true) . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    if ($edit_mode && $product['product_image'] && file_exists($upload_dir . $product['product_image'])) {
                        unlink($upload_dir . $product['product_image']);
                    }
                    $image_filename = $new_filename;
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }
        
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_name), '-'));
        
        if (!$error) {
            try {
                if ($edit_mode) {
                    $stmt = $conn->prepare("
                        UPDATE PRODUCTS 
                        SET product_name = ?, category_id = ?, slug = ?, description = ?, 
                            price = ?, stock = ?, product_image = ?, is_active = ?
                        WHERE product_id = ? AND seller_id = ?
                    ");
                    $stmt->execute([
                        $product_name, $category_id, $slug, $description,
                        $price, $stock, $image_filename, $is_active,
                        $product_id, $seller_id
                    ]);
                    $success = "Product updated successfully!";
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO PRODUCTS 
                        (seller_id, category_id, product_name, slug, description, price, stock, product_image, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $seller_id, $category_id, $product_name, $slug, $description,
                        $price, $stock, $image_filename, $is_active
                    ]);
                    $success = "Product added successfully!";
                    $_POST = [];
                }
            } catch (PDOException $e) {
                error_log("Product save error: " . $e->getMessage());
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error = "A product with this name already exists.";
                } else {
                    $error = "Failed to save product. Please try again.";
                }
            }
        }
    }
}

$page_title = ($edit_mode ? 'Edit' : 'Add') . ' Product - Seller Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="add-products-page">
    <div class="container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <h2 class="page-title">
                        <i class="fas fa-<?= $edit_mode ? 'edit' : 'plus-circle' ?> me-2"></i>
                        <?= $edit_mode ? 'Edit' : 'Add New' ?> Product
                    </h2>
                    <p class="text-muted mb-0">Fill in the details to <?= $edit_mode ? 'update' : 'add' ?> your product</p>
                </div>
                <a href="/25126463/seller/dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                
                <!-- Messages -->
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Product Form -->
                <div class="card product-form-card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="productForm">
                            
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-info-circle me-2"></i>Basic Information
                                </h5>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="product_name" class="form-label">
                                            Product Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="product_name" 
                                               name="product_name" 
                                               value="<?= htmlspecialchars($product['product_name'] ?? $_POST['product_name'] ?? '') ?>"
                                               placeholder="e.g., product name"
                                               required 
                                               autofocus>
                                        <small class="text-muted">Enter a clear and descriptive product name</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="category_id" class="form-label">
                                            Category <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($grouped_categories as $group_name => $categories): ?>
                                                <?php if (!empty($categories)): ?>
                                                    <optgroup label="<?= htmlspecialchars($group_name) ?>">
                                                        <?php foreach ($categories as $cat): ?>
                                                            <option value="<?= $cat['category_id'] ?>" 
                                                                <?= (isset($product) && $product['category_id'] == $cat['category_id']) || 
                                                                    (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) 
                                                                    ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($cat['category_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Categories are organized by type for easy selection</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="price" class="form-label">
                                            Price (Rs.) <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="price" 
                                               name="price" 
                                               value="<?= $product['price'] ?? $_POST['price'] ?? '' ?>"
                                               step="0.01" 
                                               min="0" 
                                               placeholder="0.00"
                                               required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="stock" class="form-label">
                                            Stock Quantity <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="stock" 
                                               name="stock" 
                                               value="<?= $product['stock'] ?? $_POST['stock'] ?? '' ?>"
                                               min="0" 
                                               placeholder="0"
                                               required>
                                        <small class="text-muted">Available units in inventory</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label d-block">Status</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="is_active" 
                                                   name="is_active"
                                                   <?= (!isset($product) || $product['is_active']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">
                                                Product is Active
                                            </label>
                                        </div>
                                        <small class="text-muted">Inactive products won't be visible to buyers</small>
                                    </div>

                                    <div class="col-12">
                                        <label for="description" class="form-label">
                                            Description
                                        </label>
                                        <textarea class="form-control" 
                                                  id="description" 
                                                  name="description" 
                                                  rows="5" 
                                                  placeholder="Provide detailed information about your product..."><?= htmlspecialchars($product['description'] ?? $_POST['description'] ?? '') ?></textarea>
                                        <small class="text-muted">Include key features, specifications, and benefits</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Image -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-image me-2"></i>Product Image
                                </h5>
                                
                                <div class="image-upload-container">
                                    <?php if ($edit_mode && $product['product_image']): ?>
                                        <div class="current-image mb-3">
                                            <label class="form-label">Current Image</label>
                                            <div class="current-image-wrapper">
                                                <img src="/25126463/uploads/products/<?= htmlspecialchars($product['product_image']) ?>" 
                                                     alt="Current product" 
                                                     class="img-thumbnail">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Upload Method Selection -->
                                    <div class="upload-method-selector mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-upload me-2"></i>Choose Upload Method:
                                        </label>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="method-option">
                                                    <input class="form-check-input" type="radio" name="image_upload_method" 
                                                           id="method_file" value="file" checked>
                                                    <label class="form-check-label" for="method_file">
                                                        <i class="fas fa-file-upload me-2"></i>
                                                        <span>Upload from Computer</span>
                                                        <small>Select a file from your device</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="method-option">
                                                    <input class="form-check-input" type="radio" name="image_upload_method" 
                                                           id="method_url" value="url">
                                                    <label class="form-check-label" for="method_url">
                                                        <i class="fas fa-link me-2"></i>
                                                        <span>Upload from URL</span>
                                                        <small>Paste an image link</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- File Upload (shown by default) -->
                                    <div id="file-upload-section">
                                        <label for="product_image" class="form-label">
                                            <?= $edit_mode ? 'Upload New Image (optional)' : 'Upload Image' ?>
                                        </label>
                                        <div class="file-upload-wrapper">
                                            <input type="file" 
                                                   class="form-control" 
                                                   id="product_image" 
                                                   name="product_image" 
                                                   accept="image/jpeg,image/png,image/gif,image/webp">
                                            <div class="file-upload-label" id="fileLabel">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <span>Click to upload or drag and drop</span>
                                                <small>JPG, JPEG, PNG, GIF, WEBP (Max 5MB)</small>
                                            </div>
                                            <div class="image-preview" id="imagePreview" style="display: none;">
                                                <img src="" alt="Preview" id="previewImg">
                                                <button type="button" class="btn-remove-image" id="removeImage">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- URL Upload (hidden by default) -->
                                    <div id="url-upload-section" style="display: none;">
                                        <label for="image_url" class="form-label">
                                            Image URL <span class="text-muted">(Image Link)</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-link"></i>
                                            </span>
                                            <input type="url" 
                                                   class="form-control" 
                                                   id="image_url" 
                                                   name="image_url" 
                                                   placeholder="https://example.com/image.jpg">
                                            <button class="btn btn-outline-primary" type="button" id="previewUrl">
                                                <i class="fas fa-eye me-1"></i>Preview
                                            </button>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Paste the direct link to an image. Right-click on any image → "Copy image address"
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-check-circle me-1 text-success"></i>
                                            Supported: Amazon, Google Images, manufacturer websites, etc.
                                        </small>
                                        
                                        <div class="url-preview mt-3" id="urlPreview" style="display: none;">
                                            <div class="url-preview-header">
                                                <span>Image Preview:</span>
                                                <button type="button" class="btn-close-preview" id="closeUrlPreview">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <img src="" alt="URL Preview" id="urlPreviewImg">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-<?= $edit_mode ? 'save' : 'plus-circle' ?> me-2"></i>
                                    <?= $edit_mode ? 'Update' : 'Add' ?> Product
                                </button>
                                <a href="/25126463/seller/dashboard.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/25126463/css/add_products.css">
<script src="/25126463/js/add_products.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>