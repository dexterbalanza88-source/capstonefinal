<?php
include '../../db/conn.php';
header('Content-Type: application/json');

// Decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['id'])) {
    echo json_encode(["success" => false, "message" => "Missing farmer ID"]);
    exit;
}

$id = $data['id'];
$section = isset($data['section']) ? $data['section'] : 'personal'; // default to personal

// 🔹 PERSONAL INFO UPDATE
if ($section === 'personal') {
    $sql = "UPDATE registration_form SET 
        date = ?, reference = ?, s_name = ?, f_name = ?, m_name = ?, e_name = ?, gender = ?, 
        house = ?, sitio = ?, brgy = ?, municipal = ?, province = ?, region = ?
        WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssssi",
        $data['date'],
        $data['reference'],
        $data['s_name'],
        $data['f_name'],
        $data['m_name'],
        $data['e_name'],
        $data['gender'],
        $data['house'],
        $data['sitio'],
        $data['brgy'],
        $data['municipal'],
        $data['province'],
        $data['region'],
        $id
    );
}

// 🔹 FAMILY INFO UPDATE
elseif ($section === 'family') {
    $sql = "UPDATE registration_form SET 
        mobile = ?, landline = ?, dob = ?, mother_maiden = ?, 
        no_livinghousehold = ?, no_male = ?, no_female = ?
        WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssi",
        $data['mobile'],
        $data['landline'],
        $data['dob'],
        $data['mother_maiden'],
        $data['no_livinghousehold'],
        $data['no_male'],
        $data['no_female'],
        $id
    );
}

// 🔹 EDUCATION & ID UPDATE
elseif ($section === 'education') {
    $sql = "UPDATE registration_form SET 
        education = ?, pwd = ?, for_ps = ?, with_gov = ?, specify_id = ?, id_no = ?
        WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssi",
        $data['education'],
        $data['pwd'],
        $data['for_ps'],
        $data['with_gov'],
        $data['specify_id'],
        $data['id_no'],
        $id
    );
}

// 🔹 FARM INFO UPDATE
elseif ($data['section'] === 'farm') {
    $sql = "UPDATE registration_form SET 
        farm_barangay = ?, 
        farmer_location_municipal = ?, 
        farm_size = ?, 
        farmer_location_ownership = ?, 
        farm_parcel = ?
        WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssdsii",
        $data['farm_barangay'],
        $data['farm_location_municipal'],
        $data['farm_size'],
        $data['farm_ownership'],
        $data['farm_parcel'],
        $id
    );
}

// 🔹 INVALID SECTION
else {
    echo json_encode(["success" => false, "message" => "Invalid section"]);
    exit;
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "✅ Updated successfully!"]);
} else {
    echo json_encode(["success" => false, "message" => "❌ Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>