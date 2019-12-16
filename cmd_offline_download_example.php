<?php
/**
 * Created by PhpStorm
 * User: Junior Trust
 * Date: 12/16/2019
 * Time: 2:06 AM
 */


require_once  "OfflineDownload.php";

if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
{
    echo "This file is intended to be run from the command-line.";

    exit();
}

if ($argc < 3)
{
    echo "Basic website downloader tool\n";
    echo "Purpose:  Download a website including HTML, image files, CSS, and directly referenced Javascript files.\n";
    echo "\n";
    echo "Syntax:  " . $argv[0] . " destdir starturl [linkdepth]\n";
    echo "\n";
    echo "Examples:\n";
    echo "\tphp " . $argv[0] . " offline-test https://barebonescms.com/ 3\n";

    exit();
}



$ge = new OfflineDownload($argv[1],$argv[2],$argv[3]??false);
$ge->run();
