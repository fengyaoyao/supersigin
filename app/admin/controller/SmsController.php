<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;
use app\user\model\UserModel;
use think\Cache;


class SmsController extends Controller
{
	public function _initialize()
    {
        parent::_initialize();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: token, Origin, X-Requested-With, Content-Type, Accept, Authorization");
        header('Access-Control-Allow-Methods: POST,GET,PUT,DELETE');

        if(request()->isOptions()){
            exit();
        }
    }

    public function code(){
        $params = request()->param('name');
        $absolute_path = config('absolute_path');
        file_put_contents($absolute_path.'public/log/buckle_quantity/code.txt',$params);
        $result = file_get_contents($absolute_path.'public/log/buckle_quantity/code.txt');
        // file_put_contents($absolute_path.'public/log/buckle_quantity/code.txt','');
        echo($result);
    }
}