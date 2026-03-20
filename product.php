<?php
require_once 'includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$product_id = (int) $_GET['id'];
// Use prepared statement for product fetch
$query = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    header("Location: shop.php");
    exit();
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: shop.php");
    exit();
}

$product = $result->fetch_assoc();
$pageTitle = $product['name'];
$stmt->close();

// Handle Review Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        $review_msg = "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;'>Please login to post a review.</div>";
    } else {
        $user_id = $_SESSION['user_id'];
        $user_name = $conn->real_escape_string(trim($_POST['user_name']));
        $rating = (int) $_POST['rating'];
        $comment = $conn->real_escape_string(trim($_POST['comment']));
        $image_path = '';

        // Verify Delivered Order
        $p_name_esc = $conn->real_escape_string($product['name']);
        $check_order = $conn->query("SELECT id FROM orders WHERE user_id = $user_id AND product_name LIKE '%$p_name_esc%' AND status = 'Delivered' LIMIT 1");

        if ($check_order && $check_order->num_rows > 0) {
            // Handle Photo Upload
            if (isset($_FILES['review_photo']) && $_FILES['review_photo']['error'] === UPLOAD_ERR_OK) {
                $target_dir = "images/reviews/";
                if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);
                
                $ext = strtolower(pathinfo($_FILES["review_photo"]["name"], PATHINFO_EXTENSION));
                $new_name = 'user_rev_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $target_file = $target_dir . $new_name;

                if (move_uploaded_file($_FILES["review_photo"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                }
            }

            $sql = "INSERT INTO reviews (product_id, user_name, rating, comment, image, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("isiss", $product_id, $user_name, $rating, $comment, $image_path);
                if ($stmt->execute()) {
                    $review_msg = "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;'>✅ Success! Your review is waiting for approval.</div>";
                }
                $stmt->close();
            }
        } else {
            $review_msg = "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;'>❌ Only customers who have received this product can leave a review.</div>";
        }
    }
}

// Check if current user can review
$can_review = false;
$prefill_name = '';
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $prefill_name = $_SESSION['user_name'] ?? '';
    $p_name_esc = $conn->real_escape_string($product['name']);
    $check_can = $conn->query("SELECT id FROM orders WHERE user_id = $uid AND product_name LIKE '%$p_name_esc%' AND status = 'Delivered' LIMIT 1");
    if ($check_can && $check_can->num_rows > 0) {
        $can_review = true;
    }
}

// Fetch approved reviews
$reviews_query = "SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC";
$stmt4 = $conn->prepare($reviews_query);
$approved_reviews = [];
if ($stmt4) {
    $stmt4->bind_param("i", $product_id);
    $stmt4->execute();
    $rev_res = $stmt4->get_result();
    while ($r = $rev_res->fetch_assoc()) {
        $approved_reviews[] = $r;
    }
    $stmt4->close();
}

$avg_rating = $product['admin_rating'] ?? 5.0;
$review_count = count($approved_reviews);

// Fetch user's wishlist for related products
$userWishlist = [];
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $wish_res = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $uid");
    while ($row = $wish_res->fetch_assoc()) {
        $userWishlist[] = (int)$row['product_id'];
    }
}

// Check if current user has this product in wishlist
$isWishlisted = false;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $check_wish = $conn->query("SELECT id FROM wishlist WHERE user_id = $uid AND product_id = $product_id");
    if ($check_wish && $check_wish->num_rows > 0) {
        $isWishlisted = true;
    }
}

include 'includes/header.php';

// Fetch all images from product_images table
$images_query = "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC";
$stmt2 = $conn->prepare($images_query);
if ($stmt2) {
    $stmt2->bind_param("i", $product_id);
    $stmt2->execute();
    $images_result = $stmt2->get_result();
} else {
    $images_result = null;
}

$gallery_images = [];
if ($images_result && $images_result->num_rows > 0) {
    while ($row = $images_result->fetch_assoc()) {
        $gallery_images[] = $row['image'];
    }
}

// Fallback: if no images in product_images, use the main product image
if (empty($gallery_images) && !empty($product['image'])) {
    $gallery_images[] = $product['image'];
}

$main_image = !empty($gallery_images) ? htmlspecialchars($gallery_images[0]) : 'images/frames/6x9.jpg.jpeg';
$ext = strtolower(pathinfo($main_image, PATHINFO_EXTENSION));

// Fetch related products using prepared statement
$related_query = "SELECT * FROM products WHERE category = ? AND id != ? LIMIT 4";
$stmt3 = $conn->prepare($related_query);
if ($stmt3) {
    $stmt3->bind_param("si", $product['category'], $product_id);
    $stmt3->execute();
    $related_result = $stmt3->get_result();
} else {
    $related_result = null;
}
?>

<div class="container product-details-container">
    <div class="product-gallery">
        <div class="gallery-main" id="galleryMain" style="position: relative;">
            <!-- Wishlist Icon -->
            <div class="wishlist-icon" style="position: absolute; top: 15px; right: 15px; z-index: 10; background: rgba(255,255,255,0.9); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.15); transition: all 0.3s ease;" data-product-id="<?php echo $product_id; ?>">
                <i class="<?php echo $isWishlisted ? 'fas' : 'far'; ?> fa-heart" 
                   style="font-size: 20px; color: <?php echo $isWishlisted ? '#ff4343' : '#666'; ?>;"></i>
            </div>
            <?php if ($ext === 'mp4'): ?>
                <video src="<?php echo $main_image; ?>" controls autoplay muted loop
                    style="width: 100%; max-height: 550px; border-radius: 8px; object-fit: contain;"></video>
            <?php else: ?>
                <img src="<?php echo $main_image; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>"
                    id="main-image">
            <?php endif; ?>
        </div>

        <?php if (count($gallery_images) > 1): ?>
            <div class="gallery-thumbnails">
                <?php foreach ($gallery_images as $index => $img):
                    $img_escaped = htmlspecialchars($img);
                    $img_ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                    ?>
                    <div class="thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                        onclick="switchImage('<?php echo $img_escaped; ?>', this)"
                        style="<?php echo $img_ext === 'mp4' ? 'display:none;' : ''; ?>">
                        <img src="<?php echo $img_escaped; ?>" alt="Photo <?php echo $index + 1; ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="product-meta">
        <h1>
            <?php echo htmlspecialchars($product['name']); ?>
        </h1>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
            <div style="color: var(--gold); font-size: 1.2rem;">
                <?php 
                $full_stars = floor($avg_rating);
                $half_star = ($avg_rating - $full_stars) >= 0.5 ? 1 : 0;
                $empty_stars = 5 - $full_stars - $half_star;

                for($i=0; $i<$full_stars; $i++) echo '<i class="fas fa-star"></i>';
                if($half_star) echo '<i class="fas fa-star-half-alt"></i>';
                for($i=0; $i<$empty_stars; $i++) echo '<i class="far fa-star"></i>';
                ?>
            </div>
            <span style="color: var(--text-light);">(<?php echo $review_count; ?> Customer Reviews)</span>
            <span style="background: #388e3c; color: #fff; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 0.9rem;">
                Royal Rated: <?php echo number_format($avg_rating, 1); ?>
            </span>
        </div>

        <div class="price" style="display: flex; align-items: baseline; gap: 15px;">
            <span style="font-size: 2.2rem; font-weight: 700; color: var(--text);">₹<?php echo number_format($product['price'], 2); ?></span>
            <?php if (!empty($product['mrp']) && $product['mrp'] > $product['price']): 
                $discount_percent = round((($product['mrp'] - $product['price']) / $product['mrp']) * 100);
            ?>
                <del style="color: #888; font-size: 1.2rem;">₹<?php echo number_format($product['mrp'], 0); ?></del>
                <span style="color: #388e3c; font-weight: 600; font-size: 1.2rem;"><?php echo $discount_percent; ?>% off</span>
            <?php endif; ?>
        </div>
        
        <div style="color: #388e3c; font-weight: bold; margin-bottom: 20px; font-size: 1.1rem;">
            <i class="fas fa-check-circle"></i> In Stock
        </div>

        <div class="product-description">
            <p>Premium quality wooden photo frame, perfectly crafted to preserve your most cherished memories. Features
                robust build quality, elegant finish, and comes ready to hang or display.</p>
            <ul style="margin-top: 15px; margin-left: 20px; list-style-type: disc;">
                <li>Material: High-quality engineered wood</li>
                <li>Finish: Matte / Glossy options available</li>
                <li>Includes transparent protective acrylic glass</li>
                <li>Delivery available PAN India</li>
            </ul>
        </div>

        <form action="cart" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

            <div class="quantity-selector" style="margin-bottom: 20px;">
                <label style="font-weight: 500; display: block; margin-bottom: 10px;">Quantity:</label>
                <div class="quantity-control"
                    style="display: inline-flex; align-items: center; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                    <button type="button" class="qty-btn minus"
                        style="padding: 10px 15px; background: #f9f9f9; border: none; cursor: pointer; border-right: 1px solid #ddd; font-size: 1.2rem;"
                        onclick="updateProductQty(-1)">-</button>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="50"
                        style="width: 60px; padding: 10px; border: none; text-align: center; font-size: 1.1rem; -moz-appearance: textfield;">
                    <button type="button" class="qty-btn plus"
                        style="padding: 10px 15px; background: #f9f9f9; border: none; cursor: pointer; border-left: 1px solid #ddd; font-size: 1.2rem;"
                        onclick="updateProductQty(1)">+</button>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                <button type="submit" name="action" value="add" class="btn btn-add-cart"
                    style="flex: 1; padding: 15px; font-size: 1.1rem;">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>
                <button type="submit" name="action" value="buy_now" class="btn btn-buy-now"
                    style="flex: 1; padding: 15px; font-size: 1.1rem;">
                    Buy Now
                </button>
            </div>
        </form>

        <div style="display: flex; gap: 20px; border-top: 1px solid #eee; padding-top: 20px;">
            <div style="color: var(--text-light); padding: 8px 15px;"><i class="fas fa-truck"></i> Estimated Delivery:
                3-5 Days</div>
        </div>
    </div>
</div>

<!-- Customer Reviews Section -->
<section class="container" style="padding: 60px 0; border-top: 1px solid #eee;">
    <div style="display: flex; gap: 40px; flex-wrap: wrap;">
        <!-- Existing Reviews -->
        <div style="flex: 1; min-width: 300px;">
            <h2 style="margin-bottom: 30px;">Customer Reviews</h2>
            <?php if (isset($review_msg)) echo $review_msg; ?>
            
            <?php if (empty($approved_reviews)): ?>
                <p style="color: #666; font-style: italic;">No reviews yet. Be the first to review this product!</p>
            <?php else: ?>
                <?php foreach ($approved_reviews as $rev): ?>
                    <div style="background: #fdfdfd; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <strong><?php echo htmlspecialchars($rev['user_name']); ?></strong>
                            <span style="color: #ffc107;">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="<?php echo $i <= $rev['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                <?php endfor; ?>
                            </span>
                        </div>
                        <p style="color: #444; line-height: 1.5; margin-bottom: 12px; font-style: italic;">"<?php echo htmlspecialchars($rev['comment']); ?>"</p>
                        <?php if (!empty($rev['image'])): ?>
                            <div style="margin-bottom: 12px;">
                                <img src="<?php echo htmlspecialchars($rev['image']); ?>" alt="Review photo" 
                                     style="max-width: 100%; max-height: 200px; border-radius: 6px; cursor: pointer; border: 1px solid #eee;"
                                     onclick="window.open(this.src, '_blank')">
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <small style="color: #999;"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></small>
                            <?php if (!$rev['is_admin']): ?>
                                <span style="font-size: 0.75rem; color: #388e3c; font-weight: 600;">
                                    <i class="fas fa-check-circle"></i> Verified Buyer
                                </span>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; color: var(--accent); font-weight: 600;">
                                    <i class="fas fa-certificate"></i> Official Review
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Review Form -->
        <div style="flex: 0 0 350px; background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #eee; height: fit-content;">
            <h3 style="margin-bottom: 20px;">Write a Review</h3>
            <?php if ($can_review): ?>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Your Name</label>
                        <input type="text" name="user_name" value="<?php echo htmlspecialchars($prefill_name); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Rating</label>
                        <select name="rating" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="5">5 Stars - Excellent</option>
                            <option value="4">4 Stars - Very Good</option>
                            <option value="3">3 Stars - Good</option>
                            <option value="2">2 Stars - Fair</option>
                            <option value="1">1 Star - Poor</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Photo (Optional)</label>
                        <input type="file" name="review_photo" accept="image/*" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px;">Comment</label>
                        <textarea name="comment" required rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: none;" placeholder="Share your experience..."></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn btn-primary" style="width: 100%; padding: 12px;">Submit Review</button>
                </form>
            <?php elseif(!isset($_SESSION['user_id'])): ?>
                <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <p style="margin-bottom: 15px; color: #666;">Please login to leave a review.</p>
                    <a href="<?php echo FRONTEND_URL; ?>login.php?redirect=product.php?id=<?php echo urlencode($product_id); ?>" class="btn btn-primary" style="display: block; padding: 10px;">Login / Sign Up</a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <p style="color: #666;">Only customers who have <strong>received</strong> this product can leave a review.</p>
                    <p style="font-size: 0.85rem; color: #999; margin-top: 10px;">Status must be 'Delivered' in your orders.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Fullscreen Image Modal -->
<div id="imageModal" class="modal">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<!-- Related Products -->
<?php if ($related_result && $related_result->num_rows > 0): ?>
    <section class="container" style="padding: 60px 0; border-top: 1px solid #eee;">
        <h2 class="section-title">Related Products</h2>
        <div class="product-grid">
            <?php while ($related = $related_result->fetch_assoc()): ?>
                <div class="product-card" data-product-id="<?php echo $related['id']; ?>">
                    <!-- Wishlist Icon -->
                    <div class="wishlist-icon" data-product-id="<?php echo $related['id']; ?>">
                        <i class="<?php echo in_array($related['id'], $userWishlist) ? 'fas' : 'far'; ?> fa-heart"
                           style="<?php echo in_array($related['id'], $userWishlist) ? 'color: #ff4343;' : ''; ?>"></i>
                    </div>
                    <a href="product.php?id=<?php echo $related['id']; ?>" class="product-image-container">
                        <!-- Discount Badge -->
                        <?php if (!empty($related['mrp']) && $related['mrp'] > $related['price']): 
                            $discount_percent = round((($related['mrp'] - $related['price']) / $related['mrp']) * 100);
                        ?>
                            <div style="position: absolute; top: 10px; left: 0; background: #388e3c; color: #fff; padding: 4px 8px; font-size: 0.75rem; font-weight: bold; border-radius: 0 4px 4px 0; z-index: 2;">
                                <?php echo $discount_percent; ?>% OFF
                            </div>
                        <?php endif; ?>
                        <?php if (strtolower(pathinfo($related['image'], PATHINFO_EXTENSION)) === 'mp4'): ?>
                            <video src="<?php echo htmlspecialchars($related['image']); ?>"
                                style="width: 100%; height: 250px; object-fit: cover;" muted loop onmouseover="this.play()"
                                onmouseout="this.pause()" preload="metadata"></video>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($related['image']); ?>"
                                alt="<?php echo htmlspecialchars($related['name']); ?>" loading="lazy">
                        <?php endif; ?>
                    </a>
                    <div class="product-info" style="text-align: left; padding: 15px;">
                        <div style="font-size: 0.75rem; color: #888; font-weight: 600; text-transform: uppercase;">Royal Assured <i class="fas fa-check-circle" style="color: var(--accent);"></i></div>
                        <h3 class="product-title" style="font-size: 1rem; margin: 4px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text);">
                            <?php echo htmlspecialchars($related['name']); ?>
                        </h3>
                        <div class="product-rating" style="margin-bottom: 8px;">
                            <span style="background: #388e3c; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                                4.<?php echo rand(2, 8); ?> <i class="fas fa-star" style="font-size: 0.7rem;"></i>
                            </span>
                            <span style="color: #888; font-size: 0.85rem; margin-left: 5px;">(<?php echo rand(120, 999); ?>)</span>
                        </div>
                        <div class="product-price" style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 12px;">
                            <span style="font-size: 1.25rem; font-weight: 700; color: var(--text);">₹<?php echo number_format($related['price'], 2); ?></span>
                            <?php if (!empty($related['mrp']) && $related['mrp'] > $related['price']): 
                                $discount_percent = round((($related['mrp'] - $related['price']) / $related['mrp']) * 100);
                            ?>
                                <del style="color: #888; font-size: 0.9rem;">₹<?php echo number_format($related['mrp'], 0); ?></del>
                                <span style="color: #388e3c; font-weight: 600; font-size: 0.85rem;"><?php echo $discount_percent; ?>% off</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-actions" style="flex-direction: column; gap: 8px;">
                            <form action="cart" method="POST" style="width: 100%;">
                                <input type="hidden" name="product_id" value="<?php echo $related['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <div style="display: flex; gap: 6px; width: 100%;">
                                    <button type="submit" name="action" value="add" class="btn btn-add-cart"
                                        style="flex:1; padding: 10px; font-weight: 600;"><i class="fas fa-shopping-cart"></i> Add</button>
                                    <button type="submit" name="action" value="buy_now" class="btn btn-buy-now"
                                        style="flex:1; padding: 10px; font-weight: 600;">Buy Now</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
<?php endif; ?>

<script>
    // Gallery thumbnail click handler
    function switchImage(src, thumbElement) {
        const mainImg = document.getElementById('main-image');
        if (mainImg) {
            // Fade effect
            mainImg.style.opacity = '0';
            setTimeout(() => {
                mainImg.src = src;
                mainImg.style.opacity = '1';
            }, 200);
        }

        // Update active thumbnail
        document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
        if (thumbElement) {
            thumbElement.classList.add('active');
        }
    }

    // Fullscreen modal on main image click
    function changeMainImage(src) {
        document.getElementById('mainImage').src = src;
    }

    // Handle quantity buttons on product page
    function updateProductQty(change) {
        const input = document.getElementById('quantity');
        let newVal = parseInt(input.value) + change;
        if (newVal >= parseInt(input.min) && newVal <= parseInt(input.max)) {
            input.value = newVal;
        }
    }

    const mainImage = document.getElementById('main-image');
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    const closeModal = document.querySelector('.close-modal');

    if (mainImage && modal) {
        mainImage.addEventListener('click', function () {
            modal.style.display = 'flex';
            modalImg.src = this.src;
        });
    }

    if (closeModal) {
        closeModal.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>