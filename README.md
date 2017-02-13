Ultimate Web Scraper Toolkit
============================

A PHP library of tools designed to handle all of your web scraping needs under a MIT or LGPL license.  This toolkit easily makes RFC-compliant web requests that are indistinguishable from a real web browser, a web browser-like state engine for handling cookies and redirects, and a full cURL emulation layer for web hosts without the PHP cURL extension installed.  The powerful tag filtering library TagFilter is included to easily extract the desired content from each retrieved document.

It also comes with classes for creating custom web servers and WebSocket servers.  That custom API you want the average person to install on their home computer or deploy to devices in the enterprise just became easier to deploy.

Features
--------

* Carefully follows the IETF RFC Standards surrounding the HTTP protocol.
* Supports file transfers, SSL/TLS, and HTTP/HTTPS/CONNECT proxies.
* Easy to emulate various web browser headers.
* A web browser-like state engine that emulates redirection (e.g. 301) and automatic cookie handling for managing multiple requests.
* HTML form extraction and manipulation support.  No need to fake forms!
* Extensive callback support.
* Asynchronous socket support.
* WebSocket support.
* A full cURL emulation layer for drop-in use on web hosts that are missing cURL.
* An impressive CSS3 selector tokenizer (TagFilter::ParseSelector()) that carefully follows the W3C Specification and passes the official W3C CSS3 static test suite.
* Includes a fast and powerful tag filtering library (TagFilter) for correctly parsing really difficult HTML content (e.g. Microsoft Word HTML) and can easily extract desired content from HTML and XHTML using CSS3 compatible selectors.
* TagFilter::HTMLPurify() produces XSS defense results on par with HTML Purifier.
* Includes the legacy Simple HTML DOM library to parse and extract desired content from HTML.  NOTE:  Simple HTML DOM is only included for legacy reasons.  TagFilter is much faster and more accurate as well as more powerful and flexible.
* An unncessarily feature-laden web server class with optional SSL/TLS support.  Run a web server written in pure PHP.  Why?  Because you can, that's why.
* There is a WebSocket server class too.
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

More Information
----------------

Documentation, examples, and official downloads of this project sit on the Barebones CMS website:

http://barebonescms.com/documentation/ultimate_web_scraper_toolkit/
