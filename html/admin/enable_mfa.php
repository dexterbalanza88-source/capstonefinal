<?php
// html/admin/enable_mfa.php
require_once "../../db/conn.php";
require_once __DIR__ . '/includes/session.php';
secure_session_start();
require_once __DIR__ . '/libs/googleauth/GoogleAuthenticator.php';

if (!isset($_SESSION['pending_user_id'])) {
    header('Location: adminlogin.php');
    exit;
}

$ga = new GoogleAuthenticator();
$userId = (int)$_SESSION['pending_user_id'];
$username = $_SESSION['pending_username'] ?? 'user';

$stmt = $conn->prepare("SELECT mfa_secret, two_factor_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { header('Location: adminlogin.php'); exit; }

// If already has secret -> go to verify
if (!empty($row['mfa_secret'])) {
    header('Location: verify_mfa.php');
    exit;
}

// Generate secret and save
$secret = $ga->createSecret(16);
$upd = $conn->prepare("UPDATE users SET mfa_secret = ?, two_factor_enabled = 1 WHERE id = ?");
$upd->bind_param("si", $secret, $userId);
$upd->execute();

$qrUrl = $ga->getQRCodeGoogleUrl("MAO-AbraDeIlog:{$username}", $secret, "MAO-AbraDeIlog");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Enable MFA</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
  <div class="bg-white p-6 rounded shadow w-96">
    <h2 class="text-xl font-semibold text-green-700 mb-3">Set up Two-Factor Authentication</h2>
    <p class="text-sm text-gray-600 mb-2">Scan this QR code with Google Authenticator (or Authy), then enter code to verify.</p>
    <div class="flex justify-center mb-3"><img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="QR"></div>
    <p class="text-xs text-gray-500 mb-3">Secret (store safely): <code><?php echo htmlspecialchars($secret); ?></code></p>
    <form method="post" action="verify_mfa.php">
      <label class="block mb-2">Enter code from app</label>
      <input name="code" maxlength="6" required class="border p-2 w-full mb-3" />
      <button type="submit" class="bg-green-700 text-white w-full p-2 rounded">Verify & Activate</button>
    </form>
    <p class="mt-3 text-sm"><a href="adminlogin.php" class="text-green-700">Back to login</a></p>
  </div>
</body>
</html>
