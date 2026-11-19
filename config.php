<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const DB_HOST = '127.0.0.1';
const DB_NAME = 'db_nusantara_peak';
const DB_USER = 'root';
const DB_PASS = ''; // Default Laragon kosong

function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die('Koneksi MySQL 8.0 Gagal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
    return $pdo;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Katalog publik gunung Indonesia untuk Landing Page
 */
function getMountainCatalog(): array
{
    return [
        [
            'nama'       => 'Gunung Gede Pangrango',
            'lokasi'     => 'Jawa Barat',
            'ketinggian' => '2.958 – 3.019 mdpl',
            'jalur'      => ['Cibodas', 'Gunung Putri', 'Selabintana'],
            'harga'      => 'Rp 34.000 / pendaki',
            'kuota'      => '600 pendaki/hari',
            'level'      => 'Menengah',
            'aksen'      => 'from-emerald-600 to-teal-900',
            'deskripsi'  => 'Taman Nasional dengan sabana Alun-Alun Suryakencana, kawah aktif, dan jalur hutan hujan tropis.'
        ],
        [
            'nama'       => 'Gunung Rinjani',
            'lokasi'     => 'Nusa Tenggara Barat',
            'ketinggian' => '3.726 mdpl',
            'jalur'      => ['Sembalun', 'Senaru', 'Torean'],
            'harga'      => 'Rp 50.000 / pendaki',
            'kuota'      => '400 pendaki/hari',
            'level'      => 'Sulit',
            'aksen'      => 'from-sky-600 to-slate-900',
            'deskripsi'  => 'Kaldera megah Danau Segara Anak dan puncak vulkanik tertinggi kedua di Indonesia.'
        ],
        [
            'nama'       => 'Gunung Semeru',
            'lokasi'     => 'Jawa Timur',
            'ketinggian' => '3.676 mdpl',
            'jalur'      => ['Ranu Pani'],
            'harga'      => 'Rp 45.000 / pendaki',
            'kuota'      => '500 pendaki/hari',
            'level'      => 'Sulit',
            'aksen'      => 'from-amber-600 to-stone-900',
            'deskripsi'  => 'Atap Pulau Jawa dengan pesona Danau Ranu Kumbolo dan tanjakan sabana Oro-Oro Ombo.'
        ],
        [
            'nama'       => 'Gunung Merbabu',
            'lokasi'     => 'Jawa Tengah',
            'ketinggian' => '3.145 mdpl',
            'jalur'      => ['Selo', 'Suwanting', 'Wekas', 'Thekelan'],
            'harga'      => 'Rp 30.000 / pendaki',
            'kuota'      => '550 pendaki/hari',
            'level'      => 'Menengah',
            'aksen'      => 'from-lime-600 to-emerald-950',
            'deskripsi'  => 'Hamparan sabana hijau luas dengan panorama langsung menghadap gagahnya Gunung Merapi.'
        ],
        [
            'nama'       => 'Gunung Prau',
            'lokasi'     => 'Dieng, Jawa Tengah',
            'ketinggian' => '2.565 mdpl',
            'jalur'      => ['Patak Banteng', 'Dieng', 'Dwarawati'],
            'harga'      => 'Rp 25.000 / pendaki',
            'kuota'      => '800 pendaki/hari',
            'level'      => 'Pemula',
            'aksen'      => 'from-indigo-600 to-slate-900',
            'deskripsi'  => 'Spot golden sunrise legendaris dengan latar barisan Gunung Sindoro, Sumbing, dan Merbabu.'
        ],
        [
            'nama'       => 'Gunung Kerinci',
            'lokasi'     => 'Jambi & Sumatera Barat',
            'ketinggian' => '3.805 mdpl',
            'jalur'      => ['Kersik Tuo'],
            'harga'      => 'Rp 40.000 / pendaki',
            'kuota'      => '300 pendaki/hari',
            'level'      => 'Sulit',
            'aksen'      => 'from-cyan-700 to-slate-950',
            'deskripsi'  => 'Gunung berapi tertinggi di Indonesia dan atap Pulau Sumatera di kawasan TNKS.'
        ],
    ];
}