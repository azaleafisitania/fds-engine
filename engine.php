<?php

	include "database_connection.php";
    $file = fopen("engine.txt","w");

	// LOAD TXN DATA FROM, CHARGEBACK DATA, AND RULES
	//echo "LOADING TXN DATA FROM, CHARGEBACK DATA, AND RULES".PHP_EOL;
	//include "old-fds-converter.php";
	//include "suratsanggahan-converter.php";
	//include "rules-importer.php";

    // ENGINE
    echo 'Processing transaction'.PHP_EOL;
    $ignoreable = array("ShippingCountry", "BillingCountry", "IPIDCountry", "BINCountry");
    $n_ignore = sizeof($ignoreable);        
    $query_txn = "SELECT TxnId, CustomerEmailAddress, BillingCountry, ShippingCountry, CustIPID, IPIDCountry, BINCountry, CurrentStatus FROM old_fds";
    $result_txn = mysql_query($query_txn);
    while($row_txn = mysql_fetch_array($result_txn)) {
        $TxnId = $row_txn['TxnId'];
        echo "Transaction ".$TxnId." : ";
        //fwrite($file, PHP_EOL."Transaction ".$row_txn['TxnId']." | ".$row_txn['CustomerEmailAddress']." | ".$row_txn['BillingCountry']." | ".$row_txn['ShippingCountry']." | ".$row_txn['CustIPID']." | ".$row_txn['IPIDCountry']." | ".$row_txn['BINCountry'].PHP_EOL);
        $hit = FALSE;
        $query_rule = "SELECT RuleId, StatusResult, Expression FROM rulesconv GROUP BY Expression";
        $result_rule = mysql_query($query_rule);
        while(($row_rule = mysql_fetch_array($result_rule))&&(!$hit)) {
            $RuleId = $row_rule['RuleId'];
            $StatusResult = $row_rule['StatusResult'];
            $Expression = $row_rule['Expression'];
            //ignore records with '-' values
            $having = "";
            for($i=0;$i<$n_ignore;$i++) {
                if(stripos($Expression, $ignoreable[$i]) !== FALSE) {
                    $having = "HAVING ".$ignoreable[$i]." <> '-'";
                    break;
                }
            }
            $query_check = "SELECT * FROM old_fds WHERE TxnId = $TxnId AND $Expression $having";
            fwrite($file, $query_check.PHP_EOL);
            if(mysql_query($query_check)) {
                echo "Hit rule $RuleId - ";
                //fwrite($file, "Hit rule $RuleId | $StatusResult | $Expression".PHP_EOL);
                $query_update = "UPDATE old_fds SET CompareStatus = '$StatusResult' WHERE TxnId = '$TxnId' LIMIT 1";
                if(!mysql_query($query_update)) die(mysql_error());
                $hit = TRUE;
            } else {
                //echo "Rule $RuleId: Passed".PHP_EOL;
                //fwrite($file, "Rule $RuleId: Passed".PHP_EOL);
                $hit = FALSE;
            }
        }
        if(!$hit) {
            $query_update = "UPDATE old_fds SET CompareStatus = 'Accept' WHERE TxnId = '$TxnId' LIMIT 1";
            if(!mysql_query($query_update)) die(mysql_error());
        }
        $query_check2 = "SELECT CompareStatus FROM old_fds WHERE TxnId = '$TxnId' LIMIT 1";
        $result_check2 = mysql_query($query_check2); 
        $row_check2 = mysql_fetch_array($result_check2);
        echo $row_check2[ 'CompareStatus'].PHP_EOL;
        fwrite($file, $row_check2['CompareStatus'].PHP_EOL);
    }
    
    echo "Comparing engine result".PHP_EOL;
    echo "[-] Surat Sanggahan [-] No Score :";
    $query = "SELECT TxnId, CurrentStatus, CompareStatus FROM old_fds WHERE CurrentStatus <> 'NoScore'";
    $result = mysql_query($query);
    $n_txn = mysql_num_rows($result);
    $match=0;
    while($row = mysql_fetch_assoc($result)) {
        if($row['CurrentStatus']==$row['CompareStatus']) $match++;
    }
    $accuracy = $match/$n_txn*100;
    echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;

    $acceptlist = "";
    $denylist = "";
    $query_surat = "SELECT CustomerEmail, Flag FROM suratsanggahan GROUP BY CustomerEmail";
    $result_surat = mysql_query($query_surat);
    while($row_surat = mysql_fetch_assoc($result_surat)) {
        $CustomerEmail = $row_surat['CustomerEmail'];
        $Flag = $row_surat['Flag'];
        if($Flag == 'Accept') $acceptlist[] = "'".$CustomerEmail."'";
        else $denylist[] = "'".$CustomerEmail."'";
    }
    $query_surat = "UPDATE old_fds SET ChargebackStatus = 'Accept' WHERE CustomerEmailAddress IN (".implode(',', $acceptlist).")";
    if(!mysql_query($query_surat)) die(mysql_error());
    $query_surat = "UPDATE old_fds SET ChargebackStatus = 'Deny' WHERE CustomerEmailAddress IN (".implode(',', $denylist).")";
    if(!mysql_query($query_surat)) die(mysql_error());
    
    echo "[+] Surat Sanggahan [-] No Score :";
    $query = "SELECT TxnId, ChargebackStatus, CompareStatus FROM old_fds WHERE ChargebackStatus <> 'NoScore'";
    $result = mysql_query($query);
    $n_txn = mysql_num_rows($result);
    $match=0;
    while($row = mysql_fetch_assoc($result)) {
        if($row['ChargebackStatus']==$row['CompareStatus']) $match++;
    }
    $accuracy = $match/$n_txn*100;
    echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;

    echo "[-] Surat Sanggahan [+] No Score :";
    $query = "SELECT TxnId, CurrentStatus, CompareStatus FROM old_fds";
    $result = mysql_query($query);
    $n_txn = mysql_num_rows($result);
    $match=0;
    while($row = mysql_fetch_assoc($result)) {
        if($row['CurrentStatus']==$row['CompareStatus']) $match++;
    }
    $accuracy = $match/$n_txn*100;
    echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;

    echo "[+] Surat Sanggahan [+] No Score :";
    $query = "SELECT TxnId, ChargebackStatus, CompareStatus FROM old_fds";
    $result = mysql_query($query);
    $n_txn = mysql_num_rows($result);
    $match=0;
    while($row = mysql_fetch_assoc($result)) {
        if($row['ChargebackStatus']==$row['CompareStatus']) $match++;
    }
    $accuracy = $match/$n_txn*100;
    echo $match."/".$n_txn."=".$accuracy."%".PHP_EOL;
    echo "Done".PHP_EOL;
?>
