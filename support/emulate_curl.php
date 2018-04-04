<?php
	// CubicleSoft PHP HTTP cURL emulation functions.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	// cURL HTTP emulation support requires:
	//   The CubicleSoft PHP HTTP functions.
	//   The CubicleSoft Web Browser state emulation class.
	if (!function_exists("curl_init"))
	{
		if (!class_exists("HTTP", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/http.php";

		global $curl_error__map, $curl_init__map, $curl_multi_init__map;

		// Constants based on PHP 5.4.0 and libcurl 7.25.0.
		$curl_error__map = array(
			0 => "CURLE_OK",
			1 => "CURLE_UNSUPPORTED_PROTOCOL",
			2 => "CURLE_FAILED_INIT",
			3 => "CURLE_URL_MALFORMAT",
			4 => "CURLE_NOT_BUILT_IN",
			5 => "CURLE_COULDNT_RESOLVE_PROXY",
			6 => "CURLE_COULDNT_RESOLVE_HOST",
			7 => "CURLE_COULDNT_CONNECT",
			8 => "CURLE_FTP_WEIRD_SERVER_REPLY",
			9 => "CURLE_REMOTE_ACCESS_DENIED",
			10 => "CURLE_FTP_ACCEPT_FAILED",
			11 => "CURLE_FTP_WEIRD_PASS_REPLY",
			12 => "CURLE_FTP_ACCEPT_TIMEOUT",
			13 => "CURLE_FTP_WEIRD_PASV_REPLY",
			14 => "CURLE_FTP_WEIRD_227_FORMAT",
			15 => "CURLE_FTP_CANT_GET_HOST",
			17 => "CURLE_FTP_COULDNT_SET_TYPE",
			18 => "CURLE_PARTIAL_FILE",
			19 => "CURLE_FTP_COULDNT_RETR_FILE",
			21 => "CURLE_QUOTE_ERROR",
			22 => "CURLE_HTTP_RETURNED_ERROR",
			23 => "CURLE_WRITE_ERROR",
			25 => "CURLE_UPLOAD_FAILED",
			26 => "CURLE_READ_ERROR",
			27 => "CURLE_OUT_OF_MEMORY",
			28 => "CURLE_OPERATION_TIMEDOUT",
			30 => "CURLE_FTP_PORT_FAILED",
			31 => "CURLE_FTP_COULDNT_USE_REST",
			33 => "CURLE_RANGE_ERROR",
			34 => "CURLE_HTTP_POST_ERROR",
			35 => "CURLE_SSL_CONNECT_ERROR",
			36 => "CURLE_BAD_DOWNLOAD_RESUME",
			37 => "CURLE_FILE_COULDNT_READ_FILE",
			38 => "CURLE_LDAP_CANNOT_BIND",
			39 => "CURLE_LDAP_SEARCH_FAILED",
			41 => "CURLE_FUNCTION_NOT_FOUND",
			42 => "CURLE_ABORTED_BY_CALLBACK",
			43 => "CURLE_BAD_FUNCTION_ARGUMENT",
			45 => "CURLE_INTERFACE_FAILED",
			47 => "CURLE_TOO_MANY_REDIRECTS",
			48 => "CURLE_UNKNOWN_OPTION",
			49 => "CURLE_TELNET_OPTION_SYNTAX",
			51 => "CURLE_PEER_FAILED_VERIFICATION",
			52 => "CURLE_GOT_NOTHING",
			53 => "CURLE_SSL_ENGINE_NOTFOUND",
			54 => "CURLE_SSL_ENGINE_SETFAILED",
			55 => "CURLE_SEND_ERROR",
			56 => "CURLE_RECV_ERROR",
			58 => "CURLE_SSL_CERTPROBLEM",
			59 => "CURLE_SSL_CIPHER",
			60 => "CURLE_SSL_CACERT",
			61 => "CURLE_BAD_CONTENT_ENCODING",
			62 => "CURLE_LDAP_INVALID_URL",
			63 => "CURLE_FILESIZE_EXCEEDED",
			64 => "CURLE_USE_SSL_FAILED",
			65 => "CURLE_SEND_FAIL_REWIND",
			66 => "CURLE_SSL_ENGINE_INITFAILED",
			67 => "CURLE_LOGIN_DENIED",
			68 => "CURLE_TFTP_NOTFOUND",
			69 => "CURLE_TFTP_PERM",
			70 => "CURLE_REMOTE_DISK_FULL",
			71 => "CURLE_TFTP_ILLEGAL",
			72 => "CURLE_TFTP_UNKNOWNID",
			73 => "CURLE_REMOTE_FILE_EXISTS",
			74 => "CURLE_TFTP_NOSUCHUSER",
			75 => "CURLE_CONV_FAILED",
			76 => "CURLE_CONV_REQD",
			77 => "CURLE_SSL_CACERT_BADFILE",
			78 => "CURLE_REMOTE_FILE_NOT_FOUND",
			79 => "CURLE_SSH",
			80 => "CURLE_SSL_SHUTDOWN_FAILED",
			81 => "CURLE_AGAIN",
			82 => "CURLE_SSL_CRL_BADFILE",
			83 => "CURLE_SSL_ISSUER_ERROR",
			84 => "CURLE_FTP_PRET_FAILED",
			85 => "CURLE_RTSP_CSEQ_ERROR",
			86 => "CURLE_RTSP_SESSION_ERROR",
			87 => "CURLE_FTP_BAD_FILE_LIST",
			88 => "CURLE_CHUNK_FAILED",
		);

		// Define constants in the same order as the official PHP cURL extension with the same integer values.
		// Additional defines exist in some locations because they will likely be defined in a future version of PHP.
		// DO NOT rely on these constants existing in the official PHP extension!

		// Constants for curl_setopt().
		define("CURLOPT_IPRESOLVE", 113);
		define("CURL_IPRESOLVE_WHATEVER", 0);
		define("CURL_IPRESOLVE_V4", 1);
		define("CURL_IPRESOLVE_V6", 2);
		define("CURLOPT_DNS_USE_GLOBAL_CACHE", 91);  // DEPRECATED, do not use!
		define("CURLOPT_DNS_CACHE_TIMEOUT", 92);
		define("CURLOPT_PORT", 3);
		define("CURLOPT_FILE", 10001);
		define("CURLOPT_READDATA", 10009);
		define("CURLOPT_INFILE", 10009);
		define("CURLOPT_INFILESIZE", 14);
		define("CURLOPT_URL", 10002);
		define("CURLOPT_PROXY", 10004);
		define("CURLOPT_VERBOSE", 41);
		define("CURLOPT_HEADER", 42);
		define("CURLOPT_HTTPHEADER", 10023);
		define("CURLOPT_NOPROGRESS", 43);
		define("CURLOPT_PROGRESSFUNCTION", 20056);
		define("CURLOPT_NOBODY", 44);
		define("CURLOPT_FAILONERROR", 45);
		define("CURLOPT_UPLOAD", 46);
		define("CURLOPT_POST", 47);
		define("CURLOPT_FTPLISTONLY", 48);
		define("CURLOPT_FTPAPPEND", 50);
		define("CURLOPT_NETRC", 51);
		define("CURLOPT_FOLLOWLOCATION", 52);
		define("CURLOPT_PUT", 54);
		define("CURLOPT_USERPWD", 10005);
		define("CURLOPT_PROXYUSERPWD", 10006);
		define("CURLOPT_RANGE", 10007);
		define("CURLOPT_TIMEOUT", 13);
		define("CURLOPT_TIMEOUT_MS", 155);
		define("CURLOPT_POSTFIELDS", 10015);
		define("CURLOPT_REFERER", 10016);
		define("CURLOPT_USERAGENT", 10018);
		define("CURLOPT_FTPPORT", 10017);
		define("CURLOPT_FTP_USE_EPSV", 85);
		define("CURLOPT_LOW_SPEED_LIMIT", 19);
		define("CURLOPT_LOW_SPEED_TIME", 20);
		define("CURLOPT_RESUME_FROM", 21);
		define("CURLOPT_COOKIE", 10022);
		define("CURLOPT_COOKIESESSION", 96);
		define("CURLOPT_AUTOREFERER", 58);
		define("CURLOPT_SSLCERT", 10025);
		define("CURLOPT_SSLCERTPASSWD", 10026);
		define("CURLOPT_WRITEHEADER", 10029);
		define("CURLOPT_SSL_VERIFYHOST", 81);
		define("CURLOPT_COOKIEFILE", 10031);
		define("CURLOPT_SSLVERSION", 32);
		define("CURLOPT_TIMECONDITION", 33);
		define("CURLOPT_TIMEVALUE", 34);
		define("CURLOPT_CUSTOMREQUEST", 10036);
		define("CURLOPT_STDERR", 10037);
		define("CURLOPT_TRANSFERTEXT", 53);
		define("CURLOPT_RETURNTRANSFER", 19913);
		define("CURLOPT_QUOTE", 10028);
		define("CURLOPT_POSTQUOTE", 10039);
		define("CURLOPT_INTERFACE", 10062);
		define("CURLOPT_KRB4LEVEL", 10063);
		define("CURLOPT_HTTPPROXYTUNNEL", 61);
		define("CURLOPT_FILETIME", 69);
		define("CURLOPT_WRITEFUNCTION", 20011);
		define("CURLOPT_READFUNCTION", 20012);
		define("CURLOPT_HEADERFUNCTION", 20079);
		define("CURLOPT_MAXREDIRS", 68);
		define("CURLOPT_MAXCONNECTS", 71);
		define("CURLOPT_CLOSEPOLICY", 72);
		define("CURLOPT_FRESH_CONNECT", 74);
		define("CURLOPT_FORBID_REUSE", 75);
		define("CURLOPT_RANDOM_FILE", 10076);
		define("CURLOPT_EGDSOCKET", 10077);
		define("CURLOPT_CONNECTTIMEOUT", 78);
		define("CURLOPT_CONNECTTIMEOUT_MS", 156);
		define("CURLOPT_SSL_VERIFYPEER", 64);
		define("CURLOPT_CAINFO", 10065);
		define("CURLOPT_CAPATH", 10097);
		define("CURLOPT_COOKIEJAR", 10082);
		define("CURLOPT_SSL_CIPHER_LIST", 10083);
		define("CURLOPT_BINARYTRANSFER", 19914);
		define("CURLOPT_NOSIGNAL", 99);
		define("CURLOPT_PROXYTYPE", 101);
		define("CURLOPT_BUFFERSIZE", 98);
		define("CURLOPT_HTTPGET", 80);
		define("CURLOPT_HTTP_VERSION", 84);
		define("CURLOPT_SSLKEY", 10087);
		define("CURLOPT_SSLKEYTYPE", 10088);
		define("CURLOPT_SSLKEYPASSWD", 10026);
		define("CURLOPT_SSLENGINE", 10089);
		define("CURLOPT_SSLENGINE_DEFAULT", 90);
		define("CURLOPT_SSLCERTTYPE", 10086);
		define("CURLOPT_CRLF", 27);
		define("CURLOPT_ENCODING", 10102);
		define("CURLOPT_PROXYPORT", 59);
		define("CURLOPT_UNRESTRICTED_AUTH", 105);
		define("CURLOPT_FTP_USE_EPRT", 106);
		define("CURLOPT_TCP_NODELAY", 121);
		define("CURLOPT_HTTP200ALIASES", 10104);
		define("CURL_TIMECOND_NONE", 0);
		define("CURL_TIMECOND_IFMODSINCE", 1);
		define("CURL_TIMECOND_IFUNMODSINCE", 2);
		define("CURL_TIMECOND_LASTMOD", 3);
		define("CURLOPT_MAX_RECV_SPEED_LARGE", 30146);
		define("CURLOPT_MAX_SEND_SPEED_LARGE", 30145);
		define("CURLOPT_HTTPAUTH", 107);
		define("CURLAUTH_NONE", 0);
		define("CURLAUTH_BASIC", 1);
		define("CURLAUTH_DIGEST", 2);
		define("CURLAUTH_GSSNEGOTIATE", 4);
		define("CURLAUTH_NTLM", 8);
		define("CURLAUTH_DIGEST_IE", 16);
		define("CURLAUTH_NTLM_WB", 32);
		define("CURLAUTH_ANY", -17);
		define("CURLAUTH_ANYSAFE", -18);
		define("CURLOPT_PROXYAUTH", 111);
		define("CURLOPT_FTP_CREATE_MISSING_DIRS", 110);
		define("CURLOPT_PRIVATE", 10103);

		// Constants effecting the way CURLOPT_CLOSEPOLICY works.
		define("CURLCLOSEPOLICY_LEAST_RECENTLY_USED", 2);
		define("CURLCLOSEPOLICY_LEAST_TRAFFIC", 3);
		define("CURLCLOSEPOLICY_SLOWEST", 4);
		define("CURLCLOSEPOLICY_CALLBACK", 5);
		define("CURLCLOSEPOLICY_OLDEST", 1);

		// Info constants.
		define("CURLINFO_EFFECTIVE_URL", 0x100000 + 1);
		define("CURLINFO_HTTP_CODE", 0x200000 + 2);
		define("CURLINFO_RESPONSE_CODE", 0x200000 + 2);
		define("CURLINFO_HEADER_SIZE", 0x200000 + 11);
		define("CURLINFO_REQUEST_SIZE", 0x200000 + 12);
		define("CURLINFO_TOTAL_TIME", 0x300000 + 3);
		define("CURLINFO_NAMELOOKUP_TIME", 0x300000 + 4);
		define("CURLINFO_CONNECT_TIME", 0x300000 + 5);
		define("CURLINFO_PRETRANSFER_TIME", 0x300000 + 6);
		define("CURLINFO_SIZE_UPLOAD", 0x300000 + 7);
		define("CURLINFO_SIZE_DOWNLOAD", 0x300000 + 8);
		define("CURLINFO_SPEED_DOWNLOAD", 0x300000 + 9);
		define("CURLINFO_SPEED_UPLOAD", 0x300000 + 10);
		define("CURLINFO_FILETIME", 0x200000 + 14);
		define("CURLINFO_SSL_VERIFYRESULT", 0x200000 + 14);
		define("CURLINFO_CONTENT_LENGTH_DOWNLOAD", 0x300000 + 15);
		define("CURLINFO_CONTENT_LENGTH_UPLOAD", 0x300000 + 16);
		define("CURLINFO_STARTTRANSFER_TIME", 0x300000 + 17);
		define("CURLINFO_CONTENT_TYPE", 0x100000 + 18);
		define("CURLINFO_REDIRECT_TIME", 0x300000 + 19);
		define("CURLINFO_REDIRECT_COUNT", 0x200000 + 20);
		define("CURLINFO_HEADER_OUT", 2);
		define("CURLINFO_PRIVATE", 0x100000 + 21);
		define("CURLINFO_CERTINFO", 0x400000 + 34);
		define("CURLINFO_REDIRECT_URL", 0x100000 + 31);

		// cURL compile-time constants (curl_version).
		define("CURL_VERSION_IPV6", 1);
		define("CURL_VERSION_KERBEROS4", 2);
		define("CURL_VERSION_SSL", 4);
		define("CURL_VERSION_LIBZ", 8);
		define("CURL_VERSION_NTLM", 16);
		define("CURL_VERSION_GSSNEGOTIATE", 32);
		define("CURL_VERSION_DEBUG", 64);
		define("CURL_VERSION_ASYNCHDNS", 128);
		define("CURL_VERSION_SPNEGO", 256);
		define("CURL_VERSION_LARGEFILE", 512);
		define("CURL_VERSION_IDN", 1024);
		define("CURL_VERSION_SSPI", 2048);
		define("CURL_VERSION_CONV", 4096);
		define("CURL_VERSION_CURLDEBUG", 8192);
		define("CURL_VERSION_TLSAUTH_SRP", 16384);
		define("CURL_VERSION_NTLM_WB", 32768);

		// Version constants.
		define("CURLVERSION_NOW", 3);

		// Error constants.
		foreach ($curl_error__map as $num => $name)
		{
			if (!defined($name))  define($name, $num);
		}

		// Dear PHP devs:  Comment your code.  Thanks.
		define("CURLPROXY_HTTP", 0);
		define("CURLPROXY_HTTP_1_0", 1);
		define("CURLPROXY_SOCKS4", 4);
		define("CURLPROXY_SOCKS5", 5);
		define("CURLPROXY_SOCKS4A", 6);
		define("CURLPROXY_SOCKS5_HOSTNAME", 7);

		define("CURL_NETRC_OPTIONAL", 1);
		define("CURL_NETRC_IGNORED", 0);
		define("CURL_NETRC_REQUIRED", 2);

		define("CURL_HTTP_VERSION_NONE", 0);
		define("CURL_HTTP_VERSION_1_0", 1);
		define("CURL_HTTP_VERSION_1_1", 2);

		define("CURLM_CALL_MULTI_PERFORM", -1);
		define("CURLM_OK", 0);
		define("CURLM_BAD_HANDLE", 1);
		define("CURLM_BAD_EASY_HANDLE", 2);
		define("CURLM_OUT_OF_MEMORY", 3);
		define("CURLM_INTERNAL_ERROR", 4);
		define("CURLM_BAD_SOCKET", 5);
		define("CURLM_UNKNOWN_OPTION", 6);

		define("CURLMSG_DONE", 1);

		define("CURLOPT_FTPSSLAUTH", 129);
		define("CURLFTPAUTH_DEFAULT", 0);
		define("CURLFTPAUTH_SSL", 1);
		define("CURLFTPAUTH_TLS", 2);
		define("CURLOPT_FTP_SSL", 119);
		define("CURLFTPSSL_NONE", 0);
		define("CURLFTPSSL_TRY", 1);
		define("CURLFTPSSL_CONTROL", 2);
		define("CURLFTPSSL_ALL", 3);
		define("CURLUSESSL_NONE", 0);
		define("CURLUSESSL_TRY", 1);
		define("CURLUSESSL_CONTROL", 2);
		define("CURLUSESSL_ALL", 3);

		define("CURLOPT_CERTINFO", 172);
		define("CURLOPT_POSTREDIR", 161);

		define("CURLSSH_AUTH_ANY", -1);
		define("CURLSSH_AUTH_NONE", 0);
		define("CURLSSH_AUTH_PUBLICKEY", 1);
		define("CURLSSH_AUTH_PASSWORD", 2);
		define("CURLSSH_AUTH_HOST", 4);
		define("CURLSSH_AUTH_KEYBOARD", 8);
		define("CURLSSH_AUTH_DEFAULT", -1);
		define("CURLOPT_SSH_AUTH_TYPES", 151);
		define("CURLOPT_KEYPASSWD", 10026);
		define("CURLOPT_SSH_PUBLIC_KEYFILE", 10152);
		define("CURLOPT_SSH_PRIVATE_KEYFILE", 10153);
		define("CURLOPT_SSH_HOST_PUBLIC_KEY_MD5", 10162);

		define("CURLOPT_REDIR_PROTOCOLS", 182);
		define("CURLOPT_PROTOCOLS", 181);
		define("CURLPROTO_HTTP", 1);
		define("CURLPROTO_HTTPS", 2);
		define("CURLPROTO_FTP", 4);
		define("CURLPROTO_FTPS", 8);
		define("CURLPROTO_SCP", 16);
		define("CURLPROTO_SFTP", 32);
		define("CURLPROTO_TELNET", 64);
		define("CURLPROTO_LDAP", 128);
		define("CURLPROTO_LDAPS", 256);
		define("CURLPROTO_DICT", 512);
		define("CURLPROTO_FILE", 1024);
		define("CURLPROTO_TFTP", 2048);
		define("CURLPROTO_ALL", -1);

		define("CURLOPT_FTP_FILEMETHOD", 138);
		define("CURLOPT_FTP_SKIP_PASV_IP", 137);

		define("CURLFTPMETHOD_DEFAULT", 0);
		define("CURLFTPMETHOD_MULTICWD", 1);
		define("CURLFTPMETHOD_NOCWD", 2);
		define("CURLFTPMETHOD_SINGLECWD", 3);

		// Emulation internal use ONLY.  DO NOT USE!
		define("CURLOPT_DEBUGFUNCTION", 20094);
		define("CURLOPT_DEBUGDATA", 10095);

		// Internal functions used by the public emulation routines.
		$curl_init__map = array();
		function get_curl_init_key($ch)
		{
			ob_start();
			echo $ch;
			ob_end_clean();

			return ob_get_contents();
		}

		function get_check_curl_init_key($ch)
		{
			global $curl_init__map;

			$key = get_curl_init_key($ch);
			if (!isset($curl_init__map[$key]))  throw new Exception(HTTP::HTTPTranslate("cURL Emulator:  Unable to find key mapping for resource."));

			return $key;
		}

		// Public emulation functions.
		function curl_version($age = CURLVERSION_NOW)
		{
			$curlversion = "7.25.0";
			$curlvernum = explode(".", $curlversion);

			$result = array(
				"version_number" => (($curlvernum[0] << 16) | ($curlvernum[1] << 8) | $curlvernum[2]),
				"age" => $age,
				"features" => CURL_VERSION_IPV6 | (defined("OPENSSL_VERSION_TEXT") ? CURL_VERSION_SSL : 0) | CURL_VERSION_DEBUG | CURL_VERSION_CURLDEBUG | CURL_VERSION_LARGEFILE | CURL_VERSION_IDN | CURL_VERSION_CONV,
				"ssl_version_number" => 0,
				"version" => $curlversion,
				"host" => "i386-pc-win32",
				"ssl_version" => (defined("OPENSSL_VERSION_TEXT") ? implode("/", array_slice(explode(" ", OPENSSL_VERSION_TEXT), 0, 2)) : ""),
				"libz_version" => "",
				"protocols" => array("http", "https")
			);

			return $result;
		}

		function curl_init($url = false)
		{
			global $curl_init__map;

			// Evil hack to create a "resource" so that is_resource() works.
			// get_resource_type() will reveal its true identity but only an idiot would ever call that function.
			$ch = fopen(__FILE__, "rb");
			$key = get_curl_init_key($ch);
			$options = array(
				CURLOPT_NOPROGRESS => true,
				CURLOPT_VERBOSE => false,
				CURLOPT_DNS_USE_GLOBAL_CACHE => true,
				CURLOPT_DNS_CACHE_TIMEOUT => 120,
				CURLOPT_MAXREDIRS => 20,
				CURLOPT_URL => $url
			);

			if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

			$curl_init__map[$key] = array("self" => $ch, "method" => "GET", "options" => $options, "browser" => new WebBrowser(), "errorno" => CURLE_OK, "errorinfo" => "");

			return $ch;
		}

		function curl_errno($ch)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);

			return $curl_init__map[$key]["errorno"];
		}

		function curl_error($ch)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);

			return ($curl_init__map[$key]["errorinfo"] == "" ? "" : $curl_error__map[$curl_init__map[$key]["errorno"]] . " - " . $curl_init__map[$key]["errorinfo"]);
		}

		function curl_copy_handle($ch)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);

			$ch = fopen(__FILE__, "rb");
			$key2 = get_curl_init_key($resource);
			$curl_init__map[$key2] = $curl_init__map[$key];

			return $ch;
		}

		function curl_close($ch)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);
			unset($curl_init__map[$key]);
			fclose($ch);
		}

		function curl_setopt($ch, $option, $value)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);

			if ($option != CURLINFO_HEADER_OUT)
			{
				if ($value === null)  unset($curl_init__map[$key]["options"][$option]);
				else
				{
					if ($option == CURLOPT_HTTPGET && $value)  $curl_init__map[$key]["method"] = "GET";
					else if ($option == CURLOPT_NOBODY && $value)  $curl_init__map[$key]["method"] = "HEAD";
					else if ($option == CURLOPT_POST && $value)  $curl_init__map[$key]["method"] = "POST";
					else if ($option == CURLOPT_PUT && $value)  $curl_init__map[$key]["method"] = "PUT";
					else if ($option == CURLOPT_CUSTOMREQUEST)  $curl_init__map[$key]["method"] = $value;

					$curl_init__map[$key]["options"][$option] = $value;
				}
			}
			else if ((bool)$value)
			{
				$curl_init__map[$key]["options"]["__CURLINFO_HEADER_OUT"] = true;
			}
			else
			{
				unset($curl_init__map[$key]["options"]["__CURLINFO_HEADER_OUT"]);
			}

			$curl_init__map[$key]["errorno"] = CURLE_OK;
			$curl_init__map[$key]["errorinfo"] = "";

			return true;
		}

		function curl_setopt_array($ch, $options)
		{
			foreach ($options as $option => $value)
			{
				if (!curl_setopt($ch, $option, $value))  return false;
			}

			return true;
		}

		function curl_exec($ch)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);

			// Set allowed protocols.
			$allowedprotocols = array("http" => true, "https" => true);
			if (isset($curl_init__map[$key]["options"][CURLOPT_PROTOCOLS]))
			{
				$allowedprotocols["http"] = (bool)($curl_init__map[$key]["options"][CURLOPT_PROTOCOLS] & CURLPROTO_HTTP);
				$allowedprotocols["https"] = (bool)($curl_init__map[$key]["options"][CURLOPT_PROTOCOLS] & CURLPROTO_HTTPS);
			}
			$curl_init__map[$key]["browser"]->SetState(array("allowedprotocols" => $allowedprotocols));

			// Set allowed redirect protocols.
			$allowedprotocols = array("http" => true, "https" => true);
			if (isset($curl_init__map[$key]["options"][CURLOPT_REDIR_PROTOCOLS]))
			{
				$allowedprotocols["http"] = (bool)($curl_init__map[$key]["options"][CURLOPT_REDIR_PROTOCOLS] & CURLPROTO_HTTP);
				$allowedprotocols["https"] = (bool)($curl_init__map[$key]["options"][CURLOPT_REDIR_PROTOCOLS] & CURLPROTO_HTTPS);
			}
			$curl_init__map[$key]["browser"]->SetState(array("allowedredirprotocols" => $allowedprotocols));

			// Load cookies.  Violates the PHP/cURL definition a lot.  Whatever.
			if (isset($curl_init__map[$key]["options"][CURLOPT_COOKIEFILE]) && is_string($curl_init__map[$key]["options"][CURLOPT_COOKIEFILE]) && $curl_init__map[$key]["options"][CURLOPT_COOKIEFILE] != "")
			{
				$data = @unserialize(@file_get_contents($curl_init__map[$key]["options"][CURLOPT_COOKIEFILE]));
				if ($data !== false && is_array($data))
				{
					// Load the WebBrowser() object with the cookies.
					$curl_init__map[$key]["browser"]->SetState(array("cookies" => $data));
				}
				$curl_init__map[$key]["options"][CURLOPT_COOKIEFILE] = "";
			}
			if (isset($curl_init__map[$key]["options"][CURLOPT_COOKIESESSION]) && $curl_init__map[$key]["options"][CURLOPT_COOKIESESSION])  $curl_init__map[$key]["browser"]->DeleteSessionCookies();

			// Set the autoreferer setting.
			$curl_init__map[$key]["browser"]->SetState(array("autoreferer" => (isset($curl_init__map[$key]["options"][CURLOPT_AUTOREFERER]) && $curl_init__map[$key]["options"][CURLOPT_AUTOREFERER])));

			// Set the Referer.
			if (isset($curl_init__map[$key]["options"][CURLOPT_REFERER]) && is_string($curl_init__map[$key]["options"][CURLOPT_REFERER]))
			{
				$curl_init__map[$key]["browser"]->SetState(array("referer" => $curl_init__map[$key]["options"][CURLOPT_REFERER]));
			}

			// Set the followlocation and maxfollow settings.
			$curl_init__map[$key]["browser"]->SetState(array("followlocation" => (isset($curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION]) && $curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION])));
			$curl_init__map[$key]["browser"]->SetState(array("maxfollow" => (isset($curl_init__map[$key]["options"][CURLOPT_MAXREDIRS]) ? $curl_init__map[$key]["options"][CURLOPT_MAXREDIRS] : 20)));

			// Set up the options array.
			$options = array();

			// Set connect and total timeout options.
			if (isset($curl_init__map[$key]["options"][CURLOPT_CONNECTTIMEOUT]) || isset($curl_init__map[$key]["options"][CURLOPT_CONNECTTIMEOUT_MS]))
			{
				$timeout = (isset($curl_init__map[$key]["options"][CURLOPT_CONNECTTIMEOUT]) ? $curl_init__map[$key]["options"][CURLOPT_CONNECTTIMEOUT] : 0) + ((isset($curl_init__map[$key]["options"][CURLOPT_CONNECTTIMEOUT_MS]) ? $curl_init__map[$key]["options"][CURLOPT_CONNECTTIMEOUT_MS] : 0) / 1000);
				if ($timeout > 0)
				{
					$options["connecttimeout"] = $timeout;
					$options["proxyconnecttimeout"] = $timeout;
				}
			}
			if (isset($curl_init__map[$key]["options"][CURLOPT_TIMEOUT]) || isset($curl_init__map[$key]["options"][CURLOPT_TIMEOUT_MS]))
			{
				$timeout = (isset($curl_init__map[$key]["options"][CURLOPT_TIMEOUT]) ? $curl_init__map[$key]["options"][CURLOPT_TIMEOUT] : 0) + ((isset($curl_init__map[$key]["options"][CURLOPT_TIMEOUT_MS]) ? $curl_init__map[$key]["options"][CURLOPT_TIMEOUT_MS] : 0) / 1000);
				if ($timeout > 0)
				{
					$options["connecttimeout"] = $timeout;
					$options["proxyconnecttimeout"] = $timeout;
				}
			}

			// Set proxy options.
			if (isset($curl_init__map[$key]["options"][CURLOPT_PROXY]))
			{
				if (isset($curl_init__map[$key]["options"][CURLOPT_PROXYTYPE]) && $curl_init__map[$key]["options"][CURLOPT_PROXYTYPE] != CURLPROXY_HTTP)
				{
					$curl_init__map[$key]["errorno"] = CURLE_UNSUPPORTED_PROTOCOL;
					$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("CURLOPT_PROXYTYPE option is unsupported.");

					return false;
				}

				$proxyurl = $curl_init__map[$key]["options"][CURLOPT_PROXY];
				$proxyport = (int)(isset($curl_init__map[$key]["options"][CURLOPT_PROXYPORT]) ? $curl_init__map[$key]["options"][CURLOPT_PROXYPORT] : false);
				if ($proxyport < 1 || $proxyport > 65535)  $proxyport = false;
				if (strpos($proxyurl, "://") === false)  $proxyurl = ($proxyport == 443 ? "https://" : "http://") . $proxyurl;

				$proxyurl = HTTP::ExtractURL($proxyurl);
				if ($proxyport !== false)  $proxyurl["port"] = $proxyport;
				if (isset($curl_init__map[$key]["options"][CURLOPT_PROXYUSERPWD]))
				{
					$userpass = explode(":", $curl_init__map[$key]["options"][CURLOPT_PROXYUSERPWD]);
					if (count($userpass) == 2)
					{
						$proxyurl["loginusername"] = urldecode($userpass[0]);
						$proxyurl["loginpassword"] = urldecode($userpass[1]);
					}
				}
				$options["proxyurl"] = HTTP::CondenseURL($proxyurl);

				if (isset($curl_init__map[$key]["options"][CURLOPT_HTTPPROXYTUNNEL]))  $options["proxyconnect"] = $curl_init__map[$key]["options"][CURLOPT_HTTPPROXYTUNNEL];
			}

			// Set SSL options.
			$options["sslopts"] = array();
			$options["sslopts"]["verify_peer"] = (isset($curl_init__map[$key]["options"][CURLOPT_SSL_VERIFYPEER]) ? $curl_init__map[$key]["options"][CURLOPT_SSL_VERIFYPEER] : true);
			if (isset($curl_init__map[$key]["options"][CURLOPT_CAINFO]))  $options["sslopts"]["cafile"] = $curl_init__map[$key]["options"][CURLOPT_CAINFO];
			if (isset($curl_init__map[$key]["options"][CURLOPT_CAPATH]))  $options["sslopts"]["capath"] = $curl_init__map[$key]["options"][CURLOPT_CAPATH];
			if (!isset($options["sslopts"]["cafile"]) && !isset($options["sslopts"]["capath"]))  $options["sslopts"]["auto_cainfo"] = true;
			if (isset($curl_init__map[$key]["options"][CURLOPT_SSLCERT]) && isset($curl_init__map[$key]["options"][CURLOPT_SSLKEY]))
			{
				if ($curl_init__map[$key]["options"][CURLOPT_SSLCERT] !== $curl_init__map[$key]["options"][CURLOPT_SSLKEY])
				{
					$curl_init__map[$key]["errorno"] = CURLE_SSL_CONNECT_ERROR;
					$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("CURLOPT_SSLCERT and CURLOPT_SSLKEY must be identical.");

					return false;
				}
				$certpass = (isset($curl_init__map[$key]["options"][CURLOPT_SSLCERTPASSWD]) ? $curl_init__map[$key]["options"][CURLOPT_SSLCERTPASSWD] : false);
				$keypass = (isset($curl_init__map[$key]["options"][CURLOPT_SSLKEYPASSWD]) ? $curl_init__map[$key]["options"][CURLOPT_SSLKEYPASSWD] : false);
				if ($certpass !== $keypass)
				{
					$curl_init__map[$key]["errorno"] = CURLE_SSL_CONNECT_ERROR;
					$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("CURLOPT_SSLCERTPASSWD and CURLOPT_SSLKEYPASSWD must be identical.");

					return false;
				}
				$certtype = strtoupper(isset($curl_init__map[$key]["options"][CURLOPT_SSLCERTTYPE]) ? $curl_init__map[$key]["options"][CURLOPT_SSLCERTTYPE] : "PEM");
				$keytype = strtoupper(isset($curl_init__map[$key]["options"][CURLOPT_SSLKEYTYPE]) ? $curl_init__map[$key]["options"][CURLOPT_SSLKEYTYPE] : "PEM");
				if ($certpass !== $keypass || $cert !== "PEM")
				{
					$curl_init__map[$key]["errorno"] = CURLE_SSL_CONNECT_ERROR;
					$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("CURLOPT_SSLCERTTYPE and CURLOPT_SSLKEYTYPE must be PEM format.");

					return false;
				}

				$options["sslopts"]["local_cert"] = $curl_init__map[$key]["options"][CURLOPT_SSLCERT];
				if ($certpass !== false)  $options["sslopts"]["passphrase"] = $certpass;
			}
			if (isset($curl_init__map[$key]["options"][CURLOPT_SSL_CIPHER_LIST]))  $options["sslopts"]["ciphers"] = $curl_init__map[$key]["options"][CURLOPT_SSL_CIPHER_LIST];
			$options["sslopts"]["auto_cn_match"] = true;
			$options["sslopts"]["auto_sni"] = true;
			$options["sslopts"]["capture_peer_cert"] = (isset($curl_init__map[$key]["options"][CURLOPT_CERTINFO]) ? $curl_init__map[$key]["options"][CURLOPT_CERTINFO] : false);

			// Set the method.
			if (isset($curl_init__map[$key]["options"][CURLOPT_UPLOAD]) && (bool)$curl_init__map[$key]["options"][CURLOPT_UPLOAD])  $options["method"] = "PUT";
			else  $options["method"] = $curl_init__map[$key]["method"];

			// Set the HTTP version.
			if (isset($curl_init__map[$key]["options"][CURLOPT_HTTP_VERSION]))
			{
				if ($curl_init__map[$key]["options"][CURLOPT_HTTP_VERSION] == CURL_HTTP_VERSION_1_0)  $options["httpver"] = "1.0";
				else if ($curl_init__map[$key]["options"][CURLOPT_HTTP_VERSION] == CURL_HTTP_VERSION_1_1)  $options["httpver"] = "1.1";
			}

			// Set rate limits.
			if (isset($curl_init__map[$key]["options"][CURLOPT_MAX_RECV_SPEED_LARGE]))  $options["recvratelimit"] = $curl_init__map[$key]["options"][CURLOPT_MAX_RECV_SPEED_LARGE];
			if (isset($curl_init__map[$key]["options"][CURLOPT_MAX_SEND_SPEED_LARGE]))  $options["sendratelimit"] = $curl_init__map[$key]["options"][CURLOPT_MAX_SEND_SPEED_LARGE];

			// Set headers.
			$options["headers"] = array();
			$options["headers"]["Accept"] = "*/*";
			if (isset($curl_init__map[$key]["options"][CURLOPT_HTTPHEADER]) && is_array($curl_init__map[$key]["options"][CURLOPT_HTTPHEADER]))
			{
				foreach ($curl_init__map[$key]["options"][CURLOPT_HTTPHEADER] as $header)
				{
					$pos = strpos($header, ":");
					if ($pos !== false)
					{
						$val = ltrim(substr($header, $pos + 1));
						if ($val == "")  unset($options["headers"][HTTP::HeaderNameCleanup(substr($header, 0, $pos))]);
						else  $options["headers"][HTTP::HeaderNameCleanup(substr($header, 0, $pos))] = $val;
					}
				}
			}
			if (isset($curl_init__map[$key]["options"][CURLOPT_USERAGENT]))  $options["headers"]["User-Agent"] = $curl_init__map[$key]["options"][CURLOPT_USERAGENT];
			if (isset($curl_init__map[$key]["options"][CURLOPT_COOKIE]))  $options["headers"]["Cookie"] = $curl_init__map[$key]["options"][CURLOPT_COOKIE];
			if (isset($curl_init__map[$key]["options"][CURLOPT_RANGE]))  $options["headers"]["Range"] = "bytes=" . $curl_init__map[$key]["options"][CURLOPT_RANGE];
			if (isset($curl_init__map[$key]["options"][CURLOPT_RESUME_FROM]))  $options["headers"]["Range"] = "bytes=" . $curl_init__map[$key]["options"][CURLOPT_RESUME_FROM] . "-";
			if (isset($curl_init__map[$key]["options"][CURLOPT_TIMECONDITION]) && isset($curl_init__map[$key]["options"][CURLOPT_TIMEVALUE]))
			{
				if ($curl_init__map[$key]["options"][CURLOPT_TIMECONDITION] == CURL_TIMECOND_IFMODSINCE)  $options["headers"]["If-Modified-Since"] = gmdate("D, d M Y H:i:s", $curl_init__map[$key]["options"][CURLOPT_TIMEVALUE]) . " GMT";
				else if ($curl_init__map[$key]["options"][CURLOPT_TIMECONDITION] == CURL_TIMECOND_IFUNMODSINCE)  $options["headers"]["If-Unmodified-Since"] = gmdate("D, d M Y H:i:s", $curl_init__map[$key]["options"][CURLOPT_TIMEVALUE]) . " GMT";
			}

			// Set POST variables and files.
			if (isset($curl_init__map[$key]["options"][CURLOPT_POSTFIELDS]))
			{
				$postvars = $curl_init__map[$key]["options"][CURLOPT_POSTFIELDS];
				if (is_string($postvars))
				{
					$postvars2 = array();
					$postvars = explode("&", $postvars);
					foreach ($postvars as $postvar)
					{
						$pos = strpos($postvar, "=");
						if ($pos === false)
						{
							$name = urldecode($postvar);
							$val = "";
						}
						else
						{
							$name = urldecode(substr($postvar, 0, $pos));
							$val = urldecode(substr($postvar, $pos + 1));
						}

						if (!isset($postvars2[$name]))  $postvars2[$name] = array();
						$postvars2[$name][] = $val;
					}
					$postvars = $postvars2;
					unset($postvars2);
				}

				foreach ($postvars as $name => $vals)
				{
					if (is_string($vals) || is_numeric($vals))  $vals = array($vals);
					foreach ($vals as $num => $val)
					{
						// Move files to their own array.
						if (substr($val, 0, 1) == "@")
						{
							$pos = strrpos($val, ";type=");
							if ($pos === false)  $mimetype = "";
							else
							{
								$mimetype = substr($val, $pos + 6);
								$val = substr($val, 0, $pos);
							}

							$val = substr($val, 1);
							if (file_exists($val))
							{
								if (!isset($options["files"]))  $options["files"] = array();
								$options["files"][] = array(
									"name" => $name,
									"filename" => HTTP::FilenameSafe(HTTP::ExtractFilename($val)),
									"type" => $mimetype,
									"datafile" => $val
								);

								unset($vals[$num]);
							}
						}
					}

					if (!count($vals))  unset($postvars[$name]);
					else  $postvars[$name] = $vals;
				}

				$options["postvars"] = $postvars;
				$options["method"] = "POST";
			}

			// Process the URL.
			if (!isset($curl_init__map[$key]["options"][CURLOPT_URL]) || !is_string($curl_init__map[$key]["options"][CURLOPT_URL]) || $curl_init__map[$key]["options"][CURLOPT_URL] == "")
			{
				$curl_init__map[$key]["errorno"] = CURLE_URL_MALFORMAT;
				$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("No CURLOPT_URL option specified.");

				return false;
			}
			$url = HTTP::ExtractURL($curl_init__map[$key]["options"][CURLOPT_URL]);
			if ($url["scheme"] == "")
			{
				$curl_init__map[$key]["errorno"] = CURLE_URL_MALFORMAT;
				$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("CURLOPT_URL does not have a scheme.");

				return false;
			}
			if ($url["host"] == "")
			{
				$curl_init__map[$key]["errorno"] = CURLE_URL_MALFORMAT;
				$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("CURLOPT_URL does not specify a valid host.");

				return false;
			}
			if (isset($curl_init__map[$key]["options"][CURLOPT_PORT]) && (int)$curl_init__map[$key]["options"][CURLOPT_PORT] > 0 && (int)$curl_init__map[$key]["options"][CURLOPT_PORT] < 65536)
			{
				$url["port"] = (int)$curl_init__map[$key]["options"][CURLOPT_PORT];
			}
			if (isset($curl_init__map[$key]["options"][CURLOPT_USERPWD]))
			{
				$userpass = explode(":", $curl_init__map[$key]["options"][CURLOPT_USERPWD]);
				if (count($userpass) == 2)
				{
					$url["loginusername"] = urldecode($userpass[0]);
					$url["loginpassword"] = urldecode($userpass[1]);
				}
			}
			else if (isset($curl_init__map[$key]["options"][CURLOPT_NETRC]) && $curl_init__map[$key]["options"][CURLOPT_NETRC])
			{
				$data = @file_get_contents("~/.netrc");
				if ($data !== false)
				{
					$lines = explode("\n", $data);
					unset($data);
					$host = false;
					$user = false;
					$password = false;
					foreach ($lines as $line)
					{
						$line = trim($line);
						if (substr($line, 0, 8) == "machine ")  $host = trim(substr($line, 8));
						if (substr($line, 0, 6) == "login ")  $user = trim(substr($line, 6));
						if (substr($line, 0, 9) == "password ")  $password = trim(substr($line, 9));

						if ($host !== false && $user !== false && $password !== false)
						{
							if ($host === $url["host"] || (isset($options["headers"]["Host"]) && $host === $options["headers"]["Host"]))
							{
								$url["loginusername"] = $user;
								$url["loginpassword"] = $password;
							}

							$host = false;
							$user = false;
							$password = false;
						}
					}
					unset($lines);
				}
			}

			// Condense URL.
			$url = HTTP::CondenseURL($url);

			// Set up internal callbacks.
			$options["read_headers_callback"] = "internal_curl_read_headers_callback";
			$options["read_headers_callback_opts"] = $key;

			if (!isset($curl_init__map[$key]["options"][CURLOPT_NOBODY]) || !$curl_init__map[$key]["options"][CURLOPT_NOBODY])
			{
				$options["read_body_callback"] = "internal_curl_read_body_callback";
				$options["read_body_callback_opts"] = $key;
			}

			if ($options["method"] != "GET" && $options["method"] != "POST")
			{
				$options["write_body_callback"] = "internal_curl_write_body_callback";
				$options["write_body_callback_opts"] = $key;
			}

			$options["debug_callback"] = "internal_curl_debug_callback";
			$options["debug_callback_opts"] = $key;

			// Remove weird callback results.
			unset($curl_init__map[$key]["rawproxyheaders"]);
			unset($curl_init__map[$key]["rawheaders"]);
			unset($curl_init__map[$key]["returnresponse"]);
			unset($curl_init__map[$key]["returnheader"]);
			unset($curl_init__map[$key]["returnbody"]);
			unset($curl_init__map[$key]["filetime"]);
			$curl_init__map[$key]["outputbody"] = false;

			// Process the request.
			$options["profile"] = "";
			$result = $curl_init__map[$key]["browser"]->Process($url, $options);
			$curl_init__map[$key]["lastresult"] = $result;

			// Deal with cookies.
			if (!isset($curl_init__map[$key]["options"][CURLOPT_COOKIEFILE]) || !is_string($curl_init__map[$key]["options"][CURLOPT_COOKIEFILE]))
			{
				// Delete all cookies for another run later.
				$curl_init__map[$key]["browser"]->SetState(array("cookies" => array()));
			}
			else if (isset($curl_init__map[$key]["options"][CURLOPT_COOKIEJAR]))
			{
				// Write out cookies here.  Another violation of how cURL does things.  Another - whatever.
				$state = $curl_init__map[$key]["browser"]->GetState();
				file_put_contents($curl_init__map[$key]["options"][CURLOPT_COOKIEJAR], serialize($state["cookies"]));
			}

			// Process the response.
			if (!$result["success"])
			{
				if ($result["errorcode"] == "allowed_protocols")
				{
					$curl_init__map[$key]["errorno"] = CURLE_UNSUPPORTED_PROTOCOL;
					$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("The cURL emulation layer does not support the protocol or was redirected to an unsupported protocol by the host.  %s", $result["error"]);
				}
				else if ($result["errorcode"] == "allowed_redir_protocols")
				{
					$curl_init__map[$key]["errorno"] = CURLE_UNSUPPORTED_PROTOCOL;
					$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("The cURL emulation layer was redirected to an unsupported protocol by the host.  %s", $result["error"]);
				}
				else if ($result["errorcode"] == "retrievewebpage")
				{
					if ($result["info"]["errorcode"] == "timeout_exceeded")
					{
						$curl_init__map[$key]["errorno"] = CURLE_OPERATION_TIMEDOUT;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("The operation timed out.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "get_response_line")
					{
						$curl_init__map[$key]["errorno"] = CURLE_READ_ERROR;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("Unable to get the response line.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "read_header_callback")
					{
						$curl_init__map[$key]["errorno"] = CURLE_READ_ERROR;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("A read error occurred in the read header callback.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "read_body_callback")
					{
						$curl_init__map[$key]["errorno"] = CURLE_READ_ERROR;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("A read error occurred in the read body callback.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "function_check")
					{
						$curl_init__map[$key]["errorno"] = CURLE_FUNCTION_NOT_FOUND;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("A required function was not found.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "protocol_check")
					{
						$curl_init__map[$key]["errorno"] = CURLE_UNSUPPORTED_PROTOCOL;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("The cURL emulation layer does not support the protocol or was redirected to an unsupported protocol by the host.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "transport_not_installed")
					{
						$curl_init__map[$key]["errorno"] = CURLE_NOT_BUILT_IN;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("The cURL emulation layer attempted to use a required transport to connect to a host but failed.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "proxy_transport_not_installed")
					{
						$curl_init__map[$key]["errorno"] = CURLE_NOT_BUILT_IN;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("The cURL emulation layer attempted to use a required transport to connect to a proxy but failed.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "proxy_connect")
					{
						$curl_init__map[$key]["errorno"] = CURLE_COULDNT_CONNECT;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("Unable to connect to the proxy.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "proxy_connect_tunnel")
					{
						$curl_init__map[$key]["errorno"] = CURLE_COULDNT_CONNECT;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("Unable to open a tunnel through the connected proxy.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "connect_failed")
					{
						$curl_init__map[$key]["errorno"] = CURLE_COULDNT_CONNECT;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("Unable to connect to the host.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "write_body_callback")
					{
						$curl_init__map[$key]["errorno"] = CURLE_WRITE_ERROR;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("A write error occurred in the write body callback.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "file_open")
					{
						$curl_init__map[$key]["errorno"] = CURLE_FILE_COULDNT_READ_FILE;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("Unable to open file for upload.  %s", $result["error"]);
					}
					else if ($result["info"]["errorcode"] == "file_read")
					{
						$curl_init__map[$key]["errorno"] = CURLE_READ_ERROR;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("A read error occurred while uploading a file.  %s", $result["error"]);
					}
					else
					{
						$curl_init__map[$key]["errorno"] = CURLE_HTTP_RETURNED_ERROR;
						$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("An error occurred.  %s", $result["error"]);
					}
				}

				return false;
			}

			if (isset($curl_init__map[$key]["returnresponse"]) && $curl_init__map[$key]["returnresponse"]["code"] >= 400 && isset($curl_init__map[$key]["options"][CURLOPT_FAILONERROR]) && $curl_init__map[$key]["options"][CURLOPT_FAILONERROR])
			{
				$curl_init__map[$key]["errorno"] = CURLE_HTTP_RETURNED_ERROR;
				$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("A HTTP error occurred.  %s", $curl_init__map[$key]["returnresponse"]["line"]);

				return false;
			}

			if (isset($curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION]) && $curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION] && isset($result["headers"]["Location"]))
			{
				$curl_init__map[$key]["errorno"] = CURLE_TOO_MANY_REDIRECTS;
				$curl_init__map[$key]["errorinfo"] = HTTP::HTTPTranslate("Too many redirects took place.");

				return false;
			}

			if (isset($curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER]) && $curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER])
			{
				return (isset($curl_init__map[$key]["returnheader"]) ? $curl_init__map[$key]["returnheader"] . "\r\n" : "") . (isset($curl_init__map[$key]["returnbody"]) ? $curl_init__map[$key]["returnbody"] : "");
			}

			return true;
		}

		// Internal functions used by curl_exec().
		function internal_curl_debug_callback($type, $data, $key)
		{
			global $curl_init__map;

			ob_start();

			if ($type == "proxypeercert")
			{
				if (isset($curl_init__map[$key]["options"][CURLOPT_CERTINFO]) && $curl_init__map[$key]["options"][CURLOPT_CERTINFO])
				{
					echo HTTP::HTTPTranslate("Proxy SSL Certificate:\n");
					var_dump($data);
					echo "\n";
				}
			}
			else if ($type == "peercert")
			{
				if (isset($curl_init__map[$key]["options"][CURLOPT_CERTINFO]) && $curl_init__map[$key]["options"][CURLOPT_CERTINFO])
				{
					echo HTTP::HTTPTranslate("Peer SSL Certificate:\n");
					var_dump($data);
					echo "\n";
				}
			}
			else if ($type == "rawproxyheaders")
			{
				if (isset($curl_init__map[$key]["options"]["__CURLINFO_HEADER_OUT"]) && $curl_init__map[$key]["options"]["__CURLINFO_HEADER_OUT"])
				{
					$curl_init__map[$key]["rawproxyheaders"] = $data;

					echo HTTP::HTTPTranslate("Raw Proxy Headers:\n");
					echo $data;
				}
			}
			else if ($type == "rawheaders")
			{
				if (isset($curl_init__map[$key]["options"]["__CURLINFO_HEADER_OUT"]) && $curl_init__map[$key]["options"]["__CURLINFO_HEADER_OUT"])
				{
					$curl_init__map[$key]["rawheaders"] = $data;

					echo HTTP::HTTPTranslate("Raw Headers:\n");
					echo $data;
				}
			}
			else if ($type == "rawsend")
			{
				echo HTTP::HTTPTranslate("Sent:\n");
				echo $data;
			}
			else if ($type == "rawrecv")
			{
				echo HTTP::HTTPTranslate("Received:\n");
				echo $data;
				echo "\n";
			}

			$output = ob_get_contents();
			ob_end_clean();

			if ($output !== "" && isset($curl_init__map[$key]["options"][CURLOPT_VERBOSE]) && $curl_init__map[$key]["options"][CURLOPT_VERBOSE])
			{
				if (isset($curl_init__map[$key]["options"][CURLOPT_STDERR]) && is_resource($curl_init__map[$key]["options"][CURLOPT_STDERR]))  fwrite($curl_init__map[$key]["options"][CURLOPT_STDERR], $output);
				else if (defined("STDERR"))  fwrite(STDERR, $output);
				else  echo $output;
			}
		}

		function internal_curl_write_body_callback(&$body, &$bodysize, $key)
		{
			global $curl_init__map;

			if (!$bodysize)
			{
				if (!isset($curl_init__map[$key]["options"][CURLOPT_INFILESIZE]))  return false;
				$bodysize = $curl_init__map[$key]["options"][CURLOPT_INFILESIZE];
			}
			else if (isset($curl_init__map[$key]["options"][CURLOPT_READFUNCTION]))
			{
				$bodysize2 = ($bodysize > 32768 ? 32768 : $bodysize);
				$bodysize3 = $bodysize2;
				$body = $curl_init__map[$key]["options"][CURLOPT_READFUNCTION]($curl_init__map[$key]["self"], $curl_init__map[$key]["options"][CURLOPT_INFILE], $bodysize2);
				if ($bodysize3 < strlen($body))
				{
					if (isset($curl_init__map[$key]["options"][CURLOPT_VERBOSE]) && $curl_init__map[$key]["options"][CURLOPT_VERBOSE])
					{
						$output = HTTP::HTTPTranslate("An error occurred in the read function callback while reading the data to send/upload to the host.");

						if (isset($curl_init__map[$key]["options"][CURLOPT_STDERR]) && is_resource($curl_init__map[$key]["options"][CURLOPT_STDERR]))  fwrite($curl_init__map[$key]["options"][CURLOPT_STDERR], $output);
						else if (defined("STDERR"))  fwrite(STDERR, $output);
						else  echo $output;
					}

					return false;
				}
			}
			else
			{
				if (!isset($curl_init__map[$key]["options"][CURLOPT_INFILE]) || !is_resource($curl_init__map[$key]["options"][CURLOPT_INFILE]))  return false;
				if ($bodysize > 32768)  $body = fread($curl_init__map[$key]["options"][CURLOPT_INFILE], 32768);
				else  $body = fread($curl_init__map[$key]["options"][CURLOPT_INFILE], $bodysize);
				if ($body === false)
				{
					if (isset($curl_init__map[$key]["options"][CURLOPT_VERBOSE]) && $curl_init__map[$key]["options"][CURLOPT_VERBOSE])
					{
						$output = HTTP::HTTPTranslate("An error occurred while reading the data to send/upload to the host.");

						if (isset($curl_init__map[$key]["options"][CURLOPT_STDERR]) && is_resource($curl_init__map[$key]["options"][CURLOPT_STDERR]))  fwrite($curl_init__map[$key]["options"][CURLOPT_STDERR], $output);
						else if (defined("STDERR"))  fwrite(STDERR, $output);
						else  echo $output;
					}

					return false;
				}
			}

			return true;
		}

		function internal_curl_read_headers_callback(&$response, &$headers, $key)
		{
			global $curl_init__map;

			if (isset($curl_init__map[$key]["returnresponse"]))  $data = "";
			else
			{
				$data = $response["line"] . "\r\n";

				$curl_init__map[$key]["returnresponse"] = $response;
			}

			if ($response["code"] >= 400 && isset($curl_init__map[$key]["options"][CURLOPT_FAILONERROR]) && $curl_init__map[$key]["options"][CURLOPT_FAILONERROR])  return true;

			foreach ($headers as $name => $vals)
			{
				foreach ($vals as $val)  $data .= $name . ": " . $val . "\r\n";
			}

			if (isset($curl_init__map[$key]["options"][CURLOPT_HEADER]) && $curl_init__map[$key]["options"][CURLOPT_HEADER])
			{
				if (isset($curl_init__map[$key]["options"][CURLOPT_VERBOSE]) && $curl_init__map[$key]["options"][CURLOPT_VERBOSE])
				{
					if (isset($curl_init__map[$key]["options"][CURLOPT_STDERR]) && is_resource($curl_init__map[$key]["options"][CURLOPT_STDERR]))  fwrite($curl_init__map[$key]["options"][CURLOPT_STDERR], HTTP::HTTPTranslate("Header:\n") . $data);
					else if (defined("STDERR"))  fwrite(STDERR, HTTP::HTTPTranslate("Header:\n") . $data);
					else  echo HTTP::HTTPTranslate("Header:\n") . $data;
				}
			}

			if (!isset($headers["Location"]) || !isset($curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION]) || !$curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION])
			{
				if (isset($curl_init__map[$key]["options"][CURLOPT_HEADER]) && $curl_init__map[$key]["options"][CURLOPT_HEADER])
				{
					if (isset($curl_init__map[$key]["options"][CURLOPT_WRITEHEADER]) && is_resource($curl_init__map[$key]["options"][CURLOPT_WRITEHEADER]))
					{
						fwrite($curl_init__map[$key]["options"][CURLOPT_WRITEHEADER], $data);
					}
					else if (isset($curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER]) && $curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER])
					{
						if (!isset($curl_init__map[$key]["returnheader"]))  $curl_init__map[$key]["returnheader"] = $data;
						else  $curl_init__map[$key]["returnheader"] .= $data;
					}
					else if (!$curl_init__map[$key]["outputbody"])
					{
						if (isset($curl_init__map[$key]["options"][CURLOPT_FILE]) && is_resource($curl_init__map[$key]["options"][CURLOPT_FILE]))  fwrite($curl_init__map[$key]["options"][CURLOPT_FILE], $data);
						else  echo $data;
					}
				}

				if (isset($curl_init__map[$key]["options"][CURLOPT_HEADERFUNCTION]) && $curl_init__map[$key]["options"][CURLOPT_HEADERFUNCTION])
				{
					$curl_init__map[$key]["options"][CURLOPT_HEADERFUNCTION]($curl_init__map[$key]["self"], $data);
				}
			}

			return true;
		}

		function internal_curl_read_body_callback(&$response, $data, $key)
		{
			global $curl_init__map;

			if ($response["code"] >= 400 && isset($curl_init__map[$key]["options"][CURLOPT_FAILONERROR]) && $curl_init__map[$key]["options"][CURLOPT_FAILONERROR])  return true;

			if (!isset($headers["Location"]) || !isset($curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION]) || !$curl_init__map[$key]["options"][CURLOPT_FOLLOWLOCATION])
			{
				if (!isset($curl_init__map[$key]["returnbody"]))  $curl_init__map[$key]["returnbody"] = "";

				if (isset($curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER]) && $curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER])  $curl_init__map[$key]["returnbody"] .= $data;
				else
				{
					if (isset($curl_init__map[$key]["options"][CURLOPT_FILE]) && is_resource($curl_init__map[$key]["options"][CURLOPT_FILE]))  fwrite($curl_init__map[$key]["options"][CURLOPT_FILE], $data);
					else  echo $data;

					$curl_init__map[$key]["outputbody"] = true;
				}

				if (isset($curl_init__map[$key]["options"][CURLOPT_VERBOSE]) && $curl_init__map[$key]["options"][CURLOPT_VERBOSE])
				{
					if (isset($curl_init__map[$key]["options"][CURLOPT_STDERR]) && is_resource($curl_init__map[$key]["options"][CURLOPT_STDERR]))  fwrite($curl_init__map[$key]["options"][CURLOPT_STDERR], HTTP::HTTPTranslate("Body:\n") . $data);
					else if (defined("STDERR"))  fwrite(STDERR, HTTP::HTTPTranslate("Body:\n") . $data);
					else  echo HTTP::HTTPTranslate("Body:\n") . $data;
				}

				if (isset($curl_init__map[$key]["options"][CURLOPT_WRITEFUNCTION]) && $curl_init__map[$key]["options"][CURLOPT_WRITEFUNCTION])
				{
					$datasize = strlen($data);
					$size = $curl_init__map[$key]["options"][CURLOPT_WRITEFUNCTION]($curl_init__map[$key]["self"], $data);
					if ($size != $datasize)
					{
						if (isset($curl_init__map[$key]["options"][CURLOPT_VERBOSE]) && $curl_init__map[$key]["options"][CURLOPT_VERBOSE])
						{
							$output = HTTP::HTTPTranslate("An error occurred in the write function callback while writing the data received from the host.");

							if (isset($curl_init__map[$key]["options"][CURLOPT_STDERR]) && is_resource($curl_init__map[$key]["options"][CURLOPT_STDERR]))  fwrite($curl_init__map[$key]["options"][CURLOPT_STDERR], $output);
							else if (defined("STDERR"))  fwrite(STDERR, $output);
							else  echo $output;
						}

						return false;
					}
				}
			}

			return true;
		}

		function curl_getinfo($ch, $opt = 0)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);

			if (!isset($curl_init__map[$key]["lastresult"]))  return false;

			$result = array(
				"url" => $curl_init__map[$key]["lastresult"]["url"],
				"content_type" => (isset($curl_init__map[$key]["lastresult"]["headers"]) && isset($curl_init__map[$key]["lastresult"]["headers"]["Content-Type"]) ? $curl_init__map[$key]["lastresult"]["headers"]["Content-Type"][0] : null),
				"http_code" => (isset($curl_init__map[$key]["lastresult"]["response"]) && isset($curl_init__map[$key]["lastresult"]["response"]["code"]) ? (int)$curl_init__map[$key]["lastresult"]["response"]["code"] : null),
				"header_size" => (isset($curl_init__map[$key]["lastresult"]["rawrecvheadersize"]) ? $curl_init__map[$key]["lastresult"]["rawrecvheadersize"] : 0),
				"request_size" => (isset($curl_init__map[$key]["lastresult"]["totalrawsendsize"]) ? $curl_init__map[$key]["lastresult"]["totalrawsendsize"] : 0),
				"filetime" => (isset($curl_init__map[$key]["options"][CURLOPT_FILETIME]) && $curl_init__map[$key]["options"][CURLOPT_FILETIME] && isset($curl_init__map[$key]["lastresult"]["headers"]) && isset($curl_init__map[$key]["lastresult"]["headers"]["Last-Modified"]) ? HTTP::GetDateTimestamp($curl_init__map[$key]["lastresult"]["headers"]["Last-Modified"][0]) : -1),
				"ssl_verify_result" => 0,
				"redirect_count" => (isset($curl_init__map[$key]["lastresult"]["numredirects"]) ? $curl_init__map[$key]["lastresult"]["numredirects"] : 0),
				"total_time" => (isset($curl_init__map[$key]["lastresult"]["startts"]) && isset($curl_init__map[$key]["lastresult"]["endts"]) ? $curl_init__map[$key]["lastresult"]["endts"] - $curl_init__map[$key]["lastresult"]["startts"] : 0),
				"namelookup_time" => (isset($curl_init__map[$key]["lastresult"]["startts"]) && isset($curl_init__map[$key]["lastresult"]["connected"]) ? ($curl_init__map[$key]["lastresult"]["connected"] - $curl_init__map[$key]["lastresult"]["startts"]) / 2 : 0),
				"connect_time" => (isset($curl_init__map[$key]["lastresult"]["startts"]) && isset($curl_init__map[$key]["lastresult"]["connected"]) ? ($curl_init__map[$key]["lastresult"]["connected"] - $curl_init__map[$key]["lastresult"]["startts"]) / 2 : 0),
				"pretransfer_time" => (isset($curl_init__map[$key]["lastresult"]["connected"]) && isset($curl_init__map[$key]["lastresult"]["sendstart"]) ? $curl_init__map[$key]["lastresult"]["sendstart"] - $curl_init__map[$key]["lastresult"]["connected"] : 0),
				"size_upload" => (isset($curl_init__map[$key]["lastresult"]["rawsendsize"]) && isset($curl_init__map[$key]["lastresult"]["rawsendheadersize"]) ? $curl_init__map[$key]["lastresult"]["rawsendsize"] - $curl_init__map[$key]["lastresult"]["rawsendheadersize"] : 0),
				"size_download" => (isset($curl_init__map[$key]["lastresult"]["rawrecvsize"]) && isset($curl_init__map[$key]["lastresult"]["rawrecvheadersize"]) ? $curl_init__map[$key]["lastresult"]["rawrecvsize"] - $curl_init__map[$key]["lastresult"]["rawrecvheadersize"] : 0)
			);

			$result["speed_download"] = (isset($curl_init__map[$key]["lastresult"]["recvstart"]) && isset($curl_init__map[$key]["lastresult"]["endts"]) && $curl_init__map[$key]["lastresult"]["endts"] - $curl_init__map[$key]["lastresult"]["recvstart"] > 0 ? $result["size_download"] / ($curl_init__map[$key]["lastresult"]["endts"] - $curl_init__map[$key]["lastresult"]["recvstart"]) : 0);
			$result["speed_upload"] = (isset($curl_init__map[$key]["lastresult"]["sendstart"]) && isset($curl_init__map[$key]["lastresult"]["recvstart"]) && $curl_init__map[$key]["lastresult"]["recvstart"] - $curl_init__map[$key]["lastresult"]["sendstart"] > 0 ? $result["size_upload"] / ($curl_init__map[$key]["lastresult"]["recvstart"] - $curl_init__map[$key]["lastresult"]["sendstart"]) : 0);
			$result["download_content_length"] = (isset($curl_init__map[$key]["lastresult"]["headers"]) && isset($curl_init__map[$key]["lastresult"]["headers"]["Content-Length"]) ? $curl_init__map[$key]["lastresult"]["headers"]["Content-Length"][0] : -1);
			$result["upload_content_length"] = $result["size_upload"];
			$result["starttransfer_time"] = (isset($curl_init__map[$key]["lastresult"]["startts"]) && isset($curl_init__map[$key]["lastresult"]["sendstart"]) ? $curl_init__map[$key]["lastresult"]["sendstart"] - $curl_init__map[$key]["lastresult"]["startts"] : 0);
			$result["redirect_time"] = (isset($curl_init__map[$key]["lastresult"]["firstreqts"]) && isset($curl_init__map[$key]["lastresult"]["redirectts"]) ? $curl_init__map[$key]["lastresult"]["redirectts"] - $curl_init__map[$key]["lastresult"]["firstreqts"] : 0);
			if (isset($curl_init__map[$key]["rawheaders"]))  $result["request_header"] = $curl_init__map[$key]["rawheaders"];

			if ($opt == 0)  return $result;

			$tempmap = array(
				CURLINFO_EFFECTIVE_URL => "url",
				CURLINFO_HTTP_CODE => "http_code",
				CURLINFO_FILETIME => "filetime",
				CURLINFO_TOTAL_TIME => "total_time",
				CURLINFO_NAMELOOKUP_TIME => "namelookup_time",
				CURLINFO_CONNECT_TIME => "connect_time",
				CURLINFO_PRETRANSFER_TIME => "pretransfer_time",
				CURLINFO_STARTTRANSFER_TIME => "starttransfer_time",
				CURLINFO_REDIRECT_TIME => "redirect_time",
				CURLINFO_SIZE_UPLOAD => "size_upload",
				CURLINFO_SIZE_DOWNLOAD => "size_download",
				CURLINFO_SPEED_DOWNLOAD => "speed_download",
				CURLINFO_SPEED_UPLOAD => "speed_upload",
				CURLINFO_HEADER_SIZE => "header_size",
				CURLINFO_HEADER_OUT => "request_header",
				CURLINFO_REQUEST_SIZE => "request_size",
				CURLINFO_SSL_VERIFYRESULT => "ssl_verify_result",
				CURLINFO_CONTENT_LENGTH_DOWNLOAD => "download_content_length",
				CURLINFO_CONTENT_LENGTH_UPLOAD => "upload_content_length",
				CURLINFO_CONTENT_TYPE => "content_type",
			);
			if (!isset($tempmap[$opt]) || !isset($result[$tempmap[$opt]]))  return false;

			return $result[$tempmap[$opt]];
		}

		// These functions really just cheat and do requests in serial.  Laziness at its finest!
		$curl_multi_init__map = array();
		function get_curl_multi_init_key($mh)
		{
			ob_start();
			echo $mh;
			ob_end_clean();

			return ob_get_contents();
		}

		function get_check_curl_multi_init_key($ch)
		{
			global $curl_init__map;

			$key = get_curl_multi_init_key($ch);
			if (!isset($curl_multi_init__map[$key]))  throw new Exception(HTTP::HTTPTranslate("cURL Emulator:  Unable to find key mapping for resource."));

			return $key;
		}

		function curl_multi_init()
		{
			global $curl_multi_init__map;

			// Another evil hack to create a "resource" so that is_resource() works.
			$mh = fopen(__FILE__, "rb");
			$key = get_curl_multi_init_key($mh);
			$curl_multi_init__map[$key] = array("self" => $mh, "handles" => array(), "messages" => array());

			return $mh;
		}

		function curl_multi_add_handle($mh, $ch)
		{
			global $curl_multi_init__map;

			$key = get_check_curl_multi_init_key($mh);
			$key2 = get_check_curl_init_key($ch);

			$curl_multi_init__map[$key]["handles"][$key2] = $ch;

			return 0;
		}

		function curl_multi_remove_handle($mh, $ch)
		{
			global $curl_multi_init__map;

			$key = get_check_curl_multi_init_key($mh);
			$key2 = get_check_curl_init_key($ch);

			unset($curl_multi_init__map[$key]["handles"][$key2]);

			return 0;
		}

		function curl_multi_close($mh)
		{
			global $curl_multi_init__map;

			$key = get_check_curl_multi_init_key($mh);

			unset($curl_multi_init__map[$key]);
		}

		function curl_multi_getcontent($ch)
		{
			global $curl_init__map;

			$key = get_check_curl_init_key($ch);

			if (isset($curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER]) && $curl_init__map[$key]["options"][CURLOPT_RETURNTRANSFER])
			{
				return (isset($curl_init__map[$key]["returnheader"]) ? $curl_init__map[$key]["returnheader"] . "\r\n" : "") . (isset($curl_init__map[$key]["returnbody"]) ? $curl_init__map[$key]["returnbody"] : "");
			}
		}

		function curl_multi_exec($mh, &$still_running)
		{
			global $curl_multi_init__map;

			$key = get_check_curl_multi_init_key($mh);

			foreach ($curl_multi_init__map[$key]["handles"] as $key2 => $ch)
			{
				curl_exec($ch);
				$curl_multi_init__map[$key]["messages"][] = array(
					"msg" => CURLMSG_DONE,
					"result" => curl_errno($ch),
					"handle" => $ch
				);
				unset($curl_multi_init__map[$key]["handles"][$key2]);
			}

			$still_running = 0;

			return CURLM_OK;
		}

		function curl_multi_select($mh, $timeout = 1.0)
		{
			global $curl_multi_init__map;

			$key = get_check_curl_multi_init_key($mh);

			if (!count($curl_multi_init__map[$key]["handles"]))  return -1;

			return count($curl_multi_init__map[$key]["handles"]);
		}

		function curl_multi_info_read($mh, &$msgs_in_queue = NULL)
		{
			global $curl_multi_init__map;

			$key = get_check_curl_multi_init_key($mh);

			$msgs_in_queue = count($curl_multi_init__map[$key]["messages"]);
			if (!$msgs_in_queue)  return false;
			$msgs_in_queue--;

			return array_shift($curl_multi_init__map[$key]["messages"]);
		}
	}
?>