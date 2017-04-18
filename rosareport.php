<?php

include ("rosaparser.php");
$startDate = date('Y-m-d');
$endDate  = (new DateTime())->modify("+6 days")->format('Y-m-d');
$interval = 1;
$parser = new RosaParser ($startDate,$endDate,$interval);
$parser -> extract_all_data();
$parser -> write_data('rosa');
$parser -> save_to_xls_local('rosa');
echo "----done-----";

