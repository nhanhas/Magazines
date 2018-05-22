<?php

//define("backendUrl"     , "https://sis07.drivefx.net/35E8BA15/PHCWS/REST"); //TODO CHANGE WITH CLIENT
define("backendUrl"     , "https://sis04.drivefx.net/45B784DD/PHCWS/REST"); //TODO CHANGE WITH CLIENT

//#0 - Define some Constants
define("consignNdoc"    , 16);
define("refundNdoc"     , 18);
define("invoiceNdoc"    , 1);

//#1 - MB - Code
define("MB_CODE"    , 12176);

//#2 - Email Report
define("REPORT_STAMP" , "D33396DF-0E63-402D-FXV25");
define("EMAIL_INVOICE_BODY" , "
Exmo. Senhores,<br><br>
Junto enviamos a nossa factura referente à última consignação realizada. <br>
Tem como opções de pagamento, pagar por Multibanco ou por transferência bancária. <br>
Em caso de dúvida, pode contactar o nosso serviço de apoio a clientes.<br><br>

Obrigado,<br>
Indutree");

 ?>
