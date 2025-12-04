<?php
header('Content-Type: application/json');
require '../../db/conn.php';

try {
    $registration_form_id = $_POST['registration_form_id'] ?? null;
    if (empty($registration_form_id) || !is_numeric($registration_form_id)) {
        echo json_encode(['status' => 'error', 'message' => '❌ Missing registration_form_id.']);
        exit;
    }

    $category_types = $_POST['category_types'] ?? null;
    if (empty($category_types) || !is_array($category_types)) {
        echo json_encode(['status' => 'error', 'message' => '⚠️ No livelihood selected.']);
        exit;
    }

    // prepare statements for check, insert and update
    $stmtCheck = $conn->prepare("
        SELECT id FROM livelihoods 
        WHERE registration_form_id = ? AND category_id = ? AND sub_activity_id = ?
    ");

    $stmtInsert = $conn->prepare("
        INSERT INTO livelihoods 
        (registration_form_id, category_id, sub_activity_id, remarks, total_area, farm_location, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmtUpdate = $conn->prepare("
        UPDATE livelihoods 
        SET remarks = ?, total_area = ?, farm_location = ?
        WHERE registration_form_id = ? AND category_id = ? AND sub_activity_id = ?
    ");

    $insertCount = 0;
    $updateCount = 0;
    $incompleteList = [];

    foreach ($category_types as $catId) {
        $catId = intval($catId);
        $subActivityKey = "sub_activity_id_{$catId}";
        $subActivity = $_POST[$subActivityKey] ?? '';
        $remarks = '';
        $total_area = '';
        $farm_location = '';
        $label = '';

        switch ($catId) {
            case 1: // Farmer
                $remarks = $_POST['farmer_othercrop'] ?? '';
                $total_area = $_POST['farmer_area'] ?? '';
                $farm_location = $_POST['farmer_location'] ?? '';
                $label = 'Farmer';
                break;
            case 2: // Farmer Worker
                $remarks = $_POST['farmerworker_other'] ?? '';
                $total_area = $_POST['farmerworker_area'] ?? '';
                $farm_location = $_POST['farmerworker_location'] ?? '';
                $label = 'Farmer Worker';
                break;
            case 3: // Fisherfolk
                $remarks = $_POST['fisherfolk_other'] ?? '';
                // No area requirement for Fisherfolk
                $total_area = '0'; // Default value or empty
                $farm_location = $_POST['fisherfolk_location'] ?? '';
                $label = 'Fisherfolk';
                break;
            default:
                // unknown category — skip
                continue 2;
        }

        // ✅ Common validation for all categories
        if (empty($subActivity) || $subActivity === '' || empty($farm_location)) {
            $incompleteList[] = $label . " (missing required fields)";
            continue;
        }

        // ✅ Category-specific validation
        if ($catId == 1 || $catId == 2) {
            // Farmer and Farmer Worker require area
            if (empty($total_area) || $total_area === '') {
                $incompleteList[] = $label . " (missing area)";
                continue;
            }
        }

        // Fisherfolk doesn't require area, so no check needed

        // ✅ For "Other" type sub activities, remarks are required
        if (in_array((string) $subActivity, ['21', '10', '16']) && empty($remarks)) {
            $incompleteList[] = $label . " (missing 'Other' details)";
            continue;
        }

        // ✅ For Fisherfolk, if total_area is empty string, set to 0
        if ($catId == 3 && ($total_area === '' || empty($total_area))) {
            $total_area = '0';
        }

        // ✅ Convert area to decimal if needed
        if ($total_area !== '' && $total_area !== '0') {
            $total_area = floatval($total_area);
        }

        // ✅ Check if record already exists
        $stmtCheck->bind_param("iii", $registration_form_id, $catId, $subActivity);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        $existing = $result ? $result->fetch_assoc() : null;

        if ($existing) {
            // 🔄 UPDATE existing livelihood record
            $stmtUpdate->bind_param("sssiii", $remarks, $total_area, $farm_location, $registration_form_id, $catId, $subActivity);
            if ($stmtUpdate->execute()) {
                $updateCount++;
            }
        } else {
            // ➕ INSERT new livelihood record
            $stmtInsert->bind_param("iiisss", $registration_form_id, $catId, $subActivity, $remarks, $total_area, $farm_location);
            if ($stmtInsert->execute()) {
                $insertCount++;
            }
        }
    }

    $msg = [];
    if ($insertCount > 0) {
        $msg[] = "✅ Added {$insertCount} record(s).";
    }
    if ($updateCount > 0) {
        $msg[] = "📝 Updated {$updateCount} record(s).";
    }
    if (!empty($incompleteList)) {
        $msg[] = "ℹ️ Skipped incomplete entries: " . implode(', ', $incompleteList) . ".";
    }
    if (empty($msg)) {
        $msg[] = "⚠️ No valid entries to save.";
    }

    echo json_encode([
        'status' => ($insertCount > 0 || $updateCount > 0) ? 'success' : 'warning',
        'message' => implode(' ', $msg),
        'inserted' => $insertCount,
        'updated' => $updateCount,
        'incomplete' => $incompleteList
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => '❌ Server error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmtInsert)) {
        $stmtInsert->close();
    }
    if (isset($stmtUpdate)) {
        $stmtUpdate->close();
    }
    if (isset($stmtCheck)) {
        $stmtCheck->close();
    }
    if (isset($result) && $result instanceof mysqli_result) {
        $result->free();
    }
    $conn->close();
}
?>