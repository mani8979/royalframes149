<?php
/**
 * Royal Frames - Premium Shopping Cart
 * High-end UI with category-based order limits
 */
require_once 'includes/db.php';

// Error handling: Enable full debugging for now
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Ensure clean output - only clean if a buffer actually exists and has content
if (ob_get_length()) {
    ob_clean();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// DEBUG: Start output buffer for debugging
// ob_start();

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

    if (($action === 'add' || $action === 'buy_now') && $product_id > 0) {
        $quantity = isset($_POST['quantity']) ? max(1, (int) $_POST['quantity']) : 1;
        $quantity = min($quantity, 50); // Security limit

        $query = "SELECT * FROM products WHERE id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();

                // Handle photo upload
                $user_photo_path = '';
                if (isset($_FILES['frame_photo']) && $_FILES['frame_photo']['error'] === UPLOAD_ERR_OK) {
                    $target_dir = "uploads/user_photos/";
                    if (!file_exists($target_dir)) mkdir($target_dir, 0755, true);
                    
                    $photo_ext = strtolower(pathinfo($_FILES["frame_photo"]["name"], PATHINFO_EXTENSION));
                    if (in_array($photo_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) && $_FILES["frame_photo"]["size"] <= 5 * 1024 * 1024) {
                        $photo_name = 'photo_' . time() . '_' . rand(10000, 99999) . '.' . $photo_ext;
                        $target_file = $target_dir . $photo_name;
                        if (move_uploaded_file($_FILES["frame_photo"]["tmp_name"], $target_file)) {
                            $user_photo_path = $target_file;
                            chmod($target_file, 0644);
                        }
                    }
                }

                $found = false;
                if (empty($user_photo_path)) {
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['id'] == $product_id && empty($item['user_photo'])) {
                            $item['quantity'] += $quantity;
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    $_SESSION['cart'][] = [
                        'cart_item_id' => uniqid('ci_'),
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'image' => $product['image'],
                        'quantity' => $quantity,
                        'user_photo' => $user_photo_path,
                        'category' => $product['category']
                    ];
                }
            }
        }

        if ($action === 'buy_now') {
            header("Location: checkout.php?buy_now=" . $product_id);
            exit();
        }
        header("Location: cart.php");
        exit();
    }

    if ($action === 'remove' && isset($_POST['cart_item_id'])) {
        $cart_item_id = $_POST['cart_item_id'];
        foreach ($_SESSION['cart'] as $key => $item) {
            if (isset($item['cart_item_id']) && $item['cart_item_id'] === $cart_item_id) {
                unset($_SESSION['cart'][$key]);
                break;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        header("Location: cart.php");
        exit();
    }

    if ($action === 'update' && isset($_POST['cart_item_id'])) {
        $cart_item_id = $_POST['cart_item_id'];
        $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;
        if ($quantity > 0) {
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['cart_item_id'] === $cart_item_id) {
                    $item['quantity'] = min(50, $quantity);
                    break;
                }
            }
        }
        header("Location: cart.php");
        exit();
    }
}

$pageTitle = 'Your Cart';

// DEBUG: View cart session data
// echo "<!-- DEBUG SESSION: " . print_r($_SESSION['cart'], true) . " -->";

$subtotal = 0;
$category_totals = [];
$category_limits = [];

// Fetch category limits first for validation
if (!$db_connection_error) {
    $cat_limits_query = $conn->query("SELECT name, min_order FROM categories");
    if ($cat_limits_query && $cat_limits_query->num_rows > 0) {
        while ($cl = $cat_limits_query->fetch_assoc()) {
            $category_limits[$cl['name']] = (int)$cl['min_order'];
        }
    }
}

// Ensure all items in cart have category set (fallback if missing)
foreach ($_SESSION['cart'] as &$item) {
    if (!isset($item['category']) && !$db_connection_error) {
        $p_query = $conn->query("SELECT category FROM products WHERE id = " . (int)$item['id']);
        if ($p_query && $p_query->num_rows > 0) {
            $p_row = $p_query->fetch_assoc();
            $item['category'] = $p_row['category'];
        } else {
            $item['category'] = 'Frames';  // Fallback category
        }
    } elseif (!isset($item['category'])) {
        $item['category'] = 'Frames';
    }
    
    // Normalize item structure
    if (!isset($item['cart_item_id'])) $item['cart_item_id'] = uniqid('ci_');
    if (!isset($item['price'])) $item['price'] = 0;
    if (!isset($item['quantity'])) $item['quantity'] = 1;
    if (!isset($item['name'])) $item['name'] = 'Unknown Product';
    if (!isset($item['image'])) $item['image'] = '';
    if (!isset($item['user_photo'])) $item['user_photo'] = '';

    $subtotal += ($item['price'] * $item['quantity']);
    $cat_name = $item['category'];
    if (!isset($category_totals[$cat_name])) $category_totals[$cat_name] = 0;
    $category_totals[$cat_name] += $item['quantity'];
}

// Validate checkout readiness based on per-category limits
$limit_errors = [];
foreach ($category_totals as $cat_name => $total_qty) {
    $min = isset($category_limits[$cat_name]) ? $category_limits[$cat_name] : 0;
    if ($min > 0 && $total_qty < $min) {
        $limit_errors[] = [
            'category' => $cat_name,
            'current' => $total_qty,
            'required' => $min,
            'needed' => $min - $total_qty
        ];
    }
}

$can_checkout = empty($limit_errors) && !empty($_SESSION['cart']);

// Check for combo/direct redirect logic from original file
$has_combo = false;
foreach ($_SESSION['cart'] as $item) {
    if (isset($item['category']) && strpos(strtolower($item['category']), 'combo') !== false) {
        $has_combo = true;
        break;
    }
}

if ($has_combo && !empty($_SESSION['cart']) && $can_checkout) {
    header("Location: checkout.php");
    exit();
}

include 'includes/header.php';
?>

<style>
    :root {
        --cart-bg: #0d0d0d;
        --cart-card: #181818;
        --cart-accent: #D4AF37;
        --cart-text: #ffffff;
        --cart-text-muted: #a0a0a0;
        --cart-border: rgba(212, 175, 55, 0.15);
    }

    .cart-wrapper {
        background: var(--cart-bg);
        color: var(--cart-text);
        padding: 40px 0 100px;
        min-height: 80vh;
    }

    .cart-header {
        margin-bottom: 40px;
        text-align: center;
    }

    .cart-header h1 {
        font-size: 2.5rem;
        font-weight: 700;
        letter-spacing: -1px;
        margin-bottom: 10px;
        color: var(--cart-text);
    }

    .cart-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 30px;
    }

    .cart-item-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .cart-item-card {
        background: var(--cart-card);
        border: 1px solid var(--cart-border);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.3s ease;
    }

    .cart-item-card:hover {
        transform: translateY(-2px);
        border-color: var(--cart-accent);
    }

    .cart-item-image {
        width: 100px;
        height: 100px;
        border-radius: 8px;
        object-fit: cover;
        background: #222;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .cart-item-details {
        flex: 1;
    }

    .cart-item-name {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }

    .cart-item-category {
        font-size: 0.85rem;
        color: var(--cart-text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .cart-item-price {
        font-size: 1rem;
        color: var(--cart-accent);
        font-weight: 700;
        margin-top: 8px;
    }

    .cart-item-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .quantity-box {
        display: flex;
        align-items: center;
        background: #222;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #333;
    }

    .qty-btn {
        background: transparent;
        border: none;
        color: var(--cart-text);
        padding: 8px 12px;
        cursor: pointer;
        font-size: 1.1rem;
        transition: background 0.2s;
    }

    .qty-btn:hover {
        background: #333;
    }

    .qty-input {
        width: 40px;
        background: transparent;
        border: none;
        color: var(--cart-text);
        text-align: center;
        font-weight: 600;
        font-size: 1rem;
        pointer-events: none;
    }

    .remove-btn {
        background: transparent;
        border: none;
        color: #ff4d4d;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 10px;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .remove-btn:hover {
        background: rgba(255, 77, 77, 0.1);
    }

    .cart-summary-card {
        background: var(--cart-card);
        border: 1px solid var(--cart-accent);
        border-radius: 12px;
        padding: 30px;
        position: sticky;
        top: 100px;
        height: min-content;
    }

    .summary-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 25px;
        border-bottom: 1px solid var(--cart-border);
        padding-bottom: 15px;
    }

    .summary-line {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        font-size: 1rem;
        color: var(--cart-text-muted);
    }

    .summary-total {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 2px solid var(--cart-border);
        color: var(--cart-text);
        font-size: 1.5rem;
        font-weight: 700;
    }

    .checkout-btn {
        width: 100%;
        background: var(--cart-accent);
        color: #000;
        border: none;
        padding: 16px;
        border-radius: 10px;
        font-size: 1.1rem;
        font-weight: 700;
        margin-top: 30px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .checkout-btn:hover:not(:disabled) {
        transform: scale(1.02);
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.3);
    }

    .checkout-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #555;
    }

    .limit-warning {
        background: rgba(255, 77, 77, 0.1);
        border: 1px solid #ff4d4d;
        color: #ff4d4d;
        padding: 12px;
        border-radius: 8px;
        margin-top: 20px;
        font-size: 0.85rem;
    }

    .user-photo-indicator {
        position: absolute;
        top: -5px;
        right: -5px;
        background: var(--cart-accent);
        color: #000;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        border: 2px solid var(--cart-card);
    }

    @media (max-width: 992px) {
        .cart-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Mobile-first cart item layout */
    @media (max-width: 768px) {
        .cart-item-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            padding: 18px;
        }

        .cart-item-image {
            width: 100%;
            height: auto;
            max-height: 240px;
            border-radius: 12px;
        }

        .cart-item-details {
            width: 100%;
        }

        .cart-item-name {
            white-space: normal;
            word-break: normal;
        }

        .cart-item-actions {
            width: 100%;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .quantity-box {
            width: 170px;
        }

        .remove-btn {
            margin-left: auto;
        }
    }
</style>

<div class="cart-wrapper">
    <div class="container">
        <div class="cart-header">
            <h1>Shopping Cart</h1>
            <p style="color: var(--cart-text-muted);">Review your premium frames before checkout.</p>
        </div>

        <?php
        // Display error messages
        if (isset($_GET['qty_limit_error'])) {
            echo '<div class="limit-warning">' . htmlspecialchars(urldecode($_GET['qty_limit_error'])) . '</div>';
        } elseif (isset($_GET['min_qty_error'])) {
            echo '<div class="limit-warning">You need at least 2 frames to checkout (unless you have combo offers).</div>';
        }
        ?>

        <?php if (empty($_SESSION['cart'])): ?>
            <div style="text-align: center; padding: 60px 0;">
                <i class="fas fa-shopping-bag" style="font-size: 4rem; color: #333; margin-bottom: 20px;"></i>
                <h2>Your cart is empty.</h2>
                <a href="shop.php" class="btn btn-primary" style="margin-top: 20px;">Return to Shop</a>
            </div>
        <?php else: ?>
            <div class="cart-grid">
                <div class="cart-item-list">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="cart-item-card">
                            <div style="position: relative;">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="item" class="cart-item-image">
                                <?php if (!empty($item['user_photo'])): ?>
                                    <div class="user-photo-indicator" title="Custom Photo Uploaded">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cart-item-details">
                                <span class="cart-item-category"><?php echo htmlspecialchars($item['category'] ?? 'Frames'); ?></span>
                                <span class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="cart-item-price">₹<?php echo number_format($item['price'], 2); ?></span>
                            </div>

                            <div class="cart-item-actions">
                                <form action="cart.php" method="POST" id="form-<?php echo $item['cart_item_id']; ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <div class="quantity-box">
                                        <button type="button" class="qty-btn" onclick="updateQty('<?php echo $item['cart_item_id']; ?>', -1)">-</button>
                                        <input type="number" name="quantity" class="qty-input" value="<?php echo $item['quantity']; ?>" data-id="<?php echo $item['cart_item_id']; ?>">
                                        <button type="button" class="qty-btn" onclick="updateQty('<?php echo $item['cart_item_id']; ?>', 1)">+</button>
                                    </div>
                                </form>

                                <form action="cart.php" method="POST">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <button type="submit" class="remove-btn" title="Remove Item"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <a href="shop.php" style="color: var(--cart-accent); text-decoration: none; margin-top: 10px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>

                <div class="cart-summary-col">
                    <div class="cart-summary-card">
                        <h2 class="summary-title">Order Summary</h2>
                        
                        <div class="summary-line">
                            <span>Subtotal</span>
                            <span>₹<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        
                        <div class="summary-line">
                            <span>Standard Delivery</span>
                            <span style="color: #28a745;">FREE</span>
                        </div>

                        <div class="summary-line summary-total">
                            <span>Total</span>
                            <span>₹<?php echo number_format($subtotal, 2); ?></span>
                        </div>

                        <?php if (!empty($limit_errors)): ?>
                            <div class="limit-warning">
                                <strong><i class="fas fa-exclamation-triangle"></i> Minimum requirement not met:</strong>
                                <ul style="margin: 8px 0 0 15px; padding: 0;">
                                    <?php foreach ($limit_errors as $err): ?>
                                        <li>Add <strong><?php echo $err['needed']; ?></strong> more item(s) from <strong><?php echo htmlspecialchars($err['category']); ?></strong></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form action="checkout.php" method="GET">
                            <button type="submit" class="checkout-btn" <?php echo !$can_checkout ? 'disabled' : ''; ?>>
                                <i class="fas fa-shield-alt"></i> Secure Checkout
                            </button>
                        </form>
                        
                        <p style="text-align: center; font-size: 0.8rem; color: var(--cart-text-muted); margin-top: 15px;">
                            <i class="fas fa-lock"></i> SSL Encrypted & Secure
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateQty(id, change) {
        const input = document.querySelector(`.qty-input[data-id="${id}"]`);
        let val = parseInt(input.value) + change;
        if (val >= 1 && val <= 50) {
            input.value = val;
            input.closest('form').submit();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>