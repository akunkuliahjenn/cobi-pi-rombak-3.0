<?php
// pages/produk.php
// Halaman untuk manajemen data produk (daftar, tambah, edit).

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

$products = [];
$search = $_GET['search'] ?? '';

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_start();
}

try {
    $conn = $db;

    // Pagination setup
    $limit_options = [10, 25, 50, 100];
    $limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
    $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause for search
    $whereClause = "WHERE 1=1";
    $params = [];

    // Search filter
    if (!empty($search)) {
        $whereClause .= " AND name LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    // Hitung total produk dengan filter
    $countQuery = "SELECT COUNT(*) FROM products " . $whereClause;
    $countStmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $limit);

    // Mengambil semua kolom yang relevan dari tabel products dengan pagination dan filter
    $query = "SELECT id, name, unit, sale_price FROM products " . $whereClause . " ORDER BY name ASC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error di halaman Produk: " . $e->getMessage());
}

// If this is an AJAX request, return only the table content
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual (Rp)</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <tr class="hover:bg-gray-50 transition duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['unit']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-green-600">Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                            class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <a href="/cornerbites-sia/process/simpan_produk.php?action=delete&id=<?php echo htmlspecialchars($product['id']); ?>" 
                                       class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <p class="text-gray-500 text-lg font-medium">Belum ada produk yang tercatat</p>
                                <p class="text-gray-400 text-sm mt-1">Mulai tambahkan produk pertama Anda menggunakan form di atas</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="bg-white px-6 py-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                    <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" 
                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div class="flex items-center space-x-2">
                    <form id="limitForm" method="get" class="flex items-center space-x-2">
                        <label for="limitSelect" class="text-sm text-gray-700">Per halaman:</label>
                        <select name="limit" id="limitSelect" onchange="document.getElementById('limitForm').submit()"
                                class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($limit_options as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($limit == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                    <div class="text-sm text-gray-700">
                        Menampilkan <span class="font-medium"><?php echo number_format($offset + 1); ?></span> sampai 
                        <span class="font-medium"><?php echo number_format(min($offset + $limit, $totalProducts)); ?></span> dari 
                        <span class="font-medium"><?php echo number_format($totalProducts); ?></span> produk
                    </div>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>

                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                            <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $i; ?>" 
                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo ($i == $page) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
    $content = ob_get_clean();
    echo $content;
    exit;
}

// Pesan sukses atau error setelah proses simpan
$message = '';
$message_type = ''; // 'success' or 'error'
if (isset($_SESSION['product_message'])) {
    $message = $_SESSION['product_message']['text'];
    $message_type = $_SESSION['product_message']['type'];
    unset($_SESSION['product_message']);
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Manajemen Produk</h1>
            <p class="text-gray-600">Kelola data produk dan harga untuk optimalisasi bisnis Anda</p>
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

        <!-- Form Tambah/Edit Produk -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
            <div class="flex items-center mb-6">
                <div class="p-2 bg-purple-100 rounded-lg mr-3">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900" id="form_title">Tambah Produk Baru</h3>
                    <p class="text-sm text-gray-600 mt-1" id="form_description">Isi detail produk baru Anda atau gunakan form ini untuk mengedit produk yang sudah ada.</p>
                </div>
            </div>

            <form action="/cornerbites-sia/process/simpan_produk.php" method="POST">
                <input type="hidden" name="product_id" id="product_id_to_edit" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="product_name" class="block text-sm font-semibold text-gray-700 mb-2">Nama Produk:</label>
                        <input type="text" id="product_name" name="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" placeholder="Contoh: Kopi Latte, Donat Cokelat" required>
                    </div>
                    <div>
                        <label for="unit" class="block text-sm font-semibold text-gray-700 mb-2">Satuan:</label>
                        <select id="unit" name="unit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" required onchange="toggleCustomUnit()">
                            <option value="">Pilih Satuan</option>
                            <option value="pcs">pcs (pieces)</option>
                            <option value="porsi">porsi</option>
                            <option value="bungkus">bungkus</option>
                            <option value="cup">cup</cup>
                            <option value="botol">botol</option>
                            <option value="gelas">gelas</gelas>
                            <option value="slice">slice</slice>
                            <option value="pack">pack</pack>
                            <option value="box">box</box>
                            <option value="kg">kg (kilogram)</option>
                            <option value="gram">gram</gram>
                            <option value="liter">liter</liter>
                            <option value="ml">ml (mililiter)</option>
                            <option value="custom">Lainnya (ketik sendiri)</option>
                        </select>
                        <input type="text" id="unit_custom" name="unit_custom" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 mt-2 hidden" placeholder="Ketik satuan custom...">
                    </div>
                    <div class="md:col-span-2">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-blue-800">Harga Jual Diatur di Manajemen Resep & HPP</h4>
                                    <p class="text-sm text-blue-700 mt-1">
                                        Harga jual akan diatur setelah Anda membuat resep di halaman 
                                        <a href="resep_produk.php" class="font-semibold underline hover:text-blue-900">Manajemen Resep & HPP</a> 
                                        berdasarkan kalkulasi HPP yang akurat.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200" id="submit_button">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Tambah Produk
                    </button>
                    <button type="button" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-200 hidden" id="cancel_edit_button" onclick="resetForm()">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Batal Edit
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Produk -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Daftar Produk Anda</h3>
                            <p class="text-sm text-gray-600">Kelola dan pantau semua produk dalam inventori</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <span class="text-sm text-gray-500">Total:</span>
                            <span class="text-lg font-bold text-blue-600 ml-1"><?php echo number_format($totalProducts); ?> produk</span>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Search Input -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
                            <input type="text" id="search-input" value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                                   placeholder="Cari nama produk..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                        </div>

                        <!-- Per Halaman -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Per Halaman</label>
                            <select id="limit-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                <?php foreach ($limit_options as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo ($limit == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    <!-- Grid filler untuk menjaga layout tetap rapi -->
                        <div></div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden" id="products-container">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual (Rp)</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr class="hover:bg-gray-50 transition duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['unit']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-green-600">Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                        class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    Edit
                                                </button>
                                                <a href="/cornerbites-sia/process/simpan_produk.php?action=delete&id=<?php echo htmlspecialchars($product['id']); ?>" 
                                                   class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200"
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                            <p class="text-gray-500 text-lg font-medium">Belum ada produk yang tercatat</p>
                                            <p class="text-gray-400 text-sm mt-1">Mulai tambahkan produk pertama Anda menggunakan form di atas</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="bg-white px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div class="flex items-center space-x-2">
                                <form id="limitForm" method="get" class="flex items-center space-x-2">
                                    <label for="limitSelect" class="text-sm text-gray-700">Per halaman:</label>
                                    <select name="limit" id="limitSelect" onchange="document.getElementById('limitForm').submit()"
                                            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <?php foreach ($limit_options as $opt): ?>
                                            <option value="<?php echo $opt; ?>" <?php echo ($limit == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                    <input type="hidden" name="page" value="1">
                                </form>
                                <div class="text-sm text-gray-700">
                                    Menampilkan <span class="font-medium"><?php echo number_format($offset + 1); ?></span> sampai 
                                    <span class="font-medium"><?php echo number_format(min($offset + $limit, $totalProducts)); ?></span> dari 
                                    <span class="font-medium"><?php echo number_format($totalProducts); ?></span> produk
                                </div>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Previous</span>
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php 
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    for ($i = $startPage; $i <= $endPage; $i++): 
                                    ?>
                                        <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $i; ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo ($i == $page) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="?search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Next</span>
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

    </div>
</div>

<script>
// Toggle custom unit input
function toggleCustomUnit() {
    const unitSelect = document.getElementById('unit');
    const customUnitInput = document.getElementById('unit_custom');

    if (unitSelect.value === 'custom') {
        customUnitInput.classList.remove('hidden');
        customUnitInput.required = true;
    } else {
        customUnitInput.classList.add('hidden');
        customUnitInput.required = false;
        customUnitInput.value = '';
    }
}

// Real-time search functionality
let searchTimeout;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchValue = this.value;
        const limitValue = document.getElementById('limit-select').value;
        loadProducts(searchValue, limitValue, 1);
    }, 500);
});

document.getElementById('limit-select').addEventListener('change', function() {
    const searchValue = document.getElementById('search-input').value;
    const limitValue = this.value;
    loadProducts(searchValue, limitValue, 1);
});

function loadProducts(search = '', limit = 10, page = 1) {
    const url = `?ajax=1&search=${encodeURIComponent(search)}&limit=${limit}&page=${page}`;

    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.getElementById('products-container').innerHTML = html;

            // Update URL without refreshing page
            const newUrl = `?search=${encodeURIComponent(search)}&limit=${limit}&page=${page}`;
            window.history.replaceState(null, '', newUrl);
        })
        .catch(error => {
            console.error('Error loading products:', error);
        });
}

// Edit product function
function editProduct(product) {
    document.getElementById('product_id_to_edit').value = product.id;
    document.getElementById('product_name').value = product.name;

    const unitSelect = document.getElementById('unit');
    const customUnitInput = document.getElementById('unit_custom');

    // Check if unit exists in options
    let unitFound = false;
    for (let option of unitSelect.options) {
        if (option.value === product.unit) {
            unitSelect.value = product.unit;
            unitFound = true;
            break;
        }
    }

    // If unit not found in options, use custom
    if (!unitFound) {
        unitSelect.value = 'custom';
        customUnitInput.classList.remove('hidden');
        customUnitInput.required = true;
        customUnitInput.value = product.unit;
    } else {
        customUnitInput.classList.add('hidden');
        customUnitInput.required = false;
        customUnitInput.value = '';
    }

    // Update form title and button
    document.getElementById('form_title').textContent = 'Edit Produk: ' + product.name;
    document.getElementById('form_description').textContent = 'Perbarui informasi produk yang sudah ada.';
    document.getElementById('submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
        Update Produk
    `;
    document.getElementById('cancel_edit_button').classList.remove('hidden');

    // Scroll to form
    document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
}

// Reset form function
function resetForm() {
    document.getElementById('product_id_to_edit').value = '';
    document.getElementById('product_name').value = '';
    document.getElementById('unit').value = '';
    document.getElementById('unit_custom').value = '';
    document.getElementById('unit_custom').classList.add('hidden');
    document.getElementById('unit_custom').required = false;

    // Reset form title and button
    document.getElementById('form_title').textContent = 'Tambah Produk Baru';
    document.getElementById('form_description').textContent = 'Isi detail produk baru Anda atau gunakan form ini untuk mengedit produk yang sudah ada.';
    document.getElementById('submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Tambah Produk
    `;
    document.getElementById('cancel_edit_button').classList.add('hidden');
}

// Show price management alert when editing product
function showPriceAlert() {
    if (document.getElementById('product_id_to_edit').value) {
        alert('Harga jual diatur di halaman Manajemen Resep & HPP berdasarkan kalkulasi HPP yang akurat.');
    }
}

// Add event listener to sale price field if it exists (for future compatibility)
document.addEventListener('DOMContentLoaded', function() {
    const salePriceField = document.getElementById('sale_price');
    if (salePriceField) {
        salePriceField.addEventListener('focus', showPriceAlert);
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>