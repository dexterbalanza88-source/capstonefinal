<?php
header('Content-Type: application/json');
require '../../db/conn.php';

try {
    $id = $_POST['id'] ?? null;

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => '❌ Missing record ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM livelihoods WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => '✅ Record deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '❌ Failed to delete record.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '❌ Server error: ' . $e->getMessage()]);
} finally {
    if (isset($stmt))
        $stmt->close();
    $conn->close();
}
?>