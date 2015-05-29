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
	require_once $rootpath . "/support/tag_filter.php";

	function TestHTMLTagFilter($stack, &$content, $open, $tagname, &$attrs, $options)
	{
		if ($open)
		{
			if ($tagname === "script")  return array("keep_tag" => false, "keep_interior" => false);
			else if ($tagname === "style")  return array("keep_tag" => false, "keep_interior" => false);
			else if ($tagname === "img")
			{
				if (!isset($attrs["src"]) || substr($attrs["src"], 0, 11) === "javascript:")  return array("keep_tag" => false);

				foreach ($attrs as $key => $val)
				{
					if ($key !== "src")  unset($attrs[$key]);
				}
			}
			else if ($tagname === "a")
			{
				if (!isset($attrs["href"]) || substr($attrs["href"], 0, 11) === "javascript:")  return array("keep_tag" => false);

				foreach ($attrs as $key => $val)
				{
					if ($key !== "href")  unset($attrs[$key]);
				}
			}
			else if ($tagname === "p")
			{
				foreach ($attrs as $key => $val)
				{
					if ($key !== "class")  unset($attrs[$key]);
					else
					{
						foreach ($val as $class)
						{
							if ($class !== "allowedclass")  unset($val[$class]);
						}

						$attrs["class"] = $val;
						if (!count($attrs["class"]))  unset($attrs["class"]);
					}
				}
			}
			else if ($tagname === "br" || $tagname === "b" || $tagname === "strong" || $tagname === "i" || $tagname === "ul" || $tagname === "ol" || $tagname === "li")
			{
				// Remove all attributes.
				$attrs = array();
			}
			else
			{
				return array("keep_tag" => false);
			}
		}
		else
		{
			if ($tagname === "/b" || $tagname === "/strong" || $tagname === "/i" || $tagname === "/ul" || $tagname === "/ol" || $tagname === "/li")
			{
				if (trim($content) === "")  return array("keep_tag" => false);
			}
		}

		return array();
	}

	$options = TagFilter::GetHTMLOptions();
	$options["tag_callback"] = "TestHTMLTagFilter";

	echo "Testing XSS removal\n";
	echo "-------------------\n";
	$testfile = file_get_contents("test_xss.txt");
	$pos = strpos($testfile, "@EXIT@");
	if ($pos === false)  $pos = strlen($testfile);
	$testfile = substr($testfile, 0, $pos);

	$result = TagFilter::Run($testfile, $options);
	echo $result . "\n\n";
	echo "-------------------\n\n";

	echo "Testing Word HTML cleanup\n";
	echo "-------------------------\n";
	$testfile = file_get_contents("test_word.txt");
	$pos = strpos($testfile, "@EXIT@");
	if ($pos === false)  $pos = strlen($testfile);
	$testfile = substr($testfile, 0, $pos);

	$result = TagFilter::Run($testfile, $options);
	echo $result . "\n\n";
	echo "-------------------------\n\n";


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