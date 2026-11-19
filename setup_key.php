<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';

$pdo = getDbConnection();
$crypto = new HybridMountainCrypto($pdo);

if (isset($_GET['download']) && $_GET['download'] === 'valid' && !empty($_SESSION['generated_private_key'])) {
    header('Content-Type: application/x-pem-file');
    header('Content-Disposition: attachment; filename="admin_private_key.pem"');
    echo $_SESSION['generated_private_key'];
    exit;
}

if (isset($_GET['download']) && $_GET['download'] === 'fake') {
    $fakeKey = $crypto->generateDecoyPrivateKey();
    header('Content-Type: application/x-pem-file');
    header('Content-Disposition: attachment; filename="kunci_salah_demo.pem"');
    echo $fakeKey;
    exit;
}

$statusMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $pdo->exec('TRUNCATE TABLE bookings');
    $pdo->exec('TRUNCATE TABLE admin_ops_notes');

    $pair = $crypto->generateAndStoreKeyPair();
    $_SESSION['generated_private_key'] = $pair['private_key'];
    unset($_SESSION['admin_private_key'], $_SESSION['auth_mode'], $_SESSION['failed_crypto_meta']);

    $samples = [
        [
            'kode_booking'  => 'NP-' . date('Y') . '-001',
            'nama_pendaki'  => 'Raka Pratama',
            'nik'           => '3202011508010004',
            'telepon'       => '0812-8844-9012',
            'gunung'        => 'Gunung Gede Pangrango',
            'jalur'         => 'Cibodas',
            'tanggal'       => date('Y-m-d', strtotime('+5 days')),
            'jumlah_orang'  => '4 Pendaki',
            'status'        => 'Terverifikasi',
            'catatan_admin' => 'Kelengkapan surat sehat dan logistik sudah diverifikasi pos Cibodas.',
        ],
        [
            'kode_booking'  => 'NP-' . date('Y') . '-002',
            'nama_pendaki'  => 'Nadia Kusuma',
            'nik'           => '3573045209020009',
            'telepon'       => '0857-2311-7740',
            'gunung'        => 'Gunung Rinjani',
            'jalur'         => 'Sembalun',
            'tanggal'       => date('Y-m-d', strtotime('+12 days')),
            'jumlah_orang'  => '3 Pendaki',
            'status'        => 'Menunggu',
            'catatan_admin' => 'Menunggu konfirmasi guide lokal TNGR Sembalun.',
        ],
    ];

    $stmtBooking = $pdo->prepare("
        INSERT INTO bookings
        (kode_booking_enc, nama_pendaki_enc, nik_enc, telepon_enc, gunung_enc, jalur_enc, tanggal_enc, jumlah_orang_enc, status_enc, catatan_admin_enc, encrypted_aes_key, aes_iv)
        VALUES (:kode, :nama, :nik, :telp, :gunung, :jalur, :tgl, :jml, :status, :catatan, :aes_key, :iv)
    ");

    foreach ($samples as $sample) {
        $enc = $crypto->encryptFields($sample);
        $stmtBooking->execute([
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
    }

    $noteEnc = $crypto->encryptFields([
        'gunung'      => 'Gunung Gede Pangrango',
        'judul'       => 'Briefing Cuaca & Kuota Akhir Pekan',
        'isi_laporan' => 'Jalur Cibodas dan Gunung Putri dibuka normal. Angin puncak 25 km/jam, wajib cek jaket thermal pendaki.',
        'prioritas'   => 'Tinggi',
    ]);
    $pdo->prepare("
        INSERT INTO admin_ops_notes (gunung_enc, judul_enc, isi_laporan_enc, prioritas_enc, encrypted_aes_key, aes_iv)
        VALUES (:gunung, :judul, :isi, :prio, :aes_key, :iv)
    ")->execute([
        ':gunung'  => $noteEnc['fields']['gunung'],
        ':judul'   => $noteEnc['fields']['judul'],
        ':isi'     => $noteEnc['fields']['isi_laporan'],
        ':prio'    => $noteEnc['fields']['prioritas'],
        ':aes_key' => $noteEnc['encrypted_aes_key'],
        ':iv'      => $noteEnc['aes_iv'],
    ]);

    $statusMessage = 'Pasangan kunci RSA-2048 berhasil dibuat! Unduh file admin_private_key.pem di bawah ini untuk login ke Dashboard Admin.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Kunci RSA-2048 — NusantaraPeak</title>
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
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 antialiased">
    <div class="max-w-2xl w-full bg-slate-900/90 border border-white/10 rounded-3xl p-6 sm:p-9 shadow-2xl space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <span class="text-[11px] font-mono font-bold uppercase tracking-widest text-emerald-400">Key Provisioning • RSA-2048</span>
                <h1 class="text-2xl font-extrabold text-white mt-1">Setup File Kunci Login Admin</h1>
            </div>
            <a href="index.php" class="text-xs font-semibold text-slate-400 hover:text-white border border-white/10 px-3.5 py-2 rounded-xl w-fit">← Beranda</a>
        </div>

        <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            <strong class="text-emerald-400">Public Key</strong> disimpan di tabel MySQL 8.0 untuk mengenkripsi data booking, sementara <strong class="text-amber-400">Private Key (.pem)</strong> diunduh ke perangkat Anda sebagai pengganti username & password.
        </p>

        <?php if ($statusMessage): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/35 text-emerald-300 text-xs sm:text-sm px-4 py-3.5 rounded-2xl">
                <?= e($statusMessage) ?>
            </div>
        <?php endif; ?>

        <div class="bg-slate-950 border border-white/10 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <p class="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Status Kunci Publik MySQL</p>
                <p class="text-sm font-bold mt-1 <?= $crypto->hasPublicKey() ? 'text-emerald-400' : 'text-amber-400' ?>">
                    <?= $crypto->hasPublicKey() ? 'Aktif & Siap Mengenkripsi' : 'Belum Diinisialisasi' ?>
                </p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="generate">
                <button type="submit" class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs px-5 py-3 rounded-xl transition cursor-pointer">
                    <?= $crypto->hasPublicKey() ? 'Generate Ulang Kunci RSA-2048' : 'Generate Kunci RSA-2048' ?>
                </button>
            </form>
        </div>

        <?php if (!empty($_SESSION['generated_private_key'])): ?>
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-5 space-y-4">
                <div>
                    <h2 class="text-sm font-extrabold text-amber-300">File Kunci Siap Diunduh</h2>
                    <p class="text-xs text-slate-300 mt-1">Unduh kedua file di bawah untuk menguji login berhasil (mode dekripsi) dan login gagal (mode enkripsi terkunci).</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="?download=valid" class="text-center bg-amber-400 hover:bg-amber-300 text-slate-950 font-extrabold text-xs px-4 py-3 rounded-xl transition">
                        Unduh admin_private_key.pem (Benar)
                    </a>
                    <a href="?download=fake" class="text-center bg-slate-900 hover:bg-slate-800 text-rose-300 border border-rose-500/35 font-bold text-xs px-4 py-3 rounded-xl transition">
                        Unduh kunci_salah_demo.pem (Salah)
                    </a>
                </div>
            </div>
        <?php elseif ($crypto->hasPublicKey()): ?>
            <div class="bg-slate-950 border border-white/10 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <p class="text-xs text-slate-400">Butuh file kunci palsu untuk demo mode terkunci?</p>
                <a href="?download=fake" class="text-xs font-bold text-rose-400 hover:underline">Unduh kunci_salah_demo.pem →</a>
            </div>
        <?php endif; ?>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-white/10">
            <a href="index.php" class="text-xs text-center text-slate-400 hover:text-white py-2">Kembali ke Landing Page</a>
            <a href="login.php" class="text-center bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-5 py-3 rounded-xl transition">
                Lanjut ke Login Kriptografi →
            </a>
        </div>
    </div>
</body>
</html>