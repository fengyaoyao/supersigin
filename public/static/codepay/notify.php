<?php
require_once("codepay_config.php"); //导入配置文件
require_once("includes/MysqliDb.class.php");//导入mysqli连接
require_once("includes/M.class.php");//导入mysqli操作类

function createLinkstring($data){
    $sign='';
    foreach ($data AS $key => $val) {
        if ($sign) $sign .= '&'; //第一个字符串签名不加& 其他加&连接起来参数
        $sign .= "$key=".urlencode($val); //拼接为url参数形式
    }
    return $sign;
}

function DemoHandle($data){ //业务处理例子 返回一些字符串
    $pay_id = $data['pay_id']; //需要充值的ID 或订单号 或用户名
    $money = (float)$data['money']; //实际付款金额
    $price = (float)$data['price']; //订单的原价
    $type = (int)$data['type']; //支付方式
    $pay_no = $data['pay_no']; //支付流水号
    $param = $data['param']; //自定义参数 原封返回 您创建订单提交的自定义参数
    $pay_time = (int)$data['pay_time']; //付款时间戳
    $pay_tag = $data['tag']; //支付备注 仅支付宝才有 其他支付方式全为0或空
    $status = 2; //业务处理状态 这里就全设置为2  如有必要区分是否业务同时处理了可以处理完再更新该字段为其他值
    $creat_time = time(); //创建数据的时间戳
    if ($money <= 0 || empty($pay_id) || $pay_time <= 0 || empty($pay_no)) {
        return '缺少必要的一些参数'; //测试数据中 唯一标识必须包含这些
    }
    $m = new M();
    if (!defined('DB_USERTABLE')) defined('DB_USERTABLE', 'codepay_user');  //默认的用户数据表
    if (!defined('DB_PREFIX')) defined('DB_PREFIX', 'codepay'); //默认的表前缀
    if (!defined('DB_AUTOCOMMIT')) defined('DB_AUTOCOMMIT', false); //默认使用事物 回滚
    if (!defined('DEBUG')) defined('DEBUG', false); //默认启用调试模式 但这里如果读不到就不启用了
    $m->db->autocommit(DB_AUTOCOMMIT);//默认不自动提交 即事物开启 只针对InnoDB引擎有效
    $insertSQL = "INSERT INTO `" . DB_PREFIX . "_order` (`pay_id`, `money`, `price`, `type`, `pay_no`, `param`, `pay_time`, `pay_tag`, `status`, `creat_time`)values(?,?,?,?,?,?,?,?,?,?)";
    $stmt = $m->prepare($insertSQL);//预编译SQL语句
    if (!$stmt) {
        return "数据表:" . DB_PREFIX . "_order  不存在 可能需要重新安装";
    }
    $stmt->bind_param('sddissisii', $pay_id, $money, $price, $type, $pay_no, $param, $pay_time, $pay_tag, $status, $creat_time); //防止SQL注入
    $rs = $stmt->execute(); //执行SQL
    if ($rs && $stmt->affected_rows >= 1) { //插入成功 是首次通知 可以执行业务
        mysqli_stmt_close($stmt); //关闭上次的预编译
        $price = $price * 1;//1表示比率为1:1  100则表示1元可充值100分;
        $sql = "update `" . DB_USERTABLE . "` set " . DB_USERMONEY . "=" . DB_USERMONEY . "+{$price} where " . DB_USERNAME . "=?";
        $stmt = $m->prepare($sql); //预编译SQL语句
        if (empty($stmt)) return sprintf("%s SQL语句存在问题一般是参数修改不正确造成   SQL: %s 参数：%s ", $m->db->error,$sql, createLinkstring($data));
        if ($stmt->error != '') { //捕获错误 这一般是数据表不存在造成
            $result = sprintf("数据表存在问题 ：%s SQL: %s 参数：%s ", $stmt->error, $sql, createLinkstring($data));
            mysqli_stmt_close($stmt); //关闭预编译
            $m->rollback();//回滚
            return $result;
        }
        $stmt->bind_param('s', $pay_id); //绑定参数 防止注入
        $rs = $stmt->execute(); //执行SQL语句
        if ($rs && $stmt->affected_rows >= 1) {

            if (!DB_AUTOCOMMIT) $m->db->commit(); //提交事物 保存数据
            mysqli_stmt_close($stmt); //关闭预编译
            return 'ok'; //业务处理完成 。

        } else { //如果下次还要处理则应该开启事物 数据库引擎为InnoDB 不支持事物该笔订单是无法再执行到业务处理这个步骤除非是使用订单状态标识区分
            return 'ok';
        }

    } else if ($stmt->errno == 1062) {

        return 'success';
    } else {
        $m->rollback();//错误回滚
        if ($stmt->errno == 1146) { //不存在测试数据表
            $result = '您还未安装测试数据 无法使用业务处理示范'; //需在网页执行 install.php 安装测试数据 如访问：http://您的网站/codepay/install.php
        } else {
            $result = sprintf("比较严重的错误必须处理 ：%s SQL: %s 参数：%s \r\nMYSQL信息：%s", $stmt->error, $insertSQL, createLinkstring($data), createLinkstring($stmt));
        }
    }
    mysqli_stmt_close($stmt); //关闭预编译
    return $result;
}

$codepay_key = $codepay_config['key']; //这是您的密钥

$isPost = true; //默认为POST传入

if (empty($_POST)) { //如果GET访问
    $_POST = $_GET;  //POST访问 为服务器或软件异步通知  不需要返回HTML
    $isPost = false; //标记为GET访问  需要返回HTML给用户
}
ksort($_POST); //排序post参数
reset($_POST); //内部指针指向数组中的第一个元素

$sign = ''; //加密字符串初始化

foreach ($_POST AS $key => $val) {
    if ($val == '' || $key == 'sign') continue; //跳过这些不签名
    if ($sign) $sign .= '&'; //第一个字符串签名不加& 其他加&连接起来参数
    $sign .= "$key=$val"; //拼接为url参数形式
}
$pay_id = $_POST['pay_id']; //需要充值的ID 或订单号 或用户名
$money = (float)$_POST['money']; //实际付款金额
$price = (float)$_POST['price']; //订单的原价
$param = $_POST['param']; //自定义参数
$type = (int)$_POST['type']; //支付方式
$pay_no = $_POST['pay_no'];//流水号
if (!$_POST['pay_no'] || md5($sign . $codepay_key) != $_POST['sign']) { //不合法的数据
    if ($isPost) exit('fail');  //返回失败 继续补单
    $result = '支付失败';
    $pay_id = "支付失败";
    $pay_no = "支付失败";
    if ($type < 1) $type = 1;
} else { //合法的数据
    //业务处理
    $result = DemoHandle($_POST); //调用示例业务代码 处理业务获得返回值

    //////////////////////////////////开始自身业务处理////////////////////////////////////////

    $mySQLi = new MySQLi('localhost','root','root','111',3306);
    //判断数据库是否连接
    if($mySQLi -> connect_errno){
        die('连接错误' . $mySQLi -> connect_error);
    }
    $mySQLi -> set_charset('utf8');
    //获取order订单信息
    $orderno = "select a.* from cmf_charge_log a where a.order_id = '".$pay_id."'";
    $rorder = $mySQLi -> query($orderno);
    $rowrder = $rorder->fetch_assoc();
    //获取人员信息
    $useridid = $rowrder['uid'];
    //赠送次数
    $zengsong = $rowrder['d_gift'];
    //实际购买次数
    $goumai = $rowrder['download_download'];
    //总共的次数
    $totalcishu = $zengsong+$goumai;
    //获取人员信息
    $userobj = "select a.* from cmf_user a where a.id = '".$useridid."'";
    $rorderuser = $mySQLi -> query($userobj);
    $rowuser = $rorderuser->fetch_assoc();
    //现有的次数
    $xycs = $rowuser['downloads'];
    $totalcishu+=$xycs;
    //更新人员次数
    $upxycs = "UPDATE cmf_user SET downloads = ".$totalcishu." WHERE id = '".$useridid."'";
    $mySQLi -> query($upxycs);

    //更新订单状态
    $updd= "UPDATE cmf_charge_log SET status = 1 WHERE order_id = '".$useridid."'";
    $mySQLi -> query($updd);
    $coin = $rowrder['coin'];//充值的钻石
    $coin_give = $rowrder['coin_give'];//赠送钻石
    $money = floatval($rowrder['money']);//充值金额
    $userid = $rowrder['uid'];//充值用户
    //更新订单状态
    $uporder = "UPDATE cmf_users_charge SET status = 1 WHERE orderno = '".$pay_id."'";
    $mySQLi -> query($uporder);

    if ($result == 'ok' || $result == 'success') { //返回的是业务处理完成

        if (!DEBUG) ob_clean(); //如果非调试模式 清除之前残留的东西直接打印成功
        if ($isPost) exit($result); //服务器访问 业务处理完成 下面不执行了
        $result = '支付成功';
    } else {
        $error_msg = defined('DEBUG') && DEBUG ? $result : 'no'; //调试模式显示 否则输出no
        if ($isPost) exit($error_msg);  //服务器访问 返回给服务器
        $result = '支付失败';
    }

    $return_url = $_SERVER["SERVER_PORT"] == '80' ? '/' : '//' . $_SERVER['SERVER_NAME'];


    //以下为GET访问 返回HTML结果给用户

}

if ((int)$codepay_config['go_time'] < 1) $codepay_config['go_time'] = 3;
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="Content-Language" content="zh-cn">
    <meta name="apple-mobile-web-app-capable" content="no"/>
    <meta name="apple-touch-fullscreen" content="yes"/>
    <meta name="format-detection" content="telephone=no,email=no"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="white">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <title>支付详情</title>
    <link href="css/wechat_pay.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" type="text/css" media="screen" href="css/font-awesome.min.css">
    <style>
        .text-success {
            color: #468847;
            font-size: 2.33333333em;
        }

        .text-fail {
            color: #ff0c13;
            font-size: 2.33333333em;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .error {

            display: block;
            padding: 9.5px;
            margin: 0 0 10px;
            font-size: 13px;
            line-height: 1.42857143;
            color: #333;
            word-break: break-all;
            word-wrap: break-word;
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            border-radius: 4px;

        }
    </style>
</head>

<body>
<div class="body">
    <h1 class="mod-title">
        <span class="ico_log ico-<?php echo (int)$type ?>"></span>
    </h1>

    <div class="mod-ct">
        <div class="order">
        </div>
        <div class="amount" id="money">￥<?php echo $money; ?></div>
        <h1 class="text-center text-<?php echo($result != '支付成功' ? 'fail' : 'success'); ?>"><strong><i
                        class="fa fa-check fa-lg"></i> <?php echo $result; ?></strong></h1>
        <?php echo($error_msg ? "以下错误信息关闭调试模式可隐藏：<div class='error text-left'>{$error_msg}</div>" : ''); ?>
        <div class="detail detail-open" id="orderDetail" style="display: block;">
            <dl class="detail-ct" id="desc">
                <dt>金额</dt>
                <dd><?php echo $money ?></dd>
                <dt>商户订单：</dt>
                <dd><?php echo htmlentities($pay_id) ?></dd>
                <dt>流水号：</dt>
                <dd><?php echo htmlentities($pay_no) ?></dd>
                <dt>付款时间：</dt>
                <dd><?php echo date("Y-m-d H:i:s", (int)$_GET["pay_time"]) ?></dd>
                <dt>状态</dt>
                <dd><?php echo $result; ?></dd>
            </dl>


        </div>

        <div class="tip-text">
        </div>


    </div>
    <div class="foot">
        <div class="inner">
            <p>如未到账请联系我们</p>
        </div>
    </div>

</div>
<div class="copyRight">
    <p>支付合作：<a href="http://codepay.fateqq.com/" target="_blank">码支付</a></p>
</div>
<script>
    setTimeout(function () {
        //这里可以写一些后续的业务
        <?php if($codepay_config['go_url']){
        ?>

        window.location.href = '<?php echo $codepay_config['go_url']?>'; //跳转

        <?php
        }?>

    }, <?php echo((int)$codepay_config['go_time'] * 1000)?>);//默认3秒后跳转
</script>
</body>
</html>