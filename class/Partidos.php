<?php
/**
 * Clase Partidos
 */

 class Partidos
 {
    // so imos pintar os partidos que nalgumha mesa estiverom nos N_PRIMEIROS postos na lenda
    const N_PRIMEIROS = 4;


    // todos os posibles partidos, coas cores que lhes correspondem
    private static $_coresPartidos = array(
            'PP' => '#15589D',
            'P.P.' => '#15589D',
            
            'PSOE' => 'red',
            'PSdeG-PSOE' => 'red',
            'PSdeG - PSOE' => 'red',    // galegas < 2020
        
            'PODEMOS-EU' => 'purple',
            'PODEMOS' => 'purple',
            'PODEMOS-ESQUERDA UNIDA-ANOVA' => 'purple',
            'EU-IU' => '#DF0123',
        
            'BNG' => '#6AADE4',
            'B.N.G.' => '#6AADE4',
            'NÓS' => '#6AADE4',
            'BNG-AGORA REP?BLICAS' => '#6AADE4',    // europeias 2019
            'BNG-NS' => '#6AADE4',      // galegas < 2020 (ao empregar JSON_INVALID_UTF8_IGNORE ao convertir a json com json_encode, perdese o Ó, que xa vem mal, de feito)

            'LV-GVE' => '#198706',  // os verdes, europeias 2009
        
            'Cs' => 'orange',
            'C\'s' => 'orange',       // europeias 2014
        
            'VOX' => 'green',

            // Espazo comun, Recortes cero, Os verdes, Municipalistas
            'ESCO-RC-OV-M' => 'yellow', 

            'UPYD' => 'magenta',
            'UPyD' => 'magenta',    // europeias 2014

            'EN MAREA-COMPROMISO POR GALICIA-PARTIDO GALEGUISTA' => '#1450ff',
            'EN MAREA' => '#1450ff',

            'PACMA' => '#93A607',

            'PUM+J' => '#FAD4B4',

            // municipais 2019
            'MAREAS LOCAIS' => '#1450ff',
            'MAREA ATL?NTICA' => '#01ADEF',
            'CxG' => '#2F744D',
            'TEGA' => '#F53613',
            'D.O.'  => '#FFCC00',
            'SON EN COM?N' => '#061182',
            'CA' => '#999898',
            'CP' => 'purple',

            // europeias 2014
            'AGE' => '#0695FF',


            'Votos nulos' => 'brown',
            'Votos brancos' => 'white',
        
        );

    // arrays de apoio de DatosElectorais
    private $_arrPartidos = [];
    private $_datos_electorais = [];


    // partidos que, dos datos recibidos, estiveron nos N_PRIMEIROS postos nalgumha mesa
    private $_partidos_nos_N_primeiros = [];

    // datos ja parseados por mesa e cos partidos ordeados de maior a menor
    private $_datos_electorais_de_partidos;


    /**
     * @param array $datos_electorais, o array devolve polos métodos da clase DatosElectorais cos resultados totais+porcentaxes
     * @param array array de partidos politicos dos datos desde a clase DatosElectorais, extraemse direitamente de parsear as cabeceiras do ficheiro empregado
     * @return array
     */
    function __construct($datos_electorais, $arrPartidos) {
        $this->_datos_electorais = $datos_electorais;
        $this->_arrPartidos = $arrPartidos;

        $this->_xeneraDatosElectoraisPartidos();
    }



    /**
     * recibe umha clase de datosElectorais e devolve um array similar ([distrito1|mesa1] => {data}, [distrito1|mesa2] => {data})
     * pero com so os datos de partidos e ordeados de maior a menor número de votos
     */
    private function _xeneraDatosElectoraisPartidos() {
        $datos_electorais_de_partidos = [];

        foreach($this->_datos_electorais as $distrito => $data) {
            foreach($data as $k => $v) {
                if($k < array_key_first($this->_arrPartidos) || $k > array_key_last($this->_arrPartidos)) continue;
        
                $datos_electorais_de_partidos[$distrito][$this->_arrPartidos[$k]] = $v;
            }
            arsort($datos_electorais_de_partidos[$distrito], SORT_NUMERIC);

            // colho os 3 primeiros e engadoos ao array se nom o estam ja
            $partidos = array_slice(array_keys($datos_electorais_de_partidos[$distrito]), 0, Partidos::N_PRIMEIROS);
            foreach($partidos as $p) {
                // se nom esta engado sumando, asi tenho tamem o total de mesas nas que estivo entre os n primeiros
                // asi, sabendo o total de $this->_datos_electorais_de_partidos cum count() podemos fazernos idea de quantos estamos 
                if(!in_array($p, $this->_partidos_nos_N_primeiros)) $this->_partidos_nos_N_primeiros[$p]++;
            }
        }

        $this->_datos_electorais_de_partidos = $datos_electorais_de_partidos;
    }

    /**
     * Datos electorais por partidos, ordeados de mais a menos votos
     */
    public function getDatosElectoraisPartidos() {
        return $this->_datos_electorais_de_partidos;
    }

    /**
     * Devolve partidos nos primeiros N postos
     */
    public function getPartidosNosPrimeirosPostos() {
        return $this->_partidos_nos_N_primeiros;
    }


    /**
     * Cores dos partidos
     */
    public static function getCoresPartidos() {
        return self::$_coresPartidos;
    }

    /**
     * Cor partido (tem que coincidir ao 100%)
     */
    public static function getCorPartido($partido) {
        return self::$_coresPartidos[$partido];
    }


    /**
     * devolve que numero de partidos configuramos para comprobar se estam nos primeiros
     */
    public static function getNumeroPartidosAComprobarNosPrimeiros() {
        return self::N_PRIMEIROS;
    }
}