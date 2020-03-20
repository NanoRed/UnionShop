<?php

namespace app\modules\pay\modules\v1\payments\h5wechat\libs\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\pay\modules\v1\exceptions\PayException;

use GuzzleHttp\Client;

/**
 * 微信H5支付对接
 * Class Crypt
 * @package app\modules\pay\modules\v1\payments\h5wechat\libs\behaviors
 */
class Crypt extends Behavior
{
    /**
     * 计算签名
     * @param $reqParams
     * @return string
     */
    public function calSign($reqParams)
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $signKey = isset($this->owner->params['merchant'][$this->owner->merchantId][$channelAlias]) ?
            $this->owner->params['merchant'][$this->owner->merchantId][$channelAlias]['key'] :
            $this->owner->params['merchant'][$this->owner->merchantId]['default']['key'];
        $signString = urldecode(http_build_query($reqParams)) . '&key=' . $signKey;
        return strtoupper(md5($signString));
    }

    /**
     * 接口请求
     * @param $reqParams
     * @param $api
     * @param bool $ssl
     * @return mixed
     * @throws PayException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($reqParams, $api, $ssl = false)
    {
        ksort($reqParams);
        $reqParams['sign'] = $this->calSign($reqParams);
        $xml = '<xml>';
        foreach ($reqParams as $key => $value) {
            $xml .= "<{$key}>" . $value . "</{$key}>";
        }
        $xml .= '</xml>';

        // GuzzleHttp请求
        $client = new Client([
            'base_uri' => $this->owner->params['baseUrl'],
            'timeout'  => 5,
        ]);
        if ($ssl) {
            $options = [
                'verify' => true,
                'cert' => __DIR__ . '/../certs/apiclient_cert.pem',
                'ssl_key' => __DIR__ . '/../certs/apiclient_key.pem',
                'body' => $xml
            ];
        } else {
            $options = ['verify' => false, 'body' => $xml];
        }
        $response = $client->request('POST', $api, $options);
        $xmlContent = $response->getBody()->getContents();
        libxml_disable_entity_loader(true);
        $result = json_decode(
            json_encode(simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA)),
            true
        );

        // 响应验证
        if (isset($result['return_code']) && $result['return_code'] == 'SUCCESS') {
            $getSign = $result['sign'];
            unset($result['sign']);
            ksort($result);
            if ($this->calSign($result) != $getSign) {
                Yii::error($xml . ' ' . $xmlContent, __METHOD__);
                throw new PayException('响应验签失败！');
            }
        } else {
            Yii::error($xml . ' ' . $xmlContent, __METHOD__);
            throw new PayException(isset($result['return_msg']) ? $result['return_msg'] : '未知请求错误');
        }

        return $result;
    }
}
