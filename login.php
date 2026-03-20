<?php
$pageTitle = 'Login';
require_once 'includes/db.php';

// If already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . FRONTEND_URL . "shop.php");
    exit();
}

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'shop.php';

// Security: Prevent open redirect vulnerabilities and normalize .php extension
if ($redirect !== 'checkout.php' && $redirect !== 'cart.php' && $redirect !== 'shop.php' && $redirect !== 'my-orders.php' && $redirect !== 'profile.php') {
    $redirect = 'shop.php';
}
if (strpos($redirect, '.php') === false) {
    $redirect .= '.php';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
        logSecurityEvent("csrf_failure", "Login form");
    } else {
        $phone = $conn->real_escape_string(trim($_POST['phone']));
        $password = $_POST['password'];

        if (empty($phone) || empty($password)) {
            $error = "Please enter both phone number and password.";
            logSecurityEvent("login_attempt", "Empty credentials attempt");
        } elseif (!isValidPhone($phone)) {
            $error = "Invalid phone number format (10 digits required).";
            logSecurityEvent("login_attempt", "Invalid phone format");
        } else {
            // Use prepared statement to prevent SQL injection
            $query = "SELECT * FROM users WHERE phone = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                $error = "Database error occurred. Please try again.";
            } else {
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password'])) {
                        // Password valid, set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_phone'] = $user['phone'];
                        $_SESSION['user_address'] = $user['address'];
                        logSecurityEvent("login_success", "User ID: " . $user['id']);

                        header("Location: " . FRONTEND_URL . $redirect);
                        exit();
                    } else {
                        $error = "Invalid password.";
                        logSecurityEvent("login_attempt", "Wrong password for phone: $phone");
                    }
                } else {
                    $error = "No account found with this phone number.";
                    logSecurityEvent("login_attempt", "Unknown phone: $phone");
                }
                $stmt->close();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container" style="padding: 60px 0; min-height: 60vh;">
    <div class="form-container">
        <h2 style="text-align: center; margin-bottom: 30px;">Login to Your Account</h2>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'login_required'): ?>
            <div
                style="background-color: var(--light-wood); color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
                Please login or register to complete your purchase.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                style="background-color: var(--danger); color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php?redirect=<?php echo urlencode($redirect); ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control" required
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                    placeholder="10 digit phone number">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required>
                    <span class="password-toggle-icon"><i class="fas fa-eye"></i></span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Login</button>
            <div style="text-align: center; margin-top: 15px;">
                <p style="font-size: 14px; color: #666;">Forgot password? Contact Us: <a href="tel:+917842347544" style="color: var(--classic-wood); font-weight: 500;">+91 7842347544</a></p>
            </div>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <p>Don't have an account? <a href="register.php"
                    style="color: var(--classic-wood); font-weight: 500;">Register here</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>