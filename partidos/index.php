<?php
// mapas de partidos por sectores em Galiza
//
// Alexandre Espinosa Menor - aemenor@gmail.com
//
require_once('../class/datosElectorais.php');
require_once('../class/ComarcasClass.php');
require_once('../class/Partidos.php');



$comarcaPost = $_REQUEST['comarca'];

$concelloPost = $_REQUEST['concello'];

$ids_concellos = [];
if(isset($comarcaPost) && $comarcaPost != '') {
	$concellos_comarca = ComarcasClass::concellosPorComarca($comarcaPost);

	// hack: necesitam ser strings para que funcione o Array.includes() de JS
	$ids_concellos = array_map('strval', array_keys($concellos_comarca));
	$ids_concellos = $ids_concellos == null ? [] : $ids_concellos;
}

if(isset($concelloPost) && $concelloPost != '') {
	$id_concello = array_search($concelloPost, ComarcasClass::concellosPorComarca($comarcaPost));

	// hack: necesitam ser strings para que funcione o Array.includes() de JS
	$ids_concellos = [strval($id_concello)];
}


// tinhamos que consultar com cada concelho em comarcaDeConcello(), e som 313 petizons. Como o fazemos dúas veces
// seríam 600+ == melhor um array e fazer a consulta umha vez
$ids_concellosConComarca = ComarcasClass::comarcasPorConcellos();


// ficheiros de datos electorais, umha eleicçom por ficheiro
// toda a lóxica metida é para tentar tirar contra estes ficheiros, extraidos, practicamente sem tocar, do INE e do portal de opendata do estado e da Xunta
$procesos_electorais = array(
	'Congreso Novembro 2019' => '../datos/eleccions_mesas/congreso/datos_congreso_2019-11_galiza.csv',
	'Congreso Abril 2019' => '../datos/eleccions_mesas/congreso/datos_congreso_2019-04_galiza.csv',
	'Congreso Xuño 2016' => '../datos/eleccions_mesas/congreso/datos_congreso_2016-06_galiza.csv',
	'Congreso Decembro 2015' => '../datos/eleccions_mesas/congreso/datos_congreso_2015-12_galiza.csv',
	'Congreso Novembro 2011' => '../datos/eleccions_mesas/congreso/datos_congreso_2011-11_galiza.csv',
	
	'Galegas Xullo 2020' => '../datos/eleccions_mesas/galegas/Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_Galiza.csv',
	'Galegas Setembro 2016' => '../datos/eleccions_mesas/galegas/ELECCIONS_PARLAMENTO_GALICIA_2016_MESAS.csv',
	'Galegas Outubro 2012' => '../datos/eleccions_mesas/galegas/ELECCIONS_PARLAMENTO_GALICIA_2012_MESAS.csv',
	'Galegas Marzo 2009' => '../datos/eleccions_mesas/galegas/ELECCIONS_PARLAMENTO_GALICIA_2009_MESAS.csv',
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


$eleccion = array_key_first($procesos_electorais);
$ficheiro_resultados_csv = $procesos_electorais[$eleccion];

// se vem ficheiro quitamos eleccion e despois o tipo de eleccion actual para discriminar a loxica de recorrido dos CSV
if(@$_REQUEST['eleccion']) {
	$eleccion =  $_REQUEST['eleccion'];
	$ficheiro_resultados_csv = $procesos_electorais[$eleccion];

	// se o ficheiro é incorrecto, colhemos os primeiros de novo
	if($eleccion == '') {
		$eleccion = array_key_first($procesos_electorais);
		$ficheiro_resultados_csv = $procesos_electorais[$eleccion];
	}
}

$tipo_eleccion = explode(" ", $eleccion)[0];


// clase externa de funcions de apoio
$datosElectoraisObj = new DatosElectorais($ficheiro_resultados_csv);
if($tipo_eleccion == 'Galegas') {
	$datos_electorais =  $datosElectoraisObj->construeDatosElectoraisGalegasDatasetsAbertos($ids_concellos);
}
else {
	$datos_electorais =  $datosElectoraisObj->construeDatosElectoraisFicheirosGobEsParseados(false, $ids_concellos);
}


$arrPartidos = $datosElectoraisObj->getPartidosPoliticos();


// --- datos de partidos
// limpo datos antes de tratalos, pois nom me servem
unset($datos_electorais['mesas']);
unset($datos_electorais['tantos_por_cento']);

$datosElectoraisPartidosObj = new Partidos($datos_electorais, $arrPartidos);
$datos_electorais_de_partidos = $datosElectoraisPartidosObj->getDatosElectoraisPartidos();
?>
<!DOCTYPE html>
<html>
<head>
	<title>BNG - Votos por partidos por Comarcas</title>
	<meta charset="utf-8" />

	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.2/dist/leaflet.css" />

	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">	
	<script type="text/javascript" language="javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
	<script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>

	<script src="https://unpkg.com/leaflet@1.9.2/dist/leaflet.js"></script>
	<script src="../js/spin/dist/spin.min.js"></script>
	<script src="../js/leaflet.spin.min.js"></script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">

	<script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
	<link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />

	<script src="https://code.highcharts.com/highcharts.js"></script>
	<script src="https://code.highcharts.com/modules/accessibility.js"></script>

	<style>
		.info { padding: 6px 8px; font: 14px/16px Arial, Helvetica, sans-serif; background: white; background: rgba(255,255,255,0.8); box-shadow: 0 0 15px rgba(0,0,0,0.2); border-radius: 5px; }
		.info h2 { margin: 0 0 5px; font-size: 1.5rem; }
		.info h4 { margin: 0 0 5px; color: #777; font-size: 1rem; font-weight: bold; }
		.legend { text-align: left; line-height: 18px; color: #555; }
		.legend i { width: 18px; height: 18px; float: left; margin-right: 8px; opacity: 0.7; }

		.funcionamento { padding: 10px 20px; font: 14px/16px Arial, Helvetica, sans-serif; }	
		
		#map {
			height: 800px;
			position: relative;
			margin:0;
			padding:0; 
		}
	</style>

	<script>
	// variable global para empregar em "style", em cada featureGroup, 
	//		debido a que style em L.geoJSON() nom admite parametros, ainda que em stackoverflow aparece algumha resposta e funciona, neste caso nom me vai
	var posicionPartido = 0;

	function clickLayer(id_layer) {
		eval('map._layers.concello_distrito_layer' + id_layer + '_' + posicionPartido + '.fire("click");'); 
		eval('map._layers.concello_distrito_layer' + id_layer + '_' + posicionPartido + '.fire("mouseover");'); 
	}
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
	  	<li class="nav-item"><a class="nav-link active" href="index.php">Votos a partidos por Comarcas e distritos</a></li>
		<li class="nav-item"><a class="nav-link" href="multiple.php">(ToDo) Comparador entre partidos para Concellos</a></li>
      </ul>
    </div>
</nav>


<!-- rows de bootstrap -->
<div class="row">
<div class="col-8 themed-grid-col" id="map"></div>
      <div class="col-4 themed-grid-col funcionamento">
		<p>
			<form name="form1" method="post" style="display: inline;" action="<?php echo parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?>">
					<select name="comarca" onChange="document.form1.submit();">
						<option>Galiza</option>
						<?php
						$comarcas_disponhibles =  ComarcasClass::comarcasDisponhiblesPorProvincia();
						foreach($comarcas_disponhibles as $provincia => $comarcas) {
								echo '<optgroup label="'.$provincia.'">';

								foreach($comarcas as $comarca) {
									$selected = '';
									if($comarca == $_REQUEST['comarca']) $selected = ' selected';
									echo '<option value="'.$comarca.'" '.$selected.'>'.$comarca.'</option>';
								}
								echo "</optgroup>";
						}
						?>
					</select>
					<?php if($_REQUEST['eleccion']) echo '<input type=hidden name=eleccion value="'.$_REQUEST['eleccion'].'" />'?>
				</form>

				<?php
				if($comarcaPost) { ?>
					<form name="form3" method="post" style="display: inline;" action="<?php echo parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?>">
						<input type="hidden" name="comarca" value="<?php echo $comarcaPost; ?>" />
						<?php if($_REQUEST['eleccion']) echo '<input type=hidden name=eleccion value="'.$_REQUEST['eleccion'].'" />'?>

						<select name="concello" onChange="document.form3.submit();">
							<option value="">Todos os concellos</option>
						<?php
							$concellos_disponhibles =  ComarcasClass::concellosPorComarca($comarcaPost);
							foreach($concellos_disponhibles as $id => $concello) {
								$selected = '';
								if($concello == $concelloPost) $selected = ' selected';
								echo '<option value="'.$concello.'" '.$selected.'>'.$concello.'</option>';
							}
							?>
						</select>
					</form>
				<?php } ?>


		</p>

		<p>
			<form name="form2" method="post" action="<?php echo parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?>">
				<select name="eleccion" onChange="document.form2.submit();">
						<?php
						foreach($procesos_electorais_agrupados as $opt => $procesos) {
							echo '<optgroup label="'.$opt.'">';
							foreach($procesos as $elec => $ficheiro) {
								$selected = '';
								if($elec == $_REQUEST['eleccion']) $selected = ' selected';
								echo '<option value="'.$elec.'" '.$selected.'>'.$elec.'</option>';
							}
						echo "</optgroup>";
					}
					?>
					
				</select>
				<?php if($_REQUEST['comarca']) echo '<input type=hidden name=comarca value="'.$_REQUEST['comarca'].'" />'?>
				<?php if($_REQUEST['concello']) echo '<input type=hidden name=concello value="'.$_REQUEST['concello'].'" />'?>
			</form>
		</p>

		<div id="container" style="width:100%; height:200px; display: none;"></div>

		<p>
		<table id="taboa_datos" class="display" style="margin-left: auto; margin-right: 0;">
				<thead><tr><th>Concello e secci&oacute;n</th><th>Comarca</th> <th>1&deg</th> <th>2&deg;</th> <th>3&deg;</th> <th>4&deg;</th></tr></thead>
				<tbody>
				<?php
				foreach($datos_electorais as $idProvMunDistSec => $v) {
						// cambiamos o formato '1-001' por '01001' para que coincida co CUSEC de propiedades (1500701001, por exemplo)
						list($id_concello, $seccion) = explode('|', $idProvMunDistSec);
						$id_cusec = str_replace('|', '', str_replace('-', '', $idProvMunDistSec));

						$concello = $v[3];
						if($ficheiro_resultados_csv == '../datos/eleccions_mesas/galegas/Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_Galiza.csv') {
							$concello = $v[2];
						}

						$porcentaxe = $v['porcentaxe'] ? $v['porcentaxe'].'%':'';
						echo "<tr>";
						echo "<td><a id='concelloID_{$id_cusec}' href='#' onClick='clickLayer({$id_cusec});'>$concello ($seccion)</a></td>";
						echo "<td>".$ids_concellosConComarca[$id_concello]."</td>";
						
						// partidos
						$contador = 0;
						foreach($datos_electorais_de_partidos[$idProvMunDistSec] as $partido => $votos) {
							if($contador == 4) break;

							echo "<td>$partido ($votos)</td>";

							$contador++;
						}
						
						echo "</tr>";
				}
				?>
				</tbody>
			</table>			
		</p>		
	</div>


	<script>
	// -- CODIGO datatables
	$(document).ready(function () {
		var table = $('#taboa_datos').DataTable({
			paging: false,
			pageLength: 150,

			ordering: true,
			info: false,

			scrollResize: true,
			scrollY: 675,
			scrollCollapse: true,
		})
	});


	<?php
		$numeroPartidosMostrar = Partidos::getNumeroPartidosAComprobarNosPrimeiros();

		// por performance nom cargo todos os datos do json e engado datos, como fago normalmente.
		// No seu lugar, cargo os layers asincronamente (tarda muito menos) e creo um JS cos datos electorais para usalo em 'style', no 'info', etc.
		$arrParaJS = [];
		foreach($datos_electorais_de_partidos as $idProvMunDistSec => $v) {
			$id_cusec = str_replace('|', '', str_replace('-', '', $idProvMunDistSec));
			$arrParaJS[$id_cusec] = array_slice($v, 0, $numeroPartidosMostrar);

			// os datos precalculados doutros mapas (totais, bng e porcentagem) ainda os conservo
			$arrParaJS[$id_cusec]['votos_totais'] = $datos_electorais[$idProvMunDistSec]['votos_totais'];
			$arrParaJS[$id_cusec]['votos_BNG'] = $datos_electorais[$idProvMunDistSec]['votos_BNG'];
			$arrParaJS[$id_cusec]['porcentaxe'] = $datos_electorais[$idProvMunDistSec]['porcentaxe'];
		}
		?>
		var datos_electorais_js = <?php print(json_encode($arrParaJS, JSON_INVALID_UTF8_IGNORE)); ?>;

		var map = L.map('map', {fullscreenControl:true}).setView([42.90919, -8.5230], 8);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution:
                '<a href="https://openstreetmap.org/copyright">' +
                '&copy; OpenStreetmap and Contributors</a>',
			maxZoom: 16,
			minZoom: 8,
		}).addTo(map);


		// ---
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

			info.update(layer.feature.properties);
		}

		// ----
		function resetHighlightFeature(e, layerLink) {
			var layer;
			if(e == null) {
				layer = layerLink;
			}
			else {
				layer = e.target;
			}

			layer.setStyle(style(layer.feature));
			info.update();
		}				

		function zoomToFeature(e) {
			map.fitBounds(e.target.getBounds());
		}		

		function onEachFeature(feature, layer) {
			// de id ao layer damoslhe um valor conhecido para depois poder fazer um .fire('click');
			layer._leaflet_id = 'concello_distrito_layer' + feature.properties['CUSEC'] + '_' + posicionPartido;

			layer.on({
				mouseover: highlightFeature,
				mouseout: resetHighlightFeature,
				click: zoomToFeature
			});
		}
		//-----------------------------
		// imos engadir ao propio mapa
		var layerControl = L.control.layers(null, null,{collapsed: false, sortLayers: true}).addTo(map);

		var ids = <?php echo json_encode($ids_concellos); ?>;

		// array temporal para engadir a layerGroup e este a layerControl
		var arrLayersPartidos = [];

		// conservar layers para poder fazer so visible a primeira ao cargar o mapa (carga todas e deixa por riba a última engadida)
		var arrLayers = [];

		// loading gif
		map.spin(true);

		// async
		fetch("../datos/mapas/Mapas_censais_Galiza_2022.json")
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				// imos engadir ao mapa os $numeroPartidosMostrar layers
				var layerControl = L.control.layers(null, null,{collapsed: false, sortLayers: true}).addTo(map);

				// fago um bucle para ir engadinddo layers, cambiando a cor empregando posicionPartido para que o colha style()
				for(i=0; i < <?php echo $numeroPartidosMostrar;?>; i++) {
					posicionPartido=i;
					arrLayersPartidos = [];

					var featGroups = L.geoJSON(data, {
						style: style, 
						onEachFeature: onEachFeature,
						filter: function(feature, layer) {
							if (typeof ids === 'undefined' || ids.length == 0) return true;

							if(ids.includes(feature.properties['CUMUN'])) return true;
						}
					});
					arrLayersPartidos.push(featGroups);


					// engadoo dentro do for para colher todos os layers
					// creo "array[CODIGO_MESA] = layer" porque em featGroups venhem por orde segundo estám no ficheiro .json, descolocados
					const layers = featGroups.getLayers();
					for (let a = 0; a < layers.length; a++) {
						arrLayers[layers[a]._leaflet_id.replace('concello_distrito_layer', '')] = layers[a];
					}


					// para fazer variable dinamica, empregamos o context
					// @see https://stackoverflow.com/a/28063322/4512704
					// (https://stackoverflow.com/questions/5117127/use-dynamic-variable-names-in-javascript)
					this['layers' + i] = L.layerGroup(arrLayersPartidos).addTo(map);

					layerControl.addBaseLayer(this['layers' + i], 'Posicion '+(i+1));

					// na carga inicial so se amosa a primeira capa
					if(i > 0) map.removeLayer(this['layers' + i]);
				}

				// centramos mapa
				if(ids.length > 0 ) {
					map.fitBounds(featGroups.getBounds());
				}

				// actualizamos posicionPartido para onmouseover
				posicionPartido = 0;

				// Bind event listeners to list items
				let el = document.querySelectorAll("a[id^='concelloID_']");
				for (let i = 0; i < el.length; i++) {
					el[i].addEventListener("mouseover", function (e) {
						const hoveredItem = e.target;
						IDconcello = hoveredItem.id.replace('concelloID_', '');
						
						highlightFeature(null, arrLayers[IDconcello + '_' + posicionPartido]);
						hoveredItem.classList.add("highlight");
					});
					el[i].addEventListener("mouseout", function (e) {
						const hoveredItem = e.target;
						IDconcello = hoveredItem.id.replace('concelloID_', '');

						resetHighlightFeature(null, arrLayers[IDconcello + '_' + posicionPartido]);
						hoveredItem.classList.remove("highlight");
					});
				}
				
				map.spin(false);			
			});

			// actualiza posicionPartido para saber que estilo tem que ter o layer
			map.on('baselayerchange', function (e) {
				posicionPartido = e.name.charAt(e.name.length-1)-1;
			});


			//--- quadro de infor
			// control that shows state info on hover
			var info = L.control({ position: 'topleft'});

			info.onAdd = function (map) {
				this._div = L.DomUtil.create('div', 'info');
				this.update();
				return this._div;
			};

			info.update = function (props) {
				// para highchart
				partidos = [];
				// array de objects com keys: x (votos partido), y (porcentagem), color (cor)
				votos_porcentaxe_e_cor = [];

				texto = '';
				if(props) {
					cusec = props['CUSEC'];

					texto = '<b>' + props['name'] + '</b>';
					texto = texto + '<br />Sección: '+props['CDIS'] +'-'+ props['CSEC'] +'<br /><br />';
					if(datos_electorais_js[cusec]){
						texto = texto + '<u>4 primeiros:</u><br />'
						for(partido in datos_electorais_js[cusec]) {
							partidoOut = partido;
							if(partidoOut.length > 27) partidoOut = partido.substring(0, 26) + '...';

							if(partido == 'votos_totais' || partido == 'votos_BNG' || partido == 'porcentaxe') continue;
							porcentaxe = datos_electorais_js[cusec][partido] * 100 / datos_electorais_js[cusec]['votos_totais'];
							texto = texto +partidoOut+': '+datos_electorais_js[cusec][partido]+' votos (' + porcentaxe.toFixed(2) +'%)<br/>'
							
							partidos.push(partidoOut);
							votos_porcentaxe_e_cor.push({votos: parseFloat(datos_electorais_js[cusec][partido]), y: parseFloat(porcentaxe.toFixed(2)), color: getColor(partido)})
							// votos_porcentaxe_e_cor.push({y: parseFloat(porcentaxe.toFixed(2)), color: getColor(partido)})
						}
						texto = texto + '<br/>';
						if(datos_electorais_js[cusec]['votos_totais']) texto = texto +'Votos totais: ' +datos_electorais_js[cusec]['votos_totais']; //+ ' - Votos BNG: ' + datos_electorais_js[cusec]['votos_BNG'] + ' (' + datos_electorais_js[cusec]['porcentaxe'] + '%)'


						// data para Highcharts
						dataHC = [];
						for(i=0; i < <?php echo $numeroPartidosMostrar;?>; i++) {
							dataHC.push(votos_porcentaxe_e_cor[i]);
						}


						$('#container').css("display", "block");
						Highcharts.chart('container', {
									chart: {
										type: 'bar'
									},
									title: {
										text: props['name'] + ' - ' + props['CDIS'] +'-'+ props['CSEC']
									},
									xAxis: {
										categories: partidos
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
										name: 'Tanto por cento',
										showInLegend: false,
										data: dataHC
									}]
								});
					}
				}

				this._div.innerHTML = '<h2><?php echo $eleccion;?></h2><br />'
					+ '<h4><?php echo (@isset($_REQUEST['comarca']) ? $_REQUEST['comarca'] : 'Galiza') ;?></h4>'
					+  texto;
			};
			info.addTo(map);


		// colores, estilos e legend
		function getColor(partido) {
			<?php echo "cores_e_graos = ".json_encode(Partidos::getCoresPartidos()).";";?>
			return cores_e_graos[partido];
		}


		// pintado
		function style(feature) {
			partido = null;

			cusec = feature.properties['CUSEC'];
			if(datos_electorais_js[cusec]){
				partido = Object.keys(datos_electorais_js[cusec])[posicionPartido]; 	// primeiro partido
			}

			return {
				fillColor: getColor(partido),
				weight: 2,
				opacity: 1,
				color: 'blue',
				dashArray: '3',
				fillOpacity: 0.5
			};
		}




			// quadrado de infor, legend
			var legend = L.control({position: 'bottomleft'});
			legend.onAdd = function (map) {
				var div = L.DomUtil.create('div', 'info legend');

				var labels = [];
				labels.push('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>Partido (Num. distritos aparece entre os <?php echo $numeroPartidosMostrar?> primeiros)</strong>');

				<?php
				// imos revisar o numero de partidos, nas municipais saem demasiados, polo que ordeamos por numero e so sacamos um numero significativo (15)
				$arrPartidosNosPrimeiros = $datosElectoraisPartidosObj->getPartidosNosPrimeirosPostos();
				arsort($arrPartidosNosPrimeiros);
				if(count($arrPartidosNosPrimeiros) > 15) $arrPartidosNosPrimeiros = array_slice($arrPartidosNosPrimeiros, 0, 15);
				?>;

				var partidos = <?php echo json_encode($arrPartidosNosPrimeiros, JSON_INVALID_UTF8_IGNORE);?>;
				for (var partido in partidos) {
					partidoOut = partido;
					//if(partidoOut.length > 27) partidoOut = partido.substring(0, 26) + '...';


					labels.push(
						'<i style="background:' + getColor(partido) + '"></i> ' + partidoOut + '  (' + partidos[partido] + ')');
				}
				labels.push('<br><strong>Total distritos: <?php echo count($datos_electorais_de_partidos);?></strong>');
				
				div.innerHTML = labels.join('<br>');
				return div;
			};
			legend.addTo(map);		
	</script>
</body>
</html>