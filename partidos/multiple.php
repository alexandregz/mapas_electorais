<?php
/**
 * Comparador de eleccions entre mapas, podese escolher concelho.
 * 
 */
require_once('../class/datosElectorais.php');
require_once('../class/ComarcasClass.php');
require_once('../class/Partidos.php');

// ---- Mapa
//		ainda que os resultados sejam de fora de Compos, funciona co json de 'comarca_Compostela) == a revisar :-? (empregoo porque ocupa muito menos)
$ficheiro_coordenadas_json = '../datos/mapas/Mapas_censais_comarca_Compostela_2022_DISTRITOS.json';

$numeroPartidosMostrar = Partidos::getNumeroPartidosAComprobarNosPrimeiros();


// ------- CP concelho e arrray de concelhos a crear layers nos mapas
// recibimos concelho e buscamos o ID deste
$concellos = ComarcasClass::concellosDisponhibles();
@$cp_concello = array_search($_POST['concello'], $concellos);

if(!$cp_concello) $cp_concello = 15078;	// Compostela por defecto, se nom se recibe por post

// por se imos mostrar máis, poderiamse engadir [cp_concello1 => nome, cp_concello2 => nome2, ...]
$array_concellos_mostrar = [$cp_concello => $concellos[$cp_concello]];


// ------- Ficheiros de procesos electorais e variables associadas empregadas
$procesos_electorais = array(
	'Congreso Novembro 2019' => '../datos/eleccions_mesas/congreso/datos_congreso_2019-11_galiza.csv',
	'Congreso Abril 2019' => '../datos/eleccions_mesas/congreso/datos_congreso_2019-04_galiza.csv',
	'Congreso Xuño 2016' => '../datos/eleccions_mesas/congreso/datos_congreso_2016-06_galiza.csv',
	'Congreso Decembro 2015' => '../datos/eleccions_mesas/congreso/datos_congreso_2015-12_galiza.csv',
	'Congreso Novembro 2011' => '../datos/eleccions_mesas/congreso/datos_congreso_2011-11_galiza.csv',
	
	'Galegas Xullo 2020' => '../datos/eleccions_mesas/galegas/Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_Galiza.csv',
	'Galegas Setembro 2016' => '../datos/eleccions_mesas/galegas/ELECCIONS_PARLAMENTO_GALICIA_2016_MESAS.csv',
	'Galegas Outubro 2012' => '../datos/eleccions_mesas/galegas/ELECCIONS_PARLAMENTO_GALICIA_2012_MESAS.csv',
	'Galegas Outubro 2005' => '../datos/eleccions_mesas/galegas/ELECCIONS_PARLAMENTO_GALICIA_2005_MESAS.csv',

	'Municipais Maio 2019' => '../datos/eleccions_mesas/municipais/datos_municipais_2019-05_galiza.csv',
	'Municipais Maio 2015' => '../datos/eleccions_mesas/municipais/datos_municipais_2015-05_galiza.csv',
	'Municipais Maio 2011' => '../datos/eleccions_mesas/municipais/datos_municipais_2011-05_galiza.csv',

	'Europeas Maio 2019' => '../datos/eleccions_mesas/europeas/datos_europeas_2019-05_galiza.csv',
	'Europeas Maio 2014' => '../datos/eleccions_mesas/europeas/datos_europeas_2014-05_galiza.csv',
	'Europeas Xuño 2009' => '../datos/eleccions_mesas/europeas/datos_europeas_2009-06_galiza.csv',
);

// para fazer os optgroup
$procesos_electorais_agrupados = [];
$tipo = '';
foreach($procesos_electorais as $k => $v) {
	list($tipo_actual, ) = explode(' ', $k);
	if($tipo != $tipo_actual) {
		$tipo = $tipo_actual;
	}
	$procesos_electorais_agrupados[$tipo][$k] = $v;
}

$eleccion_primeira = array_key_first($procesos_electorais);
$ficheiro_resultados_csv_primeiro = $procesos_electorais[$eleccion_primeira];

$eleccion_segunda = array_keys($procesos_electorais)[1];
$ficheiro_resultados_csv_segundo = $procesos_electorais[$eleccion_segunda];


// se vem ficheiro quitamos eleccion e despois o tipo de eleccion actual para discriminar a loxica de recorrido dos CVS (os do estado tenhem umha fila primeir)
if(@$_POST['ficheiro1']) {
	$eleccion_primeira =  array_search($_POST['ficheiro1'], $procesos_electorais);
	$ficheiro_resultados_csv_primeiro = $_POST['ficheiro1'];
}
if(@$_POST['ficheiro2']) {
	$eleccion_segunda =  array_search($_POST['ficheiro2'], $procesos_electorais);
	$ficheiro_resultados_csv_segundo = $_POST['ficheiro2'];
}


// se nom existe o ficheiro segue executandose e xera um log de 63M em menos de 10 segundos. Asi que melhor paralo antes
if(file_exists($ficheiro_resultados_csv_primeiro) == false) {
	die("Non se atopa ficheiro $ficheiro_resultados_csv_primeiro. Revisar!!");
}
if(file_exists($ficheiro_resultados_csv_segundo) == false) {
	die("Non se atopa ficheiro $ficheiro_resultados_csv_segundo. Revisar!!");
}

// para discriminar ficheiros de galegas vs. o resto (que tenhem o mesmo formato por sair todos do ministerio)
$datos_eleccions1 = explode(" ", $eleccion_primeira);
$datos_eleccions2 = explode(" ", $eleccion_segunda);
$tipo_eleccion1 = $datos_eleccions1[0];
$tipo_eleccion2 = $datos_eleccions2[0];

// ano de eleccion
$mes_ano_eleccion1 = $datos_eleccions1[1]." ".$datos_eleccions1[2];
$mes_ano_eleccion2 = $datos_eleccions2[1]." ".$datos_eleccions2[2];




// ------ Datos electorais
// clase externa de funcions de apoio
$datosElectoraisObj1 = new DatosElectorais($ficheiro_resultados_csv_primeiro);
$datosElectoraisObj2 = new DatosElectorais($ficheiro_resultados_csv_segundo);

$cps_concellos_mostrar = array_keys($array_concellos_mostrar);

// discrimino, se som galegas tenho que empregar o metodo "construeDatosElectoraisGalegasDatasetsAbertos", se nom "construeDatosElectoraisFicheirosGobEsParseados"
if($tipo_eleccion1 == 'Galegas') {
	$datos_electorais1 = $datosElectoraisObj1->construeDatosElectoraisGalegasDatasetsAbertos($cps_concellos_mostrar);
}
else {
	$datos_electorais1 = $datosElectoraisObj1->construeDatosElectoraisFicheirosGobEsParseados(false, $cps_concellos_mostrar);
}

if($tipo_eleccion2 == 'Galegas') {
	$datos_electorais2 = $datosElectoraisObj2->construeDatosElectoraisGalegasDatasetsAbertos($cps_concellos_mostrar);
}
else {
	$datos_electorais2 = $datosElectoraisObj2->construeDatosElectoraisFicheirosGobEsParseados(false, $cps_concellos_mostrar);
}

$arrPartidos1 = $datosElectoraisObj1->getPartidosPoliticos();
$arrPartidos2 = $datosElectoraisObj2->getPartidosPoliticos();


// --- datos de partidos
// limpo datos antes de tratalos, pois nom me servem
unset($datos_electorais1['mesas']);
unset($datos_electorais1['tantos_por_cento']);
unset($datos_electorais2['mesas']);
unset($datos_electorais2['tantos_por_cento']);


$datosElectoraisPartidosObj1 = new Partidos($datos_electorais1, $arrPartidos1);
$datos_electorais_de_partidos1 = $datosElectoraisPartidosObj1->getDatosElectoraisPartidos();
$datosElectoraisPartidosObj2 = new Partidos($datos_electorais2, $arrPartidos2);
$datos_electorais_de_partidos2 = $datosElectoraisPartidosObj2->getDatosElectoraisPartidos();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BNG - Comparador de votos entre eleccións</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet-src.js" crossorigin=""></script>
	<script src="../js/spin/dist/spin.min.js"></script>
	<script src="../js/leaflet.spin.min.js"></script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">

	<script src="https://code.highcharts.com/highcharts.js"></script>
	<script src="https://code.highcharts.com/modules/accessibility.js"></script>


    <style type="text/css">
        html, body { width: 100%; height: 100%; margin: 0; }
        #map, #container { width: 49.5%; height: 100%; }
        #map { float: left; }
        #container { float: right; }
        #container .map { width: 100%; height: 100%; }
        .info { padding: 6px 8px; font: 14px/16px Arial, Helvetica, sans-serif; background: white; background: rgba(255,255,255,0.8); box-shadow: 0 0 15px rgba(0,0,0,0.2); border-radius: 5px; } .info h4 { margin: 0 0 5px; color: #777; }
        .infoA { padding: 6px 8px; font: 14px/16px Arial, Helvetica, sans-serif; background: white; background: rgba(255,255,255,0.8); box-shadow: 0 0 15px rgba(0,0,0,0.2); border-radius: 5px; } .info h4 { margin: 0 0 5px; color: #777; }

		.info h2 { margin: 0 0 5px; font-size: 1.5rem; }
		.info h4 { margin: 0 0 5px; color: #777; font-size: 1rem; font-weight: bold; }

		.infoA h2 { margin: 0 0 5px; font-size: 1.5rem; }
		.infoA h4 { margin: 0 0 5px; color: #777; font-size: 1rem; font-weight: bold; }
		
    </style>

	<script>
	// variable global para empregar em "style", em cada featureGroup, 
	//		debido a que style em L.geoJSON() nom admite parametros, ainda que em stackoverflow aparece algumha resposta e funciona, neste caso nom me vai
	var posicionPartido = 0;
	var posicionPartidoA = 0;
	</script>
</head>

<body>
<!-- navbar de bootstrap -->
<nav class="navbar navbar-expand-lg bg-light navbar-dark bg-dark">
	  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
		<span class="navbar-toggler-icon"></span>
	  </button>
	  <div class="collapse navbar-collapse" id="navbarNav">
		<ul class="navbar-nav">
			<li class="nav-item"><a class="nav-link" href="../index.html">Menu principal</a></li>
			<li class="nav-item"><a class="nav-link" href="index.php">Votos a partidos por Comarcas e distritos</a></li>
			<li class="nav-item"><a class="nav-link active" href="multiple.php">Comparador entre partidos para Concellos</a></li>

			<!-- form de seleccion dos mapas -->
			<li class="nav-item">
				<form name="form1" method="post">
					<?php $nome_concello = $concellos[$cp_concello]; ?>
					<input placeholder="Concello" list="concellos" name="concello"  value="<?php echo $nome_concello; ?>"/>
					<datalist id="concellos">
						<?php
						foreach($concellos as $id => $concello) {
							echo '<option value="'.$concello.'"></option>';
						}
						?>
					</datalist>

					<select name="ficheiro1">
						<?php
						foreach($procesos_electorais_agrupados as $opt => $procesos) {
							echo '<optgroup label="'.$opt.'">';
							foreach($procesos as $elec => $ficheiro) {
								$selected = '';
								if($ficheiro == $ficheiro_resultados_csv_primeiro) $selected = ' selected';
								echo '<option value="'.$ficheiro.'" '.$selected.'>'.$elec.'</option>';
							}
							echo "</optgroup>";
						}
						?>
					</select>


					<select name="ficheiro2">
						<?php
						foreach($procesos_electorais_agrupados as $opt => $procesos) {
							echo '<optgroup label="'.$opt.'">';
							foreach($procesos as $elec => $ficheiro) {
								$selected = '';
								if($ficheiro == $ficheiro_resultados_csv_segundo) $selected = ' selected';
								echo '<option value="'.$ficheiro.'" '.$selected.'>'.$elec.'</option>';
							}
							echo "</optgroup>";
						}
						?>
					</select>

					<input type="submit" value="Comparar" />
				</form>
			</li>
		</ul>
	  </div>
  </nav>


    <div id="map"></div>
    <div id="container">
        <div id="mapA" class="map"></div>
    </div>

	<div id="highcharts1" class="info" style="position: fixed; left: 50%;  bottom: 0px;  transform: translate(-50%, 0);  margin: 0 auto; z-index: 999; height:280px;"></div>

    <script src="../js/L.Map.Sync.js"></script>

	<script>
	// para highcharts, tenho que meter dos dous mapas, que podem variar
	var partidosHC = <?php echo json_encode(array_values(array_unique(array_merge(array_keys($datosElectoraisPartidosObj1->getPartidosNosPrimeirosPostos()),  array_keys($datosElectoraisPartidosObj2->getPartidosNosPrimeirosPostos())))) , JSON_INVALID_UTF8_IGNORE); ?>;

	// para highcharts, array de objects com keys: partido, x (votos partido), y (porcentagem), color (cor), tenho que meter os datos dos dous mapas aqui
	// ver info.update() e infoA.update().
	// Vem num array coas keys da primeira e segunda eleccion.
	var votos_porcentaxe_e_cor = {};

	function HC() {
		// data para Highcharts
		var dataHC2 = {"<?php echo $mes_ano_eleccion1;?>":[], "<?php echo $mes_ano_eleccion2;?>":[]};

		for(p in votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>']) {
			dataHC2['<?php echo $mes_ano_eleccion1;?>'].push(votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>'][p]);
		}
		for(p in votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>']) {
			dataHC2['<?php echo $mes_ano_eleccion2;?>'].push(votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>'][p]);
		}


		Highcharts.chart('highcharts1', {
			chart: {
				type: 'bar'
			},
			title: {
				text: '<?php echo $eleccion_primeira." - ".$eleccion_segunda;?>'
			},
			xAxis: {
				categories: partidosHC
			},
			yAxis: {
				title: false
			},
			tooltip: {
				valueSuffix: '%'
			},
			plotOptions: {
				bar: {
					dataLabels: {
						enabled: true,
						// format: '{y}%'
						format: '{y}% ({point.votos} votos)'
					}
				},
				series: {
					animation: false, 
				}
			},

			series: [{
				name: '<?php echo $mes_ano_eleccion1;?>',
				data: dataHC2['<?php echo $mes_ano_eleccion1;?>']
			}, {
				name: '<?php echo $mes_ano_eleccion2;?>',
				data: dataHC2['<?php echo $mes_ano_eleccion2;?>']
			}]
		});
	}
	</script>

    <script type="text/javascript">
	<?php
		// por performance nom cargo todos os datos do json e engado datos, como fago normalmente.
		// No seu lugar, cargo os layers asincronamente (tarda muito menos) e creo um JS cos datos electorais para usalo em 'style', no 'info', etc.
		$arrParaJS1 = $arrParaJS2 = [];
		foreach($datos_electorais_de_partidos1 as $idProvMunDistSec => $v) {
			if($idProvMunDistSec == 'mesas') continue;
			
			$id_cusec = str_replace('|', '', str_replace('-', '', $idProvMunDistSec));
			//$arrParaJS1[$id_cusec] = array_slice($v, 0, $numeroPartidosMostrar);
			// ---- como em highcharts same máis dos $numeroPartidosMostrar (porque sumamse os das dúas eleccións), 
			// ---- colho todos os datos para que aparezam ainda que nom esteam entre os N_PRIMEIROS.
			// ---- Queda um array tocho pero...
			$arrParaJS1[$id_cusec] = $v;


			$arrParaJS1[$id_cusec]['votos_totais'] = $datos_electorais1[$idProvMunDistSec]['votos_totais'];
			$arrParaJS1[$id_cusec]['votos_BNG'] = $datos_electorais1[$idProvMunDistSec]['votos_BNG'];
			$arrParaJS1[$id_cusec]['porcentaxe'] = $datos_electorais1[$idProvMunDistSec]['porcentaxe'];
		}
		foreach($datos_electorais_de_partidos2 as $idProvMunDistSec => $v) {
			if($idProvMunDistSec == 'mesas') continue;
			
			$id_cusec = str_replace('|', '', str_replace('-', '', $idProvMunDistSec));
			// $arrParaJS2[$id_cusec] = array_slice($v, 0, $numeroPartidosMostrar);
			$arrParaJS2[$id_cusec] = $v;		// -- idem que arriba

			$arrParaJS2[$id_cusec]['votos_totais'] = $datos_electorais2[$idProvMunDistSec]['votos_totais'];
			$arrParaJS2[$id_cusec]['votos_BNG'] = $datos_electorais2[$idProvMunDistSec]['votos_BNG'];
			$arrParaJS2[$id_cusec]['porcentaxe'] = $datos_electorais2[$idProvMunDistSec]['porcentaxe'];
		}
		?>
		var datos_electorais_js1 = <?php print(json_encode($arrParaJS1, JSON_INVALID_UTF8_IGNORE)); ?>;
		var datos_electorais_js2 = <?php print(json_encode($arrParaJS2, JSON_INVALID_UTF8_IGNORE)); ?>;



		var center = [42.90919, -8.5230];

        var stamenOptions = {
            attribution:
                '<a href="https://openstreetmap.org/copyright">' +
                '&copy; OpenStreetmap and Contributors</a>',
            minZoom: 9,
            maxZoom: 15
        };

        var openstreetmap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', stamenOptions);
        var openstreetmapA = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', stamenOptions);

        var map = L.map('map', {
            layers: [openstreetmap],
            center: center,
            zoom: 12,
			zoomControl: false
        });

        var mapA = L.map('mapA', {
            layers: [openstreetmapA],
            center: center,
            zoom: 12,
            zoomControl: false
        });

        map.sync(mapA, {syncCursor: true});
        mapA.sync(map, {syncCursor: true});


		// cores e estilos
		function getColor(partido) {
			<?php echo "cores_e_graos = ".json_encode(Partidos::getCoresPartidos()).";";?>
			return cores_e_graos[partido];
		}

		function style(feature) {
			partido = null;

			cusec = feature.properties['CUSEC'];
			if(datos_electorais_js1[cusec]){
				partido = Object.keys(datos_electorais_js1[cusec])[posicionPartido]; 	// primeiro partido
			}

			return {
				fillColor: getColor(partido),
				weight: 2,
				opacity: 1,
				color: 'blue',
				dashArray: '3',
				fillOpacity: 0.7
			};
		}
		function styleA(feature) {
			partido = null;

			cusec = feature.properties['CUSEC'];
			if(datos_electorais_js2[cusec]){
				partido = Object.keys(datos_electorais_js2[cusec])[posicionPartidoA]; 	// primeiro partido
			}

			return {
				fillColor: getColor(partido),
				weight: 2,
				opacity: 1,
				color: 'blue',
				dashArray: '3',
				fillOpacity: 0.7
			};
		}


		function highlightFeature(e, layerLink) {
			var layer;
			if(e == null) {
				layer = layerLink;
			}
			else {
				layer = e.target;
			}

			layer.setStyle({
				weight: 5,
				color: '#666',
				dashArray: '',
				fillOpacity: 0.7
			});

			if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
				layer.bringToFront();
			}

			infoA.update(layer.feature.properties);
			info.update(layer.feature.properties);
		}
        // o metodo mais rapido é duplicar o código para mapA (para o segundo)
		function highlightFeature_mapA(e) {
			var layer = e.target;

			layer.setStyle({
				weight: 5,
				color: '#666',
				dashArray: '',
				fillOpacity: 0.7
			});

			if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
				layer.bringToFront();
			}

			infoA.update(layer.feature.properties);
			info.update(layer.feature.properties);
		}



		function resetHighlightFeature(e) {
			var layer = e.target;
			layer.setStyle(style(layer.feature));
			info.update();
			infoA.update();
		}	
		
		function resetHighlightFeatureA(e) {
			var layer = e.target;
			layer.setStyle(styleA(layer.feature));
			infoA.update();
			info.update();
		}			

		function zoomToFeature(e) {
			map.fitBounds(e.target.getBounds());
		}		

		function onEachFeature(feature, layer) {
			// de id ao layer damoslhe um valor conhecido para depois poder fazer um .fire('click');
			layer._leaflet_id = 'concello_distrito_layer1' + feature.properties['CUSEC'] + '_' + posicionPartido;

			layer.on({
				mouseover: highlightFeature,
				mouseout: resetHighlightFeature,
				click: zoomToFeature
			});
		}
		function onEachFeature_mapA(feature, layer) {
			// de id ao layer damoslhe um valor conhecido para depois poder fazer um .fire('click');
			layer._leaflet_id = 'concello_distrito_layer2' + feature.properties['CUSEC'] + '_' + posicionPartidoA;

			layer.on({
				mouseover: highlightFeature_mapA,
				mouseout: resetHighlightFeatureA,
				click: zoomToFeature
			});
		}        
		//--------
			// array temporal para engadir a layerGroup e este a layerControl
			var arrLayersPartidos = [];
			// conservar layers para poder fazer so visible a primeira ao cargar o mapa (carga todas e deixa por riba a última engadida)
			var arrLayers = [];


			concellos_por_id = <?php echo json_encode($array_concellos_mostrar); ?>;
			var layers = [];

			map.spin(true);
			mapA.spin(true);

			fetch("../datos/mapas/Mapas_censais_Galiza_2022.json")
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				// imos engadir ao mapa os $numeroPartidosMostrar layers
				var layerControl1 = L.control.layers(null, null,{collapsed: false, sortLayers: true}).addTo(map);
				var layerControl2 = L.control.layers(null, null,{collapsed: false, sortLayers: true}).addTo(mapA);

				// ------- mapa
				for(id in concellos_por_id) {
					// fago um bucle para ir engadinddo layers, cambiando a cor empregando posicionPartido para que o colha style()
					for(i=0; i < <?php echo $numeroPartidosMostrar;?>; i++) {
						posicionPartido=i;
						arrLayersPartidos = [];

						var featGroups = L.geoJSON(data, 
							{
								style: style, 
								onEachFeature: onEachFeature,
								filter: function(feature, layer) {
									return (feature.properties['CUMUN'] == id);
								}
							}
						);
						arrLayersPartidos.push(featGroups);


						// engadoo dentro do for para colher todos os layers
						// creo "array[CODIGO_MESA] = layer" porque em featGroups venhem por orde segundo estám no ficheiro .json, descolocados
						const layers = featGroups.getLayers();
						for (let a = 0; a < layers.length; a++) {
							arrLayers[layers[a]._leaflet_id.replace('concello_distrito_layer1', '')] = layers[a];
						}


						// para fazer variable dinamica, empregamos o context
						// @see https://stackoverflow.com/a/28063322/4512704
						// (https://stackoverflow.com/questions/5117127/use-dynamic-variable-names-in-javascript)
						this['layers' + i] = L.layerGroup(arrLayersPartidos).addTo(map);

						layerControl1.addBaseLayer(this['layers' + i], 'Posicion '+(i+1));

						// na carga inicial so se amosa a primeira capa
						if(i > 0) map.removeLayer(this['layers' + i]);
					}


					// centramos mapa (abonda cum so, nom fam falha os dous layers)
					if(featGroups.getBounds()) {
						map.fitBounds(featGroups.getBounds());
					}

					posicionPartido = 0;
				}

				// ------------ mapaA
				for(id in concellos_por_id) {
					// fago um bucle para ir engadinddo layers, cambiando a cor empregando posicionPartido para que o colha style()
					for(i=0; i < <?php echo $numeroPartidosMostrar;?>; i++) {
						posicionPartidoA=i;
						arrLayersPartidos = [];

						arrLayers = [];

						var featGroupsA = L.geoJSON(data, 
							{
								style: styleA, 
								onEachFeature: onEachFeature_mapA,
								filter: function(feature, layer) {
									return (feature.properties['CUMUN'] == id);
								}
							}
						);
						arrLayersPartidos.push(featGroupsA);


						// engadoo dentro do for para colher todos os layers
						// creo "array[CODIGO_MESA] = layer" porque em featGroups venhem por orde segundo estám no ficheiro .json, descolocados
						const layersA = featGroupsA.getLayers();
						for (let a = 0; a < layersA.length; a++) {
							arrLayers[layersA[a]._leaflet_id.replace('concello_distrito_layer1', '')] = layersA[a];
						}

						// para fazer variable dinamica, empregamos o context
						// @see https://stackoverflow.com/a/28063322/4512704
						// (https://stackoverflow.com/questions/5117127/use-dynamic-variable-names-in-javascript)
						this['layersA' + i] = L.layerGroup(arrLayersPartidos).addTo(mapA);

						layerControl2.addBaseLayer(this['layersA' + i], 'Posicion '+(i+1));

						// na carga inicial so se amosa a primeira capa
						if(i > 0) mapA.removeLayer(this['layersA' + i]);
					}


					// centramos mapa (abonda cum so, nom fam falha os dous layers)
					if(featGroupsA.getBounds()) {
						mapA.fitBounds(featGroupsA.getBounds());
					}

					posicionPartidoA = 0;
				}

				map.spin(false);
				mapA.spin(false);				
			});

			// actualiza posicionPartido para saber que estilo tem que ter o layer
			map.on('baselayerchange', function (e) {
				posicionPartido = e.name.charAt(e.name.length-1)-1;
			});
			mapA.on('baselayerchange', function (e) {
				posicionPartidoA = e.name.charAt(e.name.length-1)-1;
			});


			//--- info
			var info = L.control({ position: 'topleft'});

			info.onAdd = function (map) {
				this._div = L.DomUtil.create('div', 'info');
				this.update();
				return this._div;
			};

			info.update = function (props) {
				texto = 'Sen datos';

				if(props) {
					cusec = props['CUSEC'];

					texto = '<b>' + props['name'] + '</b>';
					texto = texto + '<br />Sección: '+props['CDIS'] +'-'+ props['CSEC'] +'<br /><br />';

					if(datos_electorais_js1[cusec]){
						votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>'] = [];
						votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>'] = [];

						// em lugar de recorrer datos_electorais_js1/datos_electorais_js2, que so tenhem os 4 partidos de cadansua eleccion, tenho que recorrer
						// "partidosHC", onde estam todos os partidos das 2 eleccions
						//---------
						for(k in partidosHC) {
							partido = partidosHC[k];
							partidoOut = partido;
							if(partidoOut.length > 27) partidoOut = partido.substring(0, 26) + '...';

							if(datos_electorais_js1[cusec][partido]) {
								porcentaxe = datos_electorais_js1[cusec][partido] * 100 / datos_electorais_js1[cusec]['votos_totais'];
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>'].push({votos: parseFloat(datos_electorais_js1[cusec][partido]), y: parseFloat(porcentaxe.toFixed(2)), color: getColor(partido)});
								
								texto = texto +partidoOut+': '+datos_electorais_js1[cusec][partido]+' votos (' + porcentaxe.toFixed(2) +'%)<br/>';
							}
							else {
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>'].push({votos: undefined, y: undefined, color: getColor(partido)});
							}

							if(datos_electorais_js2[cusec][partido]) {
								porcentaxe = datos_electorais_js2[cusec][partido] * 100 / datos_electorais_js2[cusec]['votos_totais'];
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>'].push({votos: parseFloat(datos_electorais_js2[cusec][partido]), y: parseFloat(porcentaxe.toFixed(2)), color: getColor(partido)});
							}
							else {
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>'].push({votos: undefined, y: undefined, color: getColor(partido)});
							}
						}

						texto = texto + '<br/>';

						if(datos_electorais_js1[cusec]['porcentaxe']) texto = texto + '<br />Porcentaxe BNG: <b>'+datos_electorais_js1[cusec]['porcentaxe']+'%</b> <br/><br/>'
						if(datos_electorais_js1[cusec]['votos_totais']) texto = texto +'Votos totais: ' +datos_electorais_js1[cusec]['votos_totais'] + ' - Votos BNG: ' + datos_electorais_js1[cusec]['votos_BNG'];
					}
				}

				HC();

				this._div.innerHTML = '<h2><?php echo $eleccion_primeira;?></h2><br />'
					+  texto;
			};
			info.addTo(map);


			//--- quadro de infor PARA A
			var infoA = L.control({ position: 'topleft'});

			infoA.onAdd = function (map) {
				this._div = L.DomUtil.create('div', 'infoA');
				this.update();
				return this._div;
			};

			infoA.update = function (props) {
				texto = 'Sen datos';

				if(props) {
					cusec = props['CUSEC'];

					texto = '<b>' + props['name'] + '</b>';
					texto = texto + '<br />Sección: '+props['CDIS'] +'-'+ props['CSEC'] +'<br /><br />';

					if(datos_electorais_js2[cusec]){
						votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>'] = [];
						votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>'] = [];

						// em lugar de recorrer datos_electorais_js1/datos_electorais_js2, que so tenhem os 4 partidos de cadansua eleccion, tenho que recorrer
						// "partidosHC", onde estam todos os partidos das 2 eleccions
						//---------
						for(k in partidosHC) {
							partido = partidosHC[k];
							partidoOut = partido;
							if(partidoOut.length > 27) partidoOut = partido.substring(0, 26) + '...';

							if(datos_electorais_js1[cusec][partido]) {
								porcentaxe = datos_electorais_js1[cusec][partido] * 100 / datos_electorais_js1[cusec]['votos_totais'];
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>'].push({votos: parseFloat(datos_electorais_js1[cusec][partido]), y: parseFloat(porcentaxe.toFixed(2)), color: getColor(partido)});
							}
							else {
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion1;?>'].push({votos: undefined, y: undefined, color: getColor(partido)});
							}

							if(datos_electorais_js2[cusec][partido]) {
								porcentaxe = datos_electorais_js2[cusec][partido] * 100 / datos_electorais_js2[cusec]['votos_totais'];
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>'].push({votos: parseFloat(datos_electorais_js2[cusec][partido]), y: parseFloat(porcentaxe.toFixed(2)), color: getColor(partido)});

								texto = texto +partidoOut+': '+datos_electorais_js2[cusec][partido]+' votos (' + porcentaxe.toFixed(2) +'%)<br/>';
							}
							else {
								votos_porcentaxe_e_cor['<?php echo $mes_ano_eleccion2;?>'].push({votos: undefined, y: undefined, color: getColor(partido)});
							}
						}
						texto = texto + '<br/>';

						if(datos_electorais_js2[cusec]['porcentaxe']) texto = texto + '<br />Porcentaxe BNG: <b>'+datos_electorais_js2[cusec]['porcentaxe']+'%</b> <br/><br/>'
						if(datos_electorais_js2[cusec]['votos_totais']) texto = texto +'Votos totais: ' +datos_electorais_js2[cusec]['votos_totais'] + ' - Votos BNG: ' + datos_electorais_js2[cusec]['votos_BNG']
					}
				}

				this._div.innerHTML = '<h2><?php echo $eleccion_segunda;?></h2><br />'
					+  texto;
			};
			infoA.addTo(mapA);
    </script>

</body>
</html>

