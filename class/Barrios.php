<?php
/**
 * Clase Barrios, chamadas estáticas
 */

class Barrios
{
    /**
     * Devolve barrios do ficheiro pasado, por mesas. Formato DISTRITO;SECCION;MESA;Centro votación;BARRIO/Zona
     * 
     * @return ['01-001-U' => ['centro_votacion' => xxx, 'barrio' => yyy], ... ]
     */
    public static function barriosPorMesas($ficheiro) {
        $barrios = [];

        if (($gestor = fopen($ficheiro, "r")) !== FALSE) {
            while (($datos = fgetcsv($gestor, 4096, ";")) !== FALSE) {
                $fila++;

                if($fila == 1) {
                    //$this->_cabeceiras = $datos;
                }
                else {
                    $id_mesa = str_pad($datos[0], 2, '0', STR_PAD_LEFT)."-".str_pad($datos[1], 3, '0', STR_PAD_LEFT);
                    $barrios[$id_mesa] = ['centro_votacion' => $datos[3], 'barrio' => $datos[4]];
                }
            }
        }

        return $barrios;
    }


    /**
     * Devolve barrios e IDs de mesas
     * 
     * @return ["Campus Sur"]=> array(4) { [0]=> string(6) "01-002", [1]=> string(6) "01-004",...},   ["Vista Alegre"]=>  array(1) { [0]=> string(6) "01-003" },...
     */
    public static function MesasPorBarrio($ficheiro) {
        $barrios = [];

        if (($gestor = fopen($ficheiro, "r")) !== FALSE) {
            while (($datos = fgetcsv($gestor, 4096, ";")) !== FALSE) {
                $fila++;

                if($fila == 1) {
                    //$this->_cabeceiras = $datos;
                }
                else {
                    $id_mesa = str_pad($datos[0], 2, '0', STR_PAD_LEFT)."-".str_pad($datos[1], 3, '0', STR_PAD_LEFT);
                    if(!in_array($id_mesa, $barrios[$datos[4]]) ) {
                        $barrios[$datos[4]][] = $id_mesa;
                    }
                }
            }
        }

        return $barrios;
    }
}