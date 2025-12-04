<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

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
$data = $query->fetch_assoc();

// Initialize TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->AddPage();

// 🔹 Set the scanned RSBSA form as background
$background = '../../img/rsbsa_form.jpg';
$pdf->Image($background, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);

// No border, no auto page breaks
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(false, 0);

// Set transparent font for overlay text
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);

// 🔹 Write database data over specific positions (X, Y)
$pdf->SetXY(50, 42);   // example: Surname position
$pdf->Cell(50, 5, $data['s_name']);

$pdf->SetXY(90, 42);   // First name
$pdf->Cell(50, 5, $data['f_name']);

$pdf->SetXY(130, 42);  // Middle name
$pdf->Cell(50, 5, $data['m_name']);

$pdf->SetXY(50, 50);   // Sex / Gender
$pdf->Cell(50, 5, ucfirst($data['gender']));

$pdf->SetXY(50, 58);   // Address example
$pdf->Cell(100, 5, $data['brgy'] . ', ' . $data['municipal'] . ', ' . $data['province']);

// add more fields depending on your form layout...

// Output PDF
ob_end_clean();
$pdf->Output('rsbsa_form_' . $data['s_name'] . '.pdf', 'I');
?>