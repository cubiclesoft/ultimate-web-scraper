<?php
	// CubicleSoft PHP WebSocketServer class.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	// Make sure PHP doesn't introduce weird limitations.
	ini_set("memory_limit", "-1");
	set_time_limit(0);

	// Requires the CubicleSoft PHP WebSocket class.
	class WebSocketServer
	{
		protected $fp, $clients, $nextclientid, $websocketclass, $origins;
		protected $defaultclosemode, $defaultmaxreadframesize, $defaultmaxreadmessagesize, $defaultkeepalive, $lasttimeoutcheck;

		public function __construct()
		{
			$this->Reset();
		}

		public function Reset()
		{
			if (!class_exists("WebSocket", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/websocket.php";

			$this->fp = false;
			$this->clients = array();
			$this->nextclientid = 1;
			$this->websocketclass = "WebSocket";
			$this->origins = false;

			$this->defaultclosemode = WebSocket::CLOSE_IMMEDIATELY;
			$this->defaultmaxreadframesize = 2000000;
			$this->defaultmaxreadmessagesize = 10000000;
			$this->defaultkeepalive = 30;
			$this->lasttimeoutcheck = time();
		}

		public function __destruct()
		{
			$this->Stop();
		}

		public function SetWebSocketClass($newclass)
		{
			if (class_exists($newclass))  $this->websocketclass = $newclass;
		}

		public function SetAllowedOrigins($origins)
		{
			if (is_string($origins))  $origins = array($origins);
			if (!is_array($origins))  $origins = false;
			else if (isset($origins[0]))  $origins = array_flip($origins);

			$this->origins = $origins;
		}

		public function SetDefaultCloseMode($mode)
		{
			$this->defaultclosemode = $mode;
		}

		public function SetDefaultKeepAliveTimeout($keepalive)
		{
			$this->defaultkeepalive = (int)$keepalive;
		}

		public function SetDefaultMaxReadFrameSize($maxsize)
		{
			$this->defaultmaxreadframesize = (is_bool($maxsize) ? false : (int)$maxsize);
		}

		public function SetDefaultMaxReadMessageSize($maxsize)
		{
			$this->defaultmaxreadmessagesize = (is_bool($maxsize) ? false : (int)$maxsize);
		}

		// Starts the server on the host and port.
		// $host is usually 0.0.0.0 or 127.0.0.1 for IPv4 and [::0] or [::1] for IPv6.
		public function Start($host, $port)
		{
			$this->Stop();

			$this->fp = stream_socket_server("tcp://" . $host . ":" . $port, $errornum, $errorstr);
			if ($this->fp === false)  return array("success" => false, "error" => self::WSTranslate("Bind() failed.  Reason:  %s (%d)", $errorstr, $errornum), "errorcode" => "bind_failed");

			// Enable non-blocking mode.
			stream_set_blocking($this->fp, 0);

			return array("success" => true);
		}

		public function Stop()
		{
			foreach ($this->clients as $client)
			{
				if ($client->websocket !== false)  $client->websocket->Disconnect();
				else  fclose($client->fp);
			}

			$this->clients = array();

			if ($this->fp !== false)
			{
				fclose($this->fp);

				$this->fp = false;
			}

			$this->nextclientid = 1;
		}

		// Dangerous but allows for stream_select() calls on multiple, separate stream handles.
		public function GetStream()
		{
			return $this->fp;
		}

		// Return whatever response/headers are needed here.
		protected function ProcessNewConnection($method, $path, $client)
		{
			$result = "";

			if ($method !== "GET")  $result .= "HTTP/1.1 405 Method Not Allowed\r\nConnection: close\r\n\r\n";
			else if (!isset($client->headers["Host"]) || !isset($client->headers["Connection"]) || stripos($client->headers["Connection"], "upgrade") === false || !isset($client->headers["Upgrade"]) || stripos($client->headers["Upgrade"], "websocket") === false || !isset($client->headers["Sec-Websocket-Key"]))
			{
				$result .= "HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n";
			}
			else if (!isset($client->headers["Sec-Websocket-Version"]) || $client->headers["Sec-Websocket-Version"] != 13)
			{
				$result .= "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocket-Version: 13\r\nConnection: close\r\n\r\n";
			}
			else if (!isset($client->headers["Origin"]) || ($this->origins !== false && !isset($this->origins[strtolower($client->headers["Origin"])])))
			{
				$result .= "HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n";
			}

			return $result;
		}

		// Return whatever additional HTTP headers are needed here.
		protected function ProcessAcceptedConnection($method, $path, $client)
		{
			return "";
		}

		protected function InitNewClient($fp)
		{
			$client = new stdClass();

			$client->id = $this->nextclientid;
			$client->readdata = "";
			$client->writedata = "";
			$client->request = false;
			$client->path = "";
			$client->url = "";
			$client->headers = array();
			$client->lastheader = "";
			$client->websocket = false;
			$client->fp = $fp;
			$client->ipaddr = stream_socket_get_name($fp, true);

			// Intended for application storage.
			$client->appdata = false;

			$this->clients[$this->nextclientid] = $client;

			$this->nextclientid++;

			return $client;
		}

		private function ProcessInitialResponse($method, $path, $client)
		{
			// Let a derived class handle the new connection (e.g. processing Origin and Host).
			// Since the 'websocketclass' is instantiated AFTER this function, it is possible to switch classes on the fly.
			$client->writedata .= $this->ProcessNewConnection($method, $path, $client);

			// If an error occurs, the connection will still terminate.
			$client->websocket = new $this->websocketclass();
			$client->websocket->SetCloseMode($this->defaultclosemode);
			$client->websocket->SetKeepAliveTimeout($this->defaultkeepalive);

			// If nothing was output, accept the connection.
			if ($client->writedata === "")
			{
				$client->writedata .= "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n";
				$client->writedata .= "Sec-WebSocket-Accept: " . base64_encode(sha1($client->headers["Sec-Websocket-Key"] . WebSocket::KEY_GUID, true)) . "\r\n";
				$client->writedata .= $this->ProcessAcceptedConnection($method, $path, $client);
				$client->writedata .= "\r\n";

				// Finish class initialization.
				$client->websocket->SetServerMode();
				$client->websocket->SetMaxReadFrameSize($this->defaultmaxreadframesize);
				$client->websocket->SetMaxReadMessageSize($this->defaultmaxreadmessagesize);

				// Set the socket in the WebSocket class.
				$client->websocket->Connect("", "", array("connected_fp" => $client->fp));
			}

			$this->UpdateClientState($client->id);
		}

		public function UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
		{
			if ($this->fp !== false)  $readfps[$prefix . "ws_s"] = $this->fp;
			if ($timeout === false || $timeout > $this->defaultkeepalive)  $timeout = $this->defaultkeepalive;

			foreach ($this->clients as $id => $client)
			{
				if ($client->writedata === "")  $readfps[$prefix . "ws_c_" . $id] = $client->fp;

				if ($client->writedata !== "" || ($client->websocket !== false && $client->websocket->NeedsWrite()))  $writefps[$prefix . "ws_c_" . $id] = $client->fp;

				if ($client->websocket !== false)
				{
					$timeout2 = $client->websocket->GetKeepAliveTimeout();
					if ($timeout > $timeout2)  $timeout = $timeout2;
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

		// Handles new connections, the initial conversation, basic packet management, and timeouts.
		// Can wait on more streams than just sockets and/or more sockets.  Useful for waiting on other resources.
		// 'ws_s' and the 'ws_c_' prefix are reserved.
		// Returns an array of clients that may need more processing.
		public function Wait($timeout = false, $readfps = array(), $writefps = array(), $exceptfps = NULL)
		{
			$this->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);

			$result = array("success" => true, "clients" => array(), "removed" => array(), "readfps" => array(), "writefps" => array(), "exceptfps" => array());
			if (!count($readfps) && !count($writefps))  return $result;

			$result2 = self::FixedStreamSelect($readfps, $writefps, $exceptfps, $timeout);
			if ($result2 === false)  return array("success" => false, "error" => self::WSTranslate("Wait() failed due to stream_select() failure.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed");

			// Return handles that were being waited on.
			$result["readfps"] = $readfps;
			$result["writefps"] = $writefps;
			$result["exceptfps"] = $exceptfps;

			$this->ProcessWaitResult($result);

			return $result;
		}

		protected function ProcessWaitResult(&$result)
		{
			// Handle new connections.
			if (isset($result["readfps"]["ws_s"]))
			{
				while (($fp = @stream_socket_accept($this->fp, 0)) !== false)
				{
					// Enable non-blocking mode.
					stream_set_blocking($fp, 0);

					$this->InitNewClient($fp);
				}

				unset($result["readfps"]["ws_s"]);
			}

			// Handle clients in the read queue.
			foreach ($result["readfps"] as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 6 || substr($cid, 0, 5) !== "ws_c_")  continue;

				$id = (int)substr($cid, 5);

				if (!isset($this->clients[$id]))  continue;

				$client = $this->clients[$id];

				if ($client->websocket !== false)
				{
					$this->ProcessClientQueuesAndTimeoutState($result, $id, true, isset($result["writefps"][$cid]));

					// Remove active WebSocket clients from the write queue.
					unset($result["writefps"][$cid]);
				}
				else
				{
					$result2 = @fread($fp, 8192);
					if ($result2 === false || ($result2 === "" && feof($fp)))
					{
						@fclose($fp);

						unset($this->clients[$id]);
					}
					else
					{
						$client->readdata .= $result2;

						if (strlen($client->readdata) > 100000)
						{
							// Bad header size.  Just kill the connection.
							@fclose($fp);

							unset($this->clients[$id]);
						}
						else
						{
							while (($pos = strpos($client->readdata, "\n")) !== false)
							{
								// Retrieve the next line of input.
								$line = rtrim(substr($client->readdata, 0, $pos));
								$client->readdata = (string)substr($client->readdata, $pos + 1);

								if ($client->request === false)  $client->request = trim($line);
								else if ($line !== "")
								{
									// Process the header.
									if ($client->lastheader != "" && (substr($line, 0, 1) == " " || substr($line, 0, 1) == "\t"))  $client->headers[$client->lastheader] .= $header;
									else
									{
										$pos = strpos($line, ":");
										if ($pos === false)  $pos = strlen($line);
										$client->lastheader = self::HeaderNameCleanup(substr($line, 0, $pos));
										$client->headers[$client->lastheader] = ltrim(substr($line, $pos + 1));
									}
								}
								else
								{
									// Headers have all been received.  Process the client request.
									$request = $client->request;
									$pos = strpos($request, " ");
									if ($pos === false)  $pos = strlen($request);
									$method = (string)substr($request, 0, $pos);
									$request = trim(substr($request, $pos));

									$pos = strrpos($request, " ");
									if ($pos === false)  $pos = strlen($request);
									$path = (string)substr($request, 0, $pos);
									if ($path === "")  $path = "/";

									if (isset($client->headers["Host"]))  $client->headers["Host"] = preg_replace('/[^a-z0-9.:\[\]_-]/', "", strtolower($client->headers["Host"]));

									$client->path = $path;
									$client->url = "ws://" . (isset($client->headers["Host"]) ? $client->headers["Host"] : "localhost") . $path;

									$this->ProcessInitialResponse($method, $path, $client);

									break;
								}
							}
						}
					}
				}

				unset($result["readfps"][$cid]);
			}

			// Handle remaining clients in the write queue.
			foreach ($result["writefps"] as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 6 || substr($cid, 0, 5) !== "ws_c_")  continue;

				$id = (int)substr($cid, 5);

				if (!isset($this->clients[$id]))  continue;

				$client = $this->clients[$id];

				if ($client->writedata === "")  $this->ProcessClientQueuesAndTimeoutState($result, $id, false, true);
				else
				{
					$result2 = @fwrite($fp, $client->writedata);
					if ($result2 === false || ($result2 === "" && feof($fp)))
					{
						@fclose($fp);

						unset($this->clients[$id]);
					}
					else if ($result2 === 0)  $this->ProcessClientQueuesAndTimeoutState($result, $id, true, false, 1);
					else
					{
						$client->writedata = (string)substr($client->writedata, $result2);

						// Let the application know about the new client or close the connection if the WebSocket Upgrade request failed.
						if ($client->writedata === "")
						{
							if ($client->websocket->GetStream() !== false)  $result["clients"][$id] = $client;
							else
							{
								@fclose($fp);

								unset($this->clients[$id]);
							}
						}
					}
				}

				unset($result["writefps"][$cid]);
			}

			// Handle client timeouts.
			$ts = time();
			if ($this->lasttimeoutcheck <= $ts - 5)
			{
				foreach ($this->clients as $id => $client)
				{
					if (!isset($result["clients"][$id]) && $client->writedata === "" && $client->websocket !== false)
					{
						$this->ProcessClientQueuesAndTimeoutState($result, $id, false, false);
					}
				}

				$this->lasttimeoutcheck = $ts;
			}
		}

		protected function ProcessClientQueuesAndTimeoutState(&$result, $id, $read, $write, $readsize = 65536)
		{
			$client = $this->clients[$id];

			$result2 = $client->websocket->ProcessQueuesAndTimeoutState($read, $write, $readsize);
			if ($result2["success"])  $result["clients"][$id] = $client;
			else
			{
				$result["removed"][$id] = array("result" => $result2, "client" => $client);

				$this->RemoveClient($id);
			}
		}

		public function GetClients()
		{
			return $this->clients;
		}

		public function NumClients()
		{
			return count($this->clients);
		}

		public function UpdateClientState($id)
		{
		}

		public function GetClient($id)
		{
			return (isset($this->clients[$id]) ? $this->clients[$id] : false);
		}

		public function RemoveClient($id)
		{
			if (isset($this->clients[$id]))
			{
				$client = $this->clients[$id];

				// Remove the client.
				if ($client->websocket->GetStream() !== false)
				{
					$client->websocket->Disconnect();
					$client->websocket = false;
					$client->fp = false;
				}

				if ($client->fp !== false)  @fclose($client->fp);

				unset($this->clients[$id]);
			}
		}

		public function ProcessWebServerClientUpgrade($webserver, $client)
		{
			if (!($client instanceof WebServer_Client))  return false;

			if (!$client->requestcomplete || $client->mode === "handle_response")  return false;
			if ($client->request["method"] !== "GET" || !isset($client->headers["Connection"]) || stripos($client->headers["Connection"], "upgrade") === false || !isset($client->headers["Upgrade"]) || stripos($client->headers["Upgrade"], "websocket") === false)  return false;

			// Create an equivalent WebSocket server client class.
			$webserver->DetachClient($client->id);

			$method = $client->request["method"];
			$path = $client->request["path"];

			$client2 = $this->InitNewClient($client->fp);
			$client2->request = $client->request["line"];
			$client2->headers = $client->headers;
			$client2->path = $path;
			$client2->url = "ws://" . (isset($client->headers["Host"]) ? $client->headers["Host"] : "localhost") . $path;

			$client2->appdata = $client->appdata;

			$this->ProcessInitialResponse($method, $path, $client2);

			return $client2->id;
		}

		public static function HeaderNameCleanup($name)
		{
			return preg_replace('/\s+/', "-", ucwords(strtolower(trim(preg_replace('/[^A-Za-z0-9 ]/', " ", $name)))));
		}

		public static function WSTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>