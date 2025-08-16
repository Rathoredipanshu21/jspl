<?php
// This single file handles the display, saving, and fetching of party data.

// Include the database configuration file.
@include 'config/db.php';

// --- ACTION HANDLER ---
// This block handles AJAX requests for saving and fetching data.
if (isset($conn)) {
    // Handle SAVE action
    if (isset($_POST['action']) && $_POST['action'] == 'save') {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unknown error occurred.'];

        try {
            // --- Unique ID Generation ---
            $role = htmlspecialchars(strip_tags($_POST['role']));
            $prefix_map = [
                'Distributor' => 'DIST',
                'Dealer'      => 'DEAL',
                'Retailer'    => 'RETA',
                'Wholesaler'  => 'WHOL'
            ];
            $prefix = $prefix_map[$role] ?? 'PARTY'; // Default prefix

            // Find the last ID for the given prefix to determine the next number
            $id_stmt = $conn->prepare("SELECT MAX(unique_id) as last_id FROM parties WHERE unique_id LIKE ?");
            if ($id_stmt === false) {
                throw new Exception('Prepare failed for ID check: ' . $conn->error);
            }
            $like_prefix = $prefix . '%';
            $id_stmt->bind_param("s", $like_prefix);
            $id_stmt->execute();
            $result = $id_stmt->get_result();
            $row = $result->fetch_assoc();
            $last_id = $row['last_id'];
            $id_stmt->close();

            $number = 1;
            if ($last_id) {
                // Extract the numeric part from the last ID and increment it
                $number = (int)substr($last_id, strlen($prefix)) + 1;
            }

            // Format the new unique ID with 4-digit zero padding (e.g., DIST0001)
            $unique_id = sprintf('%s%04d', $prefix, $number);
            // --- End of Unique ID Generation ---

            // Prepare the main INSERT statement
            $stmt = $conn->prepare("INSERT INTO parties (unique_id, business_name, owner_name, address, gst_uin, state, contact_number, email, pincode, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                throw new Exception('Database prepare statement failed: ' . $conn->error);
            }

            // Bind parameters (s = string)
            $stmt->bind_param("ssssssssss", $unique_id, $business_name, $owner_name, $address, $gst_uin, $state, $contact_number, $email, $pincode, $role);

            // Sanitize and set parameters from POST data
            $business_name = htmlspecialchars(strip_tags($_POST['business_name']));
            $owner_name = htmlspecialchars(strip_tags($_POST['owner_name']));
            $address = htmlspecialchars(strip_tags($_POST['address']));
            $gst_uin = htmlspecialchars(strip_tags($_POST['gst_uin']));
            $state = htmlspecialchars(strip_tags($_POST['state']));
            $contact_number = htmlspecialchars(strip_tags($_POST['contact_number']));
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $pincode = htmlspecialchars(strip_tags($_POST['pincode']));
            // $role is already sanitized above

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'New party added successfully with ID: ' . $unique_id;
            } else {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        $conn->close();
        echo json_encode($response);
        exit(); // Stop script execution
    }

    // Handle FETCH action
    if (isset($_GET['action']) && $_GET['action'] == 'fetch') {
        $output = '';
        $sql = "SELECT id, unique_id, business_name, owner_name, address, gst_uin, state, contact_number, email, pincode, role, created_at FROM parties ORDER BY id DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $output .= "<tr data-aos='fade-in' data-aos-delay='50'>";
                $output .= "<td><strong>" . htmlspecialchars($row["unique_id"]) . "</strong></td>";
                $output .= "<td>" . htmlspecialchars($row["business_name"]) . "</td>";
                $output .= "<td><span class='role-badge'>" . htmlspecialchars($row["role"]) . "</span></td>";
                $output .= "<td>" . htmlspecialchars($row["owner_name"]) . "</td>";
                $output .= "<td>" . htmlspecialchars($row["address"]) . ", " . htmlspecialchars($row["pincode"]) . "</td>";
                $output .= "<td>" . htmlspecialchars($row["gst_uin"]) . "</td>";
                $output .= "<td>
                                <div class='contact-info'>
                                    <span><i class='fas fa-phone'></i>" . htmlspecialchars($row["contact_number"]) . "</span>
                                    <span><i class='fas fa-envelope'></i>" . htmlspecialchars($row["email"]) . "</span>
                                </div>
                              </td>";
                $output .= "</tr>";
            }
        } else {
            $output = "<tr><td colspan='7' style='text-align:center; padding: 20px;'>No parties found. Click 'Add New Party' to get started.</td></tr>";
        }
        $conn->close();
        echo $output;
        exit(); // Stop script execution
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Management - Business Software</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #50e3c2;
            --background-color: #f4f7fa;
            --dark-grey: #333;
            --light-grey: #777;
            --card-bg: #ffffff;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--dark-grey);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 1600px;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
            box-sizing: border-box;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header h1 i {
            color: var(--primary-color);
            margin-right: 10px;
        }
        .add-party-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(74, 144, 226, 0.4);
        }
        .add-party-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(74, 144, 226, 0.6);
        }
        .add-party-btn i {
            margin-right: 8px;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th, td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            white-space: nowrap;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--light-grey);
            text-transform: uppercase;
            font-size: 12px;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background-color: #f1f5f8;
        }
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .contact-info span {
            display: flex;
            align-items: center;
        }
        .contact-info i {
            color: var(--primary-color);
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        .role-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            padding: 40px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 800px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: slide-down 0.5s ease-out;
        }
        @keyframes slide-down {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 22px;
        }
        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover, .close-btn:focus {
            color: black;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: var(--light-grey);
        }
        .form-group input, .form-group textarea, .form-group select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #fff; /* Ensure select has a background */
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .modal-footer {
            text-align: right;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .submit-btn {
            padding: 12px 30px;
        }
    </style>
</head>
<body>

    <div class="container" data-aos="fade-up">
        <div class="header">
            <h1><i class="fas fa-users"></i>Party / Trader Management</h1>
            <button class="add-party-btn" id="addPartyBtn"><i class="fas fa-plus-circle"></i>Add New Party</button>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Unique ID</th>
                        <th>Business Name</th>
                        <th>Role</th>
                        <th>Owner Name</th>
                        <th>Address</th>
                        <th>GST / UIN</th>
                        <th>Contact</th>
                    </tr>
                </thead>
                <tbody id="partyTableBody">
                    <?php
                        if (isset($conn)) {
                            $sql = "SELECT id, unique_id, business_name, owner_name, address, gst_uin, state, contact_number, email, pincode, role, created_at FROM parties ORDER BY id DESC";
                            $result = $conn->query($sql);

                            if ($result && $result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr data-aos='fade-in' data-aos-delay='100'>";
                                    echo "<td><strong>" . htmlspecialchars($row["unique_id"]) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row["business_name"]) . "</td>";
                                    echo "<td><span class='role-badge'>" . htmlspecialchars($row["role"]) . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row["owner_name"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["address"]) . ", " . htmlspecialchars($row["pincode"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["gst_uin"]) . "</td>";
                                    echo "<td>
                                            <div class='contact-info'>
                                                <span><i class='fas fa-phone'></i>" . htmlspecialchars($row["contact_number"]) . "</span>
                                                <span><i class='fas fa-envelope'></i>" . htmlspecialchars($row["email"]) . "</span>
                                            </div>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center; padding: 20px;'>No parties found. Click 'Add New Party' to get started.</td></tr>";
                            }
                            $conn->close();
                        } else {
                             echo "<tr><td colspan='7' style='text-align:center; padding: 20px; color: red;'>Error: Could not connect to the database.</td></tr>";
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- The Modal for adding a new party -->
    <div id="partyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New Party Details</h2>
                <span class="close-btn">&times;</span>
            </div>
            <form id="addPartyForm" onsubmit="handleFormSubmit(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="role">Party Role</label>
                        <select id="role" name="role" required>
                            <option value="" disabled selected>-- Select a role --</option>
                            <option value="Distributor">Distributor</option>
                            <option value="Dealer">Dealer</option>
                            <option value="Retailer">Retailer</option>
                            <option value="Wholesaler">Wholesaler</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="business_name">Business Name</label>
                        <input type="text" id="business_name" name="business_name" required>
                    </div>
                    <div class="form-group">
                        <label for="owner_name">Business Owner Name</label>
                        <input type="text" id="owner_name" name="owner_name" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="tel" id="contact_number" name="contact_number" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="gst_uin">GST / UIN</label>
                        <input type="text" id="gst_uin" name="gst_uin" required maxlength="15">
                    </div>
                    <div class="form-group full-width">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" required>
                    </div>
                    <div class="form-group">
                        <label for="pincode">Pincode</label>
                        <input type="text" id="pincode" name="pincode" required>
                    </div>
                </div>
                <div class="modal-footer">
                     <button type="submit" class="add-party-btn submit-btn"><i class="fas fa-save"></i> Save Party</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 600,
            once: true,
        });

        const modal = document.getElementById("partyModal");
        const btn = document.getElementById("addPartyBtn");
        const span = document.getElementsByClassName("close-btn")[0];
        const form = document.getElementById('addPartyForm');

        btn.onclick = () => {
            form.reset();
            modal.style.display = "flex";
        }
        span.onclick = () => modal.style.display = "none";
        window.onclick = (event) => {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        function handleFormSubmit(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData(form);
            formData.append('action', 'save');

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modal.style.display = "none";
                    fetchParties();
                    // Simple alert for success
                    alert(data.message || 'Party added successfully!');
                } else {
                    // Simple alert for error
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected network error occurred.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Party';
            });
        }

        function fetchParties() {
            fetch(window.location.pathname + '?action=fetch')
            .then(response => response.text())
            .then(html => {
                document.getElementById('partyTableBody').innerHTML = html;
                AOS.refresh();
            })
            .catch(error => console.error('Failed to fetch parties:', error));
        }
    </script>
</body>
</html>
