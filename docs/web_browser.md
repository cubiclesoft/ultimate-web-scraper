WebBrowser Classes:  'support/web_browser.php'
==============================================

The WebBrowser class adds a friendly layer over the HTTP class.  Specifically, it adds automatic cookie handling, following redirects, handling the HTTP "Referer" header, emulating various web browser headers (e.g. Firefox), and optionally extracting forms.  In addition, it also comes with an interactive mode for filling in HTML forms via an interactive command-line shell (of sorts).

WebBrowser also supports direct, simple integration with the MultiAsyncHandler class for performing and processing simultaneous, bulk requests.

Example usage:

```php
<?php
	require_once "support/web_browser.php";
	require_once "support/tag_filter.php";

	// Retrieve the standard HTML parsing array for later use.
	$htmloptions = TagFilter::GetHTMLOptions();

	// Retrieve a URL.
	$url = "http://www.somesite.com/something/";
	$web = new WebBrowser();
	$result = $web->Process($url);

	// Check for connectivity and response errors.
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

WebBrowser::__construct($prevstate = array())
---------------------------------------------

Access:  public

Parameters:

* $prevstate - An array containing the previous WebBrowser state (Default is array()).

Returns:  Nothing.

This function initializes a WebBrowser instance.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	// Enable automatic extraction of forms from downloaded content.
	$web = new WebBrowser(array("extractforms" => true));

	$result = $web->Process($url);

	// Check for connectivity and response errors.
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else if (count($result["forms"]) != 1)  echo "Error retrieving URL.  Server returned " . count($result["forms"]) . " forms.  Was expecting one form.\n";
	else
	{
		$form = $result["forms"][0];

		$form->SetFormValue("username", "someuser");
		$form->SetFormValue("password", "passwordgoeshere");

		$result = $form->GenerateFormRequest();
		$result = $web->Process($result["url"], $result["options"]);

		var_dump($result);
	}
?>
```

WebBrowser::ResetState()
------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function resets a WebBrowser instance state to the default state.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// Retrieve a URL.
	$url = "http://www.somesite.com/something/";

	$web = new WebBrowser(array("extractforms" => true));
	$result = $web->Process($url);

	// Reset the state (wipes cookies, resets Referer tracking, etc).
	$web->ResetState();
?>
```

WebBrowser::SetState($options = array())
----------------------------------------

Access: public

Parameters:

* $options - An array containing a partial or complete WebBrowser state (Default is array()).

Returns:  Nothing.

This function modifies the internal structures of a WebBrowser instance by merging in the specified options.  Valid options are:

* allowedprotocols - An array containing key-value pairs where the key is a string containing a protocol of "http" or "https" and the value is a boolean that specifies whether or not to allow the protocol (Default is array("http" => true, "https" => true)).
* allowedredirprotocols - An array containing key-value pairs where the key is a string containing a protocol of "http" or "https" and the value is a boolean that specifies whether or not to allow the protocol to be used in a redirect (Default is array("http" => true, "https" => true)).
* hostauths - An array containing key-value pairs of Host to Authorization mappings (Default is array()).  Allows for redirects to traverse multiple hosts.
* cookies - An array containing cookies (Default is array()).  This is intended to be for internal use only.
* referer - A string containing the HTTP referer to use (Default is "").
* autoreferer - A boolean that specifies whether or not to automatically change the 'referer' option (Default is true).
* useragent - A string containing a valid GetWebUserAgent() string (Default is "firefox").
* followlocation - A boolean that specifies whether or not to follow 'Location:' header responses (Default is true).
* maxfollow - An integer containing the maximum number of 'Location:'s to follow (Default is 20).
* extractforms - A boolean that specifies whether or not to automatically locate and extract forms from the returned HTML content (Default is false).
* httpopts - An array containing default options to use for every request (Default is array()).

Some of the information in the instance state can be overridden temporarily with the WebBrowser::Process() call.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	// Don't enable form extraction at first.
	$web = new WebBrowser();

	$result = $web->Process($url);

	// Enable automatic extraction of forms from downloaded content.
	$web->SetState(array("extractforms" => true));

	// Disable automatic extraction of forms from downloaded content.
	$web->SetState(array("extractforms" => false));
?>
```

WebBrowser::GetState()
----------------------

Access:  public

Parameters:  None.

Returns: An array containing the current state of the WebBrowser instance.

This function is useful for saving the state of a WebBrowser instance for later use with another WebBrowser instance.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	$web = new WebBrowser();

	$result = $web->Process($url);

	// Save state for later (cookies, etc).
	file_put_contents("web1.json", json_encode($web->GetState()));
?>
```

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/somewhereelse/";

	// Load the previous state.
	$prevstate = json_decode(file_get_contents("web1.json"));
	$web = new WebBrowser($prevstate);

	$result = $web->Process($url);
?>
```

WebBrowser::ProcessState(&$state)
---------------------------------

Access:  public

Parameters:

* $state - A valid WebBrowser state array.

Returns:  A standard array of information.

This internal-ish function runs the core state engine behind the scenes against the input state.  This is the primary workhorse of the WebBrowser class.

WebBrowser::Process($url, $tempoptions = array())
-------------------------------------------------

Access:  public

Parameters:

* $url - A string containing a valid URL.
* $tempoptions - An array containing options to use for only this set of requests (Default is array()).

Returns:  An array containing the results of the call.

This function processes a request for a specific URL against the specified profile and the internal state of the WebBrowser instance.  $tempoptions is used to construct an options array for a `HTTP::RetrieveWebpage()` call.

When the instance state variable "extractforms" is true, a successful result will also automatically extract forms from the HTML response.  This feature won't work as expected if body callbacks are used.

A previous version of this function had the prototype of `WebBrowser::Process($url, $profile = "auto", $tempoptions = array())`.  The `profile` option is part of the `$tempoptions` array now and is a string containing an empty string, "auto", or a valid `HTTP::GetWebUserAgent()` string (Default is "auto").  The cURL emulation layer (emulate_curl.php) uses a `profile` of "" (empty string) and relies on its own settings instead being passed through `$tempoptions` to accomplish similar results.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	$web = new WebBrowser();

	$result = $web->Process($url);

	// Save state for later (cookies, etc).
	file_put_contents("web1.json", json_encode($web->GetState()));
?>
```

WebBrowser::ProcessAsync($helper, $key, $callback, $url, $tempoptions = array())
--------------------------------------------------------------------------------

Access:  public

Parameters:

* $helper - A MultiAsyncHelper instance.
* $key - A string containing a key to uniquely identify this WebBrowser instance.
* $callback - An optional callback function to receive regular status updates on the request (specify NULL if not needed).  The callback function must accept three parameters - callback($key, $url, $result).
* $url - A string containing a valid URL.
* $tempoptions - An array containing options to use for only this set of requests (Default is array()).

Returns:  A standard array of information.

This function queues the request with the MultiAsyncHandler instance ($helper) for later async/non-blocking processing of the request.  Note that this function always succeeds since request failure can't be detected until after processing begins.

See MultiAsyncHelper for example usage.

A previous version of this function had the prototype of `ProcessAsync($helper, $key, $callback, $url, $profile = "auto", $tempoptions = array())`.  The `profile` option is part of the `$tempoptions` array now and is a string containing an empty string, "auto", or a valid `HTTP::GetWebUserAgent()` string (Default is "auto").

WebBrowser::ExtractForms($baseurl, $data)
-----------------------------------------

Access:  public

Parameters:

* $baseurl - A string containing a base URL.
* $data - A string containing HTML.

Returns:  An array containing the forms and fields in the HTML.

This function extracts all the forms and fields from HTML via Simple HTML DOM (must be loaded) into an array.  The array is supposed to be passed around to FindFormFields(), GetFormValue(), SetFormValue(), and GenerateFormRequest() to manipulate the fields in preparation for a future request to the server.

Also correctly handles HTML 5 form fields that may not reside inside the usual 'form' tag.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	$web = new WebBrowser();
	$result = $web->Process($url);

	// Check for connectivity and response errors.
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else
	{
		$forms = $web->ExtractForms($result["url"], $result["body"]);

		if (count($forms) != 1)  echo "Error retrieving URL.  Server returned " . count($forms) . " forms.  Was expecting one form.\n";

		$form = $forms[0];

		$form->SetFormValue("username", "someuser");
		$form->SetFormValue("password", "passwordgoeshere");

		$result = $form->GenerateFormRequest();
		$result = $web->Process($result["url"], $result["options"]);

		var_dump($result);
	}
?>
```

WebBrowser::InteractiveFormFill($forms, $showselected = false)
--------------------------------------------------------------

Access:  public static

Parameters:

* $forms - An array of WebBrowserForm instances or a single WebBrowserForm instance.
* $showselected - A boolean that determines whether or not this function will output the selected form and its key-value pairs to the console (Default is false).

Returns:  The output from WebBrowserForm::GenerateFormRequest() on success, a boolean of false on failure.

This static function enters a command-line shell-like interactive form filling mode.  This can be useful for getting an initial OAuth token without having to copy and paste long URLs from/to a server.

For example usage, see:

https://github.com/cubiclesoft/ultimate-web-scraper/blob/master/tests/test_interactive.php

WebBrowser::GetCookies()
------------------------

Access:  public

Parameters:  None.

Returns:  An array containing various HTTP cookies.

This function returns the internal cookie array for easier access to HTTP cookie information.  Efficiently managing HTTP cookies can be difficult, so key-value pairs may or may not be what you might expect to see.

WebBrowser::SetCookie($cookie)
------------------------------

Access:  public

Parameters:

* $cookie - An array consisting of at least "domain", "path", "name", and "value" key-value pairs.

Returns:  A standard array of information.

This function sets a new HTTP cookie in the internal cookie array.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	$web = new WebBrowser();

	$cookie = array(
		"domain" => ".somesite.com",
		"path" => "/",
		"name" => "session",
		"value" => "Z29iYmxlZHlnb29r"
	);

	var_dump($web->SetCookie($cookie));

	$result = $web->Process($url);
?>
```

WebBrowser::DeleteSessionCookies()
----------------------------------

Parameters:  None.

Returns:  Nothing.

This function scans the WebBrowser instance cookies for session cookies (cookies with no expiration date) and deletes them.  This is equivalent to closing a web browser and opening it again.  Cookies with expiration dates are left alone.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/once_per_session/";

	$web = new WebBrowser();
	$result = $web->Process($url);

	$web->DeleteSessionCookies();

	$result = $web->Process($url);
?>
```

WebBrowser::DeleteCookies($domainpattern, $pathpattern, $namepattern)
---------------------------------------------------------------------

Access:  public

Parameters:

* $domainpattern - A string containing part or all of a domain to match against.
* $pathpattern - A string containing part or all of a path to match against.
* $namepattern - A string containing part or all of a name to match against.

Returns:  Nothing.

This function scans the WebBrowser instance cookies for any cookies that matches the specified patterns and deletes them.  Using "" (an empty string) for any pattern will match all of that type.  If you are deleting all cookies, use `WebBrowser::SetState(array("cookies" => array()))` instead for faster performance.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	$web = new WebBrowser();
	$result = $web->Process($url);

	$web->DeleteCookies("www.somesite.com", "", "tracker");

	$result = $web->Process($url);
?>
```

WebBrowser::ProcessAsync__Handler($mode, &$data, $key, &$info)
--------------------------------------------------------------

Access:  _internal_ public

Parameters:

* $mode - A string representing the mode/state to process.
* $data - Mixed content the depends entirely on the $mode.
* $key - A string representing the key associated with an object.
* $info - The information associated with the key.

Returns:  Nothing.

This internal static callback function is the internal handler for MultiAsyncHandler for processing WebBrowser class instances.

WebBrowser::ExtractFieldFromDOM(&$fields, $row)
-----------------------------------------------

Access:  private

Parameters:

* $fields - An array, passed by reference, that contains the fields already extracted by previous calls to this function.
* $row - A Simple HTML DOM object containing a form input element.

Returns:  Nothing.

This internal function processes the 'form' element defined by $row and converts it into an array object.  Used by WebBrowser::ExtractForms() for each form element.

WebBrowser::GetExpiresTimestamp($ts)
------------------------------------

Access:  private

Parameters:

* $ts - A string containing a timestamp in YYYY-MM-DD HH:MM:SS format.

Returns:  An integer containing a UNIX timestamp.

This internal function converts internal cookie timestamps into UNIX timestamps for calculating whether or not the cookie has expired.


WebBrowserForm Class
====================

This class is specifically designed to manage HTML forms for later submission with the WebBrowser class.  $form->info and $form->fields are public arrays that can be read and manipulated but use of the convenient helper functions is highly recommended.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	// Enable automatic extraction of forms from downloaded content.
	$web = new WebBrowser(array("extractforms" => true));

	$result = $web->Process($url);

	// Check for connectivity and response errors.
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else if (count($result["forms"]) != 1)  echo "Error retrieving URL.  Server returned " . count($result["forms"]) . " forms.  Was expecting one form.\n";
	else
	{
		$form = $result["forms"][0];

		// Fill in normal fields (text, textarea, select).
		$form->SetFormValue("username", "someuser");
		$form->SetFormValue("password", "passwordgoeshere");

		// Check a checkbox or radio button.  Note that multi-select options are converted to checkboxes.
		$form->SetFormValue("rememberme", "yes", true);

		// Select the default submit button and generate output suitable for WebBrowser::Process().
		$result = $form->GenerateFormRequest();

		// Run the next request.
		$result = $web->Process($result["url"], $result["options"]);

		var_dump($result);
	}
?>
```

WebBrowserForm::FindFormFields($name = false, $value = false, $type = false)
----------------------------------------------------------------------------

Access:  public

Parameters:

* $name - A boolean of false or a string containing the 'name' of the element(s) to find (Default is false).
* $value - A boolean of false or a string containing the 'value' of the element(s) to find (Default is false).
* $type - A boolean of false or a string containing the 'type' of the element(s) to find (Default is false).

Returns:  An array of fields that match the input parameters.

This function finds one or more form elements in an extracted form.  Specifically, if there are multiple 'input.submit' buttons on the page that have strange names but the ordering is guaranteed to always be the same, FindFormFields can find all fields of the type 'input.submit' and return it.  After that, the name of the submit button is easily identified.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.  (ASP.NET is the culprit here.)
	$url = "http://www.somesite.com/something/awful.aspx";

	// Enable automatic extraction of forms from downloaded content.
	$web = new WebBrowser(array("extractforms" => true));

	$result = $web->Process($url);

	// Check for connectivity and response errors.
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else if (count($result["forms"]) != 1)  echo "Error retrieving URL.  Server returned " . count($result["forms"]) . " forms.  Was expecting one form.\n";
	else
	{
		$form = $result["forms"][0];

		$form->SetFormValue("username", "someuser");
		$form->SetFormValue("password", "passwordgoeshere");

		// Extract all of the submit buttons.
		$submitbuttons = $form->FindFormFields(false, false, "input.submit");

		// Use the first submit button for submitting the form.
		$result = $form->GenerateFormRequest($submitbuttons[0]["name"]);

		$result = $web->Process($result["url"], $result["options"]);

		var_dump($result);
	}
?>
```

WebBrowserForm::GetHintMap()
----------------------------

Access:  public

Parameters:  None.

Returns:  An array that maps surrounding element hints to their field names.

This function generates a human-readable hint map for the form.  This function can be useful in extremely rare cases but will result in very fragile code since human-readable strings on a page are generally subject to change more frequently than form field names.

WebBrowserForm::GetVisibleFields($submit)
-----------------------------------------

Access:  public

Parameters:

* $submit - A boolean that indicates whether or not to include submission button types.

Returns:  An array of visible form fields.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	// Enable automatic extraction of forms from downloaded content.
	$web = new WebBrowser(array("extractforms" => true));

	$result = $web->Process($url);

	// Check for connectivity and response errors.
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else if (count($result["forms"]) != 1)  echo "Error retrieving URL.  Server returned " . count($result["forms"]) . " forms.  Was expecting one form.\n";
	else
	{
		$form = $result["forms"][0];

		// Useful for debugging form field changes.
		var_dump($form->GetVisibleFields(false));
	}
?>
```

WebBrowserForm::GetFormValue($name, $checkval = false, $type = false)
---------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing the 'name' of the element to retrieve.
* $checkval - A boolean of false or a string containing the 'value' to get the 'checked' status of (Default is false).
* $type - A boolean of false or a string containing the 'type' of the element(s) to find (Default is false).

Returns:  A boolean if $checkval is a string, or a string if $checkval is false.

This function retrieves the value of a specific form item's value or 'checked' status when the 'name' is known.

Example usage:

```php
<?php
	require_once "support/web_browser.php";

	// The URL to retrieve.
	$url = "http://www.somesite.com/something/";

	// Enable automatic extraction of forms from downloaded content.
	$web = new WebBrowser(array("extractforms" => true));

	$result = $web->Process($url);

	// Check for connectivity and response errors.
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else if (count($result["forms"]) != 1)  echo "Error retrieving URL.  Server returned " . count($result["forms"]) . " forms.  Was expecting one form.\n";
	else
	{
		$form = $result["forms"][0];

		echo "User ID:  " . $form->GetFormValue("user_id") . "\n";
	}
?>
```

WebBrowserForm::SetFormValue($name, $value, $checked = false, $type = false, $create = false)
---------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing the 'name' of the element to retrieve.
* $value - A string containing the 'value' of the element to set.
* $checked - A boolean that checks or unchecks radio buttons and checkbox elements (Default is false).
* $type - A boolean of false or a string containing the 'type' of the element(s) to find (Default is false).
* $create - A boolean that specifies whether or not to create the form element if it doesn't already exist (Default is false).

Returns:  A boolean of true on success, false otherwise.

This function sets the value or alters the 'checked' status of a form element depending on the element's type.  When $create is set to true, if the form element doesn't exist, it will be created with the type specified by $type or 'input.text' if the $type is false.

See the start of this section for example usage.

WebBrowserForm::GenerateFormRequest($submitname = false, $submitvalue = false)
------------------------------------------------------------------------------

Access:  public

Parameters:

* $submitname - A boolean of false or a string containing the 'name' of the 'input.submit' or 'button.submit' element to send (Default is false).
* $submitvalue - A boolean of false or a string containing the 'value' of the 'input.submit' or 'button.submit' element to send (Default is false).

Returns:  A boolean of false if $form is not an extracted form, otherwise an array containing a WebBrowser class compliant URL and options set.

This function processes the form and returns WebBrowser compatible information.  If the form is a GET request, all the variables are condensed into the URL.  If the form is a POST request, the variables are broken out to be sent as part of the body later.  File uploads are also correctly handled.

The $submitname and $submitvalue options help select the correct 'input.submit' or 'button.submit' item in the form to include.  If both options are false, then all submit buttons are included.  It is highly recommended to use a specific submission button to avoid confusing the target web server.

See the start of this section for example usage.
