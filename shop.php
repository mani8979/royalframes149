<?php
$pageTitle = 'Shop All Frames';
require_once 'includes/db.php';
include 'includes/header.php';

$searchQuery = "";
$categoryFilter = "";

// Fetch all categories from database
$categories_result = $conn->query("SELECT id, name, display_order FROM categories ORDER BY display_order ASC, id ASC");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// If no categories exist, create default ones
if (empty($categories)) {
    $conn->query("INSERT IGNORE INTO categories (name, display_order) VALUES ('Single Frames', 1), ('Combo Offers', 2)");
    $categories_result = $conn->query("SELECT id, name, display_order FROM categories ORDER BY display_order ASC, id ASC");
    $categories = [];
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$query = "SELECT * FROM products WHERE 1=1";

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $search = sanitizeInput($_GET['q']);
    // Use prepared statement for search
    $query = "SELECT * FROM products WHERE name LIKE ?";
    $searchTerm = '%' . $search . '%';
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }
    $searchQuery = htmlspecialchars($_GET['q']);
} elseif (isset($_GET['category']) && !empty($_GET['category'])) {
    $cat = sanitizeInput($_GET['category']);
    // Use prepared statement for category filter
    $query = "SELECT * FROM products WHERE category = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("s", $cat);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }
    $categoryFilter = htmlspecialchars($cat);
} else {
    $result = $conn->query($query);
}

// Fetch user's wishlist if logged in
$userWishlist = [];
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $wish_res = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $uid");
    while ($row = $wish_res->fetch_assoc()) {
        $userWishlist[] = (int)$row['product_id'];
    }
}
?>

<div class="container" style="padding: 20px 0; min-height: 60vh;">
    <div class="product-section-black">
    <div
        style="display: flex; flex-direction: column; align-items: center; margin-bottom: 40px; border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 20px;">
        <h1 style="margin: 0; color: #ffffff !important; text-align: center; margin-bottom: 20px; font-family: var(--font-heading);">
            <?php
            if ($searchQuery)
                echo 'Search Results for: "' . $searchQuery . '"';
            else if ($categoryFilter)
                echo $categoryFilter;
            else
                echo 'All Premium Frames';
            ?>
        </h1>
        <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">
            <a href="shop.php" class="btn <?php echo (!isset($_GET['category']) ? 'btn-primary' : 'btn-secondary'); ?>"
                style="padding: 10px 20px; border-radius: 25px;">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="shop.php?category=<?php echo urlencode($cat['name']); ?>"
                    class="btn <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['name'] ? 'btn-primary' : 'btn-secondary'); ?>"
                    style="padding: 10px 20px; border-radius: 25px;">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="product-grid">
            <?php while ($product = $result->fetch_assoc()): ?>
                <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                    <!-- Wishlist Icon -->
                    <div class="wishlist-icon" data-product-id="<?php echo $product['id']; ?>">
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
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 60px 0; color: var(--text-light);">
            <?php if ($categoryFilter): ?>
                <i class="fas fa-hourglass-start" style="font-size: 4rem; margin-bottom: 20px; color: var(--gold);"></i>
                <h3>Coming Soon!</h3>
                <p><?php echo htmlspecialchars($categoryFilter); ?> section is being curated and will be available shortly.</p>
            <?php else: ?>
                <i class="fas fa-box-open" style="font-size: 4rem; margin-bottom: 20px; color: #ccc;"></i>
                <h3>No frames found.</h3>
                <p>Try a different search or browse all categories.</p>
            <?php endif; ?>
            <a href="shop.php" class="btn btn-primary" style="margin-top: 20px;">Clear Filters</a>
    <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>