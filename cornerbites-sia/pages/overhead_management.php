<?php
// pages/overhead_management.php
// Halaman manajemen biaya overhead dan tenaga kerja

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Pesan sukses atau error setelah proses
$message = '';
$message_type = '';
if (isset($_SESSION['overhead_message'])) {
    $message = $_SESSION['overhead_message']['text'];
    $message_type = $_SESSION['overhead_message']['type'];
    unset($_SESSION['overhead_message']);
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    ob_start();
}

// Initialize variables
$overhead_costs = [];
$labor_costs = [];

// Pagination and search for overhead
$search_overhead = $_GET['search_overhead'] ?? '';
$limit_overhead = isset($_GET['limit_overhead']) && in_array((int)$_GET['limit_overhead'], [5, 10, 15, 20]) ? (int)$_GET['limit_overhead'] : 5;
$page_overhead = isset($_GET['page_overhead']) ? max((int)$_GET['page_overhead'], 1) : 1;

// Persist pagination and limit values
$overhead_pagination_params = http_build_query([
    'search_overhead' => $search_overhead,
    'limit_overhead' => $limit_overhead,
    'page_overhead' => $page_overhead
]);

$offset_overhead = ($page_overhead - 1) * $limit_overhead;

// Pagination and search for labor
$search_labor = $_GET['search_labor'] ?? '';
$limit_labor = isset($_GET['limit_labor']) && in_array((int)$_GET['limit_labor'], [5, 10, 15, 20]) ? (int)$_GET['limit_labor'] : 5;
$page_labor = isset($_GET['page_labor']) ? max((int)$_GET['page_labor'], 1) : 1;

// Persist pagination and limit values
$labor_pagination_params = http_build_query([
    'search_labor' => $search_labor,
    'limit_labor' => $limit_labor,
    'page_labor' => $page_labor
]);

$offset_labor = ($page_labor - 1) * $limit_labor;

try {
    $conn = $db;

    // Get overhead costs with pagination and search
    $where_overhead = "WHERE is_active = 1";
    $params_overhead = [];
    if (!empty($search_overhead)) {
        $where_overhead .= " AND name LIKE :search_overhead";
        $params_overhead[':search_overhead'] = '%' . $search_overhead . '%';
    }

    // Count total overhead
    $count_query_overhead = "SELECT COUNT(*) FROM overhead_costs " . $where_overhead;
    $count_stmt_overhead = $conn->prepare($count_query_overhead);
    foreach ($params_overhead as $key => $value) {
        $count_stmt_overhead->bindValue($key, $value);
    }
    $count_stmt_overhead->execute();
    $total_overhead = $count_stmt_overhead->fetchColumn();
    $total_pages_overhead = ceil($total_overhead / $limit_overhead);

    // Adjust page number if it exceeds total pages
    if ($page_overhead > $total_pages_overhead && $total_pages_overhead > 0) {
        $page_overhead = $total_pages_overhead;
        $offset_overhead = ($page_overhead - 1) * $limit_overhead;
    }

    // Get overhead data
    $query_overhead = "SELECT * FROM overhead_costs " . $where_overhead . " ORDER BY name ASC LIMIT :limit OFFSET :offset";
    $stmt_overhead = $conn->prepare($query_overhead);
    foreach ($params_overhead as $key => $value) {
        $stmt_overhead->bindValue($key, $value);
    }
    $stmt_overhead->bindValue(':limit', $limit_overhead, PDO::PARAM_INT);
    $stmt_overhead->bindValue(':offset', $offset_overhead, PDO::PARAM_INT);
    $stmt_overhead->execute();
    $overhead_costs = $stmt_overhead->fetchAll(PDO::FETCH_ASSOC);

    // Get labor costs with pagination and search
    $where_labor = "WHERE is_active = 1";
    $params_labor = [];
    if (!empty($search_labor)) {
        $where_labor .= " AND position_name LIKE :search_labor";
        $params_labor[':search_labor'] = '%' . $search_labor . '%';
    }

    // Count total labor
    $count_query_labor = "SELECT COUNT(*) FROM labor_costs " . $where_labor;
    $count_stmt_labor = $conn->prepare($count_query_labor);
    foreach ($params_labor as $key => $value) {
        $count_stmt_labor->bindValue($key, $value);
    }
    $count_stmt_labor->execute();
    $total_labor = $count_stmt_labor->fetchColumn();
    $total_pages_labor = ceil($total_labor / $limit_labor);

     // Adjust page number if it exceeds total pages
     if ($page_labor > $total_pages_labor && $total_pages_labor > 0) {
        $page_labor = $total_pages_labor;
        $offset_labor = ($page_labor - 1) * $limit_labor;
    }

    // Get labor data
    $query_labor = "SELECT * FROM labor_costs " . $where_labor . " ORDER BY position_name ASC LIMIT :limit OFFSET :offset";
    $stmt_labor = $conn->prepare($query_labor);
    foreach ($params_labor as $key => $value) {
        $stmt_labor->bindValue($key, $value);
    }
    $stmt_labor->bindValue(':limit', $limit_labor, PDO::PARAM_INT);
    $stmt_labor->bindValue(':offset', $offset_labor, PDO::PARAM_INT);
    $stmt_labor->execute();
    $labor_costs = $stmt_labor->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error di Overhead Management: " . $e->getMessage());
}

// Function to build URL with updated parameters, preserving existing ones
function buildUrl($params = []) {
    $existingParams = $_GET;
    $newParams = array_merge($existingParams, $params);
    return $_SERVER['PHP_SELF'] . '?' . http_build_query($newParams);
}

// Handle AJAX response for overhead
if (isset($_GET['ajax']) && $_GET['ajax'] == 'overhead') {
    ?>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">NAMA BIAYA</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">DESKRIPSI</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">JUMLAH (RP)</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">AKSI</th>
                </tr>
            </thead>
            <tbody class="bg-white">
                <?php if (!empty($overhead_costs)): ?>
                    <?php foreach ($overhead_costs as $overhead): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($overhead['name']); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($overhead['description'] ?? '-'); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-green-600">
                                    Rp <?php echo number_format($overhead['amount'], 0, ',', '.'); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-1">
                                    <button onclick="editOverhead(<?php echo htmlspecialchars(json_encode($overhead)); ?>); scrollToForm()"
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="deleteOverhead(<?php echo $overhead['id']; ?>, '<?php echo htmlspecialchars($overhead['name']); ?>')" 
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-red-600 bg-red-50 hover:bg-red-100 border border-red-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">Belum ada biaya overhead</p>
                                <p class="text-gray-400 text-sm">Tambahkan biaya overhead pertama Anda</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Info pagination di bawah -->
    <div class="mt-4 text-sm text-gray-600">
        Menampilkan <?php echo (($page_overhead - 1) * $limit_overhead) + 1; ?> sampai <?php echo min($page_overhead * $limit_overhead, $total_overhead); ?> dari <?php echo $total_overhead; ?> hasil
    </div>

    <!-- Pagination for Overhead -->
    <?php if ($total_pages_overhead > 1): ?>
    <div class="flex items-center justify-end mt-4 space-x-2">
        <?php if ($page_overhead > 1): ?>
            <a href="javascript:void(0)" onclick="loadOverheadData(<?php echo $page_overhead - 1; ?>)"
               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <?php echo $page_overhead - 1; ?>
            </a>
        <?php endif; ?>

        <a href="javascript:void(0)" onclick="loadOverheadData(<?php echo $page_overhead; ?>)"
           class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md">
            <?php echo $page_overhead; ?>
        </a>

        <?php if ($page_overhead < $total_pages_overhead): ?>
            <a href="javascript:void(0)" onclick="loadOverheadData(<?php echo $page_overhead + 1; ?>)"
               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <?php echo $page_overhead + 1; ?>
            </a>
        <?php endif; ?>

        <?php if ($page_overhead < $total_pages_overhead): ?>
            <a href="javascript:void(0)" onclick="loadOverheadData(<?php echo $page_overhead + 1; ?>)"
               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
    $content = ob_get_clean();
    echo $content;
    exit;
}

// Handle AJAX response for labor
if (isset($_GET['ajax']) && $_GET['ajax'] == 'labor') {
    ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posisi/Jabatan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upah per Jam (Rp)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($labor_costs)): ?>
                    <?php foreach ($labor_costs as $labor): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($labor['position_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-blue-600">
                                    Rp <?php echo number_format($labor['hourly_rate'], 0, ',', '.'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button onclick="editLabor(<?php echo htmlspecialchars(json_encode($labor)); ?>); scrollToForm()"
                                            class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition duration-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="deleteLabor(<?php echo $labor['id']; ?>, '<?php echo htmlspecialchars($labor['position_name']); ?>')" 
                                            class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 transition duration-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                                </svg>
                                <p class="text-gray-500 text-lg font-medium">Belum ada data tenaga kerja</p>
                                <p class="text-gray-400 text-sm mt-1">Tambahkan posisi tenaga kerja pertama Anda</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Info pagination di bawah -->
    <div class="mt-4 text-sm text-gray-600">
        Menampilkan <?php echo (($page_labor - 1) * $limit_labor) + 1; ?> sampai <?php echo min($page_labor * $limit_labor, $total_labor); ?> dari <?php echo $total_labor; ?> hasil
    </div>

    <!-- Pagination for Labor -->
    <?php if ($total_pages_labor > 1): ?>
    <div class="flex items-center justify-end mt-4 space-x-2">
        <?php if ($page_labor > 1): ?>
            <a href="javascript:void(0)" onclick="loadLaborData(<?php echo $page_labor - 1; ?>)"
               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <?php echo $page_labor - 1; ?>
            </a>
        <?php endif; ?>

        <a href="javascript:void(0)" onclick="loadLaborData(<?php echo $page_labor; ?>)"
           class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md">
            <?php echo $page_labor; ?>
        </a>

        <?php if ($page_labor < $total_pages_labor): ?>
            <a href="javascript:void(0)" onclick="loadLaborData(<?php echo $page_labor + 1; ?>)"
               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <?php echo $page_labor + 1; ?>
            </a>
        <?php endif; ?>

        <?php if ($page_labor < $total_pages_labor): ?>
            <a href="javascript:void(0)" onclick="loadLaborData(<?php echo $page_labor + 1; ?>)"
               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
    $content = ob_get_clean();
    echo $content;
    exit;
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Manajemen Overhead & Tenaga Kerja</h1>
                    <p class="text-gray-600">Kelola biaya overhead dan data tenaga kerja untuk perhitungan HPP yang akurat</p>
                </div>

                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg border-l-4 <?php echo ($message_type == 'success' ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700'); ?>" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <?php if ($message_type == 'success'): ?>
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Forms Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        <!-- Form Biaya Overhead -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                            <div class="flex items-center mb-6">
                                <div class="p-2 bg-blue-100 rounded-lg mr-3">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900" id="overhead_form_title">Tambah Biaya Overhead Baru</h3>
                                    <p class="text-sm text-gray-600 mt-1">Kelola biaya overhead bulanan seperti listrik, sewa, dll.</p>
                                </div>
                            </div>

                            <form action="/cornerbites-sia/process/simpan_overhead.php" method="POST">
                                <input type="hidden" name="type" value="overhead">
                                <input type="hidden" name="overhead_id" id="overhead_id_to_edit">

                                <div class="space-y-4">
                                    <div>
                                        <label for="overhead_name" class="block text-sm font-semibold text-gray-700 mb-2">Nama Biaya Overhead</label>
                                        <input type="text" id="overhead_name" name="name" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" 
                                               placeholder="Contoh: Listrik, Sewa Tempat, Internet" required>
                                    </div>

                                    <div>
                                        <label for="overhead_amount" class="block text-sm font-semibold text-gray-700 mb-2">Jumlah Biaya per Bulan</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <span class="text-gray-500 text-sm font-medium">Rp</span>
                                            </div>
                                            <input type="text" id="overhead_amount" name="amount" 
                                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" 
                                                   placeholder="500000" required>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Masukkan jumlah biaya overhead per bulan</p>
                                    </div>

                                    <div>
                                        <label for="overhead_description" class="block text-sm font-semibold text-gray-700 mb-2">Deskripsi (Opsional)</label>
                                        <textarea id="overhead_description" name="description" rows="3"
                                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" 
                                                  placeholder="Deskripsi tambahan tentang biaya overhead ini"></textarea>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 mt-6">
                                    <button type="submit" id="overhead_submit_button" 
                                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Tambah Overhead
                                    </button>
                                    <button type="button" id="overhead_cancel_edit_button" 
                                            class="hidden inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-200">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Batal Edit
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Form Tenaga Kerja -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                            <div class="flex items-center mb-6">
                                <div class="p-2 bg-green-100 rounded-lg mr-3">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900" id="labor_form_title">Tambah Posisi Tenaga Kerja Baru</h3>
                                    <p class="text-sm text-gray-600 mt-1">Kelola data upah tenaga kerja per jam</p>
                                </div>
                            </div>

                            <form action="/cornerbites-sia/process/simpan_overhead.php" method="POST">
                                <input type="hidden" name="type" value="labor">
                                <input type="hidden" name="labor_id" id="labor_id_to_edit">

                                <div class="space-y-4">
                                    <div>
                                        <label for="labor_position_name" class="block text-sm font-semibold text-gray-700 mb-2">Nama Posisi/Jabatan</label>
                                        <input type="text" id="labor_position_name" name="position_name" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200" 
                                               placeholder="Contoh: Chef, Kasir, Pelayan" required>
                                    </div>

                                    <div>
                                        <label for="labor_hourly_rate" class="block text-sm font-semibold text-gray-700 mb-2">Upah per Jam</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <span class="text-gray-500 text-sm font-medium">Rp</span>
                                            </div>
                                            <input type="text" id="labor_hourly_rate" name="hourly_rate" 
                                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200" 
                                                   placeholder="15000" required>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Masukkan upah per jam untuk posisi ini</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 mt-6">
                                    <button type="submit" id="labor_submit_button" 
                                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Tambah Posisi
                                    </button>
                                    <button type="button" id="labor_cancel_edit_button" 
                                            class="hidden inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-200">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Batal Edit
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                

                <!-- Daftar Overhead & Tenaga Kerja dengan Tab Navigation -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center mb-6">
                        <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800">Daftar Overhead & Tenaga Kerja</h2>
                            <p class="text-sm text-gray-600">Kelola dan pantau semua biaya overhead dan tenaga kerja dalam inventori</p>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="flex space-x-1 mb-6 bg-gray-100 p-1 rounded-lg" role="tablist">
                        <button id="tab-overhead" 
                                class="flex-1 px-4 py-3 text-sm font-medium rounded-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 bg-white text-blue-600 shadow-sm" 
                                role="tab" 
                                aria-selected="true"
                                onclick="showTab('overhead')">
                            <div class="flex flex-col items-center space-y-1">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span>Biaya Overhead</span>
                                    <span id="badge-overhead" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?php echo $total_overhead; ?></span>
                                </div>
                                <div class="text-xs text-blue-600">
                                    <?php 
                                    $total_overhead_amount_query = "SELECT SUM(amount) FROM overhead_costs WHERE is_active = 1";
                                    $total_overhead_amount_stmt = $conn->prepare($total_overhead_amount_query);
                                    $total_overhead_amount_stmt->execute();
                                    $total_overhead_amount = $total_overhead_amount_stmt->fetchColumn() ?: 0;
                                    ?>
                                    Total Biaya: Rp <?php echo number_format($total_overhead_amount, 0, ',', '.'); ?>
                                </div>
                            </div>
                        </button>
                        <button id="tab-labor" 
                                class="flex-1 px-4 py-3 text-sm font-medium rounded-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-gray-500 hover:text-gray-700" 
                                role="tab" 
                                aria-selected="false"
                                onclick="showTab('labor')">
                            <div class="flex flex-col items-center space-y-1">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                                    </svg>
                                    <span>Tenaga Kerja</span>
                                    <span id="badge-labor" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600"><?php echo $total_labor; ?></span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php 
                                    $avg_labor_rate_query = "SELECT AVG(hourly_rate) FROM labor_costs WHERE is_active = 1";
                                    $avg_labor_rate_stmt = $conn->prepare($avg_labor_rate_query);
                                    $avg_labor_rate_stmt->execute();
                                    $avg_labor_rate = $avg_labor_rate_stmt->fetchColumn() ?: 0;
                                    ?>
                                    Rata-rata Upah/Jam: Rp <?php echo number_format($avg_labor_rate, 0, ',', '.'); ?>
                                </div>
                            </div>
                        </button>
                    </div>
                    <!-- Tab Content: Biaya Overhead -->
                    <div id="content-overhead" class="tab-content" role="tabpanel">
                        <!-- Filter & Pencarian Biaya Overhead -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <div class="flex items-center mb-4">
                                <svg class="w-5 h-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-800">Filter & Pencarian Biaya Overhead</h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="search-overhead-input" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                        </div>
                                        <input type="text" id="search-overhead-input" 
                                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                               placeholder="Cari nama biaya..."
                                               value="<?php echo htmlspecialchars($search_overhead); ?>">
                                    </div>
                                </div>

                                <div>
                                    <label for="limit-overhead-select" class="block text-sm font-medium text-gray-700 mb-2">Data per Halaman</label>
                                    <select id="limit-overhead-select" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <?php foreach ([5, 10, 15, 20] as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $limit_overhead == $option ? 'selected' : ''; ?>>
                                                <?php echo $option; ?> Data
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Info Total Overhead -->
                        <div class="mb-4">
                            <div class="flex justify-between items-center">
                                <p class="text-sm text-gray-600">
                                    Total: <span id="total-overhead-count" class="font-semibold"><?php echo $total_overhead; ?></span> biaya overhead
                                </p>
                            </div>
                        </div>

                        <!-- Tabel Overhead -->
                        <div id="overhead-container">
                            <!-- Content akan dimuat via AJAX -->
                        </div>
                    </div>

                    <!-- Tab Content: Tenaga Kerja -->
                    <div id="content-labor" class="tab-content hidden" role="tabpanel">
                        <!-- Filter & Pencarian Tenaga Kerja -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <div class="flex items-center mb-4">
                                <svg class="w-5 h-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.707A1 1 0 013 7V4z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-800">Filter & Pencarian Tenaga Kerja</h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="search-labor-input" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                        </div>
                                        <input type="text" id="search-labor-input" 
                                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                               placeholder="Cari posisi/jabatan..."
                                               value="<?php echo htmlspecialchars($search_labor); ?>">
                                    </div>
                                </div>

                                <div>
                                    <label for="limit-labor-select" class="block text-sm font-medium text-gray-700 mb-2">Data per Halaman</label>
                                    <select id="limit-labor-select" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <?php foreach ([5, 10, 15, 20] as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $limit_labor == $option ? 'selected' : ''; ?>>
                                                <?php echo $option; ?> Data
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Info Total Tenaga Kerja -->
                        <div class="mb-4">
                            <div class="flex justify-between items-center">
                                <p class="text-sm text-gray-600">
                                    Total: <span id="total-labor-count" class="font-semibold"><?php echo $total_labor; ?></span> posisi tenaga kerja
                                </p>
                            </div>
                        </div>

                        <!-- Tabel Labor -->
                        <div id="labor-container">
                            <!-- Content akan dimuat via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="/cornerbites-sia/assets/js/overhead.js"></script>
<script>
function showTab(tabName) {
    const tabOverhead = document.getElementById('tab-overhead');
    const tabLabor = document.getElementById('tab-labor');
    const contentOverhead = document.getElementById('content-overhead');
    const contentLabor = document.getElementById('content-labor');

    if (tabName === 'overhead') {
        tabOverhead.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
        tabOverhead.classList.remove('text-gray-500', 'hover:text-gray-700');
        tabOverhead.setAttribute('aria-selected', 'true');
        
        // Update text colors in overhead tab
        const overheadTextElements = tabOverhead.querySelectorAll('.text-gray-500');
        overheadTextElements.forEach(el => {
            el.classList.remove('text-gray-500');
            el.classList.add('text-blue-600');
        });

        tabLabor.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        tabLabor.classList.add('text-gray-500', 'hover:text-gray-700');
        tabLabor.setAttribute('aria-selected', 'false');
        
        // Update text colors in labor tab
        const laborTextElements = tabLabor.querySelectorAll('.text-blue-600');
        laborTextElements.forEach(el => {
            el.classList.remove('text-blue-600');
            el.classList.add('text-gray-500');
        });

        contentOverhead.classList.remove('hidden');
        contentLabor.classList.add('hidden');
    } else if (tabName === 'labor') {
        tabLabor.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
        tabLabor.classList.remove('text-gray-500', 'hover:text-gray-700');
        tabLabor.setAttribute('aria-selected', 'true');
        
        // Update text colors in labor tab
        const laborTextElements = tabLabor.querySelectorAll('.text-gray-500');
        laborTextElements.forEach(el => {
            el.classList.remove('text-gray-500');
            el.classList.add('text-blue-600');
        });

        tabOverhead.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        tabOverhead.classList.add('text-gray-500', 'hover:text-gray-700');
        tabOverhead.setAttribute('aria-selected', 'false');
        
        // Update text colors in overhead tab
        const overheadTextElements = tabOverhead.querySelectorAll('.text-blue-600');
        overheadTextElements.forEach(el => {
            el.classList.remove('text-blue-600');
            el.classList.add('text-gray-500');
        });

        contentLabor.classList.remove('hidden');
        contentOverhead.classList.add('hidden');
    }

    // Trigger AJAX refresh for the active tab
    if (tabName === 'overhead') {
        const searchOverhead = document.getElementById('search-overhead-input').value;
        const overheadLimit = document.getElementById('limit-overhead-select').value;
        loadOverheadData(1);
    } else if (tabName === 'labor') {
        const searchLabor = document.getElementById('search-labor-input').value;
        const laborLimit = document.getElementById('limit-labor-select').value;
        loadLaborData(1);
    }
}
    // Initialize data loading when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Load initial data
        loadOverheadData(<?php echo $page_overhead; ?>);
        loadLaborData(<?php echo $page_labor; ?>);

        // Show overhead tab by default
        showTab('overhead');
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>