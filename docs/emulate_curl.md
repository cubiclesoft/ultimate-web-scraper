cURL Emulation Layer:  'support/emulate_curl.php'
=================================================

If you have a web host that doesn't support cURL but need to run a piece of software that requires cURL to function properly, the cURL emulation layer allows you to easily and quickly make most applications think that cURL is available.  Put simply, the cURL emulation layer is a drop-in replacement for cURL on web hosts that don't have cURL installed.  Somewhere early on in the execution path (e.g. an 'index.php' file), just include the cURL emulation layer and it will handle the rest.

Every define() and function is available as of PHP 5.4.0.

However, there are a few limitations and differences.  CURLOPT_VERBOSE is a lot more verbose.  SSL/TLS support is a little flaky at times.  Some things like DNS options are ignored.  Only HTTP and HTTPS are supported protocols at this time.  Return values from curl_getinfo() calls are close but not the same.  curl_setopt() delays processing until curl_exec() is called.  Multi-handle support "cheats" by performing operations in linear execution rather than parallel execution.

Example usage:

```php
<?php
	if (!function_exists("curl_init"))
	{
		require_once "support/emulate_curl.php";
	}

	// Make cURL calls here...
?>
```
