<?php
declare(strict_types=1);

require_once __DIR__ . '/crypto_engine.php';

$host = '127.0.0.1';
$db   = 'db_uts_keamanan_data';
$user = 'root';
$pass = ''; // Default Laragon kosong

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Koneksi MySQL 8.0 Gagal: ' . htmlspecialchars($e->getMessage()));
}

$crypto = new HybridCryptoEngine($pdo);
$message = null;

// Proses tambah konten terenkripsi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $payload = $crypto->encryptAdminPayload([
        'judul'         => $_POST['judul'] ?? '',
        'kategori'      => $_POST['kategori'] ?? '',
        'isi_konten'    => $_POST['isi_konten'] ?? '',
        'catatan_admin' => $_POST['catatan_admin'] ?? '',
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO admin_contents 
        (judul_enc, kategori_enc, isi_konten_enc, catatan_admin_enc, encrypted_aes_key, aes_iv)
        VALUES (:judul, :kategori, :isi, :catatan, :aes_key, :iv)
    ");

    $stmt->execute([
        ':judul'    => $payload['data']['judul'],
        ':kategori' => $payload['data']['kategori'],
        ':isi'      => $payload['data']['isi_konten'],
        ':catatan'  => $payload['data']['catatan_admin'],
        ':aes_key'  => $payload['encrypted_aes_key'],
        ':iv'       => $payload['aes_iv'],
    ]);

    $message = 'Konten berhasil dienkripsi dengan AES-256-CBC dan kunci diamankan dengan RSA-2048!';
}

// Proses hapus konten
if (isset($_GET['delete'])) {
    $delStmt = $pdo->prepare("DELETE FROM admin_contents WHERE id = :id");
    $delStmt->execute([':id' => (int) $_GET['delete']]);
    header('Location: index.php');
    exit;
}

// Ambil seluruh konten halaman admin
$rows = $pdo->query("SELECT * FROM admin_contents ORDER BY id DESC")->fetchAll();
$viewMode = $_GET['mode'] ?? 'decrypted';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin Kriptografi Simetris & Asimetris</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6">
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded-full">
                    PHP <?= PHP_VERSION ?> • OpenSSL Hybrid Engine
                </span>
                <h1 class="text-2xl font-bold mt-2">Dashboard Admin Keamanan Data & Informasi</h1>
                <p class="text-slate-400 text-sm">Seluruh konten halaman admin dienkripsi menggunakan AES-256-CBC (Simetris) dan RSA-2048 (Asimetris).</p>
            </div>
            <div class="flex gap-2">
                <a href="?mode=decrypted" class="px-4 py-2 rounded-lg text-sm font-medium <?= $viewMode === 'decrypted' ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-slate-300' ?>">
                    Dekripsi (RSA Private Key + AES)
                </a>
                <a href="?mode=ciphertext" class="px-4 py-2 rounded-lg text-sm font-medium <?= $viewMode === 'ciphertext' ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-300' ?>">
                    Inspeksi Ciphertext Database
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="bg-emerald-900/50 border border-emerald-500 text-emerald-200 px-4 py-3 rounded-lg text-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Input Konten Admin -->
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h2 class="text-lg font-semibold mb-4">Tambah Konten Admin Baru</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Judul Dokumen / Konten</label>
                        <input type="text" name="judul" required placeholder="Contoh: Konfigurasi Server Inti"
                               class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Kategori Keamanan</label>
                        <select name="kategori" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                            <option value="Sangat Rahasia (Top Secret)">Sangat Rahasia (Top Secret)</option>
                            <option value="Internal Teknik Informatika">Internal Teknik Informatika</option>
                            <option value="Kredensial Infrastruktur">Kredensial Infrastruktur</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Isi Konten Utama</label>
                        <textarea name="isi_konten" rows="3" required placeholder="Masukkan data sensitif admin..."
                                  class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Catatan Otorisasi Admin</label>
                        <input type="text" name="catatan_admin" required placeholder="Diinput oleh Administrator Utama"
                               class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-lg text-sm transition">
                        Enkripsi & Simpan ke Database
                    </button>
                </form>

                <div class="mt-6 pt-4 border-t border-slate-700">
                    <span class="text-xs font-semibold text-slate-400 block mb-2">RSA-2048 Public Key Aktif:</span>
                    <pre class="bg-slate-950 text-emerald-400 p-3 rounded-lg text-[10px] overflow-x-auto"><?= htmlspecialchars($crypto->getPublicKey()) ?></pre>
                </div>
            </div>

            <!-- Daftar Konten Halaman Admin -->
            <div class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h2 class="text-lg font-semibold mb-4">
                    <?= $viewMode === 'decrypted' ? 'Daftar Konten Admin (Hasil Dekripsi Real-Time)' : 'Data Mentah di MySQL 8.0 (Ciphertext Simetris & Asimetris)' ?>
                </h2>

                <?php if (empty($rows)): ?>
                    <p class="text-slate-400 text-sm py-8 text-center">Belum ada data konten. Silakan tambahkan melalui formulir di samping.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($rows as $row): ?>
                            <?php $decrypted = $crypto->decryptAdminRow($row); ?>
                            <div class="bg-slate-900 border border-slate-700 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between items-start">
                                    <span class="text-xs text-slate-400">ID #<?= $row['id'] ?> • <?= $row['created_at'] ?></span>
                                    <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Hapus konten terenkripsi ini?')"
                                       class="text-xs text-rose-400 hover:underline">Hapus</a>
                                </div>

                                <?php if ($viewMode === 'decrypted'): ?>
                                    <div>
                                        <span class="inline-block text-xs bg-indigo-500/20 text-indigo-300 px-2.5 py-0.5 rounded mb-1">
                                            <?= htmlspecialchars($decrypted['kategori_enc']) ?>
                                        </span>
                                        <h3 class="text-base font-bold text-white"><?= htmlspecialchars($decrypted['judul_enc']) ?></h3>
                                        <p class="text-sm text-slate-300 mt-1 whitespace-pre-line"><?= htmlspecialchars($decrypted['isi_konten_enc']) ?></p>
                                        <p class="text-xs text-slate-400 mt-2 italic">Catatan Admin: <?= htmlspecialchars($decrypted['catatan_admin_enc']) ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-2 text-xs font-mono">
                                        <div>
                                            <span class="text-amber-400 block">[Simetris AES-256-CBC] Ciphertext Judul:</span>
                                            <p class="bg-slate-950 p-2 rounded break-all text-slate-300"><?= htmlspecialchars($row['judul_enc']) ?></p>
                                        </div>
                                        <div>
                                            <span class="text-amber-400 block">[Simetris AES-256-CBC] Ciphertext Isi Konten:</span>
                                            <p class="bg-slate-950 p-2 rounded break-all text-slate-300"><?= htmlspecialchars($row['isi_konten_enc']) ?></p>
                                        </div>
                                        <div>
                                            <span class="text-emerald-400 block">[Asimetris RSA-2048] Encrypted AES Key:</span>
                                            <p class="bg-slate-950 p-2 rounded break-all text-slate-300"><?= htmlspecialchars(substr($row['encrypted_aes_key'], 0, 140)) ?>...</p>
                                        </div>
                                        <div>
                                            <span class="text-sky-400 block">[AES Initialization Vector (IV)]:</span>
                                            <p class="bg-slate-950 p-2 rounded break-all text-slate-300"><?= htmlspecialchars($row['aes_iv']) ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>