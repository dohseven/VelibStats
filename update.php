<?php
include 'velib.php';

/* See param.php to set all parameters */
include 'param.php';

$velib = new Velib($apiParam, $sqlParam, $debug);
$velib->update_stations();
?>
