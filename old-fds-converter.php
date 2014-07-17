<?php	
	include "database_connection.php";
	
	// ERASE DATA
	$query = "TRUNCATE TABLE old_fds";
	if (!mysql_query($query)) die(mysql_error());

	// LOAD FILE
	//get all csv files in folder "old-dfs"
	$files = array();
	$directory = 'C:/xampplite/htdocs/veritrans/fds-data/old-fds/';
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
	while($it->valid()) {
	    if (!$it->isDot()) {
            $filename = $it->getSubPathName();
            $files[] = $filename; //push to array
	    }
	    $it->next();
	}
	
	//LOOP EACH FILE
	$j=1; //just number
	echo 'Importing transaction...'.PHP_EOL;
	    for($i=0;$i<sizeof($files);$i++) {
        $file = fopen($directory.$files[$i], "r");
        echo $files[$i].PHP_EOL;
  		
  		$emapData = fgetcsv($file, 10000, ",");
		while (($emapData = fgetcsv($file, 10000, ",")) != false) {
    		//solve ReDi date problem
	    	$Date_Time = $emapData[1];
	    	$Date_Time = toTimeStamp2($Date_Time);
	    	$Customer_Name = mysql_real_escape_string($emapData[4]); //biar bisa baca (')
	    	$Billing_Address = mysql_real_escape_string($emapData[18]); //biar bisa baca (')
	    	$IPID_State = mysql_real_escape_string($emapData[24]); //biar bisa baca (')
	    	//$TxnValue = str_replace(',', '', $emapData[6]); //biar bisa baca (,)
	    	//$LocValue = str_replace(',', '',  $emapData[8]); //biar bisa baca (,)
	    	$query = "INSERT INTO old_fds (No, TxnTime, TxnId, CustomerID, CustomerName, CustomerEmailAddress, TxnValue, TxnCurrency, LocValue, LocCurrency, OrigRecom, Reason, Note, CardType, CardNoMasked, AuthResp, RuleHits, PRISMScore, BillingAddress, BillingCountry, ShipZip, ShippingState, ShippingCountry, CustIPID, IPIDState, IPIDCountry, BINCountry, ShipMeth, TOF, ReD_TOF, DeviceID, OrderLines, FraudType, CurrentStatus, ChargebackStatus, SubClient) VALUES ('$j', '$Date_Time', '$emapData[2]', '$emapData[3]', '$Customer_Name', '$emapData[5]', '$emapData[6]', '$emapData[7]', '$emapData[8]', '$emapData[9]', '$emapData[10]', '$emapData[11]', '$emapData[12]', '$emapData[13]', '$emapData[14]', '$emapData[15]', '$emapData[16]', '$emapData[17]', '$Billing_Address', '$emapData[19]', '$emapData[20]', '$emapData[21]', '$emapData[22]', '$emapData[23]', '$IPID_State', '$emapData[25]', '$emapData[26]', '$emapData[27]', '$emapData[28]', '$emapData[29]', '$emapData[30]', '$emapData[31]', '$emapData[32]', '$emapData[33]', '$emapData[33]', '$emapData[34]')";
	    	if (!mysql_query($query)) {
	    		echo 'Problem with '.$files[$i].' ';
                die(mysql_error()).PHP_EOL;
            }
      		$j++;
    	}
	}
    // DELETE NULL VALUE
	deleteNullRecords('old_fds');    
    echo 'Done'.PHP_EOL;	


    //function timestamp
    //Note: solve format like (example "Thu27Mar")
	function toTimeStamp2($Time) {
		$year = '2014';
		$date = substr($Time, 3, 2);
		$month = substr($Time, 5, 3); 
		$month = date('m',strtotime($month)); 
		$Date = implode("-", array($year, $month, $date));
		$DateTime = implode(" ", array($Date, substr($Time, 9, 8))); 
		return $DateTime;
	}
	//function timestamp

	//function delete null records
	function deleteNullRecords($tableName) {
		//identify fields in the table
	    $fields = array();
	    $query_field = "show columns from $tableName";
	    $result = mysql_query($query_field);
	    while($row = mysql_fetch_assoc($result)) {
	        array_push($fields, $row['Field']);
	    }
	    //query delete~
	    $nfield = sizeof($fields);
	    $query_null = "DELETE FROM $tableName WHERE ";
	    for($i=0;$i<$nfield;$i++) {
	        $query_null = $query_null.$fields[$i]." IS NULL";
	        if($i<$nfield-1) {
	            $query_null = $query_null." AND ";
	        }
	    }
	    if(!mysql_query($query_null)) die(mysql_error());
	}
	//function delete null records
?>