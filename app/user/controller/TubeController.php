<?php

namespace app\user\controller;

use app\communal\Count;
use cmf\controller\UserBaseController;
use MingYuanYun\AppStore\Client;
use think\Db;
use Qiniu\entry;
use Qiniu\Auth;

class TubeController extends UserBaseController
{

    function _initialize(){
        parent::_initialize();
    }

    public function index()
    {
        $uid       = session('user.id');
        $pid       = session('user.pid');

        $userInfo  = Db::name("user")->where("id",$uid)->find();
        $childUserInfo = Db::name("user")->field('id')->where("pid",$uid)->column('id');

        $sup_down_public =  $userInfo['sup_down_public'];

        //设备列表
        $appResult = Db::name("user_posted")
            ->where('uid',$uid)
            ->where('status', '<',3)
            ->order("id desc")
            ->select();

        //当天装机数量
        $todayApp = Db::name('ios_udid_list')
            ->where('user_id',$uid)
            ->whereTime('create_time','today')
            ->count();

        //当天下载量
        $todayDownload = Db::name('super_download_log')
            ->where('uid',$uid)
            ->whereTime('addtime','today')
            ->count();

        // 当月下载数量
        $monthDownload = Db::name('super_download_log')
            ->where('uid',$uid)
            ->whereTime('addtime','month')
            ->count();

        //总共下载数量
        $allDownload = Db::name('super_download_log')
            ->where('uid',$uid)
            ->count();

        if ($pid == 0 && !empty($childUserInfo)) {

            //当天装机数量
            $todayApp += Db::name('ios_udid_list')
                ->where('user_id','in',$childUserInfo)
                ->whereTime('create_time','today')
                ->count();

            //当天下载量
            $todayDownload += Db::name('super_download_log')
                ->where('uid','in',$childUserInfo)
                ->whereTime('addtime','today')
                ->count();

            // 当月下载数量
            $monthDownload += Db::name('super_download_log')
                ->where('uid','in',$childUserInfo)
                ->whereTime('addtime','month')
                ->count();

            //总共下载数量
            $allDownload += Db::name('super_download_log')
                ->where('uid','in',$childUserInfo)
                ->count();

            $sup_down_public += Db::name("user")->where("pid",$uid)->sum('sup_down_public');
        }

        //获取7天的下载数据
        $week = Count::getDays(7);

        $data_arr = [];
        foreach ($week as $k=>$v){

		    $start1 = $v.' 00:00:00';
            $end1 = $v.' 23:59:50';
            $start = strtotime($start1);
            $end = strtotime($end1);

            $count = Db::name('ios_udid_list')
                        ->where('user_id',$uid)
                        ->where('create_time','>',$start)
                        ->where('create_time','<',$end)
                        ->count();
            if ($pid == 0 && !empty($childUserInfo)) {

                 $count += Db::name('ios_udid_list')
                        ->where('user_id','in',$childUserInfo)
                        ->where('create_time','>',$start)
                        ->where('create_time','<',$end)
                        ->count();
            }

            $data_arr['count_udid'][] = $count;
		}

       
        $this->assign([
            'week'         => json_encode($week),
            'count_udid'   => json_encode($data_arr['count_udid']),
            'assets'       => $appResult,
            'todayDownload'=> $todayDownload,
            'todayApp'     => $todayApp,
            'monthDownload'=> $monthDownload,
            'allDownload'  => $allDownload,
            'user'         => $userInfo,
            'sup_down_public'=>$sup_down_public,
            'nav'          => 'tube',
            'config'       => get_config()
            
        ]);

        return $this->fetch();
    }
	
	public function set_channel($id){
		$user_id  = get_user('id');
		$supResult = Db::name('user_posted')
            ->where('id',$id)
            ->where('uid',$user_id)
            ->find();
		if(empty($supResult)){
			return json(['code'=>0,'msg'=>'应用不存在']);	
		}
        $num = intval(input('num'));
        $count = intval(input('count'));
		if(empty($num)){
			return json(['code'=>0,'msg'=>'请输入创建个数']);	
		}
		if($num>100){
			return json(['code'=>0,'msg'=>'每次最多只能创建100个']);	
		}
		if(empty($count)){
			return json(['code'=>0,'msg'=>'请输入单个使用次数']);	
		}
		if($count>10000){
			return json(['code'=>0,'msg'=>'单个使用次数不能超过10000']);	
		}
		$data = [];
		for($i=0;$i<$num;$i++){
			$data[] = [
				'user_id' => $user_id,
				'app_id' => $id,
				'code' => random_str(8),
				'num' => $count,
				'create_time' => time()
			];
		}
		$result = Db::name('user_channel')->insertAll($data);
		if($result===false){
			return json(['code'=>0,'msg'=>'添加失败']);
		}
		return json(['code'=>1,'msg'=>'操作成功']);
	}
	
    public function edit_channel($id){
        $user_id = get_user('id');
        $down_num  = input('down_num');
        $result = Db::name('user_channel')
            ->where('user_id',$user_id)
            ->where('id',$id)
            ->where('status',1)
            ->update(['num'=>intval($down_num)]);

        return $result?json(['msg'=>'操作成功']):json(['msg'=>'操作失败']);
    }

	public function del_channel($id){
        $user_id = get_user('id');
        $result = Db::name('user_channel')
            ->where('user_id',$user_id)
            ->where('id',$id)
            ->where('status',1)
            ->update(['status'=>0]);
        if ($result) {
            return json(['code'=>1,'msg'=>'删除成功']);
        } else {
            return json(['code'=>0,'msg'=>'删除失败']);
        }
    }
	
	public function channel_list($id){
		$user_id  = get_user('id');
		$data = [];
		$data = Db::name('user_channel')
                ->where('user_id',$user_id)
                ->where('app_id',$id)
                ->where('status',1)
                ->paginate(10)
                ->each(function($v){
                    $v['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                    $v['down_num'] = '<input type="text" onBlur="edit_down_num(this,'.$v['id'].')" onFocus="edit_down(this)" class="down_num_input down_num_input_hover" value="'.$v['num'].'">';
                    $v['count'] = Db::name('ios_udid_list')->where('user_id = '.$v['user_id'].' and app_id = '.$v['app_id'].' and channel = '.$v['id'])->count();
					$v['action'] = '<a style="padding: 0 15px" href="javascript:void(0)" onclick="del('.$v['id'].')"  class="layui-btn layui-btn-danger layui-btn-sm">删除</a>';
                    return $v;
                })
                ->toArray();
		return json($data);
	}

    //超级签应用详情
    public function sup_details($id){
        $user_id   = session('user.id');
        $tab = input('tab');
        $supResult = Db::name("user_posted")
            ->where('id',$id)
            ->where('uid',$user_id)
            ->find();

        if(!$supResult){
            $this->error('页面不存在!');
        }

        //超级签
        $appCount = Db::name('ios_udid_list')
            ->where('user_id',$user_id)
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

    
    //获取数据统计接口
    public function get_sup_details_data($id,$type){
        $user_id   = get_user('id');  // || session('user.id');
        $data      = [];
        if($type=='sup'){
            //超级签
            $data = Db::name('ios_udid_list')
            //    ->where('user_id',$user_id)
                ->where('app_id',$id)
                ->order('create_time asc')
                ->paginate(10)
                ->each(function($item){
                    $item['create_time'] = date('Y-m-d H:i:s',$item['create_time']);
					if($item['channel']){
						$channel = Db::name('user_channel')->where('app_id',$item['app_id'])->where('id',$item['channel'])->field('code')->find();
						if(!empty($channel)){
							$item['channel'] = $channel['code'];
						}
					}
                    return $item;
                })
                ->toArray();
        }else if($type == 'old'){
            $data = Db::name('user_posted_log')
                ->where('uid',$user_id)
                ->where('posted_id',$id)
                ->paginate(10)
                ->each(function($item){
                    $item['creattime'] = date('Y-m-d H:i:s',$item['creattime']);
                    //下载次数
                    $item['version'] = $item['version']?$item['version']:0;
                    $down_count = Db::name('super_download_log')->where('app_id = '.$item['posted_id'].' and version = \''.$item['version'].'\'')->count();
                    $uuid_count = Db::name('ios_udid_list')->where('app_id = '.$item['posted_id'].' and version = \''.$item['version'].'\'')->count();
                    $item['down_count']=$down_count;
                    $item['uuid_count']=$uuid_count;
                    return $item;
                })
                ->toArray();
        }else if($type == 'down'){
            $data = Db::name('super_download_log')
                ->where('app_id',$id)
                ->order('addtime asc')
                ->paginate(10)
                ->each(function($item){
                    $item['addtime'] = date('Y-m-d H:i:s',$item['addtime']);
                    return $item;
                })
                ->toArray();
        }else if($type == 'hb'){
            $data = Db::name('user_posted')
                ->where('uid',$user_id)
                ->where('id',$id)
                ->field('andriod_url')
                ->find();
            if($data['andriod_url']){
                if(strpos($data['andriod_url'],'http') === false && strpos($data['andriod_url'],'https') === false){
                    $userInfo = upd_tok_config();
                    $data['andriod_url'] = 'http://'.$userInfo['domain'].'/'.$data['andriod_url'];
                }
            }

        }else if($type == 'ads'){
			$data = Db::name('user_ads')
                ->where('posted_id',$id)
				->paginate(5)
                ->each(function($v){
                    $v['addtime'] = date('Y-m-d H:i:s',$v['addtime']);
					$v['url'] = '<a href="'.$v['img'].'" target="_blank" style="color: #0c85da;"><img style="height:50px;" src="'.$v['img'].'"></a>';
					$v['action'] = '<a style="padding: 0 15px" href="javascript:void(0)" onclick="delAds('.$v['id'].')"  class="layui-btn layui-btn-danger layui-btn-sm">删除</a>';
					return $v;
                })
                ->toArray();
		}

        return json($data);
    }

    //应用状态修改
    public function sup_status_update(){
        $uid = get_user('id');
        $id  = input('id');

        $data['status'] = input('status');

        $result = Db::name('user_posted')
            ->where('uid',$uid)
            ->where('id',$id)
            ->update($data);

        return $result?json(['code'=>200]):json(['code'=>0]);
    }

    //应用合并上传安卓包
   public function sup_upload_apk(){
        $id          = input('id');
        $uid         = get_user('id');
        $file        = request()->file('file');
        $andriod_url = input('andriod_url');

        if($file){
            $filename_new = md5(time()).'.apk';
            $path = ROOT_PATH.'public/upload/super_signature/'.date('Ymd',time()).'/';
            $info = $file->validate(['ext'=>'apk'])->move($path,$filename_new);
            if($info){
            	//$res = alUpload(['fileName'=>'apk/'.$filename_new,'filePath'=>$path.$filename_new]);
            
                $res = get_site_url().'/upload/super_signature/'.date('Ymd',time()).'/'.$filename_new;
                if($res){
                
                    //删除本地文件
                    $real_path = $info->getRealPath();
                    unset($info);
                    //@unlink($real_path);
                    //写入
                    Db::name('user_posted')
                        ->where('uid',$uid)
                        ->where('id',$id)
                        ->update(['andriod_url'=>$res]);
                    return json(['code'=>200,'msg'=>'上传成功','url'=>$res]);
                }else{
                    return json(['code'=>0,'msg'=>'上传失败']);
                }
            }
        }else if($andriod_url){
            Db::name('user_posted')
                ->where('uid',$uid)
                ->where('id',$id)
                ->update(['andriod_url'=>$andriod_url]);
            return json(['code'=>200,'msg'=>'上传成功']);
        }
    }

	public function del_ads($id){
		$result = false;
		if($id){
			$result = Db::name('user_ads')
            ->where('id',$id)
            ->delete();
		}
        if ($result) {
            return json(['code'=>1,'msg'=>'删除成功']);
        } else {
            return json(['code'=>0,'msg'=>'删除失败']);
        }
    }

	public function sup_upload_ads()
    {
        $id  = input('id');
		$has = Db::name('user_ads')
            ->where('posted_id',$id)
            ->count();
		if($has>=3){
			return json(['code'=>1,'msg'=>'轮播图最多支持3张']);
		}
		$data = [];
        $file = $this->request->file('file');
        if ($file) {
            $result = $file->validate([
                'ext' => 'jpg,jpeg,gif,png',
                'size' => 5242880 //5M
            ])->move(APP_ROOT.'/upload/ads/');

            if ($result) {
                $imgSaveName = str_replace('//', '/', str_replace('\\', '/', $result->getSaveName()));
                $img = '/upload/ads/' . $imgSaveName;
            } else {
                return json(['code'=>1,'msg'=>'图片上传失败请刷新重试']);
            }
            $data['img'] = $img;
        }
		$data['posted_id'] = $id;
		$data['addtime'] = time();			
        $result = Db::name("user_ads")->insert($data);
        if ($result) {
            return json(['code'=>200,'msg'=>'添加成功']);
        } else {
            return json(['code'=>1,'msg'=>'添加失败']);
        }
    }

	//上传游戏图标
    public function sup_upload_icon(){
        $id          = input('id');
        $uid         = get_user('id');
        $file        = request()->file('file');
        if($file){
            $filename_new = md5(time()).'.png';
            
	        //设置图片保存路径
	        $path = "/upload/ads/".date("Ymd",time());
	        $icon_path = APP_ROOT.$path.'/';
	        //判断文件夹是否存在
	        if(!is_dir($icon_path)){
	            mkdir($icon_path,0777,true);
	        }
            $info = $file->validate(['ext'=>'png,jpg,jpeg'])->move($icon_path,$filename_new);
            if($info){
		        $result = Db::name('user_posted')
		            ->where('uid',$uid)
		            ->where('id',$id)
		            ->update(['img'=>$path.'/'.$filename_new]);
            
                return json(['code'=>200,'msg'=>'上传成功','url'=>$path.'/'.$filename_new]);
            }
        }else{
            return json(['code'=>0,'msg'=>'请选择文件']);
        }
    }

    //超级签应用详情修改
    public function sup_details_update(){
        $uid  = get_user('id');
        $data = input('post.');
        $id   = $data['id'];
        if(isset($data['status'])){
            $data['status'] = $data['status']=='on' ? 1 : 0;
        }
		if(isset($data['theme'])&&in_array($data['theme'],[0,1,2,3,4,5,6])){
            $data['theme'] = intval($data['theme']);
        }
		if(isset($data['way'])&&$data['way']==1&&$data['pass']==''){
			return json(['code'=>100,'msg'=>'请设置安装密码']);
		}
		if(isset($data['way'])&&$data['way']==2){
			$data['money'] = intval($data['money']);
			if(empty($data['money'])){
				return json(['code'=>100,'msg'=>'请设置付费金额']);
			}
		}
		unset($data['file']);
		$app = Db::name('user_posted')->where('uid', $uid)->where('id', $id)->find();
		$data['vpn'] = 0;
		$this->mobileconfig($id);
        unset($data['id']);
        $result = Db::name('user_posted')
            ->where('uid',$uid)
            ->where('id',$id)
            ->update($data);
		
        return $result===false ? json(['code'=>0,'msg'=>'修改失败']) : json(['code'=>200]);
    }

    public function delApp($id){
        $uid    = session('user.id');
        $record = Db::name("user_posted")->where('uid', $uid)->where("id=" . $id)->find();

        if (!$record) {
            $this->error('应用不存在！');
        }

        $result = Db::name("user_posted")->where("id=" . $id)->update(['status'=>3]);

        if ($result) {
            return json(['code'=>200,'msg'=>'删除成功']);
        } else {
            return json(['code'=>0,'msg'=>'删除失败']);
        }
    }


    public function updateApp($id,$beizhu){
        $record = Db::name("user_posted")->where('id', $id)->find();

        if (!$record) {
            $this->error('应用不存在！');
        }

        $result = Db::name("user_posted")->where("id=" . $id)->update(['bz'=>$beizhu]);

    }
    
    
    /**
     * @param $id存订单
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
   
    public function createOrder($id,$num){
        //获取充值人信息
        $user = Db::name("user")->where("id=" . $id)->find();
        //获取充值类型信息
        $charge = Db::name("super_num")->where("id=" . $num)->find();
        //获取充值价格
        $price = $charge['coin'];
        //获取订单号
        $orderid = $this->getOrderid($id);
        //保存订单信息
        $data=[
            'order_id'     => $orderid,
            'trade_id'     => $orderid,
            'uid'    => $id,
            'download_download' => $charge['num'],
            'd_gift' => $charge['gift'],
            'download_coin' => $charge['coin'],
            'addtime' => time(),
            'status' => 3,

        ];
        $result = Db::name('charge_log')->insert($data);
        $msg = $orderid."&".$id."&".$price;

        if ($result) {
            return json(['code'=>200,'msg'=>$msg]);
        } else {
            return json(['code'=>0,'msg'=>'删除失败']);
        }
    }  */


	public function mobileconfig($id){
		$url = get_site_url().'/user/install/get_udid?app_id='.$id;
		$app = Db::name('user_posted')->where('id',$id)->find();
		if(empty($app['name'])){
			$app['name'] = '超级签名';
		}
		$PayloadOrganization = get_site_url();
		$PayloadDisplayName = $app['name'];
		$guid = $this->guid();
		$xml = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
    <dict>
        <key>PayloadContent</key>
        <dict>
            <key>URL</key>
            <string>{$url}</string>
            <key>DeviceAttributes</key>
            <array>
                <string>UDID</string>
                <string>DEVICE_NAME</string>
                <string>IMEI</string>
                <string>MODEL</string>
                <string>VERSION</string>
                <string>SERIAL</string>
                <string>PRODUCT</string>
            </array>
        </dict>
        <key>PayloadOrganization</key>
        <string>授权安装app，点击进入下一步</string>
        <key>PayloadDisplayName</key>
        <string>{$PayloadDisplayName}--【点击安装】</string>
        <key>PayloadDescription</key>
        <string>授权安装app,点击进入下一步</string>
        <key>PayloadVersion</key>
        <integer>1</integer>
        <key>PayloadUUID</key>
        <string>{$guid}</string>
        <key>PayloadIdentifier</key>
        <string>dev.aiqu.profile-service</string>
        <key>PayloadType</key>
        <string>Profile Service</string>
    </dict>
</plist>
EOF;
        if (file_exists(APP_ROOT . '/ios_describe_aoi/'.$id.'.mobileconfig')) {
            @unlink(APP_ROOT . '/ios_describe_aoi/'.$id.'.mobileconfig');
        }
        file_put_contents(APP_ROOT . '/ios_describe_aoi/'.$id.'.mobileconfig', $xml);
        $absolute_path = config('absolute_path');
        $filepath      = $absolute_path . 'public/ios_describe/';
        $filepathaoi   = $absolute_path . 'public/ios_describe_aoi/';
        $filepatha     = $absolute_path . 'public/sign/';
		$shell = 'openssl smime -sign -in ' . $filepathaoi.$id.'.mobileconfig -out '.$filepath.$id.'.mobileconfig -signer '.$filepatha.'mbaike.crt -inkey '.$filepatha.'mbaikenopass.key -certfile '.$filepatha.'ca-bundle.pem -outform der -nodetach 2>&1';
        exec($shell, $out, $status);
		file_put_contents('./sign_error_log/mobile_config_'.$id.time().'_shell.txt',$shell);
		file_put_contents('./sign_error_log/mobile_config_'.$id.time().'.txt',$out);
	}
	
	private function guid(){
		if(function_exists('com_create_guid')){
			return com_create_guid();
		}else{
			mt_srand((double)microtime() * 10000);
			$charid = strtoupper(md5(uniqid(rand(), true)));
			$hyphen = chr(45);
			$guid = substr($charid, 0, 8) . $hyphen
			. substr($charid, 8, 4) . $hyphen
			. substr($charid, 12, 4) . $hyphen
			. substr($charid, 16, 4) . $hyphen
			. substr($charid, 20, 12)
			;
			return $guid;
		}
    }
}
