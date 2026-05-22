<?php

namespace App\Services\SatuSehat\Kyc;

use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\Random;

/**
 * Layanan enkripsi/dekripsi hybrid RSA-2048 + AES-256-GCM untuk KYC SatuSehat.
 * Port dari function.php library KYC resmi Kemenkes.
 */
class KycEncryptionService
{
    // Public key SatuSehat untuk environment development
    const SATUSEHAT_PUBLIC_KEY_DEV = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwqoicEXIYWYV3PvLIdvB\nqFkHn2IMhPGKTiB2XA56enpPb0UbI9oHoetRF41vfwMqfFsy5Yd5LABxMGyHJBbP\n+3fk2/PIfv+7+9/dKK7h1CaRTeT4lzJBiUM81hkCFlZjVFyHUFtaNfvQeO2OYb7U\nkK5JrdrB4sgf50gHikeDsyFUZD1o5JspdlfqDjANYAhfz3aam7kCjfYvjgneqkV8\npZDVqJpQA3MHAWBjGEJ+R8y03hs0aafWRfFG9AcyaA5Ct5waUOKHWWV9sv5DQXmb\nEAoqcx0ZPzmHJDQYlihPW4FIvb93fMik+eW8eZF3A920DzuuFucpblWU9J9o5w+2\noQIDAQAB\n-----END PUBLIC KEY-----";

    // Public key SatuSehat untuk environment production
    const SATUSEHAT_PUBLIC_KEY_PROD = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxLwvebfOrPLIODIxAwFp\n4Qhksdtn7bEby5OhkQNLTdClGAbTe2tOO5Tiib9pcdruKxTodo481iGXTHR5033I\nA5X55PegFeoY95NH5Noj6UUhyTFfRuwnhtGJgv9buTeBa4pLgHakfebqzKXr0Lce\n/Ff1MnmQAdJTlvpOdVWJggsb26fD3cXyxQsbgtQYntmek2qvex/gPM9Nqa5qYrXx\n8KuGuqHIFQa5t7UUH8WcxlLVRHWOtEQ3+Y6TQr8sIpSVszfhpjh9+Cag1EgaMzk+\nHhAxMtXZgpyHffGHmPJ9eXbBO008tUzrE88fcuJ5pMF0LATO6ayXTKgZVU0WO/4e\niQIDAQAB\n-----END PUBLIC KEY-----";

    /**
     * Generate pasangan kunci RSA-2048.
     * Return array ['private' => object, 'public' => string PEM]
     */
    public function generateRsaKeyPair(): array
    {
        $privateKey = RSA::createKey(2048);
        $publicKey = $privateKey->getPublicKey()->toString('PKCS8');

        return ['private' => $privateKey, 'public' => $publicKey];
    }

    /**
     * Enkripsi payload JSON menggunakan hybrid RSA-OAEP + AES-256-GCM.
     * Output berformat PEM "ENCRYPTED MESSAGE".
     */
    public function encryptPayload(string $json, string $satuSehatPublicKeyPem): string
    {
        $aesKey = Random::string(32);

        $serverKey = PublicKeyLoader::load($satuSehatPublicKeyPem);
        $serverKey = $serverKey->withPadding(RSA::ENCRYPTION_OAEP);
        $wrappedAesKey = $serverKey->encrypt($aesKey);

        $encryptedData = $this->aesGcmEncrypt($json, $aesKey);

        $payload = $wrappedAesKey . $encryptedData;

        return $this->formatAsPem($payload);
    }

    /**
     * Dekripsi response PEM "ENCRYPTED MESSAGE" menggunakan RSA private key.
     */
    public function decryptResponse(string $pemResponse, $privateKey): string
    {
        $beginTag = "-----BEGIN ENCRYPTED MESSAGE-----";
        $endTag = "-----END ENCRYPTED MESSAGE-----";

        $contents = substr(
            $pemResponse,
            strlen($beginTag) + 1,
            strlen($pemResponse) - strlen($endTag) - strlen($beginTag) - 2
        );

        $binary = base64_decode($contents);

        $wrappedKey = substr($binary, 0, 256);
        $encryptedData = substr($binary, 256);

        $key = PublicKeyLoader::load($privateKey);
        $aesKey = $key->decrypt($wrappedKey);

        return $this->aesGcmDecrypt($encryptedData, $aesKey);
    }

    /**
     * Enkripsi data dengan AES-256-GCM. Output: IV(12) + Ciphertext + Tag(16)
     */
    private function aesGcmEncrypt(string $data, string $key): string
    {
        $iv = random_bytes(12);

        $cipher = new AES('gcm');
        $cipher->setKeyLength(256);
        $cipher->setKey($key);
        $cipher->setNonce($iv);

        $ciphertext = $cipher->encrypt($data);
        $tag = $cipher->getTag();

        return $iv . $ciphertext . $tag;
    }

    /**
     * Dekripsi data AES-256-GCM dari format IV(12) + Ciphertext + Tag(16)
     */
    private function aesGcmDecrypt(string $encryptedData, string $key): string
    {
        $iv = substr($encryptedData, 0, 12);
        $tag = substr($encryptedData, -16);
        $ciphertext = substr($encryptedData, 12, -16);

        $cipher = new AES('gcm');
        $cipher->setKey($key);
        $cipher->setNonce($iv);
        $cipher->setTag($tag);

        return $cipher->decrypt($ciphertext);
    }

    /**
     * Bungkus binary data dalam format PEM "ENCRYPTED MESSAGE"
     */
    private function formatAsPem(string $data): string
    {
        $base64 = chunk_split(base64_encode($data));
        return "-----BEGIN ENCRYPTED MESSAGE-----\r\n{$base64}-----END ENCRYPTED MESSAGE-----";
    }
}
