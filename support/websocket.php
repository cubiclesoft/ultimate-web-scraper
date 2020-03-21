<?php
	// CubicleSoft PHP WebSocket class.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	// Implements RFC 6455 (WebSocket protocol).
	// Requires the CubicleSoft PHP HTTP/HTTPS class.
	// Requires the CubicleSoft PHP WebBrowser class.
	class WebSocket
	{
		private $fp, $client, $extensions, $csprng, $state, $closemode;
		private $readdata, $maxreadframesize, $readmessages, $maxreadmessagesize;
		private $writedata, $writemessages, $keepalive, $lastkeepalive, $keepalivesent;
		private $rawrecvsize, $rawsendsize;

		const KEY_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

		const STATE_CONNECTING = 0;
		const STATE_OPEN = 1;
		const STATE_CLOSE = 2;

		const CLOSE_IMMEDIATELY = 0;
		const CLOSE_AFTER_CURRENT_MESSAGE = 1;
		const CLOSE_AFTER_ALL_MESSAGES = 2;

		const FRAMETYPE_CONTINUATION = 0x00;
		const FRAMETYPE_TEXT = 0x01;
		const FRAMETYPE_BINARY = 0x02;

		const FRAMETYPE_CONNECTION_CLOSE = 0x08;
		const FRAMETYPE_PING = 0x09;
		const FRAMETYPE_PONG = 0x0A;

		public function __construct()
		{
			$this->Reset();
		}

		public function __destruct()
		{
			$this->Disconnect();
		}

		public function Reset()
		{
			$this->fp = false;
			$this->client = true;
			$this->extensions = array();
			$this->csprng = false;
			$this->state = self::STATE_CONNECTING;
			$this->closemode = self::CLOSE_IMMEDIATELY;
			$this->readdata = "";
			$this->maxreadframesize = 2000000;
			$this->readmessages = array();
			$this->maxreadmessagesize = 10000000;
			$this->writedata = "";
			$this->writemessages = array();
			$this->keepalive = 30;
			$this->lastkeepalive = time();
			$this->keepalivesent = false;
			$this->rawrecvsize = 0;
			$this->rawsendsize = 0;
		}

		public function SetServerMode()
		{
			$this->client = false;
		}

		public function SetClientMode()
		{
			$this->client = true;
		}

		public function SetExtensions($extensions)
		{
			$this->extensions = (array)$extensions;
		}

		public function SetCloseMode($mode)
		{
			$this->closemode = $mode;
		}

		public function SetKeepAliveTimeout($keepalive)
		{
			$this->keepalive = (int)$keepalive;
		}

		public function GetKeepAliveTimeout()
		{
			return $this->keepalive;
		}

		public function SetMaxReadFrameSize($maxsize)
		{
			$this->maxreadframesize = (is_bool($maxsize) ? false : (int)$maxsize);
		}

		public function SetMaxReadMessageSize($maxsize)
		{
			$this->maxreadmessagesize = (is_bool($maxsize) ? false : (int)$maxsize);
		}

		public function GetRawRecvSize()
		{
			return $this->rawrecvsize;
		}

		public function GetRawSendSize()
		{
			return $this->rawsendsize;
		}

		public function Connect($url, $origin, $options = array(), $web = false)
		{
			$this->Disconnect();

			if (class_exists("CSPRNG", false) && $this->csprng === false)  $this->csprng = new CSPRNG();

			if (isset($options["connected_fp"]) && is_resource($options["connected_fp"]))  $this->fp = $options["connected_fp"];
			else
			{
				if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

				// Use WebBrowser to initiate the connection.
				if ($web === false)  $web = new WebBrowser();

				// Transform URL.
				$url2 = HTTP::ExtractURL($url);
				if ($url2["scheme"] != "ws" && $url2["scheme"] != "wss")  return array("success" => false, "error" => self::WSTranslate("WebSocket::Connect() only supports the 'ws' and 'wss' protocols."), "errorcode" => "protocol_check");
				$url2["scheme"] = str_replace("ws", "http", $url2["scheme"]);
				$url2 = HTTP::CondenseURL($url2);

				// Generate correct request headers.
				if (!isset($options["headers"]))  $options["headers"] = array();
				$options["headers"]["Connection"] = "keep-alive, Upgrade";
				if ($origin != "")  $options["headers"]["Origin"] = $origin;
				$options["headers"]["Pragma"] = "no-cache";
				$key = base64_encode($this->PRNGBytes(16));
				$options["headers"]["Sec-WebSocket-Key"] = $key;
				$options["headers"]["Sec-WebSocket-Version"] = "13";
				$options["headers"]["Upgrade"] = "websocket";

				// No async support for connecting at this time.  Async mode is enabled AFTER connecting though.
				unset($options["async"]);

				// Connect to the WebSocket.
				$result = $web->Process($url2, $options);
				if (!$result["success"])  return $result;
				if ($result["response"]["code"] != 101)  return array("success" => false, "error" => self::WSTranslate("WebSocket::Connect() failed to connect to the WebSocket.  Server returned:  %s %s", $result["response"]["code"], $result["response"]["meaning"]), "errorcode" => "incorrect_server_response");
				if (!isset($result["headers"]["Sec-Websocket-Accept"]))  return array("success" => false, "error" => self::WSTranslate("Server failed to include a 'Sec-WebSocket-Accept' header in its response to the request."), "errorcode" => "missing_server_websocket_accept_header");

				// Verify the Sec-WebSocket-Accept response.
				if ($result["headers"]["Sec-Websocket-Accept"][0] !== base64_encode(sha1($key . self::KEY_GUID, true)))  return array("success" => false, "error" => self::WSTranslate("The server's 'Sec-WebSocket-Accept' header is invalid."), "errorcode" => "invalid_server_websocket_accept_header");

				$this->fp = $result["fp"];
			}

			// Enable non-blocking mode.
			stream_set_blocking($this->fp, 0);

			$this->state = self::STATE_OPEN;

			$this->readdata = "";
			$this->readmessages = array();
			$this->writedata = "";
			$this->writemessages = array();
			$this->lastkeepalive = time();
			$this->keepalivesent = false;
			$this->rawrecvsize = 0;
			$this->rawsendsize = 0;

			return array("success" => true);
		}

		public function Disconnect()
		{
			if ($this->fp !== false && $this->state === self::STATE_OPEN)
			{
				if ($this->closemode === self::CLOSE_IMMEDIATELY)  $this->writemessages = array();
				else if ($this->closemode === self::CLOSE_AFTER_CURRENT_MESSAGE)  $this->writemessages = array_slice($this->writemessages, 0, 1);

				$this->state = self::STATE_CLOSE;

				$this->Write("", self::FRAMETYPE_CONNECTION_CLOSE, true, $this->client);

				$this->Wait($this->client ? false : 0);
			}

			if ($this->fp !== false)
			{
				@fclose($this->fp);

				$this->fp = false;
			}

			$this->state = self::STATE_CONNECTING;
			$this->readdata = "";
			$this->readmessages = array();
			$this->writedata = "";
			$this->writemessages = array();
			$this->lastkeepalive = time();
			$this->keepalivesent = false;
		}

		// Reads the next message or message fragment (depending on $finished).  Returns immediately unless $wait is not false.
		public function Read($finished = true, $wait = false)
		{
			if ($this->fp === false || $this->state === self::STATE_CONNECTING)  return array("success" => false, "error" => self::WSTranslate("Connection not established."), "errorcode" => "no_connection");

			if ($wait)
			{
				while (!count($this->readmessages) || ($finished && !$this->readmessages[0]["fin"]))
				{
					$result = $this->Wait();
					if (!$result["success"])  return $result;
				}
			}

			$data = false;

			if (count($this->readmessages))
			{
				if ($finished)
				{
					if ($this->readmessages[0]["fin"])  $data = array_shift($this->readmessages);
				}
				else
				{
					$data = $this->readmessages[0];

					$this->readmessages[0]["payload"] = "";
				}
			}

			return array("success" => true, "data" => $data);
		}

		// Adds the message to the write queue.  Returns immediately unless $wait is not false.
		public function Write($message, $frametype, $last = true, $wait = false, $pos = false)
		{
			if ($this->fp === false || $this->state === self::STATE_CONNECTING)  return array("success" => false, "error" => self::WSTranslate("Connection not established."), "errorcode" => "no_connection");

			$message = (string)$message;

			$y = count($this->writemessages);
			$lastcompleted = (!$y || $this->writemessages[$y - 1]["fin"]);
			if ($frametype >= 0x08 || $lastcompleted)
			{
				if ($frametype >= 0x08)  $last = true;
				else  $pos = false;

				$frame = array(
					"fin" => (bool)$last,
					"framessent" => 0,
					"opcode" => $frametype,
					"payloads" => array($message)
				);

				array_splice($this->writemessages, ($pos !== false ? $pos : $y), 0, array($frame));
			}
			else
			{
				if ($frametype !== $this->writemessages[$y - 1]["opcode"])  return array("success" => false, "error" => self::WSTranslate("Mismatched frame type (opcode) specified."), "errorcode" => "mismatched_frame_type");

				$this->writemessages[$y - 1]["fin"] = (bool)$last;
				$this->writemessages[$y - 1]["payloads"][] = $message;
			}

			if ($wait)
			{
				while ($this->NeedsWrite())
				{
					$result = $this->Wait();
					if (!$result["success"])  return $result;
				}
			}

			return array("success" => true);
		}

		public function NeedsWrite()
		{
			$this->FillWriteData();

			return ($this->writedata !== "");
		}

		public function NumWriteMessages()
		{
			return count($this->writemessages);
		}

		// Dangerous but allows for stream_select() calls on multiple, separate stream handles.
		public function GetStream()
		{
			return $this->fp;
		}

		// Waits until one or more events time out, handles reading and writing, processes the queues (handle control types automatically), and returns the latest status.
		public function Wait($timeout = false)
		{
			if ($this->fp === false || $this->state === self::STATE_CONNECTING)  return array("success" => false, "error" => self::WSTranslate("Connection not established."), "errorcode" => "no_connection");

			$result = $this->ProcessReadData();
			if (!$result["success"])  return $result;

			$this->FillWriteData();

			$readfp = array($this->fp);
			$writefp = ($this->writedata !== "" ? array($this->fp) : NULL);
			$exceptfp = NULL;
			if ($timeout === false || $timeout > $this->keepalive)  $timeout = $this->keepalive;
			$result = @stream_select($readfp, $writefp, $exceptfp, $timeout);
			if ($result === false)  return array("success" => false, "error" => self::WSTranslate("Wait() failed due to stream_select() failure.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed");

			// Process queues and timeouts.
			$result = $this->ProcessQueuesAndTimeoutState(($result > 0 && count($readfp)), ($result > 0 && $writefp !== NULL && count($writefp)));

			return $result;
		}

		// A mostly internal function.  Useful for managing multiple simultaneous WebSocket connections.
		public function ProcessQueuesAndTimeoutState($read, $write, $readsize = 65536)
		{
			if ($this->fp === false || $this->state === self::STATE_CONNECTING)  return array("success" => false, "error" => self::WSTranslate("Connection not established."), "errorcode" => "no_connection");

			if ($read)
			{
				$result = @fread($this->fp, $readsize);
				if ($result === false || ($result === "" && feof($this->fp)))  return array("success" => false, "error" => self::WSTranslate("ProcessQueuesAndTimeoutState() failed due to fread() failure.  Most likely cause:  Connection failure."), "errorcode" => "fread_failed");

				if ($result !== "")
				{
					$this->rawrecvsize += strlen($result);
					$this->readdata .= $result;

					if ($this->maxreadframesize !== false && strlen($this->readdata) > $this->maxreadframesize)  return array("success" => false, "error" => self::WSTranslate("ProcessQueuesAndTimeoutState() failed due to peer sending a single frame exceeding %s bytes of data.", $this->maxreadframesize), "errorcode" => "max_read_frame_size_exceeded");

					$result = $this->ProcessReadData();
					if (!$result["success"])  return $result;

					$this->lastkeepalive = time();
					$this->keepalivesent = false;
				}
			}

			if ($write)
			{
				$result = @fwrite($this->fp, $this->writedata);
				if ($result === false || ($this->writedata === "" && feof($this->fp)))  return array("success" => false, "error" => self::WSTranslate("ProcessQueuesAndTimeoutState() failed due to fwrite() failure.  Most likely cause:  Connection failure."), "errorcode" => "fwrite_failed");

				if ($result)
				{
					$this->rawsendsize += $result;
					$this->writedata = (string)substr($this->writedata, $result);

					$this->lastkeepalive = time();
					$this->keepalivesent = false;
				}
			}

			// Handle timeout state.
			if ($this->lastkeepalive < time() - $this->keepalive)
			{
				if ($this->keepalivesent)  return array("success" => false, "error" => self::WSTranslate("ProcessQueuesAndTimeoutState() failed due to non-response from peer to ping frame.  Most likely cause:  Connection failure."), "errorcode" => "ping_failed");
				else
				{
					$result = $this->Write(time(), self::FRAMETYPE_PING, true, false, 0);
					if (!$result["success"])  return $result;

					$this->lastkeepalive = time();
					$this->keepalivesent = true;
				}
			}

			return array("success" => true);
		}

		protected function ProcessReadData()
		{
			while (($frame = $this->ReadFrame()) !== false)
			{
				// Verify that the opcode is probably valid.
				if (($frame["opcode"] >= 0x03 && $frame["opcode"] <= 0x07) || $frame["opcode"] >= 0x0B)  return array("success" => false, "error" => self::WSTranslate("Invalid frame detected.  Bad opcode 0x%02X.", $frame["opcode"]), "errorcode" => "bad_frame_opcode");

				// No extension support (yet).
				if ($frame["rsv1"] || $frame["rsv2"] || $frame["rsv3"])  return array("success" => false, "error" => self::WSTranslate("Invalid frame detected.  One or more reserved extension bits are set."), "errorcode" => "bad_reserved_bits_set");

				if ($frame["opcode"] >= 0x08)
				{
					// Handle the control frame.
					if (!$frame["fin"])  return array("success" => false, "error" => self::WSTranslate("Invalid frame detected.  Fragmented control frame was received."), "errorcode" => "bad_control_frame");

					if ($frame["opcode"] === self::FRAMETYPE_CONNECTION_CLOSE)
					{
						if ($this->state === self::STATE_CLOSE)
						{
							// Already sent the close state.
							@fclose($this->fp);
							$this->fp = false;

							return array("success" => false, "error" => self::WSTranslate("Connection closed by peer."), "errorcode" => "connection_closed");
						}
						else
						{
							// Change the state to close and send the connection close response to the peer at the appropriate time.
							if ($this->closemode === self::CLOSE_IMMEDIATELY)  $this->writemessages = array();
							else if ($this->closemode === self::CLOSE_AFTER_CURRENT_MESSAGE)  $this->writemessages = array_slice($this->writemessages, 0, 1);

							$this->state = self::STATE_CLOSE;

							$result = $this->Write("", self::FRAMETYPE_CONNECTION_CLOSE);
							if (!$result["success"])  return $result;
						}
					}
					else if ($frame["opcode"] === self::FRAMETYPE_PING)
					{
						if ($this->state !== self::STATE_CLOSE)
						{
							// Received a ping.  Respond with a pong with the same payload.
							$result = $this->Write($frame["payload"], self::FRAMETYPE_PONG, true, false, 0);
							if (!$result["success"])  return $result;
						}
					}
					else if ($frame["opcode"] === self::FRAMETYPE_PONG)
					{
						// Do nothing.
					}
				}
				else
				{
					// Add this frame to the read message queue.
					$lastcompleted = (!count($this->readmessages) || $this->readmessages[count($this->readmessages) - 1]["fin"]);
					if ($lastcompleted)
					{
						// Make sure the new frame is the start of a fragment or is not fragemented.
						if ($frame["opcode"] === self::FRAMETYPE_CONTINUATION)  return array("success" => false, "error" => self::WSTranslate("Invalid frame detected.  Fragment continuation frame was received at the start of a fragment."), "errorcode" => "bad_continuation_frame");

						$this->readmessages[] = $frame;
					}
					else
					{
						// Make sure the frame is a continuation frame.
						if ($frame["opcode"] !== self::FRAMETYPE_CONTINUATION)  return array("success" => false, "error" => self::WSTranslate("Invalid frame detected.  Fragment continuation frame was not received for a fragment."), "errorcode" => "missing_continuation_frame");

						$this->readmessages[count($this->readmessages) - 1]["fin"] = $frame["fin"];
						$this->readmessages[count($this->readmessages) - 1]["payload"] .= $frame["payload"];
					}

					if ($this->maxreadmessagesize !== false && strlen($this->readmessages[count($this->readmessages) - 1]["payload"]) > $this->maxreadmessagesize)  return array("success" => false, "error" => self::WSTranslate("Peer sent a single message exceeding %s bytes of data.", $this->maxreadmessagesize), "errorcode" => "max_read_message_size_exceeded");
				}

//var_dump($frame);
			}

			return array("success" => true);
		}

		// Parses the current input data to see if there is enough information to extract a single frame.
		// Does not do any error checking beyond loading the frame and decoding any masked data.
		protected function ReadFrame()
		{
			if (strlen($this->readdata) < 2)  return false;

			$chr = ord($this->readdata[0]);
			$fin = (($chr & 0x80) ? true : false);
			$rsv1 = (($chr & 0x40) ? true : false);
			$rsv2 = (($chr & 0x20) ? true : false);
			$rsv3 = (($chr & 0x10) ? true : false);
			$opcode = $chr & 0x0F;

			$chr = ord($this->readdata[1]);
			$mask = (($chr & 0x80) ? true : false);
			$length = $chr & 0x7F;
			if ($length == 126)  $start = 4;
			else if ($length == 127)  $start = 10;
			else  $start = 2;

			if (strlen($this->readdata) < $start + ($mask ? 4 : 0))  return false;

			// Frame minus payload calculated.
			if ($length == 126)  $length = self::UnpackInt(substr($this->readdata, 2, 2));
			else if ($length == 127)  $length = self::UnpackInt(substr($this->readdata, 2, 8));

			if ($mask)
			{
				$maskingkey = substr($this->readdata, $start, 4);
				$start += 4;
			}

			if (strlen($this->readdata) < $start + $length)  return false;

			$payload = substr($this->readdata, $start, $length);

			$this->readdata = substr($this->readdata, $start + $length);

			if ($mask)
			{
				// Decode the payload.
				for ($x = 0; $x < $length; $x++)
				{
					$payload[$x] = chr(ord($payload[$x]) ^ ord($maskingkey[$x % 4]));
				}
			}

			$result = array(
				"fin" => $fin,
				"rsv1" => $rsv1,
				"rsv2" => $rsv2,
				"rsv3" => $rsv3,
				"opcode" => $opcode,
				"mask" => $mask,
				"payload" => $payload
			);

			return $result;
		}

		// Converts messages in the queue to a data stream of frames.
		protected function FillWriteData()
		{
			while (strlen($this->writedata) < 65536 && count($this->writemessages) && count($this->writemessages[0]["payloads"]))
			{
				$payload = array_shift($this->writemessages[0]["payloads"]);

				$fin = ($this->writemessages[0]["fin"] && !count($this->writemessages[0]["payloads"]));

				if ($this->writemessages[0]["framessent"] === 0)  $opcode = $this->writemessages[0]["opcode"];
				else  $opcode = self::FRAMETYPE_CONTINUATION;

				$this->WriteFrame($fin, $opcode, $payload);

				$this->writemessages[0]["framessent"]++;

				if ($fin)  array_shift($this->writemessages);
			}
		}

		// Generates the actual frame data to be sent.
		protected function WriteFrame($fin, $opcode, $payload)
		{
			$rsv1 = false;
			$rsv2 = false;
			$rsv3 = false;
			$mask = $this->client;

			$data = chr(($fin ? 0x80 : 0x00) | ($rsv1 ? 0x40 : 0x00) | ($rsv2 ? 0x20 : 0x00) | ($rsv3 ? 0x10 : 0x00) | ($opcode & 0x0F));

			if (strlen($payload) < 126)  $length = strlen($payload);
			else if (strlen($payload) < 65536)  $length = 126;
			else  $length = 127;

			$data .= chr(($mask ? 0x80 : 0x00) | ($length & 0x7F));

			if ($length === 126)  $data .= pack("n", strlen($payload));
			else if ($length === 127)  $data .= self::PackInt64(strlen($payload));

			if ($mask)
			{
				$maskingkey = $this->PRNGBytes(4);
				$data .= $maskingkey;

				// Encode the payload.
				$y = strlen($payload);
				for ($x = 0; $x < $y; $x++)
				{
					$payload[$x] = chr(ord($payload[$x]) ^ ord($maskingkey[$x % 4]));
				}
			}

			$data .= $payload;

			$this->writedata .= $data;
		}

		// This function follows the specification IF CSPRNG is available, but it isn't necessary to do so.
		protected function PRNGBytes($length)
		{
			if ($this->csprng !== false)  $result = $this->csprng->GetBytes($length);
			else
			{
				$result = "";
				while (strlen($result) < $length)  $result .= chr(mt_rand(0, 255));
			}

			return $result;
		}

		public static function UnpackInt($data)
		{
			if ($data === false)  return false;

			if (strlen($data) == 2)  $result = unpack("n", $data);
			else if (strlen($data) == 4)  $result = unpack("N", $data);
			else if (strlen($data) == 8)
			{
				$result = 0;
				for ($x = 0; $x < 8; $x++)
				{
					$result = ($result * 256) + ord($data[$x]);
				}

				return $result;
			}
			else  return false;

			return $result[1];
		}

		public static function PackInt64($num)
		{
			$result = "";

			if (is_int(2147483648))  $floatlim = 9223372036854775808;
			else  $floatlim = 2147483648;

			if (is_float($num))
			{
				$num = floor($num);
				if ($num < (double)$floatlim)  $num = (int)$num;
			}

			while (is_float($num))
			{
				$byte = (int)fmod($num, 256);
				$result = chr($byte) . $result;

				$num = floor($num / 256);
				if (is_float($num) && $num < (double)$floatlim)  $num = (int)$num;
			}

			while ($num > 0)
			{
				$byte = $num & 0xFF;
				$result = chr($byte) . $result;
				$num = $num >> 8;
			}

			$result = str_pad($result, 8, "\x00", STR_PAD_LEFT);
			$result = substr($result, -8);

			return $result;
		}

		public static function WSTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>