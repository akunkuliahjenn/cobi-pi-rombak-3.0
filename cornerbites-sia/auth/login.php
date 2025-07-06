<?php
// auth/login.php
// Halaman login pengguna yang telah disederhanakan dan dirapikan.

// Memulai sesi jika belum ada.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika pengguna sudah login, alihkan ke dashboard yang sesuai.
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'user';
    $dashboard_path = ($role === 'admin') ? '/cornerbites-sia/admin/dashboard.php' : '/cornerbites-sia/pages/dashboard.php';
    header("Location: " . $dashboard_path);
    exit();
}

// Mengambil pesan flash dari sesi (jika ada).
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

$success_message = $_SESSION['success_message_register'] ?? '';
unset($_SESSION['success_message_register']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Corner Bites App</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Inter dari Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #805ad5 100%); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .shape { position: absolute; background: rgba(255, 255, 255, 0.1); border-radius: 50%; animation: float 20s infinite linear; }
        .shape:nth-child(1) { width: 80px; height: 80px; left: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 120px; height: 120px; left: 80%; animation-delay: 5s; }
        .shape:nth-child(3) { width: 60px; height: 60px; left: 70%; animation-delay: 10s; }
        .shape:nth-child(4) { width: 100px; height: 100px; left: 20%; animation-delay: 15s; }
        @keyframes float { 0% { transform: translateY(100vh) rotate(0deg); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { transform: translateY(-100px) rotate(360deg); opacity: 0; } }
        .fade-in { animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Floating Background Shapes -->
    <div class="floating-shapes absolute inset-0 z-0">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="bg-white/20 backdrop-blur-lg border border-white/30 rounded-3xl shadow-2xl w-full max-w-md p-8 relative z-10 fade-in">
        <!-- Logo/Brand Section -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-2xl mb-4">
                <!-- [UBAH] Ikon diubah menjadi ikon kasir -->
                <svg class="w-8 h-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h12A2.25 2.25 0 0020.25 14.25V3M3.75 3H20.25M3.75 3v.375c0 .621.504 1.125 1.125 1.125h15c.621 0 1.125-.504 1.125-1.125V3M3.75 6.75h16.5M3.75 10.5h16.5M7.5 14.25h9M10.5 18a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zM13.5 18a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Corner Bites App</h1>
            <p class="text-white/80 text-sm">Aplikasi Kasir dan Analisis Bisnis UMKM</p>
        </div>
        
        <!-- Pesan Selamat Datang -->
        <div class="text-center mb-8">
            <h2 class="text-2xl font-semibold text-white mb-2">Selamat Datang Kembali!</h2>
            <p class="text-white/70 text-sm">Masuk ke akun Anda untuk melanjutkan.</p>
        </div>

        <!-- Pesan Sukses atau Error -->
        <?php if ($success_message): ?>
            <div class="bg-green-500/30 border border-green-400/50 text-green-100 px-4 py-3 rounded-xl mb-6 backdrop-blur-sm" role="alert">
                <span class="font-medium"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="bg-red-500/30 border border-red-400/50 text-red-100 px-4 py-3 rounded-xl mb-6 backdrop-blur-sm" role="alert">
                <span class="font-medium"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form action="/cornerbites-sia/process/login_process.php" method="POST" class="space-y-6">
            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-medium text-white/90 mb-2">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <input type="text" id="username" name="username" class="w-full pl-10 pr-4 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30" placeholder="Masukkan username Anda" required>
                </div>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-white/90 mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <input type="password" id="password" name="password" class="w-full pl-10 pr-12 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30" placeholder="Masukkan password Anda" required>
                    <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <svg id="eye-icon" class="h-5 w-5 text-gray-400 hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    </button>
                </div>
            </div>
            
            <!-- [DIHAPUS] Bagian "Ingat Saya" dan "Lupa Password" -->

            <!-- Tombol Login -->
             <div class="pt-4">
                <button type="submit" class="w-full py-3 px-6 text-white font-semibold rounded-xl shadow-lg transition duration-300 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-300/50">
                    Masuk ke Akun
                </button>
            </div>
        </form>

        <!-- Link Registrasi -->
        <div class="text-center mt-8 pt-6 border-t border-white/20">
            <p class="text-white/70 text-sm">
                Belum punya akun? 
                <a href="/cornerbites-sia/auth/register.php" class="text-white font-semibold hover:underline ml-1">Daftar sekarang</a>
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>`;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>`;
            }
        }
    </script>
</body>
</html>
