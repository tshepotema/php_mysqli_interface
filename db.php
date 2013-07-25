<?php

/**
 * @author      : Tshepo Tema
 * @created     : 23 Jul 2013
 * @description : mysqli database interface layer
 *
 */

include_once ("globals.php");

class DB {
    
    public $bConnected;     //connection flag
    public $mysqli;
    
    public function __construct () {
        $this->mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        //check the database connection
        if (mysqli_connect_errno()) {
            //unable to connect to database
            $bConnected = false;
            exit();
        } else {
            $bConnected = true;
        }
    }
    
    public function __destruct() {
        $this->mysqli->close();
    }
    
    public function insertID() {
        return $this->mysqli->insert_id;
    }
    
    public function convertValuesToRefs($aArray){
        if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
        {
            $refs = array();
            foreach($aArray as $key => $value)
                $refs[$key] = &$aArray[$key];
            return $refs;
        }
        return $aArray;
    }
    
    public function insertRows($sTable, $aValues, $aFields = "") {
        $SQL = "SHOW COLUMNS FROM ".$sTable;
        if ($rSTMT = $this->mysqli->prepare($SQL)) {
            $rSTMT->execute();  //execute the query
            //bind the results
            $rSTMT->bind_result($sField, $sType, $sNull, $sKey, $sDefault, $sExtra);
            //loop through the results
            $sFieldTypes = $sFieldMap = "";
            while ($rSTMT->fetch()) {
                if (strpos($sType, "int") !== false) {
                    $sTypeIndicator = "i";
                } else if (strpos($sType, "double") !== false) {
                    $sTypeIndicator = "d";                        
                } else if  (strpos($sType, "blob") !== false) {
                    $sTypeIndicator = "b";
                } else {
                    $sTypeIndicator = "s";
                }
                $sFieldTypes .= $sTypeIndicator;
                $sFieldMap .= "?";
            }
            $sFieldTypes = substr($sFieldTypes, 1);
            $sFieldMap = substr($sFieldMap, 1);
            $sFieldMap = str_replace("?", "?, ", $sFieldMap);
            $sFieldMap = rtrim($sFieldMap, ", ");

            //sql to insert a new record
            $rSTMT = $this->mysqli->prepare("INSERT INTO ".$sTable." VALUES (NULL, $sFieldMap);");

            foreach ($aValues as $value) {
                $aPar[] = "";
            }

            //set and bind the parameters 
            $aReference = array_merge((array)$sFieldTypes, $aValues);
            call_user_func_array(array($rSTMT, "bind_param"), $this->convertValuesToRefs($aReference));

            //execute the prepared statement
            $rSTMT->execute();
        } else {
            echo "Failed to prepare: ".$SQL;
        }
    }
    
    public function updateRows($sTable, $aFields, $aValues, $sFilterField, $sFilterValue, $sFilterType = "=", $sExtraSQL = "") {
        $SQL = "SHOW COLUMNS FROM ".$sTable;
        if ($rSTMT = $this->mysqli->prepare($SQL)) {
            $rSTMT->execute();  //execute the query
            //bind the results
            $rSTMT->bind_result($sField, $sType, $sNull, $sKey, $sDefault, $sExtra);
            //loop through the results
            $sFieldTypes = $sFieldMap = "";
            while ($rSTMT->fetch()) {
                if (!in_array($sField, $aFields)) continue;
                
                if (strpos($sType, "int") !== false) {
                    $sTypeIndicator = "i";
                } else if (strpos($sType, "double") !== false) {
                    $sTypeIndicator = "d";                        
                } else if  (strpos($sType, "blob") !== false) {
                    $sTypeIndicator = "b";
                } else {
                    $sTypeIndicator = "s";
                }
                $sFieldTypes .= $sTypeIndicator;
                $sFieldMap .= "?";
                $aUpdateFields[$sField]['type'] = $sTypeIndicator;
            }
            $iCounter = 0;
            $sUpdates = "";
            foreach ($aFields as $sField) {
                $sUpdates .= " ".$aFields[$iCounter]." = ?,";
                $iCounter++;
            }
            $sUpdates = rtrim($sUpdates, ",");
            $sExtraSQL = (!empty($sExtraSQL)) ? " ".$sExtraSQL: "";
            $sFilterType = (empty($sFilterType)) ? "=": $sFilterType;
            $sCondition = $sFilterField." ".$sFilterType." ".$sFilterValue;
            if (!empty($sUpdates) && !empty($sFilterField) && !empty($sFilterValue)) {
                $SQL = "UPDATE ".$sTable." SET ".$sUpdates." WHERE ".$sCondition."".$sExtraSQL;
                if ($rSTMT = $this->mysqli->prepare($SQL)) {
                    $aReference = array_merge((array)$sFieldTypes, $aValues);
                    call_user_func_array(array($rSTMT, "bind_param"), $this->convertValuesToRefs($aReference));
                    $rSTMT->execute();
                } else {
                    echo "Failed to prepare: ".$SQL;
                }
            } else {
                echo "Invalid update request: Field = ".$sFilterField." :: Value = ".$sFilterValue." :: updates = ".$sUpdates;
            }
        } else {
            echo "Failed to prepare: ".$SQL;
        }
    }
    
    public function getFieldValue($sTable, $sField, $sID, $sIDvalue) {
        $SQL = "SELECT ".$sField." FROM ".$sTable." WHERE ".$sID." = '".$sIDvalue."' LIMIT 1";
        if ($rSTMT = $this->mysqli->prepare($SQL)) {
            //execute the query
            $rSTMT->execute();
            //bind the results
            $rSTMT->bind_result($sFieldValue);
            $rSTMT->fetch();    //get the results
            return $sFieldValue;
        } else {
            echo "Failed to prepare: ".$SQL;            
        }
    }
    
    public function getCount($sTable, $sID, $sField, $sValue) {
        $SQL = "SELECT COUNT(".$sID.") FROM ".$sTable." WHERE ".$sField." = '".$sValue."'";
        if ($rSTMT = $this->mysqli->prepare($SQL)) {
            //execute the query
            $rSTMT->execute();
            //bind the results
            $rSTMT->bind_result($sFieldValue);
            $rSTMT->fetch();    //get the results
            return $sFieldValue;
        } else {
            echo "Failed to prepare: ".$SQL;            
        }        
    }
    
    public function retrieveRows($sTable, $sFields, $sConditionField, $sConditionValue, $sConditionType = "=", $sExtraSQL = "") {
        $sCondition = "";
        if (!empty($sConditionField) && !empty($sConditionValue)) {
            $sCondition = " WHERE ".$sConditionField." ".$sConditionType." ".$sConditionValue;
        }
        if (empty($sFields)) {
            $SQL = "SHOW COLUMNS FROM ".$sTable;
            if ($rSTMT = $this->mysqli->prepare($SQL)) {
                $rSTMT->execute();  //execute the query
                //bind the results
                $rSTMT->bind_result($sField, $sType, $sNull, $sKey, $sDefault, $sExtra);
                //loop through the results
                $sFieldTypes = $sFieldMap = "";
                while ($rSTMT->fetch()) {
                    $sFields .= $sField.",";
                }
                $sFields = rtrim($sFields, ",");
            } else {
                echo "Failed to prepare: ".$SQL;
            }
        }
        
        $SQL = "SELECT ".$sFields." FROM ".$sTable.$sCondition.$sExtraSQL;
        if ($rSTMT = $this->mysqli->prepare($SQL)) {            
            //execute the query
            $rSTMT->execute();
            if($rSTMT instanceof mysqli_stmt)
            {
                $rSTMT->store_result();

                $variables = array();
                $data = array();
                $meta = $rSTMT->result_metadata();

                while($field = $meta->fetch_field())
                    $variables[] = &$data[$field->name]; // pass by reference
            }
                    
            //bind the results
            call_user_func_array(array($rSTMT, "bind_result"), $variables);
            $i = 0;
            while ($rSTMT->fetch()) {
                $array[$i] = array();
                foreach ($data as $k=>$v)
                    $array[$i][$k] = $v;
                $i++;
                $aResults = $array;
            }
            return $aResults;
        } else {
            echo "Failed to prepare: ".$SQL;            
        }        
    }
    
    public function query($SQL, $debug = false) {
        if ($debug) {
            print 'Query: <br />';
            print $SQL;
            print '<br />';
        }
        $rQuery = $this->mysqli->query($SQL);
        if (stripos(substr($SQL, 0, 8), "select ") !== false) {
            while ($aRow = $rQuery->fetch_assoc()) {
                $aResults[] = $aRow;
            }
            return $aResults;
        } else {
            if ($this->mysqli->errno) return $this->mysqli->error;
            else return true;
        }
    }
    
}

/* //sample code below 
	$db = new DB();

	$SQL = "CREATE TABLE users (
		user_id INT NOT NULL AUTO_INCREMENT,
		name VARCHAR(30),
		status INT NOT NULL DEFAULT 1,
		PRIMARY KEY(user_id)) ENGINE = INNODB;";
	$mysqli->query($SQL);
	
	$db->query("INSERT INTO users(name) 
			VALUES('TestUser');");

	$iUserID = $db->insertID();		//get the unique User ID of the user that has just been inserted 
	
	//getting a role from the roles table by $iRoleID
	$sRole = $db->getFieldValue("roles", "role", "role_id", $iUserRoleID);        
	
*/

?>