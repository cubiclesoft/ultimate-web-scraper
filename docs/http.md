HTTP Class:  'support/http.php'
===============================

The HTTP class is designed to perform the first half of a process known as "[web scraping](http://en.wikipedia.org/wiki/Web_scraping)".  Web scraping is essentially retrieving content from the web, parsing the content, and extracting whatever data is needed for whatever nefarious purposes the user has in mind.  However, I'm not responsible with what you choose to do with this class.  The class contains incredibly powerful PHP routines that go far beyond what PHP cURL or file_get_contents() calls typically do and is therefore quite easy to create web requests that look exactly like they came from a web browser.

You are encouraged to use the higher-level WebBrowser class, which manages things like cookies and redirects and can extract and process forms just like a real web browser.  The bulk of the HTTP class is intended to be a low-level layer.  WebBrowser uses the HTTP class under the hood but adds necessary magical goodness to handle various missing pieces.

You'll also get extensive mileage out of HTTP::ExtractURL() and HTTP::ConvertRelativeToAbsoluteURL().

Example HTTP class usage:

```php
<?php
	require_once "support/http.php";
	require_once "support/tag_filter.php";

	// Retrieve the standard HTML parsing array for later use.
	$htmloptions = TagFilter::GetHTMLOptions();

	$url = "http://www.somesite.com/something/";
	$options = array(
		"headers" => array(
			"User-Agent" => HTTP::GetWebUserAgent("Firefox"),
			"Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
			"Accept-Language" => "en-us,en;q=0.5",
			"Accept-Charset" => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Cache-Control" => "max-age=0"
		)
	);
	$result = HTTP::RetrieveWebpage($url, $options);

	if (!$result["success"])
	{
		echo "Error retrieving URL.  " . $result["error"] . "\n";
		exit();
	}

	if ($result["response"]["code"] != 200)
	{
		echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
		exit();
	}

	// Get the final URL after redirects.
	$baseurl = $result["url"];

	// Use TagFilter to parse the content.
	$html = TagFilter::Explode($result["body"], $htmloptions);

	// Retrieve a pointer object to the root node.
	$root = $html->Get();

	// Find all anchor tags.
	echo "All the URLs:\n";
	$rows = $root->Find("a[href]");
	foreach ($rows as $row)
	{
		echo "\t" . $row->href . "\n";
		echo "\t" . HTTP::ConvertRelativeToAbsoluteURL($baseurl, $row->href) . "\n";
	}

	// Find all table rows that have 'th' tags.
	$rows = $root->Find("tr")->Filter("th");
	foreach ($rows as $row)
	{
		echo "\t" . $row->GetOuterHTML() . "\n\n";
	}
?>
```

The above example doesn't get all the convenient benefits of the WebBrowser class such as automatically handling redirects and cookies.  In general, prefer using the WebBrowser class.

HTTP::ExtractURL($url)
----------------------

Access:  public static

Parameters:

* $url - A string containing a valid URL.

Returns:  An array containing the URL split up into its component pieces according to RFC 3986.

This static function takes apart a URL and breaks it up into "scheme", "authority", "login", "host", "port", "path", "query", "queryvars", and "fragment" pieces.  In many cases, this function works where the PHP parse_url() function fails.  It also breaks up the query string (the part after the question mark '?') into name => array of values pairs.

This static function can also occasionally handle broken/incomplete URLs but don't rely on that.  This function can handle more URLs and is more accurate than the built-in parse_url() function in PHP.

Example usage:

```php
<?php
	require_once "support/http.php";

	$url = "https://www.youtube.com/watch?v=dQw4w9WgXcQ";
	$parts = HTTP::ExtractURL($url);
	if ($parts["host"] === "www.youtube.com" && isset($parts["queryvars"]["v"]))
	{
		$videoid = $parts["queryvars"]["v"];
		if ($videoid === "dQw4w9WgXcQ")  echo "You're going to love this!";
	}
?>
```

HTTP::CondenseURL($data)
------------------------

Access:  public static

Parameters:

* $data - An array containing information in ExtractURL() format.

Returns:  A string containing a valid URL.

This static function takes data in the format returned from ExtractURL() and condenses it into a string containing a valid URL.  Useful for taking apart a URL with ExtractURL(), modifying it a little bit, and then generating a new URL from that to pass to another routine that expects a URL.

Example usage:

```php
<?php
	require_once "support/http.php";

	$url = "https://www.amazon.com/dp/B008B5ISVO/";
	$parts = HTTP::ExtractURL($url);
	if ($parts["host"] === "www.amazon.com")
	{
		// Let's make teh monies!
		$parts["query"] = "?tag=cubperblo-20";
		$url = HTTP::CondenseURL($parts);
	}
?>
```

HTTP::ConvertRelativeToAbsoluteURL($baseurl, $relativeurl)
----------------------------------------------------------

Access:  public static

Parameters:

* $baseurl - An array or string containing either ExtractURL() or a valid URL respectively.
* $relativeurl - An array or string containing either ExtractURL() or a valid URL respectively.

Returns:  A string containing an absolute URL.

This static function converts a relative URL into an absolute URL based on a base URL.  Useful for parsing scraped HTML documents.

Example usage:

```php
<?php
	require_once "support/http.php";

	$baseurl = "http://barebonescms.com/documentation/ultimate_web_scraper_toolkit/";
	$url = HTTP::ConvertRelativeToAbsoluteURL($baseurl, "/terms_of_service/");

	// Result should be:  http://barebonescms.com/terms_of_service/
	echo $url . "\n";
?>
```

HTTP::ExtractHeader($data)
--------------------------

Access:  public static

Parameters:

* $data - A string containing a standard HTTP header key-value(s) pair.

Returns:  An array containing the results of parsing the header.

This static function explodes a HTTP header into it's component parts.

Example:

```php
<?php
	require_once "support/http.php";

	$header = "Content-type: text/html; charset=UTF-8";
	$parts = HTTP::ExtractHeader($header);

	var_dump($parts);
?>
```

HTTP::GetWebUserAgent($type)
----------------------------

Access:  public static

Parameters:

* $type - A string containing one of "IE", "IE6", "IE7", "IE8", "IE9", "IE10", "IE11", "Firefox", "Opera", "Chrome", or "Safari".

Returns:  A string containing a valid user agent for the specified web browser.

This static function returns a popular user agent string.  These aren't always up-to-date but are usually good enough to get the job done on servers that require a user agent.  If you feel a string is too out of date, post a message to the forums.

Example usage:

```php
<?php
	require_once "support/http.php";

	echo HTTP::GetWebUserAgent("IE") . "\n";
?>
```

HTTP::GetSSLCiphers($type = "intermediate")
-------------------------------------------

Access:  public static

Parameters:

* $type - A string containing one of "modern", "intermediate", or "old" (Default is "intermediate").

Returns:  A string containing the SSL cipher list to use.

This static function returns SSL cipher lists extracted from the [Mozilla SSL configuration generator](https://mozilla.github.io/server-side-tls/ssl-config-generator/).

HTTP::GetSafeSSLOpts($cafile = true, $cipherstype = "intermediate")
-------------------------------------------------------------------

Access:  public static

Parameters:

* $cafile - A boolean that indicates whether or not to use the internally defined CA file list or a string containing the full path and filename of a CA root certificate file (Default is true).
* $cipherstype - A string containing one of "modern", "intermediate", or "old" (Default is "intermediate").  See GetSSLCiphers() above.

Returns:  An array of SSL context options.

This static function is used to generate a default "sslopts" or "proxysslopts" array if they are not provided when connecting to an associated HTTPS server.

HTTP::GetDateTimestamp($httpdate)
---------------------------------

Access:  public static

Parameters:

* $httpdate - A string containing a valid RFC1123, RFC850, or asctime() date.

Returns:  An integer containing a UNIX timestamp on success, otherwise a boolean of false.

This static function parses the valid set of timestamp formats specified by RFC2616 and returns a UNIX timestamp.  It is somewhat flexible though and accepts some minor character differences from the actual specification.

Example usage:

```php
<?php
	require_once "support/http.php";

	$date = "Sat, 11 Feb 2017 22:39:36 GMT";
	echo date("Y-m-d H:i:s", HTTP::GetDateTimestamp($date)) . "\n";
?>
```

HTTP::RetrieveWebpage($url, $options = array())
-----------------------------------------------

Access:  public static

Parameters:

* $url - A string containing the URL to retrieve.
* $options - An array containing options that control the request to the server (Default is array()).

Returns:  A standard array of information.

This static function retrieves a webpage.  It has full HTTP and HTTPS support.  It can upload files, perform GET, POST, HEAD, PUT, and custom requests, and can even place requests through a HTTP proxy server.  It handles chunked data responses, provides detailed responses, and has support for callbacks.  It can also be used to precisely emulate a web browser.

NOTE:  This function does NOT process redirects (e.g. "Location: " headers) or cookies.  For those features, you should probably use the WebBrowser class, which has excellent support.

HTTPS support requires PHP to have been compiled and enabled with SSL protocol wrapper support via OpenSSL.

The $options array accepts these options:

* timeout - A boolean of false or a numeric value containing the maximum amount of time, in seconds, to take for all operations.
* protocol - A string containing the preferred low-level protocol.  May be any supported protocol that the PHP stream_get_transports() function supports (e.g. "ssl", "tls", "tlsv1.2", "tcp").
* connecttimeout - An integer containing the amount of time to wait for the connection to the host to succeed in seconds (Default is 10).
* sslopts - An array of valid SSL context options key-value pairs to use when connection to a SSL-enabled host.  Also supports "auto_cainfo", "auto_cn_match", and "auto_sni" options to define several context options automatically.
* proxyurl - A string containing the URL of a web proxy to pass the request through.
* proxyprotocol - A string containing the preferred low-level protocol for the proxy.  May be any supported protocol that the PHP stream_get_transports() function supports (e.g. "ssl", "tls", "tlsv1.2", "tcp").
* proxyconnect - A boolean that specifies that the request through the proxy should attempt to tunnel the request via HTTP CONNECT.
* proxyconnecttimeout - An integer containing the amount of time to wait for the connection to the proxy to succeed in seconds (Default is 10).
* proxysslopts - An array of valid SSL context options key-value pairs to use when connection to a SSL-enabled proxy. Also supports "auto_cainfo", "auto_cn_match", and "auto_sni" options to define several context options automatically.
* method - A string containing the HTTP method to use (e.g. "GET", "POST", "HEAD").  If not specified, other parameters are analyzed to determine the most likely method to use.
* httpver - A string containing the HTTP version to use (Default is "1.1").
* headers - An array containing key-value pairs to send to the server.  Headers are automatically normalized for HTTP.
* rawheaders - An array containing key-value pairs to send to the server.  Raw headers are not normalized for HTTP.
* body - A string containing custom body content to the server.  Useful for making XMLRPC requests.  Overrides the 'files' and 'postvars' options.
* files - An array of information containing 'name', 'filename', 'type', and 'data' for each file to send to the server in a multipart/form-data POST request.  'datafile' may also be specified instead of 'data' with a valid filename on the host for reduced memory consumption when sending large files.
* postvars - An array of key-value pairs to send to the server in a POST request.
* sendratelimit - An integer representing the maximum acceptable upload rate in bytes/sec.  Requests are rate limited to this rate.
* recvratelimit - An integer representing the maximum acceptable download rate in bytes/sec.  Transfers are rate limited to this rate.
* debug - A boolean that determines whether or not to return the raw HTTP conversation with the results.
* debug_callback - A valid callback function for a debugging callback.  The callback function must accept three parameters - callback($type, $data, $opts).
* debug_callback_opts - Data to pass as the third parameter to the function specified by the 'debug_callback' option.
* write_body_callback - A valid callback function for a callback that will provide data to send to the server.  The callback function must accept three parameters - callback(&$body, &$bodysize, $opts).
* write_body_callback_opts - Data to pass as the third parameter to the function specified by 'write_body_callback' option.
* read_headers_callback - A valid callback function for a callback that will process a chunk of headers.  The callback function must accept three parameters - callback($response, $headers, $opts).  Note that this function may be called more than one time during a single request.
* read_headers_callback_opts - Data to pass as the third parameter to the function specified by 'read_headers_callback' option.
* read_body_callback - A valid callback function for a callback that will process some body content.  The callback function must accept three parameters - callback($response, $data, $opts).
* read_body_callback_opts - Data to pass as the third parameter to the function specified by 'read_body_callback' option.

SSL/TLS certificates are verified against the included 'cacert.pem' file.  This file comes from the [cURL website](https://curl.haxx.se/docs/caextract.html).

HTTP::HTTPTranslate($format, ...)
---------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.

HTTP::HeaderNameCleanup($name)
------------------------------

Access:  _internal_ static

Parameters:

* $name - A string containing a HTTP header name.

Returns:  A string containing a purified HTTP header name.

This internal static function cleans up a HTTP header name.  Used by HTTP::RetrieveWebpage() and other CubicleSoft classes in the toolkit to avoid code duplication.

HTTP::HeaderValueCleanup($value)
--------------------------------

Access:  _internal_ static

Parameters:

* $value - A string containing a HTTP header value.

Returns:  A string containing a purified HTTP header value.

This internal static function cleans up a HTTP header value.  Used by HTTP::RetrieveWebpage() and other CubicleSoft classes in the toolkit to avoid code duplication.

HTTP::NormalizeHeaders($headers)
--------------------------------

Access:  _internal_ static

Parameters:

* $headers - An array containing name-value pairs.

Returns:  An array containing normalized name-value pairs.

This internal static function cleans up and normalizes the names and values of a set of HTTP headers.

HTTP::MergeRawHeaders(&$headers, $rawheaders)
---------------------------------------------

Access:  _internal_ static

Parameters:

* $headers - An array containing name-value pairs.
* $rawheaders - An array containing name-value pairs.

Returns:  Nothing.

This internal static function merges an array of raw headers into another array containing a set of normalized HTTP headers.

HTTP::ProcessSSLOptions(&$options, $key, $host)
-----------------------------------------------

Access:  private static

Parameters:

* $options - An array of options.
* $key - A string specifying which SSL options to process.
* $host - A string containing alternate hostname information.

Returns:  Nothing.

This internal static function processes the "auto_cainfo", "auto_cn_match", and "auto_sni" options for "sslopts" and "proxysslopts" for SSL/TLS context purposes.

HTTP::ExtractFilename($dirfile)
-------------------------------

Access:  _internal_ static

Parameters:

* $dirfile - A string containing a path and filename.

Returns:  A string containing the filename.

This internal static function is identical to the CubicleSoft basic string function Str::ExtractFilename().  It exists so that the HTTP class can be self-contained.

HTTP::FilenameSafe($filename)
-----------------------------

Access:  _internal_ static

Parameters:

* $filename - A string containing a potentially unsafe filename.

Returns:  A string containing a safe filename prefix.

This internal static function is identical to the CubicleSoft basic string function Str::FilenameSafe().  It exists so that the HTTP class can be self-contained.

HTTP::GetTimeLeft($start, $limit)
---------------------------------

Access:  _internal_ static

Parameters:

* $start - A numeric value containing a UNIX timestamp of a start time.
* $limit - A boolean of false or a numeric value containing the maximum amount of time, in seconds, to take from $start.

Returns:  A boolean of false if $limit is false, 0 if the time limit has been reached/exceeded, or a numeric value representing the amount of time left in seconds.

This internal static function is used to calculate whether an operation has taken too long and then terminate the connection.

HTTP::ProcessRateLimit($size, $start, $limit, $async)
-----------------------------------------------------

Access:  private static

Parameters:

* $size - An integer containing the number of bytes transferred.
* $start - A numeric value containing a UNIX timestamp of a start time.
* $limit - An integer representing the maximum acceptable rate in bytes/sec.
* $async - A boolean indicating whether or not the function should not sleep (async caller).

Returns:  An integer containing the amount of time to wait for (async only), -1 otherwise.

This internal static function calculates the current rate at which bytes are being transferred over the network.  If the rate exceeds the limit, it calculates exactly how long to wait and then sleeps for that amount of time so that the average transfer rate is within the limit.

HTTP::GetDecodedBody(&$autodecode_ds, $body)
--------------------------------------------

Access:  private static

Parameters:

* $autodecode_ds - A DeflateStream object or a boolean of false.
* $body - The body content to extract.

Returns:  A string containing the optionally extracted content.

This internal static function performs automatic gzip/deflate extraction of retrieved content if necessary.  The function only does something if the remote server has responded that it is sending gzip/deflate compressed content.

HTTP::StreamTimedOut($fp)
-------------------------

Access:  private static

Parameters:

* $fp - A valid socket handle.

Returns:  A boolean of true if the underlying socket has timed out, false otherwise.

This internal static function calls `stream_get_meta_data()` to determine the validity of the socket.

HTTP::InitResponseState($fp, $debug, $options, $startts, $timeout, $result, $close, $nextread, $client = true)
--------------------------------------------------------------------------------------------------------------

Access:  _internal_ static

Parameters:

* $fp - A valid socket handle.
* $debug - A boolean that indicates whether or not debugging mode is enabled.
* $options - An array of standard HTTP class options.
* $startts - An integer indicating when the request was started.
* $timeout - An integer or a boolean of false to indicate when an automatic timeout should take place.
* $close - A boolean indicating that the connection should terminate after the response.
* $nextread - A string containing the excess bytes of the stream that were read in previously using fread().
* $client - A boolean indicating whether this response state is operating in client (true) or server (false) mode (Default is true).

Returns:  A prepared HTTP state array containing the necessary information for later use.

This internal static function initializes the state management array for use with HTTP::ProcessState() along the response path.

HTTP::ProcessState__InternalRead(&$state, $size, $endchar = false)
------------------------------------------------------------------

Access:  private static

Parameters:

* $state - A valid HTTP state array.
* $size - An integer containing the maximum length to read in.
* $endchar - A boolean of false or a string containing a single character to stop reading after (Default is false).

Returns:  Normalized fread() output.

This internal static function gets rid of the old fgets() line-by-line retrieval mechanism used by `ProcessState__ReadLine()` and standardizes on fread() with an internal cache.  Doing this also helps to work around a number of bugs in PHP.

HTTP::ProcessState__ReadLine(&$state)
-------------------------------------

Access:  private static

Parameters:

* $state - A valid HTTP state array.

Returns:  A standard array of information.

This internal static function attempts to read in a single line of information and return to the caller.

HTTP::ProcessState__ReadBodyData(&$state)
-----------------------------------------

Access:  private static

Parameters:

* $state - A valid HTTP state array.

Returns:  A standard array of information.

This internal static function attempts to read in up to 65KB of data at one time or the expected number of bytes to complete the request, whichever is lesser.

HTTP::ProcessState__WriteData(&$state, $prefix)
-----------------------------------------------

Access:  private static

Parameters:

* $state - A valid HTTP state array.
* $prefix - A string prefix related to a HTTP proxy vs. normal request.

Returns:  A standard array of information.

This internal static function attempts to write waiting data out to the socket.

HTTP::ForceClose(&$state)
-------------------------

Access:  _internal_ static

Parameters:

* $state - A valid HTTP state array.

Returns:  Nothing.

This internal static function forces closes the underlying socket.  Any future attempts to use the socket will fail.

HTTP::CleanupErrorState(&$state, $result)
-----------------------------------------

Access:  private static

Parameters:

* $state - A valid HTTP state array.
* $result - A standard array of information.

Returns:  $result unmodified.

This internal static function looks at an error condition on a socket to determine if the socket should be closed immediately or not.  When the state of the socket is in 'async' (non-blocking) mode, the "no_data" error code will be returned quite frequently due to the non-blocking nature of those sockets.

HTTP::WantRead(&$state)
-----------------------

Access:  _internal_ static

Parameters:

* $state - A valid HTTP state array.

Returns:  A boolean of true if the underlying socket is waiting to read data, false otherwise.

This internal static function is used to identify if the underlying socket in this state should be used in the read array of a stream_select() call (or equivalent) when the socket is in 'async' (non-blocking) mode.

HTTP::WantWrite(&$state)
------------------------

Access:  _internal_ static

Parameters:

* $state - A valid HTTP state array.

Returns:  A boolean of true if the underlying socket is waiting to write data, false otherwise.

This internal static function is used to identify if the underlying socket in this state should be used in the write array of a stream_select() call (or equivalent) when the socket is in 'async' (non-blocking) mode.

HTTP::ProcessState(&$state)
---------------------------

Access:  public static

Parameters:

* $state - A valid HTTP state array.

Returns:  A standard array of information.

This internal-ish static function runs the core state engine behind the scenes against the input state.  This is the primary workhorse of the HTTP class.  It supports running input states in client and server modes.

HTTP::RawFileSize($fileorname)
------------------------------

Access:  _internal_ static

Parameters:

* $fileorname - A file pointer or a string that points at a valid file.

Returns:  An integer or double containing the real file size of a file.

This internal static function determines the real file size of any file.  Many versions of PHP have difficulty determining the file size of files larger than 2GB in size, which makes uploading those files rather difficult.
