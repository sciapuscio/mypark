<?php
$podId = 'Desa';
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=mypark", "desa", "Vaporesso77!");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function query2($sql, $params = [], $single = false) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ($single) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function errorInfo() {
        return $this->pdo->errorInfo();
    }

    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function executeUpdate($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            // Puedes registrar el error aquí si es necesario
            file_put_contents('query_error_log.txt', "Error ejecutando update: " . $e->getMessage() . "\n", FILE_APPEND);
            return 0;
        }
    }
    

    // Transaction related methods
    public function beginTransaction() {
        $logPath = "milog.log";
        try {
            //file_put_contents($logPath, "Intentando iniciar transacción.\n", FILE_APPEND);
            $result = $this->pdo->beginTransaction();
            if (!$result) {
                throw new Exception("No se pudo iniciar la transacción");
            }
            //file_put_contents($logPath, "Transacción iniciada con éxito.\n", FILE_APPEND);
            return $result;
        } catch (Exception $e) {
            //file_put_contents($logPath, "Error al iniciar transacción: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
    

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function escapeString($value) {
        return $this->pdo->quote($value);
    }

}
?>
