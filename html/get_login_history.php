<?php
if (basename($_SERVER['PHP_SELF']) == 'get_login_history.php') {
    include '../config.php';
    header('Content-Type: application/json');

    $query = "SELECT lh.id, u.full_name, lh.email, lh.login_time, lh.logout_time, lh.status 
              FROM login_history lh 
              LEFT JOIN users u ON lh.user_id = u.id 
              ORDER BY lh.login_time DESC";

    $result = $conn->query($query);

    if (!$result) {
        echo json_encode(["error" => $conn->error]);
        exit;
    }

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    echo json_encode($history);
    exit;
}
?>