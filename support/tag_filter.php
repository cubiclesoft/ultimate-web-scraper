<?php
	// CubicleSoft PHP Tag Filter class.  Can repair broken HTML.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	class TagFilterStream
	{
		protected $lastcontent, $final, $options, $stack;

		public function __construct($options = array())
		{
			$this->Init($options);
		}

		public function Init($options = array())
		{
			if (!isset($options["remove_attr_newlines"]))  $options["remove_attr_newlines"] = true;
			if (!isset($options["remove_comments"]))  $options["remove_comments"] = true;
			if (!isset($options["allow_namespaces"]))  $options["allow_namespaces"] = true;
			if (!isset($options["process_attrs"]))  $options["process_attrs"] = array();
			if (!isset($options["charset"]))  $options["charset"] = "UTF-8";
			if (!isset($options["void_tags"]))  $options["void_tags"] = array();
			if (!isset($options["pre_close_tags"]))  $options["pre_close_tags"] = array();
			if (!isset($options["output_mode"]))  $options["output_mode"] = "html";
			if (!isset($options["lowercase_tags"]))  $options["lowercase_tags"] = true;
			if (!isset($options["lowercase_attrs"]))  $options["lowercase_attrs"] = true;

			$this->lastcontent = "";
			$this->final = false;
			$this->options = $options;
			$this->stack = array();
		}

		public function Process($content)
		{
			if ($this->lastcontent !== "")  $content = $this->lastcontent . $content;

			$result = "";
			$tag = false;
			$a = ord("A");
			$a2 = ord("a");
			$f = ord("F");
			$f2 = ord("f");
			$z = ord("Z");
			$z2 = ord("z");
			$hyphen = ord("-");
			$colon = ord(":");
			$zero = ord("0");
			$nine = ord("9");
			$cx = 0;
			$cy = strlen($content);
			while ($cx < $cy)
			{
				if ($tag)
				{
					$firstcx = $cx;

					// First character is '<'.  Extract all non-alpha chars.
					$prefix = "";
					$startpos = $cx + 1;
					for ($x = $startpos; $x < $cy; $x++)
					{
						$val = ord($content{$x});
						if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2))
						{
							if ($x > 1)  $prefix = ltrim(substr($content, $cx + 1, $x - $cx - 1));
							$startpos = $x;

							break;
						}
					}

					if ($prefix === "")  $open = true;
					else
					{
						if ($prefix{0} === "!")
						{
							// !DOCTYPE vs. comment.
							if (substr($prefix, 0, 3) !== "!--")
							{
								$prefix = "!";
								$open = true;
							}
							else
							{
								// Comment.
								$pos = strpos($content, "!--", $cx);
								$pos2 = strpos($content, "-->", $pos + 3);
								if ($pos2 === false)  $pos2 = $cy;
								if (!$this->options["remove_comments"])  $result .= "<!-- " . htmlspecialchars(substr($content, $pos + 3, $pos2)) . " -->";
								$cx = $pos2 + 3;

								$tag = false;

								continue;
							}
						}
						else if ($prefix{0} === "/")
						{
							// Close tag.
							$prefix = "/";
							$open = false;
						}
						else if ($prefix{0} === "<")
						{
							// Stray less than.  Encode and reset.
							$result .= "&lt;";
							$cx++;

							continue;
						}
						else
						{
							// Unknown.  Encode it.
							$data = substr($content, $cx, strpos($content, $prefix, $cx) + strlen($prefix));
							$result .= htmlspecialchars($data);
							$cx += strlen($data);

							$tag = false;

							continue;
						}
					}

					// Read the tag name.
					$tagname = "";
					$cx = $startpos;
					for (; $cx < $cy; $cx++)
					{
						$val = ord($content{$cx});
						if (!(($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2) || ($cx > $startpos && $val >= $zero && $val <= $nine) || ($this->options["allow_namespaces"] && $val == $colon)))  break;
					}
					$tagname = rtrim(substr($content, $startpos, $cx - $startpos), ":");
					$outtagname = ($this->options["lowercase_tags"] ? strtolower($tagname) : $tagname);
					$tagname = strtolower($tagname);

					// Close open tags in the stack that match the set of tags to look for to close.
					if ($open && isset($this->options["pre_close_tags"][$tagname]))
					{
						// Find matches.
						$info2 = $this->options["pre_close_tags"][$tagname];
						$limit = (isset($info2["_limit"]) ? $info2["_limit"] : array());
						if (is_string($limit))  $limit = array($limit => true);

						// Unwind the stack.
						do
						{
							$found = false;
							foreach ($this->stack as $info)
							{
								if (isset($info2[$info["tag_name"]]))
								{
									$found = true;

									break;
								}

								if (isset($limit[$info["tag_name"]]))  break;
							}

							if ($found)
							{
								do
								{
									// Let a callback handle any necessary changes.
									$attrs = array();
									if (isset($this->options["tag_callback"]) && is_callable($this->options["tag_callback"]))  $funcresult = call_user_func_array($this->options["tag_callback"], array($this->stack, &$result, false, "/" . $this->stack[0]["tag_name"], &$attrs, $this->options));
									else  $funcresult = array();

									if (!isset($funcresult["keep_tag"]))  $funcresult["keep_tag"] = true;

									$info = array_shift($this->stack);

									$result = $info["result"] . ($funcresult["keep_tag"] ? $info["open_tag"] : "") . ($info["keep_interior"] ? $result : "");
									if ($info["close_tag"] && $funcresult["keep_tag"])  $result .= "</" . $info["tag_name"] . ">" . $info["post_tag"];
								} while (!isset($info2[$info["tag_name"]]));
							}
						} while ($found);
					}

					// Process attributes/properties until a closing condition is encountered.
					$state = "key";
					$attrs = array();
					do
					{
//echo "State:  " . $state . "\n";
//echo "Content:\n" . $content . "\n";
						if ($state === "key")
						{
							// Find attribute key/property.
							for ($x = $cx; $x < $cy; $x++)
							{
								if ($content{$x} === ">" || $content{$x} === "<")
								{
									$cx = $x;

									$state = "exit";

									break;
								}
								else if ($content{$x} === "\"" || $content{$x} === "'" || $content{$x} === "`")
								{
									$pos = strpos($content, $content{$x}, $x + 1);
									if ($pos === false)  $content .= $content{$x};
									else
									{
										$keyname = substr($content, $x + 1, $pos - $x - 1);
										if ($this->options["lowercase_attrs"])  $keyname = strtolower($keyname);
										if (preg_match('/<\s*\/\s*' . $tagname . '\s*>/is', strtolower($keyname)) || (count($this->stack) && preg_match('/<\s*\/\s*' . $this->stack[0]["tag_name"] . '\s*>/is', strtolower($keyname))))
										{
											// Found a matching close tag within the key name.  Bail out.
											$state = "exit";

											break;
										}
										else
										{
											$keyname = preg_replace('/[^' . ($this->options["lowercase_attrs"] ? "" : "A-Z") . 'a-z' . ($this->options["allow_namespaces"] ? ":" : "") . ']/', "", $keyname);
											if ($this->options["allow_namespaces"])  $keyname = rtrim($keyname, ":");
											$cx = $pos + 1;

											$state = "equals";
										}
									}

									break;
								}
								else
								{
									$val = ord($content{$x});
									if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2))
									{
										$cx = $x;

										for (; $cx < $cy; $cx++)
										{
											$val = ord($content{$cx});
											if (!(($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2) || ($cx > $x && $val >= $zero && $val <= $nine) || ($cx > $x && $val == $hyphen) || ($this->options["allow_namespaces"] && $val == $colon)))  break;
										}

										$keyname = rtrim(substr($content, $x, $cx - $x), "-:");
										if ($this->options["lowercase_attrs"])  $keyname = strtolower($keyname);

										$state = "equals";

										break;
									}
								}
							}
						}
						else if ($state === "equals")
						{
							// Find the equals sign OR the start of the next attribute/property.
							for ($x = $cx; $x < $cy; $x++)
							{
								if ($content{$x} === ">" || $content{$x} === "<")
								{
									$cx = $x;

									$attrs[$keyname] = true;

									$state = "exit";

									break;
								}
								else if ($content{$x} === "=")
								{
									$cx = $x + 1;

									$state = "value";

									break;
								}
								else if ($content{$x} === "\"" || $content{$x} === "'")
								{
									$cx = $x;

									$attrs[$keyname] = true;

									$state = "key";

									break;
								}
								else
								{
									$val = ord($content{$x});
									if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2) || ($val >= $zero && $val <= $nine))
									{
										$cx = $x;

										$attrs[$keyname] = true;

										$state = "key";

										break;
									}
								}
							}
						}
						else if ($state === "value")
						{
							for ($x = $cx; $x < $cy; $x++)
							{
								if ($content{$x} === ">" || $content{$x} === "<")
								{
									$cx = $x;

									$attrs[$keyname] = true;

									$state = "exit";

									break;
								}
								else if ($content{$x} === "\"" || $content{$x} === "'" || $content{$x} === "`")
								{
									$pos = strpos($content, $content{$x}, $x + 1);
									if ($pos === false)  $content .= $content{$x};
									else
									{
										$value = substr($content, $x + 1, $pos - $x - 1);
										$cx = $pos + 1;

										$state = "key";
									}

									break;
								}
								else if ($content{$x} !== "\0" && $content{$x} !== "\r" && $content{$x} !== "\n" && $content{$x} !== "\t" && $content{$x} !== " ")
								{
									$cx = $x;

									for (; $cx < $cy; $cx++)
									{
										if ($content{$cx} === "\0" || $content{$cx} === "\r" || $content{$cx} === "\n" || $content{$cx} === "\t" || $content{$cx} === " " || $content{$cx} === "<" || $content{$cx} === ">")
										{
											break;
										}
									}

									$value = substr($content, $x, $cx - $x);

									$state = "key";

									break;
								}
							}

							if ($state === "key")
							{
								$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, $this->options["charset"]);

								// Decode remaining entities.
								$value2 = "";
								while ($value != "")
								{
									$y = strlen($value);
									$pos = strpos($value, "&#");
									$pos2 = strpos($value, "\\");
									if ($pos === false)  $pos = $y;
									if ($pos2 === false)  $pos2 = $y;
									if ($pos < $pos2)
									{
										// &#32 or &#x20 (optional trailing semi-colon)
										$value2 .= substr($value, 0, $pos);
										$value = substr($value, $pos + 2);
										if ($value != "")
										{
											if ($value{0} == "x" || $value{0} == "X")
											{
												$value = substr($value, 1);
												if ($value != "")
												{
													$y = strlen($value);
													for ($x = 0; $x < $y; $x++)
													{
														$val = ord($value{$x});
														if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
													}

													$num = hexdec(substr($value, 0, $x));
													$value = substr($value, $x);
													if ($value != "" && $value{0} == ";")  $value = substr($value, 1);

													$value2 .= self::UTF8Chr($num);
												}
											}
											else
											{
												$y = strlen($value);
												for ($x = 0; $x < $y; $x++)
												{
													$val = ord($value{$x});
													if (!($val >= $zero && $val <= $nine))  break;
												}

												$num = (int)substr($value, 0, $x);
												$value = substr($value, $x);
												if ($value != "" && $value{0} == ";")  $value = substr($value, 1);

												$value2 .= self::UTF8Chr($num);
											}
										}
									}
									else if ($pos2 < $pos)
									{
										// \0020
										$value2 .= substr($value, 0, $pos2);
										$value = substr($value, $pos2 + 1);
										if ($value == "")  $value2 .= "\\";
										else
										{
											$y = strlen($value);
											for ($x = 0; $x < $y; $x++)
											{
												$val = ord($value{$x});
												if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
											}

											$num = hexdec(substr($value, 0, $x));
											$value = substr($value, $x);

											$value2 .= self::UTF8Chr($num);
										}
									}
									else
									{
										$value2 .= $value;
										$value = "";
									}
								}
								$value = $value2;

								if ($this->options["remove_attr_newlines"])  $value = str_replace(array("\r\n", "\r", "\n"), " ", $value);

								if (isset($this->options["process_attrs"][$keyname]))
								{
									$type = $this->options["process_attrs"][$keyname];
									if ($type === "classes")
									{
										$classes = explode(" ", $value);
										$value = array();
										foreach ($classes as $class)
										{
											if ($class !== "")  $value[$class] = $class;
										}
									}
									else if ($type === "uri")
									{
										$value = str_replace(array("\0", "\r", "\n", "\t", " "), "", $value);
										$pos = strpos($value, ":");
										if ($pos !== false)  $value = preg_replace('/[^a-z]/', "", strtolower(substr($value, 0, $pos))) . substr($value, $pos);
									}
								}

								$attrs[$keyname] = $value;
							}
						}
					} while ($cx < $cy && $state !== "exit");

					// Break out of the loop if the end of the stream has been reached but not finalized and most likely in the middle of a tag.
					if ($cx >= $cy && !$this->final)
					{
						$this->lastcontent = substr($content, $firstcx);

						break;
					}

					unset($attrs[""]);

					if ($cx < $cy && $content{$cx} === ">")  $cx++;

					if ($prefix === "!" && $tagname === "doctype")  $tagname = "DOCTYPE";

					if ($tagname != "")
					{
						if ($open)
						{
							// Let a callback handle any necessary changes.
							if (isset($this->options["tag_callback"]) && is_callable($this->options["tag_callback"]))  $funcresult = call_user_func_array($this->options["tag_callback"], array($this->stack, &$result, $open, $prefix . $tagname, &$attrs, $this->options));
							else  $funcresult = array();

							if (!isset($funcresult["keep_tag"]))  $funcresult["keep_tag"] = true;
							if (!isset($funcresult["keep_interior"]))  $funcresult["keep_interior"] = true;
							if (!isset($funcresult["pre_tag"]))  $funcresult["pre_tag"] = "";
							if (!isset($funcresult["post_tag"]))  $funcresult["post_tag"] = "";
						}

						if ($funcresult["keep_tag"] && $open)
						{
							$opentag = $funcresult["pre_tag"];
							$opentag .= "<" . $prefix . $outtagname;
							foreach ($attrs as $key => $val)
							{
								$opentag .= " " . $key;

								if (is_array($val))  $val = implode(" ", $val);
								if (is_string($val))  $opentag .= "=\"" . htmlspecialchars($val) . "\"";
							}
							if (isset($this->options["void_tags"][$tagname]) && $this->options["output_mode"] === "xml")  $opentag .= " /";
							$opentag .= ">";

							if (!isset($this->options["void_tags"][$tagname]) && $prefix === "")
							{
								array_unshift($this->stack, array("tag_name" => $tagname, "out_tag_name" => $outtagname, "attrs" => $attrs, "result" => $result, "open_tag" => $opentag, "close_tag" => true, "keep_interior" => $funcresult["keep_interior"], "post_tag" => $funcresult["post_tag"]));
								$result = "";
							}
							else
							{
								$result .= $opentag;
								$result .= $funcresult["post_tag"];
							}
						}
						else if (!isset($this->options["void_tags"][$tagname]))
						{
							if ($open)
							{
								array_unshift($this->stack, array("tag_name" => $tagname, "out_tag_name" => $outtagname, "attrs" => $attrs, "result" => $result, "open_tag" => "", "close_tag" => false, "keep_interior" => $funcresult["keep_interior"], "post_tag" => $funcresult["post_tag"]));
								$result = "";
							}
							else
							{
								$found = false;
								foreach ($this->stack as $info)
								{
									if ($tagname === $info["tag_name"])
									{
										$found = true;

										break;
									}
								}

								if ($found)
								{
									do
									{
										// Let a callback handle any necessary changes.
										$attrs = array();
										if (isset($this->options["tag_callback"]) && is_callable($this->options["tag_callback"]))  $funcresult = call_user_func_array($this->options["tag_callback"], array($this->stack, &$result, false, "/" . $this->stack[0]["tag_name"], &$attrs, $this->options));
										else  $funcresult = array();

										if (!isset($funcresult["keep_tag"]))  $funcresult["keep_tag"] = true;

										$info = array_shift($this->stack);

										$result = $info["result"] . ($funcresult["keep_tag"] ? $info["open_tag"] : "") . ($info["keep_interior"] ? $result : "");
										if ($info["close_tag"] && $funcresult["keep_tag"])  $result .= "</" . $info["tag_name"] . ">" . $info["post_tag"];
									} while ($tagname !== $info["tag_name"]);
								}
							}
						}
					}

//echo "Current output:\n" . $result . "\n\n";
//echo "Prefix:  " . $prefix . "\n\n";
//echo "Tag:  " . $tagname . "\n\n";
//echo "Attrs:\n";
//var_dump($attrs);
//
//echo "Tag stack:\n";
//var_dump($this->stack);
//
//echo "\n\n";
//echo $content . "\n";
//exit();

					$tag = false;
				}
				else
				{
					// Regular content.
					$pos = strpos($content, "<", $cx);
					if ($pos === false)
					{
						$content2 = str_replace(">", "&gt;", substr($content, $cx));
						$cx = $cy;
					}
					else
					{
						$content2 = str_replace(">", "&gt;", substr($content, $cx, $pos - $cx));
						$cx = $pos;

						$tag = true;
					}

					// Let a callback handle any necessary changes.
					if (isset($this->options["content_callback"]) && is_callable($this->options["content_callback"]))  $funcresult = call_user_func_array($this->options["content_callback"], array($this->stack, $result, &$content2, $this->options));

					$result .= $content2;
				}
			}

			if ($this->final)
			{
				while (count($this->stack))
				{
					// Let a callback handle any necessary changes.
					$attrs = array();
					if (isset($this->options["tag_callback"]) && is_callable($this->options["tag_callback"]))  $funcresult = call_user_func_array($this->options["tag_callback"], array($this->stack, &$result, false, "/" . $this->stack[0]["tag_name"], &$attrs, $this->options));
					else  $funcresult = array();

					if (!isset($funcresult["keep_tag"]))  $funcresult["keep_tag"] = true;

					$info = array_shift($this->stack);

					$result = $info["result"] . ($funcresult["keep_tag"] ? $info["open_tag"] : "") . ($info["keep_interior"] ? $result : "");
					if ($info["close_tag"] && $funcresult["keep_tag"])  $result .= "</" . $info["tag_name"] . ">" . $info["post_tag"];
				}
			}

			return $result;
		}

		public function Finalize()
		{
			$this->final = true;
		}

		protected static function UTF8Chr($num)
		{
			if ($num <= 0x7F)  $result = chr($num);
			else if ($num <= 0x7FF)  $result = chr(0xC0 | (($num & 0x7C0) >> 6)) . chr(0x80 | ($num & 0x3F));
			else if ($num <= 0xFFFF)  $result = chr(0xE0 | (($num & 0xF000) >> 6)) . chr(0x80 | (($num & 0xFC0) >> 6)) . chr(0x80 | ($num & 0x3F));
			else if ($num <= 0x1FFFFF)  $result = chr(0xF0 | (($num & 0x1C0000) >> 6)) . chr(0x80 | (($num & 0x3F000) >> 6)) . chr(0x80 | (($num & 0xFC0) >> 6)) . chr(0x80 | ($num & 0x3F));
			else  $result = "";

			return $result;
		}
	}

	class TagFilter
	{
		public static function GetHTMLOptions()
		{
			$result = array(
				"void_tags" => array(
					"area" => true,
					"base" => true,
					"bgsound" => true,
					"br" => true,
					"col" => true,
					"embed" => true,
					"hr" => true,
					"img" => true,
					"input" => true,
					"keygen" => true,
					"link" => true,
					"menuitem" => true,
					"meta" => true,
					"param" => true,
					"source" => true,
					"track" => true,
					"wbr" => true
				),
				// Stored as a map for open tag elements.
				// For example, '"address" => array("p" => true)' means:  When an open 'address' tag is encountered,
				// look for an open 'p' tag anywhere (no '_limit') in the tag stack.  Apply a closing '</p>' tag for all matches.
				//
				// If '_limit' is defined as a string or an array, then stack walking stops as soon as one of the specified tags is encountered.
				"pre_close_tags" => array(
					"body" => array("body" => true, "head" => true),

					"address" => array("p" => true),
					"article" => array("p" => true),
					"aside" => array("p" => true),
					"blockquote" => array("p" => true),
					"div" => array("p" => true),
					"dl" => array("p" => true),
					"fieldset" => array("p" => true),
					"footer" => array("p" => true),
					"form" => array("p" => true),
					"h1" => array("p" => true),
					"h2" => array("p" => true),
					"h3" => array("p" => true),
					"h4" => array("p" => true),
					"h5" => array("p" => true),
					"h6" => array("p" => true),
					"header" => array("p" => true),
					"hr" => array("p" => true),
					"menu" => array("p" => true),
					"nav" => array("p" => true),
					"ol" => array("p" => true),
					"pre" => array("p" => true),
					"section" => array("p" => true),
					"table" => array("p" => true),
					"ul" => array("p" => true),
					"p" => array("p" => true),

					"tbody" => array("_limit" => "table", "thead" => true, "tr" => true, "th" => true, "td" => true),
					"tr" => array("_limit" => "table", "tr" => true, "th" => true, "td" => true),
					"th" => array("_limit" => "table", "th" => true, "td" => true),
					"td" => array("_limit" => "table", "th" => true, "td" => true),
					"tfoot" => array("_limit" => "table", "thead" => true, "tbody" => true, "tr" => true, "th" => true, "td" => true),

					"optgroup" => array("optgroup" => true, "option" => true),
					"option" => array("option" => true),

					"dd" => array("_limit" => "dl", "dd" => true, "dt" => true),
					"dt" => array("_limit" => "dl", "dd" => true, "dt" => true),

					"colgroup" => array("colgroup" => true),

					"li" => array("_limit" => array("ul" => true, "ol" => true, "menu" => true, "dir" => true), "li" => true),
				),
				"process_attrs" => array(
					"class" => "classes",
					"href" => "uri",
					"src" => "uri",
					"dynsrc" => "uri",
					"lowsrc" => "uri",
					"background" => "uri",
				),
				"remove_attr_newlines" => true,
				"remove_comments" => true,
				"allow_namespaces" => true,
				"charset" => "UTF-8",
				"output_mode" => "html",
				"lowercase_tags" => true,
				"lowercase_attrs" => true,
			);

			return $result;
		}

		public static function Run($content, $options = array())
		{
			$tfs = new TagFilterStream($options);
			$tfs->Finalize();
			$result = $tfs->Process($content);

			// Clean up output.
			$result = trim($result);
			$result = self::CleanupResults($result);

			return $result;
		}

		public static function CleanupResults($content)
		{
			$result = str_replace("\r\n", "\n", $content);
			$result = str_replace("\r", "\n", $result);
			while (strpos($result, "\n\n\n") !== false)  $result = str_replace("\n\n\n", "\n\n", $result);

			return $result;
		}

		public static function GetParentPos($stack, $tagname, $start = 0, $attrs = array())
		{
			$y = count($stack);
			for ($x = $start; $x < $y; $x++)
			{
				if ($stack[$x]["tag_name"] === $tagname)
				{
					$found = true;
					foreach ($attrs as $key => $val)
					{
						if (!isset($stack[$x]["attrs"][$key]))  $found = false;
						else if (is_string($stack[$x]["attrs"][$key]) && is_string($val) && stripos($stack[$x]["attrs"][$key], $val) === false)  $found = false;
						else if (is_array($stack[$x]["attrs"][$key]))
						{
							if (is_string($val))  $val = explode(" ", $val);

							foreach ($val as $val2)
							{
								if ($val2 !== "" && !isset($stack[$x]["attrs"][$key][$val2]))  $found = false;
							}
						}
					}

					if ($found)  return $x;
				}
			}

			return false;
		}
	}
?>