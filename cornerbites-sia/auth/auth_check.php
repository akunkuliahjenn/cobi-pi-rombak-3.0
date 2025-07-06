<?php
// auth/auth_check.php
// File ini berfungsi untuk memeriksa apakah pengguna sudah login.
// Akan diarahkan ke halaman login jika belum.

// Memulai session jika belum dimulai. Penting agar $_SESSION bisa diakses.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah variabel session 'user_id' sudah diatur.
// Ini adalah indikator bahwa pengguna sudah login.
if (!isset($_SESSION['user_id'])) {
    // Jika belum login, arahkan ke halaman login
    header("Location: /cornerbites-sia/auth/login.php");
    exit(); // Penting untuk menghentikan eksekusi skrip setelah redirect
}
?>
