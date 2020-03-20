<?php

namespace app\modules\admin\modules\v1\models;

use Yii;
use mdm\admin\components\Configs;
use mdm\admin\components\Helper;
use yii\helpers\Json;
use app\modules\admin\modules\v1\rbac\Channel as Item;

class AuthItem extends \mdm\admin\models\AuthItem
{
    /**
     * @inheritdoc
     */
    private $_item;

    /**
     * @inheritdoc
     */
    public function __construct($item = null, $config = [])
    {
        $this->_item = $item;
        parent::__construct($item, $config);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        $attributeLabels = parent::attributeLabels();
        $attributeLabels['data'] = '对应渠道';

        return $attributeLabels;
    }

    /**
     * @inheritdoc
     */
    public function save()
    {
        if ($this->validate()) {
            $manager = Configs::authManager();
            /* @var $manager \app\modules\admin\modules\v1\rbac\DbManager */
            if ($this->_item === null) {
                switch ($this->type) {
                    case Item::TYPE_ROLE:
                        $this->_item = $manager->createRole($this->name);
                        break;
                    case Item::TYPE_CHANNEL:
                        $this->_item = $manager->createChannel($this->name);
                        break;
                    default:
                        $this->_item = $manager->createPermission($this->name);
                }
                $isNew = true;
            } else {
                $isNew = false;
                $oldName = $this->_item->name;
            }
            $this->_item->name = $this->name;
            $this->_item->type = $this->type;
            $this->_item->description = $this->description;
            $this->_item->ruleName = $this->ruleName;
            $this->_item->data = $this->data === null || $this->data === '' ? null : Json::decode($this->data);
            if ($isNew) {
                $manager->add($this->_item);
            } else {
                $manager->update($oldName, $this->_item);
            }
            Helper::invalidate();
            return true;
        } else {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function addChildren($items)
    {
        $manager = Configs::authManager();
        /* @var $manager \app\modules\admin\modules\v1\rbac\DbManager */
        $success = 0;
        if ($this->_item) {
            foreach ($items as $name) {
                switch ($this->type) {
                    case Item::TYPE_ROLE:
                        $child = $manager->getPermission($name);
                        $child = $child ?: $manager->getChannel($name);
                        $child = $child ?: $manager->getRole($name);
                        break;
                    case Item::TYPE_CHANNEL:
                        $child = $manager->getChannel($name);
                        break;
                    default:
                        $child = $manager->getPermission($name);
                }
                try {
                    $manager->addChild($this->_item, $child);
                    $success++;
                } catch (\Exception $exc) {
                    Yii::error($exc->getMessage(), __METHOD__);
                }
            }
        }
        if ($success > 0) {
            Helper::invalidate();
        }
        return $success;
    }

    /**
     * @inheritdoc
     */
    public function removeChildren($items)
    {
        $manager = Configs::authManager();
        /* @var $manager \app\modules\admin\modules\v1\rbac\DbManager */
        $success = 0;
        if ($this->_item !== null) {
            foreach ($items as $name) {
                switch ($this->type) {
                    case Item::TYPE_ROLE:
                        $child = $manager->getPermission($name);
                        $child = $child ?: $manager->getChannel($name);
                        $child = $child ?: $manager->getRole($name);
                        break;
                    case Item::TYPE_CHANNEL:
                        $child = $manager->getChannel($name);
                        break;
                    default:
                        $child = $manager->getPermission($name);
                }
                try {
                    $manager->removeChild($this->_item, $child);
                    $success++;
                } catch (\Exception $exc) {
                    Yii::error($exc->getMessage(), __METHOD__);
                }
            }
        }
        if ($success > 0) {
            Helper::invalidate();
        }
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
        $break = true;
        switch ($this->type) {
            case Item::TYPE_ROLE:
                foreach (array_keys($manager->getRoles()) as $name) {
                    $available[$name] = 'role';
                }
                $break = false;
            case Item::TYPE_CHANNEL:
                foreach (array_keys($manager->getChannels()) as $name) {
                    $available[$name] = 'channel';
                }
                if ($break) break;
            default:
                foreach (array_keys($manager->getPermissions()) as $name) {
                    $available[$name] = $name[0] == '/' ? 'route' : 'permission';
                }
        }

        $assigned = [];
        foreach ($manager->getChildren($this->_item->name) as $item) {
            switch ($item->type) {
                case Item::TYPE_ROLE:
                    $assigned[$item->name] = 'role';
                    break;
                case Item::TYPE_CHANNEL:
                    $assigned[$item->name] = 'channel';
                    break;
                default:
                    $assigned[$item->name] = $item->name[0] == '/' ? 'route' : 'permission';
            }
            unset($available[$item->name]);
        }
        unset($available[$this->name]);
        return [
            'available' => $available,
            'assigned' => $assigned,
        ];
    }
}
