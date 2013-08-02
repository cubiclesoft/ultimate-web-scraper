<?php
	// CubicleSoft PHP web browser state emulation class.
	// (C) 2013 CubicleSoft.  All Rights Reserved.

	// Requires the CubicleSoft PHP HTTP functions for HTTP/HTTPS.
	class WebBrowser
	{
		private $data, $html;

		public function __construct($prevstate = array())
		{
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

		public function Process($url, $profile = "auto", $tempoptions = array())
		{
			$startts = microtime(true);
			$redirectts = $startts;
			if (isset($tempoptions["timeout"]))  $timeout = $tempoptions["timeout"];
			else if (isset($this->data["httpopts"]["timeout"]))  $timeout = $this->data["httpopts"]["timeout"];
			else  $timeout = false;

			if (!isset($this->data["httpopts"]["headers"]))  $this->data["httpopts"]["headers"] = array();
			$this->data["httpopts"]["headers"] = HTTPNormalizeHeaders($this->data["httpopts"]["headers"]);
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
			$tempoptions["headers"] = HTTPNormalizeHeaders($tempoptions["headers"]);
			if (isset($tempoptions["headers"]["Referer"]))  $this->data["referer"] = $tempoptions["headers"]["Referer"];

			// If a referrer is specified, use it to generate an absolute URL.
			if ($this->data["referer"] != "")  $url = ConvertRelativeToAbsoluteURL($this->data["referer"], $url);

			$urlinfo = ExtractURL($url);

			do
			{
				if (!isset($this->data["allowedprotocols"][$urlinfo["scheme"]]) || !$this->data["allowedprotocols"][$urlinfo["scheme"]])
				{
					return array("success" => false, "error" => HTTPTranslate("Protocol '%s' is not allowed in '%s'.", $urlinfo["scheme"], $url), "errorcode" => "allowed_protocols");
				}

				$filename = HTTPExtractFilename($urlinfo["path"]);
				$pos = strrpos($filename, ".");
				$fileext = ($pos !== false ? strtolower(substr($filename, $pos + 1)) : "");

				// Set up some standard headers.
				$headers = array();
				$profile = strtolower($profile);
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
					$headers["User-Agent"] = GetWebUserAgent(substr($profile, 0, 2) == "ie" ? $profile : $this->data["useragent"]);
				}
				else if ($profile == "firefox" || ($profile == "auto" && $this->data["useragent"] == "firefox"))
				{
					if ($fileext == "css")  $headers["Accept"] = "text/css,*/*;q=0.1";
					else if ($fileext == "png" || $fileext == "jpg" || $fileext == "jpeg" || $fileext == "gif" || $fileext == "svg")  $headers["Accept"] = "image/png,image/*;q=0.8,*/*;q=0.5";
					else if ($fileext == "js")  $headers["Accept"] = "*/*";
					else  $headers["Accept"] = "text/html, application/xhtml+xml, */*";

					$headers["Accept-Language"] = "en-us,en;q=0.5";
					$headers["Cache-Control"] = "max-age=0";
					$headers["User-Agent"] = GetWebUserAgent("firefox");
				}
				else if ($profile == "opera" || ($profile == "auto" && $this->data["useragent"] == "opera"))
				{
					// Opera has the right idea:  Just send the same thing regardless of the request type.
					$headers["Accept"] = "text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/webp, image/jpeg, image/gif, image/x-xbitmap, */*;q=0.1";
					$headers["Accept-Language"] = "en-US,en;q=0.9";
					$headers["Cache-Control"] = "no-cache";
					$headers["User-Agent"] = GetWebUserAgent("opera");
				}
				else if ($profile == "safari" || $profile == "chrome" || ($profile == "auto" && ($this->data["useragent"] == "safari" || $this->data["useragent"] == "chrome")))
				{
					if ($fileext == "css")  $headers["Accept"] = "text/css,*/*;q=0.1";
					else if ($fileext == "png" || $fileext == "jpg" || $fileext == "jpeg" || $fileext == "gif" || $fileext == "svg" || $fileext == "js")  $headers["Accept"] = "*/*";
					else  $headers["Accept"] = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";

					$headers["Accept-Charset"] = "ISO-8859-1,utf-8;q=0.7,*;q=0.3";
					$headers["Accept-Language"] = "en-US,en;q=0.8";
					$headers["User-Agent"] = GetWebUserAgent($profile == "safari" || $profile == "chrome" ? $profile : $this->data["useragent"]);
				}

				if ($this->data["referer"] != "")  $headers["Referer"] = $this->data["referer"];

				// Generate the final headers array.
				$headers = array_merge($headers, $httpopts["headers"], $tempoptions["headers"]);

				// Calculate the host and reverse host and remove port information.
				$host = (isset($headers["Host"]) ? $headers["Host"] : $urlinfo["host"]);
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
				if (substr($dothost, 0, 1) != ".")  $dothost = "." . $dothost;

				// Append cookies and delete old, invalid cookies.
				$secure = ($urlinfo["scheme"] == "https");
				$cookiepath = $urlinfo["path"];
				if ($cookiepath == "")  $cookiepath = "/";
				$pos = strrpos($cookiepath, "/");
				if ($pos !== false)  $cookiepath = substr($cookiepath, 0, $pos + 1);
				$cookies = array();
				foreach ($this->data["cookies"] as $domain => $paths)
				{
					if (substr($domain, -strlen($dothost)) == $dothost)
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
				$options = array_merge($httpopts, $tempoptions);
				$options["headers"] = $headers;
				if ($timeout !== false)  $options["timeout"] = HTTPGetTimeLeft($startts, $timeout);

				// Process the request.
				$result = RetrieveWebpage($url, $options);
				$result["url"] = $url;
				$result["options"] = $options;
				$result["firstreqts"] = $startts;
				$result["numredirects"] = $numredirects;
				$result["redirectts"] = $redirectts;
				if (isset($result["rawsendsize"]))  $totalrawsendsize += $result["rawsendsize"];
				$result["totalrawsendsize"] = $totalrawsendsize;
				unset($result["options"]["files"]);
				unset($result["options"]["body"]);
				if (!$result["success"])  return array("success" => false, "error" => HTTPTranslate("Unable to retrieve content.  %s", $result["error"]), "info" => $result, "errorcode" => "retrievewebpage");

				// Set up structures for another round.
				if ($this->data["autoreferer"])  $this->data["referer"] = $url;
				if (isset($result["headers"]["Location"]) && $this->data["followlocation"])
				{
					$redirectts = microtime(true);

					unset($tempoptions["method"]);
					unset($tempoptions["write_body_callback"]);
					unset($tempoptions["body"]);
					unset($tempoptions["postvars"]);
					unset($tempoptions["files"]);

					$tempoptions["headers"]["Referer"] = $url;
					$url = $result["headers"]["Location"][0];

					// Generate an absolute URL.
					if ($this->data["referer"] != "")  $url = ConvertRelativeToAbsoluteURL($this->data["referer"], $url);

					$urlinfo2 = ExtractURL($url);

					if (!isset($this->data["allowedredirprotocols"][$urlinfo2["scheme"]]) || !$this->data["allowedredirprotocols"][$urlinfo2["scheme"]])
					{
						return array("success" => false, "error" => HTTPTranslate("Protocol '%s' is not allowed.  Server attempted to redirect to '%s'.", $urlinfo2["scheme"], $url), "info" => $result, "errorcode" => "allowed_redir_protocols");
					}

					if ($urlinfo2["host"] != $urlinfo["host"])
					{
						unset($tempoptions["headers"]["Host"]);
						unset($httpopts["headers"]["Host"]);
					}

					$urlinfo = $urlinfo2;
					$numredirects++;
				}

				// Handle any 'Set-Cookie' headers.
				if (isset($result["headers"]["Set-Cookie"]))
				{
					foreach ($result["headers"]["Set-Cookie"] as $cookie)
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
								$ts = GetHTTPDateTimestamp($cookie["expires"]);
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

							if (!isset($cookie["domain"]))  $cookie["domain"] = $dothost;
							if (substr($cookie["domain"], 0, 1) != ".")  $cookie["domain"] = "." . $cookie["domain"];
							if (!isset($cookie["path"]))  $cookie["path"] = $cookiepath;
							$cookie["path"] = str_replace("\\", "/", $cookie["path"]);
							if (substr($cookie["path"], -1) != "/")  $cookie["path"] = "/";

							if (!isset($this->data["cookies"][$cookie["domain"]]))  $this->data["cookies"][$cookie["domain"]] = array();
							if (!isset($this->data["cookies"][$cookie["domain"]][$cookie["path"]]))  $this->data["cookies"][$cookie["domain"]][$cookie["path"]] = array();
							$this->data["cookies"][$cookie["domain"]][$cookie["path"]][] = $cookie;
						}
					}
				}

				if ($numfollow > 0)  $numfollow--;
			} while (isset($result["headers"]["Location"]) && $this->data["followlocation"] && $numfollow);

			$result["numredirects"] = $numredirects;
			$result["redirectts"] = $redirectts;

			// Extract the forms from the page in a parsed format.
			// Call WebBrowser::GenerateFormRequest() to prepare an actual request for Process().
			if ($this->data["extractforms"])  $result["forms"] = $this->ExtractForms($result["url"], $result["body"]);

			return $result;
		}

		public function ExtractForms($baseurl, $data)
		{
			$result = array();

			if ($this->html === false)  $this->html = new simple_html_dom();
			$this->html->load($data);
			$html5rows = $this->html->find("input[form],textarea[form],select[form],button[form],datalist[id]");
			$rows = $this->html->find("form");
			foreach ($rows as $row)
			{
				$info = array();
				if (isset($row->id))  $info["id"] = trim($row->id);
				if (isset($row->name))  $info["name"] = (string)$row->name;
				$info["action"] = (isset($row->action) ? ConvertRelativeToAbsoluteURL($baseurl, (string)$row->action) : $baseurl);
				$info["method"] = (isset($row->method) && strtolower(trim($row->method)) == "post" ? "post" : "get");
				if ($info["method"] == "post")  $info["enctype"] = (isset($row->enctype) ? strtolower($row->enctype) : "application/x-www-form-urlencoded");
				if (isset($row->{"accept-charset"}))  $info["accept-charset"] = (string)$row->{"accept-charset"};

				$fields = array();
				$rows2 = $row->find("input,textarea,select,button");
				foreach ($rows2 as $row2)
				{
					if (!isset($row2->form))  $this->ExtractFieldFromDOM($fields, $row2);
				}

				// Handle HTML5.
				if (isset($info["id"]) && $info["id"] != "")
				{
					foreach ($html5rows as $row2)
					{
						if (strpos(" " . $info["id"] . " ", " " . $row2->form . " ") !== false)  $this->ExtractFieldFromDOM($fields, $row2);
					}
				}

				$form = new WebBrowserForm();
				$form->info = $info;
				$form->fields = $fields;
				$result[] = $form;
			}

			return $result;
		}

		private function ExtractFieldFromDOM(&$fields, $row)
		{
			if (isset($row->name) && is_string($row->name))
			{
				switch ($row->tag)
				{
					case "input":
					{
						$field = array(
							"id" => (isset($row->id) ? (string)$row->id : false),
							"type" => "input." . (isset($row->type) ? strtolower($row->type) : "text"),
							"name" => $row->name,
							"value" => (isset($row->value) ? html_entity_decode($row->value, ENT_COMPAT, "UTF-8") : "")
						);
						if ($field["type"] == "input.radio" || $field["type"] == "input.checkbox")  $field["checked"] = (isset($row->checked));

						$fields[] = $field;

						break;
					}
					case "textarea":
					{
						$fields[] = array(
							"id" => (isset($row->id) ? (string)$row->id : false),
							"type" => "textarea",
							"name" => $row->name,
							"value" => html_entity_decode($row->innertext, ENT_COMPAT, "UTF-8")
						);

						break;
					}
					case "select":
					{
						if (isset($row->multiple))
						{
							// Change the type into multiple checkboxes.
							$rows = $row->find("option");
							foreach ($rows as $row2)
							{
								$fields[] = array(
									"id" => (isset($row->id) ? (string)$row->id : false),
									"type" => "input.checkbox",
									"name" => $row->name,
									"value" => (isset($row2->value) ? html_entity_decode($row2->value, ENT_COMPAT, "UTF-8") : ""),
									"display" => (string)$row2->innertext
								);
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

							$fields[] = array(
								"id" => (isset($row->id) ? (string)$row->id : false),
								"type" => "select",
								"name" => $row->name,
								"value" => $val,
								"options" => $options
							);
						}

						break;
					}
					case "button":
					{
						$fields[] = array(
							"id" => (isset($row->id) ? (string)$row->id : false),
							"type" => "button." . (isset($row->type) ? strtolower($row->type) : "submit"),
							"name" => $row->name,
							"value" => (isset($row->value) ? html_entity_decode($row->value, ENT_COMPAT, "UTF-8") : "")
						);

						break;
					}
				}
			}
		}

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
				else if ($field["type"] == "input.submit" || $field["type"] == "button.submit")
				{
					if (($submitname === false || $field["name"] === $submitname) && ($submitvalue === false || $field["value"] === $submitvalue))
					{
						if (!isset($fields[$field["name"]]))  $fields[$field["name"]] = array();
						$fields[$field["name"]][] = $field["value"];
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
				$url = ExtractURL($this->info["action"]);
				unset($url["query"]);
				$url["queryvars"] = $fields;
				$result = array(
					"url" => CondenseURL($url),
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