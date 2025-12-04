
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Import Enumerator Data</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

  <!-- Header -->
  <header class="bg-green-800 text-white p-4 flex justify-between items-center shadow-md">
    <h1 class="text-xl font-bold">Admin Panel - Import Enumerator Data</h1>
    <a href="dashboard.php" class="bg-white text-green-800 px-3 py-1 rounded-md font-semibold hover:bg-gray-200">Back</a>
  </header>

  <!-- Main Content -->
  <main class="flex-grow flex flex-col items-center justify-start p-4 sm:p-8">
    <div class="bg-white w-full max-w-6xl rounded-xl shadow-lg p-6">
      <h2 class="text-2xl font-bold text-green-800 mb-4 text-center">Import RSBSA Data</h2>
      <p class="text-gray-600 text-center mb-6">
        Upload the <strong>rsbsa_data.json</strong> file exported from Enumerator devices to preview and import.
      </p>

      <!-- Upload Form -->
      <div class="flex flex-col sm:flex-row justify-center gap-4 items-center mb-6">
        <input id="jsonFile" type="file" accept=".json"
          class="border rounded-lg p-2 w-full sm:w-2/3 focus:ring-green-600 focus:border-green-600" />
        <button id="previewBtn"
          class="bg-green-700 hover:bg-green-800 text-white px-6 py-2 rounded-lg font-semibold transition">
          Preview Data
        </button>
      </div>

      <!-- Table Preview -->
      <div id="previewContainer" class="hidden mt-8">
        <h3 class="text-lg font-semibold text-gray-700 mb-3">Preview Data</h3>
        <div class="overflow-x-auto border rounded-lg shadow-sm max-h-[400px] overflow-y-scroll">
          <table class="min-w-full border-collapse">
            <thead class="bg-green-700 text-white sticky top-0">
              <tr>
                <th class="px-3 py-2 text-sm">Date</th>
                <th class="px-3 py-2 text-sm">Ref No.</th>
                <th class="px-3 py-2 text-sm">Surname</th>
                <th class="px-3 py-2 text-sm">First Name</th>
                <th class="px-3 py-2 text-sm">Sex</th>
                <th class="px-3 py-2 text-sm">Barangay</th>
                <th class="px-3 py-2 text-sm">Municipality</th>
                <th class="px-3 py-2 text-sm">Province</th>
              </tr>
            </thead>
            <tbody id="previewTableBody" class="text-gray-700"></tbody>
          </table>
        </div>

        <!-- Confirm Import -->
        <div class="flex justify-center mt-6">
          <form id="importForm" method="POST">
            <input type="hidden" name="jsonData" id="jsonData">
            <button type="submit" name="confirmImport"
              class="bg-blue-700 hover:bg-blue-800 text-white px-8 py-2 rounded-lg font-semibold transition">
              Confirm Import to Database
            </button>
          </form>
        </div>
      </div>

      <?php
      // When Confirm Import button is clicked
      if (isset($_POST['confirmImport'])) {
          $jsonData = $_POST['jsonData'] ?? '';

          if (empty($jsonData)) {
              echo "<p class='text-red-600 text-center font-medium mt-6'>⚠️ No data received.</p>";
          } else {
              $data = json_decode($jsonData, true);
              if (json_last_error() !== JSON_ERROR_NONE) {
                  echo "<p class='text-red-600 text-center font-medium mt-6'>❌ Invalid JSON data format.</p>";
              } else {
                  include("../includes/db.php"); // adjust path if needed
                  $successCount = 0;
                  $errorCount = 0;

                  foreach ($data as $row) {
                      $date = $row['date'] ?? '';
                      $ref_no = $row['ref_no'] ?? '';
                      $surname = $row['surname'] ?? '';
                      $firstname = $row['firstname'] ?? '';
                      $middlename = $row['middlename'] ?? '';
                      $extension = $row['extension'] ?? '';
                      $sex = $row['sex'] ?? '';
                      $house = $row['house'] ?? '';
                      $street = $row['street'] ?? '';
                      $barangay = $row['barangay'] ?? '';
                      $municipality = $row['municipality'] ?? '';
                      $province = $row['province'] ?? '';
                      $region = $row['region'] ?? '';

                      $sql = "INSERT INTO farmer_info 
                              (date, reference_no, surname, firstname, middlename, extension, sex, 
                               house_no, street, barangay, municipality, province, region) 
                              VALUES 
                              ('$date', '$ref_no', '$surname', '$firstname', '$middlename', '$extension', '$sex',
                               '$house', '$street', '$barangay', '$municipality', '$province', '$region')";

                      if (mysqli_query($conn, $sql)) {
                          $successCount++;
                      } else {
                          $errorCount++;
                      }
                  }

                  echo "<div class='text-center mt-6'>";
                  echo "<p class='text-green-700 font-semibold'>✅ Successfully imported: $successCount records</p>";
                  if ($errorCount > 0) {
                      echo "<p class='text-red-600 font-semibold'>❌ Failed: $errorCount records</p>";
                  }
                  echo "</div>";
              }
          }
      }
      ?>

    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-green-800 text-white text-center py-3 text-sm">
    © 2025 Municipal Agriculture Office — Admin Import
  </footer>

  <script>
    const previewBtn = document.getElementById('previewBtn');
    const fileInput = document.getElementById('jsonFile');
    const previewContainer = document.getElementById('previewContainer');
    const previewBody = document.getElementById('previewTableBody');
    const jsonDataField = document.getElementById('jsonData');

    previewBtn.addEventListener('click', () => {
      const file = fileInput.files[0];
      if (!file) {
        alert('Please select a JSON file first.');
        return;
      }

      const reader = new FileReader();
      reader.onload = (event) => {
        try {
          const data = JSON.parse(event.target.result);
          if (!Array.isArray(data)) throw new Error("Invalid JSON array");

          previewBody.innerHTML = '';
          data.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td class="border px-3 py-2 text-sm">${row.date || ''}</td>
              <td class="border px-3 py-2 text-sm">${row.ref_no || ''}</td>
              <td class="border px-3 py-2 text-sm">${row.surname || ''}</td>
              <td class="border px-3 py-2 text-sm">${row.firstname || ''}</td>
              <td class="border px-3 py-2 text-sm">${row.sex || ''}</td>
              <td class="border px-3 py-2 text-sm">${row.barangay || ''}</td>
              <td class="border px-3 py-2 text-sm">${row.municipality || ''}</td>
              <td class="border px-3 py-2 text-sm">${row.province || ''}</td>
            `;
            previewBody.appendChild(tr);
          });

          // Show preview container
          previewContainer.classList.remove('hidden');
          jsonDataField.value = JSON.stringify(data);
        } catch (error) {
          alert('Invalid JSON file. Please check the format.');
        }
      };
      reader.readAsText(file);
    });
  </script>

</body>
</html>
