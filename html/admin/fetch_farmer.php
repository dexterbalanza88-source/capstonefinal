<?php
header('Content-Type: application/json; charset=utf-8');
include '../../db/conn.php';

try {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Only fetch farmer info (no commodities)
    $sql = "
        SELECT 
            rf.id, rf.s_name, rf.f_name, rf.m_name, rf.brgy, rf.mobile, rf.dob, rf.gender,
            rf.total_farmarea, rf.status
        FROM registration_form rf
        WHERE rf.status != 'Archived'
        ORDER BY rf.id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("ii", $limit, $offset);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $result = $stmt->get_result();
    if (!$result) throw new Exception("Get result failed: " . $stmt->error);

    $farmers = [];
    while ($row = $result->fetch_assoc()) {
        $farmers[] = array_map(function($v) { return $v ?? ""; }, $row);
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'data' => $farmers]);

} catch (Exception $e) {
    error_log("Fetch Farmers Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Database error: " . $e->getMessage()
    ]);
    exit;
}
?>
