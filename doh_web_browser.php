<?php
	// CubicleSoft DNS over HTTPS web browser class.
	// (C) 2021 CubicleSoft.  All Rights Reserved.

	if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

	// Requires the CubicleSoft WebBrowser class.
	class DOHWebBrowser extends WebBrowser
	{
		protected $dohapi, $dohhost, $dohtypes, $dohweb, $dohfp;
		protected static $dohcache;

		public function __construct($prevstate = array())
		{
			parent::__construct($prevstate);

			$this->SetDOHAccessInfo("https://cloudflare-dns.com/dns-query");

			if (!is_array(self::$dohcache))  self::$dohcache = array();
		}

		public function SetDOHAccessInfo($dohapi, $dohhost = false, $dohtypes = array("A", "AAAA"))
		{
			$this->dohapi = $dohapi;
			$this->dohhost = $dohhost;
			$this->dohtypes = $dohtypes;
			$this->dohweb = false;
			$this->dohfp = false;
		}

		public function ClearDOHCache()
		{
			self::$dohcache = array();
		}

		public function GetDOHCache()
		{
			return self::$dohcache;
		}

		// Override WebBrowser ProcessState().
		public function ProcessState(&$state)
		{
			if (!isset($state["tempoptions"]["doh_pre_retrievewebpage_callback"]))
			{
				$state["tempoptions"]["doh_pre_retrievewebpage_callback"] = (isset($state["tempoptions"]["pre_retrievewebpage_callback"]) && is_callable($state["tempoptions"]["pre_retrievewebpage_callback"]) ? $state["tempoptions"]["pre_retrievewebpage_callback"] : false);

				$state["tempoptions"]["pre_retrievewebpage_callback"] = array($this, "InternalDNSOverHTTPSHandler");
			}

			return parent::ProcessState($state);
		}

		public function InternalDNSOverHTTPSHandler(&$state)
		{
			// Skip hosts that appear to be IP addresses.
			$host = $state["urlinfo"]["host"];
			if (strpos($host, ":") === false && !preg_match('/^[0-9.]+$/', $host))
			{
				if (isset(self::$dohcache[$host]) && self::$dohcache[$host]["expires"] < time())  unset(self::$dohcache[$host]);

				// Obtain the IP address to connect to and cache it for later reuse.
				if (!isset(self::$dohcache[$host]))
				{
					if ($this->dohweb === false)  $this->dohweb = new WebBrowser();

					foreach ($this->dohtypes as $type)
					{
						$options = array(
							"headers" => array(
								"Accept" => "application/dns-json",
								"Connection" => "Keep-Alive"
							)
						);

						if ($this->dohhost !== false)  $options["headers"]["Host"] = $this->dohhost;
						if ($this->dohfp !== false)  $options["fp"] = $this->dohfp;

						$url2 = $this->dohapi . "?name=" . urlencode($host) . "&type=" . urlencode($type);

						$result = $this->dohweb->Process($url2, $options);
						if ($result["success"] && $result["response"]["code"] == 200)
						{
							if (isset($result["fp"]))  $this->dohfp = $result["fp"];

							$data = @json_decode($result["body"], true);
							if (is_array($data) && $data["Status"] == 0 && count($data["Answer"]))
							{
								self::$dohcache[$host] = $data["Answer"][0];
								self::$dohcache[$host]["expires"] = time() + self::$dohcache[$host]["TTL"];
								self::$dohcache[$host]["stype"] = $type;

								break;
							}
						}
					}
				}

				if (!isset(self::$dohcache[$host]))  return false;

				$state["options"]["headers"]["Host"] = $state["urlinfo"]["host"];
				$state["urlinfo"]["host"] = self::$dohcache[$host]["data"];
				$state["url"] = HTTP::CondenseURL($state["urlinfo"]);
			}

			if ($state["options"]["doh_pre_retrievewebpage_callback"] !== false && !call_user_func_array($state["options"]["doh_pre_retrievewebpage_callback"], array(&$state)))  return false;

			return true;
		}
	}
?>