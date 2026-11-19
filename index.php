<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';

$pdo = getDbConnection();
$crypto = new HybridMountainCrypto($pdo);
$mountains = getMountainCatalog();

$bookingReceipt = null;
$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book_mountain') {
    if (!$crypto->hasPublicKey()) {
        $errorMsg = 'Kunci enkripsi RSA-2048 belum diaktifkan oleh Admin. Silakan buka halaman Setup Kunci terlebih dahulu.';
    } else {
        try {
            $kodeBooking = 'NP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $plainBooking = [
                'kode_booking'  => $kodeBooking,
                'nama_pendaki'  => trim((string) ($_POST['nama_pendaki'] ?? '')),
                'nik'           => trim((string) ($_POST['nik'] ?? '')),
                'telepon'       => trim((string) ($_POST['telepon'] ?? '')),
                'gunung'        => trim((string) ($_POST['gunung'] ?? '')),
                'jalur'         => trim((string) ($_POST['jalur'] ?? '')),
                'tanggal'       => trim((string) ($_POST['tanggal'] ?? '')),
                'jumlah_orang'  => trim((string) ($_POST['jumlah_orang'] ?? '1')) . ' Pendaki',
                'status'        => 'Menunggu',
                'catatan_admin' => 'Booking baru dari Landing Page (Menunggu verifikasi basecamp).',
            ];

            $enc = $crypto->encryptFields($plainBooking);

            $stmt = $pdo->prepare("
                INSERT INTO bookings
                (kode_booking_enc, nama_pendaki_enc, nik_enc, telepon_enc, gunung_enc, jalur_enc, tanggal_enc, jumlah_orang_enc, status_enc, catatan_admin_enc, encrypted_aes_key, aes_iv)
                VALUES (:kode, :nama, :nik, :telp, :gunung, :jalur, :tgl, :jml, :status, :catatan, :aes_key, :iv)
            ");
            $stmt->execute([
                ':kode'    => $enc['fields']['kode_booking'],
                ':nama'    => $enc['fields']['nama_pendaki'],
                ':nik'     => $enc['fields']['nik'],
                ':telp'    => $enc['fields']['telepon'],
                ':gunung'  => $enc['fields']['gunung'],
                ':jalur'   => $enc['fields']['jalur'],
                ':tgl'     => $enc['fields']['tanggal'],
                ':jml'     => $enc['fields']['jumlah_orang'],
                ':status'  => $enc['fields']['status'],
                ':catatan' => $enc['fields']['catatan_admin'],
                ':aes_key' => $enc['encrypted_aes_key'],
                ':iv'      => $enc['aes_iv'],
            ]);

            $bookingReceipt = [
                'kode'            => $kodeBooking,
                'gunung'          => $plainBooking['gunung'],
                'cipher_preview'  => $enc['fields']['nama_pendaki'],
                'rsa_key_preview' => substr($enc['encrypted_aes_key'], 0, 72) . '...',
            ];
        } catch (Throwable $th) {
            $errorMsg = 'Terjadi kesalahan saat mengenkripsi booking: ' . $th->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NusantaraPeak — Reservasi SIMAKSI Gunung Indonesia Terenkripsi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
    <style>
        .hero-mesh {
            background-color: #030712;
            background-image:
                radial-gradient(at 15% 15%, rgba(16, 185, 129, 0.16) 0px, transparent 45%),
                radial-gradient(at 85% 10%, rgba(6, 182, 212, 0.14) 0px, transparent 40%),
                radial-gradient(at 50% 90%, rgba(15, 118, 110, 0.12) 0px, transparent 50%);
        }
        .grid-pattern {
            background-image: linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
    </style>
</head>
<body class="hero-mesh text-slate-100 min-h-screen antialiased selection:bg-emerald-500/30 selection:text-emerald-200 overflow-x-hidden">

    <!-- Navbar Profesional + Mobile Drawer -->
    <header class="sticky top-0 z-50 backdrop-blur-xl bg-slate-950/80 border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 via-teal-500 to-cyan-600 flex items-center justify-center shadow-lg shadow-emerald-500/25 group-hover:scale-105 transition">
                    <svg class="w-6 h-6 text-slate-950" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 19l6-11 4 6 3-4 5 9H3z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-extrabold text-lg tracking-tight block leading-none text-white">Nusantara<span class="text-emerald-400">Peak</span></span>
                    <span class="text-[11px] text-slate-400 font-medium">Official Encrypted SIMAKSI</span>
                </div>
            </a>

            <nav class="hidden lg:flex items-center gap-8 text-sm font-medium text-slate-300">
                <a href="#destinasi" class="hover:text-emerald-400 transition">Destinasi Gunung</a>
                <a href="#booking" class="hover:text-emerald-400 transition">Booking Online</a>
                <a href="#keamanan" class="hover:text-emerald-400 transition">Keamanan Data</a>
            </nav>

            <div class="hidden md:flex items-center gap-3">
                <?php if (!empty($_SESSION['auth_mode'])): ?>
                    <a href="admin.php" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-lg shadow-emerald-500/20">
                        Dashboard Admin
                    </a>
                    <a href="logout.php" onclick="return confirm('Keluar dari sesi admin?')"
                       class="inline-flex items-center gap-1.5 bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/30 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="inline-flex items-center gap-2 bg-white hover:bg-emerald-400 text-slate-950 font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl transition shadow-lg shadow-white/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        Login Admin
                    </a>
                <?php endif; ?>
            </div>

            <!-- Tombol Hamburger Mobile -->
            <button type="button" onclick="toggleMobileNav()" aria-label="Buka Menu"
                    class="md:hidden p-2.5 rounded-xl bg-slate-900 border border-white/10 text-slate-300 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <!-- Menu Mobile Dropdown -->
        <div id="mobile_nav" class="hidden md:hidden border-t border-white/10 bg-slate-950/95 backdrop-blur-xl px-4 py-4 space-y-2">
            <a href="#destinasi" onclick="toggleMobileNav()" class="block px-4 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white">Destinasi Gunung</a>
            <a href="#booking" onclick="toggleMobileNav()" class="block px-4 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white">Booking Online</a>
            <a href="#keamanan" onclick="toggleMobileNav()" class="block px-4 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white">Keamanan Data</a>
            <a href="setup_key.php" class="block px-4 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white">Setup Kunci RSA</a>
            <div class="pt-2 border-t border-white/10 flex flex-col gap-2">
                <?php if (!empty($_SESSION['auth_mode'])): ?>
                    <a href="admin.php" class="w-full text-center bg-emerald-500 text-slate-950 font-bold text-sm py-3 rounded-xl">Dashboard Admin</a>
                    <a href="logout.php" class="w-full text-center bg-rose-500/20 text-rose-300 border border-rose-500/30 font-bold text-sm py-3 rounded-xl">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="w-full text-center bg-emerald-500 text-slate-950 font-bold text-sm py-3 rounded-xl">Login Admin (Key File)</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if (!$crypto->hasPublicKey()): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-amber-400 font-bold">!</span>
                    <p class="text-xs sm:text-sm leading-relaxed"><strong>Inisialisasi Awal Diperlukan:</strong> Kunci publik RSA-2048 belum tersedia di MySQL 8.0. Buat pasangan kunci terlebih dahulu sebelum menerima booking.</p>
                </div>
                <a href="setup_key.php" class="shrink-0 text-center bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                    Setup Kunci RSA →
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="relative grid-pattern border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-20 lg:pt-20 lg:pb-28">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7 space-y-7">
                    <div class="inline-flex items-center gap-2.5 bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs font-semibold px-4 py-1.5 rounded-full">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        PHP <?= PHP_VERSION ?> • Hybrid Cryptography AES-256 & RSA-2048
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1] text-white">
                        Reservasi Pendakian <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">Gunung Nusantara</span> dengan Standar Privasi Tinggi.
                    </h1>

                    <p class="text-slate-400 text-base sm:text-lg max-w-2xl leading-relaxed">
                        Pesan izin pendakian resmi (SIMAKSI) ke puncak-puncak terbaik Indonesia. Identitas sensitif pendaki seperti NIK dan kontak darurat langsung dienkripsi secara <em>end-to-end</em> sebelum menyentuh database.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3.5 pt-1">
                        <a href="#booking" class="inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold px-7 py-4 rounded-2xl text-sm transition shadow-xl shadow-emerald-500/20">
                            Booking SIMAKSI Sekarang
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                        <a href="login.php" class="inline-flex items-center justify-center gap-2 bg-slate-900/90 hover:bg-slate-800 border border-white/15 text-white font-semibold px-7 py-4 rounded-2xl text-sm transition">
                            Portal Admin (File Kunci .PEM)
                        </a>
                    </div>

                    <div class="grid grid-cols-3 gap-4 sm:gap-8 pt-6 border-t border-white/10 max-w-lg">
                        <div>
                            <p class="text-2xl sm:text-3xl font-extrabold text-white"><?= count($mountains) ?></p>
                            <p class="text-[11px] sm:text-xs text-slate-400 mt-0.5">Taman Nasional</p>
                        </div>
                        <div>
                            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-400">256-bit</p>
                            <p class="text-[11px] sm:text-xs text-slate-400 mt-0.5">AES-CBC Simetris</p>
                        </div>
                        <div>
                            <p class="text-2xl sm:text-3xl font-extrabold text-cyan-400">2048-bit</p>
                            <p class="text-[11px] sm:text-xs text-slate-400 mt-0.5">RSA-OAEP Asimetris</p>
                        </div>
                    </div>
                </div>

                <!-- Hero Card Visual -->
                <div class="lg:col-span-5">
                    <div class="relative rounded-3xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-5 sm:p-6 shadow-2xl">
                        <div class="relative rounded-2xl overflow-hidden bg-gradient-to-b from-slate-900 via-teal-950 to-emerald-950 border border-white/10 mb-5">
                            <svg viewBox="0 0 420 220" class="w-full h-48 sm:h-56">
                                <defs>
                                    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#042f2e"/>
                                        <stop offset="100%" stop-color="#020617"/>
                                    </linearGradient>
                                    <linearGradient id="peak1" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#14b8a6" stop-opacity="0.55"/>
                                        <stop offset="100%" stop-color="#042f2e" stop-opacity="0.95"/>
                                    </linearGradient>
                                </defs>
                                <rect width="420" height="220" fill="url(#sky)"/>
                                <circle cx="330" cy="54" r="34" fill="#10b981" opacity="0.14"/>
                                <circle cx="330" cy="54" r="16" fill="#a7f3d0" opacity="0.85"/>
                                <path d="M0 190 L95 88 L175 152 L255 56 L355 162 L420 115 L420 220 L0 220 Z" fill="url(#peak1)"/>
                                <path d="M0 208 L75 130 L145 178 L225 96 L315 182 L420 138 L420 220 L0 220 Z" fill="#064e3b" opacity="0.85"/>
                                <path d="M0 220 L120 155 L210 198 L310 138 L420 192 L420 220 Z" fill="#022c22"/>
                            </svg>
                            <div class="absolute top-3 left-3 right-3 flex items-center justify-between">
                                <span class="text-[10px] font-mono bg-slate-950/75 backdrop-blur px-2.5 py-1 rounded-full text-emerald-300 border border-emerald-500/30">ZERO-PLAINTEXT DB</span>
                                <span class="text-[10px] font-mono bg-slate-950/75 backdrop-blur px-2.5 py-1 rounded-full text-slate-300 border border-white/10">MySQL 8.0</span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-white">Simulasi Alur Enkripsi Hibrida</h3>
                                <span class="text-[11px] font-semibold text-emerald-400">Aktif</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                Setiap transaksi booking dibungkus kunci sesi acak 32-byte (<code class="text-emerald-300">AES-256-CBC</code>), lalu kunci tersebut disegel memakai <code class="text-cyan-300">RSA-2048 Public Key</code>.
                            </p>
                            <div class="bg-slate-950 border border-white/10 rounded-xl p-3.5 font-mono text-[11px] space-y-1.5 overflow-hidden">
                                <div class="flex items-center justify-between text-[10px] text-slate-500">
                                    <span>PAYLOAD SNAPSHOT</span>
                                    <span class="text-emerald-400">ENCRYPTED</span>
                                </div>
                                <p class="text-amber-300/90 truncate">AES: QVfqLU678XXmhiPwNfml2HwwaTIOYb2m==</p>
                                <p class="text-cyan-300/90 truncate">RSA: QF0SF3nKhtrG6onot8IbvmEMWzKy3N8F...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Katalog Gunung + Filter Interaktif -->
    <section id="destinasi" class="py-16 sm:py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-widest text-emerald-400">Eksplorasi Destinasi</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">Gunung Populer di Indonesia</h2>
                <p class="text-sm text-slate-400 max-w-xl">Cek kuota harian, jalur resmi basecamp, dan tarif SIMAKSI sebelum mengisi formulir reservasi.</p>
            </div>

            <!-- Filter & Search Client-Side -->
            <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                <div class="relative flex-1 sm:w-64">
                    <svg class="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="mountain_search" oninput="filterMountains()" placeholder="Cari gunung atau provinsi..."
                           class="w-full bg-slate-900 border border-white/10 focus:border-emerald-500 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 outline-none">
                </div>
                <select id="level_filter" onchange="filterMountains()" class="bg-slate-900 border border-white/10 focus:border-emerald-500 rounded-xl px-4 py-2.5 text-xs text-slate-200 outline-none">
                    <option value="all">Semua Level</option>
                    <option value="Pemula">Pemula</option>
                    <option value="Menengah">Menengah</option>
                    <option value="Sulit">Sulit</option>
                </select>
            </div>
        </div>

        <div id="mountain_grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($mountains as $m): ?>
                <article class="mountain-card group bg-slate-900/75 border border-white/10 hover:border-emerald-500/40 rounded-3xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-emerald-950/50 flex flex-col"
                         data-name="<?= e(strtolower($m['nama'] . ' ' . $m['lokasi'])) ?>"
                         data-level="<?= e($m['level']) ?>">
                    <div class="h-40 bg-gradient-to-br <?= e($m['aksen']) ?> p-5 flex flex-col justify-between relative overflow-hidden">
                        <svg class="absolute -bottom-3 -right-4 w-36 h-36 text-white/10 pointer-events-none" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14 6l-3.75 5 2.85 3.8-1.6 1.2C9.81 13.75 7 10 7 10l-6 8h22L14 6z"/>
                        </svg>
                        <div class="flex justify-between items-start relative z-10 gap-2">
                            <span class="text-[11px] font-semibold bg-slate-950/60 backdrop-blur-md border border-white/10 px-3 py-1 rounded-full text-white"><?= e($m['lokasi']) ?></span>
                            <span class="text-[11px] font-bold bg-white/15 backdrop-blur-md px-2.5 py-1 rounded-full text-white"><?= e($m['level']) ?></span>
                        </div>
                        <div class="relative z-10">
                            <h3 class="text-xl font-extrabold text-white tracking-tight"><?= e($m['nama']) ?></h3>
                            <p class="text-xs text-white/85 font-mono mt-0.5"><?= e($m['ketinggian']) ?></p>
                        </div>
                    </div>

                    <div class="p-6 flex-1 flex flex-col justify-between space-y-5">
                        <p class="text-xs text-slate-400 leading-relaxed"><?= e($m['deskripsi']) ?></p>
                        <div class="space-y-2.5 text-xs bg-slate-950/60 border border-white/5 rounded-2xl p-4">
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500 shrink-0">Jalur Resmi</span>
                                <span class="font-medium text-slate-200 text-right"><?= e(implode(', ', $m['jalur'])) ?></span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500 shrink-0">Kuota Harian</span>
                                <span class="font-medium text-slate-200"><?= e($m['kuota']) ?></span>
                            </div>
                            <div class="flex justify-between gap-3 pt-2.5 border-t border-white/10">
                                <span class="text-slate-400 font-semibold">Tarif SIMAKSI</span>
                                <span class="text-emerald-400 font-extrabold"><?= e($m['harga']) ?></span>
                            </div>
                        </div>

                        <a href="#booking" onclick="selectMountain('<?= e($m['nama']) ?>', '<?= e($m['jalur'][0]) ?>')"
                           class="w-full inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-emerald-500 text-white hover:text-slate-950 font-bold text-xs py-3.5 rounded-xl transition">
                            Pilih & Booking Gunung Ini
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Form Booking Online -->
    <section id="booking" class="py-12 sm:py-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
        <div class="bg-slate-900/90 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl grid grid-cols-1 lg:grid-cols-12">
            <!-- Panel Informasi Samping -->
            <div class="lg:col-span-4 bg-gradient-to-b from-emerald-950/70 via-slate-900 to-slate-950 p-7 sm:p-9 border-b lg:border-b-0 lg:border-r border-white/10 flex flex-col justify-between space-y-6">
                <div class="space-y-4">
                    <span class="inline-block text-[11px] font-bold uppercase tracking-widest text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 rounded-full">
                        Enkripsi Otomatis
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white leading-tight">Formulir Booking SIMAKSI</h2>
                    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                        Data yang Anda masukkan langsung diproses melalui mesin <strong class="text-slate-200">AES-256-CBC</strong> dengan kunci sesi acak dan dikunci menggunakan <strong class="text-slate-200">RSA-2048 Public Key</strong>.
                    </p>
                </div>

                <div class="space-y-3 text-xs text-slate-300">
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold shrink-0">1</span>
                        <p class="text-slate-400">NIK & nomor telepon darurat tersimpan 100% sebagai <em>ciphertext</em> Base64.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-lg bg-cyan-500/20 text-cyan-300 flex items-center justify-center font-bold shrink-0">2</span>
                        <p class="text-slate-400">Hanya Admin pemegang file <code class="text-cyan-300">.pem</code> sah yang dapat mendekripsi pesanan Anda.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-300 flex items-center justify-center font-bold shrink-0">3</span>
                        <p class="text-slate-400">Kode booking dan bukti <em>ciphertext</em> langsung tampil setelah pengiriman berhasil.</p>
                    </div>
                </div>
            </div>

            <!-- Form Input -->
            <div class="lg:col-span-8 p-7 sm:p-9">
                <?php if ($errorMsg): ?>
                    <div class="mb-6 bg-rose-500/10 border border-rose-500/35 text-rose-300 text-xs sm:text-sm px-4 py-3.5 rounded-2xl">
                        <?= e($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($bookingReceipt): ?>
                    <div class="mb-7 bg-emerald-500/10 border border-emerald-500/35 rounded-2xl p-5 space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <h3 class="text-emerald-300 font-extrabold text-sm sm:text-base">Booking Berhasil & Data Terenkripsi!</h3>
                            <span class="font-mono text-xs bg-emerald-500/20 text-emerald-200 px-3 py-1 rounded-full">Kode: <?= e($bookingReceipt['kode']) ?></span>
                        </div>
                        <p class="text-xs text-slate-300">Pesanan untuk <strong><?= e($bookingReceipt['gunung']) ?></strong> berhasil diamankan ke MySQL 8.0.</p>
                        <div class="bg-slate-950 border border-white/10 rounded-xl p-3.5 font-mono text-[11px] space-y-1.5 text-slate-300 break-all">
                            <div><span class="text-amber-400">[AES-256 Nama]:</span> <?= e($bookingReceipt['cipher_preview']) ?></div>
                            <div><span class="text-cyan-400">[RSA-2048 Key]:</span> <?= e($bookingReceipt['rsa_key_preview']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <input type="hidden" name="action" value="book_mountain">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-2">Nama Lengkap Ketua Rombongan</label>
                        <input type="text" name="nama_pendaki" required placeholder="Contoh: Muhammad Ihsanul Fikri"
                               class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-2">NIK / Nomor KTP (Sensitif)</label>
                        <input type="text" name="nik" required placeholder="16 digit NIK"
                               class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl px-4 py-3 text-sm font-mono text-white placeholder-slate-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-2">Nomor WhatsApp / Darurat</label>
                        <input type="text" name="telepon" required placeholder="08xx-xxxx-xxxx"
                               class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-2">Gunung Tujuan</label>
                        <select name="gunung" id="input_gunung" required
                                class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 rounded-xl px-4 py-3 text-sm text-white outline-none transition">
                            <?php foreach ($mountains as $m): ?>
                                <option value="<?= e($m['nama']) ?>"><?= e($m['nama']) ?> (<?= e($m['lokasi']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-2">Jalur Pendakian (Basecamp)</label>
                        <input type="text" name="jalur" id="input_jalur" required value="Cibodas" placeholder="Cibodas / Sembalun / Selo"
                               class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 rounded-xl px-4 py-3 text-sm text-white outline-none transition">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-2">Tanggal Naik</label>
                            <input type="date" name="tanggal" required value="<?= date('Y-m-d', strtotime('+3 days')) ?>"
                                   class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 rounded-xl px-3 py-3 text-sm text-white outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-2">Jumlah</label>
                            <input type="number" name="jumlah_orang" min="1" max="15" value="2" required
                                   class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 rounded-xl px-3 py-3 text-sm text-white outline-none transition">
                        </div>
                    </div>
                    <div class="sm:col-span-2 pt-2">
                        <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-extrabold py-4 rounded-2xl text-sm transition shadow-lg shadow-emerald-500/20 cursor-pointer">
                            Enkripsi Data & Kirim Reservasi SIMAKSI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Seksi Arsitektur Keamanan -->
    <section id="keamanan" class="py-12 sm:py-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-slate-900/70 border border-white/10 rounded-3xl p-6 space-y-2.5">
                <span class="inline-block text-[11px] font-mono font-bold text-amber-400 bg-amber-500/10 px-3 py-1 rounded-full">01 • SIMETRIS</span>
                <h3 class="font-extrabold text-base text-white">AES-256-CBC Unik Per Baris</h3>
                <p class="text-xs text-slate-400 leading-relaxed">Setiap transaksi booking dan catatan operasional dienkripsi menggunakan kunci sesi acak 32-byte dan Initialization Vector 16-byte tersendiri.</p>
            </div>
            <div class="bg-slate-900/70 border border-white/10 rounded-3xl p-6 space-y-2.5">
                <span class="inline-block text-[11px] font-mono font-bold text-cyan-400 bg-cyan-500/10 px-3 py-1 rounded-full">02 • ASIMETRIS</span>
                <h3 class="font-extrabold text-base text-white">RSA-2048 OAEP Padding</h3>
                <p class="text-xs text-slate-400 leading-relaxed">Kunci sesi AES dibungkus dengan Public Key RSA-2048 server. Private Key tidak disimpan di MySQL sehingga pencurian database tidak membocorkan plaintext.</p>
            </div>
            <div class="bg-slate-900/70 border border-white/10 rounded-3xl p-6 space-y-2.5">
                <span class="inline-block text-[11px] font-mono font-bold text-emerald-400 bg-emerald-500/10 px-3 py-1 rounded-full">03 • PASSWORDLESS</span>
                <h3 class="font-extrabold text-base text-white">Login File Kunci & Fallback</h3>
                <p class="text-xs text-slate-400 leading-relaxed">Tanpa username/password. Jika login memakai file kunci salah, dashboard otomatis terkunci dalam tampilan ciphertext penuh tanpa membocorkan satu pun data asli.</p>
            </div>
        </div>
    </section>

    <footer class="border-t border-white/10 py-8 px-4 sm:px-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
            <p>© <?= date('Y') ?> <strong class="text-slate-300">NusantaraPeak</strong> • UTS Keamanan Data dan Informasi</p>
            <div class="flex items-center gap-4">
                <span class="font-mono text-slate-400">PHP <?= PHP_VERSION ?> & MySQL 8.0</span>
                <a href="setup_key.php" class="text-emerald-400 hover:underline font-semibold">Setup Kunci RSA</a>
            </div>
        </div>
    </footer>

    <script>
        function toggleMobileNav() {
            document.getElementById('mobile_nav').classList.toggle('hidden');
        }
        function selectMountain(nama, jalur) {
            document.getElementById('input_gunung').value = nama;
            document.getElementById('input_jalur').value = jalur;
        }
        function filterMountains() {
            const q = document.getElementById('mountain_search').value.toLowerCase().trim();
            const lvl = document.getElementById('level_filter').value;
            document.querySelectorAll('.mountain-card').forEach(card => {
                const matchName = card.dataset.name.includes(q);
                const matchLvl = (lvl === 'all') || (card.dataset.level === lvl);
                card.classList.toggle('hidden', !(matchName && matchLvl));
            });
        }
    </script>
</body>
</html>