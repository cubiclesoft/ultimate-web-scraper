<?php
	// Test suite.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../support/web_browser.php";
	require_once $rootpath . "/../support/simple_html_dom.php";
	require_once $rootpath . "/../support/tag_filter.php";
	require_once $rootpath . "/../support/multi_async_helper.php";

	function DisplayParseSelectorResult($result)
	{
		echo $result["selector"] . "\n";
		if (!$result["success"])
		{
			echo "  " . $result["error"] . " (" . $result["errorcode"] . ")\n";
			echo "  Start pos:  " . $result["startpos"] . "; End pos:  " . $result["pos"] . "; State:  " . $result["state"] . "\n";
		}

		foreach ($result["tokens"] as $num => $token)
		{
			echo "  " . ($num + 1) . ":  " . json_encode($token, JSON_UNESCAPED_SLASHES) . "\n";
		}

		echo "\n";
	}

	echo "Testing CSS selector parsing\n";
	echo "----------------------------\n";
	DisplayParseSelectorResult(TagFilter::ParseSelector("li,p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("li   ,   p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("li,p,"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("*,*.t1"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a[href]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a [href]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector('a[href="http://domain.com/"]'));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a[href='http://domain.com/']"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("tr.gotclass"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("tr[class~=gotclass]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("span[lang|=en]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a[ href ^= 'http://' ]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a[href$='.jpg']"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a[href*='/images/']"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("tr.gotclass.moreclass.moarclass"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(".t1:not(.t2)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(":not(.t2).t1"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(.t1):not(.t2)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("#the-one_id"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("#the-one_id#nevermatch"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a#the-one_id.gotclass"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("a[href]a:not(p)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p *:link"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p *:visited"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:target"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("li:lang(en-US)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("input:enabled,button:disabled"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("input:checked, input:checked + span"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ul > li:nth-child(odd)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ol > li:nth-child(even)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable tr:nth-child(-n+4)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:nth-child(3n+1)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ul > li:nth-last-child(odd)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ol > li:nth-last-child(even)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable tr:nth-last-child(-n+4)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:nth-last-child(3n+1)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:nth-of-type(3)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:nth-last-of-type(3)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:first-child"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:last-child"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:first-of-type"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:last-of-type"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:only-child"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:only-of-type"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:first-line"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p::first-line"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:first-letter"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p::first-letter"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:before"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p::before"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:after"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p::after"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv > p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(".test > div"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("#test > div"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv p + p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(".nope + p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv > p ~ p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector('div.maindiv *:not([title^="super "])'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('div.maindiv *:not([title*=" ion "])'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('div.maindiv *:not([title$=" cannon"])'));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(#badid)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(":not(:link)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(":not(:visited)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:target)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:lang(fr))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("input:not(:enabled),button:not(:disabled)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("input:not(:checked), input:not(:checked) + span"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ul > li:not(:nth-child(odd))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ol > li:not(:nth-child(even))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable tr:not(:nth-child(-n+4))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:not(:nth-child(3n+1))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ul > li:not(:nth-last-child(odd))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("ol > li:not(:nth-last-child(even))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable tr:not(:nth-last-child(-n+4))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:not(:nth-last-child(3n+1))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:nth-of-type(3))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:nth-last-of-type(3))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:not(:first-child)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("table.maintable td:not(:last-child)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:first-of-type)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:last-of-type)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:only-child)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:only-of-type)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(:not(p))"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv > div.inside p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv + div.other ~ p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv + div.other p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv div.inside > p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv ~ div.other + p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:empty"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("input[form],textarea[form],select[form],button[form],datalist[id]"));

	// Start of ugly/error tests.
	DisplayParseSelectorResult(TagFilter::ParseSelector(".5cm"));
	DisplayParseSelectorResult(TagFilter::ParseSelector('.\5cm'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('.impossible\ class\ name'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('.escaped\.class\.name'));
	DisplayParseSelectorResult(TagFilter::ParseSelector("div.maindiv & div.inner, p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("[*=nope]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("[*|*=nope]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("[*|fo-reals*=yup]"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:subject"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("\\0050\r\n:\\04e\r\n\\6F \\000054(   \\70 \r\n\t)"));  // p:not(p) in Unicode
	DisplayParseSelectorResult(TagFilter::ParseSelector("p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p,p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(".p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p,.p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(".p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p.p"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span):not(.span)"));
	DisplayParseSelectorResult(TagFilter::ParseSelector("p:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child:first-child"));
	DisplayParseSelectorResult(TagFilter::ParseSelector(".13"));
	DisplayParseSelectorResult(TagFilter::ParseSelector('.\13'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('.\13 \33'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('div:not(#other).notclass:not(.nope).test#theid#theid'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('DIV TABLE p'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('p..nope'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('p[class$=""]'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('p[class^=""]'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('p[class*=""]'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('p:not([class$=""])'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('p:not([class^=""])'));
	DisplayParseSelectorResult(TagFilter::ParseSelector('p:not([class*=""])'));
	echo "-------------------\n\n";

	echo "Testing CSS selector cleanup\n";
	echo "----------------------------\n";
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
	echo "-------------------\n\n";

	// TagFilter/TagFilterStream HTML parser tests.
	$htmloptions = TagFilter::GetHTMLOptions();
	$purifyoptions = array(
		"allowed_tags" => "img,a,p,br,b,strong,i,ul,ol,li",
		"allowed_attrs" => array("img" => "src", "a" => "href,id", "p" => "class"),
		"required_attrs" => array("img" => "src", "a" => "href"),
		"allowed_classes" => array("p" => "allowedclass"),
		"remove_empty" => "b,strong,i,ul,ol,li"
	);

	echo "Testing raw HTML cleanup\n";
	echo "------------------------\n";
	$testfile = file_get_contents($rootpath . "/test_xss.txt");
	$pos = strpos($testfile, "@EXIT@");
	if ($pos === false)  $pos = strlen($testfile);
	$testfile = substr($testfile, 0, $pos);

	$result = TagFilter::Run($testfile, $htmloptions);
	echo $result . "\n\n";
	echo "-------------------\n\n";

	echo "Testing XSS removal\n";
	echo "-------------------\n";
	$testfile = file_get_contents($rootpath . "/test_xss.txt");
	$pos = strpos($testfile, "@EXIT@");
	if ($pos === false)  $pos = strlen($testfile);
	$testfile = substr($testfile, 0, $pos);

	$result = TagFilter::HTMLPurify($testfile, $htmloptions, $purifyoptions);
	echo $result . "\n\n";
	echo "-------------------\n\n";

	echo "Testing Word HTML cleanup\n";
	echo "-------------------------\n";
	$testfile = file_get_contents($rootpath . "/test_word.txt");
	$pos = strpos($testfile, "@EXIT@");
	if ($pos === false)  $pos = strlen($testfile);
	$testfile = substr($testfile, 0, $pos);

	$result = TagFilter::HTMLPurify($testfile, $htmloptions, $purifyoptions);
	echo $result . "\n\n";
	echo "-------------------------\n\n";


	// Exploded HTML extraction and TagFilterNodes tests.
	echo "Testing Word HTML explode\n";
	echo "-------------------------\n";
	$testfile = file_get_contents($rootpath . "/test_word.txt");
	$pos = strpos($testfile, "@EXIT@");
	if ($pos === false)  $pos = strlen($testfile);
	$testfile = substr($testfile, 0, $pos);

	// Returns a TagFilterNodes object.
	$tfn = TagFilter::Explode($testfile, $htmloptions);
	echo count($tfn->nodes) . " nodes\n";
	foreach ($tfn->nodes as $num => $node)
	{
		echo "  " . $num . ":  " . json_encode($node, JSON_UNESCAPED_SLASHES) . "\n";
	}
	echo "\n";
	echo "-------------------------\n\n";

	function DisplayTFNFindResult($msg, $tfn, $result)
	{
		echo $msg . "\n";
		echo "  " . json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
		foreach ($result["ids"] as $id)
		{
			echo "  " . $id . ":\n";
			echo "    Tag:         " . $tfn->GetTag($id) . "\n";
			echo "    Node:        " . json_encode($tfn->nodes[$id], JSON_UNESCAPED_SLASHES) . "\n";
			echo "    Outer HTML:  " . $tfn->GetOuterHTML($id) . "\n";
			echo "    Inner HTML:  " . $tfn->GetInnerHTML($id) . "\n";
			echo "    Plain text:  " . $tfn->GetPlainText($id) . "\n";
			if (isset($tfn->nodes[$id]["attrs"]["href"]))  echo "    href:        " . $tfn->nodes[$id]["attrs"]["href"] . "\n";
			if (isset($tfn->nodes[$id]["attrs"]["src"]))  echo "    src:         " . $tfn->nodes[$id]["attrs"]["src"] . "\n";
		}

		echo "\n";
	}

	function DisplayOOTFNFindResults($msg, $results)
	{
		echo $msg . "\n";
		foreach ($results as $id => $row)
		{
			echo "  " . $id . ":\n";
			echo "    Tag:         " . $row->Tag($id) . "\n";
			echo "    Node:        " . json_encode($row->Node(), JSON_UNESCAPED_SLASHES) . "\n";
			echo "    Outer HTML:  " . $row->GetOuterHTML() . "\n";
			echo "    Inner HTML:  " . $row->GetInnerHTML() . "\n";
			echo "    Plain text:  " . $row->GetPlainText() . "\n";
			if (isset($row->href))  echo "    href:        " . $row->href . "\n";
			if (isset($row->src))  echo "    src:         " . $row->src . "\n";
		}

		echo "\n";
	}

	echo "Testing TagFilterNodes features\n";
	echo "-------------------------------\n";
	DisplayTFNFindResult("Find('a')", $tfn, $tfn->Find("a"));
	DisplayTFNFindResult("Filter(Find('a'), '[href]')", $tfn, $tfn->Filter($tfn->Find("a"), "[href]"));
	DisplayTFNFindResult("Find('a[href]')", $tfn, $tfn->Find("a[href]"));
	DisplayTFNFindResult("Find('p')", $tfn, $tfn->Find("p"));
	DisplayTFNFindResult("Filter(Find('p'), 'a[href]')", $tfn, $tfn->Filter($tfn->Find("p"), "a[href]"));
	DisplayTFNFindResult("Filter(Find('p'), '/contains:A link')", $tfn, $tfn->Filter($tfn->Find("p"), "/contains:A link"));
	DisplayTFNFindResult("Filter(Find('p'), '/~contains:a link')", $tfn, $tfn->Filter($tfn->Find("p"), "/~contains:a link"));

	echo "Appending a '<img src=\"awesome.jpg\">' tag to every 'p' tag that contains a 'a[href]'\n";
	$result = $tfn->Filter($tfn->Find("p"), "a[href]");
	echo "  " . json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
	foreach ($result["ids"] as $id)  echo "  " . $id . ":  " . ($tfn->Move("<img src=\"awesome.jpg\">", $id, true) ? "true" : "false") . "\n";
	echo "\n";

	DisplayTFNFindResult("Find('img')", $tfn, $tfn->Find("img"));
	DisplayTFNFindResult("Filter(Find('p'), 'a[href]')", $tfn, $tfn->Filter($tfn->Find("p"), "a[href]"));

	echo "Moving all 'img' tags to be the first child of its parent element.\n";
	$result = $tfn->Find("img");
	echo "  " . json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
	foreach ($result["ids"] as $id)  echo "  " . $id . ":  " . ($tfn->Move($id, $tfn->nodes[$id]["parent"], 0) ? "true" : "false") . "\n";
	echo "\n";

	DisplayTFNFindResult("Find('img')", $tfn, $tfn->Find("img"));
	DisplayTFNFindResult("Filter(Find('p'), 'img')", $tfn, $tfn->Filter($tfn->Find("p"), "img"));

	echo "Replacing all 'img' tags with '<img src=\"cool.jpg\">' using SetOuterHTML().\n";
	$result = $tfn->Find("img");
	echo "  " . json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
	foreach ($result["ids"] as $id)  echo "  " . $id . ":  " . ($tfn->SetOuterHTML($id, "<img src=\"cool.jpg\">") ? "true" : "false") . "\n";
	echo "\n";

	DisplayTFNFindResult("Find('img')", $tfn, $tfn->Find("img"));
	DisplayTFNFindResult("Filter(Find('p'), 'img')", $tfn, $tfn->Filter($tfn->Find("p"), "img"));

	echo "Bolding links using SetInnerHTML().\n";
	$result = $tfn->Find("a[href]");
	echo "  " . json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
	foreach ($result["ids"] as $id)  echo "  " . $id . ":  " . ($tfn->SetInnerHTML($id, "<b>" . $tfn->GetInnerHTML($id) . "</b>") ? "true" : "false") . "\n";
	echo "\n";

	DisplayTFNFindResult("Find('a')", $tfn, $tfn->Find("a"));
	DisplayTFNFindResult("Filter(Find('p'), 'a[href]')", $tfn, $tfn->Filter($tfn->Find("p"), "a[href]"));

	echo "Removing all 'span' tags but keeping all child nodes.\n";
	$result = $tfn->Find("span");
	echo "  " . json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
	foreach ($result["ids"] as $id)
	{
		$tfn->Remove($id, true);
		echo "  " . $id . ":  true\n";
	}
	echo "\n";

	DisplayTFNFindResult("Find('span')", $tfn, $tfn->Find("span"));
	DisplayTFNFindResult("Find('p')", $tfn, $tfn->Find("p"));

	DisplayTFNFindResult("Find('p:first-of-type')", $tfn, $tfn->Find("p:first-of-type"));
	DisplayTFNFindResult("Find('p:last-of-type')", $tfn, $tfn->Find("p:last-of-type"));
	DisplayTFNFindResult("Find('a b:only-of-type')", $tfn, $tfn->Find("a b:only-of-type"));
	DisplayTFNFindResult("Find('p:nth-of-type(1)')", $tfn, $tfn->Find("p:nth-of-type(1)"));
	DisplayTFNFindResult("Find('p:nth-last-of-type(2)')", $tfn, $tfn->Find("p:nth-last-of-type(2)"));

	echo "Testing object-oriented access.\n\n";
	echo "Root node:\n";
	$root = $tfn->Get();
	var_dump($root);
	echo "\n";

	DisplayOOTFNFindResults("Find('a[href]')", $root->Find("a[href]"));
	DisplayOOTFNFindResults("Find('p')->Filter('a[href]')", $root->Find("p")->Filter("a[href]"));

	echo "-------------------------\n\n";


	// Web scraping.
	$html = new simple_html_dom();

	$web = new WebBrowser();
	$result = $web->Process("http://www.barebonescms.com/");
	if (!$result["success"])  echo "[FAIL] An error occurred.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "[FAIL] An unexpected response code was returned.  " . $result["response"]["line"] . "\n";
	else
	{
		echo "[PASS] The expected response was returned.\n";

		$html->load($result["body"]);
		$rows = $html->find('a[href]');
		foreach ($rows as $row)
		{
			echo "\t" . HTTP::ConvertRelativeToAbsoluteURL($result["url"], $row->href) . "\n";
		}
	}

	$result = $web->Process("https://www.barebonescms.com/");
	if (!$result["success"])  echo "[FAIL] An error occurred.  " . $result["error"] . "\n";
	else if ($result["response"]["code"] != 200)  echo "[FAIL] An unexpected response code was returned.  " . $result["response"]["line"] . "\n";
	else
	{
		echo "[PASS] The expected response was returned.\n";

		$html->load($result["body"]);
		$rows = $html->find('a[href]');
		foreach ($rows as $row)
		{
			echo "\t" . HTTP::ConvertRelativeToAbsoluteURL($result["url"], $row->href) . "\n";
		}
	}

	// Test asynchronous access.
	$urls = array(
		"http://www.barebonescms.com/",
		"http://www.cubiclesoft.com/",
	);

	// Build the queue.
	$helper = new MultiAsyncHelper();
	$pages = array();
	foreach ($urls as $url)
	{
		$pages[$url] = new WebBrowser();
		$pages[$url]->ProcessAsync($helper, $url, NULL, $url, array("debug" => true));
	}

	// Mix in another file handle type for fun.
	$fp = fopen(__FILE__, "rb");
	stream_set_blocking($fp, 0);
	$helper->Set("__fp", $fp, "MultiAsyncHelper::ReadOnly");

	// Run the main loop.
	$result = $helper->Wait(2);
	while ($result["success"])
	{
		// Process the file handle if it is ready for reading.
		if (isset($result["read"]["__fp"]))
		{
			$fp = $result["read"]["__fp"];
			$data = fread($fp, 500);
			if ($data === false || feof($fp))
			{
				echo "[PASS] File read in successfully.\n";

				$helper->Remove("__fp");
			}
		}

		// Process everything else.
		foreach ($result["removed"] as $key => $info)
		{
			if ($key === "__fp")  continue;

			if (!$info["result"]["success"])  echo "[FAIL] Error retrieving URL (" . $key . ").  " . $info["result"]["error"] . "\n";
			else if ($info["result"]["response"]["code"] != 200)  echo "[FAIL] Error retrieving URL (" . $key . ").  Server returned:  " . $info["result"]["response"]["line"] . "\n";
			else
			{
				echo "[PASS] The expected response was returned (" . $key . ").  " . strlen($info["result"]["body"]) . " bytes returned.\n";
			}

			unset($pages[$key]);
		}


		// Break out of the loop when nothing is left.
		if ($result["numleft"] < 1)  break;

		$result = $helper->Wait(2);
	}

	// An error occurred.
	if (!$result["success"])  echo "[FAIL] Error in Wait().  " . $result["error"] . "\n";
?>