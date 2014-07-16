<?php
    // ----- function timestamp -> Note: solve mm/dd/yyyy vs. dd/mm/yyyy problem (still ambiguous for dd < 12) assuming input valid
    function toTimeStamp($Time) {
        $datetime = explode(" ", $Time); 
        $date = $datetime[0]; 
        if(mb_stripos($date, "/")!==FALSE) {
            $date2 = explode("/", $date);
            $month = $date2[0];
            $day = $date2[1]; 
            if($month>12) list($day,$month) = array($month,$day);
            $year = $date2[2]; 
            $date = $year."-".$month."-".$day;
            $time = $datetime[1];
            $Time = $date." ".$time;
        }
        return $Time;
    }
    // ----- function timestamp

    // ----- function to delete null records
    function deleteNullRecords($TableName) {
        //identify fields in the table
        $fields = array();
        $query_field = "show columns from ".$TableName;
        $result = mysql_query($query_field);
        while($row = mysql_fetch_assoc($result)) {
            array_push($fields, $row['Field']);
        }
        //delete~
        $nfield = sizeof($fields);
        $query_null = "DELETE FROM ".$TableName." WHERE ";
        for($i=0;$i<$nfield;$i++) {
            $query_null = $query_null.$fields[$i]." IS NULL";
            if($i<$nfield-1) {
                $query_null = $query_null." AND ";
            }
        }
        if(!mysql_query($query_null)) die(mysql_error());
    }
    // ----- function to delete null records

    include "database_connection.php";

    // RELOAD DATA
    $query = "TRUNCATE TABLE suratsanggahan";
    if (!mysql_query($query)) die(mysql_error());
    $files = array();
	$directory = 'C:/xampplite/htdocs/veritrans/fds-data/suratsanggahan/';
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
	while($it->valid()) {
	    if (!$it->isDot()) {
	    	$key = $it->key();
            $key[48] = '/';
            array_push($files, $key); 
	    }
	    $it->next();
	}
	//insert data from all csv to table database
    $year = date('y');
    for($i=0;$i<sizeof($files);$i++) {    
        //using getcsv
        $file = fopen($files[$i], "r");
        $csvname = substr($files[$i], 49);
        echo 'Importing '.$csvname.PHP_EOL;
        $filename = explode(".", $csvname);
        $Merchant = $filename[0];

        $emapData = fgetcsv($file, 10000, ",");
        while (($emapData = fgetcsv($file, 10000, ",")) != false) {
            $TradeTime = $emapData[1];
            $TradeTime = toTimeStamp($TradeTime);
            $SuratSanggahan = $emapData[9];
            if (stripos($SuratSanggahan, 'konfirm fraud')) {
                $Flag = "Deny";
            } else {
                $Flag = "Accept";
            }
            $query = "INSERT INTO suratsanggahan (PaymentType, TradeTime, OrderID, CCNumber, CustomerEmail, Amount, SecurityRecom, LatestStatus, Result, SuratSanggahan, Flag, Tanggal, Refund, Notes, Merchant) VALUES ('$emapData[0]', '$TradeTime', '$emapData[2]', '$emapData[3]', '$emapData[4]', '$emapData[5]', '$emapData[6]', '$emapData[7]', '$emapData[8]', '$SuratSanggahan', '$Flag', '$emapData[10]', '$emapData[11]', '$emapData[12]','$Merchant')";
            if (!mysql_query($query)) {
                echo 'Problem with'.$csvname.' ';
                die(mysql_error()).PHP_EOL;
            }
        } 
    }
    // DELETE NULL VALUE
    deleteNullRecords('suratsanggahan');
    echo 'Done'.PHP_EOL;
?>