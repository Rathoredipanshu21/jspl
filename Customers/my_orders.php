<?php
session_start();

// Include your database connection file
// Make sure the path is correct
include '../config/db.php';

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['party_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['party_id'];
$orders = [];

// Fetch all orders and their items for the logged-in user
$sql = "SELECT 
            co.order_id, 
            co.order_message, 
            co.status,
            co.rejection_condition,
            DATE_FORMAT(co.created_at, '%d %b %Y, %h:%i %p') as formatted_date,
            DATE_FORMAT(co.created_at, '%d-%m-%Y') as invoice_date,
            oi.quantity,
            g.product_name,
            g.unit
        FROM customer_orders co
        JOIN order_items oi ON co.order_id = oi.order_id
        JOIN goods g ON oi.product_id = g.id
        WHERE co.user_id = ?
        ORDER BY co.created_at DESC";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Error preparing statement: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[$row['order_id']]['details']['order_message'] = $row['order_message'];
        $orders[$row['order_id']]['details']['formatted_date'] = $row['formatted_date'];
        $orders[$row['order_id']]['details']['invoice_date'] = $row['invoice_date'];
        $orders[$row['order_id']]['details']['status'] = $row['status'];
        $orders[$row['order_id']]['details']['rejection_condition'] = $row['rejection_condition'];
        
        $orders[$row['order_id']]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'unit' => $row['unit']
        ];
    }
}
$stmt->close();

// Fetch logged-in party's details for the invoice
$party_sql = "SELECT business_name, owner_name, address, gst_uin, state, pincode FROM parties WHERE id = ?";
$party_stmt = $conn->prepare($party_sql);
$party_stmt->bind_param("i", $user_id);
$party_stmt->execute();
$party_result = $party_stmt->get_result();
$party_details = $party_result->fetch_assoc();
$party_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --background-color: #f4f7f6;
            --card-bg: #ffffff;
            --text-color: #333;
            --light-gray: #ecf0f1;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 40px;
            font-weight: 700;
            font-size: 2.5rem;
        }
        .order-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            margin-bottom: 25px;
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .order-info { display: flex; align-items: center; gap: 20px; }
        .order-icon {
            font-size: 1.8rem; color: var(--secondary-color); background-color: #eaf5fc;
            width: 60px; height: 60px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .order-details h3 { margin: 0 0 5px 0; color: var(--primary-color); font-size: 1.2rem; }
        .order-details p { margin: 0; color: #7f8c8d; font-size: 0.95rem; }
        .order-right-panel { display: flex; align-items: center; gap: 15px; }
        .order-actions { display: flex; gap: 10px; }
        
        .view-btn, .invoice-btn, .print-btn {
            color: #fff; padding: 10px 20px; border: none; border-radius: 8px;
            cursor: pointer; font-size: 0.9rem; font-weight: 500;
            transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;
        }
        .view-btn:hover, .invoice-btn:hover, .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .view-btn { background-color: var(--secondary-color); }
        .invoice-btn { background-color: var(--success-color); }
        .print-btn { background-color: #9b59b6; } /* Purple for print button */

        .status-badge {
            padding: 6px 15px; border-radius: 20px; font-size: 0.8rem;
            font-weight: 600; color: #fff; text-transform: uppercase;
        }
        .status-Accepted { background-color: var(--success-color); }
        .status-Pending { background-color: var(--warning-color); }
        .status-Rejected { background-color: var(--danger-color); }

        /* Modal Styles */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; overflow: auto;
            background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: #fefefe; margin: 5% auto; padding: 30px; border: none;
            width: 90%; max-width: 600px; border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;
        }
        .modal-header h2 { margin: 0; color: var(--primary-color); font-size: 1.5rem; }
        .modal-header-actions { display: flex; align-items: center; gap: 15px; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s; }
        .close-btn:hover, .close-btn:focus { color: var(--danger-color); }
        
        /* Invoice Modal Specific Styles */
        #invoiceModal .modal-content { max-width: 800px; }
        .invoice-box {
            padding: 20px; border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 14px; line-height: 22px; color: #555;
        }
        .invoice-header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .invoice-header-left, .invoice-header-right { width: 48%; }
        .invoice-header-right { text-align: right; }
        .invoice-header h4 { margin: 0; font-size: 1.2em; color: var(--primary-color); }
        .invoice-header p { margin: 2px 0; }
        .invoice-center-details { text-align: center; margin-bottom: 30px; }
        .invoice-center-details h2 { margin: 0; color: #333; }
        .invoice-center-details img { max-width: 150px; margin-bottom: 10px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 20px;}
        .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .invoice-table th { background-color: #f2f2f2; font-weight: 600; }
        .invoice-footer { margin-top: 30px; }
        .invoice-footer .bank-details, .invoice-footer .signature { width: 48%; display: inline-block; vertical-align: top; }
        .invoice-footer .signature { text-align: right; }
        .invoice-footer h5 { margin: 0 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; }

        /* Print Styles */
        @media print {
            body > *:not(.print-area) {
                display: none;
            }
            .print-area {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }
            .modal-content {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 0 !important;
            }
            .modal-header {
                display: none !important;
            }
            .invoice-box {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h1><i class="fas fa-receipt"></i> My Order History</h1>
        <div id="orders-list">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order_id => $order_data): ?>
                    <div class="order-card" data-aos="fade-up" data-aos-duration="600">
                        <div class="order-info">
                            <div class="order-icon"><i class="fas fa-box-open"></i></div>
                            <div class="order-details">
                                <h3><?php echo htmlspecialchars($order_id); ?></h3>
                                <p><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($order_data['details']['formatted_date']); ?></p>
                            </div>
                        </div>
                        <div class="order-right-panel">
                            <div class="order-actions">
                                <button class="view-btn" onclick="openDetailsModal('<?php echo htmlspecialchars($order_id); ?>')">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                                <?php if ($order_data['details']['status'] === 'Accepted'): ?>
                                    <button class="invoice-btn" onclick="openInvoiceModal('<?php echo htmlspecialchars($order_id); ?>')">
                                        <i class="fas fa-file-invoice"></i> View Invoice
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="status-badge status-<?php echo htmlspecialchars($order_data['details']['status']); ?>">
                                <?php echo htmlspecialchars($order_data['details']['status']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-orders" data-aos="fade-in">
                    <i class="fas fa-shopping-cart"></i>
                    <p>You haven't placed any orders yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Order Details</h2>
                <span class="close-btn" onclick="closeModal('detailsModal')">&times;</span>
            </div>
            <div class="modal-body">
                <ul id="modal-items-list" style="list-style: none; padding: 0;"></ul>
                <div id="modal-order-message" style="display: none; margin-top: 20px; background: #fffbe6; border-left: 4px solid #f1c40f; padding: 15px; border-radius: 5px;">
                    <p><strong><i class="fas fa-comment-dots"></i> Your Message:</strong> <span id="message-text"></span></p>
                </div>
                 <div id="modal-rejection-condition" style="display: none; margin-top: 15px; background: #ffebee; border-left: 4px solid var(--danger-color); padding: 15px; border-radius: 5px;">
                    <p><strong><i class="fas fa-exclamation-triangle"></i> Rejection Condition:</strong> <span id="condition-text"></span></p>
                </div>
            </div>
        </div>
    </div>
    
    <div id="invoiceModal" class="modal">
         <div class="modal-content">
            <div class="modal-header">
                <h2>Dispatch Details</h2>
                <div class="modal-header-actions">
                    <button class="print-btn" onclick="printInvoice()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <span class="close-btn" onclick="closeModal('invoiceModal')">&times;</span>
                </div>
            </div>
            <div class="modal-body">
                <div class="invoice-box" id="invoice-content">
                    </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
        const ordersData = <?php echo json_encode($orders); ?>;
        const partyData = <?php echo json_encode($party_details); ?>;

        function openDetailsModal(orderId) {
            // ... (code is unchanged, kept for brevity)
        }

        function openInvoiceModal(orderId) {
            const order = ordersData[orderId];
            if (!order) return;
            
            let itemsHtml = '';
            let serial = 1;

            order.items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td>${serial++}</td>
                        <td>${item.product_name}</td>
                        <td>${item.quantity} ${item.unit}</td>
                    </tr>
                `;
            });
            
            const invoiceHtml = `
                <div class="invoice-center-details">
                    <img src="../Assets/logo.jpg" alt="Company Logo" style="opacity: 0.7;">
                    <h2>Dispatch Summary</h2>
                    <p><strong>Order ID:</strong> ${orderId}</p>
                    <p><strong>Date:</strong> ${order.details.invoice_date}</p>
                </div>
                <div class="invoice-header">
                    <div class="invoice-header-left">
                        <h4>JSPL STEEL</h4>
                        <p>Third Floor, Plot No-747, Khata No-11,</p>
                        <p>Dhanbad, Jharkhand - 828109</p>
                    </div>
                    <div class="invoice-header-right">
                        <h4>Billed To:</h4>
                        <p><strong>${partyData.business_name}</strong></p>
                        <p>${partyData.address}, ${partyData.state} - ${partyData.pincode}</p>
                        <p><strong>GSTIN:</strong> ${partyData.gst_uin}</p>
                    </div>
                </div>
                <table class="invoice-table">
                    <thead>
                        <tr><th>S.No.</th><th>Item Description</th><th>Quantity</th></tr>
                    </thead>
                    <tbody>${itemsHtml}</tbody>
                </table>
                 <div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                     <strong>Note:</strong> ${order.details.order_message || 'No specific instructions.'}
                 </div>
                <div class="invoice-footer">
                    <div class="bank-details">
                        <h5>Bank Details</h5>
                        <p><strong>A/C NAME:</strong> JSPL STEEL</p>
                        <p><strong>A/C NO.:</strong> 50200113154873</p>
                        <p><strong>IFSC:</strong> HDFC0008981</p>
                        <p><strong>BANK:</strong> HDFC BANK LTD, DHAIYA</p>
                    </div>
                    <div class="signature">
                        <h5>For JSPL STEEL</h5><br><br><br>
                        <p>Authorised Signatory</p>
                    </div>
                </div>
            `;
            document.getElementById('invoice-content').innerHTML = invoiceHtml;
            document.getElementById('invoiceModal').style.display = 'block';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
            // Remove print-area class when modal is closed
            if (modal.classList.contains('print-area')) {
                modal.classList.remove('print-area');
            }
        }

        function printInvoice() {
            const modal = document.getElementById('invoiceModal');
            // Add a class to the modal to target it with print styles
            modal.classList.add('print-area');
            window.print();
        }

        // Optional: Remove the print-area class after printing
        window.onafterprint = () => {
            const modal = document.querySelector('.print-area');
            if (modal) {
                modal.classList.remove('print-area');
            }
        };

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        };

        // This is the same function from the previous step, included for completeness
        function openDetailsModal(orderId) {
            const order = ordersData[orderId];
            if (!order) return;

            document.getElementById('modal-title').innerHTML = `<i class="fas fa-receipt"></i> ${orderId}`;
            const itemsList = document.getElementById('modal-items-list');
            itemsList.innerHTML = '';
            order.items.forEach(item => {
                const li = document.createElement('li');
                li.innerHTML = `<span style="font-weight: 600;"><i class="fas fa-tag"></i> ${item.product_name}</span> <span style="font-weight: 700; color: var(--secondary-color);">Qty: ${item.quantity}</span>`;
                li.style.cssText = 'display: flex; justify-content: space-between; padding: 15px; border-radius: 8px; margin-bottom: 10px; background-color: #f8f9fa;';
                itemsList.appendChild(li);
            });

            const messageDiv = document.getElementById('modal-order-message');
            if (order.details.order_message) {
                document.getElementById('message-text').textContent = order.details.order_message;
                messageDiv.style.display = 'block';
            } else {
                messageDiv.style.display = 'none';
            }
            
            const conditionDiv = document.getElementById('modal-rejection-condition');
            if(order.details.status === 'Rejected' && order.details.rejection_condition){
                document.getElementById('condition-text').textContent = order.details.rejection_condition;
                conditionDiv.style.display = 'block';
            } else {
                 conditionDiv.style.display = 'none';
            }

            document.getElementById('detailsModal').style.display = 'block';
        }
    </script>
</body>
</html>