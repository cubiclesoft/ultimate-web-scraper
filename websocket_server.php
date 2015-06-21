<?php
	// CubicleSoft PHP WebSocketServer class.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	// Make sure PHP doesn't introduce weird limitations.
	ini_set("memory_limit", "-1");
	set_time_limit(0);

	// Requires the CubicleSoft PHP WebSocket class.
	class WebSocketServer
	{
		private $fp, $clients, $nextclientid, $websocketclass;
		private $defaultclosemode, $defaultmaxreadframesize, $defaultmaxreadmessagesize, $defaultkeepalive;

		public function __construct()
		{
			$this->Reset();
		}

		public function Reset()
		{
			$this->fp = false;
			$this->clients = array();
			$this->nextclientid = 1;
			$this->websocketclass = "WebSocket";

			$this->defaultclosemode = WebSocket::CLOSE_IMMEDIATELY;
			$this->defaultmaxreadframesize = 2000000;
			$this->defaultmaxreadmessagesize = 10000000;
			$this->defaultkeepalive = 30;
		}

		public function __destruct()
		{
			$this->Stop();
		}

		public function SetWebSocketClass($newclass)
		{
			if (class_exists($newclass))  $this->websocketclass = $newclass;
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
		// $host is usually 0.0.0.0 or 127.0.0.1 for IPv4 and [::0] and [fe80::1] for IPv6.
		public function Start($host, $port)
		{
			$this->Stop();

			$this->fp = stream_socket_server("tcp://" . $host . ":" . $port, $errornum, $errorstr);
			if ($this->fp === false)  return array("success" => false, "error" => HTTP::HTTPTranslate("Bind() failed.  Reason:  %s (%d)", $errorstr, $errornum), "errorcode" => "bind_failed");

			// Enable non-blocking mode.
			stream_set_blocking($this->fp, 0);

			return array("success" => true);
		}

		public function Stop()
		{
			if ($this->fp !== false)
			{
				foreach ($this->clients as $client)
				{
					if ($client["websocket"] !== false)  $client["websocket"]->Disconnect();
					else  fclose($client["fp"]);
				}

				fclose($this->fp);

				$this->clients = array();
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

			if ($method !== "GET")  $result .= "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
			else if (!isset($client["headers"]["Host"]) || !isset($client["headers"]["Upgrade"]) || stripos($client["headers"]["Upgrade"], "websocket") === false || !isset($client["headers"]["Connection"]) || stripos($client["headers"]["Connection"], "upgrade") === false || !isset($client["headers"]["Sec-Websocket-Key"]))
			{
				$result .= "HTTP/1.1 400 Bad Request\r\n\r\n";
			}
			else if (!isset($client["headers"]["Sec-Websocket-Version"]) || $client["headers"]["Sec-Websocket-Version"] != 13)
			{
				$result .= "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocket-Version: 13\r\n\r\n";
			}
			else if (!isset($client["headers"]["Origin"]))
			{
				$result .= "HTTP/1.1 403 Forbidden\r\n\r\n";
			}

			return $result;
		}

		// Return whatever additional HTTP headers are needed here.
		protected function ProcessAcceptedConnection($method, $path, $client)
		{
			return "";
		}

		// Handles new connections, the initial conversation, basic packet management, and timeouts.
		// Can wait on more streams than just sockets and/or more sockets.  Useful for waiting on other resources.
		// 'ws_s' and the 'ws_c_' prefix are reserved.
		// Returns an array of clients that may need more processing.
		public function Wait($timeout = false, $readfps = array(), $writefps = array(), $exceptfps = NULL)
		{
			$readfps["ws_s"] = $this->fp;
			if ($timeout === false || $timeout > $this->defaultkeepalive)  $timeout = $this->defaultkeepalive;

			foreach ($this->clients as $id => &$client)
			{
				if ($client["writedata"] === "")  $readfps["ws_c_" . $id] = $client["fp"];

				if ($client["writedata"] !== "" || ($client["websocket"] !== false && $client["websocket"]->NeedsWrite()))  $writefps["ws_c_" . $id] = $client["fp"];

				if ($client["websocket"] !== false)
				{
					$timeout2 = $client["websocket"]->GetKeepAliveTimeout();
					if ($timeout > $timeout2)  $timeout = $timeout2;
				}
			}

			$result = array("success" => true, "clients" => array(), "removed" => array(), "readfps" => array(), "writefps" => array(), "exceptfps" => array());

			$result2 = @stream_select($readfps, $writefps, $exceptfps, $timeout);
			if ($result2 === false)  return array("success" => false, "error" => HTTP::HTTPTranslate("Wait() failed due to stream_select() failure.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed");

			// Handle new connections.
			if (isset($readfps["ws_s"]))
			{
				while (($fp = @stream_socket_accept($this->fp, 0)) !== false)
				{
					// Enable non-blocking mode.
					stream_set_blocking($fp, 0);

					$this->clients[$this->nextclientid] = array(
						"id" => $this->nextclientid,
						"readdata" => "",
						"writedata" => "",
						"request" => false,
						"path" => "",
						"url" => "",
						"headers" => array(),
						"lastheader" => "",
						"websocket" => false,
						"fp" => $fp
					);

					$this->nextclientid++;
				}

				unset($readfps["s"]);
			}

			// Handle clients in the read queue.
			foreach ($readfps as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 6 || substr($cid, 0, 5) !== "ws_c_")  continue;

				$id = (int)substr($cid, 5);

				if (!isset($this->clients[$id]))  continue;

				if ($this->clients[$id]["websocket"] !== false)
				{
					$this->ProcessClientQueuesAndTimeoutState($result, $id, true, isset($writefps[$cid]));

					// Remove active WebSocket clients from the write queue.
					unset($writefps[$cid]);
				}
				else
				{
					$result2 = @fread($fp, 8192);
					if ($result2 === false || feof($fp))
					{
						@fclose($fp);

						unset($this->clients[$id]);
					}
					else
					{
						$this->clients[$id]["readdata"] .= $result2;

						if (strlen($this->clients[$id]["readdata"]) > 100000)
						{
							// Bad header size.  Just kill the connection.
							@fclose($fp);

							unset($this->clients[$id]);
						}
						else
						{
							while (($pos = strpos($this->clients[$id]["readdata"], "\n")) !== false)
							{
								// Retrieve the next line of input.
								$line = rtrim(substr($this->clients[$id]["readdata"], 0, $pos));
								$this->clients[$id]["readdata"] = (string)substr($this->clients[$id]["readdata"], $pos + 1);

								if ($this->clients[$id]["request"] === false)  $this->clients[$id]["request"] = trim($line);
								else if ($line !== "")
								{
									// Process the header.
									if ($this->clients[$id]["lastheader"] != "" && (substr($line, 0, 1) == " " || substr($line, 0, 1) == "\t"))  $this->clients[$id]["headers"][$this->clients[$id]["lastheader"]] .= $header;
									else
									{
										$pos = strpos($line, ":");
										if ($pos === false)  $pos = strlen($line);
										$this->clients[$id]["lastheader"] = HTTP::HeaderNameCleanup(substr($line, 0, $pos));
										$this->clients[$id]["headers"][$this->clients[$id]["lastheader"]] = ltrim(substr($line, $pos + 1));
									}
								}
								else
								{
									// Headers have all been received.  Process the client request.
									$request = $this->clients[$id]["request"];
									$pos = strpos($request, " ");
									if ($pos === false)  $pos = strlen($request);
									$method = (string)substr($request, 0, $pos);
									$request = trim(substr($request, $pos));

									$pos = strrpos($request, " ");
									if ($pos === false)  $pos = strlen($request);
									$path = (string)substr($request, 0, $pos);
									if ($path === "")  $path = "/";

									$this->clients[$id]["path"] = $path;
									$this->clients[$id]["url"] = "ws://" . $client["headers"]["Host"] . $path;

									// Let a derived class handle the new connection (e.g. processing Origin and Host).
									// Since the 'websocketclass' is instantiated AFTER this function, it is possible to switch classes on the fly.
									$this->clients[$id]["writedata"] .= $this->ProcessNewConnection($method, $path, $this->clients[$id]);

									// If an error occurs, the connection will still terminate.
									$this->clients[$id]["websocket"] = new $this->websocketclass();
									$this->clients[$id]["websocket"]->SetCloseMode($this->defaultclosemode);
									$this->clients[$id]["websocket"]->SetKeepAliveTimeout($this->defaultkeepalive);

									// If nothing was output, accept the connection.
									if ($this->clients[$id]["writedata"] === "")
									{
										$this->clients[$id]["writedata"] .= "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n";
										$this->clients[$id]["writedata"] .= "Sec-WebSocket-Accept: " . base64_encode(sha1($this->clients[$id]["headers"]["Sec-Websocket-Key"] . WebSocket::KEY_GUID, true)) . "\r\n";
										$this->clients[$id]["writedata"] .= $this->ProcessAcceptedConnection($method, $path, $this->clients[$id]);
										$this->clients[$id]["writedata"] .= "\r\n";

										// Finish class initialization.
										$this->clients[$id]["websocket"]->SetServerMode();
										$this->clients[$id]["websocket"]->SetMaxReadFrameSize($this->defaultmaxreadframesize);
										$this->clients[$id]["websocket"]->SetMaxReadMessageSize($this->defaultmaxreadmessagesize);

										// Set the socket in the WebSocket class.
										$this->clients[$id]["websocket"]->Connect("", "", "", array("fp" => $fp));
									}

									break;
								}
							}
						}
					}
				}

				unset($readfps[$cid]);
			}

			// Handle remaining clients in the write queue.
			foreach ($writefps as $cid => $fp)
			{
				if (!is_string($cid) || strlen($cid) < 6 || substr($cid, 0, 5) !== "ws_c_")  continue;

				$id = (int)substr($cid, 5);

				if (!isset($this->clients[$id]))  continue;

				if ($this->clients[$id]["writedata"] === "")  $this->ProcessClientQueuesAndTimeoutState($result, $id, false, true);
				else
				{
					$result2 = @fwrite($fp, $this->clients[$id]["writedata"]);
					if ($result2 === false || feof($fp))
					{
						@fclose($fp);

						unset($this->clients[$id]);
					}
					else
					{
						$this->clients[$id]["writedata"] = (string)substr($this->clients[$id]["writedata"], $result2);

						// Let the application know about the new client.
						if ($this->clients[$id]["writedata"] === "")  $result["clients"][$id] = $this->clients[$id];
					}
				}

				unset($writefps[$cid]);
			}

			// Handle client timeouts.
			foreach ($this->clients as $id => &$client)
			{
				if (!isset($result["clients"][$id]) && $client["writedata"] === "" && $client["websocket"] !== false)
				{
					$this->ProcessClientQueuesAndTimeoutState($result, $id, false, false);
				}
			}

			// Return any extra handles that were being waited on.
			$result["readfps"] = $readfps;
			$result["writefps"] = $writefps;
			$result["exceptfps"] = $exceptfps;

			return $result;
		}

		protected function ProcessClientQueuesAndTimeoutState(&$result, $id, $read, $write)
		{
			$result2 = $this->clients[$id]["websocket"]->ProcessQueuesAndTimeoutState($read, $write);
			if ($result2["success"])  $result["clients"][$id] = $this->clients[$id];
			else
			{
				// Remove the client.
				if ($this->clients[$id]["websocket"]->GetStream() !== false)
				{
					$this->clients[$id]["websocket"]->Disconnect();
					$this->clients[$id]["websocket"] = false;
					$this->clients[$id]["fp"] = false;
				}

				if ($this->clients[$id]["fp"] !== false)  @fclose($this->clients[$id]["fp"]);

				$result["removed"][$id] = array("result" => $result2, "client" => $this->clients[$id]);

				unset($this->clients[$id]);
			}
		}

		public function GetClients()
		{
			return $this->clients;
		}

		public function GetClient($id)
		{
			return (isset($this->client[$id]) ? $this->client[$id] : false);
		}

		public function RemoveClient($id)
		{
			if (isset($this->clients[$id]))
			{
				// Remove the client.
				if ($this->clients[$id]["websocket"]->GetStream() !== false)
				{
					$this->clients[$id]["websocket"]->Disconnect();
					$this->clients[$id]["websocket"] = false;
					$this->clients[$id]["fp"] = false;
				}

				if ($this->clients[$id]["fp"] !== false)  @fclose($this->clients[$id]["fp"]);

				unset($this->clients[$id]);
			}
		}
	}
?>