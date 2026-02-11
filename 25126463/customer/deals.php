<?php
/**
 * customer/deals.php - Deals & Offers Page
 * Visual redesign to match admin dashboard style (dark theme, cards, cyan accents)
 * ALL original functionality, sections, timers, progress bars, newsletter, quick actions kept intact
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/functions.php';

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$discount_filter = $_GET['discount'] ?? '';
$sort = $_GET['sort'] ?? 'discount_desc';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 12;
$offset = ($page - 1) * $records_per_page;

try {
    $where_clauses = ["p.is_active = 1", "p.stock > 0"];
    $params = [];
    
    if ($category_filter) {
        $where_clauses[] = "p.category_id = ?";
        $params[] = $category_filter;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    $count_sql = "SELECT COUNT(*) FROM PRODUCTS p WHERE $where_sql";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    $order_sql = match($sort) {
        'price_asc'     => 'p.price ASC',
        'price_desc'    => 'p.price DESC',
        'newest'        => 'p.created_at DESC',
        'discount_desc' => 'p.price DESC', // placeholder
        default         => 'p.price DESC'
    };
    
    $sql = "
        SELECT p.*, c.category_name,
               FLOOR(RAND() * 50 + 10) as discount_percentage,
               ROUND(p.price * (1 - (RAND() * 0.5 + 0.1)), 2) as deal_price
        FROM PRODUCTS p
        LEFT JOIN CATEGORY c ON p.category_id = c.category_id
        WHERE $where_sql
        ORDER BY $order_sql
        LIMIT ? OFFSET ?
    ";
    $params[] = $records_per_page;
    $params[] = $offset;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT COUNT(*) FROM PRODUCTS WHERE is_active = 1 AND stock > 0");
    $total_deals = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Deals page error: " . $e->getMessage());
    $deals = [];
    $categories = [];
    $total_deals = 0;
}

$page_title = 'Hot Deals & Offers - Electronic Store';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .deals-page {
        background: #0f172a;
        color: #f8fafc;
        padding: 2rem 0;
        min-height: 100vh;
    }

    .deals-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border: 1px solid rgba(6,182,212,0.2);
        border-radius: 16px;
        padding: 4rem 2rem;
        margin-bottom: 2.5rem;
        text-align: center;
    }

    .gradient-text {
        background: linear-gradient(90deg, #06b6d4, #0891b2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .hero-badge {
        background: rgba(239,68,68,0.15);
        color: #f87171;
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.5rem;
    }

    .hero-title { font-size: 3.2rem; font-weight: 800; margin-bottom: 1rem; }
    .hero-subtitle { font-size: 1.25rem; color: #94a3b8; margin-bottom: 2rem; }

    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 3rem;
        flex-wrap: wrap;
    }

    .stat-item {
        text-align: center;
    }

    .stat-item strong {
        font-size: 2rem;
        color: #06b6d4;
        display: block;
    }

    .deal-categories {
        margin-bottom: 2rem;
    }

    .categories-slider {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
    }

    .category-card {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        color: #cbd5e1;
        text-decoration: none;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .category-card:hover, .category-card.active {
        background: #06b6d4;
        color: white;
        transform: translateY(-3px);
    }

    .flash-sale-timer {
        margin-bottom: 2.5rem;
    }

    .timer-card {
        background: #1e293b;
        border: 1px solid rgba(239,68,68,0.3);
        border-radius: 16px;
        padding: 1.8rem;
        text-align: center;
        max-width: 500px;
        margin: 0 auto;
    }

    .timer-header {
        color: #f87171;
        margin-bottom: 1rem;
        font-size: 1.4rem;
    }

    .countdown {
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        font-size: 2.5rem;
        font-weight: 700;
        color: #f87171;
    }

    .time-unit {
        text-align: center;
    }

    .time-label {
        font-size: 0.9rem;
        color: #94a3b8;
        display: block;
        margin-top: 0.3rem;
    }

    .deals-filters {
        margin-bottom: 2rem;
    }

    .filters-bar {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .filter-select {
        background: #0f172a;
        border: 1px solid #334155;
        color: white;
        padding: 0.6rem 1rem;
        border-radius: 8px;
    }

    .deals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .deal-card {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .deal-card:hover {
        transform: translateY(-8px);
        border-color: #06b6d4;
        box-shadow: 0 15px 35px rgba(6,182,212,0.15);
    }

    .deal-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 10;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .discount-tag {
        background: #ef4444;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .stock-badge {
        background: rgba(245,158,11,0.9);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
    }

    .deal-image {
        height: 240px;
        background: #0f172a;
        position: relative;
    }

    .deal-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 20px;
        transition: transform 0.4s;
    }

    .deal-card:hover .deal-image img {
        transform: scale(1.06);
    }

    .quick-actions {
        position: absolute;
        top: 12px;
        right: 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .deal-card:hover .quick-actions {
        opacity: 1;
    }

    .action-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(30,41,59,0.9);
        color: #cbd5e1;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .action-btn:hover {
        background: #06b6d4;
        color: white;
        transform: scale(1.1);
    }

    .deal-info {
        padding: 1.25rem;
    }

    .category-tag {
        color: #64748b;
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    .deal-title {
        font-size: 1.15rem;
        margin-bottom: 0.8rem;
        font-weight: 600;
    }

    .deal-title a {
        color: #f8fafc;
        text-decoration: none;
    }

    .deal-title a:hover {
        color: #06b6d4;
    }

    .prices {
        display: flex;
        align-items: baseline;
        gap: 1rem;
        margin-bottom: 0.4rem;
    }

    .deal-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: #10b981;
    }

    .original-price {
        font-size: 1.1rem;
        color: #94a3b8;
        text-decoration: line-through;
    }

    .savings {
        color: #f59e0b;
        font-weight: 500;
    }

    .deal-progress {
        margin: 1rem 0;
    }

    .progress-bar {
        height: 8px;
        background: #334155;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: #ef4444;
        transition: width 1s ease;
    }

    .progress-text {
        font-size: 0.85rem;
        color: #f87171;
        margin-top: 0.4rem;
        display: block;
    }

    .btn-add-cart {
        background: #06b6d4;
        border: none;
        color: white;
        width: 100%;
        padding: 0.9rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s;
        margin-top: 1rem;
    }

    .btn-add-cart:hover {
        background: #0891b2;
        transform: translateY(-2px);
    }

    .newsletter-card {
        background: #1e293b;
        border: 1px solid rgba(6,182,212,0.2);
        border-radius: 16px;
        padding: 3rem 2rem;
        text-align: center;
    }

    .newsletter-icon {
        font-size: 3.5rem;
        color: #06b6d4;
        margin-bottom: 1.5rem;
    }

    .newsletter-form {
        display: flex;
        max-width: 500px;
        margin: 1.5rem auto 0;
        gap: 0.8rem;
    }

    .newsletter-form input {
        flex: 1;
        background: #0f172a;
        border: 1px solid #334155;
        color: white;
        padding: 0.8rem 1.2rem;
        border-radius: 8px;
    }

    .empty-state {
        text-align: center;
        padding: 6rem 2rem;
        color: #64748b;
    }

    .empty-state i {
        font-size: 5rem;
        opacity: 0.5;
        margin-bottom: 1.5rem;
    }
</style>

<div class="deals-page">
    
    <!-- Hero Banner -->
    <section class="deals-hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-bolt"></i>
                    <span>LIMITED TIME OFFERS</span>
                </div>
                <h1 class="hero-title">
                    <span class="gradient-text">Hot Deals</span> & Special Offers
                </h1>
                <p class="hero-subtitle">
                    Save big on your favorite electronics! Exclusive discounts up to 70% OFF
                </p>
                <div class="hero-stats">
                    <div class="stat-item">
                        <i class="fas fa-fire"></i>
                        <div>
                            <strong><?= number_format($total_deals) ?>+</strong>
                            <span>Active Deals</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>24 Hours</strong>
                            <span>Flash Sales</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-tag"></i>
                        <div>
                            <strong>Up to 70%</strong>
                            <span>Discount</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Deal Categories -->
    <section class="deal-categories">
        <div class="container">
            <div class="categories-slider">
                <a href="?category=" class="category-card <?= empty($category_filter) ? 'active' : '' ?>">
                    <i class="fas fa-fire"></i>
                    <span>All Deals</span>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= $cat['category_id'] ?>" 
                       class="category-card <?= $category_filter == $cat['category_id'] ? 'active' : '' ?>">
                        <i class="fas fa-tag"></i>
                        <span><?= htmlspecialchars($cat['category_name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Flash Sale Timer -->
    <section class="flash-sale-timer">
        <div class="container">
            <div class="timer-card">
                <div class="timer-header">
                    <i class="fas fa-bolt"></i>
                    <h3>Flash Sale Ends In:</h3>
                </div>
                <div class="countdown" id="countdown">
                    <div class="time-unit">
                        <span class="time-value" id="hours">00</span>
                        <span class="time-label">Hours</span>
                    </div>
                    <div class="time-separator">:</div>
                    <div class="time-unit">
                        <span class="time-value" id="minutes">00</span>
                        <span class="time-label">Minutes</span>
                    </div>
                    <div class="time-separator">:</div>
                    <div class="time-unit">
                        <span class="time-value" id="seconds">00</span>
                        <span class="time-label">Seconds</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters & Sort -->
    <section class="deals-filters">
        <div class="container">
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Sort By:</label>
                    <select id="sort-select" class="filter-select">
                        <option value="discount_desc" <?= $sort == 'discount_desc' ? 'selected' : '' ?>>Highest Discount</option>
                        <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                    </select>
                </div>
                
                <div class="results-count">
                    <i class="fas fa-tag"></i>
                    <span><?= number_format($total_records) ?> Deals Available</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Deals Grid -->
    <section class="deals-grid-section">
        <div class="container">
            <?php if (empty($deals)): ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h3>No Deals Available</h3>
                    <p>Check back soon for amazing offers!</p>
                    <a href="/25126463/index.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag me-2"></i>Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="deals-grid">
                    <?php foreach ($deals as $deal): ?>
                        <div class="deal-card">
                            <!-- Deal Badge -->
                            <div class="deal-badge">
                                <span class="discount-tag">
                                    <i class="fas fa-bolt"></i>
                                    <?= $deal['discount_percentage'] ?>% OFF
                                </span>
                                <?php if ($deal['stock'] < 10): ?>
                                    <span class="stock-badge low-stock">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Only <?= $deal['stock'] ?> left!
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Product Image -->
                            <div class="deal-image">
                                <a href="/25126463/customer/product.php?id=<?= $deal['product_id'] ?>">
                                    <?php if (!empty($deal['product_image'])): ?>
                                        <img src="/25126463/uploads/products/<?= htmlspecialchars($deal['product_image']) ?>" 
                                             alt="<?= htmlspecialchars($deal['product_name']) ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                
                                <!-- Quick Actions -->
                                <div class="quick-actions">
                                    <button class="action-btn wishlist-btn" 
                                            title="Add to Wishlist"
                                            data-product-id="<?= $deal['product_id'] ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <a href="/25126463/customer/product.php?id=<?= $deal['product_id'] ?>" 
                                       class="action-btn view-btn"
                                       title="Quick View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Deal Info -->
                            <div class="deal-info">
                                <div class="category-tag">
                                    <i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($deal['category_name'] ?? 'Uncategorized') ?>
                                </div>
                                
                                <h3 class="deal-title">
                                    <a href="/25126463/customer/product.php?id=<?= $deal['product_id'] ?>">
                                        <?= htmlspecialchars($deal['product_name']) ?>
                                    </a>
                                </h3>
                                
                                <div class="price-section">
                                    <div class="prices">
                                        <span class="deal-price">Rs. <?= number_format($deal['deal_price'], 2) ?></span>
                                        <span class="original-price">Rs. <?= number_format($deal['price'], 2) ?></span>
                                    </div>
                                    <div class="savings">
                                        Save Rs. <?= number_format($deal['price'] - $deal['deal_price'], 2) ?>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <?php 
                                $sold_percentage = rand(40, 85); // keep your simulation
                                ?>
                                <div class="deal-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $sold_percentage ?>%"></div>
                                    </div>
                                    <span class="progress-text">
                                        <i class="fas fa-fire"></i>
                                        <?= $sold_percentage ?>% Claimed
                                    </span>
                                </div>

                                <!-- Add to Cart Button -->
                                <button class="btn-add-cart" 
                                        data-product-id="<?= $deal['product_id'] ?>"
                                        data-product-name="<?= htmlspecialchars($deal['product_name']) ?>">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>Add to Cart</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pagination (unchanged structure) -->
    <?php if ($total_pages > 1): ?>
        <section class="pagination-section mt-5">
            <div class="container">
                <nav class="pagination-nav">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&category=<?= $category_filter ?>&sort=<?= $sort ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&category=<?= $category_filter ?>&sort=<?= $sort ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&category=<?= $category_filter ?>&sort=<?= $sort ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </section>
    <?php endif; ?>

    <!-- Newsletter Section -->
    <section class="deals-newsletter mt-5">
        <div class="container">
            <div class="newsletter-card">
                <div class="newsletter-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="newsletter-content">
                    <h3>Never Miss a Deal!</h3>
                    <p>Subscribe to get exclusive offers and be the first to know about flash sales</p>
                </div>
                <form class="newsletter-form" id="newsletter-form">
                    <input type="email" placeholder="Enter your email" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bell"></i>
                        Subscribe
                    </button>
                </form>
            </div>
        </div>
    </section>

</div>

<script src="/25126463/js/deals.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>