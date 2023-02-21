<?php
// xera .csv por concehos
// Alexandre Espinosa Menor <aemenor@gmail.com>
//
// Requerimentos: phpoffice/phpspreadsheet
//       $ composer require phpoffice/phpspreadsheet
//
// le ficheiros dos subdiretorios e garda em PATH_TO_SAVE/CONCELHO.csv, umha eleccion por sheet

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


require_once('../../class/ComarcasClass.php');
require_once('../../class/datosElectorais.php');

// diretorio onde gardamos XLSX
$PATH_TO_SAVE = 'concellos';
$eleccions = [];

$sheetsConcellos = [];

// array dos concelhos, com key o Codigo INE de cada um
$concellos = ComarcasClass::concellosDisponhibles();
$concellos = array_map('eliminarAcentosTildesComas', $concellos);

// comezo, busco todos os .csv das eleccions
listFolderFiles('.', $eleccions);

foreach($eleccions as $ele) {
    echo "Eleccion: $ele\n";
    $fila = 0;
    list($dir, $tipo_eleccion, $ficheiro) = explode('/', $ele);

    $directorio_excel = $dir . DIRECTORY_SEPARATOR . $PATH_TO_SAVE . DIRECTORY_SEPARATOR;


    // escapamos ficheiros que nom tenham galiza|galicia (nas galegas hai ficheiros de provinzas)
    if(strpos(strtoupper($ficheiro), 'GALICIA') === false && strpos(strtoupper($ficheiro), 'GALIZA') === false) continue;

    // nome de sheet quita infor superflua de nome de ficheiro, para so quedar com tipo de eleccion e data
    $nome_sheet = str_ireplace('datos_', '', $ficheiro);
    $nome_sheet = str_ireplace('_galiza', '', $nome_sheet);
    $nome_sheet = str_ireplace('_MESAS', '', $nome_sheet);
    $nome_sheet = str_ireplace('ELECCIONS_PARLAMENTO_GALICIA_', 'galegas_', $nome_sheet);
    $nome_sheet = str_ireplace('Escrutinio_Definitivo_Eleccions_Parlamento_', 'galegas_', $nome_sheet);
    $nome_sheet = str_ireplace('.csv', '', $nome_sheet);
    

    $cabeceiras = [];
    $datos = [];
    // lectura ficheiro eleccions que estamos percorrendo
    if (($gestor = fopen($ele, "r")) !== FALSE) {
        while (($d = fgetcsv($gestor, 8192, ";")) !== FALSE) {
            $fila++;

            if($fila == 1) {
                $d = array_map('trim', $d);
                $cabeceiras= $d;
            }
            else {
                $idProvincia = $d[0];
                $idConcelho = $d[2];

                // eleccions con campos "descolocados"
                if($ele == './galegas/Escrutinio_Definitivo_Eleccions_Parlamento_2020_mesas_Galiza.csv') {
                    $idConcelho = $d[1];
                }

                $cp = $idProvincia.str_pad($idConcelho, 3, '0', STR_PAD_LEFT);
                $datos[$cp][] = $d;
            }
        }
    }
    else {
        die("Pete? $ele");
    }

    

    // ----  creamos ficheiros: creamos um csv por concelho e gardamolos direitamente como umha sheet no excel
    $cpConcello = null;
    foreach($datos as $cp => $datosConcello) {
        $concello = $concellos[$cp];
        if($concello == '') continue;

        // // debug
        // if($concello != 'Abegondo') continue;   // debug
        // if($tipo_eleccion != 'congreso') continue;


        echo "\tGardando CSV de $concello ...\n";

        $temp_file_csv = tempnam(sys_get_temp_dir(), $concello."_".$nome_sheet);

        $fp = fopen($temp_file_csv, 'w');
        fputcsv($fp, $cabeceiras);
        foreach($datosConcello as $k => $d) {
            fputcsv($fp, $d);
        }
        fclose($fp);


        // loxica de phpspreadsheet: gardar todos os csv de cada concelho num array por concelho e despois escribir. Deume problemas tentando fazelo aqui :-(
        // array dos concelhos cos seus csv
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        $spreadsheet = $reader->load($temp_file_csv);

        $sheetsConcellos[$concello][$nome_sheet] = $spreadsheet;

        // borramos CSV temporal
        unlink($temp_file_csv);
    }
}

echo "\n---\n";

// creamos aqui os .xlsx
if (!file_exists($directorio_excel)) mkdir($directorio_excel, 0755, true);

foreach($sheetsConcellos as $concello => $v) {
    echo "\tCreando XLSX de $concello ...\n";

    $spreadsheet2 = new Spreadsheet();
    $spreadsheet2->removeSheetByIndex(0);
    
    foreach($v as $nome_sheet => $spreadsheet) {
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            // cambiamos nome ao nome da eleccion e porque daba erro porque repite "Worksheet"
            $worksheet->setTitle($nome_sheet);
            $spreadsheet2->addExternalSheet($worksheet);

            // echo('Worksheet - ' . $worksheet->getTitle() . "\n");
        }

        $ficheiroExcel = $directorio_excel .DIRECTORY_SEPARATOR. $concello.'.xlsx';
        $writer = new Xlsx($spreadsheet2);
        $writer->save($ficheiroExcel);
    }
}


echo "\n----\nFin\n";



//----- functions
// reemprazar acentos, espacios e comas, polo de agora para os nomes dos concelhos
function eliminarAcentosTildesComas($cadea) {
    $cadea = iconv("utf-8", "ascii//TRANSLIT", $cadea);
    $cadea = str_replace("'", '', $cadea);      // o TRANSLIT provoca certos cambios, como ponher ' diante das vocais acentuadas: Vilardev'os, Vilamart'in_de_Valdeorras,...
    $cadea = str_replace('~', '', $cadea);      // mesmo cos Ñs, quedam como Valdovi~no

    $cadea = str_replace(' ', '_', $cadea);
    $cadea = str_replace(',', '', $cadea);
    return $cadea;
}

// recursivo, ripped from https://stackoverflow.com/questions/7121479/listing-all-the-folders-subfolders-and-files-in-a-directory-using-php
function listFolderFiles($dir, &$eleccions){
    $ffs = scandir($dir);

    unset($ffs[array_search('.', $ffs, true)]);
    unset($ffs[array_search('..', $ffs, true)]);

    // prevent empty ordered elements
    if (count($ffs) < 1)
        return;

    foreach($ffs as $ff){
        if(substr($ff, -4) == '.csv') $eleccions[] = "$dir/$ff";
        if(is_dir($dir.'/'.$ff)) listFolderFiles($dir.'/'.$ff, $eleccions);
    }
}