<?php
	// CubicleSoft PHP multiple asynchronous helper class.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	class MultiAsyncHelper
	{
		private $objs, $queuedobjs, $limit;

		public function __construct()
		{
			$this->objs = array();
			$this->queuedobjs = array();
			$this->limit = false;
		}

		public function SetConcurrencyLimit($limit)
		{
			$this->limit = $limit;
		}

		public function Set($key, $obj, $callback)
		{
			if (is_callable($callback))
			{
				$this->queuedobjs[$key] = array(
					"obj" => $obj,
					"callback" => $callback
				);

				unset($this->objs[$key]);
			}
		}

		public function NumObjects()
		{
			return count($this->queuedobjs) + count($this->objs);
		}

		public function GetObject($key)
		{
			if (isset($this->queuedobjs[$key]))  $result = $this->queuedobjs[$key]["obj"];
			else if (isset($this->objs[$key]))  $result = $this->objs[$key]["obj"];
			else  $result = false;

			return $result;
		}

		// To be able to change a callback on the fly.
		public function SetCallback($key, $callback)
		{
			if (is_callable($callback))
			{
				if (isset($this->queuedobjs[$key]))  $this->queuedobjs[$key]["callback"] = $callback;
				else if (isset($this->objs[$key]))  $this->objs[$key]["callback"] = $callback;
			}
		}

		private function InternalDetach($key, $cleanup)
		{
			if (isset($this->queuedobjs[$key]))
			{
				call_user_func_array($this->queuedobjs[$key]["callback"], array("cleanup", &$cleanup, $key, &$this->queuedobjs[$key]["obj"]));
				$result = $this->queuedobjs[$key]["obj"];
				unset($this->queuedobjs[$key]);
			}
			else if (isset($this->objs[$key]))
			{
				call_user_func_array($this->objs[$key]["callback"], array("cleanup", &$cleanup, $key, &$this->objs[$key]["obj"]));
				$result = $this->objs[$key]["obj"];
				unset($this->objs[$key]);
			}
			else
			{
				$result = false;
			}

			return $result;
		}

		public function Detach($key)
		{
			return $this->InternalDetach($key, false);
		}

		public function Remove($key)
		{
			return $this->InternalDetach($key, true);
		}

		// A few default functions for direct file/socket handles.
		public static function ReadOnly($mode, &$data, $key, $fp)
		{
			switch ($mode)
			{
				case "init":
				case "update":
				{
					// Move to/Keep in the live queue.
					if (is_resource($fp))  $data = true;

					break;
				}
				case "read":
				case "write":
				case "writefps":
				{
					break;
				}
				case "readfps":
				{
					$data[$key] = $fp;

					break;
				}
				case "cleanup":
				{
					if ($data === true)  @fclose($fp);

					break;
				}
			}
		}

		public static function WriteOnly($mode, &$data, $key, $fp)
		{
			switch ($mode)
			{
				case "init":
				case "update":
				{
					// Move to/Keep in the live queue.
					if (is_resource($fp))  $data = true;

					break;
				}
				case "read":
				case "readfps":
				case "write":
				{
					break;
				}
				case "writefps":
				{
					$data[$key] = $fp;

					break;
				}
				case "cleanup":
				{
					if ($data === true)  @fclose($fp);

					break;
				}
			}
		}

		public static function ReadAndWrite($mode, &$data, $key, $fp)
		{
			switch ($mode)
			{
				case "init":
				case "update":
				{
					// Move to/Keep in the live queue.
					if (is_resource($fp))  $data = true;

					break;
				}
				case "read":
				case "write":
				{
					break;
				}
				case "readfps":
				case "writefps":
				{
					$data[$key] = $fp;

					break;
				}
				case "cleanup":
				{
					if ($data === true)  @fclose($fp);

					break;
				}
			}
		}

		public function Wait($timeout = false)
		{
			// Move queued objects to live.
			$result2 = array("success" => true, "read" => array(), "write" => array(), "removed" => array(), "new" => array());
			while (count($this->queuedobjs) && ($this->limit === false || count($this->objs) < $this->limit))
			{
				$info = reset($this->queuedobjs);
				$key = key($this->queuedobjs);
				unset($this->queuedobjs[$key]);

				$result2["new"][$key] = $key;

				$keep = false;
				call_user_func_array($info["callback"], array("init", &$keep, $key, &$info["obj"]));

				$this->objs[$key] = $info;

				if (!$keep)  $result2["removed"][$key] = $this->Remove($key);
			}

			// Walk the objects looking for read and write handles.
			$readfps = array();
			$writefps = array();
			$exceptfps = NULL;
			foreach ($this->objs as $key => &$info)
			{
				$keep = false;
				call_user_func_array($info["callback"], array("update", &$keep, $key, &$info["obj"]));

				if (!$keep)  $result2["removed"][$key] = $this->Remove($key);
				else
				{
					call_user_func_array($info["callback"], array("readfps", &$readfps, $key, &$info["obj"]));
					call_user_func_array($info["callback"], array("writefps", &$writefps, $key, &$info["obj"]));
				}
			}
			if (!count($readfps))  $readfps = NULL;
			if (!count($writefps))  $writefps = NULL;

			// Wait for something to happen.
			if (isset($readfps) || isset($writefps))
			{
				if ($timeout === false)  $timeout = NULL;
				$readfps2 = $readfps;
				$writefps2 = $writefps;
				$result = @stream_select($readfps, $writefps, $exceptfps, $timeout);
				if ($result === false)  return array("success" => false, "error" => self::MAHTranslate("Wait() failed due to stream_select() failure.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed");
				else if ($result > 0)
				{
					if (isset($readfps))
					{
						$readfps3 = array();
						foreach ($readfps as $key => $fp)
						{
							if (!isset($readfps2[$key]) || $readfps2[$key] !== $fp)
							{
								foreach ($readfps2 as $key2 => $fp2)
								{
									if ($fp === $fp2)  $key = $key2;
								}
							}

							if (isset($this->objs[$key]))
							{
								call_user_func_array($this->objs[$key]["callback"], array("read", &$fp, $key, &$this->objs[$key]["obj"]));

								$readfps3[$key] = $fp;
							}
						}

						$result2["read"] = $readfps3;
					}

					if (isset($writefps))
					{
						$writefps3 = array();
						foreach ($writefps as $key => $fp)
						{
							if (!isset($writefps2[$key]) || $writefps2[$key] !== $fp)
							{
								foreach ($writefps2 as $key2 => $fp2)
								{
									if ($fp === $fp2)  $key = $key2;
								}
							}

							if (isset($this->objs[$key]))
							{
								call_user_func_array($this->objs[$key]["callback"], array("write", &$fp, $key, &$this->objs[$key]["obj"]));

								$readfps3[$key] = $fp;
							}
						}

						$result2["write"] = $writefps3;
					}
				}
			}

			$result2["numleft"] = count($this->queuedobjs) + count($this->objs);

			return $result2;
		}

		public static function MAHTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>