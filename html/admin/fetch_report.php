<?php
require_once '../../db/conn.php';

$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';
$brgy = $_GET['brgy'] ?? '';
$limit = $_GET['limit'] ?? 50;
$offset = $_GET['offset'] ?? 0;

// Convert comma-separated filters into arrays
$yearArr = !empty($year) ? explode(',', $year) : [];
$monthArr = !empty($month) ? explode(',', $month) : [];
$brgyArr = !empty($brgy) ? explode(',', $brgy) : [];

// Base query using JOIN to fetch livelihoods
$query = "
    SELECT 
        f.id, 
        f.f_name, 
        f.m_name, 
        f.s_name, 
        f.brgy, 
        f.contact_num AS mobile, 
        f.dob, 
        f.gender, 
        f.total_farmarea, 
        f.status,
        GROUP_CONCAT(DISTINCT COALESCE(sa.sub_name, '') SEPARATOR ', ') AS livelihoodsList
    FROM registration_form f
    LEFT JOIN livelihoods l ON f.id = l.registration_form_id
    LEFT JOIN sub_activities sa ON l.sub_activity_id = sa.id
    WHERE f.status != 'Archived'
";

$params = [];
$types = '';

// 🔍 Search filter
if (!empty($search)) {
    $query .= " AND (f.f_name LIKE ? OR f.s_name LIKE ? OR f.brgy LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

// 📅 Year filter (multiple)
if (!empty($yearArr)) {
    $placeholders = implode(',', array_fill(0, count($yearArr), '?'));
    $query .= " AND YEAR(f.created_at) IN ($placeholders)";
    $params = array_merge($params, $yearArr);
    $types .= str_repeat('s', count($yearArr));
}

// 🗓️ Month filter (multiple)
if (!empty($monthArr)) {
    $monthNums = array_map(function ($m) {
        return is_numeric($m) ? $m : date('n', strtotime($m));
    }, $monthArr);
    $placeholders = implode(',', array_fill(0, count($monthNums), '?'));
    $query .= " AND MONTH(f.created_at) IN ($placeholders)";
    $params = array_merge($params, $monthNums);
    $types .= str_repeat('s', count($monthNums));
}

// 🏘️ Barangay filter (multiple)
if (!empty($brgyArr)) {
    // Use LOWER for case-insensitive match
    $placeholders = implode(',', array_fill(0, count($brgyArr), 'LOWER(TRIM(?))'));
    $query .= " AND LOWER(TRIM(f.brgy)) IN ($placeholders)";
    $params = array_merge($params, $brgyArr);
    $types .= str_repeat('s', count($brgyArr));
}

// Group, order, and pagination
$query .= " GROUP BY f.id ORDER BY f.id DESC LIMIT ? OFFSET ?";
$params[] = (int) $limit;
$params[] = (int) $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

// Bind params dynamically
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $count = $offset + 1;
    while ($row = $result->fetch_assoc()) {
        // Compute age
        $age = '';
        if (!empty($row['dob'])) {
            $dobObj = new DateTime($row['dob']);
            $today = new DateTime();
            $age = $today->diff($dobObj)->y;
        }

        $full_name = htmlspecialchars(trim($row['f_name'] . ' ' . $row['m_name'] . ' ' . $row['s_name']));
        $address = htmlspecialchars($row['brgy'] ?? '—');
        $contact = htmlspecialchars($row['mobile'] ?? '—');
        $dob = htmlspecialchars($row['dob'] ?? '—');
        $gender = htmlspecialchars($row['gender'] ?? '—');
        $farm_size = htmlspecialchars($row['total_farmarea'] ?? '—');
        $status = htmlspecialchars($row['status'] ?? 'Pending');
        $livelihoods = htmlspecialchars($row['livelihoodsList'] ?: '—');

        echo "
        <tr class='border-b hover:bg-gray-50'>
            <td class='px-3 py-2 text-center'>{$count}</td>
            <td class='px-3 py-2'>{$full_name}</td>
            <td class='px-3 py-2'>{$address}</td>
            <td class='px-3 py-2'>{$contact}</td>
            <td class='px-3 py-2 text-center'>{$age}</td>
            <td class='px-3 py-2'>{$dob}</td>
            <td class='px-3 py-2'>{$gender}</td>
            <td class='px-3 py-2 text-center'>{$farm_size}</td>
            <td class='px-3 py-2'>{$livelihoods}</td>
            <td class='px-3 py-2 text-yellow-500 font-medium'>{$status}</td>
            <td class='px-3 py-2 text-center'>
                <button class='text-blue-600 hover:underline'>View</button>
            </td>
        </tr>";
        $count++;
    }
} else {
    echo "<tr><td colspan='11' class='text-center py-3'>No records found</td></tr>";
}
?>