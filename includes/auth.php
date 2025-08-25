<?php
error_reporting(E_ALL);
ini_set("display_errors","On");

// Для отладки можно раскомментировать и задать имя пользователя
$_SERVER["PHP_AUTH_USER"] = "as-biserov";
//$_SERVER["PHP_AUTH_USER"] = "aa-admin";

// Получаем полное имя пользователя (например, login@domain), предоставленное веб-сервером
$kerberos_user = $_SERVER['PHP_AUTH_USER'] ?? null;

if (empty($kerberos_user)) {
    header('HTTP/1.1 401 Unauthorized');
    die('401 Не авторизован: для доступа к этому приложению требуется аутентификация Kerberos.');
}

// Парсим имя пользователя, чтобы получить часть перед символом '@'
$username_parts = explode('@', $kerberos_user);
$username = strtolower($username_parts[0]);

// Подключаем зависимости
require_once __DIR__ . '/../config.php';
require_once 'functions.php';

/**
 * Рекурсивно получает все ID дочерних подразделений для заданного ID родителя.
 */
function get_all_child_department_ids($pdo, $parent_id) {
    $children_ids = [];
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE parent_id = :parent_id");
    $stmt->execute(['parent_id' => $parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($children as $child_id) {
        $children_ids[] = $child_id;
        $children_ids = array_merge($children_ids, get_all_child_department_ids($pdo, $child_id));
    }
    return $children_ids;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        // Пользователь найден, устанавливаем основные данные.
        $USER['loggedin'] = true;
        $USER['id'] = $user_data['id'];
        $USER['username'] = $user_data['username'];
        $USER['role'] = $user_data['role'];
        $USER['department_ids'] = []; // Инициализируем как пустой массив

        // Если пользователь не администратор, получаем список его департаментов.
        if ($user_data['role'] === 'department') {
            // 1. Получаем явно назначенные права
            $stmt_perms = $pdo->prepare("SELECT department_id FROM user_department_permissions WHERE user_id = :user_id");
            $stmt_perms->execute(['user_id' => $user_data['id']]);
            $explicit_ids = $stmt_perms->fetchAll(PDO::FETCH_COLUMN);

            $all_accessible_ids = $explicit_ids;

            // 2. Для каждого явного права рекурсивно получаем все дочерние
            foreach ($explicit_ids as $dep_id) {
                $child_ids = get_all_child_department_ids($pdo, $dep_id);
                if (!empty($child_ids)) {
                    $all_accessible_ids = array_merge($all_accessible_ids, $child_ids);
                }
            }

            // 3. Сохраняем уникальные ID в сессию
            $USER['department_ids'] = array_unique($all_accessible_ids);
        }

    } else {
        header('HTTP/1.1 403 Forbidden');
        die('403 Запрещено: Ваша учетная запись (' . htmlspecialchars($username) . ') аутентифицирована, но не авторизована для использования этого приложения. Пожалуйста, свяжитесь с администратором.');
    }

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log("Ошибка проверки авторизации: " . $e->getMessage());
    die("Во время проверки авторизации произошла критическая ошибка. Пожалуйста, повторите попытку позже.");
}
?>
