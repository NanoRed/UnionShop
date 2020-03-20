<?php

namespace app\modules\logis\modules\v1\erps\wangdiantong\libs\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\logis\modules\v1\exceptions\LogisException;

use GuzzleHttp\Client;

class Crypt extends Behavior
{
    /**
     * 计算签名
     * @param $reqParams
     * @return string
     */
    public function calSign($reqParams)
    {
        $signString = '';
        ksort($reqParams);
        foreach ($reqParams as $key => $value) {
            $signString .= str_pad(iconv_strlen($key, 'UTF-8'), 2, '0');
            $signString .= '-' . $key . ':';
            $signString .= str_pad(iconv_strlen($value, 'UTF-8'), 4, '0');
            $signString .= '-' . $value . ';';
        }
        $signString = rtrim($signString, ';') . $this->owner->params['appSecret'];

        return md5($signString);
    }

    /**
     * 接口请求
     * @param $reqParams
     * @param $api
     * @return mixed
     * @throws LogisException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($reqParams, $api)
    {
        // 公共参数
        $reqParams['sid'] = $this->owner->params['sid'];
        $reqParams['appkey'] = $this->owner->params['appKey'];
        $reqParams['timestamp'] = time();
        $reqParams['sign'] = $this->calSign($reqParams);

        // GuzzleHttp请求
        $client = new Client([
            'base_uri' => $this->owner->params['baseUrl'],
            'timeout'  => 5,
        ]);
        $reqBody = urldecode(http_build_query($reqParams));
        $response = $client->request('POST', $api, ['verify' => false, 'body' => $reqBody]);
        $respContent = $response->getBody()->getContents();
        $result = json_decode($respContent, true);
        if (isset($result['code']) && $result['code'] === 0) {
            return $result;
        } else {
            Yii::info($reqBody . ' ' . $respContent, __METHOD__);
            throw new LogisException(isset($result['message']) ? $result['message'] : '未知请求错误');
        }
    }
}
