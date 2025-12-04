<?php
session_name("admin_session");
session_start();
require_once "../../db/conn.php";

// ----------------------------------------------------
// Security Headers
// ----------------------------------------------------
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ----------------------------------------------------
// Authentication check
// ----------------------------------------------------

// User must be fully logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to access this page.";
    header("Location: adminlogin.php");
    exit;
}

// If user has pending OTP verification, redirect to OTP page
if (!empty($_SESSION['pending_user_id'])) {
    header("Location: otp_verify.php");
    exit;
}

// ----------------------------------------------------
// At this point, user is fully authenticated
// ----------------------------------------------------
$username = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'guest';

$user_id = (int) $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("
    SELECT id, username, email, role, profile_image, created_at, last_login
    FROM users 
    WHERE id = ? AND is_active = 1 LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: adminlogin.php?error=account_not_found");
    exit;
}

// Set profile image path
$profileImg = (!empty($user["profile_image"]) && file_exists("uploads/profile/" . $user["profile_image"]))
    ? "uploads/profile/" . $user["profile_image"]
    : "../../img/profile.png";

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . "/uploads/profile/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ===============================
// IMAGE UPLOAD HANDLER
// ===============================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["profile_image"])) {
    $file = $_FILES["profile_image"];
    $allowedTypes = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file["error"] === UPLOAD_ERR_OK) {
        // Verify file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file["tmp_name"]);
        finfo_close($finfo);

        if (!array_key_exists($mime_type, $allowedTypes)) {
            $uploadError = "Only JPG, PNG, or WEBP files are allowed.";
        } elseif ($file["size"] > $maxSize) {
            $uploadError = "Image must be less than 2MB.";
        } else {
            $ext = $allowedTypes[$mime_type];
            $newFileName = "profile_" . $user_id . "_" . bin2hex(random_bytes(8)) . "." . $ext;
            $uploadPath = $uploadDir . $newFileName;

            // Resize image
            if (resizeImage($file["tmp_name"], $uploadPath, 500, 500)) {
                // Delete old image if exists
                if (!empty($user["profile_image"])) {
                    $oldFile = $uploadDir . $user["profile_image"];
                    if (file_exists($oldFile) && is_writable($oldFile)) {
                        unlink($oldFile);
                    }
                }

                // Update database
                $update = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $update->bind_param("si", $newFileName, $user_id);

                if ($update->execute()) {
                    $_SESSION['success'] = "Profile picture updated successfully!";
                    header("Location: profile.php");
                    exit;
                } else {
                    $uploadError = "Database update failed.";
                    // Clean up uploaded file
                    if (file_exists($uploadPath)) {
                        unlink($uploadPath);
                    }
                }
            } else {
                $uploadError = "Failed to process image.";
            }
        }
    } else {
        $uploadError = getUploadErrorMessage($file["error"]);
    }
}

// ===============================
// UPDATE PROFILE INFORMATION
// ===============================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {
    $newUsername = trim($_POST["username"] ?? '');

    // Basic validation
    if (empty($newUsername)) {
        $editError = "Username cannot be empty.";
    } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
        $editError = "Username must be between 3 and 50 characters.";
    } else {
        // Check if username is already taken (excluding current user)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $newUsername, $user_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $editError = "Username is already taken.";
        } else {
            // If no errors, proceed with update
            if (empty($editError)) {
                if ($newUsername !== $user['username']) {
                    // Update username
                    $update_stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $newUsername, $user_id);

                    if ($update_stmt->execute()) {
                        // Update session
                        $_SESSION['username'] = $newUsername;

                        $_SESSION['success'] = "Profile updated successfully!";
                        header("Location: profile.php");
                        exit;
                    } else {
                        $editError = "Database update failed. Please try again.";
                    }
                } else {
                    $editError = "No changes were made.";
                }
            }
        }
    }
}

// ===============================
// HELPER FUNCTIONS
// ===============================

/**
 * Resize image to prevent oversized uploads
 */
function resizeImage($source_path, $dest_path, $max_width, $max_height)
{
    list($orig_width, $orig_height, $type) = getimagesize($source_path);

    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }

    // Calculate new dimensions
    $ratio = $orig_width / $orig_height;
    if ($max_width / $max_height > $ratio) {
        $new_width = $max_height * $ratio;
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    }

    $destination = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency for PNG and WebP
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
    }

    imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($destination, $dest_path, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($destination, $dest_path, 9);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($destination, $dest_path, 90);
            break;
    }

    imagedestroy($source);
    imagedestroy($destination);

    return $result;
}

/**
 * Get user-friendly upload error messages
 */
function getUploadErrorMessage($error_code)
{
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File is too large. Maximum size is 2MB.";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded.";
        case UPLOAD_ERR_NO_FILE:
            return "No file was selected.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing temporary folder.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk.";
        case UPLOAD_ERR_EXTENSION:
            return "File upload stopped by extension.";
        default:
            return "Unknown upload error.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MAO Abra De Ilog</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');

        .profile-gradient {
            background: linear-gradient(135deg, #166534 0%, #22c55e 100%);
        }

        .card-hover {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .stat-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-left: 4px solid #16a34a;
        }

        .upload-area {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #16a34a;
            background-color: #f0fdf4;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <nav class="bg-[#166534] text-white shadow-lg fixed w-full z-50 top-0 left-0 border-b-4 border-[#E6B800]">
        <div class="flex items-center justify-between max-w-7xl mx-auto px-6 py-3">
            <!-- Left: Logo & Drawer Toggle -->
            <div class="flex items-center space-x-3">
                <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
                    aria-controls="drawer-navigation"
                    class="p-2 text-white rounded-lg cursor-pointer md:hidden hover:bg-[#14532d] focus:ring-2 focus:ring-[#E6B800]">
                    <!-- Menu icon -->
                    <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                            d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                            clip-rule="evenodd"></path>
                    </svg>
                    <span class="sr-only">Toggle sidebar</span>
                </button>

                <img src="../../img/logo.png" alt="LGU Logo"
                    class="h-12 w-12 rounded-full border-2 border-white bg-white">
                <h1 class="text-lg font-semibold tracking-wide">
                    Municipal Agriculture Office – Abra De Ilog
                </h1>
            </div>

            <!-- Right: Profile Dropdown -->
            <div class="flex items-center space-x-3 relative select-none">
                <button id="user-menu-button" class="flex items-center rounded-full ring-2 ring-transparent hover:ring-[#FFD447] 
        transition-all duration-200 p-[3px]" onclick="toggleUserDropdown()">
                    <img class="w-11 h-11 rounded-full shadow-md border-2 border-white" src="../../img/profile.png"
                        alt="User photo">
                </button>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside id="drawer-navigation"
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-16 transition-transform -translate-x-full md:translate-x-0 bg-white border-r border-gray-200"
        aria-label="Sidenav">
        <div class="overflow-y-auto py-5 px-3 h-full bg-white">
            <ul class="space-y-2">
                <li>
                    <a href="index.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                            <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                        </svg>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="adddata.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd"
                                d="M9 2.221V7H4.221a2 2 0 0 1 .365-.5L8.5 2.586A2 2 0 0 1 9 2.22ZM11 2v5a2 2 0 0 1-2 2H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-7Z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="ml-3">Add Data</span>
                    </a>
                </li>

                <li>
                    <a href="datalist.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-3">Data List</span>
                    </a>
                </li>

                <li>
                    <a href="report.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-3">Reports</span>
                    </a>
                </li>

                <li>
                    <a href="archived.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0-2-2v-4z">
                            </path>
                        </svg>
                        <span class="ml-3">Archived</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="pt-20 md:ml-64 min-h-screen">
        <div class="max-w-6xl mx-auto p-6">

            <!-- Success Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div
                    class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3 animate-fade-in">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500 text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-green-800 font-medium">
                            <?= htmlspecialchars($_SESSION['success']);
                            unset($_SESSION['success']); ?>
                        </p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Profile Settings</h1>
                    <p class="text-gray-600 mt-2">Manage your account information</p>
                </div>
                <button onclick="closeProfileAndGoBack()"
                    class="mt-4 md:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-all duration-200 font-medium cursor-pointer z-50 relative">
                    <i class="fas fa-arrow-left"></i>
                    Back to Previous Page
                </button>
            </div>

            <!-- Profile Overview Card -->
            <div class="profile-gradient rounded-2xl shadow-xl overflow-hidden mb-8 text-white">
                <div class="p-8">
                    <div class="flex flex-col md:flex-row items-center gap-6">
                        <!-- Profile Image -->
                        <div class="relative">
                            <img id="previewImg" src="<?= $profileImg ?>"
                                class="h-28 w-28 rounded-full border-4 border-white/80 shadow-2xl object-cover">
                            <div
                                class="absolute -bottom-2 -right-2 bg-green-500 rounded-full p-1.5 border-2 border-white">
                                <i class="fas fa-camera text-white text-xs"></i>
                            </div>
                        </div>

                        <!-- User Info -->
                        <div class="flex-1 text-center md:text-left">
                            <h1 class="text-2xl md:text-3xl font-bold mb-2"><?= htmlspecialchars($user['username']) ?>
                            </h1>
                            <p
                                class="text-green-100 text-lg mb-3 flex items-center justify-center md:justify-start gap-2">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($user['email']) ?>
                            </p>

                            <!-- Status Badges -->
                            <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                                <span class="status-badge bg-white/20 backdrop-blur-sm">
                                    <i class="fas fa-shield-alt"></i>
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="stat-card rounded-xl p-5">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-calendar-plus text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Member Since</p>
                            <p class="font-semibold text-gray-900"><?= date('M Y', strtotime($user['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl p-5">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-sign-in-alt text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Last Login</p>
                            <p class="font-semibold text-gray-900 text-sm">
                                <?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl p-5">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-user-check text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Account Status</p>
                            <p class="font-semibold text-green-600">Active</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8">

                <!-- Profile Picture Card -->
                <div class="bg-white rounded-2xl shadow-sm card-hover p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-camera text-green-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Profile Picture</h2>
                            <p class="text-gray-600 text-sm">Update your profile photo</p>
                        </div>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div
                            class="upload-area rounded-xl p-6 text-center mb-4 relative border-2 border-dashed border-gray-300 hover:border-green-500 transition-colors duration-200">
                            <div class="pointer-events-none">
                                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                <p class="text-gray-600 font-medium mb-2">Click to upload or drag and drop</p>
                                <p class="text-gray-500 text-sm">JPG, PNG or WebP. Max 2MB</p>
                            </div>
                            <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                onchange="previewProfile(event)">
                        </div>

                        <?php if (!empty($uploadError)): ?>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 flex items-center gap-3">
                                <i class="fas fa-exclamation-triangle text-red-500"></i>
                                <p class="text-red-700 text-sm"><?= htmlspecialchars($uploadError) ?></p>
                            </div>
                        <?php endif; ?>

                        <button type="submit"
                            class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 font-medium transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="fas fa-upload"></i>
                            Upload New Picture
                        </button>
                    </form>
                </div>

                <!-- Edit Profile Information -->
                <div class="bg-white rounded-2xl shadow-sm card-hover p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-user-edit text-blue-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Profile Information</h2>
                            <p class="text-gray-600 text-sm">Update your account details</p>
                        </div>
                    </div>

                    <form action="" method="POST">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="space-y-4">
                            <div>
                                <label for="username"
                                    class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input type="text" id="username" name="username"
                                    value="<?= htmlspecialchars($user['username']) ?>"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email
                                    Address</label>
                                <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                    disabled>
                                <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                            </div>

                            <?php if (!empty($editError)): ?>
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center gap-3">
                                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                                    <p class="text-red-700 text-sm"><?= htmlspecialchars($editError) ?></p>
                                </div>
                            <?php endif; ?>

                            <button type="submit"
                                class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 font-medium transition-all duration-200 flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password & Security Card -->
            <div class="bg-white rounded-2xl shadow-sm card-hover p-6 mt-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-shield-alt text-red-600"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Password & Security</h2>
                        <p class="text-gray-600 text-sm">Manage your password and security settings</p>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Password Management -->
                    <div class="space-y-4">
                        <h3 class="font-semibold text-gray-700 text-lg">Password Management</h3>
                        <p class="text-gray-600 text-sm">Change your password and manage password settings</p>

                        <a href="change_password.php"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium transition-all duration-200">
                            <i class="fas fa-key"></i>
                            Change Password
                        </a>
                    </div>

                    <!-- Security Settings -->
                    <div class="space-y-4">
                        <h3 class="font-semibold text-gray-700 text-lg">Security Settings</h3>
                        <p class="text-gray-600 text-sm">Configure security preferences and account protection</p>

                        <a href="security_settings.php"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-all duration-200">
                            <i class="fas fa-cog"></i>
                            Security Settings
                        </a>
                    </div>
                </div>

                <!-- Quick Security Links -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-4 text-lg">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <a href="login_history.php"
                            class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-all duration-200 group">
                            <div
                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                <i class="fas fa-history text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Login History</p>
                                <p class="text-sm text-gray-600">View your recent login activity</p>
                            </div>
                        </a>

                        <a href="two_factor.php"
                            class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-all duration-200 group">
                            <div
                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                <i class="fas fa-mobile-alt text-green-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Two-Factor Authentication</p>
                                <p class="text-sm text-gray-600">Add extra security to your account</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        function closeProfileAndGoBack() {
            if (document.referrer && document.referrer.includes(window.location.origin)) {
                window.history.back();
            } else {
                window.location.href = 'index.php';
            }
        }

        function previewProfile(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById("previewImg").src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

        // Set active sidebar link
        document.addEventListener('DOMContentLoaded', function () {
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('#drawer-navigation a');

            sidebarLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage) {
                    link.classList.remove('text-gray-900', 'hover:bg-green-100');
                    link.classList.add('bg-green-50', 'border-l-4', 'border-[#E6B800]', 'text-[#166534]');
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>