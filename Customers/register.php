<?php
session_start();
// --- DATABASE CONNECTION (adjust path as needed) ---
if (file_exists('../config/db.php')) {
    include '../config/db.php';
} else {
    die("Database configuration file not found.");
}

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $unique_id = trim($_POST['unique_id']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($unique_id) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match. Please try again.";
    } else {
        // Check if the unique ID exists and has not been registered yet
        $stmt = $conn->prepare("SELECT id, password FROM parties WHERE unique_id = ?");
        $stmt->bind_param("s", $unique_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Invalid Unique ID. Please check the ID provided by the admin.";
        } else {
            $party = $result->fetch_assoc();
            // Check if a password is already set
            if (!empty($party['password'])) {
                $error = "This account has already been registered. Please proceed to login.";
            } else {
                // Hash the password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update the party's record with the new password
                $update_stmt = $conn->prepare("UPDATE parties SET password = ?, status = 'pending' WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $party['id']);
                
                if ($update_stmt->execute()) {
                    $message = "Registration successful! Your account is now pending approval from the admin.";
                } else {
                    $error = "An error occurred. Please try again later.";
                }
                $update_stmt->close();
            }
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4f8;
        }
        .form-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .form-card {
            display: grid;
            grid-template-columns: 1fr;
            max-width: 900px;
            width: 100%;
            background-color: white;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        @media (min-width: 768px) {
            .form-card {
                grid-template-columns: 4fr 5fr;
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

    <div class="form-container">
        <div class="form-card" data-aos="zoom-in-up">
            <!-- Left Side Panel -->
            <div class="hidden md:flex flex-col items-center justify-center p-12 bg-teal-500 text-white text-center">
                <i class="fas fa-box-open fa-5x mb-6"></i>
                <h2 class="text-3xl font-bold mb-3">Join Our Exclusive Club</h2>
                <p class="text-teal-100">Sign up to unlock premium deals and experiences.</p>
            </div>

            <!-- Right Side Form -->
            <div class="p-8 sm:p-12">
                <div class="flex items-center gap-4 mb-6">
                    <i class="fas fa-user-plus text-3xl text-teal-500"></i>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Create Your Account</h1>
                        <p class="text-gray-500 text-sm">It's free and only takes a minute.</p>
                    </div>
                </div>

                <!-- Notification Messages -->
                <?php if (!empty($message)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
                        <p class="font-bold">Success</p>
                        <p><?php echo $message; ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                        <p class="font-bold">Error</p>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" class="space-y-6">
                    <div class="form-input-group">
                        <i class="fas fa-id-card form-input-icon"></i>
                        <!-- UPDATED: Changed padding classes from p-3 to py-3 pr-3 pl-12 -->
                        <input type="text" name="unique_id" placeholder="Your Unique ID" class="w-full border border-gray-300 rounded-lg py-3 pr-3 pl-12 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition" required>
                    </div>
                    <div class="form-input-group">
                        <i class="fas fa-lock form-input-icon"></i>
                        <!-- UPDATED: Changed padding classes from p-3 to py-3 pr-3 pl-12 -->
                        <input type="password" name="password" placeholder="Create Password" class="w-full border border-gray-300 rounded-lg py-3 pr-3 pl-12 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition" required>
                    </div>
                    <div class="form-input-group">
                        <i class="fas fa-check-circle form-input-icon"></i>
                        <!-- UPDATED: Changed padding classes from p-3 to py-3 pr-3 pl-12 -->
                        <input type="password" name="confirm_password" placeholder="Confirm Password" class="w-full border border-gray-300 rounded-lg py-3 pr-3 pl-12 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition" required>
                    </div>
                    <button type="submit" class="w-full bg-teal-500 text-white font-bold py-3 px-4 rounded-lg hover:bg-teal-600 transition-transform transform hover:scale-105 flex items-center justify-center gap-2">
                        Register Now <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <p class="text-center text-gray-500 mt-6">
                    Already have an account? <a href="login.php" class="text-teal-500 font-semibold hover:underline">Login here</a>
                </p>
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
