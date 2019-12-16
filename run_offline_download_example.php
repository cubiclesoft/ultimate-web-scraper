<?php
/**
 * Created by PhpStorm
 * User: Junior Trust
 * Date: 12/16/2019
 * Time: 2:06 AM
 */


require_once  "OfflineDownload.php";
if( php_sapi_name()=="cli") {
    if (!isset($_SERVER["argc"]) || !$_SERVER["argc"]) {
        echo "This file is intended to be run from the command-line.";

        exit();
    }

    if ($argc < 3) {
        echo "Basic website downloader tool\n";
        echo "Purpose:  Download a website including HTML, image files, CSS, and directly referenced Javascript files.\n";
        echo "\n";
        echo "Syntax:  " . $argv[0] . " destdir starturl [linkdepth]\n";
        echo "\n";
        echo "Examples:\n";
        echo "\tphp " . $argv[0] . " offline-test https://barebonescms.com/ 3\n";

        exit();
    }

    $folder_path = $argv[1];
    $url = $argv[2];
    $depth = $argv[3]??false;
    $allow_cross_domain=$argv[4]??false;
    $ignored_urls =[];
    for($i=5;$i<$argc;++$i){
        $ignored_urls[]=  $argv[$i];
    }


}else {


    ignore_user_abort(true);
    set_time_limit(0);
    if(!isset($_REQUEST['session'],$_REQUEST['url'])){
        echo "Basic website downloader tool<br>";
        echo "Purpose:  Download a website including HTML, image files, CSS, and directly referenced Javascript files.<br>";
        echo "\n";
        echo "Syntax: <code>    " . $_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'] . "?session=my_session&base_url=my_url</code> <br>";
        echo  "<b> or </b><br>";
        echo "Syntax: <code>    " . $_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'] . "?session=my_session&base_url=my_url&link_depth=3&allow_cross_domain=true&ignored_urls[]=url1&ignored_urls[]=url2 </code>";
        echo "\n";
//        echo "Examples:\n";
//        echo "\tphp " . $argv[0] . " offline-test https://barebonescms.com/ 3\n";

        exit();
    }
    $folder_path = $_REQUEST['session'];
    $url = $_REQUEST['base_url'];
    $depth = $_REQUEST['link_depth']??false;
    $allow_cross_domain=$_REQUEST['allow_cross_domain']??false;
    $ignored_urls =$_REQUEST['ignored_urls']??[];
}
//ob_end_clean();
//header("Connection: close\r\n");
//header("Content-Encoding: none\r\n");
////ignore_user_abort(true); // optional
//ob_start();
//echo ('Text user will see');
//$size = ob_get_length();
//header("Content-Length: $size");
//ob_end_flush();     // Strange behaviour, will not work
//flush();            // Unless both are called !
//ob_end_clean();

//do processing here

//$ge = new OfflineDownload("test_s","https://demo.dashboardpack.com/architectui-html-pro/index.html",3,false,["placeholdit.imgix.net"]);
$ge = new OfflineDownload($folder_path,$url,$depth,$allow_cross_domain,$allow_cross_domain);
$ge->run();
