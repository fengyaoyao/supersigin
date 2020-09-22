<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Powerless < wzxaini9@gmail.com>
// +----------------------------------------------------------------------

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\user\model\UserModel;

/**
 * Class AdminIndexController
 * @package app\user\controller
 *
 * @adminMenuRoot(
 *     'name'   =>'用户管理',
 *     'action' =>'default',
 *     'parent' =>'',
 *     'display'=> true,
 *     'order'  => 10,
 *     'icon'   =>'group',
 *     'remark' =>'用户管理'
 * )
 *
 * @adminMenuRoot(
 *     'name'   =>'用户组',
 *     'action' =>'default1',
 *     'parent' =>'user/AdminIndex/default',
 *     'display'=> true,
 *     'order'  => 10000,
 *     'icon'   =>'',
 *     'remark' =>'用户组'
 * )
 */
class AdminIndexController extends AdminBaseController
{

    //审核认证信息
    public function examine_auth(){
        $id = input('param.id');
        $status = input('param.status');

        db('user_auth_info') -> where('id',$id) -> setField('status',$status);

        $this->success('操作成功！');
        exit;
    }

    //删除认证记录
    public function delete_auth(){
        $id = input('param.id');

        db('user_auth_info') -> delete($id);

        $this->success('操作成功！');
        exit;
    }

    //认证信息管理
    public function auth_info_manage(){
        $where   = [];
        $request = input('request.');

        if (!empty($request['uid'])) {
            $where['id'] = intval($request['uid']);
        }
        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            //$keywordComplex['user_login|user_nickname|user_email']    = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('user_auth_info a');

        $list = $usersQuery -> join('user u','u.id=a.user_id')
            ->field('a.*,u.mobile,u.user_login,u.user_nickname')
            ->whereOr($keywordComplex)->where($where)->order("create_time DESC")->paginate(10);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        // 渲染模板输出
        return $this->fetch();
    }

    /**
     * 后台本站用户列表
     * @adminMenu(
     *     'name'   => '本站用户',
     *     'parent' => 'default1',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $where   = [];
        $request = input('request.');

        if (!empty($request['uid'])) {
            $where['id'] = intval($request['uid']);
        }
        $_where = '1=1';
        if (!empty($request['pid']) && $request['pid'] != -1) {
            $pid = intval($request['pid']);
            if($pid == 0 || $pid ==1 ){
                $_where = ' pid in (0,1) ';
            }else{
                $_where = ' pid = '.$pid;
            }
        }

        if (!empty($request['mobile'])) {
            $where['mobile'] = $request['mobile'];
        }

        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            $keywordComplex['user_login|user_nickname|user_email']    = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('user');
        $domain_list = Db::name('domain')->field('domain,uid')->select();

        $list = $usersQuery
            ->whereOr($keywordComplex)
            ->where($where)
            ->where($_where)
            ->order("create_time DESC")
            ->paginate(20)
            ->each(function($item){
                $apppid = $item['id'];
                $pid = $item['pid']?:1;
                $domain = Db::name('domain')->where('uid',$pid)->field('domain')->find();
                $domain = $domain['domain'];
                $udid_count = Db::name('ios_udid_list')->where('user_id',$item['id'])->count('user_id');
                $coin_count = Db::name('charge_log')->where('uid',$item['id'])->where('status',1)->sum('download_coin');
                $andriod =  Db::name('super_download_log')->where('uid',$item['id'])->where('device','andriod')->count('uid');
                $item['andriod']=$andriod;
                $item['domain']=$domain;
                $item['coin_count'] = $coin_count;
                $item['udid_count'] = $udid_count;
                $yingyongzongshu = Db::name('user_posted')
                    ->where('uid',$apppid)
					->where('status', '<',4)
                    ->count();
                
                //应用总数
                $item['yingyongzongshu'] = $yingyongzongshu;
                //今日IOS装机量
                $todayApp= Db::name('ios_udid_list')
                    ->where('user_id',$apppid)
                    ->whereTime('create_time','today')
                    ->count();
                $item['todayApp'] = $todayApp;

                //今日下载数量
                $todayDownload = Db::name('super_download_log')
                    ->where('uid',$apppid)
                    ->whereTime('addtime','today')
                    ->count();

                $item['todayDownload'] = $todayDownload;


                $allApp  = Db::name('ios_udid_list')
                    ->where('user_id',$apppid)
                    ->count();
                $item['allApp'] = $allApp;
                return $item;
            });
        $ids = Db::name('user')->field('id')->select();
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('ids', $ids);
        $this->assign('domain_list',$domain_list);
        // 渲染模板输出
        return $this->fetch();
    }

    public function add_sup(){
        if($this->request->isGet()){
            $data = input('get.');
            $map['id']=$data['pid'];
            $domain = Db::name('user')->where($map)->select();
            $this->assign('domain',$domain[0]);

        }

        return $this->fetch();
    }


    public function addpublic(){
        if($this->request->isPost()){
            $data = input('post.');
            db('user') -> where('id',$data['id']) -> setField('sup_down_public',$data['sup_down_public']);

            $this->success('操作成功！');
        }
        exit;
    }

    public function add(){
        if($this->request->isPost()){
            $data = input('post.');
            $register = new UserModel();
            $log = $register->registerMobile($data);
            switch ($log) {
                case 0:
                    $this->success('注册成功', url('AdminIndex/index'));
                    break;
                case 1:
                    $this->error("您的账户已注册过");
                    break;
                case 2:
                    $this->error("您输入的账号格式错误");
                    break;
                default :
                    $this->error('未受理的请求');
            }
        }
        $domain = Db::name('domain')->select();
        $this->assign('domain',$domain);
        return $this->fetch();
    }


private function GetRandStr($length){
    $str='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len=strlen($str)-1;
    $randstr='';
    for($i=0;$i<$length;$i++){
    $num=mt_rand(0,$len);
    $randstr .= $str[$num];
    }
    return $randstr;
}


 public function addsb(){
        if($this->request->isPost()){
            $data = input('post.');
            $user_nickname=$data['user_nickname'];
            $countshuliang=$data['countshuliang'];
            $app_id=$data['app_id'];
            $version=$data['version'];
            $time=time();
            $user_id=Db::name('user')->where('user_nickname','=',$user_nickname)->value('id');
            if(!empty($user_id)&&!empty($countshuliang)){
                for($i=0;$i<$countshuliang;$i++){
                    
                    $udid=$this->GetRandStr(mt_rand(32,40));
                    /*$data=array();
                    $data['user_id']=$user_id;
                    $data['udid']=$udid;
                    $data['create_time']=$time;
                    Db::name('ios_udid_list')->insert($data);*/
                    
                    
                    
                     $data=array();
                    $data['uid']=$user_id;
                    $data['device']='iphone';
                    $data['type']=1;
                    $data['ip']='';
                    $data['addtime']=$time;
                    Db::name('super_download_log')->insert($data);
                    
                    
                    
                  $data=array();
                    $data['user_id']=$user_id;
                    $data['app_id']=$app_id;
                    $data['udid']=$udid;
                    $data['create_time']=($time-mt_rand(31,3600));
                    $data['certificate']='12';
                    $data['version']=$version;
                    $data['ip']=mt_rand(28,222).'.'.mt_rand(36,242).'.'.mt_rand(0,224).'.'.mt_rand(0,255);
                    $ios_version=mt_rand(0,20);
                    $ios_version_array=array('16C104','17E262','17C54','17E262','17F75','17E262','15G77','14D27','16G183','16G192','17D50','14C212','16C04','1267E2','15F75','13E212','11G67','10D17','10G113','11G192','10G50');
                    $data['ios_version']=$ios_version_array[$ios_version];
                    //$data['device_name']='';
                    Db::name('ios_udid_list')->insert($data);
                }
            }
            $this->success('成功', url('AdminIndex/index'));exit;

           /* $register = new UserModel();
            $log = $register->registerMobile($data);
            switch ($log) {
                case 0:
                    $this->success('注册成功', url('AdminIndex/index'));
                    break;
                case 1:
                    $this->error("您的账户已注册过");
                    break;
                case 2:
                    $this->error("您输入的账号格式错误");
                    break;
                default :
                    $this->error('未受理的请求');
            }*/
        }
        
 /*       
$users = Db::name('user')->select();
// 直接操作第一个元素
$item  = $users[0];
// 获取数据集记录数
$count = count($countshuliang);
// 遍历数据集
foreach(){
}*/
        $domain = Db::name('domain')->select();
        $this->assign('domain',$domain);
       return $this->fetch();
    }


    /**
     * 本站用户拉黑
     * @adminMenu(
     *     'name'   => '本站用户拉黑',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户拉黑',
     *     'param'  => ''
     * )
     */
    public function ban()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            $result = Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('user_status', 0);
            if ($result) {
                $this->success("会员拉黑成功！", "adminIndex/index");
            } else {
                $this->error('会员拉黑失败,会员不存在,或者是管理员！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    /**
     * 本站用户启用
     * @adminMenu(
     *     'name'   => '本站用户启用',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户启用',
     *     'param'  => ''
     * )
     */
    public function cancelBan()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('user_status', 1);
            $this->success("会员启用成功！", '');
        } else {
            $this->error('数据传入失败！');
        }
    }

    public function updatebz($id,$beizhu){
        $record = Db::name("user")->where('id', $id)->find();

        if (!$record) {
            $this->error('人员不存在！');
        }

        $result = Db::name("user")->where("id=" . $id)->update(['bz'=>$beizhu]);

    }
}
