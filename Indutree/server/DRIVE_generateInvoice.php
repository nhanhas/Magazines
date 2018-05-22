<?php

include("UTILS_calcReferences.php");
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
$DRIVE_products   = json_decode($inputJSON)->products;
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
foreach ($DRIVE_clients as $requestedClient) {
  
    //#3.1 Get Full Client From Request (do not need)
    
    //#3.2 Get All Guias Consign From Client ( guia by no and estab )
    $consignWayBillList = DRIVE_getGuiasByNoEstab(consignNdoc, $requestedClient->no, $requestedClient->estab);
    if($consignWayBillList == null){
        $msg = "There are no Guias consign for this customer: ".$requestedClient->no.", estab: " . $requestedClient->estab . "<br>";
        logData($msg);
        continue;
    }

    //#3.2.1 - Filter WayBills only with requested refs
    $consignWayBillList = UTILS_getFilteredDocByRefs($consignWayBillList, $DRIVE_products);

    //#3.3 - Get All Guias Refund From Client ( guia by no and estab )
    $refundWayBillList = DRIVE_getGuiasByNoEstab(refundNdoc, $requestedClient->no, $requestedClient->estab);
    if($refundWayBillList == null){
        $msg = "There are no Guias Refund for this customer: ".$requestedClient->no.", estab: " . $requestedClient->estab . "<br>";
        logData($msg);
    }
    //#3.3.1 - Filter WayBills only with requested refs
    $refundWayBillList = UTILS_getFilteredDocByRefs($refundWayBillList, $DRIVE_products);


    //#3.3 For each guia, get de ref and qtt requested
    $linesToInvoice = array();

    //#3.3.1 - Iterate consigns
    foreach($consignWayBillList as $consignWayBill){
        //#3.3.2 - iterate lines 
        foreach($consignWayBill['fis'] as $invoiceLine){
            //#3.3.3 - store it in final lines array, if requested by user
            if(in_array($invoiceLine['ref'], $DRIVE_products)){
                $linesToInvoice[] = array(
                    "ref" => $invoiceLine['ref'],
                    "qtt" => $invoiceLine['qtt']
                );
            }
        }
    }

    //#3.3.2 - Iterate Refunds and recalc difference
    foreach($refundWayBillList as $refundWayBill){
        //#3.3.2 - iterate lines 
        foreach($refundWayBill['fis'] as $refundLine){
            //#3.3.3 - store it in final lines array, if requested by user
            if(in_array($refundLine['ref'], $DRIVE_products)){
                //#3.3.4 - recalc qtt with difference
                for($index = 0; $index < sizeof($linesToInvoice); $index++){                   
                    if($linesToInvoice[$index]['ref'] == $refundLine['ref']){
                        $linesToInvoice[$index]['qtt'] = $linesToInvoice[$index]['qtt'] - $refundLine['qtt'];
                    }
                }
               
            }
        }
    }

    //#3.3.3 - Iterate invoice lines to remove qtt = 0
    for($index = 0; $index < sizeof($linesToInvoice); $index++){                   
        if($linesToInvoice[$index]['qtt'] == 0){
            unset($linesToInvoice[$index]);
        }
    }

    //#3.4 Make invoice to selected Client or its headquarted
    if(empty($linesToInvoice)){
        $msg = "There is nothing to invoice to this customer: ".$requestedClient->no.", estab: " . $requestedClient->estab . "<br>";
        logData($msg);
        continue;
    }

    //#4 - Get new instance of Ft
    $newInstanceFt = DRIVE_getNewInstance('Ft', invoiceNdoc);
    if($newInstanceFt == null){
        $msg = "#ERROR# on get new instance of Invoice for customer: ".$requestedClient->no." - ".$requestedClient->estab."<br>";
        logData($msg);
        continue;
    }

    //#4.1 - Fulfill Client as HeadQuarters Or Estab
    if($requestedClient->invoiceHeadquarters == true){
        $newInstanceFt['no'] =  $requestedClient->no;
        $newInstanceFt['estab'] =  0;
        logData("Invoicing HeadQuarter<br>");
    }else{
        $newInstanceFt['no'] =  $requestedClient->no;
        $newInstanceFt['estab'] =  $requestedClient->estab;
        logData("Invoicing Estab<br>");
    }
    
    //#4.2 - Then sync
    $newInstanceFt = DRIVE_actEntiy("Ft", $newInstanceFt);
    if($newInstanceFt == null){
        $msg = "#ERROR# on sync for no estab of Invoice for customer: ".$requestedClient->no." - ".$requestedClient->estab."<br>";
        logData($msg);
        continue;
    }
    //#4.3 - fulfill fis    
    $newInstanceFt['fis'] = $linesToInvoice;
    //#4.4 - Then sync
    $newInstanceFt = DRIVE_actEntiy("Ft", $newInstanceFt);
    if($newInstanceFt == null){
        $msg = "#ERROR# on sync for product lines of Invoice for customer: ".$requestedClient->no." - ".$requestedClient->estab."<br>";
        logData($msg);
        continue;
    }
   
    //logData(json_encode($newInstanceFt));

    //#4.5 - Save Invoice
    $newInstanceFt = DRIVE_saveInstance('Ft', $newInstanceFt);
    if($newInstanceFt == null){
        $msg = "#ERROR# on Invoice for customer: ".$requestedClient->no." - ".$requestedClient->estab."<br>";
        logData($msg);
        continue;
    }

    $msg = "#SUCCESS# Invoice created for customer: ".$requestedClient->no." : ".$requestedClient->estab." <br>";
    logData($msg);

    //#6 - Sign Invoice
    $newInstanceFt = DRIVE_signDocument($newInstanceFt);
    if($newInstanceFt == null){
        $msg = "#ERROR# Sign Invoice - customer ".$requestedClient->no.".<br><br>";
        logData($msg);
        continue;
    }else{
        $msg = "#SUCCESS# Invoice (n?".$newInstanceFt['fno'].") SIGNED for customer: ".$requestedClient->no." : ".$requestedClient->estab."<br>";
        logData($msg);
    }

    //#7 - Generate Ref MB
    $baseRefToMB = generateInvoiceBaseRef($newInstanceFt['fno'], $newInstanceFt['ndoc']);
    logData("MB - base reference created: " . $baseRefToMB . "<br>");

    //#7.1 - Generate Ref MB to include in invoice
    $referencesMB = calculateReferences(MB_CODE, $newInstanceFt['etotal'], $baseRefToMB);
    logData("MB  - reference to Invoice: " . $referencesMB . "<br>");

    $newInstanceFt['entidademb'] = strval(MB_CODE);
    $newInstanceFt['refmb1'] = $referencesMB[0] . $referencesMB[1] . $referencesMB[2];
    $newInstanceFt['refmb2'] = $referencesMB[3] . $referencesMB[4] . $referencesMB[5];
    $newInstanceFt['refmb3'] = $referencesMB[6] . $referencesMB[7] . $referencesMB[8];
    $newInstanceFt['etotalmb'] = $newInstanceFt['etotal'];

    //#7.2 - Save Invoice
    $newInstanceFt = DRIVE_saveInstance('Ft', $newInstanceFt);
    if($newInstanceFt == null){
        $msg = "#ERROR# on save MB in Invoice for customer: ".$requestedClient->no." - ".$requestedClient->estab."<br>";
        logData($msg);
    }else{
        $msg = "#SUCCESS# Invoice with REF MB fulfilled for customer: ".$requestedClient->no." : ".$requestedClient->estab." <br>";
        logData($msg);
    }

    //#8 - Send Document by Email
    $printResult = DRIVE_printDocument($newInstanceFt);
    if($printResult == null){
        $msg = "#ERROR# on send Invoice for customer: ".$requestedClient->no." - ".$requestedClient->estab."<br>";
        logData($msg);
    }else{
        $msg = "#SUCCESS# Invoice sent by email for customer: ".$requestedClient->no." : ".$requestedClient->estab." <br>";
        logData($msg);
    }


    //print_r('Client no: '. $requestedClient->no.", estab: " . $requestedClient->estab . "<br>");
    //print_r($linesToInvoice);
         
}

$msg = '{"code": 0, "message":"", "data": ""}';
echo $msg;
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

//#K - Get Guias Consign - This will search only for Guias Consign
function DRIVE_getGuiasByNoEstab($ndoc, $clientNo, $clientEstab){
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
            "valueItem": '.$ndoc.'
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
            "filterItem": "draftRecord",
            "groupItem": 1,
            "skipItemTranslate": false,
            "valueItem": false
          },
          {
            "checkNull": false,
            "collationType": 0,
            "comparison": 0,
            "filterItem": "anulada",
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

    return $response['result'];

}

//#L - Get customer by no estab
function DRIVE_getCustomersByNoEstab($no, $estab){
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
            "filterItem": "no",
            "valueItem": "'. $no .'",
            "comparison": 0,
            "groupItem": 1
          },
          {
            "filterItem": "estab",
            "valueItem": "'. $estab .'",
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

//#M - function to print an order
function DRIVE_printDocument($document){
    global $ch;

    //#1 - Get customer to get email
    $customer = DRIVE_getCustomersByNoEstab($document['no'], $document['estab']);

    if($customer['email'] == ''){
        $msg = "#ATENTION# customer does not have an email: ".$customer['no']." - ".$customer['estab']."<br>";
        logData($msg);
        return null;
    }

    //#1 - doc id is the same of configured in this Sync
    $docId = $document['ndoc'];
    $repstamp = '';
    
    $url = backendUrl . '/reportws/print';

    $params =  array('options' => '{
        "docId": '.$docId.',
        "emailConfig": {
            "bcc":"",
            "body":"'. EMAIL_INVOICE_BODY .'",
            "cc":"",
            "contentType":"",
            "isBodyHtml":false,
            "sendFrom":"suporte@phc.pt",
            "sendTo":"'. $customer['email'] .'",
            "sendToMyself":false,
            "subject":"Envio de Documento"
        },
        "generateOnly": false,
        "isPreview": false,
        "outputType": 2,
        "printerId": "",
        "records": [
            {
                "docId": '.$docId.',
                "entityType": "Ft",
                "stamp": "'.$document['ftstamp'].'"
            }
        ],
        "reportStamp": "'.REPORT_STAMP.'",
        "sendToType": 0,
        "serie": 0
    
    }');
        $response=DRIVE_Request($ch, $url, $params);
        if(empty($response)){
            return false;
        } else if(count($response['result']) == 0 ){
            return null;
        }

        return $response['result'];
}

/**
 * Utils Sections
 * This Block is responsible for all
 * needed and re-usable code
 */

//#A - Calculated the diference between consign and refund
function UTILS_getCalculatedLines($consignWayBill, $refundWayBill){
    $calculatedLines = array();

    //#0 - Store description of estab
    $descriptionLine = array(
        'design' => 'Relativo ao cliente '.$consignWayBill['nome'].' (estab. '.$consignWayBill['estab'].')',
        'qtt' => 0
    );

    $calculatedLines[] = $descriptionLine;

    //#1 - Iterate both product lines
    foreach($consignWayBill['fis'] as $consignLine){
        foreach($refundWayBill['fis'] as $refundLine){
            if($consignLine['ref'] == $refundLine['ref']){
                //#2 - create a product with diference
                $newLine = array(
                    'ref' => $consignLine['ref'],
                    'qtt' => $consignLine['qtt'] - $refundLine['qtt']
                );

                //#3 - store it in array
                $calculatedLines[] = $newLine;
            }
        }
    }

    //#4 - return calculated lines (fis)
    return $calculatedLines;
 
}

//#B - Get Documents Filtered by Product Ref
function UTILS_getFilteredDocByRefs($wayBillList, $productsRefs){
    $wayBillsAlreadyContempled = array();
    $filteredWayBills = array();

    //#1 - For each product Ref, check if it is in consign list
    foreach($productsRefs as $ref){
        //#2 - Iterate Waybills
        foreach($wayBillList as $waybill){
            if( in_array($waybill['ftstamp'], $wayBillsAlreadyContempled)){
                continue;
            }

            //#3 - check if it is in Fis
            foreach($waybill['fis'] as $invoiceLine){
                if($invoiceLine['ref'] == $ref){
                    //#4 - add it to filtered list
                    $wayBillsAlreadyContempled[] = $waybill['ftstamp'];
                    $filteredWayBills[] = $waybill;
                }
            }
        }
    }
    
    //#5 - Return filtered list
    return $filteredWayBills;

}



//#B - Log and Echo messages from script
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