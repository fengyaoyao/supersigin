<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\Menu;
use app\admin\model\IosUdidListModel;
use app\admin\model\UserModel;
use app\admin\model\IOSCertificateModel;
use app\admin\model\UserPostedModel;

class MainController extends AdminBaseController
{

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     *  后台欢迎页
     */
    public function index()
    {
        $today_start_time = strtotime(date('Y-m-d 00:00:00'));
        $today_end_time = strtotime(date('Y-m-d 23:59:59'));
        // 今日下载量
        $super_download = Db::name("super_download_log")->where('addtime','>=',$today_start_time)->where('addtime','<=',$today_end_time)->count();

        $child_user_count = UserModel::where('pid','>',0)->count();
        $user_count = UserModel::where('user_type',2)->count();

        // 最近7天数据
        $start_time = strtotime(date("Y-m-d 00:00:00",strtotime("+1 day")));
        $end_time = strtotime(date("Y-m-d 00:00:00",strtotime("-6 day")));
        $week = [
            date('Y-m-d',strtotime("-6 day")),
            date('Y-m-d',strtotime("-5 day")),
            date('Y-m-d',strtotime("-4 day")),
            date('Y-m-d',strtotime("-3 day")),
            date('Y-m-d',strtotime("-2 day")),
            date('Y-m-d',strtotime("-1 day")),
            date('Y-m-d')
        ];

        // 最近7天 IOS扣量统计
        $IosUdidListModel = IosUdidListModel::field("count(*) as count_num,FROM_UNIXTIME(`create_time`, '%Y-%m-%d' ) AS forma_time")
            ->where('flag',1)
            ->where('create_time','>=',$end_time)
            ->where('create_time','<=',$start_time)
            ->group('forma_time')
            ->select()
            ->toArray();
        $time_date = array_column($IosUdidListModel,'count_num','forma_time');
        $buckle_quantity = [];
        foreach ($week as $va) {
            $num = empty($time_date[$va])? 0 :$time_date[$va];
            array_push($buckle_quantity, $num);
        }

        //超级签名总上传
        $super_all = UserPostedModel::count();
        //今日上传
        $super_day = UserPostedModel::where('addtime','>=',$today_start_time)->where('addtime','<=',$today_end_time)->count();
        //今日下载
        $superdow_day = IosUdidListModel::where('flag',0)->where('create_time','>=',$today_start_time)->where('create_time','<=',$today_end_time)->count();
        //IOS一周z最近7天装机量
        $superdow_week = [];
        $IosDownload_week = IosUdidListModel::field("count(*) as count_num,FROM_UNIXTIME(`create_time`, '%Y-%m-%d' ) AS forma_time")
                            ->where('flag',0)
                            ->where('create_time','>=',$end_time)
                            ->where('create_time','<=',$start_time)
                            ->group('forma_time')
                            ->select()
                            ->toArray();

        $IosDownload_date = array_column($IosDownload_week,'count_num','forma_time');
        foreach($week as $va){
            $num = empty($IosDownload_date[$va])? 0 :$IosDownload_date[$va];
            array_push($superdow_week, $num);
        }

        //最近7天注册统计
        $UserCreate = UserModel::field("count(*) as count_num,FROM_UNIXTIME(`create_time`, '%Y-%m-%d' ) AS forma_time")
                    ->where('create_time','>=',$end_time)
                    ->where('create_time','<=',$start_time)
                    ->group('forma_time')
                    ->select()
                    ->toArray();
        $User_date = array_column($UserCreate,'count_num','forma_time');

        $user_week = [];
        foreach($week as $va){
            $num = empty($User_date[$va])? 0 :$User_date[$va];
            array_push($user_week, $num);
        }

        //用户总下载次数
        $user_down = UserModel::sum('sup_down_public');
        //证书剩余的量
        $cert_num = IOSCertificateModel::where('status',1)->where('limit_count','>',0)->sum('limit_count');

        $dataAll = [
        	'user_down'=>$user_down,
            'cert_num'=>$cert_num,
            'week'=>json_encode($week),
            'super_all'=>$super_all,
            'super_day'=>$super_day,
            'superdow_day'=>$superdow_day,
            'buckle_quantity'=>json_encode($buckle_quantity),
            'user_week'=>json_encode($user_week),
            'superdow_week'=>json_encode($superdow_week),
            'child_user_count'=>$child_user_count,
            'user_count'=>$user_count,
            'super_download'=>$super_download,
        ];
        $this->assign($dataAll);
        return $this->fetch();
    }

    public function dashboardWidget()
    {
        $dashboardWidgets = [];
        $widgets          = $this->request->param('widgets/a');
        if (!empty($widgets)) {
            foreach ($widgets as $widget) {
                if ($widget['is_system']) {
                    array_push($dashboardWidgets, ['name' => $widget['name'], 'is_system' => 1]);
                } else {
                    array_push($dashboardWidgets, ['name' => $widget['name'], 'is_system' => 0]);
                }
            }
        }

        cmf_set_option('admin_dashboard_widgets', $dashboardWidgets, true);

        $this->success('更新成功!');
    }
}
