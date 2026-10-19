<?php
// config.php — Versão Supabase

$host   = 'db.iezmybjzefaodyiztfva.supabase.co';
$port   = '5432';
$dbname = 'postgres';
$user   = 'postgres';
$pass   = 'A-TUA-PASSWORD-AQUI'; // ⚠️ Substitui pela password que definiste no Supabase

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Erro na ligação à base de dados: " . $e->getMessage());
}

// ---------------------------------------------------------
// SISTEMA DE SESSÕES NA BASE DE DADOS (Para o Supabase/Vercel)
// ---------------------------------------------------------
class SessionHandlerDB {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function open($path, $name) { return true; }
    public function close() { return true; }
    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ? $r['data'] : '';
    }
    public function write($id, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (id, data, timestamp) VALUES (?, ?, ?)
            ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, timestamp = EXCLUDED.timestamp
        ");
        $stmt->execute([$id, $data, time()]);
        return true;
    }
    public function destroy($id) {
        $this->pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$id]);
        return true;
    }
    public function gc($max) {
        $this->pdo->prepare("DELETE FROM sessions WHERE timestamp < ?")->execute([time() - $max]);
        return true;
    }
}

$handler = new SessionHandlerDB($pdo);
session_set_save_handler(
    [$handler, 'open'], [$handler, 'close'],
    [$handler, 'read'], [$handler, 'write'],
    [$handler, 'destroy'], [$handler, 'gc']
);
session_start();
