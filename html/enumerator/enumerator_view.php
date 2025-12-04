<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enumerator Data Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e9fef0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        table {
            border-radius: 10px;
            overflow: hidden;
        }

        .test-mode {
            background-color: #fef3c7;
            border: 2px solid #f59e0b;
        }
    </style>
</head>

<body class="text-gray-800">

    <!-- ✅ Header -->
    <header class="bg-green-800 text-white rounded-b-lg shadow-md py-4 px-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Enumerator Data Viewer</h1>
            <p class="text-sm mt-1">Logged in as: <span class="font-semibold" id="enumeratorName">Enumerator 1</span>
            </p>
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

    <!-- ✅ Main Section -->
    <main class="w-full flex-1 px-8 py-6 space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                📋 Saved Local Data
            </h2>
            <div class="w-full flex gap-2">
                <input id="searchBox" type="text" placeholder="Search by name, barangay, or livelihood..."
                    class="px-3 py-2 border border-green-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-400 w-72">
                <button id="clearData" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded shadow">Clear
                    All</button>
                <button id="testSync" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded shadow">Test
                    Sync</button>
                <button id="syncData" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded shadow">Sync
                    to Server</button>
                <a href="adddata.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">←
                    Back</a>
            </div>
        </div>

        <!-- Test Mode Indicator -->
        <div id="testModeIndicator" class="test-mode hidden p-3 rounded-lg mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-amber-600 font-bold">🧪 TEST MODE ACTIVE</span>
                    <span class="text-amber-700 text-sm">Simulating server response - no data will be sent to actual
                        server</span>
                </div>
                <button id="exitTestMode" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1 rounded text-sm">
                    Exit Test Mode
                </button>
            </div>
        </div>

        <!-- ✅ Table -->
        <div class="bg-white shadow-md rounded-lg overflow-x-auto mt-2">
            <table class="min-w-full text-sm text-gray-700">
                <thead class="bg-green-100 text-green-800 font-semibold">
                    <tr>
                        <th class="border p-2 text-center w-10">#</th>
                        <th class="border p-2">Name</th>
                        <th class="border p-2">Barangay</th>
                        <th class="border p-2">Gender</th>
                        <th class="border p-2">Mobile</th>
                        <th class="border p-2">Livelihood</th>
                        <th class="border p-2">Date Collected</th>
                        <th class="border p-2">Status</th>
                        <th class="border p-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
            <div id="emptyState" class="text-center text-gray-500 py-6 hidden">
                No local records found.
            </div>
        </div>
    </main>

    <!-- ✅ Footer -->
    <footer class="text-center text-gray-600 text-sm py-3">
        Enumerator System © 2025
    </footer>

    <!-- ✅ Script -->
    <script>

        // Check login
        const user = JSON.parse(localStorage.getItem("enumeratorUser") || "null");
        if (!user) {
            alert("⚠️ Please log in first.");
            window.location.href = "enumerator_login.html";
        } else {
            document.getElementById("enumeratorName").textContent = user.name || "Enumerator";
        }

        document.getElementById('signOutBtn')?.addEventListener('click', () => {
            if (confirm("Are you sure you want to sign out?")) {
                alert("✅ You have been signed out. You can still log in offline later.");
                window.location.href = "enumerator_login.html";
            }
        });

        const tableBody = document.getElementById('tableBody');
        const emptyState = document.getElementById('emptyState');
        const searchBox = document.getElementById('searchBox');
        const statusBadge = document.getElementById('statusBadge');
        const syncBtn = document.getElementById('syncData');
        const testSyncBtn = document.getElementById('testSync');
        const clearBtn = document.getElementById('clearData');
        const testModeIndicator = document.getElementById('testModeIndicator');
        const exitTestModeBtn = document.getElementById('exitTestMode');

        let isTestMode = false;

        function updateStatus() {
            if (navigator.onLine) {
                statusBadge.textContent = "🟢 Online";
                statusBadge.className = "bg-green-600 text-white text-sm px-3 py-1 rounded-full font-semibold shadow";
                syncBtn.disabled = false;
                testSyncBtn.disabled = false;
            } else {
                statusBadge.textContent = "🔴 Offline";
                statusBadge.className = "bg-gray-500 text-white text-sm px-3 py-1 rounded-full font-semibold shadow";
                syncBtn.disabled = true;
                testSyncBtn.disabled = true;
            }
        }

        function setTestMode(active) {
            isTestMode = active;
            if (active) {
                testModeIndicator.classList.remove('hidden');
                document.body.classList.add('test-mode');
                syncBtn.disabled = true;
                syncBtn.textContent = "🔄 Sync to Server (Test Mode Active)";
            } else {
                testModeIndicator.classList.add('hidden');
                document.body.classList.remove('test-mode');
                syncBtn.disabled = !navigator.onLine;
                syncBtn.textContent = "🔄 Sync to Server";
            }
        }

        window.addEventListener("online", updateStatus);
        window.addEventListener("offline", updateStatus);
        updateStatus();

        function loadData() {
            const data = JSON.parse(localStorage.getItem('rsbsaData') || '[]');
            const searchTerm = searchBox.value.toLowerCase();

            const filtered = data.filter(item =>
                (item.s_name + ' ' + item.f_name).toLowerCase().includes(searchTerm) ||
                (item.brgy || '').toLowerCase().includes(searchTerm) ||
                (item.main_livelihood || '').toLowerCase().includes(searchTerm)
            );

            tableBody.innerHTML = "";
            if (filtered.length === 0) {
                emptyState.classList.remove('hidden');
                return;
            } else {
                emptyState.classList.add('hidden');
            }

            filtered.forEach((item, i) => {
                const tr = document.createElement('tr');
                tr.classList.add('hover:bg-green-50', 'transition');
                tr.innerHTML = `
                    <td class="border p-2 text-center">${i + 1}</td>
                    <td class="border p-2">${item.s_name ?? ""}, ${item.f_name ?? ""}</td>
                    <td class="border p-2">${item.brgy ?? ""}</td>
                    <td class="border p-2">${item.gender ?? ""}</td>
                    <td class="border p-2">${item.mobile ?? ""}</td>
                    <td class="border p-2">${item.main_livelihood ?? ""}</td>
                    <td class="border p-2">${item.date ?? ""}</td>
                    <td class="border p-2">
                        <span class="px-2 py-1 rounded text-xs font-semibold ${item.synced ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                    }">
                            ${item.synced ? '✅ Synced' : '⏳ Pending'}
                        </span>
                    </td>
                    <td class="border p-2 text-center">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded editBtn" data-index="${i}">Edit</button>
                        <button class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded deleteBtn" data-index="${i}">Delete</button>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
            addActionButtons();
        }

        searchBox.addEventListener('input', loadData);

        function addActionButtons() {
            document.querySelectorAll('.deleteBtn').forEach(btn => {
                btn.addEventListener('click', e => {
                    const index = e.target.dataset.index;
                    deleteRecord(index);
                });
            });
        }

        function deleteRecord(index) {
            const data = JSON.parse(localStorage.getItem('rsbsaData') || '[]');
            data.splice(index, 1);
            localStorage.setItem('rsbsaData', JSON.stringify(data));
            loadData();
            alert("🗑️ Record deleted locally.");
        }

        clearBtn.addEventListener('click', () => {
            if (confirm("Delete all local data?")) {
                localStorage.removeItem('rsbsaData');
                loadData();
                alert("🗑️ All local data cleared.");
            }
        });

        function removeLocalDuplicates(data) {
            const unique = [];
            const seen = new Set();
            for (const record of data) {
                const key = `${record.s_name || ''}-${record.f_name || ''}-${record.brgy || ''}-${record.dob || ''}`.toLowerCase();
                if (!seen.has(key)) {
                    seen.add(key);
                    unique.push(record);
                }
            }
            return unique;
        }

        // Test Sync Function
        testSyncBtn.addEventListener('click', async () => {
            if (!navigator.onLine) {
                alert("⚠️ You are offline. Please connect to the internet to test sync.");
                return;
            }

            let data = JSON.parse(localStorage.getItem('rsbsaData') || '[]');
            if (data.length === 0) {
                alert("⚠️ No local data to test sync.");
                return;
            }

            data = removeLocalDuplicates(data);
            setTestMode(true);

            try {
                testSyncBtn.disabled = true;
                testSyncBtn.textContent = "🧪 Testing...";

                // Simulate server response
                const result = await simulateServerResponse(data);

                if (result.status === 'success') {
                    let message = `🧪 TEST MODE: ${result.message}`;
                    if (result.errors && result.errors.length > 0) {
                        message += `\n\n⚠️ ${result.errors.length} record(s) would have issues:\n${result.errors.join('\n')}`;
                    }

                    alert(message);

                    // Show what would happen without actually syncing
                    const wouldSyncCount = data.length - result.errors.length;
                    if (wouldSyncCount > 0) {
                        alert(`🧪 In real sync, ${wouldSyncCount} record(s) would be uploaded with status='pending'`);
                    }
                }
            } catch (error) {
                alert("❌ Test sync failed: " + error.message);
            } finally {
                testSyncBtn.disabled = false;
                testSyncBtn.textContent = "Test Sync";
            }
        });

        // Real Sync Function
        syncBtn.addEventListener('click', async () => {
            if (isTestMode) {
                alert("🧪 Please exit Test Mode first to perform real sync.");
                return;
            }

            if (!navigator.onLine) {
                alert("⚠️ You are offline. Please connect to the internet to sync.");
                return;
            }

            let data = JSON.parse(localStorage.getItem('rsbsaData') || '[]');
            if (data.length === 0) {
                alert("⚠️ No local data to sync.");
                return;
            }

            data = removeLocalDuplicates(data);

            try {
                syncBtn.disabled = true;
                syncBtn.textContent = "🔄 Syncing...";

                const response = await fetch('upload_enumerator.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.status === 'success') {
                    alert(`✅ ${result.message}`);
                    if (result.errors && result.errors.length > 0) {
                        alert("⚠️ Duplicates skipped:\n" + result.errors.join("\n"));
                    }
                    localStorage.removeItem('rsbsaData');
                    loadData();
                } else {
                    alert("⚠️ Server error: " + result.message);
                }
            } catch (error) {
                alert("❌ Error syncing data: " + error.message);
            } finally {
                syncBtn.disabled = false;
                syncBtn.textContent = "🔄 Sync to Server";
            }
        });

        // Exit Test Mode
        exitTestModeBtn.addEventListener('click', () => {
            setTestMode(false);
        });

        // Simulate server response for testing
        function simulateServerResponse(data) {
            return new Promise((resolve) => {
                setTimeout(() => {
                    const errors = [];
                    let inserted = 0;

                    // Simulate some duplicates and successful inserts
                    data.forEach((record, index) => {
                        if (index % 4 === 0) { // Simulate 25% duplicates
                            errors.push(`Duplicate skipped: ${record.s_name} ${record.f_name} (${record.brgy})`);
                        } else {
                            inserted++;
                        }
                    });

                    resolve({
                        status: 'success',
                        inserted: inserted,
                        duplicates: errors.length,
                        message: `${inserted} farmer record(s) would be uploaded successfully with status='pending'.`,
                        errors: errors
                    });
                }, 2000); // Simulate 2 second delay
            });
        }

        document.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>

</html>