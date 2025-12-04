<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('../../tcpdf/TCPDF-main/tcpdf.php');

// Database connection
$conn = new mysqli("localhost", "root", "", "farmer_info");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get farmer ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch data
$query = $conn->query("SELECT * FROM registration_form WHERE id = $id");
if (!$query || $query->num_rows === 0) {
    die("No farmer found with ID: $id");
}
$data = $query->fetch_assoc();

// Initialize TCPDF
$pdf = new TCPDF('P', 'mm', 'Legal', true, 'UTF-8', false);
$pdf->AddPage();

// Set background image
$background = '../../img/rsbsaform.jpg';
if (!file_exists($background)) {
    die("Background image not found: $background");
}

// Add background to entire page
$pdf->Image($background, 0, 0, 216, 356, '', '', '', false, 300, '', false, false, 0);

// Disable headers/footers
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(false, 0);

// Set font and text color
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

// =============================
// PERSONAL INFO - USE YOUR CORRECTED COORDINATES HERE
// =============================

// REPLACE THESE COORDINATES WITH WHAT YOU FIND FROM coordinate_finder.php
// Current: X, Y positions (example - YOU MUST ADJUST THESE)

// Name - REPLACE WITH YOUR ACTUAL COORDINATES
$pdf->SetXY(25, 55);  // REPLACE 25,55
$pdf->Cell(55, 5, $data['s_name'] ?? '', 0, 0, 'L');

$pdf->SetXY(90, 55);  // REPLACE 90,55
$pdf->Cell(55, 5, $data['f_name'] ?? '', 0, 0, 'L');

$pdf->SetXY(25, 63);  // REPLACE 25,63
$pdf->Cell(55, 5, $data['m_name'] ?? '', 0, 0, 'L');

$pdf->SetXY(90, 63);  // REPLACE 90,63
$pdf->Cell(25, 5, $data['extension'] ?? '', 0, 0, 'L');

// Sex checkbox
$pdf->SetFont('zapfdingbats', '', 12);
if (isset($data['gender'])) {
    if (strtoupper($data['gender']) == 'MALE' || $data['gender'] == 'Male') {
        $pdf->SetXY(160, 55);  // REPLACE 160,55 for Male
        $pdf->Cell(5, 5, '4', 0, 0, 'L');
    } else {
        $pdf->SetXY(180, 55);  // REPLACE 180,55 for Female
        $pdf->Cell(5, 5, '4', 0, 0, 'L');
    }
}
$pdf->SetFont('helvetica', '', 10);

// Address
$pdf->SetXY(25, 78);  // REPLACE 25,78
$pdf->Cell(50, 5, $data['address_house'] ?? '', 0, 0, 'L');

$pdf->SetXY(85, 78);  // REPLACE 85,78
$pdf->Cell(50, 5, $data['address_street'] ?? '', 0, 0, 'L');

$pdf->SetXY(150, 78);  // REPLACE 150,78
$pdf->Cell(40, 5, $data['barangay'] ?? '', 0, 0, 'L');

$pdf->SetXY(25, 88);  // REPLACE 25,88
$pdf->Cell(60, 5, $data['municipality'] ?? '', 0, 0, 'L');

$pdf->SetXY(100, 88);  // REPLACE 100,88
$pdf->Cell(50, 5, $data['province'] ?? '', 0, 0, 'L');

$pdf->SetXY(165, 88);  // REPLACE 165,88
$pdf->Cell(30, 5, $data['region'] ?? '', 0, 0, 'L');

// Contact
$pdf->SetXY(25, 98);  // REPLACE 25,98
$pdf->Cell(60, 5, $data['mobile'] ?? '', 0, 0, 'L');

$pdf->SetXY(100, 98);  // REPLACE 100,98
$pdf->Cell(60, 5, $data['landline'] ?? '', 0, 0, 'L');

// DOB and POB
$pdf->SetXY(25, 118);  // REPLACE 25,118
if (isset($data['dob'])) {
    $dob = date('Y-m-d', strtotime($data['dob']));
    $pdf->Cell(40, 5, $dob, 0, 0, 'L');
}

$pdf->SetXY(100, 118);  // REPLACE 100,118
$pdf->Cell(50, 5, $data['pob_municipality'] ?? '', 0, 0, 'L');

$pdf->SetXY(165, 118);  // REPLACE 165,118
$pdf->Cell(30, 5, $data['pob_province'] ?? '', 0, 0, 'L');

// Religion
$pdf->SetXY(35, 138);  // REPLACE 35,138
$pdf->Cell(60, 5, $data['religion'] ?? '', 0, 0, 'L');

// Civil Status
$pdf->SetFont('zapfdingbats', '', 12);
$civil_status = $data['civil_status'] ?? '';
if (strtoupper($civil_status) == 'SINGLE') {
    $pdf->SetXY(40, 148);  // REPLACE 40,148
    $pdf->Cell(5, 5, '4', 0, 0, 'L');
} elseif (strtoupper($civil_status) == 'MARRIED') {
    $pdf->SetXY(70, 148);  // REPLACE 70,148
    $pdf->Cell(5, 5, '4', 0, 0, 'L');
} elseif (strtoupper($civil_status) == 'WIDOWED') {
    $pdf->SetXY(100, 148);  // REPLACE 100,148
    $pdf->Cell(5, 5, '4', 0, 0, 'L');
} elseif (strtoupper($civil_status) == 'SEPARATED') {
    $pdf->SetXY(140, 148);  // REPLACE 140,148
    $pdf->Cell(5, 5, '4', 0, 0, 'L');
}
$pdf->SetFont('helvetica', '', 10);

// Mother's Maiden Name
$pdf->SetXY(42, 163);  // REPLACE 42,163
$pdf->Cell(80, 5, $data['mother_maiden'] ?? '', 0, 0, 'L');

// Household Head
$pdf->SetFont('zapfdingbats', '', 12);
if (isset($data['household_head'])) {
    if (strtoupper($data['household_head']) == 'YES') {
        $pdf->SetXY(42, 173);  // REPLACE 42,173
        $pdf->Cell(5, 5, '4', 0, 0, 'L');
    } elseif (strtoupper($data['household_head']) == 'NO') {
        $pdf->SetXY(62, 173);  // REPLACE 62,173
        $pdf->Cell(5, 5, '4', 0, 0, 'L');
    }
}
$pdf->SetFont('helvetica', '', 10);

// Output PDF
ob_end_clean();
$filename = 'RSBSA_Form_' . ($data['s_name'] ?? 'Unknown') . '.pdf';
$pdf->Output($filename, 'I');

$conn->close();
?>