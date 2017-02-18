MultiAsyncHelper Class:  'support/multi_async_helper.php'
=========================================================

Asynchronous, or non-blocking, sockets allow for a lot of powerful functionality such as scraping multiple pages and sites simultaneously from a single script.  However, management of the entire process can result in a lot of extra code.  This class manages the nitty-gritty details of queueing up and simultaneously retrieving content from multiple URLs.  It is a powerful class, though, which means it can be used for other I/O related things besides sockets (e.g. files).

Example usage:

```php
<?php
	require_once "support/web_browser.php";
	require_once "support/multi_async_helper.php";

	// The URLs we want to load.
	$urls = array(
		"http://www.barebonescms.com/",
		"http://www.cubiclesoft.com/",
		"http://www.barebonescms.com/documentation/ultimate_web_scraper_toolkit/",
		"http://www.jb64.org/",
	);

	// Build the queue.
	$helper = new MultiAsyncHelper();
	$helper->SetConcurrencyLimit(3);

	// Add the URLs to the async helper.  The WebBrowser class knows how to correctly queue up a fully-managed request.
	$pages = array();
	foreach ($urls as $url)
	{
		$key = $url;

		$pages[$key] = new WebBrowser();

		// Definition:  public function WebBrowser::ProcessAsync($helper, $key, $callback, $url, $profile = "auto", $tempoptions = array())
		$pages[$key]->ProcessAsync($helper, $key, NULL, $url);
	}

	// Run the main loop.
	$result = $helper->Wait();
	while ($result["success"])
	{
		// Process finished pages.
		foreach ($result["removed"] as $key => $info)
		{
			if (!$info["result"]["success"])  echo "Error retrieving URL (" . $key . ").  " . $info["result"]["error"] . "\n";
			else if ($info["result"]["response"]["code"] != 200)  echo "Error retrieving URL (" . $key . ").  Server returned:  " . $info["result"]["response"]["line"] . "\n";
			else
			{
				echo "A response was returned (" . $key . ").\n";

				// Do something with the data here...
			}

			unset($pages[$key]);
		}

		// Break out of the loop when nothing is left.
		if ($result["numleft"] < 1)  break;

		$result = $helper->Wait();
	}

	// An error occurred.
	if (!$result["success"])  var_dump($result);
?>
```

MultiAsyncHelper::SetConcurrencyLimit($limit)
---------------------------------------------

Access:  public

Parameters:

* $limit - An integer that specifies the maximum number of running/active items at one time or a boolean of false for no limit.

Returns:  Nothing.

This function sets the maximum number of running/active items.  Items in the queue are automatically moved to the active state whenever space is freed up.  Note that changing this has no impact on existing items that are already in the active state.

MultiAsyncHelper::Set($key, $obj, $callback)
--------------------------------------------

Access:  public

Parameters:

* $key - A string representing the key to use to identify $obj in the future.
* $obj - A valid stream_select() compatible I/O object.  This can be a socket, a file, or anything else that PHP supports.
* $callback - A string or an array to a callback function to call whenever the mode changes in relation to the object.  The callback must specify four parameters - callback($mode, &$data, $key, $fp).

Returns:  Nothing.

This function will place the specified object in the internal queue and associate it with the specified key.  If this function is called in the future with an identical key, any existing object with the same key still in the class will be removed and the new object will be placed in the queue.

MultiAsyncHelper::GetObject($key)
---------------------------------

Access:  public

Parameters:

* $key - A string representing the key associated with an object.

Returns:  The associated object if it still exists, a boolean of false otherwise.

This function retrieves the object associated with the specified key.

MultiAsyncHelper::SetCallback($key, $callback)
----------------------------------------------

Access:  public

Parameters:

* $key - A string representing the key associated with an object.
* $callback - A string or an array to a callback function to call whenever the mode changes in relation to the object.  The new callback must specify four parameters - callback($mode, &$data, $key, $fp).

Returns:  Nothing.

This function replaces the existing callback associated with the object with the new callback.  This could be used, for example, to replace a read-only callback with a write-only callback to send a response to a request.  However, it may be simpler to use a single callback that can correctly manage state.

MultiAsyncHelper::Detach($key)
------------------------------

Access:  public

Parameters:

* $key - A string representing the key associated with an object.

Returns:  The detached object if successful, a boolean of false otherwise.

This function detaches the object from the internal structures.  The associated callback is called with a $mode of "cleanup" and $data is false.  The callback should perform whatever actions are necessary to pause operations (if any).

MultiAsyncHelper::Remove($key)
------------------------------

Access:  public

Parameters:

* $key - A string representing the key associated with an object.

Returns:  The removed object if successful, a boolean of false otherwise.

This function detaches the object from the internal structures.  The associated callback is called with a $mode of "cleanup" and $data is true.  The callback should perform whatever actions are necessary to end all operations with the object.  Normally, you won't need to call this unless you want to perform some sort of timeout if an object is unresponsive for too long.

MultiAsyncHelper::Wait($timeout = false)
----------------------------------------

Access:  public

Parameters:

* $timeout - An integer representing the maximum number of seconds to wait before continuing on anyway or a boolean of false to wait indefinitely (Default is false).

Returns:  A standard array of information.

This function moves waiting items in the queue to the active state up to the concurrency limit, runs callbacks to process the active queue items, waits for up to the specified timeout period for something to happen via stream_select(), and returns the result of the operation.  The response can include many items or no items to work on.

MultiAsyncHelper::ReadOnly($mode, &$data, $key, $fp)
----------------------------------------------------

Access:  public static

Parameters:

* $mode - A string representing the mode/state to process.
* $data - Mixed content the depends entirely on the $mode.
* $key - A string representing the key associated with an object.
* $fp - The object associated with the key.

Returns:  Nothing.

This static callback function is intended for use with direct non-blocking file/socket handles.  Using this function as the callback will cause Wait() to only wait for readability on the object.

MultiAsyncHelper::WriteOnly($mode, &$data, $key, $fp)
-----------------------------------------------------

Access:  public static

Parameters:

* $mode - A string representing the mode/state to process.
* $data - Mixed content the depends entirely on the $mode.
* $key - A string representing the key associated with an object.
* $fp - The object associated with the key.

Returns:  Nothing.

This static callback function is intended for use with direct non-blocking file/socket handles.  Using this function as the callback will cause Wait() to only wait for writability on the object.

MultiAsyncHelper::ReadAndWrite($mode, &$data, $key, $fp)
--------------------------------------------------------

Access:  public static

Parameters:

* $mode - A string representing the mode/state to process.
* $data - Mixed content the depends entirely on the $mode.
* $key - A string representing the key associated with an object.
* $fp - The object associated with the key.

Returns:  Nothing.

This static callback function is intended for use with direct non-blocking file/socket handles.  Using this function as the callback will cause Wait() to only wait for either readability or writability on the object, whichever happens first (or both).

MultiAsyncHelper::InternalDetach($key, $cleanup)
------------------------------------------------

Access:  private

Parameters:

* $key - A string representing the key associated with an object.
* $cleanup - A boolean indicating the value to pass to $data in the callback associated with the object.

Returns:  The removed object if successful, a boolean of false otherwise.

This internal function is called by Detach() and Remove().

MultiAsyncHelper::MAHTranslate($format, ...)
---------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.
