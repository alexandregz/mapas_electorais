<?php
/**
 * Clase para xerar datos electorais de CSVs
 */

// ini_set("display_errors", 1);
// error_reporting(E_ERROR);

 // ficheiros .json grandes (os de mesas censais, p.e.) podem ocupar muito
 ini_set("memory_limit", "-1");
 set_time_limit(0);
 
require_once dirname(__FILE__)."/ComarcasClass.php";

class DatosElectorais {
    protected $datosElectorais = [];

    // comarcas
    protected $comarcas = [];

    // ficheiro csv de datos electorais
    private $_ficheiro;
    private $_cabeceiras;
    private $_texto;

    private $_id_BNG;
    private $_id_VotosTotais;

    private $_tantos_por_cento_BNG; // do que coincida co $_id_BNG


    private $_keyInicialPartidos = 11;  // lugar a partir do que comezam os partidos (habitualmente, nas galegas < 2020 nom)


    /**
     * 
     */
    function __construct($ficheiro) {
        if($this->comarcas == null) {
            $this->comarcas = ComarcasClass::__concellosConCPporComarca();
        }

        if(!file_exists($ficheiro)) {
            throw new Exception("Non existe ficheiro $ficheiro!");
        }

        // nos ficheiros direitamente quitados da Xunta, o lugar de comezo dos partidos cambia
        if(preg_match('/ELECCIONS_PARLAMENTO_GALICIA_(\d)+_MESAS/', $ficheiro) === 1) {
            $this->_keyInicialPartidos = 14;
        }

        $this->_ficheiro = $ficheiro;
        $this->_getCabeceirasETexto();

        $this->buscarIDs_BNG_VotosTotais();
    }


    /**
     * Le cabeceiras e texto do csv
     */
    private function _getCabeceirasETexto() {
        $fila = 0;
        if (($gestor = fopen($this->_ficheiro, "r")) !== FALSE) {
            // aumento de 4096 a 8192 porque em certos ficheiros das galegas nom chegaba (p.e. ELECCIONS_PARLAMENTO_GALICIA_2012_MESAS.csv)
            while (($datos = fgetcsv($gestor, 8192, ";")) !== FALSE) {
                $fila++;

                if($fila == 1) {
                    $datos = array_map('trim', $datos);
                    $this->_cabeceiras = $datos;
                }
                else {
                    $this->_texto[] = $datos;

                }
            }
        }
    }



    /**
     * Devolve o lugar do texto buscado
     * 
     * @param array $datos Linha de cabeceira do csv en array para buscar posicions de partidos
     * @param string $texto_buscar Texto a buscar, por defeito "BNG"
     * @return null|int id_BNG Key do BNG no array de cabeceiras
     */
    public function buscarTextoEnArrayCabecera($texto_buscar='BNG') {
        $numero = count($this->_cabeceiras);

        for ($c=0; $c < $numero; $c++) {
            $cabeceiras[$c] = trim($this->_cabeceiras[$c]);

            // buscamos BNG nas cabeceiras para saber em que número de rexistro se vam contabilizar os votos
            if(strtoupper($cabeceiras[$c]) == strtoupper($texto_buscar)) return $c;

            // se nom atopamos literal, buscamos por comezo de cadea, como nas europeias ("BNG-AGORA REPÚBLICAS")
            if(preg_match("/^$texto_buscar/i", strtoupper($cabeceiras[$c])) ) return $c;
        }

        return null;
    }

    /**
     * Busca posicion BNG e Votos Totais no array de cabeceira do ficheiro csv
     * 
     * @param string $ficheiro CSV
     * @return null|array null ou array com [id_BNG, id_VotosTotais]
     */
    public function buscarIDs_BNG_VotosTotais() {
        $data = null;

        $id_BNG = $this->buscarTextoEnArrayCabecera('BNG');
        // municipais 2011
        if(!$id_BNG) {
            $id_BNG = $this->buscarTextoEnArrayCabecera('B.N.G.');
        }
        // nos ficheiros de "datos" (quitados da web https://infoelectoral.interior.gob.es/)
        if(!$id_BNG) {
            $id_BNG = $this->buscarTextoEnArrayCabecera('VOTOS BNG');
        }

        $id_VotosTotais = $this->buscarTextoEnArrayCabecera('VOTOS TOTAIS');
        // nos ficheiros das galegas vem "TOTAL VOTOS"
        if(!$id_VotosTotais) {
            $id_VotosTotais = $this->buscarTextoEnArrayCabecera('TOTAL VOTOS');
        }

        if($id_BNG) $data[] = $id_BNG;
        if($id_VotosTotais) $data[] = $id_VotosTotais;

        $this->_id_BNG = $id_BNG;
        $this->_id_VotosTotais = $id_VotosTotais;

        return $data;
    }


    /**
     * Devolve array de barrios coas mesas de cada barrio
     * 
     * @param string $ficheiro CSV
     * @return null|array null ou array 
     */
    public function buscarBarriosMesas() {
        $barrios_con_mesas = null;

        foreach($this->_texto as $datos) {
            $distrito = str_pad($datos[0], 2, '0', STR_PAD_LEFT);;
            $seccion = str_pad($datos[1], 3, '0', STR_PAD_LEFT);

            $identificador_seccion = trim($distrito."-".$seccion);

            // repetia mesas, necesitamos que cada mesa de cada districto e seccom apareza so umha vez
            $barrios_con_mesas[$datos[4]][] = $identificador_seccion;
            $barrios_con_mesas[$datos[4]] = array_unique($barrios_con_mesas[$datos[4]]);
        }

        return $barrios_con_mesas;
    }

    /**
     * Devolve array co formato == SECCION => DATOS e em "mesas" gardado por mesas (nom coincide sempre e hai que fazer certos hacks):
     *  	 array (
	 *  	    'DD-SSS' => array ([0]=> DISTRITO; [1]=> SECCION; [2]=> MESA; [3]=> LUGAR; [4]=> BARRIO; ...)
     * 
     *          'DD-SSS' => array ([0]=> "2"; [1]=> "4"; [2]=> "B"; [3]=> IES Xelmírez I"; [4]=> "Campus Sur"; ...)
     *       )
     *  É importante o formato NN-NNN  (distrito dúas cifras, sección tres) porque os datos dos mapas venhem num json com ese formato do INE 
     *  em keys CDIS (distrito) e CSEC (sección)
     * 
     *  OLHO, FICHEIROS EM UTF-8 (se é ISO-8859 o json peta)
     *   
     *  HOWTO: 
     *      1) crear com excel é o máis doado
     *      2) convertir co cli "iconv":
     *          alex@vosjod:.../santiago(main)$ iconv -f iso-8859-1 -t utf-8 datos_xerais_10N_2019.csv > datos_xerais_10N_2019_utf8.csv
     * 
     * @return array array co formato == SECCION => DATOS e em "mesas" gardado por mesas (nom coincide sempre e hai que fazer certos hacks)
     */
    public function construeDatosElectoraisCsvDSM() {
        $datos_electorais = [];

        foreach($this->_texto as $datos) {
            $distrito = str_pad($datos[0], 2, '0', STR_PAD_LEFT);;
            $seccion = str_pad($datos[1], 3, '0', STR_PAD_LEFT);

            $identificador_seccion = trim($distrito."-".$seccion);

            // engado ao final, $distrito-$seccion e $total, sumando os votos, se nom é numerico o valor nom fazemos nada
            foreach($datos as $k => $v) {
                if(is_numeric($v)) {
                    @$datos_electorais[$identificador_seccion][$k] += $v;
                }
                else {
                    $datos_electorais[$identificador_seccion][$k] = ($v == '' ? 0 : $v);    // por se vem baleiro, convirto a 0
                }
                $datos_electorais[$identificador_seccion][$k] = "".$datos_electorais[$identificador_seccion][$k];   // convertir a string
            }
            // engado aqui PORCENTAXE e VOTOS DO BNG, porque vounos pintar despois no datatables empregando este array
            $datosSeccion = $datos_electorais[$identificador_seccion];
            $datos_electorais[$identificador_seccion]['votos_BNG'] = $datosSeccion[$this->_id_BNG];
            $datos_electorais[$identificador_seccion]['porcentaxe'] = '';
            if( $datosSeccion[$this->_id_VotosTotais] > 0) {
                $datos_electorais[$identificador_seccion]['porcentaxe'] = round($datosSeccion[$this->_id_BNG]*100 / $datosSeccion[$this->_id_VotosTotais], 2);
            }

            if(isset($datos_electorais[$identificador_seccion]['lugar'])) {
                //  para nom repetir lugares que sejam iguais
                if(trim($datos[3]) != trim($datos_electorais[$identificador_seccion]['lugar'])) {
                    $datos_electorais[$identificador_seccion]['lugar'] .= ", ".$datos[3];
                }
            }
            else {
                $datos_electorais[$identificador_seccion]['lugar'] = $datos[3];
            }

            if(isset($datos_electorais[$identificador_seccion]['barrio'])) {
                //  para nom repetir lugares que sejam iguais
                if(trim($datos[4]) != trim($datos_electorais[$identificador_seccion]['barrio'])) {
                    $datos_electorais[$identificador_seccion]['barrio'] .= ", ".$datos[4];
                }
            }
            else {
                $datos_electorais[$identificador_seccion]['barrio'] = $datos[4];
            }

            $datos_electorais['mesas'][] = $datos;	// aqui para ter a man os datos por mesas, por se fazemos algo com eles.

        }

        $this->datosElectorais = $datos_electorais;
        return $datos_electorais;
    }



    /**
     * Devolve array de datos de ficheiros extraidos de https://infoelectoral.interior.gob.es/.
     * 
     * Estes datos vam por concelho e os ficheiros já estám parseados
     * (ver https://github.com/alexandregz/infoelectoral/blob/master/src/creaCsvMesas.php)
     * 
     * @param boolean   Identifica se estamos num mapa de concelhos ou nom. Se é assim, o identificador_seccion nom adxunta distrito-seccion, pois nim é necesario nim estám nos datos
     * @param array     CPs de concelhos a devolver, por se os ficheiros som grandes só devolver o que interese tratar
     * @return array    array co formato == ProvMuni|DistrSecc => DATOS e em "mesas" gardado por mesas
     */
    public function construeDatosElectoraisFicheirosGobEsParseados($concellos=false, $CPS_concellos=null) {
        $datos_electorais = [];

        foreach($this->_texto as $datos) {
            $id_provincia = str_pad($datos[0], 2, '0', STR_PAD_LEFT);
            $id_concelho = str_pad($datos[2], 3, '0', STR_PAD_LEFT);
            $distrito = str_pad($datos[4], 2, '0', STR_PAD_LEFT);;
            $seccion = str_pad($datos[5], 3, '0', STR_PAD_LEFT);

            $identificador_seccion = $id_provincia.$id_concelho;
            if(!$concellos) $identificador_seccion .= "|$distrito-$seccion";

            // se recibimos "CPS_concellos" so devolvemos datos do concelho indicado
            $cp_concelho = $id_provincia.$id_concelho;
            if($CPS_concellos != null) {
                if(!in_array($cp_concelho, $CPS_concellos)) continue;
            }

            // hai que sumar as mesas, porque os mapas vam por secçom
            // engado ao final, $distrito-$seccion e $total, sumando os votos, se nom é numerico o valor nom fazemos nada
            foreach($datos as $k => $v) {
                if(is_numeric($v)) {
                    @$datos_electorais[$identificador_seccion][$k] += $v;
                }
                else {
                    $datos_electorais[$identificador_seccion][$k] = ($v == '' ? 0 : $v);    // por se vem baleiro, convirto a 0
                }
                
                $datos_electorais[$identificador_seccion][$k] = "".$datos_electorais[$identificador_seccion][$k];   // convertir a string
            }
            // engado aqui PORCENTAXE e VOTOS DO BNG, porque vounos pintar despois no datatables empregando este array
            $datosSeccion = $datos_electorais[$identificador_seccion];
            $datos_electorais[$identificador_seccion]['votos_BNG'] = $datosSeccion[$this->_id_BNG];
            $datos_electorais[$identificador_seccion]['votos_totais'] = $datosSeccion[$this->_id_VotosTotais];
            $datos_electorais[$identificador_seccion]['porcentaxe'] = '';
            if( $datosSeccion[$this->_id_VotosTotais] > 0) {
                $datos_electorais[$identificador_seccion]['porcentaxe'] = round($datosSeccion[$this->_id_BNG]*100 / $datosSeccion[$this->_id_VotosTotais], 2);
            }
           
            $this->_tantos_por_cento_BNG[] = $datos_electorais[$identificador_seccion]['porcentaxe'];

            $datos_electorais['mesas'][] = $datos;	// aqui para ter a man os datos por mesas, por se fazemos algo com eles.
        }
        $datos_electorais['tantos_por_cento'] = $this->_tantos_por_cento_BNG;   // para usar em mapa de Galiza por concelhos/comarcas

        $this->datosElectorais = $datos_electorais;
        return $datos_electorais;
    }



    /**
     * Devolve array de datos por distritos. Demasiado heteroxéneo.
     * 
     * @param array     CPs de concelhos a devolver, por se os ficheiros som grandes só devolver o que interese tratar
     * @return array array co formato == SECCION => DATOS e em "mesas" gardado por mesas (nom coincide sempre e hai que fazer certos hacks)
     */
    public function construeDatosElectoraisFicheirosGobEs($CPS_concellos=null) {
        $datos_electorais = [];

        foreach($this->_texto as $datos) {
            $id_provincia = str_pad($datos[1], 2, '0', STR_PAD_LEFT);;
            $id_concelho = str_pad($datos[3], 3, '0', STR_PAD_LEFT);

            $codigoine = $id_provincia.$id_concelho;
            // se recibimos "CPS_concellos" so devolvemos datos do concelho indicado
            if($CPS_concellos != null) {
                if(!in_array($codigoine, $CPS_concellos)) continue;
            }

            $datos['votos_BNG'] = $datos[$this->_id_BNG];
            $datos['votos_totais'] = $datos[$this->_id_VotosTotais];
            $datos['porcentaxe'] = '';
            if($datos[$this->_id_VotosTotais] > 0) {
                $datos['porcentaxe'] = round($datos[$this->_id_BNG]*100 / $datos[$this->_id_VotosTotais], 2);
            }
            
            $this->_tantos_por_cento_BNG[] = $datos['porcentaxe'];

            $datos_electorais[$codigoine] = $datos;
        }
        $datos_electorais['tantos_por_cento'] = $this->_tantos_por_cento_BNG;   // para usar em mapa de Galiza por concelhos/comarcas

        $this->datosElectorais = $datos_electorais;
        return $datos_electorais;
    }


    /**
     * Devolve array de datos de ficheiros das eleccions Galegas (https://abertos.xunta.gal/catalogo/administracion-publica/-/dataset/0426/eleccions-parlamento-galicia-resultados-2020)
     * 
     * NOTA: De 2016 para atras o formato é distinto: discrimina entre "distrito", "sección" e "mesa". 
     *          Em 2020 o formato é todo xunto, tal que asi: "2-003 -B"  (distrito-seccion -mesa).
     *          Tamém cambia a orde dos códigos de prov e concelho.
     * 
     * @return array array co formato habitual, ver resposta de construeDatosElectoraisFicheirosGobEsParseados() que é a base
     */
    public function construeDatosElectoraisGalegasDatasetsAbertos($CPS_concellos=null) {
        $datos_electorais = [];

        foreach($this->_texto as $datos) {
            // ---- construccion identificador
            // buscamos se tem em cabeceiras distrito, polo que teria "distrito", "seccion" e "mesa" (2016 e anteriores)
            //  , se nom suponhemos que so tem "mesa" (2020). Tamém influe na orde dos códigos de prov e concelho.
            if(in_array(strtoupper("distrito"), $this->_cabeceiras) || in_array(strtolower("distrito"), $this->_cabeceiras)) {
                $distrito = $datos[4];
                $seccion = $datos[5];
                $mesa = $datos[6];

                $id_provincia = $datos[0];
                $id_concelho = $datos[2];
            }
            else {
                list($distrito, $seccion, $mesa) = explode("-", $datos[3]);
                $seccion = trim($seccion);

                $id_provincia = $datos[0];
                $id_concelho = $datos[1];
            }

            // padeamos
            $distrito = str_pad($distrito, 2, '0', STR_PAD_LEFT);
            $seccion = str_pad($seccion, 3, '0', STR_PAD_LEFT);
            $id_provincia = str_pad($id_provincia, 2, '0', STR_PAD_LEFT);
            $id_concelho = str_pad($id_concelho, 3, '0', STR_PAD_LEFT);

            // se recibimos "concello" so devolvemos datos do concelho indicado
            $cp_concelho = $id_provincia.$id_concelho;
            if($CPS_concellos) {                
                if(!in_array($cp_concelho, $CPS_concellos)) continue;
            }


            // keys do array, replicando o modelo de construeDatosElectoraisFicheirosGobEsParseados()
            $identificador_seccion = $id_provincia.$id_concelho."|$distrito-$seccion";
            // ---- fin construccion identificador


            // hai que sumar as mesas, porque os mapas vam por secçom
            // engado ao final, $distrito-$seccion e $total, sumando os votos, se nom é numerico o valor nom fazemos nada
            foreach($datos as $k => $v) {
                if(is_numeric($v)) {
                    @$datos_electorais[$identificador_seccion][$k] += $v;
                }
                else {
                    $datos_electorais[$identificador_seccion][$k] = ($v == '' ? 0 : $v);    // por se vem baleiro, convirto a 0
                }
                
                $datos_electorais[$identificador_seccion][$k] = "".$datos_electorais[$identificador_seccion][$k];   // convertir a string
            }
            // engado aqui PORCENTAXE e VOTOS DO BNG, porque vounos pintar despois no datatables empregando este array
            $datosSeccion = $datos_electorais[$identificador_seccion];
            $datos_electorais[$identificador_seccion]['votos_BNG'] = $datosSeccion[$this->_id_BNG];
            $datos_electorais[$identificador_seccion]['votos_totais'] = $datosSeccion[$this->_id_VotosTotais];
            $datos_electorais[$identificador_seccion]['porcentaxe'] = '';
            if( $datosSeccion[$this->_id_VotosTotais] > 0) {
                $datos_electorais[$identificador_seccion]['porcentaxe'] = round($datosSeccion[$this->_id_BNG]*100 / $datosSeccion[$this->_id_VotosTotais], 2);
            }
           
            $this->_tantos_por_cento_BNG[] = $datos_electorais[$identificador_seccion]['porcentaxe'];

            $datos_electorais['mesas'][] = $datos;	// aqui para ter a man os datos por mesas, por se fazemos algo com eles.
        }
        $datos_electorais['tantos_por_cento'] = $this->_tantos_por_cento_BNG;   // para usar em mapa de Galiza por concelhos/comarcas

        $this->datosElectorais = $datos_electorais;
        return $datos_electorais;
    }




    /**
     * Parseo de JSON dos mapas do INE. Engade certas keys por utilidade nos mapas: tanto_por_cento_voto_BNG, votos_totais, votos_BNG, lugar, barrio
     * 
     * @param string $ficheiro_coordenadas_json
     * @param array $cps_concellos_mostrar para nom devolver toda a infor em ficheiros grandes
     * @return array $json_coordenadas GeoJSON dos mapas, coas keys engadidas descritas anteriormente
     * @return array $tantos_por_cento Tantos por cento votados a BNG (datos[id_BNG]*100/datos[id_VotosTotais])
     */
    public function engadirAJsonMapasINE($ficheiro_coordenadas_json, $cps_concellos_mostrar=null) {
        $json_coordenadas = json_decode(file_get_contents($ficheiro_coordenadas_json), true);


        // NOTA: calculo aqui o tantos_por_cento; quizais por lóxica de negocio deberia estar en construeDatosElectoraisCsvDSM(), 
        //          pero é o que se vai a visualizar, com este formato, no datatables, tooltips, etc., tal e como debe de empregarse em leaflet, que é a función última
        //          deste json, polo que é máis coerente empregalo aqui
        $tantos_por_cento = [];
        foreach($json_coordenadas['features'] as $k => $v) {

            // ID de concelho, é um integer
            $id_MUN = intval($v['properties']['CMUN']);

            // formato da key: "15078|01-001"
			$distrito = str_pad($v['properties']['CDIS'], 2, '0', STR_PAD_LEFT);
			$seccion = str_pad($v['properties']['CSEC'], 3, '0', STR_PAD_LEFT);
			$identificador = $v['properties']['CUMUN']."|$distrito-$seccion";

            // eliminamos via unset key se o CP nom é dos que temos que tratar para alixeirar os datos empregados
            if($cps_concellos_mostrar != null) {
                if(!in_array($v['properties']['CUMUN'], $cps_concellos_mostrar)) {
                    unset($json_coordenadas['features'][$k]);
                    continue;
                }
            }

            $datosSeccion = $this->datosElectorais[$identificador];
            $tanto_por_cento_votos_BNG = $datosSeccion['porcentaxe'];

            // hack: converter a numeros
            $tanto_por_cento_votos_BNG = str_replace(",", ".", $tanto_por_cento_votos_BNG);
            $tanto_por_cento_votos_BNG = (float)$tanto_por_cento_votos_BNG;


            if($tanto_por_cento_votos_BNG != '') {
                $tantos_por_cento[] = $tanto_por_cento_votos_BNG;
            }

            // hack: converter a string com comilhas, se nom php nom converte a json (polo menos no MAMP em local)
            $json_coordenadas['features'][$k]['properties']['tanto_por_cento_voto_BNG'] = "'".$tanto_por_cento_votos_BNG."'";

            // engadimos numero de votos totais e do BNG
            $json_coordenadas['features'][$k]['properties']['votos_totais'] = $datosSeccion[$this->_id_VotosTotais];
            $json_coordenadas['features'][$k]['properties']['votos_BNG'] = $datosSeccion[$this->_id_BNG];
        }

        $this->_tantos_por_cento_BNG = $tantos_por_cento;   // ToDo a cambialo para que consuma isto
        return [$json_coordenadas, $tantos_por_cento];
    }




    /**
     * Devolve array con cores e graos para lenda de cores no mapa
     * 
     *      calculamos porcentagens das cores (6 cores)
     *		colhemos o resultado maior e o menor e asignamos os valores segundo estes, porque se nom hai diferencias substancias, 
     *      por exemplo entre as galegas e as do Congreso (nas galegas nom se baixa dum 12% nos peores concelhos, 
     *          nas do congreso nom se pasa dum 12% nos melhores, polo que neste último queda o mapa em branco)
     * 
     * @param array $tantos_por_cento
     * @return array $cores_e_graos
     */
    public function coresEGrados($tantos_por_cento, $numero_cores=8) {
        // calculamos porcentagens das cores (6 cores)
        //		colhemos o resultado maior e o menor e asignamos os valores segundo estes, porque se nom hai diferencias substancias, por exemplo entre as galegas e as do Congreso
        //		(nas galegas nom se baixa dum 12% nos peores concelhos, nas do congreso nom se pasa dum 12% nos melhores, polo que neste último queda o mapa em branco)

        $cores = ['#FFFFFF',  '#FFEDA0',  '#FEB24C' , '#FD8D3C' ,  '#FC4E2A' , '#E31A1C', '#BD0026',  '#800026'];
        if($numero_cores == 6) {
            $cores = ['#eff3ff','#c6dbef','#9ecae1','#6baed6','#3182bd','#08519c'];
        }
        elseif($numero_cores == 9) {
            $cores = ['#FFFFFF',  '#FFEDA0', '#FED976' , '#FEB24C' , '#FD8D3C' ,  '#FC4E2A' , '#E31A1C', '#BD0026',  '#800026'];
        }

        $cores_e_graos = [];

        // sort($tantos_por_cento, SORT_NUMERIC);
        sort($tantos_por_cento);
        $min = floor(min($tantos_por_cento));	
        $max = ceil(max($tantos_por_cento));	// redondeo cara arriba o max e cara abaixo o min para deixar mais margem
        $total = count($tantos_por_cento);

        $graos_de_separacion = ceil(($max - $min) / count($cores));

        $z = 0;
        for($i = $min; $i <= $max; $i += $graos_de_separacion) {
            $cores_e_graos[$i] = $cores[$z];
            $z++;
        }
        // engadimos o último rexistro
        $cores_e_graos[$max+$graos_de_separacion] = $cores[$z]; 
        $cores_e_graos = array_filter($cores_e_graos);	// por se sobra o ultimo e queda com NULL, quitamolo

        return $cores_e_graos;
    }



    /**
     * Devolve cabeceiras
     */
    public function getCabeceiras() {
        return $this->_cabeceiras;
    }


    /**
     * Devolve posicion do BNG nas cabeceiras
     */
    public function getIdBNG() {
        return $this->_id_BNG;
    }

    /**
     * Devolve partidos politicos por orde e coa key que lhes corresponde
     */
    // ToDo a comprobar keyInicial em cada eleccion, porque nas galegas muda entre eleccions
    public function getPartidosPoliticos() {
        $cabeceiras = [];

        foreach($this->_cabeceiras as $k => $v) {
            // se hai ficheiros novos cómpre ir revisando esta key inicial
            if($k < $this->_keyInicialPartidos) continue;

            // nas galegas quito os parenteses dos nomes, por redundancia, p.e.
            //
            // string(36) "BNG-N�S (BNG-N�S CANDIDATURA GALEGA)"
            // string(20) "PP (PARTIDO POPULAR)"
            //   string(51) "PACMA (PARTIDO ANIMALISTA CONTRA O MALTRATO ANIMAL)"
            //
            if(preg_match('/ELECCIONS_PARLAMENTO_GALICIA_(\d)+_MESAS/', $this->_ficheiro) === 1) {
                $v = preg_replace('/ \(.*\)$/', '', $v);
            }

            // hacks:
            // 1) PODEMOS-EU- == PODEMOS-EU
            if($v == 'PODEMOS-EU-' || $v == 'PODEMOS-ESQUERDA UNIDA-ANOVA') $v = 'PODEMOS-EU';
            if($v == 'B.N.G.' || $v == 'BNG-NS') $v = 'BNG';
            if($v == 'PSdeG - PSOE') $v = 'PSdeG-PSOE';

            $cabeceiras[$k] = $v;
        }

        return $cabeceiras;
    }
}
