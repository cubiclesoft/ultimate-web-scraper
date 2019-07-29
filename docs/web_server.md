WebServer Classes:  'support/web_server.php'
============================================

The WebServer class implements a fully featured web server that won't win any performance awards.  However, with it you can easily build a deployable API or custom web server for networked computing in the home or enterprise that avoids the need to set up a formal web server.  PHP does have a built-in web server but it is fairly limited in what it allows you to do with it.

Be sure to copy "web_server.php" into the "support" subdirectory before using it.

For example usage, see:

https://github.com/cubiclesoft/ultimate-web-scraper/blob/master/tests/test_web_server.php

For a pre-built, flexible, extendable API with user/token management, see Cloud Storage Server:

https://github.com/cubiclesoft/cloud-storage-server

WebServer::Reset()
------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function reinitializes the WebServer class.

WebServer::SetDefaultTimeout($timeout)
--------------------------------------

Access:  public

Parameters:

* $timeout - An integer representing the new timeout value in seconds.

Returns:  Nothing.

This function sets the default timeout for stream_select() calls.  The initial default is 30 seconds.

WebServer::SetDefaultClientTimeout($timeout)
--------------------------------------------

Access:  public

Parameters:

* $timeout - An integer representing the new timeout value in seconds.

Returns:  Nothing.

This function sets the default timeout for inactive clients.  The initial default is 30 seconds.

WebServer::SetMaxRequests($num)
-------------------------------

Access:  public

Parameters:

* $num - An integer representing the maximum number of requests each client may make.

Returns:  Nothing.

This function sets the maximum number of requests each client may make before it will automatically be terminated.  The initial default is 30 requests.

WebServer::SetDefaultClientOptions($options)
--------------------------------------------

Access:  public

Parameters:

* $options - An array containing valid options for HTTP::InitResponseState().

Returns:  Nothing.

This sets the array of options to use for the eventual call to HTTP::InitResponseState().  The default is array().  The most common options are:

* readlinelimit - An integer containing the maximum length of an input line (Default is 116000).
* maxheaders - An integer containing the maximum number of input headers (Default is 1000).
* recvlimit - An integer containin the maxmimum number of input bytes (headers + body) allowed for each request (Default is 1000000).

The default "recvlimit" may seem low (< 1MB) but that limit can be altered on a per-client basis before the body content is received.  Authentication via either the request headers or the URI can verify a user and then raise the limit for just that client.

The "recvlimit" here has no impact on the hard limit of 262,144 bytes that the WebServer::ProcessClientRequestBody() callback has built in.  To bypass the hard limit, you will need to call WebServer::SetCacheDir() to enable the disk cache.

WebServer::EnableCompression($compress)
---------------------------------------

Access:  public

Parameters:

* $compress - A boolean that determines whether or not to enable compression.

Returns:  Nothing.

This enables/disables gzip response compression.  Note that this requires the DeflateStream class and using compression may cause performance problems since this is a single-threaded server.  The default is disabled.

WebServer::MakeTempDir($prefix, $perms = 0770)
----------------------------------------------

Access:  public static

Parameters:

* $prefix - A string containing a prefix for the temporary directory.
* $perms - An integer, usually octal format, containing the permissions to set the created directory to (Default is 0770).

Returns:  A string containing the newly created directory in the temporary path.

This static function creates and returns a temporary directory with specified access permissions based on the prefix, current process ID, and timestamp.

WebServer::SetCacheDir($cachedir)
---------------------------------

Access:  public

Parameters:

* $cachedir - A string containing the complete path to the cache directory to use or a boolean of false to disable.

Returns:  Nothing.

This function sets the cache directory or disables the disk cache.  The default is disabled to accommodate a RAM-only server mode.  The downside is that not having a disk cache results in a hard limit of 262,144 bytes for the request body, which can be problematic limitation for most file uploads.

When the disk cache is enabled, any key-value pair in $client->requestvars may point at an instance of WebServer_TempFile instead of a string if any given value exceeds the hard limit.

WebServer::GetCacheDir()
------------------------

Access:  public

Parameters:  None.

Returns:  A string containing the current cache directory or a boolean of false if not set.

This function retrieves the caching directory set previously by `SetCacheDir()`.

WebServer::Start($host, $port, $sslopts = false)
------------------------------------------------

Access:  public

Parameters:

* $host - A string containing the host to bind to.
* $port - An integer containin the port number to bind to.
* $sslopts - An array of PHP SSL context options to use SSL mode on the socket or a boolean of false (Default is false).

Returns:  A standard array of information.

This function attempts to bind to the specified TCP/IP host and port.  Common options for the host are:

* `0.0.0.0` to bind to all IPv4 interfaces.
* `127.0.0.1` to bind to the localhost IPv4 interface.
* `[::0]` to bind to all IPv6 interfaces.
* `[::1]` to bind to the localhost IPv6 interface.

To select a new port number for a server, use the following link:

https://www.random.org/integers/?num=1&min=5001&max=49151&col=5&base=10&format=html&rnd=new

If it shows port 8080, just reload to get a different port number.

The most common options for the $sslopts array are "local_cert" and "local_pk" for selecting a signed certificate and private key respectively.

Example usage:

```php
<?php
	require_once "support/web_server.php";

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Start a localhost web server on port 5585.
	// You should pick a random port as indicated above this example.
	$ws = new WebServer();
	$result = $ws->Start("127.0.0.1", 5585, array("local_cert" => $rootpath . "/server_cert.pem", "local_pk" => $rootpath . "/server_key.pem"));

	var_dump($result);
?>
```

WebServer::Stop()
-----------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function stops the web server and cleans up after itself.  Automatically called by the destructor.

WebServer::GetStream()
----------------------

Access:  public

Parameters:  None.

Returns:  The internal server socket handle.

This function is considered "dangerous" but allows for stream_select() calls on multiple, separate stream handles to be used.

WebServer::UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
-----------------------------------------------------------------------------

Access:  public

Parameters:

* $prefix - A unique prefix to identify the various streams (server and client handles).
* $timeout - An integer reference containing the maximum number of seconds or a boolean of false.
* $readfps - An array reference to add streams wanting data to arrive.
* $writefps - An array reference to add streams wanting to send data.

Returns:  Nothing.

This function updates the timeout and read/write arrays with prefixed names so that a single stream_select() call can manage all sockets.

WebServer::FixedStreamSelect(&$readfps, &$writefps, &$exceptfps, $timeout)
--------------------------------------------------------------------------

Access:  public static

Parameters:  Same as stream_select() minus the microsecond parameter.

Returns:  A boolean of true on success, false on failure.

This function allows key-value pairs to work properly for the usual read, write, and except arrays.  PHP's stream_select() function is buggy and sometimes will return correct keys and other times not.  This function is called by Wait().  Directly calling this function is useful if multiple servers are running at a time (e.g. one public SSL server, one localhost non-SSL server).

WebServer::Wait($timeout = false)
---------------------------------

Access:  public

Parameters:

* $timeout - An integer reference containing the maximum number of seconds or a boolean of false.

Returns:  A standard array of information.

This function handles new connections, the initial conversation, basic packet management, rate limits, and timeouts.  The returned "clients" and "removed" arrays contain clients that may need processing.  This function is expected to be part of a loop.

Example usage:

```php
<?php
	require_once "support/web_server.php";

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Start a localhost web server on port 5585.
	// You should pick a random port as indicated by WebServer::Start().
	$ws = new WebServer();
	$result = $ws->Start("127.0.0.1", 5585, array("local_cert" => $rootpath . "/server_cert.pem", "local_pk" => $rootpath . "/server_key.pem"));
	if (!$result["success"])
	{
		var_dump($result);

		exit();
	}

	do
	{
		$result = $ws->Wait();
		if (!$result["success"])  break;

		foreach ($result["clients"] as $id => $client)
		{
			// Process the client.
		}

		foreach ($result["removed"] as $id => $info)
		{
			// Process the removed client information (e.g. clean up various tracking arrays).
		}

	} while (1);

	var_dump($result);
?>
```

WebServer::GetClients()
-----------------------

Access:  public

Parameters:  None.

Returns:  The internal array of active clients.

This function retrieves the internal array of active clients.  These are the clients that have made it past the initialization states.

WebServer::NumClients()
-----------------------

Access:  public

Parameters:  None.

Returns:  The number of active clients.

This function returns the number active clients that have made it past the initialization states.  It's more efficient to call this function than to get a copy of the clients array just to `count()` them.

WebServer::GetClient($id)
-------------------------

Access:  public

Parameters:

* $id - An integer containing a client ID.

Returns:  The associated client instance on success, a boolean of false otherwise.

This function retrieves a specific active client.  An active client is one that has made it past the initialization states.

WebServer::RemoveClient($id)
----------------------------

Access:  public

Parameters:

* $id - An integer containing a client ID.

Returns:  Nothing.

This function disconnects and removes a specific active client.

WebServer::AddClientRecvHeader($id, $name, $val)
------------------------------------------------

Access:  _internal_

Parameters:

* $id - An integer representing the client ID to add the header to.
* $name - A string containing the name of the header.
* $val - A string containing the value of the header.

Returns:  Nothing.

This internal function adds a header to the $client->requestvars array.  If the header name ends with `[]`, then the header is treated as an array and the value is appended.

WebServer::ProcessClientRequestHeaders($request, $headers, $id)
---------------------------------------------------------------

Access:  _internal_

Parameters:

* $request - An array containing the exploded request line.
* $headers - An array containing key-value pairs of headers.
* $id - An integer representing the client ID.

Returns:  A boolean indicating whether or not to terminate the client connection.

This internal callback function merges incoming header information into the client object.  HTTP cookies are parsed into their key-value pairs and added to the client cookievars and requestvars arrays.  The Content-Type header is specially processed for correct incoming body content handling.  The Host header is specially processed and a full URL is calculated for later use.  Any query string options in the URL are merged into the client requestvars arrays.

WebServer::ProcessClientRequestBody($request, $body, $id)
---------------------------------------------------------

Access:  _internal_

Parameters:

* $request - An array containing the exploded request line.
* $body - A string containing more body content.
* $id - An integer representing the client ID.

Returns:  A boolean indicating whether or not to terminate the client connection.

This internal callback function processes incoming body content as per the earlier incoming Content-Type header.  If that header was missing, then the hard limit of 262,144 bytes for body content applies.  If it is one of the valid Content-Type headers, then the content is processed accordingly.  The disk cache option significantly expands client upload capabilities for things like large file uploads.

WebServer::ProcessClientResponseBody(&$data, &$bodysize, $id)
-------------------------------------------------------------

Access:  _internal_

Parameters:

* $data - A string reference to write data to.
* $bodysize - An integer or boolean reference specifying expected body size.
* $id - An integer representing the client ID.

Returns:  A boolean indicating whether or not to terminate the client connection.

This internal callback function processes data in the client class ready to be written to the underlying HTTP data stream.

WebServer::InitNewClient()
--------------------------

Access:  _internal_

Parameters:  None.

Returns:  A new WebServer_Client instance.

This internal function creates a new client and adds it to the `initclients` array.  The following public variables are available for applications to access in a read-only fashion:

* id - The client ID.
* readdata - Contains or points to the request body if the 'contenthandled' is false.
* request - The parsed incoming request line (e.g. "GET /people/ HTTP/1.1").
* url - The fully constructed URL for the request.
* headers - An array of incoming headers and their values.
* contenttype - The content type of the request (or false if none was provided).
* contenthandled - A boolean that specifies whether or not the incoming body content was handled by the internal callback.
* cookievars - An array of processed HTTP cookies.  Similar to $_COOKIE.
* requestvars - An array of incoming request vars.  Similar to $_REQUEST.
* requestcomplete - A boolean indicating whether or not the request body has been read in.  This is useful, for example, to allow a client to upload a large file after the request headers have been processed by raising the "recvlimit".
* requests - An integer containing the number of requests this client has made.
* lastts - The time of the last interaction with this client.
* ipaddr - A string containing the source IP of the client.

WebServer::HandleNewConnections(&$readfps, &$writefps)
------------------------------------------------------

Access:  protected

Parameters:

* $readfps - An array reference to manage streams that might have data to read.
* $writefps - An array reference to manage streams that are probably ready to send data.

Returns:  Nothing.

This protected function handles new incoming connections in Wait().  Can be overridden in a derived class to provide alternate functionality.

WebServer::HandleResponseCompleted($id, $result)
------------------------------------------------

Access:  protected

Parameters:

* $id - The client ID.
* $result - A standard array of information.

Returns:  Nothing.

This protected function is called at the end of each sent response.  Can be overridden in a derived class to do things such as gather statistics.

WebServer::DetachClient($id)
----------------------------

Access:  _internal_

Parameters:

* $id - An integer containing a client ID.

Returns:  The associated client instance on success, a boolean of false otherwise.

This function detaches a specific active client.  Note that there is no AttachClient() function for WebServer.  This function is used by WebSocketServer::ProcessWebServerClientUpgrade() to handle valid Upgrade requests to the WebSocket protocol.  This function is also used by WebRouteServer::ProcessWebServerClientUpgrade() to handle valid Upgrade requests to the WebRoute protocol.


WebServer_TempFile Class
========================

This internal class is used to construct file objects to minimize the number of open file handles for input large variables.  Without this class, an attacker could easily consume all available file handles in a single request and crash the server.  This type of object is only created if the WebServer disk cache has been enabled.

Example usage:

```php
<?php
	require_once "support/web_server.php";

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Start a localhost web server on port 5585.
	// You should pick a random port as indicated by WebServer::Start().
	$ws = new WebServer();
	$result = $ws->Start("127.0.0.1", 5585, array("local_cert" => $rootpath . "/server_cert.pem", "local_pk" => $rootpath . "/server_key.pem"));
	if (!$result["success"])
	{
		var_dump($result);

		exit();
	}

	do
	{
		$result = $ws->Wait();
		if (!$result["success"])  break;

		foreach ($result["clients"] as $id => $client)
		{
			// Process the client.
			if ($client->requestcomplete && $client->contenthandled)
			{
				foreach ($client->requestvars as $key => $val)
				{
					echo $key . " = ";
					if (!is_object($val))  echo $val . "\n";
					else
					{
						if ($val->Open() !== false)
						{
							while (($data = $val->Read(4096)) !== false)  echo $data;
						}

						echo "\n";
					}
				}

				// Send the response.
				$client->SetResponseContentType("application/json");
				$client->AddResponseContent(json_encode(array("success" => true)));
				$client->FinalizeResponse();
			}
		}

		foreach ($result["removed"] as $id => $info)
		{
			// Process the removed client information (e.g. clean up various tracking arrays).
		}

	} while (1);

	var_dump($result);
?>
```

WebServer_TempFile::Open()
--------------------------

Access:  public

Parameters:  None.

Returns:  A standard file handle resource in "w+b" mode on success, a boolean of false otherwise.

This function opens or reopens the file associated with the WebServer_TempFile instance.  The return value should only be used to verify whether or not opening the file was successful.

WebServer_TempFile::Read($size)
--------------------------

Access:  public

Parameters:

* $size - An integer containing the number of bytes to read in.

Returns:  A string on success, a boolean of false otherwise.

This function attempts to read up to $size bytes of input from the file.  False is only returned if the file handle is closed as a result of running out of input from the previous `Read()` call.

WebServer_TempFile::Write($data)
--------------------------------

Access:  public

Parameters:

* $data - A string containing the data to write.

Returns:  The result from the PHP fwrite() call.

This function writes data to the file.  For most use-cases, this function will never be used by anything except the WebServer class itself.

WebServer_TempFile::Close()
---------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function closes the open file handle if it is open.  This function is automatically called when Read() runs out of input.

WebServer_TempFile::Free()
--------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function closes the open file handle if it is open and deletes the file from disk.


WebServer_Client Class
======================

This class is constructed by the web server class whenever a new client connects.  See the WebServer::InitNewClient() function for details on available public variables that the application may use.

WebServer_Client::GetHTTPOptions()
----------------------------------

Access:  public

Parameters:  None.

Returns:  An array containing internal HTTP state options on success, a boolean of false otherwise.

This function grants access to the HTTP options array.  The most common use-case is to raise the upload limit via "recvlimit" for authorized clients before the entire incoming data stream is processed.

Example usage:

```php
<?php
	require_once "support/web_server.php";

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Start a localhost web server on port 5585.
	// You should pick a random port as indicated by WebServer::Start().
	$ws = new WebServer();
	$result = $ws->Start("127.0.0.1", 5585, array("local_cert" => $rootpath . "/server_cert.pem", "local_pk" => $rootpath . "/server_key.pem"));
	if (!$result["success"])
	{
		var_dump($result);

		exit();
	}

	do
	{
		$result = $ws->Wait();
		if (!$result["success"])  break;

		foreach ($result["clients"] as $id => $client)
		{
			// Process the client.

			// Guaranteed to have at least the request line and headers if the request is not complete.
			// Raise the upload limit to ~10MB for requests that haven't completed yet.
			if (!$client->requestcomplete)
			{
				$options = $client->GetHTTPOptions();
				$options["recvlimit"] = 10000000;
				$client->SetHTTPOptions($options);
			}
			else
			{
				// Send the response.
				$client->SetResponseContentType("application/json");
				$client->AddResponseContent(json_encode(array("success" => true)));
				$client->FinalizeResponse();
			}
		}

		foreach ($result["removed"] as $id => $info)
		{
			// Process the removed client information (e.g. clean up various tracking arrays).
		}

	} while (1);

	var_dump($result);
?>
```

WebServer_Client::SetHTTPOptions($options)
------------------------------------------

Access:  public

Parameters:

* $options - An array containing modified options.

Returns:  Nothing.

This function sets the HTTP state options array for the client.  Applications should only modify arrays returned from WebServer_Client::GetHTTPOptions().

WebServer_Client::SetResponseCode($code)
----------------------------------------

Access:  public

Parameters:

* $code - An integer representing a valid HTTP code.

Returns:  Nothing.

This function sets the HTTP response line for completed requests.

WebServer_Client::SetResponseContentType($contenttype)
------------------------------------------------------

Access:  public

Parameters:

* $contenttype - A string containing a valid Content-Type response.

Returns:  Nothing.

This function sets the Content-Type response header if the headers haven't already been sent.

WebServer_Client::SetResponseCookie($name, $value = "", $expires = 0, $path = "", $domain = "", $secure = false, $httponly = false)
-----------------------------------------------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing the name of the cookie to set.
* $value - A string containing the value of the cookie to set (Default is "").
* $expires - An integer representing the expiration date of the cookie in UNIX timestamp format (Default is 0).
* $path - A string containing the path on which the cookie is valid (Default is "").
* $domain - A string containing the domain on which the cookie is valid (Default is "").
* $secure - A boolean that tells the browser to only send the cookie over HTTPS (Default is false).
* $httponly - A boolean that tells the browser whether or not Javascript should be able to access the cookie's value (Default is false).

Returns: Nothing.

This function adds a HTTP cookie response header if the headers haven't already been sent.

WebServer_Client::AddResponseHeader($name, $val, $replace = false)
------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing the header name.
* $val - A string containing the value for the header.
* $replace - A boolean indicating whether or not to replace all previous response headers with the same name (Default is false).

Returns:  Nothing.

This function adds a response header if the headers haven't already been sent.  Some headers can only be sent one time, which is where $replace comes into play.

WebServer_Client::AddResponseHeaders($headers, $replace = false)
----------------------------------------------------------------

Access:  public

Parameters:

* $headers - An array of key-value pairs where the key is the name of the header and each value may be a string or an array of strings.
* $replace - A boolean indicating whether or not to replace all previous response headers with matching name(s) (Default is false).

Returns:  Nothing.

This function adds multiple headers if the headers haven't already been sent.  Some headers can only be sent one time, which is where $replace comes into play.

WebServer_Client::SetResponseContentLength($bodysize)
-----------------------------------------------------

Access:  public

Parameters:

* $bodysize - An integer containing the exact length of the response or a boolean of false for automatic response mode.

Returns:  Nothing.

This function indirectly sets the response's Content-Length header.  Only call this when the exact number of bytes being sent in the response is known.

WebServer_Client::AddResponseContent($data)
-------------------------------------------

Access:  public

Parameters:

* $data - A string containing the data to add to the response body.

Returns:  Nothing.

This function queues up more data to go out with the response if the response hasn't been finalized.

WebServer_Client::FinalizeResponse()
------------------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function finalizes the response including queueing up any compressed data if gzip compression for the client is enabled.
