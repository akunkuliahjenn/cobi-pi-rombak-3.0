
<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Handle AJAX request for ranking pagination
if (isset($_GET['ajax']) && $_GET['ajax'] === 'ranking') {
    $ranking_page = isset($_GET['ranking_page']) ? max((int)$_GET['ranking_page'], 1) : 1;
    $ranking_limit = 5;
    $ranking_offset = ($ranking_page - 1) * $ranking_limit;

    try {
        $conn = $db;
        
        // Count total products for pagination
        $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE sale_price > 0");
        $result = $stmt->fetch();
        $total_products_ranking = $result ? ($result['total'] ?? 0) : 0;
        $total_ranking_pages = ceil($total_products_ranking / $ranking_limit);

        // Get ranking data
        $stmt = $conn->prepare("SELECT name, cost_price, sale_price, (sale_price - cost_price) as profit, ((sale_price - cost_price) / sale_price * 100) as margin, 
                              CASE WHEN sale_price > cost_price THEN 'Menguntungkan' ELSE 'Rugi' END as status 
                              FROM products WHERE sale_price > 0 
                              ORDER BY profit DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $ranking_limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $ranking_offset, PDO::PARAM_INT);
        $stmt->execute();
        $profitabilityRanking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HPP per Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit per Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin (%)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($profitabilityRanking)): ?>
                        <?php foreach ($profitabilityRanking as $index => $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo (($ranking_page - 1) * $ranking_limit) + $index + 1; ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['cost_price'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['profit'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($product['margin'], 1); ?>%</td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['status'] == 'Menguntungkan' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $product['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada data produk</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination for Ranking -->
        <?php if ($total_ranking_pages > 1): ?>
        <div class="mt-6 flex justify-between items-center">
            <div class="text-sm text-gray-700">
                Menampilkan <?php echo (($ranking_page - 1) * $ranking_limit) + 1; ?> - 
                <?php echo min($ranking_page * $ranking_limit, $total_products_ranking); ?> 
                dari <?php echo $total_products_ranking; ?> produk
            </div>
            <div class="flex space-x-2">
                <?php if ($ranking_page > 1): ?>
                    <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)"
                           class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Previous
                    </button>
                <?php endif; ?>

                <?php
                $start_page = max(1, $ranking_page - 2);
                $end_page = min($total_ranking_pages, $ranking_page + 2);
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <button onclick="loadRankingData(<?php echo $i; ?>)"
                           class="px-3 py-2 text-sm <?php echo $i == $ranking_page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>

                <?php if ($ranking_page < $total_ranking_pages): ?>
                    <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)"
                           class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Next
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php
        $content = ob_get_clean();
        echo $content;
        exit;
    } catch (Exception $e) {
        echo '<div class="text-center text-red-500 py-8">Error loading data</div>';
        exit;
    }
}

// Initialize variables with default values
$totalProducts = 0;
$totalRawMaterials = 0;
$lowStockProducts = 0;
$lowStockMaterials = 0;
$totalRecipes = 0;
$avgHPP = 0;
$avgMargin = 0;
$profitableProducts = 0;
$totalBahanBaku = 0;
$totalKemasan = 0;
$totalLaborPositions = 0;
$totalOverheadItems = 0;
$totalLaborCost = 0;
$totalOverheadCost = 0;
$highestProfitProduct = null;
$lowestProfitProduct = null;
$profitabilityRanking = [];
$total_products_ranking = 0;
$total_ranking_pages = 0;
$costBreakdown = [
    'bahan_baku' => 0,
    'kemasan' => 0,
    'tenaga_kerja' => 0,
    'overhead' => 0
];

try {
    $conn = $db;

    // Total Products
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products");
    $result = $stmt->fetch();
    $totalProducts = $result ? ($result['total'] ?? 0) : 0;

    // Total Raw Materials (Bahan Baku + Kemasan)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials");
    $result = $stmt->fetch();
    $totalRawMaterials = $result ? ($result['total'] ?? 0) : 0;

    // Total Bahan Baku only
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials WHERE type = 'bahan'");
    $result = $stmt->fetch();
    $totalBahanBaku = $result ? ($result['total'] ?? 0) : 0;

    // Total Kemasan only  
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials WHERE type = 'kemasan'");
    $result = $stmt->fetch();
    $totalKemasan = $result ? ($result['total'] ?? 0) : 0;

    // Low Stock Products (< 10)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock < 10");
    $result = $stmt->fetch();
    $lowStockProducts = $result ? ($result['total'] ?? 0) : 0;

    // Low Stock Raw Materials (< 1)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials WHERE current_stock < 1");
    $result = $stmt->fetch();
    $lowStockMaterials = $result ? ($result['total'] ?? 0) : 0;

    // Total Recipes Active (products that have recipes)
    $stmt = $conn->query("SELECT COUNT(DISTINCT product_id) as total FROM product_recipes");
    $result = $stmt->fetch();
    $totalRecipes = $result ? ($result['total'] ?? 0) : 0;

    // Total Labor Positions and Cost
    $stmt = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(hourly_rate), 0) as total_cost FROM labor_costs WHERE is_active = 1");
    $result = $stmt->fetch();
    $totalLaborPositions = $result ? ($result['total'] ?? 0) : 0;
    $totalLaborCost = $result ? ($result['total_cost'] ?? 0) : 0;

    // Total Overhead Items and Cost
    $stmt = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_cost FROM overhead_costs WHERE is_active = 1");
    $result = $stmt->fetch();
    $totalOverheadItems = $result ? ($result['total'] ?? 0) : 0;
    $totalOverheadCost = $result ? ($result['total_cost'] ?? 0) : 0;

    // Calculate Average HPP
    $stmt = $conn->query("SELECT AVG(cost_price) as avg_hpp FROM products WHERE cost_price > 0");
    $result = $stmt->fetch();
    $avgHPP = $result ? ($result['avg_hpp'] ?? 0) : 0;

    // Calculate Average Margin
    $stmt = $conn->query("SELECT AVG(((sale_price - cost_price) / sale_price) * 100) as avg_margin FROM products WHERE sale_price > 0 AND cost_price > 0");
    $result = $stmt->fetch();
    $avgMargin = $result ? ($result['avg_margin'] ?? 0) : 0;

    // Count Profitable Products (profit > 0)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE sale_price > cost_price AND sale_price > 0");
    $result = $stmt->fetch();
    $profitableProducts = $result ? ($result['total'] ?? 0) : 0;

    // Product with highest profit margin
    $stmt = $conn->query("SELECT name, cost_price, sale_price, (sale_price - cost_price) as profit, ((sale_price - cost_price) / sale_price * 100) as margin FROM products WHERE sale_price > 0 ORDER BY profit DESC LIMIT 1");
    $highestProfitProduct = $stmt->fetch();

    // Product with lowest profit margin
    $stmt = $conn->query("SELECT name, cost_price, sale_price, (sale_price - cost_price) as profit, ((sale_price - cost_price) / sale_price * 100) as margin FROM products WHERE sale_price > 0 ORDER BY profit ASC LIMIT 1");
    $lowestProfitProduct = $stmt->fetch();

    // Pagination for profitability ranking
    $ranking_page = isset($_GET['ranking_page']) ? max((int)$_GET['ranking_page'], 1) : 1;
    $ranking_limit = 5;
    $ranking_offset = ($ranking_page - 1) * $ranking_limit;

    // Count total products for pagination
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE sale_price > 0");
    $result = $stmt->fetch();
    $total_products_ranking = $result ? ($result['total'] ?? 0) : 0;
    $total_ranking_pages = ceil($total_products_ranking / $ranking_limit);

    // Ensure $total_ranking_pages is at least 1 if there are products
    if ($total_products_ranking > 0 && $total_ranking_pages == 0) {
        $total_ranking_pages = 1;
    }

    // Profitability Ranking with pagination
    if ($total_products_ranking > 0) {
        $stmt = $conn->prepare("SELECT name, cost_price, sale_price, (sale_price - cost_price) as profit, ((sale_price - cost_price) / sale_price * 100) as margin, 
                              CASE WHEN sale_price > cost_price THEN 'Menguntungkan' ELSE 'Rugi' END as status 
                              FROM products WHERE sale_price > 0 
                              ORDER BY profit DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $ranking_limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $ranking_offset, PDO::PARAM_INT);
        $stmt->execute();
        $profitabilityRanking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Cost Breakdown Analysis - Enhanced to include all cost types
    $stmt = $conn->query("
        SELECT 
            SUM(CASE WHEN rm.type = 'bahan' THEN pr.quantity * rm.purchase_price_per_unit / rm.default_package_quantity ELSE 0 END) as bahan_cost,
            SUM(CASE WHEN rm.type = 'kemasan' THEN pr.quantity * rm.purchase_price_per_unit / rm.default_package_quantity ELSE 0 END) as kemasan_cost
        FROM product_recipes pr
        JOIN raw_materials rm ON pr.raw_material_id = rm.id
    ");
    $breakdown = $stmt->fetch();
    
    if ($breakdown) {
        $costBreakdown['bahan_baku'] = $breakdown['bahan_cost'] ?? 0;
        $costBreakdown['kemasan'] = $breakdown['kemasan_cost'] ?? 0;
    }

    // Add labor and overhead costs to breakdown
    $costBreakdown['tenaga_kerja'] = $totalLaborCost;
    $costBreakdown['overhead'] = $totalOverheadCost;

} catch (PDOException $e) {
    error_log("Error di Dashboard: " . $e->getMessage());
    // Variables are already initialized above, so no need to reinitialize
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
                    <p class="text-gray-600">Analisis Harga Pokok Produksi dengan metode Full Costing</p>
                </div>

                <!-- Main Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Products -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Total Produk</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Produk</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalProducts); ?></p>
                        <p class="text-xs text-gray-500">Produk terdaftar</p>
                    </div>

                    <!-- Average Margin -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-1 rounded-full">Rata-rata Margin</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Rata-rata Margin</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($avgMargin, 1); ?>%</p>
                        <p class="text-xs text-gray-500">Margin keuntungan</p>
                    </div>

                    <!-- Average HPP -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-purple-600 bg-purple-100 px-2 py-1 rounded-full">Rata-rata HPP</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Rata-rata HPP</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1">Rp <?php echo number_format($avgHPP, 0, ',', '.'); ?></p>
                        <p class="text-xs text-gray-500">Per unit produk</p>
                    </div>

                    <!-- Profitable Products -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-yellow-600 bg-yellow-100 px-2 py-1 rounded-full">Produk Profit > 0</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Produk Profit > 0</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($profitableProducts); ?></p>
                        <p class="text-xs text-gray-500">Produk menguntungkan</p>
                    </div>
                </div>

                <!-- Additional Integrated Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Resep Aktif -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-indigo-400 to-indigo-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-indigo-600 bg-indigo-100 px-2 py-1 rounded-full">Resep Aktif</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Resep Aktif</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalRecipes); ?></p>
                        <p class="text-xs text-gray-500">Produk dengan resep</p>
                    </div>

                    <!-- Total Bahan Baku -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-amber-400 to-amber-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-amber-600 bg-amber-100 px-2 py-1 rounded-full">Bahan Baku</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Bahan Baku</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalBahanBaku); ?></p>
                        <p class="text-xs text-gray-500"><?php echo number_format($totalKemasan); ?> kemasan</p>
                    </div>

                    <!-- Total Tenaga Kerja -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-orange-400 to-orange-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-orange-600 bg-orange-100 px-2 py-1 rounded-full">Tenaga Kerja</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Tenaga Kerja</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalLaborPositions); ?></p>
                        <p class="text-xs text-gray-500">Rp <?php echo number_format($totalLaborCost, 0, ',', '.'); ?>/jam</p>
                    </div>

                    <!-- Total Overhead -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-emerald-600 bg-emerald-100 px-2 py-1 rounded-full">Overhead</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Overhead</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalOverheadItems); ?></p>
                        <p class="text-xs text-gray-500">Rp <?php echo number_format($totalOverheadCost, 0, ',', '.'); ?></p>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Ranking Profitabilitas Produk -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Ranking Profitabilitas Produk</h3>
                        </div>

                        <div id="ranking-container">
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HPP per Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit per Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin (%)</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (!empty($profitabilityRanking)): ?>
                                            <?php foreach ($profitabilityRanking as $index => $product): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo (($ranking_page - 1) * $ranking_limit) + $index + 1; ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['cost_price'], 0, ',', '.'); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['profit'], 0, ',', '.'); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($product['margin'], 1); ?>%</td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['status'] == 'Menguntungkan' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo $product['status']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada data produk</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination for Ranking -->
                            <?php if ($total_ranking_pages > 1): ?>
                            <div class="mt-6 flex justify-between items-center">
                                <div class="text-sm text-gray-700">
                                    Menampilkan <?php echo (($ranking_page - 1) * $ranking_limit) + 1; ?> - 
                                    <?php echo min($ranking_page * $ranking_limit, $total_products_ranking); ?> 
                                    dari <?php echo $total_products_ranking; ?> produk
                                </div>
                                <div class="flex space-x-2">
                                    <?php if ($ranking_page > 1): ?>
                                        <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)"
                                               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                            Previous
                                        </button>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $ranking_page - 2);
                                    $end_page = min($total_ranking_pages, $ranking_page + 2);
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <button onclick="loadRankingData(<?php echo $i; ?>)"
                                               class="px-3 py-2 text-sm <?php echo $i == $ranking_page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors">
                                            <?php echo $i; ?>
                                        </button>
                                    <?php endfor; ?>

                                    <?php if ($ranking_page < $total_ranking_pages): ?>
                                        <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)"
                                               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                            Next
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- System Overview -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                            <div class="flex items-center mb-6">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">Overview Sistem</h3>
                            </div>

                            <div class="space-y-4">
                                <!-- Resep Coverage -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700">Coverage Resep</span>
                                        <span class="text-sm text-gray-600"><?php echo $totalProducts > 0 ? number_format(($totalRecipes / $totalProducts) * 100, 1) : 0; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-500 h-2 rounded-full" style="width: <?php echo $totalProducts > 0 ? ($totalRecipes / $totalProducts) * 100 : 0; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo number_format($totalRecipes); ?> dari <?php echo number_format($totalProducts); ?> produk</div>
                                </div>

                                <!-- Bahan Baku vs Kemasan -->
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700">Bahan Baku</span>
                                        <span class="text-sm text-gray-600"><?php echo $totalRawMaterials > 0 ? number_format(($totalBahanBaku / $totalRawMaterials) * 100, 1) : 0; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-amber-500 h-2 rounded-full" style="width: <?php echo $totalRawMaterials > 0 ? ($totalBahanBaku / $totalRawMaterials) * 100 : 0; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo number_format($totalBahanBaku); ?> bahan, <?php echo number_format($totalKemasan); ?> kemasan</div>
                                </div>

                                <!-- Cost Breakdown -->
                                <?php
                                $totalCost = array_sum($costBreakdown);
                                $colors = [
                                    'bahan_baku' => 'blue', 
                                    'kemasan' => 'green', 
                                    'tenaga_kerja' => 'orange',
                                    'overhead' => 'purple'
                                ];
                                $labels = [
                                    'bahan_baku' => 'Bahan Baku', 
                                    'kemasan' => 'Kemasan', 
                                    'tenaga_kerja' => 'Tenaga Kerja',
                                    'overhead' => 'Overhead'
                                ];
                                ?>
                                <?php foreach ($costBreakdown as $type => $cost): ?>
                                    <?php 
                                    $percentage = $totalCost > 0 ? ($cost / $totalCost) * 100 : 0;
                                    $color = $colors[$type];
                                    ?>
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-700"><?php echo $labels[$type]; ?></span>
                                            <span class="text-sm text-gray-600"><?php echo number_format($percentage, 1); ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-<?php echo $color; ?>-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">Rp <?php echo number_format($cost, 0, ',', '.'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Rekomendasi Strategis -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                            <div class="flex items-center mb-6">
                                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">Rekomendasi Strategis</h3>
                            </div>

                            <div class="space-y-4">
                                <div class="bg-blue-50 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-semibold text-blue-800 mb-2">Tips Umum</h4>
                                            <ul class="text-sm text-blue-700 space-y-1">
                                                <li>• Target margin minimum 15-20% untuk UMKM makanan</li>
                                                <li>• Review HPP setiap bulan karena fluktuasi harga bahan</li>
                                                <li>• Negosiasi dengan supplier untuk pembelian dalam jumlah besar</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($totalRecipes < $totalProducts && $totalProducts > 0): ?>
                                <div class="bg-amber-50 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-amber-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-semibold text-amber-800 mb-2">Lengkapi Resep</h4>
                                            <p class="text-sm text-amber-700">
                                                <?php echo $totalProducts - $totalRecipes; ?> produk belum memiliki resep. 
                                                Lengkapi resep untuk perhitungan HPP yang akurat.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($totalLaborPositions == 0): ?>
                                <div class="bg-orange-50 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-orange-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-semibold text-orange-800 mb-2">Setup Tenaga Kerja</h4>
                                            <p class="text-sm text-orange-700">
                                                Belum ada data tenaga kerja. Tambahkan posisi & upah untuk kalkulasi HPP lengkap.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($totalOverheadItems == 0): ?>
                                <div class="bg-emerald-50 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-emerald-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-semibold text-emerald-800 mb-2">Setup Overhead</h4>
                                            <p class="text-sm text-emerald-700">
                                                Belum ada data overhead. Tambahkan biaya listrik, sewa, dll untuk HPP akurat.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($avgMargin < 15): ?>
                                <div class="bg-red-50 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-semibold text-red-800 mb-2">Peringatan Margin</h4>
                                            <p class="text-sm text-red-700">Margin rata-rata Anda di bawah 15%. Pertimbangkan untuk menaikkan harga jual atau efisiensi biaya.</p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($lowStockProducts > 0 || $lowStockMaterials > 0): ?>
                                <div class="bg-yellow-50 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-semibold text-yellow-800 mb-2">Stok Rendah</h4>
                                            <p class="text-sm text-yellow-700">
                                                Ada <?php echo $lowStockProducts + $lowStockMaterials; ?> item dengan stok rendah. 
                                                Segera lakukan restock untuk menghindari kehabisan stok.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Section (if needed) -->
                <?php if ($lowStockProducts > 0 || $lowStockMaterials > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
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
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
function loadRankingData(page) {
    fetch(`dashboard.php?ajax=ranking&ranking_page=${page}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('ranking-container').innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading ranking data:', error);
        });
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
