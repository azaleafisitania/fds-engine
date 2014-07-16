<?php
	$mysql_hostname = "localhost";
	$mysql_user = "root"; // MySQL username
	$mysql_password = ""; // MySQL password
	$mysql_database = "txn_data"; // Database name
	$prefix = "";
	$db = mysql_connect($mysql_hostname, $mysql_user, $mysql_password) or die('Error connecting to MySQL server: ' . mysql_error()); // Connect to MySQL server
	mysql_select_db($mysql_database, $db) or die('Error selecting MySQL database: ' . mysql_error()); // Select database
?>