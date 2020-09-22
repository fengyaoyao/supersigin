<?php


namespace app\user\controller;


use app\admin\service\UserService;
use app\communal\Count;
use cmf\controller\UserBaseController;
use think\Db;

/**
 * 普通用户查看代理数据
 * Class LevelController
 * @package app\user\controller
 */
class LevelController extends UserBaseController
{
    public function index(){
        $name  = $this->request->param("name");
        $uid  = get_user('id');
        $list = Db::name("user")->alias('u')
            ->field("u.id,u.pid,u.user_login,u.user_nickname,u.mobile,u.sup_down_public,u.user_email,
        (SELECT count(*) FROM cmf_super_download_log as csdl where u.id = csdl.uid ) as download_count,
        (SELECT count(*) FROM cmf_ios_udid_list as ciul where u.id = ciul.user_id ) as udid_count,
        (SELECT count(*) FROM cmf_user_posted as cup where u.id = cup.uid ) as app_count")
            ->where('pid',$uid)
            ->where('user_type',2)
            ->where(function ($query) use($name){
                if (!empty($name)) {
                    $query->where('user_email', 'like', "%{$name}%");
                }
            })
            ->order("id desc")
            ->paginate(15);

        $sup_down_public = Db::name('user')->where('id',$uid)->field('id,links,sup_down_public')->find();
		$link = $sup_down_public['links'];

		if(empty($link)){
			$link = make_password(6);
			Db::name('user')->where('id',$uid)->update(['links'=>$link]);
		}

        $this->assign([
            'nav'=>'level',
            'name' =>$name,
			'link'=>get_site_url().'/user/register/register?l='.$link,
            'page'=>$list->render(),
            'list'=>$list,
            'sup_down_public'=>$sup_down_public['sup_down_public']//自身公有池数量 
        ]);
        return $this->fetch();
    }

    /**
     * [child_app 查看代理用户的应用]
     * @return [type] [description]
     */
    public function child_app(){

        $id  = $this->request->param("id");
        //应用列表
        $list = Db::field("*,(select count(*) from cmf_super_download_log as sdl where cmf_user_posted.id=sdl.app_id) as download_count,
            (select count(*) from cmf_ios_udid_list as ciul where cmf_user_posted.id=ciul.app_id) as app_count,
            (select count(*) from cmf_super_download_log as sdl where cmf_user_posted.id=sdl.app_id and sdl.device='andriod') as andriod_download_count,
            (select count(*) from cmf_super_download_log as sdl where cmf_user_posted.id=sdl.app_id and sdl.device!='andriod') as ios_download_count
            ")->name("user_posted")
            ->where('uid',$id)
            ->where('status', '<',3)
            ->order("id desc")
            ->paginate(10);

        $this->assign([
            'nav'=>'level',
            'page'=>$list->render(),
            'list'=>$list,
        ]);
        return $this->fetch();
    }



    //超级签应用详情
    public function sup_details($id,$uid){

        $user_id   = session('user.id');
        $tab = input('tab');
        $supResult = Db::name("user_posted")
            ->where('id',$id)
            ->where('uid',$uid)
            ->find();

        if(!$supResult){
            $this->error('页面不存在!');
        }

        //超级签
        $appCount = Db::name('ios_udid_list')
            ->where('user_id',$uid)
            ->where('app_id',$id)
            ->count();
        $this->assign([
            'appCount'     => $appCount,
            'assets'       => $supResult,
            'nav'          => 'app',
            'id'           => $id,
            'tab'         =>$tab
        ]);

        return $this->fetch();
    }


    //详细下载数据
    public function downData($uid,$time=false){

        $list = Count::getDownCounByWeek($uid,7,true,$time);
        //dump($list);exit
        $time = $time?strtotime($time.' 00:00:00'):time();
        $week = Count::getDays(7,$time);
        $data_arr=[];
        $data_arr['count_udid'][0] = isset($list['count_udid'][$time])?$list['count_udid'][$time]:0;
        $data_arr['count_down'][0] = isset($list['count_down'][$time])?$list['count_down'][$time]:0;
        foreach ($week as $k=>$v){
            if(isset($list['count_down'][$v])){
                $data_arr['count_udid'][]= $list['count_udid'][$v];
                $data_arr['count_down'][]= $list['count_down'][$v];

            }else{
                $data_arr['count_udid'][] = 0;
                $data_arr['count_down'][] = 0;
            }
        }
        $this->assign([
            'week' => json_encode($week),
            'count_down' =>json_encode(array_reverse($data_arr['count_down'])),
            'count_udid' => json_encode(array_reverse($data_arr['count_udid'])),
            'uid'=>$uid,
            'time'=>date('Y-m-d',$time)


        ]);
        return $this->fetch();
    }


    //下线充值
    public function recharge(){
        $uid = get_user('id');
        $sup_down_public = Db::name('user')->where('id',$uid)->field('sup_down_public')->find();
        $sup_down_public = $sup_down_public['sup_down_public'];//自身公有池数量
        $num = input('num');
        $sid = input('sid');
        if(!is_numeric($num) || $num > $sup_down_public){
            return json(['code'=>0,'msg'=>'数据无效']);
        }
        //事务开始
        Db::startTrans();
        try{
            //为下线充值
            $s_info = Db::name('user')->where('id',$sid)->where('pid',$uid)->field('sup_down_public')->find();
            $s_sup_down_public = $s_info['sup_down_public'] + $num;
            Db::name('user')->where('id',$sid)->where('pid',$uid)->update(['sup_down_public'=>$s_sup_down_public]);
            Db::name('sup_charge_log')->insert([
                'uid'=>$sid,
                'puid'=>$uid,
                'num'=>$num,
                'type'=>1,
                'addtime'=>time(),
                'addtype'=>2,
                'msg'=>'上级充值('.$num.')设备数'
            ]);
            //扣除自身数量
            $sup_down_public = $sup_down_public - $num;
            Db::name('user')->where('id',$uid)->update(['sup_down_public'=>$sup_down_public]);
            Db::name('sup_charge_log')->insert([
                'uid'=>$uid,
                'num'=>$num,
                'type'=>1,
                'addtime'=>time(),
                'addtype'=>2,
                'is_add'=>0,
                'msg'=>'给id:'.$sid.'用户充值'.$num.'设备数'
            ]);
            // 提交事务
            Db::commit();
            return json(['code'=>200,'msg'=>'充值成功']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['code'=>0,'msg'=>'充值失败']);
        }
    }
}