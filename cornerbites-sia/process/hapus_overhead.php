
<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = $db;
        $type = $_POST['type'] ?? '';

        if ($type == 'overhead') {
            $overhead_id = $_POST['overhead_id'] ?? '';
            
            if (empty($overhead_id)) {
                throw new Exception("ID overhead tidak valid!");
            }

            // Soft delete - set is_active = 0
            $stmt = $conn->prepare("UPDATE overhead_costs SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$overhead_id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['overhead_message'] = [
                    'text' => 'Biaya overhead berhasil dihapus!',
                    'type' => 'success'
                ];
            } else {
                throw new Exception("Data overhead tidak ditemukan!");
            }

        } elseif ($type == 'labor') {
            $labor_id = $_POST['labor_id'] ?? '';
            
            if (empty($labor_id)) {
                throw new Exception("ID tenaga kerja tidak valid!");
            }

            // Soft delete - set is_active = 0
            $stmt = $conn->prepare("UPDATE labor_costs SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$labor_id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['overhead_message'] = [
                    'text' => 'Data tenaga kerja berhasil dihapus!',
                    'type' => 'success'
                ];
            } else {
                throw new Exception("Data tenaga kerja tidak ditemukan!");
            }

        } else {
            throw new Exception("Tipe data tidak valid!");
        }

    } catch (Exception $e) {
        $_SESSION['overhead_message'] = [
            'text' => $e->getMessage(),
            'type' => 'error'
        ];
    } catch (PDOException $e) {
        error_log("Database error in hapus_overhead.php: " . $e->getMessage());
        $_SESSION['overhead_message'] = [
            'text' => 'Terjadi kesalahan sistem. Silakan coba lagi.',
            'type' => 'error'
        ];
    }
}

header('Location: /cornerbites-sia/pages/overhead_management.php');
exit;
?>
