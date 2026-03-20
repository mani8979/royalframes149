<?php
$pageTitle = 'My Account';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . FRONTEND_URL . "login.php?msg=login_required");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';

// Fetch user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $error_msg = "Error loading profile. Please try again.";
    $user_data = [];
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $error_msg = "User not found.";
        $user_data = [];
    }
    $stmt->close();
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_msg = "Security validation failed. Please try again.";
        logSecurityEvent("csrf_failure", "Profile update form");
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validate inputs
        if (empty($name)) {
            $error_msg = "Name is required.";
        } elseif (empty($phone)) {
            $error_msg = "Phone number is required.";
        } elseif (!isValidPhone($phone)) {
            $error_msg = "Invalid phone number format (10 digits required).";
        } elseif (empty($address)) {
            $error_msg = "Address is required.";
        } elseif (!empty($password) && !empty($password_confirm)) {
            // Password update requested
            if ($password !== $password_confirm) {
                $error_msg = "Passwords do not match.";
            } elseif (!isStrongPassword($password)) {
                $error_msg = "Password must be at least 8 characters with uppercase, lowercase, and numbers.";
            } else {
                // Update with password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $update_query = "UPDATE users SET name = ?, phone = ?, address = ?, password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                if (!$update_stmt) {
                    $error_msg = "Database error occurred. Please try again.";
                } else {
                    $update_stmt->bind_param("ssssi", $name, $phone, $address, $hashed_password, $user_id);
                    if ($update_stmt->execute()) {
                        // Update session variables
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_phone'] = $phone;
                        $_SESSION['user_address'] = $address;
                        $user_data['name'] = $name;
                        $user_data['phone'] = $phone;
                        $user_data['address'] = $address;
                        $success_msg = "Profile updated successfully!";
                        $edit_mode = false;
                        logSecurityEvent("profile_updated", "User ID: $user_id - Password changed");
                    } else {
                        $error_msg = "Error updating profile. Please try again.";
                    }
                    $update_stmt->close();
                }
            }
        } else {
            // Update without password
            $update_query = "UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            if (!$update_stmt) {
                $error_msg = "Database error occurred. Please try again.";
            } else {
                $update_stmt->bind_param("sssi", $name, $phone, $address, $user_id);
                if ($update_stmt->execute()) {
                    // Update session variables
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_phone'] = $phone;
                    $_SESSION['user_address'] = $address;
                    $user_data['name'] = $name;
                    $user_data['phone'] = $phone;
                    $user_data['address'] = $address;
                    $success_msg = "Profile updated successfully!";
                    $edit_mode = false;
                    logSecurityEvent("profile_updated", "User ID: $user_id");
                } else {
                    $error_msg = "Error updating profile. Please try again.";
                }
                $update_stmt->close();
            }
        }
    }
}

// Fetch user orders
$orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5";
$orders_stmt = $conn->prepare($orders_query);
$orders = [];
if ($orders_stmt) {
    $orders_stmt->bind_param("i", $user_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    while ($order = $orders_result->fetch_assoc()) {
        $orders[] = $order;
    }
    $orders_stmt->close();
}

include 'includes/header.php';
?>

<div class="container" style="padding: 16px; min-height: 60vh; max-width: 1200px; margin: 0 auto;">
    <!-- Flipkart Style Account Top Card -->
    <div class="account-top-card">
        <div class="account-user-name"><?php echo htmlspecialchars($user_data['name'] ?? 'User'); ?></div>
        <div class="account-membership">Membership ID: #<?php echo htmlspecialchars($user_id); ?></div>
        <div style="margin-top: 10px;">
            <a href="<?php echo FRONTEND_URL; ?>profile.php?edit=1" style="color: var(--primary-blue); font-size: 14px; text-decoration: none; font-weight: 500;">Edit Profile</a>
            <span style="margin: 0 8px; color: #ccc;">|</span>
            <a href="<?php echo FRONTEND_URL; ?>logout.php" style="color: var(--danger); font-size: 14px; text-decoration: none; font-weight: 500;">Logout</a>
        </div>
    </div>

    <!-- Action Buttons Grid (Phase 2) -->
    <div class="action-buttons-grid">
        <a href="<?php echo FRONTEND_URL; ?>my-orders.php" class="account-action-btn">
            <i class="fas fa-box"></i>
            <span>Orders</span>
        </a>
        <a href="<?php echo FRONTEND_URL; ?>wishlist.php" class="account-action-btn">
            <i class="fas fa-heart"></i>
            <span>Wishlist</span>
        </a>

        <a href="https://wa.me/917842347544" target="_blank" class="account-action-btn">
            <i class="fas fa-headset"></i>
            <span>Customer Support</span>
        </a>
        <a href="logout.php" class="account-action-btn" style="color: var(--danger);">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <?php if ($success_msg): ?>
        <div style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin: 20px 0; font-size: 14px;">
            ✅ <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin: 20px 0; font-size: 14px;">
            ❌ <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($edit_mode): ?>
        <!-- Edit Form Backdrop/Section -->
        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-top: 20px;">
            <h2 style="font-size: 18px; margin-bottom: 20px;">Edit Profile</h2>
            <form method="POST" action="<?php echo FRONTEND_URL; ?>profile.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="update_profile" value="1">

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="name" style="font-size: 14px; color: #666; margin-bottom: 5px; display: block;">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" style="width: 100%; border: 1px solid var(--border-color); padding: 10px; border-radius: 8px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="phone" style="font-size: 14px; color: #666; margin-bottom: 5px; display: block;">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" style="width: 100%; border: 1px solid var(--border-color); padding: 10px; border-radius: 8px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="address" style="font-size: 14px; color: #666; margin-bottom: 5px; display: block;">Address</label>
                    <textarea id="address" name="address" class="form-control" required rows="3" style="width: 100%; border: 1px solid var(--border-color); padding: 10px; border-radius: 8px;"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; height: 44px; border-radius: 8px; background: var(--primary-blue); color: white; border: none; font-weight: 600;">Save</button>
                    <a href="<?php echo FRONTEND_URL; ?>profile.php" class="btn btn-secondary" style="flex: 1; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); text-decoration: none; color: #333;">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Recent Orders Compact Section -->
    <div style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="font-size: 16px; margin: 0;">Recent Orders</h3>
            <a href="<?php echo FRONTEND_URL; ?>my-orders.php" style="color: var(--primary-blue); font-size: 14px; text-decoration: none;">View All</a>
        </div>
        
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 13px; color: #666;">ID: #<?php echo htmlspecialchars($order['id']); ?></span>
                        <span style="font-size: 13px; font-weight: 600; color: var(--price-green);">₹<?php echo number_format($order['total_price'], 2); ?></span>
                    </div>
                    <div style="font-size: 14px; font-weight: 500; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo htmlspecialchars($order['product_name']); ?>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: #888;"><?php echo date('d M Y', strtotime($order['order_date'])); ?></span>
                        <a href="track-order.php?id=<?php echo $order['id']; ?>" style="font-size: 13px; color: var(--primary-blue); text-decoration: none; font-weight: 600;">Track Order</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 30px; color: #888; background: #fdfdfd; border-radius: 12px; border: 1px dashed #ccc;">
                No orders yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
