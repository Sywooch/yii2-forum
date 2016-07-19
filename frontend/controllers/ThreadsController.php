<?php

namespace worstinme\forum\frontend\controllers;

use Yii;
use yii\web\Controller;
use worstinme\forum\frontend\models\Sections;
use worstinme\forum\frontend\models\Forums;
use worstinme\forum\frontend\models\Threads;
use worstinme\forum\frontend\models\Posts;
use yii\web\NotFoundHttpException;
use yii\data\ActiveDataProvider;

class ThreadsController extends Controller
{
    public $lang;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $lang = Yii::$app->request->get('lang');

        if (in_array($lang, array_keys($this->module->languages))) {
            $this->lang = $lang;
            Yii::$app->language = $lang;
        }

        if (in_array($action->id, ['view'])) {
            \yii\helpers\Url::remember();
        }

        return true; 
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::className(),
                'only'=>['edit','delete','post-delete','lock'],
                'rules' => [
                    [
                        'actions' => ['edit','delete','post-delete','lock'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function render($view, $params = [])
    {
        \worstinme\forum\assets\Asset::register($this->view);
        return parent::render($view, $params);
    }

    public function actionView($section,$forum,$thread_id) {

        if (($section = Sections::findOne(['lang'=>$this->lang,'alias'=>$section])) !== null) {
            if (($forum = $section->getForums()->where(['alias'=>$forum])->one()) !== null) {
                if (($thread = $forum->getThreads()->where(['id'=>$thread_id])->one()) !== null) {

                    if (!Yii::$app->session->has('thread-'.$thread->id.'-view')) {
                        $thread->views += 1;
                        $thread->save();
                        Yii::$app->session->set('thread-'.$thread->id.'-view',true);
                    }

                    $post = new Posts(['thread_id'=>$thread->id]);

                    if ($post->load(Yii::$app->request->post()) && $post->save()) {
                        $thread->updateAttributes(['posted_at'=>$post->created_at]);
                        Yii::$app->session->setFlash('success',Yii::t('forum','Your message was submitted.'));
                        $post = new Posts(['thread_id'=>$thread->id]);
                    }

                    $perPage = Yii::$app->request->get('per-page',Yii::$app->controller->module->postPageSize);

                    $postProvider = new ActiveDataProvider([
                        'query' => $thread->getPosts()->with(['user','thread','thread.forum','thread.forum.section']),
                        'pagination' => [
                            'pageSize' => $perPage
                        ],
                    ]);

                    if (Yii::$app->request->get('page') === null) {
                        $postProvider->pagination->page = $thread->getLastPage($perPage);
                    }

                    return $this->render('view',[
                        'lang'=>$this->lang,
                        'thread'=>$thread,
                        'forum'=>$forum,
                        'section'=>$section,
                        'post'=>$post,
                        'postProvider'=>$postProvider,
                    ]);
                }
            }
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionEdit($section,$forum,$thread_id) {

        if (($section = Sections::findOne(['lang'=>$this->lang,'alias'=>$section])) !== null) {
            if (($forum = $section->getForums()->where(['alias'=>$forum])->one()) !== null) {
                if (($model = $forum->getThreads()->where(['id'=>$thread_id])->one()) !== null) {

                    if (!$model->canEdit) {
                        Yii::$app->session->setFlash('error', Yii::t('forum',"You havn't enough right to edit"));
                        return $this->redirect($model->url); 
                    }

                    if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post()) && $model->save()) {
                        return $this->redirect($model->url);
                    }

                    return $this->render('edit',[
                        'lang'=>$this->lang,
                        'model'=>$model,
                        'forum'=>$forum,
                        'section'=>$section,
                    ]);
                }
            }
        }

        throw new NotFoundHttpException('The requested page does not exist.');

    }

    public function actionDelete($thread_id) {

        if (($model = Threads::findOne($thread_id)) === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        if ($model->canDelete) {

            if ($model->state == $model::STATE_DELETED) {

                $forum = $model->forum->alias;
                $section = $model->forum->section->alias;

                $model->delete();

                Yii::$app->session->setFlash('success', Yii::t('forum',"Thread has just been removed."));

                return $this->redirect(['/forum/default/forum','forum'=>$forum,'section'=>$section,'lang'=>$model->forum->lang]);
            }
            else {

                $model->updateAttributes(['state'=>$model::STATE_DELETED]);
                Yii::$app->session->setFlash('success', Yii::t('forum',"Thread has been marked as DELETED."));

            }

            return $this->redirect($model->url);

        } 

        Yii::$app->session->setFlash('error', Yii::t('forum',"Thread can't be deleted"));
        
        return $this->redirect($model->url);

    }

    public function actionLock($thread_id) {

        if (($model = Threads::findOne($thread_id)) === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        if ($model->canEdit) {

            if ($model->flag == $model::FLAG_ACTIVE) {

                $model->updateAttributes(['flag'=>$model::FLAG_INACTIVE]);

                Yii::$app->session->setFlash('success', Yii::t('forum',"Thread has been unlocked."));

            }
            else {

                $model->updateAttributes(['flag'=>$model::FLAG_ACTIVE]);
                
                Yii::$app->session->setFlash('success', Yii::t('forum',"Thread has been locked."));

            }

            return $this->redirect($model->url);

        } 

        Yii::$app->session->setFlash('error', Yii::t('forum',"Thread can't be deleted"));
        
        return $this->redirect($model->url);

    }

    public function actionPostDelete($post_id) {

        if (($model = Posts::findOne($post_id)) === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        if ($model->canDelete) {

            $model->delete();
            Yii::$app->session->setFlash('success', Yii::t('forum',"Post has just been removed."));
            return $this->redirect($model->url);

        } 

        Yii::$app->session->setFlash('error', Yii::t('forum',"Post can't be deleted"));
        return $this->redirect($model->url);

    }

    public function actionNewThread() {

        $model = new Threads;

        $model->forum_id = Yii::$app->request->get('forum_id');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect($model->url);
        }

        return $this->render('edit',[
            'model'=>$model,
            'lang'=>$this->lang,
        ]);
    }



}