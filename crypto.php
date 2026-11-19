<?php
declare(strict_types=1);

class HybridMountainCrypto
{
    private PDO $pdo;
    private string $cipherMethod = 'aes-256-cbc';
    private ?string $publicKey = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadPublicKey();
    }

    /**
     * Otomatis membuat konfigurasi OpenSSL minimal agar RSA berjalan lancar di Laragon Windows
     */
    public function getOpenSslConfig(): array
    {
        $envConf = getenv('OPENSSL_CONF');
        if ($envConf !== false && file_exists($envConf)) {
            return ['config' => $envConf];
        }

        $localConf = __DIR__ . DIRECTORY_SEPARATOR . 'openssl_laragon.cnf';
        if (!file_exists($localConf)) {
            $minimalConfig = "HOME = .\n[req]\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n";
            file_put_contents($localConf, $minimalConfig);
        }

        return ['config' => $localConf];
    }

    private function loadPublicKey(): void
    {
        $stmt = $this->pdo->prepare("SELECT public_key FROM crypto_config WHERE key_name = 'admin_master' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        $this->publicKey = $row ? (string) $row['public_key'] : null;
    }

    public function hasPublicKey(): bool
    {
        return $this->publicKey !== null && $this->publicKey !== '';
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Membuat pasangan kunci RSA-2048 baru.
     * Public Key disimpan di MySQL, Private Key dikembalikan untuk diunduh admin sebagai file .pem
     */
    public function generateAndStoreKeyPair(): array
    {
        $osslConfig = $this->getOpenSslConfig();
        $configArgs = array_merge([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ], $osslConfig);

        $res = openssl_pkey_new($configArgs);
        if ($res === false) {
            throw new RuntimeException('Gagal membangkitkan pasangan kunci RSA-2048: ' . (openssl_error_string() ?: 'Unknown error'));
        }

        $privKeyOut = '';
        openssl_pkey_export($res, $privKeyOut, null, $osslConfig);
        $details = openssl_pkey_get_details($res);
        $pubKeyOut = (string) $details['key'];

        $stmt = $this->pdo->prepare("
            INSERT INTO crypto_config (key_name, public_key)
            VALUES ('admin_master', :pub)
            ON DUPLICATE KEY UPDATE public_key = VALUES(public_key), created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([':pub' => $pubKeyOut]);

        $this->publicKey = $pubKeyOut;

        return [
            'public_key'  => $pubKeyOut,
            'private_key' => $privKeyOut,
        ];
    }

    /**
     * Membuat kunci RSA-2048 palsu (tidak terdaftar di database) untuk simulasi login gagal saat presentasi UTS
     */
    public function generateDecoyPrivateKey(): string
    {
        $osslConfig = $this->getOpenSslConfig();
        $configArgs = array_merge([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ], $osslConfig);

        $res = openssl_pkey_new($configArgs);
        if ($res === false) {
            throw new RuntimeException('Gagal membuat kunci uji coba.');
        }

        $fakePriv = '';
        openssl_pkey_export($res, $fakePriv, null, $osslConfig);
        return $fakePriv;
    }

    /**
     * Autentikasi Passwordless: Challenge-Response menggunakan RSA-2048 OAEP
     */
    public function verifyAdminPrivateKey(string $uploadedPrivateKeyPem): bool
    {
        if (!$this->hasPublicKey()) {
            return false;
        }

        $privResource = @openssl_pkey_get_private($uploadedPrivateKeyPem);
        if ($privResource === false) {
            return false;
        }

        $challenge = random_bytes(32);
        $encryptedChallenge = '';
        $okEncrypt = @openssl_public_encrypt(
            $challenge,
            $encryptedChallenge,
            (string) $this->publicKey,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$okEncrypt) {
            return false;
        }

        $decryptedChallenge = '';
        $okDecrypt = @openssl_private_decrypt(
            $encryptedChallenge,
            $decryptedChallenge,
            $privResource,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        return $okDecrypt && hash_equals($challenge, $decryptedChallenge);
    }

    /**
     * Enkripsi seluruh kolom konten (Simetris AES-256-CBC + Asimetris RSA-2048 OAEP)
     */
    public function encryptFields(array $fields): array
    {
        if (!$this->hasPublicKey()) {
            throw new RuntimeException('Public Key RSA-2048 belum diinisialisasi. Jalankan setup_key.php terlebih dahulu.');
        }

        $aesKey = random_bytes(32); // 256-bit symmetric key
        $ivLength = openssl_cipher_iv_length($this->cipherMethod);
        $iv = random_bytes($ivLength); // 16-byte IV

        $encryptedFields = [];
        foreach ($fields as $key => $value) {
            $rawCipher = openssl_encrypt(
                (string) $value,
                $this->cipherMethod,
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            if ($rawCipher === false) {
                throw new RuntimeException('Enkripsi AES-256-CBC gagal pada kolom ' . $key);
            }
            $encryptedFields[$key] = base64_encode($rawCipher);
        }

        $encryptedAesKeyRaw = '';
        $okRsa = openssl_public_encrypt(
            $aesKey,
            $encryptedAesKeyRaw,
            (string) $this->publicKey,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$okRsa) {
            throw new RuntimeException('Enkripsi RSA-2048 pada kunci sesi AES gagal.');
        }

        return [
            'fields'            => $encryptedFields,
            'encrypted_aes_key' => base64_encode($encryptedAesKeyRaw),
            'aes_iv'            => base64_encode($iv),
        ];
    }

    /**
     * Dekripsi seluruh kolom konten menggunakan Private Key milik admin yang sedang login
     */
    public function decryptFields(array $row, array $columns, string $privateKeyPem): array
    {
        $encryptedAesKeyRaw = base64_decode((string) $row['encrypted_aes_key'], true);
        $iv = base64_decode((string) $row['aes_iv'], true);

        if ($encryptedAesKeyRaw === false || $iv === false) {
            return array_fill_keys($columns, '[Format Ciphertext Rusak]');
        }

        $aesKey = '';
        $okRsa = @openssl_private_decrypt(
            $encryptedAesKeyRaw,
            $aesKey,
            $privateKeyPem,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$okRsa) {
            return array_fill_keys($columns, '[Kunci Privat RSA Tidak Cocok]');
        }

        $decrypted = [];
        foreach ($columns as $col) {
            $rawCipher = base64_decode((string) $row[$col], true);
            if ($rawCipher === false) {
                $decrypted[$col] = '[Data Rusak]';
                continue;
            }
            $plain = openssl_decrypt(
                $rawCipher,
                $this->cipherMethod,
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            $decrypted[$col] = $plain !== false ? $plain : '[Gagal Dekripsi AES]';
        }

        return $decrypted;
    }

    /**
     * Menghasilkan tampilan Enkripsi Penolakan (AES-256-CBC + RSA-2048) saat Login Gagal
     */
    public function buildFailedLoginEncryption(string $uploadedFileName, string $uploadedContent, string $reason): array
    {
        $rejectPayload = json_encode([
            'status'      => 'ACCESS_DENIED_CRYPTO_MISMATCH',
            'alasan'      => $reason,
            'nama_file'   => $uploadedFileName,
            'ukuran_byte' => strlen($uploadedContent),
            'ip_pengakses'=> $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'waktu_utc'   => gmdate('Y-m-d\TH:i:s\Z'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $aesKey = random_bytes(32);
        $iv = random_bytes(openssl_cipher_iv_length($this->cipherMethod));
        $cipherRaw = openssl_encrypt((string) $rejectPayload, $this->cipherMethod, $aesKey, OPENSSL_RAW_DATA, $iv);

        $encryptedAesKeyB64 = '[Public Key Belum Diatur]';
        if ($this->hasPublicKey()) {
            $encKeyRaw = '';
            if (@openssl_public_encrypt($aesKey, $encKeyRaw, (string) $this->publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
                $encryptedAesKeyB64 = base64_encode($encKeyRaw);
            }
        }

        return [
            'aes_ciphertext'    => base64_encode((string) $cipherRaw),
            'rsa_encrypted_key' => $encryptedAesKeyB64,
            'aes_iv'            => base64_encode($iv),
            'file_sha256'       => hash('sha256', $uploadedContent !== '' ? $uploadedContent : 'EMPTY_FILE'),
            'hex_signature'     => strtoupper(bin2hex(substr((string) $cipherRaw, 0, 32))),
        ];
    }
}