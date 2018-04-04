<?php
	// Interactive command-line test.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));
	require_once $rootpath . "/../support/web_browser.php";
	require_once $rootpath . "/../support/simple_html_dom.php";
	require_once $rootpath . "/../support/tag_filter.php";

	function DisplayInteractiveRequest(&$state)
	{
		echo "Retrieving '" . $state["url"] . "'...\n";
		echo "Options:\n";
		echo "\t" . str_replace("\n", "\n\t", trim(json_encode($state["options"], JSON_PRETTY_PRINT))) . "\n";
		echo "\n";

		return true;
	}

	// Souped up initialization.
	$html = new simple_html_dom();
	$web = new WebBrowser(array("extractforms" => true, "httpopts" => array("pre_retrievewebpage_callback" => "DisplayInteractiveRequest")));
	$filteropts = TagFilter::GetHTMLOptions();

	$url = false;
	do
	{
		if ($url === false)
		{
			echo "URL:  ";
			$url = trim(fgets(STDIN));

			$result = array(
				"url" => $url,
				"options" => array()
			);
		}

		$result2 = $web->Process($result["url"], $result["options"]);
		if (!$result2["success"])
		{
			echo "An error occurred.  " . $result2["error"] . "\n";

			$url = false;
		}
		else if ($result2["response"]["code"] != 200)
		{
			echo "An unexpected response code was returned.  " . $result2["response"]["line"] . "\n";

			$url = false;
		}
		else
		{
			// Clean up the HTML.
			$body = TagFilter::Run($result2["body"], $filteropts);
			$html->load($body);

			$links = array();
			$rows = $html->find('a[href]');
			foreach ($rows as $row)
			{
				$links[] = array("url" => HTTP::ConvertRelativeToAbsoluteURL($result2["url"], (string)$row->href), "display" => trim($row->plaintext));
			}

			echo "Available links:\n";
			foreach ($links as $num => $info)
			{
				echo "\t" . ($num + 1) . ":  " . ($info["display"] !== "" ? $info["display"] : $info["url"]) . "\n";
			}
			echo "\n";

			if (count($result2["forms"]))  echo "Available forms:  " . count($result2["forms"]) . "\n\n";

			do
			{
				echo "Command (URL, link ID, 'forms', 'body', or 'exit'):  ";
				$cmd = trim(fgets(STDIN));

				if ($cmd === "body")  echo "\n" . $body . "\n\n";

			} while ($cmd != "forms" && $cmd != "exit" && strpos($cmd, "/") === false && !isset($links[(int)$cmd - 1]));

			if ($cmd === "exit")  exit();
			else if ($cmd === "forms")  $result = $web->InteractiveFormFill($result2["forms"]);
			else if (strpos($cmd, "/") !== false)
			{
				$result = array(
					"url" => HTTP::ConvertRelativeToAbsoluteURL($result2["url"], $cmd),
					"options" => array()
				);
			}
			else
			{
				$result = array(
					"url" => $links[(int)$cmd - 1]["url"],
					"options" => array()
				);
			}
		}
	} while (1);
?>