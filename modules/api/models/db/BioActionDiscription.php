<?php

namespace app\modules\api\models\db;

use Yii;

/**
 * This is the model class for table "{{%bio_action_discription}}".
 *
 * @property integer $id_discription
 * @property integer $id_action
 * @property string $disctiption
 */
class BioActionDiscription extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%bio_action_discription}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_discription'], 'required'],
            [['id_discription', 'id_action'], 'integer'],
            [['disctiption'], 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_discription' => 'Id Discription',
            'id_action' => 'Id Action',
            'disctiption' => 'Disctiption',
        ];
    }
}
