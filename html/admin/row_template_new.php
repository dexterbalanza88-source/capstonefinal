<?php
$dob = !empty($row['dob']) ? new DateTime($row['dob']) : null;
$today = new DateTime();
$age = $dob ? $today->diff($dob)->y : "N/A";

$middleInitial = !empty($row['m_name']) ? strtoupper(substr($row['m_name'], 0, 1)) . "." : "";
$fullName = ucwords(strtolower(trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name'])));

// Crops / Livelihoods
$cropsList = htmlspecialchars($row['livelihoodsList'] ?: "N/A");
?>

<tr class="border-b border-gray-200" data-id="<?= $row['id'] ?>">
    <td class="px-4 py-4">
        <input type="checkbox" class="rowCheckbox" value="<?= $row['id'] ?>">
    </td>
    <td class="px-4 py-4"><?= $row['id'] ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($fullName) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['brgy']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['mobile']) ?></td>
    <td class="px-4 py-4"><?= $age ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['dob']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['gender']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['total_farmarea']) ?></td>
    <td class="px-4 py-4"><?= $cropsList ?></td>
    <td class="px-4 py-3 text-center">
        <button data-id="<?= $row['id']; ?>"
            class="archiveAction flex items-center justify-center gap-2 px-4 py-2 bg-green-500 text-white font-medium rounded-lg shadow-sm hover:bg-green-600 hover:shadow transition-all duration-200">
            Archive
        </button>
    </td>
</tr>
