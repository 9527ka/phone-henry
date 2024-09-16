<?php
namespace app\api\controller;

use app\api\logic\MailLogic;
use app\Request;
use app\api\validate\{LoginAccountValidate,
    RegisterValidate,
    SendCodeValidate,
    WebScanLoginValidate,
    WechatLoginValidate};
use app\api\logic\LoginLogic;
use think\facade\Lang;
use think\facade\Session;
use app\common\model\user\User;

/**
 * 登录注册
 * Class LoginController
 * @package app\api\controller
 */
class LoginController extends BaseApiController
{

    public array $notNeedLogin = ['retrieve_pwd','register', 'sendCode', 'account', 'logout', 'codeUrl', 'oaLogin',  'mnpLogin', 'getScanCode', 'scanLogin'];

    /**
     * @notes 注册账号
     * @return \think\response\Json
     * @author 段誉
     * @date 2022/9/7 15:38
     */
    public function register()
    {
        $params = (new RegisterValidate())->post()->goCheck('register');
        $result = LoginLogic::register($params);
        if (true === $result) {
            return $this->success(Lang::get('register_success'), [], 1, 1);
        }
        return $this->fail(LoginLogic::getError());
    }


    /**
     * @notes 账号密码/手机号密码/手机号验证码登录
     * @return \think\response\Json
     * @author 段誉
     * @date 2022/9/16 10:42
     */
    public function account()
    {
        $params = (new LoginAccountValidate())->post()->goCheck();
        $result = LoginLogic::login($params);
        if (false === $result) {
            return $this->fail(LoginLogic::getError());
        }
        return $this->data($result);
    }
    
    //找回密码
    public function retrieve_pwd(Request $request) {
        $p = $this->request->post();
        if(empty($p['email'])){
            return $this->fail(Lang::get('email_is_empty'));
        }
        if(empty($p['account'])){
            return $this->fail(Lang::get('username_is_empty'));
        }
        //邮箱是否存在
        $has_email = User::where(['email' => $p['email']])->value('id');
        if(!$has_email){
            return $this->fail(Lang::get('email_not_exist'));
        }
        $has_username = User::where(['email' => $p['email'],'account' => $p['account']])->value('id');
        if(!$has_username){
            return $this->fail(Lang::get('username_not_exist'));
        }
        $ok = LoginLogic::sendPwd($p);
        if ($ok) {
            return $this->success(Lang::get('update_pwd_success'));
        } else {
            return $this->fail(Lang::get('update_pwd_failed'));
        }
    }
    
    // 发送验证码
    public function sendCode(Request $request) {
        $params = (new SendCodeValidate())->post()->goCheck();
        $has_email = User::where('email',$params['email'])->value('id');
        if($has_email){
            return $this->fail(Lang::get('email_already_exist'));
        }
        $ok = LoginLogic::sendCode($params['email']);
        if ($ok) {
            return $this->success(Lang::get('send_code_success'));
        } else {
            return $this->fail(Lang::get('send_code_failed'));
        }
    }

    /**
     * @notes 退出登录
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author 段誉
     * @date 2022/9/16 10:42
     */
    public function logout()
    {
        LoginLogic::logout($this->userInfo);
        return $this->success();
    }


    /**
     * @notes 获取微信请求code的链接
     * @return \think\response\Json
     * @author 段誉
     * @date 2022/9/15 18:27
     */
    public function codeUrl()
    {
        $url = $this->request->get('url');
        $result = ['url' => LoginLogic::codeUrl($url)];
        return $this->success('获取成功', $result);
    }


    /**
     * @notes 公众号登录
     * @return \think\response\Json
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @author 段誉
     * @date 2022/9/20 19:48
     */
    public function oaLogin()
    {
        $params = (new WechatLoginValidate())->post()->goCheck('oa');
        $res = LoginLogic::oaLogin($params);
        if (false === $res) {
            return $this->fail(LoginLogic::getError());
        }
        return $this->success('', $res);
    }


    /**
     * @notes 小程序-登录接口
     * @return \think\response\Json
     * @author 段誉
     * @date 2022/9/20 19:48
     */
    public function mnpLogin()
    {
        $params = (new WechatLoginValidate())->post()->goCheck('mnpLogin');
        $res = LoginLogic::mnpLogin($params);
        if (false === $res) {
            return $this->fail(LoginLogic::getError());
        }
        return $this->success('', $res);
    }


    /**
     * @notes 小程序绑定微信
     * @return \think\response\Json
     * @author 段誉
     * @date 2022/9/20 19:48
     */
    public function mnpAuthBind()
    {
        $params = (new WechatLoginValidate())->post()->goCheck("wechatAuth");
        $params['user_id'] = $this->userId;
        $result = LoginLogic::mnpAuthLogin($params);
        if ($result === false) {
            return $this->fail(LoginLogic::getError());
        }
        return $this->success('绑定成功', [], 1, 1);
    }



    /**
     * @notes 公众号绑定微信
     * @return \think\response\Json
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @author 段誉
     * @date 2022/9/20 19:48
     */
    public function oaAuthBind()
    {
        $params = (new WechatLoginValidate())->post()->goCheck("wechatAuth");
        $params['user_id'] = $this->userId;
        $result = LoginLogic::oaAuthLogin($params);
        if ($result === false) {
            return $this->fail(LoginLogic::getError());
        }
        return $this->success('绑定成功', [], 1, 1);
    }


    /**
     * @notes 获取扫码地址
     * @return \think\response\Json
     * @author 段誉
     * @date 2022/10/20 18:25
     */
    public function getScanCode()
    {
        $redirectUri = $this->request->get('url/s');
        $result = LoginLogic::getScanCode($redirectUri);
        if (false === $result) {
            return $this->fail(LoginLogic::getError() ?? '未知错误');
        }
        return $this->success('', $result);
    }


    /**
     * @notes 网站扫码登录
     * @return \think\response\Json
     * @author 段誉
     * @date 2022/10/21 10:28
     */
    public function scanLogin()
    {
        $params = (new WebScanLoginValidate())->post()->goCheck();
        $result = LoginLogic::scanLogin($params);
        if (false === $result) {
            return $this->fail(LoginLogic::getError() ?? '登录失败');
        }
        return $this->success('', $result);
    }


    /**
     * @notes 更新用户头像昵称
     * @return \think\response\Json
     * @author 段誉
     * @date 2023/2/22 11:15
     */
    public function updateUser()
    {
        $params = (new WechatLoginValidate())->post()->goCheck("updateUser");
        LoginLogic::updateUser($params, $this->userId);
        return $this->success('操作成功', [], 1, 1);
    }


}