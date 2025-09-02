<?php
session_start();
// Include your database connection file
// IMPORTANT: Make sure this path is correct for your project structure.
include '../config/db.php'; 

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['party_id'])) {
    // In a real application, you would redirect.
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
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom styles to complement Tailwind */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom scrollbar for product list */
        #product-list::-webkit-scrollbar {
            width: 8px;
        }
        #product-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        #product-list::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        #product-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        /* Animation for selected product */
        .product-item.selected {
            transform: scale(0.95);
            transition: transform 0.2s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-7xl">
        
        <header class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 flex items-center justify-center gap-3">
                <i class="fas fa-dolly-flatbed text-teal-500"></i>
                <span>Place Your Order</span>
            </h1>
            <p class="text-gray-600 mt-2">Select products and place your order with ease.</p>
        </header>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div id="alert-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6 shadow-sm" role="alert">
                <p class="font-bold">Success</p>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div id="alert-message" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6 shadow-sm" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Main Layout: Products and Cart -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Products Section (takes 2/3 width on large screens) -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800">Click a Product to Add</h2>
                <div id="product-list" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 gap-4 max-h-[65vh] overflow-y-auto pr-2">
                    <?php foreach ($products as $product): ?>
                        <div class="product-item flex items-center justify-center h-28 p-3 text-center font-semibold text-gray-700 bg-gray-100 border-2 border-transparent rounded-lg cursor-pointer transition-all duration-200 hover:border-teal-500 hover:shadow-md hover:bg-teal-50" 
                             data-id="<?php echo $product['id']; ?>" 
                             data-name="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart Section (takes 1/3 width on large screens) -->
            <div class="bg-white p-6 rounded-2xl shadow-lg lg:sticky lg:top-8 h-fit">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="order-form">
                    <h2 class="text-2xl font-semibold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-shopping-cart text-teal-500"></i>
                        Your Cart
                    </h2>
                    
                    <!-- Cart Items Display -->
                    <div id="cart-items-display" class="mb-4 space-y-3">
                        <div id="cart-empty-msg" class="text-center py-10 px-4 border-2 border-dashed border-gray-200 rounded-lg">
                            <i class="fas fa-box-open text-4xl text-gray-300 mb-2"></i>
                            <p class="text-gray-500">Your cart is empty.</p>
                        </div>
                    </div>
                    
                    <!-- Hidden inputs for form submission -->
                    <div id="hidden-form-inputs"></div>

                    <!-- Cart Summary -->
                    <div id="cart-summary" class="bg-gray-50 p-4 rounded-lg mb-6 space-y-2 hidden">
                        <div class="flex justify-between items-center text-md font-medium text-gray-600">
                            <span>Total Items:</span>
                            <span id="total-items" class="font-semibold text-gray-800">0</span>
                        </div>
                        <div class="flex justify-between items-center text-md font-medium text-gray-600">
                            <span>Total Quantity:</span>
                            <span id="total-quantity" class="font-semibold text-gray-800">0</span>
                        </div>
                    </div>

                    <!-- Order Message -->
                    <div>
                        <label for="order_message" class="block mb-2 font-medium text-gray-700">Order Message (Optional)</label>
                        <textarea name="order_message" id="order_message" rows="4" placeholder="Add any special instructions..." class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition"></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-teal-500 text-white font-bold py-3 px-4 rounded-lg mt-6 hover:bg-teal-600 focus:outline-none focus:ring-4 focus:ring-teal-300 transition-all duration-300 flex items-center justify-center gap-2 text-lg">
                        <i class="fas fa-paper-plane"></i>
                        Place Order
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // State management for the cart
        let cart = {};

        // DOM element references
        const cartDisplay = document.getElementById('cart-items-display');
        const hiddenInputsContainer = document.getElementById('hidden-form-inputs');
        const emptyCartMsg = document.getElementById('cart-empty-msg');
        const cartSummary = document.getElementById('cart-summary');
        const alertMessage = document.getElementById('alert-message');

        /**
         * Removes an item from the cart and re-renders the UI.
         * @param {string|number} id - The product ID to remove.
         */
        function removeFromCart(id) {
            delete cart[id];
            renderCart();
        }

        /**
         * Updates the quantity of an item in the cart and re-renders.
         * @param {string|number} id - The product ID to update.
         * @param {string|number} newQuantity - The new quantity.
         */
        function updateQuantity(id, newQuantity) {
            if (cart[id]) {
                cart[id].quantity = Math.max(1, parseInt(newQuantity) || 1);
                renderCart();
            }
        }

        /**
         * Renders the entire cart UI based on the current cart state.
         */
        function renderCart() {
            // Clear previous state
            cartDisplay.innerHTML = '';
            hiddenInputsContainer.innerHTML = '';
            
            const cartItems = Object.keys(cart);

            if (cartItems.length === 0) {
                cartDisplay.appendChild(emptyCartMsg);
                cartSummary.classList.add('hidden'); // Hide summary if cart is empty
            } else {
                cartSummary.classList.remove('hidden'); // Show summary
                cartItems.forEach(id => {
                    const item = cart[id];
                    
                    // Create visual cart item
                    const cartItemDiv = document.createElement('div');
                    cartItemDiv.className = 'cart-item flex items-center gap-3 p-2 bg-white rounded-lg border';
                    cartItemDiv.innerHTML = `
                        <span class="cart-item-name flex-grow font-medium text-gray-700">${item.name}</span>
                        <input type="number" value="${item.quantity}" min="1" onchange="updateQuantity(${id}, this.value)" onfocus="this.select()" class="w-16 text-center border border-gray-300 rounded-md py-1 px-2 focus:ring-1 focus:ring-teal-500">
                        <button type="button" class="remove-btn text-red-500 hover:text-red-700 transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-100" onclick="removeFromCart(${id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    cartDisplay.appendChild(cartItemDiv);

                    // Create hidden inputs for form submission
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

        /**
         * Updates the summary totals (items and quantity).
         */
        function updateSummary() {
            const cartItems = Object.values(cart);
            const totalItems = cartItems.length;
            const totalQuantity = cartItems.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('total-items').textContent = totalItems;
            document.getElementById('total-quantity').textContent = totalQuantity;
        }

        // Event delegation for product clicks for better performance
        document.getElementById('product-list').addEventListener('click', function(e) {
            const productItem = e.target.closest('.product-item');
            if (productItem) {
                const id = productItem.dataset.id;
                const name = productItem.dataset.name;

                // Add or increment the product in the cart
                if (cart[id]) {
                    cart[id].quantity++;
                } else {
                    cart[id] = { name: name, quantity: 1 };
                }

                // Update the cart display
                renderCart();

                // Provide brief visual feedback on the clicked box
                productItem.classList.add('selected', 'bg-teal-100', 'border-teal-500');
                setTimeout(() => {
                    productItem.classList.remove('selected', 'bg-teal-100', 'border-teal-500');
                }, 300);
            }
        });

        // Automatically hide success/error messages after a few seconds
        if (alertMessage) {
            setTimeout(() => {
                alertMessage.style.transition = 'opacity 0.5s ease';
                alertMessage.style.opacity = '0';
                setTimeout(() => alertMessage.remove(), 500);
            }, 5000); // 5 seconds
        }

        // Initial render on page load
        window.onload = function() {
            renderCart();
        };
    </script>
</body>
</html>
<?php
// Close the database connection in a real application
$conn->close();
?>
