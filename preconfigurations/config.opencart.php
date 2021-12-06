<?php
  include('../config.php');

// MySQL Database Configuration
  define('DB_SERVER', DB_HOSTNAME);
  define('DB_PREFIX', 'whptest_');

// Geographical Location e.g. US
  define('COUNTRY_CODE', @file_get_contents('country.dat'));

// Automatic Updates
  define('AUTO_UPDATE', true);
