<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $_GET['url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
//关闭https验证
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$file = curl_exec($ch);
curl_close($ch);
$filename = pathinfo($_GET['url'], PATHINFO_BASENAME);
file_put_contents($_GET['path'] . $filename,$file);
return "/" . $_GET['path'] . $filename;