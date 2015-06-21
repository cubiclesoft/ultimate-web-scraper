<?php
	// Requires both WebSocket and HTTP classes to work.
	require_once "websocket_server.php";
	require_once "support/websocket.php";
	require_once "support/http.php";

	$wsserver = new WebSocketServer();

	echo "Starting server...\n";
	$result = $wsserver->Start("127.0.0.1", "5578");
	if (!$result["success"])
	{
		var_dump($result);
		exit();
	}

	echo "Ready.\n";

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
				$url = HTTP::ExtractURL($client["url"]);
				if (!isset($url["queryvars"]["apikey"]) || $url["queryvars"]["apikey"][0] !== "123456789101112")  $wsserver->RemoveClient($id);
				else  echo "Valid API key used.\n";

				$tracker[$id] = array();
			}

			// This example processes the client input (add/multiply two numbers together) and sends back the result.
			$ws = $client["websocket"];

			$result2 = $ws->Read();
			while ($result2["success"] && $result2["data"] !== false)
			{
				$data = json_decode($result2["data"]["payload"], true);

				$question = $data["pre"] . " " . $data["op"] . " " . $data["post"];
				if ($data["op"] === "+")  $answer = $data["pre"] + $data["post"];
				else if ($data["op"] === "-")  $answer = $data["pre"] - $data["post"];
				else if ($data["op"] === "*")  $answer = $data["pre"] * $data["post"];
				else if ($data["op"] === "/" && $data["post"] != 0)  $answer = $data["pre"] / $data["post"];
				else  $answer = "NaN";

				$response = array(
					"question" => $question,
					"answer" => $answer
				);

				$result2 = $ws->Write(json_encode($response), $result2["data"]["opcode"]);

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