<?php
$pageTitle = 'Track Order';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch order
$query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    header("Location: my-orders.php");
    exit();
}
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my-orders.php");
    exit();
}

$order = $result->fetch_assoc();
$stmt->close();

$stages = [
    'Order Placed' => ['icon' => 'fa-clipboard-list', 'desc' => 'Your order has been received.'],
    'Processing' => ['icon' => 'fa-cog', 'desc' => 'Your frames are being crafted.'],
    'Shipped' => ['icon' => 'fa-shipping-fast', 'desc' => 'Your order has been dispatched via courier.'],
    'Out for Delivery' => ['icon' => 'fa-truck', 'desc' => 'Couriers are out to deliver your package.'],
    'Delivered' => ['icon' => 'fa-check-circle', 'desc' => 'Your order has been delivered successfully.']
];

$current_status = $order['status'];
$status_keys = array_keys($stages);

// If status is not in the normal flow (e.g. Cancelled)
if (!in_array($current_status, $status_keys)) {
    if ($current_status == 'Cancelled') {
        $stages = [
            'Order Placed' => ['icon' => 'fa-clipboard-list', 'desc' => 'Order was received.'],
            'Cancelled' => ['icon' => 'fa-times-circle', 'desc' => 'This order has been cancelled.']
        ];
        $status_keys = array_keys($stages);
    }
}

$current_idx = array_search($current_status, $status_keys);
if ($current_idx === false) $current_idx = -1;

include 'includes/header.php';
?>

<style>
/* ===== TRACK ORDER PAGE STYLES ===== */
.timeline-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* DESKTOP: Horizontal timeline */
.order-status-timeline {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
    padding: 10px 30px;
    margin-bottom: 20px;
}

/* Desktop connector line */
.order-status-timeline::before {
    content: '';
    position: absolute;
    top: 30px;
    left: 60px;
    right: 60px;
    height: 3px;
    background: #e0e0e0;
    z-index: 0;
}

.timeline-step {
    position: relative;
    z-index: 1;
    text-align: center;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.step-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #e0e0e0;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    border: 3px solid white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    margin-bottom: 8px;
}

.timeline-step.completed .step-icon {
    background: #28a745;
}

.timeline-step.active .step-icon {
    background: #2874F0;
    box-shadow: 0 0 0 5px rgba(40,116,240,0.2);
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%   { box-shadow: 0 0 0 0 rgba(40,116,240,0.4); }
    70%  { box-shadow: 0 0 0 8px rgba(40,116,240,0); }
    100% { box-shadow: 0 0 0 0 rgba(40,116,240,0); }
}

.step-label {
    font-size: 12px;
    color: #999;
    font-weight: 500;
    text-align: center;
}

.timeline-step.completed .step-label,
.timeline-step.active .step-label {
    color: #212121;
    font-weight: 700;
}

/* ===== MOBILE: Clean vertical progress bar ===== */
@media (max-width: 600px) {
    .order-status-timeline {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px 0 10px 10px;
        margin-bottom: 10px;
    }

    /* Remove desktop horizontal connector */
    .order-status-timeline::before {
        display: none;
    }

    .timeline-step {
        flex-direction: row;
        align-items: center;
        text-align: left;
        flex: none;
        width: 100%;
        margin-bottom: 0;
        position: relative;
    }

    /* Vertical connector between steps */
    .timeline-step:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 21px;
        top: 44px;
        width: 2px;
        height: 30px;
        background: #e0e0e0;
        z-index: 0;
    }

    .timeline-step.completed:not(:last-child)::after {
        background: #28a745;
    }

    .step-icon {
        flex-shrink: 0;
        margin: 0 14px 0 0;
        width: 44px;
        height: 44px;
    }

    .step-label {
        font-size: 14px;
        font-weight: 600;
        color: #555;
        padding: 14px 0;
    }

    .timeline-step.active .step-label {
        color: #2874F0;
    }

    .timeline-step.completed .step-label {
        color: #28a745;
    }
}

/* ===== INVOICE BUTTON (compact) ===== */
.invoice-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background: #28a745;
    color: white;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
}

.invoice-btn:hover {
    background: #218838;
}
</style>

<div class="container" style="padding: 20px 12px; min-height: 60vh;">
    <!-- Compact Header with Invoice Button -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h1 style="font-size: 18px; margin: 0;">Track Order</h1>
        </div>
        <a href="invoice.php?id=<?php echo $order['id']; ?>" target="_blank" class="invoice-btn">
            <i class="fas fa-file-download"></i> <span class="hide-mobile">Download Invoice</span>
        </a>
    </div>

    <!-- Order Info Card -->
    <div style="background: white; padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span style="font-size: 14px; color: #666;">Order ID: #<?php echo $order['id']; ?></span>
            <span style="font-size: 14px; font-weight: 600; color: var(--price-green);">₹<?php echo number_format($order['total_price'], 2); ?></span>
        </div>
        <div style="font-size: 13px; color: #888;">Placed on: <?php echo date('d M Y', strtotime($order['order_date'])); ?></div>
    </div>

    <!-- Advanced Orders Timeline (Phase 2) -->
    <div class="timeline-container">
        <h3 style="font-size: 15px; margin-bottom: 20px; font-weight: 600;">Order Status</h3>
        
        <div class="order-status-timeline">
            <?php 
            $flipkart_stages = [
                'Order Placed' => ['label' => 'Placed', 'icon' => 'fa-clipboard-list'],
                'Processing' => ['label' => 'Packed', 'icon' => 'fa-cog'],
                'Shipped' => ['label' => 'Shipped', 'icon' => 'fa-shipping-fast'],
                'Out for Delivery' => ['label' => 'Arriving', 'icon' => 'fa-truck'],
                'Delivered' => ['label' => 'Delivered', 'icon' => 'fa-check-circle']
            ];
            
            foreach ($flipkart_stages as $stage_name => $stage_info) {
                $stage_idx = array_search($stage_name, $status_keys);
                if ($stage_idx === false && $current_status == 'Cancelled' && $stage_name != 'Order Placed') continue;

                $is_completed = ($stage_idx !== false && $stage_idx < $current_idx);
                $is_active = ($stage_idx !== false && $stage_idx == $current_idx);
                
                $step_class = 'timeline-step';
                if ($is_completed) $step_class .= ' completed';
                if ($is_active) $step_class .= ' active';
            ?>
                <div class="<?php echo $step_class; ?>">
                    <div class="step-icon">
                        <i class="fas <?php echo $stage_info['icon']; ?>"></i>
                    </div>
                    <div class="step-label"><?php echo $stage_info['label']; ?></div>
                </div>
            <?php } ?>
        </div>

        <div style="padding: 20px 0 0; border-top: 1px solid #f0f0f0; margin-top: 20px;">
            <p style="font-size: 14px; color: #333; margin: 0; font-weight: 500; display: flex; align-items: flex-start; gap: 10px;">
                <i class="fas fa-info-circle" style="color: var(--primary-blue); margin-top: 3px;"></i>
                <span><?php echo $stages[$current_status]['desc']; ?></span>
            </p>
        </div>
    </div>

    <!-- Delivery Detail -->
    <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E0E0E0; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <h3 style="font-size: 15px; margin-bottom: 12px; font-weight: 600;">Delivery Address</h3>
        <div style="font-size: 14px; line-height: 1.6; color: #212121;">
            <div style="font-weight: 600; margin-bottom: 4px;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Customer'); ?></div>
            <div style="color: #666; font-size: 13px;">
                <?php echo nl2br(htmlspecialchars($order['address'])); ?><br>
                <?php echo htmlspecialchars($order['state'] ?? ''); ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
