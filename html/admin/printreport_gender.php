<?php
require '../../db/conn.php';

// Read selected month from GET (e.g., "01" or "January" or "January,February")
// Read month from GET
$selectedMonth = $_GET['date'] ?? '';
$monthNumber = '';
$monthTitle = '';

// --- FIX #1: Clean input: take only first month if multiple are passed ---
if (!empty($selectedMonth) && strpos($selectedMonth, ',') !== false) {
    $selectedMonth = explode(',', $selectedMonth)[0];
}

// --- FIX #2: Convert month to number and title ---
if (!empty($selectedMonth)) {

    if (is_numeric($selectedMonth)) {
        // Convert numeric month to correct 01..12 format
        $monthNumber = str_pad($selectedMonth, 2, '0', STR_PAD_LEFT);
        $monthTitle = date('F', mktime(0, 0, 0, (int) $selectedMonth, 1));
    } else {
        // Convert name to number
        $timestamp = strtotime($selectedMonth);

        if ($timestamp !== false) {
            $monthNumber = date('m', $timestamp);
            $monthTitle = date('F', $timestamp);
        }
    }
}

// Build query
$query = "
    SELECT 
        brgy AS barangay,
        SUM(CASE WHEN LOWER(gender) = 'male' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN LOWER(gender) = 'female' THEN 1 ELSE 0 END) AS female_count
    FROM registration_form
    WHERE 1 = 1
";

// Apply filter ONLY if monthNumber is correct (01–12)
if (!empty($monthNumber) && (int) $monthNumber >= 1 && (int) $monthNumber <= 12) {
    $query .= " AND MONTH(date) = '$monthNumber' ";
}


$query .= "
    GROUP BY brgy
    ORDER BY brgy
";

$result = $conn->query($query);
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'barangay' => $row['barangay'],
        'male_count' => (int) $row['male_count'],
        'female_count' => (int) $row['female_count']
    ];
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Gender Report – Abra de Ilog, Occidental Mindoro</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #fff;
            padding: 20px 60px;
            color: #333;
        }

        /* ===== Header Styling ===== */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 10px;
        }

        .report-header h1 {
            font-size: 26px;
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 5px;
        }

        .report-header h2 {
            font-size: 18px;
            color: #444;
            margin-bottom: 4px;
        }

        .report-header p {
            font-size: 14px;
            color: #666;
        }

        /* ===== Print Button ===== */
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

        @media print {
            .print-btn {
                display: none;
            }

            body {
                padding: 0 20px;
            }
        }

        /* ===== Chart + Table ===== */
        .chart-container {
            height: 450px;
            width: 100%;
            margin-bottom: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 40px 15px 40px;
            /* reduced top padding */
            margin-bottom: 10px;
            border-bottom: 3px solid #2e7d32;
        }

        .header-logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
            margin-top: 25px;
            /* 🔥 lowers the logos to align with text */
        }

        .header-center {
            text-align: center;
            margin-top: 10px;
            /* pulls the text upward */
        }

        /* Title styling */
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

        /* PRINT FIXES */
        @media print {
            @media print {
                .header-logo {
                    width: 70px;
                    height: 70px;
                    margin-top: 10px;
                }

                .report-header h1 {
                    font-size: 22px;
                }
            }

            .header-logo {
                width: 70px;
                height: 70px;
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
        }

        /* Fix chart stacking vertically when printing */
       /* Prevent Chart.js from stretching full width */
    canvas#genderBarChart {
        width: 700px !important;     /* SAME AS WRAPPER */
        max-width: 700px !important;
        margin: 0 auto !important;   /* CENTER */
        display: block !important;
    }

    /* Center the chart container */
    #chartWrapper {
        width: 700px !important;
        margin: 0 auto !important;
        text-align: center !important;
        display: block !important;
    }

    /* Disable Tailwind h-full overrides */
    .h-full,
    .h-screen {
        height: auto !important;
    }

    </style>
</head>

<body>


    <div class="report-header">
        <img src="http://localhost/capstonefinal/img/abra.png" class="header-logo">

        <div class="header-center">
            <h1>MUNICIPALITY OF ABRA DE ILOG</h1>
            <h2>Province of Occidental Mindoro</h2>
            <h2>Gender Distribution Report per Barangay</h2>
            <p>
                <?php
                echo !empty($monthNumber)
                    ? "Filtered Month: <strong>$monthTitle</strong>"
                    : "Showing All Months";
                ?>
            </p>
        </div>

        <img src="http://localhost/capstonefinal/img/logo.png" class="header-logo">
    </div>


    <!-- 🖨️ Print Button -->
    <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>

    <div id="chartWrapper">
        <canvas id="genderBarChart"></canvas>
    </div>

    <!-- 📋 Data Table -->
    <table>
        <thead>
            <tr>
                <th>Barangay</th>
                <th>Male</th>
                <th>Female</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['barangay']) ?></td>
                    <td><?= $row['male_count'] ?></td>
                    <td><?= $row['female_count'] ?></td>
                    <td><?= $row['male_count'] + $row['female_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const data = <?php echo json_encode($data); ?>;

            if (!data || data.length === 0) {
                alert("No data available for the chart.");
                return;
            }

            const ctx = document.getElementById('genderBarChart').getContext('2d');

            const barangays = data.map(item => item.barangay);
            const maleCounts = data.map(item => Number(item.male_count) || 0);
            const femaleCounts = data.map(item => Number(item.female_count) || 0);

            // ⭐ PREMIUM GRADIENT COLORS
            const maleGradient = ctx.createLinearGradient(0, 0, 0, 350);
            maleGradient.addColorStop(0, "rgba(56, 118, 255, 0.95)");
            maleGradient.addColorStop(1, "rgba(56, 118, 255, 0.45)");

            const femaleGradient = ctx.createLinearGradient(0, 0, 0, 350);
            femaleGradient.addColorStop(0, "rgba(255, 99, 132, 0.95)");
            femaleGradient.addColorStop(1, "rgba(255, 99, 132, 0.45)");

            // ⭐ ELEVATED SHADOW FOR PREMIUM EFFECT
            const shadowPlugin = {
                id: "shadow",
                beforeDatasetsDraw(chart, args, opts) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.shadowColor = "rgba(0,0,0,0.18)";
                    ctx.shadowBlur = 14;
                    ctx.shadowOffsetX = 3;
                    ctx.shadowOffsetY = 6;
                },
                afterDatasetsDraw(chart, args, opts) {
                    chart.ctx.restore();
                }
            };

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: barangays,
                    datasets: [
                        {
                            label: 'Male',
                            data: maleCounts,
                            backgroundColor: maleGradient,
                            borderRadius: 14,
                            borderSkipped: false,
                            barThickness: 32
                        },
                        {
                            label: 'Female',
                            data: femaleCounts,
                            backgroundColor: femaleGradient,
                            borderRadius: 14,
                            borderSkipped: false,
                            barThickness: 32
                        }
                    ]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    // ⭐ IMPORTANT FIX FOR PRINT CENTERING
                    layout: {
                        padding: { left: 0, right: 0 }
                    },

                    animation: {
                        duration: 1500,
                        easing: "easeOutQuart"
                    },

                    plugins: {
                        // ⭐ MODERN PREMIUM TITLE — ALWAYS CENTER
                        title: {
                            display: true,
                            text: "Gender Distribution of Registered Farmers",
                            align: "center",
                            font: {
                                size: 28,
                                weight: "bold",
                                family: "'Poppins', 'Inter', sans-serif"
                            },
                            color: "#1e293b",
                            padding: { top: 20, bottom: 8 }
                        },

                        // ⭐ LUXURY SUBTITLE — CENTERED
                        subtitle: {
                            display: true,
                            text: "<?php echo !empty($monthNumber) ? $monthTitle : 'Showing All Months'; ?>",
                            align: "center",
                            font: {
                                size: 16,
                                family: "'Inter', sans-serif",
                                style: "italic"
                            },
                            color: "#475569",
                            padding: { bottom: 20 }
                        },

                        legend: {
                            position: "top",
                            labels: {
                                usePointStyle: true,
                                pointStyle: "circle",
                                font: { size: 14, family: "'Inter', sans-serif" },
                                color: "#1e293b"
                            },
                            padding: 20
                        },

                        datalabels: {
                            color: "#0f172a",
                            anchor: "end",
                            align: "top",
                            offset: 4,
                            font: { size: 13, weight: "600" },
                            formatter: v => v > 0 ? v : ""
                        },

                        tooltip: {
                            backgroundColor: "rgba(255,255,255,0.95)",
                            titleColor: "#0f172a",
                            bodyColor: "#334155",
                            borderColor: "#e2e8f0",
                            borderWidth: 1,
                            cornerRadius: 10,
                            padding: 12,
                            displayColors: false
                        }
                    },

                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: { size: 13, family: "'Inter'" },
                                color: "#475569"
                            },
                            grid: {
                                color: "rgba(0,0,0,0.04)",
                                lineWidth: 1.2,
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: "Number of Registered Farmers",
                                color: "#1e293b",
                                font: { size: 14, weight: "bold" }
                            }
                        },

                        x: {
                            ticks: {
                                font: { size: 13, family: "'Inter'" },
                                color: "#334155"
                            },
                            grid: { display: false },
                            title: {
                                display: true,
                                text: "Barangay",
                                color: "#1e293b",
                                font: { size: 14, weight: "bold" }
                            }
                        }
                    }
                },

                plugins: [shadowPlugin, ChartDataLabels]
            });
        });
    </script>







</body>

</html>