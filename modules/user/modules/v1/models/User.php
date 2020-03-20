<?php

namespace app\modules\user\modules\v1\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\web\IdentityInterface;
use yii\filters\RateLimitInterface;
use yii\base\UnknownMethodException;

class User extends ActiveRecord implements IdentityInterface, RateLimitInterface
{
    public static function tableName()
    {
        return '{{%' . Yii::$app->getModule('channel/v1')->processor->channelId . '_users}}';
    }

    const FIBC_IF_NOT_GENERATE = 0x01; // 用户不存在则创建
    const FIBC_SET_ACCESSTOKEN = 0x02; // 设置AccessToken

    /**
     * 根据渠道用户标识返回身份信息
     * @param $cuid
     * @param null $type
     * @return User|null
     * @throws \yii\db\Exception
     */
    public static function findIdentityByCuid($cuid, $type = null)
    {
        $identity = static::findOne(['cuid' => $cuid]);

        if ($type & static::FIBC_IF_NOT_GENERATE && empty($identity)) {
            $tableName = static::getDb()->schema->getRawTableName(static::tableName());
            static::getDb()->createCommand()->insert($tableName, [
                'uuid' => new Expression('REPLACE(UUID(), "-", "")'),
                'cuid' => $cuid,
                'create_time' => date('Y-m-d H:i:s'),
            ])->execute();

            $identity = static::findOne(['cuid' => $cuid]);
        }

        if ($type & static::FIBC_SET_ACCESSTOKEN && !empty($identity)) {
            $identityReplica = clone $identity;
            $identity->generateAccessToken = function () use ($identityReplica) {
                $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
                $accessToken = md5(uniqid($identityReplica->id, true));
                $key = Yii::$app->id . md5(__CLASS__ . $channelAlias . $accessToken);
                Yii::$app->redis->setex($key, 3600*6, serialize($identityReplica)); // 登陆一次有效期6小时

                return $accessToken;
            }; // 注册一个生成AccessToken函数，由生成的函数返回AccessToken，此函数仅返回用户信息
        }

        return $identity;
    }

    private $generateAccessToken; // 注册闭包函数

    /**
     * 生成用户AccessToken
     * @return mixed
     * @throws \yii\base\UnknownMethodException
     */
    public function getAccessToken() {
        if ($this->generateAccessToken instanceof \Closure) {
            $closure = $this->generateAccessToken;
            return $closure();
        } else {
            throw new UnknownMethodException('Calling unknown method: ' . __METHOD__ . '() 请注册生成access token的闭包函数');
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id) {}

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $key = Yii::$app->id . md5(__CLASS__ . $channelAlias . $token);

        return unserialize(Yii::$app->redis->get($key));
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey() {}

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey) {}

    const REQUEST_LIMIT = 100;  // 区间内限制的请求次数
    const LIMIT_INTERVAL = 600; // 限制请求的时间区间

    /**
     * {@inheritdoc}
     */
    public function getRateLimit($request, $action)
    {
        return [static::REQUEST_LIMIT, static::LIMIT_INTERVAL];
    }

    /**
     * {@inheritdoc}
     */
    public function loadAllowance($request, $action)
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $api = get_class($action->controller) . '::' . $action->actionMethod;
        $userId = $this->getId();
        $key = Yii::$app->id . md5($channelAlias . $api . $userId);
        $allowance = json_decode(Yii::$app->redis->get($key), true);
        if (empty($allowance)) {
            $allowance = [static::REQUEST_LIMIT, time()];
        }
        return $allowance;
    }

    /**
     * {@inheritdoc}
     */
    public function saveAllowance($request, $action, $allowance, $timestamp)
    {
        $channelAlias = Yii::$app->getModule('channel/v1')->processor->channelAlias;
        $api = get_class($action->controller) . '::' . $action->actionMethod;
        $userId = $this->getId();
        $key = Yii::$app->id . md5($channelAlias . $api . $userId);
        Yii::$app->redis->setex($key, static::LIMIT_INTERVAL, json_encode([$allowance, $timestamp]));
    }
}
