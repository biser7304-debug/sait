<?php
require_once '../../layouts/header.php';
require_once '../../includes/functions.php';

if ($USER['role'] !== 'department') {
    die('Доступ запрещен.');
}

$selected_department_id = $_REQUEST['department_id'] ?? null;
$error_message = '';
$success_message = '';
$number_of_employees = '';

$user_departments = [];
if (!empty($USER['department_ids'])) {
    $in_placeholders = implode(',', array_fill(0, count($USER['department_ids']), '?'));
    $stmt = $pdo->prepare("SELECT id, name, number_of_employees FROM departments WHERE id IN ($in_placeholders) ORDER BY sort_index ASC, name ASC");
    $stmt->execute($USER['department_ids']);
    $user_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_employees'])) {
    $department_id_to_update = $_POST['department_id'];
    $new_employee_count = !empty($_POST['number_of_employees']) || $_POST['number_of_employees'] === '0' ? (int)$_POST['number_of_employees'] : null;

    if (!in_array($department_id_to_update, $USER['department_ids'])) {
        $error_message = "Ошибка: у вас нет прав на редактирование этого подразделения.";
    } else {
        $stmt_parent = $pdo->prepare("SELECT parent_id FROM departments WHERE id = ?");
        $stmt_parent->execute([$department_id_to_update]);
        $parent_id = $stmt_parent->fetchColumn();

        $validation_result = validate_employee_count($pdo, $department_id_to_update, $parent_id, $new_employee_count);
        if ($validation_result !== true) {
            $error_message = $validation_result;
        } else {
            try {
                $sql = "UPDATE departments SET number_of_employees = :num WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['num' => $new_employee_count, 'id' => $department_id_to_update]);
                log_event("Пользователь {$USER['username']} обновил количество сотрудников для департамента ID {$department_id_to_update} на значение {$new_employee_count}");
                $success_message = "Количество сотрудников успешно обновлено.";

                foreach ($user_departments as &$dep) {
                    if ($dep['id'] == $department_id_to_update) {
                        $dep['number_of_employees'] = $new_employee_count;
                        break;
                    }
                }

            } catch (PDOException $e) {
                $error_message = "Ошибка базы данных: " . $e->getMessage();
            }
        }
    }
    $selected_department_id = $department_id_to_update;
}

if ($selected_department_id) {
    foreach ($user_departments as $dep) {
        if ($dep['id'] == $selected_department_id) {
            $number_of_employees = $dep['number_of_employees'];
            break;
        }
    }
}
?>

<div class="container">
    <h3>Настройки подразделения</h3>
    <p>На этой странице вы можете изменить количество сотрудников в ваших подразделениях.</p>

    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
    <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

    <?php if (empty($user_departments)): ?>
        <div class="alert alert-warning">За вашей учетной записью не закреплено ни одного подразделения.</div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-body">
                <form action="settings.php" method="get" class="form-inline">
                    <div class="form-group">
                        <label for="department_id" class="mr-2">Выберите подразделение для редактирования:</label>
                        <select name="department_id" id="department_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Выберите --</option>
                            <?php foreach ($user_departments as $dep): ?>
                                <option value="<?php echo $dep['id']; ?>" <?php echo ($selected_department_id == $dep['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dep['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_department_id): ?>
        <div class="card">
            <div class="card-header">
                <h4>Редактирование количества сотрудников</h4>
            </div>
            <div class="card-body">
                <form action="settings.php" method="post">
                    <input type="hidden" name="department_id" value="<?php echo $selected_department_id; ?>">
                    <div class="form-group">
                        <label for="number_of_employees">Количество сотрудников</label>
                        <input type="number" name="number_of_employees" id="number_of_employees" class="form-control" value="<?php echo htmlspecialchars($number_of_employees); ?>" min="0">
                    </div>
                    <button type="submit" name="update_employees" class="btn btn-primary mt-2">Сохранить</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once '../../layouts/footer.php';
?>
