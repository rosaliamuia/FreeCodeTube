<?php


namespace frontend\controllers;

use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use common\models\Video;
use yii\web\NotFoundHttpException;
use common\models\VideoView;
use common\models\VideoLike;



class VideoController extends Controller
{
    public function behaviors()
    {
        return[
            'access' => [
                'class' =>  AccessControl::class,
                'only' => ['like', 'dislike', 'history'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@']
                    ]
                ]
            ],
            'verb' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'like' => ['post'],
                    'dislike' => ['post'],
                ]
            ]
        ];
    }
    public function actionIndex()
    {
        $this->layout ='main';
        $dataProvider = new ActiveDataProvider([
            'query' => Video::find()->with('createdBy')->published()->latest(),
            'pagination'=>[
                'pageSize' =>2
            ]
        ]);
        return $this->render('index', [
            'dataProvider' => $dataProvider
        ]);
    }

    public function actionView($id)
    {
        $this->layout = 'auth';
        $video = $this->findVideo($id);


        $videoView = new VideoView();
        $videoView->video_id = $id;
        $videoView->user_id = \Yii::$app->user->id;
        $videoView->created_at = time();
        $videoView->save();

        $similarVideos = Video::find()
            ->published()
            ->byKeyword($video->title)
            ->andWhere(['NOT', ['video_id'=> $id]])
            ->limit(10)
            ->all();

        return $this->render('view', [
            'model' => $video,
            'similarVideos' => $similarVideos
        ]);
    }

    public function actionLike($id)
    {
        $video = $this->findVideo($id);
        $userId = \Yii::$app->user->id;

        $videoLIkeDislike =VideoLike::find()
              ->userIdVideoId($userId, $id)
              ->one();
        if (!$videoLIkeDislike) {
          $this->saveLikeDislike($id,$userId,VideoLike::TYPE_LIKE);

        } else if ($videoLIkeDislike->type == Videolike::TYPE_LIKE){
            $videoLIkeDislike->delete();
        }   else {
            $videoLIkeDislike->delete();
            $this->saveLikeDislike($id,$userId,VideoLike::TYPE_LIKE);

        }

        return $this->renderAjax('_buttons',[
            'model' => $video
        ]);
    }

    public function actionDislike($id)
    {
        $video = $this->findVideo($id);
        $userId = \Yii::$app->user->id;

        $videoLIkeDislike =VideoLike::find()
            ->userIdVideoId($userId, $id)
            ->one();
        if (!$videoLIkeDislike) {
            $this->saveLikeDislike($id,$userId,VideoLike::TYPE_DISLIKE);

        } else if ($videoLIkeDislike->type == Videolike::TYPE_DISLIKE){
            $videoLIkeDislike->delete();
        }   else {
            $videoLIkeDislike->delete();
            $this->saveLikeDislike($id,$userId,VideoLike::TYPE_DISLIKE);

        }

        return $this->renderAjax('_buttons',[
            'model' => $video
        ]);
    }

    public function actionSearch($keyword)
    {
        $this->layout ='main';
        $query = Video::find()
            ->with('createdBy')
            ->published()
            ->latest();
        if ($keyword){
            $query->byKeyword($keyword);

        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);
        return $this->render('search', [
            'dataProvider' => $dataProvider
        ]);
    }

    public function actionHistory()
    {
        $this->layout ='main';
        $query = Video::find()
            ->alias('v')
           ->innerJoin("(SELECT video_id, MAX(created_at) as max_date FROM video_view
           WHERE user_id = :userId
           GROUP BY video_id) vv", 'vv.video_id = v.video_id', [
               'userId' => \Yii::$app->user->id
           ])
        ->orderBy("vv.max_date DESC");

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);
        return $this->render('history', [
            'dataProvider' => $dataProvider
        ]);
    }


    protected function findVideo($id)
    {
        $video = Video::findOne($id);
        if (!$video) {
            throw new NotFoundHttpException("Video does not exist");
        }

        return $video;
    }

    protected function saveLikeDislike($videoId, $userId, $type)
    {
        $videoLIkeDislike = new VideoLike();
        $videoLIkeDislike->video_id = $videoId;
        $videoLIkeDislike->user_id = $userId;
        $videoLIkeDislike->type =$type;
        $videoLIkeDislike->created_at = time();
        $videoLIkeDislike->save();
    }
}