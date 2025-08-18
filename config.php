<?php
/*
 * Конфигурация базы данных для PostgreSQL с использованием PDO.
 *
 * Пожалуйста, заполните данные для подключения к вашей базе данных ниже.
 */

// Имя хоста для сервера базы данных.
define('DB_HOST', 'localhost');

// Номер порта для сервера PostgreSQL. По умолчанию 5432.
define('DB_PORT', '5432');

// Название базы данных.
define('DB_NAME', 'rashod');

// Имя пользователя для подключения к базе данных.
define('DB_USER', 'postgres');

// Пароль для подключения к базе данных.
define('DB_PASSWORD', '1');


// --- Не редактировать ниже этой строки ---

// DSN (Data Source Name) для PDO
$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;

// Опции PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Генерировать исключения при ошибках
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Получать результаты в виде ассоциативных массивов
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Использовать настоящие подготовленные выражения
];

try {
    // Создаем экземпляр PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (PDOException $e) {
    // Если подключение не удалось, отображаем общее сообщение об ошибке.
    // В производственной среде следует логировать эту ошибку, а не отображать ее.
    header('Content-Type: text/plain; charset=utf-8');
    die("Ошибка подключения к базе данных. Пожалуйста, проверьте настройки в файле config.php и убедитесь, что сервер PostgreSQL доступен.\n\n" . $e->getMessage());
}
?>
