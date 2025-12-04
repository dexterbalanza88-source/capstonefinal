<?php
require '../../db/conn.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db)
    die("❌ Database connection not found.");

// ----------------------------
// GET FILTERS
// ----------------------------
$years = $_GET['years'] ?? [];
$months = $_GET['months'] ?? [];
$brgys = $_GET['brgys'] ?? [];

$where = "";

// YEAR FILTER
if (!empty($years)) {
    $safeYears = array_map(fn($y) => "'" . $db->real_escape_string($y) . "'", $years);
    $where .= " AND YEAR(r.created_at) IN (" . implode(",", $safeYears) . ")";
}

// MONTH FILTER
if (!empty($months)) {
    $safeMonths = array_map(fn($m) => "'" . $db->real_escape_string($m) . "'", $months);
    $where .= " AND MONTHNAME(r.created_at) IN (" . implode(",", $safeMonths) . ")";
}

// BARANGAY FILTER
if (!empty($brgys)) {
    $cleanBrgys = array_map(function ($b) use ($db) {
        return "'" . strtolower(str_replace(' ', '', $db->real_escape_string($b))) . "'";
    }, $brgys);
    $where .= " AND LOWER(REPLACE(r.brgy,' ','')) IN (" . implode(",", $cleanBrgys) . ")";
}

// ----------------------------
// QUERY A — TOTAL LIVELIHOOD PER CATEGORY
// ----------------------------
$query_livelihood_summary = "
    SELECT 
        ct.category_name AS livelihood_type,
        COUNT(*) AS total
    FROM livelihoods fl
    JOIN category_types ct ON fl.category_id = ct.id
    JOIN registration_form r ON fl.registration_form_id = r.id
    WHERE 1=1
    $where
    GROUP BY ct.category_name
    ORDER BY ct.category_name ASC
";

$result_summary = $db->query($query_livelihood_summary);

// ----------------------------
// QUERY B — FULL DETAILED TABLE
// ----------------------------
$query_full = "
    SELECT 
        CASE 
            WHEN LOWER(s.sub_name) = 'others'
                 AND (fl.remarks IS NOT NULL AND fl.remarks != '')
            THEN CONCAT('Others - ', TRIM(fl.remarks))
            ELSE s.sub_name
        END AS livelihood,

        ct.category_name AS type_group,

        r.s_name,
        r.f_name,
        r.m_name,
        r.brgy,
        r.municipal,
        r.contact_num,
        r.created_at
    FROM livelihoods fl
    JOIN registration_form r ON fl.registration_form_id = r.id
    JOIN sub_activities s ON fl.sub_activity_id = s.id
    JOIN category_types ct ON fl.category_id = ct.id
    WHERE 1=1
    $where
    ORDER BY type_group ASC, livelihood ASC, r.s_name ASC
";

$result_full = $db->query($query_full);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Livelihood Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial;
            padding: 20px;
        }

        h2 {
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            font-size: 14px;
        }

        th {
            background: #f0f0f0;
        }

        .graph-container {
            width: 60%;
            margin: 0 auto;
        }
    </style>
</head>

<body>

    <h2>Livelihood Summary Report</h2>

    <div class="graph-container">
        <canvas id="barChart"></canvas>
    </div>

    <script>
        const labels = [
            <?php
            if ($result_summary) {
                while ($row = $result_summary->fetch_assoc()) {
                    echo "'" . $row['livelihood_type'] . "',";
                }
            }
            ?>
        ];

        const data = [
            <?php
            // Re-run because we consumed result above
            $result_summary2 = $db->query($query_livelihood_summary);
            if ($result_summary2) {
                while ($row = $result_summary2->fetch_assoc()) {
                    echo $row['total'] . ",";
                }
            }
            ?>
        ];

        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: "Total Livelihoods",
                    data: data
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

    <h2>Detailed Livelihood List</h2>

    <table>
        <thead>
            <tr>
                <th>Livelihood</th>
                <th>Type (Farmer / Farmworker / Fisherfolk)</th>
                <th>Name</th>
                <th>Barangay</th>
                <th>Municipal</th>
                <th>Contact</th>
                <th>Date Registered</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result_full && $result_full->num_rows > 0) {
                while ($row = $result_full->fetch_assoc()) {
                    $fullname = $row['s_name'] . ", " . $row['f_name'] . " " . $row['m_name'];
                    echo "
                    <tr>
                        <td>{$row['livelihood']}</td>
                        <td>{$row['type_group']}</td>
                        <td>$fullname</td>
                        <td>{$row['brgy']}</td>
                        <td>{$row['municipal']}</td>
                        <td>{$row['contact_num']}</td>
                        <td>{$row['created_at']}</td>
                    </tr>
                ";
                }
            } else {
                echo "<tr><td colspan='7' style='text-align:center;'>No Records Found</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>

</html>