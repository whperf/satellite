<?php
// Require PHP 5
  if (phpversion() < 5) die('This script requires at least PHP 5 (2004-07-13). Your PHP version is :'. phpversion());

// Set server timezone
  date_default_timezone_set(ini_get('date.timezone'));
  
// System
  ini_set('display_errors', 'On');
  error_reporting(E_ALL | E_STRICT);
  ignore_user_abort(true);
  set_time_limit(60);
  
// Load compatibility for old platforms
  require_once('compatibility.inc.php');

// Make sure script is not executed too often
  if (file_exists('lastrun.dat') && file_get_contents('lastrun.dat') > strtotime('-5 minutes')) {
    die('I recently did my duty. For safety reasons I need at least 5 minutes of rest. =)<br />You may delete the file lastrun.dat to immediately run again.');
  }

// Make sure files and folders are writeable
  if (!is_writable('compatibility.inc.php')) die('compatibility.inc.php is not writable.');
  if (!is_writable('performance.class.php')) die('performance.class.php is not writable.');
  if (!is_writable('update.php')) die('update.php is not writable.');
  if (!is_writable('index.php')) die('index.php is not writable.');
  if (!is_writable('./')) die('Directory is not writable.');
  
// Check in for work
  file_put_contents('lastrun.dat', time());

// Load database class object
  require_once('database.class.php');

// Initiate performance class object
  require_once('performance.class.php');
  $performance = new performance;
  
// Perform auto update (silently)
  $performance->update(true);
  
// CPU Load
  $performance->perform_pi_calc();
  
// MySQL
  $performance->perform_mysql_test(1000);
  
// Disk I/O
  $performance->perform_disk_test(30000);
  
// Bandwidth
  $performance->perform_upstream_test(1*1024*1000);
  $performance->perform_downstream_test(1*1024*1000);
  
// Environment
  $performance->collect_server_info();
  
// Send data
  $performance->submit_results();
  
?>