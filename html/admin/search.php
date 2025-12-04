<?php
require_once __DIR__ . "/includes/session.php";
session_start();

$dbhost = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "farmer_info";
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$response = ['new' => '', 'registered' => ''];

if (!empty($search)) {
    $safeSearch = mysqli_real_escape_string($conn, $search);

    // Search for NEW farmers (pending status)
    $sql_new = "
        SELECT
            rf.id,
            rf.f_name,
            rf.m_name,
            rf.s_name,
            rf.brgy,
            rf.dob,
            rf.gender,
            rf.mobile,
            COALESCE(SUM(l.total_area), 0) AS total_farmarea,
            COALESCE(GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', '), 'N/A') AS livelihoodsList
        FROM registration_form rf
        LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
        LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
        WHERE LOWER(rf.status) = 'pending' AND (
            LOWER(rf.f_name) LIKE LOWER('%$safeSearch%')
            OR LOWER(rf.m_name) LIKE LOWER('%$safeSearch%')
            OR LOWER(rf.s_name) LIKE LOWER('%$safeSearch%')
            OR LOWER(rf.brgy) LIKE LOWER('%$safeSearch%')
            OR LOWER(sa.sub_name) LIKE LOWER('%$safeSearch%')
        )
        GROUP BY rf.id
        ORDER BY rf.f_name ASC
    ";

    $result_new = mysqli_query($conn, $sql_new);

    // Build new farmers HTML
    $new_html = '';
    $counter = 1;
    while ($row = mysqli_fetch_assoc($result_new)) {
        $dob = new DateTime($row['dob']);
        $today = new DateTime();
        $age = $today->diff($dob)->y;

        $middleInitial = !empty($row['m_name']) ? strtoupper(substr($row['m_name'], 0, 1)) . "." : "";
        $fullName = htmlspecialchars(ucwords(strtolower(trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name']))));

        $new_html .= "
        <tr class='border-b border-gray-200' data-id='{$row['id']}'>
            <td class='px-4 py-4'><input type='checkbox' class='rowCheckbox' value='{$row['id']}'></td>
            <td class='px-4 py-4'>{$counter}</td>
            <td class='px-4 py-4'>{$fullName}</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['brgy']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['mobile']) . "</td>
            <td class='px-4 py-4'>{$age}</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['dob']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['gender']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['total_farmarea']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['livelihoodsList'] ?: 'N/A') . "</td>
            <td class='px-4 py-3 text-center'>
                <button data-id='{$row['id']}' class='archiveAction flex items-center justify-center gap-2 px-4 py-2 bg-green-500 text-white font-medium rounded-lg shadow-sm hover:bg-green-600 hover:shadow transition-all duration-200'>
                    <svg xmlns='http://www.w3.org/2000/svg' class='w-4 h-4' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2'>
                        <path stroke-linecap='round' stroke-linejoin='round' d='M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0H4m16 0v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6' />
                    </svg>
                    Archive
                </button>
            </td>
        </tr>";
        $counter++;
    }

    if (empty($new_html)) {
        $new_html = "<tr><td colspan='11' class='px-4 py-4 text-center text-gray-500'>No new farmers found</td></tr>";
    }

    $response['new'] = $new_html;

    // Search for REGISTERED farmers
    $sql_reg = "
        SELECT
            rf.id,
            rf.f_name,
            rf.m_name,
            rf.s_name,
            rf.brgy,
            rf.dob,
            rf.gender,
            rf.mobile,
            COALESCE(SUM(l.total_area), 0) AS total_farmarea,
            COALESCE(GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', '), 'N/A') AS livelihoodsList
        FROM registration_form rf
        LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
        LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
        WHERE LOWER(rf.status) = 'registered' AND (
            LOWER(rf.f_name) LIKE LOWER('%$safeSearch%')
            OR LOWER(rf.m_name) LIKE LOWER('%$safeSearch%')
            OR LOWER(rf.s_name) LIKE LOWER('%$safeSearch%')
            OR LOWER(rf.brgy) LIKE LOWER('%$safeSearch%')
            OR LOWER(sa.sub_name) LIKE LOWER('%$safeSearch%')
        )
        GROUP BY rf.id
        ORDER BY rf.f_name ASC
    ";

    $result_reg = mysqli_query($conn, $sql_reg);

    // Build registered farmers HTML
    $reg_html = '';
    $counter = 1;
    while ($row = mysqli_fetch_assoc($result_reg)) {
        $dob = new DateTime($row['dob']);
        $today = new DateTime();
        $age = $today->diff($dob)->y;

        $middleInitial = !empty($row['m_name']) ? strtoupper(substr($row['m_name'], 0, 1)) . "." : "";
        $fullName = htmlspecialchars(ucwords(strtolower(trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name']))));

        $reg_html .= "
        <tr class='border-b border-gray-200' data-id='{$row['id']}'>
            <td class='px-4 py-4'><input type='checkbox' class='rowCheckbox' value='{$row['id']}'></td>
            <td class='px-4 py-4'>{$counter}</td>
            <td class='px-4 py-4'>{$fullName}</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['brgy']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['mobile']) . "</td>
            <td class='px-4 py-4'>{$age}</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['dob']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['gender']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['total_farmarea']) . "</td>
            <td class='px-4 py-4'>" . htmlspecialchars($row['livelihoodsList']) . "</td>
            <td class='px-4 py-3 relative'>
                <button type='button' class='dropdown-toggle inline-flex items-center p-1 text-gray-500 hover:text-gray-800 rounded-lg'>
                    <svg class='w-5 h-5' fill='currentColor' viewBox='0 0 20 20'>
                        <path d='M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z' />
                    </svg>
                </button>
                <div class='dropdown-menu hidden absolute right-0 z-50 w-48 bg-white rounded shadow divide-y divide-gray-100'>
                    <ul class='py-1 text-sm text-gray-700 font-medium'>
                        <li><a href='view_details.php?id={$row['id']}' class='flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition'>View Details</a></li>
                        <li><button data-id='{$row['id']}' class='archiveAction w-full text-left flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition'>Archive</button></li>
                        <li class='border-t border-gray-100 my-1'></li>
                        <li><button data-id='{$row['id']}' class='generateIdBtn w-full text-left flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition'>Generate ID</button></li>
                        <li><a href='print_rsbsa_form.php?id={$row['id']}' target='_blank' class='flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition'>Print Form</a></li>
                    </ul>
                </div>
            </td>
        </tr>";
        $counter++;
    }

    if (empty($reg_html)) {
        $reg_html = "<tr><td colspan='11' class='px-4 py-4 text-center text-gray-500'>No registered farmers found</td></tr>";
    }

    $response['registered'] = $reg_html;
} else {
    $response['new'] = "<tr><td colspan='11' class='px-4 py-4 text-center text-gray-500'>Please enter a search term</td></tr>";
    $response['registered'] = "<tr><td colspan='11' class='px-4 py-4 text-center text-gray-500'>Please enter a search term</td></tr>";
}

header('Content-Type: application/json');
echo json_encode($response);
?>