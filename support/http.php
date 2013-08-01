<?php
	// CubicleSoft PHP HTTP functions.
	// (C) 2013 CubicleSoft.  All Rights Reserved.

	// RFC 3986 delimeter splitting implementation.
	function ExtractURL($url)
	{
		$result = array(
			"scheme" => "",
			"authority" => "",
			"login" => "",
			"loginusername" => "",
			"loginpassword" => "",
			"host" => "",
			"port" => "",
			"path" => "",
			"query" => "",
			"queryvars" => array(),
			"fragment" => ""
		);

		$url = str_replace("&amp;", "&", $url);

		$pos = strpos($url, "#");
		if ($pos !== false)
		{
			$result["fragment"] = substr($url, $pos + 1);
			$url = substr($url, 0, $pos);
		}

		$pos = strpos($url, "?");
		if ($pos !== false)
		{
			$result["query"] = str_replace(" ", "+", substr($url, $pos + 1));
			$url = substr($url, 0, $pos);
			$vars = explode("&", $result["query"]);
			foreach ($vars as $var)
			{
				$pos = strpos($var, "=");
				if ($pos === false)
				{
					$name = $var;
					$value = "";
				}
				else
				{
					$name = substr($var, 0, $pos);
					$value = substr($var, $pos + 1);
				}
				if (!isset($result["queryvars"][urldecode($name)]))  $result["queryvars"][urldecode($name)] = array();
				$result["queryvars"][urldecode($name)][] = urldecode($value);
			}
		}

		$url = str_replace("\\", "/", $url);

		$pos = strpos($url, ":");
		$pos2 = strpos($url, "/");
		if ($pos !== false && ($pos2 === false || $pos < $pos2))
		{
			$result["scheme"] = strtolower(substr($url, 0, $pos));
			$url = substr($url, $pos + 1);
		}

		if (substr($url, 0, 2) != "//")  $result["path"] = $url;
		else
		{
			$url = substr($url, 2);
			$pos = strpos($url, "/");
			if ($pos !== false)
			{
				$result["path"] = substr($url, $pos);
				$url = substr($url, 0, $pos);
			}
			$result["authority"] = $url;

			$pos = strpos($url, "@");
			if ($pos !== false)
			{
				$result["login"] = substr($url, 0, $pos);
				$url = substr($url, $pos + 1);
				$pos = strpos($result["login"], ":");
				if ($pos === false)  $result["loginusername"] = urldecode($result["login"]);
				else
				{
					$result["loginusername"] = urldecode(substr($result["login"], 0, $pos));
					$result["loginpassword"] = urldecode(substr($result["login"], $pos + 1));
				}
			}

			$pos = strpos($url, "]");
			if (substr($url, 0, 1) == "[" && $pos !== false)
			{
				// IPv6 literal address.
				$result["host"] = substr($url, 0, $pos + 1);
				$url = substr($url, $pos + 1);

				$pos = strpos($url, ":");
				if ($pos !== false)
				{
					$result["port"] = substr($url, $pos + 1);
					$url = substr($url, 0, $pos);
				}
			}
			else
			{
				// Normal host[:port].
				$pos = strpos($url, ":");
				if ($pos !== false)
				{
					$result["port"] = substr($url, $pos + 1);
					$url = substr($url, 0, $pos);
				}

				$result["host"] = $url;
			}
		}

		return $result;
	}

	// Takes a ExtractURL() array and condenses it into a string.
	function CondenseURL($data)
	{
		$result = "";
		if (isset($data["host"]) && $data["host"] != "")
		{
			if (isset($data["scheme"]) && $data["scheme"] != "")  $result = $data["scheme"] . "://";
			if (isset($data["loginusername"]) && $data["loginusername"] != "" && isset($data["loginpassword"]))  $result .= rawurlencode($data["loginusername"]) . ($data["loginpassword"] != "" ? ":" . rawurlencode($data["loginpassword"]) : "") . "@";
			else if (isset($data["login"]) && $data["login"] != "")  $result .= $data["login"] . "@";

			$result .= $data["host"];
			if (isset($data["port"]) && $data["port"] != "")  $result .= ":" . $data["port"];

			if (isset($data["path"]))
			{
				$data["path"] = str_replace("\\", "/", $data["path"]);
				if (substr($data["path"], 0, 1) != "/")  $data["path"] = "/" . $data["path"];
				$result .= $data["path"];
			}
		}
		else if (isset($data["authority"]) && $data["authority"] != "")
		{
			if (isset($data["scheme"]) && $data["scheme"] != "")  $result = $data["scheme"] . "://";

			$result .= $data["authority"];

			if (isset($data["path"]))
			{
				$data["path"] = str_replace("\\", "/", $data["path"]);
				if (substr($data["path"], 0, 1) != "/")  $data["path"] = "/" . $data["path"];
				$result .= $data["path"];
			}
		}
		else if (isset($data["path"]))
		{
			if (isset($data["scheme"]) && $data["scheme"] != "")  $result = $data["scheme"] . ":";

			$result .= $data["path"];
		}

		if (isset($data["query"]))
		{
			if ($data["query"] != "")  $result .= "?" . $data["query"];
		}
		else if (isset($data["queryvars"]))
		{
			$data["query"] = array();
			foreach ($data["queryvars"] as $key => $vals)
			{
				if (is_string($vals))  $vals = array($vals);
				foreach ($vals as $val)  $data["query"][] = urlencode($key) . "=" . urlencode($val);
			}
			$data["query"] = implode("&", $data["query"]);

			if ($data["query"] != "")  $result .= "?" . $data["query"];
		}

		if (isset($data["fragment"]) && $data["fragment"] != "")  $result .= "#" . $data["fragment"];

		return $result;
	}

	function ConvertRelativeToAbsoluteURL($baseurl, $relativeurl)
	{
		$relative = (is_array($relativeurl) ? $relativeurl : ExtractURL($relativeurl));
		if ($relative["host"] != "")  return CondenseURL($relative);

		$base = (is_array($baseurl) ? $baseurl : ExtractURL($baseurl));
		$result = array(
			"scheme" => $base["scheme"],
			"loginusername" => $base["loginusername"],
			"loginpassword" => $base["loginpassword"],
			"host" => $base["host"],
			"port" => $base["port"],
			"path" => "",
			"query" => $relative["query"],
			"fragment" => $relative["fragment"]
		);

		if ($relative["path"] == "")  $result["path"] = $base["path"];
		else if (substr($relative["path"], 0, 1) == "/")  $result["path"] = $relative["path"];
		else
		{
			$abspath = explode("/", $base["path"]);
			array_pop($abspath);
			$relpath = explode("/", $relative["path"]);
			foreach ($relpath as $piece)
			{
				if ($piece == ".")
				{
				}
				else if ($piece == "..")  array_pop($abspath);
				else  $abspath[] = $piece;
			}

			$abspath = implode("/", $abspath);
			if (substr($abspath, 0, 1) != "/")  $abspath = "/" . $abspath;

			$result["path"] = $abspath;
		}

		return CondenseURL($result);
	}

	function GetWebUserAgent($type)
	{
		$type = strtolower($type);

		if ($type == "ie")  $type = "ie9";

		if ($type == "ie6")  return "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30; .NET CLR 3.0.04506.648; .NET CLR 3.5.21022)";
		else if ($type == "ie7")  return "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 6.0)";
		else if ($type == "ie8")  return "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; SLCC1)";
		else if ($type == "ie9")  return "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)";
		else if ($type == "firefox")  return "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:10.0.2) Gecko/20100101 Firefox/10.0.2";
		else if ($type == "opera")  return "Opera/9.80 (Windows NT 6.1; WOW64; U; en) Presto/2.10.229 Version/11.62";
		else if ($type == "safari")  return "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; en-us) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27";
		else if ($type == "chrome")  return "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.142 Safari/535.19";

		return "";
	}

	// Reasonably parses RFC1123, RFC850, and asctime() dates.
	function GetHTTPDateTimestamp($httpdate)
	{
		$timestamp_map = array(
			"jan" => 1, "feb" => 2, "mar" => 3, "apr" => 4, "may" => 5, "jun" => 6,
			"jul" => 7, "aug" => 8, "sep" => 9, "oct" => 10, "nov" => 11, "dec" => 12
		);

		$year = false;
		$month = false;
		$day = false;
		$hour = false;
		$min = false;
		$sec = false;

		$items = explode(" ", preg_replace('/\s+/', " ", str_replace("-", " ", strtolower($httpdate))));
		foreach ($items as $item)
		{
			if ($item != "")
			{
				if (strpos($item, ":") !== false)
				{
					$item = explode(":", $item);
					$hour = (int)(count($item) > 0 ? array_shift($item) : 0);
					$min = (int)(count($item) > 0 ? array_shift($item) : 0);
					$sec = (int)(count($item) > 0 ? array_shift($item) : 0);

					if ($hour > 23)  $hour = 23;
					if ($min > 59)  $min = 59;
					if ($sec > 59)  $sec = 59;
				}
				else if (is_numeric($item))
				{
					if (strlen($item) >= 4)  $year = (int)$item;
					else if ($day === false)  $day = (int)$item;
					else  $year = substr(date("Y"), 0, 2) . substr($item, -2);
				}
				else
				{
					$item = substr($item, 0, 3);
					if (isset($timestamp_map[$item]))  $month = $timestamp_map[$item];
				}
			}
		}

		if ($year === false || $month === false || $day === false || $hour === false || $min === false || $sec === false)  return false;

		return gmmktime($hour, $min, $sec, $month, $day, $year);
	}

	function HTTPTranslate()
	{
		$args = func_get_args();
		if (!count($args))  return "";

		return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
	}

	function HTTPHeaderNameCleanup($name)
	{
		return preg_replace('/\s+/', "-", ucwords(strtolower(trim(preg_replace('/[^A-Za-z0-9 ]/', " ", $name)))));
	}

	function HTTPHeaderValueCleanup($value)
	{
		return str_replace(array("\r", "\n"), array("", ""), $value);
	}

	function HTTPNormalizeHeaders($headers)
	{
		$result = array();
		foreach ($headers as $name => $val)
		{
			$val = HTTPHeaderValueCleanup($val);
			if ($val != "")  $result[HTTPHeaderNameCleanup($name)] = $val;
		}

		return $result;
	}

	function HTTPProcessSSLOptions(&$options, $key, $host)
	{
		if (isset($options[$key]["auto_cainfo"]))
		{
			unset($options[$key]["auto_cainfo"]);

			$cainfo = ini_get("curl.cainfo");
			if ($cainfo !== false && strlen($cainfo) > 0)  $options[$key]["cafile"] = $cainfo;
			else if (file_exists(str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem"))  $options[$key]["cafile"] = str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem";
		}
		if (isset($options[$key]["auto_cn_match"]))
		{
			unset($options[$key]["auto_cn_match"]);

			if (!isset($options["headers"]["Host"]))  $options[$key]["CN_match"] = $host;
			else
			{
				$info = ExtractURL("http://" . $options["headers"]["Host"]);
				$options[$key]["CN_match"] = $info["host"];
			}
		}
		if (isset($options[$key]["auto_sni"]))
		{
			unset($options[$key]["auto_sni"]);

			$options[$key]["SNI_enabled"] = true;
			if (!isset($options["headers"]["Host"]))  $options[$key]["SNI_server_name"] = $host;
			else
			{
				$info = ExtractURL("http://" . $options["headers"]["Host"]);
				$options[$key]["SNI_server_name"] = $info["host"];
			}
		}
	}

	// Swiped from str_basics.php so this file can be standalone.
	function HTTPExtractFilename($dirfile)
	{
		$dirfile = str_replace("\\", "/", $dirfile);
		$pos = strrpos($dirfile, "/");
		if ($pos !== false)  $dirfile = substr($dirfile, $pos + 1);

		return $dirfile;
	}

	function HTTPFilenameSafe($filename)
	{
		return preg_replace('/[_]+/', "_", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $filename));
	}

	function HTTPGetTimeLeft($start, $limit)
	{
		if ($limit === false)  return false;

		$difftime = microtime(true) - $start;
		if ($difftime >= $limit)  return 0;

		return $limit - $difftime;
	}

	function HTTPRateLimit($size, $start, $limit)
	{
		$difftime = microtime(true) - $start;
		if ($difftime > 0.0)
		{
			if ($size / $difftime > $limit)
			{
				// Sleeping for some amount of time will equalize the rate.
				// So, solve this for $x:  $size / ($x + $difftime) = $limit
				usleep(($size - ($limit * $difftime)) / $limit);
			}
		}
	}

	function HTTPGetDecodedBody(&$autodecode_ds, $body)
	{
		if ($autodecode_ds !== false)
		{
			$autodecode_ds->Write($body);
			$body = $autodecode_ds->Read();
		}

		return $body;
	}

	function HTTPGetResponse($fp, $debug, $options, $startts, $timeout)
	{
		$recvstart = microtime(true);
		$rawdata = $data = "";
		$rawsize = $rawrecvheadersize = 0;

		do
		{
			$autodecode = (!isset($options["auto_decode"]) || $options["auto_decode"]);

			// Process the response line.
			while (strpos($data, "\n") === false && ($data2 = fgets($fp, 116000)) !== false)
			{
				if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

				$rawsize += strlen($data2);
				$data .= $data2;

				if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
				if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
				else if ($debug)  $rawdata .= $data2;

				if (feof($fp))  break;
			}
			$pos = strpos($data, "\n");
			if ($pos === false)  return array("success" => false, "error" => HTTPTranslate("Unable to retrieve response line."), "errorcode" => "get_response_line");
			$line = trim(substr($data, 0, $pos));
			$data = substr($data, $pos + 1);
			$rawrecvheadersize += $pos + 1;
			$response = explode(" ", $line, 3);
			$response = array(
				"line" => $line,
				"httpver" => strtoupper($response[0]),
				"code" => $response[1],
				"meaning" => $response[2]
			);

			// Process the headers.
			$headers = array();
			$lastheader = "";
			do
			{
				while (strpos($data, "\n") === false && ($data2 = fgets($fp, 116000)) !== false)
				{
					if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

					$rawsize += strlen($data2);
					$data .= $data2;

					if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
					if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
					else if ($debug)  $rawdata .= $data2;

					if (feof($fp))  break;
				}
				$pos = strpos($data, "\n");
				if ($pos === false)  $pos = strlen($data);
				$header = rtrim(substr($data, 0, $pos));
				$data = substr($data, $pos + 1);
				$rawrecvheadersize += $pos + 1;
				if ($header != "")
				{
					if ($lastheader != "" && substr($header, 0, 1) == " " || substr($header, 0, 1) == "\t")  $headers[$lastheader][count($headers[$lastheader]) - 1] .= $header;
					else
					{
						$pos = strpos($header, ":");
						if ($pos === false)  $pos = strlen($header);
						$lastheader = HTTPHeaderNameCleanup(substr($header, 0, $pos));
						if (!isset($headers[$lastheader]))  $headers[$lastheader] = array();
						$headers[$lastheader][] = ltrim(substr($header, $pos + 1));
					}
				}
			} while ($header != "");

			if ($response["code"] != 100 && isset($options["read_headers_callback"]))
			{
				if (!$options["read_headers_callback"]($response, $headers, $options["read_headers_callback_opts"]))  return array("success" => false, "error" => HTTPTranslate("Read headers callback returned with a failure condition."), "errorcode" => "read_header_callback");
			}

			// Determine if decoding the content is possible and necessary.
			if ($autodecode && !isset($headers["Content-Encoding"]) || (strtolower($headers["Content-Encoding"][0]) != "gzip" && strtolower($headers["Content-Encoding"][0]) != "deflate"))  $autodecode = false;
			if (!$autodecode)  $autodecode_ds = false;
			else
			{
				require_once str_replace("\\", "/", dirname(__FILE__)) . "/deflate_stream.php";

				// Since servers and browsers do everything wrong, ignore the encoding claim and attempt to auto-detect the encoding.
				$autodecode_ds = new DeflateStream();
				$autodecode_ds->Init("rb", -1, array("type" => "auto"));
			}

			// Process the body.
			$body = "";
			if (isset($headers["Transfer-Encoding"]) && strtolower($headers["Transfer-Encoding"][0]) == "chunked")
			{
				do
				{
					// Calculate the next chunked size and ignore chunked extensions.
					while (strpos($data, "\n") === false && ($data2 = fgets($fp, 116000)) !== false)
					{
						if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

						$rawsize += strlen($data2);
						$data .= $data2;

						if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
						if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
						else if ($debug)  $rawdata .= $data2;

						if (feof($fp))  break;
					}
					$pos = strpos($data, "\n");
					if ($pos === false)  $pos = strlen($data);
					$line = trim(substr($data, 0, $pos));
					$data = substr($data, $pos + 1);
					$pos = strpos($line, ";");
					if ($pos === false)  $pos = strlen($line);
					$size = hexdec(substr($line, 0, $pos));
					if ($size < 0)  $size = 0;

					// Retrieve content.
					$size2 = $size;
					$size3 = min(strlen($data), $size);
					if ($size3 > 0)
					{
						$data2 = substr($data, 0, $size3);
						$data = substr($data, $size3);
						$size2 -= $size3;

						if ($response["code"] == 100 || !isset($options["read_body_callback"]))  $body .= HTTPGetDecodedBody($autodecode_ds, $data2);
						else if (!$options["read_body_callback"]($response, HTTPGetDecodedBody($autodecode_ds, $data2), $options["read_body_callback_opts"]))  return array("success" => false, "error" => HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback");
					}
					while ($size2 > 0 && ($data2 = fread($fp, ($size2 > 65536 ? 65536 : $size2))) !== false)
					{
						if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

						$tempsize = strlen($data2);
						$rawsize += $tempsize;
						$size2 -= $tempsize;

						if ($response["code"] == 100 || !isset($options["read_body_callback"]))  $body .= HTTPGetDecodedBody($autodecode_ds, $data2);
						else if (!$options["read_body_callback"]($response, HTTPGetDecodedBody($autodecode_ds, $data2), $options["read_body_callback_opts"]))  return array("success" => false, "error" => HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback");

						if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
						if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
						else if ($debug)  $rawdata .= $data2;

						if (feof($fp))  break;
					}

					// Ignore one newline.
					while (strpos($data, "\n") === false && ($data2 = fgets($fp, 116000)) !== false)
					{
						if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

						$rawsize += strlen($data2);
						$data .= $data2;

						if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
						if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
						else if ($debug)  $rawdata .= $data2;

						if (feof($fp))  break;
					}
					$pos = strpos($data, "\n");
					if ($pos === false)  $pos = strlen($data);
					$data = substr($data, $pos + 1);
				} while ($size);

				// Process additional headers.
				$lastheader = "";
				do
				{
					while (strpos($data, "\n") === false && ($data2 = fgets($fp, 116000)) !== false)
					{
						if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

						$rawsize += strlen($data2);
						$data .= $data2;

						if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
						if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
						else if ($debug)  $rawdata .= $data2;

						if (feof($fp))  break;
					}
					$pos = strpos($data, "\n");
					if ($pos === false)  $pos = strlen($data);
					$header = rtrim(substr($data, 0, $pos));
					$data = substr($data, $pos + 1);
					$rawrecvheadersize += $pos + 1;
					if ($header != "")
					{
						if ($lastheader != "" && (substr($header, 0, 1) == " " || substr($header, 0, 1) == "\t"))  $headers[$lastheader][count($headers[$lastheader]) - 1] .= $header;
						else
						{
							$pos = strpos($header, ":");
							if ($pos === false)  $pos = strlen($header);
							$lastheader = HTTPHeaderNameCleanup(substr($header, 0, $pos));
							if (!isset($headers[$lastheader]))  $headers[$lastheader] = array();
							$headers[$lastheader][] = ltrim(substr($header, $pos + 1));
						}
					}
				} while ($header != "");

				if ($response["code"] != 100 && isset($options["read_headers_callback"]))
				{
					if (!$options["read_headers_callback"]($response, $headers, $options["read_headers_callback_opts"]))  return array("success" => false, "error" => HTTPTranslate("Read headers callback returned with a failure condition."), "errorcode" => "read_header_callback");
				}
			}
			else if (isset($headers["Content-Length"]))
			{
				$size = (int)$headers["Content-Length"][0];
				$datasize = 0;
				while ($datasize < $size && ($data2 = fread($fp, ($size > 65536 ? 65536 : $size))) !== false)
				{
					if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

					$tempsize = strlen($data2);
					$datasize += $tempsize;
					$rawsize += $tempsize;
					if ($response["code"] == 100 || !isset($options["read_body_callback"]))  $body .= HTTPGetDecodedBody($autodecode_ds, $data2);
					else if (!$options["read_body_callback"]($response, HTTPGetDecodedBody($autodecode_ds, $data2), $options["read_body_callback_opts"]))  return array("success" => false, "error" => HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback");

					if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
					if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
					else if ($debug)  $rawdata .= $data2;

					if (feof($fp))  break;
				}
			}
			else if ($response["code"] != 100)
			{
				while (($data2 = fread($fp, 65536)) !== false)
				{
					if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

					$tempsize = strlen($data2);
					$rawsize += $tempsize;
					if ($response["code"] == 100 || !isset($options["read_body_callback"]))  $body .= HTTPGetDecodedBody($autodecode_ds, $data2);
					else if (!$options["read_body_callback"]($response, HTTPGetDecodedBody($autodecode_ds, $data2), $options["read_body_callback_opts"]))  return array("success" => false, "error" => HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback");

					if (isset($options["recvratelimit"]))  HTTPRateLimit($rawsize, $recvstart, $options["recvratelimit"]);
					if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $data2, $options["debug_callback_opts"]);
					else if ($debug)  $rawdata .= $data2;

					if (feof($fp))  break;
				}
			}

			if ($autodecode_ds !== false)
			{
				$autodecode_ds->Finalize();
				$data2 = $autodecode_ds->Read();

				if ($response["code"] == 100 || !isset($options["read_body_callback"]))  $body .= $data2;
				else if (!$options["read_body_callback"]($response, $data2, $options["read_body_callback_opts"]))  return array("success" => false, "error" => HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback");
			}
		} while ($response["code"] == 100);

		return array("success" => true, "rawrecv" => $rawdata, "rawrecvsize" => $rawsize, "rawrecvheadersize" => $rawrecvheadersize, "recvstart" => $recvstart, "response" => $response, "headers" => $headers, "body" => $body);
	}

	function RetrieveWebpage($url, $options = array())
	{
		$startts = microtime(true);
		$timeout = (isset($options["timeout"]) ? $options["timeout"] : false);

		if (!function_exists("stream_socket_client") && !function_exists("fsockopen"))  return array("success" => false, "error" => HTTPTranslate("The functions 'stream_socket_client' and 'fsockopen' do not exist."), "errorcode" => "function_check");

		// Process the URL.
		$url = trim($url);
		$url = ExtractURL($url);

		if ($url["scheme"] != "http" && $url["scheme"] != "https")  return array("success" => false, "error" => HTTPTranslate("RetrieveWebpage() only supports the 'http' and 'https' protocols."), "errorcode" => "protocol_check");

		$secure = ($url["scheme"] == "https");
		$protocol = ($secure ? (isset($options["protocol"]) && strtolower($options["protocol"]) == "ssl" ? "ssl" : "tls") : "tcp");
		if (function_exists("stream_get_transports") && !in_array($protocol, stream_get_transports()))  return array("success" => false, "error" => HTTPTranslate("The desired transport protocol '%s' is not installed.", $protocol), "errorcode" => "transport_not_installed");
		$host = str_replace(" ", "-", HTTPHeaderValueCleanup($url["host"]));
		if ($host == "")  return array("success" => false, "error" => HTTPTranslate("Invalid URL."));
		$port = ((int)$url["port"] ? (int)$url["port"] : ($secure ? 443 : 80));
		$defaultport = ((!$secure && $port == 80) || ($secure && $port == 443));
		$path = ($url["path"] == "" ? "/" : $url["path"]);
		$query = $url["query"];
		$username = $url["loginusername"];
		$password = $url["loginpassword"];

		// Cleanup input headers.
		if (!isset($options["headers"]))  $options["headers"] = array();
		$options["headers"] = HTTPNormalizeHeaders($options["headers"]);

		// Process the proxy URL (if specified).
		$useproxy = (isset($options["proxyurl"]) && trim($options["proxyurl"]) != "");
		if ($useproxy)
		{
			$proxyurl = trim($options["proxyurl"]);
			$proxyurl = ExtractURL($proxyurl);

			$proxysecure = ($proxyurl["scheme"] == "https");
			$proxyprotocol = ($proxysecure ? (isset($options["proxyprotocol"]) && strtolower($options["proxyprotocol"]) == "ssl" ? "ssl" : "tls") : "tcp");
			if (function_exists("stream_get_transports") && !in_array($proxyprotocol, stream_get_transports()))  return array("success" => false, "error" => HTTPTranslate("The desired transport proxy protocol '%s' is not installed.", $proxyprotocol), "errorcode" => "proxy_transport_not_installed");
			$proxyhost = str_replace(" ", "-", HTTPHeaderValueCleanup($proxyurl["host"]));
			$proxyport = ((int)$proxyurl["port"] ? (int)$proxyurl["port"] : ($proxysecure ? 443 : 80));
			$proxypath = ($proxyurl["path"] == "" ? "/" : $proxyurl["path"]);
			$proxyusername = $proxyurl["loginusername"];
			$proxypassword = $proxyurl["loginpassword"];

			// Open a tunnel instead of letting the proxy modify the request (HTTP CONNECT).
			$proxyconnect = (isset($options["proxyconnect"]) && $options["proxyconnect"] ? $options["proxyconnect"] : false);
			if ($proxyconnect)
			{
				$proxydata = "CONNECT " . $host . ":" . $port . " HTTP/1.1\r\n";
				if (isset($options["headers"]["User-Agent"]))  $data .= "User-Agent: " . $options["headers"]["User-Agent"] . "\r\n";
				$proxydata .= "Host: " . $host . ($defaultport ? "" : ":" . $port) . "\r\n";
				$proxydata .= "Proxy-Connection: keep-alive\r\n";
				if ($proxyusername != "")  $proxydata .= "Proxy-Authorization: BASIC " . base64_encode($proxyusername . ":" . $proxypassword) . "\r\n";
				if (isset($options["proxyheaders"]))
				{
					$options["proxyheaders"] = HTTPNormalizeHeaders($options["proxyheaders"]);
					unset($options["proxyheaders"]["Accept-Encoding"]);
					foreach ($options["proxyheaders"] as $name => $val)
					{
						if ($name != "Content-Type" && $name != "Content-Length" && $name != "Proxy-Connection" && $name != "Host")  $proxydata .= $name . ": " . $val . "\r\n";
					}
				}
				$proxydata .= "\r\n";
				if (isset($options["debug_callback"]))  $options["debug_callback"]("rawproxyheaders", $proxydata, $options["debug_callback_opts"]);
			}
		}

		// Process the method.
		if (!isset($options["method"]))
		{
			if (isset($options["write_body_callback"]) || isset($options["body"]))  $options["method"] = "PUT";
			else if (isset($options["postvars"]) || (isset($options["files"]) && count($options["files"])))  $options["method"] = "POST";
			else  $options["method"] = "GET";
		}
		$options["method"] = preg_replace('/[^A-Z]/', "", strtoupper($options["method"]));

		// Process the HTTP version.
		if (!isset($options["httpver"]))  $options["httpver"] = "1.1";
		$options["httpver"] = preg_replace('/[^0-9.]/', "", $options["httpver"]);

		// Process the request.
		$data = $options["method"] . " ";
		$data .= ($useproxy && !$proxyconnect ? $url["scheme"] . "://" . $host . ":" . $port : "") . $path . ($query != "" ? "?" . $query : "");
		$data .= " HTTP/" . $options["httpver"] . "\r\n";

		// Process the headers.
		if ($useproxy && !$proxyconnect && $proxyusername != "")  $data .= "Proxy-Authorization: BASIC " . base64_encode($proxyusername . ":" . $proxypassword) . "\r\n";
		if ($username != "")  $data .= "Authorization: BASIC " . base64_encode($username . ":" . $password) . "\r\n";
		$ver = explode(".", $options["httpver"]);
		if ((int)$ver[0] > 1 || ((int)$ver[0] == 1 && (int)$ver[1] >= 1))
		{
			if (!isset($options["headers"]["Host"]))  $options["headers"]["Host"] = $host . ($defaultport ? "" : ":" . $port);
			$data .= "Host: " . $options["headers"]["Host"] . "\r\n";
		}

		$data .= "Connection: close\r\n";

		if (isset($options["headers"]))
		{
			foreach ($options["headers"] as $name => $val)
			{
				if ($name != "Content-Type" && $name != "Content-Length" && $name != "Connection" && $name != "Host")  $data .= $name . ": " . $val . "\r\n";
			}
		}

		// Process the body.
		$body = "";
		$bodysize = 0;
		if (isset($options["write_body_callback"]))  $options["write_body_callback"]($body, $bodysize, $options["write_body_callback_opts"]);
		else if (isset($options["body"]))
		{
			if (isset($options["headers"]["Content-Type"]))  $data .= "Content-Type: " . $options["headers"]["Content-Type"] . "\r\n";

			$body = $options["body"];
			$bodysize = strlen($body);
			unset($options["body"]);
		}
		else if (isset($options["files"]) && count($options["files"]))
		{
			$mime = "--------" . substr(sha1(uniqid(mt_rand(), true)), 0, 25);
			$data .= "Content-Type: multipart/form-data; boundary=" . $mime . "\r\n";
			if (isset($options["postvars"]))
			{
				foreach ($options["postvars"] as $name => $val)
				{
					$name = HTTPHeaderValueCleanup($name);
					$name = str_replace("\"", "", $name);

					if (is_string($val) || is_numeric($val))  $val = array($val);
					foreach ($val as $val2)
					{
						$body .= "--" . $mime . "\r\n";
						$body .= "Content-Disposition: form-data; name=\"" . $name . "\"\r\n";
						$body .= "\r\n";
						$body .= $val2 . "\r\n";
					}
				}

				unset($options["postvars"]);
			}

			$bodysize = strlen($body);

			// Only count the amount of data to send.
			foreach ($options["files"] as $num => $info)
			{
				$name = HTTPHeaderValueCleanup($info["name"]);
				$name = str_replace("\"", "", $name);
				$filename = HTTPFilenameSafe(HTTPExtractFilename($info["filename"]));
				$type = HTTPHeaderValueCleanup($info["type"]);

				$body2 = "--" . $mime . "\r\n";
				$body2 .= "Content-Disposition: form-data; name=\"" . $name . "\"; filename=\"" . $filename . "\"\r\n";
				$body2 .= "Content-Type: " . $type . "\r\n";
				$body2 .= "\r\n";

				$info["filesize"] = (isset($info["datafile"]) ? filesize($info["datafile"]) : strlen($info["data"]));
				$bodysize += strlen($body2) + $info["filesize"] + 2;

				$options["files"][$num] = $info;
			}

			$body2 = "--" . $mime . "--\r\n";
			$bodysize += strlen($body2);
		}
		else
		{
			if (isset($options["postvars"]))
			{
				foreach ($options["postvars"] as $name => $val)
				{
					$name = HTTPHeaderValueCleanup($name);

					if (is_string($val) || is_numeric($val))  $val = array($val);
					foreach ($val as $val2)  $body .= ($body != "" ? "&" : "") . urlencode($name) . "=" . urlencode($val2);
				}

				unset($options["postvars"]);
			}

			if ($body != "")  $data .= "Content-Type: application/x-www-form-urlencoded\r\n";

			$bodysize = strlen($body);
		}
		if ($bodysize < strlen($body))  $bodysize = strlen($body);

		// Finalize the headers.
		if ($bodysize || $body != "" || $options["method"] == "POST")  $data .= "Content-Length: " . $bodysize . "\r\n";
		$data .= "\r\n";
		if (isset($options["debug_callback"]))  $options["debug_callback"]("rawheaders", $data, $options["debug_callback_opts"]);

		// Finalize the initial data to be sent.
		$data .= $body;
		$bodysize -= strlen($body);
		$body = "";
		$result = array("success" => true, "rawsendsize" => 0, "rawrecvsize" => 0, "rawrecvheadersize" => 0, "startts" => $startts);
		$debug = (isset($options["debug"]) && $options["debug"]);
		if ($debug)
		{
			$result["rawsend"] = "";
			$result["rawrecv"] = "";
		}

		if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
		{
			fclose($fp);

			return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
		}

		// Connect to the target server.
		$errornum = 0;
		$errorstr = "";
		if ($useproxy)
		{
			if (!isset($options["proxyconnecttimeout"]))  $options["proxyconnecttimeout"] = 10;
			$timeleft = HTTPGetTimeLeft($startts, $timeout);
			if ($timeleft !== false)  $options["proxyconnecttimeout"] = min($options["proxyconnecttimeout"], $timeleft);
			if (!function_exists("stream_socket_client"))  $fp = @fsockopen($proxyprotocol . "://" . $proxyhost, $proxyport, $errornum, $errorstr, $options["proxyconnecttimeout"]);
			else
			{
				$context = @stream_context_create();
				if ($proxysecure && isset($options["proxysslopts"]) && is_array($options["proxysslopts"]))
				{
					HTTPProcessSSLOptions($options, "proxysslopts", $host);
					foreach ($options["proxysslopts"] as $key => $val)  @stream_context_set_option($context, "ssl", $key, $val);
				}
				$fp = @stream_socket_client($proxyprotocol . "://" . $host . ":" . $port, $errornum, $errorstr, $options["proxyconnecttimeout"], STREAM_CLIENT_CONNECT, $context);

				$contextopts = stream_context_get_options($context);
				if ($proxysecure && isset($options["proxysslopts"]) && is_array($options["proxysslopts"]) && ($protocol == "ssl" || $protocol == "tls") && isset($contextopts["ssl"]["peer_certificate"]))
				{
					if (isset($options["debug_callback"]))  $options["debug_callback"]("proxypeercert", @openssl_x509_parse($contextopts["ssl"]["peer_certificate"]), $options["debug_callback_opts"]);
				}
			}
			if ($fp === false)  return array("success" => false, "error" => HTTPTranslate("Unable to establish a connection to '%s'.", ($proxysecure ? $proxyprotocol . "://" : "") . $proxyhost . ":" . $proxyport), "info" => $errorstr . " (" . $errornum . ")", "errorcode" => "proxy_connect");

			$result["connected"] = microtime(true);

			if ($proxyconnect)
			{
				// Send the HTTP CONNECT request.
				fwrite($fp, $proxydata);
				if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
				{
					fclose($fp);

					return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
				}
				$result["rawsendsize"] += strlen($proxydata);
				$result["rawsendproxyheadersize"] = strlen($proxydata);
				if (isset($options["sendratelimit"]))  HTTPRateLimit($result["rawsendsize"], $result["sendstart"], $options["sendratelimit"]);
				if (isset($options["debug_callback"]))  $options["debug_callback"]("rawsend", $proxydata, $options["debug_callback_opts"]);
				else if ($debug)  $result["rawsend"] .= $proxydata;

				// Get the response - success is a 2xx code.
				$options2 = array();
				if (isset($options["recvratelimit"]))  $options2["recvratelimit"] = $options["recvratelimit"];
				if (isset($options["debug_callback"]))
				{
					$options2["debug_callback"] = $options["debug_callback"];
					$options2["debug_callback_opts"] = $options["debug_callback_opts"];
				}
				$info = HTTPGetResponse($fp, $debug, $options2, $startts, $timeout);
				if (!$info["success"])
				{
					fclose($fp);

					return $info;
				}
				if (substr($info["response"]["code"], 0, 1) != "2")
				{
					fclose($fp);

					return array("success" => false, "error" => HTTPTranslate("Expected a 200 response from the CONNECT request.  Received:  %s.", $info["response"]["line"]), "info" => $info, "errorcode" => "proxy_connect_tunnel");
				}

				$result["rawrecvsize"] += $info["rawrecvsize"];
				$result["rawrecvheadersize"] += $info["rawrecvheadersize"];
				if (isset($options["debug_callback"]))  $options["debug_callback"]("rawrecv", $info["rawrecv"], $options["debug_callback_opts"]);
				else if ($debug)  $result["rawrecv"] .= $info["rawrecv"];
			}
		}
		else
		{
			if (!isset($options["connecttimeout"]))  $options["connecttimeout"] = 10;
			$timeleft = HTTPGetTimeLeft($startts, $timeout);
			if ($timeleft !== false)  $options["connecttimeout"] = min($options["connecttimeout"], $timeleft);
			if (!function_exists("stream_socket_client"))  $fp = @fsockopen($protocol . "://" . $host, $port, $errornum, $errorstr, $options["connecttimeout"]);
			else
			{
				$context = @stream_context_create();
				if ($secure && isset($options["sslopts"]) && is_array($options["sslopts"]) && ($protocol == "ssl" || $protocol == "tls"))
				{
					HTTPProcessSSLOptions($options, "sslopts", $host);
					foreach ($options["sslopts"] as $key => $val)  @stream_context_set_option($context, "ssl", $key, $val);
				}
				$fp = @stream_socket_client($protocol . "://" . $host . ":" . $port, $errornum, $errorstr, $options["connecttimeout"], STREAM_CLIENT_CONNECT, $context);

				$contextopts = stream_context_get_options($context);
				if ($secure && isset($options["sslopts"]) && is_array($options["sslopts"]) && ($protocol == "ssl" || $protocol == "tls") && isset($contextopts["ssl"]["peer_certificate"]))
				{
					if (isset($options["debug_callback"]))  $options["debug_callback"]("peercert", @openssl_x509_parse($contextopts["ssl"]["peer_certificate"]), $options["debug_callback_opts"]);
				}
			}
			if ($fp === false)  return array("success" => false, "error" => HTTPTranslate("Unable to establish a connection to '%s'.", ($secure ? $protocol . "://" : "") . $host . ":" . $port), "info" => $errorstr . " (" . $errornum . ")", "errorcode" => "connect_failed");

			$result["connected"] = microtime(true);
		}

		// Send the initial data.
		$result["sendstart"] = microtime(true);
		fwrite($fp, $data);
		if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
		{
			fclose($fp);

			return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
		}
		$result["rawsendsize"] += strlen($data);
		$result["rawsendheadersize"] = strlen($data);
		if (isset($options["sendratelimit"]))  HTTPRateLimit($result["rawsendsize"], $result["sendstart"], $options["sendratelimit"]);
		if (isset($options["debug_callback"]))  $options["debug_callback"]("rawsend", $data, $options["debug_callback_opts"]);
		else if ($debug)  $result["rawsend"] .= $data;

		// Send extra data.
		if (isset($options["write_body_callback"]))
		{
			while ($bodysize > 0)
			{
				$bodysize2 = $bodysize;
				if (!$options["write_body_callback"]($body, $bodysize2, $options["write_body_callback_opts"]) || strlen($body) > $bodysize)
				{
					fclose($fp);
					return array("success" => false, "error" => HTTPTranslate("HTTP write body callback function failed."), "errorcode" => "write_body_callback");
				}

				fwrite($fp, $body);
				if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
				{
					fclose($fp);

					return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
				}
				$result["rawsendsize"] += strlen($body);
				if (isset($options["sendratelimit"]))  HTTPRateLimit($result["rawsendsize"], $result["sendstart"], $options["sendratelimit"]);
				if (isset($options["debug_callback"]))  $options["debug_callback"]("rawsend", $body, $options["debug_callback_opts"]);
				else if ($debug)  $result["rawsend"] .= $body;

				$bodysize -= strlen($body);
				$body = "";
			}

			unset($options["write_body_callback"]);
			unset($options["write_body_callback_opts"]);
		}
		else if (isset($options["files"]) && count($options["files"]))
		{
			foreach ($options["files"] as $info)
			{
				$name = HTTPHeaderValueCleanup($info["name"]);
				$name = str_replace("\"", "", $name);
				$filename = HTTPFilenameSafe(HTTPExtractFilename($info["filename"]));
				$type = HTTPHeaderValueCleanup($info["type"]);

				$body = "--" . $mime . "\r\n";
				$body .= "Content-Disposition: form-data; name=\"" . $name . "\"; filename=\"" . $filename . "\"\r\n";
				$body .= "Content-Type: " . $type . "\r\n";
				$body .= "\r\n";

				fwrite($fp, $body);
				if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
				{
					fclose($fp);

					return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
				}
				$result["rawsendsize"] += strlen($body);
				if (isset($options["sendratelimit"]))  HTTPRateLimit($result["rawsendsize"], $result["sendstart"], $options["sendratelimit"]);
				if (isset($options["debug_callback"]))  $options["debug_callback"]("rawsend", $body, $options["debug_callback_opts"]);
				else if ($debug)  $result["rawsend"] .= $body;

				if (isset($info["datafile"]))
				{
					$fp2 = @fopen($info["datafile"], "rb");
					if ($fp2 === false)
					{
						fclose($fp);
						return array("success" => false, "error" => HTTPTranslate("The file '%s' does not exist.", $info["datafile"]), "errorcode" => "file_open");
					}

					// Read/Write 65K at a time.
					while ($info["filesize"] >= 65536)
					{
						$body = fread($fp2, 65536);
						if ($body === false || strlen($body) != 65536)
						{
							fclose($fp2);
							fclose($fp);
							return array("success" => false, "error" => HTTPTranslate("A read error was encountered with the file '%s'.", $info["datafile"]), "errorcode" => "file_read");
						}

						fwrite($fp, $body);
						if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
						{
							fclose($fp);

							return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
						}
						$result["rawsendsize"] += strlen($body);
						if (isset($options["sendratelimit"]))  HTTPRateLimit($result["rawsendsize"], $result["sendstart"], $options["sendratelimit"]);
						if (isset($options["debug_callback"]))  $options["debug_callback"]("rawsend", $body, $options["debug_callback_opts"]);
						else if ($debug)  $result["rawsend"] .= $body;

						$info["filesize"] -= 65536;
					}

					if ($info["filesize"] > 0)
					{
						$body = fread($fp2, $info["filesize"]);
						if ($body === false || strlen($body) != $info["filesize"])
						{
							fclose($fp2);
							fclose($fp);
							return array("success" => false, "error" => HTTPTranslate("A read error was encountered with the file '%s'.", $info["datafile"]), "errorcode" => "file_read");
						}
					}
					else
					{
						$body = "";
					}

					fclose($fp2);
				}
				else
				{
					$body = $info["data"];
				}

				$body .= "\r\n";
				fwrite($fp, $body);
				if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
				{
					fclose($fp);

					return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
				}
				$result["rawsendsize"] += strlen($body);
				if (isset($options["sendratelimit"]))  HTTPRateLimit($result["rawsendsize"], $result["sendstart"], $options["sendratelimit"]);
				if (isset($options["debug_callback"]))  $options["debug_callback"]("rawsend", $body, $options["debug_callback_opts"]);
				else if ($debug)  $result["rawsend"] .= $body;
			}

			$body = "--" . $mime . "--\r\n";
			fwrite($fp, $body);
			if ($timeout !== false && HTTPGetTimeLeft($startts, $timeout) == 0)
			{
				fclose($fp);

				return array("success" => false, "error" => HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
			}
			$result["rawsendsize"] += strlen($body);
			if (isset($options["sendratelimit"]))  HTTPRateLimit($result["rawsendsize"], $result["sendstart"], $options["sendratelimit"]);
			if (isset($options["debug_callback"]))  $options["debug_callback"]("rawsend", $body, $options["debug_callback_opts"]);
			else if ($debug)  $result["rawsend"] .= $body;

			unset($options["files"]);
		}
		else if ($bodysize > 0)
		{
			fclose($fp);
			return array("success" => false, "error" => HTTPTranslate("A weird internal HTTP error that should never, ever happen...just happened."), "errorcode" => "impossible");
		}

		// Get the response.
		$info = HTTPGetResponse($fp, $debug, $options, $startts, $timeout);
		fclose($fp);
		$info["rawsendsize"] = $result["rawsendsize"];
		if (!$info["success"])  return $info;

		$result["rawrecvsize"] += $info["rawrecvsize"];
		$result["rawrecvheadersize"] += $info["rawrecvheadersize"];
		if ($debug)  $result["rawrecv"] .= $info["rawrecv"];
		$result["recvstart"] = $info["recvstart"];

		$result["response"] = $info["response"];
		$result["headers"] = $info["headers"];
		$result["body"] = (string)$info["body"];
		$result["endts"] = microtime(true);

		return $result;
	}
?>