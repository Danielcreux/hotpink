<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class hotpink {

    public static function deJSON($entrada) {
        return new JSONConverter($entrada);
    }

    public static function deCSV($entrada, $separador = ",") {
        return new CSVConverter($entrada, $separador);
    }

    public static function deXML($entrada) {
        return new XMLConverter($entrada);
    }

    public static function deMySQL($servidor, $usuario, $contrasena, $basededatos, $tabla) {
        return new MySQLConverter($servidor, $usuario, $contrasena, $basededatos, $tabla);
    }

    public static function deSQLite($basededatos) {
        return new SQLiteConverter($basededatos);
    }
    
    #Verificamos que el login es autentico
    private static function verificarLogin() {
        if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
            header('Location: login.php');
            exit;
        }
    }

    #Comprueba si hay un usuario regitrado
    public static function login($usuario, $contrasena) {
        $archivo_usuarios = 'usuarios.json';
        if (!file_exists($archivo_usuarios)) {
            return false;
        }
        #Una vez decoficado el JSON nos permite que el usuario cargue
        $usuarios = json_decode(file_get_contents($archivo_usuarios), true);
        
        if (isset($usuarios[$usuario]) && password_verify($contrasena, $usuarios[$usuario])) {
            $_SESSION['autenticado'] = true;
            $_SESSION['usuario'] = $usuario;
            return true;
        }
        return false;
    }
    #Para cerrar sesion
    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    #Convertidor de archivo 
    public static function convertirArchivo($formato_salida, $nombre_archivo) {
        self::verificarLogin();
        
        $carpeta_salida = 'conversiones/' . $_SESSION['usuario'] . '/';
        if (!file_exists($carpeta_salida)) {
            mkdir($carpeta_salida, 0777, true);
        }

        $ruta_completa = $carpeta_salida . $nombre_archivo;
        
        $converter = self::deMySQL("localhost", "tienda", "prueba", "prueba", "productos");
        
        #Casos de formatos de salidas que tenemos dispponibles 
        switch($formato_salida) {
            case 'csv':
                $converter->aCSV($ruta_completa);
                break;
            case 'json':
                $converter->aJSON($ruta_completa);
                break;
            case 'xml':
                $converter->aXML($ruta_completa);
                break;
            case 'sqlite':
                $converter->aSQLite($ruta_completa);
                break;
        }
        return $ruta_completa;
    }
    
    #Registro de ususarios
    public static function registrarUsuario($usuario, $password, $confirmar_password) {
        if ($password !== $confirmar_password) {
            return false;
        }

        $archivo_usuarios = 'usuarios.json';
        $usuarios = [];
        
        if (file_exists($archivo_usuarios)) {
            $usuarios = json_decode(file_get_contents($archivo_usuarios), true) ?? [];
        }

        // Verificar si el usuario ya existe
        if (isset($usuarios[$usuario])) {
            return false;
        }

        // Agregar nuevo usuario
        $usuarios[$usuario] = password_hash($password, PASSWORD_DEFAULT);
        
        // Guardar usuarios actualizados
        file_put_contents($archivo_usuarios, json_encode($usuarios));
        
        return true;
    }
}
# Obtiene la información que procederá a convertirse

abstract class Conversor {
    protected $data;

    public function __construct($data) {
        $this->data = $data;
    }
  
    protected function getPDOConnection($dsn, $user, $password) {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
    
    protected function crearTabla($pdo, $nombreTabla, $columnas) {
        $definicionesColumnas = implode(", ", array_map(fn($col) => "`$col` TEXT", $columnas));
        $sql = "CREATE TABLE IF NOT EXISTS `$nombreTabla` ($definicionesColumnas)";
        $pdo->exec($sql);
    }

    protected function insertarDatos($pdo, $nombreTabla, $columnas, $data) {
        $marcadores = implode(", ", array_fill(0, count($columnas), "?"));
        $sql = "INSERT INTO `$nombreTabla` (" . implode(", ", array_map(fn($col) => "`$col`", $columnas)) . ") VALUES ($marcadores)";
        $stmt = $pdo->prepare($sql);

        foreach ($data as $fila) {
            $valores = array_map(fn($col) => $fila[$col] ?? null, $columnas);
            $stmt->execute($valores);
        }
    }

    protected function arrayToXML($data, &$xml) {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = "item";
            }
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXML($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }
}

# Formato JSON
class JSONConverter extends Conversor {
    public function aCSV($salida, $separador = ",") {
        $f = fopen($salida, "w");
        $columnas = array_keys($this->data[0]);
        fputcsv($f, $columnas, $separador);

        foreach ($this->data as $fila) {
            fputcsv($f, $fila, $separador);
        }
        fclose($f);
    }

    public function aXML($salida) {
        $xml = new SimpleXMLElement("<root/>");
        $this->arrayToXML($this->data, $xml);

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        file_put_contents($salida, $dom->saveXML());
    }

    public function aMySQL($servidor, $usuario, $contrasena, $basededatos, $tabla) {
        $pdo = $this->getPDOConnection("mysql:host=$servidor;dbname=$basededatos;charset=utf8mb4", $usuario, $contrasena);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $tabla, $columnas);
        $this->insertarDatos($pdo, $tabla, $columnas, $this->data);
    }

    public function aSQLite($archivoBD, $tabla) {
        $pdo = new PDO("sqlite:" . $archivoBD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $tabla, $columnas);
        $this->insertarDatos($pdo, $tabla, $columnas, $this->data);
    }
}

#Conversor a CSV 
class CSVConverter extends Conversor {
    private $separador;

    public function __construct($entrada, $separador = ",") {
        $this->separador = $separador;
        parent::__construct($this->parseCSV());
    }

    private function parseCSV() {
        $data = [];
        if (($handle = fopen($this->data, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, $this->separador);
            while (($row = fgetcsv($handle, 1000, $this->separador)) !== FALSE) {
                $data[] = array_combine($header, $row);
            }
            fclose($handle);
        }
        return $data;
    }

    public function aMySQL($servidor, $usuario, $contrasena, $basededatos, $tabla) {
        $pdo = $this->getPDOConnection("mysql:host=$servidor;dbname=$basededatos;charset=utf8mb4", $usuario, $contrasena);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $tabla, $columnas);
        $this->insertarDatos($pdo, $tabla, $columnas, $this->data);
    }

    public function aSQLite($archivoBD, $tabla) {
        $pdo = new PDO("sqlite:" . $archivoBD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $tabla, $columnas);
        $this->insertarDatos($pdo, $tabla, $columnas, $this->data);
    }

    public function aXML($salida) {
        $xml = new SimpleXMLElement("<root/>");
        $this->arrayToXML($this->data, $xml);

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        file_put_contents($salida, $dom->saveXML());
    }

    public function aJSON($salida) {
        file_put_contents($salida, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

# Formato XML
class XMLConverter extends Conversor {
    public function __construct($entrada) {
        parent::__construct($this->parseXML());
    }

    private function parseXML() {
        $xmlContent = file_get_contents($this->data);
        $xml = simplexml_load_string($xmlContent, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        return json_decode($json, true);
    }

    public function aMySQL($servidor, $usuario, $contrasena, $basededatos) {
        $nombreTabla = pathinfo($this->data, PATHINFO_FILENAME);
        $pdo = $this->getPDOConnection("mysql:host=$servidor;dbname=$basededatos;charset=utf8mb4", $usuario, $contrasena);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $nombreTabla, $columnas);
        $this->insertarDatos($pdo, $nombreTabla, $columnas, $this->data);
    }

    public function aSQLite($archivoBD) {
        $nombreTabla = pathinfo($this->data, PATHINFO_FILENAME);
        $pdo = new PDO("sqlite:" . $archivoBD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $nombreTabla, $columnas);
        $this->insertarDatos($pdo, $nombreTabla, $columnas, $this->data);
    }

    public function aCSV($salida, $separador = ",") {
        $f = fopen($salida, "w");
        $columnas = array_keys($this->data[0]);
        fputcsv($f, $columnas, $separador);

        foreach ($this->data as $fila) {
            fputcsv($f, $fila, $separador);
        }
        fclose($f);
    }

    public function aJSON($salida) {
        file_put_contents($salida, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

#Formato MySQL
class MySQLConverter extends Conversor {
    private $servidor;
    private $usuario;
    private $contrasena;
    private $basededatos;
    private $tabla;

    public function __construct($servidor, $usuario, $contrasena, $basededatos, $tabla) {
        $this->servidor = $servidor;
        $this->usuario = $usuario;
        $this->contrasena = $contrasena;
        $this->basededatos = $basededatos;
        $this->tabla = $tabla;
        parent::__construct($this->fetchData());
    }

    private function fetchData() {
        $pdo = $this->getPDOConnection("mysql:host=$this->servidor;dbname=$this->basededatos;charset=utf8mb4", $this->usuario, $this->contrasena);
        $sql = "SELECT * FROM $this->tabla";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aSQLite($archivoBD) {
        $pdo = new PDO("sqlite:" . $archivoBD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $this->tabla, $columnas);
        $this->insertarDatos($pdo, $this->tabla, $columnas, $this->data);
    }

    public function aCSV($salida, $separador = ",") {
        $f = fopen($salida, "w");
        $columnas = array_keys($this->data[0]);
        fputcsv($f, $columnas, $separador);

        foreach ($this->data as $fila) {
            fputcsv($f, $fila, $separador);
        }
        fclose($f);
    }

    public function aXML($salida) {
        $xml = new SimpleXMLElement("<root/>");
        $this->arrayToXML($this->data, $xml);

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        file_put_contents($salida, $dom->saveXML());
    }

    public function aJSON($salida) {
        file_put_contents($salida, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

# Formato Sqlite
class SQLiteConverter extends Conversor {
    private $basededatos;

    public function __construct($basededatos) {
        $this->basededatos = $basededatos;
        parent::__construct($this->fetchData());
    }

    private function fetchData() {
        $pdo = new PDO("sqlite:" . $this->basededatos);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT * FROM $this->tabla";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aMySQL($servidor, $usuario, $contrasena, $basededatos, $tabla) {
        $pdo = $this->getPDOConnection("mysql:host=$servidor;dbname=$basededatos;charset=utf8mb4", $usuario, $contrasena);
        $columnas = array_keys($this->data[0]);

        $this->crearTabla($pdo, $tabla, $columnas);
        $this->insertarDatos($pdo, $tabla, $columnas, $this->data);
    }

    public function aCSV($salida, $separador = ",") {
        $f = fopen($salida, "w");
        $columnas = array_keys($this->data[0]);
        fputcsv($f, $columnas, $separador);

        foreach ($this->data as $fila) {
            fputcsv($f, $fila, $separador);
        }
        fclose($f);
    }

    public function aXML($salida) {
        $xml = new SimpleXMLElement("<root/>");
        $this->arrayToXML($this->data, $xml);

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        file_put_contents($salida, $dom->saveXML());
    }

    public function aJSON($salida) {
        file_put_contents($salida, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

?>

