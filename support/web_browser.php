<?php
	// CubicleSoft PHP web browser state emulation class.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	// Requires the CubicleSoft PHP HTTP class for HTTP/HTTPS.
	class WebBrowser
	{
		private $data, $html;

		public function __construct($prevstate = array())
		{
			if (!class_exists("HTTP", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/http.php";

			$this->ResetState();
			$this->SetState($prevstate);
			$this->html = false;
		}

		public function ResetState()
		{
			$this->data = array(
				"allowedprotocols" => array("http" => true, "https" => true),
				"allowedredirprotocols" => array("http" => true, "https" => true),
				"cookies" => array(),
				"referer" => "",
				"autoreferer" => true,
				"useragent" => "firefox",
				"followlocation" => true,
				"maxfollow" => 20,
				"extractforms" => false,
				"httpopts" => array(),
			);
		}

		public function SetState($options = array())
		{
			$this->data = array_merge($this->data, $options);
		}

		public function GetState()
		{
			return $this->data;
		}

		public function ProcessState(&$state)
		{
			while ($state["state"] !== "done")
			{
				switch ($state["state"])
				{
					case "initialize":
					{
						if (!isset($this->data["allowedprotocols"][$state["urlinfo"]["scheme"]]) || !$this->data["allowedprotocols"][$state["urlinfo"]["scheme"]])
						{
							return array("success" => false, "error" => self::WBTranslate("Protocol '%s' is not allowed in '%s'.", $state["urlinfo"]["scheme"], $state["url"]), "errorcode" => "allowed_protocols");
						}

						$filename = HTTP::ExtractFilename($state["urlinfo"]["path"]);
						$pos = strrpos($filename, ".");
						$fileext = ($pos !== false ? strtolower(substr($filename, $pos + 1)) : "");

						// Set up some standard headers.
						$headers = array();
						$profile = strtolower($state["profile"]);
						$tempprofile = explode("-", $profile);
						if (count($tempprofile) == 2)
						{
							$profile = $tempprofile[0];
							$fileext = $tempprofile[1];
						}
						if (substr($profile, 0, 2) == "ie" || ($profile == "auto" && substr($this->data["useragent"], 0, 2) == "ie"))
						{
							if ($fileext == "css")  $headers["Accept"] = "text/css";
							else if ($fileext == "png" || $fileext == "jpg" || $fileext == "jpeg" || $fileext == "gif" || $fileext == "svg")  $headers["Accept"] = "image/png, image/svg+xml, image/*;q=0.8, */*;q=0.5";
							else if ($fileext == "js")  $headers["Accept"] = "application/javascript, */*;q=0.8";
							else if ($this->data["referer"] != "" || $fileext == "" || $fileext == "html" || $fileext == "xhtml" || $fileext == "xml")  $headers["Accept"] = "text/html, application/xhtml+xml, */*";
							else  $headers["Accept"] = "*/*";

							$headers["Accept-Language"] = "en-US";
							$headers["User-Agent"] = HTTP::GetUserAgent(substr($profile, 0, 2) == "ie" ? $profile : $this->data["useragent"]);
						}
						else if ($profile == "firefox" || ($profile == "auto" && $this->data["useragent"] == "firefox"))
						{
							if ($fileext == "css")  $headers["Accept"] = "text/css,*/*;q=0.1";
							else if ($fileext == "png" || $fileext == "jpg" || $fileext == "jpeg" || $fileext == "gif" || $fileext == "svg")  $headers["Accept"] = "image/png,image/*;q=0.8,*/*;q=0.5";
							else if ($fileext == "js")  $headers["Accept"] = "*/*";
							else  $headers["Accept"] = "text/html, application/xhtml+xml, */*";

							$headers["Accept-Language"] = "en-us,en;q=0.5";
							$headers["Cache-Control"] = "max-age=0";
							$headers["User-Agent"] = HTTP::GetUserAgent("firefox");
						}
						else if ($profile == "opera" || ($profile == "auto" && $this->data["useragent"] == "opera"))
						{
							// Opera has the right idea:  Just send the same thing regardless of the request type.
							$headers["Accept"] = "text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/webp, image/jpeg, image/gif, image/x-xbitmap, */*;q=0.1";
							$headers["Accept-Language"] = "en-US,en;q=0.9";
							$headers["Cache-Control"] = "no-cache";
							$headers["User-Agent"] = HTTP::GetUserAgent("opera");
						}
						else if ($profile == "safari" || $profile == "chrome" || ($profile == "auto" && ($this->data["useragent"] == "safari" || $this->data["useragent"] == "chrome")))
						{
							if ($fileext == "css")  $headers["Accept"] = "text/css,*/*;q=0.1";
							else if ($fileext == "png" || $fileext == "jpg" || $fileext == "jpeg" || $fileext == "gif" || $fileext == "svg" || $fileext == "js")  $headers["Accept"] = "*/*";
							else  $headers["Accept"] = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";

							$headers["Accept-Charset"] = "ISO-8859-1,utf-8;q=0.7,*;q=0.3";
							$headers["Accept-Language"] = "en-US,en;q=0.8";
							$headers["User-Agent"] = HTTP::GetUserAgent($profile == "safari" || $profile == "chrome" ? $profile : $this->data["useragent"]);
						}

						if ($this->data["referer"] != "")  $headers["Referer"] = $this->data["referer"];

						// Generate the final headers array.
						$headers = array_merge($headers, $state["httpopts"]["headers"], $state["tempoptions"]["headers"]);

						// Calculate the host and reverse host and remove port information.
						$host = (isset($headers["Host"]) ? $headers["Host"] : $state["urlinfo"]["host"]);
						$pos = strpos($host, "]");
						if (substr($host, 0, 1) == "[" && $pos !== false)
						{
							$host = substr($host, 0, $pos + 1);
						}
						else
						{
							$pos = strpos($host, ":");
							if ($pos !== false)  $host = substr($host, 0, $pos);
						}
						$dothost = $host;
						$dothost = strtolower($dothost);
						if (substr($dothost, 0, 1) != ".")  $dothost = "." . $dothost;
						$state["dothost"] = $dothost;

						// Append cookies and delete old, invalid cookies.
						$secure = ($state["urlinfo"]["scheme"] == "https");
						$cookiepath = $state["urlinfo"]["path"];
						if ($cookiepath == "")  $cookiepath = "/";
						$pos = strrpos($cookiepath, "/");
						if ($pos !== false)  $cookiepath = substr($cookiepath, 0, $pos + 1);
						$state["cookiepath"] = $cookiepath;
						$cookies = array();
						foreach ($this->data["cookies"] as $domain => $paths)
						{
							if (strlen($dothost) >= strlen($domain) && substr($dothost, -strlen($domain)) === $domain)
							{
								foreach ($paths as $path => $cookies2)
								{
									if (substr($cookiepath, 0, strlen($path)) == $path)
									{
										foreach ($cookies2 as $num => $info)
										{
											if (isset($info["expires_ts"]) && $this->GetExpiresTimestamp($info["expires_ts"]) < time())  unset($this->data["cookies"][$domain][$path][$num]);
											else if ($secure || !isset($info["secure"]))  $cookies[$info["name"]] = $info["value"];
										}

										if (!count($this->data["cookies"][$domain][$path]))  unset($this->data["cookies"][$domain][$path]);
									}
								}

								if (!count($this->data["cookies"][$domain]))  unset($this->data["cookies"][$domain]);
							}
						}

						$cookies2 = array();
						foreach ($cookies as $name => $value)  $cookies2[] = rawurlencode($name) . "=" . rawurlencode($value);
						$headers["Cookie"] = implode("; ", $cookies2);
						if ($headers["Cookie"] == "")  unset($headers["Cookie"]);

						// Generate the final options array.
						$state["options"] = array_merge($state["httpopts"], $state["tempoptions"]);
						$state["options"]["headers"] = $headers;
						if ($state["timeout"] !== false)  $state["options"]["timeout"] = HTTP::GetTimeLeft($state["startts"], $state["timeout"]);

						// Let a callback handle any additional state changes.
						if (isset($state["options"]["pre_retrievewebpage_callback"]) && is_callable($state["options"]["pre_retrievewebpage_callback"]) && !call_user_func_array($state["options"]["pre_retrievewebpage_callback"], array(&$state)))
						{
							return array("success" => false, "error" => self::WBTranslate("Pre-RetrieveWebpage callback returned with a failure condition for '%s'.", $state["url"]), "errorcode" => "pre_retrievewebpage_callback");
						}

						// Process the request.
						$result = HTTP::RetrieveWebpage($state["url"], $state["options"]);
						$result["url"] = $state["url"];
						unset($state["options"]["files"]);
						unset($state["options"]["body"]);
						$result["options"] = $state["options"];
						$result["firstreqts"] = $state["startts"];
						$result["numredirects"] = $state["numredirects"];
						$result["redirectts"] = $state["redirectts"];
						if (isset($result["rawsendsize"]))  $state["totalrawsendsize"] += $result["rawsendsize"];
						$result["totalrawsendsize"] = $state["totalrawsendsize"];
						if (!$result["success"])  return array("success" => false, "error" => self::WBTranslate("Unable to retrieve content.  %s", $result["error"]), "info" => $result, "state" => $state, "errorcode" => "retrievewebpage");

						if (isset($state["options"]["async"]) && $state["options"]["async"])
						{
							$state["async"] = true;
							$state["httpstate"] = $result["state"];

							$state["state"] = "process_async";
						}
						else
						{
							$state["result"] = $result;

							$state["state"] = "post_retrieval";
						}

						break;
					}
					case "process_async":
					{
						// Run a cycle of the HTTP state processor.
						$result = HTTP::ProcessState($state["httpstate"]);
						if (!$result["success"])  return $result;

						$result["url"] = $state["url"];
						$result["options"] = $state["options"];
						unset($result["options"]["files"]);
						unset($result["options"]["body"]);
						$result["firstreqts"] = $state["startts"];
						$result["numredirects"] = $state["numredirects"];
						$result["redirectts"] = $state["redirectts"];
						if (isset($result["rawsendsize"]))  $state["totalrawsendsize"] += $result["rawsendsize"];
						$result["totalrawsendsize"] = $state["totalrawsendsize"];

						$state["httpstate"] = false;
						$state["result"] = $result;

						$state["state"] = "post_retrieval";

						break;
					}
					case "post_retrieval":
					{
						// Set up structures for another round.
						if ($this->data["autoreferer"])  $this->data["referer"] = $state["url"];
						if (isset($state["result"]["headers"]["Location"]) && $this->data["followlocation"])
						{
							$state["redirectts"] = microtime(true);

							unset($state["tempoptions"]["method"]);
							unset($state["tempoptions"]["write_body_callback"]);
							unset($state["tempoptions"]["body"]);
							unset($state["tempoptions"]["postvars"]);
							unset($state["tempoptions"]["files"]);

							$state["tempoptions"]["headers"]["Referer"] = $state["url"];
							$state["url"] = $state["result"]["headers"]["Location"][0];

							// Generate an absolute URL.
							if ($this->data["referer"] != "")  $state["url"] = HTTP::ConvertRelativeToAbsoluteURL($this->data["referer"], $state["url"]);

							$urlinfo2 = HTTP::ExtractURL($state["url"]);

							if (!isset($this->data["allowedredirprotocols"][$urlinfo2["scheme"]]) || !$this->data["allowedredirprotocols"][$urlinfo2["scheme"]])
							{
								return array("success" => false, "error" => self::WBTranslate("Protocol '%s' is not allowed.  Server attempted to redirect to '%s'.", $urlinfo2["scheme"], $state["url"]), "info" => $state["result"], "errorcode" => "allowed_redir_protocols");
							}

							if ($urlinfo2["host"] != $state["urlinfo"]["host"])
							{
								unset($state["tempoptions"]["headers"]["Host"]);
								unset($state["httpopts"]["headers"]["Host"]);
							}

							$state["urlinfo"] = $urlinfo2;
							$state["numredirects"]++;
						}

						// Handle any 'Set-Cookie' headers.
						if (isset($state["result"]["headers"]["Set-Cookie"]))
						{
							foreach ($state["result"]["headers"]["Set-Cookie"] as $cookie)
							{
								$items = explode("; ", $cookie);
								$item = trim(array_shift($items));
								if ($item != "")
								{
									$cookie2 = array();
									$pos = strpos($item, "=");
									if ($pos === false)
									{
										$cookie2["name"] = urldecode($item);
										$cookie2["value"] = "";
									}
									else
									{
										$cookie2["name"] = urldecode(substr($item, 0, $pos));
										$cookie2["value"] = urldecode(substr($item, $pos + 1));
									}

									$cookie = array();
									foreach ($items as $item)
									{
										$item = trim($item);
										if ($item != "")
										{
											$pos = strpos($item, "=");
											if ($pos === false)  $cookie[strtolower(trim(urldecode($item)))] = "";
											else  $cookie[strtolower(trim(urldecode(substr($item, 0, $pos))))] = urldecode(substr($item, $pos + 1));
										}
									}
									$cookie = array_merge($cookie, $cookie2);

									if (isset($cookie["expires"]))
									{
										$ts = HTTP::GetDateTimestamp($cookie["expires"]);
										$cookie["expires_ts"] = gmdate("Y-m-d H:i:s", ($ts === false ? time() - 24 * 60 * 60 : $ts));
									}
									else if (isset($cookie["max-age"]))
									{
										$cookie["expires_ts"] = gmdate("Y-m-d H:i:s", time() + (int)$cookie["max-age"]);
									}
									else
									{
										unset($cookie["expires_ts"]);
									}

									if (!isset($cookie["domain"]))  $cookie["domain"] = $state["dothost"];
									if (!isset($cookie["path"]))  $cookie["path"] = $state["cookiepath"];

									$this->SetCookie($cookie);
								}
							}
						}

						if ($state["numfollow"] > 0)  $state["numfollow"]--;

						// If this is a redirect, handle it by starting over.
						if (isset($state["result"]["headers"]["Location"]) && $this->data["followlocation"] && $state["numfollow"])
						{
							$state["result"] = false;

							$state["state"] = "initialize";
						}
						else
						{
							$state["result"]["numredirects"] = $state["numredirects"];
							$state["result"]["redirectts"] = $state["redirectts"];

							// Extract the forms from the page in a parsed format.
							// Call WebBrowser::GenerateFormRequest() to prepare an actual request for Process().
							if ($this->data["extractforms"])  $state["result"]["forms"] = $this->ExtractForms($state["result"]["url"], $state["result"]["body"], (isset($state["tempoptions"]["extractforms_hint"]) ? $state["tempoptions"]["extractforms_hint"] : false));

							$state["state"] = "done";
						}

						break;
					}
				}
			}

			return $state["result"];
		}

		public function Process($url, $profile = "auto", $tempoptions = array())
		{
			$startts = microtime(true);
			$redirectts = $startts;
			if (isset($tempoptions["timeout"]))  $timeout = $tempoptions["timeout"];
			else if (isset($this->data["httpopts"]["timeout"]))  $timeout = $this->data["httpopts"]["timeout"];
			else  $timeout = false;

			// Deal with possible application hanging issues.
			if (isset($tempoptions["streamtimeout"]))  $streamtimeout = $tempoptions["streamtimeout"];
			else if (isset($this->data["httpopts"]["streamtimeout"]))  $streamtimeout = $this->data["httpopts"]["streamtimeout"];
			else  $streamtimeout = 300;
			$tempoptions["streamtimeout"] = $streamtimeout;

			if (!isset($this->data["httpopts"]["headers"]))  $this->data["httpopts"]["headers"] = array();
			$this->data["httpopts"]["headers"] = HTTP::NormalizeHeaders($this->data["httpopts"]["headers"]);
			unset($this->data["httpopts"]["method"]);
			unset($this->data["httpopts"]["write_body_callback"]);
			unset($this->data["httpopts"]["body"]);
			unset($this->data["httpopts"]["postvars"]);
			unset($this->data["httpopts"]["files"]);

			$httpopts = $this->data["httpopts"];
			$numfollow = $this->data["maxfollow"];
			$numredirects = 0;
			$totalrawsendsize = 0;

			if (!isset($tempoptions["headers"]))  $tempoptions["headers"] = array();
			$tempoptions["headers"] = HTTP::NormalizeHeaders($tempoptions["headers"]);
			if (isset($tempoptions["headers"]["Referer"]))  $this->data["referer"] = $tempoptions["headers"]["Referer"];

			// If a referrer is specified, use it to generate an absolute URL.
			if ($this->data["referer"] != "")  $url = HTTP::ConvertRelativeToAbsoluteURL($this->data["referer"], $url);

			$urlinfo = HTTP::ExtractURL($url);

			// Initialize the process state array.
			$state = array(
				"async" => false,
				"startts" => $startts,
				"redirectts" => $redirectts,
				"timeout" => $timeout,
				"tempoptions" => $tempoptions,
				"httpopts" => $httpopts,
				"numfollow" => $numfollow,
				"numredirects" => $numredirects,
				"totalrawsendsize" => $totalrawsendsize,
				"profile" => $profile,
				"url" => $url,
				"urlinfo" => $urlinfo,

				"state" => "initialize",
				"httpstate" => false,
				"result" => false,
			);

			// Run at least one state cycle to properly initialize the state array.
			$result = $this->ProcessState($state);

			// Return the state for async calls.  Caller must call ProcessState().
			if ($state["async"])  return array("success" => true, "state" => $state);

			return $result;
		}

		// Implements the correct MultiAsyncHelper responses for WebBrowser instances.
		public function ProcessAsync__Handler($mode, &$data, $key, &$info)
		{
			switch ($mode)
			{
				case "init":
				{
					if ($info["init"])  $data = $info["keep"];
					else
					{
						$info["result"] = $this->Process($info["url"], $info["profile"], $info["tempoptions"]);
						if (!$info["result"]["success"])
						{
							$info["keep"] = false;

							if (is_callable($info["callback"]))  call_user_func_array($info["callback"], array($key, $info["url"], $info["result"]));
						}
						else
						{
							$info["state"] = $info["result"]["state"];

							// Move to the live queue.
							$data = true;
						}
					}

					break;
				}
				case "update":
				case "read":
				case "write":
				{
					if ($info["keep"])
					{
						$info["result"] = $this->ProcessState($info["state"]);
						if ($info["result"]["success"] || $info["result"]["errorcode"] !== "no_data")  $info["keep"] = false;

						if (is_callable($info["callback"]))  call_user_func_array($info["callback"], array($key, $info["url"], $info["result"]));

						if ($mode === "update")  $data = $info["keep"];
					}

					break;
				}
				case "readfps":
				{
					if ($info["state"]["httpstate"] !== false && HTTP::WantRead($info["state"]["httpstate"]))  $data[$key] = $info["state"]["httpstate"]["fp"];

					break;
				}
				case "writefps":
				{
					if ($info["state"]["httpstate"] !== false && HTTP::WantWrite($info["state"]["httpstate"]))  $data[$key] = $info["state"]["httpstate"]["fp"];

					break;
				}
				case "cleanup":
				{
					// When true, caller is removing.  Otherwise, detaching from the queue.
					if ($data === true)
					{
						if (isset($info["state"]))
						{
							if ($info["state"]["httpstate"] !== false)  HTTP::ForceClose($info["state"]["httpstate"]);

							unset($info["state"]);
						}

						$info["keep"] = false;
					}

					break;
				}
			}
		}

		public function ProcessAsync($helper, $key, $callback, $url, $profile = "auto", $tempoptions = array())
		{
			$tempoptions["async"] = true;

			$info = array(
				"init" => false,
				"keep" => true,
				"callback" => $callback,
				"url" => $url,
				"profile" => $profile,
				"tempoptions" => $tempoptions,
				"result" => false
			);

			$helper->Set($key, $info, array($this, "ProcessAsync__Handler"));

			return array("success" => true);
		}

		public function ExtractForms($baseurl, $data, $hint = false)
		{
			$result = array();

			$lasthint = "";
			$hintmap = array();
			if ($this->html === false)
			{
				if (!class_exists("simple_html_dom", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/simple_html_dom.php";

				$this->html = new simple_html_dom();
			}
			$this->html->load($data);
			$rows = $this->html->find("label[for]");
			foreach ($rows as $row)
			{
				$hintmap[trim($row->for)] = trim($row->plaintext);
			}
			$html5rows = $this->html->find("input[form],textarea[form],select[form],button[form],datalist[id]" . ($hint !== false ? "," . $hint : ""));
			$rows = $this->html->find("form");
			foreach ($rows as $row)
			{
				$info = array();
				if (isset($row->id))  $info["id"] = trim($row->id);
				if (isset($row->name))  $info["name"] = (string)$row->name;
				$info["action"] = (isset($row->action) ? HTTP::ConvertRelativeToAbsoluteURL($baseurl, (string)$row->action) : $baseurl);
				$info["method"] = (isset($row->method) && strtolower(trim($row->method)) == "post" ? "post" : "get");
				if ($info["method"] == "post")  $info["enctype"] = (isset($row->enctype) ? strtolower($row->enctype) : "application/x-www-form-urlencoded");
				if (isset($row->{"accept-charset"}))  $info["accept-charset"] = (string)$row->{"accept-charset"};

				$fields = array();
				$rows2 = $row->find("input,textarea,select,button" . ($hint !== false ? "," . $hint : ""));
				foreach ($rows2 as $row2)
				{
					if (!isset($row2->form))
					{
						if (isset($row2->id) && $row2->id != "" && isset($hintmap[trim($row2->id)]))  $lasthint = $hintmap[trim($row2->id)];

						$this->ExtractFieldFromDOM($fields, $row2, $lasthint);
					}
				}

				// Handle HTML5.
				if (isset($info["id"]) && $info["id"] != "")
				{
					foreach ($html5rows as $row2)
					{
						if (strpos(" " . $info["id"] . " ", " " . $row2->form . " ") !== false)
						{
							if (isset($hintmap[$info["id"]]))  $lasthint = $hintmap[$info["id"]];

							$this->ExtractFieldFromDOM($fields, $row2, $lasthint);
						}
					}
				}

				$form = new WebBrowserForm();
				$form->info = $info;
				$form->fields = $fields;
				$result[] = $form;
			}

			return $result;
		}

		private function ExtractFieldFromDOM(&$fields, $row, &$lasthint)
		{
			switch ($row->tag)
			{
				case "input":
				{
					if (!isset($row->name) && ($row->type === "submit" || $row->type === "image"))  $row->name = "";

					if (isset($row->name) && is_string($row->name))
					{
						$field = array(
							"id" => (isset($row->id) ? (string)$row->id : false),
							"type" => "input." . (isset($row->type) ? strtolower($row->type) : "text"),
							"name" => $row->name,
							"value" => (isset($row->value) ? html_entity_decode($row->value, ENT_COMPAT, "UTF-8") : "")
						);
						if ($field["type"] == "input.radio" || $field["type"] == "input.checkbox")
						{
							$field["checked"] = (isset($row->checked));

							if ($field["value"] === "")  $field["value"] = "on";
						}

						if (isset($row->placeholder))  $field["hint"] = trim($row->placeholder);
						else if ($field["type"] == "input.submit" || $field["type"] == "input.image")  $field["hint"] = $field["type"] . "|" . $field["value"];
						else if ($lasthint !== "")  $field["hint"] = $lasthint;

						$fields[] = $field;

						$lasthint = "";
					}

					break;
				}
				case "textarea":
				{
					if (isset($row->name) && is_string($row->name))
					{
						$field = array(
							"id" => (isset($row->id) ? (string)$row->id : false),
							"type" => "textarea",
							"name" => $row->name,
							"value" => html_entity_decode($row->innertext, ENT_COMPAT, "UTF-8")
						);

						if (isset($row->placeholder))  $field["hint"] = trim($row->placeholder);
						else if ($lasthint !== "")  $field["hint"] = $lasthint;

						$fields[] = $field;

						$lasthint = "";
					}

					break;
				}
				case "select":
				{
					if (isset($row->name) && is_string($row->name))
					{
						if (isset($row->multiple))
						{
							// Change the type into multiple checkboxes.
							$rows = $row->find("option");
							foreach ($rows as $row2)
							{
								$field = array(
									"id" => (isset($row->id) ? (string)$row->id : false),
									"type" => "input.checkbox",
									"name" => $row->name,
									"value" => (isset($row2->value) ? html_entity_decode($row2->value, ENT_COMPAT, "UTF-8") : ""),
									"display" => (string)$row2->innertext
								);
								if ($lasthint !== "")  $field["hint"] = $lasthint;

								$fields[] = $field;
							}
						}
						else
						{
							$val = false;
							$options = array();
							$rows = $row->find("option");
							foreach ($rows as $row2)
							{
								$options[$row2->value] = (string)$row2->innertext;

								if ($val === false && isset($row2->selected))  $val = html_entity_decode($row2->value, ENT_COMPAT, "UTF-8");
							}
							if ($val === false && count($options))
							{
								$val = array_keys($options);
								$val = $val[0];
							}
							if ($val === false)  $val = "";

							$field = array(
								"id" => (isset($row->id) ? (string)$row->id : false),
								"type" => "select",
								"name" => $row->name,
								"value" => $val,
								"options" => $options
							);
							if ($lasthint !== "")  $field["hint"] = $lasthint;

							$fields[] = $field;
						}

						$lasthint = "";
					}

					break;
				}
				case "button":
				{
					if (isset($row->name) && is_string($row->name))
					{
						$field = array(
							"id" => (isset($row->id) ? (string)$row->id : false),
							"type" => "button." . (isset($row->type) ? strtolower($row->type) : "submit"),
							"name" => $row->name,
							"value" => (isset($row->value) ? html_entity_decode($row->value, ENT_COMPAT, "UTF-8") : "")
						);
						$field["hint"] = (trim($row->plaintext) !== "" ? trim($row->plaintext) : "button|" . $field["value"]);

						$fields[] = $field;

						$lasthint = "";
					}

					break;
				}
				case "datalist":
				{
					// Do nothing since browsers don't actually enforce this tag's values.

					break;
				}
				default:
				{
					// Hint for the next element.
					$lasthint = (string)$row->plaintext;

					break;
				}
			}
		}

		public static function InteractiveFormFill($forms, $showselected = false)
		{
			if (!is_array($forms))  $forms = array($forms);

			if (!count($forms))  return false;

			if (count($forms) == 1)  $form = reset($forms);
			else
			{
				echo self::WBTranslate("There are multiple forms available to fill out:\n");
				foreach ($forms as $num => $form)
				{
					echo self::WBTranslate("\t%d:\n", $num + 1);
					foreach ($form->info as $key => $val)  echo self::WBTranslate("\t\t%s:  %s\n", $key, $val);
					echo self::WBTranslate("\t\tfields:  %d\n", count($form->GetVisibleFields(false)));
					echo self::WBTranslate("\t\tbuttons:  %d\n", count($form->GetVisibleFields(true)) - count($form->GetVisibleFields(false)));
					echo "\n";
				}

				do
				{
					echo self::WBTranslate("Select:  ");

					$num = (int)trim(fgets(STDIN)) - 1;
				} while (!isset($forms[$num]));

				$form = $forms[$num];
			}

			if ($showselected)
			{
				echo self::WBTranslate("Selected form:\n");
				foreach ($form->info as $key => $val)  echo self::WBTranslate("\t%s:  %s\n", $key, $val);
				echo "\n";
			}

			if (count($form->GetVisibleFields(false)))
			{
				echo self::WBTranslate("Select form fields by field number to edit a field.  When ready to submit the form, leave 'Field number' empty.\n\n");

				do
				{
					echo self::WBTranslate("Editable form fields:\n");
					foreach ($form->fields as $num => $field)
					{
						if ($field["type"] == "input.hidden" || $field["type"] == "input.submit" || $field["type"] == "input.image" || $field["type"] == "input.button" || substr($field["type"], 0, 7) == "button.")  continue;

						echo self::WBTranslate("\t%d:  %s - %s\n", $num + 1, $field["name"], (is_array($field["value"]) ? json_encode($field["value"], JSON_PRETTY_PRINT) : $field["value"]) . (($field["type"] == "input.radio" || $field["type"] == "input.checkbox") ? ($field["checked"] ? self::WBTranslate(" [Y]") : self::WBTranslate(" [N]")) : "") . (isset($field["hint"]) && $field["hint"] !== "" ? " [" . $field["hint"] . "]" : ""));
					}
					echo "\n";

					do
					{
						echo self::WBTranslate("Field number:  ");

						$num = trim(fgets(STDIN));
						if ($num === "")  break;

						$num = (int)$num - 1;
					} while (!isset($form->fields[$num]) || $form->fields[$num]["type"] == "input.hidden" || $form->fields[$num]["type"] == "input.submit" || $form->fields[$num]["type"] == "input.image" || $form->fields[$num]["type"] == "input.button" || substr($form->fields[$num]["type"], 0, 7) == "button.");

					if ($num === "")
					{
						echo "\n";

						break;
					}

					$field = $form->fields[$num];
					$prefix = (isset($field["hint"]) && $field["hint"] !== "" ? $field["hint"] . " | " : "") . $field["name"];

					if ($field["type"] == "select")
					{
						echo self::WBTranslate("[%s] Options:\n", $prefix);
						foreach ($field["options"] as $key => $val)
						{
							echo self::WBTranslate("\t%s:  %s\n");
						}

						do
						{
							echo self::WBTranslate("[%s] Select:  ", $prefix);

							$select = rtrim(fgets(STDIN));
						} while (!isset($field["options"][$select]));

						$form->fields[$num]["value"] = $select;
					}
					else if ($field["type"] == "input.radio")
					{
						$form->SetFormValue($field["name"], $field["value"], true, "input.radio");
					}
					else if ($field["type"] == "input.checkbox")
					{
						$form->fields[$num]["checked"] = !$field["checked"];
					}
					else if ($field["type"] == "input.file")
					{
						do
						{
							echo self::WBTranslate("[%s] Filename:  ", $prefix);

							$filename = rtrim(fgets(STDIN));
						} while ($filename !== "" && !file_exists($filename));

						if ($filename === "")  $form->fields[$num]["value"] = "";
						else
						{
							$form->fields[$num]["value"] = array(
								"filename" => $filename,
								"type" => "application/octet-stream",
								"datafile" => $filename
							);
						}
					}
					else
					{
						echo self::WBTranslate("[%s] New value:  ", $prefix);

						$form->fields[$num]["value"] = rtrim(fgets(STDIN));
					}

					echo "\n";

				} while (1);
			}

			$submitoptions = array(array("name" => self::WBTranslate("Default action"), "value" => self::WBTranslate("Might not work"), "hint" => "Default action"));
			foreach ($form->fields as $num => $field)
			{
				if ($field["type"] != "input.submit" && $field["type"] != "input.image" && $field["type"] != "input.button" && $field["type"] != "button.submit")  continue;

				$submitoptions[] = $field;
			}

			if (count($submitoptions) <= 2)  $num = count($submitoptions) - 1;
			else
			{
				echo self::WBTranslate("Available submit buttons:\n");
				foreach ($submitoptions as $num => $field)
				{
					echo self::WBTranslate("\t%d:  %s - %s\n", $num, $field["name"], $field["value"] . (isset($field["hint"]) && $field["hint"] !== "" ? " [" . $field["hint"] . "]" : ""));
				}
				echo "\n";

				do
				{
					echo self::WBTranslate("Select:  ");

					$num = (int)fgets(STDIN);
				} while (!isset($submitoptions[$num]));

				echo "\n";
			}

			$result = $form->GenerateFormRequest(($num ? $submitoptions[$num]["name"] : false), ($num ? $submitoptions[$num]["value"] : false));

			return $result;
		}

		public function GetCookies()
		{
			return $this->data["cookies"];
		}

		public function SetCookie($cookie)
		{
			if (!isset($cookie["domain"]) || !isset($cookie["path"]) || !isset($cookie["name"]) || !isset($cookie["value"]))  return array("success" => false, "error" => self::WBTranslate("SetCookie() requires 'domain', 'path', 'name', and 'value' to be options."), "errorcode" => "missing_information");

			$cookie["domain"] = strtolower($cookie["domain"]);
			if (substr($cookie["domain"], 0, 1) != ".")  $cookie["domain"] = "." . $cookie["domain"];

			$cookie["path"] = str_replace("\\", "/", $cookie["path"]);
			if (substr($cookie["path"], -1) != "/")  $cookie["path"] = "/";

			if (!isset($this->data["cookies"][$cookie["domain"]]))  $this->data["cookies"][$cookie["domain"]] = array();
			if (!isset($this->data["cookies"][$cookie["domain"]][$cookie["path"]]))  $this->data["cookies"][$cookie["domain"]][$cookie["path"]] = array();
			$this->data["cookies"][$cookie["domain"]][$cookie["path"]][] = $cookie;

			return array("success" => true);
		}

		// Simulates closing a web browser.
		public function DeleteSessionCookies()
		{
			foreach ($this->data["cookies"] as $domain => $paths)
			{
				foreach ($paths as $path => $cookies)
				{
					foreach ($cookies as $num => $info)
					{
						if (!isset($info["expires_ts"]))  unset($this->data["cookies"][$domain][$path][$num]);
					}

					if (!count($this->data["cookies"][$domain][$path]))  unset($this->data["cookies"][$domain][$path]);
				}

				if (!count($this->data["cookies"][$domain]))  unset($this->data["cookies"][$domain]);
			}
		}

		public function DeleteCookies($domainpattern, $pathpattern, $namepattern)
		{
			foreach ($this->data["cookies"] as $domain => $paths)
			{
				if ($domainpattern == "" || substr($domain, -strlen($domainpattern)) == $domainpattern)
				{
					foreach ($paths as $path => $cookies)
					{
						if ($pathpattern == "" || substr($path, 0, strlen($pathpattern)) == $pathpattern)
						{
							foreach ($cookies as $num => $info)
							{
								if ($namepattern == "" || strpos($info["name"], $namepattern) !== false)  unset($this->data["cookies"][$domain][$path][$num]);
							}

							if (!count($this->data["cookies"][$domain][$path]))  unset($this->data["cookies"][$domain][$path]);
						}
					}

					if (!count($this->data["cookies"][$domain]))  unset($this->data["cookies"][$domain]);
				}
			}
		}

		private function GetExpiresTimestamp($ts)
		{
			$year = (int)substr($ts, 0, 4);
			$month = (int)substr($ts, 5, 2);
			$day = (int)substr($ts, 8, 2);
			$hour = (int)substr($ts, 11, 2);
			$min = (int)substr($ts, 14, 2);
			$sec = (int)substr($ts, 17, 2);

			return gmmktime($hour, $min, $sec, $month, $day, $year);
		}

		public static function WBTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}

	class WebBrowserForm
	{
		public $info, $fields;

		public function __construct()
		{
			$this->info = array();
			$this->fields = array();
		}

		public function FindFormFields($name = false, $value = false, $type = false)
		{
			$fields = array();
			foreach ($this->fields as $num => $field)
			{
				if (($type === false || $field["type"] === $type) && ($name === false || $field["name"] === $name) && ($value === false || $field["value"] === $value))
				{
					$fields[] = $field;
				}
			}

			return $fields;
		}

		public function GetHintMap()
		{
			$result = array();
			foreach ($this->fields as $num => $field)
			{
				if (isset($field["hint"]))  $result[$field["hint"]] = $field["name"];
			}

			return $result;
		}

		public function GetVisibleFields($submit)
		{
			$result = array();
			foreach ($this->fields as $num => $field)
			{
				if ($field["type"] == "input.hidden" || (!$submit && ($field["type"] == "input.submit" || $field["type"] == "input.image" || $field["type"] == "input.button" || substr($field["type"], 0, 7) == "button.")))  continue;

				$result[$num] = $field;
			}

			return $result;
		}

		public function GetFormValue($name, $checkval = false, $type = false)
		{
			$val = false;
			foreach ($this->fields as $field)
			{
				if (($type === false || $field["type"] === $type) && $field["name"] === $name)
				{
					if (is_string($checkval))
					{
						if ($checkval === $field["value"])
						{
							if ($field["type"] == "input.radio" || $field["type"] == "input.checkbox")  $val = $field["checked"];
							else  $val = $field["value"];
						}
					}
					else if (($field["type"] != "input.radio" && $field["type"] != "input.checkbox") || $field["checked"])
					{
						$val = $field["value"];
					}
				}
			}

			return $val;
		}

		public function SetFormValue($name, $value, $checked = false, $type = false, $create = false)
		{
			$result = false;
			foreach ($this->fields as $num => $field)
			{
				if (($type === false || $field["type"] === $type) && $field["name"] === $name)
				{
					if ($field["type"] == "input.radio")
					{
						$this->fields[$num]["checked"] = ($field["value"] === $value ? $checked : false);
						$result = true;
					}
					else if ($field["type"] == "input.checkbox")
					{
						if ($field["value"] === $value)  $this->fields[$num]["checked"] = $checked;
						$result = true;
					}
					else if ($field["type"] != "select" || !isset($field["options"]) || isset($field["options"][$value]))
					{
						$this->fields[$num]["value"] = $value;
						$result = true;
					}
				}
			}

			// Add the field if it doesn't exist.
			if (!$result && $create)
			{
				$this->fields[] = array(
					"id" => false,
					"type" => ($type !== false ? $type : "input.text"),
					"name" => $name,
					"value" => $value,
					"checked" => $checked
				);
			}

			return $result;
		}

		public function GenerateFormRequest($submitname = false, $submitvalue = false)
		{
			$method = $this->info["method"];
			$fields = array();
			$files = array();
			foreach ($this->fields as $field)
			{
				if ($field["type"] == "input.file")
				{
					if (is_array($field["value"]))
					{
						$field["value"]["name"] = $field["name"];
						$files[] = $field["value"];
						$method = "post";
					}
				}
				else if ($field["type"] == "input.reset" || $field["type"] == "button.reset")
				{
				}
				else if ($field["type"] == "input.submit" || $field["type"] == "input.image" || $field["type"] == "button.submit")
				{
					if (($submitname === false || $field["name"] === $submitname) && ($submitvalue === false || $field["value"] === $submitvalue))
					{
						if ($submitname !== "")
						{
							if (!isset($fields[$field["name"]]))  $fields[$field["name"]] = array();
							$fields[$field["name"]][] = $field["value"];
						}

						if ($field["type"] == "input.image")
						{
							if (!isset($fields["x"]))  $fields["x"] = array();
							$fields["x"][] = "1";

							if (!isset($fields["y"]))  $fields["y"] = array();
							$fields["y"][] = "1";
						}
					}
				}
				else if (($field["type"] != "input.radio" && $field["type"] != "input.checkbox") || $field["checked"])
				{
					if (!isset($fields[$field["name"]]))  $fields[$field["name"]] = array();
					$fields[$field["name"]][] = $field["value"];
				}
			}

			if ($method == "get")
			{
				$url = HTTP::ExtractURL($this->info["action"]);
				unset($url["query"]);
				$url["queryvars"] = $fields;
				$result = array(
					"url" => HTTP::CondenseURL($url),
					"options" => array()
				);
			}
			else
			{
				$result = array(
					"url" => $this->info["action"],
					"options" => array(
						"postvars" => $fields,
						"files" => $files
					)
				);
			}

			return $result;
		}
	}
?>