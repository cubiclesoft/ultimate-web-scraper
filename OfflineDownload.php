<?php
/**
 * Created by PhpStorm
 * User: Junior Trust
 * Date: 12/16/2019
 * Time: 12:25 AM
 */

require_once "./support/http.php";
require_once "./support/web_browser.php";
require_once "./support/tag_filter.php";
require_once "./support/multi_async_helper.php";
@ini_set("memory_limit", "-1");
class OfflineDownload
{
    protected $linkdepth;
    protected $destpath;
    protected $initurl;
    /**
     * @var array
     */
    private $initurl2;
    /**
     * @var array
     */
    protected $initurl3;
    /**
     * @var string
     */
    protected $opsfile;
    /**
     * @var string
     */
    protected $manifestfile;
    /**
     * @var array
     */
    protected $htmloptions;
    /**
     * @var MultiAsyncHelper
     */
    protected $helper;
    protected $opsdata =[];
    /**
     * @var array
     */
    private $processedurls;
    /**
     * @var array
     */
    protected $manifest;
    /**
     * @var array
     */
    protected $manifestrev;
    /**
     * @var array
     */
    protected $ops;


    public function __construct($folder_path, $url, $depth=false)
    {
       $this->linkdepth= ($depth!==false)?(int)$depth:$depth;

        @mkdir($folder_path, 0770, true);
        $this->destpath = realpath($folder_path);


        // Alter input URL to remove potential attack vectors.
        $this->initurl = $url;
        $this->initurl2 = HTTP::ExtractURL($this->initurl);

        $this->initurl2["authority"] = strtolower($this->initurl2["authority"]);
        $this->initurl2["host"] = strtolower($this->initurl2["host"]);
        if ($this->initurl2["path"] === "")  $this->initurl2["path"] = "/";

        $this->initurl3 = $this->initurl2;
        $this->initurl3["host"] = "";
        $this->initurl2["path"] = "/";

        $this->initurl = HTTP::ConvertRelativeToAbsoluteURL($this->initurl2, $this->initurl3);

        $this->manifestfile = $this->destpath . "/" . str_replace(":", "_", $this->initurl2["authority"]) . "_manifest.json";
        $this->opsfile = $this->destpath . "/" . str_replace(":", "_", $this-> initurl2["authority"]) . "_ops_" . md5(($this->linkdepth === false ? "-1" : $this->linkdepth) . "|" . $this->initurl) . ".json";

        $this->destpath .= "/" . str_replace(":", "_", $this->initurl2["authority"]);
        @mkdir($this->destpath, 0770, true);


        $this->helper = new MultiAsyncHelper();
        $this->helper->SetConcurrencyLimit(4);

        $this->htmloptions = TagFilter::GetHTMLOptions();
        $this->htmloptions["keep_comments"] = true;
        // Maps a manifest item to a static path on disk.
        $this->processedurls = array();

        // Load the URL mapping manifest and operations files if they exist in order to continue wherever this script left off.
        $this->manifest = @json_decode(file_get_contents($this->manifestfile), true);
        if (!is_array($this->manifest))  $this->manifest = array();
        $this->manifestrev = array();

        $this->ops = @json_decode(file_get_contents($this->opsfile), true);


    }

    public function run()
    {

        foreach ($this->manifest as $key => $val)
        {
            $vals = explode("/", $val);
            $val = array_shift($vals) . "/";
            while (count($vals))
            {
                $val .= array_shift($vals);

                $this->manifestrev[strtolower($val)] = $val;

                $val .= "/";
            }
        }


        if (is_array($this->ops))
        {
            // Initialize the operations queue.
            foreach ($this->ops as $url => &$info)
            {
                $key = $url;

                $info["status"] = "download";
                $info["retries"] = 3;
                $info["web"] = new WebBrowser($info["web_state"]);
                $info["web"]->ProcessAsync($this->helper, $key, NULL, $url, $info["options"]);

                unset($info["web_state"]);
            }

            unset($info);
        }
        else
        {
            // Queue the first operation.
            $this->ops = array();

            $key = $this->initurl;

            $this->ops[$key] = array(
                "type" => "node",
                "status" => "download",
                "depth" => 0,
                "retries" => 3,
                "ext" => false,
                "waiting" => array(),
                "web" => new WebBrowser(),
                "options" => array(
                    "pre_retrievewebpage_callback" => [$this,"DisplayURL"]
//                    "pre_retrievewebpage_callback" => "DisplayURL",
                )
            );

            $this->ops[$key]["web"]->ProcessAsync($this->helper, $key, NULL, $this->initurl, $this->ops[$key]["options"]);

            // Queue 'favicon.ico'.
//		PrepareManifestResourceItem(false, ".ico", HTTP::ConvertRelativeToAbsoluteURL($initurl, "/favicon.ico"));

            // Queue 'robots.txt'.
//		PrepareManifestResourceItem(false, ".txt", HTTP::ConvertRelativeToAbsoluteURL($initurl, "/robots.txt"));

            $this->SaveQueues();
        }

        $this->opsdata = array();

        // Run the main loop.
        $result = $this->helper->Wait();
        while ($result["success"])
        {
            // Process finished items.
            foreach ($result["removed"] as $key => $info)
            {
                if (!$info["result"]["success"])
                {
                    $this->ops[$key]["retries"]--;
                    if ($this->ops[$key]["retries"])  $this->ops[$key]["web"]->ProcessAsync($this->helper, $key, NULL, $key, $info["tempoptions"]);

                    echo "Error retrieving URL (" . $key . ").  " . ($this->ops[$key]["retries"] > 0 ? "Retrying in a moment.  " : "") . $info["result"]["error"] . " (" . $info["result"]["errorcode"] . ")\n";
                }
                else
                {
                    echo "[" . number_format(count($this->ops), 0) . " ops] Processing '" . $key . "'.\n";

                    // Just report non-200 OK responses.  Store the data except for 404 errors.
                    if ($info["result"]["response"]["code"] != 200)  echo "Error retrieving URL '" . $info["result"]["url"] . "'.\nServer returned:  " . $info["result"]["response"]["line"] . "\n";

                    $this->opsdata[$key] = array(
                        "httpcode" => $info["result"]["response"]["code"],
                        "url" => $info["result"]["url"],
                        "content" => $info["result"]["body"]
                    );

                    unset($info["result"]["body"]);

                    // Get the final file extension to use.
                    if ($this->ops[$key]["ext"] === false)  $this->ops[$key]["ext"] = $this->GetResultFileExtension($info["result"]);

                    // Calculate the reverse manifest path.
                    $this->SetReverseManifestPath($key);

                    // Process the incoming content, if relevant.
                    $this->ProcessContent($key, false);

                    // Walk parents and reduce the number of resources being waited on.
                    $process = array();
                    if ($this->ops[$key]["status"] !== "waiting")
                    {
                        $process[] = $key;

                        // Process the content a second time.  This time updating all valid, processed URLs with static URLs.
                        $this->ProcessContent($key, true);
                    }

                    foreach ($this->ops[$key]["waiting"] as $pkey)
                    {
                        $this->ops[$pkey]["wait_refs"]--;

                        if ($this->ops[$pkey]["wait_refs"] <= 0)
                        {
                            $process[] = $pkey;

                            // Process the content a second time.  This time updating all valid, processed URLs with static URLs.
                            $this->ProcessContent($pkey, true);
                        }
                    }

                    $this->ops[$key]["waiting"] = array();

                    // Store ready documents to disk.
                    while (count($process))
                    {
                        $key2 = array_shift($process);

                        if ($this->opsdata[$key2]["httpcode"] >= 400)  echo "[" . number_format(count($this->ops), 0) . " ops] Finalizing '" . $key2 . "'.\n";
                        else
                        {
                            echo "[" . number_format(count($this->ops), 0) . " ops] Saving '" . $key2 . "' to '" . $this->destpath . $this->opsdata[$key2]["path"] . "'.\n";

                            $this->manifest[str_replace(array("http://", "https://"), "//", $key2)] = $this->opsdata[$key2]["path"];

                            // Write data to disk.
                            file_put_contents($this->destpath . $this->opsdata[$key2]["path"], $this->opsdata[$key2]["content"]);
                        }

                        $this->processedurls[$key2] = true;

                        unset($this->opsdata[$key2]);

                        // Walk parents and reduce the number of resources being waited on.
                        foreach ($this->ops[$key2]["waiting"] as $pkey)
                        {
                            $this->ops[$pkey]["wait_refs"]--;

                            if ($this->ops[$pkey]["wait_refs"] <= 0)
                            {
                                $process[] = $pkey;

                                // Process the content a second time.  This time updating all valid, processed URLs with static URLs.
                                $this->ProcessContent($pkey, true);
                            }
                        }

                        unset($this->ops[$key2]);
                    }
                }
            }

            if (count($result["removed"]))  $this->SaveQueues();

            // Break out of the loop when there is nothing left to do.
            if (!$this->helper->NumObjects())  break;

            $result = $this->helper->Wait();
        }




        // Final message.
        if (count($this->ops))
        {
            echo "Unable to process the following URLs:\n\n";

            foreach ($this->ops as $url => $info)
            {
                echo "  " . $url . "\n";
            }

            echo "\n";
            echo "Done, with errors.\n";
        }
        else
        {
            echo "Done.\n";
        }
    }

    protected function SaveQueues()
    {

        file_put_contents($this->manifestfile, json_encode($this->manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $ops2 = array();
        foreach ($this->ops as $url => $info)
        {
            $info["web_state"] = $info["web"]->GetState();
            unset($info["web"]);

            $ops2[$url] = $info;
        }

        file_put_contents($this->opsfile, json_encode($ops2, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    }

    // Calculates the static file extension based on the result of a HTTP request.
    public function GetResultFileExtension(&$result)
    {
        $mimeextmap = array(
            "text/html" => ".html",
            "text/plain" => ".txt",
            "image/jpeg" => ".jpg",
            "image/png" => ".png",
            "image/gif" => ".gif",
            "text/css" => ".css",
            "text/javascript" => ".js",
        );

        // Attempt to map a Content-Type header to a file extension.
        if (isset($result["headers"]["Content-Type"]))
        {
            $header = HTTP::ExtractHeader($result["headers"]["Content-Type"][0]);

            if (isset($mimeextmap[strtolower($header[""])]))  return $mimeextmap[$header[""]];
        }

        $fileext = false;

        // Attempt to map a Content-Disposition header to a file extension.
        if (isset($result["headers"]["Content-Disposition"]))
        {
            $header = HTTP::ExtractHeader($result["headers"]["Content-Type"][0]);

            if ($header[""] === "attachment" && isset($header["filename"]))
            {
                $filename = explode("/", str_replace("\\", "/", $header["filename"]));
                $filename = array_pop($filename);
                $pos = strrpos($filename, ".");
                if ($pos !== false)  $fileext = strtolower(substr($filename, $pos));
            }
        }

        // Parse the URL and attempt to map to a file extension.
        if ($fileext === false)
        {
            $url = HTTP::ExtractURL($result["url"]);

            $filename = explode("/", str_replace("\\", "/", $url["path"]));
            $filename = array_pop($filename);
            $pos = strrpos($filename, ".");
            if ($pos !== false)  $fileext = strtolower(substr($filename, $pos));
        }

        if ($fileext === false)  $fileext = ".html";

        // Avoid unfortunate/accidental local code execution via a localhost web server.
        $maptohtml = array(
            ".php" => true,
            ".php3" => true,
            ".php4" => true,
            ".php5" => true,
            ".php7" => true,
            ".phtml" => true,
            ".asp" => true,
            ".aspx" => true,
            ".cfm" => true,
            ".jsp" => true,
            ".pl" => true,
            ".cgi" => true,
        );

        if (isset($maptohtml[$fileext]))  $fileext = ".html";

        return $fileext;
    }

    // Attempt to create a roughly-equivalent structure to the URL on the local filesystem for static serving later.
   public function SetReverseManifestPath($key)
    {

        $url2 = HTTP::ExtractURL($key);
        $path = "";
        if (strcasecmp($url2["authority"], $this->initurl2["authority"]) != 0)  $path .= "/" . str_replace(":", "_", strtolower($url2["authority"]));
        $path .= ($url2["path"] !== "" ? $url2["path"] : "/");
        $path = explode("/", str_replace("\\", "/", TagFilterStream::MakeValidUTF8($path)));
        $filename = array_pop($path);
        if ($filename === "")  $filename = "index";

        $pos = strrpos($filename, ".");
        if ($pos !== false)  $filename = substr($filename, 0, $pos);

        if ($url2["query"] !== "")  $filename .= "_" . md5($url2["query"]);

        // Make a clean directory.
        $vals = $path;
        $path = array_shift($vals) . "/";
        while (count($vals))
        {
            $path .= array_shift($vals);

            if (isset($this->manifestrev[strtolower($path)]))  $path = $this->manifestrev[strtolower($path)];
            else  $this->manifestrev[strtolower($path)] = $path;

            $x = 0;
            while (is_file($this->destpath . $path . ($x ? "_" . ($x + 1) : "")))  $x++;

            if ($x)  $path .= "_" . ($x + 1);

            $path .= "/";
        }

        @mkdir($this->destpath . $path, 0770, true);

        // And a clean filename.
        $path .= $filename;

        $x = 0;
        while (isset($this->manifestrev[strtolower($path . ($x ? "_" . ($x + 1) : "") . $this->ops[$key]["ext"])]) || is_dir($path . ($x ? "_" . ($x + 1) : "") . $this->ops[$key]["ext"]))  $x++;

        $path .= ($x ? "_" . ($x + 1) : "") . $this->ops[$key]["ext"];

        $this->opsdata[$key]["path"] = $path;

        // Reserve an entry in the reverse manifest for the full path/filename.
        $this->manifestrev[strtolower($path)] = $path;

//var_dump($opsdata[$key]["path"]);
//var_dump($manifestrev);
    }
  public  function MapManifestResourceItem($parenturl, $url)
    {

        // Strip scheme if HTTP/HTTPS.  Otherwise, just return the URL as-is (e.g. mailto: and data: URIs).
        if (strtolower(substr($url, 0, 7)) === "http://")  $url2 = substr($url, 5);
        else if (strtolower(substr($url, 0, 8)) === "https://")  $url2 = substr($url, 6);
        else  return $url;

        // If already processed and valid, return the relative reference to the path on disk.
        if ($parenturl !== false && isset($this->opsdata[$parenturl]) && (isset($this->manifest[$url2]) || isset($this->opsdata[$url])))
        {
            $path = explode("/", $this->opsdata[$parenturl]["path"]);
            $path2 = explode("/", (isset($this->manifest[$url2]) ? $this->manifest[$url2] : $this->opsdata[$url]["path"]));

            array_pop($path);

            while (count($path) && count($path2) && $path[0] === $path2[0])
            {
                array_shift($path);
                array_shift($path2);
            }

            $path2 = str_repeat("../", count($path)) . implode("/", $path2);

            return $path2;
        }

        // If already processed but not valid (e.g. a 404 error), just return the URL.
        if (isset($this->processedurls[$url]))  return $url;

        return false;
    }

    // Generates a leaf node and prevents the parent from completing until the document URLs are updated.
   public function PrepareManifestResourceItem($parenturl, $forcedext, $url)
    {

        $pos = strpos($url, "#");
        if ($pos === false)  $fragment = false;
        else
        {
            $fragment = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        // Skip downloading if the item has already been processed.
        $url2 = $this->MapManifestResourceItem($parenturl, $url);
        if ($url2 !== false)  return $url2 . $fragment;

        // Queue the resource request.
        $key = $url;

        if (!isset($this->ops[$key]))
        {
            $this->ops[$key] = array(
                "type" => "res",
                "status" => "download",
                "depth" => 0,
                "retries" => 3,
                "ext" => $forcedext,
                "waiting" => array(),
                "web" => ($parenturl === false ? new WebBrowser(array("followlocation" => false)) : clone $this->ops[$parenturl]["web"]),
                "options" => array(
                    "pre_retrievewebpage_callback" => [$this,"DisplayURL"]

                )
            );

            $this->ops[$key]["web"]->ProcessAsync($this->helper, $key, NULL, $url, $this->ops[$key]["options"]);
        }

        // Set the waiting status for the parent.
        if ($parenturl !== false)
        {
            if ($this->ops[$parenturl]["status"] === "waiting")  $this->ops[$parenturl]["wait_refs"]++;
            else
            {
                $this->ops[$parenturl]["status"] = "waiting";
                $this->ops[$parenturl]["wait_refs"] = 1;
            }

            $this->ops[$key]["waiting"][] = $parenturl;
        }

        return $url;
    }

    // Locate additional files to import in CSS.  Doesn't implement a state engine.
   public function ProcessCSS($css, $parenturl, $baseurl)
    {
        $result = $css;

        // Strip comments.
        $css = str_replace("<" . "!--", " ", $css);
        $css = str_replace("--" . ">", " ", $css);
        while (($pos = strpos($css, "/*")) !== false)
        {
            $pos2 = strpos($css, "*/", $pos + 2);
            if ($pos2 === false)  $pos2 = strlen($css);
            else  $pos2 += 2;

            $css = substr($css, 0, $pos) . substr($css, $pos2);
        }

        // Alter @import lines.
        $pos = 0;
        while (($pos = stripos($css, "@import", $pos)) !== false)
        {
            $semipos = strpos($css, ";", $pos);
            if ($semipos === false)  break;

            $pos2 = strpos($css, "'", $pos);
            if ($pos2 === false)  $pos2 = strpos($css, "\"", $pos);
            if ($pos2 === false)  break;

            $pos3 = strpos($css, $css[$pos2], $pos2 + 1);
            if ($pos3 === false)  break;

            if ($pos2 < $semipos && $pos3 < $semipos)
            {
                $url = HTTP::ConvertRelativeToAbsoluteURL($baseurl, substr($css, $pos2 + 1, $pos3 - $pos2 - 1));

                $result = str_replace(substr($css, $pos2, $pos3 - $pos2 + 1), $css[$pos2] . $this->PrepareManifestResourceItem($parenturl, ".css", $url) . $css[$pos2], $result);
            }

            $pos = $semipos + 1;
        }

        // Alter url() values.
        $pos = 0;
        while (($pos = stripos($css, "url(", $pos)) !== false)
        {
            $endpos = strpos($css, ")", $pos);
            if ($endpos === false)  break;

            $pos2 = strpos($css, "'", $pos);
            if ($pos2 !== false && $pos2 > $endpos)  $pos2 = false;
            if ($pos2 === false)  $pos2 = strpos($css, "\"", $pos);

            if ($pos2 === false || $pos2 > $endpos)
            {
                $pos2 = $pos + 3;
                $pos3 = $endpos;
            }
            else
            {
                $pos3 = strpos($css, $css[$pos2], $pos2 + 1);
                if ($pos3 === false || $pos3 > $endpos)  $pos3 = $endpos;
            }

            $url = HTTP::ConvertRelativeToAbsoluteURL($baseurl, substr($css, $pos2 + 1, $pos3 - $pos2 - 1));

            $result = str_replace(substr($css, $pos2, $pos3 - $pos2 + 1), $css[$pos2] . $this->PrepareManifestResourceItem($parenturl, false, $url) . $css[$pos3], $result);

            $pos = $endpos + 1;
        }

        return $result;
    }


    public function ProcessContent($key, $final)
    {

        if ($this->opsdata[$key]["httpcode"] >= 400)  return;
        // Process HTML, altering URLs as necessary.
        if ($this->ops[$key]["type"] === "node" && $this->ops[$key]["ext"] === ".html")
        {
            $html = TagFilter::Explode($this->opsdata[$key]["content"], $this->htmloptions);
            $root = $html->Get();

            $urlinfo = HTTP::ExtractURL($this->opsdata[$key]["url"]);

            // Handle images.
            $rows = $root->Find('img[src],img[srcset]');
            foreach ($rows as $row)
            {
                if (isset($row->src))
                {
                    $url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, $row->src);

                    $row->src = $this->PrepareManifestResourceItem($key, false, $url);
                }

                if (isset($row->srcset))
                {
                    $urls = explode(",", $row->srcset);
                    $urls2 = array();
                    foreach ($urls as $url)
                    {
                        $url = trim($url);
                        $pos = strrpos($url, " ");
                        if ($pos !== false)
                        {
                            $url2 = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, trim(substr($url, 0, $pos)));
                            $size = substr($url, $pos + 1);

                            $urls2[] = $this->PrepareManifestResourceItem($key, false, $url2) . " " . $size;
                        }
                    }

                    $row->srcset = implode(", ", $urls2);
                }
            }

            // Handle link tags with hrefs.
            $rows = $root->Find('link[href]');
            foreach ($rows as $row)
            {
                $url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, $row->href);

                $row->href = $this->PrepareManifestResourceItem($key, ((isset($row->rel) && strtolower($row->rel) === "stylesheet") || (isset($row->type) && strtolower($row->type) === "text/css") ? ".css" : false), $url);
            }

            // Handle external Javascript.
            $rows = $root->Find('script[src]');
            foreach ($rows as $row)
            {
                $url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, $row->src);

                $row->src = $this->PrepareManifestResourceItem($key, ".js", $url);
            }

            // Handle style tags.
            $rows = $root->Find('style');
            foreach ($rows as $row)
            {
                $children = $row->Children(true);
                foreach ($children as $child)
                {
                    if ($child->Type() === "content")
                    {
                        $child->Text($this->ProcessCSS($child->Text(), $key, $urlinfo));
                    }
                }
            }

            // Handle inline styles.
            $rows = $root->Find('[style]');
            foreach ($rows as $row)
            {
                $row->style = $this->ProcessCSS($row->style, $key, $urlinfo);
            }

            // Handle anchor tags and iframes.
            $rows = $root->Find('a[href],iframe[src]');
            foreach ($rows as $row)
            {
                $url = HTTP::ConvertRelativeToAbsoluteURL($urlinfo, ($row->Tag() === "iframe" ? $row->src : $row->href));
                $url2 = HTTP::ExtractURL($url);

                // Only follow links on the same domain.
                if (strcasecmp($url2["authority"], $this->initurl2["authority"]) == 0 && ($url2["scheme"] === "http" || $url2["scheme"] === "https"))
                {
                    if ($url2["path"] === "")
                    {
                        $url2["path"] = "/";
                        $url = HTTP::CondenseURL($url2);
                    }

                    $pos = strpos($url, "#");
                    if ($pos === false)  $fragment = "";
                    else
                    {
                        $fragment = substr($url, $pos);
                        $url = substr($url, 0, $pos);
                    }

                    $url2 = $this->MapManifestResourceItem($key, $url);
                    if ($url2 !== false)
                    {
                        if ($row->Tag() === "iframe")  $row->src = $url2 . $fragment;
                        else  $row->href =  $fragment?:$url2;
//						else  $row->href = $url2 . $fragment;
                    }
                    else
                    {
                        if ($row->Tag() === "iframe")  $row->src = $url . $fragment;
                        else  $row->href = $url . $fragment;

                        if ($this->linkdepth === false || $this->ops[$key]["depth"] < $this->linkdepth)
                        {
                            echo "\nanother url $url \n";
                            // Queue up another node.
                            $key2 = $url;

                            if (!isset($this->ops[$key2]))
                            {
                                $this->ops[$key2] = array(
                                    "type" => "node",
                                    "status" => "download",
                                    "depth" => $this->ops[$key]["depth"] + 1,
                                    "retries" => 3,
                                    "ext" => false,
                                    "waiting" => array(),
                                    "web" => clone $this->ops[$key]["web"],
                                    "options" => array(
                                        "pre_retrievewebpage_callback" => [$this,"DisplayURL"]
                                    )
                                );

                                $this->ops[$key]["web"]->ProcessAsync($this->helper, $key2, NULL, $url, $this->ops[$key2]["options"]);
                            }

                            if ($key !== $key2)
                            {
                                if ($this->ops[$key]["status"] === "waiting")  $this->ops[$key]["wait_refs"]++;
                                else
                                {
                                    $this->ops[$key]["status"] = "waiting";
                                    $this->ops[$key]["wait_refs"] = 1;
                                }

                                $this->ops[$key2]["waiting"][] = $key;
                            }
                        }
                    }
                }
            }

            // Mix down the content back into HTML.
            if ($final)  $this->opsdata[$key]["content"] = $root->GetOuterHTML();
        }

        // Process CSS, altering URLs as necessary.
        if ($this->ops[$key]["ext"] === ".css")
        {
            $urlinfo = HTTP::ExtractURL($this->opsdata[$key]["url"]);

            $result = $this->ProcessCSS($this->opsdata[$key]["content"], $key, $urlinfo);

            if ($final)  $this->opsdata[$key]["content"] = $result;
        }
    }

     // Provides some basic feedback prior to retrieving each URL.
    public function DisplayURL(&$state)
    {


        echo "[" . number_format(count($this->ops), 0) . " ops] Retrieving '" . $state["url"] . "'...\n";

        return true;
    }
}



