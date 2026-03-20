<?php
$pageTitle = 'Register';
require_once 'includes/db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
        logSecurityEvent("csrf_failure", "Register form");
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($phone) || empty($address) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!isValidPhone($phone)) {
            $error = "Invalid phone number format (10 digits required).";
        } elseif (strlen($name) < 3) {
            $error = "Name must be at least 3 characters long.";
        } elseif (!isStrongPassword($password)) {
            $error = "Password must be at least 8 characters with uppercase, lowercase, and numbers.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Check if phone number already exists (use prepared statement)
            $check_query = "SELECT id FROM users WHERE phone = ?";
            $check_stmt = $conn->prepare($check_query);
            if (!$check_stmt) {
                error_log("Prepare failed: " . $conn->error);
                $error = "Database error occurred. Please try again.";
            } else {
                $check_stmt->bind_param("s", $phone);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $error = "An account with this phone number already exists.";
                    logSecurityEvent("registration_duplicate", "Phone: $phone");
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user with prepared statement
                    $insert_query = "INSERT INTO users (name, phone, address, password) VALUES (?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    if (!$insert_stmt) {
                        error_log("Prepare failed: " . $conn->error);
                        $error = "Database error occurred. Please try again.";
                    } else {
                        $insert_stmt->bind_param("ssss", $name, $phone, $address, $hashed_password);
                        
                        if ($insert_stmt->execute()) {
                            // Set session and redirect
                            $_SESSION['user_id'] = $insert_stmt->insert_id;
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_phone'] = $phone;
                            $_SESSION['user_address'] = $address;
                            logSecurityEvent("registration_success", "Phone: $phone");

                            header("Location: shop.php");
                            exit();
                        } else {
                            error_log("Insert failed: " . $conn->error);
                            $error = "Registration failed. Please try again.";
                        }
                        $insert_stmt->close();
                    }
                }
                $check_stmt->close();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container" style="padding: 60px 0; min-height: 60vh;">
    <div class="form-container">
        <h2 style="text-align: center; margin-bottom: 30px;">Create an Account</h2>

        <?php if ($error): ?>
            <div
                style="background-color: var(--danger); color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" required minlength="3"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    placeholder="At least 3 characters">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control" required
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                    placeholder="10 digit phone number" pattern="[0-9]{10}">
            </div>

            <div class="form-group">
                <label for="address">Full Delivery Address</label>
                <textarea id="address" name="address" rows="3" class="form-control"
                    required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required minlength="8"
                        placeholder="Min 8 chars, uppercase, lowercase, numbers">
                    <span class="password-toggle-icon"><i class="fas fa-eye"></i></span>
                </div>
                <small style="color: #666; margin-top: 5px; display: block;">
                    Must contain: uppercase letter, lowercase letter, and number
                </small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-toggle-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                    <span class="password-toggle-icon"><i class="fas fa-eye"></i></span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Register</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <p>Already have an account? <a href="login.php" style="color: var(--classic-wood); font-weight: 500;">Login
                    here</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>