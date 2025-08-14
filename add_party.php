<?php
// This single file handles the display, saving, and fetching of party data.

// Include the database configuration file.
// Make sure you have a 'config/db.php' file with your database credentials.
@include 'config/db.php';

// --- ACTION HANDLER ---
// This block will handle AJAX requests for saving and fetching data.
// It checks for 'action' parameters in the request.

if (isset($conn)) {
    // Handle SAVE action (from the modal form submission)
    if (isset($_POST['action']) && $_POST['action'] == 'save') {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unknown error occurred.'];

        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO parties (business_name, owner_name, address, gst_uin, state, contact_number, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
            $response['message'] = 'Database prepare statement failed: ' . $conn->error;
            echo json_encode($response);
            $conn->close();
            exit();
        }
        
        // Bind parameters (s = string)
        $stmt->bind_param("sssssss", $business_name, $owner_name, $address, $gst_uin, $state, $contact_number, $email);

        // Sanitize and set parameters from POST data
        $business_name = htmlspecialchars(strip_tags($_POST['business_name']));
        $owner_name = htmlspecialchars(strip_tags($_POST['owner_name']));
        $address = htmlspecialchars(strip_tags($_POST['address']));
        $gst_uin = htmlspecialchars(strip_tags($_POST['gst_uin']));
        $state = htmlspecialchars(strip_tags($_POST['state']));
        $contact_number = htmlspecialchars(strip_tags($_POST['contact_number']));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'New party added successfully!';
        } else {
            $response['message'] = 'Execute failed: ' . $stmt->error;
        }

        $stmt->close();
        $conn->close();
        echo json_encode($response);
        exit(); // Stop script execution after handling the AJAX request
    }

    // Handle FETCH action (to refresh the table)
    if (isset($_GET['action']) && $_GET['action'] == 'fetch') {
        $output = '';
        $sql = "SELECT id, business_name, owner_name, address, gst_uin, state, contact_number, email FROM parties";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $output .= "<tr data-aos='fade-in' data-aos-delay='50'>";
                $output .= "<td><strong>" . htmlspecialchars($row["business_name"]) . "</strong></td>";
                $output .= "<td>" . htmlspecialchars($row["owner_name"]) . "</td>";
                $output .= "<td>" . htmlspecialchars($row["address"]) . "</td>";
                $output .= "<td>" . htmlspecialchars($row["gst_uin"]) . "</td>";
                $output .= "<td>" . htmlspecialchars($row["state"]) . "</td>";
                $output .= "<td>
                                <div class='contact-info'>
                                    <span><i class='fas fa-phone'></i>" . htmlspecialchars($row["contact_number"]) . "</span>
                                    <span><i class='fas fa-envelope'></i>" . htmlspecialchars($row["email"]) . "</span>
                                </div>
                              </td>";
                $output .= "</tr>";
            }
        } else {
            $output = "<tr><td colspan='6' style='text-align:center; padding: 20px;'>No parties found. Click 'Add New Party' to get started.</td></tr>";
        }
        $conn->close();
        echo $output;
        exit(); // Stop script execution after handling the AJAX request
    }
}

// If no action is specified, the script continues below to render the full HTML page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Management - Business Software</title>
    
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS (Animate on Scroll) Library for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom Stylesheet -->
    <style>
        :root {
            --primary-color: #4a90e2; /* A modern, friendly blue */
            --secondary-color: #50e3c2; /* A vibrant accent color */
            --background-color: #f4f7fa; /* A light, clean background */
            --dark-grey: #333;
            --light-grey: #777;
            --card-bg: #ffffff;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-image: linear-gradient(to right top, #6d327c, #485DA6, #00a1ba, #00BF98, #36C486);
            background-size: cover;
            background-attachment: fixed;
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
            max-width: 1400px;
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
            color: var(--dark-grey);
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
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--light-grey);
            text-transform: uppercase;
            font-size: 12px;
        }
        
        td {
            color: var(--dark-grey);
            font-size: 14px;
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
        }
        
        .contact-info span {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .contact-info i {
            color: var(--primary-color);
            margin-right: 8px;
            width: 16px;
            text-align: center;
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
            margin: auto;
            padding: 40px;
            border: none;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 700px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: slide-down 0.5s ease-out;
        }
        
        @keyframes slide-down {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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

        .close-btn:hover,
        .close-btn:focus {
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
        
        .form-group input, .form-group textarea {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
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
            background-color: var(--primary-color);
            color: white;
            padding: 12px 30px;
        }
        
        .submit-btn:hover {
            background-color: #3a7ac8;
        }
        
        /* Custom Alert Styles */
        .custom-alert {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
        }

        .custom-alert-content {
            background-color: #fff;
            padding: 30px 40px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: var(--shadow);
            position: relative;
            animation: slide-down 0.4s ease-out;
        }

        .close-alert-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .custom-alert-content p {
            margin: 0;
            font-size: 16px;
            color: var(--dark-grey);
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
                        <th>Business Name</th>
                        <th>Owner Name</th>
                        <th>Address</th>
                        <th>GST / UIN</th>
                        <th>State</th>
                        <th>Contact Information</th>
                    </tr>
                </thead>
                <tbody id="partyTableBody">
                    <?php
                        // This block fetches the initial data when the page loads.
                        if (isset($conn)) {
                            $sql = "SELECT id, business_name, owner_name, address, gst_uin, state, contact_number, email FROM parties";
                            $result = $conn->query($sql);

                            if ($result && $result->num_rows > 0) {
                                // Output data of each row
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr data-aos='fade-in' data-aos-delay='100'>";
                                    echo "<td><strong>" . htmlspecialchars($row["business_name"]) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row["owner_name"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["address"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["gst_uin"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["state"]) . "</td>";
                                    echo "<td>
                                            <div class='contact-info'>
                                                <span><i class='fas fa-phone'></i>" . htmlspecialchars($row["contact_number"]) . "</span>
                                                <span><i class='fas fa-envelope'></i>" . htmlspecialchars($row["email"]) . "</span>
                                            </div>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>No parties found. Click 'Add New Party' to get started.</td></tr>";
                            }
                            $conn->close();
                        } else {
                             echo "<tr><td colspan='6' style='text-align:center; padding: 20px; color: red;'>Error: Could not connect to the database. Please check your 'config/db.php' file.</td></tr>";
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
            <form id="addPartyForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="business_name">Business Name</label>
                        <input type="text" id="business_name" name="business_name" required>
                    </div>
                    <div class="form-group">
                        <label for="owner_name">Business Owner Name</label>
                        <input type="text" id="owner_name" name="owner_name" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="gst_uin">GST / UIN</label>
                        <input type="text" id="gst_uin" name="gst_uin" required maxlength="15">
                    </div>
                    <div class="form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" required>
                    </div>
                     <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="tel" id="contact_number" name="contact_number" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                     <button type="submit" class="add-party-btn submit-btn"><i class="fas fa-save"></i> Save Party</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Custom Alert Box -->
    <div id="customAlert" class="custom-alert">
        <div class="custom-alert-content">
            <span class="close-alert-btn">&times;</span>
            <p id="customAlertMessage"></p>
        </div>
    </div>


    <!-- AOS (Animate on Scroll) Library JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 600,
            once: true,
        });

        // --- Modal Logic ---
        const modal = document.getElementById("partyModal");
        const btn = document.getElementById("addPartyBtn");
        const span = document.getElementsByClassName("close-btn")[0];

        btn.onclick = function() {
            modal.style.display = "flex";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // --- Custom Alert Logic ---
        const customAlert = document.getElementById('customAlert');
        const closeAlertBtn = document.querySelector('.close-alert-btn');
        const customAlertMessage = document.getElementById('customAlertMessage');

        function showCustomAlert(message) {
            customAlertMessage.textContent = message;
            customAlert.style.display = 'flex';
        }

        closeAlertBtn.onclick = function() {
            customAlert.style.display = 'none';
        }
        
        // Also close the alert if clicking outside the content box
        window.addEventListener('click', function(event) {
            if (event.target == customAlert) {
                customAlert.style.display = 'none';
            }
        });

        
        // --- Form Submission Logic ---
        const form = document.getElementById('addPartyForm');
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(form);
            formData.append('action', 'save'); // Add the action parameter for the PHP handler

            // Fetch to the same file (index.php)
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modal.style.display = "none";
                    form.reset();
                    fetchParties(); // Refresh the table with new data
                } else {
                    showCustomAlert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomAlert('An unexpected error occurred.');
            });
        });

        // --- Function to fetch and update party table ---
        function fetchParties() {
            // Fetch from the same file, but with a GET parameter
            fetch(window.location.pathname + '?action=fetch')
            .then(response => response.text())
            .then(html => {
                const tableBody = document.getElementById('partyTableBody');
                tableBody.innerHTML = html;
                // Re-initialize AOS for any new elements that are faded in
                AOS.refresh();
            })
            .catch(error => console.error('Failed to fetch parties:', error));
        }
    </script>
</body>
</html>
