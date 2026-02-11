<?php
/**
 * functions.php - Helper Functions
 * Common functions used across the application
 */

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is seller
 */
function is_seller() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'seller';
}

/**
 * Check if user is buyer
 */
function is_buyer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'buyer';
}

/**
 * Redirect to login if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /25126463/auth/login.php");
        exit;
    }
}

/**
 * Redirect to admin dashboard if not admin
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        header("Location: /25126463/customer/index.php");
        exit;
    }
}

/**
 * Redirect to seller dashboard if not seller
 */
function require_seller() {
    require_login();
    if (!is_seller() && !is_admin()) {
        header("Location: /25126463/customer/index.php");
        exit;
    }
}

/**
 * Get cart count for current user
 */
function get_cart_count() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        // Check session-based cart
        return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM CART 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting cart count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Format price with currency
 */
function format_price($price) {
    return 'Rs. ' . number_format($price, 2);
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Generate slug from string
 */
function generate_slug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Upload image file
 */
function upload_image($file, $target_dir = 'products') {
    $upload_dir = __DIR__ . '/../uploads/' . $target_dir . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'];
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true, 
            'filename' => $filename,
            'path' => '/25126463/uploads/' . $target_dir . '/' . $filename
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}

/**
 * Delete image file
 */
function delete_image($filename, $target_dir = 'products') {
    $file_path = __DIR__ . '/../uploads/' . $target_dir . '/' . $filename;
    
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    
    return false;
}

/**
 * Get image URL
 */
function get_image_url($filename, $target_dir = 'products') {
    if (empty($filename)) {
        return '/25126463/uploads/placeholder.jpg';
    }
    return '/25126463/uploads/' . $target_dir . '/' . $filename;
}

/**
 * Paginate results
 */
function paginate($total_records, $records_per_page = 10, $current_page = 1) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

/**
 * Generate pagination HTML
 */
function render_pagination($pagination, $base_url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($pagination['has_previous']) {
        $prev_page = $pagination['current_page'] - 1;
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $prev_page . '">Previous</a></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $active = $i === $pagination['current_page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $base_url . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $next_page = $pagination['current_page'] + 1;
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $next_page . '">Next</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Send email (basic implementation)
 */
function send_email($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Electronic Store <noreply@electronicstore.com>" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $details = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO ACTIVITY_LOG (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt->execute([$user_id, $action, $details, $ip_address, $user_agent]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get order status badge HTML
 */
function get_status_badge($status) {
    $badges = [
        'pending' => '<span class="order-status pending"><i class="fas fa-clock me-1"></i>Pending</span>',
        'processing' => '<span class="order-status processing"><i class="fas fa-cog me-1"></i>Processing</span>',
        'shipped' => '<span class="order-status shipped"><i class="fas fa-shipping-fast me-1"></i>Shipped</span>',
        'delivered' => '<span class="order-status delivered"><i class="fas fa-check-circle me-1"></i>Delivered</span>',
        'cancelled' => '<span class="order-status cancelled"><i class="fas fa-times-circle me-1"></i>Cancelled</span>',
        'returned' => '<span class="order-status cancelled"><i class="fas fa-undo me-1"></i>Returned</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Get time ago format
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    $periods = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];
    
    foreach ($periods as $key => $value) {
        $count = floor($difference / $value);
        
        if ($count > 0) {
            return $count . ' ' . $key . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    
    return 'Just now';
}

/**
 * Check if CART table exists, if not create session-based cart
 */
function ensure_cart_system() {
    global $conn;
    
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'CART'");
        $table_exists = $stmt->rowCount() > 0;
        
        if (!$table_exists && !isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        return $table_exists;
    } catch (PDOException $e) {
        error_log("Error checking cart system: " . $e->getMessage());
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return false;
    }
}

/**
 * Add to cart (works with both database and session)
 */
function add_to_cart($product_id, $quantity = 1) {
    global $conn;
    
    if (ensure_cart_system()) {
        // Database-based cart
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        try {
            // Check if item already in cart
            $check = $conn->prepare("SELECT cart_id, quantity FROM CART WHERE user_id = ? AND product_id = ?");
            $check->execute([$_SESSION['user_id'], $product_id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                $stmt = $conn->prepare("UPDATE CART SET quantity = ? WHERE cart_id = ?");
                $stmt->execute([$new_quantity, $existing['cart_id']]);
            } else {
                // Insert new item
                $stmt = $conn->prepare("INSERT INTO CART (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error adding to cart: " . $e->getMessage());
            return false;
        }
    } else {
        // Session-based cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        
        return true;
    }
}

?>