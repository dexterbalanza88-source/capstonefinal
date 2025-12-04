<?php
require_once '../../db/conn.php';

// ✅ Detect connection variable
$db = $conn ?? ($mysqli ?? null);
if (!$db)
    die("❌ Database connection not found.");

// ✅ Get and sanitize filters
$search = trim($_GET['search'] ?? '');
$years = array_filter(explode(',', trim($_GET['year'] ?? '')));
$months = array_filter(explode(',', trim($_GET['month'] ?? '')));
$brgys = array_filter(explode(',', trim($_GET['brgy'] ?? '')));

// ✅ Base query — NOW FILTERED TO REGISTERED ONLY
$query = "SELECT 
            id,
            s_name,
            f_name,
            m_name,
            e_name,
            brgy,
            municipal,
            province,
            dob,
            no_farmparcel,
            total_farmarea,
            farmer_location_brgy,
            farmer_location_municipal,
            contact_num,
            created_at
          FROM registration_form
          WHERE status = 'Registered'";   // ← ADDED

$params = [];
$types = "";

// 🔹 Search filter
if ($search !== '') {
    $query .= " AND (f_name LIKE ? OR s_name LIKE ? OR brgy LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term);
    $types .= "sss";
}

// 🔹 Year filter (multiple)
if (!empty($years)) {
    $in = implode(',', array_fill(0, count($years), '?'));
    $query .= " AND YEAR(created_at) IN ($in)";
    $params = array_merge($params, $years);
    $types .= str_repeat('s', count($years));
}

// 🔹 Month filter (multiple)
if (!empty($months)) {
    $in = implode(',', array_fill(0, count($months), '?'));
    $query .= " AND MONTHNAME(created_at) IN ($in)";
    $params = array_merge($params, $months);
    $types .= str_repeat('s', count($months));
}

// 🔹 Barangay filter (case-insensitive)
if (!empty($brgys)) {
    $in = implode(',', array_fill(0, count($brgys), '?'));
    $query .= " AND LOWER(REPLACE(brgy,' ','')) IN ($in)";
    foreach ($brgys as $b) {
        $params[] = strtolower(str_replace(' ', '', $b));
    }
    $types .= str_repeat('s', count($brgys));
}

// 🔹 Ordering
$query .= " ORDER BY s_name, f_name";

// ✅ Prepare statement
$stmt = $db->prepare($query);
if (!$stmt)
    die("❌ SQL prepare failed: " . htmlspecialchars($db->error));

// ✅ Bind parameters
if (!empty($params))
    $stmt->bind_param($types, ...$params);

// ✅ Execute
$stmt->execute();
$result = $stmt->get_result();

// ✅ Collect results
$rows = [];
$totalParcels = 0;
$totalArea = 0.0;

while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $totalParcels += (int) $r['no_farmparcel'];
    $totalArea += (float) $r['total_farmarea'];
}

$result->free();

$reportMonthYear = date('F Y');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filtered Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* === HEADER LAYOUT === */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 30px;
            border-bottom: 2px solid #1b7e37;
            margin-bottom: 10px;
        }

        .header-logo {
            width: 80px;
            height: 80px;
        }

        .header-center {
            text-align: center;
            line-height: 1.3;
        }

        .header-center h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #0f5132;
        }

        .header-center h2 {
            margin: 2px 0;
            font-size: 16px;
            font-weight: 600;
            color: #155724;
        }

        .header-center h3 {
            margin: 4px 0 6px;
            font-size: 15px;
            font-weight: 600;
            color: #0d5030;
        }

        .header-center p {
            margin: 2px 0;
            font-size: 13px;
            color: #444;
        }

        /* === SUBHEADER SECTION === */
        .sub-header {
            text-align: center;
            margin-top: 8px;
            padding-bottom: 12px;
            border-bottom: 1px solid #ccc;
            margin-bottom: 10px;
        }

        .sub-header h4 {
            font-size: 16px;
            margin: 2px 0;
            font-weight: 700;
            color: #0f5132;
        }

        .sub-header p {
            margin: 2px;
            font-size: 13px;
        }

        /* === TABLE STYLING === */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 13px;
        }

        table th {
            background: #0f6f41;
            color: white;
            padding: 8px;
            text-align: center;
            font-size: 12px;
        }

        table td {
            padding: 6px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        .no-border {
            border: none !important;
        }

        .sub-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            width: 100%;
        }

        .sub-header h4 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .sub-header-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            margin: 15px 0;
            width: 100%;
        }

        /* Center the title */
        .sub-header h4 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            text-align: center;
            width: 100%;
        }

        .sub-header-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            margin: 15px 0;
            width: 100%;
        }

        .sub-header h4 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            text-align: center;
            width: 100%;
        }

        /* Buttons container aligned right */
        .actions {
            position: absolute;
            right: 0;
            display: flex;
            gap: 10px;
        }

        /* Back button style */
        .back-btn {
            background: #374151;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .back-btn:hover {
            background: #1f2937;
        }

        /* Print button */
        .print-btn {
            background: #0f6f41;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .print-btn:hover {
            background: #0b5933;
        }

        /* Hide buttons in print */
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto bg-white shadow-lg rounded-lg">

        <!-- Header -->
        <div class="report-header">

            <img src="http://localhost/capstonefinal/img/abra.png" class="header-logo">

            <div class="header-center">
                <h1>MUNICIPALITY OF ABRA DE ILOG</h1>
                <h2>Province of Occidental Mindoro</h2>
                <h3>Farmers List Report</h3>
                <p>As of <strong><?php echo htmlspecialchars($reportMonthYear); ?></strong></p>

                <?php if ($brgys): ?>
                    <p><strong>Barangays:</strong> <?= strtoupper(implode(', ', $brgys)); ?></p>
                <?php endif; ?>

                <?php if ($months): ?>
                    <p><strong>Months:</strong> <?= implode(', ', $months); ?></p>
                <?php endif; ?>

                <?php if ($years): ?>
                    <p><strong>Years:</strong> <?= implode(', ', $years); ?></p>
                <?php endif; ?>
            </div>

            <img src="http://localhost/capstonefinal/img/logo.png" class="header-logo">
        </div>
        <!-- =================== SECTION TITLE ===================== -->

        <div class="sub-header-wrapper">

            <!-- CENTERED TITLE -->
            <div class="sub-header">
                <h4>Official List of Registered Farmers</h4>
            </div>

            <!-- BUTTONS ON THE RIGHT -->
            <div class="actions no-print">
                <button class="back-btn" onclick="window.location.href='report.php';">
                    ⬅ BACK
                </button>

                <button class="print-btn" onclick="window.print();">
                    PRINT REPORT
                </button>
            </div>

        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full border-t border-gray-300">
                <thead class="bg-gray-100">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider">
                        <th>No.</th>
                        <th>Full Name</th>
                        <th>Address</th>
                        <th>Birthday</th>
                        <th>No. of Parcel</th>
                        <th>Farm Location</th>
                        <th>Total Area</th>
                        <th>Contact Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-gray-500">No records found.</td>
                        </tr>
                    <?php else:
                        $i = 1;
                        foreach ($rows as $r): ?>
                            <tr class="border-b text-sm">
                                <td><?php echo $i++; ?></td>
                                <td><?php echo strtoupper("{$r['f_name']} {$r['m_name']} {$r['s_name']} {$r['e_name']}"); ?>
                                </td>
                                <td><?php echo strtoupper("{$r['brgy']}, {$r['municipal']}, {$r['province']}"); ?></td>
                                <td><?php echo date('m/d/Y', strtotime($r['dob'])); ?></td>
                                <td class="text-center"><?php echo (int) $r['no_farmparcel']; ?></td>
                                <td><?php echo strtoupper("{$r['farmer_location_brgy']}, {$r['farmer_location_municipal']}"); ?>
                                </td>
                                <td class="text-center"><?php echo number_format($r['total_farmarea'], 2); ?></td>
                                <td><?php echo htmlspecialchars($r['contact_num']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>

                <?php if (!empty($rows)): ?>
                    <tfoot>
                        <tr class="border-t-2 border-gray-400 text-sm">
                            <td colspan="4" class="text-right pr-4">TOTAL:</td>
                            <td class="text-center"><?php echo number_format($totalParcels); ?></td>
                            <td></td>
                            <td class="text-center"><?php echo number_format($totalArea, 2); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>

</html>