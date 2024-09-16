<?php
// +----------------------------------------------------------------------
// | likeadmin快速开发前后端分离管理后台（PHP版）
// +----------------------------------------------------------------------
// | 欢迎阅读学习系统程序代码，建议反馈是我们前进的动力
// | 开源版本可自由商用，可去除界面版权logo
// | gitee下载：https://gitee.com/likeshop_gitee/likeadmin
// | github下载：https://github.com/likeshop-github/likeadmin
// | 访问官网：https://www.likeadmin.cn
// | likeadmin团队 版权所有 拥有最终解释权
// +----------------------------------------------------------------------
// | author: likeadminTeam
// +----------------------------------------------------------------------

namespace app\api\logic;

use app\common\cache\WebScanLoginCache;
use app\common\logic\BaseLogic;
use think\Exception;
use app\api\service\{UserTokenService, WechatUserService};
use app\common\enum\{LoginEnum, user\UserTerminalEnum, YesNoEnum};
use app\common\service\{
    ConfigService,
    FileService,
    wechat\WeChatConfigService,
    wechat\WeChatMnpService,
    wechat\WeChatOaService,
    wechat\WeChatRequestService
};
use app\common\model\user\{User, UserAuth};
use think\facade\{Cache, Db, Config, Lang, Session};

/**
 * 登录逻辑
 * Class LoginLogic
 * @package app\api\logic
 */
class LoginLogic extends BaseLogic
{
    const EMAIL_KEY = "email_verification_code_%s";


    public static function getEmailSessionKey(string $email): string
    {
        return sprintf(self::EMAIL_KEY, $email);
    }
    
    //发送6位数密码到邮箱
    public static function sendPwd(array $p): bool
    {
        try {
            $pwd = rand(100000,999999);
            // 发送密码
            $emailLogic = new MailLogic();
            $sta = $emailLogic->sendPwdEmail($p['email'], $pwd);
            //修改密码
            $pwd = getPwdEncryptString($pwd);
            User::where(['email' => $p['email'],'account' => $p['account']])->save(['password' => $pwd]);
            if($sta){
                return true;
            }
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
    public static function sendCode(string $email): bool
    {
        $sessionKey = self::getEmailSessionKey($email);
        $code = rand(100000, 999999);

        // 发送验证码
        $emailLogic = new MailLogic();
        if ($emailLogic->sendVerificationEmail($email, $code)) {
            Cache::set($sessionKey, json_encode(['code' => $code, 'time' => time()], JSON_UNESCAPED_UNICODE), 360);
           return true;
        } else {
            return false;
        }
    }

    /**
     * 根据原始密码设置加密后的密码字符串
     * @param string $originalPwd
     * @return string
     */
    // public static function getPwdEncryptString(string $originalPwd): string
    // {
    //     // $passwordSalt = Config::get('project.unique_identification');
    //     // return create_password($originalPwd, $passwordSalt);
    // }

    public static function generateReferralCode(int $length = 10): string
    {
        return substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }

    public static function getIcode(int $length = 6): string
    {
        $icode = self::generateReferralCode($length);
        $user = User::where('icode', $icode)->findOrEmpty();
        if (!$user->isEmpty()) {
            $icode = self::generateReferralCode($length);
            $user = User::where('icode', $icode)->findOrEmpty();
            if (!$user->isEmpty()) {
                throw new Exception(Lang::get('invitation_code_failed'));
            }
        }
        return $icode;
    }

    /**
     * @notes 账号密码注册
     * @param array $params
     * @return bool
     * @author 段誉
     * @date 2022/9/7 15:37
     */
    public static function register(array $params)
    {
        try {
            // 验证码校验
            $sessionKey = self::getEmailSessionKey($params['email']);
            $cacheData = Cache::get($sessionKey);

            if (empty($cacheData)) {
                throw new \Exception(Lang::get('code_empty'));
            }
            $codeInfo = json_decode($cacheData, true);
            if (time() - $codeInfo['time'] >= MailLogic::EMAIL_CODE_EXPIRE * 60) {
                throw new \Exception(Lang::get('code_expired'));
            }
            if ($codeInfo['code'] != $params['code']) {
                throw new \Exception(Lang::get('code_invalidate'));
            }
            //账号是否存在
            $has_account = User::where('account',$params['account'])->value('id');
            if($has_account){
                throw new \Exception(Lang::get('account_already_exist'));
            }
            //手机号是否存在
            $has_mobile = User::where('mobile',$params['phone_number'])->value('id');
            if($has_mobile){
                throw new \Exception(Lang::get('mobile_already_exist'));
            }
            //检查邀请码是否存在
            $parent_id = User::where('icode',$params['invitation_code'])->value('id');
            if(!$parent_id){
                throw new \Exception(Lang::get('invitation_code_error'));
            }
            $parent_2_id = User::where('id',$parent_id)->value('parent_id');
            $icode = self::getIcode();

            $userSn = User::createUserSn();
            $password = getPwdEncryptString($params['password']);
            $avatar = ConfigService::get('default_image', 'user_avatar');
            User::create([
                'username' => $params['account'],
                'sn' => $userSn,
                'avatar' => $avatar,
                'nickname' => '用户' . $userSn,
                'account' => $params['account'],
                'password' => $password,
                'real_name' => $params['full_name'],
                'email' => $params['email'],
                'invitation_code' => $params['invitation_code'] ?? '',
                'mobile' => $params['phone_number'],
                'icode' => $icode,
                'parent_id' => $parent_id ?? 0,
                'parent_2_id' => $parent_2_id ?? 0
            ]);

            return true;
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }


    /**
     * @notes 账号/手机号登录，手机号验证码
     * @param $params
     * @return array|false
     * @author 段誉
     * @date 2022/9/6 19:26
     */
    public static function login($params)
    {
        try {
            // 账号/手机号 密码登录
            $where = ['email|account|mobile' => $params['account']];
//            if ($params['scene'] == LoginEnum::MOBILE_CAPTCHA) {
//                //手机验证码登录
//                $where = ['mobile' => $params['account']];
//            }

            $user = User::where($where)->findOrEmpty();
            if ($user->isEmpty()) {
                throw new \Exception(Lang::get('user_not_exist'));
            }

            if ($user->is_disable) {
                throw new \Exception(Lang::get('account_disabled'));
            }

            // 验证密码
            // $password = self::getPwdEncryptString($params['password']);
            // if ($password != $user->password) {
            //     throw new \Exception(Lang::get('password_invalidate'));
            // }
            if(!password_verify($params['password'], $user->password)){
                throw new \Exception(Lang::get('password_invalidate'));
            }

            //更新登录信息
            $user->login_time = time();
            $user->login_ip = request()->ip();
            $user->login_device = getDevice();
            $user->save();

            //设置token
            $userInfo = UserTokenService::setToken($user->id, $params['terminal'] ?? 1);

            //返回登录信息
            $avatar = $user->avatar ?: Config::get('project.default_image.user_avatar');
            $avatar = FileService::getFileUrl($avatar);

            return [
                'user_id' => $userInfo['user_id'],
                'email' => $userInfo['email'],
                'account' => $userInfo['account'],
                'full_ame' => $userInfo['real_name'],
                'mobile' => $userInfo['mobile'],
                'avatar' => $avatar,
                'token' => $userInfo['token'],
            ];
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }


    /**
     * @notes 退出登录
     * @param $userInfo
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author 段誉
     * @date 2022/9/16 17:56
     */
    public static function logout($userInfo)
    {
        //token不存在，不注销
        if (!isset($userInfo['token'])) {
            return false;
        }

        //设置token过期
        return UserTokenService::expireToken($userInfo['token']);
    }


    /**
     * @notes 获取微信请求code的链接
     * @param string $url
     * @return string
     * @author 段誉
     * @date 2022/9/20 19:47
     */
    public static function codeUrl(string $url)
    {
        return (new WeChatOaService())->getCodeUrl($url);
    }


    /**
     * @notes 公众号登录
     * @param array $params
     * @return array|false
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @author 段誉
     * @date 2022/9/20 19:47
     */
    public static function oaLogin(array $params)
    {
        Db::startTrans();
        try {
            //通过code获取微信 openid
            $response = (new WeChatOaService())->getOaResByCode($params['code']);
            $userServer = new WechatUserService($response, UserTerminalEnum::WECHAT_OA);
            $userInfo = $userServer->getResopnseByUserInfo()->authUserLogin()->getUserInfo();

            // 更新登录信息
            self::updateLoginInfo($userInfo['id']);

            Db::commit();
            return $userInfo;

        } catch (\Exception $e) {
            Db::rollback();
            self::$error = $e->getMessage();
            return false;
        }
    }


    /**
     * @notes 小程序-静默登录
     * @param array $params
     * @return array|false
     * @author 段誉
     * @date 2022/9/20 19:47
     */
    public static function silentLogin(array $params)
    {
        try {
            //通过code获取微信 openid
            $response = (new WeChatMnpService())->getMnpResByCode($params['code']);
            $userServer = new WechatUserService($response, UserTerminalEnum::WECHAT_MMP);
            $userInfo = $userServer->getResopnseByUserInfo('silent')->getUserInfo();

            if (!empty($userInfo)) {
                // 更新登录信息
                self::updateLoginInfo($userInfo['id']);
            }

            return $userInfo;
        } catch (\Exception  $e) {
            self::$error = $e->getMessage();
            return false;
        }
    }


    /**
     * @notes 小程序-授权登录
     * @param array $params
     * @return array|false
     * @author 段誉
     * @date 2022/9/20 19:47
     */
    public static function mnpLogin(array $params)
    {
        Db::startTrans();
        try {
            //通过code获取微信 openid
            $response = (new WeChatMnpService())->getMnpResByCode($params['code']);
            $userServer = new WechatUserService($response, UserTerminalEnum::WECHAT_MMP);
            $userInfo = $userServer->getResopnseByUserInfo()->authUserLogin()->getUserInfo();

            // 更新登录信息
            self::updateLoginInfo($userInfo['id']);

            Db::commit();
            return $userInfo;
        } catch (\Exception  $e) {
            Db::rollback();
            self::$error = $e->getMessage();
            return false;
        }
    }


    /**
     * @notes 更新登录信息
     * @param $userId
     * @throws \Exception
     * @author 段誉
     * @date 2022/9/20 19:46
     */
    public static function updateLoginInfo($userId)
    {
        $user = User::findOrEmpty($userId);
        if ($user->isEmpty()) {
            throw new \Exception('用户不存在');
        }

        $time = time();
        $user->login_time = $time;
        $user->login_ip = request()->ip();
        $user->update_time = $time;
        $user->save();
    }


    /**
     * @notes 小程序端绑定微信
     * @param array $params
     * @return bool
     * @author 段誉
     * @date 2022/9/20 19:46
     */
    public static function mnpAuthLogin(array $params)
    {
        try {
            //通过code获取微信openid
            $response = (new WeChatMnpService())->getMnpResByCode($params['code']);
            $response['user_id'] = $params['user_id'];
            $response['terminal'] = UserTerminalEnum::WECHAT_MMP;

            return self::createAuth($response);

        } catch (\Exception  $e) {
            self::$error = $e->getMessage();
            return false;
        }
    }


    /**
     * @notes 公众号端绑定微信
     * @param array $params
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @author 段誉
     * @date 2022/9/16 10:43
     */
    public static function oaAuthLogin(array $params)
    {
        try {
            //通过code获取微信openid
            $response = (new WeChatOaService())->getOaResByCode($params['code']);
            $response['user_id'] = $params['user_id'];
            $response['terminal'] = UserTerminalEnum::WECHAT_OA;

            return self::createAuth($response);

        } catch (\Exception  $e) {
            self::$error = $e->getMessage();
            return false;
        }
    }


    /**
     * @notes 生成授权记录
     * @param $response
     * @return bool
     * @throws \Exception
     * @author 段誉
     * @date 2022/9/16 10:43
     */
    public static function createAuth($response)
    {
        //先检查openid是否有记录
        $isAuth = UserAuth::where('openid', '=', $response['openid'])->findOrEmpty();
        if (!$isAuth->isEmpty()) {
            throw new \Exception('该微信已被绑定');
        }

        if (isset($response['unionid']) && !empty($response['unionid'])) {
            //在用unionid找记录，防止生成两个账号，同个unionid的问题
            $userAuth = UserAuth::where(['unionid' => $response['unionid']])
                ->findOrEmpty();
            if (!$userAuth->isEmpty() && $userAuth->user_id != $response['user_id']) {
                throw new \Exception('该微信已被绑定');
            }
        }

        //如果没有授权，直接生成一条微信授权记录
        UserAuth::create([
            'user_id' => $response['user_id'],
            'openid' => $response['openid'],
            'unionid' => $response['unionid'] ?? '',
            'terminal' => $response['terminal'],
        ]);
        return true;
    }


    /**
     * @notes 获取扫码登录地址
     * @return array|false
     * @author 段誉
     * @date 2022/10/20 18:23
     */
    public static function getScanCode($redirectUri)
    {
        try {
            $config = WeChatConfigService::getOpConfig();
            $appId = $config['app_id'];
            $redirectUri = UrlEncode($redirectUri);

            // 设置有效时间标记状态, 超时扫码不可登录
            $state = MD5(time().rand(10000, 99999));
            (new WebScanLoginCache())->setScanLoginState($state);

            // 扫码地址
            $url = WeChatRequestService::getScanCodeUrl($appId, $redirectUri, $state);
            return ['url' => $url];

        } catch (\Exception $e) {
            self::$error = $e->getMessage();
            return false;
        }
    }


    /**
     * @notes 网站扫码登录
     * @param $params
     * @return array|false
     * @author 段誉
     * @date 2022/10/21 10:28
     */
    public static function scanLogin($params)
    {
        Db::startTrans();
        try {
            // 通过code 获取 access_token,openid,unionid等信息
            $userAuth = WeChatRequestService::getUserAuthByCode($params['code']);

            if (empty($userAuth['openid']) || empty($userAuth['access_token'])) {
                throw new \Exception('获取用户授权信息失败');
            }

            // 获取微信用户信息
            $response = WeChatRequestService::getUserInfoByAuth($userAuth['access_token'], $userAuth['openid']);

            // 生成用户或更新用户信息
            $userServer = new WechatUserService($response, UserTerminalEnum::PC);
            $userInfo = $userServer->getResopnseByUserInfo()->authUserLogin()->getUserInfo();

            // 更新登录信息
            self::updateLoginInfo($userInfo['id']);

            Db::commit();
            return $userInfo;

        } catch (\Exception $e) {
            Db::rollback();
            self::$error = $e->getMessage();
            return false;
        }
    }


    /**
     * @notes 更新用户信息
     * @param $params
     * @param $userId
     * @return User
     * @author 段誉
     * @date 2023/2/22 11:19
     */
    public static function updateUser($params, $userId)
    {
        return User::where(['id' => $userId])->update([
            'nickname' => $params['nickname'],
            'avatar' => FileService::setFileUrl($params['avatar']),
            'is_new_user' => YesNoEnum::NO
        ]);
    }
}