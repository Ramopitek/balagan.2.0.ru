<?php
namespace app\modules\api\controllers;

use app\modules\api\models\db\BioUser;
use app\modules\api\models\forms\RegistrationForm;
use app\modules\api\models\forms\LoginForm;
use app\modules\api\models\forms\UploadForm;
use app\modules\api\models\db\BioDoctorPacientConnection;
use yii\base\Theme;
use yii\web\UploadedFile;
use app\modules\api\models\BioFileHelper;
use app\modules\api\models\db\BioNoticeTypes;
use app\modules\api\models\db\BioUserNotice;
use Yii;
use yii\imagine\Image;
use Imagine\Image\Box;

class UserController extends _ApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::className(),
            'cors' => [
                // restrict access to
                'Origin' => ['*'],
                'Access-Control-Request-Method' => ['POST', 'PUT'],
                // Allow only POST and PUT methods
                'Access-Control-Request-Headers' => ['X-Wsse'],
                // Allow only headers 'X-Wsse'
                'Access-Control-Allow-Credentials' => true,
                // Allow OPTIONS caching
                'Access-Control-Max-Age' => 3600,
                // Allow the X-Pagination-Current-Page header to be exposed to the browser.
                'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],
            ],
        ];
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $behaviors;
    }

    public function beforeAction($action)
    {
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    public function actionIndex($access_token)
    {
        $user = BioUser::findByAccessToken($access_token);
        return $user;
    }

    public function actionLogin()
    {
        $model = new LoginForm();
        $model->setAttributes(Yii::$app->request->post());
        if ($model->validate()) {
            return [
                'success' => true,
                'result' => $model->getUser(),
            ];
        } else {
            return [
                'success' => false,
                'result' => $model->errors,
            ];
        }
    }


    public function actionUser()
    {
        if(!empty($this->user)){
            return [
                'success' => true,
                'result' => $this->user->getUser($this->user->username),
            ];
        } else {
            return [
                'success' => false,
            ];
        }
    }

    public function actionRegister()
    {
        $scenario = Yii::$app->request->post('type') == 'doctor' ? 'doctor' : 'pacient';
        $model = new RegistrationForm(['scenario' => $scenario]);
        $model->setAttributes(Yii::$app->request->post());
        if ($model->validate() && $model->register()) {
            return [
                'success' => true,
                'result' => $model->getUserInfo(),
            ];
        } else {
            return [
                'success' => false,
                'result' => $model->getErrors(),
            ];
        }
    }

    public function actionEdituser()
    {
        if (!empty($this->user)) {
            $this->user->setAttributes(Yii::$app->request->post());
            if($this->user->validate() /*&& $this->user->update()*/){
                return [
                    'success' => true,
                    'result' => $this->user->getUserInfoById($this->user->id),
                ];
            } else {
                return [
                    'success' => false,
                    'result' => $this->user->getErrors(),
                ];
            }

        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionUpload()
    {
        if (!empty($this->user)) {
            if (Yii::$app->request->isPost) {
                $dir = BioUser::getPhotoPath($this->user->path_key);
                BioFileHelper::deleteMainSymbols($dir); // create if not exist inside
                $filePath = Yii::getAlias('@app').'/uploads'.$dir;
                $file = explode('.', $_FILES["file"]["name"]);
                if (copy($_FILES["file"]["tmp_name"], $filePath . '/user_avatar_big_'.$this->user->id.'.'.$file[1])) {
                    $photo = Image::getImagine()->open($filePath . '/' . $_FILES["file"]["name"]);
                    $photo->thumbnail(new Box(59, 59))->save($filePath. '/user_avatar_min_'.$this->user->id.'.'.$file[1], ['quality' => 90]);
                    return [
                        'success' => true,
                    ];
                } else {
                    return [
                        'success' => false,
                    ];
                }
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionCreateconnectionrequest()
    {
        if (!empty($this->user)) {
            $model = new BioDoctorPacientConnection();
            $model->setAttributes(Yii::$app->request->post());
            if ($model->validate()) {
                if(!in_array($this->user->id, array(Yii::$app->request->post('doctor_id'), Yii::$app->request->post('pacient_id')))){
                    return [
                        'success' => false,
                        'result' => 'You do not have permission'
                    ];
                }
                $model->save();

                $notice = new BioUserNotice();
                $notice->user_id = Yii::$app->request->post('pacient_id');
                $notice->read = 0;
                $notice->notice_type_id = 1;
                $notice->c_time = new \yii\db\Expression('NOW()');
                $notice->extra_data = json_encode(['doctor_id' => Yii::$app->request->post('doctor_id')]);
                $notice->save();

                return [
                    'success' => true,
                    'result' => $model->attributes
                ];
            } else {
                return [
                    'success' => false,
                    'result' => $model->getErrors(),
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionDisconnectpacientfromdoctor()
    {
        if (!empty($this->user)) {
            $model = new BioDoctorPacientConnection();
            $model->setAttributes(Yii::$app->request->post());
            $result = $model->findByPacientAndDoctor();
            if ($result) {
                if(!in_array($this->user->id, array(Yii::$app->request->post('doctor_id'), Yii::$app->request->post('pacient_id')))){
                    return [
                        'success' => false,
                        'result' => 'You do not have permission'
                    ];
                }
                $result->delete();

                $notice = new BioUserNotice();
                $notice->user_id = Yii::$app->request->post('doctor_id');
                $notice->read = 0;
                $notice->notice_type_id = 2;
                $notice->c_time = new \yii\db\Expression('NOW()');
                $notice->extra_data = json_encode(['pacient' => Yii::$app->request->post('pacient_id')]);
                $notice->save();

                $notice = new BioUserNotice();
                $notice->user_id = Yii::$app->request->post('pacient_id');
                $notice->read = 0;
                $notice->notice_type_id = 2;
                $notice->c_time = new \yii\db\Expression('NOW()');
                $notice->extra_data = json_encode(['doctor_id' => Yii::$app->request->post('doctor_id')]);
                $notice->save();

                return [
                    'success' => true,
                ];
            } else {
                return [
                    'success' => false,
                    'result' => 'Connect does not exist'
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionApproveconnection()
    {
        if (!empty($this->user)) {
            $model = new BioDoctorPacientConnection();
            $model->setAttributes(Yii::$app->request->post());
            $result = $model->findByPacientAndDoctor();
            if ($result) {
                if(!in_array($this->user->id, array(Yii::$app->request->post('doctor_id'), Yii::$app->request->post('pacient_id')))){
                    return [
                        'success' => false,
                        'result' => 'You do not have permission'
                    ];
                }
                $result->approved = 1;
                $result->save();

                $notice = new BioUserNotice();
                $notice->user_id = Yii::$app->request->post('doctor_id');
                $notice->read = 0;
                $notice->notice_type_id = 3;
                $notice->c_time = new \yii\db\Expression('NOW()');
                $notice->extra_data = json_encode(['pacient' => Yii::$app->request->post('pacient_id')]);
                $notice->save();

                $notice = new BioUserNotice();
                $notice->user_id = Yii::$app->request->post('pacient_id');
                $notice->read = 0;
                $notice->notice_type_id = 3;
                $notice->c_time = new \yii\db\Expression('NOW()');
                $notice->extra_data = json_encode(['doctor_id' => Yii::$app->request->post('doctor_id')]);
                $notice->save();

                return [
                    'success' => true,
                    'result' => $result->attributes
                ];
            } else {
                return [
                    'success' => false,
                    'result' => 'Connect does not exist'
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionGetdoctorspacients()
    {
        if (!empty($this->user)) {
            if (!empty(Yii::$app->request->post('doctor_id'))) {
                $condition = [
                    'doctor_id' => Yii::$app->request->post('doctor_id'),
                    'enabled' => ''
                ];

                if(Yii::$app->request->post('enabled') != 'all')
                {
                    $condition['enabled'] = Yii::$app->request->post('enabled');
                } else {
                    unset($condition['enabled']);
                }

                $connectionList= BioDoctorPacientConnection::findAll($condition);
                if (!empty($connectionList)) {
                    $pacientList = [];
                    foreach ($connectionList as $connection){
                        $pacientList[$connection->pacient_id] = BioUser::getUserInfoById($connection->pacient_id);
                    }
                    return [
                        'success' => true,
                        'result' => $pacientList
                    ];
                } else {
                    return [
                        'success' => false,
                        'result' => 'doctor_id can not be blank'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'result' => 'No results'
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionGetpacientdoctors()
    {
        if (!empty($this->user)) {
            if (!empty(Yii::$app->request->post('pacient_id'))) {
                $condition = [
                    'pacient_id' => Yii::$app->request->post('pacient_id'),
                    'enabled' => ''
                ];

                if(Yii::$app->request->post('enabled') != 'all')
                {
                    $condition['enabled'] = Yii::$app->request->post('enabled');
                } else {
                    unset($condition['enabled']);
                }

                $connectionList= BioDoctorPacientConnection::findAll($condition);
                if (!empty($connectionList)) {
                    $doctorList = [];
                    foreach ($connectionList as $connection){
                        $doctorList[$connection->doctor_id] = BioUser::getUserInfoById($connection->doctor_id);
                    }
                    return [
                        'success' => true,
                        'result' => $doctorList
                    ];
                } else {
                    return [
                        'success' => false,
                        'result' => 'doctor_id can not be blank'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'result' => 'No results'
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionGetusernotices()
    {
        if (!empty($this->user)) {
            $condition = [
                'user_id' => $this->user->id,
                'read' => ''
            ];
            if(Yii::$app->request->post('read') != 'all')
            {
                $condition['read'] = Yii::$app->request->post('read');
            } else {
                unset($condition['read']);
            }
            $notices = BioUserNotice::findAll($condition);
            $result = [];
            if(!empty($notices)){
                foreach ($notices as $notice){
                    $data['read'] = $notice->read;
                    $data['notice_id'] = $notice->notice_id;
                    $data['notice_type'] = BioNoticeTypes::findOne(['notice_type_id' => $notice->notice_type_id])->name;
                    $data['extra_data'] = json_decode($notice->extra_data, true);
                    $data['c_time'] = $notice->c_time;
                    $result['notice_id'] = $data;
                }
            }
            return [
                'success' => true,
                'result' => $result
            ];
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionDeleteusernotice()
    {
        if (!empty($this->user)) {
            if (!empty(Yii::$app->request->post('notice_id'))) {
                $notice = BioUserNotice::findOne(['notice_id' => Yii::$app->request->post('notice_id')]);
                if(!empty($notice)){

                    if(!in_array($this->user->id, array($notice->user_id))){
                        return [
                            'success' => false,
                            'result' => 'You do not have permission'
                        ];
                    }

                    if($notice->delete()){
                        return [
                            'success' => true,
                        ];
                    } else {
                        return [
                            'success' => false,
                            'result' => 'Notice not deleted'
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'result' => 'Notice does not exist'
                    ];
                }

            } else {
                return [
                    'success' => false,
                    'result' => 'Notice does not exist'
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }

    public function actionSetnoticeread()
    {
        if (!empty($this->user)) {
            if (!empty(Yii::$app->request->post('notice_id'))) {
                $notice = BioUserNotice::findOne(['notice_id' => Yii::$app->request->post('notice_id')]);
                if(!empty($notice)){

                    if(!in_array($this->user->id, array($notice->user_id))){
                        return [
                            'success' => false,
                            'result' => 'You do not have permission'
                        ];
                    }

                    $notice->read = 1;
                    if($notice->update()){
                        return [
                            'success' => true,
                        ];
                    } else {
                        return [
                            'success' => false,
                            'result' => 'Notice not deleted'
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'result' => 'Notice does not exist'
                    ];
                }

            } else {
                return [
                    'success' => false,
                    'result' => 'Notice does not exist'
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'User does not exist'
            ];
        }
    }
}