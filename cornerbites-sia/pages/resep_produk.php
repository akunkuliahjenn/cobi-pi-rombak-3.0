<?php
// pages/resep_produk.php
// Halaman untuk mengelola resep produk (komposisi bahan baku/kemasan untuk setiap produk jadi) dengan kalkulasi HPP

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$message = '';
$message_type = '';
if (isset($_SESSION['resep_message'])) {
    $message = $_SESSION['resep_message']['text'];
    $message_type = $_SESSION['resep_message']['type'];
    unset($_SESSION['resep_message']);
}

$products = [];
$rawMaterialsAndPackaging = [];
$productRecipes = [];
$selectedProductId = $_GET['product_id'] ?? null;
$selectedProduct = null;
$hppCalculation = null;

// Satuan yang umum digunakan dalam resep
$recipeUnitOptions = ['gram', 'kg', 'ml', 'liter', 'pcs', 'buah', 'sendok teh', 'sendok makan', 'cangkir'];

// Inisialisasi variabel pencarian dan pagination untuk resep
$searchQueryRecipe = $_GET['search_recipe'] ?? '';
$totalRecipesRows = 0;
$totalRecipesPages = 1;
$recipesLimitOptions = [6, 12, 18, 24];
$recipesLimit = isset($_GET['recipe_limit']) && in_array((int)$_GET['recipe_limit'], $recipesLimitOptions) ? (int)$_GET['recipe_limit'] : 6;
$recipesPage = isset($_GET['recipe_page']) ? max((int)$_GET['recipe_page'], 1) : 1;
$recipesOffset = ($recipesPage - 1) * $recipesLimit;

try {
    $conn = $db;

    // Test database connection
    $conn->query("SELECT 1");

    // Ambil daftar produk untuk dropdown dengan error handling
    $stmtProducts = $conn->prepare("SELECT id, name, COALESCE(cost_price, 0) as cost_price, COALESCE(sale_price, 0) as sale_price, COALESCE(production_yield, 1) as production_yield, COALESCE(production_time_hours, 1) as production_time_hours FROM products ORDER BY name ASC");
    $stmtProducts->execute();
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

    // Ambil semua bahan baku dan kemasan dengan informasi stok terakhir
    $stmtRawMaterials = $conn->prepare("
        SELECT rm.id, rm.name, rm.unit, rm.type, COALESCE(rm.brand, '') as brand, 
               rm.current_stock,
               COALESCE(SUM(pr.quantity_used), 0) as total_used,
               (rm.current_stock - COALESCE(SUM(pr.quantity_used), 0)) as stok_terakhir
        FROM raw_materials rm
        LEFT JOIN product_recipes pr ON rm.id = pr.raw_material_id
        GROUP BY rm.id
        ORDER BY rm.name ASC
    ");
    $stmtRawMaterials->execute();
    $rawMaterialsAndPackaging = $stmtRawMaterials->fetchAll(PDO::FETCH_ASSOC);

    // Jika ada produk yang dipilih
    if ($selectedProductId) {
        // Ambil detail produk yang dipilih
        $stmtSelectedProduct = $conn->prepare("SELECT id, name, cost_price, sale_price, production_yield, production_time_hours FROM products WHERE id = ?");
        $stmtSelectedProduct->execute([$selectedProductId]);
        $selectedProduct = $stmtSelectedProduct->fetch(PDO::FETCH_ASSOC);

        // Hitung total baris untuk resep dengan filter pencarian
        $queryTotalRecipe = "
            SELECT COUNT(*) 
            FROM product_recipes pr
            JOIN raw_materials rm ON pr.raw_material_id = rm.id
            WHERE pr.product_id = :product_id
        ";
        if (!empty($searchQueryRecipe)) {
            $queryTotalRecipe .= " AND rm.name LIKE :search_recipe_term";
        }

        $stmtTotalRecipe = $conn->prepare($queryTotalRecipe);
        $stmtTotalRecipe->bindValue(':product_id', $selectedProductId, PDO::PARAM_INT);
        if (!empty($searchQueryRecipe)) {
            $stmtTotalRecipe->bindValue(':search_recipe_term', '%' . $searchQueryRecipe . '%', PDO::PARAM_STR);
        }
        $stmtTotalRecipe->execute();
        $totalRecipesRows = $stmtTotalRecipe->fetchColumn();
        $totalRecipesPages = ceil($totalRecipesRows / $recipesLimit);

        // Pastikan halaman tidak melebihi total halaman yang ada
        if ($recipesPage > $totalRecipesPages && $totalRecipesPages > 0) {
            $recipesPage = $totalRecipesPages;
            $recipesOffset = ($recipesPage - 1) * $recipesLimit;
        }

        // Query untuk mengambil resep dengan LIMIT, OFFSET, dan filter pencarian
        $queryRecipes = "
            SELECT pr.id, pr.product_id, pr.raw_material_id, pr.quantity_used, pr.unit_measurement,
                   rm.name AS raw_material_name, rm.unit AS raw_material_stock_unit, rm.purchase_price_per_unit, 
                   rm.default_package_quantity, rm.type AS raw_material_type, rm.brand AS raw_material_brand
            FROM product_recipes pr
            JOIN raw_materials rm ON pr.raw_material_id = rm.id
            WHERE pr.product_id = :product_id
        ";
        if (!empty($searchQueryRecipe)) {
            $queryRecipes .= " AND rm.name LIKE :search_recipe_term";
        }
        $queryRecipes .= " ORDER BY rm.name ASC LIMIT :limit OFFSET :offset";

        $stmtRecipes = $conn->prepare($queryRecipes);
        $stmtRecipes->bindValue(':product_id', $selectedProductId, PDO::PARAM_INT);
        if (!empty($searchQueryRecipe)) {
            $stmtRecipes->bindValue(':search_recipe_term', '%' . $searchQueryRecipe . '%', PDO::PARAM_STR);
        }
        $stmtRecipes->bindParam(':limit', $recipesLimit, PDO::PARAM_INT);
        $stmtRecipes->bindParam(':offset', $recipesOffset, PDO::PARAM_INT);
        $stmtRecipes->execute();
        $productRecipes = $stmtRecipes->fetchAll(PDO::FETCH_ASSOC);

        // Hitung HPP berdasarkan resep (termasuk overhead dan tenaga kerja)
        if ($selectedProduct && !empty($productRecipes)) {
            $totalCostPerBatch = 0;
            $recipeDetails = [];

            // 1. BIAYA BAHAN BAKU - Ambil semua item resep untuk perhitungan HPP
            $stmtAllRecipes = $conn->prepare("
                SELECT pr.quantity_used, pr.unit_measurement,
                       rm.name AS raw_material_name, rm.purchase_price_per_unit, 
                       rm.default_package_quantity, rm.unit AS raw_material_stock_unit, rm.type
                FROM product_recipes pr
                JOIN raw_materials rm ON pr.raw_material_id = rm.id
                WHERE pr.product_id = ?
                ORDER BY rm.name ASC
            ");
            $stmtAllRecipes->execute([$selectedProductId]);
            $allRecipeItems = $stmtAllRecipes->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allRecipeItems as $item) {
                $costPerItem = 0;

                // Hitung biaya berdasarkan harga beli per unit dan quantity used
                if ($item['default_package_quantity'] && $item['default_package_quantity'] > 0) {
                    // Jika ada default package quantity, hitung proporsi
                    $costPerUnit = $item['purchase_price_per_unit'] / $item['default_package_quantity'];
                    $costPerItem = $costPerUnit * $item['quantity_used'];
                } else {
                    // Jika tidak ada default package, gunakan harga langsung
                    $costPerItem = ($item['purchase_price_per_unit'] / 1) * $item['quantity_used'];
                }

                $totalCostPerBatch += $costPerItem;

                $recipeDetails[] = [
                    'name' => $item['raw_material_name'],
                    'type' => $item['type'],
                    'quantity_used' => $item['quantity_used'],
                    'unit_measurement' => $item['unit_measurement'],
                    'cost_per_item' => $costPerItem,
                    'category' => 'bahan_baku'
                ];
            }

            // 2. BIAYA TENAGA KERJA MANUAL - Hanya yang dipilih untuk produk ini
            $laborCostPerBatch = 0;
            $laborDetails = [];
            $estimatedProductionTimeHours = $selectedProduct['production_time_hours'] ?? 1;

            // Ambil tenaga kerja manual yang sudah dipilih untuk produk ini
            $stmtManualLabor = $conn->prepare("
                SELECT plm.*, lc.position_name, lc.hourly_rate as default_hourly_rate
                FROM product_labor_manual plm
                JOIN labor_costs lc ON plm.labor_id = lc.id
                WHERE plm.product_id = ? AND lc.is_active = 1
                ORDER BY lc.position_name ASC
            ");
            $stmtManualLabor->execute([$selectedProductId]);
            $manualLaborCosts = $stmtManualLabor->fetchAll(PDO::FETCH_ASSOC);

            foreach ($manualLaborCosts as $labor) {
                $laborCostPerBatch += $labor['total_cost'];

                $laborDetails[] = [
                    'name' => $labor['position_name'],
                    'type' => 'tenaga_kerja',
                    'hourly_rate' => $labor['custom_hourly_rate'] ?? $labor['default_hourly_rate'],
                    'production_time' => $labor['custom_hours'] ?? $estimatedProductionTimeHours,
                    'cost_per_item' => $labor['total_cost'],
                    'category' => 'tenaga_kerja',
                    'manual_id' => $labor['id']
                ];
            }

            // 3. BIAYA OVERHEAD MANUAL - Hanya yang dipilih untuk produk ini
            $overheadCostPerBatch = 0;
            $overheadDetails = [];
            $productionYield = $selectedProduct['production_yield'] ?? 1;

            // Ambil overhead manual yang sudah dipilih untuk produk ini
            $stmtManualOverhead = $conn->prepare("
                SELECT pom.*, oc.name, oc.description, oc.allocation_method, oc.amount as default_amount
                FROM product_overhead_manual pom
                JOIN overhead_costs oc ON pom.overhead_id = oc.id
                WHERE pom.product_id = ? AND oc.is_active = 1
                ORDER BY oc.name ASC
            ");
            $stmtManualOverhead->execute([$selectedProductId]);
            $manualOverheadCosts = $stmtManualOverhead->fetchAll(PDO::FETCH_ASSOC);

            foreach ($manualOverheadCosts as $overhead) {
                $overheadCostPerBatch += $overhead['final_amount'];

                $overheadDetails[] = [
                    'name' => $overhead['name'],
                    'type' => 'overhead',
                    'amount' => $overhead['custom_amount'] ?? $overhead['default_amount'],
                    'allocation_method' => $overhead['allocation_method'],
                    'description' => $overhead['description'],
                    'cost_per_item' => $overhead['final_amount'],
                    'category' => 'overhead',
                    'manual_id' => $overhead['id']
                ];
            }

            // 4. TOTAL HPP PER BATCH DAN PER UNIT
            $totalCostBahanBaku = $totalCostPerBatch;
            $totalCostPerBatch = $totalCostBahanBaku + $laborCostPerBatch + $overheadCostPerBatch;

            // Hitung HPP per unit berdasarkan production yield
            $hppPerUnit = $productionYield > 0 ? $totalCostPerBatch / $productionYield : 0;

            // Hitung profit margin
            $salePrice = $selectedProduct['sale_price'] ?? 0;
            $profitPerUnit = $salePrice - $hppPerUnit;
            $profitMarginPercent = $salePrice > 0 ? ($profitPerUnit / $salePrice) * 100 : 0;

            $hppCalculation = [
                'total_cost_per_batch' => $totalCostPerBatch,
                'total_cost_bahan_baku' => $totalCostBahanBaku,
                'total_cost_tenaga_kerja' => $laborCostPerBatch,
                'total_cost_overhead' => $overheadCostPerBatch,
                'production_yield' => $productionYield,
                'production_time_hours' => $estimatedProductionTimeHours,
                'hpp_per_unit' => $hppPerUnit,
                'sale_price' => $salePrice,
                'profit_per_unit' => $profitPerUnit,
                'profit_margin_percent' => $profitMarginPercent,
                'recipe_details' => $recipeDetails,
                'labor_details' => $laborDetails,
                'overhead_details' => $overheadDetails
            ];
        }
    }

} catch (PDOException $e) {
    error_log("Error di halaman Resep Produk: " . $e->getMessage());
    $message = "Terjadi kesalahan database: " . $e->getMessage();
    $message_type = "error";
} catch (Exception $e) {
    error_log("General error di halaman Resep Produk: " . $e->getMessage());
    $message = "Terjadi kesalahan sistem: " . $e->getMessage();
    $message_type = "error";
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
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Manajemen Resep & HPP Produk</h1>
                    <p class="text-gray-600">Kelola resep produk dan hitung HPP (Harga Pokok Produksi) untuk analisis profitabilitas bisnis Anda.</p>
                </div>

                <!-- Pesan Notifikasi -->
                <?php if (!empty($message)): ?>
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

                <!-- Bagian Pilih Produk -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
                    <div class="flex items-center mb-6">
                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Pilih Produk</h3>
                            <p class="text-sm text-gray-600 mt-1">Pilih produk untuk mengelola resep dan menghitung HPP</p>
                        </div>
                    </div>

                    <div>
                        <label for="product_select" class="block text-sm font-semibold text-gray-700 mb-2">Pilih Produk untuk Dikelola Resepnya:</label>
                        <select id="product_select" onchange="if(this.value) window.location.href = 'resep_produk.php?product_id=' + this.value;" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo htmlspecialchars($product['id']); ?>" <?php echo $selectedProductId == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($selectedProductId && $selectedProduct): ?>
                    <!-- Bagian Kalkulasi HPP -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
                        <div class="flex items-center mb-6">
                            <div class="p-2 bg-green-100 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2-2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900">Kalkulasi HPP & Profit</h3>
                                <p class="text-sm text-gray-600 mt-1">Analisis biaya produksi dan tingkat keuntungan produk</p>
                            </div>
                        </div>

                        <!-- Form Update Produk Info -->
                        <form action="../process/simpan_resep_produk.php" method="POST" class="mb-6">
                            <input type="hidden" name="action" value="update_product_info">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($selectedProductId); ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                                <div>
                                    <label for="production_yield" class="block text-sm font-semibold text-gray-700 mb-2">Hasil Produksi (Unit)</label>
                                    <input type="number" step="1" id="production_yield" name="production_yield" value="<?php echo htmlspecialchars($selectedProduct['production_yield'] ?? 1); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" min="1" required>
                                    <p class="text-xs text-gray-500 mt-2 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 000 16zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        Berapa unit produk yang dihasilkan dari 1 batch resep
                                    </p>
                                </div>

                                <div>
                                    <label for="production_time_hours" class="block text-sm font-semibold text-gray-700 mb-2">Waktu Produksi (Jam)</label>
                                    <input type="number" step="0.1" id="production_time_hours" name="production_time_hours" value="<?php echo htmlspecialchars($selectedProduct['production_time_hours'] ?? 1); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" min="0.1" required>
                                    <p class="text-xs text-gray-500 mt-2 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        Estimasi waktu untuk memproduksi 1 batch (untuk hitung biaya tenaga kerja)
                                    </p>
                                </div>

                                <div>
                                    <label for="sale_price" class="block text-sm font-semibold text-gray-700 mb-2">Harga Jual per Unit</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <span class="text-gray-500 text-sm font-medium">Rp</span>
                                        </div>
                                        <input type="text" id="sale_price_display" class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" placeholder="Masukkan harga jual" oninput="formatRupiah(this, 'sale_price')" value="<?php echo $selectedProduct['sale_price'] ? number_format($selectedProduct['sale_price'], 0, ',', '.') : ''; ?>" required>
                                        <input type="hidden" id="sale_price" name="sale_price" value="<?php echo htmlspecialchars($selectedProduct['sale_price'] ?? 0); ?>" required>
                                    </div>
                                </div>

                                <div class="flex items-end">
                                    <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                        </svg>
                                        Update Info Produk
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Hasil Kalkulasi -->
                        <?php if ($hppCalculation): ?>
                            <!-- Summary Cards -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-xs font-bold text-blue-800 mb-1">Biaya Bahan Baku</h3>
                                            <p class="text-lg font-bold text-blue-900">Rp <?php echo number_format($hppCalculation['total_cost_bahan_baku'], 0, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-orange-100 rounded-lg mr-3">
                                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-xs font-bold text-orange-800 mb-1">Biaya Tenaga Kerja</h3>
                                            <p class="text-lg font-bold text-orange-900">Rp <?php echo number_format($hppCalculation['total_cost_tenaga_kerja'], 0, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-purple-100 rounded-lg mr-3">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-xs font-bold text-purple-800 mb-1">Biaya Overhead</h3>
                                            <p class="text-lg font-bold text-purple-900">Rp <?php echo number_format($hppCalculation['total_cost_overhead'], 0, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-xs font-bold text-indigo-800 mb-1">HPP per Unit</h3>
                                            <p class="text-lg font-bold text-indigo-900">Rp <?php echo number_format($hppCalculation['hpp_per_unit'], 0, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                                               <div class="bg-<?php echo $hppCalculation['profit_per_unit'] >= 0 ? 'green' : 'red'; ?>-50 border border-<?php echo $hppCalculation['profit_per_unit'] >= 0 ? 'green' : 'red'; ?>-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-<?php echo $hppCalculation['profit_per_unit'] >= 0 ? 'green' : 'red'; ?>-100 rounded-lg mr-3">
                                            <svg class="w-5 h-5 text-<?php echo $hppCalculation['profit_per_unit'] >= 0 ? 'green' : 'red'; ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $hppCalculation['profit_per_unit'] >= 0 ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'; ?>"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-xs font-bold text-<?php echo $hppCalculation['profit_per_unit'] >= 0 ? 'green' : 'red'; ?>-800 mb-1">Profit (<?php echo number_format($hppCalculation['profit_margin_percent'], 1); ?>%)</h3>
                                            <p class="text-lg font-bold text-<?php echo $hppCalculation['profit_per_unit'] >= 0 ? 'green' : 'red'; ?>-900">Rp <?php echo number_format($hppCalculation['profit_per_unit'], 0, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detail Breakdown dengan Tab -->
                            <div class="bg-white rounded-lg border border-gray-200">
                                <div class="flex border-b border-gray-200">
                                    <button class="px-6 py-4 text-sm font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50" onclick="showBreakdownTab('bahan_baku')" id="tab-bahan_baku">
                                        Breakdown Bahan Baku (<?php echo count($hppCalculation['recipe_details']); ?> item)
                                    </button>
                                    <button class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700" onclick="showBreakdownTab('tenaga_kerja')" id="tab-tenaga_kerja">
                                        Breakdown Tenaga Kerja (<?php echo count($hppCalculation['labor_details']); ?> posisi)
                                    </button>
                                    <button class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700" onclick="showBreakdownTab('overhead')" id="tab-overhead">
                                        Breakdown Overhead (<?php echo count($hppCalculation['overhead_details']); ?> item)
                                    </button>
                                </div>

                                <!-- Tab Content Bahan Baku -->
                                <div id="content-bahan_baku" class="p-6">
                                    <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                        Detail Biaya Bahan Baku:
                                    </h4>
                                    <div class="space-y-3">
                                        <?php foreach ($hppCalculation['recipe_details'] as $detail): ?>
                                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                <div class="flex-1">
                                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['name']); ?></span>
                                                    <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded"><?php echo ucfirst($detail['type']); ?></span>
                                                </div>
                                                <div class="flex-1 text-center">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo number_format($detail['quantity_used'], 0); ?> <?php echo htmlspecialchars($detail['unit_measurement']); ?>
                                                    </span>
                                                </div>
                                                <div class="flex-1 text-right">
                                                    <span class="font-semibold text-gray-900">Rp <?php echo number_format($detail['cost_per_item'], 0, ',', '.'); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Tab Content Tenaga Kerja -->
                                <div id="content-tenaga_kerja" class="p-6 hidden">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="font-bold text-gray-800 flex items-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            Detail Biaya Tenaga Kerja:
                                        </h4>
                                        <span class="text-sm text-gray-500">Hanya yang dipilih untuk produk ini</span>
                                    </div>
                                    <?php if (!empty($hppCalculation['labor_details'])): ?>
                                        <div class="space-y-3">
                                            <?php foreach ($hppCalculation['labor_details'] as $detail): ?>
                                                <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg border border-orange-200">
                                                    <div class="flex-1">
                                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['name']); ?></span>
                                                    </div>
                                                    <div class="flex-1 text-center">
                                                        <span class="text-sm text-gray-600">
                                                            Rp <?php echo number_format($detail['hourly_rate'], 0, ',', '.'); ?>/jam × <?php echo $detail['production_time']; ?> jam
                                                        </span>
                                                    </div>
                                                    <div class="flex-1 text-right flex items-center justify-end space-x-2">
                                                        <span class="font-semibold text-gray-900">Rp <?php echo number_format($detail['cost_per_item'], 0, ',', '.'); ?></span>
                                                        <button onclick="deleteManualLabor(<?php echo $detail['manual_id'] ?? 0; ?>)" class="text-red-600 hover:text-red-800" title="Hapus dari resep">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-8">
                                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            <p class="text-gray-500 font-medium">Belum ada tenaga kerja yang dipilih</p>
                                            <p class="text-gray-400 text-sm mt-1">Gunakan form "Input Manual Overhead & Tenaga Kerja" di bawah untuk menambahkan</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Tab Content Overhead -->
                                <div id="content-overhead" class="p-6 hidden">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="font-bold text-gray-800 flex items-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            Detail Biaya Overhead:
                                        </h4>
                                        <span class="text-sm text-gray-500">Hanya yang dipilih untuk produk ini</span>
                                    </div>
                                    <?php if (!empty($hppCalculation['overhead_details'])): ?>
                                        <div class="space-y-3">
                                            <?php foreach ($hppCalculation['overhead_details'] as $detail): ?>
                                                <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg border border-purple-200">
                                                    <div class="flex-1">
                                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['name']); ?></span>
                                                        <?php if ($detail['description']): ?>
                                                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($detail['description']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex-1 text-center">
                                                        <span class="text-sm text-gray-600">
                                                            <?php echo ucfirst(str_replace('_', ' ', $detail['allocation_method'])); ?>: 
                                                            <?php echo number_format($detail['amount'], 0, ',', '.'); ?>
                                                            <?php echo $detail['allocation_method'] == 'percentage' ? '%' : ($detail['allocation_method'] == 'per_hour' ? '/jam' : '/unit'); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex-1 text-right flex items-center justify-end space-x-2">
                                                        <span class="font-semibold text-gray-900">Rp <?php echo number_format($detail['cost_per_item'], 0, ',', '.'); ?></span>
                                                        <button onclick="deleteManualOverhead(<?php echo $detail['manual_id'] ?? 0; ?>)" class="text-red-600 hover:text-red-800" title="Hapus dari resep">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-8">
                                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            <p class="text-gray-500 font-medium">Belum ada overhead yang dipilih</p>
                                            <p class="text-gray-400 text-sm mt-1">Gunakan form "Input Manual Overhead & Tenaga Kerja" di bawah untuk menambahkan</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                                <svg class="w-12 h-12 text-yellow-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <p class="text-yellow-800 font-medium">Tambahkan item resep terlebih dahulu untuk melihat kalkulasi HPP</p>
                                <p class="text-yellow-600 text-sm mt-1">Gunakan form di bawah untuk menambahkan bahan baku atau kemasan ke resep</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Container Section untuk Input Items -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        <!-- Container 1: Bahan Baku & Kemasan (Gabungan dengan Mode Edit) -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                            <div class="flex items-center mb-6">
                                <div class="p-2 bg-blue-100 rounded-lg mr-3">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900" id="recipe-form-title">Bahan Baku & Kemasan</h3>
                                    <p class="text-sm text-gray-600 mt-1" id="recipe-form-desc">Tambahkan bahan baku atau kemasan yang digunakan dalam resep</p>
                                </div>
                            </div>

                            <!-- Form Universal untuk Bahan & Kemasan -->
                            <form action="../process/simpan_resep_produk.php" method="POST" id="recipe-main-form">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($selectedProductId); ?>">
                                <input type="hidden" name="action" value="add_bahan" id="recipe-action">
                                <input type="hidden" name="recipe_id" value="" id="recipe-edit-id">

                                <!-- Tab Navigation untuk Bahan/Kemasan -->
                                <div class="border-b border-gray-200 mb-6">
                                    <nav class="-mb-px flex space-x-8">
                                        <button type="button" onclick="switchRecipeTab('bahan')" id="tab-bahan" class="py-2 px-1 border-b-2 font-medium text-sm border-blue-600 text-blue-600">
                                            Bahan Baku
                                        </button>
                                        <button type="button" onclick="switchRecipeTab('kemasan')" id="tab-kemasan" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            Kemasan
                                        </button>
                                    </nav>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2" id="recipe-label">Pilih Bahan Baku</label>
                                        <select name="raw_material_id" id="recipe-select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                            <option value="">-- Pilih Bahan Baku --</option>
                                            <?php foreach ($rawMaterialsAndPackaging as $item): ?>
                                                <?php if ($item['type'] == 'bahan'): ?>
                                                    <?php 
                                                        $stokTerakhir = $item['stok_terakhir'];
                                                        $isDisabled = $stokTerakhir <= 0;
                                                        $stockLabel = '';
                                                        if ($stokTerakhir <= 0) {
                                                            $stockLabel = ' [STOK HABIS]';
                                                        } elseif ($stokTerakhir <= 5) {
                                                            $stockLabel = ' [STOK RENDAH: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        } else {
                                                            $stockLabel = ' [Stok: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        }
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($item['id']); ?>" data-type="bahan" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['name']); ?><?php echo $item['brand'] ? ' - ' . htmlspecialchars($item['brand']) : ''; ?><?php echo $stockLabel; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php foreach ($rawMaterialsAndPackaging as $item): ?>
                                                <?php if ($item['type'] == 'kemasan'): ?>
                                                    <?php 
                                                        $stokTerakhir = $item['stok_terakhir'];
                                                        $isDisabled = $stokTerakhir <= 0;
                                                        $stockLabel = '';
                                                        if ($stokTerakhir <= 0) {
                                                            $stockLabel = ' [STOK HABIS]';
                                                        } elseif ($stokTerakhir <= 5) {
                                                            $stockLabel = ' [STOK RENDAH: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        } else {
                                                            $stockLabel = ' [Stok: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        }
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($item['id']); ?>" data-type="kemasan" style="display:none;" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['name']); ?><?php echo $item['brand'] ? ' - ' . htmlspecialchars($item['brand']) : ''; ?><?php echo $stockLabel; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
                                            <input type="number" step="0.001" name="quantity_used" id="recipe-quantity" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="250" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                                            <select name="unit_measurement" id="recipe-unit" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                                <?php foreach ($recipeUnitOptions as $unit): ?>
                                                    <option value="<?php echo htmlspecialchars($unit); ?>"><?php echo htmlspecialchars($unit); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <button type="submit" id="recipe-submit-btn" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            <span id="recipe-submit-text">Tambah Bahan Baku</span>
                                        </button>
                                        <button type="button" onclick="resetRecipeForm()" id="recipe-cancel-btn" class="hidden bg-gray-500 text-white py-2 px-4 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Info Box -->
                            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex">
                                    <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <h4 class="text-sm font-medium text-blue-800">Catatan Penting</h4>
                                        <p class="text-xs text-blue-700 mt-1">Item yang ditambahkan di sini akan masuk ke breakdown kalkulasi HPP sebagai biaya bahan baku. Pastikan jumlah dan satuan sesuai dengan kebutuhan resep produksi.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Container 2: Overhead & Tenaga Kerja Manual -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                            <div class="flex items-center mb-6">
                                <div class="p-2 bg-purple-100 rounded-lg mr-3">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Input Manual Overhead & Tenaga Kerja</h3>
                                    <p class="text-sm text-gray-600 mt-1">Pilih overhead/tenaga kerja spesifik untuk resep ini</p>
                                </div>
                            </div>

                            <!-- Tab Navigation untuk Overhead/Tenaga Kerja -->
                            <div class="border-b border-gray-200 mb-6">
                                <nav class="-mb-px flex space-x-8">
                                    <button type="button" onclick="switchManualTab('overhead')" id="manual-tab-overhead" class="py-2 px-1 border-b-2 font-medium text-sm border-purple-600 text-purple-600">
                                        Overhead
                                    </button>
                                    <button type="button" onclick="switchManualTab('labor')" id="manual-tab-labor" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                        Tenaga Kerja
                                    </button>
                                </nav>
                            </div>

                            <!-- Form Manual Input -->
                            <form action="../process/simpan_resep_produk.php" method="POST" id="manual-cost-form">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($selectedProductId); ?>">
                                <input type="hidden" name="action" value="add_manual_overhead" id="manual-action">

                                <!-- Content Overhead -->
                            <div id="manual-content-overhead">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Overhead yang Akan Ditambahkan</label>
                                        <select name="overhead_id" id="manual-overhead-select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                                            <option value="">-- Pilih Overhead --</option>
                                            <?php
                                            try {
                                                $stmtOverhead = $conn->prepare("SELECT id, name, amount, allocation_method FROM overhead_costs WHERE is_active = 1 ORDER BY name ASC");
                                                $stmtOverhead->execute();
                                                $overheadList = $stmtOverhead->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($overheadList as $overhead): ?>
                                                    <option value="<?php echo htmlspecialchars($overhead['id']); ?>" 
                                                            data-amount="<?php echo htmlspecialchars($overhead['amount']); ?>"
                                                            data-method="<?php echo htmlspecialchars($overhead['allocation_method']); ?>">
                                                        <?php echo htmlspecialchars($overhead['name']); ?> - 
                                                        Rp <?php echo number_format($overhead['amount'], 0, ',', '.'); ?>
                                                        (<?php echo $overhead['allocation_method'] == 'percentage' ? '%' : ($overhead['allocation_method'] == 'per_hour' ? '/jam' : '/unit'); ?>)
                                                    </option>
                                                <?php endforeach;
                                            } catch (Exception $e) {
                                                echo '<option value="">Tidak ada data overhead</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                                <!-- Content Tenaga Kerja -->
                            <div id="manual-content-labor" class="hidden">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Posisi Tenaga Kerja</label>
                                        <select name="labor_id" id="manual-labor-select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                                            <option value="">-- Pilih Posisi --</option>
                                            <?php
                                            try {
                                                $stmtLabor = $conn->prepare("SELECT id, position_name, hourly_rate FROM labor_costs WHERE is_active = 1 ORDER BY position_name ASC");
                                                $stmtLabor->execute();
                                                $laborList = $stmtLabor->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($laborList as $labor): ?>
                                                    <option value="<?php echo htmlspecialchars($labor['id']); ?>"
                                                            data-rate="<?php echo htmlspecialchars($labor['hourly_rate']); ?>">                                                        <?php echo htmlspecialchars($labor['position_name']); ?> - 
                                                        Rp <?php echo number_format($labor['hourly_rate'], 0, ',', '.'); ?>/jam
                                                    </option>
                                                <?php endforeach;
                                            } catch (Exception $e) {
                                                echo '<option value="">Tidak ada data tenaga kerja</option>';
                                            }
                                            ?>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-2">Sistem akan menggunakan nilai default dari pengaturan overhead & tenaga kerja</p>
                                    </div>
                                </div>
                            </div>

                                <button type="submit" id="manual-submit-btn" class="w-full mt-6 bg-purple-600 text-white py-3 px-4 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 transition duration-200">
                                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    <span id="manual-submit-text">Tambah Overhead ke Resep</span>
                                </button>
                            </form>

                            <!-- Info Box -->
                            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex">
                                    <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <h4 class="text-sm font-medium text-blue-800">Catatan Penting</h4>
                                        <p class="text-xs text-blue-700 mt-1">Item yang ditambahkan di sini akan masuk ke breakdown kalkulasi HPP. Anda bisa memilih overhead/tenaga kerja mana saja yang spesifik untuk produk ini.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Edit Resep -->
                    <div id="edit-resep-form-section" class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8" style="display: none;">
                        <div class="flex items-center mb-6">
                            <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900" id="edit-form-title">Edit Item Resep</h3>
                                <p class="text-sm text-gray-600 mt-1">Edit komposisi bahan dalam resep produk</p>
                            </div>
                        </div>

                        <form action="../process/simpan_resep_produk.php" method="POST">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($selectedProductId); ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="recipe_id" id="edit_recipe_id">

                            <!-- Tab Navigation untuk Edit -->
                            <div class="border-b border-gray-200 mb-6">
                                <nav class="-mb-px flex space-x-8">
                                    <button type="button"` onclick="showEditCategoryTab('bahan')" id="edit-tab-bahan" class="py-2 px-1 border-b-2 font-medium text-sm border-blue-600 text-blue-600">
                                        Bahan Baku
                                    </button>
                                    <button type="button" onclick="showEditCategoryTab('kemasan')" id="edit-tab-kemasan" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                        Kemasan
                                    </button>
                                </nav>
                            </div>

                            <!-- Tab Content untuk Edit Bahan -->
                            <div id="edit-content-bahan">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Bahan Baku</label>
                                        <select name="raw_material_id" id="edit_raw_material_id_bahan" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                            <option value="">-- Pilih Bahan Baku --</option>
                                            <?php foreach ($rawMaterialsAndPackaging as $item): ?>
                                                <?php if ($item['type'] == 'bahan'): ?>
                                                    <?php 
                                                        $stokTerakhir = $item['stok_terakhir'];
                                                        $isDisabled = $stokTerakhir <= 0;
                                                        $stockLabel = '';
                                                        if ($stokTerakhir <= 0) {
                                                            $stockLabel = ' [STOK HABIS]';
                                                        } elseif ($stokTerakhir <= 5) {
                                                            $stockLabel = ' [STOK RENDAH: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        } else {
                                                            $stockLabel = ' [Stok: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        }
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($item['id']); ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['name']); ?><?php echo $item['brand'] ? ' - ' . htmlspecialchars($item['brand']) : ''; ?><?php echo $stockLabel; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
                                            <input type="number" step="0.001" name="quantity_used" id="edit_quantity_used" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="250" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                                            <select name="unit_measurement" id="edit_unit_measurement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                                <?php foreach ($recipeUnitOptions as $unit): ?>
                                                    <option value="<?php echo htmlspecialchars($unit); ?>"><?php echo htmlspecialchars($unit); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab Content untuk Edit Kemasan -->
                            <div id="edit-content-kemasan" class="hidden">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Kemasan</label>
                                        <select name="raw_material_id" id="edit_raw_material_id_kemasan" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" required disabled>
                                            <option value="">-- Pilih Kemasan --</option>
                                            <?php foreach ($rawMaterialsAndPackaging as $item): ?>
                                                <?php if ($item['type'] == 'kemasan'): ?>
                                                    <?php 
                                                        $stokTerakhir = $item['stok_terakhir'];
                                                        $isDisabled = $stokTerakhir <= 0;
                                                        $stockLabel = '';
                                                        if ($stokTerakhir <= 0) {
                                                            $stockLabel = ' [STOK HABIS]';
                                                        } elseif ($stokTerakhir <= 5) {
                                                            $stockLabel = ' [STOK RENDAH: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        } else {
                                                            $stockLabel = ' [Stok: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                                        }
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($item['id']); ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['name']); ?><?php echo $item['brand'] ? ' - ' . htmlspecialchars($item['brand']) : ''; ?><?php echo $stockLabel; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
                                            <input type="number" step="0.001" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="1" disabled>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                                            <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" disabled>
                                                <?php foreach ($recipeUnitOptions as $unit): ?>
                                                    <option value="<?php echo htmlspecialchars($unit); ?>"><?php echo htmlspecialchars($unit); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-8">
                                <button type="submit" id="edit_submit_button" class="flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Update Item Resep
                                </button>
                                <button type="button" onclick="hideEditForm()" class="flex items-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 ml-4">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Batal Edit
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Bagian Daftar Resep Produk -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <div class="p-2 bg-purple-100 rounded-lg mr-3">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Daftar Resep: <?php echo htmlspecialchars($selectedProduct['name']); ?></h3>
                                        <p class="text-sm text-gray-600">Kelola dan pantau semua item dalam resep produk</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div class="text-right">
                                        <span class="text-sm text-gray-500">Total:</span>
                                        <span class="text-lg font-bold text-purple-600 ml-1"><?php echo number_format($totalRecipesRows); ?> item</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter dan Search -->
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 gap-4">
                                <div class="flex flex-col md:flex-row items-start md:items-center space-y-2 md:space-y-0 md:space-x-3 w-full md:w-auto">
                                    <div class="relative w-full md:w-auto">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                        </div>
                                        <input type="text" id="search_recipe" placeholder="Cari bahan dalam resep..." value="<?php echo htmlspecialchars($searchQueryRecipe); ?>" class="pl-10 w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                    </div>
                                    <select id="recipe_limit" class="w-full md:w-auto px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                                        <?php foreach ($recipesLimitOptions as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $recipesLimit == $option ? 'selected' : ''; ?>><?php echo $option; ?> per halaman</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                                <?php if (!empty($productRecipes)): ?>
                                    <?php foreach ($productRecipes as $item): ?>
                                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow bg-white">
                                            <div class="flex justify-between items-start mb-3">
                                                <div class="flex-1">
                                                    <h4 class="font-bold text-gray-800 text-sm md:text-base"><?php echo htmlspecialchars($item['raw_material_name']); ?></h4>
                                                    <?php if ($item['raw_material_brand']): ?>
                                                        <p class="text-xs text-gray-500 mt-1">Merek: <?php echo htmlspecialchars($item['raw_material_brand']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="<?php echo $item['raw_material_type'] === 'bahan' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?> text-xs px-2 py-1 rounded flex-shrink-0">
                                                    <?php echo htmlspecialchars(ucfirst($item['raw_material_type'])); ?>
                                                </span>
                                            </div>
                                            <div class="text-sm text-gray-600 space-y-2 mb-4">
                                                <div class="flex justify-between">
                                                    <span class="font-medium">Komposisi:</span>
                                                    <span><?php echo number_format($item['quantity_used'], 0); ?> <?php echo htmlspecialchars($item['unit_measurement']); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="font-medium">Harga Beli:</span>
                                                    <span>Rp <?php echo number_format($item['purchase_price_per_unit'], 0, ',', '.'); ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="font-medium">Satuan Stok:</span>
                                                    <span><?php echo htmlspecialchars($item['raw_material_stock_unit']); ?></span>
                                                </div>
                                                <?php if ($item['default_package_quantity'] !== null): ?>
                                                    <div class="flex justify-between">
                                                        <span class="font-medium">Volume Paket:</span>
                                                        <span><?php echo number_format($item['default_package_quantity'], 0); ?> <?php echo htmlspecialchars($item['raw_material_stock_unit']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <button onclick="editRecipeItem(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="inline-flex items-center px-3 py-1 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    Edit
                                                </button>
                                                <a href="../process/simpan_resep_produk.php?action=delete&id=<?php echo $item['id']; ?>&product_id=<?php echo htmlspecialchars($selectedProductId); ?>" class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200" onclick="return confirm('Hapus item ini dari resep?');">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Hapus
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-span-full text-center py-12">
                                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <p class="text-gray-500 text-lg font-medium">Belum ada item dalam resep produk ini</p>
                                        <p class="text-gray-400 text-sm mt-1">Mulai tambahkan bahan baku atau kemasan menggunakan form di atas</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Pagination Resep -->
                            <?php if ($totalRecipesPages > 1): ?>
                            <div class="border-t border-gray-200 pt-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 flex justify-between sm:hidden">
                                        <?php if ($recipesPage > 1): ?>
                                            <a href="<?php echo 'resep_produk.php?' . http_build_query(array_merge($_GET, ['recipe_page' => $recipesPage - 1])); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                                        <?php endif; ?>
                                        <?php if ($recipesPage < $totalRecipesPages): ?>
                                            <a href="<?php echo 'resep_produk.php?' . http_build_query(array_merge($_GET, ['recipe_page' => $recipesPage + 1])); ?>" 
                                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm text-gray-700">
                                                Menampilkan <span class="font-medium"><?php echo number_format($recipesOffset + 1); ?></span> sampai 
                                                <span class="font-medium"><?php echo number_format(min($recipesOffset + $recipesLimit, $totalRecipesRows)); ?></span> dari 
                                                <span class="font-medium"><?php echo number_format($totalRecipesRows); ?></span> item
                                            </p>
                                        </div>
                                        <div>
                                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                                <?php if ($recipesPage > 1): ?>
                                                    <a href="<?php echo 'resep_produk.php?' . http_build_query(array_merge($_GET, ['recipe_page' => $recipesPage - 1])); ?>" 
                                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                        <span class="sr-only">Previous</span>
                                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </a>
                                                <?php endif; ?>

                                                <?php 
                                                $startPage = max(1, $recipesPage - 2);
                                                $endPage = min($totalRecipesPages, $recipesPage + 2);
                                                for ($i = $startPage; $i <= $endPage; $i++): 
                                                ?>
                                                    <a href="<?php echo 'resep_produk.php?' . http_build_query(array_merge($_GET, ['recipe_page' => $i])); ?>" 
                                                       class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo ($i == $recipesPage) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                <?php endfor; ?>

                                                <?php if ($recipesPage < $totalRecipesPages): ?>
                                                    <a href="<?php echo 'resep_produk.php?' . http_build_query(array_merge($_GET, ['recipe_page' => $recipesPage + 1])); ?>" 
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
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-12 text-center">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Pilih Produk untuk Memulai</h3>
                        <p class="text-gray-600">Silakan pilih produk dari daftar di atas untuk mulai mengelola resep dan menghitung HPP</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal untuk Edit Resep -->
<div id="editResepModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Item Resep</h3>
            <form id="editResepForm" action="../process/simpan_resep_produk.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="recipe_id" id="edit_recipe_id">
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($selectedProductId); ?>">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bahan/Kemasan</label>
                        <select name="raw_material_id" id="edit_raw_material_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">-- Pilih Bahan/Kemasan --</option>
                            <?php foreach ($rawMaterialsAndPackaging as $item): ?>
                                <?php 
                                    $stokTerakhir = $item['stok_terakhir'];
                                    $isDisabled = $stokTerakhir <= 0;
                                    $stockLabel = '';
                                    if ($stokTerakhir <= 0) {
                                        $stockLabel = ' [STOK HABIS]';
                                    } elseif ($stokTerakhir <= 5) {
                                        $stockLabel = ' [STOK RENDAH: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                    } else {
                                        $stockLabel = ' [Stok: ' . number_format($stokTerakhir) . ' ' . $item['unit'] . ']';
                                    }
                                ?>
                                <option value="<?php echo htmlspecialchars($item['id']); ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($item['name']); ?><?php echo $item['brand'] ? ' - ' . htmlspecialchars($item['brand']) : ''; ?>
                                    (<?php echo ucfirst($item['type']); ?>)<?php echo $stockLabel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
                            <input type="number" step="0.001" name="quantity_used" id="edit_quantity_used" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                            <select name="unit_measurement" id="edit_unit_measurement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <?php foreach ($recipeUnitOptions as $unit): ?>
                                    <option value="<?php echo htmlspecialchars($unit); ?>"><?php echo htmlspecialchars($unit); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6 space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/cornerbites-sia/assets/js/resep_produk.js"></script>
<script>
function showBreakdownTab(tabName) {
    // Hide all tab contents
    document.getElementById('content-bahan_baku').classList.add('hidden');
    document.getElementById('content-tenaga_kerja').classList.add('hidden');
    document.getElementById('content-overhead').classList.add('hidden');

    // Deactivate all tabs
    document.getElementById('tab-bahan_baku').classList.remove('bg-blue-50', 'border-blue-600', 'text-blue-600');
    document.getElementById('tab-bahan_baku').classList.add('text-gray-500', 'hover:text-gray-700');
    document.getElementById('tab-tenaga_kerja').classList.remove('bg-blue-50', 'border-blue-600', 'text-blue-600');
    document.getElementById('tab-tenaga_kerja').classList.add('text-gray-500', 'hover:text-gray-700');
    document.getElementById('tab-overhead').classList.remove('bg-blue-50', 'border-blue-600', 'text-blue-600');
    document.getElementById('tab-overhead').classList.add('text-gray-500', 'hover:text-gray-700');

    // Show the selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');

    // Activate the selected tab
    document.getElementById('tab-' + tabName).classList.add('bg-blue-50', 'border-blue-600', 'text-blue-600');
    document.getElementById('tab-' + tabName).classList.remove('text-gray-500', 'hover:text-gray-700');
}

function showQuickActionTab(tabName) {
    // Hide all quick action tab contents
    const contents = ['bahan', 'kemasan', 'overhead', 'tenaga_kerja'];
    contents.forEach(content => {
        const element = document.getElementById('quick-content-' + content);
        if (element) {
            element.classList.add('hidden');
        }
    });

    // Show the selected tab content
    const selectedContent = document.getElementById('quick-content-' + tabName);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
    }

    // Update tab buttons styling
    contents.forEach(content => {
        const tabButton = document.getElementById('quick-tab-' + content);
        if (tabButton) {
            tabButton.classList.remove('border-blue-600', 'text-blue-600', 'border-green-600', 'text-green-600', 'border-purple-600', 'text-purple-600', 'border-orange-600', 'text-orange-600');
            tabButton.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        }
    });

    // Activate the selected tab
    const activeTab = document.getElementById('quick-tab-' + tabName);
    if (activeTab) {
        activeTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');

        // Set appropriate color based on tab
        if (tabName === 'bahan') {
            activeTab.classList.add('border-blue-600', 'text-blue-600');
        } else if (tabName === 'kemasan') {
            activeTab.classList.add('border-green-600', 'text-green-600');
        } else if (tabName === 'overhead') {
            activeTab.classList.add('border-purple-600', 'text-purple-600');
        } else if (tabName === 'tenaga_kerja') {
            activeTab.classList.add('border-orange-600', 'text-orange-600');
        }
    }
}

function editResepItem(item) {
    document.getElementById('edit_recipe_id').value = item.id;
    document.getElementById('edit_raw_material_id').value = item.raw_material_id;
    document.getElementById('edit_quantity_used').value = item.quantity_used;
    document.getElementById('edit_unit_measurement').value = item.unit_measurement;

    document.getElementById('editResepModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editResepModal').classList.add('hidden');
}

// Search functionality
document.getElementById('search_recipe').addEventListener('input', function() {
    const searchValue = this.value;
    const currentParams = new URLSearchParams(window.location.search);

    if (searchValue) {
        currentParams.set('search_recipe', searchValue);
    } else {
        currentParams.delete('search_recipe');
    }

    currentParams.delete('recipe_page'); // Reset to first page

    const newUrl = window.location.pathname + '?' + currentParams.toString();
    setTimeout(() => {
        window.location.href = newUrl;
    }, 500);
});

// Limit functionality
document.getElementById('recipe_limit').addEventListener('change', function() {
    const limitValue = this.value;
    const currentParams = new URLSearchParams(window.location.search);

    currentParams.set('recipe_limit', limitValue);
    currentParams.delete('recipe_page'); // Reset to first page

    const newUrl = window.location.pathname + '?' + currentParams.toString();
    window.location.href = newUrl;
});

// Format rupiah function
function formatRupiah(input, hiddenInput) {
    let value = input.value.replace(/\D/g, '');
    let formattedValue = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    input.value = formattedValue;
    document.getElementById(hiddenInput).value = value;
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>