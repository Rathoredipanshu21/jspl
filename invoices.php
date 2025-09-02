<?php
// Includes your db.php file and makes the $conn variable available.
require_once 'config/db.php';

// --- START: Handle POST Requests for Update and Delete ---

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle Invoice Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_invoice') {
        // Sanitize and retrieve form data
        $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_SANITIZE_NUMBER_INT);
        $invoice_no = filter_input(INPUT_POST, 'invoice_no', FILTER_SANITIZE_STRING);
        $invoice_date = filter_input(INPUT_POST, 'invoice_date', FILTER_SANITIZE_STRING);
        $biller_id = filter_input(INPUT_POST, 'biller_id', FILTER_SANITIZE_NUMBER_INT);
        $consignee_id = filter_input(INPUT_POST, 'consignee_id', FILTER_SANITIZE_NUMBER_INT);
        $eway_bill_no = filter_input(INPUT_POST, 'eway_bill_no', FILTER_SANITIZE_STRING);
        
        $delivery_note = filter_input(INPUT_POST, 'delivery_note', FILTER_SANITIZE_STRING);
        $dispatch_through = filter_input(INPUT_POST, 'dispatch_through', FILTER_SANITIZE_STRING);
        $other_fer_loading_from = filter_input(INPUT_POST, 'other_fer_loading_from', FILTER_SANITIZE_STRING);
        $bill_of_lading_no = filter_input(INPUT_POST, 'bill_of_lading_no', FILTER_SANITIZE_STRING);
        $motor_vehicle_no = filter_input(INPUT_POST, 'motor_vehicle_no', FILTER_SANITIZE_STRING);

        // --- MODIFIED: Sanitize new GST fields and handle checkbox logic ---
        $apply_igst = isset($_POST['apply_igst']);
        $cgst_amount = filter_input(INPUT_POST, 'cgst_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $sgst_amount = filter_input(INPUT_POST, 'sgst_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $igst_amount = filter_input(INPUT_POST, 'igst_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        // Based on the checkbox, nullify the other tax type
        if ($apply_igst) {
            $cgst_amount = 0;
            $sgst_amount = 0;
        } else {
            $igst_amount = 0;
        }


        // --- MODIFIED: Prepare the UPDATE statement to prevent SQL injection ---
        $stmt = $conn->prepare("UPDATE invoices SET 
                                    invoice_no = ?, 
                                    invoice_date = ?, 
                                    biller_id = ?, 
                                    consignee_id = ?, 
                                    eway_bill_no = ?, 
                                    delivery_note = ?, 
                                    dispatch_through = ?, 
                                    other_fer_loading_from = ?, 
                                    bill_of_lading_no = ?, 
                                    motor_vehicle_no = ?,
                                    cgst_amount = ?,
                                    sgst_amount = ?,
                                    igst_amount = ?
                                WHERE id = ?");
        
        // --- MODIFIED: Bind parameters (ssiissssssdddi) ---
        $stmt->bind_param("ssiissssssdddi", 
            $invoice_no, 
            $invoice_date, 
            $biller_id, 
            $consignee_id, 
            $eway_bill_no,
            $delivery_note,
            $dispatch_through,
            $other_fer_loading_from,
            $bill_of_lading_no,
            $motor_vehicle_no,
            $cgst_amount,
            $sgst_amount,
            $igst_amount,
            $invoice_id
        );
        
        // Execute and redirect
        if ($stmt->execute()) {
            // Optional: set a success message in a session variable
            // $_SESSION['message'] = "Invoice updated successfully!";
        } else {
            // Optional: set an error message
            // $_SESSION['error'] = "Error updating invoice: " . $stmt->error;
        }
        $stmt->close();
        header("Location: invoices.php");
        exit();
    }

    // Handle Invoice Delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete_invoice') {
        $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_SANITIZE_NUMBER_INT);

        // NOTE: For a complete solution, you should also delete related invoice_items.
        // Example: $conn->query("DELETE FROM invoice_items WHERE invoice_id = $invoice_id");

        // Prepare the DELETE statement
        $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->bind_param("i", $invoice_id);

        // Execute and redirect
        $stmt->execute();
        $stmt->close();
        header("Location: invoices.php");
        exit();
    }
}

// --- END: Handle POST Requests ---


// Fetch all parties for the dropdowns in the edit modal
$parties_sql = "SELECT id, business_name FROM parties ORDER BY business_name ASC";
$parties_result = $conn->query($parties_sql);
$parties = [];
if ($parties_result->num_rows > 0) {
    while($row = $parties_result->fetch_assoc()) {
        $parties[] = $row;
    }
}


// --- MODIFIED: Fetch all invoices with party names AND new fields using JOINs ---
$sql = "SELECT 
            i.id, 
            i.invoice_no, 
            i.invoice_date, 
            i.biller_id,
            i.consignee_id,
            biller.business_name AS biller_name, 
            consignee.business_name AS consignee_name,
            i.sub_total,
            i.cgst_amount,
            i.sgst_amount,
            i.igst_amount,
            i.transit_insurance_amount,
            i.grand_total,
            i.eway_bill_no,
            i.delivery_note,
            i.dispatch_through,
            i.other_fer_loading_from AS loading_from,
            i.bill_of_lading_no,
            i.motor_vehicle_no
        FROM 
            invoices AS i
        JOIN 
            parties AS biller ON i.biller_id = biller.id
        JOIN 
            parties AS consignee ON i.consignee_id = consignee.id
        ORDER BY 
            i.invoice_date DESC, i.id DESC";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table th, .table td {
            white-space: nowrap;
            vertical-align: middle;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h4 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Saved Invoices</h4>
            <a href="Bill_create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create New Invoice</a>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text" id="basic-addon1"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by Invoice No..." aria-label="Search by Invoice No" aria-describedby="basic-addon1">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Invoice No.</th>
                            <th>Date</th>
                            <th>Biller (Bill To)</th>
                            <th>Consignee (Ship To)</th>
                            <th class="text-end">Grand Total</th>
                            <th>E-Way Bill No.</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $sr_no = 1; ?>
                            <?php while($invoice = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $sr_no++ ?></td>
                                    <td><strong><?= htmlspecialchars($invoice['invoice_no']) ?></strong></td>
                                    <td><?= date("d-m-Y", strtotime($invoice['invoice_date'])) ?></td>
                                    <td><?= htmlspecialchars($invoice['biller_name']) ?></td>
                                    <td><?= htmlspecialchars($invoice['consignee_name']) ?></td>
                                    <td class="text-end"><strong><?= number_format($invoice['grand_total'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($invoice['eway_bill_no'] ?: 'N/A') ?></td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <a href="Bill_create.php?action=print&id=<?= $invoice['id'] ?>" class="btn btn-sm btn-info" title="View Invoice" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-warning edit-btn" title="Edit Invoice"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editInvoiceModal"
                                                data-id="<?= $invoice['id'] ?>"
                                                data-invoice-no="<?= htmlspecialchars($invoice['invoice_no']) ?>"
                                                data-invoice-date="<?= $invoice['invoice_date'] ?>"
                                                data-biller-id="<?= $invoice['biller_id'] ?>"
                                                data-consignee-id="<?= $invoice['consignee_id'] ?>"
                                                data-eway-bill-no="<?= htmlspecialchars($invoice['eway_bill_no']) ?>"
                                                data-delivery-note="<?= htmlspecialchars($invoice['delivery_note']) ?>"
                                                data-dispatch-through="<?= htmlspecialchars($invoice['dispatch_through']) ?>"
                                                data-loading-from="<?= htmlspecialchars($invoice['loading_from']) ?>"
                                                data-bill-of-lading-no="<?= htmlspecialchars($invoice['bill_of_lading_no']) ?>"
                                                data-motor-vehicle-no="<?= htmlspecialchars($invoice['motor_vehicle_no']) ?>"
                                                data-cgst-amount="<?= $invoice['cgst_amount'] ?? 0 ?>"
                                                data-sgst-amount="<?= $invoice['sgst_amount'] ?? 0 ?>"
                                                data-igst-amount="<?= $invoice['igst_amount'] ?? 0 ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="invoices.php" onsubmit="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.');" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_invoice">
                                                <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete Invoice">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted p-4">
                                    No invoices found. <a href="Bill_create.php">Create one now!</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editInvoiceModal" tabindex="-1" aria-labelledby="editInvoiceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editInvoiceModalLabel">Edit Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="invoices.php">
        <input type="hidden" name="action" value="update_invoice">
        <input type="hidden" name="invoice_id" id="edit-invoice-id">
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="edit-invoice-no" class="form-label">Invoice No.</label>
                    <input type="text" class="form-control" id="edit-invoice-no" name="invoice_no" required>
                </div>
                <div class="col-md-6">
                    <label for="edit-invoice-date" class="form-label">Invoice Date</label>
                    <input type="date" class="form-control" id="edit-invoice-date" name="invoice_date" required>
                </div>
                <div class="col-md-6">
                    <label for="edit-biller-id" class="form-label">Biller (Bill To)</label>
                    <select class="form-select" id="edit-biller-id" name="biller_id" required>
                        <option value="">Select Biller</option>
                        <?php foreach ($parties as $party): ?>
                            <option value="<?= $party['id'] ?>"><?= htmlspecialchars($party['business_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="edit-consignee-id" class="form-label">Consignee (Ship To)</label>
                    <select class="form-select" id="edit-consignee-id" name="consignee_id" required>
                        <option value="">Select Consignee</option>
                        <?php foreach ($parties as $party): ?>
                            <option value="<?= $party['id'] ?>"><?= htmlspecialchars($party['business_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12"><hr></div>
                <div class="col-12">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="edit-apply-igst" name="apply_igst">
                        <label class="form-check-label" for="edit-apply-igst">Apply IGST (instead of CGST/SGST)</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="edit-cgst-amount" class="form-label">CGST Amount</label>
                    <input type="number" step="0.01" class="form-control" id="edit-cgst-amount" name="cgst_amount">
                </div>
                 <div class="col-md-4">
                    <label for="edit-sgst-amount" class="form-label">SGST Amount</label>
                    <input type="number" step="0.01" class="form-control" id="edit-sgst-amount" name="sgst_amount">
                </div>
                <div class="col-md-4">
                    <label for="edit-igst-amount" class="form-label">IGST Amount</label>
                    <input type="number" step="0.01" class="form-control" id="edit-igst-amount" name="igst_amount">
                </div>
                <div class="col-12"><hr></div>
                <div class="col-md-6">
                    <label for="edit-eway-bill-no" class="form-label">E-Way Bill No.</label>
                    <input type="text" class="form-control" id="edit-eway-bill-no" name="eway_bill_no">
                </div>
                <div class="col-md-6">
                    <label for="edit-delivery-note" class="form-label">Delivery Note</label>
                    <input type="text" class="form-control" id="edit-delivery-note" name="delivery_note">
                </div>
                <div class="col-md-6">
                    <label for="edit-dispatch-through" class="form-label">Dispatched Through</label>
                    <input type="text" class="form-control" id="edit-dispatch-through" name="dispatch_through">
                </div>
                <div class="col-md-6">
                    <label for="edit-loading-from" class="form-label">Loading From</label>
                    <input type="text" class="form-control" id="edit-loading-from" name="other_fer_loading_from">
                </div>
                <div class="col-md-6">
                    <label for="edit-bill-of-lading-no" class="form-label">Bill of Lading/LR-RR No.</label>
                    <input type="text" class="form-control" id="edit-bill-of-lading-no" name="bill_of_lading_no">
                </div>
                <div class="col-md-6">
                    <label for="edit-motor-vehicle-no" class="form-label">Motor Vehicle No.</label>
                    <input type="text" class="form-control" id="edit-motor-vehicle-no" name="motor_vehicle_no">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
// Close the database connection
$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- MODIFIED: Script for Edit Modal with GST logic ---
    const editInvoiceModal = document.getElementById('editInvoiceModal');
    
    // Get GST related elements once
    const applyIgstCheckbox = editInvoiceModal.querySelector('#edit-apply-igst');
    const cgstAmountInput = editInvoiceModal.querySelector('#edit-cgst-amount');
    const sgstAmountInput = editInvoiceModal.querySelector('#edit-sgst-amount');
    const igstAmountInput = editInvoiceModal.querySelector('#edit-igst-amount');

    // Function to toggle tax fields based on checkbox state
    function toggleTaxFields() {
        if (applyIgstCheckbox.checked) {
            igstAmountInput.disabled = false;
            cgstAmountInput.disabled = true;
            sgstAmountInput.disabled = true;
        } else {
            igstAmountInput.disabled = true;
            cgstAmountInput.disabled = false;
            sgstAmountInput.disabled = false;
        }
    }

    // Add event listener to checkbox for real-time toggling
    applyIgstCheckbox.addEventListener('change', toggleTaxFields);

    editInvoiceModal.addEventListener('show.bs.modal', function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const id = button.getAttribute('data-id');
        const invoiceNo = button.getAttribute('data-invoice-no');
        const invoiceDate = button.getAttribute('data-invoice-date');
        const billerId = button.getAttribute('data-biller-id');
        const consigneeId = button.getAttribute('data-consignee-id');
        const ewayBillNo = button.getAttribute('data-eway-bill-no');
        const deliveryNote = button.getAttribute('data-delivery-note');
        const dispatchThrough = button.getAttribute('data-dispatch-through');
        const loadingFrom = button.getAttribute('data-loading-from');
        const billOfLadingNo = button.getAttribute('data-bill-of-lading-no');
        const motorVehicleNo = button.getAttribute('data-motor-vehicle-no');
        
        // Get new GST attributes
        const cgstAmount = button.getAttribute('data-cgst-amount');
        const sgstAmount = button.getAttribute('data-sgst-amount');
        const igstAmount = button.getAttribute('data-igst-amount');


        // Update the modal's content
        const modalTitle = editInvoiceModal.querySelector('.modal-title');
        const invoiceIdInput = editInvoiceModal.querySelector('#edit-invoice-id');
        const invoiceNoInput = editInvoiceModal.querySelector('#edit-invoice-no');
        const invoiceDateInput = editInvoiceModal.querySelector('#edit-invoice-date');
        const billerIdSelect = editInvoiceModal.querySelector('#edit-biller-id');
        const consigneeIdSelect = editInvoiceModal.querySelector('#edit-consignee-id');
        const ewayBillNoInput = editInvoiceModal.querySelector('#edit-eway-bill-no');
        const deliveryNoteInput = editInvoiceModal.querySelector('#edit-delivery-note');
        const dispatchThroughInput = editInvoiceModal.querySelector('#edit-dispatch-through');
        const loadingFromInput = editInvoiceModal.querySelector('#edit-loading-from');
        const billOfLadingNoInput = editInvoiceModal.querySelector('#edit-bill-of-lading-no');
        const motorVehicleNoInput = editInvoiceModal.querySelector('#edit-motor-vehicle-no');

        modalTitle.textContent = 'Edit Invoice: ' + invoiceNo;
        invoiceIdInput.value = id;
        invoiceNoInput.value = invoiceNo;
        invoiceDateInput.value = invoiceDate;
        billerIdSelect.value = billerId;
        consigneeIdSelect.value = consigneeId;
        ewayBillNoInput.value = ewayBillNo;
        deliveryNoteInput.value = deliveryNote;
        dispatchThroughInput.value = dispatchThrough;
        loadingFromInput.value = loadingFrom;
        billOfLadingNoInput.value = billOfLadingNo;
        motorVehicleNoInput.value = motorVehicleNo;

        // Set values for new GST inputs
        cgstAmountInput.value = parseFloat(cgstAmount).toFixed(2);
        sgstAmountInput.value = parseFloat(sgstAmount).toFixed(2);
        igstAmountInput.value = parseFloat(igstAmount).toFixed(2);
        
        // Set the initial state of the checkbox and fields
        applyIgstCheckbox.checked = parseFloat(igstAmount) > 0;
        toggleTaxFields();
    });
    // --- END: Script for Edit Modal ---


    // --- START: Script for Live Search ---
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('invoiceTableBody');
    const tableRows = tableBody.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();

        // Loop through all table rows, and hide those that don't match the search query
        for (let i = 0; i < tableRows.length; i++) {
            // Get the cell containing the invoice number (second column, index 1)
            let td = tableRows[i].getElementsByTagName('td')[1];
            if (td) {
                let txtValue = td.textContent || td.innerText;
                // Check if the row's invoice number includes the filter text
                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    tableRows[i].style.display = ""; // Show the row
                } else {
                    tableRows[i].style.display = "none"; // Hide the row
                }
            }
        }
    });
});
</script>
</body>
</html>