<?php

namespace App\Http\Services\Filter;


class Encryption
{
    private $_key;
	/**
     * Encryption::__construct()
     *
     * @param mixed $key
     * @return
     */
    public function __construct()
    {
		$key = env('SITEKEY', '5b24fac8f223edb967e6d6ac484f5288');
        $this->_key = sha1($key);
        if (isset($key[64])) {
            $key = pack('H32', $this->_key);
        }

        if (! isset($key[63])) {
            $key = str_pad($key, 64, chr(0));
        }

        $this->_ipad = substr($key, 0, 64) ^ str_repeat(chr(0x36), 64);
        $this->_opad = substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64);
    }
	/**
     * Encryption::encrypt()
     *
     * @param mixed $val
     * @param mixed $iv
     * @return
     */
    public function encrypt($data, $iv = '')
    {
        $iv = empty($iv) ? substr($this->_key, 0, 16) : substr($iv, 0, 16);
        $data = openssl_encrypt($data, 'aes-256-cbc', $this->_key, 0, $iv);
        return strtr($data, '+/=', '-_,');
    }

    /**
     * Encryption::decrypt()
     *
     * @param mixed $val
     * @param mixed $iv
     * @return
     */
    public function decrypt($data, $iv = '')
    {
        $iv = empty($iv) ? substr($this->_key, 0, 16) : substr($iv, 0, 16);
        $data = strtr($data, '-_,', '+/=');
        return openssl_decrypt($data, 'aes-256-cbc', $this->_key, 0, $iv);
    }

    public static function quickRandom($length = 32)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }
}
