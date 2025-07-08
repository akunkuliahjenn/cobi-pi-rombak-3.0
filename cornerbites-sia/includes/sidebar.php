<?php 
// includes/sidebar.php 
// File ini berisi struktur sidebar navigasi untuk aplikasi HPP Full Costing
// Pastikan session sudah dimulai dan user_role tersedia 
$user_role = $_SESSION['user_role'] ?? 'guest';

// Determine current page for highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<aside class="flex flex-col w-64 bg-white shadow-lg border-r border-gray-200">
    <!-- Logo Aplikasi -->
    <div class="flex items-center h-20 bg-white px-6 border-b border-gray-100">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-gray-800 rounded-md flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                </svg>
            </div>
            <div>
                <div class="text-lg font-semibold text-gray-800">Manajemen HPP</div>
                <div class="text-lg font-semibold text-gray-800">Sederhana</div>
            </div>
        </div>
    </div>

    <!-- Navigasi Menu -->
    <nav class="flex-1 mt-4 px-3 pb-4 overflow-y-auto">
        <?php if ($user_role == 'user'): ?>
            <!-- Menu untuk USER - Fokus HPP Full Costing -->
            <div class="space-y-1">
                <a href="/cornerbites-sia/pages/dashboard.php" class="flex items-center py-3 px-4 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200 group <?php echo ($current_page == 'dashboard.php' && $current_dir == 'pages') ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600' : ''; ?>">
                    <svg class="w-5 h-5 mr-3 text-gray-500 group-hover:text-gray-700 <?php echo ($current_page == 'dashboard.php' && $current_dir == 'pages') ? 'text-blue-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Dashboard
                </a>

                <a href="/cornerbites-sia/pages/produk.php" class="flex items-center py-3 px-4 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200 group <?php echo ($current_page == 'produk.php') ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600' : ''; ?>">
                    <svg class="w-5 h-5 mr-3 text-gray-500 group-hover:text-gray-700 <?php echo ($current_page == 'produk.php') ? 'text-blue-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                    </svg>
                    Manajemen Produk
                </a>

                <a href="/cornerbites-sia/pages/bahan_baku.php" class="flex items-start py-3 px-4 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200 group <?php echo ($current_page == 'bahan_baku.php') ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600' : ''; ?>">
                    <svg class="w-5 h-5 mr-3 mt-0.5 text-gray-500 group-hover:text-gray-700 <?php echo ($current_page == 'bahan_baku.php') ? 'text-blue-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <div class="flex flex-col leading-tight">
                        <span>Manajemen Bahan </span>
                        <span>Baku & Kemasan</span>
                    </div>
                </a></old_str>

                <a href="/cornerbites-sia/pages/overhead_management.php" class="flex items-start py-3 px-4 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200 group <?php echo ($current_page == 'overhead_management.php') ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600' : ''; ?>">
                    <svg class="w-5 h-5 mr-3 mt-0.5 text-gray-500 group-hover:text-gray-700 <?php echo ($current_page == 'overhead_management.php') ? 'text-blue-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <div class="flex flex-col leading-tight">
                        <span>Overhead & Tenaga</span>
                        <span>Kerja</span>
                    </div>
                </a>

                <a href="/cornerbites-sia/pages/resep_produk.php" class="flex items-start py-3 px-4 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200 group <?php echo ($current_page == 'resep_produk.php') ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600' : ''; ?>">
                    <svg class="w-5 h-5 mr-3 mt-0.5 text-gray-500 group-hover:text-gray-700 <?php echo ($current_page == 'resep_produk.php') ? 'text-blue-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <div class="flex flex-col leading-tight">
                        <span>Manajemen Resep</span>
                        <span>& HPP</span>
                    </div>
                </a>




            </div>

        <?php elseif ($user_role == 'admin'): ?>
            <!-- Menu untuk ADMIN -->
            <div class="space-y-1">
                <a href="/cornerbites-sia/admin/dashboard.php" class="flex items-center py-3 px-4 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200 group <?php echo ($current_page == 'dashboard.php' && $current_dir == 'admin') ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600' : ''; ?>">
                    <svg class="w-5 h-5 mr-3 text-gray-500 group-hover:text-gray-700 <?php echo ($current_page == 'dashboard.php' && $current_dir == 'admin') ? 'text-blue-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.515-1.378 2.053-1.378 2.568 0L15.34 9.17c.338.903.882 1.63 1.64 2.11l4.897 2.915c1.378.818 1.378 2.316 0 3.134l-4.897 2.915c-.758.48-1.302 1.207-1.64 2.11L12.893 21.683c-.515 1.378-2.053 1.378-2.568 0L7.66 16.83c-.338-.903-.882-1.63-1.64-2.11l-4.897-2.915c-1.378-.818-1.378-2.316 0-3.134l4.897-2.915c.758-.48 1.302-1.207 1.64-2.11L10.325 4.317z"></path>
                    </svg>
                    Admin Dashboard
                </a>

                <a href="/cornerbites-sia/admin/users.php" class="flex items-center py-3 px-4 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200 group <?php echo ($current_page == 'users.php') ? 'bg-blue-50 text-blue-600 border-r-4 border-blue-600' : ''; ?>">
                    <svg class="w-5 h-5 mr-3 text-gray-500 group-hover:text-gray-700 <?php echo ($current_page == 'users.php') ? 'text-blue-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M12 20a9 9 0 100-18 9 9 0 000 18zm-2-9a2 2 0 110-4 2 2 0 010 4zm7 0a2 2 0 110-4 2 2 0 010 4zm-7 7a2 2 0 110-4 2 2 0 010 4zm7 0a2 2 0 110-4 2 2 0 010 4z"></path>
                    </svg>
                    Kelola Pengguna
                </a>

            </div>
        <?php endif; ?>
    </nav>

    <!-- Tombol Logout di bagian bawah sidebar -->
    <div class="p-4 border-t border-gray-100">
        <button id="logout-btn" class="flex items-center justify-center py-3 px-4 rounded-lg bg-red-500 text-white font-medium hover:bg-red-600 transition-all duration-200 w-full group">
            <svg class="w-5 h-5 mr-2 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            Logout
        </button>
    </div>

    <!-- Custom Logout Modal -->
    <div id="logout-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform scale-95 transition-all duration-200">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="p-3 bg-red-100 rounded-full mr-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Konfirmasi Logout</h3>
                        <p class="text-sm text-gray-600">Apakah Anda yakin ingin keluar dari sistem?</p>
                    </div>
                </div>
                <div class="flex space-x-3 justify-end">
                    <button id="cancel-logout" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition duration-200 font-medium">
                        Batal
                    </button>
                    <button id="confirm-logout" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200 font-medium">
                        Ya, Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/cornerbites-sia/assets/js/sidebar.js"></script>
</aside>