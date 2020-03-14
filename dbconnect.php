<?php
function db_connect() {

        // Define connection as a static variable, to avoid connecting more than once 
    static $connection;

        // Try and connect to the database, if a connection has not been established yet
    if(!isset($connection)) {
             // Load configuration as an array. Use the actual location of your configuration file
        $config = parse_ini_file(
            $_SERVER["REMOTE_ADDR"] == '127.0.0.1' || $_SERVER["REMOTE_ADDR"] == '::1' ?
            'config.ini' :
            '/home/isaneu/private/config.ini'
        ); 
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