<?php
// MySQL Database Configuration
  define('DB_SERVER', 'localhost');
  define('DB_USERNAME', '');
  define('DB_PASSWORD', '');
  define('DB_DATABASE', '');
  define('DB_PREFIX', 'whptest_');
  
// Geographical Location e.g. US
  define('COUNTRY_CODE', @file_get_contents('country.dat'));
  
// Automatic Updates
  define('AUTO_UPDATE', true);
?>