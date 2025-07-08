<?php
// admin/users.php
// Halaman untuk admin mengelola data pengguna (view, tambah, edit, hapus).

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

// Inisialisasi variabel
$users = [];
$totalUsers = 0;
$totalAdmins = 0;
$totalRegularUsers = 0;
$recentUsers = [];
$userStats = [];

try {
    $conn = $db;

    // Ambil semua pengguna
    $stmt = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();

    // Hitung statistik
    $totalUsers = count($users);
    $totalAdmins = count(array_filter($users, function($user) { return $user['role'] === 'admin'; }));
    $totalRegularUsers = count(array_filter($users, function($user) { return $user['role'] === 'user'; }));

    // Ambil 5 pengguna terbaru
    $recentUsers = array_slice($users, 0, 5);

    // Statistik berdasarkan bulan registrasi (3 bulan terakhir)
    $stmtStats = $conn->query("
        SELECT 
            strftime('%Y-%m', created_at) as month,
            COUNT(*) as count
        FROM users 
        WHERE created_at >= date('now', '-3 months')
        GROUP BY strftime('%Y-%m', created_at)
        ORDER BY month DESC
    ");
    $userStats = $stmtStats->fetchAll();

} catch (PDOException $e) {
    error_log("Error di Admin Users: " . $e->getMessage());
}

// Pesan sukses atau error
$message = '';
$message_type = '';
if (isset($_SESSION['user_management_message'])) {
    $message = $_SESSION['user_management_message']['text'];
    $message_type = $_SESSION['user_management_message']['type'];
    unset($_SESSION['user_management_message']);
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Pengguna</h1>
                <p class="text-gray-600 mt-1">Kelola semua akun pengguna sistem</p>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <?php if ($message): ?>
                    <div class="<?php echo $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?> border rounded-lg p-4 shadow-sm">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <?php if ($message_type === 'success'): ?>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                <?php else: ?>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                <?php endif; ?>
                            </svg>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistik Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Total Pengguna</h3>
                                <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $totalUsers; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Admin</h3>
                                <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $totalAdmins; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 0 00-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Pengguna Biasa</h3>
                                <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $totalRegularUsers; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Aksi Cepat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <button onclick="showAddUserModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span>Tambah Pengguna</span>
                        </button>

                        <button onclick="exportUsers()" class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Export Data</span>
                        </button>

                        <button onclick="showUserStats()" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <span>Lihat Statistik</span>
                        </button>

                        <button onclick="bulkActions()" class="bg-orange-600 hover:bg-orange-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <span>Aksi Massal</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Pengguna Terbaru</h3>
                    <div class="space-y-3">
                        <?php foreach ($recentUsers as $user): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center">
                                        <span class="text-white font-semibold text-sm"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo ucfirst($user['role']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500"><?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Daftar Semua Pengguna -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold text-gray-800">Semua Pengguna</h3>
                            <div class="flex items-center space-x-3">
                                <input type="text" id="searchUser" placeholder="Cari pengguna..." class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <select id="filterRole" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Semua Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full" id="usersTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Daftar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50 user-row" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" class="user-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="<?php echo $user['id']; ?>">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['id']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-white font-semibold text-xs"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d M Y H:i', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                Aktif
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="text-blue-600 hover:text-blue-900 font-medium">
                                                    Edit
                                                </button>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="text-red-600 hover:text-red-900 font-medium">
                                                        Hapus
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Tambah/Edit User -->
<div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4" id="modalTitle">Tambah Pengguna Baru</h3>
                <form id="userForm" action="/cornerbites-sia/process/kelola_user.php" method="POST">
                    <input type="hidden" name="user_id" id="user_id_to_edit" value="">
                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" id="username" name="username" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1" id="passwordHelp">Minimal 6 karakter</p>
                        </div>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select id="role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors" id="submitBtn">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Search and Filter Functions
document.getElementById('searchUser').addEventListener('input', function() {
    filterUsers();
});

document.getElementById('filterRole').addEventListener('change', function() {
    filterUsers();
});

function filterUsers() {
    const searchTerm = document.getElementById('searchUser').value.toLowerCase();
    const roleFilter = document.getElementById('filterRole').value;
    const rows = document.querySelectorAll('.user-row');

    rows.forEach(row => {
        const username = row.dataset.username.toLowerCase();
        const role = row.dataset.role;

        const matchesSearch = username.includes(searchTerm);
        const matchesRole = !roleFilter || role === roleFilter;

        row.style.display = matchesSearch && matchesRole ? '' : 'none';
    });
}

// Modal Functions
function showAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Pengguna Baru';
    document.getElementById('userForm').reset();
    document.getElementById('user_id_to_edit').value = '';
    document.getElementById('passwordHelp').textContent = 'Minimal 6 karakter';
    document.getElementById('submitBtn').textContent = 'Simpan';
    document.getElementById('userModal').classList.remove('hidden');
}

function editUser(user) {
    document.getElementById('modalTitle').textContent = 'Edit Pengguna';
    document.getElementById('user_id_to_edit').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('role').value = user.role;
    document.getElementById('password').value = '';
    document.getElementById('passwordHelp').textContent = 'Kosongkan jika tidak ingin mengubah password';
    document.getElementById('submitBtn').textContent = 'Update';
    document.getElementById('userModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('userModal').classList.add('hidden');
}

function deleteUser(userId, username) {
    if (confirm(`Apakah Anda yakin ingin menghapus pengguna "${username}"?`)) {
        window.location.href = `/cornerbites-sia/process/hapus_user.php?id=${userId}`;
    }
}

// Bulk Actions
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });

     // Aktifkan/nonaktifkan tombol aksi massal berdasarkan jumlah yang dipilih
    const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = selectedCount;
    document.getElementById('bulkActionBtn').disabled = selectedCount === 0;
});

function bulkActions() {
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    if (selectedUsers.length === 0) {
        alert('Pilih setidaknya satu pengguna untuk melakukan aksi massal.');
        return;
    }

    const action = prompt('Pilih aksi:\n1. Ubah role menjadi "user"\n2. Ubah role menjadi "admin"\n3. Hapus pengguna terpilih\n\nMasukkan nomor pilihan:');

    if (action === '1' || action === '2') {
        const newRole = action === '1' ? 'user' : 'admin';
        if (confirm(`Ubah role ${selectedUsers.length} pengguna terpilih menjadi "${newRole}"?`)) {
            // Gunakan kelola_user.php yang sudah ada untuk update role
            selectedUsers.forEach(userId => {
                // Ambil data user terlebih dahulu
                const userRow = document.querySelector(`input[value="${userId}"]`).closest('tr');
                const username = userRow.querySelector('td:nth-child(3)').textContent.trim();

                // Buat form untuk setiap user
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/cornerbites-sia/process/kelola_user.php';
                form.style.display = 'none';

                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;

                const usernameInput = document.createElement('input');
                usernameInput.type = 'hidden';
                usernameInput.name = 'username';
                usernameInput.value = username;

                const roleInput = document.createElement('input');
                roleInput.type = 'hidden';
                roleInput.name = 'role';
                roleInput.value = newRole;

                form.appendChild(userIdInput);
                form.appendChild(usernameInput);
                form.appendChild(roleInput);
                document.body.appendChild(form);
                form.submit();
            });
        }
    } else if (action === '3') {
        if (confirm(`Apakah Anda yakin ingin menghapus ${selectedUsers.length} pengguna terpilih?`)) {
            // Gunakan hapus_user.php yang sudah ada
            if (selectedUsers.includes('<?php echo $_SESSION['user_id']; ?>')) {
                alert('Anda tidak bisa menghapus akun Anda sendiri!');
                return;
            }

            selectedUsers.forEach((userId, index) => {
                setTimeout(() => {
                    window.location.href = `/cornerbites-sia/process/hapus_user.php?id=${userId}`;
                }, index * 100); // Delay untuk menghindari konflik
            });
        }
    }
}

function exportUsers() {
    window.location.href = '/cornerbites-sia/process/export_users.php';
}

function showUserStats() {
    const statsData = <?php echo json_encode($userStats); ?>;
    let statsText = 'Statistik Pendaftaran Pengguna (3 Bulan Terakhir):\n\n';

    if (statsData.length > 0) {
        statsData.forEach(stat => {
            statsText += `${stat.month}: ${stat.count} pengguna\n`;
        });
    } else {
        statsText += 'Belum ada data statistik.';
    }

    alert(statsText);
}

// Close modal when clicking outside
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

function toggleBulkMenu() {
    document.getElementById('bulkMenu').classList.toggle('hidden');
}

function bulkChangeRole(newRole) {
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    if (confirm(`Ubah role ${selectedUsers.length} pengguna terpilih menjadi "${newRole}"?`)) {
        // Gunakan kelola_user.php yang sudah ada untuk update role
        selectedUsers.forEach(userId => {
            // Ambil data user terlebih dahulu
            const userRow = document.querySelector(`input[value="${userId}"]`).closest('tr');
            const username = userRow.querySelector('td:nth-child(3)').textContent.trim();

            // Buat form untuk setiap user
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/cornerbites-sia/process/kelola_user.php';
            form.style.display = 'none';

            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;

            const usernameInput = document.createElement('input');
            usernameInput.type = 'hidden';
            usernameInput.name = 'username';
            usernameInput.value = username;

            const roleInput = document.createElement('input');
            roleInput.type = 'hidden';
            roleInput.name = 'role';
            roleInput.value = newRole;

            form.appendChild(userIdInput);
            form.appendChild(usernameInput);
            form.appendChild(roleInput);
            document.body.appendChild(form);
            form.submit();
        });
    }
}

function bulkDeleteSelected() {
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    if (confirm(`Apakah Anda yakin ingin menghapus ${selectedUsers.length} pengguna terpilih?`)) {
        // Gunakan hapus_user.php yang sudah ada
        if (selectedUsers.includes('<?php echo $_SESSION['user_id']; ?>')) {
            alert('Anda tidak bisa menghapus akun Anda sendiri!');
            return;
        }

        selectedUsers.forEach((userId, index) => {
            setTimeout(() => {
                window.location.href = `/cornerbites-sia/process/hapus_user.php?id=${userId}`;
            }, index * 100); // Delay untuk menghindari konflik
        });
    }
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>