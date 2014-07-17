<?php
    include "database_connection.php";

    // RELOAD DATA
    $query = "TRUNCATE TABLE suratsanggahan";
    if (!mysql_query($query)) die(mysql_error());
    $files = array();
	$directory = 'C:/xampplite/htdocs/veritrans/fds-data/suratsanggahan/';
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
	while($it->valid()) {
	    if (!$it->isDot()) {
            $filename = $it->getSubPathName();
            $files[] = $filename; //push to array
	    }
	    $it->next();
	}
	//LOOP EACH FILE
    echo 'Importing chargeback data...'.PHP_EOL;
    for($i=0;$i<sizeof($files);$i++) {
        $file = fopen($directory.$files[$i], "r");
        echo $files[$i].PHP_EOL;
        $Merchant = substr($files[$i],0,-4);

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
                echo 'Problem with'.$files[$i].' ';
                die(mysql_error()).PHP_EOL;
            }
        } 
    }
    // DELETE NULL VALUE
    deleteNullRecords('suratsanggahan'); //kolom2 kosong dari csv masuk entah kenapa 
    echo 'Done'.PHP_EOL;


    //function timestamp
    //Note: solve mm/dd/yyyy vs. dd/mm/yyyy problem (still ambiguous for dd < 12) assuming input valid
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
    //function timestamp

    //function to delete null records
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
    //function to delete null records
?>