<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$products = [];
$overhead_costs = [];
$labor_costs = [];
$hpp_analysis = [];

try {
    $conn = $db;
    
    // Get all products with recipes
    $stmt = $conn->query("
        SELECT DISTINCT p.id, p.name, p.cost_price, p.sale_price, p.production_yield,
               p.production_time_minutes
        FROM products p 
        JOIN product_recipes pr ON p.id = pr.product_id 
        ORDER BY p.name ASC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overhead costs
    $stmt = $conn->query("SELECT * FROM overhead_costs WHERE is_active = 1");
    $overhead_costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get labor costs
    $stmt = $conn->query("SELECT * FROM labor_costs WHERE is_active = 1");
    $labor_costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate HPP for each product
    foreach ($products as $product) {
        $hpp_calculation = calculateCompleteHPP($conn, $product, $overhead_costs, $labor_costs);
        $hpp_analysis[] = array_merge($product, $hpp_calculation);
    }
    
    // Sort by profit margin
    usort($hpp_analysis, function($a, $b) {
        return $b['profit_margin_percent'] <=> $a['profit_margin_percent'];
    });
    
} catch (PDOException $e) {
    error_log("Error di Analisis HPP: " . $e->getMessage());
}

function calculateCompleteHPP($conn, $product, $overhead_costs, $labor_costs) {
    $calculation = [
        'biaya_bahan_baku' => 0,
        'biaya_tenaga_kerja' => 0,
        'biaya_overhead' => 0,
        'total_hpp_per_unit' => 0,
        'profit_per_unit' => 0,
        'profit_margin_percent' => 0
    ];
    
    // 1. HITUNG BIAYA BAHAN BAKU (dari resep)
    $stmt = $conn->prepare("
        SELECT rm.purchase_price_per_unit, rm.default_package_quantity, pr.quantity_used
        FROM product_recipes pr 
        JOIN raw_materials rm ON pr.raw_material_id = rm.id 
        WHERE pr.product_id = ?
    ");
    $stmt->execute([$product['id']]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($materials as $material) {
        $cost_per_unit = $material['purchase_price_per_unit'] / max($material['default_package_quantity'], 1);
        $material_cost = $cost_per_unit * $material['quantity_used'];
        $calculation['biaya_bahan_baku'] += $material_cost;
    }
    
    // Adjust for production yield
    $yield = max($product['production_yield'], 1);
    $calculation['biaya_bahan_baku'] = $calculation['biaya_bahan_baku'] / $yield;
    
    // 2. HITUNG BIAYA TENAGA KERJA
    $production_time_hours = ($product['production_time_minutes'] ?? 60) / 60;
    
    if ($production_time_hours > 0 && !empty($labor_costs)) {
        $total_hourly_rate = 0;
        $active_workers = 0;
        
        foreach ($labor_costs as $labor) {
            $total_hourly_rate += $labor['hourly_rate'];
            $active_workers++;
        }
        
        $avg_hourly_rate = $active_workers > 0 ? $total_hourly_rate / $active_workers : 0;
        $calculation['biaya_tenaga_kerja'] = ($production_time_hours * $avg_hourly_rate) / $yield;
    }
    
    // 3. HITUNG BIAYA OVERHEAD
    $total_overhead_bulanan = 0;
    foreach ($overhead_costs as $overhead) {
        $total_overhead_bulanan += $overhead['amount'];
    }
    
    $overhead_per_unit = 0; // Remove the monthly production dependency, no division
    $calculation['biaya_overhead'] = $overhead_per_unit;
    
    // 4. TOTAL HPP PER UNIT
    $calculation['total_hpp_per_unit'] = $calculation['biaya_bahan_baku'] + 
                                        $calculation['biaya_tenaga_kerja'] + 
                                        $calculation['biaya_overhead'];
    
    // 5. ANALISIS PROFIT
    $sale_price = $product['sale_price'] ?? 0;
    $calculation['profit_per_unit'] = $sale_price - $calculation['total_hpp_per_unit'];
    $calculation['profit_margin_percent'] = $sale_price > 0 ? 
        ($calculation['profit_per_unit'] / $sale_price) * 100 : 0;
    
    return $calculation;
}

$message = '';
$message_type = '';
if (isset($_SESSION['analisis_message'])) {
    $message = $_SESSION['analisis_message']['text'];
    $message_type = $_SESSION['analisis_message']['type'];
    unset($_SESSION['analisis_message']);
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
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Analisis HPP & Profitabilitas</h1>
                    <p class="text-gray-600">Analisis mendalam perbandingan HPP, profit margin, dan ranking profitabilitas produk UMKM makanan</p>
                </div>

                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg border-l-4 <?php echo ($message_type == 'success' ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700'); ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-600">Total Produk</h3>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($hpp_analysis); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-lg mr-4">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-600">Rata-rata Margin</h3>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php 
                                    $avg_margin = count($hpp_analysis) > 0 ? 
                                        array_sum(array_column($hpp_analysis, 'profit_margin_percent')) / count($hpp_analysis) : 0;
                                    echo number_format($avg_margin, 1);
                                    ?>%
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-lg mr-4">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-600">Rata-rata HPP</h3>
                                <p class="text-2xl font-bold text-gray-900">
                                    Rp <?php 
                                    $avg_hpp = count($hpp_analysis) > 0 ? 
                                        array_sum(array_column($hpp_analysis, 'total_hpp_per_unit')) / count($hpp_analysis) : 0;
                                    echo number_format($avg_hpp, 0, ',', '.');
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-.118l-3.976-2.888c-.784-.57-.38-1.81.588-.181h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-600">Produk Profit > 0</h3>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php echo count(array_filter($hpp_analysis, function($p) { return $p['profit_per_unit'] > 0; })); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ranking Profitabilitas -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-gray-900 mb-6">🏆 Ranking Profitabilitas Produk</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Rank</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Nama Produk</th>
                                    <th class="text-right py-3 px-4 font-semibold text-gray-700">HPP per Unit</th>
                                    <th class="text-right py-3 px-4 font-semibold text-gray-700">Harga Jual</th>
                                    <th class="text-right py-3 px-4 font-semibold text-gray-700">Profit per Unit</th>
                                    <th class="text-right py-3 px-4 font-semibold text-gray-700">Margin (%)</th>
                                    <th class="text-center py-3 px-4 font-semibold text-gray-700">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hpp_analysis as $index => $product): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-4 px-4">
                                            <div class="flex items-center">
                                                <?php if ($index < 3): ?>
                                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full <?php echo $index == 0 ? 'bg-yellow-100 text-yellow-800' : ($index == 1 ? 'bg-gray-100 text-gray-800' : 'bg-orange-100 text-orange-800'); ?> font-bold text-sm">
                                                        <?php echo $index + 1; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-600 font-medium"><?php echo $index + 1; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                        </td>
                                        <td class="py-4 px-4 text-right text-gray-900">
                                            Rp <?php echo number_format($product['total_hpp_per_unit'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="py-4 px-4 text-right text-gray-900">
                                            Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="py-4 px-4 text-right">
                                            <span class="font-semibold <?php echo $product['profit_per_unit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                Rp <?php echo number_format($product['profit_per_unit'], 0, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-right">
                                            <span class="font-bold <?php echo $product['profit_margin_percent'] >= 20 ? 'text-green-600' : ($product['profit_margin_percent'] >= 10 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                <?php echo number_format($product['profit_margin_percent'], 1); ?>%
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <?php if ($product['profit_margin_percent'] >= 20): ?>
                                                <span class="px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Sangat Baik</span>
                                            <?php elseif ($product['profit_margin_percent'] >= 10): ?>
                                                <span class="px-3 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Cukup Baik</span>
                                            <?php elseif ($product['profit_margin_percent'] >= 0): ?>
                                                <span class="px-3 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">Perlu Perbaikan</span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rugi</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Detail Analysis Cards -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Breakdown Biaya -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Rata-rata Breakdown Biaya</h3>
                        
                        <?php 
                        $total_products = count($hpp_analysis);
                        if ($total_products > 0):
                            $avg_bahan = array_sum(array_column($hpp_analysis, 'biaya_bahan_baku')) / $total_products;
                            $avg_tenaga = array_sum(array_column($hpp_analysis, 'biaya_tenaga_kerja')) / $total_products;
                            $avg_overhead = array_sum(array_column($hpp_analysis, 'biaya_overhead')) / $total_products;
                            $total_avg = $avg_bahan + $avg_tenaga + $avg_overhead;
                        ?>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg">
                                <span class="font-medium text-orange-900">Biaya Bahan Baku</span>
                                <div class="text-right">
                                    <div class="font-bold text-orange-900">Rp <?php echo number_format($avg_bahan, 0, ',', '.'); ?></div>
                                    <div class="text-xs text-orange-700">
                                        <?php echo $total_avg > 0 ? number_format(($avg_bahan / $total_avg) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <span class="font-medium text-blue-900">Biaya Tenaga Kerja</span>
                                <div class="text-right">
                                    <div class="font-bold text-blue-900">Rp <?php echo number_format($avg_tenaga, 0, ',', '.'); ?></div>
                                    <div class="text-xs text-blue-700">
                                        <?php echo $total_avg > 0 ? number_format(($avg_tenaga / $total_avg) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                                <span class="font-medium text-purple-900">Biaya Overhead</span>
                                <div class="text-right">
                                    <div class="font-bold text-purple-900">Rp <?php echo number_format($avg_overhead, 0, ',', '.'); ?></div>
                                    <div class="text-xs text-purple-700">
                                        <?php echo $total_avg > 0 ? number_format(($avg_overhead / $total_avg) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rekomendasi -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">💡 Rekomendasi Strategis</h3>
                        
                        <div class="space-y-4">
                            <?php 
                            $profitable_products = array_filter($hpp_analysis, function($p) { return $p['profit_margin_percent'] >= 20; });
                            $low_margin_products = array_filter($hpp_analysis, function($p) { return $p['profit_margin_percent'] < 10 && $p['profit_margin_percent'] >= 0; });
                            $loss_products = array_filter($hpp_analysis, function($p) { return $p['profit_margin_percent'] < 0; });
                            ?>
                            
                            <?php if (count($profitable_products) > 0): ?>
                                <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                                    <h4 class="font-semibold text-green-800 mb-2">✅ Produk Unggulan (<?php echo count($profitable_products); ?> produk)</h4>
                                    <p class="text-sm text-green-700">Fokuskan pemasaran pada produk dengan margin > 20%. Tingkatkan produksi untuk memaksimalkan keuntungan.</p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (count($low_margin_products) > 0): ?>
                                <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <h4 class="font-semibold text-yellow-800 mb-2">⚠️ Perlu Optimasi (<?php echo count($low_margin_products); ?> produk)</h4>
                                    <p class="text-sm text-yellow-700">Review resep dan supplier untuk mengurangi biaya bahan baku. Pertimbangkan penyesuaian harga jual.</p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (count($loss_products) > 0): ?>
                                <div class="p-4 bg-red-50 rounded-lg border border-red-200">
                                    <h4 class="font-semibold text-red-800 mb-2">🚨 Produk Bermasalah (<?php echo count($loss_products); ?> produk)</h4>
                                    <p class="text-sm text-red-700">Segera review total. Pertimbangkan revisi resep, ganti supplier, atau hentikan produksi sementara.</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h4 class="font-semibold text-blue-800 mb-2">📈 Tips Umum</h4>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• Target margin minimum 15-20% untuk UMKM makanan</li>
                                    <li>• Review HPP setiap bulan karena fluktuasi harga bahan</li>
                                    <li>• Negosiasi dengan supplier untuk pembelian dalam jumlah besar</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>