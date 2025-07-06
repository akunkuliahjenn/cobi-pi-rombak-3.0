<?php
// process/simpan_produk.php
// File ini menangani logika penyimpanan/pembaruan/penghapusan produk.

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Memulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = $db; // Menggunakan koneksi $db dari db.php

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // --- Proses Tambah/Edit Produk ---
        $product_id = $_POST['product_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $unit_select = trim($_POST['unit'] ?? '');
        $unit_custom = trim($_POST['unit_custom'] ?? '');
        $unit = ($unit_select === 'custom') ? $unit_custom : $unit_select;
        $stock = (int) ($_POST['stock'] ?? 0);

        // Handle sale_price - remove any formatting
        $sale_price_raw = $_POST['sale_price'] ?? 0;
        if (is_string($sale_price_raw)) {
            // Remove any currency formatting
            $sale_price_raw = preg_replace('/[^0-9.]/', '', $sale_price_raw);
        }
        $sale_price = (float) $sale_price_raw;

        // Validasi dasar
        if (empty($name)) {
            $_SESSION['product_message'] = ['text' => 'Nama produk harus diisi.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        if (empty($unit)) {
            $_SESSION['product_message'] = ['text' => 'Satuan produk harus diisi.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        if ($stock < 0) {
            $_SESSION['product_message'] = ['text' => 'Stok tidak boleh negatif.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        if ($sale_price < 0) {
            $_SESSION['product_message'] = ['text' => 'Harga jual tidak boleh negatif.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        if ($product_id) {
            // --- Update Produk ---
            $stmt = $conn->prepare("UPDATE products SET name = ?, unit = ?, stock = ?, sale_price = ? WHERE id = ?");
            if ($stmt->execute([$name, $unit, $stock, $sale_price, $product_id])) {
                $_SESSION['product_message'] = ['text' => 'Produk berhasil diperbarui!', 'type' => 'success'];
            } else {
                $_SESSION['product_message'] = ['text' => 'Gagal memperbarui produk.', 'type' => 'error'];
            }
        } else {
            // --- Tambah Produk Baru ---
            $stmt = $conn->prepare("INSERT INTO products (name, unit, stock, sale_price) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $unit, $stock, $sale_price])) {
                $_SESSION['product_message'] = ['text' => 'Produk baru berhasil ditambahkan!', 'type' => 'success'];
            } else {
                $_SESSION['product_message'] = ['text' => 'Gagal menambahkan produk baru.', 'type' => 'error'];
            }
        }

        header("Location: /cornerbites-sia/pages/produk.php");
        exit();

    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete') {
        // --- Proses Hapus Produk ---
        $product_id = $_GET['id'] ?? null;

        if (empty($product_id)) {
            $_SESSION['product_message'] = ['text' => 'ID produk tidak ditemukan untuk dihapus.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        // Cek apakah produk terkait dengan resep atau batch produksi
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM product_recipes WHERE product_id = ?");
        $stmtCheck->execute([$product_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $_SESSION['product_message'] = ['text' => 'Tidak bisa menghapus produk karena sudah memiliki resep yang terkait. Hapus resep terlebih dahulu.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        if ($stmt->execute([$product_id])) {
            $_SESSION['product_message'] = ['text' => 'Produk berhasil dihapus!', 'type' => 'success'];
        } else {
            $_SESSION['product_message'] = ['text' => 'Gagal menghapus produk.', 'type' => 'error'];
        }
        header("Location: /cornerbites-sia/pages/produk.php");
        exit();

    } else {
        // Jika diakses langsung tanpa POST/GET yang valid, redirect
        header("Location: /cornerbites-sia/pages/produk.php");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error simpan/hapus produk: " . $e->getMessage());
    $_SESSION['product_message'] = ['text' => 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'type' => 'error'];
    header("Location: /cornerbites-sia/pages/produk.php");
    exit();
}
?>