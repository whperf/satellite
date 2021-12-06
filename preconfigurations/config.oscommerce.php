<?php
  include('../includes/configure.php');

// MySQL Database Configuration
  define('DB_USERNAME', DB_SERVER_USERNAME);
  define('DB_PASSWORD', DB_SERVER_PASSWORD);
  define('DB_PREFIX', 'whptest_');

// Geographical Location e.g. US
  define('COUNTRY_CODE', @file_get_contents('country.dat'));

// Automatic Updates
  define('AUTO_UPDATE', true);
