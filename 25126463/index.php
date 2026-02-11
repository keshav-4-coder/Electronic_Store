<?php
// index.php - Clean Professional Electronic Store
require_once 'includes/functions.php';
require_once 'config/dbconfig.php';

$page_title = "Electronic Store - Quality Gadgets & Electronics";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-color: #334155;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* Hero Section - Subtle & Clean */
        .hero {
            padding: 100px 0;
            background: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.8)), 
                        url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=2000') center/cover;
            border-bottom: 1px solid var(--border-color);
        }

        .hero h1 {
            font-weight: 800;
            letter-spacing: -1px;
            color: #fff;
        }

        /* Product Cards */
        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-blue);
        }

        .product-img {
            height: 220px;
            object-fit: cover;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            background-color: #000;
            cursor: pointer;
        }

        .price-tag {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent-blue);
        }

        .btn-cart {
            background-color: var(--accent-blue);
            color: #0f172a;
            font-weight: 600;
            border: none;
            transition: opacity 0.2s;
        }

        .btn-cart:hover {
            opacity: 0.9;
            color: #000;
        }

        /* Features Section */
        .feature-box {
            padding: 30px;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            height: 100%;
        }

        .feature-box i {
            font-size: 2rem;
            color: var(--accent-blue);
            margin-bottom: 15px;
        }

        /* Category Icons */
        .cat-pill {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-block;
            text-decoration: none;
            color: var(--text-main);
            margin: 5px;
            transition: all 0.2s;
        }

        .cat-pill:hover {
            background: var(--accent-blue);
            color: var(--bg-dark);
        }

        .section-label {
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--accent-blue);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .product-title-link {
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        .product-title-link:hover {
            color: var(--accent-blue);
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="hero text-center">
    <div class="container">
        <span class="section-label mb-2 d-block">Premium Experience</span>
        <h1 class="display-4 mb-3">Upgrade Your Digital Lifestyle</h1>
        <p class="lead text-muted mb-4 mx-auto" style="max-width: 600px;">
            Shop the latest high-performance tech from global brands with guaranteed local warranty and support.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="customer/category.php" class="btn btn-primary btn-lg px-4">Shop Collection</a>
            <a href="#featured" class="btn btn-outline-light btn-lg px-4">Latest Deals</a>
        </div>
    </div>
</section>

<section class="py-5 border-bottom border-secondary border-opacity-25">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="feature-box">
                    <i class="fas fa-truck-fast"></i>
                    <h6>Free Shipping</h6>
                    <p class="small text-muted mb-0">On orders over Rs. 5000</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-box">
                    <i class="fas fa-shield-heart"></i>
                    <h6>Brand Warranty</h6>
                    <p class="small text-muted mb-0">100% Authentic Products</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-box">
                    <i class="fas fa-rotate"></i>
                    <h6>7-Day Return</h6>
                    <p class="small text-muted mb-0">Hassle-free exchanges</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-box">
                    <i class="fas fa-headset"></i>
                    <h6>Expert Support</h6>
                    <p class="small text-muted mb-0">Lifetime tech assistance</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" id="featured">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="section-label">Top Rated</span>
                <h2 class="mb-0">Featured Products</h2>
            </div>
            <a href="customer/category.php" class="text-decoration-none text-info">View All Products →</a>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php
            try {
                $stmt = $conn->prepare("SELECT p.*, c.category_name FROM PRODUCTS p 
                                        LEFT JOIN CATEGORY c ON p.category_id = c.category_id 
                                        WHERE p.is_active = 1 ORDER BY p.created_at DESC LIMIT 8");
                $stmt->execute();
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($products as $product) {
                    $image = (!empty($product['product_image']) && strpos($product['product_image'], 'http') === 0) 
                             ? $product['product_image'] 
                             : "uploads/products/" . ($product['product_image'] ?: 'default.jpg');
            ?>
            <div class="col">
                <div class="card product-card h-100">
                    <!-- Clickable Product Image -->
                    <a href="customer/product.php?id=<?php echo $product['product_id']; ?>">
                        <img src="<?php echo $image; ?>" class="product-img" alt="Product">
                    </a>
                    
                    <div class="card-body d-flex flex-column">
                        <small class="text-info"><?php echo htmlspecialchars($product['category_name']); ?></small>
                        
                        <!-- Clickable Product Title -->
                        <a href="customer/product.php?id=<?php echo $product['product_id']; ?>" class="product-title-link">
                            <h6 class="card-title mb-3 mt-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                        </a>
                        
                        <div class="mt-auto">
                            <div class="price-tag mb-3">Rs. <?php echo number_format($product['price']); ?></div>
                            <form action="customer/cart.php" method="post">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <button type="submit" name="add_to_cart" class="btn btn-cart w-100" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo $product['stock'] > 0 ? '<i class="fas fa-cart-plus me-2"></i>Add to Cart' : 'Sold Out'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php } 
            } catch (Exception $e) { echo "<p>Error loading items.</p>"; } ?>
        </div>
    </div>
</section>


<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>