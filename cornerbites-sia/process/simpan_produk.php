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

        // Default sale_price to 0 - will be set via HPP management
        $sale_price = 0;

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

        

        if ($product_id) {
            // --- Update Produk --- (hanya update name dan unit, harga jual diatur di HPP)
            $stmt = $conn->prepare("UPDATE products SET name = ?, unit = ? WHERE id = ?");
            if ($stmt->execute([$name, $unit, $product_id])) {
                $_SESSION['product_message'] = ['text' => 'Produk berhasil diperbarui! Harga jual dapat diatur di halaman Manajemen Resep & HPP.', 'type' => 'success'];
            } else {
                $_SESSION['product_message'] = ['text' => 'Gagal memperbarui produk.', 'type' => 'error'];
            }
        } else {
            // --- Tambah Produk Baru ---
            $stmt = $conn->prepare("INSERT INTO products (name, unit, sale_price) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $unit, $sale_price])) {
                $_SESSION['product_message'] = ['text' => 'Produk baru berhasil ditambahkan! Selanjutnya buat resep dan atur harga jual di halaman Manajemen Resep & HPP.', 'type' => 'success'];
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