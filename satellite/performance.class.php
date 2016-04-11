<?php
  
  class performance {
    private $_post_fields = array();
    private $_version = '2.0';
    private $_api_url = 'http://www.webhosting-performance.com/api/';
    private $_debug = false;
    private $_country_code = '';
    
    public function __construct() {
      
    // Set country code
      $this->_country_code = $this->_get_country();
    
    // Set IP address
      if (!isset($_SERVER['SERVER_ADDR']) || $_SERVER['SERVER_ADDR'] == '') {
        $_SERVER['SERVER_ADDR'] = $this->_get_ip();
      }
      
    // Perform auto update (silently)
      $this->update(true);
      
    // CPU Load
      $this->perform_pi_calc();
      
    // MySQL
      $this->perform_mysql_test(1000);
      
    // Disk I/O
      $this->perform_disk_test(30000);
      
    // Bandwidth
      $this->perform_upstream_test(1*1024*1000);
      $this->perform_downstream_test(1*1024*1000);
      
    // Environment
      $this->collect_server_info();
      
    // Send data
      $this->submit_results();
    }
    
    private function _get_country() {
      
      if (defined('COUNTRY_CODE') && COUNTRY_CODE != '') return COUNTRY_CODE;
      
      if (file_exists('country.dat')) {
        $country_code = file_get_contents('country.dat');
        if (!empty($country_code)) return $country_code;
      }
      
    // Attempt #1 - Freegeoip.net
      $response = $this->http_request('http://ip-api.com/json');
      $response = json_decode($response, true);
      $country_code = $response['countryCode'];
      if (!empty($country_code) && $country_code != 'XX') {
        file_put_contents('country.dat', $country_code);
        return $country_code;
      }
      
    // Attempt #2 - HostIP.info
      $response = $this->http_request('http://api.hostip.info/country.php');
      $country_code = $response;
      if (!empty($country_code) && $country_code != 'XX') {
        file_put_contents('country.dat', $country_code);
        return $country_code;
      }
      
      return 'US';
    }
    
    private function _get_ip() {
      $response = $this->http_request('http://checkip.dyndns.org/');
      
      if (preg_match('/Current IP Address: (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $response, $matches)) {
        return $matches[1];
      }
    }
    
    public function perform_pi_calc() {
      
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
      
      $this->_post_fields['cpu']['pi'] = $time_elapsed;
    }
    
    /*
    public function perform_for_calc($cycles=10000) {
      
      $tsStart = microtime(true);
      
      for ($i=0; $i<$cycles; $i++) {
        // Do something
        // Coming in future?
      }
      
      $time_elapsed = microtime(true) - $tsStart;
      
      $this->_post_fields['cpu']['for'] = $time_elapsed;
    }
    */
  
    public function perform_mysql_test($cycles=1000) {
    
      require_once('database.class.php');
      $database = new database;
      
      ############################################################
      
    // Measure time to connect to MySQL
      $time_elapsed = 0;
      
      for ($i=0; $i < 10; $i++) {
        $tsStart = microtime(true);
        $database->connect();
        $time_elapsed += microtime(true) - $tsStart;
        
        $database->disconnect();
      }
      
      $measure_time = $time_elapsed / 10;
      
      $this->_post_fields['mysql']['connect'] = $measure_time;
      
      ############################################################
      
    // Collect MySQL server info
      $database->connect();
      
      if (method_exists($database, 'get_server_info')) {
        $this->_post_fields['mysql']['version'] = $database->get_server_info();
      }
      
      $database->query("DROP TABLE IF EXISTS `". $database->table_prefix ."test`;");

      $database->query(
        "CREATE TABLE `". $database->table_prefix ."test` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `column` VARCHAR(32) NOT NULL
        ) ENGINE = MyISAM;"
      );
      
      ############################################################
      
    // Measure time to insert MySQL data
      $tsStart = microtime(true);
      
      for ($i=0; $i < $cycles; $i++) {
        $database->query(
          "INSERT INTO `". $database->table_prefix ."test` (`column`) VALUES ('". str_pad($i, strlen($cycles), '0', STR_PAD_LEFT) ."');"
        );
      }

      $time_elapsed = microtime(true) - $tsStart;
      
      $data_speed = round($cycles / $time_elapsed);
      
      $this->_post_fields['mysql']['insert'] = $data_speed;
    
      ############################################################

    // Measure time to select and get MySQL data
      $tsStart = microtime(true);
      
      $result = $database->query("SELECT * FROM `". $database->table_prefix ."test`");
      
      $num_results = 0;
      while($row = $database->fetch($result)) {
        extract($row);
        $num_results++;
      }
      
      $database->free($result);

      $time_elapsed = microtime(true) - $tsStart;
      
      $data_speed = $cycles / $time_elapsed;
      
      $this->_post_fields['mysql']['select'] = $data_speed;
      
      ############################################################
      
    // Measure time to search and get MySQL data
      $tsStart = microtime(true);
      
      for ($i=0; $i < $cycles; $i++) {
        $result = $database->query(
          "SELECT * FROM `". $database->table_prefix ."test`
          WHERE `column`='". str_pad(rand(0, $cycles), strlen($cycles), '0', STR_PAD_LEFT) ."';"
        );
        $database->fetch($result);
        $database->free($result);
      }

      $time_elapsed = microtime(true) - $tsStart;
      
      $measure_time = ($time_elapsed / $cycles * 1000) * 1000;
      
      $data_speed = $cycles / $time_elapsed;
      
      $this->_post_fields['mysql']['search'] = $data_speed;
      
      ############################################################
      
    // Measure time to update MySQL data
      $tsStart = microtime(true);
      
      for ($i=0; $i < $cycles; $i++) {
        $database->query(
          "UPDATE `". $database->table_prefix ."test`
          set `column` = `column`
          WHERE `column`='". str_pad(rand(0, $cycles), strlen($cycles), '0', STR_PAD_LEFT) ."';"
        );
      }
      
      $time_elapsed = microtime(true) - $tsStart;
      
      $measure_time = ($time_elapsed / $cycles * 1000) * 1000;
      
      $data_speed = $cycles / $time_elapsed;
      
      $this->_post_fields['mysql']['update'] = $data_speed;
      
      ############################################################
      
    // Measure time to delete MySQL data
      $tsStart = microtime(true);
      
      for ($i=0; $i < $cycles; $i++) {
        $database->query(
          "DELETE from `". $database->table_prefix ."test`
          WHERE `column`='". str_pad($i, strlen($cycles), '0', STR_PAD_LEFT) ."';"
        );
      }
      
      $time_elapsed = microtime(true) - $tsStart;
      
      $measure_time = ($time_elapsed / $cycles * 1000) * 1000;
      
      $data_speed = $cycles / $time_elapsed;
      
      $this->_post_fields['mysql']['delete'] = $data_speed;
      
      ############################################################
      
      $database->query("drop table `". $database->table_prefix ."test`;");
    }
    
    public function perform_disk_test($cycles=10000) {
      
      $file = 'read_write_compile.deleteme.php';
      
      file_put_contents($file, '');
    
      ############################################################
      
    // Write to disk
      $tsStart = microtime(true);

      $fh = fopen($file, 'w') or die('Can\'t open file');
      fwrite($fh, '<?php' . PHP_EOL);
      
      for ($i=0; $i < $cycles; $i++) {
        $output = '  function dummy_'.$i.'($var) {' . PHP_EOL
                 . '    return false;' . PHP_EOL
                 . '  }' . PHP_EOL;
        fwrite($fh, $output);
      }
      fwrite($fh, '?>' . PHP_EOL);
      fclose($fh);
      
      $time_elapsed = microtime(true) - $tsStart;
      
      $filesize = filesize($file);
      
      $measure_amount = $filesize / $time_elapsed / 1024 / 1000;
      
      $this->_post_fields['disk']['write'] = $measure_amount;

      ############################################################
      
    // Read from disk
      $tsStart = microtime(true);
      
      $fh = fopen($file, 'r');
      while (!feof($fh)) {
        $tmp = fread($fh, 4096);
      }
      fclose($fh);
      
      $time_elapsed = microtime(true) - $tsStart;
      
      unset($buffer);
      
      $filesize = filesize($file);
      
      $measure_amount = $filesize / $time_elapsed / 1024 / 1000;
      
      $this->_post_fields['disk']['read'] = $measure_amount;

      ############################################################
      
    // Read and compile large php file from disk
      $tsStart = microtime(true);
      
      require_once($file);
      
      $time_elapsed = microtime(true) - $tsStart;
      
      $filesize = filesize($file);
      
      $measure_amount = $filesize / $time_elapsed / 1024 / 1000;
      
      $this->_post_fields['disk']['compile'] = $measure_amount;
      
      ############################################################
      
      unlink($file);
    }
    
    public function perform_upstream_test($size=1024000, $country_code=null) {
      
      if (empty($country_code)) $country_code = $this->_country_code;
      
      switch($country_code) {
        case 'DE':
          $url = 'http://mirror.de.leaseweb.net/';
          break;
        case 'FI':
          $url = 'http://www.nic.funet.fi/';
          break;
        case 'JP':
          $url = 'http://ftp.jaist.ac.jp/';
          break;
        case 'NL':
          $url = 'http://mirror.nl.leaseweb.net/';
          break;
        case 'DK':
        case 'FI':
        case 'NO':
        case 'SE':
          $url = 'http://www.sunet.se/';
          break;
        case 'US':
          $url = 'http://mirror.us.leaseweb.net/';
          break;
        default:
          $url = 'http://'. strtolower($this->_country_code) .'.releases.ubuntu.com/';
          break;
      }
      
      $boundary = "---------------------".substr(md5(rand(0,32000)), 0, 10);
      
      $parts = parse_url($url);
      $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);
      
      if (!$fp) throw new Exception("Problem with $url, $errstr");
      
      $out = "POST " . $parts['path'] ." HTTP/1.1\r\n"
           . "Host: ". $parts['host'] ."\r\n"
           . "Content-Type: multipart/form-data; boundary=" . $boundary . "\r\n"
           . "Content-Length: ". $size ."\r\n"
           . "Connection: Close\r\n\r\n";
           
      fwrite($fp, $out);
      
      $tsStart = microtime(true);
      
      fwrite($fp, str_repeat('0', $size));
      
      $time_elapsed = microtime(true) - $tsStart;
      
      $speed = $size / $time_elapsed * 8 / 1000000;
      
      if (!$fp) {
        throw new Exception("Problem with $url, $php_errormsg");
      }
      $response = @stream_get_contents($fp);
      if ($response === false) {
        throw new Exception("Problem reading data from $url, $php_errormsg");
      }
      
      $this->_post_fields['bandwidth']['upstream'] = $speed;
      $this->_post_fields['bandwidth']['upstream_url'] = $url;
    }
    
    public function perform_downstream_test($size=1024000, $country_code=null) {
      
      if (empty($country_code)) $country_code = $this->_country_code;
      
      switch($country_code) {
        case 'DE':
          $url = 'http://mirror.de.leaseweb.net/ubuntu/dists/hardy/main/binary-i386/Packages.bz2';
          break;
        case 'FI':
          $url = 'http://www.nic.funet.fi/pub/Linux/INSTALL/Ubuntu/archive/dists/hardy/main/binary-i386/Packages.bz2';
          break;
        case 'JP':
          $url = 'http://ftp.jaist.ac.jp/ubuntu/dists/hardy/main/binary-i386/Packages.bz2';
          break;
        case 'NL':
          $url = 'http://mirror.nl.leaseweb.net/ubuntu/dists/hardy/main/binary-i386/Packages.bz2';
          break;
        case 'DK':
        case 'NO':
        case 'SE':
          $url = 'http://ftp.sunet.se/pub/Linux/distributions/ubuntu/ubuntu/dists/hardy-backports/Contents-i386.gz';
          break;
        case 'US':
          $url = 'http://mirror.us.leaseweb.net/ubuntu/dists/hardy/main/binary-i386/Packages.bz2';
          break;
        default:
          $url = 'http://'. strtolower($this->_country_code) .'.releases.ubuntu.com/oneiric/ubuntu-11.10-alternate-i386.iso.zsync';
          break;
      }
      
      $parts = parse_url($url);
      $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);
      
      if (!$fp) throw new Exception("Problem with $url, $errstr");
      
      $out = "GET " . $parts['path'] ." HTTP/1.1\r\n"
           . "Host: ". $parts['host'] ."\r\n"
           . "Connection: Close\r\n\r\n";
           
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
      
      $speed = strlen($response) / $time_elapsed * 8 / 1024 / 1000;
      
      $this->_post_fields['bandwidth']['downstream'] = $speed;
      $this->_post_fields['bandwidth']['downstream_url'] = $url;
    }
    
    public function collect_server_info() {
    
      $server_info = array(
        'php' => array(
          'version' => phpversion(),
          'extensions' => implode(', ', get_loaded_extensions()),
          'disabled_functions' => ini_get('disable_functions'),
          'memory_limit' => ini_get('memory_limit'),
          'safe_mode' => ini_get('safe_mode'),
          'register_globals' => ini_get('register_globals'),
        ),
        'httpd' => array(
          'name' => substr($_SERVER['SERVER_SOFTWARE'], 0, strpos($_SERVER['SERVER_SOFTWARE'], ' ')),
        ),
        'os' => array(
          'type' => PHP_OS,
          'version' => php_uname('r'),
        ),
        'network' => array(
          'country' => $this->_country_code,
          'machine' => php_uname('n'),
          'address' => $_SERVER['SERVER_ADDR'],
          'hostname' => gethostbyaddr($_SERVER['SERVER_ADDR']),
        ),
        'satellite' => array(
          'uri' => (($_SERVER['SERVER_PORT'] == '80') ? 'http://' : 'https://') . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'],
          'version' => $this->_version,
          'checksum' => md5_file(__FILE__),
        ),
        'noreport' => (isset($_GET['noreport']) ? 'true' : 'false'),
      );
      
      $this->_post_fields = array_merge($this->_post_fields, $server_info);
    }
    
    private function http_request($url, $post_fields=false) {
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_HEADER, "Expect:\r\n");
      curl_setopt($ch, CURLOPT_TIMEOUT, 15);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      if ($post_fields) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
      }
      
      $response = curl_exec($ch);
      
      if ($response === false) throw new Exception("Problem reading data from $url, ". curl_error($ch));
        
      return $response;
    }
    
    public function update($silent=false) {
      
      if (defined('AUTO_UPDATE') && !AUTO_UPDATE) {
        echo '<p>Auto updating is disabled, see config.php</p>';
        return;
      }
      
      $updated = false;
    
      $url = $this->_api_url.'update2';
      
      $response = $this->http_request($url, array('whoami' => (($_SERVER['SERVER_PORT'] == '80') ? 'http://' : 'https://') . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']));
      
      $response = json_decode($response, true);
      if ($response === false) trigger_error("Unexpected response data from $url", E_USER_ERROR);
      
      if (strtolower($response['status']) != 'ok') die($response['status']);
      
      if (!isset($response['files']) || !is_array($response['files'])) die('No files listed by API.');
      
      foreach ($response['files'] as $file) {
        $file['source'] = base64_decode($file['source']);
      
        if (md5($file['source']) == $file['checksum']) {

          if (!file_exists($file['filename']) || $file['checksum'] != md5_file($file['filename'])) {
          
            if (file_put_contents($file['filename'], $file['source'])) {
              $output[] = $file['filename'] .': Updated';
              $updated = true;
              
            } else {
              $output[] = $file['filename'] .': Cannot write to file';
            }
            
          } else {
            $output[] = $file['filename'] . ': Already up to date';
          }
            
        } else {
          $output[] = $file['filename'] . ': Cheksum error from api';
        }
      }
      
      if (!$updated) return;
      
      file_put_contents('lastrun.dat', '');
      
      if (!$silent) {
        echo implode('<br />' . PHP_EOL, $output);
        echo '<p>To run the test again, <a href="'. $_SERVER['REQUEST_URI'] .'">click here</a>.</p>';
      } else {
        header('Location: '. $_SERVER['REQUEST_URI']);
        exit;
      }
    }
    
    public function submit_results() {
      
      $response = $this->http_request($this->_api_url.'report', $this->_post_fields);
      
      $content = unserialize($response);
      if ($content === false) die('Invalid API response data:<br/>' . $response);
      
      echo $content['html'];
    }
  }

?>