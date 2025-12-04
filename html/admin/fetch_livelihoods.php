<?php
header('Content-Type: application/json; charset=utf-8');
include '../../db/conn.php';

try {
    if (!isset($_GET['registration_id']) || empty($_GET['registration_id'])) {
        throw new Exception("Missing registration_id parameter.");
    }

    $registration_id = intval($_GET['registration_id']);

    $sql = "
        SELECT 
            c.category_name AS category_name,
            sa.sub_name AS sub_activity_name
        FROM livelihoods l
        LEFT JOIN category_types c ON l.category_id = c.id
        LEFT JOIN sub_activities sa ON l.sub_activity_id = sa.id
        WHERE l.registration_form_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt)
        throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $registration_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $commodities = [
        "Farmer" => [],
        "Farmer Worker" => [],
        "Fisherfolk" => []
    ];

    // Map sub-activities to standardized categories
    $subActivityMap = [
        // Farmer
        "for farmer" => "Farmer",
        "rice" => "Farmer",
        "corn" => "Farmer",
        "livestock" => "Farmer",
        "poultry" => "Farmer",

        // Farmer Worker
        "for farmerworker" => "Farmer Worker",
        "farmer worker" => "Farmer Worker",
        "land preparation" => "Farmer Worker",
        "planting/transplanting" => "Farmer Worker",
        "cultivation" => "Farmer Worker",
        "harvesting" => "Farmer Worker",

        // Fisherfolk
        "fisherfolk" => "Fisherfolk",
        "fish capture" => "Fisherfolk",
        "aquaculture" => "Fisherfolk",
        "gleaning" => "Fisherfolk"
    ];

    while ($row = $result->fetch_assoc()) {
        $sub = strtolower(trim($row['sub_activity_name'] ?? ''));
        $cat = strtolower(trim($row['category_name'] ?? ''));

        $key = $subActivityMap[$sub] ?? null;

        if (!$key) {
            // fallback using category name
            if (strpos($cat, 'farmer worker') !== false)
                $key = "Farmer Worker";
            elseif (strpos($cat, 'fisher') !== false)
                $key = "Fisherfolk";
            else
                $key = "Farmer";
        }

        $commodities[$key][] = $row['sub_activity_name'];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        "success" => true,
        "data" => $commodities
    ]);

} catch (Exception $e) {
    error_log("Fetch Commodities Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>