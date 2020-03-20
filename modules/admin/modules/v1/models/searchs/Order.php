<?php

namespace app\modules\admin\modules\v1\models\searchs;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\modules\admin\modules\v1\models\Order as OrderModel;

/**
 * Order represents the model behind the search form of `app\modules\admin\modules\v1\models\Order`.
 */
class Order extends OrderModel
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id', 'order_stat'], 'integer'],
            [['order_no', 'create_time'], 'safe'],
            [['order_amt'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = OrderModel::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'user_id' => $this->user_id,
            'order_amt' => $this->order_amt,
            'order_stat' => $this->order_stat,
        ]);

        preg_match_all('/\d{4}-\d{2}-\d{2}/', $this->create_time, $matches);
        if (!empty($matches[0][0])) {
            $startDatetime = date('Y-m-d H:i:s', strtotime($matches[0][0]));
            $endDatetime = date('Y-m-d H:i:s', (strtotime($matches[0][0] . ' +1 days') - 1));
        }
        if (!empty($matches[0][1])) {
            $endDatetime = date('Y-m-d H:i:s', (strtotime($matches[0][1] . ' +1 days') - 1));
        }
        if (!empty($startDatetime) && !empty($endDatetime)) {
            $query->andFilterWhere(['between', 'create_time', $startDatetime, $endDatetime]);
        }

        $query->andFilterWhere(['like', 'order_no', $this->order_no]);

        return $dataProvider;
    }
}
