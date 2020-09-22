<?php

namespace app\user\controller;

use app\communal\Count;
use cmf\controller\UserBaseController;
use MingYuanYun\AppStore\Client;
use think\Db;
use Qiniu\entry;
use Qiniu\Auth;

class AppController extends UserBaseController
{

    function _initialize(){
        parent::_initialize();
    }

    public function index()
    {
        $uid = session('user.id');
        $name  = $this->request->param("name");


        //应用列表
        $list = Db::name("user_posted")
            ->where('uid',$uid)
            ->where('status', '<',3)
            ->where(function ($query) use($name){
                if (!empty($name)) {
                    $query->where('name', 'like', "%{$name}%");
                }
            })
            ->order("id desc")
            ->paginate(10);

        $this->assign([
            'nav'=>'app',
            'name' =>$name,
            'page'=>$list->render(),
            'list'=>$list,
        ]);
        return $this->fetch();
    }
}
