
                    www.webhosting-performance.com

######################################################################
############################# INSTRUCTIONS ###########################
######################################################################

# 1. Unzip the "satellite" folder.

# 2. Edit config.php:

    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', '');
    define('DB_PASSWORD', '');
    define('DB_DATABASE', '');
    define('DB_PREFIX', 'whptest_');

# 3. Upload the files to your desired location. Preferably in a somehow
     cryptic folder name such as whp-x4j4K1c2/. You wouldn't want someone
     repeatingly calling the script.

# 4. Make the satellite folder and it's contents writeable by chmodding
     it to i.e. 0777. This is to allow test data to be written and
     automatic updates.

# 5. Point your web browser to the folder:

     Example: http://www.mysite.com/whp-x4j4K1c2/

     This will immediately execute the performance test unless there
     are errors. The test takes aproximately 10 seconds. If you recceive
     errors e-mail us at info@webhosting-performance.com.

######################################################################

Done!
