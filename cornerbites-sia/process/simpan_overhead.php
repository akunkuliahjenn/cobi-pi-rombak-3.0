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
            $name = trim($_POST['name'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            // Validasi input
            if (empty($name)) {
                throw new Exception("Nama biaya overhead harus diisi!");
            }

            if ($amount <= 0) {
                throw new Exception("Jumlah biaya harus lebih dari 0!");
            }

            if (!empty($overhead_id)) {
                // Mode edit
                // Cek duplikasi nama (kecuali data yang sedang diedit)
                $stmt = $conn->prepare("SELECT COUNT(*) FROM overhead_costs WHERE name = ? AND id != ? AND is_active = 1");
                $stmt->execute([$name, $overhead_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Nama biaya overhead sudah ada!");
                }

                // Update data
                $stmt = $conn->prepare("UPDATE overhead_costs SET name = ?, description = ?, amount = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $description, $amount, $overhead_id]);

                $_SESSION['overhead_message'] = [
                    'text' => 'Biaya overhead berhasil diperbarui!',
                    'type' => 'success'
                ];
            } else {
                // Mode tambah
                // Cek duplikasi nama
                $stmt = $conn->prepare("SELECT COUNT(*) FROM overhead_costs WHERE name = ? AND is_active = 1");
                $stmt->execute([$name]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Nama biaya overhead sudah ada!");
                }

                $stmt = $conn->prepare("INSERT INTO overhead_costs (name, description, amount, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
                $stmt->execute([$name, $description, $amount]);

                $_SESSION['overhead_message'] = [
                    'text' => 'Biaya overhead berhasil ditambahkan!',
                    'type' => 'success'
                ];
            }

        } elseif ($type == 'labor') {
            $labor_id = $_POST['labor_id'] ?? '';
            $position_name = trim($_POST['position_name'] ?? '');
            $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);

            // Validasi input
            if (empty($position_name)) {
                throw new Exception("Nama posisi/jabatan harus diisi!");
            }

            if ($hourly_rate <= 0) {
                throw new Exception("Upah per jam harus lebih dari 0!");
            }

            if (!empty($labor_id)) {
                // Mode edit
                // Cek duplikasi posisi (kecuali data yang sedang diedit)
                $stmt = $conn->prepare("SELECT COUNT(*) FROM labor_costs WHERE position_name = ? AND id != ? AND is_active = 1");
                $stmt->execute([$position_name, $labor_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Posisi/jabatan sudah ada!");
                }

                // Update data
                $stmt = $conn->prepare("UPDATE labor_costs SET position_name = ?, hourly_rate = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$position_name, $hourly_rate, $labor_id]);

                $_SESSION['overhead_message'] = [
                    'text' => 'Data tenaga kerja berhasil diperbarui!',
                    'type' => 'success'
                ];
            } else {
                // Mode tambah
                // Cek duplikasi posisi
                $stmt = $conn->prepare("SELECT COUNT(*) FROM labor_costs WHERE position_name = ? AND is_active = 1");
                $stmt->execute([$position_name]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Posisi/jabatan sudah ada!");
                }

                $stmt = $conn->prepare("INSERT INTO labor_costs (position_name, hourly_rate, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
                $stmt->execute([$position_name, $hourly_rate]);

                $_SESSION['overhead_message'] = [
                    'text' => 'Data tenaga kerja berhasil ditambahkan!',
                    'type' => 'success'
                ];
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
        error_log("Database error in simpan_overhead.php: " . $e->getMessage());
        $_SESSION['overhead_message'] = [
            'text' => 'Terjadi kesalahan sistem. Silakan coba lagi.',
            'type' => 'error'
        ];
    }
}

header('Location: /cornerbites-sia/pages/overhead_management.php?reload=1');
exit;