<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MAO Dashboard | Abra De Ilog</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    integrity="sha512-TX8t27EcRE3e/ihU7zmQG2A0jKqOv9Wk4gQ6VV7VYvXD4h9cvv6tk7tP1UuF6fQphVj5MZ6xzF3I1i+HzF3bHg=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />

  <style>
    /* Sidebar gradient and layout */
    .sidebar {
      background: linear-gradient(180deg, #166534 0%, #1b7a42 100%);
      color: #fff;
      width: 240px;
      min-height: 100vh;
      transition: all 0.3s ease;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 18px;
      color: #f1f5f1;
      font-weight: 500;
      border-radius: 8px;
      transition: background 0.3s, transform 0.2s;
    }

    .sidebar a:hover {
      background-color: rgba(255, 255, 255, 0.15);
      transform: translateX(3px);
    }

    .sidebar i {
      background-color: rgba(255, 255, 255, 0.2);
      padding: 10px;
      border-radius: 50%;
      font-size: 1.1rem;
      min-width: 38px;
      text-align: center;
    }

    .sidebar h2 {
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 1px;
      color: #d1e7d3;
      margin: 16px 18px 6px;
      font-weight: 600;
    }

    /* Active link */
    .sidebar a.active {
      background-color: rgba(255, 255, 255, 0.25);
      font-weight: 600;
    }

    /* Navbar top section */
    nav {
      background-color: #166534;
      border-bottom: 4px solid #cddfc7;
    }
  </style>
</head>

<body class="flex bg-[#f5f9f5]">

  <!-- Sidebar -->
  <aside class="sidebar fixed left-0 top-0">
    <div class="p-6 flex items-center gap-3 border-b border-green-800">
      <img src="../img/logo.png" alt="LGU Logo" class="h-10 w-10 rounded-full border-2 border-white" />
      <div>
        <p class="font-semibold leading-tight">MAO</p>
        <p class="text-xs text-gray-200">Abra De Ilog</p>
      </div>
    </div>

    <div class="mt-4 px-2 space-y-1">
      <h2>Menu</h2>
      <a href="#" class="active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <a href="#"><i class="fa-solid fa-user-plus"></i> Add Data</a>
      <a href="#"><i class="fa-solid fa-table-list"></i> Data List</a>
      <a href="#"><i class="fa-solid fa-chart-column"></i> Reports</a>
      <a href="#"><i class="fa-solid fa-box-archive"></i> Archive</a>

      <h2>System</h2>
      <a href="#"><i class="fa-solid fa-users-gear"></i> User Management</a>
      <a href="#"><i class="fa-solid fa-gear"></i> Settings</a>
      <a href="#"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 ml-[240px]">
    <nav class="fixed top-0 left-[240px] right-0 z-10 px-6 py-3 flex justify-between items-center shadow-md text-white">
      <h1 class="font-semibold text-lg tracking-wide">Municipal Agriculture Office – Abra De Ilog</h1>
      <div class="flex items-center space-x-4">
        <i class="fa-regular fa-bell text-white text-lg"></i>
        <i class="fa-solid fa-circle-user text-white text-2xl"></i>
      </div>
    </nav>

    <section class="p-8 mt-16">
      <h2 class="text-2xl font-semibold text-[#166534] mb-4">Dashboard Overview</h2>
      <p class="text-gray-700">Welcome to the Municipal Agriculture Office Information System.</p>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>
</html>
