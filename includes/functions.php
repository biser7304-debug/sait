<?php
error_reporting(E_ALL);
ini_set("display_errors","On");
/**
 * Централизованная функция для записи событий в базу данных.
 *
 * @param string $action Описание действия для логирования.
 */
function log_event($action) {
    global $pdo, $USER;
    $username = $USER['username'] ?? 'system';

    if (!isset($pdo)) {
        error_log("log_event не удался: объект PDO недоступен.");
        return;
    }

    try {
        $sql = "INSERT INTO logs (username, action) VALUES (:username, :action)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username, 'action' => $action]);
    } catch (PDOException $e) {
        error_log("Не удалось записать событие '{$action}' для пользователя '{$username}': " . $e->getMessage());
    }
}

/**
 * Рекурсивно строит иерархическое дерево из плоского массива.
 * Включена защита от циклических зависимостей.
 *
 * @param array $elements Массив элементов, каждый из которых должен содержать 'id' и 'parent_id'.
 * @param mixed|null $parentId ID родителя, с которого начинать построение.
 * @param array $path Массив для отслеживания пути и предотвращения циклов.
 * @return array Массив узлов дерева.
 */
function build_tree(array $elements, $parentId = null, array $path = []) {
    $branch = [];
    if ($parentId !== null) {
        if (in_array($parentId, $path)) {
            error_log("Обнаружена циклическая зависимость в иерархии подразделений с ID: " . $parentId);
            return []; // Прерываем рекурсию
        }
        $path[] = $parentId;
    }

    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = build_tree($elements, $element['id'], $path);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

/**
 * Рекурсивно отображает иерархическое дерево в виде строк таблицы HTML для страницы администратора.
 *
 * @param array $nodes Массив узлов дерева.
 * @param int $level Уровень вложенности для отступов.
 */
function display_tree($nodes, $level = 0) {
    global $pdo;
    foreach ($nodes as $node) {
        $parent_name = 'N/A';
        if ($node['parent_id']) {
            $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = :id");
            $stmt->execute(['id' => $node['parent_id']]);
            $parent_name = $stmt->fetchColumn() ?: 'N/A';
        }

        echo '<tr>';
        echo '<td>' . $node['id'] . '</td>';
        echo '<td>' . str_repeat('&emsp;', $level) . htmlspecialchars($node['name']) . '</td>';
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

/**
 * Проверяет корректность значения количества сотрудников.
 *
 * @param PDO $pdo PDO объект.
 * @param int $department_id ID редактируемого департамента (0 для нового).
 * @param int|null $parent_id ID родителя.
 * @param int|null $number_of_employees Новое количество сотрудников.
 * @return bool|string True если валидация пройдена, иначе строка с ошибкой.
 */
function validate_employee_count($pdo, $department_id, $parent_id, $number_of_employees) {
    if ($parent_id) {
        $stmt_parent = $pdo->prepare("SELECT name, number_of_employees FROM departments WHERE id = :id");
        $stmt_parent->execute(['id' => $parent_id]);
        $parent = $stmt_parent->fetch();

        if ($parent && $parent['number_of_employees'] !== null) {
            $stmt_children = $pdo->prepare("SELECT SUM(number_of_employees) FROM departments WHERE parent_id = :parent_id AND id != :current_id");
            $stmt_children->execute(['parent_id' => $parent_id, 'current_id' => $department_id]);
            $children_sum = (int)$stmt_children->fetchColumn();

            $total_children_sum = $children_sum + (int)$number_of_employees;

            if ($total_children_sum > (int)$parent['number_of_employees']) {
                return "Ошибка: Сумма сотрудников дочерних подразделений ({$total_children_sum}) превышает количество в родительском ('{$parent['name']}', {$parent['number_of_employees']}).";
            }
        }
    }

    if ($department_id) {
        $stmt_children_sum = $pdo->prepare("SELECT SUM(number_of_employees) FROM departments WHERE parent_id = :id");
        $stmt_children_sum->execute(['id' => $department_id]);
        $children_sum_for_current = (int)$stmt_children_sum->fetchColumn();

        if ($number_of_employees !== null && $children_sum_for_current > (int)$number_of_employees) {
            return "Ошибка: Количество сотрудников ({$number_of_employees}) не может быть меньше суммы в дочерних ({$children_sum_for_current}).";
        }
    }

    return true;
}
?>
