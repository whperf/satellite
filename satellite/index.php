<?php

	class satellite {

		const VERSION = '3.0.0';

		private static $_whoami = [];
		private static $_report = [];

		public static function run() {

			include_once 'config.php';

			// Set whoami
			if (!$response = self::_http_request('GET', '/whoami')) {
				throw new Exception('Could not determine who I am as no response from remote location');
			}

			if (!$result = json_decode($response, true)){
				throw new Exception('Invalid JSON returned from remote location:<br>'. htmlspecialchars($response));
			}

			self::$_whoami = $result;

			// Set IP address
			if (!isset($_SERVER['SERVER_ADDR']) || $_SERVER['SERVER_ADDR'] == '') {
				$_SERVER['SERVER_ADDR'] = $result['ip_address'];
			}

			// Perform auto update (silently)
			if (defined('AUTO_UPDATE') && AUTO_UPDATE) {
				self::update(true);
			}

			// CPU Load
			self::_perform_pi_calc();

			// MySQL
			self::_perform_mysql_test(1e3);

			// Disk I/O
			self::_perform_disk_test(30e3);

			// Bandwidth
			self::_perform_upstream_test(256e3);
			self::_perform_downstream_test(256e3);

			// Environment
			self::_collect_server_info();

			// Send data
			self::_submit_results();
		}

		private static function _perform_pi_calc() {

			$tsStart = microtime(true);

			$precision = 60;

			$accuracy = $precision * 45 / 32;

			bcscale($precision);

			$bcatan1 = 0;
			$bcatan2 = 0;
			for ($n=1; $n < $accuracy; $n++) {
				$bcatan1 = bcadd($bcatan1, bcmul(bcdiv(pow(-1, $n + 1), $n * 2 - 1), bcpow(0.2, $n * 2 -1)));
				$bcatan2 = bcadd($bcatan2, bcmul(bcdiv(pow(-1, $n + 1), $n * 2 - 1), bcpow(bcdiv(1, 239), $n * 2 -1)));
			}

			$pi = bcmul(4, bcsub(bcmul(4, $bcatan1), $bcatan2), $precision);

			$time_elapsed = microtime(true) - $tsStart;

			self::$_report['cpu']['pi'] = $time_elapsed;
		}

		/*
		private static function _perform_for_calc($cycles=10000) {

			$tsStart = microtime(true);

			for ($i=0; $i<$cycles; $i++) {
				// Do something
				// Coming in future?
			}

			$time_elapsed = microtime(true) - $tsStart;

			self::$_report['cpu']['for'] = $time_elapsed;
		}
		*/

		private static function _perform_mysql_test($cycles=1000) {

			// --------------------------------------------------------------
			// Measure time to connect to MySQL
			// --------------------------------------------------------------

			$time_elapsed = 0;

			for ($i=0; $i < 10; $i++) {
				$tsStart = microtime(true);
				$dblink = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
				$time_elapsed += microtime(true) - $tsStart;
				mysqli_close($dblink);
			}

			$measure_time = $time_elapsed / 10;

			self::$_report['mysql']['connect'] = $measure_time; // ms

			// --------------------------------------------------------------
			// Collect MySQL server info
			// --------------------------------------------------------------

			$dblink = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

			self::$_report['mysql']['version'] = mysqli_get_server_info($dblink);

			mysqli_query($dblink,
				"DROP TABLE IF EXISTS `whptest_test`;"
			);

			mysqli_query($dblink,
				"CREATE TABLE `whptest_test` (
					`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`column` VARCHAR(32) NOT NULL
				) ENGINE = MyISAM;"
			);

			// --------------------------------------------------------------
			// Measure time to insert MySQL data
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			for ($i=0; $i < $cycles; $i++) {
				mysqli_query($dblink,
					"INSERT INTO `whptest_test` (`column`)
					VALUES ('". str_pad($i, strlen($cycles), '0', STR_PAD_LEFT) ."');"
				);
			}

			$time_elapsed = microtime(true) - $tsStart;

			$data_speed = round($cycles / $time_elapsed);

			self::$_report['mysql']['insert'] = $data_speed; // rps

			// --------------------------------------------------------------
			// Measure time to select and get MySQL data
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			$result = mysqli_query($dblink,
				"SELECT * FROM `whptest_test`"
			);

			$num_results = 0;
			while($row = mysqli_fetch_assoc($result)) {
				$num_results++;
			}

			mysqli_free_result($result);

			$time_elapsed = microtime(true) - $tsStart;

			$data_speed = $cycles / $time_elapsed;

			self::$_report['mysql']['select'] = $data_speed; // rps

			// --------------------------------------------------------------
			// Measure time to search and get MySQL data
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			for ($i=0; $i < $cycles; $i++) {
				$result = mysqli_query($dblink,
					"SELECT * FROM `whptest_test`
					WHERE `column` = '". str_pad(rand(0, $cycles), strlen($cycles), '0', STR_PAD_LEFT) ."';"
				);
				mysqli_fetch_assoc($result);
				mysqli_free_result($result);
			}

			$time_elapsed = microtime(true) - $tsStart;

			$measure_time = ($time_elapsed / $cycles * 1000) * 1000;

			$data_speed = $cycles / $time_elapsed;

			self::$_report['mysql']['search'] = $data_speed; // rps

			// --------------------------------------------------------------
			// Measure time to update MySQL data
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			for ($i=0; $i < $cycles; $i++) {
				mysqli_query($dblink,
					"UPDATE `whptest_test`
					set `column` = `column`
					WHERE `column` = '". str_pad(rand(0, $cycles), strlen($cycles), '0', STR_PAD_LEFT) ."';"
				);
			}

			$time_elapsed = microtime(true) - $tsStart;

			$data_speed = $cycles / $time_elapsed;

			self::$_report['mysql']['update'] = $data_speed; // rps

			// --------------------------------------------------------------
			// Measure time to delete MySQL data
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			for ($i=0; $i < $cycles; $i++) {
				mysqli_query($dblink,
					"DELETE from `whptest_test`
					WHERE `column` = '". str_pad($i, strlen($cycles), '0', STR_PAD_LEFT) ."';"
				);
			}

			$time_elapsed = microtime(true) - $tsStart;

			$data_speed = $cycles / $time_elapsed;

			self::$_report['mysql']['delete'] = $data_speed; // rps

			// --------------------------------------------------------------

			mysqli_query($dblink, "drop table `whptest_test`;");
		}

		private static function _perform_disk_test($cycles=1e4) {

			$file = 'read_write_compile.deleteme.php';

			file_put_contents($file, '');

			// --------------------------------------------------------------
			// Write to disk
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			$fh = fopen($file, 'w') or die('Can\'t open file');
			fwrite($fh, '<?php' . PHP_EOL);

			for ($i=0; $i < $cycles; $i++) {

				$output = implode(PHP_EOL, [
					'	function dummy_'.$i.'($var) {',
					'		return false;',
					'	}',
				]);

				fwrite($fh, $output);
			}

			fclose($fh);

			$time_elapsed = microtime(true) - $tsStart;

			$filesize = filesize($file);

			$measure_amount = ($filesize / $time_elapsed) / 1048576;

			self::$_report['disk']['write'] = $measure_amount;

			// --------------------------------------------------------------
			// Read from disk
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			$fh = fopen($file, 'r');
			while (!feof($fh)) {
				$tmp = fread($fh, 4096);
			}
			fclose($fh);

			$time_elapsed = microtime(true) - $tsStart;

			unset($buffer);

			$filesize = filesize($file);

			$measure_amount = ($filesize / $time_elapsed) / 1048576;

			self::$_report['disk']['read'] = $measure_amount; // MB/s

			// --------------------------------------------------------------
			// Read and compile large php file from disk
			// --------------------------------------------------------------

			$tsStart = microtime(true);

			require_once($file);

			$time_elapsed = microtime(true) - $tsStart;

			$filesize = filesize($file);

			$measure_amount = ($filesize / $time_elapsed) / 1048576;

			self::$_report['disk']['compile'] = $measure_amount; // MB/s

			// --------------------------------------------------------------

			unlink($file);
		}

		private static function _perform_upstream_test($size=256e3) {

			switch (self::$_whoami['country_code']) {

				default:
					$country_code = self::$_whoami['country_code'] ? strtolower(self::$_whoami['country_code']) : 'us';
					$url = 'http://releases.ubuntu.com/';
					break;
			}

			$boundary = "---------------------".substr(md5(rand(0, 32e3)), 0, 10);

			$parts = parse_url($url);
			$fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);

			if (!$fp) throw new Exception("Problem with $url, $errstr");

			$out = implode("\r\n", [
				"POST $parts[path] HTTP/1.1",
				"Host: $parts[host]",
				"Content-Type: multipart/form-data; boundary=$boundary",
				"Content-Length: $size",
				"Connection: Close",
			]) . "\r\n\r\n";

			fwrite($fp, $out);

			$tsStart = microtime(true);

			fwrite($fp, str_repeat('0', $size));

			$time_elapsed = microtime(true) - $tsStart;

			$speed = ($size * 8) / $time_elapsed / 1e6;

			if (!$fp) {
				throw new Exception("Problem with $url, $php_errormsg");
			}

			if (!$response = @stream_get_contents($fp)) {
				throw new Exception("Problem reading data from $url, $php_errormsg");
			}

			self::$_report['bandwidth']['upstream'] = $speed; // Mbit/s
			self::$_report['bandwidth']['upstream_url'] = $url; // Mbit/s
		}

		private static function _perform_downstream_test($size=256e3) {

			switch (self::$_whoami['country_code']) {

					default:
						$country_code = self::$_whoami['country_code'] ? strtolower(self::$_whoami['country_code']) : 'us';
						$url = 'http://releases.ubuntu.com/24.04.1/ubuntu-24.04.1-desktop-amd64.iso.zsync';
						break;
			}

			$parts = parse_url($url);
			$fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);

			if (!$fp) throw new Exception("Problem with $url, $errstr");

			$out = implode("\r\n", [
				"GET $parts[path] HTTP/1.1",
				"Host: $parts[host]",
				"Connection: Close",
			]) . "\r\n\r\n";

			fwrite($fp, $out);

			$found_body = false;
			$response = '';
			$timeout = 60;
			$start = time();

			while (!feof($fp)) {
				if ((time() - $start) > $timeout) break;
				if (strlen($response) > $size) break;
				$row = fgets($fp);
				if ($found_body) {
					$response .= $row;
				} else if ($row == "\r\n") {
					$found_body = true;
					$tsStart = microtime(true);
					continue;
				}
			}

			$time_elapsed = microtime(true) - $tsStart;

			$speed = (strlen($response) * 8) / $time_elapsed / 1e6;

			self::$_report['bandwidth']['downstream'] = $speed; // Mbit/s
			self::$_report['bandwidth']['downstream_url'] = $url; // Mbit/s
		}

		private static function _collect_server_info() {

			$server_info = [
				'php' => [
					'version' => phpversion(),
					'extensions' => implode(', ', get_loaded_extensions()),
					'disabled_functions' => ini_get('disable_functions'),
					'memory_limit' => ini_get('memory_limit'),
					'safe_mode' => ini_get('safe_mode'),
					'register_globals' => ini_get('register_globals'),
				],
				'httpd' => [
					'name' => substr($_SERVER['SERVER_SOFTWARE'], 0, strpos($_SERVER['SERVER_SOFTWARE'], ' ')),
				],
				'os' => [
					'type' => PHP_OS,
					'version' => php_uname('r'),
				],
				'network' => [
					'country' => self::$_whoami['country_code'],
					'machine' => php_uname('n'),
					'address' => $_SERVER['SERVER_ADDR'],
					'hostname' => gethostbyaddr($_SERVER['SERVER_ADDR']),
				],
				'satellite' => [
					'uri' => (($_SERVER['SERVER_PORT'] == '80') ? 'http://' : 'https://') . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'],
					'version' => self::VERSION,
					'checksum' => md5_file(__FILE__),
				],
				'noreport' => (isset($_GET['noreport']) ? 'true' : 'false'),
			];

			self::$_report = array_merge(self::$_report, $server_info);
		}

		private static function _http_request($method, $endpoint, $data=[]) {

			$ch = curl_init('https://www.webhosting-performance.com/api'.$endpoint . ((strtoupper($method) == 'GET' && $data) ? '?'.http_build_query($data) : ''));

			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Accept: application/json; charset=utf-8',
				'User-Agent: Mozilla/5.0 (compatible; WHPSat/'. self::VERSION .'; +https://www.webhosting-performance.com)',
			]);

			if (strtoupper($method) != 'GET') {
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
			}

			if (!$response = curl_exec($ch)) {
				throw new Exception("Problem reading data from $url, ". curl_error($ch));
			}

			if (($status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) != 200) {
				throw new Exception("Invalid response status code $status_code from $endpoint");
			}

			return $response;
		}

		public static function update($silent=false) {

			$response = self::_http_request('POST', '/update2', [
				'current_version' => self::VERSION,
				'whoami' => (($_SERVER['SERVER_PORT'] == '80') ? 'http://' : 'https://') . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']
			]);

			if (!$result = json_decode($response, true)) {
				throw new Exception("Unexpected response data from $url");
			}

			if (isset($result['status']) && strtolower($result['status']) == 'error') {
				throw new Exception($result['error']);
			}

			if (!isset($result['status']) || strtolower($result['status']) != 'ok') {
				throw new Exception("Invalid response ($result[status])");
			}

			if (!isset($result['files']) || !is_array($result['files'])) {
				throw new Exception('No files listed by API.');
			}

			foreach ($result['files'] as $file) {

				$file['source'] = base64_decode($file['source']);

				if (md5($file['source']) != $file['checksum']) {
					$output[] = $file['filename'] . ': Cheksum error from API';
					continue;
				}

				if (is_file($file['filename']) && $file['checksum'] == md5_file($file['filename'])) {
					$output[] = $file['filename'] . ': Already up to date';
					continue;
				}

				if (!file_put_contents($file['filename'], $file['source'])) {
					throw new Exception('Cannot write to file ('.$file['filename'].')');
				}

				$output[] = $file['filename'] .': Updated';
				$updated = true;
			}

			if (!empty($updated)) {

				file_put_contents('lastrun.dat', '');

				// Cleanup
				foreach ([
					'compatibility.inc.php',
					'country.dat',
					'database.class.php',
					'performance.class.php',
				] as $file) {
					if (is_file($file)) {
						unlink($file);
					}
				}

				if ($silent) {
					header('Location: '. $_SERVER['REQUEST_URI']);
					exit;
				}

				echo implode('<br>' . PHP_EOL, $output);
				echo '<p>To run the test again, <a href="'. $_SERVER['REQUEST_URI'] .'">click here</a>.</p>';
			}
		}

		private static function _submit_results() {

			$response = self::_http_request('POST', '/report', self::$_report);

			if (!$content = unserialize($response)) {
				throw new Exception('Invalid API response data:<br/>' . $response);
			}

			echo $content['html'];
		}
	}

	try {

		// Require PHP 5
		if (version_compare(PHP_VERSION, '5.0.0', '<')) {
			throw new Exception('This script requires at least PHP 5 (2004-07-13). Your PHP version is :'. phpversion());
		}

		// Set a timezone to prevent warning
		date_default_timezone_set(ini_get('date.timezone'));

		// System
		ini_set('display_errors', 'On');
		ini_set('error_log', 'errors.log');
		error_reporting(E_ALL | E_STRICT);
		ignore_user_abort(true);
		set_time_limit(60);

		if (is_file('errors.log') && filesize('errors.log') > 1e6) {
			file_put_contents('errors.log', '');
		}

		// Set error handler
		set_error_handler(function ($errno, $errstr, $errfile, $errline) {

			if (!(error_reporting() & $errno)) return;

			$errfile = preg_replace('#^'. $_SERVER['DOCUMENT_ROOT'] .'#', '~', str_replace('\\', '/', $errfile));

			switch($errno) {

				case E_WARNING:
				case E_USER_WARNING:
					$output = "<b>Warning:</b> $errstr in <b>$errfile</b> on line <b>$errline</b>";
					break;

				case E_STRICT:
				case E_NOTICE:
				case E_USER_NOTICE:
					$output = "<b>Notice:</b> $errstr in <b>$errfile</b> on line <b>$errline</b>";
					break;

				case E_DEPRECATED:
				case E_USER_DEPRECATED:
					$output = "<b>Deprecated:</b> $errstr in <b>$errfile</b> on line <b>$errline</b>";
					break;

				default:
					$output = "<b>Fatal error:</b> $errstr in <b>$errfile</b> on line <b>$errline</b>";
					$fatal = true;
					break;
			}

			echo $output . '<br>' . PHP_EOL;

			if (ini_get('log_errors')) {
				error_log(strip_tags($output));
			}

			if (in_array($errno, [E_ERROR, E_USER_ERROR])) exit;
		});

		// Make sure files and folders are writable
		if (!is_writable(__DIR__)) {
			throw new Exception("Directory is not writable");
		}

		foreach(['index.php', 'update.php', 'ping.php'] as $file) {
			if (!is_writable($file)) {
				throw new Exception("$file is not writable");
			}
		}

		// Make sure script is not executed too often
		if (file_exists('lastrun.dat') && file_get_contents('lastrun.dat') > strtotime('-5 minutes')) {
			throw new Exception('For safety reasons I need at least 5 minutes of rest. =)<br>You can delete the file "lastrun.dat" to immediately run again.');
		}

		// Check in for work
		file_put_contents('lastrun.dat', time());

		// Run tests
		satellite::run();

	} catch (Exception $e) {
		echo 'Error: '. $e->getMessage();
	}
