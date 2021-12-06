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

// If JSON PECL is Missing
  if (!function_exists('json_encode')) {
    function json_encode($data) {
      switch ($type = gettype($data)) {
        case 'NULL':
          return 'null';
        case 'boolean':
          return ($data ? 'true' : 'false');
        case 'integer':
        case 'double':
        case 'float':
          return $data;
        case 'string':
          return '"' . addslashes($data) . '"';
        case 'object':
          $data = get_object_vars($data);
        case 'array':
          $output_index_count = 0;
          $output_indexed = [];
          $output_associative = [];
          foreach ($data as $key => $value) {
            $output_indexed[] = json_encode($value);
            $output_associative[] = json_encode($key) . ':' . json_encode($value);
            if ($output_index_count !== NULL && $output_index_count++ !== $key) {
              $output_index_count = NULL;
            }
          }
          if ($output_index_count !== NULL) {
            return '[' . implode(',', $output_indexed) . ']';
          } else {
            return '{' . implode(',', $output_associative) . '}';
          }
        default:
          return ''; // Not supported
      }
    }
  }
