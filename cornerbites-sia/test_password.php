    <?php
    // test_password.php
    // HAPUS FILE INI SETELAH SELESAI PENGUJIAN!

    echo "<h1>Pengujian Password Hash</h1>";

    // Password yang ingin Anda gunakan (misal: "admin123")
    $plainPassword = "admin123"; // GANTI DENGAN PASSWORD YANG INGIN ANDA GUNAKAN UNTUK ADMIN

    echo "Password Asli: <strong>" . htmlspecialchars($plainPassword) . "</strong><br><br>";

    // Langkah 1: Generate hash baru
    $newHashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    echo "Hash Baru yang Dihasilkan: <strong>" . htmlspecialchars($newHashedPassword) . "</strong><br>";
    echo "<p>Salin hash di atas dan perbarui kolom `password` untuk user 'admin' di tabel `users` phpMyAdmin Anda.</p>";
    echo "<p>Contoh SQL UPDATE (Ganti HASH_BARU_ANDA):<br><code>UPDATE users SET password = '" . htmlspecialchars($newHashedPassword) . "' WHERE username = 'admin';</code></p>";

    echo "<hr>";

    // Langkah 2: Uji password_verify() dengan hash yang ada di database Anda
    // Ambil HASH YANG SAAT INI ADA DI DATABASE ANDA UNTUK USER ADMIN
    // Salin nilai dari kolom `password` di phpMyAdmin Anda untuk user 'admin'.
    $hashedPasswordFromDB = '$2y$10$wTf2zD5fL.0123456789abcdefghijklmnopqrstuvw'; // <-- GANTI DENGAN HASH DARI DATABASE ANDA
    $testPasswordToVerify = "admin123"; // GANTI DENGAN PASSWORD ASLI YANG ANDA COBA LOGIN

    echo "Password yang Dicoba: <strong>" . htmlspecialchars($testPasswordToVerify) . "</strong><br>";
    echo "Hash dari Database: <strong>" . htmlspecialchars($hashedPasswordFromDB) . "</strong><br><br>";

    if (password_verify($testPasswordToVerify, $hashedPasswordFromDB)) {
        echo "<p style='color: green; font-weight: bold;'>&#10004; password_verify() BERHASIL! Password yang dicoba cocok dengan hash dari database.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>&#10006; password_verify() GAGAL! Password yang dicoba TIDAK cocok dengan hash dari database.</p>";
    }

    echo "<hr>";

    // Langkah 3: Uji koneksi database dan ambil hash secara dinamis
    echo "<h3>Uji Koneksi Database dan Verifikasi Langsung</h3>";
    require_once __DIR__ . '/config/db.php'; // Sesuaikan path ini jika Anda menempatkan file test_password.php di luar root SIA

    try {
        $conn = $db;
        $usernameToTest = 'admin'; // GANTI DENGAN USERNAME ADMIN ANDA
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$usernameToTest]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo "<p>User '$usernameToTest' ditemukan di database.</p>";
            echo "Hash di DB untuk '$usernameToTest': <strong>" . htmlspecialchars($user['password']) . "</strong><br>";

            if (password_verify($plainPassword, $user['password'])) { // Menggunakan $plainPassword dari awal script
                echo "<p style='color: green; font-weight: bold;'>&#10004; Verifikasi langsung dari database BERHASIL!</p>";
                echo "<p>Ini berarti login_process.php seharusnya berfungsi jika password: <strong>" . htmlspecialchars($plainPassword) . "</strong> dimasukkan.</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>&#10006; Verifikasi langsung dari database GAGAL! Pastikan Anda mengetik password asli dengan benar.</p>";
            }
        } else {
            echo "<p style='color: red; font-weight: bold;'>&#10006; User '$usernameToTest' TIDAK DITEMUKAN di database. Pastikan username sudah benar.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red; font-weight: bold;'>&#10006; Error koneksi database saat pengujian: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
    