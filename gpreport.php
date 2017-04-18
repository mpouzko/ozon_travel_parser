<?php

include ("gazpromparser.php");
$startDate = date('Y-m-d');
$endDate  = (new DateTime())->modify("+6 days")->format('Y-m-d');
$interval = 1;
$parser = new GazpromParser ($startDate,$endDate,$interval);
$parser -> extract_all_data();
$parser -> write_data('gp');
$parser -> save_to_xls_local('gp');
echo "----done-----\n\r";

//clear old cache
exec("find ".__DIR__."/cache/*.* -ctime +7 -delete");
