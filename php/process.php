<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../db/conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ Define all registration_form fields
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

    // ✅ Collect data safely
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = $_POST[$field] ?? null;
    }

    // ✅ FIX 1: Handle checkbox array for main livelihood
    if (!empty($_POST['main_livelihood']) && is_array($_POST['main_livelihood'])) {
        $values['main_livelihood'] = implode(", ", $_POST['main_livelihood']);
    } elseif (isset($_POST['main_livelihood']) && !is_array($_POST['main_livelihood'])) {
        $values['main_livelihood'] = $_POST['main_livelihood'];
    } else {
        $values['main_livelihood'] = '';
    }

    // ✅ FIX 2: Convert contact number to string (Excel-safe)
    if (!empty($values['contact_num'])) {
        $values['contact_num'] = (string) $values['contact_num'];
    }

    // ✅ FIX 3: Validate date fields (avoid "out of range" warning)
    // Convert to valid Y-m-d or NULL if invalid/empty
    $values['dob'] = (!empty($values['dob']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['dob']))
        ? $values['dob']
        : null;

    $values['date'] = (!empty($values['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['date']))
        ? $values['date']
        : date('Y-m-d'); // default to today if missing

    // ✅ FIX 4: Add default status = 'pending'
    $values['status'] = 'pending';

    // ✅ Check duplicate name
    $check = $conn->prepare("SELECT COUNT(*) FROM registration_form WHERE s_name=? AND f_name=? AND m_name=?");
    $check->bind_param("sss", $values['s_name'], $values['f_name'], $values['m_name']);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        echo "<script>alert('❌ Duplicate entry: This name already exists.');history.back();</script>";
        exit;
    }

    // ✅ Insert into registration_form
    $columns = implode(", ", array_keys($values));
    $placeholders = implode(", ", array_fill(0, count($values), "?"));
    $sql = "INSERT INTO registration_form ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("<h3>❌ SQL Prepare Error: {$conn->error}</h3>");
    }

    $types = str_repeat('s', count($values));
    $params = array_values($values);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        die("<h3>❌ Insert Error: {$stmt->error}</h3>");
    }

    $registration_form_id = $conn->insert_id;
    $stmt->close();

    // ✅ Insert livelihoods dynamically
    if (!empty($_POST['main_livelihood']) && is_array($_POST['main_livelihood'])) {

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

        foreach ($_POST['main_livelihood'] as $livelihood) {
            if (!isset($categoryMap[$livelihood]))
                continue;

            $category_id = $categoryMap[$livelihood];
            $subKey = "sub_activity_id_{$category_id}";
            $subIds = $_POST[$subKey] ?? [];

            if (!is_array($subIds))
                $subIds = [$subIds];

            $remarks = $_POST["remarks_{$category_id}"] ?? '';
            $total_area = $_POST["total_area_{$category_id}"] ?? ($_POST["farmer_area"] ?? '');
            $farm_location = $_POST["farm_location_{$category_id}"] ?? ($_POST["farmer_location"] ?? '');

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

    echo "<script>alert('✅ Farmer added successfully!');window.location='../../capstonefinal/html/admin/adddata.php';</script>";
    $conn->close();

} else {
    echo "Form not submitted correctly.";
}
?>