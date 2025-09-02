<?php
session_start();

// --- Database Connection ---
// Ensure this path is correct for your project structure.
include '../config/db.php';

// --- Authentication Check ---
// Redirect to login if the user is not authenticated.
if (!isset($_SESSION['party_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['party_id'];
$orders = [];

// --- Data Fetching ---
// Fetch all orders and their associated items for the logged-in user.
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
    // It's better to log errors than to display them directly in production.
    error_log("Error preparing statement: " . $conn->error);
    die("An unexpected error occurred. Please try again later.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Organize the fetched data into a structured array.
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $order_id = $row['order_id'];
        if (!isset($orders[$order_id])) {
            $orders[$order_id] = [
                'details' => [
                    'order_message' => $row['order_message'],
                    'formatted_date' => $row['formatted_date'],
                    'invoice_date' => $row['invoice_date'],
                    'status' => $row['status'],
                    'rejection_condition' => $row['rejection_condition'],
                ],
                'items' => []
            ];
        }
        $orders[$order_id]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'unit' => $row['unit']
        ];
    }
}
$stmt->close();

// Fetch details of the logged-in party for the invoice.
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
    <title>My Order History</title>
    
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Custom styles for a more polished look */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* A lighter gray background */
        }

        /* Status Badge Colors */
        .status-Accepted { background-color: #10b981; color: white; } /* Emerald 500 */
        .status-Pending { background-color: #f59e0b; color: white; } /* Amber 500 */
        .status-Rejected { background-color: #ef4444; color: white; } /* Red 500 */

        /* Modal animation */
        .modal-enter {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }
        .modal-enter-active {
            opacity: 1;
            transform: scale(1) translateY(0);
            transition: opacity 300ms, transform 300ms;
        }
        .modal-leave-active {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
            transition: opacity 300ms, transform 300ms;
        }
        
        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
            }
            #invoice-content, #invoice-content * {
                visibility: visible;
            }
            #invoice-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="antialiased text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <header class="text-center mb-8 md:mb-12">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 tracking-tight">
                <i class="fas fa-receipt text-blue-600"></i> My Order History
            </h1>
            <p class="mt-2 text-md text-gray-600">Track and manage all your past orders in one place.</p>
        </header>

        <main id="orders-list" class="space-y-6">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order_id => $order_data): ?>
                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 overflow-hidden">
                        <div class="p-5 sm:p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-4 mb-4 sm:mb-0">
                                    <div class="hidden sm:flex items-center justify-center w-12 h-12 bg-blue-100 text-blue-600 rounded-full">
                                        <i class="fas fa-box-open text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-lg text-gray-900">Order #<?php echo htmlspecialchars($order_id); ?></h3>
                                        <p class="text-sm text-gray-500"><i class="fas fa-calendar-alt mr-1"></i> <?php echo htmlspecialchars($order_data['details']['formatted_date']); ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                                    <div class="status-badge status-<?php echo htmlspecialchars($order_data['details']['status']); ?> text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full text-center">
                                        <?php echo htmlspecialchars($order_data['details']['status']); ?>
                                    </div>
                                    <div class="flex items-center gap-2 justify-end">
                                        <button class="view-btn text-sm font-semibold bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg transition-colors duration-200" onclick="openDetailsModal('<?php echo htmlspecialchars($order_id); ?>')">
                                            <i class="fas fa-eye mr-1.5"></i> Details
                                        </button>
                                        <?php if ($order_data['details']['status'] === 'Accepted'): ?>
                                            <button class="invoice-btn text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200" onclick="openInvoiceModal('<?php echo htmlspecialchars($order_id); ?>')">
                                                <i class="fas fa-file-invoice mr-1.5"></i> Invoice
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center bg-white rounded-2xl shadow-md p-10 sm:p-16">
                    <i class="fas fa-shopping-cart text-5xl text-gray-400 mb-4"></i>
                    <h2 class="text-xl font-semibold text-gray-800">No Orders Found</h2>
                    <p class="text-gray-500 mt-2">You haven't placed any orders yet. Start shopping to see your orders here!</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden modal-enter">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-auto transform transition-all">
            <div class="flex justify-between items-center p-5 border-b border-gray-200">
                <h2 id="modal-title" class="text-xl font-bold text-gray-900">Order Details</h2>
                <button class="text-gray-400 hover:text-red-500 transition-colors" onclick="closeModal('detailsModal')">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="p-6 max-h-[70vh] overflow-y-auto">
                <ul id="modal-items-list" class="space-y-3"></ul>
                <div id="modal-order-message" class="mt-5 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg" style="display: none;">
                    <p class="font-semibold text-yellow-800"><i class="fas fa-comment-dots mr-2"></i> Your Message:</p>
                    <p id="message-text" class="text-yellow-700 text-sm mt-1"></p>
                </div>
                <div id="modal-rejection-condition" class="mt-5 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg" style="display: none;">
                    <p class="font-semibold text-red-800"><i class="fas fa-exclamation-triangle mr-2"></i> Rejection Reason:</p>
                    <p id="condition-text" class="text-red-700 text-sm mt-1"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div id="invoiceModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden modal-enter">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-auto transform transition-all">
            <div class="flex justify-between items-center p-5 border-b border-gray-200 no-print">
                <h2 class="text-xl font-bold text-gray-900">Dispatch Details</h2>
                <div class="flex items-center gap-3">
                    <button class="print-btn text-sm font-semibold bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors" onclick="printInvoice()">
                        <i class="fas fa-print mr-1.5"></i> Print
                    </button>
                    <button class="text-gray-400 hover:text-red-500 transition-colors" onclick="closeModal('invoiceModal')">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <div id="invoice-content-wrapper" class="p-2 sm:p-4 md:p-8 max-h-[80vh] overflow-y-auto">
                <!-- Invoice content will be injected here by JavaScript -->
                <div id="invoice-content"></div>
            </div>
        </div>
    </div>

    <script>
        // --- Data from PHP ---
        // Safely encode PHP data to be used in JavaScript.
        const ordersData = <?php echo json_encode($orders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const partyData = <?php echo json_encode($party_details, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        // --- Modal Management ---
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('hidden');
            // Trigger animation
            requestAnimationFrame(() => {
                 modal.classList.add('modal-enter-active');
            });
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('modal-enter-active');
            modal.classList.add('modal-leave-active');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('modal-leave-active');
            }, 300); // Match transition duration
        }

        // --- Details Modal Logic ---
        function openDetailsModal(orderId) {
            const order = ordersData[orderId];
            if (!order) return;

            document.getElementById('modal-title').innerHTML = `<i class="fas fa-receipt text-blue-600 mr-2"></i> Order #${orderId}`;
            const itemsList = document.getElementById('modal-items-list');
            itemsList.innerHTML = order.items.map(item => `
                <li class="flex justify-between items-center bg-gray-50 p-4 rounded-lg">
                    <span class="font-semibold text-gray-800">${item.product_name}</span>
                    <span class="font-bold text-blue-600 bg-blue-100 px-3 py-1 rounded-full text-sm">Qty: ${item.quantity} ${item.unit}</span>
                </li>
            `).join('');

            const messageDiv = document.getElementById('modal-order-message');
            if (order.details.order_message) {
                document.getElementById('message-text').textContent = order.details.order_message;
                messageDiv.style.display = 'block';
            } else {
                messageDiv.style.display = 'none';
            }

            const conditionDiv = document.getElementById('modal-rejection-condition');
            if (order.details.status === 'Rejected' && order.details.rejection_condition) {
                document.getElementById('condition-text').textContent = order.details.rejection_condition;
                conditionDiv.style.display = 'block';
            } else {
                conditionDiv.style.display = 'none';
            }

            openModal('detailsModal');
        }

        // --- Invoice Modal Logic ---
        function openInvoiceModal(orderId) {
            const order = ordersData[orderId];
            if (!order) return;

            const itemsHtml = order.items.map((item, index) => `
                <tr class="border-b">
                    <td class="py-2 px-3 text-center">${index + 1}</td>
                    <td class="py-2 px-3">${item.product_name}</td>
                    <td class="py-2 px-3 text-right">${item.quantity} ${item.unit}</td>
                </tr>
            `).join('');

            const invoiceHtml = `
                <div class="p-6 bg-white text-sm">
                    <div class="text-center mb-10">
                        <img src="../Assets/logo.jpg" alt="Company Logo" class="mx-auto h-16 w-auto opacity-80">
                        <h2 class="text-2xl font-bold text-gray-800 mt-4">Dispatch Summary</h2>
                        <p class="text-gray-500"><strong>Order ID:</strong> ${orderId} | <strong>Date:</strong> ${order.details.invoice_date}</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                        <div>
                            <h4 class="font-bold text-gray-700 border-b pb-1 mb-2">From:</h4>
                            <p class="font-semibold">JSPL STEEL</p>
                            <p class="text-gray-600">Third Floor, Plot No-747, Khata No-11,</p>
                            <p class="text-gray-600">Dhanbad, Jharkhand - 828109</p>
                        </div>
                        <div class="md:text-right">
                            <h4 class="font-bold text-gray-700 border-b pb-1 mb-2">Billed To:</h4>
                            <p class="font-semibold">${partyData.business_name}</p>
                            <p class="text-gray-600">${partyData.address}, ${partyData.state} - ${partyData.pincode}</p>
                            <p class="text-gray-600"><strong>GSTIN:</strong> ${partyData.gst_uin}</p>
                        </div>
                    </div>

                    <table class="w-full mb-10">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-3 text-left text-gray-600 font-bold w-16 text-center">S.No.</th>
                                <th class="py-2 px-3 text-left text-gray-600 font-bold">Item Description</th>
                                <th class="py-2 px-3 text-right text-gray-600 font-bold">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                    </table>

                     <div class="mb-10 p-3 bg-gray-50 rounded-md">
                         <strong class="text-gray-700">Note:</strong>
                         <span class="text-gray-600">${order.details.order_message || 'No specific instructions.'}</span>
                     </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6 border-t mt-10">
                        <div>
                            <h5 class="font-bold text-gray-700 mb-2">Bank Details</h5>
                            <p class="text-gray-600"><strong>A/C NAME:</strong> JSPL STEEL</p>
                            <p class="text-gray-600"><strong>A/C NO.:</strong> 50200113154873</p>
                            <p class="text-gray-600"><strong>IFSC:</strong> HDFC0008981</p>
                            <p class="text-gray-600"><strong>BANK:</strong> HDFC BANK LTD, DHAIYA</p>
                        </div>
                        <div class="md:text-right mt-8 md:mt-0">
                            <h5 class="font-bold text-gray-700">For JSPL STEEL</h5>
                            <div class="mt-16 border-t border-gray-400 pt-2">
                                <p class="text-gray-600">Authorised Signatory</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('invoice-content').innerHTML = invoiceHtml;
            openModal('invoiceModal');
        }
        
        // --- Print Functionality ---
        function printInvoice() {
            window.print();
        }

        // --- Global Event Listeners ---
        // Close modal on escape key press
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal('detailsModal');
                closeModal('invoiceModal');
            }
        });
        
        // Close modal on background click
        window.addEventListener('click', (event) => {
            if (event.target.id === 'detailsModal') {
                closeModal('detailsModal');
            }
            if (event.target.id === 'invoiceModal') {
                closeModal('invoiceModal');
            }
        });

    </script>
</body>
</html>
