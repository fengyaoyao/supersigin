<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/13 0013
 * Time: 下午 14:13
 */

namespace app\admin\controller;

use app\admin\service\ChargeService;
use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\AdminMenuModel;
use Qiniu\Auth;    // 引入鉴权类
use Qiniu\Storage\UploadManager;    // 引入上传类
use think\Validate;

//会员管理
class MembersController extends AdminBaseController
{
    public function index()
    {

        $where = "us.user_type=2";
        $name = input('param.name');
        $id = input('param.id');
        if (!empty($id)) {
            $where .= ' and us.id ='.$id;
        }
        if (!empty($name)) {
            $where .= "  and(us.user_email like '%".$name."%'" ." or us.user_nickname like " ."'%".$name."%'".')';
        }

        $user = Db::field("*,
            (select count(*) from cmf_super_download_log as sdl where us.id=sdl.uid) as download_count,
            (select count(*) from cmf_user_posted as up where us.id=up.uid) as app_count,
            (select count(*) from cmf_user as p where p.pid = us.id) as child_count,
            (select count(*) from cmf_super_download_log as sdl where us.id=sdl.uid and sdl.device='andriod') as andriod_download_count,
            (select count(*) from cmf_super_download_log as sdl1 where us.id=sdl1.uid and sdl1.device !='andriod') as ios_download_count,

            (select sum(money) from cmf_sup_charge_log as sccl where us.id=sccl.uid and sccl.addtype = 0 and sccl.is_add = 1) as money_count

            ")
        ->name("user")
        ->alias('us')
        ->where($where)
        ->order('us.sup_down_public desc')
        ->paginate(15);
        $this->assign('user', $user);
        $this->assign('members', session('members'));
        $this->assign('page', $user->render());
        return $this->fetch();
    }

    public function add_user(){

        $params = $this->request->param();
        $this->assign('params', $params);
        return $this->fetch();
    }

        /**
     * 前台用户注册提交
     */
    public function doRegister(){

        if ($this->request->isPost()) {

            $rules = [
                'user_email' => 'require|email|unique:user',
                'user_nickname' => 'require|min:2|max:32|unique:user',
                'user_pass' => 'require|min:6|max:32',
                'pid' => 'require|min:0'
            ];

            $post = $this->request->post();

            $validate = new Validate($rules);

            $validate->message([
                'user_email.require'=> '邮箱账户不能为空',
                'user_email.email'  => '邮箱规则错误',
                'user_email.unique' => '该邮箱已经存在了',
                'user_pass.require' => '密码不能为空',
                'user_pass.max'     => '密码不能超过32个字符',
                'user_pass.min'     => '密码不能小于6个字符',
                'user_nickname.require' => '用户昵称不能为空',
                'user_nickname.max'     => '用户昵称不能超过32个字符',
                'user_nickname.min'     => '用户昵称不能小于6个字符',
                'user_nickname.unique'  => '该用户昵称已经存在了',

            ]);
            if (!$validate->check($post)) {
                $this->error($validate->getError());
            }

            $data   = [
                'user_email'      => $post['user_email'],
                'user_nickname'   => $post['user_nickname'],
                'user_pass'       => cmf_password($post['user_pass']),
                'create_time'     => time(),
                'last_login_time' => time(),
                'user_status'     => 2,
                "user_type"       => 2,
                'pid'             => $post['pid'],
            ];
            
            $userId =  Db::name("user")->insertGetId($data);
            if ($userId) {
                $this->success('添加成功');
            } 

            $this->success('添加失败');

        } else {
            $this->error("请求错误");
        }
    }

    //手动添加下载数
    public function recharge(){
        $num = input('param.num');
        $uid = input('param.uid');
        $money = input('param.money');

        $data=[
            'num' =>$num,
            'uid'     =>$uid,
            'type'     =>1,
            'addtime'  =>time(),
            'is_add'   => 1,
            'money'    => $money,
            'addtype'  => 0,
            'msg'      => '后台重置设备数'
        ];
        
        $user = Db::name("user")->where("id",$uid)->setInc("sup_down_public",$num);

        if($user && Db::name("sup_charge_log")->insert($data)){
            $this->success("操作成功");
        }

        $this->error("操作失败");
        
    }

    /*用户下载次数*/
    public function cishu($uid)
    {
        $daytime = Db::name("sup_charge_log")->join("user_posted b", "b.id=a.app_id")->alias("a")->where("b.uid=$uid")->count();
        return $daytime ? $daytime : '0';
    }

    /*文件下载次数*/
    public function file_num($id)
    {
        $daytime = Db::name("sup_charge_log")->where("app_id=$id")->count();
        return $daytime ? $daytime : '0';
    }
    //扣量比例
    public function take_out()
    {
        $id = input('param.id');
        $num = input('param.num');
        if (!is_numeric($num) || empty($num) || $num > 1 || $num < 0) {
            return json(['code'=>0,'msg'=>'输入内容不合法']);
        }

        $user = Db::name("user")->where('id',$id)->update(['take_out'=>$num]);
        if ($user) {
            return json(['code'=>200,'msg'=>'设置成功!']);
        } else {
            return json(['code'=>201,'msg'=>'设置失败!']);
        }
    }
    //禁用
    public function upd()
    {
        $id = input('param.id');
        $data = array(
            'user_status' => input('param.user_status')
        );
        $user = Db::name("user")->where("id=$id")->update($data);
        if ($user) {
            $this->success("操作成功");
        } else {
            $this->success("操作失败");
        }
    }

    public function nick(){
        $id = input('id');
        $nick = input('nick');
        if(empty($id)){
            return json(['code'=>0,'msg'=>'ID不存在']);
        }
        if(empty($nick)){
            return json(['code'=>0,'msg'=>'昵称不能为空']);
        }
        $has = Db::name('user')->where('id!='.$id)->where('user_nickname',$nick)->count();
        if($has){
            return json(['code'=>0,'msg'=>'该昵称已被使用']);
        }
        Db::name('user')->where('id',$id)->update(['user_nickname'=>$nick]);
        return json(['code'=>200,'msg'=>'修改成功']);
    }

    //文件详情
    public function sele()
    {
        if ($_POST) {
            session('sele', $_POST);
        }
        if (!isset($_GET['page']) and empty($_POST)) {
            session('sele', null);
        }
        $zid = input('param.id') ? input('param.id') : session('sele.id');
        $where = "uid=$zid";
        $where .= session('sele.sid') ? " and id=" . session('sele.sid') : '';
        $where .= session('sele.er_logo') ? " and er_logo='" . session('sele.er_logo') . "'" : '';

        $result = Db::name("user_posted")->where($where)->paginate(10);
        $tmpUser = array();
        foreach ($result as &$v) {
            $id = $v['id'];
            $v['num'] = $this->file_num($id);
            $tmpUser[] = $v;
        }
        $this->assign('result', $tmpUser);
        $this->assign('uid', $zid);
        $this->assign('sele', session('sele'));
        $this->assign('page', $result->render());
        return $this->fetch();
    }

    //删除文件包
    public function del()
    {
        $id = input('param.id');
        $name = Db::name("user_posted")->where("id=" . $id)->find();
        $ymurl = explode('/', $name['url']);
        $type = $this->del_tok($ymurl[3]);
        if (!$type) {
            $result = Db::name("user_posted")->where("id=" . $id)->delete();
            if ($result) {
                $this->success("删除成功");
            } else {
                $this->success("删除失败");
            }
        } else {
            $this->success("删除失败");
        }

    }

    //删除七牛文件
    public function del_tok($url)
    {
        require_once(PLUGINS_PATH . '/qiniu/autoload.php');
        // 需要填写你的 Access Key 和 Secret Key
        $accessKey = $_SESSION['think']['user']['accessKey'];
        $secretKey = $_SESSION['think']['user']['secretKey'];
        $bucket = $_SESSION['think']['user']['bucket'];

        // 构建鉴权对象
        $key = $url;
        $auth = new Auth($accessKey, $secretKey);
        $config = new \Qiniu\Config();
        $bucketManager = new \Qiniu\Storage\BucketManager($auth, $config);
        $err = $bucketManager->delete($bucket, $key);
        return $err ? true : false;
    }

    //充值记录
    public function charge()
    {
        if ($_POST) {
            session('charge', $_POST);
        }
        if (!isset($_GET['page']) and empty($_POST)) {
            session('charge', null);
        }
        $where = session('charge.end_time') ? "addtime <" . strtotime(session('charge.end_time')) : "addtime <" . time();
        $where .= session('charge.id') ? " and uid=" . session('charge.id') : '';
        $where .= session('charge.status') > "0" ? " and status='" . session('charge.status') . "'" : '';
        $where .= session('charge.start_time') ? " and addtime >" . strtotime(session('charge.start_time')) : "";
        $user = Db::name("charge_log")->where($where) -> order('addtime desc')->paginate(10);

        $tmpUser = array();
        foreach ($user as &$v) {
            $id = $v['uid'];
            $name = Db::name("user")->where("id=" . $id)->find();
            $v['name'] = $name['user_nickname'];
            $tmpUser[] = $v;
        }

        $this->assign('user', $tmpUser);
        $this->assign('charge', session('charge'));
        $this->assign('page', $user->render());
        return $this->fetch();
    }

    //用户消费记录
    public function consume(){
        $result = ChargeService::charge();
        $this->assign($result);
        return $this->fetch();
    }

    public function agent(){
        $is_true = input('is_true');
        if($is_true===null || $is_true==-1){
            $where="1=1";
        }else{
            $where='d.is_true = '.$is_true;
        }
        $agent = Db::name('domain')
            ->alias('d')
            ->join('user u','d.uid = u.id')
            ->where($where)
            ->field('d.*,u.mobile')
            ->order('d.id desc')
            ->paginate(10,false,['query'=>request()->param()]);

        $this->assign([
            'agent'=>$agent,
            'page'=>$agent->render(),
            'is_true' =>$is_true,
        ]);
        return $this->fetch();
    }

    public function agent_edit(){
        $uid = input('uid');
        $is_true = input('is_true');
        $res = Db::name('domain')->where('uid',$uid)->update(['is_true'=>$is_true]);
        if($res){
            return json(['code'=>200,'msg'=>'修改成功']);
        }
        return json(['code'=>0,'msg'=>'修改失败']);
    }


    public function add(){
        $id = input('id');
        if ($id) {
            $user = Db::name('user')->where('id',$id)->find();
            $this->assign(['user'=>$user]);
        }
        return $this->fetch();
    }


    public function addpublic(){
        if($this->request->isPost()){
            $data = input('post.');
            $number = $data['num'];
            for($i = 0 ; $i < $number ;$i++){
                Db::name('sup_charge_log')->insert([
                    'uid'     =>$data['uid'],
                    'num'     =>1,
                    'type'    =>1,
                    'addtime' =>time(),
                    'addtype' =>1,
                    'is_add'  =>0,
                    'msg'     =>'下载应用:('.$data['msg'].')设备扣除',
                    'lx' => '1'
                ]);
            }




            $this->success('操作成功！');
        }
        exit;
    }
	   

    //下载记录列表
    public function add_certificate(){

        $result = ChargeService::chargexizai();
        $this->assign($result);
        return $this->fetch();

    }
	
	public function getNum(){
        list($msec, $sec) = explode(' ', microtime());
        return (float) $sec;
    }
}