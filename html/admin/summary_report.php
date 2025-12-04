<?php
include '../../db/conn.php';

// ✅ Total farmers
$totalFarmers = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM registration_form
"))['total'];

// ✅ Total livelihoods
$totalLivelihoods = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM livelihoods
"))['total'];

// ✅ Total barangays covered
$totalBarangays = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT brgy) AS total FROM registration_form
"))['total'];

// ✅ Gender breakdown
$genderData = mysqli_query($conn, "
    SELECT gender, COUNT(*) AS total 
    FROM registration_form 
    GROUP BY gender
");

// ✅ Livelihood type breakdown (use category_id or sub_activity)
$livelihoodData = mysqli_query($conn, "
    SELECT 
        CASE category_id
            WHEN 1 THEN 'Farmer'
            WHEN 2 THEN 'Farm Worker'
            WHEN 3 THEN 'Fisherfolk'
            ELSE 'Other'
        END AS livelihood,
        COUNT(*) AS total
    FROM livelihoods
    GROUP BY category_id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Summary Report</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-5xl mx-auto bg-white p-6 rounded-lg shadow">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            📊 Summary Report
        </h1>

        <table class="min-w-full border border-gray-200 divide-y divide-gray-200 mb-6">
            <tbody class="divide-y divide-gray-100 text-sm">
                <tr>
                    <td class="px-4 py-3 font-medium">Total Registered Farmers</td>
                    <td class="px-4 py-3 text-right"><?= $totalFarmers ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-medium">Total Livelihood Records</td>
                    <td class="px-4 py-3 text-right"><?= $totalLivelihoods ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-medium">Barangays Covered</td>
                    <td class="px-4 py-3 text-right"><?= $totalBarangays ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Gender Chart -->
        <h2 class="text-lg font-semibold mt-6 mb-4 text-gray-700">Gender Distribution</h2>
        <canvas id="genderChart" class="w-full h-64"></canvas>

        <!-- Livelihood Chart -->
        <h2 class="text-lg font-semibold mt-10 mb-4 text-gray-700">Livelihood Distribution</h2>
        <canvas id="livelihoodChart" class="w-full h-64"></canvas>
    </div>

    <script>
        // ✅ Gender Chart Data
        const genderLabels = [
            <?php
            mysqli_data_seek($genderData, 0);
            while ($g = mysqli_fetch_assoc($genderData)) {
                echo "'" . htmlspecialchars($g['gender']) . "',";
            }
            ?>
        ];

        const genderValues = [
            <?php
            mysqli_data_seek($genderData, 0);
            while ($g = mysqli_fetch_assoc($genderData)) {
                echo $g['total'] . ",";
            }
            ?>
        ];

        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: genderLabels,
                datasets: [{
                    data: genderValues,
                    backgroundColor: ['#3B82F6', '#F472B6', '#FBBF24']
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });

        // ✅ Livelihood Chart Data
        const livelihoodLabels = [
            <?php
            mysqli_data_seek($livelihoodData, 0);
            while ($l = mysqli_fetch_assoc($livelihoodData)) {
                echo "'" . htmlspecialchars($l['livelihood']) . "',";
            }
            ?>
        ];

        const livelihoodValues = [
            <?php
            mysqli_data_seek($livelihoodData, 0);
            mysqli_data_seek($livelihoodData, 0);
            mysqli_data_seek($livelihoodData, 0);
            while ($l = mysqli_fetch_assoc($livelihoodData)) {
                echo $l['total'] . ",";
            }
            ?>
        ];

        const livelihoodCtx = document.getElementById('livelihoodChart').getContext('2d');
        new Chart(livelihoodCtx, {
            type: 'bar',
            data: {
                labels: livelihoodLabels,
                datasets: [{
                    label: 'Total Records',
                    data: livelihoodValues,
                    backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444']
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
