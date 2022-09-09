<?php
$this->title = \amos\newsletter\Module::t('amosnewsletter', 'Subscribed');
$this->params['breadcrumbs'][] = $this->title;
$module = \Yii::$app->getModule('newsletter');
?>
<?=   $this->render('_search_subscribers', [ 'originAction' => Yii::$app->controller->action->id , 'mailService' =>  $mailService]); ?>
<?php
$columns = [];
$columns []=  [
    'attribute' => 'idRecipient',
    'label' => \amos\newsletter\Module::t('amosnewsletter', 'Id')
];

foreach ((Array) $module->fieldIdsSubscribersGridView as $id => $fieldName){
    $columns []= [
        'label' => $fieldName,
        'value' => function($model)use($id){
            foreach ($model->Fields as $elem){
                if($elem->Id == $id){
                    return $elem->Value;
                }
            }
        }
    ];
}
$columns []=   [
    'attribute' => 'Email',
    'label' => \amos\newsletter\Module::t('amosnewsletter', 'Email')
];
?>
<?= \open20\amos\core\views\AmosGridView::widget([
    'dataProvider' => $dataProvider,
    'showPageSummary' => false,
    'showPager' => false,
    'columns' => $columns

])?>

<?php
;

echo \open20\amos\core\views\AmosLinkPager::widget([
    'pagination' => $pagination,
    'showSummary' => true,
    'bottomPositionSummary' => true,
])?>
