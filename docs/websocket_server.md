WebSocketServer Class:  'support/websocket_server.php'
======================================================

This class is a working WebSocket server implementation in PHP.  It won't win any performance awards.  However, this class avoids the need to set up a formal web server or compile anything and then figure out how to proxy WebSocket server Upgrade requests to another server.

Pairs nicely with the WebServer class for handing off Upgrade requests to the WebSocketServer class via the WebSocketServer::ProcessWebServerClientUpgrade() function.

Be sure to copy "websocket_server.php" into the "support" subdirectory before using it.

For example basic usage, see:

https://github.com/cubiclesoft/ultimate-web-scraper/blob/master/tests/test_websocket_server.php

For a pre-built, flexible, extendable API with user/token management and mostly transparent WebSocket support, see Cloud Storage Server:

https://github.com/cubiclesoft/cloud-storage-server

WebSocketServer::Reset()
------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function resets a class instance to the default state.  Note that WebSocketServer::Stop() should be called first as this simply resets all internal class variables.

WebSocketServer::SetWebSocketClass($newclass)
---------------------------------------------

Access:  public

Parameters:

* $newclass - A string containing a valid class name.

Returns:  Nothing.

This function assigns a new class name of the instance of a class to allocate.  The default is "WebSocket" and the specified class must extend WebSocket or there will be problems later on when clients connect in.

WebSocketServer::SetAllowedOrigins($origins)
--------------------------------------------

Access:  public

Parameters:

* $origins - A string or an array containing allowed Origin header(s) or a boolean of false to allow any Origin header.

Returns:  Nothing.

This function assigns allowed Origin HTTP header strings.  Useful for validating client connections to the WebSocket server when it is public-facing to the Internet or made available via a proxy.  Can be spoofed but can prevent XSRF attacks in real web browsers that do send valid Origin header strings.

WebSocketServer::SetDefaultCloseMode($mode)
-------------------------------------------

Access:  public

Parameters:

* $mode - One of the following constants:
  * WebSocket::CLOSE_IMMEDIATELY (default mode)
  * WebSocket::CLOSE_AFTER_CURRENT_MESSAGE
  * WebSocket::CLOSE_AFTER_ALL_MESSAGES

Returns:  Nothing.

This function sets the behavior for handling the close frame for all future clients.  The default is to send the close frame immediately and/or terminate the connection.  Both sides are supposed to receive the close frame before closing the connection.

WebSocketServer::SetDefaultKeepAliveTimeout($keepalive)
-------------------------------------------------------

Access:  public

Parameters:

* $keepalive - An integer specifying the number of seconds since the last packet received before sending a ping packet.

Returns:  Nothing.

This function sets the default timeout interval for all future clients.  The default is 30 seconds.  Whenever a packet is received, the timer resets.  If the timeout is reached, first a ping packet is sent.  If nothing is received, either a pong response packet or other packet, the connection will self-terminate.

WebSocketServer::SetDefaultMaxReadFrameSize($maxsize)
-----------------------------------------------------

Access:  public

Parameters:

* $maxsize - A boolean of false to indicate unlimited size or an integer specifying the maximum number of bytes for single frame payload.

Returns:  Nothing.

This function sets the limit on received frame size for all future clients.  Without it, a malicious client or server could potentially consume all available system resources.  The default is 2000000 (2 million) bytes.  This setting has no effect on the maximum received message size since multiple frames can make up a complete message.  This also helps keeps individual frame size down to a reasonable limit.

WebSocketServer::SetDefaultMaxReadMessageSize($maxsize)
-------------------------------------------------------

Access:  public

Parameters:

* $maxsize - A boolean of false to indicate unlimited size or an integer specifying the maximum number of bytes for single message payload.

Returns:  Nothing.

This function sets the limit on message size for all future clients.  Without it, a malicious client or server could potentially consume all available system resources.  The default is 10000000 (10 million) bytes.

WebSocketServer::Start($host, $port)
------------------------------------

Access:  public

Parameters:

* $host - A string containing the host IP to bind to.
* $port - A port number to bind to.  On some systems, ports under 1024 are restricted to root/admin level access only.

Returns:  An array containing the results of the call.

This function starts a WebSocket server on the specified host and port.  The socket is also set to asynchronous (non-blocking) mode.  Some useful values for the host:

* `0.0.0.0` to bind to all IPv4 interfaces.
* `127.0.0.1` to bind to the localhost IPv4 interface.
* `[::0]` to bind to all IPv6 interfaces.
* `[::1]` to bind to the localhost IPv6 interface.

To select a new port number for a server, use the following link:

https://www.random.org/integers/?num=1&min=5001&max=49151&col=5&base=10&format=html&rnd=new

If it shows port 8080, just reload to get a different port number.

WebSocketServer::Stop()
-----------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function stops a WebSocket server after disconnecting all clients and resets some internal variables in case the class instance is reused.

WebSocketServer::GetStream()
----------------------------

Access:  public

Parameters:  None.

Returns:  The underlying socket stream (PHP resource) or a boolean of false if the server is not started.

This function is considered "dangerous" as it allows direct access to the underlying stream.  However, as long as it is only used with functions like PHP stream_select() and Wait() is used to do actual management, it should be safe enough.  This function is intended to be used where there are multiple handles being waited on (e.g. handling multiple connections to multiple WebSocket servers).

WebSocketServer::UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
-----------------------------------------------------------------------------------

Access:  public

Parameters:

* $prefix - A unique prefix to identify the various streams (server and client handles).
* $timeout - An integer reference containing the maximum number of seconds or a boolean of false.
* $readfps - An array reference to add streams wanting data to arrive.
* $writefps - An array reference to add streams wanting to send data.

Returns:  Nothing.

This function updates the timeout and read/write arrays with prefixed names so that a single stream_select() call can manage all sockets.

WebSocketServer::FixedStreamSelect(&$readfps, &$writefps, &$exceptfps, $timeout)
--------------------------------------------------------------------------------

Access:  public static

Parameters:  Same as stream_select() minus the microsecond parameter.

Returns:  A boolean of true on success, false on failure.

This function allows key-value pairs to work properly for the usual read, write, and except arrays.  PHP's stream_select() function is buggy and sometimes will return correct keys and other times not.  This function is called by Wait().  Directly calling this function is useful if multiple servers are running at a time (e.g. one public SSL server, one localhost non-SSL server).

WebSocketServer::Wait($timeout = false)
---------------------------------------

Access:  public

Parameters:

* $timeout - A boolean of false or an integer containing the number of seconds to wait for an event to trigger such as a write operation to complete (default is false).

Returns:  An array containing the results of the call.

This function is the core of the WebSocketServer class and should be called frequently (e.g. a while loop).  It handles new connections, the initial conversation, basic packet management, and timeouts.  The extra optional arrays to the call allow the function to wait on more than just sockets, which is useful when waiting on other asynchronous resources.

This function returns an array of clients that were responsive during the call and may have things to do such as Read() incoming messages.  It will also return clients that are no longer connected so that the application can have a chance to clean up resources associated with the client.

WebSocketServer::ProcessWaitResult(&$result)
--------------------------------------------

Access:  protected

Parameters:

* $result - An array of standard information containing file handles.

Returns:  Nothing.

This function processes the result of the Wait() function.  Derived classes may call this function (e.g. LibEvWebSocketServer).

WebSocketServer::ProcessClientQueuesAndTimeoutState(&$result, $id, $read, $write)
---------------------------------------------------------------------------------

Access:  protected

Parameters:

* $result - An array to store the result and/or client information.
* $id - An integer containing an ID of an active client.
* $read - A boolean that indicates that data is available to be read.
* $write - A boolean that indicates that the connection is ready for more data to be written to it.

Returns:  Nothing.

This internal function calls ProcessQueuesAndTimeoutState() on the specified WebSocket instance.  If the call fails, the connection is removed.  The results of the call decide where in the result array the client will end up.

WebSocketServer::GetClients()
-----------------------------

Access:  public

Parameters: None.

Returns:  An array of all of the active clients.

This function makes it easy to retrieve the entire list of clients currently connected to the server.  Note that this may include clients that are in the process of connecting and upgrading to the WebSocket protocol.

WebSocketServer::NumClients()
-----------------------------

Access:  public

Parameters:  None.

Returns:  The number of active clients.

This function returns the number clients currently connected to the server.  It's more efficient to call this function than to get a copy of the clients array just to `count()` them.

WebSocketServer::UpdateClientState($id)
---------------------------------------

Access:  public

Parameters:

* $id - An integer containing the ID of the client to update the internal state for.

Returns:  Nothing.

This function does nothing by default.  Derived classes may maintain internal technical state for optimized performance later on (e.g. LibEvWebSocketServer updates read/write notification state for the socket descriptor for use with a later Wait() call).  It is recommended that this function be called after calling $ws->Write() on a specific WebSocket.

WebSocketServer::GetClient($id)
-------------------------------

Access:  public

Parameters:

* $id - An integer containing the ID of the client to retrieve.

Returns:  An array containing client information associated with the ID if it exists, a boolean of false otherwise.

This function retrieves a single client array by its ID.

WebSocketServer::RemoveClient($id)
----------------------------------

Access:  public

Parameters:

* $id - An integer containing the ID of the client to retrieve.

Returns:  Nothing.

This function terminates a specified client by ID.  This is the correct way to disconnect a client.  Do not use $client["websocket"]->Disconnect() directly.

WebSocketServer::ProcessWebServerClientUpgrade($webserver, $client)
-------------------------------------------------------------------

Access:  public

Parameters:

* $webserver - An instance of the WebServer class.
* $client - An instance of WebServer_Client directly associated with the WebServer class.

Returns:  An integer representing the new WebSocketServer client ID on success, false otherwise.

This function determines if the client is attempting to Upgrade to WebSocket.  If so, it detaches the client from the WebServer instance and associates a new client with the WebSocketServer instance.  Note that the WebSocketServer instance does not require WebSocketServer::Start() to have been called.

WebSocketServer::ProcessNewConnection($method, $path, $client)
--------------------------------------------------------------

Access:  protected

Parameters:

* $method - A string containing the HTTP method (supposed to be "GET").
* $path - A string containing the path portion of the request.
* $client - An array containing introductory information about the new client (parsed headers, etc).

Returns:  A string containing the HTTP response, if any, an empty string otherwise.

This function handles basic requirements of the WebSocket protocol and will reject obviously bad connections with the appropriate HTTP response string.  However, the function can be overridden in a derived class.  This class can also call SetWebSocketClass() to switch the class to instantiate just before it is instantiated by the caller.

WebSocketServer::ProcessAcceptedConnection($method, $path, $client)
-------------------------------------------------------------------

Access:  protected

Parameters:

* $method - A string containing the HTTP method (supposed to be "GET").
* $path - A string containing the path portion of the request.
* $client - An array containing nearly complete information about the new client (parsed headers, etc).

Returns:  A string containing additional HTTP headers to add to the response, if any, otherwise an empty string.

This function is called if the connection is being accepted.  That is, ProcessNewConnection() returned an empty string.  The default function does nothing but it can be overridden in a derived class to handle things such as custom protocols and extensions.

WebSocketServer::InitNewClient($fp)
-----------------------------------

Access:  protected

Parameters:

* $fp - A stream resource or a boolean of false.

Returns:  The new stdClass instance.

This function creates a new client object.  Since there isn't anything terribly complex about the object, stdClass is used instead of something formal.

WebSocketServer::ProcessInitialResponse($method, $path, $client)
----------------------------------------------------------------

Access:  private

Parameters:

* $method - A string containing the HTTP method (supposed to be "GET").
* $path - A string containing the path portion of the request.
* $client - An array containing nearly complete information about the new client (parsed headers, etc).

Returns:  Nothing.

This function performs a standard initial response to the client as to whether or not their request to Upgrade to the WebSocket protocol was successful.  It also creates the underlying WebSocket client object and sets it to server mode.

WebSocketServer::HeaderNameCleanup($name)
-----------------------------------------

Access:  _internal_ static

Parameters:

* $name - A string containing a HTTP header name.

Returns:  A string containing a purified HTTP header name.

This internal static function cleans up a HTTP header name.

WebSocketServer::WSTranslate($format, ...)
------------------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.
