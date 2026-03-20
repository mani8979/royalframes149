<?php
$pageTitle = 'About Us';
require_once 'includes/db.php';
include 'includes/header.php';
?>

<!-- Hero Section -->
<section style="position: relative; padding: 100px 0; color: white; text-align: center;">
    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;">
        <img src="images/bg.jpeg" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.4;">
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6);"></div>
    </div>
    <div class="container">
        <h1 style="font-size: 3rem; margin-bottom: 20px; color: var(--gold);">About Royal Frames</h1>
        <p style="font-size: 1.2rem; max-width: 600px; margin: 0 auto;">Crafting Memories into Legacies Since 2010</p>
    </div>
</section>

<!-- Content Section -->
<section class="container" style="padding: 60px 0; min-height: 40vh;">
    <div style="max-width: 800px; margin: 0 auto; background: var(--white); padding: 40px; border-radius: 8px; box-shadow: var(--shadow-sm); line-height: 1.8;">
        
        <h2 style="color: var(--gold); margin-bottom: 20px;">Our Story</h2>
        <p style="color: var(--text-light); margin-bottom: 30px;">
            Royal Frames started with a simple vision: to provide premium, elegant, and timeless ways to preserve life's most cherished memories. We believe that every photo tells a unique story, and it deserves a frame that reflects its beauty and significance. Our frames are meticulously crafted using high-quality engineered wood, ensuring durability and a sophisticated finish.
        </p>

        <h2 style="color: var(--gold); margin-bottom: 20px;">Why Choose Us?</h2>
        <ul style="color: var(--text-light); margin-bottom: 30px; margin-left: 20px; list-style-type: disc;">
            <li style="margin-bottom: 10px;"><strong>Premium Quality:</strong> We source the finest materials to construct our frames.</li>
            <li style="margin-bottom: 10px;"><strong>Handcrafted Perfection:</strong> Every frame is carefully inspected for flaws.</li>
            <li style="margin-bottom: 10px;"><strong>PAN India Delivery:</strong> Safe and secure shipping across India.</li>
            <li style="margin-bottom: 10px;"><strong>Customer Satisfaction:</strong> We pride ourselves on exceptional customer service and support.</li>
        </ul>

        <div style="text-align: center; margin-top: 40px;">
            <a href="shop.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">Explore Our Collection</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
