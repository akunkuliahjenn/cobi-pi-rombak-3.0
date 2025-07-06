<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// Debug bantu lihat apakah session kebaca
// echo "<pre>"; print_r($_SESSION); echo "</pre>"; exit();

// Periksa apakah user sudah login
if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: /cornerbites-sia/admin/dashboard.php");
    } else {
        header("Location: /cornerbites-sia/pages/dashboard.php");
    }
    exit();
} else {
    // Jika belum login, arahkan ke halaman login
    header("Location: /cornerbites-sia/auth/login.php");
    exit();
}
?>
