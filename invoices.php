<?php
// Includes your db.php file and makes the $conn variable available.
require_once 'config/db.php';

// Fetch all invoices with party names using JOINs
$sql = "SELECT 
            i.id, 
            i.invoice_no, 
            i.invoice_date, 
            biller.business_name AS biller_name, 
            consignee.business_name AS consignee_name,
            i.sub_total,
            i.cgst_amount,
            i.sgst_amount,
            i.transit_insurance_amount,
            i.grand_total,
            i.eway_bill_no
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
        .table th {
            white-space: nowrap;
        }
        .table td {
            vertical-align: middle;
        }
        .action-btn {
            width: 80px;
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
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Invoice No.</th>
                            <th>Date</th>
                            <th>Biller (Bill To)</th>
                            <th>Consignee (Ship To)</th>
                            <th class="text-end">Sub Total</th>
                            <th class="text-end">CGST</th>
                            <th class="text-end">SGST</th>
                            <th class="text-end">Grand Total</th>
                            <th>E-Way Bill No.</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $sr_no = 1; ?>
                            <?php while($invoice = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $sr_no++ ?></td>
                                    <td><strong><?= htmlspecialchars($invoice['invoice_no']) ?></strong></td>
                                    <td><?= date("d-m-Y", strtotime($invoice['invoice_date'])) ?></td>
                                    <td><?= htmlspecialchars($invoice['biller_name']) ?></td>
                                    <td><?= htmlspecialchars($invoice['consignee_name']) ?></td>
                                    <td class="text-end"><?= number_format($invoice['sub_total'], 2) ?></td>
                                    <td class="text-end"><?= number_format($invoice['cgst_amount'], 2) ?></td>
                                    <td class="text-end"><?= number_format($invoice['sgst_amount'], 2) ?></td>
                                    <td class="text-end"><strong><?= number_format($invoice['grand_total'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($invoice['eway_bill_no'] ?: 'N/A') ?></td>
                                    <td class="text-center">
                                        <a href="Bill_create.php?action=print&id=<?= $invoice['id'] ?>" class="btn btn-sm btn-info action-btn" target="_blank">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted p-4">
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

<?php
// Close the database connection
$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>