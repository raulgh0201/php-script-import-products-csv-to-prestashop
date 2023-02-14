d<?php
/**
* Modificar CSV de forma personalizada
*
* @author Raul Garcia Hidalgo
* @author raulgh0201@gmail.com
*/

//Fichero original donde se encuentran todos los productos sin modificar. Se tiene que poner dentro de la carpeta upload.
$ficheroOriginal='catalogo-grupo.csv';
//Fichero donde se introduciran todos los productos modificados. 
$ficheroModificado='productosModificados.csv';
//Fichero donde se introduciran todos los productos a importar.
$ficheroImportacion='productosImportacion.csv';
//$filename_log=_PS_ROOT_DIR_.$path_log.'log_importacion_productos_'.date("j_m_Y").".txt";


if (file_exists($ficheroOriginal)) {
    comprobarCSV($ficheroOriginal,$ficheroModificado,$ficheroImportacion);
} else  {
    echo "Fichero no existe, importación parada!";
}

 /**
 * Analiza el CSV y hace diferentes modificaciones sobre el
 *
 * @access public
 * @param array $fichero fichero original
 * @param array $ficheroModificado fichero donde van a ir todos los productos modificados
 * @param string $ficheroImportacion fichero donde van a ir los productos a importar
 */

public function comprobarCSV($fichero, $ficheroModificado, $ficheroImportacion){
    //Creamos un array donde iran los nombres de los campos
    $campos = array(); 
    //Convertimos el csv de productos a un array
    $arrayOriginal = process_csv($fichero);
    //Creamos un array donde iran los productos a importar
    $arrayImportacion = array();

    //Añadimos los distintos campos que necesitaremos
    $arrayOriginal[0][12] = "Subcategoria 1";
    array_push($arrayOriginal[0], "Precio");
    array_push($arrayOriginal[0], "Imagenes");
    $arrayOriginal[0][15] = "CategoriaPrincipal";

    //Inicializamos a false la variable de importar
    $importar = false;

    
    

    for ($fila = 0; $fila< sizeof($arrayOriginal); $fila++) {
        for ($j = 0; $j < sizeof($arrayOriginal[0]); $j++){
            if ($fila==0){       
                //Al ser la primera fila, guardamos el nombre de los campos en otro array a parte             
                $campos[$j] = $arrayOriginal[$fila][$j];                
            }else{
                for ($c = 0, $b = 0; $c < sizeof($campos); $c++,$b++){

                    switch ($campos[$c]) {   
                        //Comprobamos los diferentes campos dentro del array para saber a cual pertenece cada columna              
                        case "Stock":
                            $importar = comprobar_stock($arrayOriginal[$fila][$c]);              
                            break;
                        case "Nombre":
                            $valor = $arrayOriginal[$fila][$c];
                            //Buscamos el campo Multiplicador de cantidad para adaptar el nombre                             
                            $cantidad = buscar_Columna($arrayOriginal,$campos,$fila,"Multiplicador de cantidad");
                            //La funcion comprobará segun el valor de multiplicador de cantidad pasado si se debe modificar el nombre
                            $arrayOriginal[$fila][$c] = comprobar_nombre($valor,$cantidad);
                            break;                        
                        case "Precio distribuidor":
                            //Sustituimos la coma del string por un punto para así poder convertirlo en un float
                            $precioDist = (float)str_replace(",", ".", $arrayOriginal[$fila][$c]);                         
                            //Buscamos el campo Multiplicador de cantidad para adaptar el precio          
                            $cantidad = (float)buscar_Columna($arrayOriginal,$campos,$fila,"Multiplicador de cantidad");  
                            //La funcion comprobará segun el valor de multiplicador de cantidad pasado si se debe modificar el precio              
                            $precioVenta = calcular_Precio($precioDist,$cantidad); 
                            echo gettype($precioVenta);
                            //Guardamos en el array      
                           // echo $arrayOriginal[0][sizeof($campos)-2]   ;        
                            $arrayOriginal[$fila][13] = $precioVenta;
                            break; 
                        case "Imagenes":  
                            //Juntamos las imágenes
                            $imgPrincipal = buscar_Columna($arrayOriginal,$campos,$fila,"URL Imagen principal");  
                            $restoImagenes = buscar_Columna($arrayOriginal,$campos,$fila,"URL Resto de imagenes ");  
                            //Sustituimos ";" por "," para que prestashop nos coja las distintas imágenes
                            $restoImagenes = str_replace(";", ",", $restoImagenes);   
                            //Juntamos los dos campos y guardamos en el array 
                            $imagenes = $imgPrincipal. "," .$restoImagenes ;
                            $arrayOriginal[$fila][14] = $imagenes;                      
                            break;
                        case "Categoría":
                            //Ponemos la categoría Catálogo a todos los campos de la columna categoría
                            $arrayOriginal[$fila][15] = "Catálogo" ;      
                            $valor = $arrayOriginal[$fila][$c];   
                            //Si pertenece a esta subcategoria eliminamos la categoria superior                      
                            if(preg_match_all("/CLÁSICOS DEL DISEÑO/i", $valor)) {
                                $arrayOriginal[$fila][12] = "CLÁSICOS DEL DISEÑO";
                                $arrayOriginal[$fila][$c] = "";
                            }else{
                                $categoria = comprobar_subcategoria($valor);
                                $arrayOriginal[$fila][12] = $categoria;            

                            }
                            break;   
                                    
                    }
        
                }
                     
                break;
            }  
        }
       
        //Si al acabar la fila importar es verdadero y el precio de venta del producto es mayor a 0, guardamos la fila en arrayImportacion
        
        if($fila==0 || $importar && $precioVenta>0){
            array_push($arrayImportacion, $arrayOriginal[$fila]);
        }        
    }

    //Finalmente, guardamos en un nuevo csv todos los productos, modificados.
    write_csv($arrayOriginal, $ficheroModificado);   
    //Guardamos en un nuevo csv todos los productos a importar.
    write_csv($arrayImportacion, $ficheroImportacion);   
}

 /**
 * Busca el valor de una columna específica
 *
 * @access public
 * @param array $arrayOriginal array original del csv a importar
 * @param array $arrayCampos array con el nombre de los campos
 * @param integer $fila num de la fila
 * @param string $valorColumna nombre de la columna
 * @return resultado
 */

public function buscar_Columna($arrayOriginal,$arrayCampos,$fila,$valorColumna){
    for ($b = 0; $b < sizeof($arrayCampos); $b++){
        if ($arrayCampos[$b] == $valorColumna) { 
            $resultado = $arrayOriginal[$fila][$b];
            return $resultado;      
            break;
        }
    }
}

 /**
 * Comprobar campo Stock
 *
 * Este método se usa para comprobar el valor del campo de stock y si el producto se importará o no
 *
 * @access public
 * @param string $valor valor del campo Stock
 * @return importar
 */

public function comprobar_stock($valor){
    if (preg_match("/^( *con *stock| *en *stock| *precio *destacado| *$)/i", $valor) || is_null($valor)){
        return true;
    }    
    
    
    return false;
}

 /**
 * Devuelve la categoría la cual perteneze segun su subcategoría
 * @access public
 * @param string $valor valor del campo Categoría
 * @return Categoria
 */

public function comprobar_subcategoria($valor){
    switch($valor){
        case (preg_match_all("/banco|sillas|Sillones/i", $valor) ? true : false):
            return "SILLAS Y SILLONES";
            break;
        case (preg_match_all("/taburetes/i", $valor) ? true : false):
            return "TABURETES";
            break;
        case (preg_match_all("/lámparas|apliques de pared|cuadros/i", $valor) ? true : false):
            return "ILUMINACIÓN";
            break;
        case (preg_match_all("/Sofá|Butaca/i", $valor) ? true : false):
            return "SOFÁS, BUTACAS Y SILLONES";
            break;
        case (preg_match_all("/tablero|mesa|werzalit/i", $valor) ? true : false):
            return "MESAS";
            break;
        case (preg_match_all("/tumbona|conjuntos de exterior|especial terrazas/i", $valor) ? true : false):
            return "MUEBLES DE EXTERIOR";
            break;
        case (preg_match_all("/perchero|Mesas de Oficina - Office Home|Packs en oferta|Catering|Muebles metálicos/i", $valor) ? true : false):
            return "OFICINAS";
            break;
        case (preg_match_all("/Consolas|Diseños 100 % de cristal/i", $valor) ? true : false):
            return "MUEBLES DE CRISTAL";
            break;        
    }
 
    return "OTROS";
}
 /**
 * Modifica el nombre segun su cantidad
 * @access public
 * @param string $valor valor del campo Nombre
 * @param int $cantidad valor del campo Cantidad 
 * @return valor
 */

public function comprobar_nombre($valor,$cantidad){
    
    if ($cantidad>1){
        return "Paquete de $cantidad x " .$valor ;
    }    
    
    return $valor;
}

/**
* Calcula el precio
* @access public
* @param int $precioDist precio distribuidor
* @param int $cantidad cantidad
* @param int $precioVenta precio de venta
* @return precioVenta
*/

public function calcular_Precio($precioDist,$cantidad,$precioVenta = 0){
    
    if ($precioDist>0){
        
        $precioVenta = $precioDist*$cantidad*1.35*1.21;  
    }
    return  $precioVenta;
}

 /**
 * Coge los datos del CSV y los pasa a un array
 * @access public
 * @param string $file ruta del csv
 * @return Data
 */
public function process_csv($file) {

    $file = fopen($file, "r");
    $data = array();
    
    while (!feof($file)) {
        $data[] = fgetcsv($file,null,';');
    }
    
    fclose($file);
    return $data;
}

/**
 * Coge los datos del array y los pasa a un CSV
 * @access public
 * @param array $matriz_productos array donde estan los productos
 * @param string $ruta_csv ruta del csv
 * @return Data
 */

public function write_csv($matriz_productos, $ruta_csv) {
    if( !file_exists( $ruta_csv ) ); 
        file_put_contents( $ruta_csv, '');
    $outputBuffer = fopen($ruta_csv, 'w');
    foreach($matriz_productos as $n_linea => $linea) {
        fputcsv($outputBuffer, $linea, ';', '"');
    }
    fclose($outputBuffer);

}