<?php 

define("backendUrl"     , "https://sis04.drivefx.net/45B784DD/PHCWS/REST"); //TODO CHANGE WITH CLIENT

//#1 - Accept POST references
$inputJSON = file_get_contents('php://input');
$DRIVE_credentials  = json_decode($inputJSON)->credentials;

/*****************************************************************/
$ch = curl_init();
//#2 - Begin with Login
$loginResult = DRIVE_userLogin();
if($loginResult == false){
	$msg = '{"code": 100, "message":"Erro no login. Verifique as suas credenciais."}';
    echo $msg;
    exit(1);
}

$productsBase = DRIVE_getBaseProducts();
if($productsBase == null){
    $msg = '{"code": 100, "message":"Não existem produtos disponíveis."}';
    echo $msg;
    exit(1);
}

$msg = '{"code": 0, "message":"", "data": '.json_encode($productsBase).'}';
echo $msg;
exit(1);

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


//#B - Call Drive to return a list of Base Products
function DRIVE_getBaseProducts(){
	global $ch;

	// #1 - get Order By Id
	$url = backendUrl . '/SearchWS/Query';

    $params =  array('itemQuery' => '{
        "entityName": "u6525_indutree_st",
        "distinct": true,
        "lazyLoaded": false,
        "SelectItems": [
            "baseref",
            "basedesign"
        ],
        "filterItems": [         
            {
                "filterItem": "baseref",
                "valueItem": "",
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


//#D - Drive Generic call
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

?>