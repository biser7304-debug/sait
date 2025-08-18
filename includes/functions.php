<?php
error_reporting(E_ALL);
ini_set("display_errors","On");
/**
 * Централизованная функция для записи событий в базу данных.
 *
 * Эта функция предназначена для подключения и использования везде, где требуется логирование действий.
 * Она зависит от глобального объекта $pdo из config.php и $USER['username'].
 *
 * @param string $action Описание действия для логирования.
 */
function log_event($action) {

    // Доступ к глобальному объекту PDO и имени пользователя из сессии
    global $pdo, $USER;
    
    $username = $USER['username'] ?? 'system';

    // Убеждаемся, что объект PDO доступен
    if (!isset($pdo)) {
        error_log("log_event не удался: объект PDO недоступен.");
        return;
    }

    try {
        $sql = "INSERT INTO logs (username, action) VALUES (:username, :action)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'action' => $action
        ]);
    } catch (PDOException $e) {
        // Если логирование не удалось, мы не хотим прерывать текущее действие пользователя.
        // Вместо этого мы записываем ошибку в лог ошибок сервера для проверки администратором.
        error_log("Не удалось записать событие '{$action}' для пользователя '{$username}': " . $e->getMessage());
    }
}


