<?php
/*****************************************************************
 * This Script is responsible to accept an array of references,
 * and then generate Invoices (Guia Consignacao and Guia Devolucao)
 * in order to distribute products 
 *****************************************************************/

//#0 - Define some Constants
define("consignNdoc"    , 16);
define("refundNdoc"     , 18);
define("backendUrl"     , "https://sis04.drivefx.net/45B784DD/PHCWS/REST"); //TODO CHANGE WITH CLIENT

//#1 - Accept POST references
$inputJSON = file_get_contents('php://input');
$DRIVE_credentials  = json_decode($inputJSON)->credentials;
$DRIVE_references   = json_decode($inputJSON)->products;
print_r($DRIVE_references); //Debug References

/*****************************************************************/
$ch = curl_init();
$msg = "Starting with waybill...<br>";
logData($msg);

//#2 - Begin with Login
$loginResult = DRIVE_userLogin();
if($loginResult == false){
	$msg = "Error on Drive Login.<br>";
	logData($msg);
	exit(1);
}

//#3 - Create an result Array of creation/errors
$WAYBILL_result = array(); 
$WAYBILL_customersAlreadyIssued = array(); 

//#4 - Start with waybill MAIN iteration
foreach ($DRIVE_references as $reference) {
    //The following is for use in BIZ_createDocument
    $referenceFromMonth = $reference;

    //#1 - process reference (extract last 5 chars)
    $reference = UTILS_getBaseReference($reference);
    
    //#2 - Get All Clients that subscribed that magazine (base reference)
    $customersStampsToWaybill = DRIVE_getCustomersByRefSubscription($reference);
    if($customersStampsToWaybill == null){
        $msg = "No client subscribed magazine with Base ref: ".$reference."<br>";
        logData($msg);
        continue;
    }
    
    //#3 - Get Full Client Record (for update dates later and NO/ESTAB bills)
    $customersToWaybill = UTILS_prepareCustomersByStamps($customersStampsToWaybill);
    
    //Debug - How many Customers to waybill
    //print_r(json_encode($customersToWaybill) . "<br><br>");

    //#4 - iterate for each subscriber
    foreach($customersToWaybill as $customer){
        //#4.1 - Check if this customer (clstamp) was been already issued in this script
        if (in_array($customer['clstamp'], $WAYBILL_customersAlreadyIssued)){
            //means that we already waybilled this customer in another ref iteration in this script
            $msg = "Customer ".$customer['nome']."(n.".$customer['no'].") already waybilled in this script iteration <br><br>";
            logData($msg);
            continue;
        }

        $wasIssuedThisMonth = UTILS_isSameMonth($customer['u6525_indutree_cl']['lastexpedition']);
        
        //#5 - Only waybill customer if last waybill was in last month
        if($wasIssuedThisMonth){
            $msg = "The customer ".$customer['nome']."(n.".$customer['no'].") already issued for this month.<br><br>";
            logData($msg);
            continue;
        }

        //#6 - Start creating Consign Doc
        $newConsignWaybill = BIZ_createDocument(consignNdoc, $customer, $DRIVE_references, 0);
        if($newConsignWaybill == null){
            $msg = "#ERROR# Creating Consign WayBill - customer ".$customer['nome']."(n.".$customer['no'].")<br><br>";
            logData($msg);
            continue;
        }
       
        //#7 - Start creating Refund Doc
        $newRefundWaybill = BIZ_createDocument(refundNdoc, $customer, $DRIVE_references, 1);
        if($newRefundWaybill == null){
            $msg = "#ERROR# Creating Refund WayBill - customer ".$customer['nome']."(n.".$customer['no'].")<br><br>";
            logData($msg);
            continue;
        }

        
        //#8 - Update Consign to make reference of Refund
        $newConsignWaybill['u6525_indutree_ft']['refund_ftstamp'] = $newRefundWaybill['ftstamp'];
        $newConsignWaybill['u6525_indutree_ft']['refund_uniqueid'] = $newRefundWaybill['logInfo'];
        $newConsignWaybill = DRIVE_saveInstance("Ft", $newConsignWaybill);
        if($newConsignWaybill == null){
            $msg = "#ERROR# Updating Consign WayBill - customer ".$customer['nome']."(n.".$customer['no'].")<br><br>";
            logData($msg);
            continue;
        }
           
        //#9 - Sign consign 
        //$newConsignWaybill = DRIVE_signDocument($newConsignWaybill);
		if($newConsignWaybill == null){
			$msg = "#ERROR# Updating Consign WayBill - customer ".$customer['nome']."(n.".$customer['no'].")<br><br>";
			logData($msg);
			continue;
        }
                
        //#10 - Log the success of consign waybill
        $msg = "#SUCCESS# Consign WayBill created with No.".$newConsignWaybill['fno']." - Customer ".$customer['nome']."(n.".$customer['no'].")<br>";
        logData($msg);
        

        
        //#11 - Update Refund to make reference of Refund
        $newRefundWaybill['u6525_indutree_ft']['refund_ftstamp'] = $newConsignWaybill['ftstamp'];
        $newRefundWaybill['u6525_indutree_ft']['refund_uniqueid'] = $newConsignWaybill['logInfo'];
        $newRefundWaybill = DRIVE_saveInstance("Ft", $newRefundWaybill);
        if($newRefundWaybill == null){
            $msg = "#ERROR# Creating Refund WayBill - customer ".$customer['nome']."(n.".$customer['no'].")<br><br>";
            logData($msg);
            continue;
        }
        
        //#12 - Log the success of Refund waybill
        $msg = "#SUCCESS# Refund WayBill created with No.".$newRefundWaybill['fno']." - Customer ".$customer['nome']."(n.".$customer['no'].")<br>";
        logData($msg);

        //#13 - Mark this customer as Already Waybilled
        $WAYBILL_customersAlreadyIssued[] = $customer['clstamp'];

        //#14 - Update Cl for lastExpedition date
        $customer['u6525_indutree_cl']['lastexpedition'] = UTILS_getPresentDate();
        $customer = DRIVE_saveInstance("Cl", $customer);
        if($customer == null){
            $msg = "#ERROR# Update last expedition for customer  ".$customer['nome']."(n.".$customer['no'].") went wrong.<br><br>";
            logData($msg);
            continue;
        }

        
        

        
        //print_r("<br><br>Clients issued<br><br>");
        //print_r($WAYBILL_customersAlreadyIssued);





        echo "<br><br>";        
        //exit(1);//try only 1 iteration
    }


}


/**
 * BIZ of Creating Docs
 */
function BIZ_createDocument($ndoc, $customer, $allRequestedReferences, $invoiceType){
    //#1 - Get an order new instance
	$newInstanceFt = DRIVE_getNewInstance("Ft", $ndoc);
	if($newInstanceFt == null){
		$msg = "Error on getting new instance Ft. <br>";		
		logData($msg);
		return null;
	}
	
	//#2 - Add customer no to order
    $newInstanceFt['no'] = $customer['no'];
    $newInstanceFt['estab'] = $customer['estab'];

	//#2.1 - Then sync
	$newInstanceFt = DRIVE_actEntiy("Ft", $newInstanceFt);
	if($newInstanceFt == null){
		$msg = "Error on act entity for Invoice. <br>";
		logData($msg);
		return null;
    }
    
    //#3 - Now add references - NOTE: $reference in foreach is not a base product
    foreach($allRequestedReferences as $reference){
        //#3.1 - try to get reference subscribed for this client
        $subscribedArticle = UTILS_getSubscribedStByRef($customer, $reference);
        if($subscribedArticle == null){
            //means that this client does not subscribed this article
            continue;
        }
        //#3.2 - Otherwise, add to Document row
        $productRow = array(
            "ref" => $reference,
            "qtt" => 0
        );

         //#3.3 - Set up quantity depending on 0 - Consign, 1 - Refund, 2 - Invoice
        switch ($invoiceType) {
            case 0:
                $productRow['qtt'] = $subscribedArticle['quantity'];
                break;
            case 1:
                $productRow['qtt'] = 0;
                break;
            case 2:
                //TODO - Make diference between consign and refund
                break;        
        }

        //3.4 - Add it!
        $newInstanceFt['fis'][] = $productRow;

    }
     
    
    //#3.5 - Then sync
    $newInstanceFt = DRIVE_actEntiy("Ft", $newInstanceFt);
	if($newInstanceFt == null){
		$msg = "Error on act entity for Invoice. <br>";
		logData($msg);
		return null;
    }

    //#3.6 - If type is 1 - Refund, then double sync for qtt = 0
    if($invoiceType == 1){
        for ($i = 0; $i < sizeof($newInstanceFt['fis']); $i++) {
            $newInstanceFt['fis'][$i]['qtt'] = 0;
        }         
        $newInstanceFt = DRIVE_actEntiy("Ft", $newInstanceFt);
    }

    //#3.7 - If type is 0 - Consign , Data Carga e Hora
    if($invoiceType == 0){
        /*$newInstanceFt['datacarga'] = 
        $newInstanceFt['hcarga'] = */
    }


    //#4 - Save Invoice
	$newInstanceFt = DRIVE_saveInstance("Ft", $newInstanceFt);
	if($newInstanceFt == null){
		$msg = "Error on save entity for Invoice. <br><br>";		
		logData($msg);
		return null;
	}
    
    //#5 - return it
    return $newInstanceFt;
    
} 

/**
 * DRIVE Section
 * This Block is responsible for ALL
 * the comunication with client's DriveFX
 */
//#A - Drive FX Login
function DRIVE_userLogin(){
    global $ch, $DRIVE_credentials;

    $url = backendUrl . '/UserLoginWS/userLoginCompany';

    // Create map with request parameters
    $params = (array) $DRIVE_credentials;

    // Build Http query using params
    $query = http_build_query ($params);
    //initial request with login data

    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');
    $response = curl_exec($ch);

    // send response as JSON
    $response = json_decode($response, true);
    if (curl_error($ch)) {
        return false;
    } else if(empty($response)){
        return false;
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
        echo $response['messages'][0]['messageCodeLocale']."<br>";
        echo "Error in login. Please verify your username, password, applicationType and company." ;
        return false;
    }
    return true;
}

//#B - Get New Instance (Entity= Cl , Bo, St)
function DRIVE_getNewInstance($entity, $ndos){

    global $ch;

    $url = backendUrl . "/".$entity."WS/getNewInstance";
    $params =  array('ndos' => $ndos);

    $response=DRIVE_Request($ch, $url, $params);

    if(empty($response)){
        return null;
    }
    if(isset($response['messages'][0]['messageCodeLocale'])){
        return null;
    }


    return $response['result'][0];
}

//#C - Sync entity Instance (Entity= Cl , Bo, St)
function DRIVE_actEntiy($entity, $itemVO){

	global $ch;

	$url = backendUrl . "/".$entity."WS/actEntity";
	$params =  array('entity' => json_encode($itemVO),
					 'code' => 0,
					 'newValue' => json_encode([])
				);

	$response=DRIVE_Request($ch, $url, $params);

	//echo json_encode( $response );
	if(empty($response)){
		return null;
	}
	if(isset($response['messages'][0]['messageCodeLocale']) && $response['messages'][0]['messageCode'] != 'messages.Business.Stocks.InvalidRefAutoCreate'){
		$msg = $response['messages'][0]['messageCodeLocale'] ."<br>";
		logData($msg);
		return null;
	}

	return $response['result'][0];
}

//#D - save Instance (Entity= Cl , Bo, St)
function DRIVE_saveInstance($entity, $itemVO){

	global $ch;

	$url = backendUrl .  "/".$entity."WS/Save";
	$params =  array('itemVO' => json_encode($itemVO),
					 'runWarningRules' => 'false'
				);

	$response=DRIVE_Request($ch, $url, $params);

	//echo json_encode( $response );
	if(empty($response)){
		$msg = "Empty save";
		logData($msg);
		return null;
	}
	if(isset($response['messages'][0]['messageCodeLocale'])){
		$msg = $response['messages'][0]['messageCodeLocale'];
		logData($msg);
		return null;
	}


	return $response['result'][0];

}

//#E - Drive Generic call
function DRIVE_Request($ch, $url,$params){

	// Build Http query using params
	$query = http_build_query ($params);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

	curl_setopt($ch, CURLOPT_BINARYTRANSFER, false);


	$response = curl_exec($ch);
	// send response as JSON
	return json_decode($response, true);
}

//#F - Call Drive to return a list of costumers with baseRef subscription
function DRIVE_getCustomersByRefSubscription($baseRef){
	global $ch;

	// #1 - get Order By Id
	$url = backendUrl . '/SearchWS/Query';

    $params =  array('itemQuery' => '{
        "entityName": "u6525_indutree_cl_magazines",
        "distinct": true,
        "lazyLoaded": false,
        "SelectItems": [
            "clstamp"
        ],
        "filterItems": [
          {
            "filterItem": "ref",
            "valueItem": "'. $baseRef .'",
            "comparison": 0,
            "groupItem": 0
          }
        ],
        "orderByItems": [],
        "JoinEntities": [],
        "groupByItems": []
      }');

    

	$response=DRIVE_Request($ch, $url, $params);

	if(empty($response)){
		return false;
	} else if(count($response['result']) == 0 ){
		return null;
	}

    return $response['result'];
}

//#G - Call Drive to return a Customer by stamp
function DRIVE_getCustomersByStamp($clstamp){
	global $ch;

	// #1 - get Order By Id
	$url = backendUrl . '/SearchWS/QueryAsEntities';

    $params =  array('itemQuery' => '{
        "entityName": "Cl",
        "distinct": true,
        "lazyLoaded": false,
        "SelectItems": [],
        "filterItems": [
          {
            "filterItem": "clstamp",
            "valueItem": "'. $clstamp .'",
            "comparison": 0,
            "groupItem": 1
          },
          {
            "filterItem": "inactivo",
            "valueItem": false,
            "comparison": 0,
            "groupItem": 0
          }
        ],
        "orderByItems": [],
        "JoinEntities": [],
        "groupByItems": []
      }');

    

	$response=DRIVE_Request($ch, $url, $params);

	if(empty($response)){
		return false;
	} else if(count($response['result']) == 0 ){
		return null;
	}

    return $response['result'][0];
}

//#H - Sign Document
function DRIVE_signDocument($itemVO){

	global $ch;

	$url = backendUrl . "/FtWS/signDocument";
	$params =  array('ftstamp' => $itemVO['ftstamp']);

	$response=DRIVE_Request($ch, $url, $params);

	//echo json_encode( $response );
	if(empty($response)){
		return null;
	}
	if(isset($response['messages'][0]['messageCodeLocale'])  && $response["messages"][0]["messageCode"] != "messages.saft.export.dt.webservice.successful"){
		$msg = $response['messages'][0]['messageCodeLocale'];
		logData($msg);
		return null;
	}


	return $response['result'][0];
}

/**
 * Utils Sections
 * This Block is responsible for all
 * needed and re-usable code
 */
//#A - Log and Echo messages from script
function logData($data){
    
    echo($data);

	$file = 'log.txt';
	// Open the file to get existing content
	$current = file_get_contents($file);
	// Append a new person to the file
	$current .=  "\n\n----------------------" . date("Y-m-d H:i:s") . "----------------------\n" . $data ;
	// Write the contents back to the file
	file_put_contents($file, $current);

}

//#B - Remove the last 5 chares from reference
function UTILS_getBaseReference($reference){
    return substr($reference, 0, -5);
}

//#C - Get Full Customers Record based on stamp array
function UTILS_prepareCustomersByStamps($stampArray){
    $customerList = array();
    //#1 - Iterate array
    foreach($stampArray as $customerStamp){
        $customerToAdd = DRIVE_getCustomersByStamp($customerStamp['clstamp']);
        if($customerToAdd != null){
            $customerList[] = $customerToAdd;
        }
    }

    //#2 - Return the full list
    return $customerList;
}

//#D - Convert String to Date PHP - True/False
function UTILS_isSameMonth($dateString){
    //#1 - Create date object
    $date = date_create($dateString);

    //#2 - Format date
    $formattedDate = date_format($date,"Y-m-d" );
   
    //means that is empty, so...is not same month
    if($formattedDate == date('1900-01-01')){
        return false;
    }

    //#3 - get month
    $month = date("m", strtotime($formattedDate));
    $presentMonth = date("m");

    //#4 - return the month comparison
    return $month == $presentMonth;

}

//#E - Get the quantity subscribed by reference
function UTILS_getSubscribedStByRef($customer, $reference){
    $subscriptionArticle = null;
    //#1 - iterate subscriptions
    foreach($customer['u6525_indutree_cl_magazines'] as $subscriptionLine){
        if($subscriptionLine['ref'] == UTILS_getBaseReference($reference)){
            //#2 - subscription for this article(ref) found!
            $subscriptionArticle = $subscriptionLine;
        }
    }

    //#3 - return it
    return $subscriptionArticle;
}

//#F - Get TODAY date 
function UTILS_getPresentDate(){
    $presentDay = date("d");
    $presentMonth = date("m");
    $presentYear = date("Y");

    $date =  date("Y-m-d H:i:s",mktime(0,0,0,$presentMonth,$presentDay,$presentYear));

    return $date;
}

?>