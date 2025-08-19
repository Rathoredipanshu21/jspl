<?php
// Includes your db.php file and makes the $conn variable available.
require_once 'config/db.php';

/**
 * Helper function to convert an integer to words recursively for the Indian numbering system.
 * This function should not be called directly from outside numberToWords.
 * @param int $number The integer to convert.
 * @return string The number in words, without currency units.
 */
function numberToWordsRecursive($number) {
    $hyphen      = ' ';
    $conjunction = ' and ';
    $separator   = ', ';
    $dictionary  = array(0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety', 100 => 'hundred', 1000 => 'thousand', 100000 => 'lakh', 10000000 => 'crore');
    
    $string = '';
    
    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens = ((int) ($number / 10)) * 10;
            $units = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds = floor($number / 100);
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . numberToWordsRecursive($remainder);
            }
            break;
        case $number < 100000:
            $thousands = floor($number / 1000);
            $remainder = $number % 1000;
            $string = numberToWordsRecursive($thousands) . ' ' . $dictionary[1000];
            if ($remainder) {
                $string .= $separator . numberToWordsRecursive($remainder);
            }
            break;
        case $number < 10000000:
            $lakhs = floor($number / 100000);
            $remainder = $number % 100000;
            $string = numberToWordsRecursive($lakhs) . ' ' . $dictionary[100000];
            if ($remainder) {
                $string .= $separator . numberToWordsRecursive($remainder);
            }
            break;
        default:
            $crores = floor($number / 10000000);
            $remainder = $number % 10000000;
            $string = numberToWordsRecursive($crores) . ' ' . $dictionary[10000000];
            if ($remainder) {
                $string .= $separator . numberToWordsRecursive($remainder);
            }
            break;
    }
    return $string;
}

/**
 * Converts a number to Indian currency words with precise paise handling.
 * @param float $number The number to convert.
 * @return string The number in words.
 */
function numberToWords($number) {
    if (!is_numeric($number)) {
        return false;
    }
    
    if ($number < 0) {
        return 'Negative ' . numberToWords(abs($number));
    }
    
    // Format the number to exactly two decimal places.
    $number = number_format($number, 2, '.', '');
    
    // Split into the integer (rupees) and fractional (paise) parts.
    list($rupees, $paise) = explode('.', $number);
    $rupees = (int)$rupees;
    $paise = (int)$paise;

    // Convert the rupees part to words. If rupees is 0, it should say "Zero Rupees".
    $rupees_in_words = ($rupees == 0) ? 'Zero' : numberToWordsRecursive($rupees);
    
    $final_string = ucwords($rupees_in_words) . ' Rupees';
    
    // If there is a paise part greater than 0, convert it and append it.
    if ($paise > 0) {
        $paise_in_words = numberToWordsRecursive($paise);
        $final_string .= ' and ' . ucwords($paise_in_words) . ' Paise';
    }
    
    return $final_string;
}

function formatQuantity($qty) {
    return (fmod($qty, 1) !== 0.00) ? number_format($qty, 3) : number_format($qty, 0);
}

// =============================================================================
// SECTION 2: ROUTING AND ACTION HANDLING
// =============================================================================

$action = $_REQUEST['action'] ?? 'create';

// --- AJAX Request Handling ---
if ($action === 'ajax') {
    header('Content-Type: application/json');
    $request_type = $_REQUEST['request'] ?? '';

    if ($request_type === 'get_party' && isset($_GET['id'])) {
        $party_id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT `business_name`, `address`, `gst_uin`, `state` FROM `parties` WHERE id = ?");
        $stmt->bind_param("i", $party_id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_assoc() ?: ['error' => 'Party not found']);
    } elseif ($request_type === 'search_goods' && isset($_GET['term'])) {
        $term = '%' . $_GET['term'] . '%';
        $sql = "SELECT `id`, `product_name`, `hsn_sac`, `unit`, `rate_per_gram` as rate FROM `goods` WHERE `product_name` LIKE ? OR `hsn_sac` LIKE ? LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $term, $term);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    } elseif ($request_type === 'save_party' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = ['success' => false];
        try {
            $role = htmlspecialchars($_POST['role']);
            $prefix_map = ['Distributor' => 'DIST', 'Dealer' => 'DEAL', 'Retailer' => 'RETA', 'Wholesaler' => 'WHOL'];
            $prefix = $prefix_map[$role] ?? 'PARTY';
            
            $id_stmt = $conn->prepare("SELECT MAX(unique_id) as last_id FROM parties WHERE unique_id LIKE ?");
            $like_prefix = $prefix . '%';
            $id_stmt->bind_param("s", $like_prefix);
            $id_stmt->execute();
            $last_id = $id_stmt->get_result()->fetch_assoc()['last_id'];
            $number = $last_id ? (int)substr($last_id, strlen($prefix)) + 1 : 1;
            $unique_id = sprintf('%s%04d', $prefix, $number);

            $stmt = $conn->prepare("INSERT INTO parties (unique_id, business_name, owner_name, address, gst_uin, state, contact_number, email, pincode, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $unique_id, $_POST['business_name'], $_POST['owner_name'], $_POST['address'], $_POST['gst_uin'], $_POST['state'], $_POST['contact_number'], $_POST['email'], $_POST['pincode'], $role);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['new_party'] = ['id' => $conn->insert_id, 'business_name' => $_POST['business_name'], 'unique_id' => $unique_id];
            } else {
                $response['message'] = 'Failed to save party.';
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        echo json_encode($response);
    }
    $conn->close();
    exit;
}

// --- Save Invoice Form Submission ---
if ($action === 'save_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $biller_id = $_POST['biller_id'];
        $consignee_id = (isset($_POST['same_as_biller']) && $_POST['same_as_biller'] == 'on') ? $biller_id : $_POST['consignee_id'];
        if (empty($biller_id) || empty($consignee_id)) throw new Exception("Biller and Consignee must be selected.");
        
        $sub_total = 0;
        if(isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) $sub_total += (float)$item['amount'];
        }

        $cgst_amount = $sub_total * 0.09;
        $sgst_amount = $sub_total * 0.09;
        $transit_insurance_amount = $sub_total * 0.00025;
        $grand_total = $sub_total + $cgst_amount + $sgst_amount + $transit_insurance_amount;
        $amount_in_words = "INR " . numberToWords(round($grand_total, 2)) . " Only";
        
        $stmt_invoice = $conn->prepare("INSERT INTO invoices (invoice_no, invoice_date, irn_no, ack_no, ack_date, biller_id, consignee_id, delivery_note, buyers_order_no, buyers_order_date, dispatch_through, bill_of_lading_no, bill_of_lading_date, motor_vehicle_no, eway_bill_no, sub_total, cgst_amount, sgst_amount, transit_insurance_amount, grand_total, amount_in_words) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_invoice->bind_param("sssssiisssssssssdddds", $_POST['invoice_no'], $_POST['invoice_date'], $_POST['irn_no'], $_POST['ack_no'], $_POST['ack_date'], $biller_id, $consignee_id, $_POST['delivery_note'], $_POST['buyers_order_no'], $_POST['buyers_order_date'], $_POST['dispatch_through'], $_POST['bill_of_lading_no'], $_POST['bill_of_lading_date'], $_POST['motor_vehicle_no'], $_POST['eway_bill_no'], $sub_total, $cgst_amount, $sgst_amount, $transit_insurance_amount, $grand_total, $amount_in_words);
        $stmt_invoice->execute();
        $invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        if ($invoice_id > 0 && isset($_POST['items']) && is_array($_POST['items'])) {
            $stmt_items = $conn->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, hsn_sac, quantity, unit, rate, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['items'] as $item) {
                $stmt_items->bind_param("iisssddd", $invoice_id, $item['product_id'], $item['product_name'], $item['hsn_sac'], $item['quantity'], $item['unit'], $item['rate'], $item['amount']);
                // THIS LINE WAS MISSING AND HAS BEEN FIXED
                $stmt_items->execute();
            }
            $stmt_items->close();
        }

        $conn->commit();
        header("Location: ?action=print&id=" . $invoice_id . "&status=success");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error processing invoice: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tax Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body { background-color: #f8f9fa; }
        .invoice-container { background-color: #ffffff; padding: 2.5rem; margin: 2rem auto; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); max-width: 1100px; }
        .form-control:read-only { background-color: #e9ecef; }
        .search-results { position: absolute; background-color: white; border: 1px solid #ddd; z-index: 1056; width: 100%; max-height: 250px; overflow-y: auto; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .search-item { padding: 10px; cursor: pointer; }
        .search-item:hover { background-color: #f0f2f5; }
        .modal-backdrop { z-index: 1050; }
        .modal { z-index: 1055; }
        .input-group .btn { border-radius: 0 .25rem .25rem 0 !important; }
        
        /* Print-specific styles */
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; font-size: 14px; line-height: 20px; color: #555; }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .invoice-box table td, .invoice-box table th { padding: 8px; vertical-align: top; }
        .invoice-box table tr.heading td, .invoice-box table tr.heading th { background: #f2f2f2; border-bottom: 1px solid #ddd; font-weight: bold; text-align:left; }
        .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
        .no-print { text-align: center; margin-top: 20px; }
        
        @media print {
            body { background-color: #fff; }
            .no-print, .invoice-container, .modal, .modal-backdrop { display: none !important; }
            .invoice-box-container { display: block !important; box-shadow: none; border: none; margin: 0; padding: 0; }
            .invoice-box { box-shadow: none; border: 1px solid #ccc; margin: 0; padding: 15px; }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($action === 'print' && isset($_GET['id'])): ?>
        <?php
        $invoice_id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT i.*, c.business_name as consignee_name, c.unique_id as consignee_uid, c.address as consignee_address, c.gst_uin as consignee_gst, c.state as consignee_state, b.business_name as biller_name, b.unique_id as biller_uid, b.address as biller_address, b.gst_uin as biller_gst, b.state as biller_state FROM invoices i JOIN parties c ON i.consignee_id = c.id JOIN parties b ON i.biller_id = b.id WHERE i.id = ?");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $items_stmt->bind_param("i", $invoice_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        if (!$invoice) die('Invoice not found.');
        $qr_data = json_encode(["InvNo" => $invoice['invoice_no'], "Dt" => date("d-m-Y", strtotime($invoice['invoice_date'])), "Biller" => $invoice['biller_name'], "Amt" => number_format($invoice['grand_total'], 2)]);
        $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($qr_data);
        ?>
        <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success mt-4 no-print">Invoice saved successfully!</div>
        <?php endif; ?>
        <div class="invoice-box-container my-4">
            <div class="invoice-box">
                <table cellpadding="0" cellspacing="0">
                     <tr>
                        <td colspan="2" style="padding-bottom: 20px; vertical-align: top;">
                            <img src="Assets/logo.jpg" style="max-width: 150px;" alt="Logo">
                        </td>
                        <td colspan="2" style="padding-bottom: 20px; text-align: right;">
                            <img src="<?= $qr_code_url ?>" alt="QR Code">
                        </td>
                    </tr>
                    <tr class="information"><td colspan="2"><strong>Sold By: JSPL STEEL</strong><br>Third Floor, Plot No-747, Khata No-11,<br>Dhanbad, Jharkhand - 828109</td><td colspan="2" style="text-align: right;"><strong>Invoice No:</strong> <?= htmlspecialchars($invoice['invoice_no']) ?><br><strong>Dated:</strong> <?= date("d-M-Y", strtotime($invoice['invoice_date'])) ?><br><strong>E-Way Bill No:</strong> <?= htmlspecialchars($invoice['eway_bill_no']) ?></td></tr>
                    <tr class="information" style="border-top: 1px solid #eee; border-bottom: 1px solid #eee;"><td colspan="2"><strong>IRN:</strong> <?= htmlspecialchars($invoice['irn_no']) ?></td><td colspan="2" style="text-align: right;"><strong>Ack. No:</strong> <?= htmlspecialchars($invoice['ack_no']) ?><br><strong>Ack. Date:</strong> <?= !empty($invoice['ack_date']) ? date("d-M-Y", strtotime($invoice['ack_date'])) : 'N/A' ?></td></tr>
                    <tr class="heading"><td colspan="2">Buyer (Bill To)</td><td colspan="2">Consignee (Ship To)</td></tr>
                    <tr class="details"><td colspan="2"><strong><?= htmlspecialchars($invoice['biller_name']) ?> (<?= htmlspecialchars($invoice['biller_uid']) ?>)</strong><br><?= nl2br(htmlspecialchars($invoice['biller_address'])) ?><br><strong>GSTIN:</strong> <?= htmlspecialchars($invoice['biller_gst']) ?></td><td colspan="2"><strong><?= htmlspecialchars($invoice['consignee_name']) ?> (<?= htmlspecialchars($invoice['consignee_uid']) ?>)</strong><br><?= nl2br(htmlspecialchars($invoice['consignee_address'])) ?><br><strong>GSTIN:</strong> <?= htmlspecialchars($invoice['consignee_gst']) ?></td></tr>
                    <tr class="heading"><th>Description</th><th>HSN/SAC</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Amount</th></tr>
                    <?php $total_qty = 0; while ($item = $items_result->fetch_assoc()): $total_qty += $item['quantity']; ?>
                    <tr class="item"><td><?= htmlspecialchars($item['product_name']) ?></td><td><?= htmlspecialchars($item['hsn_sac']) ?></td><td style="text-align:right;"><?= formatQuantity($item['quantity']) ?> <?= $item['unit'] ?></td><td style="text-align:right;"><?= number_format($item['amount'], 2) ?></td></tr>
                    <?php endwhile; ?>
                    <tr style="font-weight:bold; border-top: 2px solid #ccc;"><td colspan="2" style="text-align:right;">Total</td><td style="text-align:right;"><?= formatQuantity($total_qty) ?></td><td style="text-align:right;"><?= number_format($invoice['sub_total'], 2) ?></td></tr>
                    <tr><td colspan="2" rowspan="4" style="vertical-align: bottom;"><strong>Amount (in words):</strong><br><?= htmlspecialchars($invoice['amount_in_words']) ?></td><td style="font-weight:bold;">CGST @9%</td><td style="text-align:right;"><?= number_format($invoice['cgst_amount'], 2) ?></td></tr>
                    <tr><td style="font-weight:bold;">SGST @9%</td><td style="text-align:right;"><?= number_format($invoice['sgst_amount'], 2) ?></td></tr>
                    <tr><td style="font-weight:bold;">Transit Insurance @0.025%</td><td style="text-align:right;"><?= number_format($invoice['transit_insurance_amount'], 2) ?></td></tr>
                    <tr style="font-weight:bold; background: #f2f2f2; border-top:2px solid #ccc;"><td>Grand Total</td><td style="text-align:right;"><?= number_format($invoice['grand_total'], 2) ?></td></tr>
                </table>
                <div style="margin-top: 30px; padding-top: 15px; border-top: 2px solid #eee; font-size: 13px;"><table style="width: 100%;"><tr><td style="width: 60%; vertical-align: top;"><strong>BANK DETAIL</strong><br>A/C NAME: JSPL STEEL<br>A/C NO.: 50200113154873<br>IFSC: HDFC0008981<br>BANK: HDFC BANK LTD<br>BRANCH: DHAIYA<br><br><strong>Declaration:</strong><br><small>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</small></td><td style="width: 40%; text-align: right; vertical-align: top;">For <strong>JSPL STEEL</strong><br><br><br><br><br>Authorised Signatory</td></tr></table></div>
            </div>
        </div>
        <div class="no-print"><button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print</button> <a href="?" class="btn btn-primary"><i class="fas fa-plus"></i> New Invoice</a></div>
        <?php $conn->close(); ?>

    <?php else: ?>
        <?php $parties_result = $conn->query("SELECT `id`, `unique_id`, `business_name` FROM `parties` ORDER BY `business_name` ASC"); ?>
        <form action="?action=save_invoice" method="post" id="invoice-form">
            <div class="invoice-container">
                <div class="d-flex justify-content-between align-items-center mb-4"><img src="Assets/logo.jpg" alt="Logo" style="max-height: 70px;"><h1 class="mb-0">TAX INVOICE</h1><div style="width: 100px; height: 100px; border: 1px solid #ccc; display:flex; align-items:center; justify-content:center; color: #6c757d;">QR Code</div></div>
                <div class="row border-top pt-3 mb-4"><div class="col-md-4"><label for="irn_no" class="form-label">IRN:</label><input type="text" class="form-control" id="irn_no" name="irn_no"></div><div class="col-md-4"><label for="ack_no" class="form-label">Ack No.:</label><input type="text" class="form-control" id="ack_no" name="ack_no"></div><div class="col-md-4"><label for="ack_date" class="form-label">Ack Date:</label><input type="date" class="form-control" id="ack_date" name="ack_date"></div></div>
                <div class="row border-top pt-3 mb-4"><div class="col-md-5"><h5 class="mb-3 text-primary">Sold By</h5><h6 class="fw-bold">JSPL STEEL</h6><p class="mb-1" style="font-size: 0.9rem;">Third Floor, Plot No-747, Khata No-11,<br>Dhanbad, Jharkhand - 828109</p></div><div class="col-md-7"><div class="row"><div class="col-6 mb-2"><label for="invoice_no" class="form-label">Invoice No.</label><input type="text" class="form-control" id="invoice_no" name="invoice_no" required></div><div class="col-6 mb-2"><label for="invoice_date" class="form-label">Dated</label><input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required></div><div class="col-12 mb-2"><label for="delivery_note" class="form-label">Delivery Note</label><input type="text" class="form-control" id="delivery_note" name="delivery_note"></div><div class="col-6 mb-2"><label for="buyers_order_no" class="form-label">Buyer's Order No.</label><input type="text" class="form-control" id="buyers_order_no" name="buyers_order_no"></div><div class="col-6 mb-2"><label for="buyers_order_date" class="form-label">Dated</label><input type="date" class="form-control" id="buyers_order_date" name="buyers_order_date"></div><div class="col-12 mb-2"><label for="dispatch_through" class="form-label">Dispatch Through</label><input type="text" class="form-control" id="dispatch_through" name="dispatch_through"></div><div class="col-6 mb-2"><label for="bill_of_lading_no" class="form-label">Bill of Lading/LR-RR No.</label><input type="text" class="form-control" id="bill_of_lading_no" name="bill_of_lading_no"></div><div class="col-6 mb-2"><label for="bill_of_lading_date" class="form-label">Dated</label><input type="date" class="form-control" id="bill_of_lading_date" name="bill_of_lading_date"></div><div class="col-6 mb-2"><label for="motor_vehicle_no" class="form-label">Motor Vehicle No.</label><input type="text" class="form-control" id="motor_vehicle_no" name="motor_vehicle_no"></div><div class="col-6 mb-2"><label for="eway_bill_no" class="form-label">E-Way Bill No.</label><input type="text" class="form-control" id="eway_bill_no" name="eway_bill_no"></div></div></div></div>
                <div class="row border-top pt-3 mb-4">
                     <div class="col-md-6 border-end pe-4">
                        <h5 class="mb-3 text-primary">Biller (Bill To)</h5>
                        <div class="input-group"><select class="form-select" id="biller_id" name="biller_id" required><option value="">-- Choose Party --</option><?php $parties_result->data_seek(0); while($party = $parties_result->fetch_assoc()): ?><option value="<?= $party['id'] ?>"><?= htmlspecialchars($party['business_name']) . ' (' . htmlspecialchars($party['unique_id']) . ')' ?></option><?php endwhile; ?></select><button class="btn btn-outline-primary add-party-btn" type="button" data-bs-toggle="modal" data-bs-target="#partyModal"><i class="fas fa-plus"></i></button></div>
                        <textarea class="form-control mt-2" id="biller_address" placeholder="Address" readonly rows="2"></textarea>
                        <div class="input-group mt-2"><input type="text" class="form-control" id="biller_gst" placeholder="GSTIN" readonly><input type="text" class="form-control" id="biller_state" placeholder="State" readonly></div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h5 class="mb-3 text-primary">Consignee (Ship To)</h5>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="same_as_biller" name="same_as_biller"><label class="form-check-label" for="same_as_biller">Same as Biller</label></div>
                        <div class="input-group"><select class="form-select" id="consignee_id" name="consignee_id" required><option value="">-- Choose Party --</option><?php $parties_result->data_seek(0); while($party = $parties_result->fetch_assoc()): ?><option value="<?= $party['id'] ?>"><?= htmlspecialchars($party['business_name']) . ' (' . htmlspecialchars($party['unique_id']) . ')' ?></option><?php endwhile; ?></select><button class="btn btn-outline-primary add-party-btn" type="button" data-bs-toggle="modal" data-bs-target="#partyModal"><i class="fas fa-plus"></i></button></div>
                        <textarea class="form-control mt-2" id="consignee_address" placeholder="Address" readonly rows="2"></textarea>
                        <div class="input-group mt-2"><input type="text" class="form-control" id="consignee_gst" placeholder="GSTIN" readonly><input type="text" class="form-control" id="consignee_state" placeholder="State" readonly></div>
                    </div>
                </div>
                <div class="position-relative mb-3"><div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input type="text" class="form-control" id="good-search" placeholder="Search for products..."></div><div id="search-results-container" class="search-results" style="display:none;"></div></div>
                <table class="table table-bordered table-hover" id="items-table"><thead class="table-light"><tr><th>#</th><th>Description</th><th>HSN</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Amount</th><th>Act</th></tr></thead><tbody id="item-rows"></tbody></table>
                <div class="row mt-4"><div class="col-md-7"><label><strong>Amount in Words:</strong></label><input type="text" class="form-control-plaintext" value="Calculated upon saving..." readonly></div><div class="col-md-5"><dl class="row"><dt class="col-7">Sub Total</dt><dd class="col-5 text-end" id="sub-total">0.00</dd><dt class="col-7">CGST @9%</dt><dd class="col-5 text-end" id="cgst-total">0.00</dd><dt class="col-7">SGST @9%</dt><dd class="col-5 text-end" id="sgst-total">0.00</dd><dt class="col-7">Transit Ins. @0.025%</dt><dd class="col-5 text-end" id="insurance-total">0.00</dd><hr class="my-2"><dt class="col-7 h5">GRAND TOTAL</dt><dd class="col-5 text-end h5" id="grand-total">0.00</dd></dl></div></div>
                <hr class="my-4"><div class="text-center"><button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-2"></i>Save Invoice</button></div>
            </div>
        </form>
        <?php $conn->close(); ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="partyModal" tabindex="-1" aria-labelledby="partyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="partyModalLabel"><i class="fas fa-user-plus me-2"></i>Add New Party Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addPartyForm">
            <div class="row g-3">
                <div class="col-md-6"><label for="role" class="form-label">Party Role</label><select id="role" name="role" class="form-select" required><option value="" disabled selected>-- Select a role --</option><option value="Distributor">Distributor</option><option value="Dealer">Dealer</option><option value="Retailer">Retailer</option><option value="Wholesaler">Wholesaler</option></select></div>
                <div class="col-md-6"><label for="business_name" class="form-label">Business Name</label><input type="text" id="business_name" name="business_name" class="form-control" required></div>
                <div class="col-md-6"><label for="owner_name" class="form-label">Owner Name</label><input type="text" id="owner_name" name="owner_name" class="form-control" required></div>
                <div class="col-md-6"><label for="contact_number" class="form-label">Contact Number</label><input type="tel" id="contact_number" name="contact_number" class="form-control" required></div>
                <div class="col-md-6"><label for="email" class="form-label">Email Address</label><input type="email" id="email" name="email" class="form-control"></div>
                <div class="col-md-6"><label for="gst_uin" class="form-label">GST / UIN</label><input type="text" id="gst_uin" name="gst_uin" class="form-control" required maxlength="15"></div>
                <div class="col-12"><label for="address" class="form-label">Address</label><textarea id="address" name="address" class="form-control" required></textarea></div>
                <div class="col-md-6"><label for="state" class="form-label">State</label><input type="text" id="state" name="state" class="form-control" required></div>
                <div class="col-md-6"><label for="pincode" class="form-label">Pincode</label><input type="text" id="pincode" name="pincode" class="form-control" required></div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="savePartyBtn"><i class="fas fa-save me-2"></i>Save Party</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    const partyModal = new bootstrap.Modal(document.getElementById('partyModal'));

    function getPartyDetails(partyId, type) {
        if (!partyId) {
            $('#' + type + '_address, #' + type + '_gst, #' + type + '_state').val('');
            return;
        }
        $.ajax({
            url: '?action=ajax&request=get_party', type: 'GET', data: { id: partyId }, dataType: 'json',
            success: function(data) {
                if(data) {
                    $('#' + type + '_address').val(data.address);
                    $('#' + type + '_gst').val(data.gst_uin);
                    $('#' + type + '_state').val(data.state);
                }
            }
        });
    }

    $('#biller_id').change(function() { 
        getPartyDetails($(this).val(), 'biller'); 
        if ($('#same_as_biller').is(':checked')) $('#consignee_id').val($(this).val()).trigger('change'); 
    });
    $('#consignee_id').change(function() { getPartyDetails($(this).val(), 'consignee'); });
    $('#same_as_biller').change(function() {
        const consigneeSelect = $('#consignee_id');
        if ($(this).is(':checked')) {
            consigneeSelect.val($('#biller_id').val()).trigger('change').prop('disabled', true);
        } else {
            consigneeSelect.val('').trigger('change').prop('disabled', false);
        }
    });

    $('#savePartyBtn').on('click', function() {
        const form = $('#addPartyForm');
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        
        $.ajax({
            url: '?action=ajax&request=save_party',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    const party = response.new_party;
                    const newOption = `<option value="${party.id}">${party.business_name} (${party.unique_id})</option>`;
                    $('#biller_id, #consignee_id').append(newOption);
                    partyModal.hide();
                    form[0].reset();
                    alert('Party added successfully!');
                } else {
                    alert('Error: ' + (response.message || 'Could not save party.'));
                }
            },
            error: function() { alert('An unexpected error occurred.'); },
            complete: function() { btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Party'); }
        });
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
        itemCounter++;
        const item = $(this).data();
        const rowHtml = `<tr class="item-row"><td>${itemCounter}</td><td>${item.name}<input type="hidden" name="items[${itemCounter}][product_id]" value="${item.id}"><input type="hidden" name="items[${itemCounter}][product_name]" value="${item.name}"></td><td>${item.hsn}<input type="hidden" name="items[${itemCounter}][hsn_sac]" value="${item.hsn}"></td><td><input type="number" class="form-control form-control-sm quantity" name="items[${itemCounter}][quantity]" value="1" min="0.001" step="0.001"></td><td>${item.unit}<input type="hidden" name="items[${itemCounter}][unit]" value="${item.unit}"></td><td><div class="input-group"><input type="number" class="form-control form-control-sm rate" name="items[${itemCounter}][rate]" value="${parseFloat(item.rate).toFixed(2)}" step="0.01" readonly><button class="btn btn-outline-secondary btn-sm toggle-rate-lock" type="button" title="Alter Rate"><i class="fas fa-pencil-alt"></i></button></div></td><td><input type="text" class="form-control form-control-sm amount" name="items[${itemCounter}][amount]" readonly></td><td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-trash"></i></button></td></tr>`;
        $('#item-rows').append(rowHtml);
        updateCalculations();
        $('#good-search').val('');
        $('#search-results-container').hide();
    });
    
    $('#items-table').on('click', '.toggle-rate-lock', function() {
        const rateInput = $(this).closest('.input-group').find('.rate');
        rateInput.prop('readonly', !rateInput.prop('readonly'));
        $(this).toggleClass('btn-outline-secondary btn-success');
        $(this).find('i').toggleClass('fa-pencil-alt fa-unlock');
        if (!rateInput.prop('readonly')) rateInput.focus();
    });

    $('#items-table').on('click', '.remove-item', function() { $(this).closest('tr').remove(); updateCalculations(); });
    $('#items-table').on('input', '.quantity, .rate', updateCalculations);

    function updateCalculations() {
        let subTotal = 0;
        $('.item-row').each(function(index) {
            $(this).find('td:first').text(index + 1);
            const quantity = parseFloat($(this).find('.quantity').val()) || 0;
            const rate = parseFloat($(this).find('.rate').val()) || 0;
            const amount = quantity * rate;
            $(this).find('.amount').val(amount.toFixed(2));
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
    }

    $('#invoice-form').on('submit', function(e){ 
        if ($('#same_as_biller').is(':checked')) $('#consignee_id').prop('disabled', false);
        if ($('.item-row').length === 0) { 
            alert('Please add at least one item to the invoice.'); 
            e.preventDefault(); 
        } 
    });
});
</script>

</body>
</html>