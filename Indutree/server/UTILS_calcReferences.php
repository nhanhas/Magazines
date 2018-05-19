<?php
/**
 *  Code Example
 *$codigo = 12176;
 *$valor = 11.92;
 *$referencia = '1002349';
 *calculateReferences($codigo, $valor, $referencia); 
 * 
 */

$codigo = 12176;
$valor = 11.92;
$referencia = '1002349';
$result = calculateReferences($codigo, $valor, $referencia); 

echo $result . '<br>';
echo $result[0] . $result[1] . $result[2]. '<br>';
echo $result[3] . $result[4] . $result[5]. '<br>';
echo $result[6] . $result[7] . $result[8]. '<br>';

//#A - Main Algorithm to calculate References MB
function calculateReferences($codigo, $valor, $referencia){
    

    $valor_int = floor($valor);
    $valor_dec = round(($valor-$valor_int)*100);
    $valor_int2 = '000000' . $valor_int;
    $valor_int = substr($valor_int2,(strlen($valor_int2)-6),strlen($valor_int2));
    $valor_dec2 = '00' . $valor_dec;
    $valor_dec = substr($valor_dec2,(strlen($valor_dec2)-2),strlen($valor_dec2));

    $a = $codigo . $referencia . $valor_int . $valor_dec;


    $array = str_split($a);
    $i=0;
    $resultado = 0;




    foreach ($array as $char) {
        
        switch ($i) {
        case 0:
            $r = $char * 51;
            break;
        case 1:
            $r = $char * 73;
            break;
        case 2:
            $r = $char * 17;
            break;
        case 3:
            $r = $char * 89;
            break;
        case 4:
            $r = $char * 38;
            break;
        case 5:
            $r = $char * 62;
            break;
        case 6:
            $r = $char * 45;
            break;
        case 7:
            $r = $char * 53;
            break;
        case 8:
            $r = $char * 15;
            break;
        case 9:
            $r = $char * 50;
            break;
        case 10:
            $r = $char * 5;
            break;
        case 11:
            $r = $char * 49;
            break;
        case 12:
            $r = $char * 34;
            break;
        case 13:
            $r = $char * 81;
            break;
        case 14:
            $r = $char * 76;
            break;
        case 15:
            $r = $char * 27;
            break;
        case 16:
            $r = $char * 90;
            break;
        case 17:
            $r = $char * 9;
            break;
        case 18:
            $r = $char * 30;
            break;
        case 19:
            $r = $char * 3;
            break;
        }
        
        $resultado = $resultado + $r;
        $i = $i+1;
    }

    $resultado2 = 98 - ($resultado % 97);
    if ( strlen($resultado2) < 2){ $resultado2 = '0' . $resultado2;}
    //echo $resultado2;
    //echo '<br>';
    //echo $referencia . $resultado2;
    return $referencia . $resultado2;

}

//#B - Returns a base reference based on fno & ndoc
function generateInvoiceBaseRef($fno, $ndoc){
    //#1 - Concat Fno and Ndoc from invoice
    $reference = strval($ndoc) . strval($fno);

    //#2 - reference must have 7 chars
    $docTypeAndNumberFt_LEN = strlen($reference);

    //#3 - Add remains empty space with Zero "0"
    $remainZerosToAdd = 7 - $docTypeAndNumberFt_LEN;
    while($remainZerosToAdd != 0){
        $reference = "0" . $reference;
        $remainZerosToAdd--;
    }

    //#4 - Return it
    return $reference;
}


?>