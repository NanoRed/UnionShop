<?php

namespace app\modules\logis\modules\v1\tracks\kdniao\libs\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\logis\modules\v1\exceptions\LogisException;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class Crypt extends Behavior
{
    /**
     * 计算签名
     * @param $reqParams
     * @return string
     */
    public function calSign($reqParams)
    {
        $signString = json_encode($reqParams, JSON_UNESCAPED_UNICODE);
        $signString .= $this->owner->params['APIKey'];
        return base64_encode(md5($signString));
    }

    /**
     * 请求接口
     * @param $reqParams
     * @param $api
     * @return mixed
     * @throws LogisException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($reqParams, $api)
    {
        // 系统参数
        $reqParams = [
            'RequestData' => urlencode(json_encode($reqParams, JSON_UNESCAPED_UNICODE)),
            'EBusinessID' => $this->owner->params['EBusinessID'],
            'RequestType' => $this->owner->params[$api]['RequestType'],
            'DataSign' => urlencode($this->calSign($reqParams)),
            'DataType' => 2, // 2-JSON
        ];

        // GuzzleHttp请求
        $client = new Client(['timeout'  => 5]);
        $reqBody = urldecode(http_build_query($reqParams));
        $response = $client->request(
            'POST',
            $this->owner->params[$api]['Url'],
            [
                'verify' => false,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'
                ],
                'body' => $reqBody
            ]
        );
        $respContent = $response->getBody()->getContents();
        $result = json_decode($respContent, true);
        if (isset($result['Success']) && $result['Success'] === true) {
            return $result;
        } else {
            Yii::info($reqBody . ' ' . $respContent, __METHOD__);
            $errMessage = isset($result['Reason']) ?
                $result['Reason'] :
                (isset($result['Code']) ? (string)$result['Code'] : '未知请求错误');
            throw new LogisException($errMessage);
        }
    }

    /**
     * 请求接口（并发请求）
     * @param $reqParams
     * @param $api
     * @return array
     */
    public function batchRequest($reqParams, $api)
    {
        $client = new Client(['timeout'  => 3]);
        $requests = function ($reqParams, $api) {
            foreach ($reqParams as $value) {
                yield new Request(
                    'POST',
                    $this->owner->params[$api]['Url'],
                    [
                        'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'
                    ],
                    urldecode(http_build_query([
                        'RequestData' => urlencode(json_encode($value, JSON_UNESCAPED_UNICODE)),
                        'EBusinessID' => $this->owner->params['EBusinessID'],
                        'RequestType' => $this->owner->params[$api]['RequestType'],
                        'DataSign' => urlencode($this->calSign($value)),
                        'DataType' => 2, // 2-JSON
                    ]))
                );
            }
        };
        $result = [];
        $pool = new Pool($client, $requests($reqParams, $api), [
            'concurrency' => 10,
            'fulfilled' => function ($response, $index) use (&$result) {
                $respContent = $response->getBody()->getContents();
                $respData = json_decode($respContent, true);
                $result[$index] = $respData;
            },
            'rejected' => function ($reason, $index) use (&$result){
                $result[$index] = $reason->getMessage();
            },
            'options' => [
                'verify' => false
            ]
        ]);
        $promise = $pool->promise();
        $promise->wait();

        ksort($result);
        return $result;
    }
}
