<?php
require_once 'config/db.php';

// --- AJAX Request Handling ---
if (isset($_GET['action']) && $_GET['action'] === 'ajax') {
    header('Content-Type: application/json');
    $request_type = $_GET['request'] ?? '';

    // 1. Fetch ledger data (invoices) for a party within a date range
    if ($request_type === 'get_ledger_details' && isset($_GET['party_id'], $_GET['start_date'], $_GET['end_date'])) {
        $party_id = intval($_GET['party_id']);
        $start_date = $_GET['start_date'];
        $end_date = $_GET['end_date'];

        $stmt = $conn->prepare("
            SELECT id, invoice_date, invoice_no, grand_total
            FROM invoices
            WHERE biller_id = ? AND invoice_date BETWEEN ? AND ?
            ORDER BY invoice_date ASC, id ASC
        ");
        $stmt->bind_param("iss", $party_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($result);
    }
    // 2. Fetch all items for a specific invoice
    elseif ($request_type === 'get_items_for_invoice' && isset($_GET['invoice_id'])) {
        $invoice_id = intval($_GET['invoice_id']);
        $stmt = $conn->prepare("
            SELECT product_name, hsn_sac, quantity, unit, rate, amount
            FROM invoice_items
            WHERE invoice_id = ?
        ");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($result);
    }
    else {
        echo json_encode(['error' => 'Invalid request']);
    }

    $conn->close();
    exit;
}

// Fetch all parties for the filter dropdown
$parties_result = $conn->query("SELECT `id`, `business_name`, `unique_id` FROM `parties` ORDER BY `business_name` ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Ledger Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
    </style>
</head>
<body>

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="bg-white rounded-xl shadow-lg p-6 md:p-8">
            <div class="flex items-center justify-between pb-6 border-b border-gray-200">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-book-open text-indigo-500 mr-4"></i>
                    Party Ledger Report
                </h1>
                <a href="party_report.php" class="text-indigo-500 hover:text-indigo-700 transition duration-300">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Reports
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 my-8 items-end">
                <div class="md:col-span-2">
                    <label for="party_id" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-user-tie mr-2 text-gray-400"></i>Select Party</label>
                    <select id="party_id" class="block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <option value="">-- Choose a party --</option>
                        <?php while($party = $parties_result->fetch_assoc()): ?>
                            <option value="<?= $party['id'] ?>"><?= htmlspecialchars($party['business_name']) . ' (' . htmlspecialchars($party['unique_id']) . ')' ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2 text-gray-400"></i>Start Date</label>
                    <input type="date" id="start_date" value="<?= date('Y-m-01') ?>" class="block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2 text-gray-400"></i>End Date</label>
                    <input type="date" id="end_date" value="<?= date('Y-m-t') ?>" class="block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div class="md:col-start-4">
                    <button id="generate-ledger-btn" class="w-full bg-indigo-600 text-white font-bold p-3 rounded-lg shadow-md hover:bg-indigo-700 transition-transform transform hover:scale-105 flex items-center justify-center">
                        <i class="fas fa-sync-alt mr-2"></i>Generate Ledger
                    </button>
                </div>
            </div>

            <div id="ledger-output">
                <div id="placeholder" class="text-center py-16 border-2 border-dashed border-gray-300 rounded-lg">
                    <i class="fas fa-file-invoice-dollar text-5xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Select a party and date range to view their ledger.</p>
                </div>

                <div id="ledger-content" class="hidden">
                    <div class="text-center mb-6">
                        <h2 id="ledger-party-name" class="text-2xl font-bold text-gray-800"></h2>
                        <p id="ledger-date-range" class="text-gray-500"></p>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-4 text-left text-sm font-semibold text-gray-600">Date</th>
                                    <th class="p-4 text-left text-sm font-semibold text-gray-600">Particulars</th>
                                    <th class="p-4 text-left text-sm font-semibold text-gray-600">Vch Type</th>
                                    <th class="p-4 text-right text-sm font-semibold text-gray-600">Debit</th>
                                    <th class="p-4 text-right text-sm font-semibold text-gray-600">Credit</th>
                                    <th class="p-4 text-right text-sm font-semibold text-gray-600">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="ledger-body" class="divide-y divide-gray-200"></tbody>
                            <tfoot class="bg-gray-100 font-bold">
                                <tr>
                                    <td colspan="3" class="p-4 text-right">Closing Balance:</td>
                                    <td id="total-debit" class="p-4 text-right"></td>
                                    <td id="total-credit" class="p-4 text-right"></td>
                                    <td id="closing-balance" class="p-4 text-right text-lg"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="itemsModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
            <div class="flex justify-between items-center p-5 border-b">
                <h3 id="itemsModalTitle" class="text-xl font-bold text-gray-800"></h3>
                <button class="close-modal-btn text-gray-500 hover:text-red-500 text-2xl">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-sm font-semibold text-gray-600">Product Name</th>
                            <th class="p-3 text-sm font-semibold text-gray-600">HSN/SAC</th>
                            <th class="p-3 text-sm font-semibold text-gray-600 text-right">Qty</th>
                            <th class="p-3 text-sm font-semibold text-gray-600 text-right">Rate</th>
                            <th class="p-3 text-sm font-semibold text-gray-600 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="itemsModalBody" class="divide-y"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            const currencyFormatter = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

            $('#generate-ledger-btn').on('click', function() {
                const partyId = $('#party_id').val();
                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();
                const partyName = $('#party_id option:selected').text().trim();
                const btn = $(this);

                if (!partyId || !startDate || !endDate) {
                    alert('Please select a party and a valid date range.');
                    return;
                }

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Generating...');
                
                $.ajax({
                    url: `?action=ajax&request=get_ledger_details`,
                    type: 'GET',
                    data: { party_id: partyId, start_date: startDate, end_date: endDate },
                    dataType: 'json',
                    success: function(transactions) {
                        $('#placeholder').addClass('hidden');
                        $('#ledger-content').removeClass('hidden');

                        const startDateFormatted = new Date(startDate).toLocaleDateString('en-GB');
                        const endDateFormatted = new Date(endDate).toLocaleDateString('en-GB');
                        $('#ledger-party-name').text(partyName);
                        $('#ledger-date-range').text(`From ${startDateFormatted} to ${endDateFormatted}`);
                        
                        const ledgerBody = $('#ledger-body').empty();
                        let runningBalance = 0;
                        let totalDebit = 0;

                        if (transactions.length > 0) {
                            transactions.forEach(tx => {
                                const debitAmount = parseFloat(tx.grand_total);
                                runningBalance += debitAmount;
                                totalDebit += debitAmount;
                                
                                const txDate = new Date(tx.invoice_date).toLocaleDateString('en-GB');

                                const row = `
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 text-gray-700">${txDate}</td>
                                        <td class="p-4 text-gray-800">
                                            Sales - 
                                            <a href="#" class="view-items-link text-indigo-600 hover:underline" data-invoice-id="${tx.id}" data-invoice-no="${tx.invoice_no}">
                                                Inv #${tx.invoice_no}
                                            </a>
                                        </td>
                                        <td class="p-4 text-gray-500">Sales</td>
                                        <td class="p-4 text-right text-red-600">${currencyFormatter.format(debitAmount)}</td>
                                        <td class="p-4 text-right text-gray-500">—</td>
                                        <td class="p-4 text-right font-medium text-gray-900">${currencyFormatter.format(runningBalance)}</td>
                                    </tr>`;
                                ledgerBody.append(row);
                            });
                        } else {
                            ledgerBody.html('<tr><td colspan="6" class="text-center p-8 text-gray-500">No transactions found for the selected period.</td></tr>');
                        }

                        // Update footer totals
                        $('#total-debit').text(currencyFormatter.format(totalDebit));
                        $('#total-credit').text('—');
                        $('#closing-balance').text(currencyFormatter.format(runningBalance));
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-2"></i>Generate Ledger');
                    }
                });
            });

            // "View Items" Link Click to open modal
            $('#ledger-body').on('click', '.view-items-link', function(e) {
                e.preventDefault();
                const invoiceId = $(this).data('invoice-id');
                const invoiceNo = $(this).data('invoice-no');

                $('#itemsModalTitle').text(`Goods Details for Invoice #${invoiceNo}`);
                const modalBody = $('#itemsModalBody').html('<tr><td colspan="5" class="text-center p-8"><i class="fas fa-spinner fa-spin text-2xl text-indigo-500"></i></td></tr>');
                $('#itemsModal').removeClass('hidden');

                $.getJSON(`?action=ajax&request=get_items_for_invoice&invoice_id=${invoiceId}`, function(items) {
                    modalBody.empty();
                    if (items.length > 0) {
                        items.forEach(item => {
                            const quantity = (item.quantity % 1 !== 0) ? parseFloat(item.quantity).toFixed(3) : parseInt(item.quantity);
                            modalBody.append(`
                                <tr class="border-b last:border-b-0">
                                    <td class="p-3 font-medium">${item.product_name}</td>
                                    <td class="p-3 text-gray-600">${item.hsn_sac}</td>
                                    <td class="p-3 text-gray-600 text-right">${quantity} ${item.unit}</td>
                                    <td class="p-3 text-gray-600 text-right">${currencyFormatter.format(item.rate)}</td>
                                    <td class="p-3 font-semibold text-gray-800 text-right">${currencyFormatter.format(item.amount)}</td>
                                </tr>`);
                        });
                    } else {
                        modalBody.html('<tr><td colspan="5" class="text-center p-8 text-gray-500">No items found for this invoice.</td></tr>');
                    }
                });
            });

            // Close Modal Logic
            $('.close-modal-btn').on('click', function() {
                $(this).closest('.fixed').addClass('hidden');
            });
        });
    </script>
</body>
</html>