<?php
include "../db/conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = 'Staff';
    $status = 'Active';

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $fullname, $email, $passwordHash, $role, $status);

    if ($stmt->execute()) {
        echo "<script>alert('✅ User added successfully!'); window.location.href='profile.php';</script>";
    } else {
        echo "<script>alert('❌ Failed to add user: " . $conn->error . "'); window.location.href='admin_panel.php';</script>";
    }
}
?>