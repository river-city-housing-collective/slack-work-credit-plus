<?php
function db_connect() {

        // Define connection as a static variable, to avoid connecting more than once 
    static $connection;

        // Try and connect to the database, if a connection has not been established yet
    if(!isset($connection)) {
             // Load configuration as an array. Use the actual location of your configuration file
        $config = parse_ini_file(
            isset($_SERVER['HOME']) ?
                $_SERVER['HOME'] . '/private/config.ini' :
                $_SERVER['INI_PATH']); // todo fix
        $connection = mysqli_connect($config['servername'],$config['username'],$config['password'],$config['dbname']);
    }

        // If connection was not successful, handle the error
    if($connection === false) {
            // Handle error - notify administrator, log to a file, show an error screen, etc.
        return mysqli_connect_error(); 
    }
    return $connection;
}

// Connect to the database
$conn = db_connect();

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

ini_set("date.timezone", "America/Chicago");

$now = new DateTime();
$mins = $now->getOffset() / 60;
$sgn = ($mins < 0 ? -1 : 1);
$mins = abs($mins);
$hrs = floor($mins / 60);
$mins -= $hrs * 60;
$offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

$stmt = $conn->prepare("SET time_zone='$offset';");
$stmt->execute();