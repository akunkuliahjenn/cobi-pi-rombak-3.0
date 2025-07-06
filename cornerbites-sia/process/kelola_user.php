<?php
// process/kelola_user.php
// File ini menangani logika tambah dan edit pengguna oleh admin.

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login dan role admin
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['user_management_message'] = ['text' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.', 'type' => 'error'];
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

// Memulai session jika belum dimulai (sudah ada di db.php, tapi jaga-jaga)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? null; // ID user jika ini adalah operasi edit
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? ''); // Bisa kosong jika tidak ingin mengubah password
    $role = trim($_POST['role'] ?? 'user'); // Default ke 'user' jika tidak diset

    // Validasi dasar
    if (empty($username) || !in_array($role, ['user', 'admin'])) {
        $_SESSION['user_management_message'] = ['text' => 'Username atau role tidak valid.', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    try {
        $conn = $db;
        $conn->beginTransaction();

        if ($user_id) {
            // --- Proses Edit Pengguna ---
            // Cek apakah username sudah digunakan oleh user lain
            $stmtCheckUsername = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmtCheckUsername->execute([$username, $user_id]);
            if ($stmtCheckUsername->fetch()) {
                $_SESSION['user_management_message'] = ['text' => 'Username sudah digunakan oleh pengguna lain.', 'type' => 'error'];
                header("Location: /cornerbites-sia/admin/users.php");
                exit();
            }

            $query = "UPDATE users SET username = ?, role = ?";
            $params = [$username, $role];

            if (!empty($password)) {
                // Hanya update password jika diisi
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query .= ", password = ?";
                $params[] = $hashed_password;
            }
            $query .= " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $conn->prepare($query);
            if ($stmt->execute($params)) {
                $conn->commit();
                $_SESSION['user_management_message'] = ['text' => 'Pengguna berhasil diperbarui!', 'type' => 'success'];
            } else {
                $conn->rollBack();
                $_SESSION['user_management_message'] = ['text' => 'Gagal memperbarui pengguna.', 'type' => 'error'];
            }

        } else {
            // --- Proses Tambah Pengguna Baru ---
            if (empty($password)) {
                $_SESSION['user_management_message'] = ['text' => 'Password harus diisi untuk pengguna baru.', 'type' => 'error'];
                header("Location: /cornerbites-sia/admin/users.php");
                exit();
            }
            if (strlen($password) < 6) {
                $_SESSION['user_management_message'] = ['text' => 'Password minimal 6 karakter.', 'type' => 'error'];
                header("Location: /cornerbites-sia/admin/users.php");
                exit();
            }

            // Cek apakah username sudah ada
            $stmtCheckUsername = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheckUsername->execute([$username]);
            if ($stmtCheckUsername->fetch()) {
                $_SESSION['user_management_message'] = ['text' => 'Username sudah digunakan. Pilih username lain.', 'type' => 'error'];
                header("Location: /cornerbites-sia/admin/users.php");
                exit();
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $hashed_password, $role])) {
                $conn->commit();
                $_SESSION['user_management_message'] = ['text' => 'Pengguna baru berhasil ditambahkan!', 'type' => 'success'];
            } else {
                $conn->rollBack();
                $_SESSION['user_management_message'] = ['text' => 'Gagal menambahkan pengguna baru.', 'type' => 'error'];
            }
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error kelola user: " . $e->getMessage());
        $_SESSION['user_management_message'] = ['text' => 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'type' => 'error'];
    }

    header("Location: /cornerbites-sia/admin/users.php");
    exit();

} else {
    // Jika diakses langsung tanpa POST request, redirect
    header("Location: /cornerbites-sia/admin/users.php");
    exit();
}
?>
