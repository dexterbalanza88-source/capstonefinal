<?php
require_once '../../db/conn.php';

// ✅ Filters
$year = $_GET['year'] ?? '';
$brgys = $_GET['brgy'] ?? '';

$barangays = array_filter(explode(',', $brgys));
$years = array_filter(explode(',', $year));

// ✅ Dynamic WHERE conditions
$where = [];
$params = [];
$types = '';

if (!empty($years)) {
    $placeholders = implode(',', array_fill(0, count($years), '?'));
    $where[] = "YEAR(created_at) IN ($placeholders)";
    $params = array_merge($params, $years);
    $types .= str_repeat('s', count($years));
}
if (!empty($barangays)) {
    $placeholders = implode(',', array_fill(0, count($barangays), '?'));
    $where[] = "brgy IN ($placeholders)";
    $params = array_merge($params, $barangays);
    $types .= str_repeat('s', count($barangays));
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// ✅ Query to compute summary per barangay
$sql = "
    SELECT 
        brgy AS barangay,
        COUNT(*) AS total_farmers,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) AS submitted
    FROM registration_form
    $whereSQL
    GROUP BY brgy
    ORDER BY brgy ASC
";

$stmt = $conn->prepare($sql);
if ($params)
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$totalFarmers = $totalSubmitted = $totalRemaining = 0;

while ($row = $result->fetch_assoc()) {
    $remaining = $row['total_farmers'] - $row['submitted'];
    $rows[] = [
        'barangay' => $row['barangay'],
        'total_farmers' => $row['total_farmers'],
        'submitted' => $row['submitted'],
        'remaining' => $remaining
    ];
    $totalFarmers += $row['total_farmers'];
    $totalSubmitted += $row['submitted'];
    $totalRemaining += $remaining;
}

$reportMonthYear = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Barangay Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }

        th,
        td {
            font-size: 13px;
            padding: 8px 10px;
        }
    </style>
</head>

<body class="bg-gray-100 p-6">
    <div class="max-w-5xl mx-auto bg-white shadow-lg rounded-lg">
        <!-- Header -->
        <div class="flex justify-between items-center border-b p-4">
            <div class="flex items-center gap-2">
                <img src="../img/logo.png" alt="Logo" class="h-12 w-12 rounded-full border border-gray-300 bg-white">
                <div>
                    <h1 class="text-base font-semibold">Municipal Agriculture Office – Abra De Ilog</h1>
                    <p class="text-xs text-gray-600">Barangay Report Summary</p>
                </div>
            </div>
            <div class="no-print flex gap-2">
                <button onclick="window.print()"
                    class="bg-green-700 hover:bg-green-800 text-white px-3 py-1 rounded">PRINT</button>
                <button onclick="window.close()"
                    class="bg-gray-700 hover:bg-gray-800 text-white px-3 py-1 rounded">CLOSE</button>
            </div>
        </div>

        <!-- Title -->
        <div class="text-center py-4 border-b">
            <h2 class="text-lg font-semibold">SUMMARY REPORT BY BARANGAY</h2>
            <p class="text-sm">As of <?php echo htmlspecialchars($reportMonthYear); ?></p>
            <?php if (!empty($years)): ?>
                <p class="text-sm font-medium">Year: <?php echo htmlspecialchars(implode(', ', $years)); ?></p>
            <?php endif; ?>
            <?php if (!empty($barangays)): ?>
                <p class="text-sm font-medium">Barangays: <?php echo htmlspecialchars(implode(', ', $barangays)); ?></p>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-300">
                <thead class="bg-green-800 text-white">
                    <tr>
                        <th class="border px-3 py-2 text-left">Barangay</th>
                        <th class="border px-3 py-2">No. of Farmers</th>
                        <th class="border px-3 py-2">Submitted</th>
                        <th class="border px-3 py-2">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-500">No records found.</td>
                        </tr>
                    <?php else:
                        foreach ($rows as $r): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="border px-3 py-2 font-medium"><?php echo strtoupper($r['barangay']); ?></td>
                                <td class="border px-3 py-2 text-center"><?php echo $r['total_farmers']; ?></td>
                                <td class="border px-3 py-2 text-center text-green-700 font-semibold">
                                    <?php echo $r['submitted']; ?></td>
                                <td class="border px-3 py-2 text-center text-red-600 font-semibold">
                                    <?php echo $r['remaining']; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                    <tfoot class="bg-gray-100 font-semibold">
                        <tr>
                            <td class="border px-3 py-2 text-right">TOTAL</td>
                            <td class="border px-3 py-2 text-center"><?php echo number_format($totalFarmers); ?></td>
                            <td class="border px-3 py-2 text-center text-green-700">
                                <?php echo number_format($totalSubmitted); ?></td>
                            <td class="border px-3 py-2 text-center text-red-600">
                                <?php echo number_format($totalRemaining); ?></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <div class="p-4 text-xs text-gray-500 text-right">
            Generated on <?php echo date('F j, Y g:i A'); ?>
        </div>
    </div>
</body>

</html>