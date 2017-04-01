<?php

namespace app\controllers;

use app\models\BioFileHelper;
use app\models\BlackActions;
use app\models\BlackResponse;
use app\models\BlackResult;
use app\models\db\BioActions;
use app\models\db\BioDistrict;
use app\models\db\BioResponse;
use app\models\db\BioUser;
use app\models\db\BioUserMeasure;
use app\models\db\BioMeasure;
use app\models\db\BioUserPacient;
use app\models\forms\RegistrationForm;
use app\models\forms\UploadForm;
use moonland\phpexcel\Excel;
use Yii;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\helpers\BaseFileHelper;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\forms\LoginForm;
use app\models\forms\ContactForm;
use yii\web\UploadedFile;

class AccountController extends Controller
{
    public $user;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::classname(),
                'only' => ['index', 'anketa', 'anketa_group', 'setvalue', 'get-result'],
                'rules' => [
                    [
                        'actions' => ['index', 'anketa', 'anketa_group', 'setvalue', 'get-result'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function beforeAction($action)
    {
        $this->user = BioUser::findByUserId(Yii::$app->user->getId());
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $time = time();
        $user = BioUser::findByUserId(Yii::$app->user->getId());
        $pacient = BioUserPacient::findByUserId(Yii::$app->user->getId());
        /* показать popup редкатирования профиля */
        $showEditProfilePopup = false;

        /* модель редактирования профиля */
        $editProfile = new RegistrationForm();
        /* обновим атрибуты для пациента - для формы */
        $editProfile->setAttributesPacient($user, $pacient);

        $postLoad = $editProfile->load(Yii::$app->request->post());
        $postRegister = $editProfile->register();
        if ($postLoad && $postRegister) {
            /* renew data */
            $user = BioUser::findByUserId(Yii::$app->user->getId());
            $pacient = BioUserPacient::findByUserId(Yii::$app->user->getId());
            //return $this->goBack();
        } else if ($postLoad && !$postRegister) { // ошибка регистрации
            $showEditProfilePopup = true;
        }

        /* название района проживания */
        $districtName = $editProfile->district_name;
        /* сколько лет */

        $ageOld = BioUserPacient::getPacientAge($pacient, 'y');

        /* to renew photo of account */
        $uplodPhotoModel = new UploadForm();


        /* TODO refactor this cOde!!! */
        /* avatar */
        $avatar = BioFileHelper::getMainFile(BioUser::getPhotoPath($user->path_key));
        //debug($avatar);
        if (!$avatar) $avatar = '/img/main-avatar.png';
        else $avatar = '/' . $avatar;


        return $this->render('index', [
            'editProfile' => $editProfile,
            'user' => $user,
            'pacient' => $pacient,
            'ageOld' => $ageOld,
            'districtName' => $districtName,
            'showEditProfilePopup' => $showEditProfilePopup,
            'uplodPhotoModel' => $uplodPhotoModel,
            'avatar' => $avatar
        ]);
    }


    public function actionUploadPhoto()
    {

        $model = new UploadForm();

        if (Yii::$app->request->isPost) {
            $model->file = UploadedFile::getInstance($model, 'file');

            if ($model->validate()) {
                $dir = BioUser::getPhotoPath($this->user['path_key']);
                BioFileHelper::deleteMainSymbols($dir); // create if not exist inside
                $model->file->saveAs($dir . BioFileHelper::$DIRECTORY_SEPARATOR . BioFileHelper::$MAIN_SUMBOL . uniqid() . '.' . $model->file->extension);

                return $this->redirect(['account/index']);
            }

            echo 'not validate<br>';
            print_r_pre($model->errors);
            die;
        }


        echo 'no post';
        die;

    }

    public function actionAnketa($id_parent = 0)
    {
        $pacient = BioUserPacient::findByUserId(Yii::$app->user->getId());
        $questionOptions = [
            'user_id' => Yii::$app->user->getId(),
            'male' => BioUserPacient::getPacientMale($pacient),
            'age' => BioUserPacient::getPacientAge($pacient, 'months')
        ];

        /* отображать вопросы смешанно , или строго раздельно группы от вопросов*/
        $MIXED = false;

        $data = [];

        $mGroups = new BioMeasure();
        /* получим блоки вопросов */
        $groups = $mGroups->groupGroups($id_parent, $questionOptions);

        $questions = array();
        /* сэкономим ресурсы сервера */
        if (!$groups && !$MIXED) {
            $mQuestions = new BioMeasure();
            /* получим вопросы */
            $questions = $mQuestions->groupGuestions($id_parent, $questionOptions);
        }
        /*print_r_pre($questions);
        die();*/
        /*print_r_pre($questionOptions);
        print_r_pre($groups);
        die;*/


        if ($MIXED) {
            $data['anketa_groups'] = '';
            if ($groups) {
                $data['anketa_groups'] = $this->renderPartial('anketa_groups', $this->dataAnketaQuestionGroups($groups, $id_parent));
            }

            $data['anketa_questions'] = '';
            if ($questions) {
                $data['anketa_questions'] = $this->renderPartial('anketa_questions', $this->dataAnketaQuestions($questions, $questionOptions, $id_parent));
            }
        } else {
            if ($groups) {
                /* отображать как горуппы вопросов */
                return $this->render('anketa_groups', $this->dataAnketaQuestionGroups($groups, $id_parent));
            } elseif ($questions) {
                /* отображать как вопросы */
                return $this->render('anketa_questions', $this->dataAnketaQuestions($questions, $questionOptions, $id_parent));
            }

        }


        if ($groups || $questions) {
            return $this->render('anketa_mixed', $data);
        } else {
            return $this->render('//site/error_build');
        }

    }

    /* контроллер отображающий вопросы как ГРУППЫ ВОПРОСОВ */
    public function dataAnketaQuestionGroups($groups, $id_parent = 0)
    {
        /* можно ли отправлять на расчет */
        $canSend = true;

        $measure = new BioMeasure();
        foreach ($groups as $index => $group) {
            $groups[$index]['answered'] = $measure->groupQuestionCountAnswered($group['id_measure'], Yii::$app->user->getId());
            $groups[$index]['answered']['proc'] = round(
                $groups[$index]['answered']['answered'] / $groups[$index]['answered']['need'] * 100
            );
            if ($groups[$index]['answered']['proc'] != 100) $canSend = false;
        }

        /*print_r_pre($questions);
        die();*/

        /* TODO  пофиксить 98% заполненности при 100% (блоки поле имеют скрытое), а пока костыль  - всегда отправить можно */
        $canSend = true;


        return array(
            'groups' => $groups,
            'canSend' => $canSend
        );
    }


    /* контроллер отображающий вопросы как СПИСОК ВОПРОСОВ */
    public function dataAnketaQuestions($questions, $questionOptions, $id_measure = 0)
    {
        if (!$id_measure) return $this->render('//site/error_build');

        $measure = new BioMeasure();

        $group = $measure->findMeasureById($id_measure);

        $next_group = $measure->findNextOfMeasure($group, $questionOptions);

        $prev_group = $measure->findPrevOfMeasure($group, $questionOptions);

        //$values = BioUserMeasure::getValues();


        return [
            'questions' => $questions,
            'group' => $group,
            'next_group' => $next_group,
            'prev_group' => $prev_group
        ];
    }

    /* THIS ACTION IS ON TESTING MODE */
    public function actionGetResult_RESPONSE_XML()
    {

        if (!@$_SESSION['responseResult']) {

            /* шаблон массива запроса на ящик */
            $data = [
                "jsonrpc" => "2.0",
                "method" => "calc",
                "params" => [
                    "male" => 1,
                    "birthday" => "2012-12-12",
                    "dist" => 1111148000,
                    "data" => [
                        "measure_id" => [],
                        "type_value" => [],
                        "value" => []
                    ]
                ],
                "id" => 0
            ];

            $allUM = BioUserMeasure::findAll(['user_id' => Yii::$app->user->getId()]);
            /* по шаблону заполним данные с базы данных */
            foreach ($allUM as $index => $um) {
                $temp = $um->toArray();
                $data['params']['data']['measure_id'][$index] = $temp['measure_id'];
                $data['params']['data']['type_value'][$index] = $temp['type_value'];
                $data['params']['data']['value'][$index] = ((int)$temp['type_value'] == 3)
                    ? json_decode($temp['value'])
                    : $temp['value'];
            }


            $url = "http://fcrisk.ru:30851/RemoteServer.php";
            //$url = "http://fcrisk.ru:30851/index.php";
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            $json_response = curl_exec($curl);
            $response = json_decode($json_response, true);
            /* результат расчетов с ящика */
            $responseResult = $response['result'];
            $_SESSION['responseResult'] = $responseResult;
        } else {
            $responseResult = $_SESSION['responseResult'];
        }
//
//        print_r_pre($responseResult);
//        die();


        /*  BLOCK RISKS */
        /* информация о болячках */
        $bioResponse = BioResponse::find()->where([])->asArray()->all();
        $preparedBioResponse = [];
        foreach ($bioResponse as $bioResp) {
            $preparedBioResponse[$bioResp['id_response']] = $bioResp;
        }

        /* подготвленная информация с сервера */
        $risksPrepared = [];
        /* массив заготовка */
        $risksFieldsNames = [
            'time_step' => 'vremennoy_shag',
            'P_fon' => 'veroyatnost_fonovaya',
            "P" => 'veroyantnost',
            "G" => 'tyazest',
            "R_fon" => 'risk_fonoviy',
            "R" => 'risk',
            "R_action" => 'risk_c_uchetom_meropriyat',
            "R_add" => 'dopolnitelniy_risk',
            "R_add_action" => 'dop_risk_c_uchetom_meropriyat',
            "R_index" => 'privedeniy_index_riska',
            "R_index_action" => 'prived_ind_risk_c_uchet_meropr',
        ];

        $risksFieldsNames = [
            'time_step' => 'Временной_шаг',
            'P_fon' => 'Вероятность_фоновая',
            "P" => 'Вероятность',
            "G" => 'Тяжесть',
            "R_fon" => 'Риск_фоновый',
            "R" => 'Риск',
            "R_action" => 'Риск_с_учетом_меропиятий',
            "R_add" => 'дополнительный_риск',
            "R_add_action" => 'доп_риск_с_учетом_меропиятий',
            "R_index" => 'приведенный_индекс_риска',
            "R_index_action" => 'привед_инд_риска_с_учет_меропр',
        ];
        foreach ($responseResult['risk'] as $id_response => $item) {
            $preparedResponse = [];
            foreach ($item['time_step'] as $i => $item_step) {
                $step = $risksFieldsNames;
                foreach ($step as $key => $val) {
                    $step[$key] = $item[$key][$i];
                }
                $preparedResponse[] = $step;
            }

            $risksPrepared[$id_response]['data'] = $preparedResponse;
            $risksPrepared[$id_response]['dataOriginal'] = $item;
            $risksPrepared[$id_response]['meta'] = $preparedBioResponse[$id_response];
            //$step++;
        }


        $preparedActions = ['isMultipleSheet' => true, 'models' => [], 'columns' => []];
        foreach ($risksPrepared as $id_response => $response) {

            $models = [];
            $first = true;
            foreach ($response['dataOriginal'] as $key => $value) {
                $attibutes = [];
                $ii = 20;
                foreach ($value as $i => $val) {
                    if ($response['dataOriginal']['time_step'][$i] < $ii) continue;
                    if ($first) {
                        $m = new BlackResponse();
                        $m->{$risksFieldsNames[$key]} = $val;
                        $models[$i] = $m;
                    } else {
                        $models[$i]->{$risksFieldsNames[$key]} = $val;
                    }

                    $ii++;
                }

                $first = false;
            }
            $nameF = substr(str_replace(array(',', ":", '"', "'", ".", "!"), '', $response['meta']['name']), 0, 30);
            $preparedActions['models'][$nameF] = $models;
            $preparedActions['columns'][$nameF] = [];
            foreach ($risksFieldsNames as $name) {
                $preparedActions['columns'][$nameF][] = $name;
            }
        }

        return $this->render('get_result_response', ['preparedActions' => $preparedActions]);

        /* END BLOCK RISKS */

    }

    /* THIS ACTION IS ON TESTING MODE */
    public function actionGetResult_ACTIONS_XML()
    {

        if (!@$_SESSION['responseResult']) {

            /* шаблон массива запроса на ящик */
            $data = [
                "jsonrpc" => "2.0",
                "method" => "calc",
                "params" => [
                    "male" => 1,
                    "birthday" => "2012-12-12",
                    "dist" => 1111148000,
                    "data" => [
                        "measure_id" => [],
                        "type_value" => [],
                        "value" => []
                    ]
                ],
                "id" => 0
            ];

            $allUM = BioUserMeasure::findAll(['user_id' => Yii::$app->user->getId()]);
            /* по шаблону заполним данные с базы данных */
            foreach ($allUM as $index => $um) {
                $temp = $um->toArray();
                $data['params']['data']['measure_id'][$index] = $temp['measure_id'];
                $data['params']['data']['type_value'][$index] = $temp['type_value'];
                $data['params']['data']['value'][$index] = ((int)$temp['type_value'] == 3)
                    ? json_decode($temp['value'])
                    : $temp['value'];
            }


            $url = "http://fcrisk.ru:30851/RemoteServer.php";
            //$url = "http://fcrisk.ru:30851/index.php";
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            $json_response = curl_exec($curl);
            $response = json_decode($json_response, true);
            /* результат расчетов с ящика */
            $responseResult = $response['result'];
            $_SESSION['responseResult'] = $responseResult;
        } else {
            $responseResult = $_SESSION['responseResult'];
        }
//
//        print_r_pre($responseResult);
//        die();


        /*  BLOCK RISKS */
        /* информация о болячках */
//        $bioResponse = BioResponse::find()->where([])->asArray()->all();
//        $preparedBioResponse = [];
//        foreach ($bioResponse as $bioResp) {
//            $preparedBioResponse[$bioResp['id_response']] = $bioResp;
//        }

        /* подготвленная информация с сервера */
//        $risksPrepared = [];
//        /* массив заготовка */
//        $risksFieldsNames = [
//            'time_step'=>'Временной шаг',
//            'P_fon'=>'Вероятность фоновая',
//            "P" =>'Вероятность',
//            "G_fon"=>'Тяжесть фоновая',
//            "G"=>'Тяжесть',
//            "R_fon"=>'Риск фоновый',
//            "R"=>'Риск',
//            "R_action"=>'Риск с учетом меропиятий',
//            "R_add"=>'дополнительный риск',
//            "R_add_action"=>'дополнительный риск с учетом меропиятий',
//            "R_index"=>'приведенный индекс риска',
//            "R_index_action"=>'приведенный индекс риска с учетом меропиятий',
//        ];
//        foreach ($responseResult['risk'] as $id_response => $item) {
//            $preparedResponse = [];
//            foreach ($item['time_step'] as $i => $item_step) {
//                $step = $risksFieldsNames;
//                foreach ($step as $key => $val){
//                    $step[$key] = $item[$key][$i];
//                }
//                $preparedResponse[] = $step;
//            }
//
//            $risksPrepared[$id_response]['data'] = $preparedResponse;
//            $risksPrepared[$id_response]['dataOriginal'] = $item;
//            $risksPrepared[$id_response]['meta'] = $preparedBioResponse[$id_response];
//            //$step++;
//        }

        /* END BLOCK RISKS */

        /*  BLOCK ACTIONS */
        $whereIn = [];
        $allActionsTimeSteps = [];
        foreach ($responseResult['action'] as $action_id => $action) {
            $whereIn[] = $action_id;
            $allActionsTimeSteps = array_merge($allActionsTimeSteps, $action);
        }
        $allActionsTimeSteps = array_unique($allActionsTimeSteps);
        sort($allActionsTimeSteps);
        $minActionTimeStep = min($allActionsTimeSteps);
        $maxActionTimeStep = max($allActionsTimeSteps);

        /* название мероприятий которые нам рекомендуют */
        $result = BioActions::getDescriptionedActions($whereIn);
        $actionNames = [];
        foreach ($result as $r) {
            $actionNames[$r['id_action']] = $r;
        }


        $preparedActions = ['isMultipleSheet' => true, 'models' => [], 'columns' => []];
        $i = 20;
        foreach ($allActionsTimeSteps as $step) {

            if ($step < $i) continue;
            $models = [];

            foreach ($responseResult['action'] as $action_id => $actionSteps) {
                if (in_array($step, $actionSteps)) {
                    $m = new BlackActions();
                    $m->название_меропрития = $actionNames[$action_id]['name'];
                    $models[] = $m;
                }
            }


            $preparedActions['models'][$step . ' лет'] = $models;
            $preparedActions['columns'][$step . ' лет'] = ['название_меропрития'];
            $i++;
        }
        //debug($preparedActions);

        // return $this->render('get_result_response', get_defined_vars());
    }

    /* THIS ACTION IS ON TESTING MODE */
    public function actionGetResult()
    {
        $originalBlackDir = BioUser::getBlackPath($this->user['path_key']) . BioFileHelper::$DIRECTORY_SEPARATOR . 'original';
        //debug($originalBlackDir);
        BioFileHelper::deleteAllFiles($originalBlackDir);
        $originalBlackJson = BioFileHelper::fileGetContents($originalBlackDir);
        if (!$originalBlackJson) {

            $allUM = BioUserMeasure::findAll(['user_id' => Yii::$app->user->getId()]);
            /* по шаблону заполним данные с базы данных */
            $data = BlackResult::applyUMData($allUM);

            $originalBlackJson = BlackResult::curl($data);
            BioFileHelper::filePutContents(
                $originalBlackJson,
                $originalBlackDir
            );
            /* результат расчетов с ящика */

        }

        $originalBlack = json_decode($originalBlackJson, true);

        /* информация о болячках */
        $risksPrepares = BlackResult::preparedRisks($originalBlack);

        /* рекомендуемые мероприятия */
        $actionsPrepared = BlackResult::preparedActions($originalBlack);

        /* названия полей рисков */
        $risksFieldsNames = BlackResult::getRiskFieldsNames();

        /* названия полей рисков */
        $сlassifiedRisksFieldsNames = BlackResult::getClassifiedRiskFieldsNames();

        return $this->render('get_result', [
            'risksPrepared' => $risksPrepares,
            'actionsPrepared' => $actionsPrepared,
            'risksFieldsNames' => $risksFieldsNames,
            'сlassifiedRisksFieldsNames' => $сlassifiedRisksFieldsNames
        ]);
    }


    public function actionSetvalue()
    {
        BioUserMeasure::setValue(Yii::$app->request->post());
        echo json_encode(['success' => true]);
    }

}
