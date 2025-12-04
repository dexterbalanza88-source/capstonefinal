<?php
header('Content-Type: application/json');

$debug = true;

// ✅ Include connection (correct path)
try {
    include_once('../../db/conn.php');
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => '❌ Database connection include failed.',
        'error' => $debug ? $e->getMessage() : 'Server error'
    ]);
    exit;
}

// ✅ Capture POST data
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing credentials'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM enumerators WHERE username = ? LIMIT 1");

    if (!$stmt)
        throw new Exception("SQL prepare failed: " . $conn->error);

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result)
        throw new Exception("SQL execution failed: " . $conn->error);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password']) || $user['password'] === $password) {
            echo json_encode([
                'success' => true,
                'message' => 'Login successful'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid password'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error processing login.',
        'error' => $debug ? $e->getMessage() : 'Server error'
    ]);
}

$conn->close();
?>
