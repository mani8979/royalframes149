<?php
$pageTitle = 'My Wishlist';
require_once 'includes/db.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=wishlist");
    exit();
}

include 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch wishlist products
$query = "SELECT p.* FROM products p 
          JOIN wishlist w ON p.id = w.product_id 
          WHERE w.user_id = ? 
          ORDER BY w.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get wishlist IDs for the heart icon state (sanity check, should be all products here)
$userWishlist = [];
$wish_res = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $user_id");
while ($row = $wish_res->fetch_assoc()) {
    $userWishlist[] = (int)$row['product_id'];
}
?>

<div class="container" style="padding: 20px 16px; min-height: 60vh; max-width: 1200px; margin: 0 auto;">
    <div class="product-section-black">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 20px; font-weight: 600; margin: 0; color: #ffffff !important; text-align: center;"><i class="fas fa-heart" style="color: #ff4343; margin-right: 10px;"></i> My Wishlist</h1>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="wishlist-page-grid" id="wishlist-grid">
            <?php while ($product = $result->fetch_assoc()): ?>
                <div class="wishlist-card" data-product-id="<?php echo $product['id']; ?>">
                    <div class="wishlist-icon" data-product-id="<?php echo $product['id']; ?>" style="position: absolute; top: 10px; right: 10px; z-index: 5; background: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); cursor: pointer;" title="Remove">
                        <i class="fas fa-heart" style="color: #ff4343;"></i>
                    </div>
                    
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="wishlist-img-box">
                        <?php if (strtolower(pathinfo($product['image'], PATHINFO_EXTENSION)) === 'mp4'): ?>
                            <video src="<?php echo htmlspecialchars($product['image']); ?>" muted loop preload="metadata"></video>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                        <?php endif; ?>
                    </a>

                    <div class="wishlist-info" style="display: flex; flex-direction: column; flex: 1;">
                        <h3 class="wishlist-title">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h3>
                        <div class="wishlist-price-row">
                            <span class="wishlist-final-price">₹<?php echo number_format($product['price'], 2); ?></span>
                            <span class="wishlist-old-price">₹<?php echo number_format($product['price'] * 1.38, 0); ?></span>
                        </div>
                        <div style="margin-top: auto;">
                            <form action="cart" method="POST" style="width: 100%;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" name="action" value="add" class="wishlist-btn-cart btn-tap">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 100px 0; color: var(--text-light);">
            <i class="far fa-heart" style="font-size: 5rem; margin-bottom: 20px; color: #eee; border: 4px dashed #eee; padding: 30px; border-radius: 50%;"></i>
            <h3>Your wishlist is empty</h3>
            <p>Explore our premium frames and click the heart icon to save them here!</p>
            <a href="shop.php" class="btn btn-primary" style="margin-top: 30px; border-radius: 30px; padding: 12px 30px;">Go Shopping</a>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
// Specialized logic for Wishlist page to remove items from DOM
document.addEventListener('DOMContentLoaded', function() {
    const wishlistGrid = document.getElementById('wishlist-grid');
    if (!wishlistGrid) return;

    wishlistGrid.addEventListener('click', function(e) {
        const icon = e.target.closest('.wishlist-icon');
        if (icon) {
            const card = icon.closest('.wishlist-card');
            // Wait a small bit to see if the main script.js handled the remove
            setTimeout(() => {
                const heart = icon.querySelector('i');
                if (heart && heart.classList.contains('far')) {
                    // Item was removed via API (handled in script.js)
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        card.remove();
                        // Check if empty
                        if (wishlistGrid.children.length === 0) {
                            location.reload(); // Show empty state
                        }
                    }, 400);
                }
            }, 100);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
