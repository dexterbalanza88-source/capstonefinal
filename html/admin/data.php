<?php
include '../../db/conn.php';
?>

<main class="md:ml-64 pt-20 p-4 w-full">
  <div class="relative h-screen overflow-y-auto">
    <!-- CARD CONTAINER -->
    <div class="bg-white/70 backdrop-blur-lg rounded-2xl shadow p-6">
      <h1 class="text-2xl font-semibold mb-4">👩‍🌾 Farmers Data List</h1>

      <!-- Tabs -->
      <div class="flex border-b mb-6">
        <button id="tab-new" 
          class="px-4 py-2 font-semibold border-b-2 border-blue-600 text-blue-600 focus:outline-none">
          🆕 New Farmers
        </button>
        <button id="tab-registered" 
          class="px-4 py-2 font-semibold text-gray-500 hover:text-blue-600 focus:outline-none">
          ✅ Registered Farmers
        </button>
      </div>

      <!-- ✅ New Farmers Table -->
      <div id="newFarmersTable" class="block">
        <?php
        $queryNew = "SELECT * FROM registration_form WHERE LOWER(status) = 'pending'";
        $resultNew = mysqli_query($conn, $queryNew);
        ?>
        <table class="min-w-full text-sm text-left">
          <thead>
            <tr class="bg-blue-100 text-gray-700 uppercase text-xs">
              <th class="px-4 py-2">Ref. No</th>
              <th class="px-4 py-2">Name</th>
              <th class="px-4 py-2">Barangay</th>
              <th class="px-4 py-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($resultNew)): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2"><?= htmlspecialchars($row['reference']); ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['f_name'] . " " . $row['s_name']); ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['brgy']); ?></td>
                <td class="px-4 py-2 text-yellow-600 font-medium"><?= htmlspecialchars($row['status']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- ✅ Registered Farmers Table -->
      <div id="registeredFarmersTable" class="hidden">
        <?php
        $queryReg = "SELECT * FROM registration_form WHERE LOWER(status) = 'registered'";
        $resultReg = mysqli_query($conn, $queryReg);
        ?>
        <table class="min-w-full text-sm text-left">
          <thead>
            <tr class="bg-green-100 text-gray-700 uppercase text-xs">
              <th class="px-4 py-2">Ref. No</th>
              <th class="px-4 py-2">Name</th>
              <th class="px-4 py-2">Barangay</th>
              <th class="px-4 py-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($resultReg)): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2"><?= htmlspecialchars($row['reference_no']); ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['first_name'] . " " . $row['surname']); ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['barangay']); ?></td>
                <td class="px-4 py-2 text-green-600 font-medium"><?= htmlspecialchars($row['status']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Tab Switch Script -->
<script>
const tabNew = document.getElementById('tab-new');
const tabReg = document.getElementById('tab-registered');
const newTable = document.getElementById('newFarmersTable');
const regTable = document.getElementById('registeredFarmersTable');

tabNew.addEventListener('click', () => {
  newTable.classList.remove('hidden');
  regTable.classList.add('hidden');
  tabNew.classList.add('border-blue-600', 'text-blue-600');
  tabReg.classList.remove('border-blue-600', 'text-blue-600');
  tabReg.classList.add('text-gray-500');
});

tabReg.addEventListener('click', () => {
  regTable.classList.remove('hidden');
  newTable.classList.add('hidden');
  tabReg.classList.add('border-blue-600', 'text-blue-600');
  tabNew.classList.remove('border-blue-600', 'text-blue-600');
  tabNew.classList.add('text-gray-500');
});
</script>
