<?php

namespace app\modules\admin\modules\v1\components;

use yii\caching\TagDependency;
use mdm\admin\components\Configs;
use app\modules\admin\modules\v1\models\Channel;

class ChannelHelper
{
    /**
     * 获取授权渠道
     * @param $userId
     * @param bool $refresh
     * @return array
     */
    public static function getAssignedChannel($userId, $refresh = false)
    {
        $config = Configs::instance();

        $manager = Configs::authManager();
        /* @var $manager \app\modules\admin\modules\v1\rbac\DbManager */
        $key = [__METHOD__, $userId, $manager->defaultRoles];
        $cache = $config->cache;

        if ($refresh || $cache === null || ($assigned = $cache->get($key)) === false) {
            $assigned = [];
            $channelIds = array_column($manager->getChannelsByUser($userId), 'data');
            if (!empty($channelIds)) {
                $assigned = Channel::find()
                    ->select(['id', 'name', 'alias'])
                    ->where(['IN', 'id', $channelIds])
                    ->asArray()
                    ->all();
            }
            if ($cache !== null) {
                $cache->set($key, $assigned, $config->cacheDuration, new TagDependency([
                    'tags' => Configs::CACHE_TAG
                ]));
            }
        }

        return $assigned;
    }
}
