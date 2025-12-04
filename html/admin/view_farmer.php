<?php
include "../db/conn.php";

// Validate ID from GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid farmer ID.");
}

$farmer_id = (int) $_GET['id'];

// Fetch farmer data
$farmerResult = $conn->query("SELECT * FROM registration_form WHERE id=$farmer_id");
if ($farmerResult->num_rows == 0) {
    die("Farmer not found.");
}

$farmer = $farmerResult->fetch_assoc();

// Crop fields
$cropsFields = ['for_farmer','for_farmerworker','for_fisherfolk','for_agri'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Farmer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<div class="max-w-4xl mx-auto bg-white p-6 shadow rounded-lg">
    <h2 class="text-2xl font-bold mb-4">Farmer Profile - <?= htmlspecialchars($farmer['f_name'] . ' ' . $farmer['s_name']) ?></h2>

    <form method="POST" action="update_farmer.php">
        <input type="hidden" name="farmer_id" value="<?= $farmer['id'] ?>">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mt-2">First Name</label>
                <input type="text" name="f_name" value="<?= htmlspecialchars($farmer['f_name']) ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block mt-2">Surname</label>
                <input type="text" name="s_name" value="<?= htmlspecialchars($farmer['s_name']) ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block mt-2">Middle Name</label>
                <input type="text" name="m_name" value="<?= htmlspecialchars($farmer['m_name']) ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block mt-2">Contact</label>
                <input type="text" name="mobile" value="<?= htmlspecialchars($farmer['mobile']) ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block mt-2">Address / Barangay</label>
                <input type="text" name="brgy" value="<?= htmlspecialchars($farmer['brgy']) ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block mt-2">DOB</label>
                <input type="date" name="dob" value="<?= htmlspecialchars($farmer['dob']) ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block mt-2">Gender</label>
                <select name="gender" class="w-full border p-2 rounded">
                    <option value="Male" <?= $farmer['gender']=='Male' ? 'selected':'' ?>>Male</option>
                    <option value="Female" <?= $farmer['gender']=='Female' ? 'selected':'' ?>>Female</option>
                </select>
            </div>
            <div>
                <label class="block mt-2">Farm Size</label>
                <input type="text" name="total_farmarea" value="<?= htmlspecialchars($farmer['total_farmarea']) ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block mt-2">Status</label>
                <select name="status" class="w-full border p-2 rounded">
                    <option value="Pending" <?= $farmer['status']=='Pending'?'selected':'' ?>>Pending</option>
                    <option value="Process" <?= $farmer['status']=='Process'?'selected':'' ?>>Process</option>
                    <option value="Registered" <?= $farmer['status']=='Registered'?'selected':'' ?>>Registered</option>
                </select>
            </div>
        </div>

        <h3 class="text-lg font-semibold mt-4">Crops</h3>
        <div class="grid grid-cols-2 gap-4">
            <?php foreach($cropsFields as $field): ?>
                <div>
                    <label class="block mt-2"><?= ucfirst(str_replace('_',' ',$field)) ?></label>
                    <input type="text" name="<?= $field ?>" value="<?= htmlspecialchars($farmer[$field]) ?>" class="w-full border p-2 rounded">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <a href="index.php" class="bg-gray-400 text-white px-3 py-1 rounded">Back</a>
            <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Update</button>
        </div>
    </form>
</div>

</body>
</html>
