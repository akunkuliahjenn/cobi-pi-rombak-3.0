<?php
// includes/header.php
// File ini berisi bagian pembuka HTML, link CSS, dan konfigurasi font.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Akuntansi UMKM - Corner Bites</title>
    <!-- Tailwind CSS via CDN untuk development -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom CSS for theming -->
    <link rel="stylesheet" href="/cornerbites-sia/assets/css/style.css">
    <title><?php echo $page_title ?? 'Corner Bites SIA'; ?></title>

    <!-- Font Inter dari Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mengatur font global untuk body */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Styling tambahan untuk ikon di sidebar */
        .sidebar-icon {
            width: 1.25rem; /* 20px */
            height: 1.25rem; /* 20px */
            margin-right: 0.75rem; /* 12px */
            stroke-width: 2; /* Ketebalan garis SVG */
        }
    </style>
</head>
<body class="bg-gray-100 antialiased">
    <!-- Theme Management Script -->
    <script src="/cornerbites-sia/assets/js/theme.js"></script>
    <!-- Konten body akan dilanjutkan di file lain (sidebar, main content) -->