<?php
include "../../db/conn.php";

if (!isset($_GET['format'])) {
    exit('No export format selected.');
}

$format = $_GET['format'];

// Define columns (without table alias prefixes)
$columns = [
    'date',
    'reference',
    's_name',
    'f_name',
    'm_name',
    'e_name',
    'gender',
    'house',
    'sitio',
    'brgy',
    'municipal',
    'province',
    'region',
    'mobile',
    'landline',
    'dob',
    'country',
    'province_birth',
    'municipality_birth',
    'mother_maiden',
    'household_head',
    'if_nohousehold',
    'relationship',
    'no_livinghousehold',
    'no_male',
    'no_female',
    'education',
    'pwd',
    'non_farming',
    'farming',
    'for_ps',
    'with_gov',
    'specify_id',
    'id_no',
    'member_indig',
    'assoc_member',
    'specify_assoc',
    'contact_num',
    'main_livelihood',
    'no_farmparcel',
    'total_farmarea',
    'with_ancestral',
    'ownership',
    'agrarian',
    'farmer_location_brgy',
    'farmer_location_municipal',
    'farmer_location_ownership',
    'photo'
];

// Query (keep alias `r.` only in SQL)
$sql = "
    SELECT 
        " . implode(', ', array_map(fn($col) => "r.$col", $columns)) . ",
        GROUP_CONCAT(DISTINCT 
            CASE l.category_id
                WHEN 1 THEN 'Farmer'
                WHEN 2 THEN 'Farmer Worker'
                WHEN 3 THEN 'Fisherfolk'
                ELSE 'Other'
            END SEPARATOR ', '
        ) AS livelihood_types,
        GROUP_CONCAT(DISTINCT 
            CASE l.sub_activity_id
                WHEN 1 THEN 'Rice'
                WHEN 2 THEN 'Corn'
                WHEN 3 THEN 'Vegetable'
                WHEN 4 THEN 'Poultry'
                WHEN 5 THEN 'Livestock'
                WHEN 6 THEN 'Land Preparation'
                WHEN 7 THEN 'Planting'
                WHEN 8 THEN 'Cultivation'
                WHEN 9 THEN 'Harvesting'
                WHEN 10 THEN 'Fish Capture'
                WHEN 11 THEN 'Aquaculture'
                WHEN 12 THEN 'Gleaning'
                WHEN 13 THEN 'Fish Processing'
                WHEN 14 THEN 'Fish Vending'
                ELSE 'N/A'
            END SEPARATOR ', '
        ) AS commodities
    FROM registration_form r
    LEFT JOIN livelihoods l ON l.registration_form_id = r.id
    GROUP BY r.id
";

$result = mysqli_query($conn, $sql);
if (!$result) {
    exit('Database error: ' . mysqli_error($conn));
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // ✅ Convert binary flags to Yes/No
    $flags = ['pwd', 'non_farming', 'farming', 'for_ps', 'with_gov', 'member_indig', 'assoc_member'];
    foreach ($flags as $col) {
        if (isset($row[$col])) {
            $row[$col] = ($row[$col] == 1 || strtolower($row[$col]) == 'yes') ? 'Yes' : 'No';
        }
    }

    // ✅ Fix and format date fields
    foreach (['date', 'dob'] as $dateCol) {
        if (!empty($row[$dateCol]) && $row[$dateCol] != '0000-00-00') {
            $timestamp = strtotime($row[$dateCol]);
            if ($timestamp && $timestamp > 0) {
                $row[$dateCol] = date('Y-m-d', $timestamp); // keep ISO format
            } else {
                $row[$dateCol] = 'N/A';
            }
        } else {
            $row[$dateCol] = 'N/A';
        }
    }

    // ✅ Replace null/empty values with 'N/A'
    foreach ($row as $key => $value) {
        if ($value === null || $value === '') {
            $row[$key] = 'N/A';
        }
    }

    $data[] = $row;
}

$columns[] = 'livelihood_types';
$columns[] = 'commodities';

// EXPORT HANDLER
switch ($format) {
    case 'csv':
    case 'excel':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="registration_export.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $columns);

        foreach ($data as $row) {
            // ✅ Fix phone numbers – treat as text so Excel won’t convert them
            foreach (['mobile', 'landline', 'contact_num'] as $numCol) {
                if (isset($row[$numCol]) && $row[$numCol] !== 'N/A') {
                    $row[$numCol] = "'" . $row[$numCol]; // prepend apostrophe to force text
                }
            }

            // ✅ Protect date cells from Excel auto-formatting or invalid display
            foreach (['date', 'dob'] as $dateCol) {
                if ($row[$dateCol] !== 'N/A') {
                    $row[$dateCol] = "'" . $row[$dateCol]; // force Excel to treat as text
                }
            }

            fputcsv($output, $row);
        }

        fclose($output);
        break;

    case 'json':
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="registration_export.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        break;

    case 'pdf':
        require('../fpdf/fpdf.php');
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 6);
        $colWidth = 28;

        foreach ($columns as $col) {
            $pdf->Cell($colWidth, 5, $col, 1);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 5);

        foreach ($data as $row) {
            foreach ($columns as $col) {
                $pdf->Cell($colWidth, 5, substr($row[$col], 0, 30), 1);
            }
            $pdf->Ln();
        }

        $pdf->Output('D', 'registration_export.pdf');
        break;

    default:
        exit('Invalid export format selected.');
}
?>