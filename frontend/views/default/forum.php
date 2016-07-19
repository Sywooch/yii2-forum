<?php

use yii\helpers\Html;
use worstinme\uikit\widgets\ListView;
use yii\widgets\Pjax;

$this->title = $forum->title;

\worstinme\uikit\assets\Accordion::register($this);

$this->params['breadcrumbs'][] = ['label'=>Yii::t('forum','Форум'), 'url'=> ['/forum/default/index','lang'=>$lang]];
$this->params['breadcrumbs'][] = $this->title;

?>

<section class="forum">

    <article class="forum-view forum-panel">

    <h1><?=$this->title?></h1>

    <?=$forum->description?>

    </article>

    <?php Pjax::begin(); ?>    
    	
    	<?= ListView::widget([
            'dataProvider' => $dataProvider,
            'options'=>['class'=>'forum-threads uk-margin-top'],
            'layout'=>'<div class="forum-threads-box">{items}</div><div class="uk-margin-top">{pager}</div>',
            'itemOptions' => ['class' => 'thread'],
            'itemView' => '_thread',
        ]) ?>

    <?php Pjax::end(); ?>

    <?= Html::a(Yii::t('forum','Создать новую тему'), 
    	['/forum/threads/new-thread','lang'=>$lang,'forum_id'=>$forum->id], 
    	['class' => 'uk-button uk-button-success']); ?>

</section>

<?php  $script = <<<JS

	
JS;

$this->registerJs($script,$this::POS_READY);