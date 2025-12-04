<?php
$enumerator_id = $_SESSION['enumerator_id'] ?? 1;
$enumerator_name = $_SESSION['enumerator_name'] ?? "Enumerator 1";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Enumerator Data Collection</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-green-50 min-h-screen">

  <header class="bg-green-800 text-white rounded-b-lg shadow-md py-4 px-8 flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold">Enumerator Data Viewer</h1>
      <p class="text-sm mt-1">Logged in as: <span class="font-semibold">Enumerator 1</span></p>
    </div>
    <div class="flex items-center gap-3">
      <span id="statusBadge"
        class="bg-gray-500 text-white text-sm px-3 py-1 rounded-full font-semibold shadow">Offline</span>

      <!-- ✅ Sign Out Button -->
      <button id="signOutBtn" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded shadow transition">
        🚪 Sign Out
      </button>
    </div>
  </header>

  <!-- MAIN FORM CONTAINER -->
  <main class="px-4 mt-5 pb-12">
    <form id="dataForm"
      class="bg-white shadow-lg rounded-2xl p-6 md:p-8 max-w-4xl mx-auto space-y-6 border border-green-200">

      <!-- Section -->
      <section>
        <h2 class="text-xl font-bold text-green-700 border-b-2 border-green-600 pb-1 mb-4">Personal Information</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="block text-sm font-semibold mb-1">Date:</label><input type="date" name="date"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Reference No:</label><input type="text" name="reference"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Surname:</label><input type="text" name="s_name" required
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">First Name:</label><input type="text" name="f_name"
              required class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Middle Name:</label><input type="text" name="m_name"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Extension:</label><input type="text" name="e_name"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Gender:</label>
            <select name="gender" class="w-full border rounded-lg p-2">
              <option value="">Select</option>
              <option>Male</option>
              <option>Female</option>
            </select>
          </div>
          <div><label class="block text-sm font-semibold mb-1">Date of Birth:</label><input type="date" name="dob"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Mobile No:</label><input type="text" name="mobile"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Landline:</label><input type="text" name="landline"
              class="w-full border rounded-lg p-2"></div>
        </div>
      </section>

      <!-- Address -->
      <section>
        <h2 class="text-xl font-bold text-green-700 border-b-2 border-green-600 pb-1 mb-4">Address</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="block text-sm font-semibold mb-1">House No.:</label><input type="text" name="house"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Sitio:</label><input type="text" name="sitio"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Barangay:</label><input type="text" name="brgy"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Municipality:</label><input type="text" name="municipal"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Province:</label><input type="text" name="province"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Region:</label><input type="text" name="region"
              class="w-full border rounded-lg p-2"></div>
        </div>
      </section>

      <!-- Family Info -->
      <section>
        <h2 class="text-xl font-bold text-green-700 border-b-2 border-green-600 pb-1 mb-4">Family Information</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="block text-sm font-semibold mb-1">Mother’s Maiden Name:</label>
            <input type="text" name="mother_maiden" class="w-full border rounded-lg p-2">
          </div>
          <div><label class="block text-sm font-semibold mb-1">Household Head:</label>
            <select name="household_head" class="w-full border rounded-lg p-2">
              <option value="">Select</option>
              <option>Yes</option>
              <option>No</option>
            </select>
          </div>
          <div><label class="block text-sm font-semibold mb-1">If Not, Relationship:</label><input type="text"
              name="relationship" class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">No. of Living Household:</label><input type="number"
              name="no_livinghousehold" class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">No. of Male:</label><input type="number" name="no_male"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">No. of Female:</label><input type="number"
              name="no_female" class="w-full border rounded-lg p-2"></div>
        </div>
      </section>

      <!-- Education & Work -->
      <section>
        <h2 class="text-xl font-bold text-green-700 border-b-2 border-green-600 pb-1 mb-4">Education & Work</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="block text-sm font-semibold mb-1">Highest Education:</label><input type="text"
              name="education" class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">PWD:</label>
            <select name="pwd" class="w-full border rounded-lg p-2">
              <option value="">Select</option>
              <option>Yes</option>
              <option>No</option>
            </select>
          </div>
          <div><label class="block text-sm font-semibold mb-1">Non-Farming Source:</label><input type="text"
              name="non_farming" class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Farming Source:</label><input type="text" name="farming"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">With Government ID:</label>
            <select name="with_gov" class="w-full border rounded-lg p-2">
              <option value="">Select</option>
              <option>Yes</option>
              <option>No</option>
            </select>
          </div>
          <div><label class="block text-sm font-semibold mb-1">Specify ID:</label><input type="text" name="specify_id"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">ID No.:</label><input type="text" name="id_no"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Main Livelihood:</label><input type="text"
              name="main_livelihood" placeholder="Farmer, Fisherfolk, etc." class="w-full border rounded-lg p-2"></div>
        </div>
      </section>

      <!-- Farm Details -->
      <section>
        <h2 class="text-xl font-bold text-green-700 border-b-2 border-green-600 pb-1 mb-4">Farm Details</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="block text-sm font-semibold mb-1">No. of Farm Parcels:</label><input type="number"
              name="no_farmparcel" class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Total Farm Area (ha):</label><input type="text"
              name="total_farmarea" class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Ownership:</label><input type="text" name="ownership"
              class="w-full border rounded-lg p-2"></div>
          <div><label class="block text-sm font-semibold mb-1">Agrarian Reform Beneficiary:</label>
            <select name="agrarian" class="w-full border rounded-lg p-2">
              <option value="">Select</option>
              <option>Yes</option>
              <option>No</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Buttons -->
      <div class="flex flex-wrap gap-3 justify-between mt-6">
        <button type="submit"
          class="bg-green-700 hover:bg-green-800 text-white font-semibold px-5 py-2 rounded-lg shadow">
          Save Locally
        </button>
        <button type="button" id="viewList"
          class="bg-gray-700 hover:bg-gray-800 text-white font-semibold px-5 py-2 rounded-lg shadow">
          View Saved List
        </button>
      </div>
    </form>
  </main>

  <script>
    document.getElementById('signOutBtn')?.addEventListener('click', () => {
      if (confirm("Are you sure you want to sign out?")) {
        alert("✅ You have been signed out. You can still log in offline later.");
        window.location.href = "enumerator_login.html";
      }
    });

    const enumeratorId = "<?php echo $enumerator_id; ?>";
    const enumeratorName = "<?php echo addslashes($enumerator_name); ?>";
    const form = document.getElementById('dataForm');

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      data.enumerator_id = enumeratorId;
      data.enumerator_name = enumeratorName;
      data.date_collected = new Date().toISOString();

      const existing = JSON.parse(localStorage.getItem('rsbsaData') || '[]');
      existing.push(data);
      localStorage.setItem('rsbsaData', JSON.stringify(existing));

      alert("✅ Data saved locally!");
      form.reset();
    });

    document.getElementById('viewList').addEventListener('click', () => {
      window.location.href = 'enumerator_view.php';
    });

    function updateStatus() {
      if (navigator.onLine) {
        statusBadge.textContent = "🟢 Online";
        statusBadge.className = "bg-green-600 text-white text-sm px-3 py-1 rounded-full font-semibold shadow";
        syncBtn.disabled = false;
      } else {
        statusBadge.textContent = "🔴 Offline";
        statusBadge.className = "bg-gray-500 text-white text-sm px-3 py-1 rounded-full font-semibold shadow";
        syncBtn.disabled = true;
      }
    }
    window.addEventListener("online", updateStatus);
    window.addEventListener("offline", updateStatus);
    updateStatus();
  </script>
</body>

</html>