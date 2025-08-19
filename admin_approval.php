<?php
session_start();
// --- (IMPORTANT) Add your admin session check here ---
// For example:
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: admin_login.php");
//     exit();
// }

// --- DATABASE CONNECTION (adjust path as needed) ---
if (file_exists('config/db.php')) {
    include 'config/db.php';
} else {
    die("Database configuration file not found.");
}

$message = '';
$error = '';

// --- Handle Approval/Rejection Actions ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['party_id']) && isset($_POST['action'])) {
    $party_id = intval($_POST['party_id']);
    $action = $_POST['action']; // 'approve' or 'reject'
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE parties SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $party_id);
    if ($stmt->execute()) {
        $message = "Party has been successfully " . $new_status . ".";
    } else {
        $error = "Failed to update status.";
    }
    $stmt->close();
}

// --- Fetch all pending parties ---
$pending_parties = [];
$result = $conn->query("SELECT id, unique_id, business_name, owner_name, contact_number, email, created_at FROM parties WHERE status = 'pending'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pending_parties[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Pending Approvals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4f8;
        }
    </style>
</head>
<body class="p-4 sm:p-6 lg:p-8">

<div class="max-w-7xl mx-auto">
    <header class="mb-8" data-aos="fade-down">
        <div class="bg-white rounded-2xl shadow-lg p-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Pending Approvals</h1>
                <p class="text-gray-600 mt-1">Review and manage new party registrations.</p>
            </div>
            <i class="fas fa-user-check fa-3x text-teal-500"></i>
        </div>
    </header>

    <!-- Notifications -->
    <?php if (!empty($message)): ?>
        <div data-aos="fade-left" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert"><p><?php echo $message; ?></p></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div data-aos="fade-left" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert"><p><?php echo $error; ?></p></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-lg p-6" data-aos="fade-up">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Owner / Business</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Unique ID</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($pending_parties)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No pending approvals at the moment.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_parties as $party): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($party['owner_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($party['business_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><i class="fas fa-phone-alt mr-2 text-gray-400"></i><?php echo htmlspecialchars($party['contact_number']); ?></div>
                                    <div class="text-sm text-gray-500"><i class="fas fa-envelope mr-2 text-gray-400"></i><?php echo htmlspecialchars($party['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-mono"><?php echo htmlspecialchars($party['unique_id']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" class="inline-flex gap-2">
                                        <input type="hidden" name="party_id" value="<?php echo $party['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-3 rounded-lg transition-transform transform hover:scale-105" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="submit" name="action" value="reject" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-3 rounded-lg transition-transform transform hover:scale-105" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
