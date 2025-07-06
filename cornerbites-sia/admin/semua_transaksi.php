<?php
// admin/semua_transaksi.php
// Halaman untuk admin melihat semua transaksi dari semua pengguna (atau semua transaksi global).

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login dan role admin
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

$allTransactions = [];
try {
    $conn = $db;
    // Mengambil semua transaksi. Jika ada kolom user_id di tabel transactions,
    // Anda bisa JOIN dengan tabel users untuk menampilkan username.
    $stmt = $conn->query("
        SELECT t.*, p.name as product_name, u.username
        FROM transactions t
        LEFT JOIN products p ON t.product_id = p.id
        LEFT JOIN users u ON t.user_id = u.id -- Asumsi kolom user_id ada di tabel transactions
        ORDER BY t.date DESC, t.created_at DESC
    ");
    $allTransactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error di Admin Semua Transaksi: " . $e->getMessage());
    // echo "Terjadi kesalahan saat memuat semua data transaksi.";
}

?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="flex items-center justify-between h-16 bg-white border-b border-gray-200 px-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-800">Semua Transaksi</h1>
        </header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-200 p-6">
            <div class="container mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Semua Transaksi Sistem</h2>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Ringkasan Transaksi</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah (Rp)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk Terkait</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($allTransactions)): ?>
                                    <?php foreach ($allTransactions as $transaction): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($transaction['date']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo ($transaction['type'] == 'pemasukan' ? 'text-green-600' : 'text-red-600'); ?>">
                                                <?php echo htmlspecialchars(ucfirst($transaction['type'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($transaction['product_name'] ?? '-'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($transaction['quantity'] ?? '-'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($transaction['username'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <!-- Aksi Edit/Hapus, perlu disesuaikan dengan kebutuhan admin -->
                                                <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3">Detail</a>
                                                <!-- <a href="#" class="text-red-600 hover:text-red-900">Hapus</a> -->
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Belum ada transaksi tercatat di sistem.</td>
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
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
