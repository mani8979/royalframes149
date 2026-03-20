<?php
$pageTitle = 'Checkout';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout&msg=login_required");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_phone = $_SESSION['user_phone'];
$user_address = $_SESSION['user_address'];

// Determine checkout type
$items = [];
$subtotal = 0;
$total_qty = 0;
$product_names = [];
$has_combo = false;

if (isset($_GET['buy_now']) && !empty($_GET['buy_now'])) {
    $product_id = (int) $_GET['buy_now'];
    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        header("Location: shop.php");
        exit();
    }
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        // Check if buy_now product is a combo
        if (strpos(strtolower($product['category']), 'combo') !== false) {
            $has_combo = true;
        }
        $items[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => 1,
            'image' => $product['image'],
            'user_photo' => '' 
        ];
        $subtotal = $product['price'];
        $total_qty = 1;
        $product_names[] = $product['name'] . ' (x1)';
    } else {
        header("Location: shop.php");
        exit();
    }
} else {
    // Normal Cart Checkout
    if (empty($_SESSION['cart'])) {
        header("Location: cart.php");
        exit();
    }

    foreach ($_SESSION['cart'] as $item) {
        $items[] = $item;
        $subtotal += ($item['price'] * $item['quantity']);
        $total_qty += $item['quantity'];
        $product_names[] = $item['name'] . ' (x' . $item['quantity'] . ')';

        // Check if any cart item is a combo
        $item_id = (int) $item['id'];
        $check_category = "SELECT category FROM products WHERE id = ?";
        $cat_stmt = $conn->prepare($check_category);
        if ($cat_stmt) {
            $cat_stmt->bind_param("i", $item_id);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            if ($cat_result && $cat_result->num_rows > 0) {
                $cat_row = $cat_result->fetch_assoc();
                if (strpos(strtolower($cat_row['category']), 'combo') !== false) {
                    $has_combo = true;
                }
            }
            $cat_stmt->close();
        }
    }

    // Validate category quantity limits
    $category_quantities = [];
    foreach ($items as $item) {
        $item_id = (int) $item['id'];
        $item_qty = (int) $item['quantity'];
        
        // Get product category
        $cat_query = "SELECT category FROM products WHERE id = ?";
        $cat_stmt = $conn->prepare($cat_query);
        if ($cat_stmt) {
            $cat_stmt->bind_param("i", $item_id);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            if ($cat_result && $cat_result->num_rows > 0) {
                $cat_row = $cat_result->fetch_assoc();
                $category = $cat_row['category'];
                
                if (!isset($category_quantities[$category])) {
                    $category_quantities[$category] = 0;
                }
                $category_quantities[$category] += $item_qty;
            }
            $cat_stmt->close();
        }
    }

    // Check quantity limits for each category
    $quantity_error = '';
    foreach ($category_quantities as $category => $qty) {
        // Get category limits
        $limit_query = "SELECT min_order, max_order FROM categories WHERE name = ?";
        $limit_stmt = $conn->prepare($limit_query);
        if ($limit_stmt) {
            $limit_stmt->bind_param("s", $category);
            $limit_stmt->execute();
            $limit_result = $limit_stmt->get_result();
            if ($limit_result && $limit_result->num_rows > 0) {
                $limit_row = $limit_result->fetch_assoc();
                $min_order = (int) $limit_row['min_order'];
                $max_order = (int) $limit_row['max_order'];
                
                // Validate minimum
                if ($min_order > 0 && $qty < $min_order) {
                    $quantity_error = "Category '<strong>$category</strong>' requires a minimum of <strong>$min_order items</strong>, but you have <strong>$qty items</strong>. Please add more items from this category.";
                    break;
                }
                
                // Validate maximum
                if ($max_order > 0 && $qty > $max_order) {
                    $quantity_error = "Category '<strong>$category</strong>' allows a maximum of <strong>$max_order items</strong>, but you have <strong>$qty items</strong>. Please remove some items from this category.";
                    break;
                }
            }
            $limit_stmt->close();
        }
    }

    // Redirect to cart if quantity limits not met
    if (!empty($quantity_error)) {
        header("Location: cart.php?qty_limit_error=" . urlencode($quantity_error));
        exit();
    }
}

// Collect photo paths
$photo_paths_array = [];
foreach ($items as $item) {
    if (!empty($item['user_photo'])) {
        $photo_paths_array[] = $item['user_photo'];
    }
}
$combined_photos = implode(',', $photo_paths_array);

$combined_product_names = implode(", ", $product_names);

// Fetch delivery charges from database
$delivery_charges = [];
$has_shipping_regions = false;
$table_check = $conn->query("SHOW TABLES LIKE 'shipping_regions'");
if ($table_check && $table_check->num_rows > 0) {
    $has_shipping_regions = true;
}

if ($has_shipping_regions) {
    $sr_query = "SELECT state_code, state_name, charge FROM shipping_regions ORDER BY state_name ASC";
    $sr_result = $conn->query($sr_query);
    if ($sr_result && $sr_result->num_rows > 0) {
        while ($row = $sr_result->fetch_assoc()) {
            $delivery_charges[$row['state_code']] = [
                'label' => $row['state_name'],
                'charge' => $row['charge']
            ];
        }
    }
}

if (empty($delivery_charges)) {
    // Fallback if table is missing or empty
    $delivery_charges = [
        'AP' => ['label' => 'Andhra Pradesh', 'charge' => 1],
        'TS' => ['label' => 'Telangana', 'charge' => 250],
        'TN' => ['label' => 'Tamil Nadu', 'charge' => 280],
        'OTHER' => ['label' => 'Other States', 'charge' => 400]
    ];
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
        logSecurityEvent("csrf_failure", "Checkout form");
    } else {
        $state_code = sanitizeInput($_POST['state'] ?? '');
        $state_name = $delivery_charges[$state_code]['label'] ?? 'Other';
        $delivery_charge = $delivery_charges[$state_code]['charge'] ?? 400;

        $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
        // COD removed, defaulting to 'Success' since Razorpay processes before submission in our flow
        $payment_id = isset($_POST['razorpay_payment_id']) ? sanitizeInput($_POST['razorpay_payment_id']) : 'ONLINE-' . time();
        $payment_status = 'Success';

        $shipping_address = sanitizeInput($_POST['shipping_address'] ?? '');
        $pincode = sanitizeInput($_POST['pincode'] ?? '');
        if (!empty($pincode)) {
            $shipping_address .= ' - ' . $pincode;
        }

        // Check for photo uploaded during checkout
        if (isset($_FILES['checkout_photo']) && $_FILES['checkout_photo']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/user_photos/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true); // Secure permissions
            }

            // Security: Validate file type and size
            $photo_ext = strtolower(pathinfo($_FILES["checkout_photo"]["name"], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (in_array($photo_ext, $allowed_extensions) && $_FILES["checkout_photo"]["size"] <= $max_file_size) {
                $photo_name = 'photo_' . time() . '_checkout_' . rand(10000, 99999) . '.' . $photo_ext;
                $target_file = $target_dir . $photo_name;

                if (move_uploaded_file($_FILES["checkout_photo"]["tmp_name"], $target_file)) {
                    chmod($target_file, 0644); // Secure file permissions
                    if (!empty($combined_photos)) {
                        $combined_photos .= ',' . $target_file;
                    } else {
                        $combined_photos = $target_file;
                    }
                }
            }
        }

        $total_price = $subtotal + $delivery_charge;

        // Insert Order with photos using prepared statement
        $stmt = $conn->prepare("INSERT INTO orders (user_id, product_name, photos, price, quantity, state, delivery_charge, total_price, payment_id, payment_status, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Placed')");
        $stmt->bind_param("isssissdsss", $user_id, $combined_product_names, $combined_photos, $subtotal, $total_qty, $state_name, $delivery_charge, $total_price, $payment_id, $payment_status, $shipping_address);

        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;
            // If not a buy_now order, clear cart
            if (!isset($_GET['buy_now'])) {
                unset($_SESSION['cart']);
            }

            // Format products list for Telegram
            $products_list_tg = "";
            $product_items = explode(", ", $combined_product_names);
            foreach ($product_items as $p_item) {
                $products_list_tg .= "• " . htmlspecialchars($p_item) . "\n";
            }

            // Silent Backend Telegram Notification
            sendTelegramOrderNotification(
                $order_id, 
                htmlspecialchars($user_name), 
                htmlspecialchars($user_phone), 
                htmlspecialchars($shipping_address), 
                $products_list_tg, 
                $total_price
            );

            // Construct the return URL dynamically to point back to our InfinityFree site
            $current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $current_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $return_url = $current_domain . $current_path . "/my-orders.php?success=1&order_id=$order_id";

            $first_product_image = !empty($items[0]['image']) ? $items[0]['image'] : '';

            // Redirect to GitHub Pages bypass page for Telegram notification
            $redirect_url = (defined('GITHUB_BYPASS_URL') ? GITHUB_BYPASS_URL : FRONTEND_URL . "github_bypass.html") .
                "?success=1&order_id=$order_id" .
                "&customer_name=" . urlencode($user_name) .
                "&customer_phone=" . urlencode($user_phone) .
                "&customer_address=" . urlencode($shipping_address) .
                "&products=" . urlencode($combined_product_names) .
                "&product_image=" . urlencode($first_product_image) .
                "&total_price=" . urlencode($total_price) .
                "&return_url=" . urlencode($return_url);

            header("Location: $redirect_url");
            exit();
        } else {
            $error = "Payment failed to process. Try again. Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<div class="container" style="padding: 60px 0; min-height: 60vh;">

    <?php if (isset($_GET['success'])): ?>
        <div style="text-align: center; padding: 40px 0;">
            <i class="fas fa-check-circle" style="font-size: 5rem; color: #28a745; margin-bottom: 20px;"></i>
            <h1 class="section-title" style="margin-bottom: 10px;">Order Placed Successfully!</h1>
            <p style="font-size: 1.2rem; margin-bottom: 20px;">Thank you! Your order ID is <strong>#<?php echo htmlspecialchars($_GET['order_id'] ?? ''); ?></strong>.</p>
            <p style="color: var(--text-light); margin-bottom: 30px;">Redirecting you to your orders...</p>
            <a href="my-orders.php?success=1&order_id=<?php echo urlencode($_GET['order_id'] ?? ''); ?>" class="btn btn-primary">View My Orders</a>
            <script>
                // Auto-redirect after 2 seconds to prevent form resubmission
                setTimeout(function() {
                    window.location.replace('my-orders.php?success=1&order_id=<?php echo urlencode($_GET['order_id'] ?? ''); ?>');
                }, 2000);
            </script>
        </div>
    <?php else: ?>

        <h1 class="section-title">Secure Checkout</h1>

        <?php if (isset($error)): ?>
            <div style="background-color: var(--danger); color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form id="checkout-form" action="" method="POST" enctype="multipart/form-data" class="checkout-layout">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="place_order">
            <input type="hidden" id="razorpay_payment_id" name="razorpay_payment_id">

            <!-- SECTION 1: Billing & Shipping -->
            <div class="co-billing">
                <div class="co-card">
                    <h3>Billing &amp; Shipping Details</h3>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_name); ?>"
                            readonly>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_phone); ?>"
                            readonly>
                    </div>

                    <div class="form-group">
                        <label>Shipping Address <small
                                style="font-weight:400; color:var(--text-light);">(editable)</small></label>
                        <textarea name="shipping_address" rows="3" class="form-control"
                            required><?php echo htmlspecialchars($user_address); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Pincode <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="pincode" class="form-control" placeholder="Enter Pincode" pattern="[0-9]{6}" title="Please enter a valid 6-digit Pincode" required>
                    </div>

                    <div class="form-group">
                        <label>State/District <span style="color: var(--danger);">*</span></label>
                        <select name="state" id="checkout_state" class="form-control" required
                            onchange="updateCheckoutTotal()">
                            <option value="">Select State/District</option>
                            <?php foreach ($delivery_charges as $code => $data): ?>
                                <option value="<?php echo $code; ?>" data-charge="<?php echo $data['charge']; ?>">
                                    <?php echo $data['label']; ?> (₹<?php echo $data['charge']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
<style>
    .co-summary .cart-summary h3 {
        margin-bottom: 25px;
        font-family: var(--font-heading);
        color: var(--gold);
        font-size: 1.4rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        padding-bottom: 15px;
    }

    .checkout-item-card {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        background: rgba(255, 255, 255, 0.03);
        padding: 12px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .checkout-item-img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .checkout-item-info {
        flex: 1;
    }

    .checkout-item-name {
        font-size: 0.95rem;
        font-weight: 500;
        color: #fff;
        display: block;
        margin-bottom: 2px;
    }

    .checkout-item-meta {
        font-size: 0.85rem;
        color: var(--text-light);
    }

    .checkout-item-price {
        font-weight: 600;
        color: var(--gold);
    }
</style>
                </div>
            </div>

            <!-- SECTION 2: Order Summary (middle on mobile) -->
            <div class="co-summary">
                <div class="cart-summary">
                    <h3>Order Summary</h3>

                    <div style="margin-bottom: 25px;">
                        <?php foreach ($items as $i): ?>
                            <div class="checkout-item-card">
                                <?php 
                                    $img_src = $i['image'];
                                    if (!filter_var($img_src, FILTER_VALIDATE_URL)) {
                                        $img_src = FRONTEND_URL . ltrim($img_src, '/');
                                    }
                                ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="product" class="checkout-item-img">
                                <div class="checkout-item-info">
                                    <span class="checkout-item-name"><?php echo htmlspecialchars($i['name']); ?></span>
                                    <div class="checkout-item-meta">
                                        Qty: <?php echo $i['quantity']; ?> &bull; <span class="checkout-item-price">₹<?php echo number_format($i['price'], 2); ?></span>
                                    </div>
                                </div>
                                <div style="text-align: right; font-weight: 600; font-size: 0.95rem;">
                                    ₹<?php echo number_format($i['price'] * $i['quantity'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row" style="border-top: 1px dashed rgba(212,175,55,0.3); padding-top: 15px;">
                        <span>Subtotal:</span>
                        <span style="font-weight: 600;">₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Delivery Charge:</span>
                        <span id="final_delivery_display">Select state first</span>
                    </div>

                    <div class="summary-row summary-total">
                        <span>Final Total:</span>
                        <span id="final_total_display">₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>

                    <input type="hidden" id="final_total_amount" value="<?php echo $subtotal * 100; ?>">


                    <button type="button" id="pay-btn" class="btn btn-primary co-pay-btn" onclick="processPayment()">
                        <i class="fas fa-lock"></i> Pay Now
                    </button>

                    <div style="text-align: center; margin-top: 15px; font-size: 0.85rem; color: var(--text-light);">
                        <i class="fas fa-shield-alt"></i> 100% Secure Checkout &bull; SSL Encrypted
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Payment Options -->
            <div class="co-payment">
                <div class="co-card">
                    <h3>Payment Options</h3>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="razorpay" required checked>
                            <span style="font-weight: 500;">Pay Online (UPI, Credit Card, Debit Card, Net Banking)
                                <img src="https://razorpay.com/blog-content/uploads/2020/10/rzp-glyph-positive.png"
                                    alt="Razorpay"
                                    style="height: 20px; display: inline; vertical-align: middle; margin-left: 10px;">
                            </span>
                        </label>
                        <p style="margin-left: 25px; color: var(--text-light); font-size: 0.9rem;">Secure payment via
                            Razorpay.</p>
                    </div>
                </div>
            </div>

        </form>

    <?php endif; ?>
</div>

<!-- Razorpay Integration Checkout script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>

    const subtotal = <?php echo $subtotal; ?>;

    function updateCheckoutTotal() {
        const stateSelect = document.getElementById('checkout_state');
        const selectedOption = stateSelect.options[stateSelect.selectedIndex];

        let deliveryCost = 0;
        if (selectedOption.value !== "") {
            deliveryCost = parseFloat(selectedOption.getAttribute('data-charge')) || 0;
            document.getElementById('final_delivery_display').innerText = '₹' + deliveryCost.toFixed(2);
        } else {
            document.getElementById('final_delivery_display').innerText = 'Select state pending';
        }

        const total = subtotal + deliveryCost;
        document.getElementById('final_total_display').innerText = '₹' + total.toFixed(2);
        document.getElementById('final_total_amount').value = Math.round(total * 100);
    }

    function processPayment() {
        try {
            const form = document.getElementById('checkout-form');

            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const paymentMethodInput = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethodInput) {
                alert("Please select a payment method.");
                return;
            }
            const paymentMethod = paymentMethodInput.value;

            if (paymentMethod === 'razorpay') {
                if (typeof Razorpay === 'undefined') {
                    alert("Razorpay failed to load. Please disable ad-blockers or check your connection.");
                    return;
                }

                const finalAmount = parseInt(document.getElementById('final_total_amount').value);

                // Razorpay options
                var options = {
                    "key": "rzp_test_SOcwvJTvOP9S6a", // Updated to the test Key ID provided
                    "amount": finalAmount, // Amount is in currency subunits
                    "currency": "INR",
                    "name": "Royal Frames",
                    "description": "Premium Wooden Frames Purchase",
                    "image": "<?php echo FRONTEND_URL; ?>images/frames/12x18.jpg",
                    "handler": function (response) {
                        try {
                            // Save order details for frontend Telegram notification fallback
                            const stateSelect = document.getElementById('checkout_state');
                            const orderDetails = {
                                customer: <?php echo json_encode($user_name); ?>,
                                phone: <?php echo json_encode($user_phone); ?>,
                                address: document.querySelector('textarea[name="shipping_address"]').value + ' - ' + document.querySelector('input[name="pincode"]').value + ', ' + stateSelect.options[stateSelect.selectedIndex].text,
                                products: <?php echo json_encode($combined_product_names); ?>,
                                product_image: <?php echo json_encode(!empty($items[0]['image']) ? $items[0]['image'] : ''); ?>,
                                total: (parseFloat(document.getElementById('final_total_amount').value) / 100).toFixed(2),
                                order_id: 'pending' // Will be matched by order_id from URL on success page
                            };
                            localStorage.setItem('last_order_details', JSON.stringify(orderDetails));

                            // Pass the payment ID to the form and submit
                            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                            form.submit();
                        } catch (err) {
                            alert("Error submitting form after payment: " + err.message);
                        }
                    },
                    "prefill": {
                        "name": <?php echo json_encode($user_name); ?>,
                        "contact": <?php echo json_encode($user_phone); ?>
                    },
                    "theme": {
                        "color": "#111111"
                    }
                };
                var rzp1 = new Razorpay(options);
                rzp1.on('payment.failed', function (response) {
                    alert("Payment Failed: " + response.error.description);
                });
                rzp1.prompt !== undefined ? rzp1.prompt() : rzp1.open();
            }
        } catch (e) {
            alert("An error occurred trying to boot the payment window: " + e.message);
            console.error(e);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>