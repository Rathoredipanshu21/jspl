<?php
// This page is now an open admin panel.
// Login/session requirements have been removed, but approve/reject functionality is active.
require_once 'config/db.php';

// Handle approve/reject actions submitted from the page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];
    $status = ($action === 'approve') ? 'Approved' : 'Rejected';

    // Prepare and execute the database update
    $stmt = $conn->prepare("UPDATE customer_orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("ss", $status, $order_id);
    $stmt->execute();
    
    // Refresh the page to show the updated list of pending orders
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all pending orders along with customer names from the 'parties' table.
// Based on your database file, the customer name is 'business_name' and the join key is 'id'.
$sql = "SELECT o.order_id, o.order_date, o.message, o.status, p.business_name 
        FROM customer_orders o
        JOIN parties p ON o.customer_id = p.id
        WHERE o.status = 'Pending'
        ORDER BY o.order_date DESC";
$pending_orders = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Order Approval</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="antialiased text-gray-800">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <header class="mb-8" data-aos="fade-down">
            <h1 class="text-4xl font-bold text-gray-900">Order Approval Queue</h1>
            <p class="text-lg text-gray-600 mt-2">Review and process new customer orders.</p>
        </header>

        <div class="bg-white p-6 rounded-2xl shadow-lg" data-aos="fade-up">
            <?php if (empty($pending_orders)): ?>
                <div class="text-center py-12" data-aos="zoom-in">
                    <i class="fas fa-check-double text-6xl text-green-500 mb-4"></i>
                    <h2 class="text-2xl font-semibold">All Caught Up!</h2>
                    <p class="text-gray-500">There are no pending orders to review.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="border border-gray-200 rounded-xl p-4 md:p-6 hover:shadow-xl hover:border-indigo-500 transition-all duration-300" data-aos="fade-up" data-aos-delay="100">
                            <div class="flex flex-wrap justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-xl font-bold text-indigo-600"><?= htmlspecialchars($order['order_id']) ?></h3>
                                    <p class="text-sm text-gray-500">
                                        <i class="far fa-user mr-1"></i> Customer: <strong><?= htmlspecialchars($order['business_name']) ?></strong>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <i class="far fa-calendar-alt mr-1"></i> Date: <?= date('d M Y, h:i A', strtotime($order['order_date'])) ?>
                                    </p>
                                </div>
                                <!-- Approve and Reject buttons are back -->
                                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <button type="submit" name="action" value="approve" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors flex items-center">
                                            <i class="fas fa-check mr-2"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <button type="submit" name="action" value="reject" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors flex items-center">
                                            <i class="fas fa-times mr-2"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php if (!empty($order['message'])): ?>
                                <div class="border-t border-gray-200 pt-4 mt-4">
                                    <p class="text-sm font-semibold text-gray-700"><i class="far fa-comment-dots mr-2"></i>Customer's Message:</p>
                                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg mt-2"><em>"<?= htmlspecialchars($order['message']) ?>"</em></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init({ duration: 800, once: true });</script>
</body>
</html>
