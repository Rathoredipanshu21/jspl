<?php
// This single file handles the display, saving, updating, deleting, and fetching of goods data.
@include 'config/db.php'; // Make sure your db connection file is available.

// --- ACTION HANDLER ---
if (isset($conn)) {
    // Handle SAVE action3
    if (isset($_POST['action']) && $_POST['action'] == 'save') {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An error occurred.'];
        
        $stmt = $conn->prepare("INSERT INTO goods (product_name, description, hsn_sac, unit, rate_per_gram) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisd", $_POST['product_name'], $_POST['description'], $_POST['hsn_sac'], $_POST['unit'], $_POST['rate_per_gram']);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Goods added successfully!'];
        } else {
            $response['message'] = 'Database Error: ' . $stmt->error;
        }
        $stmt->close();
        $conn->close();
        echo json_encode($response);
        exit();
    }

    // Handle UPDATE action
    if (isset($_POST['action']) && $_POST['action'] == 'update') {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An error occurred.'];

        $stmt = $conn->prepare("UPDATE goods SET product_name = ?, description = ?, hsn_sac = ?, unit = ?, rate_per_gram = ? WHERE id = ?");
        $stmt->bind_param("ssisdi", $_POST['product_name'], $_POST['description'], $_POST['hsn_sac'], $_POST['unit'], $_POST['rate_per_gram'], $_POST['good_id']);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Goods updated successfully!'];
        } else {
            $response['message'] = 'Database Error: ' . $stmt->error;
        }
        $stmt->close();
        $conn->close();
        echo json_encode($response);
        exit();
    }
    
    // Handle DELETE action
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An error occurred.'];

        $stmt = $conn->prepare("DELETE FROM goods WHERE id = ?");
        $stmt->bind_param("i", $_POST['good_id']);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Goods deleted successfully!'];
        } else {
            $response['message'] = 'Database Error: ' . $stmt->error;
        }
        $stmt->close();
        $conn->close();
        echo json_encode($response);
        exit();
    }

    // Handle FETCH action (for table refresh)
    if (isset($_GET['action']) && $_GET['action'] == 'fetch') {
        $output = '';
        $sql = "SELECT * FROM goods ORDER BY id DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $output .= "<tr data-aos='fade-up' data-good-id='{$row['id']}'>";
                $output .= "<td><i class='fas fa-box-open icon-blue'></i> <strong>" . htmlspecialchars($row["product_name"]) . "</strong></td>";
                $output .= "<td>" . htmlspecialchars($row["description"]) . "</td>";
                $output .= "<td>" . htmlspecialchars($row["hsn_sac"]) . "</td>";
                $output .= "<td>" . htmlspecialchars($row["quantity"]) . " " . htmlspecialchars($row["unit"]) . "</td>";
                $output .= "<td>₹" . number_format($row["rate_per_gram"], 2) . " / gram</td>";
                $output .= "<td class='actions'>
                                <button class='action-btn edit-btn' onclick='openEditModal(" . json_encode($row) . ")'><i class='fas fa-pencil-alt'></i> Edit</button>
                                <button class='action-btn delete-btn' onclick='deleteGood({$row['id']})'><i class='fas fa-trash-alt'></i> Delete</button>
                            </td>";
                $output .= "</tr>";
            }
        } else {
            $output = "<tr><td colspan='6' class='text-center p-4'>No goods found. Add some!</td></tr>";
        }
        $conn->close();
        echo $output;
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goods Management - Business Software</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90e2; --secondary-color: #50e3c2; --background-color: #f4f7fa;
            --dark-grey: #333; --light-grey: #777; --card-bg: #ffffff;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1); --border-radius: 12px;
            --danger-color: #e74c3c; --danger-hover: #c0392b;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-image: linear-gradient(to right top, #051937, #004d7a, #008793, #00bf72, #a8eb12);
            background-size: cover; background-attachment: fixed; color: var(--dark-grey);
            margin: 0; padding: 20px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh;
        }
        .container {
            width: 100%; max-width: 1400px; background: var(--card-bg);
            border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 30px; box-sizing: border-box;
        }
        .header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; flex-wrap: wrap; gap: 15px;
        }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header h1 i { color: var(--primary-color); margin-right: 10px; }
        .add-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 16px;
            font-weight: 500; cursor: pointer; transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(74, 144, 226, 0.4);
        }
        .add-btn:hover { transform: translateY(-3px); box-shadow: 0 7px 20px rgba(74, 144, 226, 0.6); }
        .add-btn i { margin-right: 8px; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 15px; border-bottom: 1px solid #e0e0e0; }
        th { background-color: #f8f9fa; font-weight: 600; color: var(--light-grey); text-transform: uppercase; font-size: 12px; }
        td { color: var(--dark-grey); font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f1f5f8; }
        .icon-blue { color: var(--primary-color); }
        .actions { text-align: right; }
        .action-btn {
            border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer;
            font-size: 13px; font-weight: 500; transition: all 0.2s ease; margin-left: 5px;
        }
        .action-btn i { margin-right: 5px; }
        .edit-btn { background-color: #f1c40f; color: white; }
        .edit-btn:hover { background-color: #f39c12; }
        .delete-btn { background-color: var(--danger-color); color: white; }
        .delete-btn:hover { background-color: var(--danger-hover); }
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: #fefefe; margin: auto; padding: 40px; border: none; border-radius: var(--border-radius);
            width: 90%; max-width: 700px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: slide-down 0.5s ease-out;
        }
        @keyframes slide-down { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
        .modal-header h2 { margin: 0; font-size: 22px; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; font-size: 14px; color: var(--light-grey); }
        .form-group input, .form-group textarea, .form-group select {
            padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px;
            font-family: 'Poppins', sans-serif; transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .full-width { grid-column: 1 / -1; }
        .modal-footer { text-align: right; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .submit-btn { background-color: var(--primary-color); color: white; padding: 12px 30px; }
        .submit-btn:hover { background-color: #3a7ac8; }
    </style>
</head>
<body>

    <div class="container" data-aos="fade-in">
        <div class="header">
            <h1><i class="fas fa-boxes-stacked"></i>Goods / Products</h1>
            <button class="add-btn" id="addGoodBtn"><i class="fas fa-plus-circle"></i>Add New Good</button>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>HSN/SAC</th>
                        <th>Quantity / Unit</th>
                        <th>Rate</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="goodsTableBody">
                    <?php
                        // Initial data load
                        if (isset($conn)) {
                            $sql = "SELECT * FROM goods ORDER BY id DESC";
                            $result = $conn->query($sql);
                            if ($result && $result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr data-aos='fade-up' data-good-id='{$row['id']}'>";
                                    echo "<td><i class='fas fa-box-open icon-blue'></i> <strong>" . htmlspecialchars($row["product_name"]) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["hsn_sac"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["quantity"]) . " " . htmlspecialchars($row["unit"]) . "</td>";
                                    echo "<td>₹" . number_format($row["rate_per_gram"], 2) . " / gram</td>";
                                    echo "<td class='actions'>
                                            <button class='action-btn edit-btn' onclick='openEditModal(" . json_encode($row) . ")'><i class='fas fa-pencil-alt'></i> Edit</button>
                                            <button class='action-btn delete-btn' onclick='deleteGood({$row['id']})'><i class='fas fa-trash-alt'></i> Delete</button>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>No goods found. Click 'Add New Good' to get started.</td></tr>";
                            }
                            $conn->close();
                        } else {
                             echo "<tr><td colspan='6' style='text-align:center; padding: 20px; color: red;'>Error: Could not connect to the database.</td></tr>";
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="goodModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-box"></i> Add New Good</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="goodForm">
                <input type="hidden" id="good_id" name="good_id">
                <input type="hidden" id="action" name="action" value="save">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Description (if any)</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="hsn_sac">HSN/SAC Number</label>
                        <input type="number" id="hsn_sac" name="hsn_sac" required>
                    </div>
                    <div class="form-group">
                        <label for="unit">Unit</label>
                        <select id="unit" name="unit" required>
                            <option value="M.T">M.T (Metric Ton)</option>
                            <option value="KG">KG (Kilogram)</option>
                            <option value="PCS">PCS (Pieces)</option>
                            <option value="LTR">LTR (Litre)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rate_per_gram">Rate (per 1 gram)</label>
                        <input type="number" step="0.01" id="rate_per_gram" name="rate_per_gram" required>
                    </div>
                </div>
                <div class="modal-footer">
                     <button type="submit" class="add-btn submit-btn" id="modalSubmitBtn"><i class="fas fa-save"></i> Save Good</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 600, once: true });

        const modal = document.getElementById("goodModal");
        const addBtn = document.getElementById("addGoodBtn");
        const closeBtn = document.querySelector(".close-btn");
        const form = document.getElementById('goodForm');
        
        // Open modal for adding
        addBtn.onclick = () => {
            form.reset();
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-box"></i> Add New Good';
            document.getElementById('modalSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Save Good';
            document.getElementById('action').value = 'save';
            modal.style.display = "flex";
        }
        
        // Open modal for editing
        function openEditModal(goodData) {
            form.reset();
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Good';
            document.getElementById('modalSubmitBtn').innerHTML = '<i class="fas fa-check-circle"></i> Update Good';
            document.getElementById('action').value = 'update';
            
            // Populate form
            document.getElementById('good_id').value = goodData.id;
            document.getElementById('product_name').value = goodData.product_name;
            document.getElementById('description').value = goodData.description;
            document.getElementById('hsn_sac').value = goodData.hsn_sac;
            document.getElementById('unit').value = goodData.unit;
            document.getElementById('rate_per_gram').value = goodData.rate_per_gram;
            
            modal.style.display = "flex";
        }

        // Close modal
        closeBtn.onclick = () => { modal.style.display = "none"; }
        window.onclick = (event) => { if (event.target == modal) { modal.style.display = "none"; } }

        // Form Submission (Add & Update)
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            
            fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modal.style.display = "none";
                    fetchGoods();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        });
        
        // Delete Good
        function deleteGood(id) {
            if (confirm('Are you sure you want to delete this item?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('good_id', id);
                
                fetch(window.location.pathname, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fetchGoods();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        // Fetch and refresh table
        function fetchGoods() {
            fetch(window.location.pathname + '?action=fetch')
            .then(response => response.text())
            .then(html => {
                document.getElementById('goodsTableBody').innerHTML = html;
                AOS.refresh();
            })
            .catch(error => console.error('Failed to fetch goods:', error));
        }
    </script>
</body>
</html>
