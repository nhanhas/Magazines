<?php


include("DRIVE_config.php");
/*****************************************************************
 * This Script is responsible to accept an array of references,
 * and then generate Invoices (Guia Consignacao and Guia Devolucao)
 * in order to distribute products
 *****************************************************************/



//#1 - Accept POST references
$inputJSON = file_get_contents('php://input');
$DRIVE_credentials  = json_decode($inputJSON)->credentials;
$DRIVE_clients   = json_decode($inputJSON)->clients;
logData($inputJSON);
//print_r($DRIVE_clients); //Debug References
//exit(1);

/*****************************************************************/
$ch = curl_init();
$msg = "Starting with Invoicing...<br>";
logData($msg);

//#2 - Begin with Login
$loginResult = DRIVE_userLogin();
if($loginResult == false){
	$msg = "Error on Drive Login.<br>";
	logData($msg);
	exit(1);
}

//#3 - Start with invoicing MAIN iteration
foreach ($DRIVE_clients as $headquarter) {
    //#3.1 - Get All Estab Active for this headquarter
    $clientEstabs = DRIVE_getEstabFromHeadquarters($headquarter->no);
    if($clientEstabs == null){
        $msg = "There is no estabs for this headquarter: ".$headquarter->no."<br>";
        logData($msg);
        continue;
    }

    //#3.2 - Iterate estabs
    foreach($clientEstabs as $customer){
        //#3.3 - Get Guias Consign not invoiced for this 
        $consignWayBill = DRIVE_getGuiasNotInvoiced($customer['no'], $customer['estab']);
        if($consignWayBill == null){
            $msg = "There is no Guia consign not invoiced for this customer: ".$customer['no']." - ".$customer['nome']."<br>";
            logData($msg);
            continue;
        }
        
        //3.4 - Get Guia Refund for this Consign
        $refundWayBill = DRIVE_getRefundByStamp($consignWayBill['u6525_indutree_ft']['refund_ftstamp']);
        if($consignWayBill == null){
            $msg = "There is no Guia Refund that matches this consign: ".$consignWayBill['fno']."for this customer: ".$customer['no']." - ".$customer['nome']."<br>";
            logData($msg);
            continue;
        }

        //#3.5 - Check if it is Draft Record
        if($refundWayBill['draftRecord'] == true){
            $msg = "Guia Refund (n. ".$refundWayBill['fno'].") that matches this consign: ".$consignWayBill['fno']." Is in DRAFT MODE. Customer: ".$customer['no']." - ".$customer['nome']."<br>";
            logData($msg);
            continue;
        }
        
        print_r(json_encode($refundWayBill));
        exit(1);
    }
    
    

}




exit(1);


/**
 * BIZ of Creating Docs
 */
function BIZ_createDocument($ndoc, $customer, $allRequestedReferences, $invoiceType){
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
        //echo $response['messages'][0]['messageCodeLocale']."<br>";
        //echo "Error in login. Please verify your username, password, applicationType and company." ;
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

//#F - Get Guias Not invoiced - This will search only for Guias Consign
function DRIVE_getGuiasNotInvoiced($clientNo, $clientEstab){
    global $ch;

        // #1 - get Order By No estab = 0
	$url = backendUrl . '/SearchWS/QueryAsEntities';

    $params =  array('itemQuery' => '
    {
        "distinct": false,
        "entityName": "Ft",
        "filterCod": "",
        "filterItems": [
          {
            "checkNull": false,
            "collationType": 0,
            "comparison": 0,
            "filterItem": "ndoc",
            "groupItem": 1,
            "skipItemTranslate": false,
            "valueItem": '.consignNdoc.'
          },
          {
            "checkNull": false,
            "collationType": 0,
            "comparison": 0,
            "filterItem": "",
            "groupItem": 18,
            "skipItemTranslate": false,
            "valueItem": {}
          },
          {
            "checkNull": false,
            "collationType": 0,
            "comparison": 0,
            "filterItem": "u6525_indutree_ft.invoiced",
            "groupItem": 1,
            "skipItemTranslate": false,
            "valueItem": false
          },
          {
            "checkNull": false,
            "collationType": 0,
            "comparison": 0,
            "filterItem": "no",
            "groupItem": 1,
            "skipItemTranslate": false,
            "valueItem": '.$clientNo.'
          },
          {
            "checkNull": false,
            "collationType": 0,
            "comparison": 0,
            "filterItem": "estab",
            "groupItem": 0,
            "skipItemTranslate": false,
            "valueItem": '.$clientEstab.'
          },
          {
            "checkNull": false,
            "collationType": 0,
            "comparison": 0,
            "filterItem": "",
            "groupItem": 17,
            "skipItemTranslate": false,
            "valueItem": {}
          }
        ],
        "groupByItems": [],
        "joinEntities": [],
        "lazyLoaded": false,
        "limit": 20,
        "ndoc": 0,
        "offset": 0,
        "orderByItems": [],
        "SelectItems": [
        ]
      }');



	$response=DRIVE_Request($ch, $url, $params);

	if(empty($response)){
		return false;
	} else if(count($response['result']) == 0 ){
		return null;
	}

    return $response['result'][0];

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

//#I - Get Estab From HeadQuarters
function DRIVE_getEstabFromHeadquarters($clientNo){
    global $ch;

    // #1 - get Order By No estab = 0
	$url = backendUrl . '/SearchWS/QueryAsEntities';

    $params =  array('itemQuery' => '{
        "entityName": "Cl",
        "distinct": true,
        "lazyLoaded": false,
        "SelectItems": [],
        "filterItems": [
          {
            "filterItem": "no",
            "valueItem": "'. $clientNo .'",
            "comparison": 0,
            "groupItem": 1
          },
          {
            "filterItem": "inactivo",
            "valueItem": false,
            "comparison": 0,
            "groupItem": 1
          },
          {
            "filterItem": "estab",
            "valueItem": 0,
            "comparison": 1,
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

//#J - Get Refund consign by ftstamp
function DRIVE_getRefundByStamp($refundStamp){
    global $ch;

    // #1 - get Order By No estab = 0
	$url = backendUrl . '/SearchWS/QueryAsEntities';

    $params =  array('itemQuery' => '{
        "entityName": "Ft",
        "distinct": true,
        "lazyLoaded": false,
        "SelectItems": [],
        "filterItems": [
          {
            "filterItem": "ftstamp",
            "valueItem": "'. $refundStamp .'",
            "comparison": 0,
            "groupItem": 0
          }],
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

/**
 * Utils Sections
 * This Block is responsible for all
 * needed and re-usable code
 */
//#A - Log and Echo messages from script
function logData($data){

    //echo($data);

	$file = 'log.txt';
	// Open the file to get existing content
	$current = file_get_contents($file);
	// Append a new person to the file
	$current .=  "\n\n----------------------" . date("Y-m-d H:i:s") . "----------------------\n" . $data ;
	// Write the contents back to the file
	file_put_contents($file, $current);

}


?>