<?php

namespace api\controllers;

use common\models\UserInfo;
use Yii;
use api\models\ApiLoginForm;
use common\models\User;
use common\models\Userstat;

class UserApiController extends \yii\web\Controller
{
    public $enableCsrfValidation = false;

    public function actionIndex()
    {

    }
    /**
     * @return array
     */
    public function actionCheckAuth(){
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $items = array();
        if(\Yii::$app->request->post()){
            $user_email = \Yii::$app->request->post('email');
            $user_key = \Yii::$app->request->post('key');
            if($user_email && $user_key){
                $userInfo = User::find()->where(['email' => $user_email])->with('userInfos')->one();
                if($userInfo && md5($userInfo->auth_key) == $user_key && $userInfo->status > 0){
                    $items['success'] = array(
                        'auth_key' => md5($userInfo->auth_key)
                    );
                }
            }
        }

        if(!$items){
            $items['error'] = array(
                'ErrorText' => \Yii::t('app', 'You User account data is not valid.'),
                'PostData' => \Yii::$app->request->post()
            );
        }

        return $items;
    }

    /**
     * @return array
     */
    public function actionMobileAuth(){
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        if(\Yii::$app->request->post()){
            $model = new ApiLoginForm();
            $model->email = \Yii::$app->request->post('email');
            $model->password = \Yii::$app->request->post('password');
            $items = array();
            if ($model->login()) {
                $user = \Yii::$app->user->identity;
                //$userInfo = User::find()->where(['id' => $user->id,'status' > 0])->with('userInfos')->one();
                $userInfo = User::find(['id' => $user->id])->with('userInfos')->one();

                $userstat_model = new Userstat();
                $userstat_model->setTableName($user->id);
                $userstat_shema = $userstat_model->getTableSchema();
                if($userstat_shema === null){
                    $userstat_model->createUserStatTable($user->id);
                }

                $items['success'] = array(
                    'id' => $user->id,
                    'username' => $user->username,
                    'email'=> $user->email,
                    'auth_key' => md5($user->auth_key),
                    'user_gender' => $userInfo->userInfos->user_gender ? 'male' : 'female',
                    'user_age' => $userInfo->userInfos->user_birthday,
                );

            } else {
                $items['error'] = array(
                    'ErrorText' => \Yii::t('app', 'Incorrect email or password.'),
                    'PostData' => \Yii::$app->request->post(),
                    'Email' => $model->email,
                    'Password' => $model->password
                );
            }
        } else {
            $items['error'] = array(
                'ErrorText' => \Yii::t('app', 'No data sending.'),
                'PostData' => \Yii::$app->request->post()
            );
        }

        return $items;
    }

    /**
     * @return array
     */
    public function actionSynchronizeUserData(){
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $items = array();
        if(\Yii::$app->request->post()){
            $userInfo = User::find()->where(['auth_key' => md5(\Yii::$app->request->post('auth_key'))])->with('userInfos')->one();
            if($userInfo->auth_key == \Yii::$app->request->post('auth_key') && $userInfo->email== \Yii::$app->request->post('email')){
                $items['success'] = array(
                    'id' => $userInfo->id,
                    'username' => $userInfo->username,
                    'email'=> $userInfo->email,
                    'auth_key' => md5($userInfo->auth_key),
                    'user_gender' => $userInfo->userInfos->user_gender ? 'male' : 'female',
                    'user_birthday' => $userInfo->userInfos->user_birthday,
                );
            } else {
                $items['error'] = array(
                    'ErrorText' => \Yii::t('app', 'Sinhronize error!'),
                );
            }
        } else {
            $items['error'] = array(
                'ErrorText' => \Yii::t('app', 'No data sending.'),
            );
        }
        return $items;
    }

    /**
     * @return array
     */
    public function actionMobileSignup(){
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $items = array();
        if(\Yii::$app->request->post()){
            if(\Yii::$app->request->post("user_email") &&
                \Yii::$app->request->post("user_name") &&
                \Yii::$app->request->post("user_firstname") &&
                \Yii::$app->request->post("user_birthday") &&
                \Yii::$app->request->post("user_gender")){

                $userModel = User::findByEmail(\Yii::$app->request->post("user_email"));
                if(!$userModel){
                    $userinfo = new UserInfo();
                    $random_string = Yii::$app->security->generateRandomString(10);
                    $user = new User();
                    $user->username = \Yii::$app->request->post("user_name") ;
                    $user->email = \Yii::$app->request->post("user_email");
                    $user->setPassword($random_string);
                    $user->generateAuthKey();
                    $user->status = 0;
                    if($user->save()){
                        $userinfo->user_id = $user->id;
                        $userinfo->user_name = \Yii::$app->request->post("user_name");
                        $userinfo->user_firstname = \Yii::$app->request->post("user_firstname") ;
                        $userinfo->user_gender = (int)\Yii::$app->request->post("user_gender");
                        $userinfo->user_birthday = date_format(date_create(\Yii::$app->request->post("user_birthday")),'Y-m-d');
                        $userinfo->user_subscribe = 1;
                        $userinfo->user_avatar = \Yii::$app->request->post("user_gender") ? 'avataricon-m7':'avataricon-w6';
                        $userinfo->save();
                    };
                        $email = \Yii::$app->mailer->compose(
                            ['html' => 'newUserMobile-html', 'text' => 'newUserMobile-text'],
                            ['user' => $user, 'generated_pass' => $random_string,]
                        )
                            ->setTo($user->email)
                            ->setFrom([\Yii::$app->params['supportEmail'] => \Yii::$app->name . ' robot'])
                            ->setSubject(\Yii::t('app','Signup Confirmation'))
                            ->send();
                    $items['success'] = true;
                } else {
                    $items['error'] = array(
                        'ErrorText' => \Yii::t('app','Email already use!'),
                    );
                }
            }
        } else {
            $items['error'] = array(
                'ErrorText' => \Yii::t('app', 'No data sending.'),
            );
        }
        return $items;
    }
}
