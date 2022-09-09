<?php
namespace amos\newsletter\controllers;


use amos\newsletter\models\FormSubscribeNewsletter;
use amos\newsletter\models\Newsletter;
use amos\newsletter\Module;
use open20\amos\core\controllers\BackendController;
use open20\amos\core\controllers\CrudController;
use open20\amos\core\icons\AmosIcons;
use open20\amos\core\module\BaseAmosModule;
use Yii;
use yii\data\ArrayDataProvider;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

class ServiceEmailController extends CrudController
{
    public $module;

    public function init()
    {
        $this->setModelObj(new Newsletter);
        $this->setModelSearch(new Newsletter);
        $this->setAvailableViews([
            'grid' => [
                'name' => 'grid',
                'label' => AmosIcons::show('view-list-alt') . Html::tag('p', BaseAmosModule::tHtml('amoscore', 'Table')),
                'url' => '?currentView=grid'
            ],
        ]);

        $this->module = \Yii::$app->getModule('newsletter');
        parent::init();
//        $this->setModelObj(Newsletter::className());

    }

    /**
     * @inheritdoc
     */
    public function behaviors() {
        $behaviors = ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'subscribe-to-group',
                            'subscribe-to-array-group',

                            'get-fields'
                        ],
                    ],
                    [
                        'allow' => true,
                        'actions' => [
                            'index',
                            'index-groups',
                            'view-group',
                            'subscribers-list',
                            'fields'
                        ],
                        'roles' => ['ADMIN']
                    ],
                ]
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post', 'get']
                ]
            ]
        ]);
        return $behaviors;
    }

    public function actionIndex($layout=null){
            Url::remember();
            $mailServiceClassname = $this->module->mail_service_driver;
            $mailService = new $mailServiceClassname();

            $lists = [];
            $decoded = $mailService->getLists();

            if(!empty($decoded->Items)){
                $lists = $decoded->Items;
            }

            $pagination = $this->setPagination($mailService, $decoded);

            $dataProvider = new ArrayDataProvider([
                    'allModels'=> $lists
                ]);

            return $this->render('index', ['dataProvider' => $dataProvider, 'pagination' => $pagination]);
    }


    /**
     * @param $idList
     * @return string
     */
    public function actionIndexGroups($idList){
        Url::remember();
        $mailServiceClassname = $this->module->mail_service_driver;
        $mailService = new $mailServiceClassname();

        $lists = [];
        $decoded = $mailService->getGroupsByList($idList);
        if(!empty($decoded->Items)){
            $lists = $decoded->Items;
        }

        $pagination = $this->setPagination($mailService, $decoded);

        $dataProvider = new ArrayDataProvider([
            'allModels'=> $lists
        ]);

        return $this->render('index-groups', ['dataProvider' => $dataProvider, 'pagination' => $pagination]);
    }

    /**
     * @param $idList
     * @return string
     */
    public function actionViewGroup($idGroup){
        $this->layout='list';
        if($this->module) {
            $mailServiceClassname = $this->module->mail_service_driver;
            $mailService = new $mailServiceClassname();
            $lists = [];

            $queryParams = \Yii::$app->request->queryParams;
            if(!empty(\Yii::$app->request->get('ServiceEmail'))){
                $queryParams = $mailService->buildQueryParams($queryParams, \Yii::$app->request->get('ServiceEmail'));
            }

            $decoded = $mailService->getSubscribtionsToGroup($idGroup, $queryParams);

            if (!empty($decoded->Items)) {
                $lists = $decoded->Items;
            }

            $pagination = $this->setPagination($mailService, $decoded);
            $dataProvider = new ArrayDataProvider([
                'allModels' => $lists,
            ]);
            return $this->render('subscribers', ['dataProvider' => $dataProvider, 'pagination' => $pagination, 'mailService' =>  $mailService]);
        }
    }

    /**
     * @param $mailService
     * @param $decoded
     * @return Pagination
     */
    public function setPagination($mailService, $decoded){
        $currentPage = 0;
        $totElement = 0; ;

        $paginationConfig = $mailService->getPaginationConfigs();
        $pagination = new \yii\data\Pagination();

        if(!empty($paginationConfig['totalCount'])) {
            $totalCount = $paginationConfig['totalCount'];
            if(property_exists($decoded, $totalCount)) {
                $totElement = $decoded->$totalCount;
                $pagination->totalCount = $totElement;
            }
        }

        if(!empty($paginationConfig['pageParam'])) {
            $pageParam = $paginationConfig['pageParam'];
            if(property_exists($decoded, $pageParam)){
                $currentPage = $decoded->$pageParam;
                $pagination->pageParam = $pageParam;
                $pagination->setPage($currentPage-1);
            }

        }
        return $pagination;
    }

    /**
     * @param $idGroup
     * @return \yii\web\Response
     */
    public function actionSubscribeToGroup($idGroup){
        $mailServiceClassname = $this->module->mail_service_driver;
        $mailService = new $mailServiceClassname();
        $model = new FormSubscribeNewsletter();
        if(\Yii::$app->request->post() && $model->load(\Yii::$app->request->post())){
            $data = new \stdClass();
            $data->Email = $model->email;
            $data->Name = $model->name;
            $data->Fields = [];
            foreach ($model->fields as $key => $name){
                $data->Fields[]= ['Id' => $key, 'Value' => $name];
            }
            $data = [$data];
            $res = $mailService->subscribeToGroup($idGroup, $data);

            if($res){
                \Yii::$app->session->addFlash('success', Module::t('amosnewsletter', 'You have successfully registered to the newsletter'));
                if(!empty($post['url_redirect'])){
                    return $this->redirect($post['url_redirect']);
                }
                return $this->redirect(Url::previous());
            }
        }
        return $this->redirect(Url::previous());
    }

     /**
      *
      * @return type
      */
    public function actionSubscribeToArrayGroup(){
        $module = \Yii::$app->getModule('newsletter');
        $mailServiceClassname = $this->module->mail_service_driver;
        $mailService = new $mailServiceClassname();
        if(!empty($module) && !empty($module->customModel)){
            $classModel = $module->customModel;
            $model = new $classModel;
        } else {
            $model = new FormSubscribeNewsletter();
        }

        if(\Yii::$app->request->post() && $model->load(\Yii::$app->request->post())){
            $idGroups = $model->idgroups;
            $tematiche = \Yii::$app->request->post('tematiche');
            $tematicheser = implode(', ', $tematiche);

            $data = new \stdClass();
            $data->Email = $model->email;
            $data->Name = $model->name;
            $data->Fields = [];
//            foreach ($model->fields as $key => $name){
//                $data->Fields[]= ['Id' => $key, 'Value' => $name];
//            }
            $data->Fields[] = ['Id' => '1', 'Value' => $model->campo1];
            $data->Fields[] = ['Id' => '2', 'Value' => $model->campo2];
            $data->Fields[] = ['Id' => '3', 'Value' => $model->campo3];
            $data->Fields[] = ['Id' => '5', 'Value' => $model->campo5];
            $data->Fields[]=  ['Id' => '28', 'Value' => $tematicheser];
            $data->Fields[]=  ['Id' => '29', 'Value' => $model->ruolo];

            $data = [$data];
            foreach ($idGroups as $value) {
                $res = $mailService->subscribeToGroup($value, $data, true);
                if(!$res){
                    break;
                }
            }

            if($res){
                \Yii::$app->session->addFlash('success', Module::t('amosnewsletter', 'You have successfully registered to the newsletter'));
                if(!empty($model->redirect_url)){
                    return $this->redirect($model->redirect_url);
                }
                return $this->redirect(Url::previous());
            }
        }
        return $this->redirect(Url::previous());
    }

    /**
     * @param $idGroup
     * @return \yii\web\Response
     */
    public function actionSubscribeToList($idList){
        $mailServiceClassname = $this->module->mail_service_driver;
        $mailService = new $mailServiceClassname();
        $model = new FormSubscribeNewsletter();
        if(\Yii::$app->request->post() && $model->load(\Yii::$app->request->post())){
            $data = new \stdClass();
            $data->Email = $model->email;
            $data->Name = $model->name;
            $data->Fields = [];
            foreach ($model->fields as $key => $name){
                $data->Fields[]= ['Id' => $key, 'Value' => $name];
            }
            $data = [$data];
            $res = $mailService->subscribeToList($idList, $data);

            if($res){
                \Yii::$app->session->addFlash('success', Module::t('amosnewsletter', 'You have successfully registered to the newsletter'));
                if(!empty($post['url_redirect'])){
                    return $this->redirect($post['url_redirect']);
                }
                return $this->redirect(Url::previous());
            }
        }
        return $this->redirect(Url::previous());
    }


    /**
     * @param $idGroup
     * @return \yii\web\Response
     */
    public function actionSubscribersList($idList){
        //&filterby="Email==%27ivano.piazza@gmail.com%27"
        $this->setUpLayout('list');
        $mailServiceClassname = $this->module->mail_service_driver;
        $mailService = new $mailServiceClassname();

        $lists = [];
        $queryParams = \Yii::$app->request->queryParams;
        if(!empty(\Yii::$app->request->get('ServiceEmail'))){
            $queryParams = $mailService->buildQueryParams($queryParams, \Yii::$app->request->get('ServiceEmail'));
        }
        $decoded = $mailService->getSubscribersByList($idList, $queryParams);
        if(!empty($decoded->Items)){
            $lists = $decoded->Items;
        }

        $pagination = $this->setPagination($mailService, $decoded);
        $dataProvider = new ArrayDataProvider([
            'allModels'=> $lists
        ]);

        return $this->render('subscribers', ['dataProvider' => $dataProvider,'pagination' => $pagination, 'mailService' =>  $mailService]);
    }

    /**
     * @return string
     */
    public function actionFields(){
        $fields = $this->actionGetFields();
        $dataProvider = new ArrayDataProvider([
            'allModels'=> $fields
        ]);

        return $this->render('fields', ['dataProvider' => $dataProvider]);
    }

    /**
     * TODO Sistemare  i parametri e  $decode->Items che sono chiodati per driver
     * @return array
     */
    public function actionGetFields(){
        $mailServiceClassname = $this->module->mail_service_driver;
        $mailService = new $mailServiceClassname();
        $params = [];
        $decoded = $mailService->getDynamicFields($params);

        $fields = [];
        if(!empty($decoded->Items)){
            $fields = $decoded->Items;
        }


        if($decoded->IsPaginated){
            while ($decoded->Skipped < $decoded->TotalElementsCount){
                $pagenumber = $decoded->PageNumber + 1;
                $params =  ArrayHelper::merge($params, ['PageNumber' => $pagenumber]);
                $decoded = $mailService->getDynamicFields($params);
                if(!empty($decoded->Items)){
                    $fields =  ArrayHelper::merge($decoded->Items, $fields);;
                }
            }
        }
//        pr($fields);

        return $fields;

    }



}