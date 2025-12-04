<?php
require_once '../../db/conn.php';
header('Content-Type: application/json');

// ✅ Validate the ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No farmer ID provided.']);
    exit;
}

$id = intval($_GET['id']);

// ✅ Fetch the farmer data
$stmt = $conn->prepare("
    SELECT 
        CONCAT(UCASE(f_name), ' ', LEFT(UCASE(m_name), 1), '. ', UCASE(s_name)) AS full_name,
        reference
    FROM registration_form 
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Farmer not found.']);
    exit;
}

$farmer = $result->fetch_assoc();

// ✅ Return the data in JSON format
echo json_encode([
    'status' => 'success',
    'full_name' => $farmer['full_name'],
    'reference' => $farmer['reference'] ?? '17-51-01-009-00004'
]);
?>
