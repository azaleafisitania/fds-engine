<?php
	include "database_connection.php";
    $file = fopen("engine.txt","w");

	//echo "LOADING TXN DATA...".PHP_EOL;
	//include "old-fds-converter.php";
	//include "suratsanggahan-converter.php";
	//include "rules-importer.php";

    //ENGINE
    echo 'Processing transaction...'.PHP_EOL;
    $ignoreable = array("ShippingCountry", "BillingCountry", "IPIDCountry", "BINCountry");
    $n_ignore = sizeof($ignoreable);        
    $query_txn = "SELECT TxnId, CustomerEmailAddress, BillingCountry, ShippingCountry, CustIPID, IPIDCountry, BINCountry, CurrentStatus FROM old_fds LIMIT 1";
    $result_txn = mysql_query($query_txn);
    while($row_txn = mysql_fetch_array($result_txn)) {
        $TxnId = $row_txn['TxnId'];
        echo "Txn ".$TxnId." : ";
        fwrite($file, PHP_EOL."Txn ".$row_txn['TxnId']." | ".$row_txn['CustomerEmailAddress']." | ".$row_txn['BillingCountry']." | ".$row_txn['ShippingCountry']." | ".$row_txn['CustIPID']." | ".$row_txn['IPIDCountry']." | ".$row_txn['BINCountry'].PHP_EOL);
        $HIT = FALSE;
        $query_rule = "SELECT RuleId, StatusResult, Expression, Glue FROM rulesconv ORDER BY StatusResult DESC";
        $result_rule = mysql_query($query_rule);
        while(($row_rule = mysql_fetch_assoc($result_rule))&&(!$HIT)) {
            $hit = NULL;
            $RuleId = $row_rule['RuleId'];
            $StatusResult = $row_rule['StatusResult'];
            $Glue = $row_rule['Glue'];
            $Expression_final = $row_rule['Expression']; 
            $Expressions = explode(" ; ", $Expression_final);
            fwrite($file,"Rule $RuleId | $StatusResult | ");
            for($i=0; $i<sizeof($Expressions);$i++) {
                fwrite($file, $Expression);
                //ignore records with '-' values
                $expression = $Expressions[$i];
                $having = "";
                for($i=0;$i<$n_ignore;$i++) {
                    if(stripos($expression, $ignoreable[$i]) !== FALSE) {
                        $having = "HAVING ".$ignoreable[$i]." <> '-'";
                        break;
                    }
                }
                $query_check = "SELECT * FROM old_fds WHERE TxnId = $TxnId AND $expression $having LIMIT 1";
                    $rows_check = mysql_num_rows(mysql_query($query_check));
                    if($rows_check==0) {
                        fwrite($file, '0');
                    } else {
                        fwrite($file, '1');
                    //if($Glue == "AND") $hit = $hit & $rows_check;
                    //else if($Glue == "OR") $hit = $hit | $rows_check;
                }
            }
            if($hit) {
                $query_update = "UPDATE old_fds SET CompareStatus = '$StatusResult' WHERE TxnId = '$TxnId' LIMIT 1";
                if(!mysql_query($query_update)) die(mysql_error());
                $HIT = TRUE;
            }
            fwrite($file, PHP_EOL);
        }
        if(!$HIT) {
            $query_update = "UPDATE old_fds SET CompareStatus = 'Accept' WHERE TxnId = '$TxnId' LIMIT 1";
            if(!mysql_query($query_update)) die(mysql_error());
        }
        $query_check2 = "SELECT CompareStatus FROM old_fds WHERE TxnId = '$TxnId' LIMIT 1";
        $result_check2 = mysql_query($query_check2); 
        $row_check2 = mysql_fetch_assoc($result_check2);
        echo $row_check2['CompareStatus'].PHP_EOL;
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
