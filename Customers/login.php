<?php
session_start();
// --- DATABASE CONNECTION (adjust path as needed) ---
if (file_exists('../config/db.php')) {
    include '../config/db.php';
} else {
    // This is a placeholder for the connection.
    // Replace with your actual database connection logic.
    // For demonstration, we'll assume a $conn object exists.
    // Example: $conn = new mysqli("localhost", "username", "password", "database");
    // If connection fails: die("Connection failed: " . $conn->connect_error);
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assuming $conn is your database connection variable
    if (!isset($conn)) {
        $error = "Database connection is not configured.";
    } else {
        $unique_id = trim($_POST['unique_id']);
        $password = $_POST['password'];

        if (empty($unique_id) || empty($password)) {
            $error = "Both Unique ID and Password are required.";
        } else {
            // UPDATED: Query now selects business_name
            $stmt = $conn->prepare("SELECT id, password, status, owner_name, business_name FROM parties WHERE unique_id = ?");
            $stmt->bind_param("s", $unique_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $party = $result->fetch_assoc();

                if (empty($party['password'])) {
                    $error = "Your account is not yet registered. Please complete the registration process.";
                } elseif (password_verify($password, $party['password'])) {
                    // Password is correct, now check status
                    if ($party['status'] == 'approved') {
                        // Login successful
                        // UPDATED: Storing owner_name and business_name in the session
                        $_SESSION['party_id'] = $party['id'];
                        $_SESSION['owner_name'] = $party['owner_name'];
                        $_SESSION['business_name'] = $party['business_name'];
                        header("Location: index.php");
                        exit();
                    } elseif ($party['status'] == 'pending') {
                        $error = "Your account is awaiting admin approval. Please check back later.";
                    } elseif ($party['status'] == 'rejected') {
                        $error = "Your account registration has been rejected. Please contact support.";
                    } else {
                        $error = "Invalid account status.";
                    }
                } else {
                    $error = "Invalid Unique ID or Password.";
                }
            } else {
                $error = "Invalid Unique ID or Password.";
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4f8;
        }
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .login-card {
            display: grid;
            grid-template-columns: 1fr;
            max-width: 800px;
            width: 100%;
            background-color: white;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        @media (min-width: 768px) {
            .login-card {
                grid-template-columns: 5fr 4fr;
            }
        }
        .form-input-group {
            position: relative;
        }
        .form-input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none; /* Make icon non-interactive */
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-card" data-aos="fade-up">
            <div class="p-8 sm:p-12">
                <div class="flex items-center gap-4 mb-6">
                    <i class="fas fa-sign-in-alt text-3xl text-teal-500"></i>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Secure Login</h1>
                        <p class="text-gray-500 text-sm">Welcome back! Please enter your details.</p>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" class="space-y-6">
                    <div class="form-input-group">
                        <i class="fas fa-id-card form-input-icon"></i>
                        <input type="text" name="unique_id" placeholder="Your Unique ID" class="w-full border border-gray-300 rounded-lg py-3 pr-3 pl-12 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition" required>
                    </div>
                    <div class="form-input-group">
                        <i class="fas fa-lock form-input-icon"></i>
                        <input type="password" name="password" placeholder="Password" class="w-full border border-gray-300 rounded-lg py-3 pr-3 pl-12 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition" required>
                    </div>
                    <button type="submit" class="w-full bg-teal-500 text-white font-bold py-3 px-4 rounded-lg hover:bg-teal-600 transition-transform transform hover:scale-105 flex items-center justify-center gap-2">
                        Login Securely <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <p class="text-center text-gray-500 mt-6">
                    Don't have an account? <a href="register.php" class="text-teal-500 font-semibold hover:underline">Register here</a>
                </p>
            </div>
            
            <div class="hidden md:flex flex-col items-center justify-center p-12 bg-teal-500 text-white text-center">
                <i class="fas fa-lock fa-5x mb-6"></i>
                <h2 class="text-3xl font-bold mb-3">Welcome Back!</h2>
                <p class="text-teal-100">Your next great stay is just a login away.</p>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
        });
    </script>
</body>
</html>