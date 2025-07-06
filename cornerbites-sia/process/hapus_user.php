<?php
// process/hapus_user.php
// File ini menangani logika penghapusan pengguna oleh admin.

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login dan role admin
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Pastikan hanya admin yang bisa mengakses dan tidak menghapus diri sendiri
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['user_management_message'] = ['text' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.', 'type' => 'error'];
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $user_id_to_delete = (int) $_GET['id'];

    // Cek agar admin tidak menghapus akunnya sendiri
    if ($user_id_to_delete === (int)$_SESSION['user_id']) {
        $_SESSION['user_management_message'] = ['text' => 'Anda tidak bisa menghapus akun Anda sendiri!', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    try {
        $conn = $db;
        $conn->beginTransaction(); // Mulai transaksi database

        // Opsional: Hapus semua transaksi terkait pengguna yang akan dihapus
        // Atau ubah user_id transaksi menjadi NULL atau ke user_id 'guest'
        // Untuk proyek ini, kita asumsikan tidak ada relasi cascade delete
        // atau relasi user_id di tabel transaksi adalah NULLable.
        // Jika tidak NULLable dan ada transaksi, penghapusan akan gagal.
        $stmtDeleteTransactions = $conn->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmtDeleteTransactions->execute([$user_id_to_delete]);


        // Hapus pengguna dari database
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id_to_delete])) {
            $conn->commit();
            $_SESSION['user_management_message'] = ['text' => 'Pengguna berhasil dihapus!', 'type' => 'success'];
        } else {
            $conn->rollBack();
            $_SESSION['user_management_message'] = ['text' => 'Gagal menghapus pengguna.', 'type' => 'error'];
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error hapus user: " . $e->getMessage());
        $_SESSION['user_management_message'] = ['text' => 'Terjadi kesalahan sistem saat menghapus pengguna: ' . $e->getMessage(), 'type' => 'error'];
    }

    header("Location: /cornerbites-sia/admin/users.php");
    exit();

} else {
    // Jika diakses tanpa ID atau metode tidak valid
    $_SESSION['user_management_message'] = ['text' => 'Permintaan tidak valid untuk menghapus pengguna.', 'type' => 'error'];
    header("Location: /cornerbites-sia/admin/users.php");
    exit();
}
?>
