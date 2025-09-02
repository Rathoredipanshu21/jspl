<?php
// Start the session to access session variables
session_start();

// Check if the user is actually logged in
if (isset($_SESSION['user_id'])) {
    
    // Include the database connection
    include 'config/db.php';

    $user_id = $_SESSION['user_id'];
    $current_session_id = session_id();

    // Prepare a statement to get the current active sessions
    $stmt = $conn->prepare("SELECT active_sessions FROM aspl_admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $active_sessions = $user['active_sessions'] ? unserialize($user['active_sessions']) : [];

        // Find and remove the current session ID from the array
        if (($key = array_search($current_session_id, $active_sessions)) !== false) {
            unset($active_sessions[$key]);
        }

        // Update the database with the modified session list
        $serialized_sessions = serialize(array_values($active_sessions)); // Re-index array
        $update_stmt = $conn->prepare("UPDATE aspl_admin SET active_sessions = ? WHERE id = ?");
        $update_stmt->bind_param("si", $serialized_sessions, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    $stmt->close();
    $conn->close();
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>
