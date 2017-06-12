<?php
namespace app\modules\api\models\forms;

use yii\base\Model;
use yii\web\UploadedFile;

/**
 * UploadForm is the model behind the upload form.
 */
class UploadForm extends Model
{
    /**
     * @var UploadedFile|Null file attribute
     */
    public $file;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['file'], 'image', 'skipOnEmpty' => false, 'mimeTypes' => 'image/jpeg, image/png'],
        ];
    }
}

?>