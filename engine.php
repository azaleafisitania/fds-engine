<?php
	
	// ================= SUPPORTING FUNCTIONS =================
	/*
	function timestamp2 for covertCSVOldFDS
	@param: time (in strange format)
	note: solve strange format such "Thu27Mar", year assigned as 2014
	*/
	function toTimeStamp2($Time) {
		$year = '2014';
		$date = substr($Time, 3, 2);
		$month = substr($Time, 5, 3); 
		$month = date('m',strtotime($month)); 
		$Date = implode("-", array($year, $month, $date));
		$DateTime = implode(" ", array($Date, substr($Time, 9, 8))); 
		return $DateTime;
	}

	/*
	function timestamp, for convertCSVSuratSanggahan
	@param: time (in strange format)
    note: solve mm/dd/yyyy vs. dd/mm/yyyy problem (still ambiguous for dd < 12)
    assumption: input valid (no ambiguous dd < 12)
    */
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

	/*
	function strpos array, search certain element in array of string
	@param: array, element
	*/
	function strposArray($haystack, $needle, $start=0) {
		if(!is_array($needle)) $needle = array($needle);
		foreach($needle as $nee) {
			if(strpos($haystack, $nee, $start) !== false) return $nee; // stop on first true result
		}
		return false;
	}

	/*
	function arrayToString, convert array to string in format ('element0', 'element1', ... )
	@param: array
	*/
	function arrayToString($array) {
		$array_temp = array();
		for($x=0;$x<sizeof($array);$x++) {
			$array_temp[$x] = "'".$array[$x]."'";
		}
		$string = "(".implode(",", $array_temp).")";
		return $string;
	}

	// ================= DATABASE/TABLE RELATED FUNCTIONS =================
	/*
	function delete null records
	@param: table name
	*/
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
	    mysql_query($query_null);
	}

	/*
    function import from .sql
	@param: path to .sql file
    */
	function importFromSQLFile($Path) {
		$templine = ''; // Temporary variable, used to store current query
		$lines = file($Path); // Read in entire file
		foreach ($lines as $line) {
			if (substr($line, 0, 2) == '--' || $line == '') continue; // Skip it if it's a comment
			$templine .= $line; // Add this line to the current segment
			if (substr(trim($line), -1, 1) == ';') { // If it has a semicolon at the end, it's the end of the query
			    mysql_query($templine) or print('Error performing query \'' . $templine . '\': ' . mysql_error().PHP_EOL); // Perform the query
			    $templine = ''; // Reset temp variable to empty
			}
		}
	}

	/*
	function recreate column
	@param: database name, table name, column name, field type, after which column
	*/
	function recreateColumn($DatabaseName, $TableName, $ColumnName, $FieldType, $AfterWhat) {
		$query = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$DatabaseName' AND TABLE_NAME = '$TableName' AND COLUMN_NAME = '$ColumnName' LIMIT 1";
	    $result = mysql_query($query);
	    $row = mysql_fetch_array($result);
	    if($row['COLUMN_NAME']) {
	    	$query = "ALTER TABLE old_fds DROP COLUMN $ColumnName";
	    	mysql_query($query);
	    }
	    $query = "ALTER TABLE old_fds ADD $ColumnName $FieldType after $AfterWhat";
		mysql_query($query);
	}
	/*
	function recreate table
	@param: database name, table name, fields (in string, deliminate by ',')
	*/
	function recreateTable($DatabaseName, $TableName, $Fields) {
		$query = "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$DatabaseName' AND TABLE_NAME = '$TableName' LIMIT 1";
	    $result = mysql_query($query);
	    $row = mysql_fetch_array($result);
	    if($row['TABLE_NAME']) {
	    	$query = "DROP TABLE $TableName";
	    	mysql_query($query);
	    }
	    $query = "CREATE TABLE $TableName ($Fields)"; 
		mysql_query($query);
	}

	/*
	function clone table
	@param: database name, table name, clone-table name
	*/
	function cloneTable($DatabaseName, $TableName, $CloneName) {
		$query = "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$DatabaseName' AND TABLE_NAME = '$CloneName' LIMIT 1";
	    $result = mysql_query($query);
	    $row = mysql_fetch_array($result);
	    if($row['TABLE_NAME']) {
	    	$query = "DROP TABLE $CloneName";
	    	mysql_query($query);
	    }
		$query = "CREATE TABLE $CloneName LIKE $TableName"; 
		if(!mysql_query($query)) die(mysql_error());
		$query = "INSERT $CloneName SELECT * FROM $TableName";
		if(!mysql_query($query)) die(mysql_error());
	}

	// ================= ENGINE RELATED FUNCTIONS =================
	/*
	function old FDS converter
	@param: path to csv files
	prerequisite: table 'old_fds' in database, fields exist already
	*/
	function convertCSVOldFDS($directory) {
		//erase data
		$query = "TRUNCATE TABLE old_fds";
		mysql_query($query);
		//load file
		$files = array();
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
		while($it->valid()) {
		    if (!$it->isDot()) {
	            $files[] = $it->getSubPathName();; //add file name to array
		    }
		    $it->next();
		}
		//loop
		$j=1; //number
		echo '= Importing transaction ='.PHP_EOL;
		for($i=0;$i<sizeof($files);$i++) {
	        echo $files[$i].PHP_EOL;
	  		$file = fopen($directory.$files[$i], "r");
	        $emapData = fgetcsv($file, 10000, ",");
			while (($emapData = fgetcsv($file, 10000, ",")) != false) {
	    		//solve ReDi date problem
		    	$Date_Time = $emapData[1];
		    	$Date_Time = toTimeStamp2($Date_Time);
		    	$Customer_Name = mysql_real_escape_string($emapData[4]); //biar bisa baca (')
		    	$Cust_ = explode(',', $Customer_Name);
		    	$CustomerLastName = mysql_real_escape_string($Cust_[0]);
		    	$Billing_Address = mysql_real_escape_string($emapData[18]); //biar bisa baca (')
		    	$IPID_State = mysql_real_escape_string($emapData[24]); //biar bisa baca (')
		    	$CurrentStatus = $emapData[33];
		    	if($CurrentStatus == 'NoScore') {
		    		$CurrentStatus = 'Deny';
		    	}
		    	$query = "INSERT INTO old_fds (No, TxnTime, TxnId, CustomerName, CustomerEmailAddress, TxnValue, CardType, BillingAddress, BillingCountry, ShipZip, ShippingCountry, CustIPID, IPIDState, IPIDCountry, BINCountry, CurrentStatus, SubClient, CustomerLastName, ChargebackStatus, CompareStatus) VALUES ('$j', '$Date_Time', '$emapData[2]', '$Customer_Name', '$emapData[5]', '$emapData[6]', '$emapData[13]', '$Billing_Address', '$emapData[19]', '$emapData[20]', '$emapData[22]', '$emapData[23]', '$IPID_State', '$emapData[25]', '$emapData[26]', '$CurrentStatus', '$emapData[34]','$CustomerLastName', '$CurrentStatus', '-')";
		    	if (!mysql_query($query)) {
		    		echo 'Problem with '.$files[$i].' ';
	                die(mysql_error()).PHP_EOL;
	            }
	      		$j++;
	    	}
		}
	    //DELETE NULL VALUE
		deleteNullRecords('old_fds');
	    echo 'Done'.PHP_EOL;
	}

    /*
    function surat sanggahan converter
    @param: path to csv files
	prerequisite: table 'suratsanggahan' in database, fields exist already
    */
    function convertCSVSuratSanggahan($directory) {
    	//erase data
    	$query = "TRUNCATE TABLE suratsanggahan";
	    mysql_query($query);
	    $files = array();
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
		while($it->valid()) {
		    if (!$it->isDot()) {
	            $files[] = $it->getSubPathName(); //add file name to array
		    }
		    $it->next();
		}
		//LOOP EACH FILE
	    echo '= Importing chargeback data ='.PHP_EOL;
	    for($i=0;$i<sizeof($files);$i++) {
	        $file = fopen($directory.$files[$i], "r");
	        echo $files[$i].PHP_EOL;
	        $Merchant = substr($files[$i],0,-4);

	        $emapData = fgetcsv($file, 10000, ",");
	        while (($emapData = fgetcsv($file, 10000, ",")) != false) {
	            $TradeTime = $emapData[1];
	            $TradeTime = toTimeStamp($TradeTime);
	            $SuratSanggahan = $emapData[9];
	            if ((strpos($SuratSanggahan, 'konfirm fraud')== FALSE) && (strpos($SuratSanggahan, 'email fraud')== FALSE) && (strpos($SuratSanggahan, 'confirm fraud')== FALSE)) {
	                $Flag = "Accept";
	            } else {
	                $Flag = "Deny";
	            }
	            $query = "INSERT INTO suratsanggahan (PaymentType, TradeTime, OrderID, CCNumber, CustomerEmail, Amount, SecurityRecom, LatestStatus, Result, SuratSanggahan, Flag, Tanggal, Refund, Notes, Merchant) VALUES ('$emapData[0]', '$TradeTime', '$emapData[2]', '$emapData[3]', '$emapData[4]', '$emapData[5]', '$emapData[6]', '$emapData[7]', '$emapData[8]', '$SuratSanggahan', '$Flag', '$emapData[10]', '$emapData[11]', '$emapData[12]','$Merchant')";
	            if (!mysql_query($query)) {
	                echo 'Problem with'.$files[$i].' ';
	                die(mysql_error()).PHP_EOL;
	            }
	        } 
	    }
	    //DELETE NULL VALUE
	    deleteNullRecords('suratsanggahan'); //kolom2 kosong dari csv masuk entah kenapa
	    //UPDATE TRANSACTION DATA
	    echo '= Updating transaction data ='.PHP_EOL;
	    $acceptlist = "";
	    $denylist = "";
	    $query_surat = "SELECT CustomerEmail, Flag FROM suratsanggahan GROUP BY CustomerEmail";
	    $result_surat = mysql_query($query_surat);
	    while($row_surat = mysql_fetch_assoc($result_surat)) {
	        $CustomerEmail = $row_surat['CustomerEmail'];
	        $Flag = $row_surat['Flag'];
	        if($Flag == 'Accept') $acceptlist[] = $CustomerEmail;
	        else $denylist[] = $CustomerEmail;
	    }
	    $query_surat = "UPDATE old_fds SET ChargebackStatus = 'Deny' WHERE CustomerEmailAddress IN  ".arrayToString($denylist);
	    if(!mysql_query($query_surat)) die(mysql_error());
	    $query_surat = "UPDATE old_fds SET ChargebackStatus = 'Accept' WHERE CustomerEmailAddress IN ".arrayToString($acceptlist);
	    if(!mysql_query($query_surat)) die(mysql_error());
	    echo 'Done'.PHP_EOL;
    }

	/*
	function rules importer
	@param: path to rules.sql, table rulesconv, datarules
	*/
	function convertSQLRules($Path) {
		$file = fopen("rules.txt","w");
		//import
		echo "= Importing rules =".PHP_EOL;
		importFromSQLFile($Path);
		//Clean up a little
		$query = "ALTER TABLE datarules DROP empty"; if (!mysql_query($query)) die(mysql_error());
		$query = "UPDATE datarules SET Description = REPLACE(Description, '\"', '') WHERE Description LIKE '\"%'"; if (!mysql_query($query)) die(mysql_error()); //remove (")
		$query = "UPDATE datarules SET Description = REPLACE(Description, 'when', 'where') WHERE Description LIKE '%when%'"; if (!mysql_query($query)) die(mysql_error()); //change 'when' to 'where' 
		$query = "DELETE FROM datarules WHERE Description = ''"; if(!mysql_query($query)) die(mysql_error());
		// TRANSFORM RULES INTO MYSQL QUERY
		echo "= Transforming rules =".PHP_EOL;
		recreateTable(txn_data, rulesconv, "No INT(255), RuleId VARCHAR(255), DateAdded VARCHAR(255), StatusResult VARCHAR(255), Expression VARCHAR(255), Glue VARCHAR(255)");
		//Convert variables
		$statuses = array( "deny" => "Deny", "challenge" => "Challenge", "allow" => "Accept");
		$signs = array( " like " => "LIKE", " = " => "=", " is not equal to " => "<>");
		$signs_for_arr = array( " is not like " => "NOT IN");
		$glues = array(" AND ", " OR ");
		$vars = array(
			"CUSTEMAIL" => "CustomerEmailAddress",
			"E-mail Domain" => "CustomerEmailAddress",
			"CUSTIP" => "CustIPID",
			"SHIPZIPCD" => "ShipZip",
			"SHIPCOUNTRY" => "ShippingCountry",
			"BILLCOUNTRY" => "BillingCountry",
			"Geolocation Country(IPID)" => "IPIDCountry",
			"Card Issuing Country(VIRTBIN)" => "BINCountry"
		);
		//Retrieve rules
		$j=1;
	    $ignored_description = array('%more than%', '%CARDNO%', '%Observe Only%', '%PRODCD%', '%Phone%', '%VIRTIPIDANONYMIZER%', '%USERDATA14%', '%AFRICA%', '%BILLZIPCD%', '%Geolocation Continent(IPID)%', '%AUTHRESP%', '%CUSTLASTNAME%', '%CUSTID%');
		$query = "SELECT RuleId, DateAdded, Description FROM datarules WHERE ";
		$n_ignored = sizeof($ignored_description); 
		for($i=0;$i<$n_ignored;$i++) {
			$query = $query."Description NOT LIKE '".$ignored_description[$i]."'";
			if($i<$n_ignored-1) {
				$query = $query." AND ";
			}
		}
		$result = mysql_query($query);
	    while($row = mysql_fetch_assoc($result)) {
	    	$StatusResult = "";
			$Glue = "";
			$Expression_final = "";
			$Expressions = array();
			$rule = explode(" if it equals ", str_replace(" then abort rule processing","",$row['Description']));

			if($rule[1]) {
	    		// Special case: pattern "Manually created by ReD - STATUS VAR if it equals VAL"
	    		$Glue = ""; 
	    		$var = "";
				$val = "";
	    		$status_var = explode(' ', $rule[0]); 
	    		$var = array_pop($status_var); //get variable
				$StatusResult = $statuses[strtolower(array_pop($status_var))]; //get status
				$val = $rule[1]; //get value
				$Expressions = array($vars[$var]." = '".$val."'");

	    	} else {
	    		// Case biasa, pakai where
	    		$Glue = "";
	    		$status_expressions = explode(" where ", $rule[0]);
	    		$status_ = explode(' ', $status_expressions[0]);
	    		$StatusResult = $statuses[strtolower(array_pop($status_))]; //get status
	    		//breakdown according to conjunctions used in rule
	    		$expressions = array();
	    		$n_exp = 0;
	    		foreach($glues as $glue) {
	    			if(strpos($status_expressions[1], $glue) !==FALSE) {
	    				$expressions = explode($glue, $status_expressions[1]);	
	    				$Glue = trim($glue);
	    				$n_exp = sizeof($expressions); 
	    				break;
	    			}
	    		}
	    		if($n_exp == 0) {
	    			$expressions = array($status_expressions[1]);
	    			$n_exp = 1;
	    		}
	    		//iterate all expressions
				for($i=0;$i<$n_exp;$i++) {
					$Expression = "";
					$var = "";
					$val = "";
					if(strripos($expressions[$i], "more than") !== FALSE) {
						$expressions_ = substr($expressions[$i],10);

						//Special case: pattern "more than"
						if(strripos($expressions_, "unique") !== FALSE) {
							$var1 = "";
							$var2 = "";
							$var2_var1_range = preg_split("/ (per|in) /", $expressions_);
							$var2 = $var2_var1_range[0]; 
							$var2_ = explode(" unique ", $var2);
							$val = $var2_[0];
							$var2 = $var2_[1];
							$var1 = $var2_var1_range[1];
							$range = $var2_var1_range[2];
							$Expression = $vars[$var1]." MORE THAN ".$val." UNIQUE ".$vars[$var2]." IN ".$range;
						} else if(strripos($expressions_, "transaction") !== FALSE) {
							$var_range = array_filter(preg_split("/ (per|in) /", $expressions_));
							$val = $var_range[0];
							$var = $var_range[1];
							$range = $var_range[2];
							$Expression = $vars[$var]." MORE THAN ".$val." IN ".$range;
						} else if(strripos($expressions_, "in value") !== FALSE) {
							$var_range = array_filter(preg_split("/ (in value per|in) /", $expressions_));
							$val = $var_range[0];
							$var = $var_range[1];
							$range = $var_range[2];
							$Expression = $vars[$var]." MORE THAN ".$val." IN ".$range;
						}

					} else if(strripos($expressions[$i], " is not like ") !== FALSE) {

						//Special case: pattern "is not like"
						$var_val = explode(" is not like ", $expressions[$i]);
	    				$var = $var_val[0];
	    				$val = $var_val[1];
	    				//into proper array format
	    				$val = str_replace(" or", ",", $val);
	    				$vals = explode(", ", $val);
	    				foreach ($vals as $key=>$val) {
	    					$val_ = substr($val, 4, -1);
	    					$vals[$key] = "'".$val_."'";
	    				}
	    				$val = implode(", ", $vals);
	    				$Expression = $vars[$var]." ".$signs_for_arr[" is not like "]." (".$val.")";

					} else {
						// Case biasa: pattern "VAR SIGN VAL"
						foreach($signs as $key=>$sign) {
			    			if(strripos($expressions[$i], $key) !== FALSE) {
			    				$var_val = explode($key, $expressions[$i]);
			    				$var = $var_val[0];
			    				$val = $var_val[1];
			    				if(($vars[$var]=="BillingCountry")||($vars[$var]=="ShippingCountry")||($vars[$var]=="IPIDCountry")||($vars[$var]=="BINCountry")) {
			    					$val_ = explode("(", $val);
			    					$val_ = explode(")", $val_[1]);
			    					$val = $val_[0];
			    				}
			    				if($sign = "LIKE") {
			    					$val = '%'.$val.'%';
			    				}
			    				$Expression = $vars[$var]." ".$sign." '$val'";
			    				break;
			    			}
			    		}
					}
					array_push($Expressions, $Expression);
				}
	    	}
	    	$Expression_final = implode(" ; ", $Expressions); //into one Expression!
	    	fwrite($file, $row['RuleId']." ".$row['Description'].PHP_EOL.$row['RuleId']." ".$StatusResult." ".$Expression_final." ".$Glue.PHP_EOL.PHP_EOL);
	    	$query_insert = "INSERT INTO rulesconv (No, RuleId, DateAdded, StatusResult, Expression, Glue) VALUES ('".$j."', '".$row['RuleId']."', '".$row['DateAdded']."', '".$StatusResult."', '".mysql_real_escape_string($Expression_final)."', '".$Glue."')"; 
	    	if (!mysql_query($query_insert)) die(mysql_error());
	    	$j++;
	    }
	    fclose($file);
	    echo "Done".PHP_EOL;
	}
	
	/*
	function exportToCSV
	prerequisite: table old_fds
	*/
	function exportToCSV() {
		$fp = fopen('old-fds.csv', 'w');
		$fields = array('TxnTime', 'CustomerName', 'CustomerEmailAddress', 'TxnValue', 'CardType', 'BillingAddress', 'BillingCountry', 'ShipZip', 'ShippingCountry', 'CustIPID', 'IPIDState', 'IPIDCountry', 'BINCountry', 'CurrentStatus', 'ChargebackStatus','SubClient');
		fputcsv($fp, $fields);
		$query = "SELECT * FROM old_fds";
		$result = mysql_query($query);
		while($row = mysql_fetch_assoc($result)) {
			$fields = array(date("M_D", strtotime($row['TxnTime'])), $row['CustomerName'], $row['CustomerEmailAddress'], $row['TxnValue'], $row['CardType'], $row['BillingAddress'], $row['BillingCountry'], $row['ShipZip'], $row['ShippingCountry'], $row['CustIPID'], $row['IPIDState'], $row['IPIDCountry'], $row['BINCountry'], $row['CurrentStatus'], $row['ChargebackStatus'], $row['SubClient']);
			fputcsv($fp, $fields);
		}
		fclose($fp);
		echo 'Done'.PHP_EOL;
	}

	/*
	function analyzeTransaction
	prerequisite: table old_fds, rulesconv
	*/
	function engine() {
		//create temp old-fds table
		cloneTable('txn_data', 'old_fds', 'temp_fds');
		
		$file = fopen("engine.txt","w");
		$ignore_if_dash = array("ShippingCountry", "BillingCountry", "IPIDCountry", "BINCountry");
		//$analyzed_txn = array(''); //filled with TxnId
		
		$query_rule = "SELECT RuleId, StatusResult, Expression, Glue FROM rulesconv ORDER BY StatusResult DESC";
		$result_rule = mysql_query($query_rule);
		$n_rule = mysql_num_rows($result_rule);
		$k = 0;
		while($row_rule = mysql_fetch_assoc($result_rule)) {
			$k++;
			$query = "SELECT No FROM temp_fds";
			$result = mysql_query($query);
			$n_txn = mysql_num_rows($result);	

			echo $k."/".$n_rule." Rule ".$row_rule['RuleId'].PHP_EOL;
			fwrite($file, "Rule ".$row_rule['RuleId']);
			//explode expression (some rules are multi-expression)
			$Expressions = explode(" ; ", $row_rule['Expression']);
			$hit = array();
			for($i=0; $i<sizeof($Expressions);$i++) {
				$expression = $Expressions[$i];
				$is_ignored = strposArray($expression,$ignore_if_dash); 
				if($is_ignored !== FALSE) {
					$query_txn = "SELECT No, $is_ignored FROM temp_fds WHERE $expression HAVING $is_ignored <> '-'";
				} else {
					$query_txn = "SELECT No FROM temp_fds WHERE $expression";
				}
				if($result_txn = mysql_query($query_txn)) {
					$hit[] = 1;
				} else {
					$hit[] = 0;
				}
			}
			$Glue = $row_rule['Glue'];
			$analyzed_txn = array();
			if($Glue=='') {
				while($row_txn = mysql_fetch_assoc($result_txn)) {
					$analyzed_txn[] = $row_txn['No'];
				}
				//to include analyzed txn
				if(sizeof($analyzed_txn)>0){
					$analyzed = "WHERE No IN ".arrayToString($analyzed_txn);
				}
				$query_update = "UPDATE old_fds SET CompareStatus = '".$row_rule['StatusResult']."' $analyzed";
				if(!mysql_query($query_update)) die(mysql_error());
				$query_update = "DELETE FROM temp_fds $analyzed";
				if(!mysql_query($query_update)) die(mysql_error());
			} else {
				$HIT = $hit[0];
				for($k=1;$k<sizeof($hit);$k++) {
					if($Glue=='AND') {
						$HIT = $HIT & $hit[$k];
					} else if($Glue=='OR') {
						$HIT = $HIT | $hit[$k];
					}
				}
				if($HIT) {
					while($row_txn = mysql_fetch_assoc($result_txn)) {
						$analyzed_txn[] = $row_txn['No'];
					}
					//to include analyzed txn
					if(sizeof($analyzed_txn)>0){
						$analyzed = "WHERE No IN ".arrayToString($analyzed_txn);
					}
					$query_update = "UPDATE old_fds SET CompareStatus = '".$row_rule['StatusResult']."' $analyzed";
					if(!mysql_query($query_update)) die(mysql_error());
					$query_update = "DELETE FROM temp_fds $analyzed";
					if(!mysql_query($query_update)) die(mysql_error());
				}	
			}
			//fwrite($file, " : transaction hit ".arrayToString($analyzed_txn).PHP_EOL);
			fwrite($file, " : transaction hit ".sizeof($analyzed_txn)."/".$n_txn.PHP_EOL);	
				
		}
		//finally~
		//$query_update = "UPDATE old_fds SET CompareStatus = 'Accept' WHERE $new_analyzed";
		$query_update = "UPDATE old_fds SET CompareStatus = 'Accept' WHERE CompareStatus = '-'";
		if(!mysql_query($query_update)) die(mysql_error());		
		$query = "DROP TABLE temp_fds";
		mysql_query($query);
		echo 'Done'.PHP_EOL;
	}

	// ================= MAIN =================
	/*
	biasanya aku pisah jadi "database_connection.php"
	*/
	$hostname = "localhost";
	$user = "root"; // MySQL username
	$password = ""; // MySQL password
	$database = "txn_data"; // Database name
	$db = mysql_connect($hostname, $user, $password) or die('Error connecting to MySQL server: '.mysql_error()); // Connect to MySQL server
	mysql_select_db($database, $db) or die('Error selecting MySQL database: '.mysql_error()); // Select database
	
	/*
	engine
	*/
    echo "[1 of 4] Reload old FDS transaction? (y/n) ";
	$stdin = fopen('php://stdin', 'r');
	$response = trim(fgetc($stdin));
	if($response == 'y'){
		convertCSVOldFDS('C:/xampplite/htdocs/veritrans/fds-data/old-fds/');
	}
	echo "[2 of 4] Reload surat sanggahan? (y/n) ";
	$stdin = fopen('php://stdin', 'r');
	$response = trim(fgetc($stdin));
	if($response == 'y'){
		convertCSVSuratSanggahan('C:/xampplite/htdocs/veritrans/fds-data/suratsanggahan/');
	}
	echo "[3 of 4] Regenerate csv? (y/n) ";
	$stdin = fopen('php://stdin', 'r');
	$response = trim(fgetc($stdin));
	if($response == 'y'){
		echo '= Creating finalized dataset to CSV =',PHP_EOL;
		exportToCSV();
	}
	echo "[3 of 4] Reload rules? (y/n) ";
	$stdin = fopen('php://stdin', 'r');
	$response = trim(fgetc($stdin));
	if($response == 'y'){
		convertSQLRules('C:\xampplite\htdocs\veritrans\fds-data\datarules.sql');
	}

	echo '= Engine processing transaction ='.PHP_EOL;
	$starttime = time();
	engine();
	$finishtime = time(); 
	$exectime = $finishtime-$starttime;
	echo $exectime." s";


	/*
	echo "= Comparing engine result =".PHP_EOL;
	
	$query = "SELECT TxnId, CurrentStatus, ChargebackStatus, CompareStatus FROM old_fds WHERE CurrentStatus <> 'NoScore' LIMIT 1";
	$result = mysql_query($query);
	$n_txn = mysql_num_rows($result);
	echo "[-] Surat Sanggahan [-] No Score :";
	$match=0;
	while($row = mysql_fetch_assoc($result)) {
		if($row['CurrentStatus']==$row['CompareStatus']) $match++;
	}
	$accuracy = $match/$n_txn*100;
	echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;
	
	mysql_data_seek($result, 0);
	echo "[+] Surat Sanggahan [-] No Score :";
	$match=0;
	while($row = mysql_fetch_assoc($result)) {
		if($row['ChargebackStatus']==$row['CompareStatus']) $match++;
	}
	$accuracy = $match/$n_txn*100;
	echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;

	$query = "SELECT TxnId, CurrentStatus, ChargebackStatus, CompareStatus FROM old_fds LIMIT 1";
	$result = mysql_query($query);
	$n_txn = mysql_num_rows($result);
	$match=0;
	echo "[-] Surat Sanggahan [+] No Score :";
	while($row = mysql_fetch_assoc($result)) {
		if($row['CurrentStatus']==$row['CompareStatus']) $match++;
	}
	$accuracy = $match/$n_txn*100;
	echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;

	mysql_data_seek($result, 0);
	echo "[+] Surat Sanggahan [+] No Score :";
	$match=0;
	while($row = mysql_fetch_assoc($result)) {
		if($row['ChargebackStatus']==$row['CompareStatus']) $match++;
	}
	$accuracy = $match/$n_txn*100;
	echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;*/
?>