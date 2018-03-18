<?php
/*****************************************************************
 * This Script is responsible to accept an array of references,
 * and then generate Invoices (Guia Consignacao and Guia Devolucao)
 * in order to distribute products 
 *****************************************************************/

//#0 - Define some Constants
define("consignNdoc"    , 1);
define("refundNdoc"     , 1);
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

//#4 - Start with waybill MAIN iteration
foreach ($DRIVE_references as $reference) {
    
    //#1 - process reference (extract last 5 chars)
    $reference = UTILS_getBaseReference($reference);
    


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
		$msg = $response['messages'][0]['messageCodeLocale'];
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

?>