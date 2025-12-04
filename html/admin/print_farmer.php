<?php
require_once '../../db/conn.php'; // adjust path to your db connection

// ✅ Get farmer by ID or reference
$id = $_GET['id'] ?? null;
$reference = $_GET['reference'] ?? null;

if (!$id && !$reference) {
    die("❌ Missing farmer ID or reference number.");
}

// ✅ Prepare query safely
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM registration_form WHERE id = ?");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("SELECT * FROM registration_form WHERE reference = ?");
    $stmt->bind_param("s", $reference);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("⚠️ No record found for the given reference or ID.");
}
$farmer = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Farmer Profile - Printable</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
    body {
        font-family: Arial, Helvetica, sans-serif
    }

    /* Custom styles for the input boxes to mimic a paper form */
    .form-input-box {
        display: inline-block;
        width: 24px;
        height: 24px;
        border: 1px solid black;
        text-align: center;
        font-size: 14px;
    }

    /* Style for the bordered sections */
    .section-border {
        border-bottom: 2.5px solid black;
    }

    .button-container {
        text-align: right;
        margin-bottom: 10px;
    }

    .btn {
        background-color: #007a4d;
        /* green shade */
        color: white;
        border: none;
        padding: 10px 18px;
        margin-left: 5px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
    }

    .btn:hover {
        opacity: 0.9;
    }

    .btn-close {
        background-color: #333;
        /* dark gray for close */
    }

    /* Print layout optimization */
    @media print {
        @page {
            size: A4 portrait;
            margin: 0.5cm 0.8cm;
        }

        html,
        body {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Prevent cutoff on right edge */
        .container,
        .section,
        .form-content {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Remove page overflow (extra blank pages) */
        * {
            box-sizing: border-box;
            overflow: visible !important;
        }

        /* Prevent breaking of form parts */
        .section,
        .form-block,
        .footer-section {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Hide buttons when printing */
        .no-print {
            display: none !important;
        }

        /* Optional: footer page numbers */
        footer::after {
            content: "Page " counter(page) " of " counter(pages);
            position: fixed;
            bottom: 5mm;
            right: 10mm;
            font-size: 10px;
            color: black;
        }
    }

    @media print {

        /* Force two-column layout to stay in a row when printing */
        .two-column,
        .flex-row,
        .form-row {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            width: 100% !important;
        }

        /* Ensure both left and right sections take up half the page */
        .left-section,
        .right-section {
            width: 49% !important;
            display: inline-block !important;
            vertical-align: top !important;
            box-sizing: border-box !important;
        }

        /* Remove any margin/padding causing breaks */
        .left-section>*,
        .right-section>* {
            page-break-inside: avoid !important;
        }

        /* Prevent flex children from wrapping down */
        .form-container {
            display: flex !important;
            flex-wrap: nowrap !important;
        }

        /* Optional: small scale to fit exactly 1 page width */
        body {
            transform: scale(0.95);
            transform-origin: top left;
        }
    }

    @media print {

        /* Force side-by-side layout */
        .flex-col.md\:flex-row {
            flex-direction: row !important;
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            width: 100% !important;
        }

        /* Left and right section each take half the page */
        .w-1\/2 {
            width: 49% !important;
            display: inline-block !important;
            vertical-align: top !important;
            box-sizing: border-box !important;
        }

        /* Make sure children don’t break between pages */
        .w-1\/2 * {
            page-break-inside: avoid !important;
        }

        /* Prevent shrinking to column due to flex wrapping */
        .flex {
            flex-wrap: nowrap !important;
        }

        /* Optional: slightly reduce scale to fit A4 width perfectly */
        body {
            transform: scale(0.96);
            transform-origin: top left;
        }

        /* Hide buttons in print view */
        button,
        .no-print {
            display: none !important;
        }

        /* Remove background colors that might cause black boxes */
        * {
            background: transparent !important;
            box-shadow: none !important;
        }

        /* Prevent margins that push content to next page */
        @page {
            margin: 0.5cm;
            size: A4 portrait;
        }
    }

    @media print {

        /* Fix two-column form layout */
        .form-columns {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            width: 100% !important;
            gap: 8px !important;
            /* small space between left/right */
        }

        .form-left,
        .form-right {
            width: 49% !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
            border: 1.5px solid black !important;
            padding: 4px !important;
        }

        /* Prevent overlapping lines inside */
        .form-left *,
        .form-right * {
            max-width: 100% !important;
            overflow: hidden !important;
        }

        /* Keep lines consistent between columns */
        .form-left input,
        .form-right input {
            border-width: 1px !important;
            border-color: black !important;
        }

        /* Align both columns height visually */
        .form-left {
            min-height: 100%;
        }

        .form-right {
            min-height: 100%;
        }

        /* Optional small scaling to fit perfectly */
        body {
            transform: scale(0.97);
            transform-origin: top left;
        }
    }
</style>

<body class="p-4 bg-gray-100 flex justify-center">
    <div
        class="relative flex flex-col items-center border border-black bg-white w-full max-w-5xl rounded-lg shadow-xl p-5 ">
        <div class="absolute top-1 right-4 text-xs font-bold text-gray-700 ">
            REVISED VERSION: 03-2021
        </div>
        <!-- Main Form Container -->
        <div class="relative flex flex-col items-center  border-black bg-white w-full max-w-5xl -lg shadow-xl">

            <!-- Header Section -->
            <div class="w-full border border-black">
                <div class="flex flex-col md:flex-row justify-between items-start">

                    <!-- LEFT SECTION -->
                    <div class="flex-1 p-2">
                        <!-- Logo + Title -->
                        <div class="flex items-center space-x-3">
                            <img src="../img/logo.png" alt="Department of Agriculture Logo"
                                class="h-28 w-28 object-contain filter grayscale">
                            <div class="flex flex-col leading-tight">
                                <h1 class="text-2xl font-bold uppercase text-black">ANI AT KITA</h1>
                                <h1 class="text-3xl font-extrabold uppercase text-black">RSBSA ENROLLMENT FORM</h1>
                                <h2 class="text-sm font-semibold uppercase text-black tracking-wide">
                                    REGISTRY SYSTEM FOR BASIC SECTORS IN AGRICULTURE (RSBSA)
                                </h2>
                            </div>
                        </div>

                        <!-- Enrollment Type & Reference -->
                        <div class="mt-3 text-sm text-black space-y-3">
                            <!-- Enrollment Type -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-3">
                                <span class="font-bold">ENROLLMENT TYPE & DATE ADMINISTERED:</span>
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center space-x-1">
                                        <input type="checkbox" class="w-4 h-4 border border-black">
                                        <span>New</span>
                                    </label>
                                    <label class="flex items-center space-x-1">
                                        <input type="checkbox" class="w-4 h-4 border border-black">
                                        <span>Updating</span>
                                    </label>
                                </div>
                                <!-- Date Administered -->
                                <div class="flex space-x-1 ml-auto mt-2 sm:mt-0">
                                    <input type="text" maxlength="2"
                                        class="w-6 h-6 border border-black text-center text-xs uppercase">
                                    <input type="text" maxlength="2"
                                        class="w-6 h-6 border border-black text-center text-xs uppercase">
                                    <span class="mx-1">/</span>
                                    <input type="text" maxlength="2"
                                        class="w-6 h-6 border border-black text-center text-xs uppercase">
                                    <input type="text" maxlength="2"
                                        class="w-6 h-6 border border-black text-center text-xs uppercase">
                                    <span class="mx-1">/</span>
                                    <input type="text" maxlength="4"
                                        class="w-10 h-6 border border-black text-center text-xs uppercase">
                                </div>
                            </div>

                            <!-- Reference Number -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-3">
                                <span class="font-bold whitespace-nowrap">Reference Number:</span>
                                <div class="flex items-center flex-wrap space-x-1">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <span class="mx-1">-</span>
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <span class="mx-1">-</span>
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                    <input type="text" maxlength="1"
                                        class="w-5 h-5 border border-black text-center text-xs">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SECTION: 2x2 PICTURE BOX -->
                    <div class="border-l-2 border-black flex flex-col justify-center items-center text-center p-4"
                        style="width: 230px; height: 200px;">
                        <div class="font-bold text-lg leading-tight">
                            <p>2x2</p>
                            <p>PICTURE</p>
                        </div>
                        <div class="mt-2 text-xs leading-tight">
                            <p>PHOTO TAKEN</p>
                            <p>WITHIN 6 MONTHS</p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Add "Revised Version" text -->

            <!-- Part I: Personal Information -->
            <div class="w-full border-2 border-black">
                <div class="w-full">
                    <h3 class="text-white font-bold bg-black px-4 py-2 text-lg">PART I: PERSONAL INFORMATION</h3>
                    <div class="grid grid-cols-2 gap-8">
                        <div class=" ">
                            <input type="text"
                                class="block py-1 w-full text-sm text-center text-gray-900 bg-transparent border-0 border-b-2 border-gray-600 appearance-none dark:text-white dark:border-gray-600 focus:outline-none focus:ring-0 peer"
                                placeholder="" /> <label class="flex font-bold justify-center">Surname</label>
                        </div>
                        <div class=" ">
                            <input type="text"
                                class="block py-1 w-full text-sm text-center text-gray-900 bg-transparent border-0 border-b-2 border-gray-600 appearance-none dark:text-white dark:border-gray-600 focus:outline-none focus:ring-0 peer"
                                placeholder="" /> <label class="flex font-bold justify-center">First Name</label>
                        </div>
                    </div>
                </div>
                <!-- Name and Sex Row -->
                <div class="grid grid-cols-2 gap-8">
                    <div class=" ">
                        <input type="text"
                            class="block py-1 w-full text-sm text-center text-gray-900 bg-transparent border-0 border-b-2 border-gray-600 appearance-none dark:text-white dark:border-gray-600 focus:outline-none focus:ring-0 peer"
                            placeholder="" />
                        <label class="flex font-bold justify-center">Middle Name</label>
                    </div>
                    <div class="flex flex-row w-full items-center gap-5">
                        <div class="w-full">
                            <input type="text"
                                class="block py-1 w-full text-sm text-center text-gray-900 bg-transparent border-0 border-b-2 border-gray-600 appearance-none dark:text-white dark:border-gray-600 focus:outline-none focus:ring-0 peer"
                                placeholder="" />
                            <label class="flex font-bold justify-center">Extension Name</label>
                        </div>
                        <div
                            class="flex flex-row items-center h-5 py-4 -mb-5 -mr-0.5 gap-5 justify-center w-full border border-black">
                            <label class="text-sm font-bold ">SEX</label>
                            <div class="flex flex-row gap-5 ">
                                <div class="flex flex-row gap-2 items-center">
                                    <input type="checkbox">
                                    <label class="text-base font-medium">Male</label>
                                </div>
                                <div class="flex flex-row gap-2 items-center">
                                    <input type="checkbox">
                                    <label class="text-base font-medium">Female</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Row -->
                <div class="w-full flex flex-row border-2 border-l-0 border-r-0 border-black">
                    <div class=" pt-2 pl-2">
                        <label class="flex font-bold mt-2.5">ADDRESS</label>
                    </div>
                    <div class="flex flex-col w-full px-2 pt-2 pb-0">
                        <div class="flex flex-row w-full items-center justify-between gap-2 py-2 text-sm">
                            <div class="flex flex-col w-full items-center">
                                <input type="text" class="w-full p-1 border-2 border-gray-300">
                                <label class="font-medium">HOUSE/LOT/BLDG. NO./PUROK</label>
                            </div>
                            <div class="flex flex-col w-full items-center">
                                <input type="text" class="w-full border-2 p-1 border-gray-300">
                                <label class="font-medium">STREET/SITIO/SUBDIV.</label>
                            </div>
                            <div class="flex flex-col w-full items-center">
                                <input type="text" class="w-full border-2 p-1 border-gray-300">
                                <label class="font-medium">BARANGAY</label>
                            </div>
                        </div>
                        <div class="flex flex-row w-full items-center justify-between gap-2 py-2 text-sm">
                            <div class="flex flex-col w-full items-center">
                                <input type="text" class="w-full border-2 p-1 border-gray-300">
                                <label class="font-medium ">MUNICIPALITY/CITY</label>
                            </div>
                            <div class="flex flex-col w-full items-center">
                                <input type="text" class="w-full border-2 p-1 border-gray-300">
                                <label class="font-medium">PROVINCE</label>
                            </div>
                            <div class="flex flex-col w-full items-center">
                                <input type="text" class="w-full border-2 p-1 border-gray-300">
                                <label class="font-medium">REGION</label>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Contact, DOB, Education Section -->
                <div class="flex flex-col md:flex-row w-full">
                    <!-- Left half -->
                    <div class="flex flex-col w-1/2">
                        <div class="flex flex-col w-full border-[1.5px] border-black md:rounded-r-none ">
                            <div class="flex flex-col sm:flex-row items-center px-2  border-b-[2.5px]  border-black">
                                <!-- Mobile Number -->
                                <div class="w-full mb-1">
                                    <label class="text-sm font-bold">MOBILE NUMBER</label>
                                    <div class="flex flex-wrap ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box ">
                                    </div>
                                </div>
                                <!-- Landline Number -->
                                <div class="w-[350px] mb-1">
                                    <label class="text-sm font-bold">LANDLINE NUMBER</label>
                                    <div class="flex flex-wrap ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box ">
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col w-full sm:flex-row ">
                                <!-- Date of Birth -->
                                <div class="flex flex-row w-full items-center px-2 border-r-[2.5px] border-black">
                                    <div class="flex flex-col  w-full">
                                        <label class="text-sm font-bold">DATE OF BIRTH</label>
                                        <div class="flex flex-wrap items-center">
                                            <!-- MM -->
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1"
                                                    class="form-input-box border-r-0 w-7 h-7">
                                                <label class="text-[14px] font-medium">M</label>
                                            </div>
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1"
                                                    class="form-input-box border-r-0 w-7 h-7">
                                                <label class="text-[14px] font-medium">M</label>
                                            </div>

                                            <!-- DD -->
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1"
                                                    class="form-input-box border-r-0 w-7 h-7">
                                                <label class="text-[14px] font-medium">D</label>
                                            </div>
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1"
                                                    class="form-input-box border-r-0 w-7 h-7">
                                                <label class="text-[14px] font-medium">D</label>
                                            </div>

                                            <!-- YYYY -->
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1"
                                                    class="form-input-box border-r-0 w-7 h-7">
                                                <label class="text-[14px] font-medium">Y</label>
                                            </div>
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1"
                                                    class="form-input-box border-r-0 w-7 h-7">
                                                <label class="text-[14px] font-medium">Y</label>
                                            </div>
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1"
                                                    class="form-input-box border-r-0 w-7 h-7">
                                                <label class="text-[14px] font-medium">Y</label>
                                            </div>
                                            <div class="flex flex-col items-center">
                                                <input type="text" maxlength="1" class="form-input-box w-7 h-7">
                                                <label class="text-[14px] font-medium">Y</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-row px-2 w-full">
                                    <!-- Place of Birth -->
                                    <div class="flex flex-col w-full ">
                                        <label class="text-sm font-bold">PLACE OF BIRTH</label>
                                        <div class="flex flex-col border-1 ">
                                            <div class="w-full">
                                                <input type="text"
                                                    class="block h-5 w-full text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                                    placeholder=" ">
                                                <label
                                                    class="flex font-medium justify-center text-[12px]">MUNICIPALITY</label>
                                            </div>
                                            <div class="flex flex-row gap-2 ">
                                                <div class="flex flex-col">
                                                    <input type="text"
                                                        class="block h-5 w-full text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                                        placeholder=" ">
                                                    <label
                                                        class="flex font-medium justify-center text-[12px]">PROVINCE</label>
                                                </div>
                                                <div class="flex flex-col">
                                                    <input type="text"
                                                        class="block h-5 w-full text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                                        placeholder=" ">
                                                    <label
                                                        class="flex font-medium justify-center text-[12px]">COUNTRY</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col w-full border-[1.5px]  border-black md:rounded-r-none ">
                            <!-- Civil Status and Religion -->
                            <div class="p-2 border-b-[1.5px] border-l-0 border-0  border-black">
                                <div class="flex flex-row  py-2 items-center gap-1">
                                    <label class="font-bold">RELIGION:</label>
                                    <div class="flex flex-row items-center gap-4 justify-between w-full text-sm">
                                        <div class="flex flex-row gap-1 items-center ">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <label class="flex items-center ">Christianity</label>
                                        </div>
                                        <div class="flex flex-row gap-1 items-center ">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <label class="flex items-center ">Islam</label>
                                        </div>
                                        <div class="flex flex-row gap-1 items-center ">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <div class="flex flex-row gap-1 ">
                                                <label class="flex items-center w-full">Others, specify</label>
                                                <input type="text"
                                                    class="border-b-[2.5px] h-5 w-20 border-gray-600 appearance-none focus:outline-none focus:ring-0 peer">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-row py-2 w-full items-center gap-1">
                                    <label class="font-bold w-[160px] ">CIVIL STATUS:</label>
                                    <div class="flex flex-row items-center gap-4  w-full justify-between text-sm">
                                        <div class="flex flex-row gap-1 items-center ">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <label class="flex items-center ">Single</label>
                                        </div>
                                        <div class="flex flex-row gap-1 items-center ">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <label class="flex items-center ">Maried</label>
                                        </div>
                                        <div class="flex flex-row gap-1 items-center ">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <label class="flex items-center ">Widowed</label>
                                        </div>
                                        <div class="flex flex-row gap-1 items-center ">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <label class="flex items-center ">Separated</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col mt-2">
                                    <label class="font-bold">NAME OF SPOUSE</label>
                                    <div class="flex flex-row w-full  ">
                                        <label class="font-bold w-[128px] mr-8">IF MARIED:</label>
                                        <input type="text"
                                            class="block w-full h-5 text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                            placeholder=" ">
                                    </div>
                                </div>
                            </div>

                            <!-- Name of Spouse & Mother's Maiden Name -->
                            <div
                                class="flex flex-row w-full p-2 border-[1.5px] border-l-0 border-r-0 border-black md:rounded-r-none ">
                                <div class="flex flex-row ">
                                    <div class="flex flex-col ">
                                        <label class="font-bold">MOTHER'S</label>
                                        <div class="flex flex-row w-full ">
                                            <label class="font-bold w-full">MAIDEN NAME:</label>
                                            <input type="text"
                                                class="block ml-[-179px] w-[540px] h-5 text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                                placeholder=" ">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Household Head -->
                            <div
                                class="flex flex-col w-full border-[1.5px] border-b-0 border-l-0 border-r-0 border-black pb-3">
                                <!-- Household Head -->
                                <div class="flex flex-row items-center ml-2 mb-1">
                                    <label class="font-bold text-sm">HOUSEHOLD HEAD?</label>
                                    <div class="flex flex-row items-center gap-4 text-sm ml-10">
                                        <label class="flex items-center gap-1">
                                            <input type="checkbox" class="h-4 w-4 border border-black rounded-sm">
                                            <span>Yes</span>
                                        </label>
                                        <label class="flex items-center gap-1">
                                            <input type="checkbox" class="h-4 w-4 border border-black rounded-sm">
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Name of Household Head -->
                                <div class="flex flex-row items-center ml-4 mb-1">
                                    <p class="text-sm">If no, name of household head:</p>
                                    <input type="text"
                                        class="block w-[262px] ml-2 h-4 text-center border-0 border-b-2 border-gray-600 focus:outline-none focus:ring-0"
                                        placeholder="" />
                                </div>

                                <!-- Relationship -->
                                <div class="flex flex-row items-center ml-4 mb-1">
                                    <p class="text-sm ml-[112px]">Relationship:</p>
                                    <input type="text"
                                        class="block w-[262px] ml-2 h-4 text-center border-0 border-b-2 border-gray-600 focus:outline-none focus:ring-0"
                                        placeholder="" />
                                </div>

                                <!-- Number of living household members -->
                                <div class="flex flex-row items-center ml-4 mb-1">
                                    <p class="text-sm">No. of living household members:</p>
                                    <input type="text"
                                        class="block w-[200px] ml-2 h-4 text-center border-0 border-b-2 border-gray-600 focus:outline-none focus:ring-0"
                                        placeholder="" />
                                </div>

                                <!-- Male/Female count -->
                                <div class="flex flex-row justify-between ml-4 pr-6">
                                    <div class="flex flex-row items-center">
                                        <p class="text-sm">No. of male:</p>
                                        <input type="text"
                                            class="block w-[100px] ml-2 h-4 text-center border-0 border-b-2 border-gray-600 focus:outline-none focus:ring-0"
                                            placeholder="" />
                                    </div>
                                    <div class="flex flex-row items-center">
                                        <p class="text-sm mr-2">No. of female:</p>
                                        <input type="text"
                                            class="block w-[100px] h-4 text-center border-0 border-b-2 border-gray-600 focus:outline-none focus:ring-0"
                                            placeholder="" />
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- Right Section -->
                    <div class="flex flex-col w-1/2">
                        <div class="w-full p-2 border-[1.5px] border-black">
                            <label class="text-base font-bold block mb-1">HIGHEST FORMAL EDUCATION:</label>

                            <div class="grid grid-cols-3 text-sm leading-tight">
                                <!-- Column 1 -->
                                <div class="space-y-1">
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>Pre-school</span>
                                    </label>
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>Elementary</span>
                                    </label>
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>High School (non K-12)</span>
                                    </label>
                                </div>

                                <!-- Column 2 -->
                                <div class="space-y-1 ml-2.5 ">
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black gap-4">
                                        <span>Junior High School (K-12)</span>
                                    </label>
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>Senior High School (K-12)</span>
                                    </label>
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>College</span>
                                    </label>
                                </div>

                                <!-- Column 3 -->
                                <div class="space-y-1 ml-10">
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>Vocational</span>
                                    </label>
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>Post-Graduate</span>
                                    </label>
                                    <label class="flex items-center gap-1 whitespace-nowrap">
                                        <input type="checkbox" class="h-4 w-4 border border-black">
                                        <span>None</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="w-full flex flex-row p-2 py-3 gap-4  border-[1.5px]  border-black">
                            <div class="">
                                <label class="text-base font-bold">PERSON WITH DISABILITY (PWD)?</label>
                            </div>
                            <div class="flex flex-row gap-4">
                                <div class="flex flex-row gap-4">
                                    <div class="w-full flex items-center flex-row gap-2">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <span>Yes</span>
                                        </label>
                                    </div>
                                    <div class="flex flex-row items-center gap-4">
                                        <label class="flex gap-2 items-center">
                                            <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Member of an Indigenous Group? -->
                        <div class="flex flex-col w-full border-[1.5px]  border-black md:rounded-l-none md:mt-0">
                            <div
                                class="w-full flex flex-row p-2 py-1 gap-2 items-center border-[1.5px] border-t-0 border-l-0 border-r-0 border-b-0 border-black">
                                <div class="">
                                    <label class="text-sm font-bold">4P's Beneficiary?</label>
                                </div>
                                <div class="flex flex-row gap-4 ml-[143px]">
                                    <div class="flex flex-row gap-4">
                                        <div class="w-full flex items-center flex-row gap-2">
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                <span>Yes</span>
                                            </label>
                                        </div>
                                        <div class="flex flex-row items-center gap-4">
                                            <label class="flex gap-2 items-center">
                                                <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                <span>No</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="w-full flex flex-row p-2 py-1 gap-4 items-center border-[1.5px] border-t-0 border-l-0 border-r-0 border-b-0 border-black">
                                <div class="">
                                    <label class="text-sm font-normal">Member of an </label> <label
                                        class="text-sm font-bold">Indigenous Group?</label>
                                </div>
                                <div class="flex flex-row gap-4 ml-[40px]">
                                    <div class="flex flex-row gap-4">
                                        <div class="w-full flex items-center flex-row gap-2">
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                <span>Yes</span>
                                            </label>
                                        </div>
                                        <div class="flex flex-row items-center gap-4">
                                            <label class="flex gap-2 items-center">
                                                <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                <span>No</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="w-full flex flex-col py-1 border-[1.5px] border-t-0 border-r-0  border-l-0 border-black">
                                <div class="flex flex-row ml-[10px]">
                                    <div class="flex flex-row">
                                        <div class="flex flex-row w-full">
                                            <p class="text-xs italic w-[130px] ">If yes, specify:</p>
                                            <input type="text"
                                                class="ml-[-60px] block w-[372px] h-4 border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                                placeholder=" ">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- With Government ID? -->
                            <div
                                class="w-full flex flex-row p-2 py-1 gap-4 items-center border-[1.5px]  border-l-0 border-r-0 border-b-0 border-black">
                                <div class="">
                                    <label class="text-sm font-normal">With</label> <label
                                        class="text-sm font-bold">Governement
                                        ID:</label>
                                </div>
                                <div class="flex flex-row gap-4 ml-[20px]">
                                    <div class="flex flex-row gap-4">
                                        <div class="w-full flex items-center flex-row gap-2">
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                <span>Yes</span>
                                            </label>
                                        </div>
                                        <div class="flex flex-row items-center gap-4">
                                            <label class="flex gap-2 items-center">
                                                <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                <span>No</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="w-full flex flex-col  border-[1.5px] border-t-0 border-r-0 border-l-0 border-black">
                                <div class="flex flex-row ml-[10px]">
                                    <div class="flex flex-col">
                                        <div class="flex flex-row w-full">
                                            <p class="text-sm w-[150px] ml-[3px]">If yes, specify ID Type:</p>
                                            <input type="text"
                                                class="block w-[290px] h-4 text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer "
                                                placeholder=" ">
                                        </div>
                                        <div class="flex flex-row py-1">
                                            <label class="text-sm font-bold w-[90px] ml-[62px]">ID Number:</label>
                                            <input type="text"
                                                class="block w-[292px] h-4 border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer "
                                                placeholder=" ">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Member of any Farmers Association/Cooperative? -->
                            <div
                                class="w-full flex flex-col border-[1.5px] border-r-0 border-b-0 border-l-0  border-black">
                                <div class="flex flex-row p-2 border-b-0 border-black gap-1">
                                    <label class="font-normal">Member of any </label><label class="font-bold ">Farmers
                                        Association/Cooperative?</label>
                                    <div class="flex flex-row ml-[10px]">
                                        <div class="flex flex-row">
                                            <div class="flex flex-row items-center gap-4 text-sm">
                                                <label class="flex items-center gap-1">
                                                    <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                    <span>Yes</span></label>
                                                <label class="flex items-center gap-1">
                                                    <input type="checkbox" class="h-4 w-4 rounded-sm border-black">
                                                    <span>No</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="w-full flex flex-col border-[1.5px] py-1 border-l-0 border-t-0 border-r-0  border-black">
                                <div class="flex flex-col ml-[10px]">
                                    <div class="flex flex-row">
                                        <div class="flex flex-row ">
                                            <p class="text-sm w-[90px]">If yes, specify:</p>
                                            <input type="text"
                                                class="ml-[-10px] block w-[365px] h-4 text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer "
                                                placeholder=" ">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Person to Notify in Case of Emergency -->
                            <div
                                class="w-full flex flex-col p-2 border-[1.5px] py-1 border-l-0  border-r-0 border-black">
                                <div class="flex flex-col ">
                                    <label class="text-xs font-bold">PERSON TO NOTIFY IN</label>
                                    <div class="flex flex-row ">
                                        <label class="text-xs font-bold w-[190px]">CASE OF
                                            EMERGENCY:</label>
                                        <input type="text"
                                            class="ml-[-40px] block w-[295px] h-4 mt-1 text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                            placeholder=" ">
                                    </div>
                                </div>
                                <div class="flex flex-row mt-2">
                                    <label class="text-s font-bold w-[185px]">MOBILE NUMBER:</label>
                                    <div class="flex flex-wrap ml-[-34px] w-[270px]">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box border-r-0 ">
                                        <input type="text" maxlength="1" class="form-input-box ">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ================= PART II: FARM PROFILE ================= -->
            <div class="section border-2 border-black border-b-0">
                <!-- Section Header -->
                <div class="section-header">
                    <h3
                        class="text-white font-bold border-[1.5px] border-r-0 border-l-0 border-b-0 border-t-0 bg-black px-4 py-2 text-lg">
                        PART II: FARM PROFILE
                    </h3>
                </div>

                <!-- Section Body -->
                <div class="section-body border-[1.5px] border-r-0 border-l-0 border-black text-sm">
                    <div
                        class="w-full flex flex-wrap justify-between p-2 mr-10 border-[1.5px] border-black border-b-0 border-l-0 border-r-0">
                        <label for="" class="font-bold">MAIN LIVELIHOOD</label>
                        <label class="flex items-center font-bold"><input type="checkbox"
                                class="mr-2 h-4 w-4 rounded-sm border-black">
                            <span>FARMER</span></label>
                        <label class="flex items-center font-bold"><input type="checkbox"
                                class="mr-2 h-4 w-4 rounded-sm border-black">
                            <span>FARMWORKER/LABORER</span></label>
                        <label class="flex items-center font-bold"><input type="checkbox"
                                class="mr-2 h-4 w-4 rounded-sm border-black">
                            <span>FISHERFOLK</span></label>
                        <label class="flex items-center font-bold"><input type="checkbox"
                                class="mr-2 h-4 w-4 rounded-sm border-black">
                            <span>AGRI-YOUTH</span></label>
                    </div>

                    <!-- Grid Sections -->
                    <div
                        class="grid grid-cols-1 md:grid-cols-4 border-[2.5px] border-r-0 border-l-0 border-black text-sm">
                        <!-- For Farmers -->
                        <div class="p-2 border-r-[2.5px] border-black py-2 gap-4">
                            <div class="flex flex-col w-full gap-4">
                                <em class="font-italic underline font-semibold flex justify-center">For Farmers:</em>
                                <label class="flex items-center font-bold">Type of Farming Activity</label>
                                <div class="flex flex-col gap-4">
                                    <label class="flex items-center "><input type="checkbox"
                                            class="mr-1 h-4 w-4 rounded-sm">Rice</label>
                                    <label class="flex items-center"><input type="checkbox"
                                            class="mr-1 h-4 w-4 rounded-sm">Corn</label>
                                </div>
                                <div class="flex flex-row"><input type="checkbox" class="mr-1 h-4 w-4 rounded-sm">
                                    <label class="flex w-[90px]">Other Crops, please specify:</label>
                                    <input type="text"
                                        class="ml-[-5px] w-[115px] border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer">
                                </div>
                                <div class="flex flex-row"><input type="checkbox" class="mr-1 h-4 w-4 rounded-sm">
                                    <label class="flex w-[90px]">Livestock, please specify:</label>
                                    <input type="text"
                                        class="ml-[-5px] w-[115px] border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer">
                                </div>
                                <div class="flex flex-row"><input type="checkbox" class="mr-1 h-4 w-4 rounded-sm">
                                    <div class="flex flex-col">
                                        <label class="flex w-[10px]">Poultry,</label>
                                        <div class="flex flex-col">
                                            <label class="flex flex-col">please specify:</label>
                                            <input type="text"
                                                class="ml-[85px] w-[115px] h-1 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- For Farmworkers -->
                        <div class="p-2 border-r-[2.5px] border-black">
                            <div class="flex flex-col gap-5">
                                <em class="font-italic underline font-semibold flex justify-center">For
                                    Farmworkers:</em>
                                <label class="flex items-center font-bold">Kind of Work</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Land
                                    Preparation</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Planting/Transplanting</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Cultivation</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Harvesting</label>
                                <div class="flex flex-row"><input type="checkbox" class="mr-1 h-4 w-4 rounded-sm">
                                    <label class="flex w-[200px]">Others, please specify:</label>
                                </div>
                                <div class="flex flex-col">
                                    <input type="text"
                                        class="w-[220px] ml-1 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer">
                                </div>
                            </div>
                        </div>

                        <!-- For Fisherfolk -->
                        <div class="p-2 border-r-[2.5px] border-black">
                            <em class="font-italic underline font-semibold flex justify-center">For Fisherfolk:</em>
                            <p class="text-xs mt-2">The Lending Conduit shall coordinate with the Bureau of Fisheries
                                and Aquatic Resources
                                (BFAR) in the issuance of a certification that the fisherfolk-borrower under PUNLA/PLEA
                                is registered under
                                the Municipal Registration (FishR).</p>
                            <label class="block mt-4 font-bold">Type of Fishing Activity</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Fish
                                    Capture</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Fish
                                    Processing</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Aquaculture</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Fish Vending</label>
                                <label class="flex items-center"><input type="checkbox"
                                        class="mr-1 h-4 w-4 rounded-sm">Gleaning</label>
                            </div>
                            <div class="flex flex-row mt-2"><input type="checkbox" class="mr-1 h-4 w-4 rounded-sm">
                                <label class="flex w-[90px]">Others, please specify:</label>
                            </div>
                            <div class="flex flex-col">
                                <input type="text"
                                    class="w-[220px] ml-1 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer">
                            </div>
                        </div>

                        <!-- For Agri-Youth -->
                        <div class="p-2">
                            <em class="font-italic underline font-semibold flex justify-center">For Agri-Youth:</em>
                            <p class="text-xs mt-2">For the purposes of trainings, financial assistance, and other
                                programs catered to the
                                youth with involvement to any agricultural activity.</p>
                            <label class="block mt-4 font-bold">Type of Involvement:</label>
                            <div class="flex flex-col">
                                <div class="mb-2"><label class="flex "><input type="checkbox"
                                            class="mr-1 h-4 w-4 rounded-sm">Part of a
                                        farming household</label></div>
                                <div class="flex"><label class="flex"><input type="checkbox"
                                            class="mr-1 h-5 w-[23px] rounded-sm">attending/attended
                                        formal agri-fishery related course</label></div>
                                <div class="flex"><label class="flex"><input type="checkbox"
                                            class="mr-1 h-5 w-[25.5px] rounded-sm">attending/attended
                                        non-formal agri-fishery related course</label></div>
                                <div class="flex"><label class="flex "><input type="checkbox"
                                            class="mr-1 h-5 w-[21px] rounded-sm">participated
                                        in any agricultural activity/program</label></div>
                                <div class="flex flex-row"><input type="checkbox" class="mr-1 h-4 w-4 rounded-sm">
                                    <label class="flex w-[90px]">Others, specify:</label>
                                </div>
                                <div class="flex flex-col">
                                    <input type="text"
                                        class="w-[220px] ml-1 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Income Section -->
                    <div class="flex flex-row justify-between">
                        <div class="flex flex-row p-2"><label class="block font-bold">Gross Annual Income Last
                                Year:</label></div>
                        <div class="flex flex-row p-2"><label class="block">Farming:</label><input type="text"
                                class="h-4 mt-1 text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                placeholder=" "></div>
                        <div class="flex flex-row p-2"><label class="block">Non-Farming:</label><input type="text"
                                class="h-4 mt-1 text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                placeholder=" "></div>
                    </div>
                </div>
            </div>

            <!-- Dashed divider -->
            <div class="w-full mt-4 border-t-2 border-black border-dashed"></div>

            <!-- ================= FOOTER: CLIENT'S COPY ================= -->
            <div class="footer-section w-full mt-4 border-[2.5px] border-black">
                <div>
                    <p class="mt-4 font-bold text-center">Registry System for Basic Sector in Agriculture
                        (RSBSA)<br>ENROLLMENT CLIENT'S COPY</p>

                    <div
                        class="flex flex-col sm:flex-row items-start sm:items-center w-full p-2 sm:space-y-0 sm:space-x-4 mt-4">
                        <em class="font-normal whitespace-nowrap">Reference Number:</em>

                        <!-- Reference Number Input Boxes -->
                        <div class="flex flex-wrap flex-grow space-x-1">
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <span class="mx-1">-</span>
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <span class="mx-1">-</span>
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                            <input type="text" maxlength="1" class="form-input-box">
                        </div>
                    </div>

                    <div class="flex flex-col w-full mb-2">
                        <div class="flex flex-row justify-between">
                            <div>
                                <input type="text"
                                    class="block py-1 w-[460px] text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                    placeholder=" ">
                                <label class="flex font-bold justify-center text-xs">Surname:</label>
                            </div>
                            <div>
                                <input type="text"
                                    class="block py-1 w-[460px] text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                    placeholder=" ">
                                <label class="flex font-bold justify-center text-xs">First Name</label>
                            </div>
                        </div>

                        <div class="flex flex-row justify-between">
                            <div>
                                <input type="text"
                                    class="block py-1 w-[460px] text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                    placeholder=" ">
                                <label class="flex font-bold justify-center text-xs">Middle Name</label>
                            </div>
                            <div class="mr-[265px]">
                                <input type="text"
                                    class="block py-1 w-full text-center border-0 border-b-[2.5px] border-gray-600 appearance-none focus:outline-none focus:ring-0 peer"
                                    placeholder=" ">
                                <label class="flex font-bold justify-center text-xs">Extension Name</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-center font-bold text-lg">THIS FORM IS NOT FOR SALE</p>

        </div>
        <div class="button-container">
            <button class="btn" onclick="window.print()">PRINT</button>
            <button class="btn btn-close" onclick="window.close()">CLOSE</button>
        </div>
</body>

</html>