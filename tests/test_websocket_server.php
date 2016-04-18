<?php
	// This is strictly a test of the WebSocketServer class which only implements WebSocket.
	// For a more complete end-user experience, see 'test_web_server.php'.

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));
	require_once $rootpath . "/../websocket_server.php";
	require_once $rootpath . "/../support/websocket.php";
	require_once $rootpath . "/../support/http.php";

	$wsserver = new WebSocketServer();

	echo "Starting server...\n";
	$result = $wsserver->Start("127.0.0.1", "5578");
	if (!$result["success"])
	{
		var_dump($result);
		exit();
	}

	echo "Ready.\n";

	function ProcessAPI($url, $data)
	{
		if (!is_array($data))  return array("success" => false, "error" => "Data sent is not an array/object or was not able to be decoded.", "errorcode" => "invalid_data");

		if (!isset($data["pre"]) || !isset($data["op"]) || !isset($data["post"]))  return array("success" => false, "error" => "Missing required 'pre', 'op', or 'post' data entries.", "errorcode" => "missing_data");

		// Sanitize inputs.
		$data["pre"] = (double)$data["pre"];
		$data["op"] = (string)$data["op"];
		$data["post"] = (double)$data["post"];

		$question = $data["pre"] . " " . $data["op"] . " " . $data["post"];
		if ($data["op"] === "+")  $answer = $data["pre"] + $data["post"];
		else if ($data["op"] === "-")  $answer = $data["pre"] - $data["post"];
		else if ($data["op"] === "*")  $answer = $data["pre"] * $data["post"];
		else if ($data["op"] === "/" && $data["post"] != 0)  $answer = $data["pre"] / $data["post"];
		else  $answer = "NaN";

		$result = array(
			"success" => true,
			"response" => array(
				"question" => $question,
				"answer" => $answer
			)
		);

		return $result;
	}

	$tracker = array();

	do
	{
		$result = $wsserver->Wait();

		// Do something with active clients.
		foreach ($result["clients"] as $id => $client)
		{
			if (!isset($tracker[$id]))
			{
				echo "Client ID " . $id . " connected.\n";

				// Example of checking for an API key.
				$url = HTTP::ExtractURL($client->url);
				if (!isset($url["queryvars"]["apikey"]) || $url["queryvars"]["apikey"][0] !== "123456789101112")
				{
					$wsserver->RemoveClient($id);

					continue;
				}

				echo "Valid API key used.\n";

				$tracker[$id] = array();
			}

			// This example processes the client input (add/multiply two numbers together) and sends back the result.
			$ws = $client->websocket;

			$result2 = $ws->Read();
			while ($result2["success"] && $result2["data"] !== false)
			{
				echo "Sending API response via WebSocket.\n";

				// Attempt to normalize the input.
				$data = json_decode($result2["data"]["payload"], true);

				// Process the request.
				$result3 = ProcessAPI($client->url, $data);

				// Send the response.
				$result2 = $ws->Write(json_encode($result3), $result2["data"]["opcode"]);

				$result2 = $ws->Read();
			}
		}

		// Do something with removed clients.
		foreach ($result["removed"] as $id => $result2)
		{
			if (isset($tracker[$id]))
			{
				echo "Client ID " . $id . " disconnected.\n";

//				echo "Client ID " . $id . " disconnected.  Reason:\n";
//				var_dump($result2["result"]);
//				echo "\n";

				unset($tracker[$id]);
			}
		}
	} while (1);
?>