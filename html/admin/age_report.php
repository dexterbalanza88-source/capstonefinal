<?php
include "../../db/conn.php";

// GET FILTERS
$selectedYears = isset($_GET['year']) ? explode(",", $_GET['year']) : [];
$selectedMonths = isset($_GET['month']) ? explode(",", $_GET['month']) : [];
$selectedBrgys = isset($_GET['brgy']) ? explode(",", $_GET['brgy']) : [];

// Convert month names (e.g. October) to numeric value (10)
$monthNumbers = [];
foreach ($selectedMonths as $m) {
    if (!empty($m)) {
        $monthNumbers[] = date("m", strtotime($m));
    }
}

// Build WHERE Conditions
$where = [];

if (!empty($selectedYears)) {
    $yearList = "'" . implode("','", $selectedYears) . "'";
    $where[] = "YEAR(`date`) IN ($yearList)";
}

if (!empty($monthNumbers)) {
    $monthList = "'" . implode("','", $monthNumbers) . "'";
    $where[] = "MONTH(`date`) IN ($monthList)";
}

if (!empty($selectedBrgys)) {
    $where[] = "UPPER(brgy) IN (" . implode(',', array_map(
        fn($b) => "'" . strtoupper(trim($b)) . "'",
        $selectedBrgys
    )) . ")";
}

$whereSQL = "";
if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

// =====================================================
//                AGE QUERY (CLEANED)
// =====================================================
$query = "
SELECT 
    UPPER(TRIM(brgy)) AS brgy,
    CASE
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 31 AND 45 THEN '31-45'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 46 AND 60 THEN '46-60'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) >= 61 THEN '61+'
        ELSE 'Unknown'
    END AS age_group,
    COUNT(*) AS total_farmers
FROM registration_form
$whereSQL
GROUP BY UPPER(TRIM(brgy)), age_group
ORDER BY UPPER(TRIM(brgy)), age_group;
";

$result = $conn->query($query);

// =====================================================
//     PREPARE VARIABLES SAFELY (PREVENT ERRORS)
// =====================================================
$labels = [];
$data18_30 = [];
$data31_45 = [];
$data46_60 = [];
$data61 = [];

// 🔥 Always initialize to avoid undefined variable warning
$barangayData = [];

// Fill rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $brgy = strtoupper(trim($row['brgy']));
        $age = $row['age_group'];
        $total = (int) $row['total_farmers'];

        if (!isset($barangayData[$brgy])) {
            // Ensure defaults exist for missing groups
            $barangayData[$brgy] = [
                '18-30' => 0,
                '31-45' => 0,
                '46-60' => 0,
                '61+' => 0,
                'Unknown' => 0
            ];
        }

        $barangayData[$brgy][$age] = $total;
    }
}

// Convert to arrays for Chart.js
foreach ($barangayData as $brgy => $ages) {
    $labels[] = $brgy;
    $data18_30[] = $ages['18-30'] ?? 0;
    $data31_45[] = $ages['31-45'] ?? 0;
    $data46_60[] = $ages['46-60'] ?? 0;
    $data61[] = $ages['61+'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Age Report | Farmers</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<style>
    body {
        font-family: "Segoe UI", Arial, sans-serif;
        background: #fff;
        padding: 20px 60px;
        color: #333;
    }

    /* ===== HEADER ===== */
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 40px 15px 40px;
        border-bottom: 3px solid #2e7d32;
        margin-bottom: 20px;
    }

    .header-logo {
        width: 85px;
        height: 85px;
        object-fit: contain;
        margin-top: 25px;
    }

    .header-center {
        text-align: center;
        margin-top: 10px;
    }

    .report-header h1 {
        font-size: 30px;
        font-weight: 800;
        margin-bottom: 4px;
        color: #1b5e20;
    }

    .report-header h2 {
        font-size: 17px;
        margin: 2px 0;
    }

    .report-header p {
        margin-top: 6px;
        font-size: 13px;
        color: #444;
    }

    /* ===== PRINT BUTTON ===== */
    .print-btn {
        display: inline-block;
        background-color: #2e7d32;
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        border: none;
        font-size: 14px;
        cursor: pointer;
        margin-bottom: 20px;
        float: right;
    }

    .print-btn:hover {
        background-color: #256628;
    }

    /* ===== CHART WRAPPER ===== */
    #chartWrapper {
        width: 700px;
        margin: 0 auto;
        background: white;
        padding: 25px;
        border-radius: 16px;
        box-shadow: 0px 3px 12px rgba(0, 0, 0, 0.12);
    }

    #chartWrapper h2 {
        color: #1b5e20;
        font-weight: 700;
    }

    canvas#ageBarChart,
    canvas#genderBarChart {
        width: 700px !important;
        max-width: 700px !important;
        height: auto !important;
        margin: 0 auto !important;
        display: block !important;
    }

    /* ===== TABLE ===== */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 14px;
    }

    th,
    td {
        border: 1px solid #ccc;
        padding: 8px;
        text-align: center;
    }

    th {
        background-color: #c8e6c9;
        color: #2e7d32;
        font-weight: bold;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    /* ===== PRINT FIX ===== */
    @media print {
        .print-btn {
            display: none;
        }

        body {
            padding: 0 20px;
            zoom: 95%;
        }

        .header-logo {
            width: 70px;
            height: 70px;
            margin-top: 5px;
        }

        .report-header h1 {
            font-size: 24px;
        }

        .report-header h2 {
            font-size: 14px;
        }

        .report-header p {
            font-size: 12px;
        }

        #chartWrapper {
            width: 700px !important;
            margin: 0 auto !important;
            box-shadow: none;
            border: none;
        }
    }
</style>


<body class="bg-gray-50 p-6">

    <!-- =============== HEADER =============== -->
    <div class="report-header">

        <!-- Left Logo -->
        <img src="http://localhost/capstonefinal/img/abra.png" class="header-logo">

        <!-- Center Title -->
        <div class="header-center">
            <h1>MUNICIPALITY OF ABRA DE ILOG</h1>
            <h2>Province of Occidental Mindoro</h2>
            <h1 class="text-green-700" style="font-size: 22px; margin-top: 4px;">
                Gender Distribution Report per Barangay
            </h1>
            <p>
                <?php
                echo !empty($monthNumber)
                    ? "Filtered Month: <strong>$monthTitle</strong>"
                    : "Showing All Months";
                ?>
            </p>
        </div>
        <!-- Right Logo -->
        <img src="http://localhost/capstonefinal/img/logo.png" class="header-logo">

    </div>

    <!-- =============== PRINT BUTTON =============== -->
    <button onclick="window.print()" class="print-btn">
        🖨️ Print Report
    </button>

    <!-- =============== CHART WRAPPER =============== -->
    <div id="chartWrapper" class="bg-white p-6 mt-6 rounded-xl shadow-md">

        <h2 class="text-2xl font-bold mb-1 text-center text-green-700">
            Age Distribution of Registered Farmers
        </h2>

        <p class="text-center text-gray-500 mb-5 italic">Grouped by Barangay</p>

        <div class="chart-container flex items-center justify-center">
            <canvas id="ageBarChart"></canvas>
        </div>

    </div>



    <!-- =============== TABLE =============== -->
    <table>
        <thead>
            <tr>
                <th>Barangay</th>
                <th>18–30</th>
                <th>31–45</th>
                <th>46–60</th>
                <th>61+</th>
                <th>Total</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($barangayData as $brgy => $age): ?>
                <tr>
                    <td><?= $brgy ?></td>

                    <td><?= $age['18-30'] ?? 0 ?></td>
                    <td><?= $age['31-45'] ?? 0 ?></td>
                    <td><?= $age['46-60'] ?? 0 ?></td>
                    <td><?= $age['61+'] ?? 0 ?></td>

                    <td class="font-bold">
                        <?=
                            ($age['18-30'] ?? 0) +
                            ($age['31-45'] ?? 0) +
                            ($age['46-60'] ?? 0) +
                            ($age['61+'] ?? 0)
                            ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>

    </table>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {

            const labels = <?php echo json_encode($labels); ?>;
            const data18_30 = <?php echo json_encode($data18_30); ?>;
            const data31_45 = <?php echo json_encode($data31_45); ?>;
            const data46_60 = <?php echo json_encode($data46_60); ?>;
            const data61 = <?php echo json_encode($data61); ?>;

            const ctx = document.getElementById("ageBarChart").getContext("2d");

            // Gradient Colors
            const g1 = ctx.createLinearGradient(0, 0, 0, 350);
            g1.addColorStop(0, "rgba(46, 204, 113,0.95)");
            g1.addColorStop(1, "rgba(46, 204, 113,0.45)");

            const g2 = ctx.createLinearGradient(0, 0, 0, 350);
            g2.addColorStop(0, "rgba(52, 152, 219,0.95)");
            g2.addColorStop(1, "rgba(52, 152, 219,0.45)");

            const g3 = ctx.createLinearGradient(0, 0, 0, 350);
            g3.addColorStop(0, "rgba(241, 196, 15,0.95)");
            g3.addColorStop(1, "rgba(241, 196, 15,0.45)");

            const g4 = ctx.createLinearGradient(0, 0, 0, 350);
            g4.addColorStop(0, "rgba(231, 76, 60,0.95)");
            g4.addColorStop(1, "rgba(231, 76, 60,0.45)");

            const shadowPlugin = {
                id: "shadow",
                beforeDatasetsDraw(chart) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.shadowColor = "rgba(0,0,0,0.18)";
                    ctx.shadowBlur = 14;
                    ctx.shadowOffsetX = 3;
                    ctx.shadowOffsetY = 6;
                },
                afterDatasetsDraw(chart) {
                    chart.ctx.restore();
                }
            };

            new Chart(ctx, {
                type: "bar",
                data: {
                    labels: labels,
                    datasets: [
                        { label: "18–30", data: data18_30, backgroundColor: g1, borderRadius: 14, barThickness: 28 },
                        { label: "31–45", data: data31_45, backgroundColor: g2, borderRadius: 14, barThickness: 28 },
                        { label: "46–60", data: data46_60, backgroundColor: g3, borderRadius: 14, barThickness: 28 },
                        { label: "61+", data: data61, backgroundColor: g4, borderRadius: 14, barThickness: 28 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    plugins: {
                        title: {
                            display: true,
                            text: "Age Distribution of Registered Farmers",
                            font: { size: 22, family: "Poppins", weight: "bold" },
                            padding: { bottom: 10 }
                        },
                        legend: {
                            position: "top",
                            labels: {
                                usePointStyle: true,
                                pointStyle: "circle",
                                font: { size: 13 }
                            }
                        }
                    },

                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                },

                plugins: [shadowPlugin]
            });

        });
    </script>




</body>

</html>