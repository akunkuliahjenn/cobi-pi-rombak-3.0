<?php
// pages/dashboard.php
// Halaman dashboard utama yang menampilkan ringkasan keuangan dan grafik untuk pengguna biasa.

// Pastikan auth_check.php dipanggil di awal setiap halaman yang membutuhkan login
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Inisialisasi variabel dengan nilai default
$totalPenjualan = 0;
$totalPengeluaran = 0;
$estimasiLabaBersih = 0;
$produkTerjual = 0;
$stokRendahCount = 0;

$monthlySales = [];
$monthlyExpenses = [];
$monthsLabel = [];

$popularProducts = [];
$popularProductNames = [];
$popularProductQuantities = [];

try {
    // Ambil koneksi database
    $conn = $db; // $db sudah didefinisikan di config/db.php

    // Query untuk Total Penjualan
    $stmtPenjualan = $conn->query("SELECT SUM(amount) AS total_penjualan FROM transactions WHERE type = 'pemasukan'");
    $resultPenjualan = $stmtPenjualan->fetch();
    $totalPenjualan = $resultPenjualan['total_penjualan'] ?? 0;

    // Query untuk Total Pengeluaran
    $stmtPengeluaran = $conn->query("SELECT SUM(amount) AS total_pengeluaran FROM transactions WHERE type = 'pengeluaran'");
    $resultPengeluaran = $stmtPengeluaran->fetch();
    $totalPengeluaran = $resultPengeluaran['total_pengeluaran'] ?? 0;

    // Hitung Estimasi Laba Bersih
    $estimasiLabaBersih = $totalPenjualan - $totalPengeluaran;

    // Query untuk Jumlah Produk Terjual (total quantity dari transaksi pemasukan)
    $stmtProdukTerjual = $conn->query("SELECT SUM(quantity) AS total_quantity_sold FROM transactions WHERE type = 'pemasukan' AND product_id IS NOT NULL");
    $resultProdukTerjual = $stmtProdukTerjual->fetch();
    $produkTerjual = $resultProdukTerjual['total_quantity_sold'] ?? 0;

    // Query untuk jumlah produk dengan stok rendah (menggunakan 'stock' sesuai DB Anda)
    // Batas stok rendah bisa disesuaikan di sini (misal < 10, < 5, dll.)
    $stmtStokRendah = $conn->query("SELECT COUNT(*) AS stok_rendah_count FROM products WHERE stock < 10");
    $resultStokRendah = $stmtStokRendah->fetch();
    $stokRendahCount = $resultStokRendah['stok_rendah_count'] ?? 0;

    // PAGINATION
    $limit_options = [10, 25, 50, 100];
    $limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
    $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
    $offset = ($page - 1) * $limit;

    $totalStmt = $conn->query("SELECT COUNT(*) FROM transactions");
    $totalTransaksi = $totalStmt->fetchColumn();
    $totalPages = ceil($totalTransaksi / $limit);

    // Query untuk Transaksi Terbaru (misal, 5 transaksi terakhir)
    // Join dengan tabel products untuk mendapatkan nama produk
    // Mengambil transaksi terbaru dengan pagination untuk dashboard
    $stmtTransactions = $conn->prepare("SELECT t.*, p.name as product_name FROM transactions t LEFT JOIN products p ON t.product_id = p.id ORDER BY t.date DESC, t.id DESC LIMIT :limit OFFSET :offset");
    $stmtTransactions->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtTransactions->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtTransactions->execute();
    $transaksiTerbaru = $stmtTransactions->fetchAll(PDO::FETCH_ASSOC);

    // --- Data untuk Grafik Tren (Penjualan dan Pengeluaran Bulanan) ---
    // Mengambil data 6 bulan terakhir
    $stmtMonthlySales = $conn->query("
        SELECT
            DATE_FORMAT(date, '%Y-%m') as period,
            DATE_FORMAT(date, '%M %Y') as month_label,
            SUM(amount) as total_amount
        FROM transactions
        WHERE type = 'pemasukan' AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY period, month_label
        ORDER BY period ASC
    ");
    $rawMonthlySales = $stmtMonthlySales->fetchAll(PDO::FETCH_ASSOC);

    $stmtMonthlyExpenses = $conn->query("
        SELECT
            DATE_FORMAT(date, '%Y-%m') as period,
            DATE_FORMAT(date, '%M %Y') as month_label,
            SUM(amount) as total_amount
        FROM transactions
        WHERE type = 'pengeluaran' AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY period, month_label
        ORDER BY period ASC
    ");
    $rawMonthlyExpenses = $stmtMonthlyExpenses->fetchAll(PDO::FETCH_ASSOC);

    // Populate monthsLabel, monthlySales, monthlyExpenses for the last 6 months
    $salesData = [];
    $expenseData = [];

    // Convert array to associative array with period as key
    foreach ($rawMonthlySales as $row) {
        $salesData[$row['period']] = $row['total_amount'];
    }

    foreach ($rawMonthlyExpenses as $row) {
        $expenseData[$row['period']] = $row['total_amount'];
    }

    // Generate data for last 6 months
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("-$i months");
        $period = $date->format('Y-m');
        $monthLabel = $date->format('M Y');
        $monthsLabel[] = $monthLabel;

        $monthlySales[] = isset($salesData[$period]) ? $salesData[$period] : 0;
        $monthlyExpenses[] = isset($expenseData[$period]) ? $expenseData[$period] : 0;
    }


    // --- Data untuk Grafik Produk Terlaris (Top 5 Products) ---
    $stmtPopularProducts = $conn->query("
        SELECT p.name AS product_name, SUM(t.quantity) AS total_sold_quantity
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        WHERE t.type = 'pemasukan' AND t.product_id IS NOT NULL
        GROUP BY p.name
        ORDER BY total_sold_quantity DESC
        LIMIT 5
    ");
    $popularProducts = $stmtPopularProducts->fetchAll();

    foreach ($popularProducts as $product) {
        $popularProductNames[] = $product['product_name'];
        $popularProductQuantities[] = $product['total_sold_quantity'];
    }

} catch (PDOException $e) {
    // Tangani error database
    error_log("Error di Dashboard Pengguna: " . $e->getMessage());
    $transaksiTerbaru = [];
    $monthsLabel = [];
    $monthlySales = [];
    $monthlyExpenses = [];
    $popularProductNames = [];
    $popularProductQuantities = [];
}

// Convert PHP arrays to JSON for JavaScript
$monthsLabelJson = json_encode($monthsLabel);
$monthlySalesJson = json_encode($monthlySales);
$monthlyExpensesJson = json_encode($monthlyExpenses);
$popularProductNamesJson = json_encode($popularProductNames);
$popularProductQuantitiesJson = json_encode($popularProductQuantities);

?>



<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Bar/Header -->
        <header class="flex items-center justify-between h-16 bg-white border-b border-gray-200 px-6 shadow-sm">
            <div class="flex items-center">
                <h1 class="text-xl font-semibold text-gray-800">Dashboard Utama</h1>
            </div>
            <div>
                <span class="text-gray-600">Halo, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Pengguna'); ?>!</span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-200 p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Ringkasan Keuangan Anda</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                <!-- Card Total Penjualan -->
                <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Penjualan</h3>
                    <p class="text-4xl font-extrabold text-green-600">Rp <?php echo number_format($totalPenjualan, 0, ',', '.'); ?></p>
                    <p class="text-sm text-gray-500 mt-2">Selama Ini</p>
                </div>

                <!-- Card Total Pengeluaran -->
                <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Pengeluaran</h3>
                    <p class="text-4xl font-extrabold text-red-600">Rp <?php echo number_format($totalPengeluaran, 0, ',', '.'); ?></p>
                    <p class="text-sm text-gray-500 mt-2">Selama Ini</p>
                </div>

                <!-- Card Estimasi Laba Bersih -->
                <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Estimasi Laba Bersih</h3>
                    <p class="text-4xl font-extrabold text-blue-600">Rp <?php echo number_format($estimasiLabaBersih, 0, ',', '.'); ?></p>
                    <p class="text-sm text-gray-500 mt-2">Selama Ini</p>
                </div>

                <!-- Card Jumlah Produk Terjual -->
                <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Produk Terjual</h3>
                    <p class="text-4xl font-extrabold text-purple-600"><?php echo number_format($produkTerjual, 0, ',', '.'); ?> unit</p>
                    <p class="text-sm text-gray-500 mt-2">Total Item Terjual</p>
                </div>

                <!-- Card Stok Produk Rendah -->
                <div class="bg-white rounded-lg shadow-md p-6 transform hover:scale-105 transition duration-200 ease-in-out">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Produk Stok Rendah</h3>
                    <p class="text-4xl font-extrabold text-orange-500"><?php echo $stokRendahCount; ?> Item</p>
                    <p class="text-sm text-gray-500 mt-2">Perlu Restock</p>
                </div>
            </div>

            <!-- Bagian untuk Grafik (Dua Kolom) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Grafik Tren Penjualan & Pengeluaran Bulanan (Line Chart) -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Tren Penjualan & Pengeluaran Bulanan</h3>
                    <div class="relative h-96">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- Grafik Produk Terlaris (Bar Chart) -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Top 5 Produk Terlaris (Unit Terjual)</h3>
                    <div class="relative h-96">
                        <canvas id="popularProductsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Bagian untuk menampilkan tabel transaksi terbaru -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Transaksi Terbaru Anda</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk (Qty)</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($transaksiTerbaru)): ?>
                                <?php foreach ($transaksiTerbaru as $transaksi): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($transaksi['date']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo ($transaksi['type'] == 'pemasukan' ? 'text-green-600' : 'text-red-600'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($transaksi['type'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($transaksi['description']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php
                                                if ($transaksi['type'] == 'pemasukan' && !empty($transaksi['product_name'])) {
                                                    echo htmlspecialchars($transaksi['product_name']) . ' (' . htmlspecialchars($transaksi['quantity']) . ' unit)';
                                                } else {
                                                    echo '-';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($transaksi['amount'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Belum ada transaksi tercatat.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                   <!-- Kontrol Navigasi Halaman dan Limit -->
                    <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3">
                        <!-- Dropdown Limit -->
                        <div>
                            <form id="limitForm" method="get">
                                <label for="limitSelect" class="text-sm text-gray-700">Tampilkan:</label>
                                <select name="limit" id="limitSelect" onchange="document.getElementById('limitForm').submit()"
                                    class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                                    <?php foreach ($limit_options as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($limit == $opt) ? 'selected' : ''; ?>>
                                            <?php echo $opt; ?> data
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="page" value="1">
                            </form>
                        </div>

                        <!-- Navigasi Halaman -->
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition">Sebelumnya</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?limit=<?php echo $limit; ?>&page=<?php echo $i; ?>" class="px-3 py-1 rounded <?php echo ($i == $page) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?> transition"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition">Berikutnya</a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

<script>
    // Data PHP yang di-encode ke JSON akan tersedia di sini
    const monthsLabel = <?php echo $monthsLabelJson; ?>;
    const monthlySales = <?php echo $monthlySalesJson; ?>;
    const monthlyExpenses = <?php echo $monthlyExpensesJson; ?>;
    const popularProductNames = <?php echo $popularProductNamesJson; ?>;
    const popularProductQuantities = <?php echo $popularProductQuantitiesJson; ?>;

    // Inisialisasi Chart.js untuk Tren Penjualan & Pengeluaran Bulanan (Line Chart)
    const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(ctxMonthly, {
        type: 'line', // Tipe grafik diubah menjadi 'line'
        data: {
            labels: monthsLabel, // Label sumbu X (bulan)
            datasets: [
                {
                    label: 'Penjualan',
                    data: monthlySales, // Data penjualan
                    backgroundColor: 'rgba(75, 192, 192, 0.2)', // Warna dengan transparansi untuk area di bawah garis
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2, // Ketebalan garis
                    fill: true, // Mengisi area di bawah garis
                    tension: 0.4, // Membuat garis lebih melengkung/halus
                },
                {
                    label: 'Pengeluaran',
                    data: monthlyExpenses, // Data pengeluaran
                    backgroundColor: 'rgba(255, 99, 132, 0.2)', // Warna dengan transparansi untuk area di bawah garis
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2, // Ketebalan garis
                    fill: true, // Mengisi area di bawah garis
                    tension: 0.4, // Membuat garis lebih melengkung/halus
                }
            ]
        },
        options: {
            responsive: true, // Grafik responsif
            maintainAspectRatio: false, // Memungkinkan Anda mengontrol ukuran canvas
            plugins: {
                legend: {
                    position: 'top', // Posisi legend di atas grafik
                    labels: {
                        font: {
                            family: 'Inter', // Sesuaikan font legend
                        }
                    }
                },
                title: {
                    display: false, // Tidak menampilkan judul grafik terpisah
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    },
                    bodyFont: {
                        family: 'Inter', // Sesuaikan font tooltip
                    },
                    titleFont: {
                        family: 'Inter', // Sesuaikan font tooltip
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah (Rp)', // Label sumbu Y
                        font: {
                            family: 'Inter',
                        }
                    },
                    ticks: {
                        callback: function(value, index, values) {
                            return 'Rp ' + value.toLocaleString('id-ID'); // Format mata uang di sumbu Y
                        },
                        font: {
                            family: 'Inter',
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Bulan', // Label sumbu X
                        font: {
                            family: 'Inter',
                        }
                    },
                    ticks: {
                        font: {
                            family: 'Inter',
                        }
                    }
                }
            }
        }
    });

    // Inisialisasi Chart.js untuk Produk Terlaris (Bar Chart)
    const ctxPopular = document.getElementById('popularProductsChart').getContext('2d');
    const popularProductsChart = new Chart(ctxPopular, {
        type: 'bar', // Tipe grafik: batang
        data: {
            labels: popularProductNames, // Label sumbu X (nama produk)
            datasets: [{
                label: 'Jumlah Unit Terjual',
                data: popularProductQuantities, // Data jumlah terjual
                backgroundColor: 'rgba(153, 102, 255, 0.8)', // Warna ungu
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1,
                borderRadius: 5,
            }]
        },
        options: {
            indexAxis: 'y', // Membuat grafik batang horizontal
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            family: 'Inter',
                        }
                    }
                },
                title: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.x !== null) { // Untuk grafik horizontal, nilai ada di x
                                label += new Intl.NumberFormat('id-ID').format(context.parsed.x) + ' unit';
                            }
                            return label;
                        }
                    },
                    bodyFont: {
                        family: 'Inter',
                    },
                    titleFont: {
                        family: 'Inter',
                    }
                }
            },
            scales: {
                x: { // Sumbu X untuk nilai (jumlah)
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Unit Terjual',
                        font: {
                            family: 'Inter',
                        }
                    },
                    ticks: {
                        callback: function(value, index, values) {
                            return value.toLocaleString('id-ID');
                        },
                        font: {
                            family: 'Inter',
                        }
                    }
                },
                y: { // Sumbu Y untuk label (produk)
                    title: {
                        display: true,
                        text: 'Produk',
                        font: {
                            family: 'Inter',
                        }
                    },
                    ticks: {
                        font: {
                            family: 'Inter',
                        }
                    }
                }
            }
        }
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>