<?php

namespace app\user\controller;

use think\Validate;
use cmf\controller\HomeBaseController;
use app\user\model\UserModel;

class LoginController extends HomeBaseController
{
    public function index()
    {
        $redirect = $this->request->post("redirect");

        if (empty($redirect)) {
            $redirect = $this->request->server('HTTP_REFERER');
        } else {
            $redirect = base64_decode($redirect);
        }

        session('login_http_referer', $redirect);

        if (cmf_is_user_login()) { //已经登录时直接跳到首页
            return redirect(get_site_url() . '/user/tube/index.html');
        } else {
            return $this->fetch(":login");
        }
    }

    /**
     * 登录验证提交
     */
    public function doLogin(){
        if ($this->request->isPost()) {
            $validate = new Validate([
                'captcha'  => 'require',
                'username' => 'require',
                'password' => 'require|min:6|max:32',
            ]);
            $validate->message([
                'username.require' => '用户名不能为空',
                'password.require' => '密码不能为空',
                'password.max'     => '密码不能超过32个字符',
                'password.min'     => '密码不能小于6个字符',
                'captcha.require'  => '验证码不能为空',
            ]);

            $data = $this->request->post();
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            if (!cmf_captcha_check($data['captcha'])) {
                $this->error('验证码错误');
            }

            $userModel = new UserModel();
            $user['user_pass'] = $data['password'];
            if (Validate::is($data['username'], 'email')) {
                $user['user_email'] = $data['username'];
                $log = $userModel->doEmail($user);
            } else if (preg_match('/^1\d{10}$/', $data['username'])) {
                $user['mobile'] = $data['username'];
                $log = $userModel->doMobile($user);
            } else {
                $user['user_login'] = $data['username'];
                $log = $userModel->doName($user);
            }
            //$session_login_http_referer = session('login_http_referer');
            //$redirect = empty($session_login_http_referer) ? $this->request->root() : $session_login_http_referer;
            switch ($log) {
                case 0:
                    cmf_user_action('login');
                    $this->success('登录成功', get_site_url().'/user/tube/index.html');
                    break;
                case 1:
                    $this->error('登录密码错误');
                    break;
                case 2:
                    $this->error('账户不存在');
                    break;
                case 3:
                    $this->error('账号被禁止访问系统');
                    break;
                default :
                    $this->error('未受理的请求');
            }
        } else {
            $this->error("请求错误");
        }
    }

    /**
     * 找回密码
     */
    public function findPassword(){
		$this->error("功能维护中");
        return $this->fetch('/find_password');
    }

    /**
     * 用户密码重置
     */
    public function passwordReset(){
		$this->error("功能维护中");
        if ($this->request->isPost()) {
            $validate = new Validate([
                'captcha'           => 'require',
                'verification_code' => 'require',
                'password'          => 'require|min:6|max:32',
            ]);
            $validate->message([
                'verification_code.require' => '验证码不能为空',
                'password.require'          => '密码不能为空',
                'password.max'              => '密码不能超过32个字符',
                'password.min'              => '密码不能小于6个字符',
                'captcha.require'           => '验证码不能为空',
            ]);

            $data = $this->request->post();
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            if (!cmf_captcha_check($data['captcha'])) {
                $this->error('验证码错误');
            }
            $errMsg = cmf_check_verification_code($data['username'], $data['verification_code']);
            if (!empty($errMsg)) {
                $this->error($errMsg);
            }

            $userModel = new UserModel();
            if ($validate::is($data['username'], 'email')) {

                $log = $userModel->emailPasswordReset($data['username'], $data['password']);

            } else if (preg_match('/^1\d{10}$/', $data['username'])) {
                $user['mobile'] = $data['username'];
                $log = $userModel->mobilePasswordReset($data['username'], $data['password']);
            } else {
                $log = 2;
            }
            switch ($log) {
                case 0:
                    $this->success('密码重置成功', $this->request->root());
                    break;
                case 1:
                    $this->error("您的账户尚未注册");
                    break;
                case 2:
                    $this->error("您输入的账号格式错误");
                    break;
                default :
                    $this->error('未受理的请求');
            }

        } else {
            $this->error("请求错误");
        }
    }
}
