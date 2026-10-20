<?php
// 1. Inicia a sessão para a poder destruir
session_start();

// 2. Limpa todas as variáveis de sessão
$_SESSION = array();

// 3. Se quiseres destruir o cookie de sessão também.
// Nota: Isto destruirá a sessão, e não apenas os dados da sessão!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destrói a sessão.
session_destroy();

// 5. Redireciona para o login
header("Location: login.php");
exit;
