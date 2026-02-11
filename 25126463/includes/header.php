<?php
/**
 * header.php - Professional header with navbar, hamburger & offcanvas
 * Cyan/Teal Dark Theme - Electronic Store - FIXED VERSION
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine the correct base path
$script_path = $_SERVER['SCRIPT_FILENAME'];
$doc_root = $_SERVER['DOCUMENT_ROOT'];

// Find the position of '25126463' in the path
if (strpos($script_path, '25126463') !== false) {
    $base_path = '/25126463';
} else {
    $base_path = '';
}

// Determine current directory level
$current_file = basename($_SERVER['PHP_SELF']);
$path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
$depth = count($path_parts) - 1;

// Calculate relative path to root
$relative_root = str_repeat('../', $depth);
if (empty($relative_root)) {
    $relative_root = './';
}

// Include required files with proper paths
$config_path = __DIR__ . '/../config/dbconfig.php';
$functions_path = __DIR__ . '/functions.php';

if (file_exists($config_path)) {
    require_once $config_path;
} else {
    require_once dirname(__DIR__) . '/config/dbconfig.php';
}

if (file_exists($functions_path)) {
    require_once $functions_path;
} else {
    require_once __DIR__ . '/functions.php';
}

$cart_count = function_exists('get_cart_count') ? get_cart_count() : 0;

// Profile picture support
$user_profile_picture = $_SESSION['profile_picture'] ?? null;
$user_initials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 1)) : 'U';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : "Electronic Store" ?></title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Custom styles -->
    <link rel="stylesheet" href="/25126463/css/index.css">
    <link rel="stylesheet" href="/25126463/css/header.css">
    <link rel="stylesheet" href="/25126463/css/auth-pages.css">

    <!-- Page-specific CSS -->
    <?php
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    
    // Admin pages
    if ($current_dir === 'admin' || strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
        echo '<link rel="stylesheet" href="/25126463/css/dashboard.css">';
    }
    
    // Customer pages
    if (in_array($current_file, ['cart.php','checkout.php','my_account.php','orders.php','payment.php','wishlist.php'])) {
        echo '<link rel="stylesheet" href="/25126463/css/customer.css">';
    }
    
    // Product pages
    if (in_array($current_file, ['product.php','category.php','search.php','brands.php'])) {
        echo '<link rel="stylesheet" href="/25126463/css/product.css">';
    }
    
    // Auth pages
    if (in_array($current_file, ['login.php','register.php','forget-password.php','reset-password.php','change-password.php'])) {
        echo '<link rel="stylesheet" href="/25126463/css/login.css">';
        if ($current_file === 'register.php') {
            echo '<link rel="stylesheet" href="/25126463/css/register.css">';
        }
        if ($current_file === 'forget-password.php') {
            echo '<link rel="stylesheet" href="/25126463/css/forget-password.css">';
        }
        if (in_array($current_file, ['reset-password.php','change-password.php'])) {
            echo '<link rel="stylesheet" href="/25126463/css/reset-password.css">';
        }
        if ($current_file === 'my_account.php') {
            echo '<link rel="stylesheet" href="/25126463/css/my_account.css">';
        }
    }
    
    // Seller pages
    if ($current_dir === 'seller' || strpos($_SERVER['PHP_SELF'], '/seller/') !== false) {
        echo '<link rel="stylesheet" href="/25126463/css/dashboard.css">';
    }
    ?>
    
    <style>
        /* Layout Structure */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            background-color: #0a1929;
        }

        /* Navbar */
        .navbar {
            background-color: #0d2135;
            border-bottom: 1px solid rgba(64, 196, 255, 0.1);
            padding: 0.75rem 0;
        }

        .navbar-brand {
            color: #40c4ff !important;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .navbar-brand:hover {
            color: #80d4ff !important;
        }

        /* Hamburger Button */
        .hamburger-btn {
            background: transparent;
            border: 1px solid rgba(64, 196, 255, 0.3);
            color: #40c4ff;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            margin-right: 1rem;
        }

        .hamburger-btn:hover {
            background-color: rgba(64, 196, 255, 0.1);
            border-color: #40c4ff;
        }

        /* Search Bar */
        .navbar .form-control {
            background-color: #0a1929;
            border-color: rgba(64, 196, 255, 0.2);
            color: #b0bec5;
        }

        .navbar .form-control:focus {
            background-color: #081521;
            border-color: #40c4ff;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(64, 196, 255, 0.25);
        }

        .navbar .btn-outline-cyan {
            border-color: rgba(64, 196, 255, 0.5);
            color: #40c4ff;
        }

        .navbar .btn-outline-cyan:hover {
            background-color: #40c4ff;
            border-color: #40c4ff;
            color: #fff;
        }

        /* Cart Badge */
        .navbar .position-relative .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.65rem;
        }

        /* User Dropdown */
        .navbar .dropdown-toggle {
            color: #b0bec5 !important;
            padding: 0.5rem;
        }

        .navbar .dropdown-toggle:hover {
            color: #40c4ff !important;
        }

        .navbar .dropdown-toggle::after {
            display: none;
        }

        .navbar .dropdown-menu {
            background-color: #0d2135;
            border-color: rgba(64, 196, 255, 0.2);
            min-width: 200px;
        }

        .navbar .dropdown-item {
            color: #b0bec5;
        }

        .navbar .dropdown-item:hover {
            background-color: rgba(64, 196, 255, 0.1);
            color: #40c4ff;
        }

        /* Profile Picture in Navbar */
        .navbar .user-profile-pic {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(64, 196, 255, 0.5);
        }

        .navbar .user-profile-initials {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(64, 196, 255, 0.4), rgba(20, 184, 166, 0.4));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #40c4ff;
            font-weight: 600;
            font-size: 0.9rem;
            border: 2px solid rgba(64, 196, 255, 0.3);
        }

        /* Sidebar Container - Desktop */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 60px;
            width: 260px;
            height: calc(100vh - 60px);
            background-color: #0a1929;
            border-right: 1px solid rgba(64, 196, 255, 0.1);
            overflow-y: auto;
            z-index: 1000;
        }

        /* Main Content Wrapper */
        .main-content-wrapper {
            flex: 1;
            margin-left: 0;
            padding-top: 60px;
        }

        /* Desktop: Add left margin for sidebar */
        @media (min-width: 992px) {
            .main-content-wrapper {
                margin-left: 260px;
            }
        }

        /* Offcanvas for Mobile */
        .offcanvas {
            background-color: #0a1929;
            border-right: 1px solid rgba(64, 196, 255, 0.1);
        }

        .offcanvas-header {
            background-color: #0d2135;
            border-bottom: 1px solid rgba(64, 196, 255, 0.1);
            color: #40c4ff;
        }

        .offcanvas-title {
            color: #40c4ff;
            font-weight: 600;
        }

        /* Scrollbar */
        .sidebar-container::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-container::-webkit-scrollbar-track {
            background: #081521;
        }

        .sidebar-container::-webkit-scrollbar-thumb {
            background: rgba(64, 196, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar-container::-webkit-scrollbar-thumb:hover {
            background: rgba(64, 196, 255, 0.5);
        }

        /* Main content padding */
        .main-content {
            padding: 1.5rem;
        }

        @media (max-width: 991.98px) {
            .main-content {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid px-3 px-md-4">

        <!-- Left: Hamburger (mobile) + Brand -->
        <div class="d-flex align-items-center">
            <!-- Hamburger - visible on mobile and tablets -->
            <button class="btn hamburger-btn d-lg-none" 
                    type="button" 
                    data-bs-toggle="offcanvas" 
                    data-bs-target="#sidebarOffcanvas"
                    aria-controls="sidebarOffcanvas"
                    aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Brand - always visible -->
            <a class="navbar-brand d-flex align-items-center" href="/25126463/index.php">
                <i class="fas fa-bolt me-2"></i>Electronic Store
            </a>
        </div>

        <!-- Right side: Search + Cart + User/Login -->
        <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
            
            <!-- Search Form (desktop) -->
            <form class="d-none d-md-flex" action="/25126463/customer/search.php" method="GET" role="search">
                <div class="input-group">
                    <input class="form-control form-control-sm" 
                           type="search" 
                           name="q" 
                           placeholder="Search products..." 
                           aria-label="Search"
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    <button class="btn btn-sm btn-outline-cyan" type="submit" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <!-- Cart Icon -->
            <a href="/25126463/customer/cart.php" 
               class="text-white position-relative text-decoration-none"
               aria-label="Shopping cart">
                <i class="fas fa-shopping-cart fs-5"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="badge rounded-pill bg-danger">
                        <?= $cart_count ?>
                        <span class="visually-hidden">items in cart</span>
                    </span>
                <?php endif; ?>
            </a>

            <!-- User Dropdown / Login Button -->
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['full_name'])): ?>
                <div class="dropdown">
                    <a class="dropdown-toggle d-flex align-items-center gap-2 text-decoration-none" 
                       href="#"
                       role="button"
                       id="userDropdown"
                       data-bs-toggle="dropdown" 
                       aria-expanded="false">
                        <!-- Profile Picture or Initials -->
                        <?php if ($user_profile_picture): ?>
                            <img src="/25126463/uploads/profiles/<?= htmlspecialchars($user_profile_picture) ?>" 
                                 alt="Profile" 
                                 class="user-profile-pic"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="user-profile-initials" style="display:none;">
                                <?= $user_initials ?>
                            </div>
                        <?php else: ?>
                            <div class="user-profile-initials">
                                <?= $user_initials ?>
                            </div>
                        <?php endif; ?>
                        <span class="d-none d-md-inline text-white">
                            <?= htmlspecialchars(substr($_SESSION['full_name'], 0, 15)) ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="/25126463/auth/my_account.php">
                                <i class="fas fa-user me-2"></i>My Account
                            </a>
                        </li>
                        
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'buyer'): ?>
                        <li>
                            <a class="dropdown-item" href="/25126463/customer/orders.php">
                                <i class="fas fa-box me-2"></i>My Orders
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/25126463/customer/wishlist.php">
                                <i class="fas fa-heart me-2"></i>Wishlist
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/25126463/customer/cart.php">
                                <i class="fas fa-shopping-cart me-2"></i>Shopping Cart
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (function_exists('is_admin') && is_admin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-warning" href="/25126463/admin/dashboard.php">
                                    <i class="fas fa-crown me-2"></i>Admin Panel
                                </a>
                            </li>
                        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'seller'): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-info" href="/25126463/seller/dashboard.php">
                                    <i class="fas fa-store me-2"></i>Seller Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/25126463/auth/change-password.php">
                                <i class="fas fa-key me-2"></i>Change Password
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="/25126463/auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="/25126463/auth/login.php" 
                   class="btn btn-outline-cyan btn-sm">
                    <i class="fas fa-sign-in-alt me-1"></i>
                    <span class="d-none d-sm-inline">Login</span>
                </a>
                <a href="/25126463/auth/register.php" 
                   class="btn btn-cyan btn-sm d-none d-md-inline-flex">
                    <i class="fas fa-user-plus me-1"></i>
                    Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start" 
     tabindex="-1" 
     id="sidebarOffcanvas" 
     aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">
            <i class="fas fa-bolt me-2"></i>Menu
        </h5>
        <button type="button" 
                class="btn-close btn-close-white" 
                data-bs-dismiss="offcanvas" 
                aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php include __DIR__ . '/slidebar.php'; ?>
    </div>
</div>

<!-- Desktop Fixed Sidebar -->
<div class="d-none d-lg-block sidebar-container">
    <?php include __DIR__ . '/slidebar.php'; ?>
</div>

<!-- Main content wrapper (offset by sidebar on desktop) -->
<div class="main-content-wrapper">
    <main class="main-content">
        <!-- Page content goes here -->
        
        <!-- Bootstrap JS Bundle (REQUIRED FOR DROPDOWNS) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        
        <!-- Auto-dismiss alerts script -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
        </script>