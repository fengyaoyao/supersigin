<?php


namespace app\admin\controller;


use cmf\controller\AdminBaseController;
use think\Db;
use MingYuanYun\AppStore\Client;

class CertificateController extends AdminBaseController
{

    //证书管理
    public function index()
    {
		$params = input('param.');
		$where = [];
		if(!empty($params['id'])){
			$where['id'] = intval($params['id']);
		}
		if(!empty($params['iss'])){
			$where['iss'] = trim($params['iss']);
		}
		if(!empty($params['username'])){
			$where['username'] = trim($params['username']);
		}
        if(isset($params['status'])&&in_array($params['status'],[0,1,4,5,401,403])){
			$where['status'] = intval($params['status']);
		}
        /**搜索条件**/
        $team_id = $this->request->param('team_id');

        if ($team_id) {
            $where['team_id'] = ['like', "%$team_id%"];
        }

        $list = Db::name('ios_certificate')
            ->where($where)
            ->order("status")
            ->order('limit_count desc')
            ->paginate(20);
        // 获取分页显示
        $page = $list->render();

        $this->assign("params", $params);
        $this->assign("page", $page);
        $this->assign("list", $list);

        return $this->fetch();
    }

    public function udid($cid){
       $list =  Db::name('ios_udid_list')->where('certificate',$cid)->field('udid')->select();
        $this->assign([
            'list'=>$list
        ]);
        return $this->fetch();
    }

    //添加证书
    public function add_certificate()
    {
        $userAll = db('user')->where(['user_type'=>2,'user_status'=>1])->order('id desc')->select();
        $this->assign('userAll',$userAll);
        return $this->fetch();

    }

    //编辑证书
    public function edit_certificate()
    {
        $id = input('param.id');
        $certificate = db('ios_certificate')->find($id);
        if (!$certificate) {
            $this->error('证书不存在！');
            exit;
        }
        $userAll = db('user')->where(['user_type'=>2,'user_status'=>1])->order('id desc')->select();
        $this->assign('userAll',$userAll);
        $this->assign('certificate', $certificate);
        return $this->fetch();
    }

    //编辑保存
    public function edit_certificate_post()
    {
        $id = input('param.id');
        $iss = input('param.iss');
        $kid = input('param.kid');
        $tid = input('param.tid');
        $user_id = input('param.user_id');
        $mark = trim(input('param.mark'));		
        $day_num = intval(input('param.day_num'));
		if($day_num>100){
			$this->error('每日注册上限不能超过100');
            exit;
		}
        $data = [
            'type' => 1,
            'user_id' => 1,
            'iss' => $iss,
            'kid' => $kid,
            'tid' => $tid,
            'create_time' => time(),
            'mark' => $mark,
            'day_num' => $day_num,			
        ];
        db('ios_certificate')->where('id', $id)->update($data);
        $this->success('编辑成功！');

    }
    
    public function test_add(){
    	return $this->fetch();
    }
   
    public function save_certificate(){
   		include PLUGINS_PATH . "/ipaphp/vendor/autoload.php";
        include PLUGINS_PATH . "/ipaphp/vendor/yunchuang/appstore-connect-api/src/Client.php";
        
 
        $iss	  = input('param.iss');
        $kid	  = input('param.kid');
        $user_id  = 1;//input('param.user_id');
        $mark	  = trim(input('param.mark'));
        $day_num  = intval(input('param.day_num'));
		if($day_num>100){
			$this->error('每日注册上限不能超过100');
            exit;
		}
        $p12_pwd  = '123456';
        $p8_file  = request()->file('p8_file');
    	$path	  = '/ios_test_c/';
    	$key_path = APP_ROOT.$path;
        $app_path = APP_ROOT.$path.$iss.'/';		
        
        //判断文件夹是否存在
        if(!is_dir($app_path)){
            mkdir($app_path);
        }
        if (!$p8_file) {
            $this->error('请上传p8文件！');
            exit;
        }
        if ($p8_file) {
            $p8_info = $p8_file->validate(['size' => 15678, 'ext' => 'p8'])->move($app_path,$iss.'.p8');
            if ($p8_info) {
                // 成功上传后 获取上传信息
                $p8_name = $p8_info->getSaveName();
                $p8_file_path = $app_path.$p8_name;
            } else {
                // 上传失败获取错误信息
                $this->error($p8_info->getError());
                exit;
            }
        }
	
        $config = [
            'iss'    => $iss,
            'kid'    => $kid,
            'secret' => $p8_file_path
        ];
     
        $client = new Client($config);
		
        $client->setHeaders([
			'Authorization' => 'Bearer ' . $client->getToken(),
		]);
		$device_info =  $client->api('certificates')->all([]);
		if(isset($device_info['errors'][0]['status'])){
			$this->error($device_info['errors'][0]['title']);
		}
		foreach ($device_info['data'] as $k=>$v){
			$del_res = $client->api('certificates')->del($v['id']);
		}		
		$device_info  = $client->api('certificates')->reg();
		$device_info  = $device_info['data'];
		$device_count = $client->api('device')->all([]);
		$total_count  = $device_count['meta']['paging']['total'];
		$limit_count  = 100-$device_count['meta']['paging']['total'];
		
		$record = db('ios_certificate')->where('tid', $device_info['id'])->find();
		if ($record) {
			$this->error('该证书已存在！');
			exit;
		}
		
		$name = $iss;
		file_put_contents(".{$path}{$iss}/{$name}.cer", base64_decode($device_info['attributes']['certificateContent']));
		$tid = $device_info['id'];
		$output		= [];
		$return_var = '';
		$p12_name   = $name.'.p12';
		
		exec('openssl x509 -in '.$app_path.$name.'.cer -inform DER -outform PEM -out '.$app_path.$name.'.pem 2>&1',$output,$return_var);
		exec('openssl pkcs12 -export -inkey '.$key_path.'ios.key -in '.$app_path.$name.'.pem -out '.$app_path.$p12_name.' -passout pass:'.$p12_pwd,$output,$return_var);
				
        $data = [
            'type'	  => 1,
            'user_id' => 1,
            'iss'	  => $iss,
            'kid'	  => $kid,
            'tid'	  => $tid,
            'p12_pwd' => $p12_pwd,
            'create_time' => time(),
            'mark' => $mark,
            'p12_file'=>$path.$iss.'/'.$p12_name,
            'p8_file'=>$path.$iss.'/'.$p8_name,
            'total_count' => $total_count,
            'limit_count' => $limit_count,
            'day_num' => $day_num,			
        ];
        
        db('ios_certificate')->insert($data);
        
        $this->success('添加成功！');
   }

    public function certificate_status(){
        $id = input('param.id');
        $info = db('ios_certificate')->find($id);
        if($info['status']==1){
            db('ios_certificate')->where('id',$id)->update(['status'=>0]);
        }else{
            db('ios_certificate')->where('id',$id)->update(['status'=>1]);
        }
        $this->success('操作成功！');
    }

    public function certificate_del(){
        $id = input('param.id');
        $cert = db('ios_certificate')->where('id',$id)->find();
        $p12 = $cert['p12_file'];
        $p8 = $cert['p8_file'];
        $path = explode('.',$p12);
        $path = $path[0];
        $cer = $path.'.cer';
        $pem = $path.'.pem';
        @unlink(APP_ROOT.$p12);
        @unlink(APP_ROOT.$p8);
        @unlink(APP_ROOT.$cer);
        @unlink(APP_ROOT.$pem);
        db('ios_certificate')->where('id',$id)->delete();
        $this->success('删除成功！');
    }

}