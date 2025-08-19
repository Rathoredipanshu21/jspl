<?php
// --- ERROR REPORTING (Crucial for Debugging) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DATABASE SETUP (Replace with your credentials) ---
include 'config/db.php';

// --- INITIALIZATION ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid request.'];

// Check if the database connection was successful
if ($conn->connect_error) {
    $response['message'] = "Database Connection Failed: " . $conn->connect_error;
    echo json_encode($response);
    exit(); // Stop execution if connection fails
}

// --- PROCESS POST DATA ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data && isset($data['order_pk']) && isset($data['status'])) {
    $order_pk = $data['order_pk'];
    $status = $data['status'];
    // Use null coalescing operator for cleaner code
    $condition = $data['condition'] ?? null;

    // --- DATABASE UPDATE ---
    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("UPDATE customer_orders SET status = ?, rejection_condition = ? WHERE id = ?");
    
    // Check if statement preparation was successful
    if ($stmt === false) {
        $response['message'] = 'SQL statement preparation failed: ' . $conn->error;
        echo json_encode($response);
        $conn->close();
        exit();
    }
    
    // Bind parameters: s = string, i = integer
    $stmt->bind_param("ssi", $status, $condition, $order_pk);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Order status updated successfully.';
    } else {
        $response['message'] = 'Error updating record: ' . $stmt->error;
    }

    $stmt->close();
} else {
    $response['message'] = 'Missing required data (order_pk or status).';
}

$conn->close();

// --- SEND RESPONSE ---
echo json_encode($response);
?>
