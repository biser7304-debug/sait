<?php
// error_reporting(E_ALL);
// ini_set("display_errors","On");
require_once '../../layouts/admin/header.php';

// Инициализация переменных
$department_name = "";
$department_id = 0;
$update_mode = false;
$error_message = '';
$success_message = "";

if (isset($_COOKIE["success_message"]))
{
   $success_message = $_COOKIE["success_message"];
   setcookie("success_message", "", time()-1000);
}


// Обработка POST-запросов для создания и обновления
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $department_name = trim($_POST['name']);

    if (empty($department_name)) {
        $error_message = "Название департамента не может быть пустым.";
    } else {
        // Обновление существующего департамента
        if (isset($_POST['update'])) {
            $department_id = $_POST['id'];
            try {
                $sql = "UPDATE departments SET name = :name WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['name' => $department_name, 'id' => $department_id]);
                log_event("Обновлен департамент ID: {$department_id} с новым названием '{$department_name}'");
                $success_message = "Департамент успешно обновлен.";

                setcookie("success_message", $success_message, time()+3600);
                header("Location: departments.php");
                
            } catch (PDOException $e) {
                $error_message = "Ошибка обновления департамента. Возможно, он уже существует. " . $e->getMessage();
            }
        // Создание нового департамента
        } elseif (isset($_POST['save'])) {
            try {
                $sql = "INSERT INTO departments (name) VALUES (:name)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['name' => $department_name]);
                $new_id = $pdo->lastInsertId();
                //log_event("Создан новый департамент '{$department_name}' (ID: {$new_id})");
                $success_message = "Департамент успешно создан.";
                $department_name = ""; // Очистить поле после успешной вставки

                setcookie("success_message", $success_message, time()+3600);
                header("Location: departments.php");

              
            } catch (PDOException $e) {
                $error_message = "Ошибка создания департамента. Возможно, он уже существует. " . $e->getMessage();
            }
        }
    }
}

// Обработка GET-запросов для редактирования и удаления
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Заполнение формы для редактирования
    if (isset($_GET['edit'])) {
        $department_id = (int) $_GET['edit'];
        $update_mode = true;
        $sql = "SELECT name FROM departments WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $department_id]);
        $department = $stmt->fetch();
        if ($department) {
            $department_name = $department['name'];
        }
    }
    // Обработка удаления
    if (isset($_GET['delete'])) {
        $department_id = $_GET['delete'];

        try {
            // Проверка, есть ли пользователи, назначенные этому департаменту
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = :id");
            $stmt_check->execute(['id' => $department_id]);
            $user_count = $stmt_check->fetchColumn();

            if ($user_count > 0) {
                $error_message = "Невозможно удалить департамент: к нему привязаны пользователи. Пожалуйста, сначала переназначьте их.";
            } else {
                $sql = "DELETE FROM departments WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id' => $department_id]);
                log_event("Удален департамент ID: {$department_id}");
                $success_message = "Департамент успешно удален.";
            }
        } catch (PDOException $e) {
            $error_message = "Ошибка удаления департамента: " . $e->getMessage();
        }
    }
}
?>


<div class="row">
    <div class="col-md-4">
        <h3><?php echo $update_mode ? 'Редактировать департамент' : 'Добавить новый департамент'; ?></h3>
        <form action="departments.php" method="post" class="card p-3">
            <input type="hidden" name="id" value="<?php echo $department_id; ?>">
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
                
            <?php endif; ?>
            
            <div class="form-group">
                <label for="name">Название департамента</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($department_name); ?>" required>
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
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
                while ($row = $stmt->fetch()) { ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td>
                            <a href="departments.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="Редактировать"><i class="bi bi-pencil"></i></a>
                            <a href="departments.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот департамент?');" title="Удалить"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once '../../layouts/admin/footer.php';
?>