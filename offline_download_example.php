<?php
	// Basic website downloader.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/support/http.php";
	require_once $rootpath . "/support/web_browser.php";
	require_once $rootpath . "/support/tag_filter.php";
	require_once $rootpath . "/support/multi_async_helper.php";

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

	// Don't let PHP run out of RAM.
	@ini_set("memory_limit", "-1");

	@mkdir($argv[1], 0770, true);
	$destpath = realpath($argv[1]);

	// Link traversal depth.
	$linkdepth = ($argc > 3 ? (int)$argv[3] : false);

	// Alter input URL to remove potential attack vectors.
	$initurl = $argv[2];
	$initurl2 = HTTP::ExtractURL($initurl);

	$initurl2["authority"] = strtolower($initurl2["authority"]);
	$initurl2["host"] = strtolower($initurl2["host"]);
	if ($initurl2["path"] === "")  $initurl2["path"] = "/";

	$initurl3 = $initurl2;
	$initurl3["host"] = "";
	$initurl2["path"] = "/";

	$initurl = HTTP::ConvertRelativeToAbsoluteURL($initurl2, $initurl3);

	$manifestfile = $destpath . "/" . str_replace(":", "_", $initurl2["authority"]) . "_manifest.json";
	$opsfile = $destpath . "/" . str_replace(":", "_", $initurl2["authority"]) . "_ops_" . md5(($linkdepth === false ? "-1" : $linkdepth) . "|" . $initurl) . ".json";

	$destpath .= "/" . str_replace(":", "_", $initurl2["authority"]);
	@mkdir($destpath, 0770, true);

	$helper = new MultiAsyncHelper();
	$helper->SetConcurrencyLimit(4);

	$htmloptions = TagFilter::GetHTMLOptions();
	$htmloptions["keep_comments"] = true;

	// Provides some basic feedback prior to retrieving each URL.
	function DisplayURL(&$state)
	{
		global $ops;

		echo "[" . number_format(count($ops), 0) . " ops] Retrieving '" . $state["url"] . "'...\n";

		return true;
	}

	// Calculates the static file extension based on the result of a HTTP request.
	function GetResultFileExtension(&$result)
	{
		$mimeextmap = array(
			"text/html" => ".html",
			"text/plain" => ".txt",
			"image/jpeg" => ".jpg",
			"image/png" => ".png",
			"image/gif" => ".gif",
			"text/css" => ".css",
			"text/javascript" => ".js",
		);

		// Attempt to map a Content-Type header to a file extension.
		if (isset($result["headers"]["Content-Type"]))
		{
			$header = HTTP::ExtractHeader($result["headers"]["Content-Type"][0]);

			if (isset($mimeextmap[strtolower($header[""])]))  return $mimeextmap[$header[""]];
		}

		$fileext = false;

		// Attempt to map a Content-Disposition header to a file extension.
		if (isset($result["headers"]["Content-Disposition"]))
		{
			$header = HTTP::ExtractHeader($result["headers"]["Content-Type"][0]);

			if ($header[""] === "attachment" && isset($header["filename"]))
			{
				$filename = explode("/", str_replace("\\", "/", $header["filename"]));
				$filename = array_pop($filename);
				$pos = strrpos($filename, ".");
				if ($pos !== false)  $fileext = strtolower(substr($filename, $pos));
			}
		}

		// Parse the URL and attempt to map to a file extension.
		if ($fileext === false)
		{
			$url = HTTP::ExtractURL($result["url"]);

			$filename = explode("/", str_replace("\\", "/", $url["path"]));
			$filename = array_pop($filename);
			$pos = strrpos($filename, ".");
			if ($pos !== false)  $fileext = strtolower(substr($filename, $pos));
		}

		if ($fileext === false)  $fileext = ".html";

		// Avoid unfortunate/accidental local code execution via a localhost web server.
		$maptohtml = array(
			".php" => true,
			".php3" => true,
			".php4" => true,
			".php5" => true,
			".php7" => true,
			".phtml" => true,
			".asp" => true,
			".aspx" => true,
			".cfm" => true,
			".jsp" => true,
			".pl" => true,
			".cgi" => true,
		);

		if (isset($maptohtml[$fileext]))  $fileext = ".html";

		return $fileext;
	}

	// Attempt to create a roughly-equivalent structure to the URL on the local filesystem for static serving later.
	function SetReverseManifestPath($key)
	{
		global $ops, $opsdata, $initurl2, $manifestrev, $destpath;

		$url2 = HTTP::ExtractURL($key);
		$path = "";
		if (strcasecmp($url2["authority"], $initurl2["authority"]) != 0)  $path .= "/" . str_replace(":", "_", strtolower($url2["authority"]));
		$path .= ($url2["path"] !== "" ? $url2["path"] : "/");
		$path = explode("/", str_replace("\\", "/", TagFilterStream::MakeValidUTF8($path)));
		$filename = array_pop($path);
		if ($filename === "")  $filename = "index";

		$pos = strrpos($filename, ".");
		if ($pos !== false)  $filename = substr($filename, 0, $pos);

		if ($url2["query"] !== "")  $filename .= "_" . md5($url2["query"]);

		// Make a clean directory.
		$vals = $path;
		$path = array_shift($vals) . "/";
		while (count($vals))
		{
			$path .= array_shift($vals);

			if (isset($manifestrev[strtolower($path)]))  $path = $manifestrev[strtolower($path)];
			else  $manifestrev[strtolower($path)] = $path;

			$x = 0;
			while (is_file($destpath . $path . ($x ? "_" . ($x + 1) : "")))  $x++;

			if ($x)  $path .= "_" . ($x + 1);

			$path .= "/";
		}

		@mkdir($destpath . $path, 0770, true);

		// And a clean filename.
		$path .= $filename;

		$x = 0;
		while (isset($manifestrev[strtolower($path . ($x ? "_" . ($x + 1) : "") . $ops[$key]["ext"])]) || is_dir($path . ($x ? "_" . ($x + 1) : "") . $ops[$key]["ext"]))  $x++;

		$path .= ($x ? "_" . ($x + 1) : "") . $ops[$key]["ext"];

		$opsdata[$key]["path"] = $path;

		// Reserve an entry in the reverse manifest for the full path/filename.
		$manifestrev[strtolower($path)] = $path;

//var_dump($opsdata[$key]["path"]);
//var_dump($manifestrev);
	}

	// Maps a manifest item to a static path on disk.
	$processedurls = array();
	function MapManifestResourceItem($parenturl, $url)
	{
		global $manifest, $processedurls, $opsdata;

		// Strip scheme if HTTP/HTTPS.  Otherwise, just return the URL as-is (e.g. mailto: and data: URIs).
		if (strtolower(substr($url, 0, 7)) === "http://")  $url2 = substr($url, 5);
		else if (strtolower(substr($url, 0, 8)) === "https://")  $url2 = substr($url, 6);
		else  return $url;

		// If already processed and valid, return the relative reference to the path on disk.
		if ($parenturl !== false && isset($opsdata[$parenturl]) && (isset($manifest[$url2]) || isset($opsdata[$url])))
		{
			$path = explode("/", $opsdata[$parenturl]["path"]);
			$path2 = explode("/", (isset($manifest[$url2]) ? $manifest[$url2] : $opsdata[$url]["path"]));

			array_pop($path);

			while (count($path) && count($path2) && $path[0] === $path2[0])
			{
				array_shift($path);
				array_shift($path2);
			}

			$path2 = str_repeat("../", count($path)) . implode("/", $path2);

			return $path2;
		}

		// If already processed but not valid (e.g. a 404 error), just return the URL.
		if (isset($processedurls[$url]))  return $url;

		return false;
	}

	// Generates a leaf node and prevents the parent from completing until the document URLs are updated.
	function PrepareManifestResourceItem($parenturl, $forcedext, $url)
	{
		global $ops, $helper;

		$pos = strpos($url, "#");
		if ($pos === false)  $fragment = false;
		else
		{
			$fragment = substr($url, $pos);
			$url = substr($url, 0, $pos);
		}

		// Skip downloading if the item has already been processed.
		$url2 = MapManifestResourceItem($parenturl, $url);
		if ($url2 !== false)  return $url2 . $fragment;

		// Queue the resource request.
		$key = $url;

		if (!isset($ops[$key]))
		{
			$ops[$key] = array(
				"type" => "res",
				"status" => "download",
				"depth" => 0,
				"retries" => 3,
				"ext" => $forcedext,
				"waiting" => array(),
				"web" => ($parenturl === false ? new WebBrowser(array("followlocation" => false)) : clone $ops[$parenturl]["web"]),
				"options" => array(
					"pre_retrievewebpage_callback" => "DisplayURL"
				)
			);

			$ops[$key]["web"]->ProcessAsync($helper, $key, NULL, $url, $ops[$key]["options"]);
		}

		// Set the waiting status for the parent.
		if ($parenturl !== false)
		{
			if ($ops[$parenturl]["status"] === "waiting")  $ops[$parenturl]["wait_refs"]++;
			else
			{
				$ops[$parenturl]["status"] = "waiting";
				$ops[$parenturl]["wait_refs"] = 1;
			}

			$ops[$key]["waiting"][] = $parenturl;
		}

		return $url;
	}

	// Locate additional files to import in CSS.  Doesn't implement a state engine.
	function ProcessCSS($css, $parenturl, $baseurl)
	{
		$result = $css;

		// Strip comments.
		$css = str_replace("<" . "!--", " ", $css);
		$css = str_replace("--" . ">", " ", $css);
		while (($pos = strpos($css, "/*")) !== false)
		{
			$pos2 = strpos($css, "*/", $pos + 2);
			if ($pos2 === false)  $pos2 = strlen($css);
			else  $pos2 += 2;

			$css = substr($css, 0, $pos) . substr($css, $pos2);
		}

		// Alter @import lines.
		$pos = 0;
		while (($pos = stripos($css, "@import", $pos)) !== false)
		{
			$semipos = strpos($css, ";", $pos);
			if ($semipos === false)  break;

			$pos2 = strpos($css, "'", $pos);
			if ($pos2 === false)  $pos2 = strpos($css, "\"", $pos);
			if ($pos2 === false)  break;

			$pos3 = strpos($css, $css[$pos2], $pos2 + 1);
			if ($pos3 === false)  break;

			if ($pos2 < $semipos && $pos3 < $semipos)
			{
				$url = HTTP::ConvertRelativeToAbsoluteURL($baseurl, substr($css, $pos2 + 1, $pos3 - $pos2 - 1));

				$result = str_replace(substr($css, $pos2, $pos3 - $pos2 + 1), $css[$pos2] . PrepareManifestResourceItem($parenturl, ".css", $url) . $css[$pos2], $result);
			}

			$pos = $semipos + 1;
		}

		// Alter url() values.
		$pos = 0;
		while (($pos = stripos($css, "url(", $pos)) !== false)
		{
			$endpos = strpos($css, ")", $pos);
			if ($endpos === false)  break;

			$pos2 = strpos($css, "'", $pos);
			if ($pos2 !== false && $pos2 > $endpos)  $pos2 = false;
			if ($pos2 === false)  $pos2 = strpos($css, "\"", $pos);

			if ($pos2 === false || $pos2 > $endpos)
			{
				$pos2 = $pos + 3;
				$pos3 = $endpos;
			}
			else
			{
				$pos3 = strpos($css, $css[$pos2], $pos2 + 1);
				if ($pos3 === false || $pos3 > $endpos)  $pos3 = $endpos;
			}

			$url = HTTP::ConvertRelativeToAbsoluteURL($baseurl, substr($css, $pos2 + 1, $pos3 - $pos2 - 1));

			$result = str_replace(substr($css, $pos2, $pos3 - $pos2 + 1), $css[$pos2] . PrepareManifestResourceItem($parenturl, false, $url) . $css[$pos3], $result);

			$pos = $endpos + 1;
		}

		return $result;
	}

	function ProcessContent($key, $final)
	{
		global $ops, $opsdata, $htmloptions, $initurl2, $linkdepth, $helper;

		// Process HTML, altering URLs as necessary.
		if ($ops[$key]["type"] === "node" && $ops[$key]["ext"] === ".html")
		{
			$html = TagFilter::Explode($opsdata[$key]["content"], $htmloptions);
			$root = $html->Get();

			$urlinfo = HTTP::ExtractURL($opsdata[$key]["url"]);

			// Handle images.
			$rows = $root->Find('img[src],img[srcset],picture source[srcset]');
			foreach ($rows as $row)
			{
				if (isset($row->src))
				{
					$url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, $row->src);

					$row->src = PrepareManifestResourceItem($key, false, $url);
				}

				if (isset($row->srcset))
				{
					$urls = explode(",", $row->srcset);
					$urls2 = array();
					foreach ($urls as $url)
					{
						$url = trim($url);
						$pos = strrpos($url, " ");
						if ($pos !== false)
						{
							$url2 = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, trim(substr($url, 0, $pos)));
							$size = substr($url, $pos + 1);

							$urls2[] = PrepareManifestResourceItem($key, false, $url2) . " " . $size;
						}
					}

					$row->srcset = implode(", ", $urls2);
				}
			}

			// Handle link tags with hrefs.
			$rows = $root->Find('link[href],use[xlink\:href]');
			foreach ($rows as $row)
			{
				$url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, (isset($row->href) ? $row->href : $row->{"xlink:href"}));

				$row->href = PrepareManifestResourceItem($key, ((isset($row->rel) && strtolower($row->rel) === "stylesheet") || (isset($row->type) && strtolower($row->type) === "text/css") ? ".css" : false), $url);
			}

			// Handle external Javascript.
			$rows = $root->Find('script[src]');
			foreach ($rows as $row)
			{
				$url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, $row->src);

				$row->src = PrepareManifestResourceItem($key, ".js", $url);
			}

			// Handle style tags.
			$rows = $root->Find('style');
			foreach ($rows as $row)
			{
				$children = $row->Children(true);
				foreach ($children as $child)
				{
					if ($child->Type() === "content")
					{
						$child->Text(ProcessCSS($child->Text(), $key, $urlinfo));
					}
				}
			}

			// Handle inline styles.
			$rows = $root->Find('[style]');
			foreach ($rows as $row)
			{
				$row->style = ProcessCSS($row->style, $key, $urlinfo);
			}

			// Handle anchor tags and iframes.
			$rows = $root->Find('a[href],iframe[src]');
			foreach ($rows as $row)
			{
				$url = ($row->Tag() === "iframe" ? $row->src : $row->href);

				// Skip altering fragment-only URIs.  The browser knows how to natively handle these.
				if (substr($url, 0, 1) === "#")  continue;

				$url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, $url);
				$url2 = HTTP::ExtractURL($url);

				// Only follow links on the same domain.
				if (strcasecmp($url2["authority"], $initurl2["authority"]) == 0 && ($url2["scheme"] === "http" || $url2["scheme"] === "https"))
				{
					if ($url2["path"] === "")
					{
						$url2["path"] = "/";
						$url = HTTP::CondenseURL($url2);
					}

					$pos = strpos($url, "#");
					if ($pos === false)  $fragment = false;
					else
					{
						$fragment = substr($url, $pos);
						$url = substr($url, 0, $pos);
					}

					$url2 = MapManifestResourceItem($key, $url);
					if ($url2 !== false)
					{
						if ($row->Tag() === "iframe")  $row->src = $url2 . $fragment;
						else  $row->href = $url2 . $fragment;
					}
					else
					{
						if ($row->Tag() === "iframe")  $row->src = $url . $fragment;
						else  $row->href = $url . $fragment;

						if ($linkdepth === false || $ops[$key]["depth"] < $linkdepth)
						{
							// Queue up another node.
							$key2 = $url;

							if (!isset($ops[$key2]))
							{
								$ops[$key2] = array(
									"type" => "node",
									"status" => "download",
									"depth" => $ops[$key]["depth"] + 1,
									"retries" => 3,
									"ext" => false,
									"waiting" => array(),
									"web" => clone $ops[$key]["web"],
									"options" => array(
										"pre_retrievewebpage_callback" => "DisplayURL"
									)
								);

								$ops[$key]["web"]->ProcessAsync($helper, $key2, NULL, $url, $ops[$key2]["options"]);
							}

							if ($key !== $key2)
							{
								if ($ops[$key]["status"] === "waiting")  $ops[$key]["wait_refs"]++;
								else
								{
									$ops[$key]["status"] = "waiting";
									$ops[$key]["wait_refs"] = 1;
								}

								$ops[$key2]["waiting"][] = $key;
							}
						}
					}
				}
			}

			// Mix down the content back into HTML.
			if ($final)  $opsdata[$key]["content"] = $root->GetOuterHTML();
		}

		// Process CSS, altering URLs as necessary.
		if ($ops[$key]["ext"] === ".css")
		{
			$urlinfo = HTTP::ExtractURL($opsdata[$key]["url"]);

			$result = ProcessCSS($opsdata[$key]["content"], $key, $urlinfo);

			if ($final)  $opsdata[$key]["content"] = $result;
		}
	}

	function SaveQueues()
	{
		global $ops, $opsfile, $destpath, $manifest, $manifestfile;

		file_put_contents($manifestfile, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

		$ops2 = array();
		foreach ($ops as $url => $info)
		{
			$info["web_state"] = $info["web"]->GetState();
			unset($info["web"]);

			$ops2[$url] = $info;
		}

		file_put_contents($opsfile, json_encode($ops2, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	}

	// Load the URL mapping manifest and operations files if they exist in order to continue wherever this script left off.
	$manifest = @json_decode(file_get_contents($manifestfile), true);
	if (!is_array($manifest))  $manifest = array();

	$manifestrev = array();
	foreach ($manifest as $key => $val)
	{
		$vals = explode("/", $val);
		$val = array_shift($vals) . "/";
		while (count($vals))
		{
			$val .= array_shift($vals);

			$manifestrev[strtolower($val)] = $val;

			$val .= "/";
		}
	}

	$ops = @json_decode(file_get_contents($opsfile), true);
	if (is_array($ops))
	{
		// Initialize the operations queue.
		foreach ($ops as $url => &$info)
		{
			$key = $url;

			$info["status"] = "download";
			$info["retries"] = 3;
			$info["web"] = new WebBrowser($info["web_state"]);
			$info["web"]->ProcessAsync($helper, $key, NULL, $url, $info["options"]);

			unset($info["web_state"]);
		}

		unset($info);
	}
	else
	{
		// Queue the first operation.
		$ops = array();

		$key = $initurl;

		$ops[$key] = array(
			"type" => "node",
			"status" => "download",
			"depth" => 0,
			"retries" => 3,
			"ext" => false,
			"waiting" => array(),
			"web" => new WebBrowser(),
			"options" => array(
				"pre_retrievewebpage_callback" => "DisplayURL"
			)
		);

		$ops[$key]["web"]->ProcessAsync($helper, $key, NULL, $initurl, $ops[$key]["options"]);

		// Queue 'favicon.ico'.
//		PrepareManifestResourceItem(false, ".ico", HTTP::ConvertRelativeToAbsoluteURL($initurl, "/favicon.ico"));

		// Queue 'robots.txt'.
//		PrepareManifestResourceItem(false, ".txt", HTTP::ConvertRelativeToAbsoluteURL($initurl, "/robots.txt"));

		SaveQueues();
	}

	$opsdata = array();

	// Run the main loop.
	$result = $helper->Wait();
	while ($result["success"])
	{
		// Process finished items.
		foreach ($result["removed"] as $key => $info)
		{
			if (!$info["result"]["success"])
			{
				$ops[$key]["retries"]--;
				if ($ops[$key]["retries"])  $ops[$key]["web"]->ProcessAsync($helper, $key, NULL, $key, $info["tempoptions"]);

				echo "Error retrieving URL (" . $key . ").  " . ($ops[$key]["retries"] > 0 ? "Retrying in a moment.  " : "") . $info["result"]["error"] . " (" . $info["result"]["errorcode"] . ")\n";
			}
			else
			{
				echo "[" . number_format(count($ops), 0) . " ops] Processing '" . $key . "'.\n";

				// Just report non-200 OK responses.  Store the data except for 404 errors.
				if ($info["result"]["response"]["code"] != 200)  echo "Error retrieving URL '" . $info["result"]["url"] . "'.\nServer returned:  " . $info["result"]["response"]["line"] . "\n";

				$opsdata[$key] = array(
					"httpcode" => $info["result"]["response"]["code"],
					"url" => $info["result"]["url"],
					"content" => $info["result"]["body"]
				);

				unset($info["result"]["body"]);

				// Get the final file extension to use.
				if ($ops[$key]["ext"] === false)  $ops[$key]["ext"] = GetResultFileExtension($info["result"]);

				// Calculate the reverse manifest path.
				SetReverseManifestPath($key);

				// Process the incoming content, if relevant.
				ProcessContent($key, false);

				// Walk parents and reduce the number of resources being waited on.
				$process = array();
				if ($ops[$key]["status"] !== "waiting")
				{
					$process[] = $key;

					// Process the content a second time.  This time updating all valid, processed URLs with static URLs.
					ProcessContent($key, true);
				}

				foreach ($ops[$key]["waiting"] as $pkey)
				{
					$ops[$pkey]["wait_refs"]--;

					if ($ops[$pkey]["wait_refs"] <= 0)
					{
						$process[] = $pkey;

						// Process the content a second time.  This time updating all valid, processed URLs with static URLs.
						ProcessContent($pkey, true);
					}
				}

				$ops[$key]["waiting"] = array();

				// Store ready documents to disk.
				while (count($process))
				{
					$key2 = array_shift($process);

					if ($opsdata[$key2]["httpcode"] >= 400)  echo "[" . number_format(count($ops), 0) . " ops] Finalizing '" . $key2 . "'.\n";
					else
					{
						echo "[" . number_format(count($ops), 0) . " ops] Saving '" . $key2 . "' to '" . $destpath . $opsdata[$key2]["path"] . "'.\n";

						$manifest[str_replace(array("http://", "https://"), "//", $key2)] = $opsdata[$key2]["path"];

						// Write data to disk.
						file_put_contents($destpath . $opsdata[$key2]["path"], $opsdata[$key2]["content"]);
					}

					$processedurls[$key2] = true;

					unset($opsdata[$key2]);

					// Walk parents and reduce the number of resources being waited on.
					foreach ($ops[$key2]["waiting"] as $pkey)
					{
						$ops[$pkey]["wait_refs"]--;

						if ($ops[$pkey]["wait_refs"] <= 0)
						{
							$process[] = $pkey;

							// Process the content a second time.  This time updating all valid, processed URLs with static URLs.
							ProcessContent($pkey, true);
						}
					}

					unset($ops[$key2]);
				}
			}
		}

		if (count($result["removed"]))  SaveQueues();

		// Break out of the loop when there is nothing left to do.
		if (!$helper->NumObjects())  break;

		$result = $helper->Wait();
	}

	// Final message.
	if (count($ops))
	{
		echo "Unable to process the following URLs:\n\n";

		foreach ($ops as $url => $info)
		{
			echo "  " . $url . "\n";
		}

		echo "\n";
		echo "Done, with errors.\n";
	}
	else
	{
		echo "Done.\n";
	}
?>