<?php
declare(strict_types=1);

class HybridCryptoEngine
{
    private PDO $pdo;
    private string $cipherMethod = 'aes-256-cbc';
    private string $publicKey;
    private string $privateKey;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initializeRsaKeys();
    }

    /**
     * Memastikan konfigurasi OpenSSL tersedia di lingkungan Windows / Laragon
     */
    private function getOpenSslConfig(): array
    {
        $envConf = getenv('OPENSSL_CONF');
        if ($envConf && file_exists($envConf)) {
            return ['config' => $envConf];
        }

        $localConf = __DIR__ . DIRECTORY_SEPARATOR . 'openssl_laragon.cnf';
        if (!file_exists($localConf)) {
            $minimalConfig = "HOME = .\n[req]\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n";
            file_put_contents($localConf, $minimalConfig);
        }

        return ['config' => $localConf];
    }

    /**
     * Membuat atau memuat pasangan kunci Asimetris RSA-2048 dari database
     */
    private function initializeRsaKeys(): void
    {
        $stmt = $this->pdo->prepare("SELECT public_key, private_key FROM rsa_keypair WHERE key_name = 'admin_master' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->publicKey = $row['public_key'];
            $this->privateKey = $row['private_key'];
            return;
        }

        $osslConfig = $this->getOpenSslConfig();
        $configArgs = array_merge([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ], $osslConfig);

        $res = openssl_pkey_new($configArgs);
        if ($res === false) {
            throw new RuntimeException('Gagal membuat pasangan kunci RSA: ' . openssl_error_string());
        }

        openssl_pkey_export($res, $privKeyOut, null, $osslConfig);
        $pubKeyDetails = openssl_pkey_get_details($res);
        $pubKeyOut = $pubKeyDetails['key'];

        $insert = $this->pdo->prepare("INSERT INTO rsa_keypair (key_name, public_key, private_key) VALUES ('admin_master', :pub, :priv)");
        $insert->execute([
            ':pub'  => $pubKeyOut,
            ':priv' => $privKeyOut
        ]);

        $this->publicKey = $pubKeyOut;
        $this->privateKey = $privKeyOut;
    }

    /**
     * Enkripsi seluruh konten admin (Simetris AES-256-CBC + Asimetris RSA-2048)
     */
    public function encryptAdminPayload(array $fields): array
    {
        // 1. Buat kunci simetris acak (256-bit / 32 byte) dan IV (16 byte)
        $aesKey = random_bytes(32);
        $ivLength = openssl_cipher_iv_length($this->cipherMethod);
        $iv = random_bytes($ivLength);

        // 2. Enkripsi setiap kolom konten halaman admin dengan AES-256-CBC (Simetris)
        $encryptedFields = [];
        foreach ($fields as $key => $plaintext) {
            $cipherRaw = openssl_encrypt(
                (string) $plaintext,
                $this->cipherMethod,
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            $encryptedFields[$key] = base64_encode($cipherRaw);
        }

        // 3. Enkripsi kunci AES menggunakan RSA Public Key (Asimetris)
        $encryptedAesKeyRaw = '';
        $ok = openssl_public_encrypt(
            $aesKey,
            $encryptedAesKeyRaw,
            $this->publicKey,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$ok) {
            throw new RuntimeException('Enkripsi RSA Public Key gagal.');
        }

        return [
            'data'              => $encryptedFields,
            'encrypted_aes_key' => base64_encode($encryptedAesKeyRaw),
            'aes_iv'            => base64_encode($iv)
        ];
    }

    /**
     * Dekripsi seluruh konten admin (Asimetris RSA-2048 -> Simetris AES-256-CBC)
     */
    public function decryptAdminRow(array $row): array
    {
        $encryptedAesKeyRaw = base64_decode($row['encrypted_aes_key']);
        $iv = base64_decode($row['aes_iv']);

        // 1. Dekripsi kunci AES menggunakan RSA Private Key (Asimetris)
        $aesKey = '';
        $ok = openssl_private_decrypt(
            $encryptedAesKeyRaw,
            $aesKey,
            $this->privateKey,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$ok) {
            throw new RuntimeException('Dekripsi RSA Private Key gagal.');
        }

        // 2. Dekripsi seluruh kolom konten halaman admin menggunakan kunci AES (Simetris)
        $columns = ['judul_enc', 'kategori_enc', 'isi_konten_enc', 'catatan_admin_enc'];
        $decrypted = [];

        foreach ($columns as $col) {
            $cipherRaw = base64_decode($row[$col]);
            $plain = openssl_decrypt(
                $cipherRaw,
                $this->cipherMethod,
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            $decrypted[$col] = $plain !== false ? $plain : '[Gagal Dekripsi]';
        }

        return $decrypted;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}