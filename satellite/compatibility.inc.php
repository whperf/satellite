<?php

// Missing in < PHP 5
  if (!function_exists('file_put_contents')) {
    function file_put_contents($file, $data) {
      $fh = fopen($file, 'w');
      if ($fh) {
        $bytes = fwrite($fh, $data);
        fclose($fh);
        return $bytes;
      }
    }
  }

// Missing in < PHP 5
  if (!function_exists('file_get_contents')) {
    function file_get_contents($file) {
      $fh = fopen($file, 'r');
      $contents = fread($fh, filesize($file));
      fclose($fh);
      return $fcontents;
    }
  }
