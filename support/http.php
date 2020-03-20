<?php
	// CubicleSoft PHP HTTP class.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class HTTP
	{
		// RFC 3986 delimeter splitting implementation.
		public static function ExtractURL($url)
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
						$value = urldecode(substr($var, $pos + 1));
					}
					$name = urldecode($name);
					if (!isset($result["queryvars"][$name]))  $result["queryvars"][$name] = array();
					$result["queryvars"][$name][] = $value;
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
		public static function CondenseURL($data)
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

		public static function ConvertRelativeToAbsoluteURL($baseurl, $relativeurl)
		{
			$relative = (is_array($relativeurl) ? $relativeurl : self::ExtractURL($relativeurl));
			$base = (is_array($baseurl) ? $baseurl : self::ExtractURL($baseurl));

			if ($relative["host"] != "" || ($relative["scheme"] != "" && $relative["scheme"] != $base["scheme"]))
			{
				if ($relative["scheme"] == "")  $relative["scheme"] = $base["scheme"];

				return self::CondenseURL($relative);
			}

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

			return self::CondenseURL($result);
		}

		public static function GetUserAgent($type)
		{
			$type = strtolower($type);

			if ($type == "ie")  $type = "ie11";

			if ($type == "ie6")  return "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30; .NET CLR 3.0.04506.648; .NET CLR 3.5.21022)";
			else if ($type == "ie7")  return "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 6.0)";
			else if ($type == "ie8")  return "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; SLCC1)";
			else if ($type == "ie9")  return "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)";
			else if ($type == "ie10")  return "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)";
			else if ($type == "ie11")  return "Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko";
			else if ($type == "firefox")  return "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0";
			else if ($type == "opera")  return "Opera/9.80 (Windows NT 6.1; WOW64) Presto/2.12.388 Version/12.16";
			else if ($type == "safari")  return "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/537.13+ (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2";
			else if ($type == "chrome")  return "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.146 Safari/537.36";

			return "";
		}

		public static function GetSSLCiphers($type = "intermediate")
		{
			$type = strtolower($type);

			// Cipher list last updated May 3, 2017.
			if ($type == "modern")  return "ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256";
			else if ($type == "old")  return "ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:ECDHE-RSA-DES-CBC3-SHA:ECDHE-ECDSA-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:AES:DES-CBC3-SHA:HIGH:SEED:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!RSAPSK:!aDH:!aECDH:!EDH-DSS-DES-CBC3-SHA:!KRB5-DES-CBC3-SHA:!SRP";

			return "ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA:ECDHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-RSA-AES256-SHA256:DHE-RSA-AES256-SHA:ECDHE-ECDSA-DES-CBC3-SHA:ECDHE-RSA-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:DES-CBC3-SHA:!DSS";
		}

		public static function GetSafeSSLOpts($cafile = true, $cipherstype = "intermediate")
		{
			// Result array last updated Feb 15, 2020.
			$result = array(
				"ciphers" => self::GetSSLCiphers($cipherstype),
				"disable_compression" => true,
				"allow_self_signed" => false,
				"verify_peer" => true,
				"verify_depth" => 5,
				"SNI_enabled" => true
			);

			if ($cafile === true)  $result["auto_cainfo"] = true;
			else if ($cafile !== false)  $result["cafile"] = $cafile;

			return $result;
		}

		// Reasonably parses RFC1123, RFC850, and asctime() dates.
		public static function GetDateTimestamp($httpdate)
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

		public static function HTTPTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}

		public static function HeaderNameCleanup($name)
		{
			return preg_replace('/\s+/', "-", ucwords(strtolower(trim(preg_replace('/[^A-Za-z0-9 ]/', " ", $name)))));
		}

		private static function HeaderValueCleanup($value)
		{
			return str_replace(array("\r", "\n"), array("", ""), $value);
		}

		public static function NormalizeHeaders($headers)
		{
			$result = array();
			foreach ($headers as $name => $val)
			{
				$val = self::HeaderValueCleanup($val);
				if ($val != "")  $result[self::HeaderNameCleanup($name)] = $val;
			}

			return $result;
		}

		public static function MergeRawHeaders(&$headers, $rawheaders)
		{
			foreach ($rawheaders as $name => $val)
			{
				$val = self::HeaderValueCleanup($val);
				if ($val != "")
				{
					$name2 = self::HeaderNameCleanup($name);
					if (isset($headers[$name2]))  unset($headers[$name2]);

					$headers[$name] = $val;
				}
			}
		}

		public static function ExtractHeader($data)
		{
			$result = array();
			$data = trim($data);
			while ($data != "")
			{
				// Extract name/value pair.
				$pos = strpos($data, "=");
				$pos2 = strpos($data, ";");
				if (($pos !== false && $pos2 === false) || ($pos !== false && $pos2 !== false && $pos < $pos2))
				{
					$name = trim(substr($data, 0, $pos));
					$data = trim(substr($data, $pos + 1));
					if (ord($data[0]) == ord("\""))
					{
						$pos = strpos($data, "\"", 1);
						if ($pos !== false)
						{
							$value = substr($data, 1, $pos - 1);
							$data = trim(substr($data, $pos + 1));
							$pos = strpos($data, ";");
							if ($pos !== false)  $data = substr($data, $pos + 1);
							else  $data = "";
						}
						else
						{
							$value = $data;
							$data = "";
						}
					}
					else
					{
						$pos = strpos($data, ";");
						if ($pos !== false)
						{
							$value = trim(substr($data, 0, $pos));
							$data = substr($data, $pos + 1);
						}
						else
						{
							$value = $data;
							$data = "";
						}
					}
				}
				else if ($pos2 !== false)
				{
					$name = "";
					$value = trim(substr($data, 0, $pos2));
					$data = substr($data, $pos2 + 1);
				}
				else
				{
					$name = "";
					$value = $data;
					$data = "";
				}

				if ($name != "" || $value != "")  $result[strtolower($name)] = $value;

				$data = trim($data);
			}

			return $result;
		}

		private static function ProcessSSLOptions(&$options, $key, $host)
		{
			if (isset($options[$key]["auto_cainfo"]))
			{
				unset($options[$key]["auto_cainfo"]);

				$cainfo = ini_get("curl.cainfo");
				if ($cainfo !== false && strlen($cainfo) > 0)  $options[$key]["cafile"] = $cainfo;
				else if (file_exists(str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem"))  $options[$key]["cafile"] = str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem";
			}

			if (isset($options[$key]["auto_peer_name"]))
			{
				unset($options[$key]["auto_peer_name"]);

				if (!isset($options["headers"]["Host"]))  $options[$key]["peer_name"] = $host;
				else
				{
					$info = self::ExtractURL("https://" . $options["headers"]["Host"]);
					$options[$key]["peer_name"] = $info["host"];
				}
			}

			if (isset($options[$key]["auto_cn_match"]))
			{
				unset($options[$key]["auto_cn_match"]);

				if (!isset($options["headers"]["Host"]))  $options[$key]["CN_match"] = $host;
				else
				{
					$info = self::ExtractURL("https://" . $options["headers"]["Host"]);
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
					$info = self::ExtractURL("https://" . $options["headers"]["Host"]);
					$options[$key]["SNI_server_name"] = $info["host"];
				}
			}
		}

		// Swiped from str_basics.php so this file can be standalone.
		public static function ExtractFilename($dirfile)
		{
			$dirfile = str_replace("\\", "/", $dirfile);
			$pos = strrpos($dirfile, "/");
			if ($pos !== false)  $dirfile = substr($dirfile, $pos + 1);

			return $dirfile;
		}

		public static function FilenameSafe($filename)
		{
			return preg_replace('/[_]+/', "_", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $filename));
		}

		public static function GetTimeLeft($start, $limit)
		{
			if ($limit === false)  return false;

			$difftime = microtime(true) - $start;
			if ($difftime >= $limit)  return 0;

			return $limit - $difftime;
		}

		private static function ProcessRateLimit($size, $start, $limit, $async)
		{
			$difftime = microtime(true) - $start;
			if ($difftime > 0.0)
			{
				if ($size / $difftime > $limit)
				{
					// Sleeping for some amount of time will equalize the rate.
					// So, solve this for $x:  $size / ($x + $difftime) = $limit
					$amount = ($size - ($limit * $difftime)) / $limit;
					$amount += 0.001;

					if ($async)  return microtime(true) + $amount;
					else  usleep($amount * 1000000);
				}
			}

			return -1.0;
		}

		private static function GetDecodedBody(&$autodecode_ds, $body)
		{
			if ($autodecode_ds !== false)
			{
				$autodecode_ds->Write($body);
				$body = $autodecode_ds->Read();
			}

			return $body;
		}

		private static function StreamTimedOut($fp)
		{
			if (!function_exists("stream_get_meta_data"))  return false;

			$info = stream_get_meta_data($fp);

			return $info["timed_out"];
		}

		public static function InitResponseState($fp, $debug, $options, $startts, $timeout, $result, $close, $nextread, $client = true)
		{
			$state = array(
				"fp" => $fp,
				"type" => "response",
				"async" => (isset($options["async"]) ? $options["async"] : false),
				"debug" => $debug,
				"startts" => $startts,
				"timeout" => $timeout,
				"waituntil" => -1.0,
				"rawdata" => "",
				"data" => "",
				"rawsize" => 0,
				"rawrecvheadersize" => 0,
				"numheaders" => 0,
				"autodecode" => (!isset($options["auto_decode"]) || $options["auto_decode"]),

				"state" => ($client ? "response_line" : "request_line"),

				"options" => $options,
				"result" => $result,
				"close" => $close,
				"nextread" => $nextread,
				"client" => $client
			);

			$state["result"]["recvstart"] = microtime(true);
			$state["result"]["response"] = false;
			$state["result"]["headers"] = false;
			$state["result"]["body"] = false;

			return $state;
		}

		// Handles partially read input.  Also deals with the hacky workaround to the second bugfix in ProcessState__WriteData().
		private static function ProcessState__InternalRead(&$state, $size, $endchar = false)
		{
			$y = strlen($state["nextread"]);

			do
			{
				if ($size <= $y)
				{
					if ($endchar === false)  $pos = $size;
					else
					{
						$pos = strpos($state["nextread"], $endchar);
						if ($pos === false || $pos > $size)  $pos = $size;
						else  $pos++;
					}

					$data = substr($state["nextread"], 0, $pos);
					$state["nextread"] = (string)substr($state["nextread"], $pos);

					return $data;
				}

				if ($endchar !== false)
				{
					$pos = strpos($state["nextread"], $endchar);
					if ($pos !== false)
					{
						$data = substr($state["nextread"], 0, $pos + 1);
						$state["nextread"] = (string)substr($state["nextread"], $pos + 1);

						return $data;
					}
				}

				if ($state["debug"])  $data2 = fread($state["fp"], $size);
				else  $data2 = @fread($state["fp"], $size);

				if ($data2 === false || $data2 === "")
				{
					if ($state["nextread"] === "")  return $data2;

					if ($state["async"] && $endchar !== false && $data2 === "")  return "";

					$data = $state["nextread"];
					$state["nextread"] = "";

					return $data;
				}

				$state["nextread"] .= $data2;

				$y = strlen($state["nextread"]);
			} while (!$state["async"] || ($size <= $y) || ($endchar !== false && strpos($state["nextread"], $endchar) !== false));

			if ($endchar !== false)  return "";

			$data = $state["nextread"];
			$state["nextread"] = "";

			return $data;
		}

		// Reads one line.
		private static function ProcessState__ReadLine(&$state)
		{
			while (strpos($state["data"], "\n") === false)
			{
				$data2 = self::ProcessState__InternalRead($state, 116000, "\n");

				if ($data2 === false || $data2 === "")
				{
					if (feof($state["fp"]))  return array("success" => false, "error" => self::HTTPTranslate("Remote peer disconnected."), "errorcode" => "peer_disconnected");
					else if ($state["async"])  return array("success" => false, "error" => self::HTTPTranslate("Non-blocking read returned no data."), "errorcode" => "no_data");
					else if ($data2 === false)  return array("success" => false, "error" => self::HTTPTranslate("Underlying stream encountered a read error."), "errorcode" => "stream_read_error");
				}
				$pos = strpos($data2, "\n");
				if ($pos === false)
				{
					if (feof($state["fp"]))  return array("success" => false, "error" => self::HTTPTranslate("Remote peer disconnected."), "errorcode" => "peer_disconnected");
					if (self::StreamTimedOut($state["fp"]))  return array("success" => false, "error" => self::HTTPTranslate("Underlying stream timed out."), "errorcode" => "stream_timeout_exceeded");

					$pos = strlen($data2);
				}
				if ($state["timeout"] !== false && self::GetTimeLeft($state["startts"], $state["timeout"]) == 0)  return array("success" => false, "error" => self::HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");
				if (isset($state["options"]["readlinelimit"]) && strlen($state["data"]) + $pos > $state["options"]["readlinelimit"])  return array("success" => false, "error" => self::HTTPTranslate("Read line exceeded limit."), "errorcode" => "read_line_limit_exceeded");

				$state["rawsize"] += strlen($data2);
				$state["data"] .= $data2;

				if (isset($state["options"]["recvlimit"]) && $state["options"]["recvlimit"] < $state["rawsize"])  return array("success" => false, "error" => self::HTTPTranslate("Received data exceeded limit."), "errorcode" => "receive_limit_exceeded");
				if (isset($state["options"]["recvratelimit"]))  $state["waituntil"] = self::ProcessRateLimit($state["rawsize"], $state["recvstart"], $state["options"]["recvratelimit"], $state["async"]);

				if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("rawrecv", $data2, &$state["options"]["debug_callback_opts"]));
				else if ($state["debug"])  $state["rawdata"] .= $data2;
			}

			return array("success" => true);
		}

		// Reads data in.
		private static function ProcessState__ReadBodyData(&$state)
		{
			while ($state["sizeleft"] === false || $state["sizeleft"] > 0)
			{
				$data2 = self::ProcessState__InternalRead($state, ($state["sizeleft"] === false || $state["sizeleft"] > 65536 ? 65536 : $state["sizeleft"]));

				if ($data2 === false)  return array("success" => false, "error" => self::HTTPTranslate("Underlying stream encountered a read error."), "errorcode" => "stream_read_error");
				if ($data2 === "")
				{
					if (feof($state["fp"]))  return array("success" => false, "error" => self::HTTPTranslate("Remote peer disconnected."), "errorcode" => "peer_disconnected");
					if (self::StreamTimedOut($state["fp"]))  return array("success" => false, "error" => self::HTTPTranslate("Underlying stream timed out."), "errorcode" => "stream_timeout_exceeded");

					if ($state["async"])  return array("success" => false, "error" => self::HTTPTranslate("Non-blocking read returned no data."), "errorcode" => "no_data");
				}
				if ($state["timeout"] !== false && self::GetTimeLeft($state["startts"], $state["timeout"]) == 0)  return array("success" => false, "error" => self::HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

				$tempsize = strlen($data2);
				$state["rawsize"] += $tempsize;
				if ($state["sizeleft"] !== false)  $state["sizeleft"] -= $tempsize;

				if ($state["result"]["response"]["code"] == 100 || !isset($state["options"]["read_body_callback"]) || !is_callable($state["options"]["read_body_callback"]))  $state["result"]["body"] .= self::GetDecodedBody($state["autodecode_ds"], $data2);
				else if (!call_user_func_array($state["options"]["read_body_callback"], array($state["result"][($state["client"] ? "response" : "request")], self::GetDecodedBody($state["autodecode_ds"], $data2), &$state["options"]["read_body_callback_opts"])))  return array("success" => false, "error" => self::HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback");

				if (isset($state["options"]["recvlimit"]) && $state["options"]["recvlimit"] < $state["rawsize"])  return array("success" => false, "error" => self::HTTPTranslate("Received data exceeded limit."), "errorcode" => "receive_limit_exceeded");

				if (isset($state["options"]["recvratelimit"]))
				{
					$state["waituntil"] = self::ProcessRateLimit($state["rawsize"], $state["recvstart"], $state["options"]["recvratelimit"], $state["async"]);
					if (microtime(true) < $state["waituntil"])  return array("success" => false, "error" => self::HTTPTranslate("Rate limit for non-blocking connection has not been reached."), "errorcode" => "no_data");
				}

				if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("rawrecv", $data2, &$state["options"]["debug_callback_opts"]));
				else if ($state["debug"])  $state["rawdata"] .= $data2;
			}

			return array("success" => true);
		}

		// Writes data out.
		private static function ProcessState__WriteData(&$state, $prefix)
		{
			if ($state[$prefix . "data"] !== "")
			{
				// Serious bug in PHP core for non-blocking SSL sockets:  https://bugs.php.net/bug.php?id=72333
				if ($state["secure"] && $state["async"] && version_compare(PHP_VERSION, "7.1.4") <= 0)
				{
					// This is a huge hack that has a pretty good chance of blocking on the socket.
					// Peeling off up to just 4KB at a time helps to minimize that possibility.  It's better than guaranteed failure of the socket though.
					@stream_set_blocking($state["fp"], 1);
					if ($state["debug"])  $result = fwrite($state["fp"], (strlen($state[$prefix . "data"]) > 4096 ? substr($state[$prefix . "data"], 0, 4096) : $state[$prefix . "data"]));
					else  $result = @fwrite($state["fp"], (strlen($state[$prefix . "data"]) > 4096 ? substr($state[$prefix . "data"], 0, 4096) : $state[$prefix . "data"]));
					@stream_set_blocking($state["fp"], 0);
				}
				else
				{
					if ($state["debug"])  $result = fwrite($state["fp"], $state[$prefix . "data"]);
					else  $result = @fwrite($state["fp"], $state[$prefix . "data"]);
				}

				if ($result === false || feof($state["fp"]))  return array("success" => false, "error" => self::HTTPTranslate("A fwrite() failure occurred.  Most likely cause:  Connection failure."), "errorcode" => "fwrite_failed");

				// Serious bug in PHP core for all socket types:  https://bugs.php.net/bug.php?id=73535
				if ($result === 0)
				{
					// Temporarily switch to non-blocking sockets and test a one byte read (doesn't matter if data is available or not).
					// This is a bigger hack than the first hack above.
					if (!$state["async"])  @stream_set_blocking($state["fp"], 0);

					if ($state["debug"])  $data2 = fread($state["fp"], 1);
					else  $data2 = @fread($state["fp"], 1);

					if ($data2 === false)  return array("success" => false, "error" => self::HTTPTranslate("Underlying stream encountered a read error."), "errorcode" => "stream_read_error");
					if ($data2 === "" && feof($state["fp"]))  return array("success" => false, "error" => self::HTTPTranslate("Remote peer disconnected."), "errorcode" => "peer_disconnected");

					if ($data2 !== "")  $state["nextread"] .= $data2;

					if (!$state["async"])  @stream_set_blocking($state["fp"], 1);
				}

				if ($state["timeout"] !== false && self::GetTimeLeft($state["startts"], $state["timeout"]) == 0)  return array("success" => false, "error" => self::HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

				$data2 = (string)substr($state[$prefix . "data"], 0, $result);
				$state[$prefix . "data"] = (string)substr($state[$prefix . "data"], $result);

				$state["result"]["rawsend" . $prefix . "size"] += $result;

				if (isset($state["options"]["sendratelimit"]))
				{
					$state["waituntil"] = self::ProcessRateLimit($state["result"]["rawsendsize"], $state["result"]["connected"], $state["options"]["sendratelimit"], $state["async"]);
					if (microtime(true) < $state["waituntil"])  return array("success" => false, "error" => self::HTTPTranslate("Rate limit for non-blocking connection has not been reached."), "errorcode" => "no_data");
				}

				if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("rawsend", $data2, &$state["options"]["debug_callback_opts"]));
				else if ($state["debug"])  $state["result"]["rawsend"] .= $data2;

				if ($state["async"] && strlen($state[$prefix . "data"]))  return array("success" => false, "error" => self::HTTPTranslate("Non-blocking write did not send all data."), "errorcode" => "no_data");
			}

			return array("success" => true);
		}

		public static function ForceClose(&$state)
		{
			if ($state["fp"] !== false)
			{
				@fclose($state["fp"]);
				$state["fp"] = false;
			}

			if (isset($state["currentfile"]) && $state["currentfile"] !== false)
			{
				if ($state["currentfile"]["fp"] !== false)  @fclose($state["currentfile"]["fp"]);
				$state["currentfile"] = false;
			}
		}

		private static function CleanupErrorState(&$state, $result)
		{
			if (!$result["success"] && $result["errorcode"] !== "no_data")
			{
				self::ForceClose($state);

				$state["error"] = $result;
			}

			return $result;
		}

		public static function WantRead(&$state)
		{
			return ($state["type"] === "response" || $state["state"] === "proxy_connect_response" || $state["state"] === "receive_switch" || $state["state"] === "connecting_enable_crypto" || $state["state"] === "proxy_connect_enable_crypto");
		}

		public static function WantWrite(&$state)
		{
			return (!self::WantRead($state) || $state["state"] === "connecting_enable_crypto" || $state["state"] === "proxy_connect_enable_crypto");
		}

		public static function ProcessState(&$state)
		{
			if (isset($state["error"]))  return $state["error"];

			if ($state["timeout"] !== false && self::GetTimeLeft($state["startts"], $state["timeout"]) == 0)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded"));
			if (microtime(true) < $state["waituntil"])  return array("success" => false, "error" => self::HTTPTranslate("Rate limit for non-blocking connection has not been reached."), "errorcode" => "no_data");

			if ($state["type"] === "request")
			{
				while ($state["state"] !== "done")
				{
					switch ($state["state"])
					{
						case "connecting":
						{
							if (function_exists("stream_select") && $state["async"])
							{
								$readfp = NULL;
								$writefp = array($state["fp"]);
								$exceptfp = array($state["fp"]);
								if ($state["debug"])  $result = stream_select($readfp, $writefp, $exceptfp, 0);
								else  $result = @stream_select($readfp, $writefp, $exceptfp, 0);
								if ($result === false || count($exceptfp))  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("A stream_select() failure occurred.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed"));

								if (!count($writefp))  return array("success" => false, "error" => self::HTTPTranslate("Connection not established yet."), "errorcode" => "no_data");
							}

							// Deal with failed connections that hang applications.
							if (isset($state["options"]["streamtimeout"]) && $state["options"]["streamtimeout"] !== false && function_exists("stream_set_timeout"))  @stream_set_timeout($state["fp"], $state["options"]["streamtimeout"]);

							// Switch to the next state.
							if ($state["async"] && function_exists("stream_socket_client") && (($state["useproxy"] && $state["proxysecure"]) || (!$state["useproxy"] && $state["secure"])))  $state["state"] = "connecting_enable_crypto";
							else  $state["state"] = "connection_ready";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "connecting_enable_crypto":
						{
							// This is only used by clients that connect asynchronously via SSL.
							if ($state["debug"])  $result = stream_socket_enable_crypto($state["fp"], true, STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
							else  $result = @stream_socket_enable_crypto($state["fp"], true, STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);

							if ($result === false)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("A stream_socket_enable_crypto() failure occurred.  Most likely cause:  Connection failure or incompatible crypto setup."), "errorcode" => "stream_socket_enable_crypto_failed"));
							else if ($result === true)  $state["state"] = "connection_ready";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "connection_ready":
						{
							// Handle peer certificate retrieval.
							if (function_exists("stream_context_get_options"))
							{
								$contextopts = stream_context_get_options($state["fp"]);
								if ($state["useproxy"])
								{
									if ($state["proxysecure"] && isset($state["options"]["proxysslopts"]) && is_array($state["options"]["proxysslopts"]))
									{
										if (isset($state["options"]["peer_cert_callback"]) && is_callable($state["options"]["peer_cert_callback"]))
										{
											if (isset($contextopts["ssl"]["peer_certificate"]) && !call_user_func_array($state["options"]["peer_cert_callback"], array("proxypeercert", $contextopts["ssl"]["peer_certificate"], &$state["options"]["peer_cert_callback_opts"])))  return array("success" => false, "error" => self::HTTPTranslate("Peer certificate callback returned with a failure condition."), "errorcode" => "peer_cert_callback");
											if (isset($contextopts["ssl"]["peer_certificate_chain"]) && !call_user_func_array($state["options"]["peer_cert_callback"], array("proxypeercertchain", $contextopts["ssl"]["peer_certificate_chain"], &$state["options"]["peer_cert_callback_opts"])))  return array("success" => false, "error" => self::HTTPTranslate("Peer certificate callback returned with a failure condition."), "errorcode" => "peer_cert_callback");
										}
									}
								}
								else
								{
									if ($state["secure"] && isset($state["options"]["sslopts"]) && is_array($state["options"]["sslopts"]))
									{
										if (isset($state["options"]["peer_cert_callback"]) && is_callable($state["options"]["peer_cert_callback"]))
										{
											if (isset($contextopts["ssl"]["peer_certificate"]) && !call_user_func_array($state["options"]["peer_cert_callback"], array("peercert", $contextopts["ssl"]["peer_certificate"], &$state["options"]["peer_cert_callback_opts"])))  return array("success" => false, "error" => self::HTTPTranslate("Peer certificate callback returned with a failure condition."), "errorcode" => "peer_cert_callback");
											if (isset($contextopts["ssl"]["peer_certificate_chain"]) && !call_user_func_array($state["options"]["peer_cert_callback"], array("peercertchain", $contextopts["ssl"]["peer_certificate_chain"], &$state["options"]["peer_cert_callback_opts"])))  return array("success" => false, "error" => self::HTTPTranslate("Peer certificate callback returned with a failure condition."), "errorcode" => "peer_cert_callback");
										}
									}
								}
							}

							$state["result"]["connected"] = microtime(true);

							// Switch to the correct state.
							if ($state["proxyconnect"])
							{
								$state["result"]["rawsendproxysize"] = 0;
								$state["result"]["rawsendproxyheadersize"] = strlen($state["proxydata"]);

								$state["state"] = "proxy_connect_send";
							}
							else
							{
								$state["result"]["sendstart"] = microtime(true);

								$state["state"] = "send_data";
							}

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "proxy_connect_send":
						{
							// Send the HTTP CONNECT request to the proxy.
							$result = self::ProcessState__WriteData($state, "proxy");
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							// Prepare the state for handling the response from the proxy server.
							$options2 = array();
							if (isset($state["options"]["async"]))  $options2["async"] = $state["options"]["async"];
							if (isset($state["options"]["recvratelimit"]))  $options2["recvratelimit"] = $state["options"]["recvratelimit"];
							if (isset($state["options"]["debug_callback"]))
							{
								$options2["debug_callback"] = $state["options"]["debug_callback"];
								$options2["debug_callback_opts"] = $state["options"]["debug_callback_opts"];
							}
							$state["proxyresponse"] = self::InitResponseState($state["fp"], $state["debug"], $options2, $state["startts"], $state["timeout"], $state["result"], false, $state["nextread"]);
							$state["proxyresponse"]["proxyconnect"] = true;

							$state["state"] = "proxy_connect_response";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "proxy_connect_response":
						{
							// Recursively call this function to handle the proxy response.
							$result = self::ProcessState($state["proxyresponse"]);
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							$state["result"]["rawrecvsize"] += $result["rawrecvsize"];
							$state["result"]["rawrecvheadersize"] += $result["rawrecvheadersize"];

							if (substr($result["response"]["code"], 0, 1) != "2")  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("Expected a 200 response from the CONNECT request.  Received:  %s.", $result["response"]["line"]), "info" => $result, "errorcode" => "proxy_connect_tunnel_failed"));

							// Proxy connect tunnel established.  Proceed normally.
							$state["result"]["sendstart"] = microtime(true);

							if ($state["secure"])  $state["state"] = "proxy_connect_enable_crypto";
							else  $state["state"] = "send_data";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "proxy_connect_enable_crypto":
						{
							if ($state["debug"])  $result = stream_socket_enable_crypto($state["fp"], true, STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
							else  $result = @stream_socket_enable_crypto($state["fp"], true, STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);

							if ($result === false)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("A stream_socket_enable_crypto() failure occurred.  Most likely cause:  Tunnel connection failure or incompatible crypto setup."), "errorcode" => "stream_socket_enable_crypto_failed"));
							else if ($result === true)
							{
								// Handle peer certificate retrieval.
								if (function_exists("stream_context_get_options"))
								{
									$contextopts = stream_context_get_options($state["fp"]);

									if (isset($state["options"]["sslopts"]) && is_array($state["options"]["sslopts"]))
									{
										if (isset($state["options"]["peer_cert_callback"]) && is_callable($state["options"]["peer_cert_callback"]))
										{
											if (isset($contextopts["ssl"]["peer_certificate"]) && !call_user_func_array($state["options"]["peer_cert_callback"], array("peercert", $contextopts["ssl"]["peer_certificate"], &$state["options"]["peer_cert_callback_opts"])))  return array("success" => false, "error" => self::HTTPTranslate("Peer certificate callback returned with a failure condition."), "errorcode" => "peer_cert_callback");
											if (isset($contextopts["ssl"]["peer_certificate_chain"]) && !call_user_func_array($state["options"]["peer_cert_callback"], array("peercertchain", $contextopts["ssl"]["peer_certificate_chain"], &$state["options"]["peer_cert_callback_opts"])))  return array("success" => false, "error" => self::HTTPTranslate("Peer certificate callback returned with a failure condition."), "errorcode" => "peer_cert_callback");
										}
									}
								}

								// Secure connection established.
								$state["state"] = "send_data";

								if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));
							}

							break;
						}
						case "send_data":
						{
							// Send the queued data.
							$result = self::ProcessState__WriteData($state, "");
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							// Queue up more data.
							if (isset($state["options"]["write_body_callback"]) && is_callable($state["options"]["write_body_callback"]))
							{
								if ($state["bodysize"] === false || $state["bodysize"] > 0)
								{
									$bodysize2 = $state["bodysize"];
									$result = call_user_func_array($state["options"]["write_body_callback"], array(&$state["data"], &$bodysize2, &$state["options"]["write_body_callback_opts"]));
									if (!$result || ($state["bodysize"] !== false && strlen($state["data"]) > $state["bodysize"]))  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("HTTP write body callback function failed."), "errorcode" => "write_body_callback"));

									if ($state["bodysize"] === false)
									{
										if ($state["data"] !== "" && $state["chunked"])  $state["data"] = dechex(strlen($state["data"])) . "\r\n" . $state["data"] . "\r\n";

										// When $bodysize2 is set to true, it is the last chunk.
										if ($bodysize2 === true)
										{
											if ($state["chunked"])
											{
												$state["data"] .= "0\r\n";

												// Allow the body callback function to append additional headers to the content to send.
												// It is up to the callback function to correctly format the extra headers.
												$result = call_user_func_array($state["options"]["write_body_callback"], array(&$state["data"], &$bodysize2, &$state["options"]["write_body_callback_opts"]));
												if (!$result)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("HTTP write body callback function failed."), "errorcode" => "write_body_callback"));

												$state["data"] .= "\r\n";
											}

											$state["bodysize"] = 0;
										}
									}
									else
									{
										$state["bodysize"] -= strlen($state["data"]);
									}
								}
							}
							else if (isset($state["options"]["files"]) && $state["bodysize"] > 0)
							{
								// Select the next file to upload.
								if ($state["currentfile"] === false && count($state["options"]["files"]))
								{
									$state["currentfile"] = array_shift($state["options"]["files"]);

									$name = self::HeaderValueCleanup($state["currentfile"]["name"]);
									$name = str_replace("\"", "", $name);
									$filename = self::FilenameSafe(self::ExtractFilename($state["currentfile"]["filename"]));
									$type = self::HeaderValueCleanup($state["currentfile"]["type"]);

									$state["data"] = "--" . $state["mime"] . "\r\n";
									$state["data"] .= "Content-Disposition: form-data; name=\"" . $name . "\"; filename=\"" . $filename . "\"\r\n";
									$state["data"] .= "Content-Type: " . $type . "\r\n";
									$state["data"] .= "\r\n";

									if (!isset($state["currentfile"]["datafile"]))
									{
										$state["data"] .= $state["currentfile"]["data"];
										$state["data"] .= "\r\n";

										$state["currentfile"] = false;
									}
									else
									{
										$state["currentfile"]["fp"] = @fopen($state["currentfile"]["datafile"], "rb");
										if ($state["currentfile"]["fp"] === false)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("The file '%s' does not exist.", $state["currentfile"]["datafile"]), "errorcode" => "file_does_not_exist"));
									}
								}

								// Process the next chunk of file information.
								if ($state["currentfile"] !== false && isset($state["currentfile"]["fp"]))
								{
									// Read/Write up to 65K at a time.
									if ($state["currentfile"]["filesize"] >= 65536)
									{
										$data2 = fread($state["currentfile"]["fp"], 65536);
										if ($data2 === false || strlen($data2) !== 65536)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("A read error was encountered with the file '%s'.", $state["currentfile"]["datafile"]), "errorcode" => "file_read"));

										$state["data"] .= $data2;

										$state["currentfile"]["filesize"] -= 65536;
									}
									else
									{
										// Read in the rest.
										if ($state["currentfile"]["filesize"] > 0)
										{
											$data2 = fread($state["currentfile"]["fp"], $state["currentfile"]["filesize"]);
											if ($data2 === false || strlen($data2) != $state["currentfile"]["filesize"])  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("A read error was encountered with the file '%s'.", $state["currentfile"]["datafile"]), "errorcode" => "file_read"));

											$state["data"] .= $data2;
										}

										$state["data"] .= "\r\n";

										fclose($state["currentfile"]["fp"]);

										$state["currentfile"] = false;
									}
								}

								// If there is no more data, write out the closing MIME line.
								if ($state["data"] === "")  $state["data"] = "--" . $state["mime"] . "--\r\n";

								$state["bodysize"] -= strlen($state["data"]);
							}
							else if ($state["bodysize"] === false || $state["bodysize"] > 0)
							{
								return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("A weird internal HTTP error that should never, ever happen...just happened."), "errorcode" => "impossible"));
							}

							// All done sending data.
							if ($state["data"] === "")
							{
								if ($state["client"])
								{
									$state["state"] = "receive_switch";

									if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));
								}
								else
								{
									$state["result"]["endts"] = microtime(true);

									if ($state["close"])  fclose($state["fp"]);
									else  $state["result"]["fp"] = $state["fp"];

									return $state["result"];
								}
							}

							break;
						}
						case "receive_switch":
						{
							if (function_exists("stream_select") && $state["async"])
							{
								$readfp = array($state["fp"]);
								$writefp = NULL;
								$exceptfp = NULL;
								if ($state["debug"])  $result = stream_select($readfp, $writefp, $exceptfp, 0);
								else  $result = @stream_select($readfp, $writefp, $exceptfp, 0);
								if ($result === false)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("A stream_select() failure occurred.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed"));

								if (!count($readfp))  return array("success" => false, "error" => self::HTTPTranslate("Connection not fully established yet."), "errorcode" => "no_data");
							}

							$state["state"] = "done";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
					}
				}

				// The request has been sent.  Change the state to a response state.
				$state = self::InitResponseState($state["fp"], $state["debug"], $state["options"], $state["startts"], $state["timeout"], $state["result"], $state["close"], $state["nextread"]);

				// Run one cycle.
				return self::ProcessState($state);
			}
			else if ($state["type"] === "response")
			{
				while ($state["state"] !== "done")
				{
					switch ($state["state"])
					{
						case "response_line":
						{
							$result = self::ProcessState__ReadLine($state);
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							// Parse the response line.
							$pos = strpos($state["data"], "\n");
							if ($pos === false)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("Unable to retrieve response line."), "errorcode" => "get_response_line"));
							$line = trim(substr($state["data"], 0, $pos));
							$state["data"] = substr($state["data"], $pos + 1);
							$state["rawrecvheadersize"] += $pos + 1;
							$response = explode(" ", $line, 3);

							$state["result"]["response"] = array(
								"line" => $line,
								"httpver" => strtoupper($response[0]),
								"code" => $response[1],
								"meaning" => (isset($response[2]) ? $response[2] : "")
							);

							$state["state"] = "headers";
							$state["result"]["headers"] = array();
							$state["lastheader"] = "";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "request_line":
						{
							// Server mode only.
							$result = self::ProcessState__ReadLine($state);
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							// Parse the request line.
							$pos = strpos($state["data"], "\n");
							if ($pos === false)  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("Unable to retrieve request line."), "errorcode" => "get_request_line"));
							$line = trim(substr($state["data"], 0, $pos));
							$state["data"] = substr($state["data"], $pos + 1);
							$state["rawrecvheadersize"] += $pos + 1;

							$request = $line;
							$pos = strpos($request, " ");
							if ($pos === false)  $pos = strlen($request);
							$method = (string)substr($request, 0, $pos);
							$request = trim(substr($request, $pos));

							$pos = strrpos($request, " ");
							if ($pos === false)  $pos = strlen($request);
							$path = trim(substr($request, 0, $pos));
							if ($path === "")  $path = "/";
							$version = (string)substr($request, $pos + 1);

							$state["result"]["request"] = array(
								"line" => $line,
								"method" => strtoupper($method),
								"path" => $path,
								"httpver" => strtoupper($version),
							);

							// Fake the response line to bypass some client-only code.
							$state["result"]["response"] = array(
								"line" => "200",
								"httpver" => "",
								"code" => 200,
								"meaning" => ""
							);

							$state["state"] = "headers";
							$state["result"]["headers"] = array();
							$state["lastheader"] = "";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "headers":
						case "body_chunked_headers":
						{
							$result = self::ProcessState__ReadLine($state);
							if (!$result["success"] && ($state["state"] === "headers" || ($result["errorcode"] !== "stream_read_error" && $result["errorcode"] !== "peer_disconnected")))  return self::CleanupErrorState($state, $result);

							$pos = strpos($state["data"], "\n");
							if ($pos === false)  $pos = strlen($state["data"]);
							$header = rtrim(substr($state["data"], 0, $pos));
							$state["data"] = substr($state["data"], $pos + 1);
							$state["rawrecvheadersize"] += $pos + 1;
							if ($header != "")
							{
								if ($state["lastheader"] != "" && (substr($header, 0, 1) == " " || substr($header, 0, 1) == "\t"))  $state["result"]["headers"][$state["lastheader"]][count($state["result"]["headers"][$state["lastheader"]]) - 1] .= $header;
								else
								{
									$pos = strpos($header, ":");
									if ($pos === false)  $pos = strlen($header);
									$state["lastheader"] = self::HeaderNameCleanup(substr($header, 0, $pos));
									if (!isset($state["result"]["headers"][$state["lastheader"]]))  $state["result"]["headers"][$state["lastheader"]] = array();
									$state["result"]["headers"][$state["lastheader"]][] = ltrim(substr($header, $pos + 1));
								}

								$state["numheaders"]++;
								if (isset($state["options"]["maxheaders"]) && $state["numheaders"] > $state["options"]["maxheaders"])  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("The number of headers exceeded the limit."), "errorcode" => "headers_limit_exceeded"));
							}
							else
							{
								if ($state["result"]["response"]["code"] != 100 && isset($state["options"]["read_headers_callback"]) && is_callable($state["options"]["read_headers_callback"]))
								{
									if (!call_user_func_array($state["options"]["read_headers_callback"], array(&$state["result"][($state["client"] ? "response" : "request")], &$state["result"]["headers"], &$state["options"]["read_headers_callback_opts"])))  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("Read headers callback returned with a failure condition."), "errorcode" => "read_header_callback"));
								}

								// Additional headers (optional) are the last bit of data in a chunked response.
								if ($state["state"] === "body_chunked_headers")
								{
									$state["state"] = "body_finalize";

									if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));
								}
								else
								{
									$state["result"]["body"] = "";

									// Handle 100 Continue below OR WebSocket among other things by letting the caller handle reading the body.
									if ($state["result"]["response"]["code"] == 100 || $state["result"]["response"]["code"] == 101)
									{
										$state["state"] = "done";

										if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));
									}
									else
									{
										// Determine if decoding the content is possible and necessary.
										if ($state["autodecode"] && !isset($state["result"]["headers"]["Content-Encoding"]) || (strtolower($state["result"]["headers"]["Content-Encoding"][0]) != "gzip" && strtolower($state["result"]["headers"]["Content-Encoding"][0]) != "deflate"))  $state["autodecode"] = false;
										if (!$state["autodecode"])  $state["autodecode_ds"] = false;
										else
										{
											if (!class_exists("DeflateStream", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/deflate_stream.php";

											// Since servers and browsers do everything wrong, ignore the encoding claim and attempt to auto-detect the encoding.
											$state["autodecode_ds"] = new DeflateStream();
											$state["autodecode_ds"]->Init("rb", -1, array("type" => "auto"));
										}

										// Use the appropriate state for handling the next bit of input.
										if (isset($state["result"]["headers"]["Transfer-Encoding"]) && strtolower($state["result"]["headers"]["Transfer-Encoding"][0]) == "chunked")
										{
											$state["state"] = "body_chunked_size";
										}
										else
										{
											$state["sizeleft"] = (isset($state["result"]["headers"]["Content-Length"]) ? (double)preg_replace('/[^0-9]/', "", $state["result"]["headers"]["Content-Length"][0]) : false);
											$state["state"] = (!isset($state["proxyconnect"]) && ($state["sizeleft"] !== false || $state["client"]) ? "body_content" : "done");
										}

										if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

										// Let servers have a chance to alter limits before processing the input body.
										if (!$state["client"] && $state["state"] !== "done")  return array("success" => false, "error" => self::HTTPTranslate("Intermission for adjustments to limits."), "errorcode" => "no_data");
									}
								}
							}

							break;
						}
						case "body_chunked_size":
						{
							$result = self::ProcessState__ReadLine($state);
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							$pos = strpos($state["data"], "\n");
							if ($pos === false)  $pos = strlen($state["data"]);
							$line = trim(substr($state["data"], 0, $pos));
							$state["data"] = substr($state["data"], $pos + 1);
							$pos = strpos($line, ";");
							if ($pos === false)  $pos = strlen($line);
							$size = hexdec(substr($line, 0, $pos));
							if ($size < 0)  $size = 0;

							// Retrieve content.
							$size2 = $size;
							$size3 = min(strlen($state["data"]), $size);
							if ($size3 > 0)
							{
								$data2 = substr($state["data"], 0, $size3);
								$state["data"] = substr($state["data"], $size3);
								$size2 -= $size3;

								if ($state["result"]["response"]["code"] == 100 || !isset($state["options"]["read_body_callback"]) || !is_callable($state["options"]["read_body_callback"]))  $state["result"]["body"] .= self::GetDecodedBody($state["autodecode_ds"], $data2);
								else if (!call_user_func_array($state["options"]["read_body_callback"], array($state["result"][($state["client"] ? "response" : "request")], self::GetDecodedBody($state["autodecode_ds"], $data2), &$state["options"]["read_body_callback_opts"])))  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback"));
							}

							$state["chunksize"] = $size;
							$state["sizeleft"] = $size2;
							$state["state"] = "body_chunked_data";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "body_chunked_data":
						{
							$result = self::ProcessState__ReadBodyData($state);
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							if ($state["chunksize"] > 0)  $state["state"] = "body_chunked_skipline";
							else
							{
								$state["lastheader"] = "";
								$state["state"] = "body_chunked_headers";
							}

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "body_chunked_skipline":
						{
							$result = self::ProcessState__ReadLine($state);
							if (!$result["success"])  return self::CleanupErrorState($state, $result);

							// Ignore one newline.
							$pos = strpos($state["data"], "\n");
							if ($pos === false)  $pos = strlen($state["data"]);
							$state["data"] = substr($state["data"], $pos + 1);

							$state["state"] = "body_chunked_size";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "body_content":
						{
							$result = self::ProcessState__ReadBodyData($state);
							if (!$result["success"] && (($state["sizeleft"] !== false && $state["sizeleft"] > 0) || ($state["sizeleft"] === false && $result["errorcode"] !== "stream_read_error" && $result["errorcode"] !== "peer_disconnected" && $result["errorcode"] !== "stream_timeout_exceeded")))  return self::CleanupErrorState($state, $result);

							$state["state"] = "body_finalize";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
						case "body_finalize":
						{
							if ($state["autodecode_ds"] !== false)
							{
								$state["autodecode_ds"]->Finalize();
								$data2 = $state["autodecode_ds"]->Read();

								if ($state["result"]["response"]["code"] == 100 || !isset($state["options"]["read_body_callback"]) || !is_callable($state["options"]["read_body_callback"]))  $state["result"]["body"] .= $data2;
								else if (!call_user_func_array($state["options"]["read_body_callback"], array($state["result"][($state["client"] ? "response" : "request")], $data2, &$state["options"]["read_body_callback_opts"])))  return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("Read body callback returned with a failure condition."), "errorcode" => "read_body_callback"));
							}

							$state["state"] = "done";

							if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));

							break;
						}
					}

					// Handle HTTP 100 Continue status codes.
					if ($state["state"] === "done" && $state["result"]["response"]["code"] == 100)
					{
						$state["autodecode"] = (!isset($state["options"]["auto_decode"]) || $state["options"]["auto_decode"]);
						$state["state"] = "response";
						$state["result"]["response"] = false;
						$state["result"]["headers"] = false;
						$state["result"]["body"] = false;

						if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("nextstate", $state["state"], &$state["options"]["debug_callback_opts"]));
					}
				}

				if ($state["debug"])  $state["result"]["rawrecv"] .= $state["rawdata"];
				$state["result"]["rawrecvsize"] += $state["rawsize"];
				$state["result"]["rawrecvheadersize"] += $state["rawrecvheadersize"];
				$state["result"]["endts"] = microtime(true);

				if ($state["close"] || ($state["client"] && isset($state["result"]["headers"]["Connection"]) && strtolower($state["result"]["headers"]["Connection"][0]) === "close"))  fclose($state["fp"]);
				else  $state["result"]["fp"] = $state["fp"];

				return $state["result"];
			}
			else
			{
				return self::CleanupErrorState($state, array("success" => false, "error" => self::HTTPTranslate("Invalid 'type' in state tracker."), "errorcode" => "invalid_type"));
			}
		}

		public static function RawFileSize($fileorname)
		{
			if (is_resource($fileorname))  $fp = $fileorname;
			else
			{
				$fp = @fopen($fileorname, "rb");
				if ($fp === false)  return 0;
			}

			if (PHP_INT_SIZE < 8)
			{
				$pos = 0;
				$size = 1073741824;
				fseek($fp, 0, SEEK_SET);
				while ($size > 1)
				{
					if (fseek($fp, $size, SEEK_CUR) === -1)  break;

					if (fgetc($fp) === false)
					{
						fseek($fp, -$size, SEEK_CUR);
						$size = (int)($size / 2);
					}
					else
					{
						fseek($fp, -1, SEEK_CUR);
						$pos += $size;
					}
				}

				if ($size > 1)
				{
					// Unfortunately, fseek() failed for some reason.  Going to have to do this the old-fashioned way.
					do
					{
						$data = fread($fp, 10485760);
						if ($data === false)  $data = "";
						$pos += strlen($data);
					} while ($data !== "");
				}
				else
				{
					while (fgetc($fp) !== false)  $pos++;
				}
			}
			else
			{
				fseek($fp, 0, SEEK_END);
				$pos = ftell($fp);
			}

			if (!is_resource($fileorname))  fclose($fp);

			return $pos;
		}

		public static function RetrieveWebpage($url, $options = array())
		{
			$startts = microtime(true);
			$timeout = (isset($options["timeout"]) ? $options["timeout"] : false);

			if (!function_exists("stream_socket_client") && !function_exists("fsockopen"))  return array("success" => false, "error" => self::HTTPTranslate("The functions 'stream_socket_client' and 'fsockopen' do not exist."), "errorcode" => "function_check");

			// Process the URL.
			$url = trim($url);
			$url = self::ExtractURL($url);

			if ($url["scheme"] != "http" && $url["scheme"] != "https")  return array("success" => false, "error" => self::HTTPTranslate("RetrieveWebpage() only supports the 'http' and 'https' protocols."), "errorcode" => "protocol_check");

			$secure = ($url["scheme"] == "https");
			$async = (isset($options["async"]) ? $options["async"] : false);
			$protocol = ($secure && !$async ? (isset($options["protocol"]) ? strtolower($options["protocol"]) : "ssl") : "tcp");
			if (function_exists("stream_get_transports") && !in_array($protocol, stream_get_transports()))  return array("success" => false, "error" => self::HTTPTranslate("The desired transport protocol '%s' is not installed.", $protocol), "errorcode" => "transport_not_installed");
			$host = str_replace(" ", "-", self::HeaderValueCleanup($url["host"]));
			if ($host == "")  return array("success" => false, "error" => self::HTTPTranslate("Invalid URL."));
			$port = ((int)$url["port"] ? (int)$url["port"] : ($secure ? 443 : 80));
			$defaultport = ((!$secure && $port == 80) || ($secure && $port == 443));
			$path = ($url["path"] == "" ? "/" : $url["path"]);
			$query = $url["query"];
			$username = $url["loginusername"];
			$password = $url["loginpassword"];

			// Cleanup input headers.
			if (!isset($options["headers"]))  $options["headers"] = array();
			$options["headers"] = self::NormalizeHeaders($options["headers"]);
			if (isset($options["rawheaders"]))  self::MergeRawHeaders($options["headers"], $options["rawheaders"]);

			// Process the proxy URL (if specified).
			$useproxy = (isset($options["proxyurl"]) && trim($options["proxyurl"]) != "");
			$proxysecure = false;
			$proxyconnect = false;
			$proxydata = "";
			if ($useproxy)
			{
				$proxyurl = trim($options["proxyurl"]);
				$proxyurl = self::ExtractURL($proxyurl);

				$proxysecure = ($proxyurl["scheme"] == "https");
				if ($proxysecure && $secure)  return array("success" => false, "error" => self::HTTPTranslate("The PHP SSL sockets implementation does not support tunneled SSL/TLS connections over SSL/TLS."), "errorcode" => "multi_ssl_tunneling_not_supported");
				$proxyprotocol = ($proxysecure && !$async ? (isset($options["proxyprotocol"]) ? strtolower($options["proxyprotocol"]) : "ssl") : "tcp");
				if (function_exists("stream_get_transports") && !in_array($proxyprotocol, stream_get_transports()))  return array("success" => false, "error" => self::HTTPTranslate("The desired transport proxy protocol '%s' is not installed.", $proxyprotocol), "errorcode" => "proxy_transport_not_installed");
				$proxyhost = str_replace(" ", "-", self::HeaderValueCleanup($proxyurl["host"]));
				if ($proxyhost === "")  return array("success" => false, "error" => self::HTTPTranslate("The specified proxy URL is not a URL.  Prefix 'proxyurl' with http:// or https://"), "errorcode" => "invalid_proxy_url");
				$proxyport = ((int)$proxyurl["port"] ? (int)$proxyurl["port"] : ($proxysecure ? 443 : 80));
				$proxypath = ($proxyurl["path"] == "" ? "/" : $proxyurl["path"]);
				$proxyusername = $proxyurl["loginusername"];
				$proxypassword = $proxyurl["loginpassword"];

				// Open a tunnel instead of letting the proxy modify the request (HTTP CONNECT).
				$proxyconnect = (isset($options["proxyconnect"]) && $options["proxyconnect"] ? $options["proxyconnect"] : false);
				if ($proxyconnect)
				{
					$proxydata = "CONNECT " . $host . ":" . $port . " HTTP/1.1\r\n";
					if (isset($options["headers"]["User-Agent"]))  $proxydata .= "User-Agent: " . $options["headers"]["User-Agent"] . "\r\n";
					$proxydata .= "Host: " . $host . ($defaultport ? "" : ":" . $port) . "\r\n";
					$proxydata .= "Proxy-Connection: keep-alive\r\n";
					if ($proxyusername != "")  $proxydata .= "Proxy-Authorization: BASIC " . base64_encode($proxyusername . ":" . $proxypassword) . "\r\n";
					if (!isset($options["proxyheaders"]))  $options["proxyheaders"] = array();
					$options["proxyheaders"] = self::NormalizeHeaders($options["proxyheaders"]);
					if (isset($options["rawproxyheaders"]))  self::MergeRawHeaders($options["proxyheaders"], $options["rawproxyheaders"]);

					unset($options["proxyheaders"]["Accept-Encoding"]);
					foreach ($options["proxyheaders"] as $name => $val)
					{
						if ($name != "Content-Type" && $name != "Content-Length" && $name != "Proxy-Connection" && $name != "Host")  $proxydata .= $name . ": " . $val . "\r\n";
					}

					$proxydata .= "\r\n";
					if (isset($options["debug_callback"]) && is_callable($options["debug_callback"]))  call_user_func_array($options["debug_callback"], array("rawproxyheaders", $proxydata, &$options["debug_callback_opts"]));
				}
			}

			// Process the method.
			if (!isset($options["method"]))
			{
				if ((isset($options["write_body_callback"]) && is_callable($options["write_body_callback"])) || isset($options["body"]))  $options["method"] = "PUT";
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

			if (!isset($options["headers"]["Connection"]))  $options["headers"]["Connection"] = "close";
			$data .= "Connection: " . $options["headers"]["Connection"] . "\r\n";

			foreach ($options["headers"] as $name => $val)
			{
				if ($name != "Content-Type" && $name != "Content-Length" && $name != "Connection" && $name != "Host")  $data .= $name . ": " . $val . "\r\n";
			}

			if (isset($options["files"]) && !count($options["files"]))  unset($options["files"]);

			// Process the body.
			$mime = "";
			$body = "";
			$bodysize = 0;
			if (isset($options["write_body_callback"]) && is_callable($options["write_body_callback"]))
			{
				if (isset($options["headers"]["Content-Type"]))  $data .= "Content-Type: " . $options["headers"]["Content-Type"] . "\r\n";

				call_user_func_array($options["write_body_callback"], array(&$body, &$bodysize, &$options["write_body_callback_opts"]));
			}
			else if (isset($options["body"]))
			{
				if (isset($options["headers"]["Content-Type"]))  $data .= "Content-Type: " . $options["headers"]["Content-Type"] . "\r\n";

				$body = $options["body"];
				$bodysize = strlen($body);
				unset($options["body"]);
			}
			else if ((isset($options["files"]) && count($options["files"])) || (isset($options["headers"]["Content-Type"]) && stripos($options["headers"]["Content-Type"], "multipart/form-data") !== false))
			{
				$mime = "--------" . substr(sha1(uniqid(mt_rand(), true)), 0, 25);
				$data .= "Content-Type: multipart/form-data; boundary=" . $mime . "\r\n";
				if (isset($options["postvars"]))
				{
					foreach ($options["postvars"] as $name => $val)
					{
						$name = self::HeaderValueCleanup($name);
						$name = str_replace("\"", "", $name);

						if (!is_array($val))
						{
							if (is_string($val) || is_numeric($val))  $val = array($val);
							else  return array("success" => false, "error" => "A supplied 'postvars' value is an invalid type.  Expected string, numeric, or array.", "errorcode" => "invalid_postvars_value", "info" => array("name" => $name, "val" => $val));
						}

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
				if (!isset($options["files"]))  $options["files"] = array();
				foreach ($options["files"] as $num => $info)
				{
					$name = self::HeaderValueCleanup($info["name"]);
					$name = str_replace("\"", "", $name);
					$filename = self::FilenameSafe(self::ExtractFilename($info["filename"]));
					$type = self::HeaderValueCleanup($info["type"]);

					$body2 = "--" . $mime . "\r\n";
					$body2 .= "Content-Disposition: form-data; name=\"" . $name . "\"; filename=\"" . $filename . "\"\r\n";
					$body2 .= "Content-Type: " . $type . "\r\n";
					$body2 .= "\r\n";

					$info["filesize"] = (isset($info["datafile"]) ? self::RawFileSize($info["datafile"]) : strlen($info["data"]));
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
						$name = self::HeaderValueCleanup($name);

						if (!is_array($val))
						{
							if (is_string($val) || is_numeric($val))  $val = array($val);
							else  return array("success" => false, "error" => "A supplied 'postvars' value is an invalid type.  Expected string, numeric, or array.", "errorcode" => "invalid_postvars_value", "info" => array("name" => $name, "val" => $val));
						}

						foreach ($val as $val2)  $body .= ($body != "" ? "&" : "") . urlencode($name) . "=" . urlencode($val2);
					}

					unset($options["postvars"]);
				}

				if ($body != "")  $data .= "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n";

				$bodysize = strlen($body);
			}
			if (($bodysize === false && strlen($body) > 0) || ($bodysize !== false && $bodysize < strlen($body)))  $bodysize = strlen($body);

			// Finalize the headers.
			if ($bodysize === false)  $data .= "Transfer-Encoding: chunked\r\n";
			else if ($bodysize > 0 || $body != "" || $options["method"] == "POST")  $data .= "Content-Length: " . $bodysize . "\r\n";
			$data .= "\r\n";
			if (isset($options["debug_callback"]) && is_callable($options["debug_callback"]))  call_user_func_array($options["debug_callback"], array("rawheaders", $data, &$options["debug_callback_opts"]));
			$rawheadersize = strlen($data);

			// Finalize the initial data to be sent.
			$data .= $body;
			if ($bodysize !== false)  $bodysize -= strlen($body);
			$body = "";
			$result = array("success" => true, "rawsendsize" => 0, "rawsendheadersize" => $rawheadersize, "rawrecvsize" => 0, "rawrecvheadersize" => 0, "startts" => $startts);
			$debug = (isset($options["debug"]) && $options["debug"]);
			if ($debug)
			{
				$result["rawsend"] = "";
				$result["rawrecv"] = "";
			}

			if ($timeout !== false && self::GetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => self::HTTPTranslate("HTTP timeout exceeded."), "errorcode" => "timeout_exceeded");

			// Connect to the target server.
			$errornum = 0;
			$errorstr = "";
			if (isset($options["fp"]) && is_resource($options["fp"]))
			{
				$fp = $options["fp"];
				unset($options["fp"]);

				$useproxy = false;
				$proxyconnect = false;
				$proxydata = "";
			}
			else if ($useproxy)
			{
				if (!isset($options["proxyconnecttimeout"]))  $options["proxyconnecttimeout"] = 10;
				$timeleft = self::GetTimeLeft($startts, $timeout);
				if ($timeleft !== false)  $options["proxyconnecttimeout"] = min($options["proxyconnecttimeout"], $timeleft);
				if (!function_exists("stream_socket_client"))
				{
					if ($debug)  $fp = fsockopen($proxyprotocol . "://" . $proxyhost, $proxyport, $errornum, $errorstr, $options["proxyconnecttimeout"]);
					else  $fp = @fsockopen($proxyprotocol . "://" . $proxyhost, $proxyport, $errornum, $errorstr, $options["proxyconnecttimeout"]);
				}
				else
				{
					$context = @stream_context_create();
					if (isset($options["source_ip"]))  $context["socket"] = array("bindto" => $options["source_ip"] . ":0");
					if ($proxysecure)
					{
						if (!isset($options["proxysslopts"]) || !is_array($options["proxysslopts"]))  $options["proxysslopts"] = self::GetSafeSSLOpts();
						self::ProcessSSLOptions($options, "proxysslopts", $host);
						foreach ($options["proxysslopts"] as $key => $val)  @stream_context_set_option($context, "ssl", $key, $val);
					}
					else if ($secure)
					{
						if (!isset($options["sslopts"]) || !is_array($options["sslopts"]))
						{
							$options["sslopts"] = self::GetSafeSSLOpts();
							$options["sslopts"]["auto_peer_name"] = true;
						}

						self::ProcessSSLOptions($options, "sslopts", $host);
						foreach ($options["sslopts"] as $key => $val)  @stream_context_set_option($context, "ssl", $key, $val);
					}

					if ($debug)  $fp = stream_socket_client($proxyprotocol . "://" . $proxyhost . ":" . $proxyport, $errornum, $errorstr, $options["proxyconnecttimeout"], ($async ? STREAM_CLIENT_ASYNC_CONNECT : STREAM_CLIENT_CONNECT), $context);
					else  $fp = @stream_socket_client($proxyprotocol . "://" . $proxyhost . ":" . $proxyport, $errornum, $errorstr, $options["proxyconnecttimeout"], ($async ? STREAM_CLIENT_ASYNC_CONNECT : STREAM_CLIENT_CONNECT), $context);
				}

				if ($fp === false)  return array("success" => false, "error" => self::HTTPTranslate("Unable to establish a connection to '%s'.", ($proxysecure ? $proxyprotocol . "://" : "") . $proxyhost . ":" . $proxyport), "info" => $errorstr . " (" . $errornum . ")", "errorcode" => "proxy_connect");
			}
			else
			{
				if (!isset($options["connecttimeout"]))  $options["connecttimeout"] = 10;
				$timeleft = self::GetTimeLeft($startts, $timeout);
				if ($timeleft !== false)  $options["connecttimeout"] = min($options["connecttimeout"], $timeleft);
				if (!function_exists("stream_socket_client"))
				{
					if ($debug)  $fp = fsockopen($protocol . "://" . $host, $port, $errornum, $errorstr, $options["connecttimeout"]);
					else  $fp = @fsockopen($protocol . "://" . $host, $port, $errornum, $errorstr, $options["connecttimeout"]);
				}
				else
				{
					$context = @stream_context_create();
					if (isset($options["source_ip"]))  $context["socket"] = array("bindto" => $options["source_ip"] . ":0");
					if ($secure)
					{
						if (!isset($options["sslopts"]) || !is_array($options["sslopts"]))  $options["sslopts"] = self::GetSafeSSLOpts();
						self::ProcessSSLOptions($options, "sslopts", $host);
						foreach ($options["sslopts"] as $key => $val)  @stream_context_set_option($context, "ssl", $key, $val);
					}

					if ($debug)  $fp = stream_socket_client($protocol . "://" . $host . ":" . $port, $errornum, $errorstr, $options["connecttimeout"], ($async ? STREAM_CLIENT_ASYNC_CONNECT : STREAM_CLIENT_CONNECT), $context);
					else $fp = @stream_socket_client($protocol . "://" . $host . ":" . $port, $errornum, $errorstr, $options["connecttimeout"], ($async ? STREAM_CLIENT_ASYNC_CONNECT : STREAM_CLIENT_CONNECT), $context);
				}

				if ($fp === false)  return array("success" => false, "error" => self::HTTPTranslate("Unable to establish a connection to '%s'.", ($secure ? $protocol . "://" : "") . $host . ":" . $port), "info" => $errorstr . " (" . $errornum . ")", "errorcode" => "connect_failed");
			}

			if (function_exists("stream_set_blocking"))  @stream_set_blocking($fp, ($async ? 0 : 1));

			// Initialize the connection request state array.
			$state = array(
				"fp" => $fp,
				"type" => "request",
				"async" => $async,
				"debug" => $debug,
				"startts" => $startts,
				"timeout" => $timeout,
				"waituntil" => -1.0,
				"mime" => $mime,
				"data" => $data,
				"bodysize" => $bodysize,
				"chunked" => ($bodysize === false),
				"secure" => $secure,
				"useproxy" => $useproxy,
				"proxysecure" => $proxysecure,
				"proxyconnect" => $proxyconnect,
				"proxydata" => $proxydata,
				"currentfile" => false,

				"state" => "connecting",

				"options" => $options,
				"result" => $result,
				"close" => ($options["headers"]["Connection"] === "close"),
				"nextread" => "",
				"client" => true
			);

			// Return the state for async calls.  Caller must call ProcessState().
			if ($state["async"])  return array("success" => true, "state" => $state);

			// Run through all of the valid states and return the result.
			return self::ProcessState($state);
		}
	}
?>