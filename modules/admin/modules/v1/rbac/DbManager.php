<?php

namespace app\modules\admin\modules\v1\rbac;

use yii\db\Query;
use yii\rbac\Role;
use yii\rbac\Permission;
use app\modules\admin\modules\v1\rbac\Channel as Item;

class DbManager extends \yii\rbac\DbManager
{
    /**
     * @inheritdoc
     */
    protected function populateItem($row)
    {
        switch ($row['type']) {
            case Item::TYPE_ROLE:
                $class = Role::className();
                break;
            case Item::TYPE_CHANNEL:
                $class = Channel::className();
                break;
            default:
                $class = Permission::className();
                break;
        }

        if (!isset($row['data']) || ($data = @unserialize(is_resource($row['data']) ? stream_get_contents($row['data']) : $row['data'])) === false) {
            $data = null;
        }

        return new $class([
            'name' => $row['name'],
            'type' => $row['type'],
            'description' => $row['description'],
            'ruleName' => $row['rule_name'] ?: null,
            'data' => $data,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ]);
    }

    /**
     * 创建渠道权限并返回
     * @param $name
     * @return Channel
     */
    public function createChannel($name)
    {
        $permission = new Channel();
        $permission->name = $name;
        return $permission;
    }

    /**
     * 返回渠道权限
     * @param $name
     * @return Item|\yii\rbac\Item|null
     */
    public function getChannel($name)
    {
        $item = $this->getItem($name);
        return $item instanceof Item && $item->type == Item::TYPE_CHANNEL ? $item : null;
    }

    /**
     * 返回所有渠道权限
     * @return array|\yii\rbac\Item[]
     */
    public function getChannels()
    {
        return $this->getItems(Item::TYPE_CHANNEL);
    }

    /**
     * 返回所有用户拥有的渠道权限
     * @param $userId
     * @return array
     */
    public function getChannelsByUser($userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return [];
        }

        $directChannel = $this->getDirectChannelsByUser($userId);
        $inheritedChannel = $this->getInheritedChannelsByUser($userId);

        return array_merge($directChannel, $inheritedChannel);
    }

    /**
     * 返回用户所有直接分配的渠道权限
     * @param $userId
     * @return array
     */
    protected function getDirectChannelsByUser($userId)
    {
        $query = (new Query())->select('b.*')
            ->from(['a' => $this->assignmentTable, 'b' => $this->itemTable])
            ->where('{{a}}.[[item_name]]={{b}}.[[name]]')
            ->andWhere(['a.user_id' => (string) $userId])
            ->andWhere(['b.type' => Item::TYPE_CHANNEL]);

        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }

        return $permissions;
    }

    /**
     * 返回所有用户被分配的角色所包含的渠道权限
     * @param $userId
     * @return array
     */
    protected function getInheritedChannelsByUser($userId)
    {
        $query = (new Query())->select('item_name')
            ->from($this->assignmentTable)
            ->where(['user_id' => (string) $userId]);

        $childrenList = $this->getChildrenList();
        $result = [];
        foreach ($query->column($this->db) as $roleName) {
            $this->getChildrenRecursive($roleName, $childrenList, $result);
        }

        if (empty($result)) {
            return [];
        }

        $query = (new Query())->from($this->itemTable)->where([
            'type' => Item::TYPE_CHANNEL,
            'name' => array_keys($result),
        ]);
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }

        return $permissions;
    }
}
