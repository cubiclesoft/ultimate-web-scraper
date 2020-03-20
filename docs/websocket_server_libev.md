LibEvWebSocketServer Class:  'support/websocket_server_libev.php'
=================================================================

This class overrides specific functions of WebSocketServer to add PECL ev and libev support.  This class is designed to require only minor code changes in order to support PECL ev.

While the base WebSocketServer class can be used with the WebServer class, using LibEvWebSocketServer with the WebServer class is not recommended at this time.

For example usage, see [Data Relay Center](https://github.com/cubiclesoft/php-drc).

LibEvWebSocketServer::IsSupported()
-----------------------------------

Access:  public static

Parameters:  None.

Returns:  A boolean of true if the PECL ev extension is available and will function on the platform, false otherwise.

This static function returns whether or not the class will work.  Since libev doesn't use I/O Completion Ports (IOCP) on Windows, the function always returns false for PHP on Windows.

LibEvWebSocketServer::Reset()
-----------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function resets a class instance to the default state.  Note that LibEvWebSocketServer::Stop() should be called first as this simply resets all internal class variables.

LibEvWebSocketServer::Internal_LibEvHandleEvent($watcher, $revents)
-------------------------------------------------------------------

Access:  _internal_

Parameters:

* $watcher - An object containing a PECL ev watcher.
* $revents - An integer containing a set of watcher event flags.

Returns:  Nothing.

This internal callback function handles PECL ev socket events that are fired.

LibEvWebSocketServer::Start($host, $port)
-----------------------------------------

Access:  public

Parameters:

* $host - A string containing the host IP to bind to.
* $port - A port number to bind to.  On some systems, ports under 1024 are restricted to root/admin level access only.

Returns:  An array containing the results of the call.

This function starts a WebSocket server on the specified host and port.  Identical to WebSocketServer::Start() but also registers for read events on the server socket handle to accept connections.

LibEvWebSocketServer::Stop()
----------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function stops a WebSocket server after disconnecting all clients and resets some internal variables in case the class instance is reused.  Also stops all registered event watchers.

LibEvWebSocketServer::InitNewClient($fp)
----------------------------------------

Access:  protected

Parameters:

* $fp - A stream resource or a boolean of false.

Returns:  The new stdClass instance.

This function creates a new client object.  Identical to WebSocketServer::InitNewClient() but also registers for read events on the socket handle.

LibEvWebSocketServer::UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
----------------------------------------------------------------------------------------

Access:  public

Parameters:

* $prefix - A unique prefix to identify the various streams (server and client handles).
* $timeout - An integer reference containing the maximum number of seconds or a boolean of false.
* $readfps - An array reference to add streams wanting data to arrive.
* $writefps - An array reference to add streams wanting to send data.

Returns:  Nothing.

This function updates the timeout and read/write arrays with prefixed names so that a single stream_select() call can manage all sockets.

Calling this function is not recommended when working with PECL ev.

LibEvWebSocketServer::Internal_LibEvTimeout($watcher, $revents)
---------------------------------------------------------------

Access:  _internal_

Parameters:

* $watcher - An object containing a PECL ev watcher.
* $revents - An integer containing a set of watcher event flags.

Returns:  Nothing.

This internal callback function handles PECL ev timer events that are fired.

LibEvWebSocketServer::Wait($timeout = false)
--------------------------------------------

Access:  public

Parameters:

* $timeout - A boolean of false or an integer containing the number of seconds to wait for an event to trigger such as a write operation to complete (default is false).

Returns:  An array containing the results of the call.

This function is the core of the LibEvWebSocketServer class and should be called frequently (e.g. a while loop).  It runs the libev event loop one time and processes WebSocket clients that are returned.

LibEvWebSocketServer::UpdateClientState($id)
--------------------------------------------

Access:  public

Parameters:

* $id - An integer containing the ID of the client to update the internal state for.

Returns:  Nothing.

This function updates the watcher for the client for read/write handling during the next Wait() operation.  It is recommended that this function be called after calling $ws->Write() on a specific WebSocket.

LibEvWebSocketServer::RemoveClient($id)
---------------------------------------

Access:  public

Parameters:

* $id - An integer containing the ID of the client to retrieve.

Returns:  Nothing.

This function terminates a specified client by ID.  Identical to WebSocketServer::RemoveClient() and also stops the PECL ev watcher associated with the client.
