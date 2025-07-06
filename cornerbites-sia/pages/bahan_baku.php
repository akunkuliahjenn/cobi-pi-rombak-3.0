<?php
// pages/bahan_baku.php
// Halaman manajemen data bahan baku (CRUD) dengan pagination dan pencarian

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_start();
    $ajaxType = $_GET['ajax_type'] ?? '';

    if ($ajaxType === 'raw') {
        // Handle raw materials AJAX
        $searchQueryRaw = $_GET['search_raw'] ?? '';
        $rawMaterialsLimit = isset($_GET['bahan_limit']) && in_array((int)$_GET['bahan_limit'], [6, 12, 18, 24]) ? (int)$_GET['bahan_limit'] : 6;

        try {
            $conn = $db;
            $queryRaw = "SELECT rm.id, rm.name, rm.brand, rm.unit, rm.purchase_price_per_unit, rm.default_package_quantity, rm.type
                         FROM raw_materials rm
                         WHERE rm.type = 'bahan'";
            if (!empty($searchQueryRaw)) {
                $queryRaw .= " AND rm.name LIKE :search_raw_term";
            }
            $queryRaw .= " ORDER BY rm.name ASC LIMIT :limit";

            $stmtRaw = $conn->prepare($queryRaw);
            if (!empty($searchQueryRaw)) {
                $stmtRaw->bindValue(':search_raw_term', '%' . $searchQueryRaw . '%', PDO::PARAM_STR);
            }
            $stmtRaw->bindParam(':limit', $rawMaterialsLimit, PDO::PARAM_INT);
            $stmtRaw->execute();
            $rawMaterials = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);

            // Output raw materials grid
            if (!empty($rawMaterials)):
                foreach ($rawMaterials as $material): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow bg-white">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($material['name']); ?></h3>
                                <?php if (!empty($material['brand'])): ?>
                                    <p class="text-xs text-gray-500">Merek: <?php echo htmlspecialchars($material['brand']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">Bahan</span>
                        </div>
                        <div class="text-sm text-gray-600 space-y-2">
                            <div class="flex justify-between">
                                <span>Harga Per Kemasan:</span>
                                <span class="font-semibold">Rp <?php echo number_format($material['purchase_price_per_unit'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Ukuran Kemasan:</span>
                                <span><?php echo number_format($material['default_package_quantity'], ($material['default_package_quantity'] == floor($material['default_package_quantity'])) ? 0 : 1, ',', '.'); ?> <?php echo htmlspecialchars($material['unit']); ?></span>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center space-x-2">
                            <button onclick="editBahanBaku(<?php echo htmlspecialchars(json_encode($material)); ?>)" class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit
                            </button>
                            <a href="../process/simpan_bahan_baku.php?action=delete&id=<?php echo $material['id']; ?>" class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200" onclick="return confirm('Hapus bahan ini?');">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Hapus
                            </a>
                        </div>
                    </div>
                <?php endforeach;
            else: ?>
                <div class="col-span-full text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <p class="text-lg font-medium">Belum ada bahan baku tercatat</p>
                    <p class="text-sm">Tambahkan bahan baku pertama Anda di atas</p>
                </div>
            <?php endif;
        } catch (PDOException $e) {
            echo '<div class="col-span-full text-center py-12 text-red-600">Terjadi kesalahan saat memuat data.</div>';
        }
    } elseif ($ajaxType === 'kemasan') {
        // Handle packaging materials AJAX
        $searchQueryPackaging = $_GET['search_kemasan'] ?? '';
        $packagingMaterialsLimit = isset($_GET['kemasan_limit']) && in_array((int)$_GET['kemasan_limit'], [6, 12, 18, 24]) ? (int)$_GET['kemasan_limit'] : 6;

        try {
            $conn = $db;
            $queryPackaging = "SELECT rm.id, rm.name, rm.brand, rm.unit, rm.purchase_price_per_unit, rm.default_package_quantity, rm.type
                       FROM raw_materials rm
                       WHERE rm.type = 'kemasan'";
            if (!empty($searchQueryPackaging)) {
                $queryPackaging .= " AND rm.name LIKE :search_kemasan_term";
            }
            $queryPackaging .= " ORDER BY rm.name ASC LIMIT :limit";

            $stmtPackaging = $conn->prepare($queryPackaging);
            if (!empty($searchQueryPackaging)) {
                $stmtPackaging->bindValue(':search_kemasan_term', '%' . $searchQueryPackaging . '%', PDO::PARAM_STR);
            }
            $stmtPackaging->bindParam(':limit', $packagingMaterialsLimit, PDO::PARAM_INT);
            $stmtPackaging->execute();
            $packagingMaterials = $stmtPackaging->fetchAll(PDO::FETCH_ASSOC);

            // Output packaging materials grid
            if (!empty($packagingMaterials)):
                foreach ($packagingMaterials as $material): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow bg-white">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($material['name']); ?></h3>
                                <?php if (!empty($material['brand'])): ?>
                                    <p class="text-xs text-gray-500">Merek: <?php echo htmlspecialchars($material['brand']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Kemasan</span>
                        </div>
                        <div class="text-sm text-gray-600 space-y-2">
                            <div class="flex justify-between">
                                <span>Harga Per Kemasan:</span>
                                <span class="font-semibold">Rp <?php echo number_format($material['purchase_price_per_unit'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Ukuran Kemasan:</span>
                                <span><?php echo number_format($material['default_package_quantity'], ($material['default_package_quantity'] == floor($material['default_package_quantity'])) ? 0 : 1, ',', '.'); ?> <?php echo htmlspecialchars($material['unit']); ?></span>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center space-x-2">
                            <button onclick="editBahanBaku(<?php echo htmlspecialchars(json_encode($material)); ?>)" class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit
                            </button>
                            <a href="../process/simpan_bahan_baku.php?action=delete&id=<?php echo $material['id']; ?>" class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200" onclick="return confirm('Hapus kemasan ini?');">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Hapus
                            </a>
                        </div>
                    </div>
                <?php endforeach;
            else: ?>
                <div class="col-span-full text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <p class="text-lg font-medium">Belum ada kemasan tercatat</p>
                    <p class="text-sm">Tambahkan kemasan pertama Anda di atas</p>
                </div>
            <?php endif;
        } catch (PDOException $e) {
            echo '<div class="col-span-full text-center py-12 text-red-600">Terjadi kesalahan saat memuat data.</div>';
        }
    }

    $content = ob_get_clean();
    echo $content;
    exit;
}

// Pesan sukses atau error setelah proses
$message = '';
$message_type = ''; // 'success' or 'error'
if (isset($_SESSION['bahan_baku_message'])) {
    $message = $_SESSION['bahan_baku_message']['text'];
    $message_type = $_SESSION['bahan_baku_message']['type'];
    unset($_SESSION['bahan_baku_message']);
}

// Pilihan unit dan jenis (type)
$unitOptions = ['kg', 'gram', 'liter', 'ml', 'pcs', 'buah', 'roll', 'meter', 'box', 'botol', 'lembar'];
$typeOptions = ['bahan', 'kemasan'];

// Inisialisasi variabel pencarian
$searchQueryRaw = $_GET['search_raw'] ?? '';
$searchQueryPackaging = $_GET['search_kemasan'] ?? '';

// --- Logika Pagination dan Pengambilan Data untuk BAHAN BAKU ---
$rawMaterials = [];
$totalRawMaterialsRows = 0;
$totalRawMaterialsPages = 1;
$rawMaterialsLimitOptions = [6, 12, 18, 24];
$rawMaterialsLimit = isset($_GET['bahan_limit']) && in_array((int)$_GET['bahan_limit'], $rawMaterialsLimitOptions) ? (int)$_GET['bahan_limit'] : 6;
$rawMaterialsPage = isset($_GET['bahan_page']) ? max((int)$_GET['bahan_page'], 1) : 1;
$rawMaterialsOffset = ($rawMaterialsPage - 1) * $rawMaterialsLimit;
$lowStockRaw = [];

try {
    $conn = $db;

    // Hitung total baris untuk bahan baku dengan filter pencarian
    $queryTotalRaw = "SELECT COUNT(*) FROM raw_materials WHERE type = 'bahan'";
    if (!empty($searchQueryRaw)) {
        $queryTotalRaw .= " AND name LIKE :search_raw_term";
    }
    $stmtTotalRaw = $conn->prepare($queryTotalRaw);
    if (!empty($searchQueryRaw)) {
        $stmtTotalRaw->bindValue(':search_raw_term', '%' . $searchQueryRaw . '%', PDO::PARAM_STR);
    }
    $stmtTotalRaw->execute();
    $totalRawMaterialsRows = $stmtTotalRaw->fetchColumn();
    $totalRawMaterialsPages = ceil($totalRawMaterialsRows / $rawMaterialsLimit);

    // Pastikan halaman tidak melebihi total halaman yang ada untuk bahan baku
    if ($rawMaterialsPage > $totalRawMaterialsPages && $totalRawMaterialsPages > 0) {
        $rawMaterialsPage = $totalRawMaterialsPages;
        $rawMaterialsOffset = ($rawMaterialsPage - 1) * $rawMaterialsLimit;
    }

    // Query untuk mengambil bahan baku dengan informasi penggunaan
    $queryRaw = "SELECT rm.id, rm.name, rm.brand, rm.unit, rm.purchase_price_per_unit, rm.default_package_quantity, rm.type, rm.minimum_stock
                 FROM raw_materials rm
                 WHERE rm.type = 'bahan'";
    if (!empty($searchQueryRaw)) {
        $queryRaw .= " AND name LIKE :search_raw_term";
    }
    $queryRaw .= " ORDER BY rm.name ASC LIMIT :limit OFFSET :offset";

    $stmtRaw = $conn->prepare($queryRaw);
    if (!empty($searchQueryRaw)) {
        $stmtRaw->bindValue(':search_raw_term', '%' . $searchQueryRaw . '%', PDO::PARAM_STR);
    }
    $stmtRaw->bindParam(':limit', $rawMaterialsLimit, PDO::PARAM_INT);
    $stmtRaw->bindParam(':offset', $rawMaterialsOffset, PDO::PARAM_INT);
    $stmtRaw->execute();
    $rawMaterials = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error di halaman Bahan Baku (fetch bahan): " . $e->getMessage());
    $message = "Terjadi kesalahan saat memuat data bahan baku.";
    $message_type = "error";
}

// --- Logika Pagination dan Pengambilan Data untuk KEMASAN ---
$packagingMaterials = [];
$totalPackagingMaterialsRows = 0;
$totalPackagingMaterialsPages = 1;
$packagingMaterialsLimitOptions = [6, 12, 18, 24];
$packagingMaterialsLimit = isset($_GET['kemasan_limit']) && in_array((int)$_GET['kemasan_limit'], $packagingMaterialsLimitOptions) ? (int)$_GET['kemasan_limit'] : 6;
$packagingMaterialsPage = isset($_GET['kemasan_page']) ? max((int)$_GET['kemasan_page'], 1) : 1;
$packagingMaterialsOffset = ($packagingMaterialsPage - 1) * $packagingMaterialsLimit;
$lowStockPackaging = [];

try {
    // Hitung total baris untuk kemasan dengan filter pencarian
    $queryTotalPackaging = "SELECT COUNT(*) FROM raw_materials WHERE type = 'kemasan'";
    if (!empty($searchQueryPackaging)) {
        $queryTotalPackaging .= " AND name LIKE :search_kemasan_term";
    }
    $stmtTotalPackaging = $conn->prepare($queryTotalPackaging);
    if (!empty($searchQueryPackaging)) {
        $stmtTotalPackaging->bindValue(':search_kemasan_term', '%' . $searchQueryPackaging . '%', PDO::PARAM_STR);
    }
    $stmtTotalPackaging->execute();
    $totalPackagingMaterialsRows = $stmtTotalPackaging->fetchColumn();
    $totalPackagingMaterialsPages = ceil($totalPackagingMaterialsRows / $packagingMaterialsLimit);

    // Pastikan halaman tidak melebihi total halaman yang ada untuk kemasan
    if ($packagingMaterialsPage > $totalPackagingMaterialsPages && $totalPackagingMaterialsPages > 0) {
        $packagingMaterialsPage = $totalPackagingMaterialsPages;
        $packagingMaterialsOffset = ($packagingMaterialsPage - 1) * $packagingMaterialsLimit;
    }

    // Query untuk mengambil kemasan
    $queryPackaging = "SELECT rm.id, rm.name, rm.brand, rm.unit, rm.purchase_price_per_unit, rm.default_package_quantity, rm.type
                       FROM raw_materials rm
                       WHERE rm.type = 'kemasan'";
    if (!empty($searchQueryPackaging)) {
        $queryPackaging .= " AND name LIKE :search_kemasan_term";
    }
    $queryPackaging .= " ORDER BY rm.name ASC LIMIT :limit OFFSET :offset";

    $stmtPackaging = $conn->prepare($queryPackaging);
    if (!empty($searchQueryPackaging)) {
        $stmtPackaging->bindValue(':search_kemasan_term', '%' . $searchQueryPackaging . '%', PDO::PARAM_STR);
    }
    $stmtPackaging->bindParam(':limit', $packagingMaterialsLimit, PDO::PARAM_INT);
    $stmtPackaging->bindParam(':offset', $packagingMaterialsOffset, PDO::PARAM_INT);
    $stmtPackaging->execute();
    $packagingMaterials = $stmtPackaging->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error di halaman Bahan Baku (fetch kemasan): " . $e->getMessage());
}

// Fungsi helper untuk membangun URL pagination dengan mempertahankan parameter lain
function buildPaginationUrl($baseUrl, $paramsToUpdate) {
    $queryParams = $_GET;
    foreach ($paramsToUpdate as $key => $value) {
        $queryParams[$key] = $value;
    }
    return $baseUrl . '?' . http_build_query($queryParams);
}

?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gray-50 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header Section -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Manajemen Bahan Baku & Kemasan</h1>
                    <p class="text-gray-600">Kelola stok dan harga bahan baku serta kemasan produk Anda</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="p-4 mb-6 text-sm rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>" role="alert">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Tambah/Edit Bahan Baku/Kemasan -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                    <div class="flex items-center mb-6">
                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800" id="form-title">Tambah Bahan Baku/Kemasan Baru</h2>
                            <p class="text-sm text-gray-600 mt-1">Isidetail bahan baku atau kemasan baru Anda atau gunakan form ini untuk mengedit yang sudah ada.</p>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <svg class="w-4 h-4 inline mr-1 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <strong>Catatan:</strong> Form ini untuk menambah bahan/kemasan yang benar-benar baru. Jika bahan sudah ada dan hanya ingin mengubah stok, gunakan tombol "Edit" pada daftar di bawah.
                    </p>

                    <form action="../process/simpan_bahan_baku.php" method="POST">
                        <input type="hidden" name="bahan_baku_id" id="bahan_baku_id">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Bahan/Kemasan</label>
                                <input type="text" id="name" name="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Contoh: Gula Halus, Plastik Cup, Kemasan Box" required>
                                <p class="text-xs text-gray-500 mt-1">Nama yang jelas dan spesifik untuk identifikasi</p>
                            </div>

                            <div>
                                <label for="brand" class="block text-gray-700 text-sm font-semibold mb-2">Merek</label>
                                <input type="text" id="brand" name="brand" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Contoh: Gulaku, Indosalt, Rose Brand">
                                <p class="text-xs text-gray-500 mt-1">Opsional - untuk membedakan produk sejenis</p>
                            </div>

                            <div>
                                <label for="type" class="block text-gray-700 text-sm font-semibold mb-2">Kategori</label>
                                <select id="type" name="type" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                                    <option value="bahan">Bahan Baku (untuk produksi)</option>
                                    <option value="kemasan">Kemasan (untuk pembungkus)</option>                                </select>
                                <p class="text-xs text-gray-500 mt-1">Pilih kategori yang sesuai</p>
                            </div>

                            <div>
                                <label for="unit" class="block text-gray-700 text-sm font-semibold mb-2">Satuan</label>
                                <select id="unit" name="unit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                                    <?php foreach ($unitOptions as $unitOption): ?>
                                        <option value="<?php echo htmlspecialchars($unitOption); ?>"><?php echo htmlspecialchars($unitOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Satuan yang digunakan dalam resep</p>
                            </div>

                            <div>
                                <label for="purchase_size" class="block text-gray-700 text-sm font-semibold mb-2" id="purchase_size_label">Ukuran Beli Kemasan Bahan</label>
                                <input type="number" step="0.01" id="purchase_size" name="purchase_size" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Contoh: 1000 (untuk 1 kg), 250 (untuk 250 gram)" min="0" required>
                                <p class="text-xs text-gray-500 mt-1" id="purchase_size_help">Isi per kemasan yang Anda beli (sesuai satuan di atas)</p>
                            </div>

                            <div>
                                <label for="purchase_price_per_unit" class="block text-gray-700 text-sm font-semibold mb-2" id="purchase_price_label">Harga Beli Per Kemasan</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-medium">Rp</span>
                                    <input type="text" id="purchase_price_per_unit" name="purchase_price_per_unit" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="15000" required>
                                </div>
                                <p class="text-xs text-gray-500 mt-1" id="purchase_price_help">Harga per kemasan saat pembelian</p>
                            </div>

                            
                        </div>

                        <div class="flex items-center justify-between mt-8">
                            <button type="submit" id="submit_button" class="flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Tambah Bahan
                            </button>
                            <button type="button" id="cancel_edit_button" class="hidden flex items-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 ml-4">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Batal Edit
                            </button>
                        </div>
                    </form>
                </div>

                

                <!-- Bagian Daftar Bahan Baku dan Kemasan -->
                <div class="grid grid-cols-1 gap-8">
                    <!-- Daftar Bahan Baku -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                    Daftar Bahan Baku
                                </h2>
                                <p class="text-sm text-gray-600 mt-1">Kelola dan pantau semua bahan dalam inventori</p>
                            </div>
                        </div>

                        <!-- Filter & Pencarian Bahan Baku -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
                            <div class="flex items-center mb-4">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-800">Filter & Pencarian Bahan Baku</h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                                    <div class="relative">
                                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <input type="text" id="search_raw" placeholder="Cari nama bahan..." value="<?php echo htmlspecialchars($searchQueryRaw); ?>" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Data per Halaman</label>
                                    <select id="bahan_limit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <?php foreach ($rawMaterialsLimitOptions as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $rawMaterialsLimit == $option ? 'selected' : ''; ?>><?php echo $option; ?> Data</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" id="raw-materials-container">
                            <?php if (!empty($rawMaterials)): ?>
                                <?php foreach ($rawMaterials as $material): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow bg-white">
                                        <div class="flex justify-between items-start mb-3">
                                            <div class="flex-1">
                                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($material['name']); ?></h3>
                                                <?php if (!empty($material['brand'])): ?>
                                                    <p class="text-xs text-gray-500">Merek: <?php echo htmlspecialchars($material['brand']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">Bahan</span>
                                        </div>
                                        <div class="text-sm text-gray-600 space-y-2">
                                            <div class="flex justify-between">
                                                <span>Harga Per Kemasan:</span>
                                                <span class="font-semibold">Rp <?php echo number_format($material['purchase_price_per_unit'], 0, ',', '.'); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Ukuran Kemasan:</span>
                                                <span><?php echo number_format($material['default_package_quantity'], ($material['default_package_quantity'] == floor($material['default_package_quantity'])) ? 0 : 1, ',', '.'); ?> <?php echo htmlspecialchars($material['unit']); ?></span>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center space-x-2">
                                            <button onclick="editBahanBaku(<?php echo htmlspecialchars(json_encode($material)); ?>)" class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </button>
                                            <a href="../process/simpan_bahan_baku.php?action=delete&id=<?php echo $material['id']; ?>" class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200" onclick="return confirm('Hapus bahan ini?');">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Hapus
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-full text-center py-12 text-gray-500">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <p class="text-lg font-medium">Belum ada bahan baku tercatat</p>
                                    <p class="text-sm">Tambahkan bahan baku pertama Anda di atas</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pagination Bahan Baku -->
                        <?php if ($totalRawMaterialsPages > 1): ?>
                            <div class="flex justify-center items-center space-x-2">
                                <?php if ($rawMaterialsPage > 1): ?>
                                    <a href="<?php echo buildPaginationUrl('/cornerbites-sia/pages/bahan_baku.php', ['bahan_page' => $rawMaterialsPage - 1]); ?>" class="flex items-center px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                        Prev
                                    </a>
                                <?php endif; ?>

                                <?php 
                                $startPage = max(1, $rawMaterialsPage - 2);
                                $endPage = min($totalRawMaterialsPages, $rawMaterialsPage + 2);
                                for ($i = $startPage; $i <= $endPage; $i++): 
                                ?>
                                    <a href="<?php echo buildPaginationUrl('/cornerbites-sia/pages/bahan_baku.php', ['bahan_page' => $i]); ?>" class="px-3 py-2 text-sm <?php echo $i == $rawMaterialsPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($rawMaterialsPage < $totalRawMaterialsPages): ?>
                                    <a href="<?php echo buildPaginationUrl('/cornerbites-sia/pages/bahan_baku.php', ['bahan_page' => $rawMaterialsPage + 1]); ?>" class="flex items-center px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                        Next
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Daftar Kemasan -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    Daftar Kemasan
                                </h2>
                                <p class="text-sm text-gray-600 mt-1">Kelola dan pantau semua kemasan dalam inventori</p>
                            </div>
                        </div>

                        <!-- Filter & Pencarian Kemasan -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
                            <div class="flex items-center mb-4">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 001-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-800">Filter & Pencarian Kemasan</h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                                    <div class="relative">
                                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <input type="text" id="search_kemasan" placeholder="Cari nama kemasan..." value="<?php echo htmlspecialchars($searchQueryPackaging); ?>" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Data per Halaman</label>
                                    <select id="kemasan_limit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <?php foreach ($packagingMaterialsLimitOptions as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $packagingMaterialsLimit == $option ? 'selected' : ''; ?>><?php echo $option; ?> Data</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" id="packaging-materials-container">
                            <?php if (!empty($packagingMaterials)): ?>
                                <?php foreach ($packagingMaterials as $material): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow bg-white">
                                        <div class="flex justify-between items-start mb-3">
                                            <div class="flex-1">
                                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($material['name']); ?></h3>
                                                <?php if (!empty($material['brand'])): ?>
                                                    <p class="text-xs text-gray-500">Merek: <?php echo htmlspecialchars($material['brand']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Kemasan</span>
                                        </div>
                                        <div class="text-sm text-gray-600 space-y-2">
                                            <div class="flex justify-between">
                                                <span>Harga Per Kemasan:</span>
                                                <span class="font-semibold">Rp <?php echo number_format($material['purchase_price_per_unit'], 0, ',', '.'); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Ukuran Kemasan:</span>
                                                <span><?php echo number_format($material['default_package_quantity'], ($material['default_package_quantity'] == floor($material['default_package_quantity'])) ? 0 : 1, ',', '.'); ?> <?php echo htmlspecialchars($material['unit']); ?></span>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center space-x-2">
                                            <button onclick="editBahanBaku(<?php echo htmlspecialchars(json_encode($material)); ?>)" class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </button>
                                            <a href="../process/simpan_bahan_baku.php?action=delete&id=<?php echo $material['id']; ?>" class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200" onclick="return confirm('Hapus kemasan ini?');">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Hapus
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-full text-center py-12 text-gray-500">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <p class="text-lg font-medium">Belum ada kemasan tercatat</p>
                                    <p class="text-sm">Tambahkan kemasan pertama Anda di atas</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pagination Kemasan -->
                        <?php if ($totalPackagingMaterialsPages > 1): ?>
                            <div class="flex justify-center items-center space-x-2">
                                <?php if ($packagingMaterialsPage > 1): ?>
                                    <a href="<?php echo buildPaginationUrl('/cornerbites-sia/pages/bahan_baku.php', ['kemasan_page' => $packagingMaterialsPage - 1]); ?>" class="flex items-center px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                        Prev
                                    </a>
                                <?php endif; ?>

                                <?php 
                                $startPage = max(1, $packagingMaterialsPage - 2);
                                $endPage = min($totalPackagingMaterialsPages, $packagingMaterialsPage + 2);
                                for ($i = $startPage; $i <= $endPage; $i++): 
                                ?>
                                    <a href="<?php echo buildPaginationUrl('/cornerbites-sia/pages/bahan_baku.php', ['kemasan_page' => $i]); ?>" class="px-3 py-2 text-sm <?php echo $i == $packagingMaterialsPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($packagingMaterialsPage < $totalPackagingMaterialsPages): ?>
                                    <a href="<?php echo buildPaginationUrl('/cornerbites-sia/pages/bahan_baku.php', ['kemasan_page' => $packagingMaterialsPage + 1]); ?>" class="flex items-center px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                        Next
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- JavaScript Terpisah -->
<script src="../assets/js/bahan_baku.js"></script>

<script>
    function showStockHistory(materialId, materialName) {
        alert('Fitur riwayat stok untuk ' + materialName + ' akan segera hadir!');
    }

    document.addEventListener('DOMContentLoaded', function() {
        

        // Existing code for raw materials and packaging
        const searchRawInput = document.getElementById('search_raw');
        const searchKemasanInput = document.getElementById('search_kemasan');
        const bahanLimitSelect = document.getElementById('bahan_limit');
        const kemasanLimitSelect = document.getElementById('kemasan_limit');
        const rawMaterialsContainer = document.getElementById('raw-materials-container');
        const packagingMaterialsContainer = document.getElementById('packaging-materials-container');

        function performSearch(type) {
            let searchQuery = '';
            let limitSelect = null;
            let container = null;
            let ajaxType = '';

            if (type === 'raw') {
                searchQuery = searchRawInput.value;
                limitSelect = bahanLimitSelect;
                container = rawMaterialsContainer;
                ajaxType = 'raw';
            } else if (type === 'kemasan') {
                searchQuery = searchKemasanInput.value;
                limitSelect = kemasanLimitSelect;
                container = packagingMaterialsContainer;
                ajaxType = 'kemasan';
            }

            const limit = limitSelect.value;

            // Construct URL
            let url = '?ajax=1&ajax_type=' + ajaxType + '&' + type + '_limit=' + limit;
            if (searchQuery) {
                url += '&search_' + type + '=' + encodeURIComponent(searchQuery);
            }

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                container.innerHTML = data;
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="col-span-full text-center py-12 text-red-600">Terjadi kesalahan saat memuat data.</div>';
            });
        }

        // Attach event listeners
        searchRawInput.addEventListener('input', function() {
            performSearch('raw');
        });

        searchKemasanInput.addEventListener('input', function() {
            performSearch('kemasan');
        });

        bahanLimitSelect.addEventListener('change', function() {
            performSearch('raw');
        });

        kemasanLimitSelect.addEventListener('change', function() {
            performSearch('kemasan');
        });`
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>