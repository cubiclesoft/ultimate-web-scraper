<?php
	// Tests the functionality of the DOHWebBrowser (DOH = DNS Over HTTPS) class.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../support/web_browser.php";
	require_once $rootpath . "/../doh_web_browser.php";
	require_once $rootpath . "/../support/tag_filter.php";

	$web = new DOHWebBrowser();

	$urls = array(
		"https://cubiclesoft.com/",
		"https://barebonescms.com/",
	);

	$htmloptions = TagFilter::GetHTMLOptions();

	foreach ($urls as $url)
	{
		echo "Retrieving '" . $url . "'...\n";

		$result = $web->Process($url);
		if (!$result["success"] || $result["response"]["code"] != 200)
		{
			var_dump($result);

			exit();
		}

		$html = TagFilter::Explode($result["body"], $htmloptions);
		$root = $html->Get();

		echo "Page title:  " . $root->Find("title")->current()->GetPlainText() . "\n\n";
	}

	echo "DNS over HTTPS query results:\n";
	echo json_encode($web->GetDOHCache(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
?>