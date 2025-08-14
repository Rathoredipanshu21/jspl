<?php
// config/db.php
// This file contains the database connection settings.
// Store your database credentials here.

// --- Database Credentials ---
$servername = "localhost"; // Or your database server IP
$username = "root";        // Your database username
$password = "";            // Your database password
$dbname = "jspl"; // << IMPORTANT: Change this to your actual database name

// --- Create Connection ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Check Connection ---
if ($conn->connect_error) {
    // If connection fails, stop the script and display an error.
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");

?>
