DOHWebBrowser Class:  'support/doh_web_browser.php'
===================================================

The DNS Over HTTPS (DOH) web browser class integrates with DOH servers to attempt to provide more secure responses to DNS queries than standard DNS resolvers.  This drop-in replacement class for WebBrowser overrides some aspects of the WebBrowser class to provide transparent DOH support.

By default, the class uses the [Cloudflare DOH resolver](https://developers.cloudflare.com/1.1.1.1/dns-over-https/request-structure) to rewrite each web request.

Note that this class is not asynchronous/non-blocking except if a host IP has already been resolved and stored in the cache in RAM.

DOHWebBrowser::SetDOHAccessInfo($dohapi, $dohhost = false, $dohtypes = array("A", "AAAA"))
------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $dohapi - A string containing the DNS Over HTTPS API to use.  Must be compatible with the Cloudflare DNS JSON API.
* $dohhost- A string containing the DNS Over HTTPS host to use (Default is false).  Use if `$dohapi` contains an IP address.
* $dohtypes - An array containing the DNS types to query in order of preference (Default is array("A", "AAAA")).

Returns:  Nothing.

This function sets the DOH access information to use when resolving DNS queries.

DOHWebBrowser::ClearDOHCache()
------------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function clears/flushes the shared internal resolver cache of all entries.

DOHWebBrowser::GetDOHCache()
----------------------------

Access:  public

Parameters:  None.

Returns:  An array containing the shared internal resolver cache entries.

This function returns the internal resolver cache entries.  These cached entries are shared across all DOHWebBrowser class instances.

DOHWebBrowser::InternalDNSOverHTTPSHandler(&$state)
---------------------------------------------------

Access:  public

Parameters:

* $state - An array containing state information.

Returns:  A boolean that determines whether or not the request should continue.

This internal function is the workhorse of the class.  It connects to the DOH resolver as needed, runs queries, alters the original request using the resolver response, and then lets the WebBrowser class continue on using the modified information.
