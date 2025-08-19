<?php
// Убедимся, что скрипт не запускается на рабочем сервере
if (getenv('APP_ENV') === 'production') {
    die("Этот скрипт не может быть запущен в производственной среде.");
}

header('Content-Type: text/plain; charset=utf-8');

require_once 'config.php';

try {
    echo "Запуск установки базы данных для PostgreSQL...\n\n";

    // SQL-запросы для создания таблиц
    $sql = "
        -- Таблица для подразделений
        CREATE TABLE IF NOT EXISTS departments (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) UNIQUE NOT NULL,
            number_of_employees INTEGER, -- Количество сотрудников, может быть пустым
            parent_id INTEGER REFERENCES departments(id) ON DELETE SET NULL -- Для древовидной структуры
        );

        -- Таблица пользователей
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            role VARCHAR(50) NOT NULL CHECK (role IN ('admin', 'department'))
        );

        -- Новая таблица для связи пользователей и подразделений (многие-ко-многим)
        CREATE TABLE IF NOT EXISTS user_department_permissions (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            department_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, department_id)
        );

        -- Таблица для ежедневных статусов
        CREATE TABLE IF NOT EXISTS statuses (
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
            UNIQUE (department_id, report_date)
        );

        -- Таблица для логов
        CREATE TABLE IF NOT EXISTS logs (
            id SERIAL PRIMARY KEY,
            log_time TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            username VARCHAR(100),
            action TEXT NOT NULL
        );

        -- Таблица для настроек
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT
        );
    ";

    // Выполнение создания таблиц
    $pdo->exec($sql);
    echo "УСПЕХ: Все таблицы успешно созданы или уже существуют.\n";

    // --- Вставка данных по умолчанию ---

    // Добавление администраторов по умолчанию
    echo "\nОбработка администраторов по умолчанию...\n";
    $admins = ['as-biserov', 'as-karpov'];
    $stmt_admins = $pdo->prepare("INSERT INTO users (username, role) VALUES (:username, 'admin') ON CONFLICT (username) DO NOTHING");

    foreach ($admins as $admin) {
        $stmt_admins->execute(['username' => $admin]);
        if ($stmt_admins->rowCount() > 0) {
            echo "  - Администратор '{$admin}' создан.\n";
        } else {
            echo "  - Администратор '{$admin}' уже существует.\n";
        }
    }

    // Добавление настроек по умолчанию
    echo "\nОбработка настроек по умолчанию...\n";
    $settings = [
        'app_title' => 'Учет Статуса Сотрудников',
        'app_logo' => '',
        'color_scheme' => 'default',
        'custom_colors' => '{}'
    ];
    $stmt_settings = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON CONFLICT (setting_key) DO NOTHING");

    foreach ($settings as $key => $value) {
        $stmt_settings->execute(['key' => $key, 'value' => $value]);
         if ($stmt_settings->rowCount() > 0) {
            echo "  - Настройка '{$key}' создана.\n";
        } else {
            echo "  - Настройка '{$key}' уже существует.\n";
        }
    }

    echo "\n--------------------------------------------------\n";
    echo "УСПЕХ: Установка базы данных завершена.\n";
    echo "ВАЖНО: Пожалуйста, удалите этот файл (install.php) с вашего сервера в целях безопасности.\n";

} catch (PDOException $e) {
    die("ОШИБКА УСТАНОВКИ БАЗЫ ДАННЫХ: " . $e->getMessage());
}
?>
