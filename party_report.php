<?php
require_once 'config/db.php';

// --- AJAX Request Handling ---
if (isset($_GET['action']) && $_GET['action'] === 'ajax') {
    header('Content-Type: application/json');
    $request_type = $_GET['request'] ?? '';

    // 1. Fetch monthly summary for a party and year
    if ($request_type === 'get_monthly_summary' && isset($_GET['party_id'], $_GET['year'])) {
        $party_id = intval($_GET['party_id']);
        $year = intval($_GET['year']);
        $stmt = $conn->prepare("
            SELECT
                MONTH(invoice_date) as month,
                YEAR(invoice_date) as year,
                SUM(grand_total) as total_sales,
                COUNT(id) as invoice_count
            FROM invoices
            WHERE biller_id = ? AND YEAR(invoice_date) = ?
            GROUP BY MONTH(invoice_date), YEAR(invoice_date)
            ORDER BY month ASC
        ");
        $stmt->bind_param("ii", $party_id, $year);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($result);
    }
    // 2. Fetch all invoices for a specific month
    elseif ($request_type === 'get_invoices_for_month' && isset($_GET['party_id'], $_GET['year'], $_GET['month'])) {
        $party_id = intval($_GET['party_id']);
        $year = intval($_GET['year']);
        $month = intval($_GET['month']);
        $stmt = $conn->prepare("
            SELECT id, invoice_no, invoice_date, grand_total
            FROM invoices
            WHERE biller_id = ? AND YEAR(invoice_date) = ? AND MONTH(invoice_date) = ?
            ORDER BY invoice_date ASC
        ");
        $stmt->bind_param("iii", $party_id, $year, $month);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($result);
    }
    // 3. Fetch all items for a specific invoice
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
    // Invalid request
    else {
        echo json_encode(['error' => 'Invalid request']);
    }

    $conn->close();
    exit;
}

// Fetch all parties for the dropdown
$parties_result = $conn->query("SELECT `id`, `business_name`, `unique_id` FROM `parties` ORDER BY `business_name` ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party-wise Sales Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        /* Custom scrollbar for better aesthetics */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        body { background-color: #f7fafc; }
    </style>
</head>
<body>

    <div class="container mx-auto p-4 md:p-8">
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8" data-aos="fade-up">
            <div class="flex items-center justify-between pb-6 border-b border-gray-200">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-chart-line text-blue-500 mr-4"></i>
                    Party Sales Report
                </h1>
                <a href="invoices.php" class="text-blue-500 hover:text-blue-700 transition duration-300">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Invoices
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 my-8" id="filter-form">
                <div>
                    <label for="party_id" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-tie mr-2 text-gray-400"></i>Select Party
                    </label>
                    <select id="party_id" class="block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition">
                        <option value="">-- Choose a party --</option>
                        <?php while($party = $parties_result->fetch_assoc()): ?>
                            <option value="<?= $party['id'] ?>">
                                <?= htmlspecialchars($party['business_name']) . ' (' . htmlspecialchars($party['unique_id']) . ')' ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt mr-2 text-gray-400"></i>Select Year
                    </label>
                    <select id="year" class="block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition">
                        <?php 
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-day mr-2 text-gray-400"></i>Select Month
                    </label>
                    <select id="month" class="block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition">
                        <option value="all" selected>All Months (Yearly Report)</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 10)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="self-end">
                    <button id="generate-report-btn" class="w-full bg-blue-600 text-white font-bold p-3 rounded-lg shadow-md hover:bg-blue-700 transition-transform transform hover:scale-105 flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i>Generate Report
                    </button>
                </div>
            </div>

            <div id="report-output">
                <div id="placeholder" class="text-center py-16 border-2 border-dashed border-gray-300 rounded-lg">
                    <i class="fas fa-folder-open text-5xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Your report will be displayed here.</p>
                </div>
                <div id="report-content" class="hidden">
                    <h2 id="report-title" class="text-2xl font-bold text-gray-700 mb-6"></h2>
                    
                    <div id="yearly-summary-view">
                        <div id="monthly-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>
                        <div class="mt-8 pt-6 border-t-2 border-blue-500 text-right">
                            <p class="text-gray-600 text-lg">Annual Total for Selected Year:</p>
                            <p id="annual-total" class="text-3xl font-extrabold text-blue-600">₹0.00</p>
                        </div>
                    </div>
                    
                    <div id="monthly-detail-view" class="hidden">
                         <div class="overflow-x-auto rounded-lg border border-gray-200">
                             <table class="w-full text-left">
                                 <thead class="bg-gray-100">
                                     <tr>
                                         <th class="p-4 text-sm font-semibold text-gray-700">Invoice #</th>
                                         <th class="p-4 text-sm font-semibold text-gray-700">Date</th>
                                         <th class="p-4 text-sm font-semibold text-gray-700 text-right">Amount</th>
                                         <th class="p-4 text-sm font-semibold text-gray-700 text-center">Actions</th>
                                     </tr>
                                 </thead>
                                 <tbody id="monthly-invoice-list"></tbody>
                             </table>
                         </div>
                         <div class="mt-8 pt-6 border-t-2 border-green-500 text-right">
                            <p class="text-gray-600 text-lg">Total for Selected Month:</p>
                            <p id="monthly-total" class="text-3xl font-extrabold text-green-600">₹0.00</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="itemsModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col" data-aos="zoom-in-up">
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
                            <th class="p-3 text-sm font-semibold text-gray-600 text-center">Unit</th>
                            <th class="p-3 text-sm font-semibold text-gray-600 text-right">Rate</th>
                            <th class="p-3 text-sm font-semibold text-gray-600 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="itemsModalBody"></tbody>
                </table>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        $(document).ready(function() {
            AOS.init({ duration: 800, once: true });

            const currencyFormatter = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
            
            // --- Generate Report Button Click ---
            $('#generate-report-btn').on('click', function() {
                const partyId = $('#party_id').val();
                const year = $('#year').val();
                const month = $('#month').val();
                const partyName = $('#party_id option:selected').text().trim();
                const btn = $(this);

                if (!partyId) {
                    alert('Please select a party first.');
                    return;
                }

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Generating...');
                
                $('#placeholder').addClass('hidden');
                $('#report-content').removeClass('hidden');

                // Decide which report to show: Yearly or Monthly
                if (month === 'all') {
                    showYearlyReport(partyId, year, partyName, btn);
                } else {
                    showMonthlyReport(partyId, year, month, partyName, btn);
                }
            });

            // --- Function to Show YEARLY Report ---
            function showYearlyReport(partyId, year, partyName, btn) {
                $('#yearly-summary-view').removeClass('hidden');
                $('#monthly-detail-view').addClass('hidden');
                $('#report-title').text(`Yearly Report for ${partyName} (${year})`);

                $.ajax({
                    url: `?action=ajax&request=get_monthly_summary&party_id=${partyId}&year=${year}`,
                    dataType: 'json',
                    success: function(data) {
                        const container = $('#monthly-cards').empty();
                        let annualTotal = 0;
                        if (data.length > 0) {
                            data.forEach(monthData => {
                                const monthName = new Date(year, monthData.month - 1, 1).toLocaleString('default', { month: 'long' });
                                annualTotal += parseFloat(monthData.total_sales);
                                container.append(`
                                    <div class="bg-gray-50 p-6 rounded-xl shadow-lg border hover:shadow-2xl hover:border-blue-400 transition-all" data-aos="fade-up">
                                        <h4 class="text-xl font-bold text-gray-700">${monthName}</h4>
                                        <p class="text-3xl font-extrabold text-green-600 my-2">${currencyFormatter.format(monthData.total_sales)}</p>
                                        <p class="text-sm text-gray-500">${monthData.invoice_count} Invoices</p>
                                    </div>
                                `);
                            });
                        } else {
                            container.html('<p class="col-span-full text-center text-gray-500 py-10">No sales data found for the selected year.</p>');
                        }
                        $('#annual-total').text(currencyFormatter.format(annualTotal));
                        setTimeout(() => AOS.refresh(), 100);
                    },
                    complete: () => btn.prop('disabled', false).html('<i class="fas fa-search mr-2"></i>Generate Report')
                });
            }

            // --- Function to Show MONTHLY Report ---
            function showMonthlyReport(partyId, year, month, partyName, btn) {
                $('#monthly-detail-view').removeClass('hidden');
                $('#yearly-summary-view').addClass('hidden');
                const monthName = $('#month option:selected').text();
                $('#report-title').text(`Monthly Report for ${partyName} (${monthName} ${year})`);

                $.ajax({
                    url: `?action=ajax&request=get_invoices_for_month&party_id=${partyId}&year=${year}&month=${month}`,
                    dataType: 'json',
                    success: function(invoices) {
                        const container = $('#monthly-invoice-list').empty();
                        let monthlyTotal = 0;
                        if (invoices.length > 0) {
                            invoices.forEach(inv => {
                                monthlyTotal += parseFloat(inv.grand_total);
                                const invDate = new Date(inv.invoice_date).toLocaleDateString('en-GB');
                                container.append(`
                                    <tr class="border-b hover:bg-gray-50" data-aos="fade-up">
                                        <td class="p-4 font-medium text-blue-600">${inv.invoice_no}</td>
                                        <td class="p-4 text-gray-600">${invDate}</td>
                                        <td class="p-4 font-semibold text-gray-800 text-right">${currencyFormatter.format(inv.grand_total)}</td>
                                        <td class="p-4 text-center">
                                            <button class="view-items-btn bg-blue-100 text-blue-700 px-3 py-1 rounded-md text-sm font-semibold hover:bg-blue-200" data-invoice-id="${inv.id}" data-invoice-no="${inv.invoice_no}">
                                                <i class="fas fa-eye mr-1"></i>View Items
                                            </button>
                                        </td>
                                    </tr>
                                `);
                            });
                        } else {
                             container.html('<tr><td colspan="4" class="text-center p-8 text-gray-500">No invoices found for this month.</td></tr>');
                        }
                        $('#monthly-total').text(currencyFormatter.format(monthlyTotal));
                         setTimeout(() => AOS.refresh(), 100);
                    },
                    complete: () => btn.prop('disabled', false).html('<i class="fas fa-search mr-2"></i>Generate Report')
                });
            }

            // --- "View Items" Button Click (works for both views) ---
            $('body').on('click', '.view-items-btn', function() {
                const invoiceId = $(this).data('invoice-id');
                const invoiceNo = $(this).data('invoice-no');

                $('#itemsModalTitle').text(`Items for Invoice #${invoiceNo}`);
                const modalBody = $('#itemsModalBody');
                modalBody.html('<tr><td colspan="6" class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-blue-500"></i></td></tr>');
                $('#itemsModal').removeClass('hidden');

                $.getJSON(`?action=ajax&request=get_items_for_invoice&invoice_id=${invoiceId}`, function(items) {
                    modalBody.empty();
                     if(items.length > 0) {
                        items.forEach(item => {
                            const quantity = (item.quantity % 1 !== 0) ? parseFloat(item.quantity).toFixed(3) : parseInt(item.quantity);
                            modalBody.append(`
                                <tr class="border-b">
                                    <td class="p-4 font-medium">${item.product_name}</td>
                                    <td class="p-4 text-gray-600">${item.hsn_sac}</td>
                                    <td class="p-4 text-gray-600 text-right">${quantity}</td>
                                    <td class="p-4 text-gray-600 text-center">${item.unit}</td>
                                    <td class="p-4 text-gray-600 text-right">${currencyFormatter.format(item.rate)}</td>
                                    <td class="p-4 font-semibold text-gray-800 text-right">${currencyFormatter.format(item.amount)}</td>
                                </tr>`);
                        });
                    } else {
                        modalBody.html('<tr><td colspan="6" class="text-center p-8 text-gray-500">No items found for this invoice.</td></tr>');
                    }
                });
            });

            // --- Close Modal Logic ---
            $('.close-modal-btn').on('click', function() {
                $(this).closest('.fixed').addClass('hidden');
            });
        });
    </script>
</body>
</html>