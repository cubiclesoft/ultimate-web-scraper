TagFilter Classes: 'support/tag_filter.php'
===========================================

Retrieving a webpage is just half of the battle.  Parsing it is the other half.

Included with the Ultimate Web Scraper Toolkit is TagFilter, which can be used to parse the page and then use jQuery/CSS3-style selectors to extract the content that you are actually interested in (not all selectors are supported though).  The selector query-based approach is significantly better than the traditional regular expression approach that web scraping is associated with.  It helps to know CSS3 selectors or jQuery prior to using TagFilter.

TagFilter includes the following features:

* Tag cleanup of and/or data extraction from the gnarliest, ugliest HTML this side of the galaxy.  Microsoft Word HTML and ASP.NET HTML are no problem.  See:  TagFilter::Run()
* Powerful HTML filtering to remove cross-site scripting (XSS) attempts from user-submitted HTML content.  See:  TagFilter::HTMLPurify()
* Extract content into a DOM-like environment with CSS3-style selector support.  See TagFilter::Explode() and the TagFilterNodes class.
* Modify content in the aforementioned DOM-like environment and then turn part or all of it back into HTML.  See the TagFilterNodes class.
* Tokenize the gnarliest, ugliest CSS3 selectors.  See:  TagFilter::ParseSelector() and TagFilterNodes::MakeValidSelector()
* Process multi-gigabyte HTML and XHTML files with blazing fast performance and not use more than a few KB of RAM.  See the TagFilterStream class.
* Easily invent your own tags to create a robust, flexible templating language for a wide variety of uses.  It doesn't have to be HTML or it could be HTML/XHTML with additional tags!

There's really no limit to what TagFilter can be used for.  I've even made a relatively user-friendly template language with TagFilter.

Example direct usage:

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
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else
	{
		// Use TagFilter to parse the content.
		$html = TagFilter::Explode($result["body"], $htmloptions);

		// Find all anchor tags.
		echo "All the URLs:\n";
		$result2 = $html->Find("a[href]");
		if (!$result2["success"])  echo "Error parsing/finding URLs.  " . $result2["error"] . "\n";
		else
		{
			foreach ($result2["ids"] as $id)
			{
				// Fast direct access.
				echo "\t" . $html->nodes[$id]["attrs"]["href"] . "\n";
			}
		}

		// Find all table rows that have 'th' tags.
		// The 'tr' tag IDs are returned.
		$result2 = $html->Filter($hmtl->Find("tr"), "th");
		if (!$result2["success"])  echo "Error parsing/finding table rows.  " . $result2["error"] . "\n";
		else
		{
			foreach ($result2["ids"] as $id)
			{
				echo "\t" . $html->GetOuterHTML($id) . "\n\n";
			}
		}
	}
?>
```

Example object-oriented usage:

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
	if (!$result["success"])  echo "Error retrieving URL.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
	else
	{
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
		}

		// Find all table rows that have 'th' tags.
		$rows = $root->Find("tr")->Filter("th");
		foreach ($rows as $row)
		{
			echo "\t" . $row->GetOuterHTML() . "\n\n";
		}
	}
?>
```

TagFilter::GetHTMLOptions()
---------------------------

Access:  public static

Parameters:  None.

Returns:  An array of information representing HTML.

This function returns a set of baseline rules for correctly processing HTML.  This array can be modified with appropriate callbacks and additional tag information before it is passed into another TagFilter function or a TagFilterStream class instance.

TagFilter::Run($content, $options = array())
--------------------------------------------

Access:  public static

Parameters:

* $content - A string containing the content to parse.
* $options - An array containing the ruleset to use (Default is array()).

Returns:  A string containing the results of parsing the content against the ruleset.

This static function parses a complete document in one pass using the input $options array.  The default behavior is to attempt to clean up the content without awareness of the type of information being processed.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<a href=\"http://barebonescms.com/\">A link< / a >< IMG SrC=\"cool.jpg\" >";
	$htmloptions = TagFilter::GetHTMLOptions();

	echo TagFilter::Run($html, $htmloptions) . "\n";
?>
```

TagFilter::CleanupResults($content)
-----------------------------------

Access:  public/_internal_ static

Parameters:

* $content - A string of results to clean up.

Returns:  A string with certain whitespace bits trimmed to look nicer.

This public/internal static function removes very specific and unneeded whitespace characters from the input string and returns the result.  It is called by TagFilter::Run() but users of TagFilterStream might find it useful in some situations.

TagFilter::Explode($content, $options = array())
------------------------------------------------

Access:  public static

Parameters:

* $content - A string containing the content to parse/tokenize.
* $options - An array containing the ruleset to use (Default is array()).

Returns:  A TagFilterNodes class instance with the content split into elements, content, and optionally comments.

This static function parses and tokenizes a complete document in one pass using the input $options array.  The default behavior is to attempt to parse/tokenize the content without awareness of the type of information being processed.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\">";
	$htmloptions = TagFilter::GetHTMLOptions();

	var_dump(TagFilter::Explode($html, $htmloptions));
?>
```

TagFilter::HTMLPurify($content, $htmloptions, $purifyopts)
----------------------------------------------------------

Access:  public static

Parameters:

* $content - A string containing the content to cleanse of XSS.
* $options - An array containing the HTML ruleset to use.
* $purifyopts - An array of purification options.

Returns:  A string cleansed of XSS attempts.

This static function takes in content to be cleansed, a standard HTML or XHTML ruleset, and a purification options array and uses that information to clean up the content of XSS attempts using the defined whitelist.  This function also does normal tag cleanup in the process.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p class=\"allowedclass notallowed\"><a href=\"http://barebonescms.com/\" class=\"allowedclass notallowed\">A link</a><img src=\"cool.jpg\" onclick=\"bad\"><script>do bad stuff here</script>";

	$htmloptions = TagFilter::GetHTMLOptions();

	$purifyopts = array(
		"allowed_tags" => "img,a,p,br,b,strong,i,ul,ol,li",
		"allowed_attrs" => array("img" => "src", "a" => "href,id", "p" => "class"),
		"required_attrs" => array("img" => "src", "a" => "href"),
		"allowed_classes" => array("p" => "allowedclass"),
		"remove_empty" => "b,strong,i,ul,ol,li"
	);

	echo TagFilter::HTMLPurify($html, $htmloptions, $purifyopts) . "\n";
?>
```

TagFilter::NormalizeHTMLPurifyOptions($purifyopts)
--------------------------------------------------

Access:  public static

Parameters:

* $purifyopts - An array of purification options.

Returns:  A normalized array of purification options.

This static function normalizes the input array for use with the TagFilter::HTMLPurifyTagCallback() callback function.  TagFilter::HTMLPurify() automatically calls this function.  This function is useful if you want to use the TagFilterStream class directly.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p class=\"allowedclass notallowed\"><a href=\"http://barebonescms.com/\" class=\"allowedclass notallowed\">A link</a><img src=\"cool.jpg\" onclick=\"bad\"><script>do bad stuff here</script>";

	$purifyopts = array(
		"allowed_tags" => "img,a,p,br,b,strong,i,ul,ol,li",
		"allowed_attrs" => array("img" => "src", "a" => "href,id", "p" => "class"),
		"required_attrs" => array("img" => "src", "a" => "href"),
		"allowed_classes" => array("p" => "allowedclass"),
		"remove_empty" => "b,strong,i,ul,ol,li"
	);

	$htmloptions = TagFilter::GetHTMLOptions();
	$htmloptions["tag_callback"] = "TagFilter::HTMLPurifyTagCallback";
	$htmloptions["htmlpurify"] = TagFilter::NormalizeHTMLPurifyOptions($purifyopts);

	$tfs = new TagFilterStream($htmloptions);
	$tfs->Finalize();
	$result = $tfs->Process($html);

	echo $result . "\n";
?>
```

TagFilter::ParseSelector($query, $splitrules = false)
-----------------------------------------------------

Access:  public static

Parameters:

* $query - A string containing a valid CSS3 selector.
* $splitrules - A boolean indicating whether or not to split the returned tokens on ',' combiners (Default is false).

Returns:  A standard array of information.

This static function attempts to tokenize the input CSS3 selector query string.  Note that even if an error occurs, it is still possible to receive valid, complete rules that came before the last ',' combiner.  This function passes the W3C CSS3 static selector test suite.  It forms the basis of TagFilterNodes::MakeValidSelector() and TagFilterNodes::Find() but can be reused for other purposes.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	var_dump(TagFilter::ParseSelector("a[href]"));

	// p:not(p) in Unicode.
	var_dump(TagFilter::ParseSelector("\\0050\r\n:\\04e\r\n\\6F \\000054(   \\70 \r\n\t)"));
?>
```

TagFilter::ReorderSelectorTokens($tokens, $splitrules, $order, $endnots = true)
-------------------------------------------------------------------------------

Access:  public static

Parameters:

* $tokens - A valid array of tokens from TagFilter::ParseSelector().
* $splitrules - A boolean indicating whether or not to split the returned tokens on ',' combiners.
* $order - An array containing the inverse order of the output types (Default is array("pseudo-element" => array(), "pseudo-class" => array(), "attr" => array(), "class" => array(), "element" => array(), "id" => array())).
* $endnots - A boolean indicating to put :not() portions at the end or not (Default is true).

Returns:  An array of reordered tokens.

This static function can be called multiple times on the same set of tokens to reorder them in various ways.  The $order array indicates the order in which tokens will be array_unshift()'ed onto the result.

TagFilter::GetParentPos($stack, $tagname, $start = 0, $attrs = array())
-----------------------------------------------------------------------

Access:  public static

Parameters:

* $stack - An array containing a standard TagFilterStream stack.
* $tagname - A string containing a tag name to locate in the stack.
* $pos - An integer containing the starting position in the stack (Default is 0).
* $attrs - An array containing key-value pairs (Default is array()).

Returns:  The first matching position in the input stack.

This static function attempts to find a matching parent tag in the input stack.  This convenience function is primarily used when writing callbacks for TagFilterStream for high-performance detection of where in the input that the function is at and take action accordingly.

Example usage:

```php
	...
	function MyTagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
	{
		if ($tagname === "a")
		{
			// Only interested in 'a' tags inside 'p' tags with a classes of 'bestlinks' and 'coolstuff'.
			$pos = TagFilter::GetParentPos($stack, "p", 0, array("class" => "bestlinks coolstuff"));
			if ($pos !== false)
			{
				// Do something with this tag.
			}
		}
	}
	...
```

TagFilter::HTMLSpecialTagContentCallback($stack, $final, &$tag, &$content, &$cx, $cy, &$content2, $options)
-----------------------------------------------------------------------------------------------------------

Access:  _internal_ static

Parameters:  Standard TagFilterStream 'alt_tag_content_rules' parameters.

Returns:  Nothing.

This internal static function is a standard TagFilterStream 'alt_tag_content_rules' callback that specially handles 'script' and 'style' tags.

TagFilter::ExplodeTagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
------------------------------------------------------------------------------------

Access:  _internal_ static

Parameters:  Standard TagFilterStream 'tag_callback' parameters.

Returns:  array("keep_tag" => false, "keep_interior" => false).

This internal static function is a standard TagFilterStream 'tag_callback' callback that processes the input tag as TagFilter::Explode() constructs a TagFilterNodes object via TagFilterStream.

TagFilter::ExplodeContentCallback($stack, $result, &$content, $options)
-----------------------------------------------------------------------

Access:  _internal_ static

Parameters:  Standard TagFilterStream 'content_callback' parameters.

Returns:  Nothing.

This internal static function is a standard TagFilterStream 'content_callback' that processes incoming content as TagFilter::Explode() constructs a TagFilterNodes object via TagFilterStream.

TagFilter::HTMLPurifyTagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
---------------------------------------------------------------------------------------

Access:  _internal_ static

Parameters:  Standard TagFilterStream 'tag_callback' parameters.

Returns:  An array indicating whether or not to keep a tag and/or its interior content.

This internal static function is a standard TagFilterStream 'content_callback' that processes the input tag against the HTML purification ruleset provided.

TagFilter::Internal_NormalizeHTMLPurifyOptions($value)
------------------------------------------------------

Access:  _internal_ static

Parameters:

* $value - A string or array to normalize.

Returns:  A normalized array.

This function takes in a comma-separated string and normalizes it by exploding it and making the values into keys in a new array for higher performance when using TagFilter::HTMLPurifyTagCallback().

TagFilterNodes Class
====================

An instance of the TagFilterNodes class is created as the output of TagFilter::Explode().  The $nodes array in a TagFilterNodes instance is publicly available for direct read access for high-performance applications.  Calling TagFilterNodes::Get() will access a slower object-oriented interface to the underlying array.

TagFilterNodes::MakeValidSelector($query)
-----------------------------------------

Access:  public static

Parameters:

* $query - A string containing a (mostly) valid CSS3 selector.

Returns:  A string containing a valid CSS3 selector with duplicates removed.

This static function cleans up input CSS3-style selectors so that they are suitable for TagFilterNodes::Find() and TagFilterNodes::Filter().  In the process, duplicate selectors are removed.  Note that not all of CSS3 is supported by this function and therefore you shouldn't rely on it to be able to clean up all CSS3 selector inputs.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$sel = "a[href]";
	echo $sel . "\n";
	echo "  Result:  " . TagFilterNodes::MakeValidSelector($sel) . "\n\n";
	$sel = "p.someclass.anotherclass#theid[attr][attr2=val]:first-child:not(a):not(.nope)#theid.someclass,@invalid";
	echo $sel . "\n";
	echo "  Result:  " . TagFilterNodes::MakeValidSelector($sel) . "\n\n";
	$sel = "input:checked";
	echo $sel . "\n";
	echo "  Result:  " . TagFilterNodes::MakeValidSelector($sel) . "\n\n";
	$sel = "div div.someclass  >   p    ~     p";
	echo $sel . "\n";
	echo "  Result:  " . TagFilterNodes::MakeValidSelector($sel) . "\n\n";
	$sel = "span,SPAN,SpAn,sPaN";
	echo $sel . "\n";
	echo "  Result:  " . TagFilterNodes::MakeValidSelector($sel) . "\n\n";
?>
```

TagFilterNodes::Find($query, $id = 0, $cachequery = true, $firstmatch = false)
------------------------------------------------------------------------------

Access:  public

Parameters:

* $query - A string containing a compatible CSS3 selector.
* $id - An integer containing a node ID (Default is 0).
* $cachequery - A boolean that determines whether or not to cache the CSS3 query (Default is true).
* $firstmatch - A boolean that determines whether to return immediately after finding the first match (Default is false).

Returns:  A standard array of information.

This function finds all elements that match the specified CSS3 selector query.  If $firstmatch is true, the function returns immediately after finding the first match.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\">";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	// Direct access.
	var_dump($html->Find("a[href]"));
	var_dump($html->Find("img:not([src])"));

	// Object-oriented access.
	$root = $html->Get();

	$rows = $root->Find("a[href]");
	foreach ($rows as $row)
	{
		echo $row . "\n";
	}
?>
```

TagFilterNodes::Filter($ids, $query, $cachequery = true)
--------------------------------------------------------

Access:  public

Parameters:

* $ids - An array of node IDs or a standard array from another TagFilterNodes::Find() or TagFilterNodes::Filter() call.
* $query - A string containing a compatible CSS3 selector OR a string that starts with "/contains:" or "/~contains:".
* $cachequery - A boolean that determines whether or not to cache the CSS3 query (Default is true).

Returns:  A standard array of information.

This function takes in the results of a previous TagFilterNodes::Find() or TagFilterNodes::Filter() call and returns node IDs that match the input query.  If the input query starts with the string "/contains:" (case-sensitive) or "/~contains:" (case-insensitive), each input node is analyzed for the existence of the rest of the query string in the plain text version of the node and its children.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	// Direct access.
	var_dump($html->Find("p"));
	var_dump($html->Filter($html->Find("p"), "a[href]"));

	// Object-oriented access.
	$root = $html->Get();
	var_dump($root->Find("p")->Filter("a[href]"));
?>
```

TagFilterNodes::Implode($id, $options = array())
------------------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $options - An array containing options (Default is array()).

Returns:  A string.

This function starts at the specified node and walks the node tree, generating the result as defined by the options.  Think of this function as doing the opposite of TagFilter::Explode().  The options array accepts the following key-value pairs:

* include_id - A boolean indicating whether or not to include the $id node in the output (Default is true).
* types - A string consisiting of one or more comma-separated values.  Valid values are "element", "content", and "comment" (Default is "element,content,comment").  Note that the TagFilter::GetHTMLOptions() function has comment inclusion disabled by default.
* output_mode - A string consisting of the value "html" or "xml", which changes void node behavior (Default is "html").
* post_elements - An array mapping tag names to post tag content (Default is array()).
* no_content_elements - An array that is used to exclude content of specific elements if "types" does not include "element" (Default is array("script" => true, "style" => true)).

This function is used by TagFilterNodes::GetOuterHTML(), TagFilterNodes::GetInnerHTML(), and TagFilterNodes::GetPlainText() functions, which are generally easier to use.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	echo $html->Implode(0, array("types" => "content", "post_elements" => array("p" => "\n\n"))) . "\n";
?>
```

TagFilterNodes::Get($id = 0)
----------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID (Default is 0).  Can also pass an array of node IDs.

Returns:  An instance of TagFilterNode on success, a boolean of false otherwise.  When an array of IDs is used, an array of objects are returned.

This function determines if the specified node ID exists and, if it does, instantiates a TagFilterNode object and returns it.  This is the easiest way to access a TagFilterNodes instance in an object-oriented fashion.  Note that there is a performance penalty for instantiating objects in PHP and may have RAM usage penalties as well.

Note that only Get() has support for arrays of IDs.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p")->Filter("a[href]");
	foreach ($rows as $row)
	{
		var_dump($row);
	}
?>
```

TagFilterNodes::GetParent($id)
------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.

Returns:  An instance of TagFilterNode on success, a boolean of false otherwise.

This function instantiates a TagFilterNode object pointing to the parent and returns it.  See TagFilterNode::Parent(), which is nearly identical to this method.

TagFilterNodes::GetChildren($id, $objects = false)
--------------------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $objects - A boolean indicating whether to return the child nodes as objects instead of an array (Default is false).

Returns:  An array of node IDs or objects depending on the value of $objects on success, a boolean of false otherwise.

This function retrieves all of the children node IDs of the specified node as either an array of node IDs or an array of TagFilterNode objects.  Returning an array of node IDs limits the possibility of memory leaks.  See TagFilterNode::Children(), which is nearly identical to this method.

TagFilterNodes::GetChild($id, $pos)
-----------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $pos - An integer containing the 0-based position of the child node to return.

Returns:  An instance of TagFilterNode on success, a boolean of false otherwise.

This function instantiates a TagFilterNode object pointing to the specified child node and returns it.  See TagFilterNode::Child(), which is nearly identical to this method.

TagFilterNodes::GetPrevSibling($id)
-----------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.

Returns:  An instance of TagFilterNode on success, a boolean of false otherwise.

This function instantiates a TagFilterNode object pointing to the previous sibling node and returns it.  See TagFilterNode::PrevSibling(), which is nearly identical to this method.

TagFilterNodes::GetNextSibling($id)
-----------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.

Returns:  An instance of TagFilterNode on success, a boolean of false otherwise.

This function instantiates a TagFilterNode object pointing to the next sibling node and returns it.  See TagFilterNode::NextSibling(), which is nearly identical to this method.

TagFilterNodes::GetTag($id)
---------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.

Returns:  A string containing the element's tag name on success, a boolean of false otherwise.

This function only works with element nodes and returns the tag name of the element (e.g. "table").

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$result = $html->Find("p");
	foreach ($result["ids"] as $id)
	{
		echo $html->GetTag($id) . "\n";
	}
?>
```

TagFilterNodes::Move($src, $newpid, $newpos)
--------------------------------------------

Access:  public

Parameters:

* $src - A TagFilterNodes instance, a correctly formatted node array, a string containing HTML, or an integer containing a node ID.
* $newpid - An integer containing a node ID that will become the parent node.
* $newpos - An integer containing the position to insert the new node(s) or a boolean to insert the new node(s) after the last child.

Returns:  A boolean of true on success, false otherwise.

This function modifies the $nodes array by moving the $src to the destination.  When $src is a TagFilterNodes instance, all of the nodes under the root (0) are copied, given new, non-conflicting IDs, and then attached to the destination.  When $src is a correctly formatted node array, it is attached to the destination.  When $src is a string, TagFilter::Explode() is called and then this function handles the new TagFilterNodes instance.  When $src is an integer, the node is reparented but only if it is not being reparented to one of its child nodes.

The TagFilterNodes::SetOuterHTML(), TagFilterNodes::SetInnerHTML(), and TagFilterNodes::SetPlainText() functions are generally easier to use than this function.  However, reparenting existing nodes with this function is much faster.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	echo $html->Implode(0) . "\n";

	// Move all image tags to be the first child of their parent element.
	$result = $html->Find("img");
	foreach ($result["ids"] as $id)
	{
		$html->Move($id, $html->nodes[$id]["parent"], 0);
	}

	echo $html->Implode(0) . "\n";

	// Append some HTML to each paragraph tag.
	$result = $html->Find("p");
	foreach ($result["ids"] as $id)
	{
		$html->Move("<br>You can do it!<br>Woo!", $html->nodes[$id]["parent"], 0);
	}

	echo $html->Implode(0) . "\n";
?>
```

TagFilterNodes::Remove($id, $keepchildren = false)
--------------------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $keepchildren - A boolean that indicates whether or not to reparent the child nodes.

Returns:  Nothing.

This function removes the specified node.  When $keepchildren is true, the node's children, if any, are moved into the parent node starting at the same position in the parent as the node being removed was located at.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	echo $html->Implode(0) . "\n";

	// Remove all 'a' tags but leave their content alone.
	$result = $html->Find("a");
	foreach ($result["ids"] as $id)
	{
		$html->Remove($id, true);
	}

	echo $html->Implode(0) . "\n";
?>
```

TagFilterNodes::Replace($id, $src, $inneronly = false)
------------------------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID to replace.
* $src - A TagFilterNodes instance, a correctly formatted node array, a string containing HTML, or an integer containing a node ID to use as the replacement.
* $inneronly - A boolean indicating whether to replace the specified node or just its child nodes.

Returns:  A boolean of true on success, false otherwise.

This function replaces the specified node OR its children depending on $inneronly.  This function performs a combination of TagFilterNodes::Remove() and TagFilterNodes::Move().

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	echo $html->Implode(0) . "\n";

	// Replace all image tags.
	$result = $html->Find("img");
	foreach ($result["ids"] as $id)
	{
		$html->Replace($id, "<b>[img was here]</b>");
	}

	echo $html->Implode(0) . "\n";
?>
```

TagFilterNodes::GetOuterHTML($id, $mode = "html")
-------------------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $mode - A string consisting of the value "html" or "xml".

Returns:  A string.

This function calls TagFilterNodes::Implode(), which starts at the specified node and walks the node tree, generating the result.  This function includes the initial node in the result.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$result = $html->Filter($html->Find("p"), "a[href]");
	foreach ($result["ids"] as $id)
	{
		echo $html->GetOuterHTML($id) . "\n\n";
	}
?>
```

TagFilterNodes::SetOuterHTML($id, $src)
---------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $src - A string containing HTML, a TagFilterNodes instance, a correctly formatted node array, or an integer containing a node ID to use as the replacement.

Returns:  A boolean of true on success, false otherwise.

This function replaces the specified node with $src.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$result = $html->Find("img");
	foreach ($result["ids"] as $id)
	{
		$html->SetOuterHTML($id, "<b>I used to be an image!</b>");
	}

	echo $html->Implode(0) . "\n";
?>
```

TagFilterNodes::GetInnerHTML($id, $mode = "html")
-------------------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $mode - A string consisting of the value "html" or "xml".

Returns:  A string.

This function calls TagFilterNodes::Implode(), which starts at the specified node and walks the node tree, generating the result.  This function does NOT include the initial node in the result.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$result = $html->Filter($html->Find("p"), "a[href]");
	foreach ($result["ids"] as $id)
	{
		echo $html->GetInnerHTML($id) . "\n\n";
	}
?>
```

TagFilterNodes::SetInnerHTML($id, $src)
---------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $src - A string containing HTML, a TagFilterNodes instance, a correctly formatted node array, or an integer containing a node ID to use as the replacement.

Returns:  A boolean of true on success, false otherwise.

This function replaces the children of the specified node with $src.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$result = $html->Find("a[href]");
	foreach ($result["ids"] as $id)
	{
		$html->SetInnerHTML($id, "<b>A bold link</b>");
	}

	echo $html->Implode(0) . "\n";
?>
```

TagFilterNodes::GetPlainText($id)
---------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.

Returns:  A string.

This function calls TagFilterNodes::Implode(), which starts at the specified node and walks the node tree, generating the result.  This function only includes content nodes in the result.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$result = $html->Find("p");
	foreach ($result["ids"] as $id)
	{
		echo $html->GetPlainText($id) . "\n\n";
	}
?>
```

TagFilterNodes::SetPlainText($id, $src)
---------------------------------------

Access:  public

Parameters:

* $id - An integer containing a node ID.
* $src - A string containing plain text, a TagFilterNodes instance, a correctly formatted node array, or an integer containing a node ID to use as the replacement.

Returns:  A boolean of true on success, false otherwise.

This function replaces the specified node with the plain text version of $src.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$result = $html->Find("a[href]");
	foreach ($result["ids"] as $id)
	{
		$html->SetPlainText($id, "I used to be a link.");
	}

	echo $html->Implode(0) . "\n";
?>
```

TagFilterNodes::RealignChildren($id, $pos)
------------------------------------------

Access:  private

Parameters:

* $id - An integer containing a node ID.
* $pos - An integer containing the starting position to begin adjusting child node parent positions.

Returns:  Nothing.

This internal function walks child nodes starting at the specified position and corrects each child node's "parentpos" value.  This function is called by other TagFilterNodes functions after the nodes array has been modified so that children point at their correct location in the parent node.


TagFilterNode Class
===================

Most users should implicitly create a TagFilterNode instance via TagFilterNodes::Get().  From there, additional classes will be created on the fly through the various methods of TagFilterNode.

Note that using TagFilterNode::SetOuterHTML() and TagFilterNode::SetPlainText() will cause the TagFilterNode instance to become invalid.  Future attempts to use an instance beyond that results in undefined behavior (most likely an exception/error condition will be raised by PHP).

TagFilterNode::__get($key)
--------------------------

Access:  public magic

Parameters:

* $key - A string of the attribute to retrieve.

Returns:  An array or string depending on the attribute if it exists, a boolean of false otherwise.

This magic function returns an element attribute's value.  The method will return false for non-elements (i.e. content and comments) and non-existent attributes.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("a[href]");
	foreach ($rows as $row)
	{
		// Implicitly call $row->__get("href")
		echo $row->href . "\n";
	}
?>
```

TagFilterNode::__set($key, $val)
--------------------------------

Access:  public magic

Parameters:

* $key - A string of the attribute to set.
* $val - A string or an array to use as the new value.

Returns:  Nothing.

This magic function sets an element attribute's value.  When $val is an array, it is assumed that the keys and values of the array are identical.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("a[href]");
	foreach ($rows as $row)
	{
		// Implicitly call $row->__set("href", "http://cubiclesoft.com/")
		$row->href = "http://cubiclesoft.com/";
	}

	echo $root . "\n";
?>
```

TagFilterNode::__isset($key)
----------------------------

Access:  public magic

Parameters:

* $key - A string of the attribute to check for.

Returns:  A boolean of true if the attribute exists, false otherwise.

This magic function checks for the existense of the specified attribute in an element.  The method will return false for non-elements (i.e. content and comments) and non-existent attributes.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("a");
	foreach ($rows as $row)
	{
		// Implicitly call $row->__isset("href")
		var_dump(isset($row->href));
	}
?>
```

TagFilterNode::__unset($key)
----------------------------

Access:  public magic

Parameters:

* $key - A string of the attribute to remove.

Returns:  Nothing.

This magic function removes the specified attribute from an element.  The method does nothing for non-elements (i.e. content and comments) and non-existent attributes.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p onclick=\"javascript here\"><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	// Note:  You are better off using TagFilter::HTMLPurify() and whitelisting what tags and attributes
	//        you want to allow instead of using a blacklist like this code demonstrates.
	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("[onclick]");
	foreach ($rows as $row)
	{
		// Implicitly call $row->__unset("onclick")
		unset($row->onclick);
	}

	echo $root . "\n";
?>
```

TagFilterNode::__toString()
---------------------------

Access:  public magic

Parameters:  None.

Returns:  A string containing HTML.

This magic function calls TagFilterNodes::GetOuterHTML() to get the HTML for the current node.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	// Implicitly call $root->__toString()
	echo $root . "\n";
?>
```

TagFilterNode::__debugInfo()
----------------------------

Access:  public magic

Parameters:  None.

Returns:  The array for the current node with the "id" set to the node ID.

This magic function returns the current node information.  If the node doesn't exist in the TagFilterNodes instance, then an array is returned that only contains the "id".

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	// Implicitly call $root->__debugInfo()
	var_dump($root);
?>
```

TagFilterNode::ID()
-------------------

Access:  public

Parameters:  None.

Returns:  An integer containing the node ID.

This function returns the node ID.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p");
	foreach ($rows as $row)
	{
		echo $row->ID() . "\n";
	}
?>
```

TagFilterNode::Node()
---------------------

Access:  public

Parameters:  None.

Returns:  An array containing the direct node information.

This function returns the underlying node.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p");
	foreach ($rows as $row)
	{
		var_dump($row->Node());
	}
?>
```

TagFilterNode::Type()
---------------------

Access:  public

Parameters:  None.

Returns:  A string containing the type of this node.  One of "element", "content", or "comment".

This function returns the type of the node.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("*");
	foreach ($rows as $row)
	{
		echo $row->ID() . ":  " $row->Type() . "\n";
	}
?>
```

TagFilterNode::Tag()
--------------------

Access:  public

Parameters:  None.

Returns:  A string containing the element tag name if the element exists, a boolean of false otherwise.

This function returns the tag name of an element node.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p><a>Not me!</a></p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find(":not(p)");
	foreach ($rows as $row)
	{
		echo $row->ID() . ":  " $row->Tag() . "\n";
	}
?>
```

TagFilterNode::AddClass($name, $attr = "class")
-----------------------------------------------

Access:  public

Parameters:

* $name - A string containing a class name to add.
* $attr - A string containing the attribute name to use (Default is "class").

Returns:  Nothing.

This function adds the specified name to the list of classes associated with the element node.  The optional attribute name parameter allows for flexibility with non-HTML documents where an attribute value is space-separated and exploded similar to the "class" attribute.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p class=\"cant-touch-this\"><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find(".cant-touch-this");
	foreach ($rows as $row)
	{
		$row->RemoveClass("cant-touch-this");
		$row->AddClass("wanna-bet");
	}

	echo $root . "\n";
?>
```

TagFilterNode::RemoveClass($name, $attr = "class")
--------------------------------------------------

Access:  public

Parameters:

* $name - A string containing a class name to add.
* $attr - A string containing the attribute name to use (Default is "class").

Returns:  Nothing.

This function removes the specified name from the list of classes associated with the element node.  The optional attribute name parameter allows for flexibility with non-HTML documents where an attribute value is space-separated and exploded similar to the "class" attribute.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p class=\"cant-touch-this\"><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find(".cant-touch-this");
	foreach ($rows as $row)
	{
		$row->RemoveClass("cant-touch-this");
		$row->AddClass("wanna-bet");
	}

	echo $root . "\n";
?>
```

TagFilterNode::Parent()
-----------------------

Access:  public

Parameters:  None.

Returns:  A TagFilterNode instance pointing to the parent node if it exists, false otherwise.

This function creates a new TagFilterNode instance that points to the parent node.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("a");
	foreach ($rows as $row)
	{
		echo $row . "\n";
		echo $row->Parent() . "\n";
	}
?>
```

TagFilterNode::Children($objects = false)
-----------------------------------------

Access:  public

Parameters:

* $objects - A boolean indicating whether or not the returned array contains objects instead of node IDs (Default is false).

Returns:  An array of node IDs or TagFilterNode instances on success, a boolean of false otherwise.

This function returns the node's children as node IDs or TagFilterNode instances.  Note that returning an array of objects has the potential to leak RAM due to historical refcounting issues within PHP itself.  Passing false, which is the default, is preferred.  Use with caution.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$ids = $root->Children();
	foreach ($ids as $id)
	{
		echo $html->Get($id) . "\n";
	}
?>
```

TagFilterNode::Child($pos)
--------------------------

Access:  public

Parameters:

* $pos - An integer representing the child to retrieve (may be negative).

Returns:  A TagFilterNode instance pointing at the child at the specified position on success, a boolean of false otherwise.

This function returns a TagFilterNode instance pointing at a specific child.  When $pos is negative, it retrieves the requested child node from the end.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$row = $root->Child(-1);
	echo $row . "\n";
?>
```

TagFilterNode::PrevSibling()
----------------------------

Access:  public

Parameters:  None.

Returns:  A TagFilterNode instance pointing at the previous sibling node on success, a boolean of false otherwise.

This function returns a TagFilterNode instance pointing at the previous sibling.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$row = $root->Child(-1)->PrevSibling();
	echo $row . "\n";
?>
```

TagFilterNode::NextSibling()
----------------------------

Access:  public

Parameters:  None.

Returns:  A TagFilterNode instance pointing at the next sibling node on success, a boolean of false otherwise.

This function returns a TagFilterNode instance pointing at the next sibling.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$row = $root->Child(0)->NextSibling();
	echo $row . "\n";
?>
```

TagFilterNode::Find($query, $cachequery = true, $firstmatch = false)
--------------------------------------------------------------------

Access:  public

Parameters:

* $query - A string containing a compatible CSS3 selector.
* $cachequery - A boolean that determines whether or not to cache the CSS3 query (Default is true).
* $firstmatch - A boolean that determines whether to return immediately after finding the first match (Default is false).

Returns:  A TagFilterNodeIterator instance on success, a standard array of information otherwise.

This function finds all elements that match the specified CSS3 selector query starting at the current node.  If $firstmatch is true, the function returns immediately after finding the first match.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\">";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("a[href]");
	foreach ($rows as $row)
	{
		echo $row . "\n";
	}
?>
```

TagFilterNode::Implode($options = array())
------------------------------------------

Access:  public

Parameters:

* $options - An array containing options (Default is array()).

Returns:  A string.

This function starts at the current node and walks the node tree, generating the result as defined by the options.  See TagFilterNodes::Implode() for details about the $options array.

The TagFilterNode::GetOuterHTML(), TagFilterNode::GetInnerHTML(), and TagFilterNode::GetPlainText() functions are generally easier to use.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	echo $root->Implode(0, array("types" => "content", "post_elements" => array("p" => "\n\n"))) . "\n";
?>
```

TagFilterNode::GetOuterHTML($mode = "html")
-------------------------------------------

Access:  public

Parameters:

* $mode - A string consisting of the value "html" or "xml".

Returns:  A string.

This function calls TagFilterNodes::Implode(), which starts at the current node and walks the node tree, generating the result.  This function includes the current node in the result.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p")->Filter("a[href]");
	foreach ($rows as $row)
	{
		echo $row->GetOuterHTML() . "\n\n";
	}
?>
```

TagFilterNode::SetOuterHTML($src)
---------------------------------

Access:  public

Parameters:

* $src - A string containing HTML, a TagFilterNodes instance, a correctly formatted node array, or an integer containing a node ID to use as the replacement.

Returns:  A boolean of true on success, false otherwise.

This function replaces the current node with $src.  Attempting to use the object any further results in undefined behavior.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("img");
	foreach ($rows as $row)
	{
		$row->SetOuterHTML("<b>I used to be an image!</b>");
	}

	echo $root . "\n";
?>
```

TagFilterNode::GetInnerHTML($mode = "html")
-------------------------------------------

Access:  public

Parameters:

* $mode - A string consisting of the value "html" or "xml".

Returns:  A string.

This function calls TagFilterNodes::Implode(), which starts at the current node and walks the node tree, generating the result.  This function does NOT include the current node in the result.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p")->Filter("a[href]");
	foreach ($rows as $row)
	{
		echo $row->GetInnerHTML() . "\n\n";
	}
?>
```

TagFilterNode::SetInnerHTML($src)
---------------------------------

Access:  public

Parameters:

* $src - A string containing HTML, a TagFilterNodes instance, a correctly formatted node array, or an integer containing a node ID to use as the replacement.

Returns:  A boolean of true on success, false otherwise.

This function replaces the children of the current node with $src.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("a[href]");
	foreach ($rows as $row)
	{
		$row->SetInnerHTML("<b>A bold link</b>");
	}

	echo $root . "\n";
?>
```

TagFilterNode::GetPlainText()
-----------------------------

Access:  public

Parameters:  None.

Returns:  A string.

This function calls TagFilterNodes::Implode(), which starts at the current node and walks the node tree, generating the result.  This function only includes content nodes in the result.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p");
	foreach ($rows as $row)
	{
		echo $root->GetPlainText() . "\n\n";
	}
?>
```

TagFilterNode::SetPlainText($src)
---------------------------------

Access:  public

Parameters:

* $src - A string containing plain text, a TagFilterNodes instance, a correctly formatted node array, or an integer containing a node ID to use as the replacement.

Returns:  A boolean of true on success, false otherwise.

This function replaces the current node with the plain text version of $src.  Attempting to use the object any further results in undefined behavior.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("a[href]");
	foreach ($rows as $row)
	{
		$row->SetPlainText("I used to be a link.");
	}

	echo $root . "\n";
?>
```


TagFilterNodeIterator Class
===========================

An instance of this class is returned in response to a TagFilterNode::Find() call.  It can be iterated over with a foreach.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p");
	foreach ($rows as $row)
	{
		echo $row . "\n\n";
	}
?>
```

TagFilterNodeIterator::Filter($query, $cachequery = true)
---------------------------------------------------------

Access:  public

Parameters:

* $query - A string containing a compatible CSS3 selector OR a string that starts with "/contains:" or "/~contains:".
* $cachequery - A boolean that determines whether or not to cache the CSS3 query (Default is true).

Returns:  A TagFilterNodeIterator instance on success, a standard array of information otherwise.

This function takes in the results of a previous TagFilterNode::Find() or TagFilterNodeIterator::Filter() call and returns a TagFilterNodeIterator that match the input query.  If the input query starts with the string "/contains:" (case-sensitive) or "/~contains:" (case-insensitive), each input node is analyzed for the existence of the rest of the query string in the plain text version of the node and its children.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";
	$htmloptions = TagFilter::GetHTMLOptions();

	$html = TagFilter::Explode($html, $htmloptions);

	$root = $html->Get();

	$rows = $root->Find("p")->Filter("a[href]");
	foreach ($rows as $row)
	{
		echo $row . "\n\n";
	}
?>
```


TagFilterStream Class
=====================

This class implements a powerful steam-enabled state engine to parse tag-based content in a single pass over the input data.  Callbacks are extensively utilized to allow for complete customization of the output stream.  This class is the underlying workhorse of all of the other classes.  Unless you have multi-megabyte HTML files or similar content to parse, most users will want to use the higher-level static functions found in TagFilter.  However, for sheer performance and minimizing memory usage, nothing beats using TagFilterSream directly.

Example usage:

```php
<?php
	require_once "support/tag_filter.php";

	$html = "<p><a href=\"http://barebonescms.com/\">A link</a><img src=\"cool.jpg\"></p><p>Not me!</p>";

	$hrefs = array();

	function ExtractHrefsCallback($stack, &$content, $open, $tagname, &$attrs, $options)
	{
		global $hrefs;

		if ($open)
		{
			if (isset($attrs["href"]))  $hrefs[] = $attrs["href"];
		}

		return array("keep_tag" => false, "keep_interior" => false);
	}

	$htmloptions = TagFilter::GetHTMLOptions();
	$htmloptions["tag_callback"] = "ExtractHrefsCallback";

	$tfs = new TagFilterStream($htmloptions);
	$tfs->Finalize();
	$result = $tfs->Process($html);

	var_dump($hrefs);
?>
```

TagFilterStream::__construct($options = array())
------------------------------------------------

Access:  public

Parameters:

* $options - An array containing options (Default is array()).

Returns:  Nothing.

This function calls TagFilterStream::Init().

TagFilterStream::Init($options = array())
-----------------------------------------

Access:  public

Parameters:

* $options - An array containing options (Default is array()).

Returns:  Nothing.

This function initializes or reinitializes the TagFilterStream instance with the new options array.  The options array may contain the following key-value mappings:

* tag_name_map - An array containing mappings for special tag names (e.g. array("!doctype" => "DOCTYPE")).  The key must include the prefix while the value is just the mapped tag name itself so that prefixes can't be modified.  (Default is array())
* untouched_tag_attr_keys - An array containing names of tags as the keys that the parser does not modify the attributes of (e.g. array("doctype" => true)).  Prefixes should not be included.  (Default is array())
* void_tags - An array containing void tags (e.g. array("br" => true)).  A void tag is one that does not normally have a matching termination tag.  Without this array, the engine would automatically add closing tags for void tags.  (Default is array())
* alt_tag_content_rules - An array containing names of tags and the callback function to use to process that tag's content (e.g. array("script" => "TagFilter::HTMLSpecialTagContentCallback")).  Certain tags such as 'script' and 'style' have different processing rules.  Each callback function must accept 8 parameters and correctly handle moving the internal engine state forward before returning to avoid an infinite loop - callback($stack, $final, &$tag, &$content, &$cx, $cy, &$content2, $options).  This is an advanced callback.  (Default is array())
* pre_close_tags - An array with a complex set of sub-rules for how the parser behaves when it encounters a new open tag and it is inside a tag that it shouldn't be inside.  For example, a "p" tag inside a "h1" tag is not allowed by the HTML specification so the "h1" is first closed before opening the "p" tag.  The parser will generally first close the existing open tag(s) before continuing.  See TagFilter::GetHTMLOptions() for more details.  (Default is array())
* process_attrs - An array that maps attribute names to special internal processing directives.  Currently only "classes" and "uri" mappings are supported (e.g. array("class" => "classes", "href" => "uri")).  (Default is array())
* keep_attr_newlines - A boolean that determines whether or not to keep newlines inside of attributes.  In general, this should be false as newlines in attributes break most homegrown parsers.  (Default is false)
* keep_comments - A boolean that determines whether or not to keep HTML style comments (Default is false).
* allow_namespaces - A boolean that determines whether or not to allow namespaces (Default is true).
* charset - A string representing a character set (Default is "UTF-8").
* output_mode - A string containing one of "html" or "xml" (Default is "html").
* lowercase_tags - A boolean that determines whether or not to force tag names to be lowercase (Default is true).
* lowercase_attrs - A boolean that determines whether or not to force attribute keys (not values) to be lowercase (Default is true).
* tag_callback - A valid callback function for a callback that will handle tags.  The callback function must accept six parameters and return an array - callback($stack, &$content, $open, $tagname, &$attrs, $options).
* content_callback - A valid callback function for a callback that will handle content.  The callback function must accept four parameters - callback($stack, $result, &$content, $options)

TagFilter::GetHTMLOptions() initializes most of the above options except for the optional tag and content callbacks.

The "tag_callback" and "content_callback" functions allow for the manipulation of the output and extraction of data in a single pass.  They are, however, rather cumbersome to write code for.  The TagFilter class source code contains several examples including TagFilter::ExplodeTagCallback(), TagFilter::ExplodeContentCallback(), TagFilter::HTMLPurifyTagCallback(), and TagFilter::HTMLSpecialTagContentCallback().

TagFilterStream::Process($content)
----------------------------------

Access:  public

Parameters:

* $content - A string of content to process.

Returns:  A string containing the result of processing.

This function runs the state engine as far as it can and returns the result of processing the input.  Until TagFilterStream::Finalize() is called, the function will return empty strings as output.  Gathered output can be forced by using TagFilterStream::GetStack(true) to get the stack, using TagFilter::GetParentPos() to determine where a specific tag is located in the stack, and then TagFilterStream::GetResult($pos) to return the flushed content up to the specified stack position.

TagFilterStream::Finalize()
---------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function sets the finalization bit so that the next call to Process() closes all open tags and returns any unflushed results.

TagFilterStream::GetStack($invert = false)
------------------------------------------

Access: public

Parameters:

* $invert - A boolean that specifies whether or not to invert the stack (Default is false).

Returns:  An array containing the current internal tag stack.

This function retrieves the internal tag stack.  If you plan on calling TagFilterStream::GetResult(), be sure to call this function with $invert set to true.

TagFilterStream::GetResult($invertedstackpos)
---------------------------------------------

Access:  public

Parameters:

* $invertedstackpos - An integer that indicates what inverted stack position to stop flushing results at in the tag stack.

Returns:  A string containing the flushed result.

This function returns the result so far up to the specified stack position and flushes the stored output to keep RAM usage low when using TagFilterStream in streaming mode.  Note that callback functions returning 'keep_tag' of false for the closing tag won't work for tags that were already output using this function.

TagFilterStream::UTF8Chr($num)
------------------------------

Access:  _internal_ public static

Parameters:

* $num - An integer representing a Unicode character.

Returns:  A UTF-8 string containing the Unicode character specified by $num on success, an empty string otherwise.

This internal static function converts an integer Unicode character to a UTF-8 string representation.
