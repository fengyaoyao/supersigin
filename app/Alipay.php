<?php
namespace app;

use think\Db;
use think\Controller;
use think\Request;

class AliPay extends Controller{
    public function Pay($order){
		$params = [];
		$config = get_config();
		if(empty($config['alipay_appId'])||empty($config['alipay_charset'])||empty($config['alipay_signType'])||empty($config['alipay_notifyUrl'])||empty($config['alipay_returnUrl'])||empty($config['alipay_merchantPrivateKey'])||empty($config['alipay_publicKey'])){
			$this->error('请联系管理配置支付宝信息');
		}
		$params['app_id'] = $config['alipay_appId'];
		$params['method'] = 'alipay.trade.wap.pay';
		$params['format'] = 'JSON'; 
		$params['charset'] = $config['alipay_charset'];
		$params['sign_type'] = $config['alipay_signType'];
		$params['timestamp'] = date('Y-m-d H:i:s');
		$params['version'] = '1.0';
		$params['notify_url'] = $config['alipay_notifyUrl'];
		$params['return_url'] = $config['alipay_returnUrl'].'?order='.$order['orderid'];
		$biz_content = [];
		$biz_content['subject'] = '设备:'.$order['udid'].'充值';
		$biz_content['out_trade_no'] = $order['orderid'];
		$biz_content['timeout_express'] = '30m';
		$biz_content['total_amount'] = $order['money'];
		$biz_content['product_code'] = 'QUICK_WAP_PAY';		

		$params['biz_content'] = json_encode($biz_content);
		unset($biz_content);
		ksort($params);

		$res = "-----BEGIN RSA PRIVATE KEY-----\n".wordwrap($config['alipay_merchantPrivateKey'],64,"\n",true)."\n-----END RSA PRIVATE KEY-----";
		openssl_sign(urldecode(http_build_query($params)), $sign, $res, OPENSSL_ALGO_SHA256);
		$params['sign'] = base64_encode($sign);		
		$url = http_build_query($params);
		$url = 'https://openapi.alipay.com/gateway.do?'.$url;
		$this->wlog(APP_ROOT.'/paylog/alipay/'.date('Ym').'/pay_'.date('d').'.log',var_export($order,true)."\r\n".var_export($params,true)."\r\n".$url."\r\n\r\n");
		$this->success($url);
    }

	public function Notify(){
		$this->wlog((APP_ROOT.'/paylog/alipay/'.date('Ym').'/notify_'.date('d').'.log'),(date('H:i:s')."\r\n".var_export($_POST,true)."\r\n\r\n"));
		foreach($_POST as $k => &$v){
			if(strpos($v,'[{&quot;')!==false){
				$v = htmlspecialchars_decode($v);
			}
		}
		if(!empty($_POST)){
			$notify = $_POST;
			$config = get_config();	
			if(isset($notify['app_id'])&&isset($config['alipay_appId'])&&$notify['app_id']==$config['alipay_appId']&&isset($config['alipay_publicKey'])&&isset($notify['trade_status'])&&in_array($notify['trade_status'],['TRADE_SUCCESS','TRADE_FINISHED'])){
				$sign = $notify['sign'];
				unset($notify['sign'],$notify['sign_type'],$notify['type']);
				ksort($notify);							
				$res = "-----BEGIN PUBLIC KEY-----\n".wordwrap($config['alipay_publicKey'],64,"\n",true)."\n-----END PUBLIC KEY-----";
				$verify = openssl_verify(urldecode(http_build_query($notify)),base64_decode($sign),$res,OPENSSL_ALGO_SHA256);
				if($verify==1){
					$pay = Db::name('pay')->where('orderid',$notify['out_trade_no'])->find();
					if(!empty($pay)){
						if(number_format($notify['total_amount'],2,'.','')==number_format($pay['money'],2,'.','')||intval($notify['total_amount'])==intval($pay['money'])){
							if($pay['status']==0){
								$up = [];
								$up['paytime'] = time();
								$up['status'] = 1;
								$up['tradeno'] = $notify['out_trade_no'];
								Db::name('pay')->where('id',$pay['id'])->update($up);
							}
							echo 'success';exit;
						}						
					}
				}
			}
		}
		echo 'fail';exit;
	}
	
	private function wlog($filepath, $data, $add = true){
		$dir = dirname($filepath);
		if(!is_dir($dir)){
			$this->mk_dir($dir);
		}
		if($add){
			$result = file_put_contents($filepath,$data,FILE_APPEND);
		}else{
			$result = file_put_contents($filepath,$data);
		}
		if($result === false){
			return false;
		}else{
			return true;
		}
	}

	private function mk_dir($dir, $mode = 0777){
		if(is_dir($dir) || @mkdir($dir, $mode)){
			return true;
		}
		if(!$this->mk_dir(dirname($dir), $mode)){
			return false;
		}
		return @mkdir($dir, $mode);
	}
}