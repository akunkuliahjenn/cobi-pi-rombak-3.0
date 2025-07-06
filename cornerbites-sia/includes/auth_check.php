<?php
// includes/auth_check.php
// File ini berfungsi untuk memeriksa apakah pengguna sudah login dan memiliki role yang sesuai.

// Memulai session jika belum dimulai. Penting agar $_SESSION bisa diakses.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan koneksi database sudah ada, karena kita mungkin perlu query user role
require_once __DIR__ . '/../config/db.php'; // Atau pastikan sudah di-include di file yang memanggil auth_check.php

// Cek apakah user_id ada di session (sudah login)
if (!isset($_SESSION['user_id'])) {
    // Jika belum login, arahkan ke halaman login
    header("Location: /cornerbites-sia/auth/login.php");
    exit(); // Penting untuk menghentikan eksekusi skrip setelah redirect
}

// Ambil role pengguna dari session
$user_role = $_SESSION['user_role'] ?? 'guest';

// Tentukan halaman yang memerlukan role 'admin'
$admin_pages = [
    '/cornerbites-sia/admin/dashboard.php',
    '/cornerbites-sia/admin/users.php',
    '/cornerbites-sia/admin/semua_transaksi.php',
    '/cornerbites-sia/admin/statistik.php',
];

// Ambil path script yang sedang diakses
$current_page = $_SERVER['PHP_SELF'];

// Periksa apakah halaman yang diakses adalah halaman admin
if (in_array($current_page, $admin_pages)) {
    // Jika halaman admin diakses oleh non-admin, redirect ke dashboard user
    if ($user_role !== 'admin') {
        header("Location: /cornerbites-sia/pages/dashboard.php");
        exit();
    }
} else {
    // Jika halaman bukan admin (misal pages/dashboard.php) diakses oleh admin,
    // dan admin mencoba mengakses dashboard user, arahkan ke dashboard admin.
    // Ini opsional, tergantung UX yang diinginkan.
    // if ($user_role === 'admin' && strpos($current_page, '/pages/') !== false) {
    //     header("Location: /cornerbites-sia/admin/dashboard.php");
    //     exit();
    // }
}

// Jika sampai di sini, artinya user sudah login dan memiliki role yang sesuai
?>
