<?php
$dir = "super_signature_ipa";

    //先删除目录下的文件：
    $dh=opendir($dir);
    echo "执行成功";
    while ($file=readdir($dh)) {

        if($file!="." && $file!="..") {

            $fullpath=$dir."/".$file;

            if(!is_dir($fullpath)) {
                unlink($fullpath);
            } else {
                //deldir($fullpath);// 递归
            }
        }
    }
    closedir($dh);

   
