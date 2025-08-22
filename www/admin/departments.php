<?php
require_once '../../layouts/admin/header.php';
require_once '../../includes/functions.php'; // Подключаем файл с общими функциями

// --- Инициализация переменных ---
$department_name = "";
$number_of_employees = "";
$parent_id = null;
$sort_index = 0;
$department_id = 0;
$update_mode = false;
$error_message = '';
$success_message = "";

if (isset($_COOKIE["success_message"])) {
   $success_message = $_COOKIE["success_message"];
   setcookie("success_message", "", time() - 3600);
}

// --- Получение всех департаментов для выпадающего списка ---
$all_departments_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY sort_index ASC, name ASC");
$all_departments = $all_departments_stmt->fetchAll();

// --- Обработка POST-запросов ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $department_name = trim($_POST['name']);
    $number_of_employees = !empty($_POST['number_of_employees']) || $_POST['number_of_employees'] === '0' ? (int)$_POST['number_of_employees'] : null;
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $sort_index = (int)$_POST['sort_index'];
    $department_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (empty($department_name)) {
        $error_message = "Название департамента не может быть пустым.";
    } elseif ($department_id && $department_id == $parent_id) {
        $error_message = "Департамент не может быть родителем для самого себя.";
    } else {
        $validation_result = validate_employee_count($pdo, $department_id, $parent_id, $number_of_employees);
        if ($validation_result !== true) {
            $error_message = $validation_result;
        } else {
            try {
                if (isset($_POST['update'])) { // Обновление
                    $sql = "UPDATE departments SET name = :name, number_of_employees = :num, parent_id = :parent_id, sort_index = :sort_index WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'name' => $department_name, 'num' => $number_of_employees,
                        'parent_id' => $parent_id, 'sort_index' => $sort_index, 'id' => $department_id
                    ]);
                    log_event("Администратор обновил департамент ID {$department_id}. Новые данные: Имя='{$department_name}', Сотрудники={$number_of_employees}, Родитель ID={$parent_id}, Сортировка={$sort_index}");
                    setcookie("success_message", "Департамент успешно обновлен.", time() + 5);
                } else { // Создание
                    $sql = "INSERT INTO departments (name, number_of_employees, parent_id, sort_index) VALUES (:name, :num, :parent_id, :sort_index)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'name' => $department_name, 'num' => $number_of_employees,
                        'parent_id' => $parent_id, 'sort_index' => $sort_index
                    ]);
                    $new_id = $pdo->lastInsertId();
                    log_event("Администратор создал новый департамент '{$department_name}' (ID: {$new_id}) со значениями: Сотрудники={$number_of_employees}, Родитель ID={$parent_id}, Сортировка={$sort_index}");
                    setcookie("success_message", "Департамент успешно создан.", time() + 5);
                }
                header("Location: departments.php");
                exit();
            } catch (PDOException $e) {
                $error_message = "Ошибка базы данных: " . $e->getMessage();
            }
        }
    }
}

// --- Обработка GET-запросов ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['edit'])) {
        $department_id = (int)$_GET['edit'];
        $update_mode = true;
        $stmt = $pdo->prepare("SELECT name, number_of_employees, parent_id, sort_index FROM departments WHERE id = :id");
        $stmt->execute(['id' => $department_id]);
        $department = $stmt->fetch();
        if ($department) {
            $department_name = $department['name'];
            $number_of_employees = $department['number_of_employees'];
            $parent_id = $department['parent_id'];
            $sort_index = $department['sort_index'];
        }
    }
    if (isset($_GET['delete'])) {
        $department_id = (int)$_GET['delete'];
        try {
            $stmt_check_children = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE parent_id = :id");
            $stmt_check_children->execute(['id' => $department_id]);
            if ($stmt_check_children->fetchColumn() > 0) {
                $error_message = "Невозможно удалить департамент: у него есть дочерние подразделения.";
            } else {
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

// --- Получение данных для дерева ---
$stmt_all = $pdo->query("SELECT * FROM departments ORDER BY sort_index ASC, name ASC");
$all_nodes = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
$department_tree = build_tree($all_nodes);

?>

<div class="row">
    <div class="col-md-4">
        <h3><?php echo $update_mode ? 'Редактировать департамент' : 'Добавить'; ?></h3>
        <form action="departments.php" method="post" class="card p-3">
            <input type="hidden" name="id" value="<?php echo $department_id; ?>">
            
            <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
            <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
            
            <div class="form-group mb-3">
                <label for="name">Название</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($department_name); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="number_of_employees">Количество сотрудников</label>
                <input type="number" name="number_of_employees" id="number_of_employees" class="form-control" value="<?php echo htmlspecialchars($number_of_employees); ?>" min="0">
            </div>

            <div class="form-group mb-3">
                <label for="parent_id">Родительский департамент</label>
                <select name="parent_id" id="parent_id" class="form-control">
                    <option value="">-- Нет --</option>
                    <?php foreach ($all_departments as $dep): ?>
                        <?php if ($update_mode && $dep['id'] == $department_id) continue; ?>
                        <option value="<?php echo $dep['id']; ?>" <?php echo ($parent_id == $dep['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dep['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-3">
                <label for="sort_index">Индекс сортировки</label>
                <input type="number" name="sort_index" id="sort_index" class="form-control" value="<?php echo (int)$sort_index; ?>">
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-success" name="<?php echo $update_mode ? 'update' : 'save'; ?>">
                    <?php echo $update_mode ? 'Обновить' : 'Сохранить'; ?>
                </button>
                <?php if ($update_mode): ?>
                    <a href="departments.php" class="btn btn-secondary">Отмена</a>
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
                    <th>Сотрудники</th>
                    <th>Родительский</th>
                    <th>Сорт.</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php display_tree($department_tree); // Используем функцию из functions.php ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once '../../layouts/admin/footer.php';
?>