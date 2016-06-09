<?php
if (isset($_GET['id']))
{
    /* Parameters */
    include 'param.php';

    /* SQL connection */
    $options = array(
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );

    $sql = new PDO("mysql:host=" . $sqlParam['sqlHost'] . ";dbname=" . $sqlParam['sqlDb'], $sqlParam['sqlUser'], $sqlParam['sqlPwd'], $options);
    $sql->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    /* Get station static data */
    $rqt = $sql->prepare('SELECT * FROM stations WHERE id = :id');
    $rqt->execute(array(
        'id' => $_GET['id']
    ));
    $static_data = $rqt->fetch();

    /* Get station dynamic data */
    $rqt = $sql->prepare('SELECT * FROM stationsstats WHERE station_id = :station_id');
    $rqt->execute(array(
        'station_id' => $_GET['id']
    ));
    $dynamic_data = $rqt->fetchAll();

    /* Prepare series for the graph */
    $last_ts    = 0;
    $velibs     = "";
    $free_slots = "";
    $velibs_pct = "";
    $free_pct   = "";
    foreach ($dynamic_data as $data) {
        /* Here it is, the famous "000" for the timestamp! */
        $current_ts  = $data->updated*1000;
        if ($last_ts != $current_ts) {
            $velibs     .= "[".$current_ts.", ".$data->available_bikes."], ";
            $free_slots .= "[".$current_ts.", ".$data->free_stands."], ";
            if (0 != ($data->available_bikes + $data->free_stands)) {
                $velibs_pct .= "[".$current_ts.", ".round(100*($data->available_bikes/($data->available_bikes + $data->free_stands)), 0)."], ";
                $free_pct   .= "[".$current_ts.", ".round(100*($data->free_stands/($data->available_bikes + $data->free_stands)), 0)."], ";
            }
            else {
                $velibs_pct .= "[".$current_ts.", 0], ";
                $free_pct   .= "[".$current_ts.", 0], ";
            }
            $last_ts     = $current_ts;
        }
    }
    $velibs     = substr($velibs, 0, -2);
    $free_slots = substr($free_slots, 0, -2);
    $velibs_pct = substr($velibs_pct, 0, -2);
    $free_pct   = substr($free_pct, 0, -2);

    /* Prepare data to send back */
    $result['innerHTML'] = "<h2>Station ".$static_data->name."</h2>\n
<div id='Stats' class='divElement'></div>\n
<button id='Button'>Pourcentage / Nombre</button>";
    $result['velibs']     = "[".$velibs."]";
    $result['velibs_pct'] = "[".$velibs_pct."]";
    $result['free_slots'] = "[".$free_slots."]";
    $result['free_pct']   = "[".$free_pct."]";

    /* Set in JSON format */
    echo json_encode($result);
}
?>
