<?php
include 'config/conexionDb.php';

function obtenerEstadia($token){
    $db = Database::getInstance();
    $sql = "SELECT * FROM estadias WHERE token = ?";
    $result = $db->query2($sql, [$token]);
    return $result;
}

function obtenerDatosCliente($idCliente){
    $db = Database::getInstance();
    $sql = "SELECT * FROM clientes WHERE id = ?";
    $result = $db->query2($sql, [$idCliente]);
    return $result;
}
?>