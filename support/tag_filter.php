<?php
	// CubicleSoft PHP Tag Filter class.  Can repair broken HTML.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	class TagFilterStream
	{
		protected $lastcontent, $lastresult, $final, $options, $stack;

		public function __construct($options = array())
		{
			$this->Init($options);
		}

		public function Init($options = array())
		{
			if (!isset($options["keep_attr_newlines"]))  $options["keep_attr_newlines"] = false;
			if (!isset($options["keep_comments"]))  $options["keep_comments"] = false;
			if (!isset($options["allow_namespaces"]))  $options["allow_namespaces"] = true;
			if (!isset($options["process_attrs"]))  $options["process_attrs"] = array();
			if (!isset($options["charset"]))  $options["charset"] = "UTF-8";
			$options["charset"] = strtoupper($options["charset"]);
			if (!isset($options["charset_tags"]))  $options["charset_tags"] = true;
			if (!isset($options["charset_attrs"]))  $options["charset_attrs"] = true;
			if (!isset($options["tag_name_map"]))  $options["tag_name_map"] = array();
			if (!isset($options["untouched_tag_attr_keys"]))  $options["untouched_tag_attr_keys"] = array();
			if (!isset($options["void_tags"]))  $options["void_tags"] = array();
			if (!isset($options["alt_tag_content_rules"]))  $options["alt_tag_content_rules"] = array();
			if (!isset($options["pre_close_tags"]))  $options["pre_close_tags"] = array();
			if (!isset($options["output_mode"]))  $options["output_mode"] = "html";
			if (!isset($options["lowercase_tags"]))  $options["lowercase_tags"] = true;
			if (!isset($options["lowercase_attrs"]))  $options["lowercase_attrs"] = true;
			$options["tag_num"] = 0;

			$this->lastcontent = "";
			$this->lastresult = "";
			$this->final = false;
			$this->options = $options;
			$this->stack = array();
		}

		public function Process($content)
		{
			if ($this->lastcontent !== "")  $content = $this->lastcontent . $content;

			$result = $this->lastresult;
			$this->lastresult = "";
			$tag = false;
			$a = ord("A");
			$a2 = ord("a");
			$f = ord("F");
			$f2 = ord("f");
			$z = ord("Z");
			$z2 = ord("z");
			$hyphen = ord("-");
			$underscore = ord("_");
			$period = ord(".");
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
						$val = ord($content[$x]);
						if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2))
						{
							if ($x > $cx + 1)  $prefix = ltrim(substr($content, $cx + 1, $x - $cx - 1));
							$startpos = $x;

							break;
						}
					}

					if ($prefix === "")  $open = true;
					else
					{
						if ($prefix[0] === "!")
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
								if ($pos2 === false)
								{
									if (!$this->final)
									{
										$cx = $firstcx;

										break;
									}

									$pos2 = $cy;
								}

								if ($this->options["keep_comments"])
								{
									$content2 = substr($content, $pos + 3, $pos2 - $pos - 3);
									if ($this->options["charset"] === "UTF-8" && !self::IsValidUTF8($content2))  $content2 = self::MakeValidUTF8($content2);
									$content2 = "<!-- " . trim(htmlspecialchars($content2, ENT_COMPAT | ENT_HTML5, $this->options["charset"])) . " -->";

									// Let a callback handle any necessary changes.
									if (isset($this->options["content_callback"]) && is_callable($this->options["content_callback"]))  call_user_func_array($this->options["content_callback"], array($this->stack, $result, &$content2, $this->options));

									$result .= $content2;
								}
								$cx = $pos2 + 3;

								$tag = false;

								continue;
							}
						}
						else if ($prefix[0] === "/")
						{
							// Close tag.
							$prefix = "/";
							$open = false;
						}
						else if ($prefix[0] === "<")
						{
							// Stray less than.  Encode and reset.
							$content2 = "&lt;";

							// Let a callback handle any necessary changes.
							if (isset($this->options["content_callback"]) && is_callable($this->options["content_callback"]))  call_user_func_array($this->options["content_callback"], array($this->stack, $result, &$content2, $this->options));

							$result .= $content2;
							$cx++;

							continue;
						}
						else
						{
							// Unknown.  Encode it.
							$data = substr($content, $cx, strpos($content, $prefix, $cx) + strlen($prefix) - $cx);
							$content2 = $data;
							if ($this->options["charset"] === "UTF-8" && !self::IsValidUTF8($content2))  $content2 = self::MakeValidUTF8($content2);
							$content2 = htmlspecialchars($content2, ENT_COMPAT | ENT_HTML5, $this->options["charset"]);

							// Let a callback handle any necessary changes.
							if (isset($this->options["content_callback"]) && is_callable($this->options["content_callback"]))  call_user_func_array($this->options["content_callback"], array($this->stack, $result, &$content2, $this->options));

							$result .= $content2;
							$cx += strlen($data);

							$tag = false;

							continue;
						}
					}

					// Read the tag name.
					$tagname = "";
					$parse = false;
					$cx = $startpos;
					for (; $cx < $cy; $cx++)
					{
						$val = ord($content[$cx]);
						if ($val > 127)  $parse = true;
						else if (!(($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2) || ($cx > $startpos && (($val >= $zero && $val <= $nine) || $val == $hyphen || $val == $underscore || $val == $period)) || ($this->options["allow_namespaces"] && $val == $colon)))  break;
					}
					$tagname = substr($content, $startpos, $cx - $startpos);
					if ($parse)
					{
						if ($this->options["charset_tags"] && $this->options["charset"] === "UTF-8")  $tagname = (self::IsValidUTF8($tagname) ? $tagname : self::MakeValidUTF8($tagname));
						else  $tagname = preg_replace(($this->options["allow_namespaces"] ? '/[^A-Za-z0-9:._-]/' : '/[^A-Za-z0-9._-]/'), "", $tagname);
					}
					$tagname = rtrim($tagname, "._-:");
					if (!$this->options["charset_tags"])  $tagname = preg_replace('/[^A-Za-z0-9:]/', "", $tagname);
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
									if ($info["close_tag"] && $funcresult["keep_tag"])  $result .= "</" . $info["out_tag_name"] . ">" . $info["post_tag"];
								} while (!isset($info2[$info["tag_name"]]));
							}
						} while ($found);
					}

					// Process attributes/properties until a closing condition is encountered.
					$state = "name";
					$voidtag = false;
					$attrs = array();
					do
					{
						if ($state === "name")
						{
							// Find attribute key/property.
							for ($x = $cx; $x < $cy; $x++)
							{
								if ($content[$x] === ">" || $content[$x] === "<")
								{
									$cx = $x;

									$state = "exit";

									break;
								}
								else if ($content[$x] === "/")
								{
									$pos = strpos($content, ">", $x + 1);
									if ($pos !== false && trim(substr($content, $x + 1, $pos - $x - 1)) === "")
									{
										$cx = $pos;
										$voidtag = true;

										$state = "exit";

										break;
									}
								}
								else if ($content[$x] === "\"" || $content[$x] === "'" || $content[$x] === "`")
								{
									$pos = strpos($content, $content[$x], $x + 1);
									if ($pos === false)  $content .= $content[$x];
									else if (isset($this->options["untouched_tag_attr_keys"][$tagname]))
									{
										$keyname = substr($content, $x, $pos - $x + 1);
										$cx = $pos + 1;

										$state = "equals";
									}
									else
									{
										$keyname = substr($content, $x + 1, $pos - $x - 1);
										if ($this->options["lowercase_attrs"])  $keyname = strtolower($keyname);
										if (preg_match('/<\s*\/\s*' . $tagname . '(\s*|\s+.+?)>/is', strtolower($keyname)) || (count($this->stack) && preg_match('/<\s*\/\s*' . $this->stack[0]["tag_name"] . '(\s*|\s+.+?)>/is', strtolower($keyname))))
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
									$val = ord($content[$x]);
									if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2))
									{
										$cx = $x;
										$parse = false;

										for (; $cx < $cy; $cx++)
										{
											if ($content[$cx] === " " || $content[$cx] === "=" || $content[$cx] === "\"" || $content[$cx] === "'" || $content[$cx] === "`" || $content[$cx] === ">" || $content[$cx] === "<" || $content[$cx] === "/" || $content[$cx] === "\0" || $content[$cx] === "\r" || $content[$cx] === "\n" || $content[$cx] === "\t")  break;
											else if (ord($content[$cx]) > 127)  $parse = true;
										}

										$keyname = substr($content, $x, $cx - $x);
										if ($parse && $this->options["charset_attrs"] && $this->options["charset"] === "UTF-8")
										{
											$keyname = preg_replace(($this->options["allow_namespaces"] ? '/[^A-Za-z0-9:._\-\x80-\xFF]/' : '/[^A-Za-z0-9._\-\x80-\xFF]/'), "", $keyname);
											if (!self::IsValidUTF8($keyname))  $keyname = self::MakeValidUTF8($keyname);
										}
										else
										{
											$keyname = preg_replace(($this->options["allow_namespaces"] ? '/[^A-Za-z0-9:._-]/' : '/[^A-Za-z0-9._-]/'), "", $keyname);
										}
										$keyname = rtrim($keyname, "._-:");
										if (!isset($this->options["untouched_tag_attr_keys"][$tagname]) && $this->options["lowercase_attrs"])  $keyname = strtolower($keyname);

										$state = "equals";

										break;
									}
								}
							}

							if ($state === "name")
							{
								$cx = $cy;

								$state = "exit";
							}
						}
						else if ($state === "equals")
						{
							// Find the equals sign OR the start of the next attribute/property.
							for ($x = $cx; $x < $cy; $x++)
							{
								if ($content[$x] === ">" || $content[$x] === "<")
								{
									$cx = $x;

									$attrs[$keyname] = true;

									$state = "exit";

									break;
								}
								else if ($content[$x] === "=")
								{
									$cx = $x + 1;

									$state = "value";

									break;
								}
								else if ($content[$x] === "\"" || $content[$x] === "'")
								{
									$cx = $x;

									$attrs[$keyname] = true;

									$state = "name";

									break;
								}
								else
								{
									$val = ord($content[$x]);
									if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2) || ($val >= $zero && $val <= $nine))
									{
										$cx = $x;

										$attrs[$keyname] = true;

										$state = "name";

										break;
									}
								}
							}

							if ($state === "equals")
							{
								$cx = $cy;

								$attrs[$keyname] = true;

								$state = "exit";
							}
						}
						else if ($state === "value")
						{
							for ($x = $cx; $x < $cy; $x++)
							{
								if ($content[$x] === ">" || $content[$x] === "<")
								{
									$cx = $x;

									$attrs[$keyname] = true;

									$state = "exit";

									break;
								}
								else if ($content[$x] === "\"" || $content[$x] === "'" || $content[$x] === "`")
								{
									$pos = strpos($content, $content[$x], $x + 1);
									if ($pos === false)  $content .= $content[$x];
									else
									{
										$value = substr($content, $x + 1, $pos - $x - 1);
										$cx = $pos + 1;

										$state = "name";
									}

									break;
								}
								else if ($content[$x] !== "\0" && $content[$x] !== "\r" && $content[$x] !== "\n" && $content[$x] !== "\t" && $content[$x] !== " ")
								{
									$cx = $x;

									for (; $cx < $cy; $cx++)
									{
										if ($content[$cx] === "\0" || $content[$cx] === "\r" || $content[$cx] === "\n" || $content[$cx] === "\t" || $content[$cx] === " " || $content[$cx] === "<" || $content[$cx] === ">")
										{
											break;
										}
									}

									$value = substr($content, $x, $cx - $x);

									$state = "name";

									break;
								}
							}

							if ($state === "value")
							{
								$cx = $cy;

								$attrs[$keyname] = true;

								$state = "exit";
							}

							if ($state === "name")
							{
								if ($this->options["charset"] === "UTF-8" && !self::IsValidUTF8($value))  $value = self::MakeValidUTF8($value);
								$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, $this->options["charset"]);

								// Decode remaining entities.
								$value2 = "";
								$vx = 0;
								$vy = strlen($value);
								while ($vx < $vy)
								{
									$pos = strpos($value, "&#", $vx);
									$pos2 = strpos($value, "\\", $vx);
									if ($pos === false)  $pos = $vy;
									if ($pos2 === false)  $pos2 = $vy;
									if ($pos < $pos2)
									{
										// &#32 or &#x20 (optional trailing semi-colon)
										$value2 .= substr($value, $vx, $pos - $vx);
										$vx = $pos + 2;
										if ($vx < $vy)
										{
											if ($value[$vx] == "x" || $value[$vx] == "X")
											{
												$vx++;
												if ($vx < $vy)
												{
													for ($x = $vx; $x < $vy; $x++)
													{
														$val = ord($value[$x]);
														if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
													}

													$num = hexdec(substr($value, $vx, $x - $vx));
													$vx = $x;
													if ($vx < $vy && $value[$vx] == ";")  $vx++;

													$value2 .= self::UTF8Chr($num);
												}
											}
											else
											{
												for ($x = $vx; $x < $vy; $x++)
												{
													$val = ord($value[$x]);
													if (!($val >= $zero && $val <= $nine))  break;
												}

												$num = (int)substr($value, $vx, $x - $vx);
												$vx = $x;
												if ($vx < $vy && $value[$vx] == ";")  $vx++;

												$value2 .= self::UTF8Chr($num);
											}
										}
									}
									else if ($pos2 < $pos)
									{
										// Unicode (e.g. \0020)
										$value2 .= substr($value, $vx, $pos2 - $vx);
										$vx = $pos2 + 1;
										if ($vx >= $vy)  $value2 .= "\\";
										else
										{
											for ($x = $vx; $x < $vy; $x++)
											{
												$val = ord($value[$x]);
												if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
											}

											if ($x > $vx)
											{
												$num = hexdec(substr($value, $vx, $x - $vx));
												$vx = $x;

												$value2 .= self::UTF8Chr($num);
											}
											else
											{
												$value2 .= "\\";
											}
										}
									}
									else
									{
										$value2 .= substr($value, $vx);
										$vx = $vy;
									}
								}
								$value = $value2;

								if (!$this->options["keep_attr_newlines"])  $value = str_replace(array("\r\n", "\r", "\n"), " ", $value);

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
										$value = str_replace(array("\0", "\r", "\n", "\t", " "), array("", "", "", "", "%20"), trim($value));
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
						$cx = $firstcx;

						break;
					}

					unset($attrs[""]);

					if ($cx < $cy && $content[$cx] === ">")  $cx++;

					if (isset($this->options["tag_name_map"][$prefix . $tagname]))  $outtagname = $tagname = $this->options["tag_name_map"][$prefix . $tagname];

					if ($tagname != "")
					{
						if ($open)
						{
							if ($voidtag && isset($this->options["void_tags"][$tagname]))  $voidtag = false;
							$this->options["tag_num"]++;

							// Let a callback handle any necessary changes.
							if (isset($this->options["tag_callback"]) && is_callable($this->options["tag_callback"]))  $funcresult = call_user_func_array($this->options["tag_callback"], array($this->stack, &$result, $open, $prefix . $tagname, &$attrs, $this->options));
							else  $funcresult = array();

							if (!isset($funcresult["keep_tag"]))  $funcresult["keep_tag"] = true;
							if (!isset($funcresult["keep_interior"]))  $funcresult["keep_interior"] = true;
							if (!isset($funcresult["pre_tag"]))  $funcresult["pre_tag"] = "";
							if (!isset($funcresult["post_tag"]))  $funcresult["post_tag"] = "";
							if (!isset($funcresult["state"]))  $funcresult["state"] = false;
						}

						if ($open && $funcresult["keep_tag"])
						{
							$opentag = $funcresult["pre_tag"];
							$opentag .= "<" . $prefix . $outtagname;
							foreach ($attrs as $key => $val)
							{
								$opentag .= " " . $key;

								if (is_array($val))  $val = implode(" ", $val);
								if (is_string($val))
								{
									if ($this->options["charset"] === "UTF-8" && !self::IsValidUTF8($val))  $val = self::MakeValidUTF8($val);
									$opentag .= "=\"" . htmlspecialchars($val, ENT_COMPAT | ENT_HTML5, $this->options["charset"]) . "\"";
								}
							}
							if (($voidtag || isset($this->options["void_tags"][$tagname])) && $this->options["output_mode"] === "xml")
							{
								$opentag .= " /";

								$voidtag = false;
							}
							$opentag .= ">";

							if (!isset($this->options["void_tags"][$tagname]) && $prefix === "")
							{
								array_unshift($this->stack, array("tag_num" => $this->options["tag_num"], "tag_name" => $tagname, "out_tag_name" => $outtagname, "attrs" => $attrs, "result" => $result, "open_tag" => $opentag, "close_tag" => true, "keep_interior" => $funcresult["keep_interior"], "post_tag" => $funcresult["post_tag"], "state" => $funcresult["state"]));
								$result = "";

								if ($voidtag)  $open = false;
							}
							else
							{
								$result .= $opentag;
								$result .= $funcresult["post_tag"];
							}
						}

						if ((!$open || !$funcresult["keep_tag"]) && !isset($this->options["void_tags"][$tagname]))
						{
							if ($open)
							{
								array_unshift($this->stack, array("tag_num" => $this->options["tag_num"], "tag_name" => $tagname, "out_tag_name" => $outtagname, "attrs" => $attrs, "result" => $result, "open_tag" => "", "close_tag" => false, "keep_interior" => $funcresult["keep_interior"], "post_tag" => $funcresult["post_tag"], "state" => $funcresult["state"]));
								$result = "";
							}

							if (!$open)
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

										// Force close tag to be kept if the stream already output the open tag.
										if (!isset($funcresult["keep_tag"]) || ($info["close_tag"] && $info["open_tag"] == ""))  $funcresult["keep_tag"] = true;

										$info = array_shift($this->stack);

										$result = $info["result"] . ($funcresult["keep_tag"] ? $info["open_tag"] : "") . ($info["keep_interior"] ? $result : "");
										if ($info["close_tag"] && $funcresult["keep_tag"])  $result .= "</" . $info["out_tag_name"] . ">" . $info["post_tag"];
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
					$regular = true;

					// Special content handler for certain tags.
					if (count($this->stack) && isset($this->options["alt_tag_content_rules"][$this->stack[0]["tag_name"]]) && is_callable($this->options["alt_tag_content_rules"][$this->stack[0]["tag_name"]]))
					{
						$content2 = "";

						// Expected to return true until the function is no longer interested in the data.
						if (call_user_func_array($this->options["alt_tag_content_rules"][$this->stack[0]["tag_name"]], array($this->stack, $this->final, &$tag, &$content, &$cx, $cy, &$content2, $this->options)))  $regular = false;
						else if (!$this->final)
						{
							// Let a callback handle any necessary changes.
							if (isset($this->options["content_callback"]) && is_callable($this->options["content_callback"]))  call_user_func_array($this->options["content_callback"], array($this->stack, $result, &$content2, $this->options));

							$result .= $content2;

							break;
						}
					}

					if ($regular)
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
					}

					// Let a callback handle any necessary changes.
					if (isset($this->options["content_callback"]) && is_callable($this->options["content_callback"]))  call_user_func_array($this->options["content_callback"], array($this->stack, $result, &$content2, $this->options));

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

					$info = array_shift($this->stack);

					// Force close tag to be kept if the stream already output the open tag.
					if (!isset($funcresult["keep_tag"]) || ($info["close_tag"] && $info["open_tag"] == ""))  $funcresult["keep_tag"] = true;

					$result = $info["result"] . ($funcresult["keep_tag"] ? $info["open_tag"] : "") . ($info["keep_interior"] ? $result : "");
					if ($info["close_tag"] && $funcresult["keep_tag"])  $result .= "</" . $info["out_tag_name"] . ">" . $info["post_tag"];
				}
			}
			else
			{
				$this->lastcontent = ($cx < $cy ? substr($content, $cx) : "");
				$this->lastresult = $result;
				$result = "";
			}

			return $result;
		}

		public function Finalize()
		{
			$this->final = true;
		}

		// To cleanly figure out how far in to flush output, call GetStack(true), use TagFilter::GetParentPos(), and call GetResult().
		public function GetStack($invert = false)
		{
			return ($invert ? array_reverse($this->stack) : $this->stack);
		}

		// Returns the result so far up to the specified stack position and flushes the stored output to keep RAM usage low.
		// NOTE:  Callback functions returning 'keep_tag' of false for the closing tag won't work for tags that were already output using this function.
		public function GetResult($invertedstackpos)
		{
			$y = count($this->stack);
			$pos = $y - $invertedstackpos - 1;
			if ($pos < 0)  $pos = 0;

			$result = "";
			for ($x = $y - 1; $x >= $pos; $x--)
			{
				$result .= $this->stack[$x]["result"] . $this->stack[$x]["open_tag"];

				$this->stack[$x]["result"] = "";
				$this->stack[$x]["open_tag"] = "";
			}

			if (!$pos)
			{
				$result .= $this->lastresult;
				$this->lastresult = "";
			}

			return $result;
		}

		public static function MakeValidUTF8($data)
		{
			$result = "";
			$x = 0;
			$y = strlen($data);
			while ($x < $y)
			{
				$tempchr = ord($data[$x]);
				if ($y - $x > 1)  $tempchr2 = ord($data[$x + 1]);
				else  $tempchr2 = 0x00;
				if ($y - $x > 2)  $tempchr3 = ord($data[$x + 2]);
				else  $tempchr3 = 0x00;
				if ($y - $x > 3)  $tempchr4 = ord($data[$x + 3]);
				else  $tempchr4 = 0x00;
				if (($tempchr >= 0x20 && $tempchr <= 0x7E) || $tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D)
				{
					// ASCII minus control and special characters.
					$result .= chr($tempchr);
					$x++;
				}
				else if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))
				{
					// Non-overlong (2 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$x += 2;
				}
				else if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
				{
					// Non-overlong (3 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$x += 3;
				}
				else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
				{
					// Normal/straight (3 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$x += 3;
				}
				else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
				{
					// Non-surrogates (3 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$x += 3;
				}
				else if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
				{
					// Planes 1-3 (4 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$result .= chr($tempchr4);
					$x += 4;
				}
				else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
				{
					// Planes 4-15 (4 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$result .= chr($tempchr4);
					$x += 4;
				}
				else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
				{
					// Plane 16 (4 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$result .= chr($tempchr4);
					$x += 4;
				}
				else  $x++;
			}

			return $result;
		}

		public static function IsValidUTF8($data)
		{
			$x = 0;
			$y = strlen($data);
			while ($x < $y)
			{
				$tempchr = ord($data[$x]);
				if (($tempchr >= 0x20 && $tempchr <= 0x7E) || $tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D)  $x++;
				else if ($tempchr < 0xC2)  return false;
				else
				{
					$left = $y - $x;
					if ($left > 1)  $tempchr2 = ord($data[$x + 1]);
					else  return false;

					if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $x += 2;
					else
					{
						if ($left > 2)  $tempchr3 = ord($data[$x + 2]);
						else  return false;

						if ($tempchr3 < 0x80 || $tempchr3 > 0xBF)  return false;

						if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF))  $x += 3;
						else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $x += 3;
						else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F))  $x += 3;
						else
						{
							if ($left > 3)  $tempchr4 = ord($data[$x + 3]);
							else  return false;

							if ($tempchr4 < 0x80 || $tempchr4 > 0xBF)  return false;

							if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF))  $x += 4;
							else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $x += 4;
							else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F))  $x += 4;
							else  return false;
						}
					}
				}
			}

			return true;
		}

		public static function UTF8Chr($num)
		{
			if ($num < 0 || ($num >= 0xD800 && $num <= 0xDFFF) || ($num >= 0xFDD0 && $num <= 0xFDEF) || ($num & 0xFFFE) == 0xFFFE)  return "";

			if ($num <= 0x7F)  $result = chr($num);
			else if ($num <= 0x7FF)  $result = chr(0xC0 | ($num >> 6)) . chr(0x80 | ($num & 0x3F));
			else if ($num <= 0xFFFF)  $result = chr(0xE0 | ($num >> 12)) . chr(0x80 | (($num >> 6) & 0x3F)) . chr(0x80 | ($num & 0x3F));
			else if ($num <= 0x10FFFF)  $result = chr(0xF0 | ($num >> 18)) . chr(0x80 | (($num >> 12) & 0x3F)) . chr(0x80 | (($num  >> 6) & 0x3F)) . chr(0x80 | ($num & 0x3F));
			else  $result = "";

			return $result;
		}
	}

	// Accessing the data in TagFilterNodes (with an 's') via objects is not the most performance-friendly method of access.
	// The classes TagFilterNode and TagFilterNodeIterator defer method calls to the referenced TagFilterNodes instance.
	// Removed/replaced nodes in the original data will result in undefined behavior with object reuse.
	class TagFilterNode
	{
		private $tfn, $id;

		public function __construct($tfn, $rootid)
		{
			$this->tfn = $tfn;
			$this->id = $rootid;
		}

		public function __get($key)
		{
			return (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]) && isset($this->tfn->nodes[$this->id]["attrs"][$key]) ? $this->tfn->nodes[$this->id]["attrs"][$key] : false);
		}

		public function __set($key, $val)
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))
			{
				if (is_array($val))  $this->tfn->nodes[$this->id]["attrs"][$key] = $val;
				else if (isset($this->tfn->nodes[$this->id]["attrs"][$key]) && is_array($this->tfn->nodes[$this->id]["attrs"][$key]))  $this->tfn->nodes[$this->id]["attrs"][$key][(string)$val] = (string)$val;
				else  $this->tfn->nodes[$this->id]["attrs"][$key] = (string)$val;
			}
		}

		public function __isset($key)
		{
			return (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]) && isset($this->tfn->nodes[$this->id]["attrs"][$key]));
		}

		public function __unset($key)
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))  unset($this->tfn->nodes[$this->id]["attrs"][$key]);
		}

		public function __toString()
		{
			return $this->tfn->GetOuterHTML($this->id);
		}

		public function __debugInfo()
		{
			$result = (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id] : array());
			$result["id"] = $this->id;

			return $result;
		}

		public function ID()
		{
			return $this->id;
		}

		public function Node()
		{
			return (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id] : false);
		}

		public function Type()
		{
			return (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id]["type"] : false);
		}

		public function Tag()
		{
			return $this->tfn->GetTag($this->id);
		}

		public function Text($val = null)
		{
			if ($val !== null)  $this->tfn->SetText($this->id, $val);
			else  return $this->tfn->GetText($this->id);
		}

		public function AddClass($name, $attr = "class")
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))
			{
				if (!isset($this->tfn->nodes[$this->id]["attrs"][$attr]) || !is_array($this->tfn->nodes[$this->id]["attrs"][$attr]))  $this->tfn->nodes[$this->id]["attrs"][$attr] = array();

				$this->tfn->nodes[$this->id]["attrs"][$attr][$name] = $name;
			}
		}

		public function RemoveClass($name, $attr = "class")
		{
			if (isset($this->tfn->nodes[$this->id]) && isset($this->tfn->nodes[$this->id]["attrs"]))
			{
				if (isset($this->tfn->nodes[$this->id]["attrs"][$attr]) && is_array($this->tfn->nodes[$this->id]["attrs"][$attr]))  unset($this->tfn->nodes[$this->id]["attrs"][$attr][$name]);
			}
		}

		public function Parent()
		{
			return $this->tfn->GetParent($this->id);
		}

		public function ParentPos()
		{
			return (isset($this->tfn->nodes[$this->id]) ? $this->tfn->nodes[$this->id]["parentpos"] : false);
		}

		// Passing true to this method has the potential to leak RAM.  Passing false is preferred, use with caution.
		public function Children($objects = false)
		{
			return $this->tfn->GetChildren($this->id, $objects);
		}

		public function Child($pos)
		{
			return $this->tfn->GetChild($this->id, $pos);
		}

		public function PrevSibling()
		{
			return $this->tfn->GetPrevSibling($this->id);
		}

		public function NextSibling()
		{
			return $this->tfn->GetNextSibling($this->id);
		}

		public function Find($query, $cachequery = true, $firstmatch = false)
		{
			$result = $this->tfn->Find($query, $this->id, $cachequery, $firstmatch);
			if (!$result["success"])  return $result;

			return new TagFilterNodeIterator($this->tfn, $result["ids"]);
		}

		public function Implode($options = array())
		{
			return $this->tfn->Implode($this->id, $options);
		}

		public function GetOuterHTML($mode = "html")
		{
			return $this->tfn->GetOuterHTML($this->id, $mode);
		}

		// Set functions ruin the object.
		public function SetOuterHTML($src)
		{
			return $this->tfn->SetOuterHTML($this->id, $src);
		}

		public function GetInnerHTML($mode = "html")
		{
			return $this->tfn->GetInnerHTML($this->id, $mode);
		}

		public function SetInnerHTML($src)
		{
			return $this->tfn->SetInnerHTML($this->id, $src);
		}

		public function GetPlainText()
		{
			return $this->tfn->GetPlainText($this->id);
		}

		// Set functions ruin the object.
		public function SetPlainText($src)
		{
			return $this->tfn->SetPlainText($this->id, $src);
		}
	}

	class TagFilterNodeIterator implements Iterator, Countable
	{
		private $tfn, $ids, $x, $y;

		public function __construct($tfn, $ids)
		{
			$this->tfn = $tfn;
			$this->ids = $ids;
			$this->x = 0;
			$this->y = count($ids);
		}

		public function rewind()
		{
			$this->x = 0;
		}

		public function valid()
		{
			return ($this->x < $this->y);
		}

		public function current()
		{
			return $this->tfn->Get($this->ids[$this->x]);
		}

		public function key()
		{
			return $this->ids[$this->x];
		}

		public function next()
		{
			$this->x++;
		}

		public function count()
		{
			return $this->y;
		}

		public function Filter($query, $cachequery = true)
		{
			$result = $this->tfn->Filter($this->ids, $query, $cachequery);
			if (!$result["success"])  return $result;

			return new TagFilterNodeIterator($this->tfn, $result["ids"]);
		}
	}

	// Output from TagFilter::Explode().
	class TagFilterNodes
	{
		public $nodes, $nextid;
		private $queries;

		public function __construct()
		{
			$this->nodes = array(
				array(
					"type" => "root",
					"parent" => false,
					"parentpos" => false,
					"children" => array()
				)
			);

			$this->nextid = 1;
			$this->queries = array();
		}

		// Makes a selector suitable for Find() and Filter() by altering or removing rules.  Query is not cached.
		public static function MakeValidSelector($query)
		{
			if (!is_array($query))  $result = TagFilter::ParseSelector($query, true);
			else if (isset($query["success"]) && isset($query["tokens"]))
			{
				$result = $query;
				$result["tokens"] = TagFilter::ReorderSelectorTokens(array_reverse($result["tokens"]), true);
			}
			else
			{
				$result = array("success" => true, "tokens" => TagFilter::ReorderSelectorTokens(array_reverse($query), true));
			}

			// Alter certain CSS3 tokens to equivalent tokens.
			foreach ($result["tokens"] as $num => $rules)
			{
				foreach ($rules as $num2 => $rule)
				{
					if ($rule["type"] === "pseudo-class")
					{
						if ($rule["pseudo"] === "link")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "element", "namespace" => false, "tag" => "a");
						else if ($rule["pseudo"] === "disabled")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "attr", "namespace" => false, "attr" => "disabled", "cmp" => false);
						else if ($rule["pseudo"] === "enabled")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "attr", "namespace" => false, "attr" => "enabled", "cmp" => false);
						else if ($rule["pseudo"] === "checked")  $result["tokens"][$num][$num2] = array("not" => false, "type" => "attr", "namespace" => false, "attr" => "checked", "cmp" => false);
					}
				}
			}

			// Reorder the tokens so that the order is simple for output.
			$tokens = TagFilter::ReorderSelectorTokens(array_reverse($result["tokens"]), true, array("element" => array(), "id" => array(), "class" => array(), "attr" => array(), "pseudo-class" => array(), "pseudo-element" => array()), false);

			// Generate a duplicate-free Find()-safe string.
			$result = array();
			foreach ($tokens as $rules)
			{
				$groups = array();
				$strs = array();
				$rules = array_reverse($rules);
				$y = count($rules);
				for ($x = 0; $x < $y; $x++)
				{
					$str = "";

					if (isset($rules[$x]["not"]) && $rules[$x]["not"])  $str .= ":not(";

					switch ($rules[$x]["type"])
					{
						case "id":  $str .= "#" . $rules[$x]["id"];  $valid = true;  break;
						case "element":  $str .= ($rules[$x]["namespace"] !== false ? $rules[$x]["namespace"] . "|" : "") . strtolower($rules[$x]["tag"]);  $valid = true;  break;
						case "class":  $str .= "." . $rules[$x]["class"];  $valid = true;  break;
						case "attr":  $str .= "[" . ($rules[$x]["namespace"] !== false ? $rules[$x]["namespace"] . "|" : "") . strtolower($rules[$x]["attr"]) . ($rules[$x]["cmp"] !== false ? $rules[$x]["cmp"] . "\"" . str_replace("\"", "\\\"", $rules[$x]["val"]) . "\"" : "") . "]";  $valid = true;  break;
						case "pseudo-class":
						{
							$pc = $rules[$x]["pseudo"];
							$valid = ($pc === "first-child" || $pc === "last-child" || $pc === "only-child" || $pc === "nth-child" || $pc === "nth-last-child" || $pc === "first-child-all" || $pc === "last-child-all" || $pc === "only-child-all" || $pc === "nth-child-all" || $pc === "nth-last-child-all" || $pc === "first-of-type" || $pc === "last-of-type" || $pc === "only-of-type" || $pc === "nth-of-type" || $pc === "nth-last-of-type" || $pc === "empty");

							if ($valid && substr($rules[$x]["pseudo"], 0, 4) === "nth-" && (!isset($rules[$x]["a"]) || !isset($rules[$x]["b"])))  $valid = false;

							break;
						}
						case "combine":
						{
							switch ($rules[$x]["combine"])
							{
								case "prev-parent":  $groups[] = implode("", $strs);  $groups[] = ">";  $strs = array();  $valid = true;  break;
								case "any-parent":  $groups[] = implode("", $strs);  $strs = array();  $valid = true;  break;
								case "prev-sibling":  $groups[] = implode("", $strs);  $groups[] = "+";  $strs = array();  $valid = true;  break;
								case "any-prev-sibling":  $groups[] = implode("", $strs);  $groups[] = "~";  $strs = array();  $valid = true;  break;
								default:  $valid = false;
							}

							break;
						}
						default:  $valid = false;  break;
					}

					if (!$valid)  break;

					if (isset($rules[$x]["not"]) && $rules[$x]["not"])  $str .= ")";

					$strs[$str] = $str;
				}

				if ($x == $y)
				{
					if (count($strs))  $groups[] = implode("", $strs);
					$str = implode(" ", $groups);
					$result[$str] = $str;
				}
			}

			return implode(", ", $result);
		}

		public function Find($query, $id = 0, $cachequery = true, $firstmatch = false)
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return array("success" => false, "error" => "Invalid initial ID.", "errorcode" => "invalid_init_id");

			if (isset($this->queries[$query]))  $result = $this->queries[$query];
			else
			{
				if (!is_array($query))  $result = TagFilter::ParseSelector($query, true);
				else if (isset($query["success"]) && isset($result["selector"]) && isset($query["tokens"]))
				{
					$result = $query;
					$result["tokens"] = TagFilter::ReorderSelectorTokens($result["tokens"], true);

					$query = $result["selector"];
				}
				else
				{
					$result = array("success" => true, "tokens" => TagFilter::ReorderSelectorTokens($query, true));

					$cachequery = false;
				}

				if ($cachequery)
				{
					foreach ($this->queries as $key => $val)
					{
						if (count($this->queries) < 25)  break;

						unset($this->queries[$key]);
					}

					$this->queries[$query] = $result;
				}
			}

			if (!$result["success"])  return $result;

			$rules = $result["tokens"];
			$numrules = count($rules);

			$result = array();
			$childcache = array();
			$oftypecache = array();
			$rootid = $id;
			$pos = 0;
			$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
			do
			{
				if (!$pos && $this->nodes[$id]["type"] === "element")
				{
					// Attempt to match a rule.
					for ($x = 0; $x < $numrules; $x++)
					{
						$id2 = $id;
						$y = count($rules[$x]);
						for ($x2 = 0; $x2 < $y; $x2++)
						{
							if ($this->nodes[$id2]["type"] === "content" || $this->nodes[$id2]["type"] === "comment")
							{
								// Always backtrack at non-element nodes since the rules are element based.
								$backtrack = !(isset($rules[$x][$x2]["not"]) && $rules[$x][$x2]["not"]);
							}
							else if (isset($rules[$x][$x2]["namespace"]) && $rules[$x][$x2]["namespace"] !== false && $rules[$x][$x2]["namespace"] !== "*" && (($rules[$x][$x2]["namespace"] === "" && strpos($this->nodes[$id2]["tag"], ":") !== false) || ($rules[$x][$x2]["namespace"] !== "" && strcasecmp(substr($this->nodes[$id2]["tag"], 0, strlen($rules[$x][$x2]["namespace"]) + 1), $rules[$x][$x2]["namespace"] . ":") !== 0)))
							{
								$backtrack = true;
							}
							else
							{
								switch ($rules[$x][$x2]["type"])
								{
									case "id":  $backtrack = (!isset($this->nodes[$id2]["attrs"]["id"]) || $this->nodes[$id2]["attrs"]["id"] !== $rules[$x][$x2]["id"]);  break;
									case "element":  $backtrack = ($rules[$x][$x2]["tag"] !== "*" && strcasecmp($this->nodes[$id2]["tag"], (isset($rules[$x][$x2]["namespace"]) && $rules[$x][$x2]["namespace"] !== false ? $rules[$x][$x2]["namespace"] . ":" : "") . $rules[$x][$x2]["tag"]) !== 0);  break;
									case "class":  $backtrack = (!isset($this->nodes[$id2]["attrs"]["class"]) || !isset($this->nodes[$id2]["attrs"]["class"][$rules[$x][$x2]["class"]]));  break;
									case "attr":
									{
										$attr = strtolower($rules[$x][$x2]["attr"]);
										if (!isset($this->nodes[$id2]["attrs"][$attr]))  $backtrack = true;
										else
										{
											$val = $this->nodes[$id2]["attrs"][$attr];
											if (is_array($val))  $val = implode(" ", $val);

											switch ($rules[$x][$x2]["cmp"])
											{
												case "=":  $backtrack = ($val !== $rules[$x][$x2]["val"]);  break;
												case "^=":  $backtrack = ($rules[$x][$x2]["val"] === "" || substr($val, 0, strlen($rules[$x][$x2]["val"])) !== $rules[$x][$x2]["val"]);  break;
												case "$=":  $backtrack = ($rules[$x][$x2]["val"] === "" || substr($val, -strlen($rules[$x][$x2]["val"])) !== $rules[$x][$x2]["val"]);  break;
												case "*=":  $backtrack = ($rules[$x][$x2]["val"] === "" || strpos($val, $rules[$x][$x2]["val"]) === false);  break;
												case "~=":  $backtrack = ($rules[$x][$x2]["val"] === "" || strpos($rules[$x][$x2]["val"], " ") !== false || strpos(" " . $val . " ", " " . $rules[$x][$x2]["val"] . " ") === false);  break;
												case "|=":  $backtrack = ($rules[$x][$x2]["val"] === "" || ($val !== $rules[$x][$x2]["val"] && substr($val, 0, strlen($rules[$x][$x2]["val"]) + 1) !== $rules[$x][$x2]["val"] . "-"));  break;
												default:  $backtrack = false;  break;
											}
										}

										break;
									}
									case "pseudo-class":
									{
										// Handle various bits of common code.
										$pid = $this->nodes[$id2]["parent"];
										$pnum = count($this->nodes[$pid]["children"]);

										$nth = (substr($rules[$x][$x2]["pseudo"], 0, 4) === "nth-");
										if ($nth && (!isset($rules[$x][$x2]["a"]) || !isset($rules[$x][$x2]["b"])))  return array("success" => false, "error" => "Pseudo-class ':" . $rules[$x][$x2]["pseudo"] . "(n)' requires an expression for 'n'.", "errorcode" => "missing_pseudo_class_expression");

										if (substr($rules[$x][$x2]["pseudo"], -6) === "-child")
										{
											if (!isset($childcache[$id2]))
											{
												$children = 0;
												foreach ($this->nodes[$pid]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element")
													{
														$childcache[$id3] = array("cx" => $children);

														$children++;
													}
												}

												foreach ($this->nodes[$pid]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element")  $childcache[$id3]["cy"] = $children;
												}
											}

											$cx = $childcache[$id2]["cx"];
											$cy = $childcache[$id2]["cy"];
										}

										if (substr($rules[$x][$x2]["pseudo"], -8) === "-of-type")
										{
											if (!isset($oftypecache[$id2]))
											{
												$types = array();
												foreach ($this->nodes[$pid]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element")
													{
														$tag = $this->nodes[$id3]["tag"];
														if (!isset($types[$tag]))  $types[$tag] = 0;

														$oftypecache[$id3] = array("tx" => $types[$tag]);

														$types[$tag]++;
													}
												}

												foreach ($this->nodes[$pid]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element")
													{
														$tag = $this->nodes[$id3]["tag"];
														$oftypecache[$id3]["ty"] = $types[$tag];
													}
												}
											}

											$tx = $oftypecache[$id2]["tx"];
											$ty = $oftypecache[$id2]["ty"];
										}

										switch ($rules[$x][$x2]["pseudo"])
										{
											case "first-child":  $backtrack = ($cx !== 0);  break;
											case "last-child":  $backtrack = ($cx !== $cy - 1);  break;
											case "only-child":  $backtrack = ($cy !== 1);  break;
											case "nth-child":  $px = $cx;  break;
											case "nth-last-child":  $px = $cy - $cx - 1;  break;
											case "first-child-all":  $backtrack = ($this->nodes[$id2]["parentpos"] !== 0);  break;
											case "last-child-all":  $backtrack = ($this->nodes[$id2]["parentpos"] !== $pnum - 1);  break;
											case "only-child-all":  $backtrack = ($pnum !== 1);  break;
											case "nth-child-all":  $px = $this->nodes[$id2]["parentpos"];  break;
											case "nth-last-child-all":  $px = $pnum - $this->nodes[$id2]["parentpos"] - 1;  break;
											case "first-of-type":  $backtrack = ($tx !== 0);  break;
											case "last-of-type":  $backtrack = ($tx !== $ty - 1);  break;
											case "only-of-type":  $backtrack = ($ty !== 1);  break;
											case "nth-of-type":  $px = $tx;  break;
											case "nth-last-of-type":  $px = $ty - $tx - 1;  break;
											case "empty":
											{
												$backtrack = false;
												foreach ($this->nodes[$id2]["children"] as $id3)
												{
													if ($this->nodes[$id3]["type"] === "element" || ($this->nodes[$id3]["type"] === "content" && trim($this->nodes[$id3]["text"]) !== ""))
													{
														$backtrack = true;

														break;
													}
												}

												break;
											}
											default:  return array("success" => false, "error" => "Unknown/Unsupported pseudo-class ':" . $rules[$x][$x2]["pseudo"] . "'.", "errorcode" => "unknown_unsupported_pseudo_class");
										}

										if ($nth)
										{
											// Calculated expression:  a * n + b - 1 = x
											// Solved for n:  n = (x + 1 - b) / a
											// Where 'n' is a non-negative integer.  When 'a' is 0, solve for 'b' instead.
											$pa = $rules[$x][$x2]["a"];
											$pb = $rules[$x][$x2]["b"];

											if ($pa == 0)  $backtrack = ($pb != $px + 1);
											else
											{
												$pn = (($px + 1 - $pb) / $pa);

												$backtrack = ($pn < 0 || $pn - (int)$pn > 0.000001);
											}
										}

										break;
									}
									case "pseudo-element":  return array("success" => false, "error" => "Pseudo-elements are not supported.  Found '::" . $rules[$x][$x2]["pseudo"] . "'.", "errorcode" => "unsupported_selector_type");
									case "combine":
									{
										switch ($rules[$x][$x2]["combine"])
										{
											case "prev-parent":
											case "any-parent":
											{
												$backtrack = ($id2 === $rootid || !$this->nodes[$id2]["parent"]);
												if (!$backtrack)  $id2 = $this->nodes[$id2]["parent"];

												break;
											}
											case "prev-sibling":
											case "any-prev-sibling":
											{
												$backtrack = ($this->nodes[$id2]["parentpos"] == 0);
												if (!$backtrack)  $id2 = $this->nodes[$this->nodes[$id2]["parent"]]["children"][$this->nodes[$id2]["parentpos"] - 1];

												break;
											}
											default:  return array("success" => false, "error" => "Unknown combiner " . $rules[$x][$x2]["pseudo"] . ".", "errorcode" => "unknown_combiner");
										}

										// For unknown parent/sibling combiners such as '~', use the rule stack to allow for backtracking to try another path if a match fails (e.g. h1 p ~ p).
										$rules[$x][$x2]["lastid"] = $id2;

										break;
									}
									default:  return array("success" => false, "error" => "Unknown selector type '" . $rules[$x][$x2]["type"] . "'.", "errorcode" => "unknown_selector_type");
								}
							}

							if (isset($rules[$x][$x2]["not"]) && $rules[$x][$x2]["not"])  $backtrack = !$backtrack;

							// Backtrack through the rule to an unknown parent/sibling combiner.
							if ($backtrack)
							{
								if ($x2)
								{
									for ($x2--; $x2; $x2--)
									{
										if ($rules[$x][$x2]["type"] === "combine")
										{
											if ($rules[$x][$x2]["combine"] === "any-parent")
											{
												$id2 = $rules[$x][$x2]["lastid"];
												if ($id2 !== $rootid && $this->nodes[$id2]["parent"])
												{
													$id2 = $this->nodes[$id2]["parent"];
													$rules[$x][$x2]["lastid"] = $id2;

													break;
												}
											}
											else if ($rules[$x][$x2]["combine"] === "any-prev-sibling")
											{
												$id2 = $rules[$x][$x2]["lastid"];
												if ($this->nodes[$id2]["parentpos"] != 0)
												{
													$id2 = $this->nodes[$this->nodes[$id2]["parent"]]["children"][$this->nodes[$id2]["parentpos"] - 1];
													$rules[$x][$x2]["lastid"] = $id2;

													break;
												}
											}
										}
									}
								}

								if (!$x2)  break;
							}
						}

						// Match found.
						if ($x2 === $y)
						{
							$result[] = $id;

							if ($firstmatch)  return array("success" => true, "ids" => $result);

							break;
						}
					}
				}

				if ($pos >= $maxpos)
				{
					if ($rootid === $id)  break;

					$pos = $this->nodes[$id]["parentpos"] + 1;
					$id = $this->nodes[$id]["parent"];
					$maxpos = count($this->nodes[$id]["children"]);
				}
				else
				{
					$id = $this->nodes[$id]["children"][$pos];
					$pos = 0;
					$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
				}
			} while (1);

			return array("success" => true, "ids" => $result);
		}

		// Filter results from Find() based on a matching query.
		public function Filter($ids, $query, $cachequery = true)
		{
			if (!is_array($ids))  $ids = array($ids);

			// Handle lazy chaining from both Find() and Filter().
			if (isset($ids["success"]))
			{
				if (!$ids["success"])  return $ids;
				if (!isset($ids["ids"]))  return array("success" => false, "error" => "Bad filter input.", "invalid_filter_ids");

				$ids = $ids["ids"];
			}

			$ids2 = array();
			if (is_string($query) && strtolower(substr($query, 0, 10)) === "/contains:")
			{
				$query = substr($query, 10);
				foreach ($ids as $id)
				{
					if (strpos($this->GetPlainText($id), $query) !== false)  $ids2[] = $id;
				}
			}
			else if (is_string($query) && strtolower(substr($query, 0, 11)) === "/~contains:")
			{
				$query = substr($query, 11);
				foreach ($ids as $id)
				{
					if (stripos($this->GetPlainText($id), $query) !== false)  $ids2[] = $id;
				}
			}
			else
			{
				foreach ($ids as $id)
				{
					$result = $this->Find($query, $id, $cachequery, true);
					if ($result["success"] && count($result["ids"]))  $ids2[] = $id;
				}
			}

			return array("success" => true, "ids" => $ids2);
		}

		// Convert all or some of the nodes back into a string.
		public function Implode($id, $options = array())
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return "";

			if (!isset($options["include_id"]))  $options["include_id"] = true;
			if (!isset($options["types"]))  $options["types"] = "element,content,comment";
			if (!isset($options["output_mode"]))  $options["output_mode"] = "html";
			if (!isset($options["post_elements"]))  $options["post_elements"] = array();
			if (!isset($options["no_content_elements"]))  $options["no_content_elements"] = array("script" => true, "style" => true);
			if (!isset($options["charset"]))  $options["charset"] = "UTF-8";
			$options["charset"] = strtoupper($options["charset"]);

			$types2 = explode(",", $options["types"]);
			$types = array();
			foreach ($types2 as $type)
			{
				$type = trim($type);
				if ($type !== "")  $types[$type] = true;
			}

			$result = "";
			$include = (bool)$options["include_id"];
			$rootid = $id;
			$pos = 0;
			$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
			do
			{
				if (!$pos && isset($types[$this->nodes[$id]["type"]]))
				{
					switch ($this->nodes[$id]["type"])
					{
						case "element":
						{
							if ($include || $rootid != $id)
							{
								$result .= "<" . $this->nodes[$id]["tag"];
								foreach ($this->nodes[$id]["attrs"] as $key => $val)
								{
									$result .= " " . $key;

									if (is_array($val))  $val = implode(" ", $val);
									if (is_string($val))  $result .= "=\"" . htmlspecialchars($val, ENT_COMPAT | ENT_HTML5, $options["charset"]) . "\"";
								}
								$result .= (!$maxpos && $options["output_mode"] === "xml" ? " />" : ">");
							}

							break;
						}
						case "content":
						case "comment":
						{
							if (isset($types["element"]) || !isset($this->nodes[$this->nodes[$id]["parent"]]["tag"]) || !isset($options["no_content_elements"][$this->nodes[$this->nodes[$id]["parent"]]["tag"]]))  $result .= $this->nodes[$id]["text"];

							break;
						}
						default:  break;
					}
				}

				if ($pos >= $maxpos)
				{
					if ($this->nodes[$id]["type"] === "element" && is_array($this->nodes[$id]["children"]))
					{
						if (($include || $rootid != $id) && isset($types[$this->nodes[$id]["type"]]))  $result .= "</" . $this->nodes[$id]["tag"] . ">";
					}

					if ($this->nodes[$id]["type"] === "element" && isset($options["post_elements"][$this->nodes[$id]["tag"]]))  $result .= $options["post_elements"][$this->nodes[$id]["tag"]];

					if ($rootid === $id)  break;

					$pos = $this->nodes[$id]["parentpos"] + 1;
					$id = $this->nodes[$id]["parent"];
					$maxpos = count($this->nodes[$id]["children"]);
				}
				else
				{
					$id = $this->nodes[$id]["children"][$pos];
					$pos = 0;
					$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
				}
			} while (1);

			return $result;
		}

		// Object-oriented access methods.  Only Get() supports multiple IDs.
		public function Get($id = 0)
		{
			if (is_array($id))
			{
				if (isset($id["success"]) && $id["ids"])  $id = $id["ids"];

				$result = array();
				foreach ($id as $id2)  $result[] = $this->Get($id2);

				return $result;
			}

			return ($id !== false && isset($this->nodes[$id]) ? new TagFilterNode($this, $id) : false);
		}

		public function GetParent($id)
		{
			return ($id !== false && isset($this->nodes[$id]) && isset($this->nodes[$this->nodes[$id]["parent"]]) ? new TagFilterNode($this, $this->nodes[$id]["parent"]) : false);
		}

		public function GetChildren($id, $objects = false)
		{
			if (!isset($this->nodes[$id]) || !isset($this->nodes[$id]["children"]) || !is_array($this->nodes[$id]["children"]))  return false;

			return ($objects ? $this->Get($this->nodes[$id]["children"]) : $this->nodes[$id]["children"]);
		}

		public function GetChild($id, $pos)
		{
			if (!isset($this->nodes[$id]) || !isset($this->nodes[$id]["children"]) || !is_array($this->nodes[$id]["children"]))  return false;

			$pos = (int)$pos;
			$y = count($this->nodes[$id]["children"]);
			if ($pos < 0)  $pos = $y + $pos;
			if ($pos < 0 || $pos > $y - 1)  return false;

			return $this->Get($this->nodes[$id]["children"][$pos]);
		}

		public function GetPrevSibling($id)
		{
			if (!isset($this->nodes[$id]) || $this->nodes[$id]["parentpos"] == 0)  return false;

			return $this->Get($this->nodes[$this->nodes[$id]["parent"]]["children"][$this->nodes[$id]["parentpos"] - 1]);
		}

		public function GetNextSibling($id)
		{
			if ($id === false || !isset($this->nodes[$id]) || $this->nodes[$id]["parentpos"] >= count($this->nodes[$this->nodes[$id]["parent"]]["children"]) - 1)  return false;

			return $this->Get($this->nodes[$this->nodes[$id]["parent"]]["children"][$this->nodes[$id]["parentpos"] + 1]);
		}

		public function GetTag($id)
		{
			return (isset($this->nodes[$id]) && $this->nodes[$id]["type"] === "element" ? $this->nodes[$id]["tag"] : false);
		}

		public function SetText($id, $val)
		{
			if (isset($this->nodes[$id]) && ($this->nodes[$id]["type"] === "content" || $this->nodes[$id]["type"] === "comment"))  $this->nodes[$id]["text"] = (string)$val;
		}

		public function GetText($id)
		{
			return (isset($this->nodes[$id]) && ($this->nodes[$id]["type"] === "content" || $this->nodes[$id]["type"] === "comment") ? $this->nodes[$id]["text"] : false);
		}

		public function Move($src, $newpid, $newpos)
		{
			$newpid = (int)$newpid;
			if (!isset($this->nodes[$newpid]) || !isset($this->nodes[$newpid]["children"]) || !is_array($this->nodes[$newpid]["children"]))  return false;

			$newpos = (is_bool($newpos) ? count($this->nodes[$newpid]["children"]) : (int)$newpos);
			if ($newpos < 0)  $newpos = count($this->nodes[$newpid]["children"]) + $newpos;
			if ($newpos < 0)  $newpos = 0;
			if ($newpos > count($this->nodes[$newpid]["children"]))  $newpos = count($this->nodes[$newpid]["children"]);

			if ($src instanceof TagFilterNodes)
			{
				if ($src === $this)  return false;

				// Bulk node import.  Doesn't remove source nodes.
				foreach ($src->nodes as $id => $node)
				{
					if ($node["type"] === "element" || $node["type"] === "content" || $node["type"] === "comment")
					{
						$node["parent"] += $this->nextid - 1;

						if (isset($node["children"]) && is_array($node["children"]))
						{
							foreach ($node["children"] as $pos => $id2)  $node["children"][$pos] += $this->nextid - 1;
						}

						$this->nodes[$id + $this->nextid - 1] = $node;
					}
				}

				// Merge root children.
				foreach ($src->nodes[0]["children"] as $pos => $id)
				{
					$this->nodes[$id + $this->nextid - 1]["parent"] = $newpid;
					array_splice($this->nodes[$newpid]["children"], $newpos + $pos, 0, array($id + $this->nextid - 1));
				}

				$this->RealignChildren($newpid, $newpos);

				$this->nextid += $src->nextid - 1;
			}
			else if (is_array($src))
			{
				// Attach the array to the position if it is valid.
				if (!isset($src["type"]))  return false;

				switch ($src["type"])
				{
					case "element":
					{
						if (!isset($src["tag"]) || !isset($src["attrs"]) || !is_array($src["attrs"]) || !isset($src["children"]))  return false;

						$src["tag"] = (string)$src["tag"];
						$src["parent"] = $newpid;

						break;
					}
					case "content":
					case "comment":
					{
						if (!isset($src["text"]) || isset($src["children"]))  return false;

						$src["text"] = (string)$src["text"];

						break;
					}
					default:  return false;
				}

				array_splice($this->nodes[$newpid]["children"], $newpos, 0, array($this->nextid));
				$this->RealignChildren($newpid, $newpos);
				$this->nextid++;
			}
			else if (is_string($src))
			{
				return $this->Move(TagFilter::Explode($src, TagFilter::GetHTMLOptions()), $newpid, $newpos);
			}
			else
			{
				// Reparents an internal id.
				$id = (int)$src;

				if (!$id || !isset($this->nodes[$id]))  return false;

				// Don't allow reparenting to a child node.
				$id2 = $newpid;
				while ($id2)
				{
					if ($id === $id2)  return false;

					$id2 = $this->nodes[$id2]["parent"];
				}

				// Detach.
				array_splice($this->nodes[$this->nodes[$id]["parent"]]["children"], $this->nodes[$id]["parentpos"], 1);
				$this->RealignChildren($this->nodes[$id]["parent"], $this->nodes[$id]["parentpos"]);

				// Attach.
				array_splice($this->nodes[$newpid]["children"], $newpos, 0, array($id));
				$this->RealignChildren($newpid, $newpos);
			}

			return true;
		}

		// When $keepchildren is true, the node's children are moved into the parent of the node being removed.
		public function Remove($id, $keepchildren = false)
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return;

			if (!$id)
			{
				if (!$keepchildren)
				{
					// Reset all nodes.
					$this->nodes = array(
						array(
							"type" => "root",
							"parent" => false,
							"parentpos" => false,
							"children" => array()
						)
					);

					$this->nextid = 1;
				}
			}
			else
			{
				// Detach the node from the parent.
				$pid = $this->nodes[$id]["parent"];
				$pos = $this->nodes[$id]["parentpos"];

				if ($keepchildren)
				{
					// Reparent the children and attach them to the new parent.
					if (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]))
					{
						foreach ($this->nodes[$id]["children"] as $cid)  $this->nodes[$cid]["parent"] = $pid;
						array_splice($this->nodes[$pid]["children"], $pos, 1, $this->nodes[$id]["children"]);
					}
					else
					{
						array_splice($this->nodes[$pid]["children"], $pos, 1);
					}

					$this->RealignChildren($pid, $pos);

					unset($this->nodes[$id]);
				}
				else
				{
					array_splice($this->nodes[$pid]["children"], $pos, 1);

					$this->RealignChildren($pid, $pos);

					// Remove node and all children.
					$rootid = $id;
					$pos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
					do
					{
						if (!$pos)
						{
							$pid = $this->nodes[$id]["parent"];
							$pos = $this->nodes[$id]["parentpos"];

							unset($this->nodes[$id]);
							if ($rootid === $id)  break;

							$id = $pid;
						}
						else
						{
							$id = $this->nodes[$id]["children"][$pos - 1];
							$pos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
						}
					} while (1);
				}
			}
		}

		public function Replace($id, $src, $inneronly = false)
		{
			$id = (int)$id;
			if (!isset($this->nodes[$id]))  return false;

			if ($inneronly)
			{
				// Remove children.
				if (!isset($this->nodes[$id]["children"]) || !is_array($this->nodes[$id]["children"]))  return false;

				while (count($this->nodes[$id]["children"]))  $this->Remove($this->nodes[$id]["children"][0]);

				$newpid = $id;
				$newpos = 0;
			}
			else
			{
				$newpid = $this->nodes[$id]["parent"];
				$newpos = $this->nodes[$id]["parentpos"];

				$this->Remove($id);
			}

			return $this->Move($src, $newpid, $newpos);
		}

		private static function SplitAt_CopyNode($nodes, &$pid, $node)
		{
			// Copy the node.
			$node["parent"] = $pid;
			$node["parentpos"] = count($nodes->nodes[$pid]["children"]);
			if (isset($node["children"]))  $node["children"] = (is_array($node["children"]) ? array() : false);

			// Attach the node.
			$nodes->nodes[$nodes->nextid] = $node;
			$nodes->nodes[$pid]["children"][] = $nodes->nextid;

			$pid = $nodes->nextid;

			$nodes->nextid++;
		}

		public function SplitAt($ids, $keepidparents = false)
		{
			$ids2 = array();
			if (!is_array($ids))  $ids = array($ids);
			foreach ($ids as $id)  $ids2[(int)$id] = true;
			unset($ids2[0]);

			$result = array();

			// Walk the entire set of nodes, cloning until an ID match occurs (if any).
			$newnodes = new TagFilterNodes();
			$newpid = 0;
			$id = 0;
			$pos = 0;
			$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
			do
			{
				if (!$pos)
				{
					if (isset($ids2[$id]) && count($newnodes->nodes[0]["children"]))
					{
						// Found an ID match.
						$result[] = $newnodes;
						$newnodes = new TagFilterNodes();
						$newpid = 0;

						if ($keepidparents instanceof TagFilterNodes)
						{
							$newnodes = clone $keepidparents;
							$newpid = $newnodes->nextid - 1;
						}
						else if ($keepidparents)
						{
							$stack = array();
							$id2 = $this->nodes[$id]["parent"];
							while ($id2)
							{
								$stack[] = $id2;

								$id2 = $this->nodes[$id2]["parent"];
							}
							$stack = array_reverse($stack);
							foreach ($stack as $id2)
							{
								self::SplitAt_CopyNode($newnodes, $newpid, $this->nodes[$id2]);
							}
						}
					}

					if ($id)  self::SplitAt_CopyNode($newnodes, $newpid, $this->nodes[$id]);
				}

				if ($pos >= $maxpos)
				{
					if (!$id)  break;

					if (isset($ids2[$id]))
					{
						// Start a new set of nodes.
						$result[] = $newnodes;
						$newnodes = new TagFilterNodes();
						$newpid = 0;

						$stack = array();
						$id2 = $this->nodes[$id]["parent"];
						while ($id2)
						{
							$stack[] = $id2;

							$id2 = $this->nodes[$id2]["parent"];
						}
						$stack = array_reverse($stack);
						foreach ($stack as $id2)
						{
							self::SplitAt_CopyNode($newnodes, $newpid, $this->nodes[$id2]);
						}
					}
					else
					{
						$newpid = $newnodes->nodes[$newpid]["parent"];
					}

					$pos = $this->nodes[$id]["parentpos"] + 1;
					$id = $this->nodes[$id]["parent"];
					$maxpos = count($this->nodes[$id]["children"]);
				}
				else
				{
					$id = $this->nodes[$id]["children"][$pos];
					$pos = 0;
					$maxpos = (isset($this->nodes[$id]["children"]) && is_array($this->nodes[$id]["children"]) ? count($this->nodes[$id]["children"]) : 0);
				}
			} while (1);

			if (!count($result) || count($newnodes->nodes[0]["children"]))  $result[] = $newnodes;

			return $result;
		}

		public function GetOuterHTML($id, $mode = "html")
		{
			return $this->Implode($id, array("output_mode" => $mode));
		}

		public function SetOuterHTML($id, $src)
		{
			return $this->Replace($id, $src);
		}

		public function GetInnerHTML($id, $mode = "html")
		{
			return $this->Implode($id, array("include_id" => false, "output_mode" => $mode));
		}

		public function SetInnerHTML($id, $src)
		{
			return $this->Replace($id, $src, true);
		}

		public function GetPlainText($id)
		{
			return $this->Implode($id, array("types" => "content", "post_elements" => array("p" => "\n\n", "br" => "\n")));
		}

		public function SetPlainText($id, $src)
		{
			// Convert $src to a string.
			if ($src instanceof TagFilterNodes)
			{
				$src = $src->GetPlainText(0);
			}
			else if (is_array($src))
			{
				$temp = new TagFilterNodes();
				$temp->Move($src, 0, 0);

				$src = $temp->GetPlainText(0);
			}
			else if (!is_string($src))
			{
				$src = $this->GetPlainText((int)$src);
			}

			$src = array(
				"type" => "content",
				"text" => (string)$src,
				"parent" => false,
				"parentpos" => false
			);

			return $this->Replace($id, $src, true);
		}

		private function RealignChildren($id, $pos)
		{
			$y = count($this->nodes[$id]["children"]);
			for ($x = $pos; $x < $y; $x++)  $this->nodes[$this->nodes[$id]["children"][$x]]["parentpos"] = $x;
		}
	}

	class TagFilter
	{
		// Internal callback function for extracting interior content of HTML 'script' and 'style' tags.
		public static function HTMLSpecialTagContentCallback($stack, $final, &$tag, &$content, &$cx, $cy, &$content2, $options)
		{
			if (preg_match('/<\s*\/\s*' . $stack[0]["tag_name"] . '(\s*|\s+.+?)>/is', $content, $matches, PREG_OFFSET_CAPTURE, $cx))
			{
				$pos = $matches[0][1];

				$content2 = substr($content, $cx, $pos - $cx);
				$cx = $pos;
				$tag = true;

				return true;
			}
			else
			{
				if ($final)
				{
					$content2 = substr($content, $cx);
					$cx = $cy;
				}

				return false;
			}
		}

		public static function GetHTMLOptions()
		{
			$result = array(
				"tag_name_map" => array(
					"!doctype" => "DOCTYPE"
				),
				"untouched_tag_attr_keys" => array(
					"doctype" => true,
				),
				"void_tags" => array(
					"DOCTYPE" => true,
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
				// Alternate tag internal content rules for specialized tags.
				"alt_tag_content_rules" => array(
					"script" => __CLASS__ . "::HTMLSpecialTagContentCallback",
					"style" => __CLASS__ . "::HTMLSpecialTagContentCallback"
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
				"keep_attr_newlines" => false,
				"keep_comments" => false,
				"allow_namespaces" => true,
				"charset" => "UTF-8",
				"charset_tags" => true,
				"charset_attrs" => true,
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

			if (function_exists("gc_mem_caches"))  gc_mem_caches();

			return $result;
		}

		public static function CleanupResults($content)
		{
			$result = str_replace("\r\n", "\n", $content);
			$result = str_replace("\r", "\n", $result);
			while (strpos($result, "\n\n\n") !== false)  $result = str_replace("\n\n\n", "\n\n", $result);

			return $result;
		}

		public static function ExplodeTagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
		{
			if ($open)
			{
				$pid = (count($options["data"]->stackmap) ? $options["data"]->stackmap[0] : 0);

				$tagname2 = (isset($options["tag_name_map"][strtolower($tagname)]) ? $options["tag_name_map"][strtolower($tagname)] : $tagname);

				$options["nodes"]->nodes[$options["nodes"]->nextid] = array(
					"type" => "element",
					"tag" => $tagname,
					"attrs" => $attrs,
					"parent" => $pid,
					"parentpos" => count($options["nodes"]->nodes[$pid]["children"]),
					"children" => (isset($options["void_tags"][$tagname2]) ? false : array())
				);

				$options["nodes"]->nodes[$pid]["children"][] = $options["nodes"]->nextid;

				// Append non-void tags to the ID stack.
				if (!isset($options["void_tags"][$tagname2]))  array_unshift($options["data"]->stackmap, $options["nodes"]->nextid);

				$options["nodes"]->nextid++;
			}
			else
			{
				array_shift($options["data"]->stackmap);
			}

			return array("keep_tag" => false, "keep_interior" => false);
		}

		public static function ExplodeContentCallback($stack, $result, &$content, $options)
		{
			if ($content === "")  return;

			$type = (substr($content, 0, 5) === "<!-- " ? "comment" : "content");
			$pid = (count($options["data"]->stackmap) ? $options["data"]->stackmap[0] : 0);
			$parentpos = count($options["nodes"]->nodes[$pid]["children"]);

			if ($parentpos && $options["nodes"]->nodes[$options["nodes"]->nodes[$pid]["children"][$parentpos - 1]]["type"] == $type)  $options["nodes"]->nodes[$options["nodes"]->nodes[$pid]["children"][$parentpos - 1]]["text"] .= $content;
			else
			{
				$options["nodes"]->nodes[$options["nodes"]->nextid] = array(
					"type" => $type,
					"text" => $content,
					"parent" => $pid,
					"parentpos" => $parentpos
				);

				$options["nodes"]->nodes[$pid]["children"][] = $options["nodes"]->nextid;

				$options["nodes"]->nextid++;
			}

			$content = "";
		}

		public static function Explode($content, $options = array())
		{
			$options["tag_callback"] = __CLASS__ . "::ExplodeTagCallback";
			$options["content_callback"] = __CLASS__ . "::ExplodeContentCallback";
			$options["nodes"] = new TagFilterNodes();
			$options["data"] = new stdClass();
			$options["data"]->stackmap = array();

			self::Run($content, $options);

			return $options["nodes"];
		}

		public static function HTMLPurifyTagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
		{
			if ($open)
			{
				if ($tagname === "script")  return array("keep_tag" => false, "keep_interior" => false);
				if ($tagname === "style")  return array("keep_tag" => false, "keep_interior" => false);

				if (isset($attrs["src"]) && substr($attrs["src"], 0, 11) === "javascript:")  return array("keep_tag" => false, "keep_interior" => false);
				if (isset($attrs["href"]) && substr($attrs["href"], 0, 11) === "javascript:")  return array("keep_tag" => false);

				if (!isset($options["htmlpurify"]["allowed_tags"][$tagname]))  return array("keep_tag" => false);

				if (!isset($options["htmlpurify"]["allowed_attrs"][$tagname]))  $attrs = array();
				else
				{
					// For classes, "class" needs to be specified as an allowed attribute.
					foreach ($attrs as $attr => $val)
					{
						if (!isset($options["htmlpurify"]["allowed_attrs"][$tagname][$attr]))  unset($attrs[$attr]);
					}
				}

				if (isset($options["htmlpurify"]["required_attrs"][$tagname]))
				{
					foreach ($options["htmlpurify"]["required_attrs"][$tagname] as $attr => $val)
					{
						if (!isset($attrs[$attr]))  return array("keep_tag" => false);
					}
				}

				if (isset($attrs["class"]))
				{
					if (!isset($options["htmlpurify"]["allowed_classes"][$tagname]))  unset($attrs["class"]);
					else
					{
						foreach ($attrs["class"] as $class)
						{
							if (!isset($options["htmlpurify"]["allowed_classes"][$tagname][$class]))  unset($attrs["class"][$class]);
						}

						if (!count($attrs["class"]))  unset($attrs["class"]);
					}
				}
			}
			else
			{
				if (isset($options["htmlpurify"]["remove_empty"][substr($tagname, 1)]) && trim(str_replace(array("&nbsp;", "\xC2\xA0"), " ", $content)) === "")
				{
					if ($content !== "")  $content = " ";

					return array("keep_tag" => false);
				}
			}

			return array();
		}

		private static function Internal_NormalizeHTMLPurifyOptions($value)
		{
			if (is_string($value))
			{
				$opts = explode(",", $value);
				$value = array();
				foreach ($opts as $opt)
				{
					$opt = (string)trim($opt);
					if ($opt !== "")  $value[$opt] = true;
				}
			}

			return $value;
		}

		public static function NormalizeHTMLPurifyOptions($purifyopts)
		{
			if (!isset($purifyopts["allowed_tags"]))  $purifyopts["allowed_tags"] = array();
			if (!isset($purifyopts["allowed_attrs"]))  $purifyopts["allowed_attrs"] = array();
			if (!isset($purifyopts["required_attrs"]))  $purifyopts["required_attrs"] = array();
			if (!isset($purifyopts["allowed_classes"]))  $purifyopts["allowed_classes"] = array();
			if (!isset($purifyopts["remove_empty"]))  $purifyopts["remove_empty"] = array();

			$purifyopts["allowed_tags"] = self::Internal_NormalizeHTMLPurifyOptions($purifyopts["allowed_tags"]);
			foreach ($purifyopts["allowed_attrs"] as $key => $val)  $purifyopts["allowed_attrs"][$key] = self::Internal_NormalizeHTMLPurifyOptions($val);
			foreach ($purifyopts["required_attrs"] as $key => $val)  $purifyopts["required_attrs"][$key] = self::Internal_NormalizeHTMLPurifyOptions($val);
			foreach ($purifyopts["allowed_classes"] as $key => $val)  $purifyopts["allowed_classes"][$key] = self::Internal_NormalizeHTMLPurifyOptions($val);
			$purifyopts["remove_empty"] = self::Internal_NormalizeHTMLPurifyOptions($purifyopts["remove_empty"]);

			return $purifyopts;
		}

		public static function HTMLPurify($content, $htmloptions, $purifyopts)
		{
			$htmloptions["tag_callback"] = __CLASS__ . "::HTMLPurifyTagCallback";
			$htmloptions["htmlpurify"] = self::NormalizeHTMLPurifyOptions($purifyopts);

			return self::Run($content, $htmloptions);
		}

		public static function ReorderSelectorTokens($tokens, $splitrules, $order = array("pseudo-element" => array(), "pseudo-class" => array(), "attr" => array(), "class" => array(), "element" => array(), "id" => array()), $endnots = true)
		{
			// Collapse split rules.
			if (count($tokens) && !isset($tokens[0]["type"]) && isset($tokens[0][0]["type"]))
			{
				$tokens2 = array();
				foreach ($tokens as $rules)
				{
					if (count($tokens2))  $tokens2[] = array("type" => "combine", "combine" => "or");
					$rules = array_reverse($rules);
					foreach ($rules as $rule)  $tokens2[] = $rule;
				}

				$tokens = $tokens2;
			}

			$result = array();
			$rules = array();
			$selector = $order;
			foreach ($tokens as $token)
			{
				if ($token["type"] != "combine")  array_unshift($selector[$token["type"]], $token);
				else
				{
					foreach ($selector as $vals)
					{
						foreach ($vals as $token2)
						{
							if (($endnots && $token2["not"]) || (!$endnots && !$token2["not"]))  array_unshift($result, $token2);
						}

						foreach ($vals as $token2)
						{
							if (($endnots && !$token2["not"]) || (!$endnots && $token2["not"]))  array_unshift($result, $token2);
						}
					}

					if (!$splitrules || $token["combine"] != "or")  array_unshift($result, $token);
					else if ($token["combine"] == "or")
					{
						if (count($result))  $rules[] = $result;

						$result = array();
					}

					$selector = $order;
				}
			}

			foreach ($selector as $vals)
			{
				foreach ($vals as $token2)
				{
					if (($endnots && $token2["not"]) || (!$endnots && !$token2["not"]))  array_unshift($result, $token2);
				}

				foreach ($vals as $token2)
				{
					if (($endnots && !$token2["not"]) || (!$endnots && $token2["not"]))  array_unshift($result, $token2);
				}
			}

			if ($splitrules)
			{
				if (count($result))  $rules[] = $result;

				$result = $rules;
			}
			else
			{
				// Ignore a stray group combiner at the end.
				if (count($result) && $result[0]["type"] == "combine" && $result[0]["combine"] == "or")  array_shift($result);
			}

			return $result;
		}

		public static function ParseSelector($query, $splitrules = false)
		{
			// Tokenize query into individual action steps.
			$query = trim($query);
			$tokens = array();
			$lastor = 0;
			$a = ord("A");
			$a2 = ord("a");
			$f = ord("F");
			$f2 = ord("f");
			$z = ord("Z");
			$z2 = ord("z");
			$backslash = ord("\\");
			$hyphen = ord("-");
			$underscore = ord("_");
			$pipe = ord("|");
			$asterisk = ord("*");
			$colon = ord(":");
			$period = ord(".");
			$zero = ord("0");
			$nine = ord("9");
			$cr = ord("\r");
			$nl = ord("\n");
			$ff = ord("\f");
			$cx = 0;
			$cy = strlen($query);
			$state = "next_selector";
			do
			{
				$currcx = $cx;
				$currstate = $state;

				switch ($state)
				{
					case "next_selector":
					{
						// This state is necessary to handle the :not(selector) function.
						$token = array("not" => false);
					}
					case "selector":
					{
						if ($cx >= $cy)  break;

						switch ($query[$cx])
						{
							case "#":
							{
								$token["type"] = "id";
								$state = "ident_name";
								$allownamespace = false;
								$identasterisk = false;
								$allowperiod = false;
								$namespace = false;
								$range = true;
								$ident = "";
								$nextstate = "selector_ident_result";
								$cx++;

								break;
							}
							case ".":
							{
								$token["type"] = "class";
								$state = "ident";
								$allownamespace = false;
								$identasterisk = false;
								$allowperiod = false;
								$nextstate = "selector_ident_result";
								$cx++;

								break;
							}
							case "[":
							{
								$token["type"] = "attr";
								$state = "ident";
								$state2 = "attr";
								$allownamespace = true;
								$identasterisk = false;
								$allowperiod = false;
								$nextstate = "selector_ident_result";
								$cx++;

								// Find a non-whitespace character.
								while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;

								break;
							}
							case ":":
							{
								$cx++;
								if ($cx >= $cy || $query[$cx] != ":")  $token["type"] = "pseudo-class";
								else
								{
									$token["type"] = "pseudo-element";
									$cx++;
								}

								$state = "ident";
								$allownamespace = true;
								$identasterisk = false;
								$allowperiod = false;
								$nextstate = "selector_ident_result";

								break;
							}
							case ",":
							case "+":
							case ">":
							case "~":
							case " ":
							case "\r":
							case "\n":
							case "\t":
							case "\f":
							{
								$state = "combine";

								break;
							}
							default:
							{
								$token["type"] = "element";
								$state = "ident";
								$allownamespace = true;
								$identasterisk = true;
								$allowperiod = false;
								$nextstate = "selector_ident_result";

								break;
							}
						}

						break;
					}
					case "selector_ident_result":
					{
						switch ($token["type"])
						{
							case "id":
							{
								$token["id"] = $ident;
								$tokens[] = $token;
								$state = ($token["not"] ? "negate_close" : "next_selector");

								break;
							}
							case "class":
							{
								$token["class"] = $ident;
								$tokens[] = $token;
								$state = ($token["not"] ? "negate_close" : "next_selector");

								break;
							}
							case "element":
							{
								$token["namespace"] = $namespace;
								$token["tag"] = $ident;
								$tokens[] = $token;
								$state = ($token["not"] ? "negate_close" : "next_selector");

								break;
							}
							case "attr":
							{
								if ($state2 == "attr")
								{
									$token["namespace"] = $namespace;
									$token[$state2] = $ident;

									// Find a non-whitespace character.
									while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;

									if ($cx >= $cy || $query[$cx] == "]")
									{
										$token["cmp"] = false;
										$tokens[] = $token;
										$state = ($token["not"] ? "negate_close" : "next_selector");
										$cx++;
									}
									else
									{
										if ($query[$cx] == "=")
										{
											$token["cmp"] = "=";
											$cx++;
										}
										else if ($cx + 1 < $cy && ($query[$cx] == "^" || $query[$cx] == "$" || $query[$cx] == "*" || $query[$cx] == "~" || $query[$cx] == "|") && $query[$cx + 1] == "=")
										{
											$token["cmp"] = substr($query, $cx, 2);
											$cx += 2;
										}
										else
										{
											return array("success" => false, "error" => "Unknown or invalid attribute comparison operator '" . $query[$cx] . "' detected at position " . $cx . ".", "errorcode" => "invalid_attr_compare", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);
										}

										// Find a non-whitespace character.
										while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;

										if ($cx < $cy && ($query[$cx] == "\"" || $query[$cx] == "'"))
										{
											$state = "string";
											$endchr = ord($query[$cx]);
											$cx++;
										}
										else
										{
											$state = "ident";
											$allownamespace = false;
											$identasterisk = false;
											$allowperiod = false;
										}

										$state2 = "val";
										$nextstate = "selector_ident_result";
									}
								}
								else if ($state2 == "val")
								{
									$token[$state2] = $ident;

									// Find a non-whitespace character.
									while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;

									$tokens[] = $token;
									$state = ($token["not"] ? "negate_close" : "next_selector");

									if ($cx < $cy && $query[$cx] == "]")  $cx++;
								}

								break;
							}
							case "pseudo-class":
							case "pseudo-element":
							{
								$ident = strtolower($ident);

								// Deal with CSS1 and CSS2 compatibility.
								if ($ident === "first-line" || $ident === "first-letter" || $ident === "before" || $ident === "after")  $token["type"] = "pseudo-element";

								if ($token["type"] == "pseudo-class" && $ident == "not")
								{
									if ($token["not"])  return array("success" => false, "error" => "Invalid :not() embedded inside another :not() detected at position " . $cx . ".", "errorcode" => "invalid_not", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);
									if ($cx >= $cy || $query[$cx] != "(")  return array("success" => false, "error" => "Missing '(' detected at position " . $cx . ".", "errorcode" => "invalid_not", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

									unset($token["type"]);
									$token["not"] = true;

									$state = "selector";
									$cx++;

									// Find a non-whitespace character.
									while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;
								}
								else
								{
									$token["pseudo"] = $ident;

									if ($cx < $cy && $query[$cx] == "(")
									{
										$token["expression"] = "";
										$ident = "";
										$state = "pseudo_expression";
										$cx++;
									}
									else
									{
										$token["expression"] = false;
										$tokens[] = $token;
										$state = ($token["not"] ? "negate_close" : "next_selector");
									}
								}

								break;
							}
						}

						break;
					}
					case "negate_close":
					{
						// Find a non-whitespace character.
						while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;

						if ($cx < $cy && $query[$cx] != ")")  return array("success" => false, "error" => "Invalid :not() close character '" . $query[$cx] . "' detected at position " . $cx . ".", "errorcode" => "invalid_negate_close", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

						$cx++;
						$state = "next_selector";

						break;
					}
					case "pseudo_expression":
					{
						$token["expression"] .= $ident;

						// Find a non-whitespace character.
						while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;

						if ($cx >= $cy)  break;

						if ($query[$cx] == ")")
						{
							if (substr($token["pseudo"], 0, 4) === "nth-")
							{
								// Convert the expression to an+b syntax.
								$exp = strtolower($token["expression"]);

								if ($exp == "even")  $exp = "2n";
								else if ($exp == "odd")  $exp = "2n+1";
								else
								{
									do
									{
										$currexp = $exp;

										$exp = str_replace(array("++", "+-", "-+", "--"), array("+", "-", "-", "+"), $exp);

									} while ($currexp !== $exp);
								}

								if (substr($exp, 0, 2) == "-n")  $exp = "-1n" . substr($exp, 2);
								else if (substr($exp, 0, 2) == "+n")  $exp = "1n" . substr($exp, 2);
								else if (substr($exp, 0, 1) == "n")  $exp = "1n" . substr($exp, 1);

								$pos = strpos($exp, "n");
								if ($pos === false)
								{
									$token["a"] = 0;
									$token["b"] = (double)$exp;
								}
								else
								{
									$token["a"] = (double)$exp;
									$token["b"] = (double)substr($exp, $pos + 1);
								}

								$token["expression"] = $token["a"] . "n" . ($token["b"] < 0 ? $token["b"] : "+" . $token["b"]);
							}

							$tokens[] = $token;
							$state = ($token["not"] ? "negate_close" : "next_selector");
							$cx++;
						}
						else if ($query[$cx] == "+" || $query[$cx] == "-")
						{
							$ident = $query[$cx];
							$cx++;
						}
						else if ($query[$cx] == "\"" || $query[$cx] == "'")
						{
							$state = "string";
							$endchr = ord($query[$cx]);
							$cx++;
						}
						else
						{
							$val = ord($query[$cx]);

							$state = ($val >= $zero && $val <= $nine ? "ident_name" : "ident");
							$allownamespace = false;
							$identasterisk = false;
							$allowperiod = ($val >= $zero && $val <= $nine);
							$namespace = false;
							$range = true;
							$ident = "";

							$nextstate = "pseudo_expression";
						}

						break;
					}
					case "string":
					{
						$startcx = $cx;
						$ident = "";

						for (; $cx < $cy; $cx++)
						{
							$val = ord($query[$cx]);

							if ($val == $endchr)
							{
								$cx++;

								break;
							}
							else if ($val == $backslash)
							{
								// Escape sequence.
								if ($cx + 1 >= $cy)  $ident .= "\\";
								else
								{
									$cx++;
									$val = ord($query[$cx]);

									if (($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine))
									{
										// Unicode (e.g. \0020)
										for ($x = $cx + 1; $x < $cy; $x++)
										{
											$val = ord($query[$x]);
											if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
										}

										$num = hexdec(substr($query, $cx, $x - $cx));
										$cx = $x - 1;

										$ident .= TagFilterStream::UTF8Chr($num);

										// Skip one optional \r\n OR a single whitespace char.
										if ($cx + 2 < $cy && $query[$cx + 1] == "\r" && $query[$cx + 2] == "\n")  $cx += 2;
										else if ($cx + 1 < $cy && ($query[$cx + 1] == " " || $query[$cx + 1] == "\r" || $query[$cx + 1] == "\n" || $query[$cx + 1] == "\t" || $query[$cx + 1] == "\f"))  $cx++;
									}
									else
									{
										$ident .= $query[$cx];
									}
								}
							}
							else
							{
								$ident .= $query[$cx];
							}
						}

						$state = $nextstate;

						break;
					}
					case "ident":
					{
						$namespace = false;
						$range = false;

						if ($cx >= $cy)  break;

						if ($query[$cx] != "-")  $ident = "";
						else
						{
							$ident = "-";
							$cx++;
						}

						$state = "ident_name";

						break;
					}
					case "ident_name":
					{
						// Find the first invalid character.
						$startcx = $cx;
						for (; $cx < $cy; $cx++)
						{
							$val = ord($query[$cx]);

							if ($val != $period && ($val < $zero || $val > $nine))  $allowperiod = false;

							if (($val >= $a && $val <= $z) || ($val >= $a2 && $val <= $z2) || $val == $underscore || $val > 127)
							{
								$ident .= $query[$cx];
							}
							else if ($allowperiod && $val == $period)
							{
								$allowperiod = false;

								$ident .= ".";
							}
							else if ($val == $hyphen || ($val >= $zero && $val <= $nine))
							{
								// Only allowed AFTER the first character.
								if (!$range)  return array("success" => false, "error" => "Invalid identifier character '" . $query[$cx] . "' detected at position " . $cx . ".", "errorcode" => "invalid_ident", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

								$allowperiod = false;

								$ident .= $query[$cx];
							}
							else if ($val == $backslash)
							{
								// Escape sequence.
								if ($cx + 1 >= $cy)  $ident .= "\\";
								else
								{
									$cx++;
									$val = ord($query[$cx]);

									if (($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine))
									{
										// Unicode (e.g. \0020)
										for ($x = $cx + 1; $x < $cy; $x++)
										{
											$val = ord($query[$x]);
											if (!(($val >= $a && $val <= $f) || ($val >= $a2 && $val <= $f2) || ($val >= $zero && $val <= $nine)))  break;
										}

										$num = hexdec(substr($query, $cx, $x - $cx));
										$cx = $x - 1;

										$ident .= TagFilterStream::UTF8Chr($num);

										// Skip one optional \r\n OR a single whitespace char.
										if ($cx + 2 < $cy && $query[$cx + 1] == "\r" && $query[$cx + 2] == "\n")  $cx += 2;
										else if ($cx + 1 < $cy && ($query[$cx + 1] == " " || $query[$cx + 1] == "\r" || $query[$cx + 1] == "\n" || $query[$cx + 1] == "\t" || $query[$cx + 1] == "\f"))  $cx++;
									}
									else if ($val != $cr && $val != $nl && $val != $ff)
									{
										$ident .= $query[$cx];
									}
								}
							}
							else if ($allownamespace && $val == $pipe && ($cx + 1 >= $cy || $query[$cx + 1] != "="))
							{
								// Handle namespaces (rare).
								if ($ident != "")
								{
									$namespace = $ident;
									$ident = "";
								}

								$allownamespace = false;
							}
							else if ($val == $asterisk)
							{
								// Handle wildcard (*) characters.
								if ($allownamespace && $cx + 1 < $cy && $query[$cx + 1] == "|")
								{
									// Wildcard namespace (*|).
									$namespace = "*";
									$allownamespace = false;
									$cx++;
								}
								else if ($identasterisk)
								{
									if ($ident != "")  return array("success" => false, "error" => "Invalid identifier wildcard character '*' detected at position " . $cx . ".", "errorcode" => "invalid_wildcard_ident", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

									$ident = "*";
									$cx++;

									break;
								}
								else
								{
									// End of ident.
									break;
								}
							}
							else
							{
								// End of ident.
								break;
							}

							$range = true;
						}

						if ($ident == "")  return array("success" => false, "error" => "Missing or invalid identifier at position " . $cx . ".", "errorcode" => "missing_ident", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

						$state = $nextstate;

						break;
					}
					case "combine":
					{
						$token = array("type" => "combine");

						// Find a non-whitespace character.
						while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;

						if ($cx < $cy)
						{
							switch ($query[$cx])
							{
								case ",":
								{
									$token["combine"] = "or";
									$lastor = count($tokens);
									$cx++;

									break;
								}
								case "+":
								{
									$token["combine"] = "prev-sibling";
									$cx++;

									break;
								}
								case ">":
								{
									$token["combine"] = "prev-parent";
									$cx++;

									break;
								}
								case "~":
								{
									$token["combine"] = "any-prev-sibling";
									$cx++;

									break;
								}
								default:
								{
									$token["combine"] = "any-parent";

									break;
								}
							}

							if (!count($tokens) || $tokens[count($tokens) - 1]["type"] == "combine")  return array("success" => false, "error" => "Invalid combiner '" . $token["type"] . "' detected at position " . $cx . ".", "errorcode" => "invalid_combiner", "selector" => $query, "startpos" => $currcx, "pos" => $cx, "state" => $currstate, "tokens" => self::ReorderSelectorTokens(array_slice($tokens, 0, $lastor), $splitrules), "splitrules" => $splitrules);

							$tokens[] = $token;

							// Find a non-whitespace character.
							while ($cx < $cy && ($query[$cx] == " " || $query[$cx] == "\t" || $query[$cx] == "\r" || $query[$cx] == "\n" || $query[$cx] == "\f"))  $cx++;
						}

						$state = "next_selector";

						break;
					}
				}
			} while ($currstate !== $state || $currcx !== $cx);

			return array("success" => true, "selector" => $query, "tokens" => self::ReorderSelectorTokens($tokens, $splitrules), "splitrules" => $splitrules);
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