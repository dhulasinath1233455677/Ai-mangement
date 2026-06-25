<?php
// database/connect.php

$host = "localhost";
$db_name = "project_management";
$username = "root";
$password = ""; // Default for XAMPP/WAMP is empty; MAMP uses "root"

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    die("Database Engine Connectivity Failure: " . $exception->getMessage());
}
?>