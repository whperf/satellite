<?php
  include('../includes/config.inc.php');

// MySQL Database Configuration
  define('DB_PREFIX', 'whptest_');

// Geographical Location e.g. US
  define('COUNTRY_CODE', @file_get_contents('country.dat'));

// Automatic Updates
  define('AUTO_UPDATE', true);
