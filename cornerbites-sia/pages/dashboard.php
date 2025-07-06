<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Initialize variables
$totalProducts = 0;
$totalRawMaterials = 0;
$lowStockProducts = 0;
$lowStockMaterials = 0;
$totalRecipes = 0;
$avgHPP = 0;
$highestProfitProduct = null;
$lowestProfitProduct = null;

try {
    $conn = $db;

    // Total Products
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products");
    $totalProducts = $stmt->fetch()['total'] ?? 0;

    // Total Raw Materials
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials");
    $totalRawMaterials = $stmt->fetch()['total'] ?? 0;

    // Low Stock Products (< 10)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock < 10");
    $lowStockProducts = $stmt->fetch()['total'] ?? 0;

    // Low Stock Raw Materials (< 1)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials WHERE current_stock < 1");
    $lowStockMaterials = $stmt->fetch()['total'] ?? 0;

    // Total Recipes
    $stmt = $conn->query("SELECT COUNT(DISTINCT product_id) as total FROM product_recipes");
    $totalRecipes = $stmt->fetch()['total'] ?? 0;

    // Calculate Average HPP
    $stmt = $conn->query("SELECT AVG(cost_price) as avg_hpp FROM products WHERE cost_price > 0");
    $avgHPP = $stmt->fetch()['avg_hpp'] ?? 0;

    // Product with highest profit margin
    $stmt = $conn->query("SELECT name, cost_price, sale_price, (sale_price - cost_price) as profit FROM products WHERE sale_price > 0 ORDER BY profit DESC LIMIT 1");
    $highestProfitProduct = $stmt->fetch();

    // Product with lowest profit margin
    $stmt = $conn->query("SELECT name, cost_price, sale_price, (sale_price - cost_price) as profit FROM products WHERE sale_price > 0 ORDER BY profit ASC LIMIT 1");
    $lowestProfitProduct = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Error di Dashboard: " . $e->getMessage());
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
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard HPP Calculator</h1>
                    <p class="text-gray-600">Kelola dan analisis Harga Pokok Produksi dengan metode Full Costing</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Products -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Produk</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Produk</h3>
                        <p class="text-2xl font-bold text-gray-800 mb-1"><?php echo number_format($totalProducts); ?></p>
                        <p class="text-xs text-gray-500">Produk terdaftar</p>
                    </div>

                    <!-- Total Raw Materials -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-1 rounded-full">Bahan</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Bahan Baku</h3>
                        <p class="text-2xl font-bold text-gray-800 mb-1"><?php echo number_format($totalRawMaterials); ?></p>
                        <p class="text-xs text-gray-500">Item bahan & kemasan</p>
                    </div>

                    <!-- Total Recipes -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-purple-600 bg-purple-100 px-2 py-1 rounded-full">Resep</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Resep Aktif</h3>
                        <p class="text-2xl font-bold text-gray-800 mb-1"><?php echo number_format($totalRecipes); ?></p>
                        <p class="text-xs text-gray-500">Resep produk dibuat</p>
                    </div>

                    <!-- Average HPP -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-indigo-400 to-indigo-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-indigo-600 bg-indigo-100 px-2 py-1 rounded-full">HPP</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Rata-rata HPP</h3>
                        <p class="text-2xl font-bold text-gray-800 mb-1">Rp <?php echo number_format($avgHPP, 0, ',', '.'); ?></p>
                        <p class="text-xs text-gray-500">Per unit produk</p>
                    </div>
                </div>

                <!-- Alert Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Low Stock Products -->
                    <?php if ($lowStockProducts > 0): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-yellow-800">Stok Produk Rendah</h3>
                                <p class="text-sm text-yellow-600"><?php echo $lowStockProducts; ?> produk dengan stok < 10</p>
                            </div>
                        </div>
                        <a href="/cornerbites-sia/pages/produk.php" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                            Lihat Detail →
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Low Stock Materials -->
                    <?php if ($lowStockMaterials > 0): ?>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-red-800">Bahan Baku Menipis</h3>
                                <p class="text-sm text-red-600"><?php echo $lowStockMaterials; ?> bahan dengan stok < 1</p>
                            </div>
                        </div>
                        <a href="/cornerbites-sia/pages/bahan_baku.php" class="text-red-600 hover:text-red-800 text-sm font-medium">
                            Lihat Detail →
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Profit Analysis -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Highest Profit Product -->
                    <?php if ($highestProfitProduct): ?>
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Produk Profit Tertinggi</h3>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="font-semibold text-green-800 mb-2"><?php echo htmlspecialchars($highestProfitProduct['name']); ?></h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">HPP:</span>
                                    <span class="font-medium">Rp <?php echo number_format($highestProfitProduct['cost_price'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Harga Jual:</span>
                                    <span class="font-medium">Rp <?php echo number_format($highestProfitProduct['sale_price'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="flex justify-between border-t pt-1">
                                    <span class="text-green-700 font-medium">Profit:</span>
                                    <span class="font-bold text-green-700">Rp <?php echo number_format($highestProfitProduct['profit'], 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Lowest Profit Product -->
                    <?php if ($lowestProfitProduct): ?>
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Produk Profit Terendah</h3>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h4 class="font-semibold text-red-800 mb-2"><?php echo htmlspecialchars($lowestProfitProduct['name']); ?></h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">HPP:</span>
                                    <span class="font-medium">Rp <?php echo number_format($lowestProfitProduct['cost_price'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Harga Jual:</span>
                                    <span class="font-medium">Rp <?php echo number_format($lowestProfitProduct['sale_price'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="flex justify-between border-t pt-1">
                                    <span class="text-red-700 font-medium">Profit:</span>
                                    <span class="font-bold text-red-700">Rp <?php echo number_format($lowestProfitProduct['profit'], 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>