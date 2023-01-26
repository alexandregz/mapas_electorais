<?php
/**
 * Clase Comarcas, chamadas estaticas
 */

class ComarcasClass
{
    /**
     * Devolve concelhos e ID de cada um da comarca recibida, segundo os datos do IGE
     * @return array $comarcas Array co formato { ["Arzúa"]=> array(4) { [15006]=> "Arzúa", [15010]=> "Boimorto", ... } }
     */
    public static function __concellosConCPporComarca() {
        $comarcas = [];
        // hack: em dinahosting file_get_contents parece que nom estea ativado nos hostings compartidos. Emprego fopen()
        // $contido = trim(file_get_contents(__DIR__.'/comarcas.txt'));
        $fp = fopen(__DIR__.'/comarcas.txt', "r");
        $contido = fread($fp, filesize(__DIR__.'/comarcas.txt'));

        
        $provinza = '';
        $comarca = '';
        foreach(explode("\n", $contido) as $line) {
            $line = trim($line);
       
            // nome da comarca
            if(preg_match('/^\d{4} /', $line)) {
                list($cp, $nome_comarca) = explode(" ", $line, 2);
                $comarca = $nome_comarca;
            }
            elseif(preg_match('/^\d{5} /', $line)) {
                list($cp, $concello) = explode(" ", $line, 2);
                $comarcas[$provinza][$comarca][$cp] = $concello;        // aqui engadimos
            }
            // agrupamos por provinzas primeiro
            // hack: ponho de ultimo no else para que nom vaia entrando sempre e porque so hai 4 que nom comezem por numero
            elseif(preg_match('/^[A-Z]/', $line)){
                $provinza = $line;
            }
        }

        return $comarcas;
    }


    /**
     * Devolve províncias disponhiveis do IGE
     * 
     * @return array 
     */
    public function provinciasDisponhibles() {
        return array_keys(self::__concellosConCPporComarca());
    }

    /**
     * Devolve comarcas disponhiveis do IGE
     * 
     * @return array 
     */
    public static function comarcasDisponhibles() {
        $concellosPorComarca = self::__concellosConCPporComarca();
        
        $comarcas = [];
        foreach($concellosPorComarca as $provincia) {
            $comarcas = array_merge($comarcas, array_keys($provincia));
        }
        return $comarcas;
    }

    /**
     * Devolve comarcas disponhiveis agrupadas por província
     * 
     * @return array 
     */
    public static function comarcasDisponhiblesPorProvincia() {
        $concellosPorComarca = self::__concellosConCPporComarca();


        $comarcas = [];
        foreach($concellosPorComarca as $provincia => $comarca) {
            $comarcas[$provincia] = array_keys($comarca);
        }
        return $comarcas;
    }


    /**
     * Devolve concelhos dumha comarca
     * @param string $comarca
     * @return array Array formato {[cp1] => concelho1, [cp2] => concelho2, ...}
     */
    public static function concellosPorComarca($comarca) {
        $concellosPorComarca = self::__concellosConCPporComarca();

        foreach($concellosPorComarca as $provincia => $comarcas) {
            foreach($comarcas as $c => $concellos) {
                if(strtoupper($c) == strtoupper($comarca)) return $concellos;
            }
        }
    }

    /**
     * Devolve todos os concelhos
     * 
     * @return array 
     */
    public static function concellosDisponhibles() {
        $concellosPorComarca = self::__concellosConCPporComarca();
        $concellosTotais = [];
        foreach($concellosPorComarca as $provincia => $comarcas) {
            foreach($comarcas as $c => $concellos) {
                foreach($concellos as $id => $nome) {
                    $concellosTotais[$id] = $nome;
                }
            }
        }
        asort($concellosTotais);
        return $concellosTotais;
    }


    /**
     * Devolve comarca do concelho segundo o seu ID
     * 
     * @return string
     */
    public static function comarcaDeConcello($ID_concello) {
        $concellosPorComarca = self::__concellosConCPporComarca();

        foreach($concellosPorComarca as $provincia => $comarcas) {
            foreach($comarcas as $c => $concellos) {
                foreach($concellos as $id => $concello) {
                    if($id == $ID_concello) return $c;
                }
            }
        }

        return null;
    }


    /**
     * Devolve array con formato [ID_concello1 => 'Comarca', ID_concello2 => 'Comarca2',... ]
     * 
     * @return array
     */
    public static function comarcasPorConcellos() {
        $concellosPorComarca = self::__concellosConCPporComarca();

        $concellosComarcas = [];
        foreach($concellosPorComarca as $provincia => $comarcas) {
            foreach($comarcas as $c => $concellos) {
                foreach($concellos as $id => $concello) {
                    $concellosComarcas[$id] = $c;
                }
            }
        }

        return $concellosComarcas;
    }
}