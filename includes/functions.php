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

/**
 * Рекурсивно строит иерархическое дерево из плоского массива элементов.
 *
 * @param array $elements Плоский массив ассоциативных массивов, каждый из которых должен содержать 'id' и 'parent_id'.
 * @param mixed|null $parentId ID родительского элемента, с которого начинается построение ветки.
 * @return array Возвращает массив узлов дерева.
 */
function build_tree(array $elements, $parentId = null) {
    $branch = [];
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = build_tree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

/**
 * Рекурсивно отображает иерархическое дерево в виде строк таблицы HTML.
 *
 * @param array $nodes Массив узлов дерева (результат работы build_tree).
 * @param int $level Текущий уровень вложенности для визуального отступа.
 */
function display_tree($nodes, $level = 0) {
    global $pdo;
    foreach ($nodes as $node) {
        // Получаем имя родителя
        $parent_name = 'N/A';
        if ($node['parent_id']) {
            $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = :id");
            $stmt->execute(['id' => $node['parent_id']]);
            $parent_name = $stmt->fetchColumn() ?: 'N/A';
        }

        echo '<tr>';
        echo '<td>' . $node['id'] . '</td>';
        echo '<td>' . str_repeat('&mdash; ', $level) . htmlspecialchars($node['name']) . '</td>';
        echo '<td>' . htmlspecialchars($node['number_of_employees'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($parent_name) . '</td>';
        echo '<td>' . htmlspecialchars($node['sort_index'] ?? 0) . '</td>';
        echo '<td>';
        echo '<a href="departments.php?edit=' . $node['id'] . '" class="btn btn-sm btn-info" title="Редактировать"><i class="bi bi-pencil"></i></a> ';
        echo '<a href="departments.php?delete=' . $node['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Вы уверены?\');" title="Удалить"><i class="bi bi-trash"></i></a>';
        echo '</td>';
        echo '</tr>';
        if (isset($node['children'])) {
            display_tree($node['children'], $level + 1);
        }
    }
}

