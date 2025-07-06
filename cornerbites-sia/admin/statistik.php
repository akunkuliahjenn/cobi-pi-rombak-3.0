<?php
// admin/statistik.php
// Halaman untuk admin melihat statistik global sistem.

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login dan role admin
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

$totalUsers = 0;
$totalTransactions = 0;
$totalProducts = 0;
$totalRevenueGlobal = $stmtRevenue->fetchColumn() ?? 0;
$totalExpenseGlobal = $stmtExpense->fetchColumn() ?? 0;
$averageSalePrice = $stmtAvgSalePrice->fetchColumn() ?? 0;
$mostSoldProduct = ['name' => 'N/A', 'total_quantity' => 0];

try {
    $conn = $db;

    // Statistik yang sama dengan dashboard admin
    $stmtUsers = $conn->query("SELECT COUNT(*) AS total_users FROM users");
    $totalUsers = $stmtUsers->fetchColumn();

    $stmtTransactions = $conn->query("SELECT COUNT(*) AS total_transactions FROM transactions");
    $totalTransactions = $stmtTransactions->fetchColumn();

    $stmtProducts = $conn->query("SELECT COUNT(*) AS total_products FROM products");
    $totalProducts = $stmtProducts->fetchColumn();

    $stmtRevenue = $conn->query("SELECT SUM(amount) AS total_revenue FROM transactions WHERE type = 'pemasukan'");
    $totalRevenueGlobal = $stmtRevenue->fetchColumn();

    $stmtExpense = $conn->query("SELECT SUM(amount) AS total_expense FROM transactions WHERE type = 'pengeluaran'");
    $totalExpenseGlobal = $stmtExpense->fetchColumn();

    // Statistik tambahan: Rata-rata Harga Jual Produk
    $stmtAvgSalePrice = $conn->query("SELECT AVG(sale_price) FROM products");
    $averageSalePrice = $stmtAvgSalePrice->fetchColumn();

    // Statistik tambahan: Produk Paling Banyak Terjual
    $stmtMostSoldProduct = $conn->query("
        SELECT p.name, SUM(t.quantity) as total_quantity
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        WHERE t.type = 'pemasukan' AND t.product_id IS NOT NULL
        GROUP BY p.name
        ORDER BY total_quantity DESC
        LIMIT 1
    ");
    $resultMostSoldProduct = $stmtMostSoldProduct->fetch();
    if ($resultMostSoldProduct) {
        $mostSoldProduct = $resultMostSoldProduct;
    }

} catch (PDOException $e) {
    error_log("Error di Admin Statistik: " . $e->getMessage());
    // Anda bisa menampilkan pesan error yang ramah pengguna di sini
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="flex items-center justify-between h-16 bg-white border-b border-gray-200 px-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-800">Statistik Global Sistem</h1>
        </header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-200 p-6">
            <div class="container mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Statistik & Analisis Sistem</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <!-- Kartu Statistik Umum -->
                    <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Pengguna</h3>
                        <p class="text-4xl font-extrabold text-indigo-600"><?php echo $totalUsers; ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Transaksi</h3>
                        <p class="text-4xl font-extrabold text-blue-600"><?php echo $totalTransactions; ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Produk</h3>
                        <p class="text-4xl font-extrabold text-purple-600"><?php echo $totalProducts; ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Pendapatan (Global)</h3>
                        <p class="text-4xl font-extrabold text-green-600">Rp <?php echo number_format($totalRevenueGlobal, 0, ',', '.'); ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Pengeluaran (Global)</h3>
                        <p class="text-4xl font-extrabold text-red-600">Rp <?php echo number_format($totalExpenseGlobal, 0, ',', '.'); ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Rata-rata Harga Jual Produk</h3>
                        <p class="text-4xl font-extrabold text-gray-700">Rp <?php echo number_format($averageSalePrice, 0, ',', '.'); ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Produk Paling Laris</h3>
                        <p class="text-4xl font-extrabold text-orange-500"><?php echo htmlspecialchars($mostSoldProduct['name']); ?></p>
                        <p class="text-sm text-gray-500 mt-2"><?php echo number_format($mostSoldProduct['total_quantity'], 0, ',', '.'); ?> unit terjual</p>
                    </div>
                </div>

                <!-- Bagian untuk menampilkan grafik lebih detail -->
                <div class="bg-white rounded-lg shadow-md p-6 mt-8">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Visualisasi Data (Contoh Placeholder)</h3>
                    <p class="text-gray-600">Di sini Anda bisa mengintegrasikan library JavaScript untuk grafik (misalnya Chart.js atau D3.js) untuk menampilkan visualisasi data tren penjualan, pengeluaran per kategori, dll.</p>
                    <div class="h-64 bg-gray-100 rounded-md flex items-center justify-center text-gray-500 mt-4">
                        Area untuk Grafik Interaktif
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
