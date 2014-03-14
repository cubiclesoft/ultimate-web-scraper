<?php
	// Test suite.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));
	require_once $rootpath . "/support/web_browser.php";
	require_once $rootpath . "/support/http.php";
	require_once $rootpath . "/support/crc32_stream.php";
	require_once $rootpath . "/support/deflate_stream.php";
	require_once $rootpath . "/support/simple_html_dom.php";

	$html = new simple_html_dom();

	$web = new WebBrowser();
	$result = $web->Process("http://www.barebonescms.com/");
	if (!$result["success"])  echo "[FAIL] An error occurred.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "[FAIL] An unexpected response code was returned.  " . $result["response"]["line"] . "\n";
	else
	{
		echo "[PASS] The expected response was returned.\n";

		$html->load($result["body"]);
		$rows = $html->find('a[href]');
		foreach ($rows as $row)
		{
			echo "\t" . HTTP::ConvertRelativeToAbsoluteURL($result["url"], $row->href) . "\n";
		}
	}

	$result = $web->Process("https://www.barebonescms.com/");
	if (!$result["success"])  echo "[FAIL] An error occurred.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "[FAIL] An unexpected response code was returned.  " . $result["response"]["line"] . "\n";
	else
	{
		echo "[PASS] The expected response was returned.\n";

		$html->load($result["body"]);
		$rows = $html->find('a[href]');
		foreach ($rows as $row)
		{
			echo "\t" . HTTP::ConvertRelativeToAbsoluteURL($result["url"], $row->href) . "\n";
		}
	}
?>