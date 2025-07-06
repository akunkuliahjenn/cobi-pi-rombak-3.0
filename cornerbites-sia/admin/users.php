<?php
// admin/users.php
// Halaman untuk admin mengelola data pengguna (view, tambah, edit, hapus).

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login dan role admin
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

$users = [];
try {
    $conn = $db;
    $stmt = $conn->query("SELECT id, username, role FROM users ORDER BY username ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error di Admin Users: " . $e->getMessage());
    // echo "Terjadi kesalahan saat memuat data pengguna.";
}

// Pesan sukses atau error setelah proses
$message = '';
$message_type = ''; // 'success' or 'error'
if (isset($_SESSION['user_management_message'])) {
    $message = $_SESSION['user_management_message']['text'];
    $message_type = $_SESSION['user_management_message']['type'];
    unset($_SESSION['user_management_message']);
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="flex items-center justify-between h-16 bg-white border-b border-gray-200 px-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-800">Manajemen Pengguna</h1>
        </header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-200 p-6">
            <div class="container mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Kelola Akun Pengguna</h2>

                <?php if ($message): ?>
                    <?php
                        $alertClasses = 'border ';
                        if ($message_type === 'success') {
                            $alertClasses .= 'bg-green-100 border-green-400 text-green-700';
                        } else {
                            $alertClasses .= 'bg-red-100 border-red-400 text-red-700';
                        }
                        ?>
                    <div class="mb-4 p-4 rounded-md <?= $alertClasses ?>" role="alert">
                            <?= htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Form Tambah/Edit Pengguna -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Tambah / Edit Pengguna</h3>
                    <form action="/cornerbites-sia/process/kelola_user.php" method="POST">
                        <input type="hidden" name="user_id" id="user_id_to_edit" value="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username:</label>
                                <input type="text" id="username" name="username" class="form-input w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                            </div>
                            <div>
                                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password (Kosongkan jika tidak diubah):</label>
                                <input type="password" id="password" name="password" class="form-input w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </div>
                            <div>
                                <label for="role" class="block text-gray-700 text-sm font-semibold mb-2">Role:</label>
                                <select id="role" name="role" class="form-select w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors duration-200" id="submit_button">
                            Tambah Pengguna
                        </button>
                        <button type="button" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors duration-200 ml-2 hidden" id="cancel_edit_button" onclick="resetForm()">
                            Batal Edit
                        </button>
                    </form>
                </div>

                <!-- Daftar Pengguna -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Daftar Pengguna Sistem</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                                <?php if ($user['id'] != $_SESSION['user_id']): // Tidak bisa menghapus akun sendiri ?>
                                                    <a href="/cornerbites-sia/process/hapus_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">Hapus</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Belum ada pengguna terdaftar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // JavaScript untuk mengisi form saat tombol edit diklik
    function editUser(user) {
        document.getElementById('user_id_to_edit').value = user.id;
        document.getElementById('username').value = user.username;
        document.getElementById('role').value = user.role;
        document.getElementById('password').value = ''; // Kosongkan password saat edit untuk keamanan

        document.getElementById('submit_button').textContent = 'Update Pengguna';
        document.getElementById('submit_button').classList.remove('bg-green-600', 'hover:bg-green-700');
        document.getElementById('submit_button').classList.add('bg-blue-600', 'hover:bg-blue-700');
        document.getElementById('cancel_edit_button').classList.remove('hidden');
    }

    // JavaScript untuk mereset form
    function resetForm() {
        document.getElementById('user_id_to_edit').value = '';
        document.getElementById('username').value = '';
        document.getElementById('password').value = '';
        document.getElementById('role').value = 'user'; // Reset ke default user

        document.getElementById('submit_button').textContent = 'Tambah Pengguna';
        document.getElementById('submit_button').classList.remove('bg-blue-600', 'hover:bg-blue-700');
        document.getElementById('submit_button').classList.add('bg-green-600', 'hover:bg-green-700');
        document.getElementById('cancel_edit_button').classList.add('hidden');
    }
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
