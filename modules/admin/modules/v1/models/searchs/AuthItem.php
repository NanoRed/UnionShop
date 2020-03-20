<?php

namespace app\modules\admin\modules\v1\models\searchs;

use yii\data\ArrayDataProvider;
use mdm\admin\components\Configs;
use app\modules\admin\modules\v1\rbac\Channel as Item;

class AuthItem extends \mdm\admin\models\searchs\AuthItem
{
    /**
     * @inheritdoc
     */
    public function search($params)
    {
        /* @var \app\modules\admin\modules\v1\rbac\DbManager $authManager */
        $authManager = Configs::authManager();
        switch ($this->type) {
            case Item::TYPE_ROLE:
                $items = $authManager->getRoles();
                break;
            case Item::TYPE_CHANNEL:
                $items = $authManager->getChannels();
                break;
            default:
                $items = array_filter($authManager->getPermissions(), function($item) {
                    return $this->type == Item::TYPE_PERMISSION xor strncmp($item->name, '/', 1) === 0;
                });
                break;
        }
        $this->load($params);
        if ($this->validate()) {

            $search = mb_strtolower(trim($this->name));
            $desc = mb_strtolower(trim($this->description));
            $ruleName = $this->ruleName;
            foreach ($items as $name => $item) {
                $f = (empty($search) || mb_strpos(mb_strtolower($item->name), $search) !== false) &&
                    (empty($desc) || mb_strpos(mb_strtolower($item->description), $desc) !== false) &&
                    (empty($ruleName) || $item->ruleName == $ruleName);
                if (!$f) {
                    unset($items[$name]);
                }
            }
        }

        return new ArrayDataProvider([
            'allModels' => $items,
        ]);
    }
}
