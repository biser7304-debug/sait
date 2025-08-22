<?php
header('Content-Type: text/plain; charset=utf-8');
require_once 'config.php';

try {
    echo "--- НАЧАЛО УСТАНОВКИ ТЕСТОВОЙ БД ---\n\n";

    // 1. Удаление старых таблиц в правильном порядке
    echo "1. Удаление существующих таблиц...\n";
    $pdo->exec("DROP TABLE IF EXISTS statuses CASCADE;");
    $pdo->exec("DROP TABLE IF EXISTS user_department_permissions CASCADE;");
    $pdo->exec("DROP TABLE IF EXISTS users CASCADE;");
    $pdo->exec("DROP TABLE IF EXISTS departments CASCADE;");
    $pdo->exec("DROP TABLE IF EXISTS logs CASCADE;");
    $pdo->exec("DROP TABLE IF EXISTS settings CASCADE;");
    echo "SUCCESS: Таблицы успешно удалены.\n\n";

    // 2. Создание таблиц заново
    echo "2. Создание структуры таблиц...\n";
    $sql_create_tables = "
        CREATE TABLE departments (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) UNIQUE NOT NULL,
            number_of_employees INTEGER,
            parent_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
            sort_index INTEGER DEFAULT 0
        );

        CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            role VARCHAR(50) NOT NULL CHECK (role IN ('admin', 'department'))
        );

        CREATE TABLE user_department_permissions (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            department_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, department_id)
        );

        CREATE TABLE statuses (
            id SERIAL PRIMARY KEY,
            department_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
            report_date DATE NOT NULL,
            present INTEGER NOT NULL DEFAULT 0,
            on_duty INTEGER NOT NULL DEFAULT 0,
            trip INTEGER NOT NULL DEFAULT 0,
            vacation INTEGER NOT NULL DEFAULT 0,
            sick INTEGER NOT NULL DEFAULT 0,
            other INTEGER NOT NULL DEFAULT 0,
            notes TEXT,
            is_admin_override BOOLEAN NOT NULL DEFAULT FALSE,
            UNIQUE (department_id, report_date)
        );

        CREATE TABLE logs (
            id SERIAL PRIMARY KEY,
            log_time TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            username VARCHAR(100),
            action TEXT NOT NULL
        );

        CREATE TABLE settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT
        );
    ";
    $pdo->exec($sql_create_tables);
    echo "SUCCESS: Структура таблиц успешно создана.\n\n";

    // 3. Наполнение данными
    echo "3. Наполнение базы тестовыми данными...\n";

    // --- Пользователи ---
    $pdo->exec("INSERT INTO users (username, role) VALUES ('as-biserov', 'admin')");
    $pdo->exec("INSERT INTO users (username, role) VALUES ('aa-admin', 'department')");
    $user_aa_admin_id = $pdo->lastInsertId();
    echo "  - Пользователи 'as-biserov' (admin) и 'aa-admin' (department) созданы.\n";

    // --- Подразделения ---
    $pdo->exec("INSERT INTO departments (name, number_of_employees, sort_index) VALUES ('Центр', 100, 0)");
    $center_id = $pdo->lastInsertId();

    $pdo->exec("INSERT INTO departments (name, number_of_employees, sort_index, parent_id) VALUES ('Офис 1', 60, 10, {$center_id})");
    $office1_id = $pdo->lastInsertId();

    $pdo->exec("INSERT INTO departments (name, number_of_employees, sort_index, parent_id) VALUES ('1 подразделение', 30, 11, {$office1_id})");
    $pdo->exec("INSERT INTO departments (name, number_of_employees, sort_index, parent_id) VALUES ('2 подразделение', 20, 12, {$office1_id})");
    $pdo->exec("INSERT INTO departments (name, number_of_employees, sort_index, parent_id) VALUES ('3 подразделение', 10, 13, {$office1_id})");
    echo "  - Структура подразделений создана.\n";

    // --- Права доступа ---
    $pdo->exec("INSERT INTO user_department_permissions (user_id, department_id) VALUES ({$user_aa_admin_id}, {$office1_id})");
    echo "  - Пользователю 'aa-admin' предоставлены права на 'Офис 1'.\n";

    // --- Настройки ---
    $settings = [
        'app_title' => 'Учет Статуса Сотрудников (ТЕСТ)',
        'app_logo' => '',
        'color_scheme' => 'default',
        'custom_colors' => '{}'
    ];
    $stmt_settings = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
    foreach ($settings as $key => $value) {
        $stmt_settings->execute(['key' => $key, 'value' => $value]);
    }
    echo "  - Базовые настройки применены.\n";

    echo "\n--- УСТАНОВКА ТЕСТОВОЙ БД ЗАВЕРШЕНА ---\n";

} catch (PDOException $e) {
    die("ОШИБКА УСТАНОВКИ БАЗЫ ДАННЫХ: " . $e->getMessage());
}
?>
