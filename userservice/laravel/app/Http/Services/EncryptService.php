<?php

namespace App\Http\Services;

use App\Http\Services\Filter\Encryption;

class EncryptService
{
    private $encrypt;

    public function __construct(Encryption $encrypt)
    {
        $this->encrypt = $encrypt;
    }

    public function isValidator($sign, $apikey, $timestamp): bool
    {
        if (empty($sign) || $timestamp + 600000 < time() * 1000) {
            return false;
        }

        $secret = $this->encrypt->encrypt($apikey);
        $postHash = hash_hmac('sha256', $apikey . $timestamp, $secret);

        return $sign === $postHash;
    }

    public function createCredential($apikey, $timestamp): string
    {
        $secret = $this->encrypt->encrypt($apikey);
        $postHash = hash_hmac('sha256', $apikey . $timestamp, $secret);

        return $postHash;
    }

    public function apikeyGen(): string
    {
        return $this->encrypt->quickRandom(32);
    }
}
