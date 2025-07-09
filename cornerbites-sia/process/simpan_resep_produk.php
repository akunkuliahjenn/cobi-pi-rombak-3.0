<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Function untuk menghitung HPP berdasarkan resep
function calculateHPPForProduct($conn, $product_id, $production_yield = 1, $production_time_hours = 1) {
    $totalCostPerBatch = 0;

    try {
        // 1. BIAYA BAHAN BAKU - Ambil semua item resep
        $stmtRecipes = $conn->prepare("
            SELECT pr.quantity_used, pr.unit_measurement,
                   COALESCE(rm.purchase_price_per_unit, 0) as purchase_price_per_unit, 
                   COALESCE(rm.default_package_quantity, 1) as default_package_quantity
            FROM product_recipes pr
            JOIN raw_materials rm ON pr.raw_material_id = rm.id
            WHERE pr.product_id = ?
        ");
        $stmtRecipes->execute([$product_id]);
        $allRecipeItems = $stmtRecipes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allRecipeItems as $item) {
            if ($item['default_package_quantity'] && $item['default_package_quantity'] > 0) {
                $costPerUnit = $item['purchase_price_per_unit'] / $item['default_package_quantity'];
                $costPerItem = $costPerUnit * $item['quantity_used'];
            } else {
                $costPerItem = $item['purchase_price_per_unit'] * $item['quantity_used'];
            }
            $totalCostPerBatch += $costPerItem;
        }

        // 2. BIAYA TENAGA KERJA MANUAL
        $stmtManualLabor = $conn->prepare("
            SELECT plm.total_cost
            FROM product_labor_manual plm
            JOIN labor_costs lc ON plm.labor_id = lc.id
            WHERE plm.product_id = ? AND lc.is_active = 1
        ");
        $stmtManualLabor->execute([$product_id]);
        $manualLaborCosts = $stmtManualLabor->fetchAll(PDO::FETCH_ASSOC);

        $laborCostPerBatch = 0;
        foreach ($manualLaborCosts as $labor) {
            $laborCostPerBatch += $labor['total_cost'];
        }

        // 3. BIAYA OVERHEAD MANUAL
        $stmtManualOverhead = $conn->prepare("
            SELECT pom.final_amount
            FROM product_overhead_manual pom
            JOIN overhead_costs oc ON pom.overhead_id = oc.id
            WHERE pom.product_id = ? AND oc.is_active = 1
        ");
        $stmtManualOverhead->execute([$product_id]);
        $manualOverheadCosts = $stmtManualOverhead->fetchAll(PDO::FETCH_ASSOC);

        $overheadCostPerBatch = 0;
        foreach ($manualOverheadCosts as $overhead) {
            $overheadCostPerBatch += $overhead['final_amount'];
        }

        // 4. TOTAL HPP PER BATCH DAN PER UNIT
        $totalCostPerBatch = $totalCostPerBatch + $laborCostPerBatch + $overheadCostPerBatch;

        // Hitung HPP per unit berdasarkan production yield
        $hppPerUnit = $production_yield > 0 ? $totalCostPerBatch / $production_yield : 0;

        return $hppPerUnit;

    } catch (Exception $e) {
        error_log("Error calculating HPP: " . $e->getMessage());
        return 0;
    }
}

try {
    $conn = $db;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;
        $product_id = $_POST['product_id'] ?? null;

        if (!$product_id) {
            throw new Exception('Product ID tidak ditemukan');
        }

        switch ($action) {
            case 'add_bahan':
            case 'add_kemasan':
                $raw_material_id = $_POST['raw_material_id'] ?? null;
                $quantity_used = $_POST['quantity_used'] ?? null;
                $unit_measurement = $_POST['unit_measurement'] ?? null;

                if (!$raw_material_id || !$quantity_used || !$unit_measurement) {
                    throw new Exception('Data tidak lengkap untuk menambah item');
                }

                // Check if this combination already exists
                $checkStmt = $conn->prepare("SELECT id FROM product_recipes WHERE product_id = ? AND raw_material_id = ?");
                $checkStmt->execute([$product_id, $raw_material_id]);
                if ($checkStmt->fetchColumn()) {
                    throw new Exception('Bahan/kemasan ini sudah ada dalam resep. Silakan edit yang sudah ada.');
                }

                $stmt = $conn->prepare("INSERT INTO product_recipes (product_id, raw_material_id, quantity_used, unit_measurement) VALUES (?, ?, ?, ?)");
                $stmt->execute([$product_id, $raw_material_id, $quantity_used, $unit_measurement]);

                // Auto-update HPP setelah menambah item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }

                $_SESSION['resep_message'] = [
                    'text' => 'Item berhasil ditambahkan ke resep. HPP otomatis diupdate.',
                    'type' => 'success'
                ];
                break;

            case 'edit':
                $recipe_id = $_POST['recipe_id'] ?? null;
                $raw_material_id = $_POST['raw_material_id'] ?? null;
                $quantity_used = $_POST['quantity_used'] ?? null;
                $unit_measurement = $_POST['unit_measurement'] ?? null;

                if (!$recipe_id || !$raw_material_id || !$quantity_used || !$unit_measurement) {
                    throw new Exception('Data tidak lengkap untuk update. Recipe ID: ' . $recipe_id . ', Material ID: ' . $raw_material_id);
                }

                // Get current recipe data to check if material is being changed
                $currentStmt = $conn->prepare("SELECT raw_material_id, quantity_used FROM product_recipes WHERE id = ? AND product_id = ?");
                $currentStmt->execute([$recipe_id, $product_id]);
                $currentRecipe = $currentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$currentRecipe) {
                    throw new Exception('Data resep tidak ditemukan');
                }

                // If raw material is being changed, check for duplicates
                if ($currentRecipe['raw_material_id'] != $raw_material_id) {
                    $checkStmt = $conn->prepare("SELECT id FROM product_recipes WHERE product_id = ? AND raw_material_id = ? AND id != ?");
                    $checkStmt->execute([$product_id, $raw_material_id, $recipe_id]);
                    if ($checkStmt->fetchColumn()) {
                        throw new Exception('Bahan/kemasan ini sudah ada dalam resep. Pilih bahan/kemasan yang berbeda.');
                    }
                }

                $stmt = $conn->prepare("UPDATE product_recipes SET raw_material_id = ?, quantity_used = ?, unit_measurement = ? WHERE id = ? AND product_id = ?");
                $stmt->execute([$raw_material_id, $quantity_used, $unit_measurement, $recipe_id, $product_id]);

                // Auto-update HPP setelah edit item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }

                $_SESSION['resep_message'] = [
                    'text' => 'Item resep berhasil diupdate. HPP otomatis diupdate.',
                    'type' => 'success'
                ];
                break;

            case 'add_manual_overhead':
                // Debug logging
                error_log("Processing add_manual_overhead for product_id: " . $product_id);
                error_log("POST data: " . print_r($_POST, true));

                $overhead_id = $_POST['overhead_id'] ?? null;

                if (!$overhead_id) {
                    error_log("Error: overhead_id is missing from POST data");
                    throw new Exception('ID overhead tidak ditemukan. Data yang diterima: ' . print_r($_POST, true));
                }

                error_log("Overhead ID received: " . $overhead_id);

                // Check if already exists
                $checkStmt = $conn->prepare("SELECT id FROM product_overhead_manual WHERE product_id = ? AND overhead_id = ?");
                $checkStmt->execute([$product_id, $overhead_id]);
                if ($checkStmt->fetchColumn()) {
                    throw new Exception('Overhead ini sudah ditambahkan ke resep produk ini');
                }

                // Get overhead details
                $overheadStmt = $conn->prepare("SELECT name, amount, allocation_method FROM overhead_costs WHERE id = ? AND is_active = 1");
                $overheadStmt->execute([$overhead_id]);
                $overhead = $overheadStmt->fetch(PDO::FETCH_ASSOC);

                if (!$overhead) {
                    throw new Exception('Data overhead tidak ditemukan atau tidak aktif');
                }

                // Get product details for calculation
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                $productionYield = $product['production_yield'] ?? 1;
                $productionTimeHours = $product['production_time_hours'] ?? 1;

                // Calculate final amount based on allocation method
                $finalAmount = 0;
                $allocationMethod = $overhead['allocation_method'] ?? 'fixed';
                
                switch ($allocationMethod) {
                    case 'percentage':
                        // For percentage, calculate based on material costs later
                        // For now, store as is and calculate in display
                        $finalAmount = $overhead['amount'];
                        break;
                    case 'per_unit':
                        $finalAmount = $overhead['amount'] * $productionYield;
                        break;
                    case 'per_hour':
                        $finalAmount = $overhead['amount'] * $productionTimeHours;
                        break;
                    case 'fixed':
                    default:
                        $finalAmount = $overhead['amount'];
                        break;
                }

                // Insert into database
                $stmt = $conn->prepare("INSERT INTO product_overhead_manual (product_id, overhead_id, custom_amount, final_amount) VALUES (?, ?, ?, ?)");
                $success = $stmt->execute([$product_id, $overhead_id, $overhead['amount'], $finalAmount]);

                if (!$success) {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Database error inserting overhead: " . print_r($errorInfo, true));
                    throw new Exception('Gagal menyimpan data overhead ke database: ' . $errorInfo[2]);
                }

                error_log("Successfully inserted overhead for product " . $product_id);

                $_SESSION['resep_message'] = [
                    'text' => 'Overhead "' . $overhead['name'] . '" berhasil ditambahkan ke resep',
                    'type' => 'success'
                ];
                break;

            case 'add_manual_labor':
                $labor_id = $_POST['labor_id'] ?? null;

                if (!$labor_id) {
                    throw new Exception('ID tenaga kerja tidak ditemukan');
                }

                // Check if already exists
                $checkStmt = $conn->prepare("SELECT id FROM product_labor_manual WHERE product_id = ? AND labor_id = ?");
                $checkStmt->execute([$product_id, $labor_id]);
                if ($checkStmt->fetchColumn()) {
                    throw new Exception('Tenaga kerja ini sudah ditambahkan ke resep produk ini');
                }

                // Get labor details
                $laborStmt = $conn->prepare("SELECT hourly_rate FROM labor_costs WHERE id = ? AND is_active = 1");
                $laborStmt->execute([$labor_id]);
                $labor = $laborStmt->fetch(PDO::FETCH_ASSOC);

                if (!$labor) {
                    throw new Exception('Data tenaga kerja tidak ditemukan atau tidak aktif');
                }

                // Get product details for calculation
                $productStmt = $conn->prepare("SELECT production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                $productionTimeHours = $product['production_time_hours'] ?? 1;
                $totalCost = $labor['hourly_rate'] * $productionTimeHours;

                $stmt = $conn->prepare("INSERT INTO product_labor_manual (product_id, labor_id, custom_hourly_rate, custom_hours, total_cost) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $labor_id, $labor['hourly_rate'], $productionTimeHours, $totalCost]);

                $_SESSION['resep_message'] = [
                    'text' => 'Tenaga kerja berhasil ditambahkan ke resep',
                    'type' => 'success'
                ];
                break;

            case 'update_product_info':
                $production_yield = $_POST['production_yield'] ?? 1;
                $production_time_hours = $_POST['production_time_hours'] ?? 1;
                $sale_price = $_POST['sale_price'] ?? 0;

                // Hitung HPP terbaru setelah update info produk
                $hppPerUnit = calculateHPPForProduct($conn, $product_id, $production_yield, $production_time_hours);

                // Update product dengan cost_price yang baru dihitung
                $stmt = $conn->prepare("UPDATE products SET production_yield = ?, production_time_hours = ?, sale_price = ?, cost_price = ? WHERE id = ?");
                $stmt->execute([$production_yield, $production_time_hours, $sale_price, $hppPerUnit, $product_id]);

                $_SESSION['resep_message'] = [
                    'text' => 'Informasi produk berhasil diupdate. HPP otomatis dihitung: Rp ' . number_format($hppPerUnit, 0, ',', '.'),
                    'type' => 'success'
                ];
                break;

            case 'delete_manual_overhead':
                $overhead_manual_id = $_POST['overhead_manual_id'] ?? null;

                if (!$overhead_manual_id) {
                    throw new Exception('ID overhead manual tidak ditemukan');
                }

                $stmt = $conn->prepare("DELETE FROM product_overhead_manual WHERE id = ? AND product_id = ?");
                $stmt->execute([$overhead_manual_id, $product_id]);

                $_SESSION['resep_message'] = [
                    'text' => 'Overhead berhasil dihapus dari resep',
                    'type' => 'success'
                ];
                break;

            case 'delete_manual_labor':
                $labor_manual_id = $_POST['labor_manual_id'] ?? null;

                if (!$labor_manual_id) {
                    throw new Exception('ID tenaga kerja manual tidak ditemukan');
                }

                $stmt = $conn->prepare("DELETE FROM product_labor_manual WHERE id = ? AND product_id = ?");
                $stmt->execute([$labor_manual_id, $product_id]);

                $_SESSION['resep_message'] = [
                    'text' => 'Tenaga kerja berhasil dihapus dari resep',
                    'type' => 'success'
                ];
                break;

            default:
                throw new Exception('Action tidak valid');
        }

        // Redirect back to resep page
        header("Location: ../pages/resep_produk.php?product_id=" . $product_id);
        exit;

    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? null;
        $id = $_GET['id'] ?? null;
        $product_id = $_GET['product_id'] ?? null;

        if ($action === 'delete' && $id && $product_id) {
            $stmt = $conn->prepare("DELETE FROM product_recipes WHERE id = ? AND product_id = ?");
            $stmt->execute([$id, $product_id]);

            // Auto-update HPP setelah hapus item
            $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
            $productStmt->execute([$product_id]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                $updateStmt->execute([$hppPerUnit, $product_id]);
            }

            $_SESSION['resep_message'] = [
                'text' => 'Item berhasil dihapus dari resep. HPP otomatis diupdate.',
                'type' => 'success'
            ];

            header("Location: ../pages/resep_produk.php?product_id=" . $product_id);
            exit;
        }
    }

} catch (PDOException $e) {
    error_log("Database error in simpan_resep_produk.php: " . $e->getMessage());
    $_SESSION['resep_message'] = [
        'text' => 'Terjadi kesalahan database: ' . $e->getMessage(),
        'type' => 'error'
    ];
    
    if (isset($product_id)) {
        header("Location: ../pages/resep_produk.php?product_id=" . $product_id);
    } else {
        header("Location: ../pages/resep_produk.php");
    }
    exit;

} catch (Exception $e) {
    error_log("General error in simpan_resep_produk.php: " . $e->getMessage());
    $_SESSION['resep_message'] = [
        'text' => $e->getMessage(),
        'type' => 'error'
    ];
    
    if (isset($product_id)) {
        header("Location: ../pages/resep_produk.php?product_id=" . $product_id);
    } else {
        header("Location: ../pages/resep_produk.php");
    }
    exit;
}
?>