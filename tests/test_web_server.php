<?php
	// Tests the functionality of the WebServer and WebSocketServer classes by implementing a simple math API.

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));
	require_once $rootpath . "/../web_server.php";
	require_once $rootpath . "/../websocket_server.php";
	require_once $rootpath . "/../support/websocket.php";
	require_once $rootpath . "/../support/http.php";

	$webserver = new WebServer();
	$wsserver = new WebSocketServer();

	echo "Starting server...\n";
	$result = $webserver->Start("127.0.0.1", "5578");
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
	$tracker2 = array();

	do
	{
		// Implement the stream_select() call directly since multiple server instances are involved.
		$timeout = 30;
		$readfps = array();
		$writefps = array();
		$exceptfps = NULL;
		$webserver->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);
		$wsserver->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);
		$result = @stream_select($readfps, $writefps, $exceptfps, $timeout);
		if ($result === false)  break;

		// Web server.
		$result = $webserver->Wait(0);

		// Do something with active clients.
		foreach ($result["clients"] as $id => $client)
		{
			if (!isset($tracker[$id]))
			{
				echo "Client ID " . $id . " connected.\n";

				$tracker[$id] = array("validapikey" => false);
			}

			// Example of checking for an API key.
			if (!$tracker[$id]["validapikey"] && isset($client->requestvars["apikey"]) && $client->requestvars["apikey"] === "123456789101112")
			{
				echo "Valid API key used.\n";

				// Guaranteed to have at least the request line and headers if the request is incomplete.
				// Raise the upload limit to ~10MB for requests that haven't completed yet.  Just an example.
				if (!$client->requestcomplete)
				{
					$options = $client->GetHTTPOptions();
					$options["recvlimit"] = 10000000;
					$client->SetHTTPOptions($options);
				}

				$tracker[$id]["validapikey"] = true;
			}

			// Wait until the request is complete before fully processing inputs.
			if ($client->requestcomplete)
			{
				if (!$tracker[$id]["validapikey"])
				{
					echo "Missing API key.\n";

					$client->SetResponseCode(403);
					$client->SetResponseContentType("application/json");
					$client->AddResponseContent(json_encode(array("success" => false, "error" => "Invalid or missing 'apikey'.", "errorcode" => "invalid_missing_apikey")));
					$client->FinalizeResponse();
				}
				else
				{
					// Handle WebSocket upgrade requests.
					$id2 = $wsserver->ProcessWebServerClientUpgrade($webserver, $client);
					if ($id2 !== false)
					{
						echo "Client ID " . $id . " upgraded to WebSocket.  WebSocket client ID is " . $id2 . ".\n";

						$tracker2[$id2] = $tracker[$id];

						unset($tracker[$id]);
					}
					else
					{
						echo "Sending API response.\n";

						// Attempt to normalize input.
						if ($client->contenthandled)  $data = $client->requestvars;
						else if (!is_object($client->readdata))  $data = @json_decode($client->readdata, true);
						else
						{
							$client->readdata->Open();
							$data = @json_decode($client->readdata->Read(1000000), true);
						}

						// Process the request.
						$result2 = ProcessAPI($client->url, $data);
						if (!$result2["success"])  $client->SetResponseCode(400);

						// Send the response.
						$client->SetResponseContentType("application/json");
						$client->AddResponseContent(json_encode($result2));
						$client->FinalizeResponse();
					}
				}
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

		// WebSocket server.
		$result = $wsserver->Wait(0);

		// Do something with active clients.
		foreach ($result["clients"] as $id => $client)
		{
			// This example processes the client input (add/multiply two numbers together) and sends back the result.
			$ws = $client->websocket;

			$result2 = $ws->Read();
			while ($result2["success"] && $result2["data"] !== false)
			{
				echo "Sending API response via WebSocket.\n";

				// Attempt to normalize the input.
				$data = @json_decode($result2["data"]["payload"], true);

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
			if (isset($tracker2[$id]))
			{
				echo "WebSocket client ID " . $id . " disconnected.\n";

//				echo "Client ID " . $id . " disconnected.  Reason:\n";
//				var_dump($result2["result"]);
//				echo "\n";

				unset($tracker2[$id]);
			}
		}
	} while (1);
?>