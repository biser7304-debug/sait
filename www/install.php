<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config.php';

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
    $pdo->beginTransaction();

    // --- Пользователи ---
    $stmt_user = $pdo->prepare("INSERT INTO users (username, role) VALUES (?, ?)");
    $stmt_user->execute(['as-biserov', 'admin']);
    $stmt_user->execute(['aa-admin', 'department']);
    $user_aa_admin_id = $pdo->lastInsertId();
    echo "  - Пользователи 'as-biserov' (admin) и 'aa-admin' (department) созданы.\n";

    // --- Подразделения ---
    $stmt_dep = $pdo->prepare("INSERT INTO departments (name, number_of_employees, sort_index, parent_id) VALUES (?, ?, ?, ?)");

    $stmt_dep->execute(['Центр', 100, 0, null]);
    $center_id = $pdo->lastInsertId();

    $stmt_dep->execute(['Офис 1', 60, 10, $center_id]);
    $office1_id = $pdo->lastInsertId();

    $stmt_dep->execute(['1 подразделение', 30, 11, $office1_id]);
    $stmt_dep->execute(['2 подразделение', 20, 12, $office1_id]);
    $stmt_dep->execute(['3 подразделение', 10, 13, $office1_id]);
    echo "  - Структура подразделений создана.\n";

    // --- Права доступа ---
    $stmt_perm = $pdo->prepare("INSERT INTO user_department_permissions (user_id, department_id) VALUES (?, ?)");
    $stmt_perm->execute([$user_aa_admin_id, $office1_id]);
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

    $pdo->commit();
    echo "SUCCESS: Данные успешно добавлены.\n";

    echo "\n--- УСТАНОВКА ТЕСТОВОЙ БД ЗАВЕРШЕНА ---\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("ОШИБКА УСТАНОВКИ БАЗЫ ДАННЫХ: " . $e->getMessage());
}
?>
