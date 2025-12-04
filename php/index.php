<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RSBSA Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center items-center min-h-screen">

    <div class="w-full max-w-3xl bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-bold mb-4">RSBSA Registration Form</h2>

        <form action="process.php" method="POST" class="space-y-6">
            <!-- Step 1 -->
            <div id="step1">
                <h3 class="font-semibold mb-2">Step 1: Reference & Date</h3>
                <label class="block mb-2">Reference No:</label>
                <input type="text" name="reference" required class="w-full border p-2 rounded">

                <label class="block mb-2">Date:</label>
                <input type="date" name="reg_date" required class="w-full border p-2 rounded">

                <div class="flex justify-end mt-4">
                    <button type="button" onclick="nextStep()" class="px-4 py-2 bg-blue-600 text-white rounded">Next</button>
                </div>
            </div>

            <!-- Step 2 -->
            <div id="step2" style="display:none;">
                <h3 class="font-semibold mb-2">Step 2: Personal Info</h3>
                <input type="text" name="s_name" placeholder="Surname" required class="w-full border p-2 rounded mb-2">
                <input type="text" name="f_name" placeholder="First Name" required class="w-full border p-2 rounded mb-2">
                <input type="text" name="m_name" placeholder="Middle Name" class="w-full border p-2 rounded mb-2">
                <input type="text" name="e_name" placeholder="Extension Name" class="w-full border p-2 rounded mb-2">
                <select name="gender" required class="w-full border p-2 rounded">
                    <option value="">-- Select Gender --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>

                <div class="flex justify-between mt-4">
                    <button type="button" onclick="prevStep()" class="px-4 py-2 bg-gray-500 text-white rounded">Back</button>
                    <button type="button" onclick="nextStep()" class="px-4 py-2 bg-blue-600 text-white rounded">Next</button>
                </div>
            </div>

            <!-- Step 3 -->
            <div id="step3" style="display:none;">
                <h3 class="font-semibold mb-2">Step 3: Address</h3>
                <input type="text" name="house" placeholder="House No." required class="w-full border p-2 rounded mb-2">
                <input type="text" name="sitio" placeholder="Sitio" class="w-full border p-2 rounded mb-2">
                <input type="text" name="brgy" placeholder="Barangay" required class="w-full border p-2 rounded mb-2">

                <div class="flex justify-between mt-4">
                    <button type="button" onclick="prevStep()" class="px-4 py-2 bg-gray-500 text-white rounded">Back</button>
                    <button type="button" onclick="nextStep()" class="px-4 py-2 bg-blue-600 text-white rounded">Next</button>
                </div>
            </div>

            <!-- Step 4 -->
            <div id="step4" style="display:none;">
                <h3 class="font-semibold mb-2">Step 4: Municipality</h3>
                <input type="text" name="municipal" placeholder="Municipality" required class="w-full border p-2 rounded mb-2">

                <div class="flex justify-between mt-4">
                    <button type="button" onclick="prevStep()" class="px-4 py-2 bg-gray-500 text-white rounded">Back</button>
                    <button type="button" onclick="nextStep()" class="px-4 py-2 bg-blue-600 text-white rounded">Next</button>
                </div>
            </div>

            <!-- Step 5 -->
            <div id="step5" style="display:none;">
                <h3 class="font-semibold mb-2">Step 5: Province</h3>
                <input type="text" name="province" placeholder="Province" required class="w-full border p-2 rounded mb-2">

                <div class="flex justify-between mt-4">
                    <button type="button" onclick="prevStep()" class="px-4 py-2 bg-gray-500 text-white rounded">Back</button>
                    <button type="button" onclick="nextStep()" class="px-4 py-2 bg-blue-600 text-white rounded">Next</button>
                </div>
            </div>

            <!-- Step 6 -->
            <div id="step6" style="display:none;">
                <h3 class="font-semibold mb-2">Step 6: Region</h3>
                <input type="text" name="region" placeholder="Region" required class="w-full border p-2 rounded mb-2">

                <div class="flex justify-between mt-4">
                    <button type="button" onclick="prevStep()" class="px-4 py-2 bg-gray-500 text-white rounded">Back</button>
                    <!-- ✅ Final Submit -->
                    <button type="submit" name="submit" class="px-4 py-2 bg-green-600 text-white rounded">Submit</button>
                </div>
            </div>
        </form>
    </div>

<script>
let currentStep = 1;
const totalSteps = 6;

function showStep(step) {
    for (let i = 1; i <= totalSteps; i++) {
        document.getElementById("step" + i).style.display = (i === step) ? "block" : "none";
    }
    currentStep = step;
}

function nextStep() {
    if (currentStep < totalSteps) {
        showStep(currentStep + 1);
    }
}

function prevStep() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
}

window.onload = function() {
    showStep(1);
}
</script>
</body>
</html>
