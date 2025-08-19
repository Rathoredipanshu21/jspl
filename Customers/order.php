<?php
session_start();
// Include your database connection file
include '../config/db.php';

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['party_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all goods from the database to populate the product list
$goods_query = "SELECT id, product_name FROM goods ORDER BY product_name ASC";
$goods_result = $conn->query($goods_query);
$products = [];
if ($goods_result->num_rows > 0) {
    while ($row = $goods_result->fetch_assoc()) {
        $products[] = $row;
    }
}


// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['party_id'];
    $order_message = isset($_POST['order_message']) ? $conn->real_escape_string($_POST['order_message']) : '';
    $product_ids = isset($_POST['product_id']) ? $_POST['product_id'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];

    $ordered_products = [];
    if (!empty($product_ids)) {
        for ($i = 0; $i < count($product_ids); $i++) {
            $product_id = (int)$product_ids[$i];
            $quantity = (int)$quantities[$i];
            if ($product_id > 0 && $quantity > 0) {
                if (isset($ordered_products[$product_id])) {
                    $ordered_products[$product_id] += $quantity;
                } else {
                    $ordered_products[$product_id] = $quantity;
                }
            }
        }
    }


    if (!empty($ordered_products)) {
        // Generate a unique order ID
        $order_id = 'ORD-' . time() . '-' . $user_id;

        // Start a transaction
        $conn->begin_transaction();

        try {
            // Insert into customer_orders table
            $stmt_customer_order = $conn->prepare("INSERT INTO customer_orders (order_id, user_id, order_message) VALUES (?, ?, ?)");
            $stmt_customer_order->bind_param("sis", $order_id, $user_id, $order_message);
            $stmt_customer_order->execute();
            $stmt_customer_order->close();

            // Insert into order_items table
            $stmt_order_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
            foreach ($ordered_products as $product_id => $quantity) {
                $stmt_order_item->bind_param("sii", $order_id, $product_id, $quantity);
                $stmt_order_item->execute();
            }
            $stmt_order_item->close();

            $conn->commit();
            $success_message = "Your order has been placed successfully! Your Order ID is: " . htmlspecialchars($order_id);
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $error_message = "Error placing order: " . $exception->getMessage();
        }
    } else {
        $error_message = "Please add at least one product to your cart.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Your Order</title>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #fdfbf5;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1300px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        h1, h2 {
            color: #2c3e50;
            text-align: center;
            margin-top: 0;
            margin-bottom: 30px;
            font-weight: 700;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 600;
            text-align: center;
        }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .order-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .product-list-container {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
        }
        #product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            max-height: 550px;
            overflow-y: auto;
            padding: 5px;
        }
        .product-item {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 80px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .product-item:hover {
            border-color: #1abc9c;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .product-item.selected {
            transform: scale(0.95);
            border-color: #16a085;
            background-color: #e8f8f5;
        }
        .cart-container { /* No changes needed */ }
        #cart-items-display { margin-bottom: 20px; }
        .cart-item {
            display: grid;
            grid-template-columns: 1fr 100px 50px;
            gap: 15px;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .cart-item-name { font-weight: 600; }
        .cart-item input[type="number"] {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
            width: 80px;
            text-align: center;
        }
        .remove-btn {
            background: #e74c3c; color: white; border: none; width: 35px; height: 35px;
            border-radius: 50%; cursor: pointer; font-size: 16px; transition: background 0.3s;
            display: flex; align-items: center; justify-content: center;
        }
        .remove-btn:hover { background: #c0392b; }
        #cart-empty-msg {
            text-align: center; padding: 40px; color: #777;
            border: 2px dashed #eee; border-radius: 8px;
        }
        .cart-summary {
            background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;
        }
        .summary-row {
            display: flex; justify-content: space-between; font-size: 18px;
            font-weight: 600; margin-bottom: 10px;
        }
        .summary-row:last-child { margin-bottom: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        textarea {
            width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc;
            font-family: 'Source Sans Pro', sans-serif; font-size: 16px; resize: vertical;
        }
        .submit-btn {
            background-color: #2c3e50; color: #fff; padding: 15px 25px; border: none;
            border-radius: 5px; cursor: pointer; font-size: 18px; font-weight: 700;
            transition: background-color 0.3s ease; display: block; width: 100%;
        }
        .submit-btn:hover { background-color: #34495e; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-dolly-flatbed"></i> Place Your Order</h1>

        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="order-layout">
            <div class="product-list-container">
                <h2>Click a Product to Add</h2>
                <div id="product-list">
                    <?php foreach ($products as $product): ?>
                        <div class="product-item" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cart-container">
                <form action="order.php" method="post" id="order-form">
                    <h2><i class="fas fa-shopping-cart"></i> Your Cart</h2>
                    <div id="cart-items-display">
                        <p id="cart-empty-msg">Your cart is empty.</p>
                    </div>
                    <div id="hidden-form-inputs"></div>

                    <div class="cart-summary">
                        <div class="summary-row"><span>Total Items:</span><span id="total-items">0</span></div>
                        <div class="summary-row"><span>Total Quantity:</span><span id="total-quantity">0</span></div>
                    </div>

                    <div class="form-group">
                        <label for="order_message">Order Message (Optional)</label>
                        <textarea name="order_message" id="order_message" rows="4" placeholder="Add any special instructions for your order..."></textarea>
                    </div>
                    <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Place Order</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let cart = {};

        const cartDisplay = document.getElementById('cart-items-display');
        const hiddenInputsContainer = document.getElementById('hidden-form-inputs');
        const emptyCartMsg = document.getElementById('cart-empty-msg');

        function removeFromCart(id) {
            delete cart[id];
            renderCart();
        }

        function updateQuantity(id, newQuantity) {
            if (cart[id]) {
                cart[id].quantity = Math.max(1, parseInt(newQuantity) || 1);
                renderCart();
            }
        }

        function renderCart() {
            cartDisplay.innerHTML = '';
            hiddenInputsContainer.innerHTML = '';
            const cartItems = Object.keys(cart);

            if (cartItems.length === 0) {
                cartDisplay.appendChild(emptyCartMsg);
            } else {
                if (cartDisplay.contains(emptyCartMsg)) {
                    cartDisplay.removeChild(emptyCartMsg);
                }
                cartItems.forEach(id => {
                    const item = cart[id];
                    const cartItemDiv = document.createElement('div');
                    cartItemDiv.className = 'cart-item';
                    cartItemDiv.innerHTML = `
                        <span class="cart-item-name">${item.name}</span>
                        <input type="number" value="${item.quantity}" min="1" onchange="updateQuantity(${id}, this.value)" onfocus="this.select()">
                        <button type="button" class="remove-btn" onclick="removeFromCart(${id})"><i class="fas fa-trash"></i></button>
                    `;
                    cartDisplay.appendChild(cartItemDiv);

                    // Create hidden inputs for the form submission
                    const hiddenIdInput = document.createElement('input');
                    hiddenIdInput.type = 'hidden';
                    hiddenIdInput.name = 'product_id[]';
                    hiddenIdInput.value = id;
                    hiddenInputsContainer.appendChild(hiddenIdInput);

                    const hiddenQuantityInput = document.createElement('input');
                    hiddenQuantityInput.type = 'hidden';
                    hiddenQuantityInput.name = 'quantity[]';
                    hiddenQuantityInput.value = item.quantity;
                    hiddenInputsContainer.appendChild(hiddenQuantityInput);
                });
            }
            updateSummary();
        }

        function updateSummary() {
            const cartItems = Object.values(cart);
            const totalItems = cartItems.length;
            const totalQuantity = cartItems.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('total-items').textContent = totalItems;
            document.getElementById('total-quantity').textContent = totalQuantity;
        }

        // Add event listeners for product box selection
        document.querySelectorAll('.product-item').forEach(box => {
            box.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;

                // Add or increment the product in the cart
                if (cart[id]) {
                    cart[id].quantity++;
                } else {
                    cart[id] = { name: name, quantity: 1 };
                }

                // Update the cart display
                renderCart();

                // Provide brief visual feedback on the clicked box
                this.classList.add('selected');
                setTimeout(() => {
                    this.classList.remove('selected');
                }, 300); // Visual feedback lasts for 300ms
            });
        });

        window.onload = function() {
            renderCart();
        };
    </script>
</body>
</html>
<?php
$conn->close();
?>