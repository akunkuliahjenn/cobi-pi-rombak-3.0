<?php
// process/login_process.php
// File ini menangani logika proses login.

// Memulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = 'Username dan password harus diisi.';
        header("Location: /cornerbites-sia/auth/login.php");
        exit();
    }

    try {
        $conn = $db; // Menggunakan koneksi $db dari db.php

        // Siapkan query untuk mencari user berdasarkan username dan mengambil role
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verifikasi user dan password
        if ($user && password_verify($password, $user['password'])) {
             
            // Login berhasil
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role']; // Simpan role di session
            
            // Redirect sesuai role
            if ($user['role'] === 'admin') {
                header("Location: /cornerbites-sia/admin/dashboard.php");
            } else {
                header("Location: /cornerbites-sia/pages/dashboard.php");
            }
            exit();
        } else {
            // Login gagal
            $_SESSION['error_message'] = 'Username atau password salah.';
            header("Location: /cornerbites-sia/auth/login.php");
            exit();
        }

    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        header("Location: /cornerbites-sia/auth/login.php");
        exit();
    }
} else {
    // Jika diakses langsung tanpa POST request, redirect ke login
    header("Location: /cornerbites-sia/auth/login.php");
    exit();
}
?>
