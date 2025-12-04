<?php
include "../../db/conn.php";
header('Content-Type: application/json');

// SQL query to fetch latest 50 logs
$sql = "SELECT id, name AS user, action, created_at 
        FROM activity_log 
        ORDER BY created_at DESC";

$result = mysqli_query($conn, $sql);

// Error handling
if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Database query failed: " . mysqli_error($conn)
    ]);
    exit;
}

// Fetch data
$logs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = $row;
}

// Handle no records
if (empty($logs)) {
    echo json_encode([
        "success" => true,
        "data" => [],
        "message" => "No activity logs found."
    ]);
    exit;
}

// Return success response
echo json_encode([
    "success" => true,
    "data" => $logs
]);

mysqli_close($conn);
?>