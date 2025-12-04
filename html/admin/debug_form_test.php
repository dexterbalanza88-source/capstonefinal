<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'form_debug.log');

session_name("admin_session");
session_start();

echo "<h2>Form Debug Test</h2>";

// Log everything
error_log("=== FORM DEBUG TEST ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION data: " . print_r($_SESSION, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request detected!");
    echo "<p style='color: green;'>POST request received!</p>";
    echo "<pre>POST data: " . print_r($_POST, true) . "</pre>";
    
    // Test redirect
    error_log("Attempting redirect to otp_verify.php");
    header("Location: otp_verify.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug Form Test</title>
</head>
<body>
    <h3>Test Form</h3>
    <form method="POST" action="">
        <input type="hidden" name="test" value="debug">
        <button type="submit">Test Submit</button>
    </form>
    
    <hr>
    
    <h3>Your Actual Login Form</h3>
    <form method="POST" action="adminlogin.php?role=admin">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? 'test'; ?>">
        <button type="submit">Login</button>
    </form>
</body>
</html>