<?php
	// CubicleSoft PHP WebSocketServer class with libev support.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	if (!class_exists("WebSocketServer", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/websocket_server.php";

	class LibEvWebSocketServer extends WebSocketServer
	{
		protected $ev_watchers, $ev_read_ready, $ev_write_ready;

		public static function IsSupported()
		{
			$os = php_uname("s");
			$windows = (strtoupper(substr($os, 0, 3)) == "WIN");

			return (extension_loaded("ev") && !$windows);
		}

		public function Reset()
		{
			parent::Reset();

			$this->ev_watchers = array();
		}

		public function Internal_LibEvHandleEvent($watcher, $revents)
		{
			if ($revents & Ev::READ)  $this->ev_read_ready[$watcher->data] = $watcher->fd;
			if ($revents & Ev::WRITE)  $this->ev_write_ready[$watcher->data] = $watcher->fd;
		}

		public function Start($host, $port)
		{
			$result = parent::Start($host, $port);
			if (!$result["success"])  return $result;

			$this->ev_watchers["ws_s"] = new EvIo($this->fp, Ev::READ, array($this, "Internal_LibEvHandleEvent"), "ws_s");

			return $result;
		}

		public function Stop()
		{
			parent::Stop();

			foreach ($this->ev_watchers as $key => $watcher)
			{
				$watcher->stop();
			}

			$this->ev_watchers = array();
		}

		protected function InitNewClient($fp)
		{
			$client = parent::InitNewClient($fp);

			$this->ev_watchers["ws_c_" . $client->id] = new EvIo($client->fp, Ev::READ, array($this, "Internal_LibEvHandleEvent"), "ws_c_" . $client->id);

			return $client;
		}

		public function UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
		{
			if ($this->fp !== false)  $readfps[$prefix . "ws_s"] = $this->fp;
			if ($timeout === false || $timeout > $this->defaultkeepalive)  $timeout = $this->defaultkeepalive;

			foreach ($this->clients as $id => $client)
			{
				if ($client->writedata === "")  $readfps[$prefix . "ws_c_" . $id] = $client->fp;

				if ($client->writedata !== "" || ($client->websocket !== false && $client->websocket->NeedsWrite()))  $writefps[$prefix . "ws_c_" . $id] = $client->fp;
			}
		}

		public function Internal_LibEvTimeout($watcher, $revents)
		{
			Ev::stop(Ev::BREAK_ALL);
		}

		public function Wait($timeout = false, $readfps = array(), $writefps = array(), $exceptfps = NULL)
		{
			if ($timeout === false || $timeout > $this->defaultkeepalive)  $timeout = $this->defaultkeepalive;

			$result = array("success" => true, "clients" => array(), "removed" => array(), "readfps" => array(), "writefps" => array(), "exceptfps" => array());
			if (!count($this->ev_watchers) && !count($readfps) && !count($writefps))  return $result;

			$this->ev_read_ready = array();
			$this->ev_write_ready = array();

			// Temporarily attach other read/write handles.
			$tempwatchers = array();

			foreach ($readfps as $key => $fp)
			{
				$tempwatchers[] = new EvIo($fp, Ev::READ, array($this, "Internal_LibEvHandleEvent"), $key);
			}

			foreach ($writefps as $key => $fp)
			{
				$tempwatchers[] = new EvIo($fp, Ev::WRITE, array($this, "Internal_LibEvHandleEvent"), $key);
			}

			$tempwatchers[] = new EvTimer($timeout, 0, array($this, "Internal_LibEvTimeout"));

			// Wait for one or more events to fire.
			Ev::run(Ev::RUN_ONCE);

			// Remove temporary watchers.
			foreach ($tempwatchers as $watcher)  $watcher->stop();

			// Return handles that were being waited on.
			$result["readfps"] = $this->ev_read_ready;
			$result["writefps"] = $this->ev_write_ready;
			$result["exceptfps"] = (is_array($exceptfps) ? array() : $exceptfps);

			$this->ProcessWaitResult($result);

			// Post-process clients.
			foreach ($result["clients"] as $id => $client)
			{
				$this->UpdateClientState($id);
			}

			return $result;
		}

		public function UpdateClientState($id)
		{
			if (isset($this->clients[$id]))
			{
				$client = $this->clients[$id];

				$events = 0;

				if ($client->writedata === "")  $events = Ev::READ;

				if ($client->writedata !== "" || ($client->websocket !== false && $client->websocket->NeedsWrite()))  $events |= Ev::WRITE;

				$this->ev_watchers["ws_c_" . $id]->set($client->fp, $events);
			}
		}

		public function RemoveClient($id)
		{
			parent::RemoveClient($id);

			if (isset($this->ev_watchers["ws_c_" . $id]))
			{
				$this->ev_watchers["ws_c_" . $id]->stop();

				unset($this->ev_watchers["ws_c_" . $id]);
			}
		}
	}
?>