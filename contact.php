<?php
$pageTitle = 'Contact Us';
require_once 'includes/db.php';
include 'includes/header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    
    if (!empty($name) && !empty($message)) {
        // Logic to save message or send email could go here
        $success = true;
    } else {
        $error = 'Please fill out all required fields.';
    }
}
?>

<!-- Hero Section -->
<section style="position: relative; padding: 100px 0; color: white; text-align: center;">
    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;">
        <img src="images/bg.jpeg" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.4;">
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6);"></div>
    </div>
    <div class="container">
        <h1 style="font-size: 3rem; margin-bottom: 20px; color: var(--gold);">Get In Touch</h1>
        <p style="font-size: 1.2rem; max-width: 600px; margin: 0 auto;">We are here to assist you with any inquiries or custom orders.</p>
    </div>
</section>

<!-- Content Section -->
<section class="container" style="padding: 60px 0;">
    <div style="display: flex; flex-wrap: wrap; gap: 40px;">
        
        <!-- Contact Info -->
        <div style="flex: 1; min-width: 300px; background: var(--white); padding: 40px; border-radius: 8px; box-shadow: var(--shadow-sm);">
            <h2 style="color: var(--gold); margin-bottom: 30px;">Contact Information</h2>
            
            <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px;">
                <i class="fas fa-map-marker-alt" style="color: var(--accent); font-size: 1.5rem; margin-top: 5px;"></i>
                <div>
                    <h4 style="margin-bottom: 5px;">Our Location</h4>
                    <p style="color: var(--text-light); line-height: 1.6;">Royal Frames 149<br>Hyderabad, Telangana, India</p>
                </div>
            </div>
            
            <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px;">
                <i class="fas fa-phone-alt" style="color: var(--accent); font-size: 1.5rem; margin-top: 5px;"></i>
                <div>
                    <h4 style="margin-bottom: 5px;">Phone / WhatsApp</h4>
                    <p style="color: var(--text-light);"><a href="tel:7842347544" style="color: inherit; text-decoration: none;">7842347544</a></p>
                </div>
            </div>
            
            <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px;">
                <i class="fas fa-envelope" style="color: var(--accent); font-size: 1.5rem; margin-top: 5px;"></i>
                <div>
                    <h4 style="margin-bottom: 5px;">Email Support</h4>
                    <p style="color: var(--text-light);"><a href="mailto:support@royalframes.in" style="color: inherit; text-decoration: none;">support@royalframes.in</a></p>
                </div>
            </div>
            
            <h4 style="margin-top: 30px; margin-bottom: 15px;">Follow Us</h4>
            <div style="display: flex; gap: 15px;">
                <a href="https://instagram.com/royalframe149" target="_blank" style="width: 40px; height: 40px; border-radius: 50%; background: #E1306C; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; text-decoration: none;">
                    <i class="fab fa-instagram"></i>
                </a>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div style="flex: 2; min-width: 300px; background: var(--white); padding: 40px; border-radius: 8px; box-shadow: var(--shadow-sm);">
            <h2 style="color: var(--gold); margin-bottom: 30px;">Send Us a Message</h2>
            
            <?php if ($success): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> Thank you! Your message has been sent successfully. We will get back to you soon.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form action="contact" method="POST">
                <input type="hidden" name="action" value="contact">
                
                <div style="display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Your Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Email Address</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Subject</label>
                    <input type="text" name="subject" class="form-control">
                </div>
                
                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Message *</label>
                    <textarea name="message" class="form-control" rows="5" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
        
    </div>
</section>

<?php include 'includes/footer.php'; ?>
