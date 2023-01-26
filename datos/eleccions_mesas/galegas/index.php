<?php
/**
 * Agrupa resultados num so ficheiros de varios .csv com distintas cabeceiras dos resultados electorais das galegas de 2020
 * (https://abertos.xunta.gal/catalogo/administracion-publica/-/dataset/0426/eleccions-parlamento-galicia-resultados-2020)
 * 
 * Uso: $ php index.php     ==> xenera um ficheiro.csv co total
 */
if ( php_sapi_name() !== 'cli' ) {
    die("Not allowed");
}

//
$cabeceiras = $cabeceirasTmp = $datos = [];

// indice da key a partir da qual estam os partidos politicos, que podem vir desordeados (ver ficheiros para comprobalo)
$lugarComezoPartidosPoliticos = 12;


// lemos os .csv do diretorio atual
/*
alex@vosjod:~/Desktop/Ames politica/BNG_Ames/MAPAS_tereborace/mapas_toda_a_comarca_2022-07-03/datos_galegas_2020_venhem_por_provincias$ ls -1 Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_*.csv
Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_ACoruna.csv
Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_Lugo.csv
Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_Ourense.csv
Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_Pontevedra.csv
 */
$files = glob("Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_*.csv");



// Ficheiro a ficheiro:
//  1) revisamos as cabeceiras de cada ficheiro, onde venhem datos estatíticos e o desglose por partidos e engadimos estas cabeceiras a um array. Se no ficheiro tratado
//     hai algun partido que nom esta, porque se presenta nesa provincia e nom nas outras anteriores, engadimolo
//  2) recolhemos todos os datos, agrupados por cabeceiras
// Posteriormente:
//  3) damos umha segunda volta a todos os datos, colocando segundo a orde do seu partido politico (cabeceira), para que saiam bem agrupados
foreach($files as $numFile => $filepath) {
    $fila = 0;

    // echo $filepath.": \n";
    if (($gestor = fopen($filepath, "r")) !== FALSE) {
        // aumento de 4096 a 8192 porque em certos ficheiros das galegas nom chegaba (p.e. ELECCIONS_PARLAMENTO_GALICIA_2012_MESAS.csv)
        while (($data = fgetcsv($gestor, 8192, ";")) !== FALSE) {
            $fila++;
        
            if($fila == 1) {
                $data = array_map('trim', $data);

                if($numFile == 0) {
                    $cabeceiras = $data;
                    continue;
                }
                else {
                    // para conservar as cabeceiras e tratalas nos textos no else{}
                    $cabeceirasTmp = $data;

                    // workaround: se a cabeceira nom e dum partido, pode ser um erro de codificacom nos datos do comezo (codigos concelho, provincia, censo, etc.)
                    //             polo que creo dous arrays temporais com so os partidos politicos, comparo estes arrays e engado os que faltem ao primeiro (cabeceiras), 
                    //              se fai falha. Se no segundo array hai menos partidos (porque nesa provincia nom se presentarom tantos), 
                    $lugarCabeceira = 0;

                    foreach($data as $k => $d) {
                        if($lugarCabeceira <= $lugarComezoPartidosPoliticos) {
                            $lugarCabeceira++;

                            continue;
                        }

                        // workaround: no escrutinio de OU e Pontevedra o BNG aparece sem pontos no acronimo
                        //              asi que miro se ja esta engadida a cabeceira, se nom vouna engadir
                        if($d == 'BNG' && !in_array($d, $cabeceiras)) $d = 'B.N.G.';

                        if(!in_array($d, $cabeceiras)) {
                            $cabeceiras[] = $d;
                        }
                    }
                }
            }
            else {
                // workaround: ao final engade "Residentes", que nom imos gardar porque nom hai mapa onde reflexalos, 
                //                  e "Total", que nom imos gardar porque desquadra a infor
                if(strtoupper($data[0]) == 'TOTAL') continue;
                if(strtoupper($data[1]) == 990) continue;       // colexio de Residentes Ausentes

                // na primeira iteracion do primeiro ficheiro so engadimos
                if($numFile == 0) {
                    $datos[] = $data;
                    continue;
                }
                else {
                    if(@!$totalCabeceiras) $totalCabeceiras = count($cabeceiras);
                    $datosTmp = array_fill(0, $totalCabeceiras, 0);     // precargo keys com '0'

                    // workaround: se a cabeceira nom e dum partido, pode ser um erro de codificacom nos datos do comezo (codigos concelho, provincia, censo, etc.)
                    //             polo que creo dous arrays temporais com so os partidos politicos, comparo estes arrays e engado os que faltem ao primeiro (cabeceiras), 
                    //              se fai falha. Se no segundo array hai menos partidos (porque nesa provincia nom se presentarom tantos), 
                    $lugarCabeceira = 0;

                    foreach($data as $k => $d) {
                        if($lugarCabeceira <= $lugarComezoPartidosPoliticos) {
                            $lugarCabeceira++;

                            $datosTmp[$k] = $d;
                        }
                        else {
                            // aqui comezam os partidos: hai que buscar em cabeceiras a posicion do partido actual e ponhelo ai
                            $nomePartido = $cabeceirasTmp[$k];

                            // workaround: no escrutinio de OU e Pontevedra o BNG aparece sem pontos no acronimo
                            //              asi que miro se ja esta engadida a cabeceira, se nom vouna engadir
                            if($nomePartido == 'BNG') $nomePartido = 'B.N.G.';

                            $posicionCabeceira = array_search($nomePartido, $cabeceiras);
                            $datosTmp[$posicionCabeceira] = $d;
                        }
                    }

                    // ordeo e completo as keys que faltem
                    ksort($datosTmp);
                    $datos[] = $datosTmp;
                }
            }
        }
    }
}


$fp = fopen('ficheiro.csv', 'w');

fputcsv($fp, $cabeceiras, ';', '"');

foreach ($datos as $campos) {
    fputcsv($fp, $campos, ';', '"');
}
fclose($fp);