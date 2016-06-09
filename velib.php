<?php
class Velib
{
    public $debug,
           $apiParam = array(),
           $sqlParam = array();

    function __construct($apiParam = array(), $sqlParam = array(), $debug) {
        $this->apiParam  = $apiParam;
        $this->sqlParam  = $sqlParam;
        $this->debug     = $debug;

        /* SQL connection */
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );
        $this->sql = new PDO("mysql:host=" . $this->sqlParam['sqlHost'], $this->sqlParam['sqlUser'], $this->sqlParam['sqlPwd'], $options);
        $this->sql->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        $this->sql->query("CREATE DATABASE IF NOT EXISTS ".$this->sqlParam['sqlDb']);
        $this->sql->query("USE ".$this->sqlParam['sqlDb']);

        /* Create tables if necessary */
        $rqt = $this->sql->prepare("CREATE TABLE IF NOT EXISTS stations(
                                        id INTEGER PRIMARY KEY,
                                        name TEXT,
                                        address TEXT,
                                        latitude REAL,
                                        longitude REAL,
                                        banking BOOL,
                                        bonus BOOL
                                    )");
        $rqt->execute();
        $rqt = $this->sql->prepare("CREATE TABLE IF NOT EXISTS stationsstats(
                                        station_id INTEGER,
                                        bike_stands INTEGER,
                                        available_bikes INTEGER,
                                        free_stands INTEGER,
                                        status TEXT,
                                        updated BIGINT,
                                        day_of_week INTEGER,
                                        FOREIGN KEY(station_id) REFERENCES stations(id) ON DELETE CASCADE
                                    )");
        $rqt->execute();
        $rqt = $this->sql->prepare("CREATE TABLE IF NOT EXISTS stationsevents(
                                        station_id INTEGER,
                                        timestamp BIGINT,
                                        event TEXT,
                                        FOREIGN KEY(station_id) REFERENCES stations(id) ON DELETE CASCADE
                                    )");
        $rqt->execute();
    }

    function echo_debug($string) {
        if ($this->debug) {
            echo $string."\n";
        }
    }

    /* CURL to send HTTP requests and decode result */
    function curl($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $return = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($return);

        if (isset($data)) {
            if (isset($data->error)) {
                $this->echo_debug("API error: '".$data->error);
                return NULL;
            }
            else {
                return $data;
            }
        }
        else {
            $this->echo_debug("Invalid JSON response: ".$return);
            return NULL;
        }
    }

    /* Get and update the stations list */
    function update_stations() {
        /* Get the whole list */
        $url_request = $this->apiParam['apiEndpoint']."?apiKey=".$this->apiParam['apiKey']."&contract=".$this->apiParam['contract'];
        $stations_list = $this->curl($url_request);

        if (isset($stations_list)) {
            /* Update the station data */
            foreach ($stations_list as $station) {
                /* Convert types for SQL */
                $station->banking = (int)$station->banking;
                $station->bonus = (int)$station->bonus;
                /* For some reason, the timestamp is filled with "000" on the right... */
                $station->last_update = $station->last_update/1000;

                $rqt = $this->sql->prepare('SELECT * FROM stations WHERE id = :id LIMIT 1');
                $rqt->execute(array(
                    'id' => $station->number
                ));
                $result = $rqt->fetch();

                /* Update station static data */
                if(!$result) {
                    $this->echo_debug("Add station #".$station->number);

                    /* Insert new station in database */
                    $rqt_1 = $this->sql->prepare('INSERT INTO stations SET id = :id,
                                                                           name = :name,
                                                                           address = :address,
                                                                           latitude = :latitude,
                                                                           longitude = :longitude,
                                                                           banking = :banking,
                                                                           bonus = :bonus');
                    $rqt_1->execute(array(
                        'id' => $station->number,
                        'name' => $station->name,
                        'address' => $station->address,
                        'latitude' => $station->position->lat,
                        'longitude' => $station->position->lng,
                        'banking' => $station->banking,
                        'bonus' => $station->bonus
                    ));
                }
                else {
                    /* Check if something changed */
                    $event = [];
                    if ($station->name != $result->name) {
                        $event[] = array('key' => 'name', 'old_value' => $result->name, 'new_value' => $station->name);
                    }
                    if ($station->address != $result->address) {
                        $event[] = array('key' => 'address', 'old_value' => $result->address, 'new_value' => $station->address);
                    }
                    if ($station->banking != $result->banking) {
                        $event[] = array('key' => 'banking', 'old_value' => $result->banking, 'new_value' => $station->banking);
                    }
                    if ($station->bonus != $result->bonus) {
                        $event[] = array('key' => 'bonus', 'old_value' => $result->bonus, 'new_value' => $station->bonus);
                    }
                    if (abs($station->position->lat - $result->latitude) > 0.0001) {
                        $event[] = array('key' => 'latitude', 'old_value' => $result->latitude, 'new_value' => $station->position->lat);
                    }
                    if (abs($station->position->lng - $result->longitude) > 0.0001) {
                        $event[] = array('key' => 'longitude', 'old_value' => $result->longitude, 'new_value' => $station->position->lng);
                    }
                    if (count($event)) {
                        $this->echo_debug("Update station #".$station->number);

                        /* Update information */
                        $rqt_1 = $this->sql->prepare('UPDATE stations SET name = :name,
                                                                          address = :address,
                                                                          latitude = :latitude,
                                                                          longitude = :longitude,
                                                                          banking = :banking,
                                                                          bonus = :bonus
                                                      WHERE id = :id
                                                     ');
                        $rqt_1->execute(array(
                            'name' => $station->name,
                            'address' => $station->address,
                            'latitude' => $station->position->lat,
                            'longitude' => $station->position->lng,
                            'banking' => $station->banking,
                            'bonus' => $station->bonus,
                            'id' => $station->number
                        ));

                        /* And add event to log it */
                        $rqt_1 = $this->sql->prepare('INSERT INTO stationsevents SET station_id = :station_id,
                                                                                     timestamp = :timestamp,
                                                                                     event = :event');
                        $rqt_1->execute(array(
                            'station_id' => $station->number,
                            'timestamp' => time(),
                            'event' => json_encode($event)
                        ));
                    }
                }

                /* Insert station dynamic data */
                $rqt_1 = $this->sql->prepare('INSERT INTO stationsstats SET station_id = :station_id,
                                                                            bike_stands = :bike_stands,
                                                                            available_bikes = :available_bikes,
                                                                            free_stands = :free_stands,
                                                                            status = :status,
                                                                            updated = :updated,
                                                                            day_of_week = :day_of_week');
                $rqt_1->execute(array(
                    'station_id' => $station->number,
                    'bike_stands' => $station->bike_stands,
                    'available_bikes' => $station->available_bikes,
                    'free_stands' => $station->available_bike_stands,
                    'status' => $station->status,
                    'updated' => $station->last_update,
                    'day_of_week' => date('w', $station->last_update)
                ));
            }
        }
    }
}

?>
