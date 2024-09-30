<?php

	try {

		ini_set('display_errors', 'On');
		ini_set('error_log', 'errors.log');
		error_reporting(E_ALL | E_STRICT);
		ignore_user_abort(true);

		$ch = curl_init('https://www.webhosting-performance.com/api/update2');

		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if (!$response = curl_exec($ch)) {
			throw new Exception("No response from remote API: ". curl_error($ch));
		}

		if (!$result = json_decode($response, true)) {
			throw new Exception("Invalid JSON response");
		}

		if (strtolower($result['status']) != 'ok') {
			throw new Exception($result['status']);
		}

		if (!isset($result['files']) || !is_array($result['files'])) {
			throw new Exception('No files returned by API');
		}

		foreach ($result['files'] as $file) {
			$file['source'] = base64_decode($file['source']);

			if (md5($file['source']) != $file['checksum']) {
				$output[] = $file['filename'] . ': Cheksum error for file ('. $file['filename'] .')';
				continue;
			}

			if (is_file($file['filename']) && $file['checksum'] == md5_file($file['filename'])) {
				$output[] = $file['filename'] . ': Already up to date';
				continue;
			}

			if (!file_put_contents($file['filename'], $file['source'])) {
				$output[] = $file['filename'] .': Cannot write to file';
				continue;
			}

			$output[] = $file['filename'] .': Updated';
			file_put_contents('lastrun.dat', '');
		}

		echo '<p>'. implode('<br>' . PHP_EOL, $output) .'</p>';

		echo '<p>To run the test again, <a href="'. str_replace('update.php', '', $_SERVER['REQUEST_URI']) .'">click here</a>.</p>';

	} catch (Exception $e) {
		echo 'Error: '.$e->getMessage();
	}
