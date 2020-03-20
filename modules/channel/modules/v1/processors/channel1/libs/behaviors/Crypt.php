<?php

namespace app\modules\channel\modules\v1\processors\channel1\libs\behaviors;

use yii\base\Behavior;

/**
 * 渠道实例一对接
 * Class Crypt
 * @package app\modules\channel\modules\v1\processors\channel1\libs\behaviors
 */
class Crypt extends Behavior
{
    /**
     * 登陆Token参数私钥解密
     * @param $cipherText
     * @param $privateKey
     * @return string
     */
    public function loginTokenDecrypt($cipherText, $privateKey)
    {
        // 私钥格式化
        if ($privateKey{0} != '-') {
            $privateKey = $this->formatPrivateKey($privateKey);
        }

        // 加载私钥
        $privateKey = openssl_pkey_get_private($privateKey);

        // 分段解密
        $plainText = '';
        foreach (str_split(base64_decode(hex2bin($cipherText)), 128) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $privateKey);
            $plainText .= $decryptData;
        }

        // 释放密钥
        openssl_free_key($privateKey);

        return $plainText;
    }

    /**
     * 登陆Signature验签
     * @param $plainText
     * @param $signature
     * @param $publicKey
     * @return bool
     */
    public function loginSignVerify($plainText, $signature, $publicKey)
    {
        // 公钥格式化
        if ($publicKey{0} != '-') {
            $publicKey = $this->formatPublicKey($publicKey);
        }

        // 加载公钥
        $publicKey = openssl_pkey_get_public($publicKey);

        // 验签
        $ret = openssl_verify(sha1($plainText), hex2bin($signature), $publicKey, OPENSSL_ALGO_MD5);

        // 释放密钥
        openssl_free_key($publicKey);

        return $ret == 1;
    }

    /**
     * 格式化私钥
     * @param $key
     * @return string
     */
    public function formatPrivateKey($key)
    {
        if (strpos($key, '-') === false) {
            $key = chunk_split($key, 64, "\n");
            $key = "-----BEGIN PRIVATE KEY-----\n" . $key . "-----END PRIVATE KEY-----";
        }

        return $key;
    }

    /**
     * 格式化公钥
     * @param $key
     * @return string
     */
    public function formatPublicKey($key)
    {
        if (strpos($key, '-') === false) {
            $key = chunk_split($key, 64, "\n");
            $key = "-----BEGIN PUBLIC KEY-----\n" . $key . "-----END PUBLIC KEY-----";
        }

        return $key;
    }
}
