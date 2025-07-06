<?php
// process/register_process.php
// (Opsional) File ini menangani logika proses registrasi user baru.
// Pengguna yang mendaftar melalui form ini akan secara default memiliki role 'user'.

// Memulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role = 'user'; // Default role untuk registrasi melalui form ini

    // Validasi input
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $_SESSION['error_message_register'] = 'Semua field harus diisi.';
        header("Location: /cornerbites-sia/auth/register.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error_message_register'] = 'Konfirmasi password tidak cocok.';
        header("Location: /cornerbites-sia/auth/register.php");
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['error_message_register'] = 'Password minimal 6 karakter.';
        header("Location: /cornerbites-sia/auth/register.php");
        exit();
    }

    try {
        $conn = $db; // Menggunakan koneksi $db dari db.php

        // Cek apakah username sudah ada
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['error_message_register'] = 'Username sudah digunakan. Pilih username lain.';
            header("Location: /cornerbites-sia/auth/register.php");
            exit();
        }

        // Hash password sebelum disimpan
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Masukkan data user baru ke database dengan role 'user'
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $hashed_password, $role])) {
            $_SESSION['success_message_register'] = 'Registrasi berhasil! Silakan login.';
            header("Location: /cornerbites-sia/auth/login.php");
            exit();
        } else {
            $_SESSION['error_message_register'] = 'Gagal mendaftar user. Silakan coba lagi.';
            header("Location: /cornerbites-sia/auth/register.php");
            exit();
        }

    } catch (PDOException $e) {
        error_log("Register Error: " . $e->getMessage());
        $_SESSION['error_message_register'] = 'Terjadi kesalahan sistem saat mendaftar. Silakan coba lagi nanti.';
        header("Location: /cornerbites-sia/auth/register.php");
        exit();
    }
} else {
    // Jika diakses langsung tanpa POST request, redirect ke register
    header("Location: /cornerbites-sia/auth/register.php");
    exit();
}
?>
