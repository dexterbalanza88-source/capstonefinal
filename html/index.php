<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/output.css" rel="stylesheet">
</head>

<body>
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <nav class="bg-[#166534] text-white shadow-lg fixed w-full z-50 top-0 left-0 border-b-4 border-[#E6B800]">
            <div class="flex items-center justify-between max-w-7xl mx-auto px-6 py-3">
                <!-- Left: Logo & Drawer Toggle -->
                <div class="flex items-center space-x-3">
                    <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
                        aria-controls="drawer-navigation"
                        class="p-2 text-white rounded-lg cursor-pointer md:hidden hover:bg-[#14532d] focus:ring-2 focus:ring-[#E6B800]">
                        <!-- Menu icon -->
                        <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>

                    <img src="../img/logo.png" alt="LGU Logo"
                        class="h-12 w-12 rounded-full border-2 border-white bg-white">
                    <h1 class="text-lg font-semibold tracking-wide">
                        Municipal Agriculture Office – Abra De Ilog
                    </h1>
                </div>

                <!-- Right: Profile Dropdown -->
                <div class="flex items-center space-x-3">
                    <button id="user-menu-button" data-dropdown-toggle="dropdown"
                        class="flex items-center text-sm bg-white rounded-full focus:ring-2 focus:ring-[#E6B800]">
                        <img class="w-9 h-9 rounded-full" src="../img/profile.png" alt="User photo">
                    </button>

                    <!-- Dropdown -->
                    <div id="dropdown"
                        class="hidden z-50 my-4 w-56 text-base list-none bg-white divide-y divide-gray-100 shadow rounded-xl">
                        <div class="py-3 px-4 text-center">
                            <span class="block text-sm font-semibold text-gray-900">
                                <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
                            </span>
                            <span class="block text-sm text-gray-500 truncate">
                                <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                            </span>
                        </div>
                        <ul class="py-1 text-gray-700" aria-labelledby="dropdown">
                            <li>
                                <a href="profile.html" class="block py-2 px-4 text-sm hover:bg-gray-100">My profile</a>
                            </li>
                            <li>
                                <a href="login.html" class="block py-2 px-4 text-sm hover:bg-gray-100">Sign out</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- SIDEBAR -->
        <aside id="drawer-navigation"
            class="fixed top-0 left-0 z-40 w-64 h-screen pt-16 transition-transform -translate-x-full md:translate-x-0 bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700"
            aria-label="Sidenav">
            <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
                <ul class="space-y-2">
                    <li>
                        <a href="index.php"
                            class="flex items-center p-2 text-base font-medium text-[#166534] rounded-lg bg-green-50 border-l-4 border-[#E6B800] group">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                            </svg>
                            <span class="ml-3">Dashboard</span>
                        </a>
                    </li>

                    <li>
                        <a href="adddata.php"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd"
                                    d="M9 2.221V7H4.221a2 2 0 0 1 .365-.5L8.5 2.586A2 2 0 0 1 9 2.22ZM11 2v5a2 2 0 0 1-2 2H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-7Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="ml-3">Add Data</span>
                        </a>
                    </li>

                    <li>
                        <a href="datalist.php"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3">Data List</span>
                        </a>
                    </li>
                </ul>

                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="report.php"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3">Reports</span>
                        </a>
                    </li>

                    <li>
                        <a href="archived.php"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0-2-2v-4z">
                                </path>
                            </svg>
                            <span class="ml-3">Archived</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        <main class="md:ml-64 pt-20 p-2 width-full">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Hectares -->
                <div
                    class="flex items-center justify-between bg-gradient-to-r from-green-400 to-green-300 p-5 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300">
                    <div>
                        <p class="text-white font-medium text-lg">Hectares</p>
                        <h2 id="hectares" class="text-3xl font-bold text-white mt-2">0</h2>
                    </div>
                    <div class="text-white opacity-80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 7l9 4 9-4M3 17l9 4 9-4M3 12l9 4 9-4" />
                        </svg>
                    </div>
                </div>

                <!-- Total Farmers -->
                <div
                    class="flex items-center justify-between bg-gradient-to-r from-blue-400 to-blue-300 p-5 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300">
                    <div>
                        <p class="text-white font-medium text-lg">Total Farmers</p>
                        <h2 id="farmers" class="text-3xl font-bold text-white mt-2">0</h2>
                    </div>
                    <div class="text-white opacity-80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M5.121 17.804A8 8 0 0112 15a8 8 0 016.879 2.804M12 12a4 4 0 100-8 4 4 0 000 8z" />
                        </svg>
                    </div>
                </div>

                <!-- Male -->
                <div
                    class="flex items-center justify-between bg-gradient-to-r from-sky-400 to-sky-300 p-5 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300">
                    <div>
                        <p class="text-white font-medium text-lg">Male</p>
                        <h2 id="male" class="text-3xl font-bold text-white mt-2">0</h2>
                    </div>
                    <div class="text-white opacity-80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M16 2v6h6M8 8a6 6 0 118 8m-8-8l8-8" />
                        </svg>
                    </div>
                </div>

                <!-- Female -->
                <div
                    class="flex items-center justify-between bg-gradient-to-r from-pink-400 to-pink-300 p-5 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300">
                    <div>
                        <p class="text-white font-medium text-lg">Female</p>
                        <h2 id="female" class="text-3xl font-bold text-white mt-2">0</h2>
                    </div>
                    <div class="text-white opacity-80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M12 14v8m-4-4h8M12 14a6 6 0 100-12 6 6 0 000 12z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 text-center">Crops Overview</h3>
                    <canvas id="cropsChart" class="w-full"></canvas>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 text-center">Age Overview</h3>
                    <canvas id="ageChart" class="w-full"></canvas>
                </div>
            </div>

        </main>
        <script>
            document.addEventListener("DOMContentLoaded", async () => {
                try {
                    const res = await fetch('fetch_dashboard.php');
                    const data = await res.json();

                    // 🧩 Fill cards
                    document.getElementById("hectares").textContent = data.total_farmarea || 0;
                    document.getElementById("farmers").textContent = data.total_farmers || 0;
                    document.getElementById("male").textContent = data.male || 0;
                    document.getElementById("female").textContent = data.female || 0;

                    // 🧩 Crops Overview Chart (Main Livelihood)
                    const ctxCrops = document.getElementById("cropsChart").getContext("2d");
                    new Chart(ctxCrops, {
                        type: "doughnut",
                        data: {
                            labels: Object.keys(data.main_livelihood),
                            datasets: [{
                                data: Object.values(data.main_livelihood),
                                backgroundColor: ["#34d399", "#60a5fa", "#fbbf24", "#f87171", "#a78bfa", "#10b981"]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: "bottom" }
                            }
                        }
                    });

                    // 🧩 Age Overview Chart
                    const ctxAge = document.getElementById("ageChart").getContext("2d");
                    new Chart(ctxAge, {
                        type: "bar",
                        data: {
                            labels: Object.keys(data.age_groups),
                            datasets: [{
                                label: "Farmers",
                                data: Object.values(data.age_groups),
                                backgroundColor: ["#3b82f6", "#fbbf24", "#f87171"]
                            }]
                        },
                        options: {
                            scales: { y: { beginAtZero: true } },
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });

                } catch (err) {
                    console.error("Error loading dashboard data:", err);
                }
            });
        </script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="../js/tailwind.config.js"></script>
        <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>