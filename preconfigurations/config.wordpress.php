<?php
  include('../wp-config.php');

// MySQL Database Configuration
  define('DB_SERVER', DB_HOST);
  define('DB_USERNAME', DB_USER);
  define('DB_PASSWORD', DB_PASSWORD);
  define('DB_DATABASE', DB_NAME);
  define('DB_PREFIX', 'whptest_');

// Geographical Location e.g. US
  define('COUNTRY_CODE', @file_get_contents('country.dat'));

// Automatic Updates
  define('AUTO_UPDATE', true);
