<?php

	include "database_connection.php";
	$file = fopen("rules.txt","w");

	// IMPORT RULES FROM SQL
	echo "Importing rules".PHP_EOL;
	importFromSQLFile('C:\xampplite\htdocs\veritrans\etl\datarules.sql');

	//Clean up a little
	$query = "ALTER TABLE datarules DROP empty"; if (!mysql_query($query)) die(mysql_error());
	$query = "UPDATE datarules SET Description = REPLACE(Description, '\"', '') WHERE Description LIKE '\"%'"; if (!mysql_query($query)) die(mysql_error()); //remove (")
	$query = "UPDATE datarules SET Description = REPLACE(Description, 'when', 'where') WHERE Description LIKE '%when%'"; if (!mysql_query($query)) die(mysql_error()); //change 'when' to 'where' 
	
	
	// TRANSFORM RULES INTO MYSQL QUERY

	echo "Transforming rules".PHP_EOL;
	recreateTable(txn_data, rulesconv, "RuleId VARCHAR(255), DateAdded VARCHAR(255), StatusResult VARCHAR(255), Expression VARCHAR(255), Glue VARCHAR(255)");
	//Kamus
	$statuses = array( "Challenge" => "Challenge", "CHALLENGE" => "Challenge", "Deny" => "Deny", "DENY" => "Deny", "Allow" => "Accept", "ALLOW" => "Accept", "allow" => "Accept");
	$signs = array( " like " => "LIKE", " = " => "=", " is not equal to " => "<>");
	$signs_for_arr = array( " is not like " => "NOT IN"/*, " not in " => "NOT IN"*/);
	$conjs = array(" AND ", " OR ");
	$vars = array(
		"CUSTID" => "CustomerID",
		"CUSTLASTNAME" => "CustomerLastName", 
		"CUSTEMAIL" => "CustomerEmailAddress",
		"E-mail Domain" => "CustomerEmailAddress",
		"CUSTIP" => "CustIPID",
		"SHIPZIPCD" => "ShipZip",
		"SHIPCOUNTRY" => "ShippingCountry",
		"BILLCOUNTRY" => "BillingCountry",
		"Geolocation Country(IPID)" => "IPIDCountry",
		//"Geolocation Continent(IPID)" => "BillingCountry",
		"Card Issuing Country(VIRTBIN)" => "BINCountry"
	);

	//Retrieve rules
	$ignored_description = array('%CARDNO%', '%Observe Only%', '%PRODCD%', '%Phone%', '%VIRTIPIDANONYMIZER%', '%USERDATA14%', '%AFRICA%', '%BILLZIPCD%', '%Geolocation Continent(IPID)%');
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
		$Expressions = array();
		$rule = explode(" if it equals ", str_replace(" then abort rule processing","",$row['Description']));

		if($rule[1]) {

    		// Special case: pattern "Manually created by ReD - STATUS VAR if it equals VAL"
    		$var = "";
			$val = "";
    		$status_var = explode(' ', $rule[0]); 
    		$var = array_pop($status_var); //get variable
			$StatusResult = $statuses[array_pop($status_var)]; //get status
			$val = $rule[1]; //get value
			$Expressions = array($vars[$var]." = '".$val."'");

    	} else {

    		// Case biasa, pakai where
    		$status_expressions = explode(" where ", $rule[0]);
    		$status_ = explode(' ', $status_expressions[0]);
    		$StatusResult = $statuses[array_pop($status_)]; //get status
    		
    		//breakdown according to conjunctions used in rule
    		$expressions = array();
    		$n_exp = 0;
    		foreach($conjs as $conj) {
    			if(strripos($status_expressions[1], $conj) !==FALSE) {
    				$expressions = explode($conj, $status_expressions[1]);	
    				$Glue = $conj;
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
		    				$Expression = $vars[$var]." ".$sign." '$val'";
		    				break;
		    			}
		    		}
				}
				array_push($Expressions, $Expression);
			}
    	}
    	$Expression_final = implode(" ; ", $Expressions); //into one Expression!
    	
    	$Expression_final = mysql_real_escape_string($Expression_final);
    	fwrite($file, $row['RuleId']." ".$row['Description'].PHP_EOL.$row['RuleId']." ".$StatusResult." ".$Expression_final.PHP_EOL.PHP_EOL);
    	$query_insert = "INSERT INTO rulesconv (RuleId, DateAdded, StatusResult, Expression, Glue) VALUES ('".$row['RuleId']."', '".$row['DateAdded']."', '".$StatusResult."', '".$Expression_final."', '".$Glue."')"; 
    	if (!mysql_query($query_insert)) die(mysql_error());
    }
    fclose($file);
    echo "Done".PHP_EOL;

    // ----- function import from .sql
	function importFromSQLFile($Path) {
		$filename = $Path; // Name of the file
		$templine = ''; // Temporary variable, used to store current query
		$lines = file($filename); // Read in entire file
		// Loop through each line
		foreach ($lines as $line) {
			if (substr($line, 0, 2) == '--' || $line == '') continue; // Skip it if it's a comment
			$templine .= $line; // Add this line to the current segment
			if (substr(trim($line), -1, 1) == ';') { // If it has a semicolon at the end, it's the end of the query
			    mysql_query($templine) or print('Error performing query \'' . $templine . '\': ' . mysql_error().PHP_EOL); // Perform the query
			    $templine = ''; // Reset temp variable to empty
			}
		}
	}
	// ----- function import from .sql

	// ----- function recreate column
	function recreateColumn($DatabaseName, $TableName, $ColumnName, $FieldType, $AfterWhat) {
		$query = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$DatabaseName' AND TABLE_NAME = '$TableName' AND COLUMN_NAME = '$ColumnName'";
	    $result = mysql_query($query);
	    $row = mysql_fetch_array($result);
	    if($row['COLUMN_NAME']) {
	    	$query = "ALTER TABLE old_fds DROP COLUMN $ColumnName";
	    	if (!mysql_query($query)) die(mysql_error());
	    }
	    $query = "ALTER TABLE old_fds ADD $ColumnName $FieldType after $AfterWhat";
		if (!mysql_query($query)) die(mysql_error());
	}
	// ----- function recreate column

	// ----- function recreate table
	function recreateTable($DatabaseName, $TableName, $Fields) {
		$query = "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$DatabaseName' AND TABLE_NAME = '$TableName'";
	    $result = mysql_query($query);
	    $row = mysql_fetch_array($result);
	    if($row['TABLE_NAME']) {
	    	$query = "DROP TABLE $TableName";
	    	if (!mysql_query($query)) die(mysql_error());
	    }
	    $query = "CREATE TABLE $TableName ($Fields)"; 
		if (!mysql_query($query)) die(mysql_error());
	}
	// ----- function recreate table
?>