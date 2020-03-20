<?php

namespace app\modules\admin\modules\v1\models;

use mdm\admin\components\Configs;
use mdm\admin\components\Helper;
use Yii;

class Assignment extends \mdm\admin\models\Assignment
{
    /**
     * @inheritdoc
     */
    public function assign($items)
    {
        $manager = Configs::authManager();
        /* @var $manager \app\modules\admin\modules\v1\rbac\DbManager */
        $success = 0;
        foreach ($items as $name) {
            try {
                $item = $manager->getChannel($name);
                $item = $item ?: $manager->getRole($name);
                $item = $item ?: $manager->getPermission($name);
                $manager->assign($item, $this->id);
                $success++;
            } catch (\Exception $exc) {
                Yii::error($exc->getMessage(), __METHOD__);
            }
        }
        Helper::invalidate();
        return $success;
    }

    /**
     * @inheritdoc
     */
    public function revoke($items)
    {
        $manager = Configs::authManager();
        /* @var $manager \app\modules\admin\modules\v1\rbac\DbManager */
        $success = 0;
        foreach ($items as $name) {
            try {
                $item = $manager->getChannel($name);
                $item = $item ?: $manager->getRole($name);
                $item = $item ?: $manager->getPermission($name);
                $manager->revoke($item, $this->id);
                $success++;
            } catch (\Exception $exc) {
                Yii::error($exc->getMessage(), __METHOD__);
            }
        }
        Helper::invalidate();
        return $success;
    }

    /**
     * @inheritdoc
     */
    public function getItems()
    {
        $manager = Configs::authManager();
        /* @var $manager \app\modules\admin\modules\v1\rbac\DbManager */
        $available = [];
        foreach (array_keys($manager->getRoles()) as $name) {
            $available[$name] = 'role';
        }

        foreach (array_keys($manager->getPermissions()) as $name) {
            if ($name[0] != '/') {
                $available[$name] = 'permission';
            }
        }

        foreach (array_keys($manager->getChannels()) as $name) {
            $available[$name] = 'channel';
        }

        $assigned = [];
        foreach ($manager->getAssignments($this->id) as $item) {
            $assigned[$item->roleName] = $available[$item->roleName];
            unset($available[$item->roleName]);
        }

        return [
            'available' => $available,
            'assigned' => $assigned,
        ];
    }
}
