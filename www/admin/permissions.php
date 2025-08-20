<?php
require_once '../../layouts/admin/header.php';

// Администраторы по умолчанию, которых нельзя изменять
$default_admins = ['as-biserov', 'as-karpov'];

// Инициализация переменных
$user_id = 0;
$username = '';
$role = 'department';
$department_ids = []; // Теперь массив для нескольких ID
$update_mode = false;
$error_message = '';
$success_message = '';

// Получение всех департаментов для выпадающего списка
$departments_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY sort_index ASC, name ASC");
$departments = $departments_stmt->fetchAll();

// --- Обработка POST-запросов ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = strtolower(trim($_POST['username']));
    $role = $_POST['role'];
    // Получаем массив ID департаментов
    $department_ids = isset($_POST['department_ids']) ? $_POST['department_ids'] : [];

    if (empty($username) || empty($role)) {
        $error_message = "Имя пользователя и роль обязательны для заполнения.";
    } elseif ($role === 'department' && empty($department_ids)) {
        $error_message = "Для роли 'Пользователь департамента' необходимо выбрать хотя бы один департамент.";
    } else {
        $pdo->beginTransaction();
        try {
            // --- Создание или обновление пользователя ---
            if (isset($_POST['update'])) {
                $user_id = $_POST['id'];
                $original_user_stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
                $original_user_stmt->execute(['id' => $user_id]);
                $original_username = $original_user_stmt->fetchColumn();

                if (in_array($original_username, $default_admins) && $role !== 'admin') {
                    throw new Exception("Нельзя изменить роль администратора по умолчанию.");
                }

                $stmt = $pdo->prepare("UPDATE users SET username = :username, role = :role WHERE id = :id");
                $stmt->execute(['username' => $username, 'role' => $role, 'id' => $user_id]);
                log_event("Обновлен пользователь '{$username}' (ID: {$user_id})");

            } elseif (isset($_POST['save'])) {
                $stmt = $pdo->prepare("INSERT INTO users (username, role) VALUES (:username, :role)");
                $stmt->execute(['username' => $username, 'role' => $role]);
                $user_id = $pdo->lastInsertId();
                log_event("Создан пользователь '{$username}' (ID: {$user_id})");
            }

            // --- Обновление прав на департаменты ---
            if ($user_id > 0) {
                // Удаляем старые права
                $stmt_delete_perms = $pdo->prepare("DELETE FROM user_department_permissions WHERE user_id = :user_id");
                $stmt_delete_perms->execute(['user_id' => $user_id]);

                // Если роль - админ, права не назначаются. Если пользователь, назначаем выбранные.
                if ($role === 'department') {
                    $stmt_insert_perms = $pdo->prepare("INSERT INTO user_department_permissions (user_id, department_id) VALUES (:user_id, :department_id)");
                    foreach ($department_ids as $dep_id) {
                        $stmt_insert_perms->execute(['user_id' => $user_id, 'department_id' => $dep_id]);
                    }
                }
            }

            $pdo->commit();
            $success_message = "Права пользователя успешно сохранены.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Ошибка сохранения прав: " . $e->getMessage();
        }
    }
}

// --- Обработка GET-запросов ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['edit'])) {
        $user_id = (int)$_GET['edit'];
        $update_mode = true;

        // Получаем данные пользователя
        $stmt_user = $pdo->prepare("SELECT username, role FROM users WHERE id = :id");
        $stmt_user->execute(['id' => $user_id]);
        $user = $stmt_user->fetch();

        if ($user) {
            $username = $user['username'];
            $role = $user['role'];

            // Получаем связанные департаменты
            $stmt_perms = $pdo->prepare("SELECT department_id FROM user_department_permissions WHERE user_id = :user_id");
            $stmt_perms->execute(['id' => $user_id]);
            $department_ids = $stmt_perms->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    if (isset($_GET['delete'])) {
        $user_id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user_to_delete = $stmt->fetchColumn();

        if (in_array($user_to_delete, $default_admins)) {
            $error_message = "Администраторов по умолчанию нельзя удалить.";
        } else {
            // Транзакция для безопасного удаления
            $pdo->beginTransaction();
            try {
                // Сначала удаляем права
                $stmt_del_perms = $pdo->prepare("DELETE FROM user_department_permissions WHERE user_id = :id");
                $stmt_del_perms->execute(['id' => $user_id]);
                // Затем удаляем пользователя
                $stmt_del_user = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt_del_user->execute(['id' => $user_id]);

                $pdo->commit();
                log_event("Удален пользователь '{$user_to_delete}' (ID: {$user_id})");
                $success_message = "Пользователь и его права успешно удалены.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Ошибка удаления пользователя: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-4">
        <h3><?php echo $update_mode ? 'Редактировать права' : 'Предоставить права'; ?></h3>
        <form action="permissions.php" method="post" class="card p-3">
            <input type="hidden" name="id" value="<?php echo $user_id; ?>">
            <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
            <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

            <div class="form-group mb-3">
                <label for="username">Имя пользователя (из Kerberos, без @domain)</label>
                <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="form-group mb-3">
                <label for="role">Роль</label>
                <select name="role" id="role" class="form-control" required>
                    <option value="department" <?php if($role === 'department') echo 'selected'; ?>>Пользователь департамента</option>
                    <option value="admin" <?php if($role === 'admin') echo 'selected'; ?>>Администратор</option>
                </select>
            </div>
            <div class="form-group mb-3" id="department-select-group" style="<?php echo ($role !== 'department') ? 'display: none;' : ''; ?>">
                <label for="department_ids">Департаменты (удерживайте Ctrl/Cmd для выбора нескольких)</label>
                <select name="department_ids[]" id="department_ids" class="form-control" multiple style="height: 150px;">
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?php echo $dep['id']; ?>" <?php echo in_array($dep['id'], $department_ids) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dep['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <?php if ($update_mode): ?>
                    <button type="submit" class="btn btn-primary" name="update">Обновить</button>
                    <a href="permissions.php" class="btn btn-secondary">Отмена</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-success" name="save">Сохранить</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-8">
        <h3>Список прав пользователей</h3>
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Имя пользователя</th>
                    <th>Роль</th>
                    <th>Разрешенные департаменты</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Запрос для получения пользователей и списка их департаментов
                $sql = "
                    SELECT
                        u.id,
                        u.username,
                        u.role,
                        STRING_AGG(d.name, ', ' ORDER BY d.sort_index ASC, d.name ASC) AS department_names
                    FROM users u
                    LEFT JOIN user_department_permissions udp ON u.id = udp.user_id
                    LEFT JOIN departments d ON udp.department_id = d.id
                    GROUP BY u.id, u.username, u.role
                    ORDER BY u.username
                ";
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>
                    <tr>
                        <td><i class="bi bi-person"></i> <?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo $row['role'] === 'admin' ? '<i class="bi bi-shield-lock"></i> Администратор' : '<i class="bi bi-person-workspace"></i> Пользователь'; ?></td>
                        <td><?php echo htmlspecialchars($row['department_names'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="permissions.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="Редактировать"><i class="bi bi-pencil"></i></a>
                            <?php if (!in_array($row['username'], $default_admins)): ?>
                                <a href="permissions.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Вы уверены?');" title="Удалить"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Показать/скрыть выпадающий список департаментов в зависимости от выбора роли
document.getElementById('role').addEventListener('change', function() {
    const departmentSelect = document.getElementById('department-select-group');
    if (this.value === 'department') {
        departmentSelect.style.display = 'block';
    } else {
        departmentSelect.style.display = 'none';
    }
});
</script>

<?php
require_once '../../layouts/admin/footer.php';
?>
