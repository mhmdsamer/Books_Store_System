<?php
$servername = "localhost";
$username = "root"; // Change this if you have a different MySQL username
$password = ""; // Change this if you have a password
$database = "bookstore";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
