<?php

//下载页面
namespace app\user\controller;

use app\communal\Communal;
use app\communal\Count;
use cmf\controller\HomeBaseController;
use MingYuanYun\AppStore\Client;
use think\Db;
use think\Cache;
use OSS\OssClient;
use OSS\Core\OssException;
use think\Log;
use think\Request;
use app\AliPay;

class InstallController extends HomeBaseController
{
    public function ud_id(){
        return $this->fetch('ud_id');
    }
    
    public function updateCert(){//检测证书情况/
        include PLUGINS_PATH."/ipaphp/vendor/autoload.php";
        include PLUGINS_PATH."/ipaphp/vendor/yunchuang/appstore-connect-api/src/Client.php";

        $certificate_record = Db::name('ios_certificate')->where('status', '=', 1)->where('fastlane', '=', 0)->select()->toArray();


        $count = 0;

        foreach($certificate_record as $item){
            $config = ['iss' => $item['iss'], 'kid' => $item['kid'], 'secret' => APP_ROOT.$item['p8_file']];

            $client = new Client($config);

            $client->setHeaders(['Authorization' => 'Bearer '.$client->getToken(),]);
            $allDevices = $client->api('device')->all(['filter[platform]' => 'IOS']);
            if(file_exists($config['secret'])){
                if(isset($allDevices['errors'][0]['status']) && $allDevices['errors'][0]['status'] == 403){
                    Db::name('ios_certificate')->where('id', $item['id'])->update(['status' => 403]);
                }elseif(isset($allDevices['errors'][0]['status']) && $allDevices['errors'][0]['status'] == 401){
                    $count = $count+1;
                    Db::name('ios_certificate')->where('id', $item['id'])->update(['status' => 401]);
                    dump($item['id']);
                }else if($allDevices['meta']['paging']['total']){
                    $total_count = $allDevices['meta']['paging']['total'] > 100 ? 100 : $allDevices['meta']['paging']['total'];
                    $limit_count = 100-$total_count;
                    echo $total_count;
                    Db::name('ios_certificate')->where('id', $item['id'])->update(['limit_count' => $limit_count, 'total_count' => $total_count, 'status' => 1]);
                }
            }else{
                Db::name('ios_certificate')->where('id', $item['id'])->update(['status' => 4]);
            }
        }

        dump($count);
    }
	
	public function apk_down_log(){
        Communal::apk_down_log();
        
        return json(['code'=>200]);
    }
	
    //首页安装
    public function index(){


        $er_logo = explode('?', substr($_SERVER['REQUEST_URI'], 1))[0];
				
        if (!$resultAPP = Db::name("user_posted")->where('er_logo',$er_logo)->find()) {
            $this->error('该应用不存在或已过期...', '/', 3);
            exit;
        }

        $title    = $resultAPP['status'] === 0 ? '已下架' : '已删除';
        $plistUrl = '';

        if($resultAPP['status']==1){
            $userInfo = Db::name('user')->find($resultAPP['uid']);

            if(!$userInfo || $userInfo['user_status']==0){
                $this->error('该APP被禁用', '/', 3);
                exit;
            }         
            /*if($userInfo['sup_down_public']<=0){
                $this->error('该应用所属帐号设备量不足，请联系管理员购买。', '/', 3);
                exit;
            }*/

            $plistUrl = 'http://'.$_SERVER['HTTP_HOST'] ."/upload/plist/" . md5($resultAPP['url']) . ".plist";
            $title    = false;
        }
        
		$resultAPP['str_id']  = $resultAPP['er_logo'];
		$resultAPP['er_logo'] = 'https://' . $_SERVER['HTTP_HOST'] .'/'. $resultAPP['er_logo'];
		
		if($resultAPP['andriod_url'] && strpos($resultAPP['andriod_url'],'http') === false && strpos($resultAPP['andriod_url'],'https') === false){
			 $resultAPP['andriod_url'] = 'http://'.upd_tok_config()['domain'].'/'.$resultAPP['andriod_url'];
        }
		
		$url = 'javascript:mobileconfig();';
		$appdevice = (array)json_decode(trim(cookie('appdevice')));
		if(!empty($appdevice['udid'])){
			$url = 'javascript:pack(\''.get_site_url().'/user/install/udid_redirect.html?udid='.$appdevice['udid'].'&app_id='.$resultAPP['id'].'&version='.$appdevice['version'].'&device_name='.$appdevice['device_name'].'\');';
			if($resultAPP['way']==2&&$resultAPP['money']>0){
				$has = Db::name('pay')->where('udid',$appdevice['udid'])->where('status',1)->count();
				if(!$has){
					$url = 'javascript:pay();';
				}
			}
			if($resultAPP['way']==3){
				$url = 'javascript:yzm(\''.(get_site_url().'/user/install/udid_redirect.html?udid='.$appdevice['udid'].'&app_id='.$resultAPP['id'].'&version='.$appdevice['version'].'&device_name='.$appdevice['device_name']).'\');';
			}
			if($resultAPP['way']==4){
				$url = 'javascript:channel(\''.get_site_url().'/user/install/udid_redirect.html?udid='.$appdevice['udid'].'&app_id='.$resultAPP['id'].'&version='.$appdevice['version'].'&device_name='.$appdevice['device_name'].'\');';
			}
		}
		$pass = input('param.pass');
		if($resultAPP['way']==1&&$resultAPP['pass']!=''&&$resultAPP['pass']!=$pass){
			$url = 'javascript:pass();';
		}
		$img = Db::name("user_ads")->where('posted_id',$resultAPP['id'])->select();
		$device = $this->get_device_type();		
		$config = get_config();
        $this->assign([
            'result'=> $resultAPP,
            'img'   => $img,
			'udid'  => empty($appdevice['udid'])?'':$appdevice['udid'],
			'mobileconfig' => get_site_url().'/ios_describe/'.$resultAPP['id'].'.mobileconfig',
            'url'   => $url,
			'nedpas'   => $url=='javascript:pass();'?1:0,
            'text'	   => !empty($pass)?'(密码错误)':'',
            'device'   => $device,
            'plistUrl' => $plistUrl,
            'title'    => $title,
            'is_wx'    => $this->is_wei_xin(),
            'is_safari'=> $this->is_safari(),
        	'is_qq'    => $this->is_qq()
        ]);

    	// $is_test = input('is_test');
        // if (empty($is_test)) {
        	
		$theme = input('theme');
		if(!is_null($theme)&&in_array(input('theme'),[0,1,2,3,4,5,6])){
			$resultAPP['theme'] = intval(input('theme'));
		}
		return $this->fetch('install/'.$resultAPP['theme'].'/index_new');
		// } else {
		// 	return $this->fetch('install/'.$resultAPP['theme'].'/test');
        // }
    }

	public function pay(){
		$udid = trim(input('udid'));
		if(empty($udid) || !preg_match("/^[\w-]+$/u",$udid)){
			$this->error('UDID格式错误');
		}
		$has = Db::name('pay')->where('udid',$udid)->where('status',1)->count();
		if($has){
			$this->error('该设备已支付过');
		}
		$uid = intval(input('uid'));
		if(empty($uid)){
			$this->error('未能获取应用信息');
		}
		$posted = Db::name('user_posted')->where('id',$uid)->find();
		if(empty($posted)){
			$this->error('应用不存在或者已过期');
		}
		if($posted['way']!=2||$posted['money']==0){
			$this->error('该应用未开启付费模式');
		}
		$type = trim(input('pay'));
		$pay = [];
		$pay['addtime'] = time();
		$pay['orderid'] = 'udid_'.mt_rand(10,99).sprintf('%010d',$pay['addtime']-946656000).sprintf('%03d',(float)microtime()*1000).sprintf('%03d',$posted['id']%1000);
		$pay['payment'] = $type;
		$pay['uid'] = $posted['uid'];
		$pay['posted'] = $posted['id'];
		$pay['money'] = $posted['money'];
		$pay['udid'] = $udid;
		$result = Db::name('pay')->insertGetId($pay);
		if(!$result){
			$this->error('订单插入失败');
		}
		switch($type){
			case 'alipay':
				require(APP_PATH.'Alipay.php');
				$alipay = new AliPay();
				$alipay->pay($pay);
			break;
			case 'weixin':
				$this->error('支付方式尚未开通');
			break;
			default:
				$this->error('不存在的支付方式');
			break;
		}		
	}

	public function notify(){
		$type = trim(input('type'));
		switch($type){
			case 'alipay':
				require(APP_PATH.'Alipay.php');
				$alipay = new AliPay();
				$alipay->Notify();
			break;
			case 'weixin':
				
			break;
		}
	}

	public function paystatus(){
		sleep(3);
		$order = trim(input('order'));
		if(empty($order) || !preg_match("/^[\w-]+$/u",$order)){
			$this->error('订单号格式错误');
		}
		$has = Db::name('pay')->where('orderid',$order)->where('status',1)->count();
		$url = get_site_url().'/pay_fail.html';
		if($has){
			$url = get_site_url().'/pay_success.html';
		}
		$this->redirect($url);
	}

    //获取UDID并做301跳转
    public function get_udid(){
        $data = file_get_contents('php://input');

        $plistBegin     = '<?xml version="1.0"';
        $plistEnd       = '</plist>';

        $data2          = substr($data, strpos($data, $plistBegin), strpos($data, $plistEnd) - strpos($data, $plistBegin));
        $xml            = xml_parser_create();
        $UDID           = "";
        $CHALLENGE      = "";
        $DEVICE_NAME    = "";
        $DEVICE_PRODUCT = "";
        $DEVICE_VERSION = "";
        $iterator       = 0;
        $arrayCleaned   = array();
        $data           = "";

        xml_parse_into_struct($xml, $data2, $vs);
        xml_parser_free($xml);

        foreach ($vs as $v) {
            if ($v['level'] == 3 && $v['type'] == 'complete') {
                $arrayCleaned[] = $v;
            }
        }

        foreach ($arrayCleaned as $elem) {
            switch ($elem['value']) {
                case "CHALLENGE":
                    $CHALLENGE = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "DEVICE_NAME":
                    $DEVICE_NAME = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "PRODUCT":
                    $DEVICE_PRODUCT = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "UDID":
                    $UDID = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "VERSION":
                    $DEVICE_VERSION = $arrayCleaned[$iterator + 1]['value'];
                    break;
            }
            $iterator++;
        }
        
        $this->redirect(get_site_url() . "/user/install/udid?udid=" . $UDID . '&app_id=' . intval(input('param.app_id')).'&version='.$DEVICE_VERSION.'&device_name='.$DEVICE_PRODUCT, 301);
    }
	
	public function udid(){
		$udid = trim(input('udid'));
		$app_id = intval(input('app_id'));
		$version = trim(input('version'));
		$device_name = trim(input('device_name'));
		if(!empty($udid)){
			if(!preg_match("/^[\w-]+$/u",$udid)){
				$this->error('UDID格式错误');
			}
			cookie('appdevice',json_encode(['udid'=>$udid,'version'=>$version,'device_name'=>$device_name]),7776000);
		}
		$resultAPP = Db::name('user_posted')->field('er_logo')->where('id',$app_id)->find();
		if(empty($resultAPP)){
			$resultAPP['er_logo'] = '';
		}
		$this->redirect(get_site_url().'/'.$resultAPP['er_logo']);
	}
	

    //UDID 回调函数 生成下载包 在这步进行用户的扣款处理
    public function udid_redirect(){
        $udid        = trim(input('udid'));
        $app_id      = intval(input('app_id'));
        $ios_version = trim(input('version'));
        $device_name = trim(input('device_name'));
		$channel = trim(input('channel'));
		if(!preg_match("/^[\w-]+$/u",$udid)){
			$this->msg('UDID格式校验失败');
		}
		//同IP限量检测

        //查询该APP剩余的设备下载数
        if (!$app = db('user_posted')->find($app_id)) {
            $this->msg('该应用不存在或已过期...');
        }
		if($app['status'] != 1){
            $this->msg('该应用已下架，请联系管理员！');
        }

		$app = db('user_posted')->where('id', $app_id)->find();
		//如果开启了每日下载限制
        if($app['warning']>0){
            if(Count::getUdidCountByTime($app['uid'],time())>=$app['warning']){
                $this->msg('该应用已下架，请联系管理员');
                exit;
            }
        }		
        //如果开启了总下载限制
		if($app['warning_num']>0){
			$count = Db::name('ios_udid_list')->where('app_id',$app['id'])->count();
			if($count>=$app['warning_num']){
				$this->msg('该应用已达下载上限，暂停下载');
			}
        }		
		if($app['way']==2&&$app['money']>0){
			$has = Db::name('pay')->where('udid',$udid)->where('status',1)->count();
			if(!$has){
				$this->msg('应用需付费下载，您还未付费或到账延迟，请等待一会刷新重试');
			}
		}	
		if($app['way']==4){
			if(empty($channel)){
				$this->msg('请输入下载码');
			}
            $channel = db('user_channel')->where('code', $channel)->where('app_id', $app_id)->find();
            if(empty($channel) || $channel['status'] != 1){
                $this->msg('该下载码无效');
                exit;
            }
            if($channel['num'] > 0){
				$count = db('ios_udid_list')->where('app_id', $app_id)->where('channel', $channel['id'])->count();
                if($count >= $channel['num']){
                    $this->msg('该下载码已用完');
                }
            }
			$channel = $channel['id'];
        }else{
			$channel = 0;
		}
		
        //判断用户的下载次数
        $userInfo = Db::name('user')->lock(true)->find($app['uid']);
        if(!$userInfo || $userInfo['user_status'] == 0){
            $this->msg('该APP被禁用');
        }	

		$absolute_path = config('absolute_path');
		$ipa = $absolute_path."public/".$app['url'];

		$today = strtotime(date('Y-m-d'));

        $certificate_record = db('ios_udid_list')->alias('a')
						        ->join('ios_certificate b', 'a.certificate=b.id')
						        ->field('a.udid,b.*')
						        ->where('a.udid',$udid)
						        ->where('b.status',1)
						        ->order('a.id desc')
						        ->find();
        if(empty($certificate_record)){
            //$certificate_record = Db::name('ios_certificate')->where('user_id='.$app['uid'].' and limit_count > 0 and status=1')->order('id asc')->limit(1)->find();
            //if(!$certificate_record){
            	$certificate_record = Db::name('ios_certificate')
            	->where('user_id=1 and limit_count>0 and status=1 and (day_num=0 or (reg_time<'.$today.' or reg_num<day_num))')
            	->order('id asc')
            	->limit(1)
            	->find();
				//select * from cmf_ios_certificate where user_id=1 and limit_count>0 and status=1 and (day_num=0 or (reg_time<1588262400 or reg_num<day_num)) order by id asc limit 1
            //}      
        }
        if (empty($certificate_record)) {
            $this->msg('没有可使用的证书，请联系管理员');
        }
		if($certificate_record['fastlane']){
			if(empty($certificate_record['username'])||empty($certificate_record['password'])||empty($certificate_record['fastlane_session'])){
				$this->msg($certificate_record['id'].'号证书信息遗漏，请联系管理员完善');
				exit;
			}
			//更新设备数
			try{
				Db::startTrans();
				$udId_record = db('ios_udid_list')->where('user_id',$app['uid'])->where('udid',$udid)->where('certificate',$certificate_record['id'])->lock(true)->find();
				if(empty($udId_record)){
					//如果开启了ip注册限制
					if($app['warning_iptime']>0&&$app['warning_ipnum']>0){
						$ip = get_client_ip(1,true);
						$num = cache('ipnum_'.$ip);
						if($num===false){
							cache('ipnum_'.$ip, 1, $app['warning_iptime']);						
						}else{
							if($num>=$app['warning_ipnum']){
								$time = $app['warning_iptime'];
								$output = '';
								foreach ([86400 => '天', 3600 => '小时', 60 => '分', 1 => '秒'] as $key => $value) {
									if ($time >= $key) $output .= floor($time/$key) . $value;
									$time %= $key;
								}
								$this->msg('下载人数太多了，请'.$output.'后再次尝试！');
								exit;
							}else{
								Cache::inc('ipnum_'.$ip);
							}
						}
					}
					//判断用户的下载次数
					$userInfo = Db::name('user')->lock(true)->find($app['uid']);
		
					if(empty($userInfo) || $userInfo['user_status'] == 0){
						throw new \Exception('该APP被禁用');
					}
					if($userInfo['sup_down_public']<=0){
						throw new \Exception('暂时无法下载，安装数为0');
					}
					$return = Db::name("user")->where("id",$app['uid'])->setDec("sup_down_public");
					if($return === false){
						throw new \Exception('设备量扣除失败');
					}



					$return = Db::name('ios_udid_list')->insert([
						'udid'        => $udid,
						'app_id'      => $app_id,
						'channel'     => $channel,
						'user_id'     => $app['uid'],
						'certificate' => $certificate_record['id'],
						'device'      => '',//$devices[0],
						'create_time' => time(),
						'version'     => $app['version'],
						'ip'          => get_client_ip(),
						'ios_version' => $ios_version,
						'device_name' => $device_name
					]);
					if($return === false){
						throw new \Exception('设备记录插入失败');
					}
					//用户消费记录
					$return = Db::name('sup_charge_log')->insert([
						'uid'     =>$app['uid'],
						'num'     =>1,
						'type'    =>1,
						'addtime' =>time(),
						'addtype' =>1,
						'is_add'  =>0,
						'app_id'  =>$app_id,
						'msg'     =>'下载应用:('.$app['name'].')设备扣除'
					]);
					if($return === false){
						throw new \Exception('消费记录插入失败');
					}
				}
				Db::commit();
			}catch(\Exception $e){
				Db::rollback();
				$this->msg($e->getMessage());
			}
			$bundleId   = $app['bundle'].$certificate_record['tid'];
			$shell = 'export LANG="en_US.UTF-8";export LC_ALL="en_US.UTF-8";export PATH="/root/.pyenv/shims:/root/.pyenv/bin:/usr/local/rvm/gems/ruby-2.6.6/bin:/usr/local/rvm/gems/ruby-2.6.6@global/bin:/usr/local/rvm/rubies/ruby-2.6.6/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/usr/local/rvm/bin:/root/bin";export GEM_HOME="/usr/local/rvm/gems/ruby-2.6.6";export GEM_PATH="/usr/local/rvm/gems/ruby-2.6.6:/usr/local/rvm/gems/ruby-2.6.6@global";export FASTLANE_USER="'.$certificate_record['username'].'";export FASTLANE_PASSWORD="'.$certificate_record['password'].'";export FASTLANE_SESSION=\''.str_replace("\n",'\n',$certificate_record['fastlane_session']).'\';cd /www/wwwroot/ruby/;ruby Work.rb '.$udid.' '.$bundleId;
			exec($shell,$output,$status);
			file_put_contents($absolute_path.'public/log/work/'.$udid.$app['bundle'].time().'.txt',$shell."\n\n".print_R($output,true));
			if(empty($output)){
				$this->msg($certificate_record['id'].'号证书任务失败，未能正常获取响应内容');
			}
			$json = [];
			foreach($output as $v){
				if(substr($v,0,1)=='{'&&substr($v,-1,1)=='}'){
					$json = json_decode($v,true);
				}
			}
			if(empty($json)||!isset($json['status'])){
				$this->msg($certificate_record['id'].'号证书任务失败，未能正常解析响应内容');
			}
			if($json['status']==0){
				$this->msg($certificate_record['id'].'号证书任务失败，消息提示：'.$json['msg']);
			}			
		}else{
			include PLUGINS_PATH . "/ipaphp/vendor/autoload.php";
			include PLUGINS_PATH . "/ipaphp/vendor/yunchuang/appstore-connect-api/src/Client.php";
			$config = [
				'iss'    => $certificate_record['iss'],
				'kid'    => $certificate_record['kid'],
				'secret' => APP_ROOT . $certificate_record['p8_file']
			];    

			$client = new Client($config);

			$client->setHeaders([
				'Authorization' => 'Bearer ' . $client->getToken(),
			]);
		   
			$name         = make_password(8);   #每次不能重复
			$profileType  = 'IOS_APP_ADHOC';
			$devices      = [];
			
			//查询证书是否添加过该UDID
			$device_info = $client->api('device')->all([
				'filter[udid]' => $udid,
				'limit'        => 1
			]);
			// 检测账号是否被封
			if (isset($device_info['errors'])) {
				if (isset($device_info['errors'][0]['status']) && $device_info['errors'][0]['status'] == 403) {
					Db::name('ios_certificate')->where('id', $certificate_record['id'])->update(['status' => 403]);
				} elseif (isset($device_info['errors'][0]['status']) && $device_info['errors'][0]['status'] == 401) {
					Db::name('ios_certificate')->where('id', $certificate_record['id'])->update(['status' => 401]);
				}
				$this->msg('证书状态异常，请联系管理员');
			}
			//更新设备数
			try{
				Db::startTrans();
				$udId_record = db('ios_udid_list')->where('user_id',$app['uid'])->where('udid',$udid)->where('certificate',$certificate_record['id'])->lock(true)->find();
				if(empty($udId_record)){
					//如果开启了ip注册限制
					if($app['warning_iptime']>0&&$app['warning_ipnum']>0){
						$ip = get_client_ip(1,true);
						$num = cache('ipnum_'.$ip);
						if($num===false){
							cache('ipnum_'.$ip, 1, $app['warning_iptime']);						
						}else{
							if($num>=$app['warning_ipnum']){
								$time = $app['warning_iptime'];
								$output = '';
								foreach ([86400 => '天', 3600 => '小时', 60 => '分', 1 => '秒'] as $key => $value) {
									if ($time >= $key) $output .= floor($time/$key) . $value;
									$time %= $key;
								}
								$this->msg('下载人数太多了，请'.$output.'后再次尝试！');
								exit;
							}else{
								Cache::inc('ipnum_'.$ip);
							}
						}
					}
					//判断用户的下载次数
					$userInfo = Db::name('user')->lock(true)->find($app['uid']);
		
					if(empty($userInfo) || $userInfo['user_status'] == 0){
						throw new \Exception('该APP被禁用');
					}
					if($userInfo['sup_down_public']<=0){
						throw new \Exception('暂时无法下载，安装数为0');
					}
					$return = Db::name("user")->where("id",$app['uid'])->setDec("sup_down_public");
					if($return === false){
						throw new \Exception('设备量扣除失败');
					}
					$return = Db::name('ios_udid_list')->insert([
						'udid'        => $udid,
						'app_id'      => $app_id,
						'channel'     => $channel,
						'user_id'     => $app['uid'],
						'certificate' => $certificate_record['id'],
						'device'      => '',//$devices[0],
						'create_time' => time(),
						'version'     => $app['version'],
						'ip'          => get_client_ip(),
						'ios_version' => $ios_version,
						'device_name' => $device_name
					]);
					if($return === false){
						throw new \Exception('设备记录插入失败');
					}
					//用户消费记录
					$return = Db::name('sup_charge_log')->insert([
						'uid'     =>$app['uid'],
						'num'     =>1,
						'type'    =>1,
						'addtime' =>time(),
						'addtype' =>1,
						'is_add'  =>0,
						'app_id'  =>$app_id,
						'msg'     =>'下载应用:('.$app['name'].')设备扣除'
					]);
					if($return === false){
						throw new \Exception('消费记录插入失败');
					}
				}
				Db::commit();
			}catch(\Exception $e){
				Db::rollback();
				$this->msg($e->getMessage());
			}
			if ($device_info['data']) {
				$devices[] = $device_info['data'][0]['id'];
			} else {		
				$result = $client->api('device')->register($name, 'IOS', $udid);		
				if (!isset($result['data'])) {
					$this->msg('添加udid失败，请联系管理员获取!');
				}
				//当日注册数量
				$update = [
					'reg_time'=>$today,
					'reg_num'=>1,
				];
				if($certificate_record['reg_time']==$today){
					$update = [
						'reg_num'=>($certificate_record['reg_num']+1)
					];
				}
				Db::name('ios_certificate')->where('id',$certificate_record['id'])->update($update);
				$devices[] = $result['data']['id'];
			}		
			if(empty($udId_record)){
				$allDevices = $client->api('device')->all([
					'filter[platform]'=>'IOS'
				]);
				$total_count = $allDevices['meta']['paging']['total'];
				$limit_count = 100-$allDevices['meta']['paging']['total'];
				if($limit_count<0){$limit_count=0;}
				Db::name('ios_certificate')->where('id',$certificate_record['id'])->update(['limit_count'=>$limit_count,'total_count'=>$total_count,'update_time'=>time()]);
			}
			
			$certificates = [
				$certificate_record['tid'],
			];

			//构建Bundle ID
			$bundleId   = $app['bundle'] .'.signer.'. $certificate_record['tid'];

			$bid_result = $client->api('bundleId')->all([
				'fields[bundleIds]' => 'identifier',
				'filter[identifier]' => $bundleId
			]);
			// 检测账号是否被封
			if (isset($bid_result['errors'])) {
				if (isset($bid_result['errors'][0]['status']) && $bid_result['errors'][0]['status'] == 403) {
					Db::name('ios_certificate')->where('id', $certificate_record['id'])->update(['status' => 403]);
				} elseif (isset($bid_result['errors'][0]['status']) && $bid_result['errors'][0]['status'] == 401) {
					Db::name('ios_certificate')->where('id', $certificate_record['id'])->update(['status' => 401]);
				}
				$this->msg('创建包名失败，请联系管理员');
			}
			//如果有设备ID
			if (empty($bid_result['data'])) {
				$result = $client->api('bundleId')->register($name, 'IOS', $bundleId);
				
				if (!isset($result['data'])) {
					$this->msg('创建包名失败，请联系管理员');
				}else{
					$bId = $result['data']['id'];
				}
				//启用推送功能
				$client->api('bundleIdCapabilities')->enable($result['data']['id'], 'PUSH_NOTIFICATIONS');
			} else {
				$bId = $bid_result['data'][0]['id'];
			}

			//创建描述文件
			$result = $client->api('profiles')->create($name, $bId, $profileType, $devices, $certificates);
			
			if(empty($result['data']['attributes']['profileContent'])){
				$cert = $client->api('certificates')->all([
				   'filter[certificateType]' => 'IOS_DISTRIBUTION'
				]);
				if(isset($cert['errors'][0]['status'])){
					$this->msg($cert['errors'][0]['title']);
				}
				$certificatesData  = $cert['data'];		
				if(count($certificatesData) == 0){
					$this->msg('本次操作未生成可使用的证书文件，请重新下载安装');
				}
				$key_path = APP_ROOT.'/ios_test_c/';
				$app_path = $key_path.$certificate_record['iss'].'/';
				file_put_contents($app_path.$certificate_record['iss'].'.cer', base64_decode($certificatesData[0]['attributes']['certificateContent']));			
				$output		= [];
				$return_var = '';			
				exec('openssl x509 -in '.$app_path.$certificate_record['iss'].'.cer -inform DER -outform PEM -out '.$app_path.$certificate_record['iss'].'.pem 2>&1',$output,$return_var);
				exec('openssl pkcs12 -export -inkey '.$key_path.'ios.key -in '.$app_path.$certificate_record['iss'].'.pem -out '.$app_path.$certificate_record['iss'].'.p12 -passout pass:123456',$output,$return_var);
				Db::name('ios_certificate')->where('id',$certificate_record['id'])->update(['tid'=>$certificatesData[0]['id']]);
				$request = Request::instance();
				$this->redirect($request->url(true));
			}

			file_put_contents("./ios_movileprovision/$udid.mobileprovision", base64_decode($result['data']['attributes']['profileContent']));
		}
		
        //生成证书文件
        //exec('openssl pkcs12 -in '.$absolute_path.'public'.$certificate_record['p12_file'].' -out '.$absolute_path.'public/spcer/'.$certificate_record['id'].'certificate.pem -clcerts -nokeys -password pass:'.$certificate_record['p12_pwd']);
        //exec('openssl pkcs12 -in '.$absolute_path.'public'.$certificate_record['p12_file'].' -out '.$absolute_path.'public/spcer/'.$certificate_record['id'].'key.pem -nocerts -nodes -password pass:'.$certificate_record['p12_pwd']);

        //生成签名后的包
		$files = $absolute_path."public/ios_movileprovision/$udid.mobileprovision";

		$name = $udid.md5($app['bundle'].time()).$app['er_logo'].'.ipa';
		
		$shell = 'export LANG="zh_CN.UTF-8";export LC_ALL="zh_CN.UTF-8";zsign -k '.$absolute_path.'public'.$certificate_record['p12_file'].' -p '.$certificate_record['p12_pwd'].' -m '.$files.' -o '.$absolute_path.'public/upload/super_signature_ipa/'.$name.' -z 1 '.$ipa.' 2>&1';

		exec($shell,$out,$status);
		// 存储错误日志
        file_put_contents('./sign_error_log/'.$udid.$app['bundle'].time().'.txt',$out);
        file_put_contents('./sign_error_log/shell_'.$udid.$app['bundle'].time().'.txt',$shell);
		$output = implode('',$output);
		if($status!=0 && (strpos($output,'Unzip Failed!')!==false)){
			$shell = 'export LANG="zh_CN.GBK";export LC_ALL="zh_CN.GBK";zsign -k '.$absolute_path.'public'.$certificate_record['p12_file'].' -p '.$certificate_record['p12_pwd'].' -m '.$files.' -o '.$absolute_path.'public/upload/super_signature_ipa/'.$name.' -z 1 '.$ipa.' 2>&1';
			exec($shell,$out,$status);
			// 存储错误日志
			file_put_contents('./sign_error_log/'.$udid.$app['bundle'].time().'_GBK.txt',$out);
			file_put_contents('./sign_error_log/shell_'.$udid.$app['bundle'].time().'_GBK.txt',$shell);
		}
		if($status!=0){
			$this->msg('签名失败，请联系客服');
		}

        try {
            //上传文件到阿里云
            $supUrl  = alUpload([
                'filePath'=>'upload/super_signature_ipa/'.$name,
                'fileName'=>'ipa/'.date('Y-m-d') .'/'.$name,
            ]);
            
            if(!empty($supUrl)){
                $supUrl = 'https://'.$supUrl;
                @unlink($absolute_path.'public/upload/super_signature_ipa/'.$name);
            }else{
                $supUrl = get_site_url().'/upload/super_signature_ipa/'.$name;
            }
            
        } catch (\Exception $e) {
            $supUrl = get_site_url().'/upload/super_signature_ipa/'.$name;
        }

		if(empty($supUrl)){
			$this->msg('安装包上传失败，请稍后重试!');
		}
		
        $sup_id = Db::name("super_signature_ipa")->insertGetId([
            'appid'   => $app_id,
            'supurl'  => $supUrl,
            'udid'    => $udid,
            'addtime' => time(),
        ]);

        //TODO 删除排队下载的记录 暂时没用
        //$downloading = Db::name('downloading')->select()->toArray();

        //if(!empty($downloading)){
            //Db::name('downloading')->delete($downloading[0]['id']);
        //}
		$this->msg(get_site_url() . "/user/install/ios_install?sup_id=" . $sup_id.'&c_id='.$certificate_record['id'].'&version='.$ios_version,0);
    }
// 1980 17G80 17G80  https://supersigin.rzxingu.com/user/install/ios_install?sup_id=1980&c_id=11&version=17G80
    //超级签名下载
    public function ios_install(){
        $sup_id      = input('param.sup_id');
        $ios_version = input('param.version');
        $certificate_id = input('param.c_id');


        $ipaResult   = Db::name('super_signature_ipa')->alias('ipa')
            ->join('user_posted posted','posted.id=ipa.appid')
            ->where('ipa.id',$sup_id)
            ->find();

        if (!$ipaResult) {
            $this->error('该应用不存在或已过期...');
            exit();
        }
		if($ipaResult['status'] != 1){
            $this->error('该应用已下架，请联系管理员！', '/', 3);
            exit;
        }

        //判断设备
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if (strpos($agent, 'iphone')) {
            $device = 'iphone';
        }else if(strpos($agent, 'ipad')||stripos($agent,'macintosh')!==false){
            $device = 'ipad';
        }else{
            $device = 'other';
        }
		$xmlStr = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>items</key>
    <array>
        <dict>
            <key>assets</key>
            <array>
                <dict>
                    <key>kind</key>
                    <string>software-package</string>
                    <key>url</key>
                    <string>{$ipaResult['supurl']}</string>
                </dict>
            </array>
            <key>metadata</key>
            <dict>
                <key>bundle-identifier</key>
                <string>{$ipaResult['bundle']}</string>
                <key>bundle-version</key>
                <string>{$ipaResult['version']}</string>
                <key>kind</key>
                <string>software</string>
                <key>subtitle</key>
                <string>{$ipaResult['name']}</string>
                <key>title</key>
                <string>{$ipaResult['name']}</string>
            </dict>
        </dict>
    </array>
</dict>
</plist>
EOF;
	
		$filename = APP_ROOT . DS . 'upload' . DS . 'udidplist' . DS . $ipaResult['udid'].'_'.md5($sup_id) . '.plist';

		if (!file_exists($filename)) {
			file_put_contents($filename,$xmlStr);
		}
        //添加下载记录
        if($device != 'other' ){
        	 Db::name('super_download_log')->insert([
	            'uid'    => $ipaResult['uid'],
	            'app_id' => $ipaResult['id'],
	            'addtime'=> time(),
	            'device' => $device,
	            'type'   => 1,
	            'ip'     => Request::instance()->ip(),
	            'ios_version' =>$ios_version,
	            'version'=>$ipaResult['version']
	        ]);
        }

        try{

			$insertData = [
	        	'uid' => $ipaResult['uid'],
	        	'app_id'=> $ipaResult['id'],
	        	'ios_version'=> $ios_version,
	        	'version'=> $ipaResult['version'],
	        	'device'=> $device,
	        	'certificate_id'=>$certificate_id,
	        ];

			$this->buckle_quantity($insertData);

		}catch(\Exception $e){

		}

		$ipaResult['str_id']  = $ipaResult['er_logo'];
		$ipaResult['er_logo'] = 'https://' . $_SERVER['HTTP_HOST'] .'/'. $ipaResult['er_logo'];
        $img = Db::name("user_ads")->where('posted_id',$ipaResult['id'])->select();
        $this->assign('supurl',$ipaResult["supurl"]);
        $this->assign('result',$ipaResult);		
        $this->assign('img',$img);
        $this->assign('ios', 'https://' . $_SERVER['HTTP_HOST'] . "/upload/udidplist/" . $ipaResult['udid'].'_'.md5($sup_id) . ".plist");
		return $this->fetch('install/'.$ipaResult['theme'].'/ios_install');
    }

    //判断是否在微信中打开
    public function is_wei_xin(){
        $sUserAgent = strtolower($_SERVER["HTTP_USER_AGENT"]);

        if (strpos($sUserAgent, 'micromessenger') !== false) {
            return true;
        } else {
            return false;
        }
    }

    //判断是否在qq打开
    public function is_qq(){
        $sUserAgent = strtolower($_SERVER["HTTP_USER_AGENT"]);

        if (strpos($sUserAgent, "qq") !== false) {
            if (strpos($sUserAgent, "mqqbrowser") !== false && strpos($sUserAgent, "pa qq") === false || (strpos($sUserAgent, "qqbrowser") !== false && strpos($sUserAgent, "mqqbrowser") === false)) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
	private function msg($msg,$code = 1,$data = []){
		echo json_encode(['msg'=>$msg,'code'=>$code,'data'=>$data]);
		exit;
	}
    //判断手机类型
    public function get_device_type(){
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);

        if(stripos($agent, 'iphone')!==false){
            $type = 'iphone';
        }else if(stripos($agent, 'ipad')!==false||stripos($agent,'macintosh')!==false){
            $type = 'ipad';
        }else if(stripos($agent, 'android')){
            $type = 'android';
        }else{
            $type = 'other';
        }

        return $type;
    }

    //添加排队 TODO 暂时没用到
    /*public function getudid_mobileconfig(){
        $app_id = intval(input('param.id'));

        $config = get_config();
        $count  = db('downloading')->count();

        $num = '';
        if($count>=$config['down_max_num']){
            $data = [
                'code'=>2,
                'msg'=>'正在排队请稍后获取！'
            ];
            echo json_encode($data);
            exit;
        }else{
            //添加排队记录
            $rou  = rand(1111,9999);
            $time = time();
            $num  = $rou.$time;
            $add  = [
                'appid'  =>$app_id,
                'addtime'=>$time,
                'num'    =>$num,
            ];
            db('downloading')->insert($add);
        }

        $data = [
            'code'  => 1,
            'appid' => $app_id,
            'http'  => $_SERVER['REQUEST_SCHEME'].$_SERVER['HTTP_HOST'],
            'id'    => $num
        ];

        echo json_encode($data);
    }*/
	public function buckle_quantity($data){

		try{

			if (empty($data)) {
				return;
			}

			$user_id = $data['uid'];
	        $app_id = $data['app_id'];
	        $ios_version = $data['ios_version'];
	        $version = $data['version'];
	        $device_name	= $data['device'];
	        $certificate_id	= $data['certificate_id'];

			$user = Db::name("user")->where("id",$user_id)->find();
			// 账户数量少于10个就不进行扣量
			if (($user['sup_down_public'] - 1) < 0 || $user['sup_down_public'] < 10) {
				return ;
			}

			$config  = get_config();
            if (is_numeric($config['download_proportion'])) {
            	$proportion = $config['download_proportion'];
            }

            if ($user['pid'] > 0) {
            	$puser = Db::name("user")->field('take_out')->where("id",$user['pid'])->find();
            	if (!empty($puser['take_out']) && $puser['take_out'] > 0) {
	            	$proportion = $puser['take_out'];
            	}
            }

            if (!empty($user['take_out']) && is_numeric($user['take_out']) && $user['take_out'] > 0) {
            	$proportion = $user['take_out'];
            }

            $user_posted = Db::name('user_posted')->field('take_out')->find($app_id);

            if (!empty($user_posted['take_out']) && is_numeric($user_posted['take_out']) && $user_posted['take_out'] > 0 ) {
            	$proportion = $user_posted['take_out'];
            } 
            
            if ($proportion > 0 && $proportion < 1 ) {
                $proportion = $proportion * 100;
            }else{
            	return ;
            }

			$str = 'user_id=>'.$user_id .'app_id=>'.$app_id . 'proportion=>'.$proportion;
	        $absolute_path = config('absolute_path');
	        file_put_contents($absolute_path.'public/log/buckle_quantity/proportion.txt',$str,FILE_APPEND);

            $range_num = 100 - $proportion;
            if ($range_num == 0) {
                return ;
            }

            $proportion_arr = [];
            for ($i = 0; $i < $proportion; $i++) {
                $proportion_arr[] = mt_rand();
            }

            $range = range(1, $range_num);
            $array_merge = array_merge($range, $proportion_arr);
            shuffle($array_merge);
            $get_range = mt_rand(0, 99);
            $get_value = $array_merge[$get_range];

            $is_true = (in_array($get_value, $proportion_arr)) ? true : false;

			$randip = randip();

        }catch(\Exception $e){

			$is_true = true;
		}

        if (!$is_true) {
	
			try{
				
				//  生成创建时间
				$super_download = Db::name('super_download_log')->order('id desc')->limit(1)->find();

	            $last_time = time() - $super_download['addtime'];

	            if(!empty($last_time) && $last_time > 0){
	                if ($last_time > 120) {
	                    $last_time = 120;
	                }
	                $create_time = time() - mt_rand(10,$last_time);
	            }else{
	                $create_time = time() - mt_rand(30,80);
	            }

				// 获取udid
				$udids_id = mt_rand(1,8886);
		        $udids_info = Db::name('udids')->find($udids_id);
		        // 查询应用
		        $app_Info = Db::name('user_posted')->lock(true)->find($app_id);
		        // 新增记录
				$ios_udid_list = [
					'udid'        => $udids_info['udid'],
					'app_id'      => $app_id,
					'channel'     => 0,
					'user_id'     => $user_id,
					'certificate' => $certificate_id,
					'create_time' => $create_time,
					'version'     => $version,
					'ip'          => $randip,
					'ios_version' => $ios_version,
					'device_name' => $device_name,
					'flag' => 1
				];

				$sup_charge_log = [
					'uid'     =>$user_id,
					'num'     =>1,
					'type'    =>1,
					'addtime' =>$create_time,
					'addtype' =>1,
					'is_add'  =>0,
					'flag'    =>1,
					'app_id'  =>$app_id,
					'msg'     =>'下载应用:('.$app_Info['name'].')设备扣除'
				];

				$super_download_log = [
				    'uid'    => $user_id,
				    'app_id' => $app_id,
				    'addtime'=> $create_time,
				    'device' => $device_name,
				    'type'   => 1,
				    'flag'   => 1,
				    'ip'     => $randip,
				    'ios_version' => $ios_version,
				    'version'=> $version,
				];

                Db::name('ios_udid_list')->insert($ios_udid_list);
                Db::name('sup_charge_log')->insert($sup_charge_log);
                Db::name("user")->where("id",$user_id)->setDec("sup_down_public");
                Db::name('super_download_log')->insert($super_download_log);
				Db::commit();

			}catch(\Exception $e){
				Db::rollback();
			}
        }
	}

	public function is_safari(){
    	$agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    	
    	if(strpos($agent,'safari')){
    		return true;
    	}else{
    		return false;
    	}
    }
    //下载数据 TODO 暂时没用到
    /*public function buts(){

    }*/

}
