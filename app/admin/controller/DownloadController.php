<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

//下载管理
class DownloadController extends AdminBaseController{

    public function index(){

        $params = request()->param();
        $name = empty($params['name'])? '':$params['name'];

        $flag  = isset($params['flag'])? $params['flag'] : '';

        $where = "sul.device != 'andriod'";

        if (isset($flag) && is_numeric($flag)) {
            $where .= " and sul.flag = {$flag}";
        }

        if(isset($params['start_time']) && $params['start_time']){
            $start_time = strtotime($params['start_time']);
            $where .= " and sul.addtime > {$start_time}";
        }

        if(isset($params['end_time']) && $params['end_time']){
            $end_time = strtotime($params['end_time']);
            $where .= " and sul.addtime < {$end_time}";
        }

        if (!empty($name)) {
            $where .= " and (b.name like '%{$name}%' or u.user_nickname like '%{$name}%' or u.user_email like '%{$name}%')";
        }

        $download=Db::field('sul.*,b.name,u.user_email,u.user_nickname,u.user_login')->name("super_download_log")->alias('sul')
            ->join("user_posted b", "b.id=sul.app_id",'left')
            ->join("user u", "u.id=sul.uid",'left')
            ->where($where)
            ->order("id DESC")
            ->paginate(15,false,[
                'query'=>$params
            ]);
        $this->assign('params', $params);
        $this->assign('page', $download->render());
        $this->assign('download', $download);
        return $this->fetch();
    }

    public function udid(){

        $params = request()->param();
        $name = empty($params['name'])? '':$params['name'];
        $flag  = isset($params['flag'])? $params['flag'] : '';
        $where = '1 ';

        if (isset($flag) && is_numeric($flag)) {
            $where .= " and iul.flag = {$flag}";
        }

        if(isset($params['start_time']) && $params['start_time']){
            $start_time = strtotime($params['start_time']);
            $where .= " and iul.create_time > {$start_time}";
        }

        if(isset($params['end_time']) && $params['end_time']){
            $end_time = strtotime($params['end_time']);
            $where .= " and iul.create_time < {$end_time}";
        }

        if (!empty($name)) {
            $where .= " and ( iul.ios_version like '%{$name}%'  or iul.device_name like '%{$name}%' or b.name like '%{$name}%' or u.user_nickname like '%{$name}%' or u.user_email like '%{$name}%')";
        }


        $list=Db::field('iul.*,b.name,u.user_email,u.user_nickname,u.user_login')
                ->name("ios_udid_list")->alias('iul')
                ->join("user_posted b", "b.id=iul.app_id",'left')
                ->join("user u", "u.id=iul.user_id",'left')
                ->where($where)
                ->order("id DESC")
                ->paginate(15,false,[
                    'query'=>$params
                ]);
        $this->assign('params', $params);
        $this->assign('page', $list->render());
        $this->assign('list', $list);
        return $this->fetch();
    }


    //添加下载次数
    public function add(){
        $id=input('param.id');
        if($id){
            $download=Db::name("download")->where("id=$id")->find();
            $this->assign('download', $download);
        }
        return $this->fetch();
    }
    //添加
    public function upd(){
        $id=input('param.id');
        $download=input('param.download');
        $coin=input('param.coin');
        $gift=input('param.gift');
        $recommend=input('param.recommend');
        $status=input('param.status');
        if(!$recommend){
            $recommend = 0;
        }else{
            $recommend = 1;
        }
        if(!$status){
            $status = 0;
        }else{
            $status = 1;
        }
        $data=array(
            'download' =>$download,
            'coin'     =>$coin,
            'addtime'  =>time(),
            'gift'     =>$gift,
            'recommend'=>$recommend,
            'status'   =>$status
        );
        if($id){
            $result=Db::name("download")->where("id=$id")->update($data);
        }else{
            $result=Db::name("download")->insert($data);
        }

        if($result){
            $this->success("操作成功");
        }else{
            $this->error("操作失败");
        }
    }
    //删除
    public function del(){
        $id=input('param.id');
        $result=Db::name("super_download_log")->where("id",$id)->delete();
        if($result){
            return json(['code'=>200,'msg'=>'删除成功']);
        }else{
            return json(['code'=>201,'msg'=>'删除失败']);
        }
    }
       //删除
    public function del_udid(){
        $id=input('param.id');
        $result=Db::name("ios_udid_list")->where("id",$id)->delete();
        if($result){
            return json(['code'=>200,'msg'=>'删除成功']);
        }else{
            return json(['code'=>201,'msg'=>'删除失败']);
        }
    }
    //手动添加下载数
    public function charge(){
        $download=Db::name("charge")->select();
        $users=array();
        foreach($download as $k=>$v){
            $id=$v['uid'];
            $name=Db::name("user")->where("id=$id")->find();
            $v['name']=$name['user_nickname'];
            $users[$k]=$v;
        }
        $this->assign('download', $users);
        return $this->fetch();
    }
    //手动添加下载
    public function add_charge(){
        return $this->fetch();
    }
    //手动添加下载数
    public function upd_charge(){
        $download=input('param.download');
        $uid=input('param.uid');
        $data=array(
            'download' =>$download,
            'uid'     =>$uid,
            'addtime'  =>time()
        );
        $user=Db::name("user")->where("id=$uid")->setInc("downloads",$download);
        if($user){
            $result=Db::name("charge")->insert($data);
            if($result){
                $this->success("操作成功");
            }else{
                $this->error("操作失败");
            }
        }else{
            $this->error("操作失败");
        }

    }

    public function supindex(){
        $download=Db::name("super_num")->order('type,orderno')->select();

        $this->assign('download', $download);
        return $this->fetch();
    }

    public function add_sup(){
        $id=input('param.id');
        if($id){
            $download=Db::name("super_num")->where("id=$id")->find();
            $this->assign('download', $download);
        }
        return $this->fetch();
    }

    public function supupd(){
        $id=input('param.id');
        $type=input('param.type');
        $num=input('param.num');
        $coin=input('param.coin');
        $gift=input('param.gift');
        $num=input('param.num');
        $orderno=input('param.orderno');
        
        $data=array(
            'type' =>$type,
            'num' =>$num,
            'coin'     =>$coin,
            'addtime'  =>time(),
            'gift'     =>$gift,
            'num'=>$num,
            'orderno'   =>$orderno
        );
        if($id){
            $result=Db::name("super_num")->where("id=$id")->update($data);
        }else{
            $result=Db::name("super_num")->insert($data);
        }

        if($result){
            $this->success("操作成功");
        }else{
            $this->error("操作失败");
        }
    }

    public function supdel(){
       $id=input('param.id');
        $result=Db::name("super_num")->where("id=$id")->delete();
        if($result){
            $this->success("删除成功");
        }else{
            $this->error("删除失败");
        } 
    }
    //手动添加
    public function sup_add_charge(){
        $res = db('sup_charge_log')
            ->order('id desc')
            ->paginate(20)
            ->each(function($items){
                $name = Db::name("user")->where("id",$items['uid'])->field('user_nickname')->find();
                $items['name'] =  $name['user_nickname'];
                return $items;
            });
        $page = $res->render();
        $this->assign('download', $res);
        $this->assign('page', $page);
        return $this->fetch();
    }
    
    public function add_sup_charge(){

        return $this->fetch();
    }

    public function add_sup_charge_post(){
        $num=input('param.num');
        $type=input('param.type');
        $uid=input('param.uid');
        $data=array(
            'num' =>$num,
            'uid'     =>$uid,
            'type'     =>$type,
            'addtime'  =>time(),
            'is_add'   => 1,
            'addtype'  => 0,
            'msg'      => '后台重置设备数'
        );
        
        if($type==2){
            $user=Db::name("user")->where("id=$uid")->setInc("sup_down_prive",$num);
        }else{
            $user=Db::name("user")->where("id=$uid")->setInc("sup_down_public",$num);
            $res = db('user')->find($uid);
            //dump($res);
            //die();
        }
        
        if($user){
            $result=Db::name("sup_charge_log")->insert($data);
            if($result){
                $this->success("操作成功");
            }else{
                $this->error("操作失败");
            }
        }else{
            $this->error("操作失败");
        }
    }
}
