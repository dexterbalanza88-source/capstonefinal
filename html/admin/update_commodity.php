<?php
header('Content-Type: application/json');
require '../../db/conn.php';

try {
    // Required fields
    $registration_form_id = $_POST['registration_form_id'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $livelihood = $_POST['livelihood'] ?? '';
    $commodity = $_POST['commodity'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $farm_location = $_POST['farm_location'] ?? '';
    $livelihood_id = $_POST['livelihood_id'] ?? null;

    // Validation
    if (empty($registration_form_id) || empty($category_id)) {
        echo json_encode(['status' => 'error', 'message' => '❌ Missing registration_form_id or category_id.']);
        exit;
    }

    if (empty($livelihood_id)) {
        echo json_encode(['status' => 'error', 'message' => '❌ Missing livelihood_id.']);
        exit;
    }

    // Handle total area based on category
    // Fisherfolk (category_id = 3) doesn't require area
    if ($category_id == 3) {
        // For Fisherfolk, set total_area to NULL or 0
        $total_area = NULL; // or '0' for zero value
    } else {
        // For Farmer (1) and Farmer Worker (2), get area from POST
        $total_area = $_POST['total_area'] ?? '';

        // Validate area for non-Fisherfolk categories
        if (empty($total_area)) {
            echo json_encode(['status' => 'error', 'message' => '❌ Total area is required for this livelihood type.']);
            exit;
        }
    }

    // Validate farm_location
    if (empty($farm_location)) {
        echo json_encode(['status' => 'error', 'message' => '❌ Farm location is required.']);
        exit;
    }

    // Update statement - only updating fields that can be changed
    $stmt = $conn->prepare("
        UPDATE livelihoods
        SET remarks = ?, total_area = ?, farm_location = ?
        WHERE id = ? AND registration_form_id = ?
    ");

    // If using NULL for Fisherfolk, need to handle binding differently
    if ($category_id == 3 && $total_area === NULL) {
        // For Fisherfolk with NULL area
        $stmt->bind_param("sssii", $remarks, $total_area, $farm_location, $livelihood_id, $registration_form_id);
    } else {
        // For Farmer/Farmer Worker with string area
        $stmt->bind_param("sssii", $remarks, $total_area, $farm_location, $livelihood_id, $registration_form_id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => '✅ Livelihood record updated successfully.',
            'category_id' => $category_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '❌ Failed to update record: ' . $conn->error]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '❌ Server error: ' . $e->getMessage()]);
} finally {
    if (isset($stmt))
        $stmt->close();
    $conn->close();
}
?>