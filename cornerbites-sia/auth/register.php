<?php
// auth/register.php
// Halaman registrasi pengguna baru dengan perbaikan pada sisi frontend dan backend.

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
$error_message = $_SESSION['error_message_register'] ?? '';
$success_message = $_SESSION['success_message_register'] ?? '';
unset($_SESSION['error_message_register'], $_SESSION['success_message_register']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - Corner Bites App</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Inter dari Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #ff7e5f 0%, #feb47b 50%, #ff6b6b 100%); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .shape { position: absolute; background: rgba(255, 255, 255, 0.1); border-radius: 50%; animation: float 25s infinite linear; }
        .shape:nth-child(1) { width: 100px; height: 100px; left: 15%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 150px; height: 150px; left: 75%; animation-delay: 7s; }
        .shape:nth-child(3) { width: 80px; height: 80px; left: 60%; animation-delay: 14s; }
        .shape:nth-child(4) { width: 120px; height: 120px; left: 30%; animation-delay: 21s; }
        @keyframes float { 0% { transform: translateY(100vh) rotate(0deg); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { transform: translateY(-100px) rotate(360deg); opacity: 0; } }
        .fade-in { animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .password-strength-bar { height: 4px; border-radius: 2px; transition: all 0.3s ease; }
        .strength-weak { background-color: #ef4444; width: 25%; }
        .strength-fair { background-color: #f59e0b; width: 50%; }
        .strength-good { background-color: #10b981; width: 75%; }
        .strength-strong { background-color: #059669; width: 100%; }
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
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Corner Bites App</h1>
            <p class="text-white/80 text-sm">Aplikasi Kasir dan Analisis Bisnis UMKM</p>
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

        <!-- Form Registrasi -->
        <form action="/cornerbites-sia/process/register_process.php" method="POST" class="space-y-6">
            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-medium text-white/90 mb-2">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <input type="text" id="username" name="username" class="w-full pl-10 pr-4 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white/30" placeholder="Pilih username unik" required>
                </div>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-white/90 mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <input type="password" id="password" name="password" class="w-full pl-10 pr-12 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white/30" placeholder="Buat password yang kuat" required oninput="validateForm()">
                    <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <svg id="eye-icon-password" class="h-5 w-5 text-gray-400 hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    </button>
                </div>
                <div class="mt-2">
                    <div class="bg-white/20 rounded-full h-1"><div id="password-strength-bar" class="password-strength-bar"></div></div>
                    <p id="password-strength-text" class="text-xs text-white/60 mt-1">Minimal 6 karakter</p>
                </div>
            </div>

            <!-- Konfirmasi Password -->
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-white/90 mb-2">Konfirmasi Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full pl-10 pr-12 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white/30" placeholder="Ulangi password Anda" required oninput="validateForm()">
                </div>
                <p id="password-match-text" class="text-xs text-white/60 mt-1"></p>
            </div>
            
            <!-- [DIHAPUS] Bagian Syarat & Ketentuan sudah tidak ada -->

            <!-- Tombol Daftar -->
            <div class="pt-4"> <!-- Memberi sedikit jarak atas setelah penghapusan checkbox -->
                <button type="submit" id="submitBtn" class="w-full py-3 px-6 text-white font-semibold rounded-xl shadow-lg transition duration-300 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 focus:outline-none focus:ring-4 focus:ring-orange-300/50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Buat Akun Baru
                </button>
            </div>
        </form>

        <!-- Link Login -->
        <div class="text-center mt-8 pt-6 border-t border-white/20">
            <p class="text-white/70 text-sm">
                Sudah punya akun? 
                <a href="/cornerbites-sia/auth/login.php" class="text-white font-semibold hover:underline ml-1">Masuk di sini</a>
            </p>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');

        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');
        const matchText = document.getElementById('password-match-text');
        
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const icon = document.getElementById(`eye-icon-${fieldId}`);
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>`;
            } else {
                input.type = 'password';
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>`;
            }
        }
        
        function checkPasswordStrength(password) {
            let score = 0;
            if (password.length >= 6) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^a-zA-Z0-9]/)) score++;

            strengthBar.className = 'password-strength-bar';
            switch(score) {
                case 0: case 1: strengthText.textContent = 'Password sangat lemah'; break;
                case 2: strengthBar.classList.add('strength-weak'); strengthText.textContent = 'Password lemah'; break;
                case 3: strengthBar.classList.add('strength-fair'); strengthText.textContent = 'Password cukup'; break;
                case 4: strengthBar.classList.add('strength-good'); strengthText.textContent = 'Password baik'; break;
                case 5: strengthBar.classList.add('strength-strong'); strengthText.textContent = 'Password sangat kuat'; break;
            }
            return password.length >= 6;
        }

        function checkPasswordMatch(password, confirmPassword) {
            if (confirmPassword === '') {
                matchText.textContent = '';
                return false;
            }
            if (password === confirmPassword) {
                matchText.textContent = '✓ Password cocok';
                matchText.className = 'text-xs text-green-300 mt-1';
                return true;
            } else {
                matchText.textContent = '✗ Password tidak cocok';
                matchText.className = 'text-xs text-red-300 mt-1';
                return false;
            }
        }

        function validateForm() {
            const pass = passwordInput.value;
            const confirmPass = confirmPasswordInput.value;
            const isStrengthOk = checkPasswordStrength(pass);
            const isMatchOk = checkPasswordMatch(pass, confirmPass);
            
            // [UBAH] Tombol submit aktif jika password kuat DAN cocok, tanpa cek checkbox.
            if (isStrengthOk && isMatchOk) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        document.addEventListener('DOMContentLoaded', validateForm);
    </script>
</body>
</html>
