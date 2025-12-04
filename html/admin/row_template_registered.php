<?php
$dob = new DateTime($row['dob']);
$today = new DateTime();
$age = $today->diff($dob)->y;

$middleInitial = !empty($row['m_name']) ? strtoupper(substr($row['m_name'], 0, 1)) . "." : "";
$fullName = ucwords(strtolower(trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name'])));
?>

<tr class="border-b border-gray-200" data-id="<?= $row['id'] ?>">
    <td class="px-4 py-4"><input type="checkbox" class="rowCheckbox" value="<?= $row['id'] ?>"></td>
    <td class="px-4 py-4"><?= $row['id'] ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($fullName) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['brgy']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['mobile']) ?></td>
    <td class="px-4 py-4"><?= $age ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['dob']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['gender']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['total_farmarea']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['livelihoodsList']) ?></td>

    <td class="px-4 py-3 relative">
        <button type="button" class="dropdown-toggle inline-flex items-center p-1 text-gray-500 hover:text-gray-800 rounded-lg">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/>
            </svg>
        </button>

        <div class="dropdown-menu hidden absolute right-0 z-50 w-48 bg-white rounded shadow divide-y divide-gray-100">
            <ul class="py-1 text-sm text-gray-700 font-medium">

                <li>
                    <a href="view_details.php?id=<?= $row['id']; ?>" class="flex items-center gap-2 px-4 py-2 hover:bg-green-50 hover:text-green-700">
                        View Details
                    </a>
                </li>

                <li>
                    <button data-id="<?= $row['id']; ?>" class="archiveAction w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-green-50 hover:text-green-700">
                        Archive
                    </button>
                </li>

                <li class="border-t border-gray-100 my-1"></li>

                <li>
                    <button data-id="<?= $row['id']; ?>" class="generateIdBtn w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-green-50 hover:text-green-700">
                        Generate ID
                    </button>
                </li>

                <li>
                    <a href="print_rsbsa_form.php?id=<?= $row['id']; ?>" target="_blank"
                        class="flex items-center gap-2 px-4 py-2 hover:bg-green-50 hover:text-green-700">
                        Print Form
                    </a>
                </li>
            </ul>
        </div>
    </td>
</tr>
