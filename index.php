<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$pageTitle = 'Home';
require_once 'includes/db.php';
if (isset($db_connection_error) && $db_connection_error) {
    http_response_code(500);
    echo "<h1>Database connection error</h1>";
    echo "<pre>" . htmlspecialchars($conn->connect_error ?? 'unknown') . "</pre>";
    exit;
}

include 'includes/header.php';

// Fetch all categories
$categories_result = $conn->query("SELECT * FROM categories ORDER BY display_order ASC");
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    error_log('Index categories query failed: ' . $conn->error);
}

// Fetch Instagram photos
$insta_query = "SELECT image, link FROM instagram_photos ORDER BY created_date DESC LIMIT 6";
$insta_result = $conn->query($insta_query);

// Fetch user's wishlist if logged in
$userWishlist = [];
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $wish_res = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $uid");
    if ($wish_res) {
        while ($row = $wish_res->fetch_assoc()) {
            $userWishlist[] = (int)$row['product_id'];
        }
    }
}
?>



<!-- Products shown directly as requested -->



<!-- Dynamic Product Categories Section -->
<?php
// Fetch all categories from database based on what products actually have
$all_categories = $conn->query("SELECT DISTINCT category as name FROM products WHERE category != '' ORDER BY category ASC");
$categories_to_display = [];
if ($all_categories) {
    while ($cat = $all_categories->fetch_assoc()) {
        $categories_to_display[] = $cat['name'];
    }
} else {
    error_log('Index categories list query failed: ' . $conn->error);
}

foreach ($categories_to_display as $cat_name):
    // Fetch products for this category
    $cat_safe = $conn->real_escape_string($cat_name);
    $products_query = "SELECT id, name, price, mrp, image FROM products WHERE category = '$cat_safe' ORDER BY id DESC LIMIT 12";
    $products_result = $conn->query($products_query);

    if (!$products_result) {
        echo "<!-- Query error for category $cat_name: " . $conn->error . " -->";
        continue;
    }

    $products_data = [];
    while ($row = $products_result->fetch_assoc()) {
        $products_data[] = $row;
    }

    // Show section for all categories (even if no products yet)
    if (true):
        ?>
        <section class="container" style="padding: 20px 0;">
            <div class="product-section-black animate-fade-up">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                <h2 class="section-title"><?php echo htmlspecialchars($cat_name); ?></h2>
            </div>

            <?php if (!empty($products_data)): ?>
                <div class="product-grid">
                    <?php
                    $delay_count = 1;
                    foreach ($products_data as $product):
                        ?>
                        <div class="product-card animate-fade-up delay-<?php echo min($delay_count++, 6); ?>" data-product-id="<?php echo $product['id']; ?>">
                            <!-- Wishlist Icon -->
                            <div class="wishlist-icon">
                                <i class="<?php echo in_array($product['id'], $userWishlist) ? 'fas' : 'far'; ?> fa-heart"
                                   style="<?php echo in_array($product['id'], $userWishlist) ? 'color: #ff4343;' : ''; ?>"></i>
                            </div>
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="product-image-container">
                                <!-- Discount Badge -->
                                <?php if (!empty($product['mrp']) && $product['mrp'] > $product['price']): 
                                    $discount_percent = round((($product['mrp'] - $product['price']) / $product['mrp']) * 100);
                                ?>
                                    <div class="discount-badge">
                                        <?php echo $discount_percent; ?>% OFF
                                    </div>
                                <?php endif; ?>
                                <?php if (strtolower(pathinfo($product['image'], PATHINFO_EXTENSION)) === 'mp4'): ?>
                                    <video src="<?php echo htmlspecialchars($product['image']); ?>"
                                        class="product-video" muted loop onmouseover="this.play()"
                                        onmouseout="this.pause()" preload="metadata"></video>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                        alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy" class="product-img">
                                <?php endif; ?>
                            </a>
                            <div class="product-info">
                                <div class="product-brand">Royal Assured <i class="fas fa-check-circle"></i></div>
                                <h3 class="product-title">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </h3>
                                <div class="product-rating">
                                    <span class="rating-badge">
                                        4.<?php echo rand(2, 8); ?> <i class="fas fa-star"></i>
                                    </span>
                                    <span class="rating-count">(<?php echo rand(120, 999); ?>)</span>
                                </div>
                                <div class="product-price-row">
                                    <span class="price-current">₹<?php echo number_format($product['price'], 2); ?></span>
                                    <?php if (!empty($product['mrp']) && $product['mrp'] > $product['price']): 
                                        $discount_percent = round((($product['mrp'] - $product['price']) / $product['mrp']) * 100);
                                    ?>
                                        <del class="price-old">₹<?php echo number_format($product['mrp'], 0); ?></del>
                                        <span class="price-discount"><?php echo $discount_percent; ?>% off</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-actions-row">
                                    <form action="cart.php" method="POST" style="width: 100%;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <div class="action-buttons">
                                            <button type="submit" name="action" value="add" class="btn btn-add-cart"><i class="fas fa-shopping-cart"></i> Add</button>
                                            <button type="submit" name="action" value="buy_now" class="btn btn-buy-now">Buy Now</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="shop.php?category=<?php echo urlencode($cat_name); ?>" class="btn btn-secondary">View All
                        <?php echo htmlspecialchars($cat_name); ?></a>
                </div>
            <?php else: ?>
                <div
                    style="text-align: center; padding: 60px 20px; background: linear-gradient(135deg, rgba(212,175,55,0.05), rgba(212,175,55,0.05)); border-radius: 8px; color: var(--text-light);">
                    <i class="fas fa-hourglass-start" style="font-size: 3rem; margin-bottom: 20px; color: var(--accent);"></i>
                    <h3 style="color: var(--text); margin-bottom: 10px;">Coming Soon!</h3>
                    <p><?php echo htmlspecialchars($cat_name); ?> products are being curated and will be available shortly.</p>
                </div>
            <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Delivery Information -->
<section class="container" style="padding: 60px 0; text-align: center;">
    <h2 class="section-title animate-fade-up">Fast & Secure Delivery</h2>
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-top: 40px;">
        <div class="animate-slide-left delay-1"
            style="padding: 30px; background: var(--card-bg); border-radius: 8px; box-shadow: var(--shadow-sm);">
            <i class="fas fa-truck" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
            <h3>Andhra Pradesh & Telangana</h3>
            <p style="color: var(--text-light); margin-top: 10px;">Standard Delivery: ₹180 / ₹250. Expected delivery
                within 2-4 business days.</p>
        </div>
        <div class="animate-fade-up delay-2"
            style="padding: 30px; background: var(--card-bg); border-radius: 8px; box-shadow: var(--shadow-sm);">
            <i class="fas fa-shipping-fast" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
            <h3>Tamil Nadu</h3>
            <p style="color: var(--text-light); margin-top: 10px;">Delivery Charge: ₹280. Expected delivery within 3-5
                business days.</p>
        </div>
        <div class="animate-slide-right delay-3"
            style="padding: 30px; background: var(--card-bg); border-radius: 8px; box-shadow: var(--shadow-sm);">
            <i class="fas fa-globe-asia" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
            <h3>Other States</h3>
            <p style="color: var(--text-light); margin-top: 10px;">Delivery Charge: ₹400. Expected delivery within 5-7
                business days across India.</p>
        </div>
    </div>
</section>

<!-- Instagram Section -->
<section style="background-color: var(--card-bg); color: var(--text); padding: 60px 0;">
    <div class="container text-center" style="text-align: center;">
        <h2 style="color: var(--accent); font-family: var(--font-heading); font-size: 2.5rem; margin-bottom: 15px;">
            Follow Us on Instagram</h2>
        <p style="color: var(--text-light); margin-bottom: 30px;">@royal_frames149</p>

        <div class="instagram-grid">
            <?php
            $delay_count = 1;

            if ($insta_result && $insta_result->num_rows > 0) {
                while ($photo = $insta_result->fetch_assoc()) {
                    $image_path = htmlspecialchars($photo['image']);
                    // Add error fallback for missing images
                    ?>
                    <?php
                    $reel_url = !empty($photo['link']) ? htmlspecialchars($photo['link']) : "https://www.instagram.com/royalframe149";
                    ?>
                    <a href="<?php echo $reel_url; ?>" target="_blank"
                        class="insta-item animate-scale-in delay-<?php echo min($delay_count++, 6); ?>">
                        <img src="<?php echo $image_path; ?>" alt="Instagram Photo" loading="lazy"
                            onerror="this.src='images/frames/6x9.jpg';" style="width: 100%; height: 250px; object-fit: cover;">
                        <div class="insta-overlay"><i class="fab fa-instagram"></i></div>
                    </a>
                    <?php
                }
            } else {
                // Default images if no photos uploaded
                $default_images = [
                    'images/frames/6x9.jpg',
                    'images/combo/combo1.jpg',
                    'images/frames/16x20.jpg',
                    'images/combo/combo3.jpg',
                    'images/frames/24x36.jpg',
                    'images/combo/combo4.jpg'
                ];
                foreach ($default_images as $image) {
                    ?>
                    <a href="https://www.instagram.com/royalframe149" target="_blank"
                        class="insta-item animate-scale-in delay-<?php echo min($delay_count++, 6); ?>">
                        <img src="<?php echo $image; ?>" alt="Instagram Photo" loading="lazy"
                            style="width: 100%; height: 250px; object-fit: cover;">
                        <div class="insta-overlay"><i class="fab fa-instagram"></i></div>
                    </a>
                    <?php
                }
            }
            ?>
        </div>

        <a href="https://www.instagram.com/royalframe149" target="_blank" class="btn btn-primary"
            style="margin-top: 40px; background: var(--accent); color: var(--primary); font-weight: bold;">View More on
            Instagram <i class="fab fa-instagram" style="margin-left: 5px;"></i></a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>