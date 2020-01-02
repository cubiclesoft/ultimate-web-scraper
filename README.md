Ultimate Web Scraper Toolkit
============================

A PHP library of tools designed to handle all of your web scraping needs under a MIT or LGPL license.  This toolkit easily makes RFC-compliant web requests that are indistinguishable from a real web browser, has a web browser-like state engine for handling cookies and redirects, and a full cURL emulation layer for web hosts without the PHP cURL extension installed.  The powerful tag filtering library TagFilter is included to easily extract the desired content from each retrieved document or used to process HTML documents that are offline.

This tookit also comes with classes for creating custom web servers and WebSocket servers.  That custom API you want the average person to install on their home computer or deploy to devices in the enterprise just became easier to deploy.

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/)

Features
--------

* Carefully follows the IETF RFC Standards surrounding the HTTP protocol.
* Supports file transfers, SSL/TLS, and HTTP/HTTPS/CONNECT proxies.
* Easy to emulate various web browser headers.
* A web browser-like state engine that emulates redirection (e.g. 301) and automatic cookie handling for managing multiple requests.
* HTML form extraction and manipulation support.  No need to fake forms!
* Extensive callback support.
* Asynchronous/Non-blocking socket support.  For when you need to scrape lots of content simultaneously.
* WebSocket support.
* A full cURL emulation layer for drop-in use on web hosts that are missing cURL.
* An impressive CSS3 selector tokenizer (TagFilter::ParseSelector()) that carefully follows the W3C Specification and passes the official W3C CSS3 static test suite.
* Includes a fast and powerful tag filtering library (TagFilter) for correctly parsing really difficult HTML content (e.g. Microsoft Word HTML) and can easily extract desired content from HTML and XHTML using CSS3 compatible selectors.
* TagFilter::HTMLPurify() produces XSS defense results on par with HTML Purifier.
* Includes the legacy Simple HTML DOM library to parse and extract desired content from HTML.  NOTE:  Simple HTML DOM is only included for legacy reasons.  TagFilter is much faster and more accurate as well as more powerful and flexible.
* An unncessarily [feature-laden web server class](https://github.com/cubiclesoft/ultimate-web-scraper/blob/master/docs/web_server.md) with optional SSL/TLS support.  Run a web server written in pure PHP.  Why?  Because you can, that's why.
* A decent [WebSocket server class](https://github.com/cubiclesoft/ultimate-web-scraper/blob/master/docs/websocket_server.md) is included too.  For a scalable version of the WebSocket server class, see [Data Relay Center](https://github.com/cubiclesoft/php-drc).
* Can be used to [download entire websites for offline use](#offline-downloading).
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Getting Started
---------------

[![Web Scraping - Techniques and tools of the trade](https://user-images.githubusercontent.com/1432111/42725116-523907e6-8733-11e8-8322-71631f5e198a.png "Watch video")](https://www.youtube.com/watch?v=54tB8t1r0og)

Example object-oriented usage:

```php
<?php
	require_once "support/web_browser.php";
	require_once "support/tag_filter.php";

	// Retrieve the standard HTML parsing array for later use.
	$htmloptions = TagFilter::GetHTMLOptions();

	// Retrieve a URL (emulating Firefox by default).
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

Example direct ID usage:

```php
<?php
	require_once "support/web_browser.php";
	require_once "support/tag_filter.php";

	// Retrieve the standard HTML parsing array for later use.
	$htmloptions = TagFilter::GetHTMLOptions();

	// Retrieve a URL (emulating Firefox by default).
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

	// Find all anchor tags.
	echo "All the URLs:\n";
	$result2 = $html->Find("a[href]");
	if (!$result2["success"])
	{
		echo "Error parsing/finding URLs.  " . $result2["error"] . "\n";
		exit();
	}

	foreach ($result2["ids"] as $id)
	{
		// Faster direct access.
		echo "\t" . $html->nodes[$id]["attrs"]["href"] . "\n";
		echo "\t" . HTTP::ConvertRelativeToAbsoluteURL($baseurl, $html->nodes[$id]["attrs"]["href"]) . "\n";
	}

	// Find all table rows that have 'th' tags.
	// The 'tr' tag IDs are returned.
	$result2 = $html->Filter($hmtl->Find("tr"), "th");
	if (!$result2["success"])
	{
		echo "Error parsing/finding table rows.  " . $result2["error"] . "\n";
		exit();
	}

	foreach ($result2["ids"] as $id)
	{
		echo "\t" . $html->GetOuterHTML($id) . "\n\n";
	}
?>
```

Example HTML form extraction:

```php
<?php
	require_once "support/web_browser.php";
	require_once "support/tag_filter.php";

	// Retrieve the standard HTML parsing array for later use.
	$htmloptions = TagFilter::GetHTMLOptions();

	$url = "https://www.somewebsite.com/login/";

	// Turn on the automatic forms extraction option.  Note that Javascript is not executed.
	$web = new WebBrowser(array("extractforms" => true));
	$result = $web->Process($url);

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

	if (count($result["forms"]) != 1)
	{
		echo "Was expecting one form.  Received:  " . count($result["forms"]) . "\n";
		exit();
	}

	// Forms are extracted in the order they appear in the HTML.
	$form = $result["forms"][0];

	// Set some or all of the variables in the form.
	$form->SetFormValue("username", "cooldude123");
	$form->SetFormValue("password", "password123");

	// Submit the form.
	$result2 = $form->GenerateFormRequest();
	$result = $web->Process($result2["url"], $result2["options"]);

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

	// Use TagFilter to parse the content.
	$html = TagFilter::Explode($result["body"], $htmloptions);

	// Do something with the response here...
?>
```

Example POST request:

```php
<?php
	require_once "support/web_browser.php";

	$url = "https://api.somesite.com/profile";

	// Send a POST request to a URL.
	$web = new WebBrowser();
	$options = array(
		"postvars" => array(
			"id" => 12345,
			"firstname" => "John",
			"lastname" => "Smith"
		)
	);

	$result = $web->Process($url, $options);

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

	// Do something with the response.
?>
```

Example large file/content retrieval:

```php
<?php
	require_once "support/web_browser.php";

	function DownloadFileCallback($response, $data, $opts)
	{
		if ($response["code"] == 200)
		{
			$size = ftell($opts);
			fwrite($opts, $data);

			if ($size % 1000000 > ($size + strlen($data)) % 1000000)  echo ".";
		}

		return true;
	}

	// Download a large file.
	$url = "http://downloads.somesite.com/large_file.zip";
	$fp = fopen("the_file.zip", "wb");
	$web = new WebBrowser();
	$options = array(
		"read_body_callback" => "DownloadFileCallback",
		"read_body_callback_opts" => $fp
	);
	echo "Downloading '" . $url . "'...";
	$result = $web->Process($url, $options);
	echo "\n";
	fclose($fp);

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

	// Do something with the response.
?>
```

Example custom SSL options usage:

```php
<?php
	require_once "support/http.php";
	require_once "support/web_browser.php";

	// Generate default safe SSL/TLS options using the "modern" ciphers.
	// See:  https://mozilla.github.io/server-side-tls/ssl-config-generator/
	$sslopts = HTTP::GetSafeSSLOpts(true, "modern");

	// Adjust the options as necessary.
	// For a complete list of options, see:  http://php.net/manual/en/context.ssl.php
	$sslopts["capture_peer_cert"] = true;

	// Demonstrates capturing the SSL certificate.
	// Returning false terminates the connection without sending any data.
	function CertCheckCallback($type, $cert, $opts)
	{
		var_dump($type);
		var_dump($cert);

		return true;
	}

	// Send a POST request to a URL.
	$url = "https://api.somesite.com/profile";
	$web = new WebBrowser();
	$options = array(
		"sslopts" => $sslopts,
		"peer_cert_callback" => "CertCheckCallback",
		"peer_cert_callback_opts" => false,
		"postvars" => array(
			"id" => 12345,
			"firstname" => "John",
			"lastname" => "Smith"
		)
	);
	$result = $web->Process($url, $options);

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

	// Do something with the response.
?>
```

Example debug mode usage:

```php
<?php
	require_once "support/web_browser.php";

	// Send a POST request to a URL with debugging enabled.
	// Enabling debug mode for a request uses more RAM since it collects all data sent and received over the wire.
	$url = "https://api.somesite.com/profile";
	$web = new WebBrowser();
	$options = array(
		"debug" => true,
		"postvars" => array(
			"id" => 12345,
			"firstname" => "John",
			"lastname" => "Smith"
		)
	);
	$result = $web->Process($url, $options);

	// Check for connectivity errors.
	if (!$result["success"])
	{
		echo "Error retrieving URL.  " . $result["error"] . "\n";
		exit();
	}

	echo "------- RAW SEND START -------\n";
	echo $result["rawsend"];
	echo "------- RAW SEND END -------\n\n";

	echo "------- RAW RECEIVE START -------\n";
	echo $result["rawrecv"];
	echo "------- RAW RECEIVE END -------\n\n";
?>
```

Uploading Files
---------------

File uploads are handled several different ways so that very large files can be processed.  The "files" option is an array of arrays that represents one or more files to upload.  File uploads will automatically switch a POST request's `Content-Type` from "application/x-www-form-urlencoded" to "multipart/form-data".

```php
<?php
	require_once "support/web_browser.php";

	// Upload two files.
	$url = "http://api.somesite.com/photos";
	$web = new WebBrowser();
	$options = array(
		"postvars" => array(
			"uid" => 12345
		),
		"files" => array(
			array(
				"name" => "file1",
				"filename" => "mycat.jpg",
				"type" => "image/jpeg",
				"data" => file_get_contents("/path/to/mycat.jpg")
			),
			array(
				"name" => "file2",
				"filename" => "mycat-hires.jpg",
				"type" => "image/jpeg",
				"datafile" => "/path/to/mycat-hires.jpg"
			)
		)
	);
	$result = $web->Process($url, $options);

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

	// Do something with the response.
?>
```

Each file in the "files" array must have the following options:

* name - The server-side key to use.
* filename - The filename to send to the server.  Well-written server-side software will generally ignore this other than to look at the file extension (e.g. ".jpg", ".png", ".pdf").
* type - The MIME type to send to the server.  Run a Google search for "mime type for xyz" where "xyz" is the file extension of the file you are sending.

One of the following options must also be provided for each file:

* data - A string containing the data to send.  This should only be used for small files.
* datafile - A string containing the path and filename to the data to send OR a seekable file resource handle.  This is the preferred method for uploading large files.  Files exceeding 2GB may have issues under 32-bit PHP.

File uploads within extracted forms are handled similarly to the above.  When calling `$form->SetFormValue()`, pass in an array containing the file information with "filename", "type", and "data" or "datafile".  The "name" key-value will automatically be filled in when calling `$form->GenerateFormRequest()`.

Sending Non-Standard Requests
-----------------------------

The vast majority of requests to servers are GET, POST application/x-www-form-urlencoded, and POST multipart/form-data.  However, there may be times that other request types need to be sent to a server.  For example, a lot of APIs being written these days want JSON content instead of a standard POST request to be able to handle richer incoming data.

Example:

```php
<?php
	require_once "support/web_browser.php";

	// Send a POST request with a custom body.
	$url = "http://api.somesite.com/profile";
	$web = new WebBrowser();
	$options = array(
		"method" => "POST",
		"headers" => array(
			"Content-Type" => "application/json"
		),
		"body" => json_encode(array(
			"id" => 12345,
			"firstname" => "John",
			"lastname" => "Smith"
		), JSON_UNESCAPED_SLASHES)
	);
	$result = $web->Process($url, $options);

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

	// Do something with the response.
?>
```

Working with such APIs is best done by building a SDK.  Here are several SDKs and their relevant API documentation that might be useful:

* [Twilio SDK](https://github.com/cubiclesoft/php-twilio-sdk/) and [API documentation](https://www.twilio.com/docs)
* [DigitalOcean SDK](https://github.com/cubiclesoft/digitalocean/) and [API documentation](https://developers.digitalocean.com/documentation/)
* [OpenDrive SDK](https://github.com/cubiclesoft/cloud-backup/blob/master/support/sdk_opendrive.php) and [API documentation](https://www.opendrive.com/api)

The above SDKs utilize this toolkit.

Debugging SSL/TLS
-----------------

Connecting to a SSL/TLS enabled server is fraught with difficulties.  SSL/TLS connections are much more fragile through no fault of the toolkit but rather SSL/TLS doing its thing.  Here are the known reasons a SSL/TLS connection will fail to establish:

* Network failures.  The server gives up because SSL/TLS is expensive on both local and remote system resources (mostly CPU), there is a temporary network condition (just retry the request), or the request is being actively blocked by a firewall (e.g. port blocking for a range of abusive IPs).
* The server (or client) SSL/TLS certificate is incomplete or does not validate against a known root CA certificate list.
* The server (or client) SSL/TLS certificate has expired.  Not much can be done here except completely disable SSL validation.
* A bug in Ultimate Web Scraper Toolkit exposed due to underlying TLS bugs in PHP.  This is really rare though.

PHP does not expose much of the underlying SSL/TLS layer to applications when establishing connections, which makes it incredibly difficult to diagnose certain issues with SSL/TLS.  To diagnose network related problems, use the 'openssl s_client' command line tool from the same host the problematic script is running on.  Setting the "cafile", "auto_cn_match", and "auto_sni" SSL options may help too.

If all else fails and secure, encrypted communication with the server are not required, disable the "verify_peer" and "verify_peer_name" SSL options and enable the "allow_self_signed" SSL option.  Note that making these changes results in a connection that is no more secure that plaintext HTTP.  Don't send passwords or other information that should be kept secure.  This solution should only ever be used as a last resort.  Always try to get the toolkit working with verification first.

Handling Pagination
-------------------

There is a common pattern in the scraping world:  Pagination.  This is most often seen when submitting a form and the request is passed off to a basic search engine that usually returns anywhere from 10 to 50 results.

Unfortunately, you need all 8,946 results for the database you are constructing.  There are two ways to handle the scenario:  Fake it or follow the links/buttons.

"Faking it" eliminates the need to handle pagination in the first place.  What is meant by this?  Well, a lot of GET/POST requests in pagination scenarios pass along the "page size" to the server.  Let's say 50 results are being returned but the number '50' in a size attribute is also being sent to the server either on the first page or subsequent pages in the URL.  Well, what happens if the value '10000' is sent for the page size instead of '50'?  About 85% of the time, the server-side web facing software assumes it will only be passed the page size values provided in some client-side select box.  Therefore, the server-side just casts the submitted value to an integer and passes it along to the database AND does all of its pagination calculations from that submitted value.  The result is that all of the desired server-side data can be retrieved with just one request.  Frequently, if the page size is not in the first page of search results, page 2 of those search results will generally reveal what parameter is used for page size.  The ability to fake it on such a broad scale just goes to show that writing a functional search engine is a difficult task for a lot of developers.

But what if faking it doesn't work?  There's plenty of server-side software that can't handle processing/returning lots of data and will instead return an error - for example, with experimenting, maybe a server fails to return more than 3,000 rows at a time but that's still significantly more than 50 rows at a time.  Or the developer wrote their code to assume that their data might get scraped and forces the upper limit on the page size anyway.  Doing so just hurts them more than anything else since the scraping script will end up using more of their system resources to retrieve the same amount of data.  Regardless, if the data can't be retrieved all at once, pagination at whatever limit is imposed by the server is the only option.  If the requests are just URL-based, then pagination can be done by manipulating the URL.  If the requests are POST-based, then extracting forms from the page may be required.  It depends entirely on how the search engine was constructed.

Example:

```php
<?php
	require_once "support/web_browser.php";
	require_once "support/tag_filter.php";

	// Retrieve the standard HTML parsing array for later use.
	$htmloptions = TagFilter::GetHTMLOptions();

	$url = "http://www.somesite.com/something/?s=3000";
	$web = new WebBrowser(array("extractforms" => true));

	do
	{
		// Retrieve a URL.
		$retries = 3;
		do
		{
			$result = $web->Process($url);
			$retries--;
			if (!$result["success"])  sleep(1);
		} while (!$result["success"] && $retries > 0);

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

		$baseurl = $result["url"];

		// Use TagFilter to parse the content.
		$html = TagFilter::Explode($result["body"], $htmloptions);

		// Retrieve a pointer object to the root node.
		$root = $html->Get();

		$found = false;
		// Attempt to extract information.
		// Set $found to true if there is at least one row of data.

		if ($found)
		{
			$row = $root->Find("div.pagination a[href]")->Filter("/~contains:Next")->current();
			if ($row === false)  break;

			$url = HTTP::ConvertRelativeToAbsoluteURL($baseurl, $row->href);
		}
	} while ($found);
?>
```

One other useful tip is to attempt to use wildcard SQL characters or text patterns to extract more data than the website operator likely intended.  If a search box requires some field to be filled in for a search to be accepted, try a single '%' to see if the server is accepting wildcard LIKE queries.  If not, then maybe walking through the set of possible alphanumeric values will work (e.g. "a", "b", "c", "d") and then being careful to exclude duplicated data (e.g. "XYZ, Inc." would show up in six different search result sets).

Another useful tip is to be aware of URLs for detail pages.  For example, when viewing details about an item from a search and the item has "id=2018000001" in the URL for that page and then another item has "id=2017003449", then there may be a predictable pattern of year + sequence within that year as part of the ID for any given item.  Searching may not even be necessary as it may be possible to generate the URL dynamically (e.g. "id=2018000001", "id=2018000002", "id=2018000003") if the goal is to copy all records.

Offline Downloading
-------------------

Included with Ultimate Web Scraper Toolkit is an [example script](offline_download_example.php) to download a website starting at a specified URL.  The script demonstrates bulk concurrent downloading and processing of HTML, CSS, images, Javascript, and other files almost like a web browser would do.

Example usage:

```
php offline_download_example.php offline-test https://barebonescms.com/ 3
```

That will download content up to three links deep to the local computer system starting at the root URL of barebonescms.com.  All valid URLs to barebonescms.com are transformed into local disk references.  CDNs for images and Javascript are transformed into subdirectories.  The script also attempts to maintain the relative URL structure of the original website wherever possible.

The script is only an example of what a website downloader might look like since it lacks features that a better tool might have (e.g. the ability to exclude certain URL paths).  It's a great starting point though for building something more complete and/or a custom solution for a specific purpose.

There are some limitations.  For example, any files loaded via Javascript won't necessarily be retrieved.  See the Limitations section below for additional information.

Limitations
-----------

The only real limitation with Ultimate Web Scraper Toolkit is its inability to process Javascript.  A simple regex here and there to extract data hardcoded via Javascript usually works well enough.

For the 0.5% of websites where there is useful content to scrape but the entire page content is generated using Javascript or is protected by Javascript in unusual ways, a real web browser is required.  Fortunately, there is [PhantomJS](http://phantomjs.org/) (headless Webkit), which can be scripted (i.e. automated) to handle the aforementioned Javascript-heavy sites.  However, PhantomJS is rather resource intensive and slooooow.  After all, PhantomJS emulates a real web browser which includes the full startup sequence and then it proceeds to download the entire page's content.  That, in turn, can take hundreds of requests to complete and can easily include downloading things such as ads.

It is very rare though to run into a website like that.  Ultimate Web Scraper Toolkit can handle most anything else.

More Information
----------------

Full documentation and more examples can be found in the '[docs](https://github.com/cubiclesoft/ultimate-web-scraper/tree/master/docs)' directory of this repository.
