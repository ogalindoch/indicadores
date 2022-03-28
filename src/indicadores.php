<?php

namespace euroglas\indicadores;

class indicadores implements \euroglas\eurorest\restModuleInterface
{
    // Nombre oficial del modulo
    public function name() { return "indicadores"; }

    // Descripcion del modulo
    public function description() { return "Acceso a la informacion de Indicadores"; }

    // Regresa un arreglo con los permisos del modulo
    // (Si el modulo no define permisos, debe regresar un arreglo vacío)
    public function permisos()
    {
        $permisos = array();

        // $permisos['_test_'] = 'Permiso para pruebas';

        return $permisos;
    }

    // Regresa un arreglo con las rutas del modulo
    public function rutas()
    {
        $items['/indicadores']['GET'] = array(
            'name' => 'Lista indicadores',
            'callback' => 'listaIndicadores',
            'token_required' => FALSE,
        );

        $items['/indicadores/[i:idIndicador]']['GET'] = array(
            'name' => 'Configuracion del indicador',
            'callback' => 'getIndicadorConfig',
            'token_required' => FALSE,
        );

        $items['/indicadores/[i:idIndicador]/datos']['GET'] = array(
            'name' => 'Datos historicos del indicador',
            'callback' => 'getIndicadorHistory',
            'token_required' => FALSE,
        );

        $items['/indicadores/[i:idIndicador]/valores']['GET'] = array(
            'name' => 'Valor actual del indicador',
            'callback' => 'getIndicadorValue',
            'token_required' => FALSE,
        );

        return $items;
    }

    public function listaIndicadores()  {
        $query = "SELECT idIndicador,Nombre,Descripcion,tipo FROM IndicadorConfig WHERE Activo=1";

        $dbRH = $this->connect_db("IndicadoresDB");

        $sth = $dbRH->query($query);

        if( $sth === false )
        {
            die( $dbRH->getLastError() );
        }

        //$datos = $sth->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        $datos = $sth->fetchAll( \PDO::FETCH_ASSOC);


        die( $this->formateaRespuesta($datos) );

    }
    public function getIndicadorConfig( $idIndicador )
    {
        $query = "SELECT idIndicador,Nombre,Descripcion,tipo FROM IndicadorConfig WHERE idIndicador=$idIndicador";

        $dbRH = $this->connect_db("IndicadoresDB");

        $sth = $dbRH->query($query);

        if( $sth === false )
        {
            die( $dbRH->getLastError() );
        }

        //$datos = $sth->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        //$datos = $sth->fetchAll( \PDO::FETCH_ASSOC);
        $datos = $sth->fetch( \PDO::FETCH_ASSOC );

        die( $this->formateaRespuesta($datos) );

    }
    public function getIndicadorHistory( $idIndicador )
    {
        $query = "SELECT * FROM IndicadorDatos WHERE idIndicador=$idIndicador";

        if( isset( $_REQUEST['intervalo'] ) )
        {
            $intervalo = str_replace(array('\'', '"'), '', $_REQUEST['intervalo'] );
            $query .= " AND Cuando >= DATE_SUB(NOW(), INTERVAL {$intervalo} ) ";
        }

        $query .= " ORDER BY idIndicadorDatos DESC ";

        if( isset( $_REQUEST['last'] ) )
        {
            $query .= " LIMIT {$_REQUEST['last']}";
        }

        echo $query;

        $dbRH = $this->connect_db("IndicadoresDB");

        $sth = $dbRH->query($query);

        if( $sth === false )
        {
            die( $dbRH->getLastError() );
        }

        //$datos = $sth->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        $datos = $sth->fetchAll( \PDO::FETCH_ASSOC);


        die( $this->formateaRespuesta($datos) );

    }
    public function getIndicadorValue( $idIndicador )
    {
        $query = "SELECT * FROM IndicadorValor WHERE idIndicador=$idIndicador";

        $dbRH = $this->connect_db("IndicadoresDB");

        $sth = $dbRH->query($query);

        if( $sth === false )
        {
            die( $dbRH->getLastError() );
        }

        //$datos = $sth->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        //$datos = $sth->fetchAll( \PDO::FETCH_ASSOC);
        $datos = $sth->fetch( \PDO::FETCH_ASSOC );


        die( $this->formateaRespuesta($datos) );

    }

    /**
     * Define que secciones de configuracion requiere
     *
     * @return array Lista de secciones requeridas
     */
    public function requiereConfig()
    {
        $secciones = array();

        $secciones[] = 'dbaccess';

        return $secciones;
    }

    private $config = array();

    /**
     * Carga UNA seccion de configuración
     *
     * Esta función será llamada por cada seccion que indique "requiereConfig()"
     *
     * @param string $sectionName Nombre de la sección de configuración
     * @param array $config Arreglo con la configuracion que corresponde a la seccion indicada
     *
     */
    public function cargaConfig($sectionName, $config)
    {
        $this->config[$sectionName] = $config;
    }

    /**
     * Conecta a la Base de Datos
     */
    private function connect_db($dbKey)
    {
        $unaBD = null;

        if ($this->config && $this->config['dbaccess'])
        {
            $unaBD = new \euroglas\dbaccess\dbaccess($this->config['dbaccess']['config']);

            if( $unaBD->connect($dbKey) === false )
            {
                throw new Exception($unaBD->getLastError());
            }
        } else {
            throw new Exception("La BD no esta configurada");
        }

        return $unaBD;
    }

    function __construct()
    {
        $this->DEBUG = isset($_REQUEST['debug']);
    }


    private function formateaRespuesta($datos)
	{
		if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'JSON')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'JSON')!==false)
		)
		{
			header('content-type: application/json');
			return( json_encode( $datos ) );
		}
		else if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'CSV')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'CSV')!==false)
		)
		{
			$output = fopen("php://output",'w') or die("Can't open php://output");
			header("Content-Type:application/csv");
			foreach($datos as $dato) {
				if(is_array($dato))
				{
    				fputcsv($output, $dato);
				} else {
					fputs($output, $dato . "\n");
				}
			}
			fclose($output) or die("Can't close php://output");
			return;
			//return( json_encode( $datos ) );
		}
		else
		{
			// Formato no definido
			header('content-type: text/plain');
			return( print_r($datos, TRUE) );
		}
    }

        /**
     * Formatea el error usando estandar de HATEOAS
     * @param integer $code Codigo HTTP o Codigo de error interno
     * @param string  $userMessage Mensaje a mostrar al usuario (normalmente, con vocabulario sencillo)
     * @param string  $internalMessage Mensaje para usuarios avanzados, posiblemente con detalles técnicos
     * @param string  $moreInfoUrl URL con una explicación del error, o documentacion relacionada
     * @return string Error en un arreglo, para ser enviado al cliente usando json_encode().
    */
    private function reportaErrorUsandoHateoas($code=400, $userMessage='', $internalMessage='',$moreInfoUrl=null)
    {
        $hateoas = array(
            'links' => array(
                'self' => $_SERVER['REQUEST_URI'],
            ),
        );
        $hateoasErrors = array();
        $hateoasErrors[] = array(
            'code' => $code,
            'userMessage' => $userMessage,
            'internalMessage' => $internalMessage,
            'more info' => $moreInfoUrl,
        );

        $hateoas['errors'] = $hateoasErrors;

        // No lo codificamos con Jason aqui, para que el cliente pueda agregar cosas mas facilmente
        return($hateoas);
    }

    private $DEBUG = false;
}