<?php


namespace app\admin\controller;


use cmf\controller\AdminBaseController;
use think\Db;

class FastlaneController extends AdminBaseController
{

    //证书管理
    public function index()
    {
        $params = input('param.');
		$where = [];
		$where['fastlane'] = 1;
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
        $list = Db::name('ios_certificate')
            ->where($where)
            ->order("total_count DESC")
            ->paginate(15,false,['query'=>$params]);
        // 获取分页显示
        $page = $list->render();
		$this->assign("params", $params);
        $this->assign("page", $page);
        $this->assign("list", $list);

        return $this->fetch();
    }
	
	public function checkLogin(){


      // $com = 'export LANG="en_US.UTF-8";export LC_ALL="en_US.UTF-8";export PATH="/root/.pyenv/shims:/root/.pyenv/bin:/usr/local/rvm/gems/ruby-2.6.6/bin:/usr/local/rvm/gems/ruby-2.6.6@global/bin:/usr/local/rvm/rubies/ruby-2.6.6/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/usr/local/rvm/bin:/root/bin";export GEM_HOME="/usr/local/rvm/gems/ruby-2.6.6";export GEM_PATH="/usr/local/rvm/gems/ruby-2.6.6:/usr/local/rvm/gems/ruby-2.6.6@global";export FASTLANE_USER="shutantuo33293@21cn.com";export FASTLANE_PASSWORD="Ar112211";export FASTLANE_APPLE_APPLICATION_SPECIFIC_PASSWORD="";export SPACESHIP_2FA_SMS_DEFAULT_PHONE_NUMBER="+86 18381001233";export FASTLANE_SESSION="";cd /www/wwwroot/ruby/;fastlane spaceauth -u shutantuo33293@21cn.com;ruby checkLogin.rb shutantuo33293@21cn.com';

      //   $wirte = '';
      //   $handle = popen($com, 'w');
      //   $absolute_path = config('absolute_path');
      //   for ($i = 0; $i <60 ; $i++) {
      //       $result = file_get_contents($absolute_path.'public/log/buckle_quantity/code.txt');
      //       if (empty($result)) {
      //           sleep(1);
      //       }else{
      //           $wirte = fwrite($handle, $result);
      //           pclose($handle);
      //           break;
      //       } 
      //   }
      //   echo $wirte;exit;

		$id = input('param.id');
        $certificate = db('ios_certificate')->find($id);
        if (!$certificate) {
            $this->error('证书不存在！');
        }
		if(empty($certificate['username'])||empty($certificate['password'])||empty($certificate['mobile'])){
			$this->error($certificate['id'].'号证书信息遗漏，请联系管理员完善');
		}
		$shell = 'export LANG="en_US.UTF-8";export LC_ALL="en_US.UTF-8";export PATH="/root/.pyenv/shims:/root/.pyenv/bin:/usr/local/rvm/gems/ruby-2.6.6/bin:/usr/local/rvm/gems/ruby-2.6.6@global/bin:/usr/local/rvm/rubies/ruby-2.6.6/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/usr/local/rvm/bin:/root/bin";export GEM_HOME="/usr/local/rvm/gems/ruby-2.6.6";export GEM_PATH="/usr/local/rvm/gems/ruby-2.6.6:/usr/local/rvm/gems/ruby-2.6.6@global";export FASTLANE_USER="'.$certificate['username'].'";export FASTLANE_PASSWORD="'.$certificate['password'].'";export FASTLANE_APPLE_APPLICATION_SPECIFIC_PASSWORD="'.$certificate['specific_pass'].'";export SPACESHIP_2FA_SMS_DEFAULT_PHONE_NUMBER="'.$certificate['mobile'].'";export FASTLANE_SESSION=\''.str_replace("\n",'\n',$certificate['fastlane_session']).'\';cd /www/wwwroot/ruby/;';
		exec($shell.'ruby checkLogin.rb '.$certificate['username'],$output,$status);
		file_put_contents(config('absolute_path').'public/log/checkLogin/'.$certificate['username'].'.txt',$shell.'fastlane spaceauth -u '.$certificate['username'].';ruby checkLogin.rb '.$certificate['username']);
		if(empty($output)){
			$this->error('未能正常获取响应内容');
		}
		$json = [];
		foreach($output as $v){
			if(substr($v,0,1)=='{'&&substr($v,-1,1)=='}'){
				$json = json_decode($v,true);
			}
		}
		if(empty($json)||!isset($json['status'])){
			$this->error('登录失败，未能正常获取响应内容');
		}
		if($json['status']==0){
			$this->error('登录失败，消息提示：'.$json['msg']);
		}
		$json['session'] = base64_decode($json['session']);
		if(empty($json['session'])){
			$this->error('未能获取session');
		}
		$update = [];
		if(!in_array($certificate['status'],[0,1])){
			$update['status'] = 0;
		}
		if(!empty($update)){
			db('ios_certificate')->where('id='.$id)->update($update);
		}
		$this->error('登录正常，session更新成功');
	}
	
	public function saveCert(){
		$id = input('param.id');
        $certificate = db('ios_certificate')->find($id);
        if (!$certificate) {
            $this->error('证书不存在！');
        }
		if(empty($certificate['username'])||empty($certificate['password'])||empty($certificate['mobile'])){
			$this->error($certificate['id'].'号证书信息遗漏，请联系管理员完善');
		}
		$shell = 'export LANG="en_US.UTF-8";export LC_ALL="en_US.UTF-8";export PATH="/root/.pyenv/shims:/root/.pyenv/bin:/usr/local/php/bin:/usr/local/nginx/sbin:/usr/local/mysql/bin:/usr/local/rvm/gems/ruby-2.6.6/bin:/usr/local/rvm/gems/ruby-2.6.6@global/bin:/usr/local/rvm/rubies/ruby-2.6.6/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/usr/local/rvm/bin:/root/bin";export GEM_HOME="/usr/local/rvm/gems/ruby-2.6.6";export GEM_PATH="/usr/local/rvm/gems/ruby-2.6.6:/usr/local/rvm/gems/ruby-2.6.6@global";export FASTLANE_USER="'.$certificate['username'].'";export FASTLANE_PASSWORD="'.$certificate['password'].'";export FASTLANE_APPLE_APPLICATION_SPECIFIC_PASSWORD="'.$certificate['specific_pass'].'";export SPACESHIP_2FA_SMS_DEFAULT_PHONE_NUMBER="'.$certificate['mobile'].'";export FASTLANE_SESSION=\''.str_replace("\n",'\n',$certificate['fastlane_session']).'\';cd /www/wwwroot/ruby/;ruby saveCert.rb '.$certificate['iss'].' 1';
		exec($shell,$output,$status);
		file_put_contents(config('absolute_path').'public/log/saveCert/'.$certificate['username'].time().'.txt',$shell."\n\n".print_R($output,true));
		if(empty($output)){
			$this->error('未能正常获取响应内容');
		}
		$json = [];
		foreach($output as $v){
			if(substr($v,0,1)=='{'&&substr($v,-1,1)=='}'){
				$json = json_decode($v,true);
			}
		}
		if(empty($json)||!isset($json['status'])){
			$this->error('未能正常解析响应内容');
		}
		if($json['status']==0){
			$this->error('消息提示：'.$json['msg']);
		}
		$this->error('处理成功');
	}

    public function udid($cid){
        $list =  Db::name('ios_udid_list')
                   ->field('udid')
                   ->where('certificate',$cid)
                   ->where('flag','0')
                   ->group('udid')
                   ->select();
                    $this->assign(['list'=>$list]);
        return $this->fetch();
    }

    //添加证书
    public function add_certificate()
    {
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
        $this->assign('certificate', $certificate);
        return $this->fetch();
    }

    //编辑保存
    public function edit_certificate_post()
    {

        $id = input('param.id');
        $tid = input('param.tid');        
		$username = input('param.username');
        $password = input('param.password');
        $mobile = input('param.mobile');
        $specific_pass = input('param.specific_pass');
        $fastlane_session = input('param.fastlane_session');

        $data = [
            'type' => 1,            
            'tid' => $tid,
            'create_time' => time(),
			'username' => $username,
            'password' => $password,
            'mobile' => $mobile,
            'specific_pass' => $specific_pass,
            'fastlane' => 1,
            'fastlane_session' => $fastlane_session,
        ];
        db('ios_certificate')->where('id', $id)->update($data);
        $this->success('编辑成功！');

    }

    //保存证书
    public function save_certificate()
    {
        $username = input('param.username');
        $password = input('param.password');
        $mobile = input('param.mobile');
        $specific_pass = input('param.specific_pass');
        $fastlane = 1;
        $fastlane_session = input('param.fastlane_session');
		
        $record = db('ios_certificate')->where('username', $username)->find();
        if ($record) {
            $this->error('该证书已存在！');
            exit;
        }
		$p12_name   = $username.'.p12';
		$path	  = '/ios_test_c/';
        $data = [
            'type' => 1,            
            'user_id' => 1,            
            'iss' => $username,       
			'p12_pwd' => '123456',
			'p12_file'=>$path.$username.'/'.$p12_name,
            'create_time' => time(),
            'total_count' => 0,
            'limit_count' => 100,
			'username' => $username,
            'password' => $password,
            'mobile' => $mobile,
            'specific_pass' => $specific_pass,
            'fastlane' => 1,
            'fastlane_session' => $fastlane_session,
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
        db('ios_certificate')->where('id',$id)->delete();
        $this->success('删除成功！');
    }

    public function import_excel(){

        $file = request()->file('file');
        $filename_new = md5(time()).'.xlsx';
        $path = ROOT_PATH.'public/upload/appaccount/'.date('Ymd',time()).'/';
        $info = $file->validate(['ext'=>'xls,xlsx'])->move($path,$filename_new);

        if ($info) {
            $file_name = $path .$info->getSaveName();
            $objReader =  \PHPExcel_IOFactory::createReader('Excel2007'); 
            $obj_PHPExcel = $objReader->load($file_name, $encode = 'utf-8'); 
            $excel_array = $obj_PHPExcel->getsheet(0)->toArray();
            array_shift($excel_array); 
            $data = [];
            $path  = '/ios_test_c/';
            $record = [];

            foreach ($excel_array as $value) {

                $is_exist = db('ios_certificate')->where('username', $value[0])->find();
                if (!empty($is_exist) ) {
                    $record[] =  $value[0];
                    continue;
                }

                $p12 = $path.$value[0].'/'.$value[0].'.p12';

                $data[] = [
                    'iss'=>$value[0],
                    'username'=>$value[0],
                    'password'=>$value[1],
                    'mobile'=>$value[2],
                    'status'=>$value[3],
                    'type'=>1,
                    'user_id'=>1,
                    'fastlane'=>1,
                    'total_count'=>0,
                    'limit_count'=>100,
                    'p12_file' => $p12,
                    'p12_pwd' => '123456',
                    'create_time'=>time(),
                ];
            }

            $insert_result = db('ios_certificate')->insertAll($data);

            if ($insert_result) {
                return json(['data'=>[],'status'=>200,'messge'=>'批量添加成功!']);
            }
            if (!empty($record)) {
                return json(['data'=>$record,'status'=>202,'messge'=>'以下账户已经存在系统了']);
            }
            return json(['data'=>[],'status'=>201,'messge'=>'账户添加失败!']);
        }
        
        return json(['data'=>[],'status'=>201,'messge'=>$file->getError()]);
    }

}