<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Helper function to check if link is active
if (!function_exists('is_active')) {
    function is_active($page) {
        global $current_page;
        return $current_page === $page ? 'active' : '';
    }
}

// Get user role
$user_role = $_SESSION['role'] ?? 'buyer';
$is_logged_in = isset($_SESSION['user_id']);

// Profile picture support
$user_profile_picture = $_SESSION['profile_picture'] ?? null;
$user_initials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 1)) : 'U';

// Get categories for dropdown (only for buyers)
$categories = [];
if ($user_role === 'buyer') {
    try {
        $categories_stmt = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name ASC");
        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $categories = [];
    }
}
?>

<!-- Sidebar Styles -->
<style>
/* Sidebar Container */
.sidebar-nav {
    width: 100%;
    background: linear-gradient(180deg, #0a1929 0%, #132f4c 100%);
    min-height: 100vh;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-nav .nav {
    padding: 0;
    margin: 0;
}

/* Section Title */
.sidebar-section-title {
    padding: 1.25rem 1.5rem 0.75rem;
    color: #40c4ff;
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-section-title i {
    font-size: 0.9rem;
}

/* Navigation Links */
.sidebar-nav .nav-link {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.5rem;
    color: #b0bec5;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-left: 3px solid transparent;
    font-size: 0.9rem;
    font-weight: 500;
    position: relative;
}

.sidebar-nav .nav-link i {
    width: 20px;
    margin-right: 14px;
    font-size: 1.1rem;
    text-align: center;
    transition: transform 0.3s ease;
}

.sidebar-nav .nav-link:hover {
    background: linear-gradient(90deg, rgba(64, 196, 255, 0.15) 0%, rgba(64, 196, 255, 0.05) 100%);
    color: #40c4ff;
    border-left-color: #40c4ff;
    padding-left: 1.75rem;
}

.sidebar-nav .nav-link:hover i {
    transform: translateX(3px);
}

.sidebar-nav .nav-link.active {
    background: linear-gradient(90deg, rgba(64, 196, 255, 0.2) 0%, rgba(64, 196, 255, 0.08) 100%);
    color: #40c4ff;
    border-left-color: #40c4ff;
    font-weight: 600;
    box-shadow: inset 0 1px 3px rgba(64, 196, 255, 0.3);
}

.sidebar-nav .nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: #40c4ff;
    box-shadow: 0 0 10px rgba(64, 196, 255, 0.5);
}

.sidebar-nav .nav-link.text-danger:hover {
    background: linear-gradient(90deg, rgba(244, 67, 54, 0.15) 0%, rgba(244, 67, 54, 0.05) 100%);
    color: #f44336;
    border-left-color: #f44336;
}

/* Dropdown */
.sidebar-nav .dropdown {
    position: relative;
}

.sidebar-nav .dropdown-toggle {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.875rem 1.5rem;
    background: transparent;
    border: none;
    border-left: 3px solid transparent;
    color: #b0bec5;
    text-align: left;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
}

.sidebar-nav .dropdown-toggle::after {
    display: none;
}

.sidebar-nav .dropdown-toggle i:first-child {
    width: 20px;
    margin-right: 14px;
    font-size: 1.1rem;
    text-align: center;
}

.sidebar-nav .dropdown-toggle .fa-chevron-down {
    margin-left: auto;
    font-size: 0.7rem;
    transition: transform 0.3s ease;
}

.sidebar-nav .dropdown-toggle[aria-expanded="true"] .fa-chevron-down {
    transform: rotate(180deg);
}

.sidebar-nav .dropdown-toggle:hover {
    background: linear-gradient(90deg, rgba(64, 196, 255, 0.15) 0%, rgba(64, 196, 255, 0.05) 100%);
    color: #40c4ff;
    border-left-color: #40c4ff;
    padding-left: 1.75rem;
}

/* Dropdown Menu */
.sidebar-nav .dropdown-menu {
    position: static !important;
    transform: none !important;
    background: rgba(8, 21, 33, 0.6);
    backdrop-filter: blur(10px);
    border: none;
    border-radius: 0;
    width: 100%;
    margin: 0;
    padding: 0.5rem 0;
    display: none;
    box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.2);
}

.sidebar-nav .dropdown-menu.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}

.sidebar-nav .dropdown-item {
    padding: 0.625rem 1.5rem 0.625rem 3.25rem;
    color: #90a4ae;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    position: relative;
}

.sidebar-nav .dropdown-item::before {
    content: '›';
    position: absolute;
    left: 2.5rem;
    color: #546e7a;
    font-weight: bold;
    transition: all 0.2s ease;
}

.sidebar-nav .dropdown-item:hover {
    background: linear-gradient(90deg, rgba(64, 196, 255, 0.1) 0%, transparent 100%);
    color: #40c4ff;
    padding-left: 3.5rem;
}

.sidebar-nav .dropdown-item:hover::before {
    color: #40c4ff;
    left: 2.75rem;
}

.sidebar-nav .dropdown-divider {
    border-color: rgba(255, 255, 255, 0.1);
    margin: 0.5rem 1rem;
}

/* Divider */
.sidebar-divider {
    border-color: rgba(255, 255, 255, 0.15);
    margin: 1rem 1.5rem;
    box-shadow: 0 1px 0 rgba(255, 255, 255, 0.05);
}

/* Badge */
.sidebar-nav .badge {
    font-size: 0.65rem;
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
    font-weight: 600;
    margin-left: auto;
}

/* Profile Section at Top */
.sidebar-profile {
    padding: 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 1rem;
}

.sidebar-profile-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-profile-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sidebar-profile-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(64, 196, 255, 0.5);
}

.sidebar-profile-initials {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
    box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
}

.sidebar-profile-details h6 {
    margin: 0;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 600;
}

.sidebar-profile-details p {
    margin: 0;
    color: #90a4ae;
    font-size: 0.75rem;
}

/* Mobile Search */
.mobile-search-form {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.mobile-search-form .input-group {
    height: 42px;
}

.mobile-search-form .form-control {
    background: rgba(8, 21, 33, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #b0bec5;
    font-size: 0.9rem;
}

.mobile-search-form .form-control:focus {
    border-color: #40c4ff;
    background: rgba(8, 21, 33, 0.8);
    box-shadow: 0 0 0 0.2rem rgba(64, 196, 255, 0.25);
    color: #fff;
}

.mobile-search-form .btn {
    background: #40c4ff;
    border-color: #40c4ff;
    color: #0a1929;
}

.mobile-search-form .btn:hover {
    background: #29b6f6;
    border-color: #29b6f6;
}

/* Scrollbar Styling */
.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(64, 196, 255, 0.3);
    border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(64, 196, 255, 0.5);
}
</style>

<nav class="sidebar-nav">
    <div class="nav flex-column">
        
        <?php if ($is_logged_in): ?>
        <!-- Profile Section -->
        <div class="sidebar-profile">
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-avatar">
                    <?php if ($user_profile_picture): ?>
                        <img src="/25126463/uploads/profiles/<?= htmlspecialchars($user_profile_picture) ?>" 
                             alt="Profile"
                             onerror="this.style.display='none'; this.parentElement.querySelector('.sidebar-profile-initials').style.display='flex';">
                        <div class="sidebar-profile-initials" style="display:none;">
                            <?= $user_initials ?>
                        </div>
                    <?php else: ?>
                        <div class="sidebar-profile-initials">
                            <?= $user_initials ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sidebar-profile-details">
                    <h6><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></h6>
                    <p><?= ucfirst($user_role) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user_role === 'admin'): ?>
            <!-- ========== ADMIN MENU ========== -->
            <div class="sidebar-section-title">
                <i class="fas fa-crown"></i> ADMIN PANEL
            </div>
            
            <a href="/25126463/admin/dashboard.php" class="nav-link <?= is_active('dashboard.php') ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="/25126463/admin/manage_categories.php" class="nav-link <?= is_active('manage_categories.php') ?>">
                <i class="fas fa-tags"></i>
                <span>Manage Categories</span>
            </a>
            
            <a href="/25126463/admin/manage_products.php" class="nav-link <?= is_active('manage_products.php') ?>">
                <i class="fas fa-box"></i>
                <span>Manage Products</span>
            </a>
            
            <a href="/25126463/admin/manage_orders.php" class="nav-link <?= is_active('manage_orders.php') ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Manage Orders</span>
            </a>
            
            <a href="/25126463/admin/manage_users.php" class="nav-link <?= is_active('manage_users.php') ?>">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            
            <a href="/25126463/admin/settings.php" class="nav-link <?= is_active('settings.php') ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-section-title">
                <i class="fas fa-user"></i> ACCOUNT
            </div>
            
            <a href="/25126463/auth/my_account.php" class="nav-link <?= is_active('my_account.php') ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            
            <a href="/25126463/auth/change-password.php" class="nav-link <?= is_active('change-password.php') ?>">
                <i class="fas fa-key"></i>
                <span>Change Password</span>
            </a>
            
            <a href="/25126463/auth/logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        <?php elseif ($user_role === 'seller'): ?>
            <!-- ========== SELLER MENU ========== -->
            <div class="sidebar-section-title">
                <i class="fas fa-store"></i> SELLER PANEL
            </div>
            
            <a href="/25126463/seller/dashboard.php" class="nav-link <?= is_active('dashboard.php') ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="/25126463/seller/products.php" class="nav-link <?= is_active('products.php') ?>">
                <i class="fas fa-box-open"></i>
                <span>My Products</span>
            </a>
            
            <a href="/25126463/seller/add_products.php" class="nav-link <?= is_active('add_products.php') ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Product</span>
            </a>
            
            <a href="/25126463/seller/orders.php" class="nav-link <?= is_active('orders.php') ?>">
                <i class="fas fa-shipping-fast"></i>
                <span>Orders</span>
            </a>
            
            <a href="/25126463/seller/analytics.php" class="nav-link <?= is_active('analytics.php') ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-section-title">
                <i class="fas fa-user"></i> ACCOUNT
            </div>
            
            <a href="/25126463/auth/my_account.php" class="nav-link <?= is_active('my_account.php') ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            
            <a href="/25126463/auth/change-password.php" class="nav-link <?= is_active('change-password.php') ?>">
                <i class="fas fa-key"></i>
                <span>Change Password</span>
            </a>
            
            <a href="/25126463/auth/logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        <?php else: ?>
            <!-- ========== BUYER/CUSTOMER MENU ========== -->
            
            <a href="/25126463/index.php" class="nav-link <?= is_active('index.php') ?>">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>

            <?php if (!empty($categories)): ?>
            <div class="dropdown">
                <a class="dropdown-toggle" 
                   href="#"
                   role="button"
                   id="categoriesDropdown"
                   data-bs-toggle="dropdown" 
                   aria-expanded="false">
                    <i class="fas fa-th-large"></i>
                    <span>Categories</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="dropdown-menu" aria-labelledby="categoriesDropdown">
                    <li>
                        <a class="dropdown-item" href="/25126463/customer/category.php">
                            <i class="fas fa-border-all me-2"></i>All Categories
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php foreach ($categories as $category): ?>
                    <li>
                        <a class="dropdown-item" 
                           href="/25126463/customer/category.php?category=<?= $category['category_id'] ?>">
                            <?= htmlspecialchars($category['category_name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <a href="/25126463/customer/category.php" class="nav-link <?= is_active('category.php') ?>">
                <i class="fas fa-shopping-bag"></i>
                <span>All Products</span>
            </a>

            <a href="/25126463/customer/deals.php" class="nav-link <?= is_active('deals.php') ?>">
                <i class="fas fa-tag"></i>
                <span>Deals & Offers</span>
            </a>

            <hr class="sidebar-divider">

            <div class="sidebar-section-title">
                <i class="fas fa-user"></i> ACCOUNT
            </div>

            <?php if ($is_logged_in): ?>
                <a href="/25126463/auth/my_account.php" class="nav-link <?= is_active('my_account.php') ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>My Profile</span>
                </a>

                <a href="/25126463/customer/orders.php" class="nav-link <?= is_active('orders.php') ?>">
                    <i class="fas fa-box"></i>
                    <span>My Orders</span>
                </a>

                <a href="/25126463/customer/wishlist.php" class="nav-link <?= is_active('wishlist.php') ?>">
                    <i class="fas fa-heart"></i>
                    <span>Wishlist</span>
                </a>

                <a href="/25126463/customer/cart.php" class="nav-link <?= is_active('cart.php') ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Shopping Cart</span>
                    <?php if (isset($cart_count) && $cart_count > 0): ?>
                        <span class="badge bg-danger"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>

                <hr class="sidebar-divider">

                <a href="/25126463/auth/change-password.php" class="nav-link <?= is_active('change-password.php') ?>">
                    <i class="fas fa-key"></i>
                    <span>Change Password</span>
                </a>

                <a href="/25126463/auth/logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>

            <?php else: ?>
                <a href="/25126463/auth/login.php" class="nav-link <?= is_active('login.php') ?>">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>

                <a href="/25126463/auth/register.php" class="nav-link <?= is_active('register.php') ?>">
                    <i class="fas fa-user-plus"></i>
                    <span>Register</span>
                </a>

                <hr class="sidebar-divider">

                <div class="text-center px-3 py-3">
                    <div class="alert alert-info" style="font-size: 0.85rem; margin: 0;">
                        <i class="fas fa-info-circle me-1"></i>
                        Login to access more features
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</nav>

<!-- Mobile Search Script -->
<?php if ($user_role === 'buyer'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const offcanvasBody = document.querySelector('#sidebarOffcanvas .offcanvas-body');
    if (offcanvasBody && window.innerWidth < 768) {
        const navElement = offcanvasBody.querySelector('.sidebar-nav');
        
        if (navElement && !document.querySelector('.mobile-search-form')) {
            const searchForm = document.createElement('form');
            searchForm.className = 'mobile-search-form';
            searchForm.action = '/25126463/customer/search.php';
            searchForm.method = 'GET';
            searchForm.innerHTML = `
                <div class="input-group">
                    <input type="search" 
                           name="q" 
                           class="form-control" 
                           placeholder="Search products..."
                           required>
                    <button class="btn" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            `;
            offcanvasBody.insertBefore(searchForm, navElement);
        }
    }
});
</script>
<?php endif; ?>