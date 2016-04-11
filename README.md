
www.webhosting-performance.com

# INSTRUCTIONS

## 1. Unzip the "satellite" folder.

## 2. Insert your MySQL credentials in config.php:

    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', '');
    define('DB_PASSWORD', '');
    define('DB_DATABASE', '');
    define('DB_PREFIX', 'whptest_');

## 3. Upload the files to your desired location.
      Preferably in a somewhat cryptic folder name such as whp-x4j4K1c2/.
      You wouldn't want someone repeatedly calling the script.

## 4. Make the satellite folder and it's contents writeable.
      Chmod it to e.g. 0777 to allow test data to be written and receive automatic updates.

## 5. Point your web browser to the folder:

      Example: http://www.mysite.com/whp-x4j4K1c2/

      This will immediately execute the performance test unless there
      are errors. The test takes aproximately 10 seconds. If you recceive
      errors e-mail us at info@webhosting-performance.com.

## 6. Add Satellite for monitoring
      Among with your test results you will have the opportunity to add the satellite for monitoring. Adding private machines or home computers is a violation to our service. Only web hosting providers are allowed to be monitored.
      