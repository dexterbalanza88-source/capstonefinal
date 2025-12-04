<?php
header('Content-Type: application/json; charset=utf-8');
include '../../db/conn.php';

// ✅ Enable backend error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// ✅ Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON data"]);
    exit;
}

// ✅ Initialize counters
$inserted = 0;
$duplicates = 0;
$errors = [];

foreach ($data as $record) {

    // 🔹 All fields in your RSBSA form (add/remove as needed)
    $fields = [
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

    // ✅ Collect safe values
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = isset($record[$field]) ? trim((string) $record[$field]) : null;
    }

    // ✅ IMPORTANT: Add status field with value 'pending'
    $values['status'] = 'pending';

    // ✅ Handle checkbox arrays
    if (!empty($record['main_livelihood']) && is_array($record['main_livelihood'])) {
        $values['main_livelihood'] = implode(", ", $record['main_livelihood']);
    } elseif (isset($record['main_livelihood'])) {
        $values['main_livelihood'] = $record['main_livelihood'];
    } else {
        $values['main_livelihood'] = '';
    }

    // ✅ Set default values for required fields that might be missing from enumerator data
    if (empty($values['country'])) {
        $values['country'] = 'Philippines';
    }

    // Use mobile as contact_num if contact_num is empty
    if (empty($values['contact_num']) && !empty($values['mobile'])) {
        $values['contact_num'] = $values['mobile'];
    }

    // ✅ Validate and format dates
    if (!empty($values['dob']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['dob'])) {
        $values['dob'] = null; // Set to null if invalid date format
    }

    if (empty($values['date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['date'])) {
        $values['date'] = date('Y-m-d'); // Default to today if missing or invalid
    }

    // ✅ Prevent duplicates (based on s_name + f_name + brgy)
    $check = $conn->prepare("
        SELECT COUNT(*) 
        FROM registration_form 
        WHERE s_name=? AND f_name=? AND brgy=?
    ");
    $check->bind_param("sss", $values['s_name'], $values['f_name'], $values['brgy']);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        $duplicates++;
        $errors[] = "Duplicate skipped: {$values['s_name']} {$values['f_name']} ({$values['brgy']})";
        continue;
    }

    // ✅ Insert farmer record
    $columns = implode(", ", array_keys($values));
    $placeholders = implode(", ", array_fill(0, count($values), "?"));
    $sql = "INSERT INTO registration_form ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $errors[] = "SQL Prepare Error: " . $conn->error;
        continue;
    }

    $types = str_repeat('s', count($values));
    $params = array_values($values);

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $inserted++;
        $registration_form_id = $conn->insert_id;
    } else {
        $errors[] = "Insert Error: " . $stmt->error;
    }
    $stmt->close();

    // ✅ Optional: Insert into livelihoods if data exists
    if (!empty($record['main_livelihood']) && is_array($record['main_livelihood'])) {
        $insertLivelihood = $conn->prepare("
            INSERT INTO livelihoods 
            (registration_form_id, category_id, sub_activity_id, remarks, total_area, farm_location, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $categoryMap = [
            'farmer' => 1,
            'farmerworker' => 2,
            'fisherfolk' => 3
        ];

        foreach ($record['main_livelihood'] as $livelihood) {
            if (!isset($categoryMap[$livelihood]))
                continue;

            $category_id = $categoryMap[$livelihood];
            $subIds = $record["sub_activity_id_{$category_id}"] ?? [];
            if (!is_array($subIds))
                $subIds = [$subIds];

            $remarks = $record["remarks_{$category_id}"] ?? '';
            $total_area = $record["total_area_{$category_id}"] ?? '';
            $farm_location = $record["farm_location_{$category_id}"] ?? '';

            foreach ($subIds as $subId) {
                $subId = (int) $subId;
                if ($subId <= 0)
                    continue;

                $insertLivelihood->bind_param(
                    "iiisss",
                    $registration_form_id,
                    $category_id,
                    $subId,
                    $remarks,
                    $total_area,
                    $farm_location
                );
                $insertLivelihood->execute();
            }
        }
        $insertLivelihood->close();
    }
}

// ✅ Close connection
$conn->close();

// ✅ Send JSON response
echo json_encode([
    "status" => "success",
    "inserted" => $inserted,
    "duplicates" => $duplicates,
    "message" => "$inserted farmer record(s) uploaded successfully with status='pending'.",
    "errors" => $errors
], JSON_PRETTY_PRINT);
?>