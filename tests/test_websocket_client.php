<?php
	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../support/websocket.php";

	$ws = new WebSocket();

	// The first parameter is the WebSocket server.
	// The second parameter is the Origin URL.
	$result = $ws->Connect("ws://127.0.0.1:5578/math?apikey=123456789101112", "http://localhost");
	if (!$result["success"])
	{
		var_dump($result);
		exit();
	}

	// Send a text frame (just an example).
	// Get the answer to 5 + 10.
	$data = array(
		"pre" => "5",
		"op" => "+",
		"post" => "10",
	);
	$result = $ws->Write(json_encode($data), WebSocket::FRAMETYPE_TEXT);

	// Send a binary frame in two fragments (just an example).
	// Get the answer to 5 * 10.
	$data["op"] = "*";
	$data2 = json_encode($data);
	$y = (int)(strlen($data2) / 2);
	$result = $ws->Write(substr($data2, 0, $y), WebSocket::FRAMETYPE_BINARY, false);
	$result = $ws->Write(substr($data2, $y), WebSocket::FRAMETYPE_BINARY);

	// Main loop.
	$result = $ws->Wait();
	while ($result["success"])
	{
		do
		{
			$result = $ws->Read();
			if (!$result["success"])  break;
			if ($result["data"] !== false)
			{
				// Do something with the data.
				echo "Raw message from server:\n";
				var_dump($result["data"]);
				echo "\n";

				$data = json_decode($result["data"]["payload"], true);
				echo "The server said:  " . ($data["success"] ? $data["response"]["question"] . " = " . $data["response"]["answer"] : $data["error"] . " (" . $data["errorcode"] . ")") . "\n\n";
			}
		} while ($result["data"] !== false);

		$result = $ws->Wait();
	}

	// An error occurred.
	var_dump($result);
?>