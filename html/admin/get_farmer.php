<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db/conn.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing farmer ID']);
    exit;
}

$farmer_id = intval($_GET['id']);

try {
    // 🔹 1. Fetch main farmer info
    $sql = "SELECT * FROM registration_form WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $farmer = $stmt->get_result()->fetch_assoc();

    if (!$farmer) {
        echo json_encode(['status' => 'error', 'message' => 'Farmer not found']);
        exit;
    }

    // 🔹 2. Fetch livelihood info from commodity_type (direct type link)
    $livelihoodSql = "
        SELECT 
            c.category_type_id,
            c.sub_activity_id,
            s.sub_activity_name,
            c.other_text
        FROM commodity_type c
        LEFT JOIN sub_activity s ON s.id = c.sub_activity_id
        WHERE c.registration_form_id = ?
    ";
    $stmt2 = $conn->prepare($livelihoodSql);
    $stmt2->bind_param("i", $farmer_id);
    $stmt2->execute();
    $livelihoodResult = $stmt2->get_result();

    $livelihoods = [
        "Farmer" => [],
        "Farmer Worker" => [],
        "Fisherfolk" => []
    ];

    while ($row = $livelihoodResult->fetch_assoc()) {
        $value = $row['other_text'] ?: $row['sub_activity_name'];
        switch ($row['category_type_id']) {
            case 1:
                $livelihoods["Farmer"][] = $value;
                break;
            case 2:
                $livelihoods["Farmer Worker"][] = $value;
                break;
            case 3:
                $livelihoods["Fisherfolk"][] = $value;
                break;
        }
    }

    // 🔹 3. Fetch livelihood mapping from livelihoods table (if available)
    $sqlLivelihoods = "
        SELECT 
            c.category_name AS category_name,
            sa.sub_name AS sub_activity_name
        FROM livelihoods l
        LEFT JOIN category_types c ON l.category_id = c.id
        LEFT JOIN sub_activities sa ON l.sub_activity_id = sa.id
        WHERE l.registration_form_id = ?
    ";
    $stmt3 = $conn->prepare($sqlLivelihoods);
    $stmt3->bind_param("i", $farmer_id);
    $stmt3->execute();
    $result2 = $stmt3->get_result();

    // Merge with existing array
    while ($row = $result2->fetch_assoc()) {
        $cat = strtolower(trim($row['category_name'] ?? ''));
        $sub = $row['sub_activity_name'];

        if (strpos($cat, 'farmer worker') !== false) {
            $livelihoods["Farmer Worker"][] = $sub;
        } elseif (strpos($cat, 'fisher') !== false) {
            $livelihoods["Fisherfolk"][] = $sub;
        } else {
            $livelihoods["Farmer"][] = $sub;
        }
    }

    // 🔹 4. Remove duplicates and empty items
    foreach ($livelihoods as $key => $list) {
        $clean = array_filter(array_unique($list));
        $livelihoods[$key] = array_values($clean);
    }

    // 🔹 5. Merge everything into final JSON
    $response = [
        'status' => 'success',
        'data' => [
            'farmer' => $farmer,
            'livelihoods' => $livelihoods
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>