<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

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
                    throw new Exception('Data tidak lengkap');
                }

                // Check if combination already exists
                $checkStmt = $conn->prepare("SELECT id FROM product_recipes WHERE product_id = ? AND raw_material_id = ?");
                $checkStmt->execute([$product_id, $raw_material_id]);
                
                if ($checkStmt->fetch()) {
                    throw new Exception('Bahan ini sudah ada dalam resep. Silakan edit yang sudah ada.');
                }

                // Check stock availability - hitung stok terakhir yang sesungguhnya
                $stockStmt = $conn->prepare("
                    SELECT rm.name, rm.current_stock, rm.type, 
                           COALESCE(SUM(pr.quantity_used), 0) as total_used_all_products
                    FROM raw_materials rm
                    LEFT JOIN product_recipes pr ON rm.id = pr.raw_material_id
                    WHERE rm.id = ?
                    GROUP BY rm.id
                ");
                $stockStmt->execute([$raw_material_id]);
                $material = $stockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$material) {
                    throw new Exception('Bahan/kemasan tidak ditemukan');
                }

                // Hitung stok terakhir (stok fisik - total digunakan di semua resep)
                $stokTerakhir = $material['current_stock'] - $material['total_used_all_products'];

                if ($stokTerakhir <= 0) {
                    $materialType = $material['type'] === 'bahan' ? 'bahan baku' : 'kemasan';
                    throw new Exception('Stok terakhir ' . $materialType . ' "' . $material['name'] . '" sudah habis (Stok fisik: ' . number_format($material['current_stock']) . ', Digunakan: ' . number_format($material['total_used_all_products']) . '). Silakan tambah stok terlebih dahulu di halaman Bahan Baku & Kemasan.');
                }

                if ($stokTerakhir < $quantity_used) {
                    $materialType = $material['type'] === 'bahan' ? 'bahan baku' : 'kemasan';
                    throw new Exception('Stok terakhir ' . $materialType . ' "' . $material['name'] . '" tidak mencukupi. Stok terakhir tersedia: ' . number_format($stokTerakhir) . ', dibutuhkan: ' . number_format($quantity_used) . '. Silakan kurangi jumlah atau tambah stok terlebih dahulu.');
                }

                $stmt = $conn->prepare("INSERT INTO product_recipes (product_id, raw_material_id, quantity_used, unit_measurement) VALUES (?, ?, ?, ?)");
                $stmt->execute([$product_id, $raw_material_id, $quantity_used, $unit_measurement]);

                $_SESSION['resep_message'] = [
                    'text' => ($action === 'add_bahan' ? 'Bahan baku' : 'Kemasan') . ' berhasil ditambahkan ke resep',
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

                // Only check for duplicate if the material is being changed
                if ($currentRecipe['raw_material_id'] != $raw_material_id) {
                    $checkStmt = $conn->prepare("SELECT id FROM product_recipes WHERE product_id = ? AND raw_material_id = ? AND id != ?");
                    $checkStmt->execute([$product_id, $raw_material_id, $recipe_id]);

                    if ($checkStmt->fetch()) {
                        throw new Exception('Bahan ini sudah ada dalam resep. Silakan pilih bahan yang berbeda.');
                    }
                }

                // Check stock availability for edit - hitung stok terakhir yang sesungguhnya
                $stockStmt = $conn->prepare("
                    SELECT rm.name, rm.current_stock, rm.type, 
                           COALESCE(SUM(pr.quantity_used), 0) as total_used_all_products
                    FROM raw_materials rm
                    LEFT JOIN product_recipes pr ON rm.id = pr.raw_material_id
                    WHERE rm.id = ?
                    GROUP BY rm.id
                ");
                $stockStmt->execute([$raw_material_id]);
                $material = $stockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$material) {
                    throw new Exception('Bahan/kemasan tidak ditemukan');
                }

                // Hitung stok terakhir dan adjust jika material yang sama sedang diedit
                $stokTerakhir = $material['current_stock'] - $material['total_used_all_products'];
                
                // Jika material yang sama, tambahkan kembali quantity yang sedang digunakan di resep ini
                if ($currentRecipe['raw_material_id'] == $raw_material_id) {
                    $stokTerakhir += $currentRecipe['quantity_used'];
                }

                if ($stokTerakhir <= 0) {
                    $materialType = $material['type'] === 'bahan' ? 'bahan baku' : 'kemasan';
                    throw new Exception('Stok terakhir ' . $materialType . ' "' . $material['name'] . '" sudah habis (Stok fisik: ' . number_format($material['current_stock']) . ', Digunakan: ' . number_format($material['total_used_all_products']) . '). Silakan tambah stok terlebih dahulu di halaman Bahan Baku & Kemasan.');
                }

                if ($stokTerakhir < $quantity_used) {
                    $materialType = $material['type'] === 'bahan' ? 'bahan baku' : 'kemasan';
                    throw new Exception('Stok terakhir ' . $materialType . ' "' . $material['name'] . '" tidak mencukupi. Stok terakhir tersedia: ' . number_format($stokTerakhir) . ', dibutuhkan: ' . number_format($quantity_used) . '. Silakan kurangi jumlah atau tambah stok terlebih dahulu.');
                }

                $stmt = $conn->prepare("UPDATE product_recipes SET raw_material_id = ?, quantity_used = ?, unit_measurement = ? WHERE id = ? AND product_id = ?");
                $stmt->execute([$raw_material_id, $quantity_used, $unit_measurement, $recipe_id, $product_id]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Tidak ada data yang diupdate. Silakan coba lagi.');
                }

                $_SESSION['resep_message'] = [
                    'text' => 'Item resep berhasil diupdate',
                    'type' => 'success'
                ];
                break;

            case 'add_manual_overhead':
                $overhead_id = $_POST['overhead_id'] ?? null;
                $custom_amount = $_POST['custom_amount'] ?? null;
                $multiplier = $_POST['multiplier'] ?? 1;

                if (!$overhead_id) {
                    throw new Exception('Overhead tidak dipilih');
                }

                // Check if already exists
                $checkStmt = $conn->prepare("SELECT id FROM product_overhead_manual WHERE product_id = ? AND overhead_id = ?");
                $checkStmt->execute([$product_id, $overhead_id]);
                
                if ($checkStmt->fetch()) {
                    throw new Exception('Overhead ini sudah ditambahkan ke resep produk ini');
                }

                // Get overhead data
                $stmtOverhead = $conn->prepare("SELECT * FROM overhead_costs WHERE id = ? AND is_active = 1");
                $stmtOverhead->execute([$overhead_id]);
                $overhead = $stmtOverhead->fetch(PDO::FETCH_ASSOC);

                if (!$overhead) {
                    throw new Exception('Data overhead tidak ditemukan');
                }

                // Use custom amount if provided, otherwise use default
                $amount = $custom_amount ? $custom_amount : $overhead['amount'];
                $final_amount = $amount * $multiplier;

                // Create table if not exists
                $conn->exec("CREATE TABLE IF NOT EXISTS product_overhead_manual (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    overhead_id INTEGER NOT NULL,
                    custom_amount DECIMAL(15,2),
                    multiplier DECIMAL(5,2) DEFAULT 1,
                    final_amount DECIMAL(15,2),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id),
                    FOREIGN KEY (overhead_id) REFERENCES overhead_costs(id)
                )");

                $stmt = $conn->prepare("INSERT INTO product_overhead_manual (product_id, overhead_id, custom_amount, multiplier, final_amount) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $overhead_id, $amount, $multiplier, $final_amount]);

                $_SESSION['resep_message'] = [
                    'text' => 'Overhead manual berhasil ditambahkan ke resep',
                    'type' => 'success'
                ];
                break;

            case 'add_manual_labor':
                $labor_id = $_POST['labor_id'] ?? null;
                $custom_hours = $_POST['custom_hours'] ?? null;
                $custom_hourly_rate = $_POST['custom_hourly_rate'] ?? null;

                if (!$labor_id) {
                    throw new Exception('Tenaga kerja tidak dipilih');
                }

                // Check if already exists
                $checkStmt = $conn->prepare("SELECT id FROM product_labor_manual WHERE product_id = ? AND labor_id = ?");
                $checkStmt->execute([$product_id, $labor_id]);
                
                if ($checkStmt->fetch()) {
                    throw new Exception('Posisi tenaga kerja ini sudah ditambahkan ke resep produk ini');
                }

                // Get labor data
                $stmtLabor = $conn->prepare("SELECT * FROM labor_costs WHERE id = ? AND is_active = 1");
                $stmtLabor->execute([$labor_id]);
                $labor = $stmtLabor->fetch(PDO::FETCH_ASSOC);

                if (!$labor) {
                    throw new Exception('Data tenaga kerja tidak ditemukan');
                }

                // Get product time if custom hours not provided
                $stmtProduct = $conn->prepare("SELECT production_time_hours FROM products WHERE id = ?");
                $stmtProduct->execute([$product_id]);
                $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

                $hours = $custom_hours ? $custom_hours : ($product['production_time_hours'] ?? 1);
                $hourly_rate = $custom_hourly_rate ? $custom_hourly_rate : $labor['hourly_rate'];
                $total_cost = $hours * $hourly_rate;

                // Create table if not exists
                $conn->exec("CREATE TABLE IF NOT EXISTS product_labor_manual (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    labor_id INTEGER NOT NULL,
                    custom_hours DECIMAL(5,2),
                    custom_hourly_rate DECIMAL(10,2),
                    total_cost DECIMAL(15,2),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id),
                    FOREIGN KEY (labor_id) REFERENCES labor_costs(id)
                )");

                $stmt = $conn->prepare("INSERT INTO product_labor_manual (product_id, labor_id, custom_hours, custom_hourly_rate, total_cost) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $labor_id, $hours, $hourly_rate, $total_cost]);

                $_SESSION['resep_message'] = [
                    'text' => 'Tenaga kerja manual berhasil ditambahkan ke resep',
                    'type' => 'success'
                ];
                break;

            case 'update_product_info':
                $production_yield = $_POST['production_yield'] ?? 1;
                $production_time_hours = $_POST['production_time_hours'] ?? 1;
                $sale_price = $_POST['sale_price'] ?? 0;

                $stmt = $conn->prepare("UPDATE products SET production_yield = ?, production_time_hours = ?, sale_price = ? WHERE id = ?");
                $stmt->execute([$production_yield, $production_time_hours, $sale_price, $product_id]);

                $_SESSION['resep_message'] = [
                    'text' => 'Informasi produk berhasil diupdate',
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

            $_SESSION['resep_message'] = [
                'text' => 'Item berhasil dihapus dari resep',
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