<?php
$pageTitle = 'My Orders';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Enhanced Query to get product image for display
$query = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY order_date DESC";
$result = $conn->query($query);

include 'includes/header.php';
?>

<style>
@media (min-width: 768px) {
    .desktop-track-btn { display: block !important; }
    .mobile-chevron { display: none !important; }
    .order-item-card { padding: 20px; }
    .order-title { font-size: 18px !important; margin-bottom: 8px !important; }
    .order-date-status { font-size: 14px !important; margin-bottom: 12px !important; }
    .order-price-box { font-size: 18px !important; }
    .order-img-box { width: 120px !important; height: 120px !important; }
}
</style>

<div class="orders-list-container" style="min-height: 60vh;">
    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
        <div style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #c3e6cb;">
            ✅ <strong>Order Placed Successfully!</strong> #<?php echo htmlspecialchars($_GET['order_id'] ?? ''); ?>
        </div>
    <?php endif; ?>

    <h1 style="font-size: 24px; font-weight: 600; margin-bottom: 24px; color: var(--text);">My Orders</h1>

    <?php if ($result->num_rows > 0): ?>
        <?php while ($order = $result->fetch_assoc()): ?>
            <?php
            // Determine display image
            $display_image = 'images/placeholder.jpg';
            if (!empty($order['photos'])) {
                $photos = explode(',', $order['photos']);
                $display_image = trim($photos[0]);
            } else {
                $p_names = explode(',', $order['product_name']);
                $search_name = trim(preg_replace('/\(x\d+\)/', '', $p_names[0]));
                $img_q = $conn->query("SELECT image FROM products WHERE name LIKE '%" . $conn->real_escape_string($search_name) . "%' LIMIT 1");
                if ($img_q && $img_q->num_rows > 0) {
                    $img_row = $img_q->fetch_assoc();
                    $display_image = $img_row['image'];
                }
            }
            
            // Status color logic refinement
            $status_class = 'dot-gray';
            if ($order['status'] == 'Delivered') $status_class = 'dot-green';
            if (in_array($order['status'], ['Shipped', 'Out for Delivery'])) $status_class = 'dot-blue';
            ?>
            <a href="track-order.php?id=<?php echo $order['id']; ?>" class="order-item-card" style="flex-wrap: wrap;">
                <div style="display: flex; width: 100%; align-items: center; gap: 12px;">
                    <div class="order-img-box">
                        <img src="<?php echo htmlspecialchars($display_image); ?>" alt="Product">
                    </div>
                    <div class="order-details">
                        <div class="order-title"><?php echo htmlspecialchars($order['product_name']); ?></div>
                        <div class="order-date-status" style="flex-wrap: wrap;">
                            <span class="status-dot <?php echo $status_class; ?>"></span>
                            <span style="color: var(--text);"><?php echo $order['status']; ?></span>
                            <span style="color: #ccc;">|</span>
                            <span style="color: var(--text-light);"><?php echo date('d M Y', strtotime($order['order_date'])); ?></span>
                            <span style="color: #ccc; margin-left: 4px; margin-right: 4px;">|</span>
                            <span style="color: var(--text-light); font-weight: 500;">Order #<?php echo $order['id']; ?></span>
                        </div>
                        <div class="order-price-box" style="color: var(--text);">₹<?php echo number_format($order['total_price'], 2); ?></div>
                    </div>
                    <div class="mobile-chevron" style="color: #878787; font-size: 18px;">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 100px 0;">
            <i class="fas fa-box-open" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
            <h3>No Orders Found</h3>
            <p style="color: #878787;">You haven't placed any orders yet.</p>
            <a href="shop.php" class="btn btn-primary" style="margin-top: 20px; background: #2874F0; border-radius: 8px;">Start Shopping</a>
        </div>
<?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>