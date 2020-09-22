<?php

namespace app\portal\controller;

use cmf\controller\HomeBaseController;
use think\Db;
use ApkParser;
use app\pay\ali\AliPay;

class IndexController extends HomeBaseController{
    //超级签名
    public function supper_sign(){
        return $this->fetch(':supper_sign');
    }

	public function checkLogin(){
		set_time_limit(150);
		$certificate_record = Db::name('ios_certificate')->where('status', '=', 5)->where('fastlane', '=', 1)->limit(5)->select()->toArray();
		if(!empty($certificate_record)){
			foreach($certificate_record as $k => $v){
				if($k>0){
					sleep(1);
				}
				echo "\n\n";
				echo '时间: '.date('Y-m-d H:i:s')."\n证书检测: ID=>".$v['id']."\n返回值: ";				
				if(!empty($v['username'])&&!empty($v['password'])&&!empty($v['mobile'])){				
					$shell = 'export LANG="en_US.UTF-8";export LC_ALL="en_US.UTF-8";export PATH="/root/.pyenv/shims:/root/.pyenv/bin:/usr/local/rvm/gems/ruby-2.6.6/bin:/usr/local/rvm/gems/ruby-2.6.6@global/bin:/usr/local/rvm/rubies/ruby-2.6.6/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/usr/local/rvm/bin:/root/bin";export GEM_HOME="/usr/local/rvm/gems/ruby-2.6.6";export GEM_PATH="/usr/local/rvm/gems/ruby-2.6.6:/usr/local/rvm/gems/ruby-2.6.6@global";export FASTLANE_USER="'.$v['username'].'";export FASTLANE_PASSWORD="'.$v['password'].'";export FASTLANE_APPLE_APPLICATION_SPECIFIC_PASSWORD="'.$v['specific_pass'].'";export SPACESHIP_2FA_SMS_DEFAULT_PHONE_NUMBER="'.$v['mobile'].'";export FASTLANE_SESSION=\''.str_replace("\n",'\n',$v['fastlane_session']).'\';cd /www/wwwroot/ruby/;ruby checkLogin.rb '.$v['username'];
					exec($shell,$output,$status);
					file_put_contents(config('absolute_path').'public/log/cronLogin/'.$v['username'].time().'.txt',$shell."\n\n".print_R($output,true));
					//print_R($output);
					echo "\n";
					if(empty($output)){
						echo '未能正常获取响应内容';
						continue;
					}
					$json = [];
					foreach($output as $c){
						if(substr($c,0,1)=='{'&&substr($c,-1,1)=='}'){
							$json = json_decode($c,true);
						}
					}
					if(empty($json)||!isset($json['status'])){
						echo '未能正常解析响应内容';
						continue;
					}
					if($json['status']==0){
						echo '消息提示：'.$json['msg'];
						continue;
					}
					$json['session'] = base64_decode($json['session']);
					if(empty($json['session'])){
						echo '未能获取session';
						continue;
					}
					$update = [];
					if(!in_array($v['status'],[0,1])){
						$update['status'] = 1;
					}
					if(!empty($update)){
						db('ios_certificate')->where('id='.$v['id'])->update($update);
					}
					echo '登录正常，session更新成功';
				}else{
					echo '证书信息遗漏，请完善';
				}
			}
		}
	}

    //首页
    public function index(){
        return $this->fetch(':index');
    }

    //服务协议
    public function protocol(){
        return $this->fetch(':protocol');
    }

    public function pay(){
        if (!cmf_is_user_login()) {
            $this->error('请先登录后操作！');
            exit;
        }

        $uid     = session('user.id');

        $user    = Db::name("user")->where("id=$uid ")->find();
        $public  = Db::name('super_num')->where('type',1)->order('orderno')->select();

        $this->assign('public',$public);
        $this->assign('url',Alipay::config('url'));
        $this->assign('user', $user);

        return $this->fetch(':pay');
    }

    //上传IPA包文件
    public function uploadIpa(){
        if (!cmf_is_user_login()) {
            $this->error('请先登录后操作！');
            exit;
        }

        $result   = $this->request->param();
        $saveInfo = $this->request->file('file')->validate([
            'ext'=>'ipa'
        ])->move('../public/upload/super_signature/');

        if(!$saveInfo){
            echo json_encode([
                'code'    => 2,
                'message' => $saveInfo->getError()
            ]);
            exit;
        }

//		TODO  不是AD-HOC包的脱签工具
 //        if($this->request->param('isProvisioned') == 'true'){
 //            $signRoot            = "/www/wwwroot/shanqian.vip/ios_sign_linux/";
 //            $signPath            = $signRoot."ausign";
 //            $mobileProvisionPath = $signRoot."sign.mobileprovision";
 //            $certPath            = $signRoot."sign.p12";
 //            $ipaPath             = 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName();
 //            $saveIpaPath         = 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName();
 //            $certPassword        = '123456';
 //            $loginCmd            = $signPath.' -email 2767302***@qq.com -p 123***';
 //            $signCmd             = $signPath.' -sign '.$ipaPath." -c ".$certPath." -m ".$mobileProvisionPath." -p ".$certPassword." -o ".$saveIpaPath;

 //            exec($loginCmd,$outputString,$loginStatus);
			
 //            if($loginStatus!=0){
 //                echo json_encode(['code' => 2]);
 //                exit;
 //            }else{
 //                exec($signCmd,$outputString,$signStatus);
	
 //                if($signStatus!=0){
 //                    echo json_encode(['code' => 2]);
 //                    exit;
 //                }
 //            }
 //        }
	
        if(isset($result['id']) && $result['id']){
            //更新操作
            $bundle = $result['bundle'];
            $uid    = get_user('id');
			
            if(!$postedOld = Db::name('user_posted')->where('uid',$uid)->where('id',$result['id'])->where('bundle',$bundle)->find()){
                echo json_encode(['code' => 0,'message'=>'bundle未匹配，更新失败']);
                exit;
            }
			
			/*if(Db::name('user_posted')->where('uid',$uid)->where('id',$result['id'])->where('version',$result['version'])->find()){
                echo json_encode(['code' => 0,'message'=>'版本号相同，更新失败']);
                exit;
            }*/
			
            Db::name('user_posted')
                ->where('id',$postedOld['id'])
                ->update([
                    'name'       => $result['name'],
                    'url_name'   => $result['name'],
                    'version'    => $result['version'],
                    'build'      => $result['build'],
                    'img'        => $result['icon'],
                    'type'       => 1,
                    'url'        => 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName(),
                    'big'        => round($saveInfo->getSize() / 1024 / 1024, 2),
                    'addtime'    => time(),
                ]);

            Db::name('user_posted_log')->insert([
                'uid'       =>$uid,
                'posted_id' =>$result['id'],
                'creattime' =>time(),
                'version'   =>$postedOld['version'],
                'big'       =>$postedOld['big']
            ]);
            
            $postedId = $postedOld['id'];
        }else{
            $postedId = Db::name("user_posted")->insertGetId(array(
                'uid'        => session('user.id'),
                'name'       => $result['name'],
                'url_name'   => $result['name'],
                'version'    => $result['version'],
                'build'      => $result['build'],
                'img'        => $result['icon'],
                'bundle'     => $result['bundle'],
                'type'       => 1,
                'url'        => 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName(),
                'big'        => round($saveInfo->getSize() / 1024 / 1024, 2),
                'er_logo'    => make_password(6),
                'addtime'    => time(),
            ));
            //生成描述文件
            $this->saveMobileConfig($postedId);
        }

        echo json_encode(['code' => 1,'appId'=>$postedId]);
        
        	//TODO 
		// $absolute_path       = config('absolute_path');
		// $mobileprovisionFile = $absolute_path."public/ios-sign-file/1.mobileprovision";
		// $keyFile			   = $absolute_path."public/ios-sign-file/key.pem";
		// $certificateFile     = $absolute_path."public/ios-sign-file/certificate.pem";
		
		// exec('export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/root/bin;isign -c '.$certificateFile.' -k '.$keyFile.' -p '.$mobileprovisionFile.'  -o '.$absolute_path.'public/upload/super_signature/'.$saveInfo->getSaveName().' '.$absolute_path.'public/upload/super_signature/'.$saveInfo->getSaveName().' 2>&1',$out,$status);
		
		// file_put_contents('./sign_error_log/'.time().'.txt',$out);
    }

    //生成mobileConfig文件

	public function saveMobileConfig($id){
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
            unlink(APP_ROOT . '/ios_describe_aoi/'.$id.'.mobileconfig');
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
