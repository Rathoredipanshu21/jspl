<?php
// Turn on error reporting for debugging. Turn off in production.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =============================================================================
// SECTION 1: DATABASE AND HELPER FUNCTIONS
// =============================================================================

// --- Database Configuration ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Your DB username
define('DB_PASSWORD', '');     // Your DB password
define('DB_NAME', 'jspl'); // IMPORTANT: Change this to your database name

/**
 * Establishes a database connection with robust error handling.
 * @return mysqli|null The mysqli connection object or null on failure.
 */
function getDbConnection() {
    // Enable error reporting for MySQLi
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        // In a real app, log this error. For this example, we'll just die.
        die("Database Connection Error: " . $e->getMessage() . "<br><br><strong>Troubleshooting Tips:</strong><br>1. Make sure your MySQL server (like in XAMPP) is running.<br>2. Ensure a database named '<strong>" . DB_NAME . "</strong>' exists.<br>3. Verify the database credentials (username/password) are correct in this file.<br>4. Run the SQL setup query provided in the comments to create the necessary tables.");
    }
}

/*
-- =============================================================================
-- REQUIRED SQL SETUP: Run this in your database (e.g., via phpMyAdmin)
-- =============================================================================
CREATE DATABASE IF NOT EXISTS `billing_system`;
USE `billing_system`;

CREATE TABLE IF NOT EXISTS `parties` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `business_name` VARCHAR(255) NOT NULL, `owner_name` VARCHAR(255) DEFAULT NULL, `address` TEXT, `gst_uin` VARCHAR(15) DEFAULT NULL, `state` VARCHAR(100) DEFAULT NULL, `pincode` VARCHAR(10) DEFAULT NULL, `contact_number` VARCHAR(20) DEFAULT NULL, `email` VARCHAR(255) DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `goods` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `product_name` VARCHAR(255) NOT NULL, `description` TEXT, `hsn_sac` VARCHAR(20) NOT NULL, `quantity` DECIMAL(10, 2) DEFAULT 0.00, `unit` VARCHAR(20) NOT NULL, `rate_per_gram` DECIMAL(15, 2) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `invoices` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `invoice_no` VARCHAR(50) NOT NULL, `invoice_date` DATE NOT NULL, `irn_no` VARCHAR(100) DEFAULT NULL, `ack_no` VARCHAR(100) DEFAULT NULL, `ack_date` DATE DEFAULT NULL, `consignee_id` INT NOT NULL, `biller_id` INT NOT NULL, `delivery_note` VARCHAR(100) DEFAULT NULL, `buyers_order_no` VARCHAR(50) DEFAULT NULL, `buyers_order_date` DATE DEFAULT NULL, `dispatch_through` VARCHAR(100) DEFAULT NULL, `bill_of_lading_no` VARCHAR(50) DEFAULT NULL, `bill_of_lading_date` DATE DEFAULT NULL, `motor_vehicle_no` VARCHAR(50) DEFAULT NULL, `sub_total` DECIMAL(15, 2) NOT NULL, `cgst_rate` DECIMAL(5, 2) NOT NULL DEFAULT 9.00, `cgst_amount` DECIMAL(15, 2) NOT NULL, `sgst_rate` DECIMAL(5, 2) NOT NULL DEFAULT 9.00, `sgst_amount` DECIMAL(15, 2) NOT NULL, `transit_insurance_rate` DECIMAL(8, 5) NOT NULL DEFAULT 0.02500, `transit_insurance_amount` DECIMAL(15, 2) NOT NULL, `grand_total` DECIMAL(15, 2) NOT NULL, `amount_in_words` VARCHAR(255) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`consignee_id`) REFERENCES `parties`(`id`), FOREIGN KEY (`biller_id`) REFERENCES `parties`(`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `invoice_items` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `invoice_id` INT NOT NULL, `product_id` INT NOT NULL, `product_name` VARCHAR(255) NOT NULL, `hsn_sac` VARCHAR(20) NOT NULL, `quantity` DECIMAL(10, 3) NOT NULL, `unit` VARCHAR(20) NOT NULL, `rate` DECIMAL(15, 2) NOT NULL, `amount` DECIMAL(15, 2) NOT NULL, FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE, FOREIGN KEY (`product_id`) REFERENCES `goods`(`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

/**
 * Converts a number to Indian currency words.
 * @param float $number The number to convert.
 * @return string The number in words.
 */
function numberToWords($number) {
    $hyphen      = ' '; $conjunction = ' and '; $separator   = ', '; $negative    = 'negative '; $dictionary  = array(0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety', 100 => 'hundred', 1000 => 'thousand', 100000 => 'lakh', 10000000 => 'crore');
    if (!is_numeric($number)) return false;
    if ($number < 0) return $negative . numberToWords(abs($number));
    $string = $fraction = null;
    if (strpos($number, '.') !== false) list($number, $fraction) = explode('.', $number);
    switch (true) {
        case $number < 21: $string = $dictionary[$number]; break;
        case $number < 100: $tens = ((int) ($number / 10)) * 10; $units = $number % 10; $string = $dictionary[$tens]; if ($units) $string .= $hyphen . $dictionary[$units]; break;
        case $number < 1000: $hundreds = floor($number / 100); $remainder = $number % 100; $string = $dictionary[$hundreds] . ' ' . $dictionary[100]; if ($remainder) $string .= $conjunction . numberToWords($remainder); break;
        case $number < 100000: $thousands = floor($number / 1000); $remainder = $number % 1000; $string = numberToWords($thousands) . ' ' . $dictionary[1000]; if ($remainder) $string .= $separator . numberToWords($remainder); break;
        case $number < 10000000: $lakhs = floor($number / 100000); $remainder = $number % 100000; $string = numberToWords($lakhs) . ' ' . $dictionary[100000]; if ($remainder) $string .= $separator . numberToWords($remainder); break;
        default: $crores = floor($number / 10000000); $remainder = $number % 10000000; $string = numberToWords($crores) . ' ' . $dictionary[10000000]; if ($remainder) $string .= $separator . numberToWords($remainder); break;
    }
    if (null !== $fraction && is_numeric($fraction) && (int)$fraction > 0) {
        $string .= ' and ' . numberToWords((int)substr($fraction, 0, 2)) . ' Paise';
    }
    return ucwords($string);
}

// =============================================================================
// SECTION 2: ROUTING AND ACTION HANDLING
// =============================================================================

$action = $_REQUEST['action'] ?? 'create'; // Default action

// --- AJAX Request Handling ---
if ($action === 'ajax') {
    header('Content-Type: application/json');
    $conn = getDbConnection();
    $request_type = $_GET['request'] ?? '';

    if ($request_type === 'get_party' && isset($_GET['id'])) {
        $party_id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT `business_name`, `owner_name`, `address`, `gst_uin`, `state`, `pincode`, `contact_number`, `email` FROM `parties` WHERE id = ?");
        $stmt->bind_param("i", $party_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($party = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $party]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Party not found.']);
        }
    } elseif ($request_type === 'search_goods' && isset($_GET['term'])) {
        $term = $_GET['term'];
        $words = array_filter(explode(' ', $term));
        $conditions = []; $params = []; $types = '';
        foreach ($words as $word) {
            $conditions[] = "(`product_name` LIKE ? OR `hsn_sac` LIKE ?)";
            $like_word = "%" . $word . "%";
            $params[] = $like_word; $params[] = $like_word;
            $types .= 'ss';
        }
        if (!empty($conditions)) {
            $sql = "SELECT `id`, `product_name`, `description`, `hsn_sac`, `unit`, `rate_per_gram` as rate FROM `goods` WHERE " . implode(' AND ', $conditions) . " LIMIT 10";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            echo json_encode([]);
        }
    }
    $conn->close();
    exit;
}

// --- Save Invoice Form Submission ---
if ($action === 'save_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDbConnection();
    $conn->begin_transaction();
    try {
        $sub_total = 0;
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $sub_total += (float)$item['amount'];
            }
        }
        $cgst_amount = $sub_total * 0.09;
        $sgst_amount = $sub_total * 0.09;
        $transit_insurance_amount = $sub_total * 0.00025;
        $grand_total = $sub_total + $cgst_amount + $sgst_amount + $transit_insurance_amount;
        $amount_in_words = "INR " . numberToWords($grand_total) . " Only";

        $stmt_invoice = $conn->prepare(
            "INSERT INTO invoices (invoice_no, invoice_date, irn_no, ack_no, ack_date, consignee_id, biller_id, delivery_note, buyers_order_no, buyers_order_date, dispatch_through, bill_of_lading_no, bill_of_lading_date, motor_vehicle_no, sub_total, cgst_amount, sgst_amount, transit_insurance_amount, grand_total, amount_in_words) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_invoice->bind_param(
            "sssssiisssssssdsssss",
            $_POST['invoice_no'], $_POST['invoice_date'], $_POST['irn_no'], $_POST['ack_no'], $_POST['ack_date'],
            $_POST['consignee_id'], $_POST['biller_id'], $_POST['delivery_note'], $_POST['buyers_order_no'],
            $_POST['buyers_order_date'], $_POST['dispatch_through'], $_POST['bill_of_lading_no'],
            $_POST['bill_of_lading_date'], $_POST['motor_vehicle_no'], $sub_total,
            $cgst_amount, $sgst_amount, $transit_insurance_amount, $grand_total, $amount_in_words
        );
        $stmt_invoice->execute();
        $invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        if (!$invoice_id) throw new Exception("Failed to create invoice record. The database did not return a new ID.");

        $stmt_items = $conn->prepare(
            "INSERT INTO invoice_items (invoice_id, product_id, product_name, hsn_sac, quantity, unit, rate, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($_POST['items'] as $item) {
            $stmt_items->bind_param(
                "iisssddd",
                $invoice_id, $item['product_id'], $item['product_name'], $item['hsn_sac'],
                $item['quantity'], $item['unit'], $item['rate'], $item['amount']
            );
            $stmt_items->execute();
        }
        $stmt_items->close();

        $conn->commit();
        header("Location: ?action=print&id=" . $invoice_id . "&status=success");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error processing invoice: " . $e->getMessage());
    } finally {
        $conn->close();
    }
}

// =============================================================================
// SECTION 3: HTML, CSS, AND JAVASCRIPT PRESENTATION
// =============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .invoice-container, .invoice-box { background-color: #ffffff; padding: 2.5rem; margin-top: 2rem; margin-bottom: 2rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .invoice-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #dee2e6; padding-bottom: 1rem; margin-bottom: 2rem; }
        .invoice-header .logo { max-height: 80px; }
        .invoice-header h1 { font-weight: 700; color: #343a40; margin: 0; }
        .section-title { font-weight: 600; color: #0d6efd; border-bottom: 2px solid #0d6efd; padding-bottom: 5px; margin-bottom: 1.5rem; font-size: 1.2rem; }
        .form-control:read-only { background-color: #e9ecef; cursor: not-allowed; }
        .search-results { position: absolute; background-color: white; border: 1px solid #ddd; z-index: 1000; width: calc(100% - 2rem); max-height: 250px; overflow-y: auto; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .search-item { padding: 10px; cursor: pointer; }
        .search-item:hover { background-color: #f0f2f5; }
        .totals-section { background-color: #f8f9fa; padding: 1.5rem; border-radius: 10px; }
        .grand-total { font-size: 1.5rem; color: #0d6efd; }
        .party-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
        /* Print-specific styles */
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; font-size: 14px; line-height: 20px; color: #555; }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .invoice-box table td { padding: 5px; vertical-align: top; }
        .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
        .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
        .no-print { text-align: center; margin-top: 20px; }
        @media print {
            body { background-color: #fff; }
            .no-print, .invoice-container:not(.invoice-box) { display: none; }
            .invoice-box { display: block !important; box-shadow: none; border: none; margin: 0; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($action === 'print' && isset($_GET['id'])): ?>
        <?php
        $conn = getDbConnection();
        $invoice_id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT i.*, 
            c.business_name as consignee_name, c.address as consignee_address, c.gst_uin as consignee_gst, c.state as consignee_state, c.pincode as consignee_pincode,
            b.business_name as biller_name, b.address as biller_address, b.gst_uin as biller_gst, b.state as biller_state, b.pincode as biller_pincode
            FROM invoices i
            JOIN parties c ON i.consignee_id = c.id
            JOIN parties b ON i.biller_id = b.id
            WHERE i.id = ?");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        
        $items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $items_stmt->bind_param("i", $invoice_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        $conn->close();


        if (!$invoice) die('Invoice not found.');
        ?>
        <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success mt-4 no-print">Invoice saved successfully!</div>
        <?php endif; ?>

        <div class="invoice-box">
            <table cellpadding="0" cellspacing="0">
                <tr class="top"><td colspan="4"><table><tr>
                    <td><img src="Assets/logo.jpg" style="max-width: 150px;" alt="Logo"></td>
                    <td class="text-end"><strong>Tax Invoice</strong><br>(Original for Recipient)</td>
                </tr></table></td></tr>
                <tr><td colspan="2"><strong>IRN:</strong> <?= htmlspecialchars($invoice['irn_no']) ?></td><td colspan="2" class="text-end"><img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= urlencode('Invoice No: '.$invoice['invoice_no']) ?>" alt="QR Code"></td></tr>
                <tr class="information"><td colspan="2"><strong>Sold By: JSPL STEEL</strong><br>Third Floor, Plot No-747, Khata No-11,<br>LAL Bangla, Gosaidih, Govindpur,<br>Dhanbad, Jharkhand - 828109</td><td colspan="2" class="text-end"><strong>Invoice No.:</strong> <?= htmlspecialchars($invoice['invoice_no']) ?><br><strong>Dated:</strong> <?= date("d-M-Y", strtotime($invoice['invoice_date'])) ?></td></tr>
                <tr class="heading"><td colspan="2">Consignee (Ship To)</td><td colspan="2">Buyer (Bill To)</td></tr>
                <tr class="details"><td colspan="2"><strong><?= htmlspecialchars($invoice['consignee_name']) ?></strong><br><?= nl2br(htmlspecialchars($invoice['consignee_address'])) ?><br><strong>State:</strong> <?= htmlspecialchars($invoice['consignee_state']) ?>, <strong>PIN:</strong> <?= htmlspecialchars($invoice['consignee_pincode']) ?><br><strong>GSTIN/UIN:</strong> <?= htmlspecialchars($invoice['consignee_gst']) ?></td><td colspan="2"><strong><?= htmlspecialchars($invoice['biller_name']) ?></strong><br><?= nl2br(htmlspecialchars($invoice['biller_address'])) ?><br><strong>State:</strong> <?= htmlspecialchars($invoice['biller_state']) ?>, <strong>PIN:</strong> <?= htmlspecialchars($invoice['biller_pincode']) ?><br><strong>GSTIN/UIN:</strong> <?= htmlspecialchars($invoice['biller_gst']) ?></td></tr>
                <tr class="heading"><td>Description of Goods</td><td>HSN/SAC</td><td class="text-end">Qty</td><td class="text-end">Amount</td></tr>
                <?php $total_qty = 0; while ($item = $items_result->fetch_assoc()): $total_qty += $item['quantity']; ?>
                <tr class="item"><td><?= htmlspecialchars($item['product_name']) ?></td><td><?= htmlspecialchars($item['hsn_sac']) ?></td><td class="text-end"><?= rtrim(rtrim(number_format($item['quantity'], 3), '0'), '.') ?> <?= $item['unit'] ?></td><td class="text-end"><?= number_format($item['amount'], 2) ?></td></tr>
                <?php endwhile; ?>
                <tr style="font-weight:bold;"><td colspan="2" class="text-end">Total</td><td class="text-end"><?= rtrim(rtrim(number_format($total_qty, 3), '0'), '.') ?></td><td class="text-end"><?= number_format($invoice['sub_total'], 2) ?></td></tr>
                <tr><td colspan="2" rowspan="4" style="vertical-align: bottom;"><strong>Amount (in words):</strong><br><?= htmlspecialchars($invoice['amount_in_words']) ?></td><td>CGST @9%</td><td class="text-end"><?= number_format($invoice['cgst_amount'], 2) ?></td></tr>
                <tr><td>SGST @9%</td><td class="text-end"><?= number_format($invoice['sgst_amount'], 2) ?></td></tr>
                <tr><td>Transit Insurance @0.025%</td><td class="text-end"><?= number_format($invoice['transit_insurance_amount'], 2) ?></td></tr>
                <tr style="font-weight:bold; border-top:2px solid #eee;"><td>Grand Total</td><td class="text-end"><?= number_format($invoice['grand_total'], 2) ?></td></tr>
            </table>
        </div>
        <div class="no-print"><button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print Invoice</button> <a href="?" class="btn btn-primary"><i class="fas fa-plus"></i> Create New Invoice</a></div>

    <?php else: ?>
        <?php
        $conn = getDbConnection();
        $parties_result = $conn->query("SELECT `id`, `business_name` FROM `parties` ORDER BY `business_name` ASC");
        ?>
        <form action="?action=save_invoice" method="post" id="invoice-form">
            <div class="invoice-container">
                <header class="invoice-header">
                    <img src="Assets/logo.jpg" alt="Company Logo" class="logo">
                    <h1>TAX INVOICE</h1>
                    <div style="width: 80px; height: 80px; border: 1px solid #ccc; text-align: center; line-height: 80px; font-size:12px;">QR Code</div>
                </header>
                <div class="row mb-4"><div class="col-md-6"><div class="form-group mb-2"><label for="irn_no">IRN:</label><input type="text" class="form-control" id="irn_no" name="irn_no"></div><div class="form-group mb-2"><label for="ack_no">Ack No.:</label><input type="text" class="form-control" id="ack_no" name="ack_no"></div><div class="form-group"><label for="ack_date">Ack Date:</label><input type="date" class="form-control" id="ack_date" name="ack_date"></div></div></div><hr>
                <div class="row mb-4">
                    <div class="col-md-6"><h5 class="section-title">Sold By</h5><h6 class="fw-bold">JSPL STEEL</h6><p class="mb-1">Third Floor, Plot No-747, Khata No-11,<br>LAL Bangla, Gosaidih, P.O = K G Ashram, Govindpur,<br>Dhanbad, Jharkhand - 828109</p></div>
                    <div class="col-md-6"><div class="row">
                        <div class="col-6 mb-2"><label for="invoice_no">Invoice No.</label><input type="text" class="form-control" id="invoice_no" name="invoice_no" required></div>
                        <div class="col-6 mb-2"><label for="invoice_date">Dated</label><input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="col-12 mb-2"><label for="delivery_note">Delivery Note</label><input type="text" class="form-control" id="delivery_note" name="delivery_note"></div>
                        <div class="col-6 mb-2"><label for="buyers_order_no">Buyer's Order No.</label><input type="text" class="form-control" id="buyers_order_no" name="buyers_order_no"></div>
                        <div class="col-6 mb-2"><label for="buyers_order_date">Dated</label><input type="date" class="form-control" id="buyers_order_date" name="buyers_order_date"></div>
                        <div class="col-12 mb-2"><label for="dispatch_through">Dispatch Through</label><input type="text" class="form-control" id="dispatch_through" name="dispatch_through"></div>
                        <div class="col-6 mb-2"><label for="bill_of_lading_no">Bill of Lading/LR-RR No.</label><input type="text" class="form-control" id="bill_of_lading_no" name="bill_of_lading_no"></div>
                        <div class="col-6 mb-2"><label for="bill_of_lading_date">Dated</label><input type="date" class="form-control" id="bill_of_lading_date" name="bill_of_lading_date"></div>
                        <div class="col-12 mb-2"><label for="motor_vehicle_no">Motor Vehicle No.</label><input type="text" class="form-control" id="motor_vehicle_no" name="motor_vehicle_no"></div>
                    </div></div>
                </div>
                <div class="row border-top border-bottom py-3 mb-4">
                    <div class="col-md-6 border-end">
                        <h5 class="section-title"><i class="fas fa-truck me-2"></i>Consignee (Ship To)</h5>
                        <div class="form-group mb-2"><label for="consignee_id">Select Party</label><select class="form-select" id="consignee_id" name="consignee_id" required><option value="">-- Choose Party --</option><?php while($party = $parties_result->fetch_assoc()): ?><option value="<?= $party['id'] ?>"><?= htmlspecialchars($party['business_name']) ?></option><?php endwhile; ?></select></div>
                        <input type="text" class="form-control mb-1" id="consignee_business_name" placeholder="Business Name" readonly>
                        <textarea class="form-control mb-1" id="consignee_address" placeholder="Address" readonly rows="2"></textarea>
                        <div class="party-details-grid"><input type="text" class="form-control" id="consignee_gst" placeholder="GST/UIN" readonly><input type="text" class="form-control" id="consignee_state" placeholder="State" readonly></div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="section-title"><i class="fas fa-file-invoice-dollar me-2"></i>Biller (Bill To)</h5>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="same_as_consignee"><label class="form-check-label" for="same_as_consignee">Same as Consignee</label></div>
                        <div class="form-group mb-2"><select class="form-select" id="biller_id" name="biller_id" required><option value="">-- Choose Party --</option><?php $parties_result->data_seek(0); while($party = $parties_result->fetch_assoc()): ?><option value="<?= $party['id'] ?>"><?= htmlspecialchars($party['business_name']) ?></option><?php endwhile; ?></select></div>
                        <input type="text" class="form-control mb-1" id="biller_business_name" placeholder="Business Name" readonly>
                        <textarea class="form-control mb-1" id="biller_address" placeholder="Address" readonly rows="2"></textarea>
                        <div class="party-details-grid"><input type="text" class="form-control" id="biller_gst" placeholder="GST/UIN" readonly><input type="text" class="form-control" id="biller_state" placeholder="State" readonly></div>
                    </div>
                </div>
                <div class="input-group mb-3"><span class="input-group-text"><i class="fas fa-search"></i></span><input type="text" class="form-control" id="good-search" placeholder="Search for products by name or HSN..."></div>
                <div id="search-results-container" class="search-results" style="display:none;"></div>
                <table class="table table-bordered" id="items-table"><thead class="table-dark"><tr><th>#</th><th>Description</th><th>HSN</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Amount</th><th>Act</th></tr></thead><tbody id="item-rows"></tbody></table>
                <div class="row mt-4"><div class="col-md-7"><label><strong>Amount in Words:</strong></label><input type="text" class="form-control-plaintext" id="amount_in_words_display" readonly></div><div class="col-md-5"><div class="totals-section"><dl class="row"><dt class="col-7">Sub Total</dt><dd class="col-5 text-end" id="sub-total">0.00</dd><dt class="col-7">CGST @9%</dt><dd class="col-5 text-end" id="cgst-total">0.00</dd><dt class="col-7">SGST @9%</dt><dd class="col-5 text-end" id="sgst-total">0.00</dd><dt class="col-7">Transit Ins. @0.025%</dt><dd class="col-5 text-end" id="insurance-total">0.00</dd><hr class="my-2"><dt class="col-7 grand-total">GRAND TOTAL</dt><dd class="col-5 text-end" id="grand-total">0.00</dd></dl></div></div></div>
                <hr class="my-4"><div class="text-center d-flex justify-content-center"><button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Save Invoice</button><button type="button" id="preview-invoice" class="btn btn-info btn-lg ms-3"><i class="fas fa-eye me-2"></i>Generate Invoice</button></div>
            </div>
        </form>
        <?php $conn->close(); ?>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    function getPartyDetails(partyId, type) {
        const fields = ['business_name', 'address', 'gst', 'state'];
        if (!partyId) {
            fields.forEach(field => $('#' + type + '_' + field).val(''));
            return;
        }
        $.ajax({
            url: '?action=ajax&request=get_party', type: 'GET', data: { id: partyId }, dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    $('#' + type + '_business_name').val(data.business_name);
                    $('#' + type + '_address').val(data.address);
                    $('#' + type + '_gst').val(data.gst_uin);
                    $('#' + type + '_state').val(data.state);
                }
            }
        });
    }

    $('#consignee_id').change(function() { 
        getPartyDetails($(this).val(), 'consignee'); 
        if ($('#same_as_consignee').is(':checked')) { 
            $('#biller_id').val($(this).val()).trigger('change'); 
        } 
    });

    $('#biller_id').change(function() { 
        getPartyDetails($(this).val(), 'biller'); 
    });

    $('#same_as_consignee').change(function() {
        if ($(this).is(':checked')) {
            // Set the value and trigger change. Crucially, DO NOT disable the dropdown.
            // Disabling a field prevents it from being submitted with the form.
            $('#biller_id').val($('#consignee_id').val()).trigger('change');
            // Instead of disabling, we can visually indicate it's locked.
            $('#biller_id').css({'pointer-events': 'none', 'background-color': '#e9ecef'});
        } else {
            // Re-enable interaction and clear the value if unchecked.
            $('#biller_id').val('').trigger('change');
            $('#biller_id').css({'pointer-events': 'auto', 'background-color': '#fff'});
        }
    });

    $('#good-search').on('keyup', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length > 1) {
            $.ajax({
                url: '?action=ajax&request=search_goods', type: 'GET', data: { term: searchTerm }, dataType: 'json',
                success: function(data) {
                    let resultsHtml = data.length > 0 ? data.map(item => `<div class="search-item" data-id="${item.id}" data-name="${item.product_name}" data-hsn="${item.hsn_sac}" data-unit="${item.unit}" data-rate="${item.rate}">${item.product_name} (${item.hsn_sac})</div>`).join('') : '<div class="p-2">No products found.</div>';
                    $('#search-results-container').html(resultsHtml).show();
                }
            });
        } else { $('#search-results-container').hide(); }
    });

    let itemCounter = 0;
    $(document).on('click', '.search-item', function() {
        if($(this).data('id')) {
            itemCounter++;
            const item = $(this).data();
            const rowHtml = `<tr class="item-row"><td>${itemCounter}</td><td>${item.name}<input type="hidden" name="items[${itemCounter}][product_id]" value="${item.id}"><input type="hidden" name="items[${itemCounter}][product_name]" value="${item.name}"></td><td>${item.hsn}<input type="hidden" name="items[${itemCounter}][hsn_sac]" value="${item.hsn}"></td><td><input type="number" class="form-control quantity" name="items[${itemCounter}][quantity]" value="1" min="0.001" step="0.001"></td><td>${item.unit}<input type="hidden" name="items[${itemCounter}][unit]" value="${item.unit}"></td><td><input type="number" class="form-control rate" name="items[${itemCounter}][rate]" value="${parseFloat(item.rate).toFixed(2)}" step="0.01"></td><td><input type="text" class="form-control amount" name="items[${itemCounter}][amount]" readonly></td><td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-trash"></i></button></td></tr>`;
            $('#item-rows').append(rowHtml);
            updateCalculations();
        }
        $('#good-search').val('');
        $('#search-results-container').hide();
    });

    $('#items-table').on('click', '.remove-item', function() { $(this).closest('tr').remove(); updateCalculations(); });
    $('#items-table').on('input', '.quantity, .rate', function() { updateCalculations(); });

    function updateCalculations() {
        let subTotal = 0;
        $('.item-row').each(function(index) {
            $(this).find('td:first').text(index + 1); // Renumber rows
            const row = $(this);
            const quantity = parseFloat(row.find('.quantity').val()) || 0;
            const rate = parseFloat(row.find('.rate').val()) || 0;
            const amount = quantity * rate;
            row.find('.amount').val(amount.toFixed(2));
            subTotal += amount;
        });
        const cgstAmount = subTotal * 0.09;
        const sgstAmount = subTotal * 0.09;
        const insuranceAmount = subTotal * 0.00025;
        const grandTotal = subTotal + cgstAmount + sgstAmount + insuranceAmount;
        $('#sub-total').text(subTotal.toFixed(2));
        $('#cgst-total').text(cgstAmount.toFixed(2));
        $('#sgst-total').text(sgstAmount.toFixed(2));
        $('#insurance-total').text(insuranceAmount.toFixed(2));
        $('#grand-total').text(grandTotal.toFixed(2));
        $('#amount_in_words_display').val('Amount will be calculated upon saving...');
    }

    $('#invoice-form').on('submit', function(e){ if ($('.item-row').length === 0) { alert('Please add at least one item to the invoice.'); e.preventDefault(); } });
    
    // --- Generate Invoice Preview ---
    $('#preview-invoice').on('click', function() {
        let itemsHtml = '';
        let totalQty = 0;
        $('.item-row').each(function() {
            const name = $(this).find('input[name*="[product_name]"]').val();
            const hsn = $(this).find('input[name*="[hsn_sac]"]').val();
            const qty = parseFloat($(this).find('.quantity').val() || 0);
            const unit = $(this).find('input[name*="[unit]"]').val();
            const amount = parseFloat($(this).find('.amount').val() || 0).toFixed(2);
            totalQty += qty;
            itemsHtml += `<tr class="item"><td>${name}</td><td>${hsn}</td><td class="text-end">${qty} ${unit}</td><td class="text-end">${amount}</td></tr>`;
        });

        const subTotal = parseFloat($('#sub-total').text()).toFixed(2);
        const grandTotal = parseFloat($('#grand-total').text()).toFixed(2);

        const previewHtml = `
            <html><head><title>Invoice Preview</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body{font-family:sans-serif;} .invoice-box{max-width:800px; margin:auto; padding:30px; border:1px solid #eee; font-size:14px; line-height:20px;}
                .invoice-box table{width:100%; line-height:inherit; text-align:left; border-collapse:collapse;}
                .invoice-box table td{padding:5px; vertical-align:top;} .invoice-box table tr.heading td{background:#eee; border-bottom:1px solid #ddd; font-weight:bold;}
                .invoice-box table tr.item td{border-bottom:1px solid #eee;} .text-end{text-align:right!important;}
            </style></head><body>
            <div class="invoice-box">
                <h1 class="text-center mb-4">INVOICE PREVIEW</h1>
                <table>
                    <tr><td colspan="2"><strong>Sold By: JSPL STEEL</strong><br>Dhanbad, Jharkhand</td><td colspan="2" class="text-end"><strong>Invoice No:</strong> ${$('#invoice_no').val()}<br><strong>Dated:</strong> ${$('#invoice_date').val()}</td></tr>
                    <tr class="heading"><td colspan="2">Consignee (Ship To)</td><td colspan="2">Buyer (Bill To)</td></tr>
                    <tr><td colspan="2"><strong>${$('#consignee_business_name').val()}</strong><br>${$('#consignee_address').val().replace(/\n/g, '<br>')}<br><strong>GST:</strong> ${$('#consignee_gst').val()}</td><td colspan="2"><strong>${$('#biller_business_name').val()}</strong><br>${$('#biller_address').val().replace(/\n/g, '<br>')}<br><strong>GST:</strong> ${$('#biller_gst').val()}</td></tr>
                    <tr class="heading"><td>Description</td><td>HSN/SAC</td><td class="text-end">Qty</td><td class="text-end">Amount</td></tr>
                    ${itemsHtml}
                    <tr style="font-weight:bold;"><td colspan="2" class="text-end">Total</td><td class="text-end">${totalQty}</td><td class="text-end">${subTotal}</td></tr>
                    <tr><td colspan="2" rowspan="4"></td><td>CGST @9%</td><td class="text-end">${$('#cgst-total').text()}</td></tr>
                    <tr><td>SGST @9%</td><td class="text-end">${$('#sgst-total').text()}</td></tr>
                    <tr><td>Transit Ins. @0.025%</td><td class="text-end">${$('#insurance-total').text()}</td></tr>
                    <tr style="font-weight:bold; border-top:2px solid #eee;"><td>Grand Total</td><td class="text-end">${grandTotal}</td></tr>
                </table>
            </div>
            </body></html>`;

        const previewWindow = window.open('', 'Invoice Preview');
        previewWindow.document.write(previewHtml);
        previewWindow.document.close();
    });
});
</script>

</body>
</html>
