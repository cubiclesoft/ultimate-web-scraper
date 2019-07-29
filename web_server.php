<?php
	// CubicleSoft PHP WebServer class.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	// Make sure PHP doesn't introduce weird limitations.
	ini_set("memory_limit", "-1");
	set_time_limit(0);

	// Requires the CubicleSoft PHP HTTP class.
	// Compression support requires the CubicleSoft PHP DeflateStream class.
	class WebServer
	{
		private $fp, $ssl, $initclients, $clients, $readreadyclients, $writereadyclients, $nextclientid;
		private $defaulttimeout, $defaultclienttimeout, $maxrequests, $defaultclientoptions, $usegzip, $cachedir;

		public function __construct()
		{
			$this->Reset();
		}

		public function Reset()
		{
			if (!class_exists("HTTP", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/http.php";

			$this->fp = false;
			$this->ssl = false;
			$this->initclients = array();
			$this->clients = array();
			$this->readreadyclients = array();
			$this->writereadyclients = array();
			$this->nextclientid = 1;

			$this->defaulttimeout = 30;
			$this->defaultclienttimeout = 30;
			$this->maxrequests = 30;
			$this->defaultclientoptions = array();
			$this->usegzip = false;
			$this->cachedir = false;
		}

		public function __destruct()
		{
			$this->Stop();
		}

		public function SetDefaultTimeout($timeout)
		{
			$this->defaulttimeout = (int)$timeout;
		}

		public function SetDefaultClientTimeout($timeout)
		{
			$this->defaultclienttimeout = (int)$timeout;
		}

		public function SetMaxRequests($num)
		{
			$this->maxrequests = (int)$num;
		}

		public function SetDefaultClientOptions($options)
		{
			$this->defaultclientoptions = $options;
		}

		public function EnableCompression($compress)
		{
			if (!class_exists("DeflateStream", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/deflate_stream.php";

			$this->usegzip = (bool)$compress;
		}

		public static function MakeTempDir($prefix, $perms = 0770)
		{
			$dir = sys_get_temp_dir();
			$dir = str_replace("\\", "/", $dir);
			if (substr($dir, -1) !== "/")  $dir .= "/";
			$dir .= $prefix . "_" . getmypid() . "_" . microtime(true);
			@mkdir($dir, 0770, true);
			@chmod($dir, $perms);

			return $dir;
		}

		public function SetCacheDir($cachedir)
		{
			if ($cachedir !== false)
			{
				$cachedir = str_replace("\\", "/", $cachedir);
				if (substr($cachedir, -1) !== "/")  $cachedir .= "/";
			}

			$this->cachedir = $cachedir;
		}

		public function GetCacheDir()
		{
			return $this->cachedir;
		}

		// Starts the server on the host and port.
		// $host is usually 0.0.0.0 or 127.0.0.1 for IPv4 and [::0] or [::1] for IPv6.
		public function Start($host, $port, $sslopts = false)
		{
			$this->Stop();

			$context = stream_context_create();

			if (is_array($sslopts))
			{
				stream_context_set_option($context, "ssl", "ciphers", HTTP::GetSSLCiphers());
				stream_context_set_option($context, "ssl", "disable_compression", true);
				stream_context_set_option($context, "ssl", "allow_self_signed", true);
				stream_context_set_option($context, "ssl", "verify_peer", false);

				// 'local_cert' and 'local_pk' are common options.
				foreach ($sslopts as $key => $val)
				{
					stream_context_set_option($context, "ssl", $key, $val);
				}

				$this->ssl = true;
			}

			$this->fp = stream_socket_server("tcp://" . $host . ":" . $port, $errornum, $errorstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
			if ($this->fp === false)  return array("success" => false, "error" => HTTP::HTTPTranslate("Bind() failed.  Reason:  %s (%d)", $errorstr, $errornum), "errorcode" => "bind_failed");

			// Enable non-blocking mode.
			stream_set_blocking($this->fp, 0);

			return array("success" => true);
		}

		public function Stop()
		{
			if ($this->fp !== false)
			{
				foreach ($this->initclients as $id => $client)
				{
					@fclose($client->fp);
				}

				foreach ($this->clients as $id => $client)
				{
					$this->RemoveClient($id);
				}

				fclose($this->fp);

				$this->initclients = array();
				$this->clients = array();
				$this->readreadyclients = array();
				$this->writereadyclients = array();
				$this->fp = false;
				$this->ssl = false;
			}

			$this->nextclientid = 1;
		}

		// Dangerous but allows for stream_select() calls on multiple, separate stream handles.
		public function GetStream()
		{
			return $this->fp;
		}

		public function AddClientRecvHeader($id, $name, $val)
		{
			if ($name === "" && $val === "")  return;

			$client = $this->clients[$id];

			if (substr($name, -2) !== "[]")  $client->requestvars[$name] = $val;
			else
			{
				$name = substr($name, 0, -2);

				if (!isset($client->requestvars[$name]) || !is_array($client->requestvars[$name]))  $client->requestvars[$name] = array();
				$client->requestvars[$name][] = $val;
			}
		}

		public function ProcessClientRequestHeaders($request, $headers, $id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			$client->request = $request;

			$client->headers = array();
			foreach ($headers as $key => $vals)
			{
				$client->headers[$key] = $vals[count($vals) - 1];
			}

			if (isset($client->headers["Content-Type"]))  $client->contenttype = HTTP::ExtractHeader($client->headers["Content-Type"]);

			if (isset($client->headers["Host"]))  $client->headers["Host"] = preg_replace('/[^a-z0-9.:\[\]_-]/', "", strtolower($client->headers["Host"]));

			$client->url = ($this->ssl ? "https" : "http") . "://" . (isset($client->headers["Host"]) ? $client->headers["Host"] : "localhost") . $request["path"];

			// Process cookies.
			$client->cookievars = array();
			$client->requestvars = array();
			if (isset($client->headers["Cookie"]))
			{
				$cookies = explode(";", $client->headers["Cookie"]);
				foreach ($cookies as $cookie)
				{
					$pos = strpos($cookie, "=");
					if ($pos === false)
					{
						$name = $cookie;
						$val = "";
					}
					else
					{
						$name = substr($cookie, 0, $pos);
						$val = urldecode(trim(substr($cookie, $pos + 1)));
					}

					$name = urldecode(trim($name));

					$this->AddClientRecvHeader($id, $name, $val);

					if (substr($name, -2) !== "[]")  $client->cookievars[$name] = $val;
					else
					{
						$name = substr($name, 0, -2);

						if (!isset($client->cookievars[$name]) || !is_array($client->cookievars[$name]))  $client->cookievars[$name] = array();
						$client->cookievars[$name][] = $val;
					}
				}
			}

			// Process query string.
			$url = HTTP::ExtractURL($client->url);
			foreach ($url["queryvars"] as $name => $vals)
			{
				foreach ($vals as $val)  $this->AddClientRecvHeader($id, $name, $val);
			}

			return true;
		}

		public function ProcessClientRequestBody($request, $body, $id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			if ($body !== "")
			{
				if (is_object($client->readdata))  $client->readdata->Write($body);
				else
				{
					$client->readdata .= $body;
					if (strlen($client->readdata) > 262144)  return false;

					if ($client->contenttype !== false)
					{
						if ($client->contenttype[""] === "application/x-www-form-urlencoded")
						{
							$pos = 0;
							$pos2 = strpos($client->readdata, "&");
							while ($pos2 !== false)
							{
								$str = (string)substr($client->readdata, $pos, $pos2 - $pos);
								$pos3 = strpos($str, "=");
								if ($pos3 === false)
								{
									$name = $str;
									$val = "";
								}
								else
								{
									$name = substr($str, 0, $pos3);
									$val = urldecode(trim(substr($str, $pos3 + 1)));
								}

								$name = urldecode(trim($name));

								$this->AddClientRecvHeader($id, $name, $val);

								$pos = $pos2 + 1;
								$pos2 = strpos($client->readdata, "&", $pos);
							}

							if ($pos)  $client->readdata = substr($client->readdata, $pos);
						}
						else if ($client->contenttype[""] === "multipart/form-data" && isset($client->contenttype["boundary"]))
						{
							$pos = 0;
							do
							{
								$origmode = $client->mode;

								switch ($client->mode)
								{
									case "handle_request":
									{
										$pos2 = strpos($client->readdata, "\n", $pos);
										if ($pos2 === false)  break;
										$str = trim(substr($client->readdata, $pos, $pos2 - $pos));
										if ($str === "--" . $client->contenttype["boundary"] . "--")
										{
											$pos = $pos2 + 1;
										}
										else if ($str === "--" . $client->contenttype["boundary"])
										{
											$pos = $pos2 + 1;
											$client->mode = "handle_request_mime_headers";
											$client->mimeheaders = array();
											$client->lastmimeheader = "";
										}

										break;
									}
									case "handle_request_mime_headers":
									{
										while (($pos2 = strpos($client->readdata, "\n", $pos)) !== false)
										{
											$header = rtrim(substr($client->readdata, $pos, $pos2 - $pos));
											$pos = $pos2 + 1;
											if ($header != "")
											{
												if ($client->lastmimeheader != "" && (substr($header, 0, 1) == " " || substr($header, 0, 1) == "\t"))  $client->mimeheaders[$client->lastmimeheader] .= $header;
												else
												{
													$pos2 = strpos($header, ":");
													if ($pos2 === false)  $pos2 = strlen($header);
													$client->lastmimeheader = HTTP::HeaderNameCleanup(substr($header, 0, $pos2));
													$client->mimeheaders[$client->lastmimeheader] = ltrim(substr($header, $pos2 + 1));
												}

												if (isset($client->httpstate["options"]["maxheaders"]) && count($client->mimeheaders) > $client->httpstate["options"]["maxheaders"])  return false;
											}
											else
											{
												if (!isset($client->mimeheaders["Content-Disposition"]))  $client->mode = "handle_request_mime_content_skip";
												else
												{
													$client->mime_contentdisposition = HTTP::ExtractHeader($client->mimeheaders["Content-Disposition"]);

													if ($client->mime_contentdisposition[""] !== "form-data" || !isset($client->mime_contentdisposition["name"]) || $client->mime_contentdisposition["name"] === "")
													{
														$client->mode = "handle_request_mime_content_skip";
													}
													else if ($this->cachedir !== false && isset($client->mime_contentdisposition["filename"]) && $client->mime_contentdisposition["filename"] !== "")
													{
														if ($client->currfile !== false)  $client->files[$client->currfile]->Close();

														$filename = $this->cachedir . $id . "_" . $client->requests . "_" . count($client->files) . ".dat";
														$client->currfile = $filename;

														if (!is_dir($this->cachedir))  @mkdir($this->cachedir, 0770, true);
														if (file_exists($filename))  @unlink($filename);
														$tempfile = new WebServer_TempFile();
														$tempfile->filename = $filename;
														if ($tempfile->Open() === false)  return false;

														$client->files[$filename] = $tempfile;
														$this->AddClientRecvHeader($id, $client->mime_contentdisposition["name"], $tempfile);

														$client->mode = "handle_request_mime_content_file";
													}
													else
													{
														$client->mime_value = "";

														$client->mode = "handle_request_mime_content";
													}
												}

												break;
											}
										}

										break;
									}
									case "handle_request_mime_content_skip":
									case "handle_request_mime_content_file":
									case "handle_request_mime_content":
									{
										$pos3 = $pos2 = strpos($client->readdata, "\r\n--" . $client->contenttype["boundary"], $pos);
										if ($pos3 === false)
										{
											$pos3 = strlen($client->readdata) - strlen($client->contenttype["boundary"]) - 6;
											if ($pos3 < $pos)  $pos3 = $pos;
										}

										$data = (string)substr($client->readdata, $pos, $pos3 - $pos);
										if ($data !== "")
										{
											if ($origmode === "handle_request_mime_content_file")  $client->files[$client->currfile]->Write($data);
											else if ($origmode === "handle_request_mime_content")
											{
												$client->mime_value .= $data;

												if (strlen($client->mime_value) > 262144)  return false;
											}
										}

										if ($pos2 === false)  $pos = $pos3;
										else
										{
											if ($client->mode === "handle_request_mime_content")  $this->AddClientRecvHeader($id, $client->mime_contentdisposition["name"], $client->mime_value);

											$client->mode = "handle_request";

											$pos = $pos2 + 2;
										}

										break;
									}
								}
							} while ($origmode !== $client->mode);

							if ($pos)  $client->readdata = (string)substr($client->readdata, $pos);
						}
						else if ($this->cachedir !== false && strlen($client->readdata) > 100000)
						{
							$client->contenthandled = false;

							$filename = $this->cachedir . $id . "_" . $client->requests . ".dat";
							$client->currfile = $filename;

							if (!is_dir($this->cachedir))  @mkdir($this->cachedir, 0770, true);
							if (file_exists($filename))  @unlink($filename);
							$tempfile = new WebServer_TempFile();
							$tempfile->filename = $filename;
							if ($tempfile->Open() === false)  return false;

							$client->files[$filename] = $tempfile;
							$client->files[$filename]->Write($client->readdata);

							$client->readdata = $tempfile;
						}
						else
						{
							$client->contenthandled = false;
						}
					}
				}
			}

			return true;
		}

		public function ProcessClientResponseBody(&$data, &$bodysize, $id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			$data .= $client->writedata;
			$client->writedata = "";

			if ($bodysize === false && $client->responsefinalized)  $bodysize = true;

			return true;
		}

		public function UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
		{
			if ($this->fp !== false)  $readfps[$prefix . "http_s"] = $this->fp;
			if ($timeout === false || $timeout > $this->defaulttimeout)  $timeout = $this->defaulttimeout;

			$ts = microtime(true);
			foreach ($this->initclients as $id => $client)
			{
				if ($client->mode === "init")
				{
					$readfps[$prefix . "http_c_" . $id] = $client->fp;
					if ($timeout > 1)  $timeout = 1;
				}
			}

			if (count($this->readreadyclients))  $timeout = 0;

			foreach ($this->clients as $id => $client)
			{
				if ($client->httpstate !== false)
				{
					if ($ts < $client->httpstate["waituntil"] && $timeout > $client->httpstate["waituntil"] - $ts + 0.5)
					{
						$timeout = (int)($client->httpstate["waituntil"] - $ts + 0.5);

						$client->lastts = $ts;
					}
					else if (HTTP::WantRead($client->httpstate))  $readfps[$prefix . "http_c_" . $id] = $client->fp;
					else if ($client->mode !== "init_response" && ($client->writedata !== "" || $client->httpstate["data"] !== ""))  $writefps[$prefix . "http_c_" . $id] = $client->fp;
					else if ($client->responsefinalized)
					{
						$this->writereadyclients[$id] = $client->fp;

						$timeout = 0;
					}
				}
			}
		}

		// Sometimes keyed arrays don't work properly.
		public static function FixedStreamSelect(&$readfps, &$writefps, &$exceptfps, $timeout)
		{
			// In order to correctly detect bad outputs, no '0' integer key is allowed.
			if (isset($readfps[0]) || isset($writefps[0]) || ($exceptfps !== NULL && isset($exceptfps[0])))  return false;

			$origreadfps = $readfps;
			$origwritefps = $writefps;
			$origexceptfps = $exceptfps;

			$result2 = @stream_select($readfps, $writefps, $exceptfps, $timeout);
			if ($result2 === false)  return false;

			if (isset($readfps[0]))
			{
				$fps = array();
				foreach ($origreadfps as $key => $fp)  $fps[(int)$fp] = $key;

				foreach ($readfps as $num => $fp)
				{
					$readfps[$fps[(int)$fp]] = $fp;

					unset($readfps[$num]);
				}
			}

			if (isset($writefps[0]))
			{
				$fps = array();
				foreach ($origwritefps as $key => $fp)  $fps[(int)$fp] = $key;

				foreach ($writefps as $num => $fp)
				{
					$writefps[$fps[(int)$fp]] = $fp;

					unset($writefps[$num]);
				}
			}

			if ($exceptfps !== NULL && isset($exceptfps[0]))
			{
				$fps = array();
				foreach ($origexceptfps as $key => $fp)  $fps[(int)$fp] = $key;

				foreach ($exceptfps as $num => $fp)
				{
					$exceptfps[$fps[(int)$fp]] = $fp;

					unset($exceptfps[$num]);
				}
			}

			return true;
		}

		public function InitNewClient()
		{
			$client = new WebServer_Client();

			$client->id = $this->nextclientid;
			$client->mode = "init";
			$client->httpstate = false;
			$client->readdata = "";
			$client->request = false;
			$client->url = "";
			$client->headers = false;
			$client->contenttype = false;
			$client->contenthandled = true;
			$client->cookievars = false;
			$client->requestvars = false;
			$client->requestcomplete = false;
			$client->keepalive = true;
			$client->requests = 0;
			$client->responseheaders = false;
			$client->responsefinalized = false;
			$client->deflate = false;
			$client->writedata = "";
			$client->lastts = microtime(true);
			$client->fp = false;
			$client->ipaddr = false;
			$client->currfile = false;
			$client->files = array();

			// Intended for application storage.
			$client->appdata = false;

			$this->initclients[$this->nextclientid] = $client;

			$this->nextclientid++;

			return $client;
		}

		protected function HandleNewConnections(&$readfps, &$writefps)
		{
			if (isset($readfps["http_s"]))
			{
				while (($fp = @stream_socket_accept($this->fp, 0)) !== false)
				{
					// Enable non-blocking mode.
					stream_set_blocking($fp, 0);

					$client = $this->InitNewClient();
					$client->fp = $fp;
					$client->ipaddr = stream_socket_get_name($fp, true);
				}

				unset($readfps["http_s"]);
			}
		}

		protected function HandleResponseCompleted($id, $result)
		{
		}

		// Handles new connections, the initial conversation, basic packet management, rate limits, and timeouts.
		// Can wait on more streams than just sockets and/or more sockets.  Useful for waiting on other resources.
		// 'http_s' and the 'http_c_' prefix are reserved.
		// Returns an array of clients that may need more processing.
		public function Wait($timeout = false, $readfps = array(), $writefps = array(), $exceptfps = NULL)
		{
			$this->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);

			$result = array("success" => true, "clients" => array(), "removed" => array(), "readfps" => array(), "writefps" => array(), "exceptfps" => array());
			if (!count($readfps) && !count($writefps))  return $result;

			$result2 = self::FixedStreamSelect($readfps, $writefps, $exceptfps, $timeout);
			if ($result2 === false)  return array("success" => false, "error" => HTTP::HTTPTranslate("Wait() failed due to stream_select() failure.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed");

			// Handle new connections.
			$this->HandleNewConnections($readfps, $writefps);

			// Handle ready clients.
			foreach ($this->readreadyclients as $id => $fp)  $readfps["http_c_" . $id] = $fp;
			$this->readreadyclients = array();

			// Handle clients in the read queue.
			foreach ($readfps as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 8 || substr($cid, 0, 7) !== "http_c_")  continue;

				$id = (int)substr($cid, 7);

				if (!isset($this->clients[$id]))  continue;

				$client = $this->clients[$id];

				$client->lastts = microtime(true);

				if ($client->httpstate !== false)
				{
					$result2 = HTTP::ProcessState($client->httpstate);
					if ($result2["success"])
					{
						// Trigger the last variable to process when extracting form variables.
						if ($client->contenttype !== false && $client->contenttype[""] === "application/x-www-form-urlencoded")  $this->ProcessClientRequestBody($result2["request"], "&", $id);

						if ($client->currfile !== false)
						{
							$client->files[$client->currfile]->Close();

							$client->currfile = false;
						}

						$result["clients"][$id] = $client;

						$client->requestcomplete = true;
						$client->requests++;
						$client->mode = "init_response";
						$client->responseheaders = array();
						$client->responsefinalized = false;
						$client->responsebodysize = true;

						$client->httpstate["type"] = "request";
						$client->httpstate["startts"] = microtime(true);
						$client->httpstate["waituntil"] = -1.0;
						$client->httpstate["data"] = "";
						$client->httpstate["bodysize"] = false;
						$client->httpstate["chunked"] = false;
						$client->httpstate["secure"] = $this->ssl;
						$client->httpstate["state"] = "send_data";

						$client->SetResponseCode(200);
						$client->SetResponseContentType("text/html; charset=UTF-8");

						if (isset($client->headers["Connection"]))
						{
							$connection = HTTP::ExtractHeader($client->headers["Connection"]);
							if (strtolower($connection[""]) === "close")  $client->keepalive = false;
						}

						$ver = explode("/", $client->request["httpver"]);
						$ver = (double)array_pop($ver);
						if ($ver < 1.1)  $client->keepalive = false;

						if ($client->requests >= $this->maxrequests)  $client->keepalive = false;

						if ($this->usegzip && isset($client->headers["Accept-Encoding"]))
						{
							$encodings = HTTP::ExtractHeader($client->headers["Accept-Encoding"]);
							$encodings = explode(",", $encodings[""]);
							$gzip = false;
							foreach ($encodings as $encoding)
							{
								if (strtolower(trim($encoding)) === "gzip")  $gzip = true;
							}

							if ($gzip)
							{
								$client->deflate = new DeflateStream();
								$client->deflate->Init("wb", -1, array("type" => "gzip"));

								$client->AddResponseHeader("Content-Encoding", "gzip", true);
							}
						}
					}
					else if ($result2["errorcode"] !== "no_data")
					{
						if ($client->requests)  $result["removed"][$id] = array("result" => $result2, "client" => $client);

						$this->RemoveClient($id);
					}
					else if ($client->requestcomplete === false && $client->httpstate["state"] !== "request_line" && $client->httpstate["state"] !== "headers")
					{
						// Allows the caller an opportunity to adjust some client options based on inputs on a per-client basis (e.g. recvlimit).
						$this->readreadyclients[$id] = $fp;
						$result["clients"][$id] = $client;
					}
				}

				unset($readfps[$cid]);
			}

			// Handle ready clients.
			foreach ($this->writereadyclients as $id => $fp)  $writefps["http_c_" . $id] = $fp;
			$this->writereadyclients = array();

			// Handle clients in the write queue.
			foreach ($writefps as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 6 || substr($cid, 0, 7) !== "http_c_")  continue;

				$id = (int)substr($cid, 7);

				if (!isset($this->clients[$id]))  continue;

				$client = $this->clients[$id];

				$client->lastts = microtime(true);

				if ($client->httpstate !== false)
				{
					// Transform the client response into real data.
					if ($client->mode === "response_ready")
					{
						if ($client->responsefinalized)
						{
							if ($client->responsebodysize !== false)  $client->AddResponseHeader("Content-Length", (string)strlen($client->writedata), true);
							$client->httpstate["bodysize"] = strlen($client->writedata);
						}
						else if ($client->responsebodysize !== true)
						{
							$client->AddResponseHeader("Content-Length", (string)$client->responsebodysize, true);
							$client->httpstate["bodysize"] = $client->responsebodysize;
						}
						else if ($client->keepalive)
						{
							$client->AddResponseHeader("Transfer-Encoding", "chunked", true);
							$client->httpstate["chunked"] = true;
						}

						$client->AddResponseHeader("Date", gmdate("D, d M Y H:i:s T"), true);

						if (!$client->keepalive || $client->requests >= $this->maxrequests)  $client->AddResponseHeader("Connection", "close", true);

						foreach ($client->responseheaders as $name => $vals)
						{
							foreach ($vals as $val)  $client->httpstate["data"] .= $name . ": " . $val . "\r\n";
						}
						$client->responseheaders = false;

						$client->httpstate["data"] .= "\r\n";
						$client->httpstate["result"]["rawsendheadersize"] = strlen($client->httpstate["data"]);

						$client->mode = "handle_response";
					}

					$result2 = HTTP::ProcessState($client->httpstate);
					if ($result2["success"])
					{
						if (!$client->responsefinalized)
						{
							$result["clients"][$id] = $client;
						}
						else if ($client->keepalive && $client->requests < $this->maxrequests)
						{
							$this->HandleResponseCompleted($id, $result2);

							// Reset client for another request.
							$client->mode = "init_request";
							$client->readdata = $client->httpstate["nextread"];
							$client->httpstate = false;
							$client->request = false;
							$client->url = "";
							$client->headers = false;
							$client->contenttype = false;
							$client->contenthandled = true;
							$client->cookievars = false;
							$client->requestvars = false;
							$client->requestcomplete = false;
							$client->deflate = false;
							$client->writedata = "";

							foreach ($client->files as $filename => $tempfile)
							{
								$client->files[$filename]->Free();

								unset($client->files[$filename]);
							}

							$client->files = array();

							$this->initclients[$id] = $client;
							unset($this->clients[$id]);

							if ($client->readdata !== "")  $this->readreadyclients[$id] = $fp;
						}
						else
						{
							$this->HandleResponseCompleted($id, $result2);

							$result["removed"][$id] = array("result" => array("success" => true), "client" => $client);

							$this->RemoveClient($id);
						}
					}
					else if ($result2["errorcode"] !== "no_data")
					{
						$this->HandleResponseCompleted($id, $result2);

						$result["removed"][$id] = array("result" => $result2, "client" => $client);

						$this->RemoveClient($id);
					}
				}

				unset($writefps[$cid]);
			}

			// Initialize new clients.
			foreach ($this->initclients as $id => $client)
			{
				do
				{
					$origmode = $client->mode;

					switch ($client->mode)
					{
						case "init":
						{
							$result2 = ($this->ssl ? @stream_socket_enable_crypto($client->fp, true, STREAM_CRYPTO_METHOD_TLSv1_1_SERVER | STREAM_CRYPTO_METHOD_TLSv1_2_SERVER) : true);

							if ($result2 === true)  $client->mode = "init_request";
							else if ($result2 === false)
							{
								@fclose($client->fp);

								unset($this->initclients[$id]);
							}

							break;
						}
						case "init_request":
						{
							// Use the HTTP class in server mode to handle state.
							// The callback functions are located in WebServer to avoid the issue of pass-by-reference memory leaks.
							$options = $this->defaultclientoptions;
							$options["async"] = true;
							$options["read_headers_callback"] = array($this, "ProcessClientRequestHeaders");
							$options["read_headers_callback_opts"] = $id;
							$options["read_body_callback"] = array($this, "ProcessClientRequestBody");
							$options["read_body_callback_opts"] = $id;
							$options["write_body_callback"] = array($this, "ProcessClientResponseBody");
							$options["write_body_callback_opts"] = $id;

							if (!isset($options["readlinelimit"]))  $options["readlinelimit"] = 116000;
							if (!isset($options["maxheaders"]))  $options["maxheaders"] = 1000;
							if (!isset($options["recvlimit"]))  $options["recvlimit"] = 1000000;

							$startts = microtime(true);
							$timeout = (isset($options["timeout"]) ? $options["timeout"] : false);
							$result2 = array("success" => true, "rawsendsize" => 0, "rawsendheadersize" => 0, "rawrecvsize" => 0, "rawrecvheadersize" => 0, "startts" => $startts);
							$debug = (isset($options["debug"]) && $options["debug"]);
							if ($debug)
							{
								$result2["rawsend"] = "";
								$result2["rawrecv"] = "";
							}

							$client->httpstate = HTTP::InitResponseState($client->fp, $debug, $options, $startts, $timeout, $result2, false, $client->readdata, false);
							$client->readdata = "";
							$client->mode = "handle_request";

							$client->lastts = microtime(true);

							$this->clients[$id] = $client;
							unset($this->initclients[$id]);

							break;
						}
					}
				} while (isset($this->initclients[$id]) && $origmode !== $client->mode);
			}

			// Handle client timeouts.
			$ts = microtime(true);
			foreach ($this->clients as $id => $client)
			{
				if ($client->lastts + $this->defaultclienttimeout < $ts)
				{
					if ($client->requests)
					{
						$result2 = array("success" => false, "error" => HTTP::HTTPTranslate("Client timed out.  Most likely cause:  Connection failure."), "errorcode" => "client_timeout");

						$result["removed"][$id] = array("result" => $result2, "client" => $client);
					}

					$this->RemoveClient($id);
				}
			}

			// Return any extra handles that were being waited on.
			$result["readfps"] = $readfps;
			$result["writefps"] = $writefps;
			$result["exceptfps"] = $exceptfps;

			return $result;
		}

		public function GetClients()
		{
			return $this->clients;
		}

		public function NumClients()
		{
			return count($this->clients);
		}

		public function GetClient($id)
		{
			return (isset($this->clients[$id]) ? $this->clients[$id] : false);
		}

		public function DetachClient($id)
		{
			if (!isset($this->clients[$id]))  return false;

			$client = $this->clients[$id];

			unset($this->clients[$id]);
			unset($this->readreadyclients[$id]);

			return $client;
		}

		public function RemoveClient($id)
		{
			if (isset($this->clients[$id]))
			{
				$client = $this->clients[$id];

				// Remove the client.
				foreach ($client->files as $filename => $tempfile)
				{
					$client->files[$filename]->Free();
				}

				if ($client->fp !== false)  @fclose($client->fp);

				unset($this->clients[$id]);
				unset($this->readreadyclients[$id]);
			}
		}
	}

	// Internal class used to construct file objects to minimize the number of open file handles.
	// Otherwise an attacker could easily consume all available file handles in a single request.
	class WebServer_TempFile
	{
		public $fp, $filename;

		public function Open()
		{
			$this->Close();

			$this->fp = fopen($this->filename, "w+b");

			return $this->fp;
		}

		public function Read($size)
		{
			if ($this->fp === false)  return false;

			$data = fread($this->fp, $size);
			if ($data === false || feof($this->fp))  $this->Close();
			if ($data === false)  $data = "";

			return $data;
		}

		public function Write($data)
		{
			return fwrite($this->fp, $data);
		}

		public function Close()
		{
			if (is_resource($this->fp))  fclose($this->fp);

			$this->fp = false;
		}

		public function Free()
		{
			$this->Close();

			unlink($this->filename);
		}
	}

	// Internal client class constructed by the web server class.
	class WebServer_Client
	{
		public function GetHTTPOptions()
		{
			return ($this->httpstate !== false ? $this->httpstate["options"] : false);
		}

		public function SetHTTPOptions($options)
		{
			if ($this->httpstate !== false)  $this->httpstate["options"] = $options;
		}

		public function SetResponseCode($code)
		{
			if ($this->requestcomplete && $this->mode !== "handle_response")
			{
				if (is_int($code))
				{
					$codemap = array(
						100 => "Continue", 101 => "Switching Protocols", 102 => "Processing",

						200 => "OK", 201 => "Created", 202 => "Accepted", 203 => "Non-Authoritative Information", 204 => "No Content", 205 => "Reset Content",
						206 => "Partial Content", 207 => "Multi-Status", 208 => "Already Reported", 226 => "IM Used",

						300 => "Multiple Choices", 301 => "Moved Permanently", 302 => "Found", 303 => "See Other", 304 => "Not Modified", 305 => "Use Proxy",
						306 => "Switch Proxy", 307 => "Temporary Redirect", 308 => "Permanent Redirect",

						400 => "Bad Request", 401 => "Unauthorized", 402 => "Payment Required", 403 => "Forbidden", 404 => "Not Found", 405 => "Method Not Allowed",
						406 => "Not Acceptable", 407 => "Proxy Authentication Required", 408 => "Request Timeout", 409 => "Conflict", 410 => "Gone",
						411 => "Length Required", 412 => "Precondition Failed", 413 => "Payload Too Large", 414 => "URI Too Long", 415 => "Unsupported Media Type",
						416 => "Range Not Satisfiable", 417 => "Expectation Failed", 418 => "I'm a teapot", 421 => "Misdirected Request",
						422 => "Unprocessable Entity", 423 => "Locked", 424 => "Failed Dependency", 426 => "Upgrade Required", 428 => "Precondition Required",
						429 => "Too Many Requests", 431 => "Request Header Fields Too Large", 451 => "Unavailable For Legal Reasons",

						500 => "Internal Server Error", 501 => "Not Implemented", 502 => "Bad Gateway", 503 => "Service Unavailable", 504 => "Gateway Timeout",
						505 => "HTTP Version Not Supported", 506 => "Variant Also Negotiates", 507 => "Insufficient Storage", 508 => "Loop Detected",
						510 => "Not Extended", 511 => "Network Authentication Required",
					);

					if (!isset($codemap[$code]))  $code = 500;

					if ($this->responsebodysize === true && (($code >= 100 && $code < 200) || $code === 204 || $code === 304))  $this->responsebodysize = false;

					$code = $code . " " . $codemap[$code];
				}

				$this->httpstate["data"] = $this->request["httpver"] . " " . $code . "\r\n";
				$this->writedata = "";
			}
		}

		public function SetResponseContentType($contenttype)
		{
			$this->AddResponseHeader("Content-Type", $contenttype, true);
		}

		public function SetResponseCookie($name, $value = "", $expires = 0, $path = "", $domain = "", $secure = false, $httponly = false)
		{
			if (!empty($domain))
			{
				// Remove port information.
				$pos = strpos($domain, "]");
				if (substr($domain, 0, 1) == "[" && $pos !== false)  $domain = substr($domain, 0, $pos + 1);
				else
				{
					$port = strpos($domain, ":");
					if ($port !== false)  $domain = substr($domain, 0, $port);

					// Fix the domain to accept domains with and without 'www.'.
					if (strtolower(substr($domain, 0, 4)) == "www.")  $domain = substr($domain, 4);
					if (strpos($domain, ".") === false)  $domain = "";
					else if (substr($domain, 0, 1) != ".")  $domain = "." . $domain;
				}
			}

			$this->AddResponseHeader("Set-Cookie", rawurlencode($name) . "=" . rawurlencode($value)
									. (empty($expires) ? "" : "; expires=" . gmdate("D, d-M-Y H:i:s", $expires) . " GMT")
									. (empty($path) ? "" : "; path=" . $path)
									. (empty($domain) ? "" : "; domain=" . $domain)
									. (!$secure ? "" : "; secure")
									. (!$httponly ? "" : "; HttpOnly"));
		}

		public function SetResponseNoCache()
		{
			$this->AddResponseHeader("Expires", "Tue, 03 Jul 2001 06:00:00 GMT", true);
			$this->AddResponseHeader("Last-Modified", gmdate("D, d M Y H:i:s T"), true);
			$this->AddResponseHeader("Cache-Control", "max-age=0, no-cache, must-revalidate, proxy-revalidate", true);
		}

		public function AddResponseHeader($name, $val, $replace = false)
		{
			if ($this->requestcomplete && $this->mode !== "handle_response")
			{
				$name = preg_replace('/\s+/', "-", trim(preg_replace('/[^A-Za-z0-9 ]/', " ", $name)));

				if (!isset($this->responseheaders[$name]) || $replace)  $this->responseheaders[$name] = array();
				$this->responseheaders[$name][] = str_replace(array("\r", "\n"), array("", ""), $val);
			}
		}

		public function AddResponseHeaders($headers, $replace = false)
		{
			if ($this->requestcomplete && $this->mode !== "handle_response")
			{
				foreach ($headers as $name => $vals)
				{
					if (is_string($vals))  $vals = array($vals);

					$name = preg_replace('/\s+/', "-", trim(preg_replace('/[^A-Za-z0-9 ]/', " ", $name)));

					if (!isset($this->responseheaders[$name]) || $replace)  $this->responseheaders[$name] = array();
					foreach ($vals as $val)  $this->responseheaders[$name][] = $val;
				}
			}
		}

		public function SetResponseContentLength($bodysize)
		{
			if ($this->requestcomplete && !$this->responsefinalized)
			{
				$this->responsebodysize = $bodysize;
			}
		}

		public function AddResponseContent($data)
		{
			if ($this->requestcomplete && !$this->responsefinalized)
			{
				if ($this->deflate !== false)
				{
					$this->deflate->Write($data);
					$data = $this->deflate->Read();
				}

				$this->writedata .= $data;

				if ($this->mode !== "handle_response")  $this->mode = "response_ready";
			}
		}

		public function FinalizeResponse()
		{
			if ($this->requestcomplete && !$this->responsefinalized)
			{
				if ($this->deflate !== false)
				{
					$this->deflate->Finalize();
					$data = $this->deflate->Read();

					$this->writedata .= $data;

					$this->deflate = false;
				}

				if ($this->mode !== "handle_response")  $this->mode = "response_ready";

				$this->responsefinalized = true;
			}
		}
	}
?>