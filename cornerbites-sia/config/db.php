<?php
// config/db.php

/**
 * Class Database untuk mengelola koneksi ke database MySQL menggunakan PDO.
 */
class Database {
    // Kredensial database - GANTI DENGAN KREDENSIAL ANDA YANG SEBENARNYA
    private $host = 'localhost';
    private $db_name = 'corner_bites_sia'; // Pastikan nama database ini sudah Anda buat
    private $username = 'root';
    private $password = ''; // Kosongkan jika tidak ada password, atau isi password Anda
    private $conn; // Variabel untuk menyimpan objek koneksi PDO

    /**
     * Mendapatkan koneksi database PDO.
     * Mengatur mode error ke exception dan mode fetch default ke asosiatif.
     *
     * @return PDO|null Objek PDO jika koneksi berhasil, null jika gagal.
     */
    public function connect() {
        $this->conn = null; // Reset koneksi sebelumnya

        try {
            // Data Source Name (DSN) dengan charset
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8";
            
            // Buat objek PDO
            $this->conn = new PDO($dsn, $this->username, $this->password);

            // Atur atribut PDO untuk penanganan error dan mode fetch
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Perintah set names utf8 tidak lagi mutlak diperlukan jika charset=utf8 sudah di DSN,
            // tetapi tidak ada salahnya jika dibiarkan.
            // $this->conn->exec("set names utf8");

        } catch(PDOException $e) {
            // Tangani error koneksi database
            error_log("Koneksi database gagal: " . $e->getMessage()); // Catat error ke log
            die("Koneksi database gagal: " . $e->getMessage()); // Hentikan skrip jika koneksi sangat krusial
        }

        return $this->conn; // Kembalikan objek koneksi
    }
}

// Inisialisasi koneksi database
$database = new Database();
$db = $database->connect();

// PENTING: Baris session_start() telah dihapus dari sini.
// session_start() hanya akan dipanggil di includes/auth_check.php.
?>