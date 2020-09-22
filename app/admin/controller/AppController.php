<?php

// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use Qiniu\Auth;    // 引入鉴权类
use Qiniu\Storage\UploadManager;    // 引入上传类
class AppController extends AdminBaseController
{
    public function index()
    {
        $where = [];
        /**搜索条件**/
        $params = $this->request->param();
        if (!empty($params)) {
            // print_r($params );exit;
            $name = empty($params['name'])?'':$params['name'];
            $uid = empty($params['uid'])?'':$params['uid'];
        }
 
        if (!empty($uid)){
            $where['uid'] = $uid;
        }

        if (!empty($name)){
            $where['name'] = ['like', "%$name%"];
        }

        $app = Db::name('user_posted')->alias('up')
                ->field("up.*,u.user_email,u.user_nickname,(SELECT count(*) FROM cmf_super_download_log as csdl where up.id = csdl.app_id and csdl.device !='andriod') as ios_count,(SELECT count(*) FROM cmf_super_download_log as csdl where up.id = csdl.app_id and csdl.device ='andriod') as andriod_count")
            ->join('user u','up.uid =u.id')
            ->where($where)
            ->order("up.id DESC")
            ->paginate(15,false,[
                'query'=>$params
            ]);

        // 获取分页显示
        $this->assign("params", $params);
        $this->assign("page", $app->render());
        $this->assign("app", $app);
        return $this->fetch();
    }

    public function get_user_info(){
        $id = input('uid');
        $user_info = Db::name('user')->where('id',$id)->field('mobile,sup_down_public,pid')->find();
        $pid = $user_info['pid'] == 0?1:$user_info['pid'];
        $domain = Db::name('domain')->where('uid',$pid)->field('domain')->find();
        $user_info['domain'] = $domain['domain'];
        $user_info['tid'] = input('id');
        return json(['code'=>1,'data'=>$user_info]);
    }

    //获取udid数据
    public function udid($appId){
        $list = Db::name('ios_udid_list')->where('app_id',$appId)->field('udid')->select();
		$export = input('export');
		if(!empty($export)){
			$data = [];
			foreach($list as $k => $v){
				$data[$k][] = $v['udid'];
			}
			exportToExcels('导出UDID-'.date('Y-m-d H:i:s', time()).'.csv', ['udid'], $data);
			exit;			
		}
        $this->assign([
            'list'=>$list
        ]);
        return $this->fetch();
    }

    public function delete()
    {
        $id = $this->request->param('id', 0, 'intval');
        if (!empty($id)) {
            //状态：1正常，2审核中，3已删除，4官方删除
            $result = Db::name('user_posted')->where(["id" => $id])->setField('status', '3');
            if ($result !== false) {
                $this->success("应用删除成功！", url("App/index"));
            } else {
                $this->error('应用删除失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }
    //扣量比例
    public function take_out()
    {
        $id = input('param.id');
        $num = input('param.num');
        if (!is_numeric($num) || empty($num) || $num > 1 || $num < 0) {
            return json(['code'=>0,'msg'=>'输入内容不合法']);
        }

        $user = Db::name("user_posted")->where('id',$id)->update(['take_out'=>$num]);
        if ($user) {
            return json(['code'=>200,'msg'=>'设置成功!']);
        } else {
            return json(['code'=>201,'msg'=>'设置失败!']);
        }
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');
        $app = DB::name('user_posted')->where(["id" => $id])->find();
        $this->assign($app);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
			if (strpos($_POST['img'], 'base64') === false && strpos($_POST['img'], '/upload') === false) {
                $_POST['img'] = '/upload/' . $_POST['img'];
            }
			$this->mobileconfig($_POST['id']);
            $result = DB::name('user_posted')->update($_POST);
            if ($result !== false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }

        }
    }
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
    //修改APP状态
    public function edit_app_status()
    {
        $status = intval(input('param.status'));
        $id = intval(input('param.id'));

        db('user_posted')->where('id=' . $id)->setField('status', $status);
        $this->success('操作成功！');
    }

    //删除app并删除文件
    public function delete_file()
    {
        $id = intval(input('param.id'));

        $record = Db::name("user_posted")->where("id=" . $id)->find();
        $type = false;
        if (!$record) {
            $this->error('应用不存在！');
        }
        if ($record['url_name'] != '1') {
            if($record['is_open_super_sign']!=1){
                $ymurl = $record['url'];
                $upload = ROOT_PATH."public/";
                $urlss = $upload.$ymurl;
                @unlink($urlss);
            }
        }
        $result = Db::name("user_posted")->where("id=" . $id)->delete();
        if (!$type) {
            $this->success("删除成功");
        } else {
            $this->success("文件删除失败");
        }
    }

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

}
