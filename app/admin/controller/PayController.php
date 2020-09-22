<?php
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\AdminMenuModel;

class PayController extends AdminBaseController{
    public function index(){
		if($_POST){
            session('search', $_POST);
        }
        if (!isset($_GET['page']) and empty($_POST)) {
            session('search', null);
        }
		$where = [];
		if(session('search.orderid')){
			$where[] = 'pay.orderid="'.session('search.orderid').'"';
		}
		if(session('search.uid')){
			$where[] = 'pay.uid="'.intval(session('search.uid')).'"';
		}
		if(session('search.udid')){
			$where[] = 'pay.udid="'.session('search.udid').'"';
		}
        switch(session('search.status')){
			case '-1':
			
			break;
			case '0':
				$where[] = 'pay.status=0';
			break;
			default:
				$where[] = 'pay.status=1';
			break;
		}
		if(session('search.start_time')){
			$where[] = 'pay.addtime>=' . strtotime(session('search.start_time'));
		}
		if(session('search.end_time')){
			$where[] = 'pay.addtime<' . strtotime(session('search.end_time'));
		}
        $pay  = Db::name('pay')->alias('pay')
            ->join('user user','pay.uid=user.id')
            ->join('user_posted posted','pay.posted=posted.id')
			->field('pay.*,user.mobile,posted.name')
			->where(implode(' and ',$where))
            ->paginate(10)
			->each(function($v){
                $v['paytime'] = $v['paytime']?date('Y-m-d',$v['addtime']):'';
                return $v;
            });
		$this->assign('search', session('search'));
        $this->assign('pay', $pay);
		$this->assign('page', $pay->render());
        return $this->fetch();
    }

	public function status(){
        $id  = intval(input('id'));
        $data['status'] = input('status')==1?0:1;
		$result = false;
		if($id){
			$result = Db::name('pay')
				->where('id',$id)
				->update($data);
		}
        return $result?json(['code'=>200]):json(['code'=>0]);
    }
    
}
