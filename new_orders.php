<?php
// --- DATABASE SETUP (Replace with your credentials) ---
include 'config/db.php';

// --- DATA FETCHING AND STRUCTURING ---
$users_data = [];

// Query is updated to fetch the new status and rejection_condition fields
// The ORDER BY clause is changed to show the newest orders first across all users
$sql = "
    SELECT
        co.user_id,
        p.business_name,
        p.owner_name,
        co.id as order_pk,
        co.order_id,
        co.order_message,
        co.status,
        co.rejection_condition,
        co.created_at,
        oi.product_id,
        oi.quantity,
        g.product_name
    FROM
        customer_orders co
    LEFT JOIN
        parties p ON co.user_id = p.id
    LEFT JOIN
        order_items oi ON co.order_id = oi.order_id
    LEFT JOIN
        goods g ON oi.product_id = g.id
    ORDER BY
        co.created_at DESC, co.user_id"; // <-- MODIFIED LINE: Prioritize by latest date

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $order_pk = $row['order_pk'];

        if (!isset($users_data[$user_id])) {
            $users_data[$user_id] = [
                'user_id' => $user_id,
                'business_name' => $row['business_name'],
                'owner_name' => $row['owner_name'],
                'orders' => []
            ];
        }

        if (!isset($users_data[$user_id]['orders'][$order_pk])) {
            $users_data[$user_id]['orders'][$order_pk] = [
                'order_id' => $row['order_id'],
                'order_message' => $row['order_message'],
                'created_at' => $row['created_at'],
                'status' => $row['status'],
                'rejection_condition' => $row['rejection_condition'],
                'items' => []
            ];
        }

        if ($row['product_id']) {
            $users_data[$user_id]['orders'][$order_pk]['items'][] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'quantity' => $row['quantity']
            ];
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Orders Dashboard</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #f4f7fc;
            --card-bg: #ffffff;
            --primary-color: #ffffff;
            --secondary-color: #6a11cb;
            --accent-color: #2575fc;
            --text-color: #333;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 20px;
        }

        .container { max-width: 900px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 {
            background: linear-gradient(45deg, var(--secondary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .user-card {
            background: var(--card-bg);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            cursor: pointer;
            background-color: var(--primary-color);
            border-bottom: 1px solid var(--border-color);
        }

        .party-info { display: flex; align-items: center; gap: 15px; }
        .party-icon { font-size: 1.8rem; color: var(--accent-color); }
        .business-name { font-size: 1.2rem; font-weight: 600; color: var(--text-color); }
        .owner-name { font-size: 0.9rem; color: var(--text-muted); font-weight: 400; margin-left: 8px; }
        
        .toggle-icon { font-size: 1.2rem; transition: transform 0.3s ease; }
        .user-card.open .toggle-icon { transform: rotate(180deg); }

        .orders-container {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.7s ease-out, padding 0.7s ease-out;
        }

        .order-row {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s;
        }
        .order-row:last-child { border-bottom: none; }
        .order-row:hover { background-color: #fafafa; }

        .order-main-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .order-detail { font-size: 0.9rem; }
        .order-detail strong { color: var(--text-muted); display: block; margin-bottom: 4px; font-weight: 500; }
        
        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            min-width: 90px;
            text-align: center;
        }
        .status-Pending { background-color: var(--warning-color); }
        .status-Accepted { background-color: var(--success-color); }
        .status-Rejected { background-color: var(--danger-color); }

        .order-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed var(--border-color);
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .action-btn {
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            color: white;
        }
        .action-btn i { margin-right: 8px; }
        .accept-btn { background-color: var(--success-color); }
        .accept-btn:hover { background-color: #218838; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(40,167,69,0.3); }
        .reject-btn { background-color: var(--danger-color); }
        .reject-btn:hover { background-color: #c82333; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220,53,69,0.3); }

        .rejection-input {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .rejection-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(37, 117, 252, 0.15);
        }

        .view-items-btn {
            background: linear-gradient(45deg, var(--secondary-color), var(--accent-color));
            color: white; border: none; padding: 8px 15px; border-radius: 8px;
            cursor: pointer; font-weight: 500; transition: all 0.3s ease;
        }
        .view-items-btn:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        .no-orders { text-align: center; padding: 40px; background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.4); backdrop-filter: blur(5px); }
        .modal-content { background-color: var(--card-bg); margin: 10% auto; padding: 30px; border: none; width: 90%; max-width: 500px; border-radius: 12px; position: relative; animation: slideIn 0.4s ease-out; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-button { color: #aaa; position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-button:hover, .close-button:focus { color: var(--danger-color); }
        .modal-header { color: var(--accent-color); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); }
        #modal-body ul { list-style: none; }
        #modal-body li { background-color: #f8f9fa; border: 1px solid var(--border-color); padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

    <div class="container">
        <div class="header" data-aos="fade-down">
            <h1><i class="fas fa-tasks"></i> Order Management</h1>
        </div>

        <?php if (!empty($users_data)): ?>
            <?php foreach ($users_data as $user_id => $data): ?>
                <div class="user-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="user-header">
                        <div class="party-info">
                            <i class="fas fa-store party-icon"></i>
                            <div>
                                <span class="business-name"><?php echo htmlspecialchars($data['business_name'] ?: 'N/A'); ?></span>
                                <span class="owner-name"><?php echo htmlspecialchars($data['owner_name'] ?: 'Unknown Owner'); ?></span>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="orders-container">
                        <?php foreach ($data['orders'] as $order_pk => $order): ?>
                            <div class="order-row" id="order-<?php echo $order_pk; ?>" data-aos="fade-in" data-aos-delay="200">
                                <div class="order-main-info">
                                    <div class="order-detail">
                                        <strong>Order ID</strong>
                                        <?php echo htmlspecialchars($order['order_id']); ?>
                                    </div>
                                    <div class="order-detail">
                                        <strong>Date</strong>
                                        <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                    </div>
                                    <div class="order-detail">
                                        <button class="view-items-btn" data-order-pk="<?php echo $order_pk; ?>" data-user-id="<?php echo $user_id; ?>">
                                            <i class="fas fa-box-open"></i> View Items
                                        </button>
                                    </div>
                                    <div class="order-detail">
                                        <strong>Status</strong>
                                        <span class="order-status status-<?php echo htmlspecialchars($order['status']); ?>" id="status-badge-<?php echo $order_pk; ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="order-actions" id="actions-<?php echo $order_pk; ?>">
                                    <input type="text" class="rejection-input" id="condition-<?php echo $order_pk; ?>" placeholder="Enter rejection reason if applicable..." value="<?php echo htmlspecialchars($order['rejection_condition'] ?? ''); ?>">
                                    <button class="action-btn accept-btn" onclick="updateStatus(<?php echo $order_pk; ?>, 'Accepted')">
                                        <i class="fas fa-check-circle"></i> Accept
                                    </button>
                                    <button class="action-btn reject-btn" onclick="updateStatus(<?php echo $order_pk; ?>, 'Rejected')">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-orders" data-aos="fade-up">
                <h2><i class="fas fa-info-circle"></i> No New Orders Found</h2>
            </div>
        <?php endif; ?>
    </div>

    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 class="modal-header">Order Items</h2>
            <div id="modal-body"></div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 600,
            once: true,
        });

        const usersData = <?php echo json_encode(array_values($users_data), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const usersDataMap = usersData.reduce((map, user) => {
            map[user.user_id] = user;
            return map;
        }, {});

        // --- FIX: Define a more robust, absolute URL for the update script ---
        const updateUrl = "<?php 
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            echo $protocol . $host . $path . '/update_order_status.php'; 
        ?>";


        document.addEventListener('DOMContentLoaded', function () {
            const userHeaders = document.querySelectorAll('.user-header');
            const modal = document.getElementById('orderModal');
            const closeModal = document.querySelector('.close-button');
            const modalBody = document.getElementById('modal-body');

            // Accordion Functionality
            userHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const card = header.closest('.user-card');
                    const ordersContainer = card.querySelector('.orders-container');
                    card.classList.toggle('open');
                    if (card.classList.contains('open')) {
                        ordersContainer.style.maxHeight = ordersContainer.scrollHeight + 'px';
                    } else {
                        ordersContainer.style.maxHeight = '0';
                    }
                });
            });

            // Modal Functionality for viewing items
            document.body.addEventListener('click', function(event) {
                if (event.target.classList.contains('view-items-btn')) {
                    const userId = event.target.dataset.userId;
                    const orderPk = event.target.dataset.orderPk;
                    const user = usersDataMap[userId];
                    if (!user) return;
                    const order = user.orders[orderPk];

                    modalBody.innerHTML = ''; 
                    if (order && order.items && order.items.length > 0) {
                        const list = document.createElement('ul');
                        order.items.forEach(item => {
                            const listItem = document.createElement('li');
                            const productName = item.product_name || `ID: ${item.product_id}`;
                            listItem.innerHTML = `
                                <span><i class="fas fa-tag"></i> Product: <strong>${productName}</strong></span>
                                <span>Quantity: <strong>${item.quantity}</strong></span>
                            `;
                            list.appendChild(listItem);
                        });
                        modalBody.appendChild(list);
                    } else {
                        modalBody.innerHTML = '<p>No items found for this order.</p>';
                    }
                    modal.style.display = 'block';
                }
            });

            closeModal.addEventListener('click', () => { modal.style.display = 'none'; });
            window.addEventListener('click', (event) => { if (event.target == modal) { modal.style.display = 'none'; } });
        });

        // --- FUNCTION TO UPDATE ORDER STATUS (with improved error handling) ---
        async function updateStatus(orderPk, newStatus) {
            const conditionInput = document.getElementById(`condition-${orderPk}`);
            const condition = conditionInput.value;

            if (newStatus === 'Rejected' && !condition.trim()) {
                alert('Please provide a reason for rejection.');
                conditionInput.focus();
                return;
            }

            // For debugging: log the URL we are trying to call
            console.log("Attempting to post update to:", updateUrl);

            try {
                const response = await fetch(updateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_pk: orderPk,
                        status: newStatus,
                        condition: condition
                    })
                });

                if (!response.ok) {
                    throw new Error(`Network response was not ok. Status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    const statusBadge = document.getElementById(`status-badge-${orderPk}`);
                    statusBadge.textContent = newStatus;
                    statusBadge.className = `order-status status-${newStatus}`;

                    const orderRow = document.getElementById(`order-${orderPk}`);
                    orderRow.style.transition = 'background-color 0.5s ease';
                    orderRow.style.backgroundColor = newStatus === 'Accepted' ? '#e9f7ec' : '#fbe9e9';

                    const actionContainer = document.getElementById(`actions-${orderPk}`);
                    actionContainer.style.display = 'none';
                } else {
                    alert('Failed to update status: ' + result.message);
                }
            } catch (error) {
                // Log the detailed error to the console for easier debugging
                console.error('Fetch Error:', error);
                alert('An error occurred. Please check the browser console (F12) for more details.');
            }
        }
    </script>
</body>
</html>