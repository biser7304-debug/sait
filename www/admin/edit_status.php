<?php
require_once '../../layouts/admin/header.php';

// Инициализация переменных
$department_id = $_REQUEST['department_id'] ?? null;
$report_date = $_REQUEST['report_date'] ?? date('Y-m-d');
$error_message = '';
$success_message = '';

// Получение всех отделов для селектора
$departments_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$departments = $departments_stmt->fetchAll();

// Обработка POST-запроса для сохранения данных о статусе
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_status'])) {
    $department_id = $_POST['department_id'];
    $report_date = $_POST['report_date'];

    $status_values = [
        'present' => (int)$_POST['present'],
        'on_duty' => (int)$_POST['on_duty'],
        'trip' => (int)$_POST['trip'],
        'vacation' => (int)$_POST['vacation'],
        'sick' => (int)$_POST['sick'],
        'other' => (int)$_POST['other'],
        'notes' => trim($_POST['notes'])
    ];

    // --- Логика валидации ---
    $stmt_dep_info = $pdo->prepare("SELECT number_of_employees FROM departments WHERE id = ?");
    $stmt_dep_info->execute([$department_id]);
    $department_employees = $stmt_dep_info->fetchColumn();

    $form_total = array_sum(array_intersect_key($status_values, array_flip(['present', 'on_duty', 'trip', 'vacation', 'sick', 'other'])));

    if ($department_employees !== null && $form_total != $department_employees) {
        $error_message = "Ошибка: Сумма по всем полям ({$form_total}) не совпадает с количеством сотрудников в подразделении ({$department_employees}). Пожалуйста, исправьте данные.";
    } else {
        try {
            // Используем INSERT ... ON CONFLICT и устанавливаем флаг перезаписи администратором
            $sql = "
                INSERT INTO statuses (department_id, report_date, present, on_duty, trip, vacation, sick, other, notes, is_admin_override)
                VALUES (:department_id, :report_date, :present, :on_duty, :trip, :vacation, :sick, :other, :notes, TRUE)
                ON CONFLICT (department_id, report_date)
                DO UPDATE SET
                    present = EXCLUDED.present,
                    on_duty = EXCLUDED.on_duty,
                    trip = EXCLUDED.trip,
                    vacation = EXCLUDED.vacation,
                    sick = EXCLUDED.sick,
                    other = EXCLUDED.other,
                    notes = EXCLUDED.notes,
                    is_admin_override = TRUE
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge(
                ['department_id' => $department_id, 'report_date' => $report_date],
                $status_values
            ));

            log_event("Администратор изменил статус для департамента ID {$department_id} за дату {$report_date}");
            $success_message = "Данные успешно сохранены.";

        } catch (PDOException $e) {
            $error_message = "Error saving data: " . $e->getMessage();
        }
    }
}

// Получение данных для выбранного отдела и даты для отображения в форме
$status_data = null;
if ($department_id) {
    // Если произошла ошибка отправки, повторно заполняем форму отправленными данными.
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $error_message) {
        $status_data = $_POST;
    } else {
        // В противном случае, получаем данные из базы данных.
        $stmt = $pdo->prepare("SELECT * FROM statuses WHERE department_id = :id AND report_date = :date");
        $stmt->execute(['id' => $department_id, 'date' => $report_date]);
        $status_data = $stmt->fetch();
    }
}

// Если на этот день нет данных, инициализируем значениями по умолчанию
if (!$status_data) {
    $status_data = [
        'present' => 0, 'on_duty' => 0, 'trip' => 0,
        'vacation' => 0, 'sick' => 0, 'other' => 0, 'notes' => ''
    ];
}
?>

<h3>Редактирование данных о статусе</h3>
<p>Выберите отдел и дату для загрузки и редактирования информации о состоянии.</p>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

<!-- Форма выбора -->
<div class="card mb-4">
    <div class="card-body">
        <form action="edit_status.php" method="get" class="form-inline">
            <div class="form-group mr-3">
                <label for="department_id" class="mr-2">Департамент:</label>
                <select name="department_id" id="department_id" class="form-control" required>
                    <option value="">-- Выберите департамент --</option>
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?php echo $dep['id']; ?>" <?php echo ($department_id == $dep['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dep['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mr-3">
                <label for="report_date" class="mr-2">Дата:</label>
                <input type="date" id="report_date" name="report_date" class="form-control" value="<?php echo $report_date; ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Загрузить данные</button>
        </form>
    </div>
</div>

<!-- Форма редактирования (отображается, если выбран отдел) -->
<?php if ($department_id): ?>
<div class="card">
    <div class="card-header">
        <h4>
            Редактирование данных для "<?php echo htmlspecialchars(array_column($departments, 'name', 'id')[$department_id]); ?>"
            за <?php echo date('d.m.Y', strtotime($report_date)); ?>
        </h4>
    </div>
    <div class="card-body">
        <form action="edit_status.php" method="post">
            <input type="hidden" name="department_id" value="<?php echo $department_id; ?>">
            <input type="hidden" name="report_date" value="<?php echo $report_date; ?>">

            <div class="form-row">
                <div class="form-group col-md-2"><label>Присутствуют</label><input type="number" class="form-control" name="present" value="<?php echo $status_data['present']; ?>" required min="0"></div>
                <div class="form-group col-md-2"><label>На дежурстве</label><input type="number" class="form-control" name="on_duty" value="<?php echo $status_data['on_duty']; ?>" required min="0"></div>
                <div class="form-group col-md-2"><label>В командировке</label><input type="number" class="form-control" name="trip" value="<?php echo $status_data['trip']; ?>" required min="0"></div>
                <div class="form-group col-md-2"><label>В отпуске</label><input type="number" class="form-control" name="vacation" value="<?php echo $status_data['vacation']; ?>" required min="0"></div>
                <div class="form-group col-md-2"><label>На больничном</label><input type="number" class="form-control" name="sick" value="<?php echo $status_data['sick']; ?>" required min="0"></div>
                <div class="form-group col-md-2"><label>Прочее</label><input type="number" class="form-control" name="other" value="<?php echo $status_data['other']; ?>" required min="0"></div>
            </div>
            <div class="form-group">
                <label for="notes">Примечания</label>
                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($status_data['notes']); ?></textarea>
            </div>
            <button type="submit" name="save_status" class="btn btn-success">Сохранить изменения</button>
            <a href="edit_status.php" class="btn btn-secondary">Отменить выбор</a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
require_once '../../layouts/admin/footer.php';
?>
