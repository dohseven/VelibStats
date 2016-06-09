<?php
/* Parameters */
/* See param.php to set all parameters */
include 'param.php';

/* SQL connection */
$options = array(
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
);

$sql = new PDO("mysql:host=" . $sqlParam['sqlHost'] . ";dbname=" . $sqlParam['sqlDb'], $sqlParam['sqlUser'], $sqlParam['sqlPwd'], $options);
$sql->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
?>

<!DOCTYPE HTML>
<html>
    <head>
        <title>VelibStats</title>
        <meta charset="utf-8">
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="icon" href="images/biker.png" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="style.css" />

        <!-- Leaflet -->
        <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet/v0.7.7/leaflet.css" />
        <script src="http://cdn.leafletjs.com/leaflet/v0.7.7/leaflet.js"></script>
        <!-- MarkerCluster -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/0.5.0/MarkerCluster.Default.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/0.5.0/MarkerCluster.css" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/0.5.0/leaflet.markercluster.js"></script>
        <!-- JQuery -->
        <script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
        <!-- Highcharts -->
        <script src="https://code.highcharts.com/highcharts.js"></script>
    </head>

    <body>
        <h2>Stations de Vélib</h2>
        <p class='centerText'>Cliquez sur une station pour afficher l'historique des données</p>

        <div id='Map' class='divElement'></div>
        <div id='loadingDiv'><img src='images/loader.gif' alt='loader' /></div>

        <script language = "Javascript">
        <!-- Graph beginning -->
        function createGraph(velibData) {
            Highcharts.setOptions({
                global: {
                    useUTC : false
                },
                lang: {
                    shortMonths: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Jui', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'],
                    months: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                    weekdays: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']
                }
            });

            $('#Stats').highcharts({
                chart: {
                    type: 'area',
                    zoomType: 'x',
                    resetZoomButton: {
                        position: {
                            x: 0,
                            y: -45
                        }
                    }
                },
                title: {
                    text: 'Historique'
                },
                xAxis: {
                    type: 'datetime',
                    dateTimeLabelFormats: {
                        day: '%e %b %H:%M'
                    },
                    title: {
                        text: 'Date'
                    }
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Nombre'
                    }
                },
                tooltip: {
                    shared: true,
                    dateTimeLabelFormats: {
                        second: '%A %e %b %H:%M'
                    }
                },
                plotOptions: {
                    area: {
                        stacking: 'normal',
                        lineColor: '#666666',
                        lineWidth: 1,
                        marker: {
                            lineWidth: 1,
                            lineColor: '#666666'
                        }
                    }
                },
                series: [{
                    name: 'Places libres',
                    data: JSON.parse(velibData.free_slots)
                }, {
                    name: 'Vélibs',
                    data: JSON.parse(velibData.velibs)
                }]
            });

            var chart = $('#Stats').highcharts(),
                percent = false,
                extremes,
                xExtreme = 0,
                yExtreme = 0;

            /* Display only last 7 days at first */
            extremes = chart.xAxis[0].getExtremes();
            xExtreme = extremes.max;
            chart.xAxis[0].setExtremes(xExtreme - (7*24*3600*1000), xExtreme);
            chart.showResetZoom();

            $('#Button').click(function () {
                if (!percent) {
                    /* Store previous max on yAxis */
                    extremes = chart.yAxis[0].getExtremes();
                    yExtreme = extremes.max;
                    /* Change data */
                    chart.series[0].setData(JSON.parse(velibData.free_pct));
                    chart.series[1].setData(JSON.parse(velibData.velibs_pct));
                    /* Update chart parameters */
                    chart.yAxis[0].setTitle({
                        text: 'Pourcentage'
                    });
                    chart.yAxis[0].setExtremes(0, 100);
                    chart.series[0].update({
                        tooltip: {
                            valueSuffix: '%'
                        }
                    });
                    chart.series[1].update({
                        tooltip: {
                            valueSuffix: '%'
                        }
                    });
                }
                else {
                    /* Change data */
                    chart.series[0].setData(JSON.parse(velibData.free_slots));
                    chart.series[1].setData(JSON.parse(velibData.velibs));
                    /* Update chart parameters */
                    chart.yAxis[0].setTitle({
                        text: 'Nombre'
                    });
                    chart.yAxis[0].setExtremes(0, yExtreme);
                    chart.series[0].update({
                        tooltip: {
                            valueSuffix: ''
                        }
                    });
                    chart.series[1].update({
                        tooltip: {
                            valueSuffix: ''
                        }
                    });
                }

                percent = !percent;
            });
        }
        <!-- Graph end -->

        <!-- Map beginning -->
        /* Create map tiles */
        L.map.accessToken = 'pk.eyJ1IjoiZG9oc2V2ZW4iLCJhIjoiZldwVV8tayJ9.Ar_vQBP4ZQc0MZNYc-MF4w';
        var tileUrl = 'https://{s}.tiles.mapbox.com/v4/examples.map-dev-fr/{z}/{x}/{y}.png?access_token=' + L.map.accessToken;
        var attrib = 'Fond de carte &copy; <a href="http://mapbox.com/" target="_blank">MapBox</a>';
        var aerialTileUrl = 'https://{s}.tiles.mapbox.com/v4/dohseven.lp14gga5/{z}/{x}/{y}.png?access_token=' + L.map.accessToken;
        var aerialAttrib = 'Fond de carte &copy; <a href="http://mapbox.com/" target="_blank">MapBox</a>';

        /* Plan */
        var basis = L.tileLayer(tileUrl, {
            attribution: attrib,
            minZoom: 1,
            maxZoom: 18
        });

        /* Satellite */
        var aerial = L.tileLayer(aerialTileUrl, {
            attribution: aerialAttrib,
            minZoom: 1,
            maxZoom: 18
        });

        /* Create map */
        var map = L.map('Map', {
                        /* Use initially the "basis" layer */
                        layers: [basis]
                    })
                    .setView([ 48.857091635218, 2.3417479951579], 11);

        /* Add a scale on the map */
        L.control.scale({
            position: 'bottomright',
            imperial: false
        }).addTo(map);

        /* Create layers group for control buttons*/
        var baseLayers = {
            'Plan': basis,
            'Satellite': aerial
        };

        var layersOptions = {
            collapsed: false
        };

        L.control.layers(baseLayers, 0, layersOptions).addTo(map);

        /* Function launched when popup is openend */
        function onPopupOpen(p) {
            var popupContent = p.popup.getContent();
            $.ajax({
                url:"get_station_data.php?id=" + popupContent.id,
                type: 'GET',
                async: true,
                dataType: "json",
                beforeSend: function() {
                    document.getElementById('StationStats').innerHTML = "";
                    $('#loadingDiv').show();
                },
                complete: function(){
                    $('#loadingDiv').hide();
                },
                success: function(data) {
                    document.getElementById('StationStats').innerHTML = data.innerHTML;
                    createGraph(data);
                }
            });
        }

        /* Create ClusterGroup */
        var markers = L.markerClusterGroup();
        /* Add markers for all stations */
        <?php
        /* Get all stations static data */
        $rqt = $sql->prepare('SELECT * FROM stations');
        $rqt->execute();
        $static_data = $rqt->fetchAll();
        echo "var domelem;\n";
        echo "            var marker;\n";

        foreach ($static_data as $data)
        {
            echo "            marker = L.marker([".$data->latitude.", ".$data->longitude."]);\n";
            $popup = "<b>".$data->name."</b><br/>".$data->address;
            if ($data->bonus)
                $popup .= "<br/>Station bonus";
            echo "            domelem = document.createElement('p');
        domelem.id = '".$data->id."';
        domelem.innerHTML = \"".$popup."\";\n";
            echo "            marker.bindPopup(domelem).on('popupopen', onPopupOpen);\n";
            echo "            markers.addLayer(marker);\n";
        }
        ?>
        map.addLayer(markers);
        <!-- Map end -->

        $('#loadingDiv').hide();
        </script>

        <div id='StationStats'></div>
    </body>
    <footer>
        <p>VelibStats, real-time data of the Velib service with history stats - See it on <a href="https://github.com/dohseven/VelibStats" target="_blank"><img src='images/github.png' alt='GitHub' height='15px'/></a></p>
    </footer>
</html>
