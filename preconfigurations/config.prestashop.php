<?php
  $config = include '../app/config/parameters.php';

// MySQL Database Configuration
  define('DB_SERVER', $config['parameters']['database_host']);
  define('DB_USERNAME', $config['parameters']['database_user']);
  define('DB_PASSWORD', $config['parameters']['database_password']);
  define('DB_DATABASE', $config['parameters']['database_name']);
  define('DB_PREFIX', 'whptest_');

// Geographical Location e.g. US
  define('COUNTRY_CODE', @file_get_contents('country.dat'));

// Automatic Updates
  define('AUTO_UPDATE', true);
