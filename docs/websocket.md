WebSocket Class:  'support/websocket.php'
=========================================

This class provides client-side routines to communicate with a WebSocket server (RFC 6455) and enable live scraping of data from such servers.  The WebSocket class allows a web scraping application to bring in data immediately as soon as it becomes available.

Example usage:

```php
<?php
	require_once "support/websocket.php";

	$ws = new WebSocket();

	// The first parameter is the WebSocket server to connect to.
	// The second parameter is the Origin URL.  Some WebSocket servers require it.
	$result = $ws->Connect("ws://ws.something.org/", "http://www.something.org");
	if (!$result["success"])
	{
		var_dump($result);
		exit();
	}

	// Send a text frame (just an example).
	$result = $ws->Write("Testtext", WebSocket::FRAMETYPE_TEXT);

	// Send a binary frame (just an example).
	$result = $ws->Write("Testbinary", WebSocket::FRAMETYPE_BINARY);

	// Main loop.
	do
	{
		$result = $ws->Wait();
		if (!$result["success"])  break;

		do
		{
			$result = $ws->Read();
			if (!$result["success"])  break;
			if ($result["data"] !== false)
			{
				// Do something with the data.
				var_dump($result["data"]);
			}
		} while ($result["data"] !== false);
	} while (1);

	// An error occurred.
	var_dump($result);
?>
```

Another example:

https://github.com/cubiclesoft/ultimate-web-scraper/blob/master/tests/test_websocket_client.php

WebSocket::Reset()
------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function resets a class instance to the default state.  Note that Disconnect() should be called first as this simply resets all internal class variables.

WebSocket::SetServerMode()
--------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function switches the client to server mode.  Prefer using the WebSocketServer class for implementing an actual WebSocket server.

WebSocket::SetClientMode()
--------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function switches to client mode.  This is the default mode.

WebSocket::SetExtensions($extensions)
-------------------------------------

Access:  public

Parameters:

* $extensions - An array containing a list of extensions to support.

Returns:  Nothing.

This function replaces the internal extensions variable.  It currently has no effect but is intended to be used to support whatever IETF standards track extensions are eventually invented (if any).  The "permessage-deflate" extension seems to be headed this route.

WebSocket::SetCloseMode($mode)
------------------------------

Access:  public

Parameters:

* $mode - One of the following constants:
  * WebSocket::CLOSE_IMMEDIATELY (default mode)
  * WebSocket::CLOSE_AFTER_CURRENT_MESSAGE
  * WebSocket::CLOSE_AFTER_ALL_MESSAGES

Returns:  Nothing.

This function sets the behavior for handling the close frame.  The default is to send the close frame immediately and/or terminate the connection.  Both sides are supposed to receive the close frame before closing the connection.

WebSocket::SetKeepAliveTimeout($keepalive)
------------------------------------------

Access:  public

Parameters:

* $keepalive - An integer specifying the number of seconds since the last packet received before sending a ping packet.

Returns:  Nothing.

This function sets the timeout interval.  The default is 30 seconds.  Whenever a packet is received, the timer resets.  If the timeout is reached, first a ping packet is sent.  If nothing is received for another timeout period, neither a pong response packet nor other packet, the connection will self-terminate.

WebSocket::GetKeepAliveTimeout()
--------------------------------

Access:  public

Parameters:  None.

Returns:  An integer specifying the current keep alive timeout period.

This function returns the internal keep alive timeout value.

WebSocket::SetMaxReadFrameSize($maxsize)
----------------------------------------

Access:  public

Parameters:

* $maxsize - A boolean of false to indicate unlimited size or an integer specifying the maximum number of bytes for single frame payload.

Returns:  Nothing.

This function sets the limit on received frame size.  Without it, a malicious client or server could potentially consume all available system resources.  The default is 2000000 (2 million) bytes.  This setting has no effect on the maximum received message size since multiple frames can make up a complete message.  This also helps keeps individual frame size down to a reasonable limit.

WebSocket::SetMaxReadMessageSize($maxsize)
------------------------------------------

Access:  public

Parameters:

* $maxsize - A boolean of false to indicate unlimited size or an integer specifying the maximum number of bytes for single message payload.

Returns:  Nothing.

This function sets the limit on message size.  Without it, a malicious client or server could potentially consume all available system resources.  The default is 10000000 (10 million) bytes.

WebSocket::GetRawRecvSize()
---------------------------

Access:  public

Parameters:  None.

Returns:  An integer containing the total number of bytes received.

This function retrieves the total number of bytes received, which includes frame bytes.

WebSocket::GetRawSendSize()
---------------------------

Access:  public

Parameters:  None.

Returns:  An integer containing the total number of bytes sent.

This function retrieves the total number of bytes sent, which includes frame bytes.

WebSocket::Connect($url, $origin, $profile = "auto", $options = array(), $web = false)
--------------------------------------------------------------------------------------

Access:  public

Parameters:

* $url - A string containing a WebSocket URL (starts with ws:// or wss://).
* $origin - A string containing an Origin URL.
* $profile - A string containing a valid WebBrowser class profile string (default is "auto").
* $options - An array of valid WebBrowser class options (Default is array()).
* $web - A valid WebBrowser class instance (default is false, which means one will be created).

Returns:  An array containing the results of the call.

This function initiates a connection to a WebSocket server via the WebBrowser class.  If you set up your own WebBrowser class (e.g. to handle cookies), pass it in as the $web parameter to use it for the connection.

$origin is a required parameter because servers expect only web browsers to connect to WebSocket servers and the specification (RFC 6455) clearly states that only automated processes will not send the Origin header but web browsers must do so.  Sending the Origin header is necessary to mimic a web browser.

WebSocket::Disconnect()
-----------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function disconnects an active connection after sending/receiving the close frame (clean shutdown) and resets a few internal variables in case the class is reused.  Note that it is better to call Disconnect() instead of letting the destructor handle shutting down the connection so that a graceful shutdown may take place.

WebSocket::Read($finished = true, $wait = false)
------------------------------------------------

Access:  public

Parameters:

* $finished - A boolean indicating whether or not to return finished messages (default is true).
* $wait - A boolean indicating whether or not to wait until a message has arrived that meets the rest of the function call criteria (default is false).

Returns:  An array containing the results of the call.

This function performs an asynchronous read operation on the message queue (except if $wait is true).  When $finished is false, the returned message may be a fragment, which can be useful if there is a large amount of data flowing in for a single message.

It is a good idea to clear out the read queue at every opportunity.  WebSocket servers tend to push lots of messages, so multiple messages may queue up.  Always call Read() multiple times until there are no more messages before doing another Wait().

WebSocket::Write($message, $frametype, $last = true, $wait = false)
-------------------------------------------------------------------

Access:  public

Parameters:

* $message - A string containing the message to send.
* $frametype - One of the following constants:
  * WebSocket::FRAMETYPE_TEXT - A UTF-8 text frame.
  * WebSocket::FRAMETYPE_BINARY - A binary frame.
  * WebSocket::FRAMETYPE_CONNECTION_CLOSE - To close the connection. Call Disconnect() instead.
  * WebSocket::FRAMETYPE_PING - A ping frame.  Let WebSocket manage keep-alives.
  * WebSocket::FRAMETYPE_PONG - A pong frame.  Let WebSocket manage keep-alives.
* $last - A boolean indicating whether or not this is the last fragment of the message (default is true).
* $wait - A boolean indicating whether or not to wait until the write queue has been emptied (default is false).

Returns:  An array containing the results of the call.

This function sends a single frame.  Applications should only ever need to use the first two frame types.  The class manages the other three types via other high-level functions such as Wait() and Disconnect().

Indicate a fragmented message by using $last.  Set $last to false until the last fragment, at which point set it to true (the default).

WebSocket::NeedsWrite()
-----------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if there is data ready to be written to the socket, false otherwise.

This function transforms up to 65KB of data for writing from the write queue into output frames and returns whether or not there is data for writing.  This function can be useful in conjunction with GetStream() when handling multiple streams.

WebSocket::GetStream()
----------------------

Access:  public

Parameters:  None.

Returns:  The underlying socket stream (PHP resource) if connected, a boolean of false otherwise.

This function is considered "dangerous" as it allows direct access to the underlying data stream.  However, as long as it is only used with functions like PHP stream_select() and Wait() is used to do actual management, it should be safe enough.  This function is intended to be used where there are multiple handles being waited on (e.g. handling multiple connections to multiple WebSocket servers).

WebSocket::Wait($timeout = false)
---------------------------------

Access:  public

Parameters:

* $timeout - A boolean of false or an integer containing the number of seconds to wait for an event to trigger such as a write operation to complete (default is false).

Returns:  An standard array of information.

This function waits until an event occurs such as data arriving, the write end clearing so more data can be sent, or the "nothing has happened for a while, so send a keepalive" timeout.  Then WebSocket::ProcessQueuesAndTimeoutState() is called.  This function is the core of the WebSocket class and should be called frequently (e.g. a while loop).

WebSocket::ProcessQueuesAndTimeoutState($read, $write)
------------------------------------------------------

Access:  _internal_

Parameters:

* $read - A boolean that indicates that data is available to be read.
* $write - A boolean that indicates that the connection is ready for more data to be written to it.

Returns:  An standard array of information.

This mostly internal function handles post-Wait() queue processing and a keepalive, if necessary, is queued to be sent.  It is declared public so that WebSocketServer can call it to handle the queues and timeout state for an individual client.

WebSocket::ProcessReadData()
----------------------------

Access:  protected

Parameters:  None.

Returns:  An array containing the results of the call.

This internal function extracts complete frames that have been read in and puts them into the read queue for a later Read() call to handle.  Control messages are automatically handled here.

WebSocket::ReadFrame()
----------------------

Access:  protected

Parameters:  None.

Returns:  A boolean of false if there isn't enough data for a complete frame otherwise an array containing the results of the call.

This internal function attempts to read in the next complete frame from the data that has been read in from the underlying socket.

WebSocket::FillWriteData()
--------------------------

Access:  protected

Parameters:  None.

Returns:  Nothing.

This internal function processes messages to be sent into complete frames until at least 65KB of data is scheduled to be written or all messages have been removed from the queue, whichever comes first.

WebSocket::WriteFrame($fin, $opcode, $payload)
----------------------------------------------

Access:  protected

Parameters:

* $fin - A boolean indicating whether or not this is the final frame in a message.
* $opcode - A valid frame type which may also be a continuation frame type. WebSocket automatically determines continuation frames.
* $payload - A string containing part or all of the message to send.

Returns:  Nothing.

This internal function creates and writes the frame to the internal write data buffer to be sent.

WebSocket::PRNGBytes($length)
-----------------------------

Access:  protected

Parameters:

* $length - An integer containing the length of the desired output.

Returns:  A string containing $length pseudorandom bytes.

This internal function follows the RFC 6455 specification for various needs if CSPRNG is available, but it isn't necessary to do so.  Rely on WebSocket over SSL (wss://) for actual security.

WebSocket::UnpackInt($data)
---------------------------

Access:  _internal_ static

Parameters:

* $data - A string containing a big-endian value.

Returns:  An integer or double containing the unpacked value.

This internal static function is used to process the payload length of incoming frames.

WebSocket::PackInt64($num)
--------------------------

Access:  _internal_ static

Parameters:

* $num - An integer or double containing the value to pack into an 8 byte string.

Returns:  An 8-byte string containing the packed number.

This internal function is used to generate the correct payload length for outgoing frames.

WebSocket::WSTranslate($format, ...)
------------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.
