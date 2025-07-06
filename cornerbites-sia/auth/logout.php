<?php
// auth/logout.php
// File ini untuk proses logout pengguna.

// Memulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel session
$_SESSION = array();

// Hapus session cookie. Perhatikan bahwa ini akan menghancurkan session,
// dan bukan hanya data session!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Redirect ke halaman login setelah logout
header("Location: /cornerbites-sia/auth/login.php");
exit(); // Penting untuk menghentikan eksekusi skrip
?>
