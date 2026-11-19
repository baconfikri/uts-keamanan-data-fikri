<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';

$pdo = getDbConnection();
$crypto = new HybridMountainCrypto($pdo);

if (
    ($_SESSION['auth_mode'] ?? '') === 'valid' &&
    !empty($_SESSION['admin_private_key']) &&
    $crypto->verifyAdminPrivateKey((string) $_SESSION['admin_private_key'])
) {
    header('Location: admin.php');
    exit;
}

$warningMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileName = 'TIDAK_ADA_FILE';
    $keyContent = '';

    if (isset($_FILES['key_file']) && $_FILES['key_file']['error'] === UPLOAD_ERR_OK) {
        $fileName = (string) $_FILES['key_file']['name'];
        $keyContent = (string) file_get_contents((string) $_FILES['key_file']['tmp_name']);
    } elseif (!empty($_POST['key_text'])) {
        $fileName = 'INPUT_MANUAL_PEM';
        $keyContent = trim((string) $_POST['key_text']);
    }

    if (!$crypto->hasPublicKey()) {
        $warningMsg = 'Public Key RSA-2048 belum diinisialisasi di MySQL. Jalankan halaman Setup Kunci terlebih dahulu.';
    } elseif ($keyContent === '') {
        $warningMsg = 'Silakan unggah file kunci kriptografi (.pem / file lain) atau tempel teks kunci terlebih dahulu.';
    } else {
        session_regenerate_id(true);
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        $_SESSION['uploaded_key_name'] = $fileName;
        $_SESSION['key_fingerprint'] = strtoupper(substr(hash('sha256', $keyContent), 0, 24));

        if ($crypto->verifyAdminPrivateKey($keyContent)) {
            $_SESSION['auth_mode'] = 'valid';
            $_SESSION['admin_private_key'] = $keyContent;
            unset($_SESSION['failed_crypto_meta']);
        } else {
            $_SESSION['auth_mode'] = 'invalid';
            unset($_SESSION['admin_private_key']);
            $_SESSION['failed_crypto_meta'] = $crypto->buildFailedLoginEncryption(
                $fileName,
                $keyContent,
                'RSA_2048_PRIVATE_KEY_MISMATCH'
            );
        }

        header('Location: admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kriptografi Admin — NusantaraPeak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex items-center justify-center p-4 sm:p-6">
    <div class="max-w-5xl w-full bg-slate-900/90 border border-white/10 rounded-3xl overflow-hidden shadow-2xl grid grid-cols-1 lg:grid-cols-12">

        <!-- Kolom Kiri: Branding & Informasi Keamanan -->
        <div class="lg:col-span-5 bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950 p-8 sm:p-10 border-b lg:border-b-0 lg:border-r border-white/10 flex flex-col justify-between gap-8">
            <div class="space-y-6">
                <a href="index.php" class="inline-flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center text-slate-950">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 19l6-11 4 6 3-4 5 9H3z"/>
                        </svg>
                    </div>
                    <span class="font-extrabold text-lg text-white">Nusantara<span class="text-emerald-400">Peak</span></span>
                </a>

                <div class="space-y-2">
                    <span class="inline-block text-[11px] font-mono font-semibold text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 rounded-full">
                        PASSWORDLESS RSA-2048
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white leading-tight pt-1">
                        Autentikasi Tanpa Username & Password
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                        Sistem memverifikasi identitas admin menggunakan uji <em>Challenge-Response</em> RSA-2048 OAEP terhadap file kunci privat yang Anda unggah.
                    </p>
                </div>
            </div>

            <div class="space-y-3 bg-slate-950/70 border border-white/10 rounded-2xl p-4 text-xs">
                <div class="flex items-start gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 mt-1.5 shrink-0"></span>
                    <p class="text-slate-300"><strong class="text-emerald-400">Kunci Benar:</strong> Dashboard terbuka penuh dan seluruh konten terdekripsi secara real-time.</p>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-rose-400 mt-1.5 shrink-0"></span>
                    <p class="text-slate-300"><strong class="text-rose-400">Kunci Salah:</strong> Dashboard tetap masuk namun seluruh data terkunci dalam wujud <em>ciphertext</em>.</p>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Form Upload Kunci -->
        <div class="lg:col-span-7 p-7 sm:p-10 flex flex-col justify-between space-y-6">
            <div>
                <div class="flex items-center justify-between gap-4 mb-6">
                    <a href="index.php" class="text-xs font-semibold text-slate-400 hover:text-emerald-400 transition">← Kembali ke Beranda</a>
                    <a href="setup_key.php" class="text-xs font-semibold text-cyan-400 hover:underline">Setup / Unduh Kunci</a>
                </div>

                <?php if (($_GET['status'] ?? '') === 'logged_out'): ?>
                    <div class="mb-5 bg-emerald-500/10 border border-emerald-500/35 text-emerald-300 text-xs px-4 py-3 rounded-2xl flex items-center gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                        <span>Berhasil <strong>Logout</strong>. Sesi kunci privat telah dibersihkan dari memori server.</span>
                    </div>
                <?php endif; ?>

                <?php if ($warningMsg !== null): ?>
                    <div class="mb-5 bg-amber-500/10 border border-amber-500/35 text-amber-200 text-xs px-4 py-3 rounded-2xl">
                        <?= e($warningMsg) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <label for="key_file" id="drop_zone"
                           class="block border-2 border-dashed border-slate-700 hover:border-emerald-500 bg-slate-950/70 rounded-2xl p-7 sm:p-9 text-center cursor-pointer transition group">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center mb-3.5 group-hover:scale-105 transition">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-white" id="file_label">Tarik & lepas file kunci (.pem) ke sini atau klik untuk memilih</p>
                        <p class="text-xs text-slate-500 mt-1.5">Mendukung <code class="text-slate-300">admin_private_key.pem</code> atau <code class="text-slate-300">kunci_salah_demo.pem</code></p>
                        <input type="file" name="key_file" id="key_file" class="hidden" onchange="updateFileName(this)">
                    </label>

                    <details class="bg-slate-950/60 border border-white/10 rounded-2xl p-4">
                        <summary class="text-xs font-semibold text-slate-400 cursor-pointer hover:text-slate-200">
                            Alternatif: Tempel langsung blok teks PEM
                        </summary>
                        <textarea name="key_text" rows="4" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
                                  class="w-full mt-3 bg-slate-900 border border-slate-700 rounded-xl p-3 font-mono text-xs text-emerald-300 outline-none focus:border-emerald-500"></textarea>
                    </details>

                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-extrabold py-4 rounded-2xl text-sm transition shadow-lg shadow-emerald-500/20 cursor-pointer">
                        Verifikasi & Masuk Dashboard Admin
                    </button>
                </form>
            </div>

            <p class="text-[11px] text-center text-slate-500 pt-2 border-t border-white/5">
                Dilindungi OpenSSL Hybrid Engine • PHP <?= PHP_VERSION ?>
            </p>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('drop_zone');
        const fileInput = document.getElementById('key_file');

        function updateFileName(input) {
            if (input.files && input.files.length > 0) {
                document.getElementById('file_label').textContent = 'File dipilih: ' + input.files[0].name;
                dropZone.classList.add('border-emerald-500', 'bg-emerald-500/5');
            }
        }

        ['dragenter', 'dragover'].forEach(evt => {
            dropZone.addEventListener(evt, e => {
                e.preventDefault();
                dropZone.classList.add('border-emerald-400', 'bg-emerald-500/10');
            });
        });

        ['dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, e => {
                e.preventDefault();
                dropZone.classList.remove('border-emerald-400', 'bg-emerald-500/10');
            });
        });

        dropZone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileName(fileInput);
            }
        });
    </script>
</body>
</html>