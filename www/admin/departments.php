<?php
require_once '../../layouts/admin/header.php';

// --- Инициализация переменных ---
$department_name = "";
$number_of_employees = "";
$parent_id = null;
$department_id = 0;
$update_mode = false;
$error_message = '';
$success_message = "";

if (isset($_COOKIE["success_message"])) {
   $success_message = $_COOKIE["success_message"];
   setcookie("success_message", "", time() - 3600);
}

// --- Получение всех департаментов для выпадающего списка ---
$all_departments_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$all_departments = $all_departments_stmt->fetchAll();

// --- Обработка POST-запросов для создания и обновления ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $department_name = trim($_POST['name']);
    $number_of_employees = !empty($_POST['number_of_employees']) ? (int)$_POST['number_of_employees'] : null;
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (empty($department_name)) {
        $error_message = "Название департамента не может быть пустым.";
    } else {
        // Обновление
        if (isset($_POST['update'])) {
            $department_id = $_POST['id'];
            if ($department_id == $parent_id) {
                $error_message = "Департамент не может быть родителем для самого себя.";
            } else {
                try {
                    $sql = "UPDATE departments SET name = :name, number_of_employees = :num_employees, parent_id = :parent_id WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'name' => $department_name,
                        'num_employees' => $number_of_employees,
                        'parent_id' => $parent_id,
                        'id' => $department_id
                    ]);
                    log_event("Обновлен департамент ID: {$department_id}");
                    setcookie("success_message", "Департамент успешно обновлен.", time() + 5);
                    header("Location: departments.php");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Ошибка обновления департамента: " . $e->getMessage();
                }
            }
        // Создание
        } elseif (isset($_POST['save'])) {
            try {
                $sql = "INSERT INTO departments (name, number_of_employees, parent_id) VALUES (:name, :num_employees, :parent_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $department_name,
                    'num_employees' => $number_of_employees,
                    'parent_id' => $parent_id
                ]);
                $new_id = $pdo->lastInsertId();
                log_event("Создан новый департамент '{$department_name}' (ID: {$new_id})");
                setcookie("success_message", "Департамент успешно создан.", time() + 5);
                header("Location: departments.php");
                exit();
            } catch (PDOException $e) {
                $error_message = "Ошибка создания департамента: " . $e->getMessage();
            }
        }
    }
}

// --- Обработка GET-запросов для редактирования и удаления ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Заполнение формы для редактирования
    if (isset($_GET['edit'])) {
        $department_id = (int)$_GET['edit'];
        $update_mode = true;
        $stmt = $pdo->prepare("SELECT name, number_of_employees, parent_id FROM departments WHERE id = :id");
        $stmt->execute(['id' => $department_id]);
        $department = $stmt->fetch();
        if ($department) {
            $department_name = $department['name'];
            $number_of_employees = $department['number_of_employees'];
            $parent_id = $department['parent_id'];
        }
    }
    // Обработка удаления
    if (isset($_GET['delete'])) {
        $department_id = (int)$_GET['delete'];
        try {
            // Проверка на наличие дочерних департаментов
            $stmt_check_children = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE parent_id = :id");
            $stmt_check_children->execute(['id' => $department_id]);
            if ($stmt_check_children->fetchColumn() > 0) {
                $error_message = "Невозможно удалить департамент: у него есть дочерние подразделения.";
            } else {
                // Проверка, привязан ли департамент к пользователям
                $stmt_check_users = $pdo->prepare("SELECT COUNT(*) FROM user_department_permissions WHERE department_id = :id");
                $stmt_check_users->execute(['id' => $department_id]);
                if ($stmt_check_users->fetchColumn() > 0) {
                    $error_message = "Невозможно удалить департамент: к нему привязаны пользователи.";
                } else {
                    $stmt_delete = $pdo->prepare("DELETE FROM departments WHERE id = :id");
                    $stmt_delete->execute(['id' => $department_id]);
                    log_event("Удален департамент ID: {$department_id}");
                    setcookie("success_message", "Департамент успешно удален.", time() + 5);
                    header("Location: departments.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error_message = "Ошибка удаления департамента: " . $e->getMessage();
        }
    }
}

// --- Функция для построения и отображения дерева ---
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

// Получение всех департаментов для построения дерева
$stmt_all = $pdo->query("SELECT * FROM departments ORDER BY name");
$all_nodes = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
$department_tree = build_tree($all_nodes);

?>

<div class="row">
    <div class="col-md-4">
        <h3><?php echo $update_mode ? 'Редактировать департамент' : 'Добавить новый департамент'; ?></h3>
        <form action="departments.php" method="post" class="card p-3">
            <input type="hidden" name="id" value="<?php echo $department_id; ?>">
            
            <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
            <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
            
            <div class="form-group mb-3">
                <label for="name">Название департамента</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($department_name); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="number_of_employees">Количество сотрудников</label>
                <input type="number" name="number_of_employees" id="number_of_employees" class="form-control" value="<?php echo htmlspecialchars($number_of_employees); ?>">
            </div>

            <div class="form-group mb-3">
                <label for="parent_id">Родительский департамент</label>
                <select name="parent_id" id="parent_id" class="form-control">
                    <option value="">-- Нет --</option>
                    <?php foreach ($all_departments as $dep): ?>
                        <?php // Нельзя выбрать самого себя в качестве родителя
                        if ($update_mode && $dep['id'] == $department_id) continue; ?>
                        <option value="<?php echo $dep['id']; ?>" <?php echo ($parent_id == $dep['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dep['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <?php if ($update_mode): ?>
                    <button type="submit" class="btn btn-primary" name="update">Обновить</button>
                    <a href="departments.php" class="btn btn-secondary">Отмена</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-success" name="save">Сохранить</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-8">
        <h3>Список департаментов</h3>
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Кол-во сотрудников</th>
                    <th>Родительский</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php display_tree($department_tree); ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once '../../layouts/admin/footer.php';
?>