<?php
session_start();

$host   = 'localhost';
$dbname = '11TGPSIBD_13';
$user   = 'root';
$pass   = '';

ini_set('extension', 'pgsql.so');        // Algumas versões do PHP suportam, outras não
ini_set('extension', 'pdo_pgsql.so');


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na ligação à base de dados: " . $e->getMessage());
}