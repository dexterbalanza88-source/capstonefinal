<?php
require_once '../db/conn.php';
header('Content-Type: application/json');

// 🧩 Make sure connection exists
if (!isset($mysqli) || $mysqli->connect_error) {
    die(json_encode(["error" => "Database connection failed."]));
}

// 🧩 Helper function for single-row queries
function getRow($mysqli, $query)
{
    $result = $mysqli->query($query);
    if ($result && $row = $result->fetch_assoc())
        return $row;
    return [];
}

$data = [
    'total_farmarea' => 0,
    'total_farmers' => 0,
    'male' => 0,
    'female' => 0,
    'main_livelihood' => [],
    'age_groups' => []
];

// ✅ 1. Total Farmers, Male, Female, Farm Area
$row = getRow($mysqli, "
    SELECT 
        COUNT(*) AS total_farmers,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS female,
        SUM(total_farmarea) AS total_farmarea
    FROM registration_form
");
if ($row) {
    $data['total_farmers'] = (int) $row['total_farmers'];
    $data['male'] = (int) $row['male'];
    $data['female'] = (int) $row['female'];
    $data['total_farmarea'] = round((float) $row['total_farmarea'], 2);
}

// ✅ 2. Main Livelihood Distribution
$result = $mysqli->query("
    SELECT main_livelihood, COUNT(*) AS count
    FROM registration_form
    WHERE main_livelihood IS NOT NULL AND main_livelihood != ''
    GROUP BY main_livelihood
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data['main_livelihood'][$row['main_livelihood']] = (int) $row['count'];
    }
}

// ✅ 3. Age Group Breakdown
$result = $mysqli->query("
    SELECT dob FROM registration_form 
    WHERE dob IS NOT NULL AND dob != ''
");
$today = new DateTime();
$ageGroups = ['20-39' => 0, '40-59' => 0, '60+' => 0];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dob = new DateTime($row['dob']);
        $age = $today->diff($dob)->y;

        if ($age >= 20 && $age <= 39)
            $ageGroups['20-39']++;
        elseif ($age >= 40 && $age <= 59)
            $ageGroups['40-59']++;
        elseif ($age >= 60)
            $ageGroups['60+']++;
    }
}
$data['age_groups'] = $ageGroups;

// ✅ Output JSON
echo json_encode($data);
?>