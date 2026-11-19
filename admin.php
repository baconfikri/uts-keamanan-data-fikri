<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';

$pdo = getDbConnection();
$crypto = new HybridMountainCrypto($pdo);

$authMode = (string) ($_SESSION['auth_mode'] ?? '');
if ($authMode !== 'valid' && $authMode !== 'invalid') {
    header('Location: login.php');
    exit;
}

$adminPrivateKey = (string) ($_SESSION['admin_private_key'] ?? '');
$isValidKey = ($authMode === 'valid') && $adminPrivateKey !== '' && $crypto->verifyAdminPrivateKey($adminPrivateKey);
$failedMeta = $_SESSION['failed_crypto_meta'] ?? null;

$mountains = getMountainCatalog();
$flashMsg = null;

if ($isValidKey && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_booking') {
        $kode = 'NP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $enc = $crypto->encryptFields([
            'kode_booking'  => $kode,
            'nama_pendaki'  => trim((string) ($_POST['nama_pendaki'] ?? '')),
            'nik'           => trim((string) ($_POST['nik'] ?? '')),
            'telepon'       => trim((string) ($_POST['telepon'] ?? '')),
            'gunung'        => trim((string) ($_POST['gunung'] ?? '')),
            'jalur'         => trim((string) ($_POST['jalur'] ?? '')),
            'tanggal'       => trim((string) ($_POST['tanggal'] ?? '')),
            'jumlah_orang'  => trim((string) ($_POST['jumlah_orang'] ?? '1')) . ' Pendaki',
            'status'        => trim((string) ($_POST['status'] ?? 'Terverifikasi')),
            'catatan_admin' => trim((string) ($_POST['catatan_admin'] ?? 'Diinput langsung oleh Admin.')),
        ]);

        $pdo->prepare("
            INSERT INTO bookings
            (kode_booking_enc, nama_pendaki_enc, nik_enc, telepon_enc, gunung_enc, jalur_enc, tanggal_enc, jumlah_orang_enc, status_enc, catatan_admin_enc, encrypted_aes_key, aes_iv)
            VALUES (:kode, :nama, :nik, :telp, :gunung, :jalur, :tgl, :jml, :status, :catatan, :aes_key, :iv)
        ")->execute([
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
        $flashMsg = 'Booking baru berhasil dienkripsi (AES-256-CBC + RSA-2048) dan disimpan ke MySQL 8.0.';
    }

    if ($action === 'update_booking_status') {
        $id = (int) ($_POST['booking_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            $cols = ['kode_booking_enc', 'nama_pendaki_enc', 'nik_enc', 'telepon_enc', 'gunung_enc', 'jalur_enc', 'tanggal_enc', 'jumlah_orang_enc', 'status_enc', 'catatan_admin_enc'];
            $dec = $crypto->decryptFields($row, $cols, $adminPrivateKey);

            $reEnc = $crypto->encryptFields([
                'kode_booking'  => $dec['kode_booking_enc'],
                'nama_pendaki'  => $dec['nama_pendaki_enc'],
                'nik'           => $dec['nik_enc'],
                'telepon'       => $dec['telepon_enc'],
                'gunung'        => $dec['gunung_enc'],
                'jalur'         => $dec['jalur_enc'],
                'tanggal'       => $dec['tanggal_enc'],
                'jumlah_orang'  => $dec['jumlah_orang_enc'],
                'status'        => trim((string) ($_POST['status_baru'] ?? 'Terverifikasi')),
                'catatan_admin' => trim((string) ($_POST['catatan_baru'] ?? $dec['catatan_admin_enc'])),
            ]);

            $pdo->prepare("
                UPDATE bookings SET
                    kode_booking_enc = :kode, nama_pendaki_enc = :nama, nik_enc = :nik, telepon_enc = :telp,
                    gunung_enc = :gunung, jalur_enc = :jalur, tanggal_enc = :tgl, jumlah_orang_enc = :jml,
                    status_enc = :status, catatan_admin_enc = :catatan, encrypted_aes_key = :aes_key, aes_iv = :iv
                WHERE id = :id
            ")->execute([
                ':kode'    => $reEnc['fields']['kode_booking'],
                ':nama'    => $reEnc['fields']['nama_pendaki'],
                ':nik'     => $reEnc['fields']['nik'],
                ':telp'    => $reEnc['fields']['telepon'],
                ':gunung'  => $reEnc['fields']['gunung'],
                ':jalur'   => $reEnc['fields']['jalur'],
                ':tgl'     => $reEnc['fields']['tanggal'],
                ':jml'     => $reEnc['fields']['jumlah_orang'],
                ':status'  => $reEnc['fields']['status'],
                ':catatan' => $reEnc['fields']['catatan_admin'],
                ':aes_key' => $reEnc['encrypted_aes_key'],
                ':iv'      => $reEnc['aes_iv'],
                ':id'      => $id,
            ]);
            $flashMsg = 'Status booking #' . $id . ' diperbarui dan dienkripsi ulang dengan kunci sesi AES & IV baru.';
        }
    }

    if ($action === 'delete_booking') {
        $pdo->prepare('DELETE FROM bookings WHERE id = :id')->execute([':id' => (int) ($_POST['booking_id'] ?? 0)]);
        $flashMsg = 'Data booking terenkripsi berhasil dihapus.';
    }

    if ($action === 'create_ops_note') {
        $enc = $crypto->encryptFields([
            'gunung'      => trim((string) ($_POST['gunung_ops'] ?? '')),
            'judul'       => trim((string) ($_POST['judul_ops'] ?? '')),
            'isi_laporan' => trim((string) ($_POST['isi_ops'] ?? '')),
            'prioritas'   => trim((string) ($_POST['prioritas_ops'] ?? 'Normal')),
        ]);
        $pdo->prepare("
            INSERT INTO admin_ops_notes (gunung_enc, judul_enc, isi_laporan_enc, prioritas_enc, encrypted_aes_key, aes_iv)
            VALUES (:gunung, :judul, :isi, :prio, :aes_key, :iv)
        ")->execute([
            ':gunung'  => $enc['fields']['gunung'],
            ':judul'   => $enc['fields']['judul'],
            ':isi'     => $enc['fields']['isi_laporan'],
            ':prio'    => $enc['fields']['prioritas'],
            ':aes_key' => $enc['encrypted_aes_key'],
            ':iv'      => $enc['aes_iv'],
        ]);
        $flashMsg = 'Laporan operasional gunung berhasil dienkripsi dan disimpan.';
    }

    if ($action === 'delete_ops_note') {
        $pdo->prepare('DELETE FROM admin_ops_notes WHERE id = :id')->execute([':id' => (int) ($_POST['note_id'] ?? 0)]);
        $flashMsg = 'Laporan operasional berhasil dihapus.';
    }
}

$viewMode = $isValidKey
    ? (($_GET['mode'] ?? 'decrypted') === 'ciphertext' ? 'ciphertext' : 'decrypted')
    : 'ciphertext';

$activeTab = ($_GET['tab'] ?? 'bookings') === 'ops' ? 'ops' : 'bookings';

$bookingRows = $pdo->query('SELECT * FROM bookings ORDER BY id DESC')->fetchAll();
$opsRows = $pdo->query('SELECT * FROM admin_ops_notes ORDER BY id DESC')->fetchAll();

$bookingCols = ['kode_booking_enc', 'nama_pendaki_enc', 'nik_enc', 'telepon_enc', 'gunung_enc', 'jalur_enc', 'tanggal_enc', 'jumlah_orang_enc', 'status_enc', 'catatan_admin_enc'];
$opsCols = ['gunung_enc', 'judul_enc', 'isi_laporan_enc', 'prioritas_enc'];

$totalVerified = 0;
$decryptedBookings = [];
if ($isValidKey) {
    foreach ($bookingRows as $row) {
        $dec = $crypto->decryptFields($row, $bookingCols, $adminPrivateKey);
        if (($dec['status_enc'] ?? '') === 'Terverifikasi') {
            $totalVerified++;
        }
        $decryptedBookings[$row['id']] = $dec;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isValidKey ? 'Dashboard Admin' : 'Dashboard Terkunci (Enkripsi)' ?> — NusantaraPeak</title>
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
<body class="<?= $isValidKey ? 'bg-slate-950' : 'bg-[#12060a]' ?> text-slate-100 min-h-screen antialiased overflow-x-hidden">

    <!-- Backdrop Mobile Sidebar -->
    <div id="sidebar_backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 hidden lg:hidden"></div>

    <!-- Sidebar Navigasi (Desktop Fixed + Mobile Slide-Over) -->
    <aside id="admin_sidebar"
           class="fixed top-0 left-0 z-50 h-screen w-72 <?= $isValidKey ? 'bg-slate-900 border-white/10' : 'bg-[#1b0910] border-rose-500/25' ?> border-r flex flex-col justify-between transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-out">
        <div class="p-6 space-y-6 overflow-y-auto">
            <!-- Brand -->
            <div class="flex items-center justify-between">
                <a href="index.php" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl <?= $isValidKey ? 'bg-gradient-to-br from-emerald-400 to-teal-600 text-slate-950' : 'bg-rose-500/20 border border-rose-500/50 text-rose-300' ?> flex items-center justify-center font-extrabold">
                        <?= $isValidKey ? 'NP' : '!' ?>
                    </div>
                    <div>
                        <span class="font-extrabold text-base text-white block leading-none">Nusantara<span class="<?= $isValidKey ? 'text-emerald-400' : 'text-rose-400' ?>">Peak</span></span>
                        <span class="text-[11px] text-slate-400">Admin Console</span>
                    </div>
                </a>
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white">✕</button>
            </div>

            <!-- Status Sesi Kriptografi -->
            <div class="rounded-2xl p-4 <?= $isValidKey ? 'bg-emerald-500/10 border border-emerald-500/25' : 'bg-rose-500/10 border border-rose-500/35' ?> space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full <?= $isValidKey ? 'bg-emerald-400' : 'bg-rose-400 animate-pulse' ?>"></span>
                    <span class="text-[11px] font-mono font-bold <?= $isValidKey ? 'text-emerald-300' : 'text-rose-300' ?>">
                        <?= $isValidKey ? 'RSA-2048 VERIFIED' : 'INVALID KEY LOCKED' ?>
                    </span>
                </div>
                <p class="text-[11px] text-slate-400 font-mono truncate" title="<?= e((string) ($_SESSION['uploaded_key_name'] ?? '')) ?>">
                    File: <?= e((string) ($_SESSION['uploaded_key_name'] ?? '-')) ?>
                </p>
                <p class="text-[10px] text-slate-500 font-mono truncate">
                    FP: <?= e((string) ($_SESSION['key_fingerprint'] ?? '-')) ?>
                </p>
            </div>

            <!-- Menu Navigasi -->
            <nav class="space-y-1.5">
                <p class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Modul Terenkripsi</p>
                <a href="?tab=bookings&mode=<?= e($viewMode) ?>"
                   class="flex items-center justify-between px-3.5 py-3 rounded-xl text-sm font-semibold transition <?= $activeTab === 'bookings' ? ($isValidKey ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-200 border border-rose-500/40') : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Booking Pendaki
                    </span>
                    <span class="text-xs font-mono opacity-80"><?= count($bookingRows) ?></span>
                </a>
                <a href="?tab=ops&mode=<?= e($viewMode) ?>"
                   class="flex items-center justify-between px-3.5 py-3 rounded-xl text-sm font-semibold transition <?= $activeTab === 'ops' ? ($isValidKey ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-200 border border-rose-500/40') : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Laporan Gunung
                    </span>
                    <span class="text-xs font-mono opacity-80"><?= count($opsRows) ?></span>
                </a>

                <p class="px-3 pt-4 text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Pintasan</p>
                <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-400 hover:bg-white/5 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3"/></svg>
                    Lihat Landing Page
                </a>
                <a href="setup_key.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-400 hover:bg-white/5 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    Pengaturan Kunci RSA
                </a>
            </nav>
        </div>

        <!-- Tombol Logout di Bawah Sidebar -->
        <div class="p-4 border-t border-white/10">
            <form action="logout.php" method="POST" onsubmit="return confirm('Keluar dari dashboard dan hapus sesi kunci dari memori?');">
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition shadow-lg shadow-rose-900/40 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <?= $isValidKey ? 'Kunci & Logout' : 'Logout & Ganti Kunci' ?>
                </button>
            </form>
        </div>
    </aside>

    <!-- Area Konten Utama -->
    <div class="lg:pl-72 min-h-screen flex flex-col">
        <!-- Top Header Bar -->
        <header class="sticky top-0 z-30 <?= $isValidKey ? 'bg-slate-950/85 border-white/10' : 'bg-[#1a080f]/90 border-rose-500/25' ?> backdrop-blur-xl border-b">
            <div class="px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" onclick="toggleSidebar()" aria-label="Buka Sidebar"
                            class="lg:hidden p-2 rounded-xl bg-slate-900 border border-white/10 text-slate-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="text-sm sm:text-lg font-extrabold text-white truncate">
                            <?= $activeTab === 'bookings' ? 'Manajemen Booking Pendaki' : 'Laporan Operasional Gunung' ?>
                        </h1>
                        <p class="text-[11px] text-slate-400 hidden sm:block">PHP <?= PHP_VERSION ?> • Hybrid AES-256-CBC & RSA-2048 Engine</p>
                    </div>
                </div>

                <!-- Switch Mode Dekripsi / Ciphertext -->
                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($isValidKey): ?>
                        <div class="inline-flex bg-slate-900 border border-white/10 p-1 rounded-xl">
                            <a href="?tab=<?= e($activeTab) ?>&mode=decrypted"
                               class="px-3 sm:px-4 py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition <?= $viewMode === 'decrypted' ? 'bg-emerald-500 text-slate-950 shadow' : 'text-slate-400 hover:text-white' ?>">
                                Dekripsi
                            </a>
                            <a href="?tab=<?= e($activeTab) ?>&mode=ciphertext"
                               class="px-3 sm:px-4 py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition <?= $viewMode === 'ciphertext' ? 'bg-amber-400 text-slate-950 shadow' : 'text-slate-400 hover:text-white' ?>">
                                Ciphertext
                            </a>
                        </div>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl text-[11px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">
                            LOCKED: CIPHERTEXT ONLY
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 max-w-[1440px] w-full mx-auto">

            <!-- Banner Mode Kunci Salah -->
            <?php if (!$isValidKey): ?>
                <section class="bg-rose-950/55 border border-rose-500/45 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <span class="text-[10px] font-mono font-bold uppercase tracking-widest text-rose-400">RSA-2048 Challenge Failed • Encrypted Fallback Mode</span>
                            <h2 class="text-lg sm:text-xl font-extrabold text-white">Kunci Kriptografi Salah — Seluruh Konten Terkunci</h2>
                            <p class="text-xs text-rose-200/80 leading-relaxed max-w-3xl">
                                File <strong><?= e((string) ($_SESSION['uploaded_key_name'] ?? '')) ?></strong> gagal mendekripsi kunci sesi AES. Seluruh data di bawah ditampilkan dalam wujud <em>ciphertext</em> mentah dan fitur ubah/hapus dinonaktifkan.
                            </p>
                        </div>
                        <form action="logout.php" method="POST" class="shrink-0">
                            <button type="submit" class="w-full md:w-auto bg-white hover:bg-slate-200 text-slate-950 font-extrabold text-xs px-5 py-3 rounded-xl transition cursor-pointer">
                                Logout & Gunakan Kunci Benar →
                            </button>
                        </form>
                    </div>

                    <?php if (is_array($failedMeta)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 font-mono text-[11px] pt-3 border-t border-rose-500/25">
                            <div class="bg-slate-950/90 border border-rose-500/30 rounded-xl p-3">
                                <span class="text-amber-400 font-bold block mb-1">[AES-256-CBC] Ciphertext Penolakan:</span>
                                <p class="text-rose-200 break-all"><?= e((string) $failedMeta['aes_ciphertext']) ?></p>
                            </div>
                            <div class="bg-slate-950/90 border border-rose-500/30 rounded-xl p-3">
                                <span class="text-cyan-400 font-bold block mb-1">[RSA-2048 OAEP] Encrypted Session Key:</span>
                                <p class="text-slate-300 break-all"><?= e(substr((string) $failedMeta['rsa_encrypted_key'], 0, 130)) ?>...</p>
                            </div>
                            <div class="bg-slate-950/90 border border-rose-500/30 rounded-xl p-3">
                                <span class="text-emerald-400 font-bold block mb-1">[AES Initialization Vector (IV)]:</span>
                                <p class="text-slate-300 break-all"><?= e((string) $failedMeta['aes_iv']) ?></p>
                            </div>
                            <div class="bg-slate-950/90 border border-rose-500/30 rounded-xl p-3">
                                <span class="text-rose-400 font-bold block mb-1">[SHA-256 Sidik Jari File]:</span>
                                <p class="text-slate-300 break-all"><?= e((string) $failedMeta['file_sha256']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- KPI Cards -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="<?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/30 border-rose-500/30' ?> border rounded-2xl p-4 sm:p-5">
                    <p class="text-[11px] text-slate-400 font-medium">Total Booking</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-white mt-1"><?= count($bookingRows) ?></p>
                    <p class="text-[10px] sm:text-[11px] <?= $isValidKey ? 'text-emerald-400' : 'text-amber-400' ?> mt-1">100% AES-256-CBC</p>
                </div>
                <div class="<?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/30 border-rose-500/30' ?> border rounded-2xl p-4 sm:p-5">
                    <p class="text-[11px] text-slate-400 font-medium">Terverifikasi</p>
                    <?php if ($isValidKey): ?>
                        <p class="text-xl sm:text-2xl font-extrabold text-emerald-400 mt-1"><?= $totalVerified ?></p>
                        <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1">Dekripsi real-time</p>
                    <?php else: ?>
                        <p class="text-xs sm:text-sm font-mono font-extrabold text-rose-400 mt-2 truncate">0x7F9A_LOCKED</p>
                        <p class="text-[10px] sm:text-[11px] text-rose-300/70 mt-1">Data terenkripsi</p>
                    <?php endif; ?>
                </div>
                <div class="<?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/30 border-rose-500/30' ?> border rounded-2xl p-4 sm:p-5">
                    <p class="text-[11px] text-slate-400 font-medium">Laporan Gunung</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-cyan-400 mt-1"><?= count($opsRows) ?></p>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1">Terkunci RSA-2048</p>
                </div>
                <div class="<?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/30 border-rose-500/30' ?> border rounded-2xl p-4 sm:p-5">
                    <p class="text-[11px] text-slate-400 font-medium">Kunci Privat</p>
                    <p class="text-sm sm:text-base font-extrabold <?= $isValidKey ? 'text-emerald-400' : 'text-rose-400' ?> mt-1.5">
                        <?= $isValidKey ? 'Valid (Aktif)' : 'Terkunci' ?>
                    </p>
                    <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1"><?= $isValidKey ? 'Di memori sesi PHP' : 'Kunci tidak cocok' ?></p>
                </div>
            </section>

            <?php if ($flashMsg): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/35 text-emerald-300 text-xs sm:text-sm px-4 py-3.5 rounded-2xl flex items-center gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                    <span><?= e($flashMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Konten Modul Aktif -->
            <?php if ($activeTab === 'bookings'): ?>
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    <!-- Panel Form / Kunci -->
                    <div class="xl:col-span-4 space-y-5">
                        <div class="<?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/30 border-rose-500/30' ?> border rounded-3xl p-5 sm:p-6 space-y-4">
                            <?php if ($isValidKey): ?>
                                <h2 class="text-sm font-extrabold text-white">Tambah Booking Manual</h2>
                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="action" value="create_booking">
                                    <input type="text" name="nama_pendaki" required placeholder="Nama Ketua Pendaki" class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                    <input type="text" name="nik" required placeholder="NIK KTP (16 Digit)" class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs font-mono text-white outline-none">
                                    <input type="text" name="telepon" required placeholder="No. HP / WhatsApp" class="w-full bg-slate-950 border border-white/10 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                    <select name="gunung" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                        <?php foreach ($mountains as $m): ?>
                                            <option value="<?= e($m['nama']) ?>"><?= e($m['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <input type="text" name="jalur" required placeholder="Jalur Basecamp" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                        <input type="number" name="jumlah_orang" min="1" value="2" required class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <input type="date" name="tanggal" value="<?= date('Y-m-d', strtotime('+4 days')) ?>" required class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white outline-none">
                                        <select name="status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white outline-none">
                                            <option value="Terverifikasi">Terverifikasi</option>
                                            <option value="Menunggu">Menunggu</option>
                                            <option value="Selesai">Selesai</option>
                                        </select>
                                    </div>
                                    <textarea name="catatan_admin" rows="2" placeholder="Catatan otorisasi basecamp..." class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"></textarea>
                                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs py-3 rounded-xl transition cursor-pointer">
                                        Enkripsi & Simpan Booking
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-4 space-y-2.5">
                                    <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center text-rose-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    </div>
                                    <h2 class="text-sm font-extrabold text-rose-300">Panel Kontrol Terkunci</h2>
                                    <p class="text-xs text-slate-400 leading-relaxed">Penambahan, perubahan status, dan penghapusan data dinonaktifkan karena kunci privat RSA tidak valid.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Public Key Card -->
                        <div class="bg-slate-900/90 border border-white/10 rounded-3xl p-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-300">RSA-2048 Public Key Server</span>
                                <button type="button" onclick="copyText('pub_key_box', this)" class="text-[11px] font-semibold text-emerald-400 hover:underline cursor-pointer">Salin</button>
                            </div>
                            <pre id="pub_key_box" class="bg-slate-950 border border-white/5 text-emerald-400/90 p-3 rounded-xl text-[9px] font-mono overflow-x-auto max-h-36"><?= e((string) $crypto->getPublicKey()) ?></pre>
                        </div>
                    </div>

                    <!-- Daftar Booking -->
                    <div class="xl:col-span-8 space-y-4">
                        <?php if (empty($bookingRows)): ?>
                            <div class="bg-slate-900/80 border border-white/10 rounded-3xl p-12 text-center text-sm text-slate-400">
                                Belum ada data booking pendakian di MySQL 8.0.
                            </div>
                        <?php else: ?>
                            <?php foreach ($bookingRows as $row): ?>
                                <article class="<?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/25 border-rose-500/35' ?> border rounded-3xl p-5 sm:p-6 space-y-4">
                                    <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-white/10">
                                        <div class="flex items-center flex-wrap gap-2">
                                            <span class="text-xs font-mono text-slate-400">#<?= (int) $row['id'] ?> • <?= e((string) $row['created_at']) ?></span>
                                            <?php if ($isValidKey && $viewMode === 'decrypted'): ?>
                                                <?php $d = $decryptedBookings[$row['id']]; ?>
                                                <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full <?= $d['status_enc'] === 'Terverifikasi' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300' ?>">
                                                    <?= e($d['status_enc']) ?>
                                                </span>
                                            <?php elseif (!$isValidKey): ?>
                                                <span class="text-[10px] font-mono font-bold px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300">ENCRYPTED_LOCKED</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($isValidKey): ?>
                                            <form method="POST" onsubmit="return confirm('Hapus booking terenkripsi ini?')">
                                                <input type="hidden" name="action" value="delete_booking">
                                                <input type="hidden" name="booking_id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit" class="text-xs font-semibold text-rose-400 hover:text-rose-300 cursor-pointer">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($isValidKey && $viewMode === 'decrypted'): ?>
                                        <?php $d = $decryptedBookings[$row['id']]; ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                            <div class="space-y-1.5">
                                                <span class="font-mono text-emerald-400 font-bold"><?= e($d['kode_booking_enc']) ?></span>
                                                <h3 class="text-base font-extrabold text-white"><?= e($d['nama_pendaki_enc']) ?></h3>
                                                <p class="text-slate-400">NIK: <span class="text-slate-200 font-mono"><?= e($d['nik_enc']) ?></span></p>
                                                <p class="text-slate-400">Telepon: <span class="text-slate-200"><?= e($d['telepon_enc']) ?></span></p>
                                            </div>
                                            <div class="bg-slate-950/75 border border-white/10 rounded-2xl p-4 space-y-1.5">
                                                <p class="font-bold text-cyan-300 text-sm"><?= e($d['gunung_enc']) ?></p>
                                                <p class="text-slate-300">Jalur <?= e($d['jalur_enc']) ?> • <?= e($d['jumlah_orang_enc']) ?></p>
                                                <p class="text-slate-400">Jadwal Naik: <span class="text-white font-semibold"><?= e($d['tanggal_enc']) ?></span></p>
                                                <p class="text-slate-400 italic pt-1 border-t border-white/5"><?= e($d['catatan_admin_enc']) ?></p>
                                            </div>
                                        </div>

                                        <form method="POST" class="flex flex-col sm:flex-row gap-2 pt-1">
                                            <input type="hidden" name="action" value="update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?= (int) $row['id'] ?>">
                                            <select name="status_baru" class="bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white outline-none">
                                                <?php foreach (['Menunggu', 'Terverifikasi', 'Selesai', 'Dibatalkan'] as $st): ?>
                                                    <option value="<?= $st ?>" <?= $d['status_enc'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="catatan_baru" value="<?= e($d['catatan_admin_enc']) ?>" class="flex-1 bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white outline-none">
                                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition cursor-pointer">
                                                Update & Re-Enkripsi
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 font-mono text-[11px]">
                                            <?php
                                            $labels = [
                                                'kode_booking_enc'  => 'Kode Booking',
                                                'nama_pendaki_enc'  => 'Nama Pendaki',
                                                'nik_enc'           => 'NIK KTP',
                                                'telepon_enc'       => 'Telepon',
                                                'gunung_enc'        => 'Gunung',
                                                'jalur_enc'         => 'Jalur',
                                                'tanggal_enc'       => 'Tanggal',
                                                'jumlah_orang_enc'  => 'Jumlah Pendaki',
                                                'status_enc'        => 'Status',
                                                'catatan_admin_enc' => 'Catatan Admin',
                                            ];
                                            foreach ($labels as $colKey => $labelText): ?>
                                                <div class="bg-slate-950/90 border <?= $isValidKey ? 'border-white/10' : 'border-rose-500/25' ?> rounded-xl p-3">
                                                    <span class="text-amber-400 block mb-0.5">[AES-256-CBC] <?= $labelText ?>:</span>
                                                    <p class="text-slate-300 break-all"><?= e((string) $row[$colKey]) ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="md:col-span-2 bg-slate-950/90 border border-cyan-500/30 rounded-xl p-3">
                                                <span class="text-cyan-400 block mb-0.5">[RSA-2048 OAEP] Encrypted AES Session Key:</span>
                                                <p class="text-slate-300 break-all"><?= e((string) $row['encrypted_aes_key']) ?></p>
                                            </div>
                                            <div class="md:col-span-2 bg-slate-950/90 border border-emerald-500/30 rounded-xl p-3">
                                                <span class="text-emerald-400 block mb-0.5">[AES Initialization Vector (IV)]:</span>
                                                <p class="text-slate-300 break-all"><?= e((string) $row['aes_iv']) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Modul Laporan Operasional -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    <div class="xl:col-span-4 <?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/30 border-rose-500/30' ?> border rounded-3xl p-5 sm:p-6 h-fit space-y-4">
                        <?php if ($isValidKey): ?>
                            <h2 class="text-sm font-extrabold text-white">Tambah Laporan Operasional</h2>
                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="action" value="create_ops_note">
                                <select name="gunung_ops" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                    <?php foreach ($mountains as $m): ?>
                                        <option value="<?= e($m['nama']) ?>"><?= e($m['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="judul_ops" required placeholder="Judul Laporan / Info Cuaca" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                <select name="prioritas_ops" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none">
                                    <option value="Tinggi">Prioritas Tinggi</option>
                                    <option value="Normal">Prioritas Normal</option>
                                    <option value="Rahasia Basecamp">Rahasia Basecamp</option>
                                </select>
                                <textarea name="isi_ops" rows="4" required placeholder="Detail kondisi jalur, evakuasi, atau kuota khusus..." class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white outline-none"></textarea>
                                <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs py-3 rounded-xl transition cursor-pointer">
                                    Enkripsi & Simpan Laporan
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-6 space-y-2">
                                <h2 class="text-sm font-extrabold text-rose-300">Input Laporan Terkunci</h2>
                                <p class="text-xs text-slate-400">Gunakan kunci privat RSA-2048 yang valid untuk membuat atau mendekripsi laporan operasional.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="xl:col-span-8 space-y-4">
                        <?php foreach ($opsRows as $note): ?>
                            <article class="<?= $isValidKey ? 'bg-slate-900/90 border-white/10' : 'bg-rose-950/25 border-rose-500/35' ?> border rounded-3xl p-5 sm:p-6 space-y-3">
                                <div class="flex justify-between items-center pb-2.5 border-b border-white/10">
                                    <span class="text-xs font-mono text-slate-400">Note #<?= (int) $note['id'] ?> • <?= e((string) $note['created_at']) ?></span>
                                    <?php if ($isValidKey): ?>
                                        <form method="POST" onsubmit="return confirm('Hapus laporan ini?')">
                                            <input type="hidden" name="action" value="delete_ops_note">
                                            <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                                            <button type="submit" class="text-xs font-semibold text-rose-400 hover:text-rose-300 cursor-pointer">Hapus</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-[10px] font-mono text-rose-300">ENCRYPTED_LOCKED</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isValidKey && $viewMode === 'decrypted'): ?>
                                    <?php $dn = $crypto->decryptFields($note, $opsCols, $adminPrivateKey); ?>
                                    <div class="space-y-2">
                                        <div class="flex items-center flex-wrap gap-2">
                                            <span class="text-[11px] font-bold bg-cyan-500/20 text-cyan-300 px-2.5 py-0.5 rounded-full"><?= e($dn['gunung_enc']) ?></span>
                                            <span class="text-[11px] font-bold bg-amber-500/20 text-amber-300 px-2.5 py-0.5 rounded-full"><?= e($dn['prioritas_enc']) ?></span>
                                        </div>
                                        <h3 class="text-base font-extrabold text-white"><?= e($dn['judul_enc']) ?></h3>
                                        <p class="text-sm text-slate-300 leading-relaxed whitespace-pre-line"><?= e($dn['isi_laporan_enc']) ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-2 font-mono text-[11px]">
                                        <div class="bg-slate-950/90 p-3 rounded-xl"><span class="text-amber-400">[AES-256] Gunung:</span> <span class="break-all text-slate-300"><?= e((string) $note['gunung_enc']) ?></span></div>
                                        <div class="bg-slate-950/90 p-3 rounded-xl"><span class="text-amber-400">[AES-256] Judul:</span> <span class="break-all text-slate-300"><?= e((string) $note['judul_enc']) ?></span></div>
                                        <div class="bg-slate-950/90 p-3 rounded-xl"><span class="text-amber-400">[AES-256] Isi Laporan:</span> <span class="break-all text-slate-300"><?= e((string) $note['isi_laporan_enc']) ?></span></div>
                                        <div class="bg-slate-950/90 p-3 rounded-xl"><span class="text-amber-400">[AES-256] Prioritas:</span> <span class="break-all text-slate-300"><?= e((string) $note['prioritas_enc']) ?></span></div>
                                        <div class="bg-slate-950/90 p-3 rounded-xl border border-cyan-500/30"><span class="text-cyan-400">[RSA-2048] Encrypted AES Key:</span> <span class="break-all text-slate-300"><?= e((string) $note['encrypted_aes_key']) ?></span></div>
                                        <div class="bg-slate-950/90 p-3 rounded-xl border border-emerald-500/30"><span class="text-emerald-400">[AES IV]:</span> <span class="break-all text-slate-300"><?= e((string) $note['aes_iv']) ?></span></div>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('admin_sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar_backdrop').classList.toggle('hidden');
        }
        function copyText(id, btn) {
            const txt = document.getElementById(id).textContent;
            navigator.clipboard.writeText(txt).then(() => {
                const orig = btn.textContent;
                btn.textContent = 'Tersalin!';
                setTimeout(() => btn.textContent = orig, 1500);
            });
        }
    </script>
</body>
</html>