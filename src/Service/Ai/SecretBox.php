<?php

declare(strict_types=1);

namespace App\Service\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts designers' API keys at rest (libsodium secretbox).
 * The key comes from MAKER_ENCRYPTION_KEY; changing it makes stored keys unreadable.
 */
final class SecretBox
{
    private readonly string $key;

    public function __construct(
        #[Autowire('%env(MAKER_ENCRYPTION_KEY)%')]
        string $encryptionKey,
        #[Autowire('%kernel.secret%')]
        string $appSecret,
    ) {
        // Any string works: it is stretched to a 32-byte key; fall back to APP_SECRET
        $this->key = sodium_crypto_generichash($encryptionKey !== '' ? $encryptionKey : 'app-secret:'.$appSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('The stored API key is corrupted. Enter it again.');
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if ($plain === false) {
            throw new \RuntimeException('The stored API key cannot be decrypted (was MAKER_ENCRYPTION_KEY changed?). Enter it again.');
        }

        return $plain;
    }

    public static function hint(string $key): string
    {
        return \strlen($key) > 8 ? substr($key, 0, 4).'…'.substr($key, -4) : '…';
    }
}
