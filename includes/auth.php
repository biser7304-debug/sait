<?php
error_reporting(E_ALL);
ini_set("display_errors","On");

//echo $_SERVER["PHP_AUTH_USER"];
//$_SERVER["PHP_AUTH_USER"] = "mr-morozov";

//if (in_array($_SERVER['REMOTE_ADDR'], ["116.128.8.123", "116.128.8.24"]))
//{
//    $_SERVER["PHP_AUTH_USER"] = "mr-morozov";
//}


//echo $_SERVER["PHP_AUTH_USER"];



// Запускаем сессию, если она еще не запущена
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// --- Аутентификация Kerberos и парсинг пользователя ---

// В целях разработки/тестирования, если PHP_AUTH_USER не установлен сервером,
// вы можете симулировать его, раскомментировав одну из строк ниже.
// В реальной производственной среде с Kerberos этот блок 'if' можно удалить.
// if (!isset($_SERVER['PHP_AUTH_USER'])) {
//     // Для тестирования как администратор:
//     // $_SERVER['PHP_AUTH_USER'] = 'as-biserov@domain.com';
// 
//     // Для тестирования как обычный пользователь (предполагается, что этот пользователь будет добавлен в БД с правами департамента):
//     // $_SERVER['PHP_AUTH_USER'] = 'testuser@domain.com';
// 
//     // Для тестирования как неавторизованный пользователь:
//     // $_SERVER['PHP_AUTH_USER'] = 'unknown@domain.com';
// 
//     // Если все еще не установлено, по умолчанию используем известного администратора для разработки.
//     if (!isset($_SERVER['PHP_AUTH_USER'])) {
//         $_SERVER['PHP_AUTH_USER'] = 'as-karpov@domain.com';
//     }
// }

// Получаем полное имя пользователя (например, login@domain), предоставленное веб-сервером
$kerberos_user = $_SERVER['PHP_AUTH_USER'] ?? null;

if (empty($kerberos_user)) {
    // Этот случай в идеале должен обрабатываться конфигурацией веб-сервера (например, AuthType Kerberos в Apache),
    // которая не должна разрешать доступ к скрипту без аутентификации. Это запасной вариант.
    header('HTTP/1.1 401 Unauthorized');
    die('401 Не авторизован: для доступа к этому приложению требуется аутентификация Kerberos.');
}

// Парсим имя пользователя, чтобы получить часть перед символом '@'
$username_parts = explode('@', $kerberos_user);
$username = strtolower($username_parts[0]); // Используем нижний регистр для единообразия

// --- Авторизация и управление сессиями ---

// Проверяем, активна ли уже сессия и совпадает ли имя пользователя.
// Это позволяет избежать обращения к базе данных при каждой загрузке страницы для уже авторизованного пользователя.
// if (isset($USER['loggedin']) && $USER['loggedin'] === true && isset($USER['username']) && $USER['username'] === $username) {
//     // Пользователь уже аутентифицирован и авторизован в этой сессии.
//     return;
// }

// Если для этого пользователя нет активной сессии, мы должны запросить базу данных, чтобы получить его роль.
// Этот код будет выполняться только один раз за сессию.
require_once __DIR__ . '/../config.php'; // Убеждаемся, что объект $pdo доступен
require_once 'functions.php';
try {
    $stmt = $pdo->prepare("SELECT username, role, department_id FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        // Пользователь найден в нашей базе данных. Авторизуем его, создав сессию.
        //session_regenerate_id(true); // Регенерируем ID сессии для предотвращения атак фиксации сессии
//  echo $_SERVER['PHP_AUTH_USER'] . " 6 username=" . $user_data['role'];
        $USER['loggedin'] = true;
        $USER['username'] = $user_data['username'];
        $USER['role'] = $user_data['role'];
        $USER['department_id'] = $user_data['department_id'];

    } else {
        // Пользователь аутентифицирован через Kerberos, но не зарегистрирован в базе данных нашего приложения.
        // Следовательно, он не авторизован для использования приложения.
//         session_destroy(); // Очищаем любую частичную сессию
        header('HTTP/1.1 403 Forbidden');
        die('403 Запрещено: Ваша учетная запись (' . htmlspecialchars($username) . ') аутентифицирована, но не авторизована для использования этого приложения. Пожалуйста, свяжитесь с администратором.');
    }
   ;

} catch (PDOException $e) {
    // Это произойдет, если база данных не работает или произошла ошибка запроса.
    //session_destroy();
    header('HTTP/1.1 500 Internal Server Error');
    error_log("Ошибка проверки авторизации: " . $e->getMessage()); // Логируем фактическую ошибку
    die("Во время проверки авторизации произошла критическая ошибка. Пожалуйста, повторите попытку позже.");
}
