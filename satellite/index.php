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
  
// Set error handler
  function error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
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
    
    $backtraces = debug_backtrace();
    $backtraces = array_slice($backtraces, 2);
    
    if (!empty($backtraces)) {
      foreach ($backtraces as $backtrace) {
        if (empty($backtrace['file'])) continue;
        $backtrace['file'] = preg_replace('#^'. $_SERVER['DOCUMENT_ROOT'] .'#', '~', str_replace('\\', '/', $backtrace['file']));
        $output .= "<br />" . PHP_EOL . "  <- <b>{$backtrace['file']}</b> on line <b>{$backtrace['line']}</b> in <b>{$backtrace['function']}()</b>";
      }
    }
    
    if (in_array(strtolower(ini_get('display_errors')), array('on', 'true', '1'))) {
      if (in_array(strtolower(ini_get('html_errors')), array(0, 'off', 'false')) || PHP_SAPI == 'cli') {
        echo strip_tags($output) . PHP_EOL;
      } else {
        echo $output . '<br />' . PHP_EOL;
      }
    } else {
      if (!empty($_SERVER['REQUEST_URI'])) $output .= " {$_SERVER['REQUEST_URI']}";
    }
    
    if (ini_get('log_errors')) {
      error_log(strip_tags($output));
    }
    
    if (in_array($errno, array(E_ERROR, E_USER_ERROR))) exit;
  }
  
  set_error_handler('error_handler');
  
// Make sure files and folders are writeable
  if (!is_writable('./')) die('Directory is not writable.');
  if (!is_writable('compatibility.inc.php')) die('compatibility.inc.php is not writable.');
  if (!is_writable('performance.class.php')) die('performance.class.php is not writable.');
  if (!is_writable('update.php')) die('update.php is not writable.');
  if (!is_writable('index.php')) die('index.php is not writable.');

// Make sure script is not executed too often
  if (file_exists('lastrun.dat') && file_get_contents('lastrun.dat') > strtotime('-5 minutes')) {
    die('I recently did my duty. For safety reasons I need at least 5 minutes of rest. =)<br />You may delete the file lastrun.dat to immediately run again.');
  }
  
// Check in for work
  file_put_contents('lastrun.dat', time());
  
// Initiate performance class object
  require_once('performance.class.php');
  $performance = new performance;
  
// Run performance check
  $performance->run_test();
  
?>