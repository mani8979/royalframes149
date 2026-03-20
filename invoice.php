<?php
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch order
$query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Order not found or access denied.");
}

$order = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['id']; ?> - Royal Frames</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            margin: 0;
            padding: 20px;
            background: #f9f9f9;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
        }
        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
        }
        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }
        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }
        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
            font-weight: bold;
        }
        .invoice-box table tr.information table td {
            padding-bottom: 40px;
        }
        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.item.last td {
            border-bottom: none;
        }
        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }
        
        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td {
                width: 100%;
                display: block;
                text-align: center;
            }
            .invoice-box table tr.information table td {
                width: 100%;
                display: block;
                text-align: center;
            }
        }
        
        .print-btn {
            display: block;
            width: 150px;
            margin: 20px auto;
            padding: 10px;
            background: #D4AF37;
            color: #000;
            text-align: center;
            font-weight: bold;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        
        @media print {
            .print-btn {
                display: none;
            }
            body {
                background: #fff;
                padding: 0;
            }
            .invoice-box {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print Invoice</button>

    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title" style="color: #D4AF37; font-family: sans-serif;">
                                Royal Frames
                            </td>
                            
                            <td>
                                Invoice #: <?php echo $order['id']; ?><br>
                                Created: <?php echo date('d M Y', strtotime($order['order_date'])); ?><br>
                                Payment Status: <?php echo htmlspecialchars($order['payment_status']); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                Royal Frames, Inc.<br>
                                Visakhapatnam, Andhra Pradesh<br>
                                support@royalframes.in
                            </td>
                            
                            <td>
                                <strong>Billed To:</strong><br>
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?><br>
                                <?php echo htmlspecialchars($_SESSION['user_phone']); ?><br>
                                <?php echo nl2br(htmlspecialchars($order['address'])); ?><br>
                                <?php echo htmlspecialchars($order['state']); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="heading">
                <td>Item</td>
                <td>Price</td>
            </tr>
            
            <tr class="item">
                <td>
                    <?php echo htmlspecialchars($order['product_name']); ?>
                </td>
                <td>
                    ₹<?php echo number_format($order['price'], 2); ?>
                </td>
            </tr>
            
            <tr class="item">
                <td>Delivery Charge</td>
                <td>
                    ₹<?php echo number_format($order['delivery_charge'], 2); ?>
                </td>
            </tr>
            
            <tr class="total">
                <td></td>
                <td>
                   Total: ₹<?php echo number_format($order['total_price'], 2); ?>
                </td>
            </tr>
        </table>
        
        <div style="text-align: center; margin-top: 50px; font-size: 14px; color: #777;">
            Thank you for your business!<br>
            If you have any questions about this invoice, please contact support.
        </div>
    </div>
</body>
</html>
