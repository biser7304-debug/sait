<?php
require_once '../layouts/header.php';
require_once '../includes/functions.php';

$error_message = '';
$success_message = '';
$view_date = $_GET['view_date'] ?? date('Y-m-d');
$selected_department_id = null;
$user_departments = [];

// --- Логика для пользователей департаментов ---
if ($USER['role'] === 'department') {
    // Получаем список департаментов, к которым у пользователя есть доступ, и сортируем его
    if (!empty($USER['department_ids'])) {
        $in_placeholders = implode(',', array_fill(0, count($USER['department_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id IN ($in_placeholders) ORDER BY sort_index ASC, name ASC");
        $stmt->execute($USER['department_ids']);
        $user_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Определяем выбранный департамент
    if (isset($_GET['dep_id']) && in_array($_GET['dep_id'], $USER['department_ids'])) {
        $selected_department_id = (int)$_GET['dep_id'];
    } elseif (count($user_departments) === 1) {
        $selected_department_id = $user_departments[0]['id'];
    }

    // Обработка отправки формы
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_status'])) {
        $department_id_to_submit = (int)$_POST['department_id'];
        // Проверяем, имеет ли пользователь право на отправку для этого департамента
        if (!in_array($department_id_to_submit, $USER['department_ids'])) {
            $error_message = "Ошибка: у вас нет прав на отправку данных для этого департамента.";
        } else {
            $report_date_today = date('Y-m-d');
            $status_values = [
                'department_id' => $department_id_to_submit,
                'report_date' => $report_date_today,
                'present' => (int)$_POST['present'], 'on_duty' => (int)$_POST['on_duty'],
                'trip' => (int)$_POST['trip'], 'vacation' => (int)$_POST['vacation'],
                'sick' => (int)$_POST['sick'], 'other' => (int)$_POST['other'],
                'notes' => trim($_POST['notes'])
            ];

            try {
                $sql = "INSERT INTO statuses (department_id, report_date, present, on_duty, trip, vacation, sick, other, notes)
                        VALUES (:department_id, :report_date, :present, :on_duty, :trip, :vacation, :sick, :other, :notes)
                        ON CONFLICT (department_id, report_date) DO UPDATE SET
                        present = EXCLUDED.present, on_duty = EXCLUDED.on_duty, trip = EXCLUDED.trip,
                        vacation = EXCLUDED.vacation, sick = EXCLUDED.sick, other = EXCLUDED.other, notes = EXCLUDED.notes";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($status_values);
                log_event("Отправлен/обновлен статус для департамента ID {$department_id_to_submit} за дату {$report_date_today}");
                $success_message = "Данные для департамента успешно сохранены.";
            } catch (PDOException $e) {
                $error_message = "Ошибка сохранения данных: " . $e->getMessage();
            }
        }
    }

    // Получение данных для предзаполнения формы, если департамент выбран
    $current_status = ['present' => 0, 'on_duty' => 0, 'trip' => 0, 'vacation' => 0, 'sick' => 0, 'other' => 0, 'notes' => ''];
    if ($selected_department_id) {
        $stmt = $pdo->prepare("SELECT * FROM statuses WHERE department_id = :id AND report_date = :date");
        $stmt->execute(['id' => $selected_department_id, 'date' => date('Y-m-d')]);
        $fetched_status = $stmt->fetch();
        if ($fetched_status) $current_status = $fetched_status;
    }
}

// --- Логика для всех пользователей (отображение сводной таблицы) ---

// 1. Получаем все департаменты, отсортированные правильно
$stmt_all_deps = $pdo->query("SELECT * FROM departments ORDER BY sort_index ASC, name ASC");
$all_departments = $stmt_all_deps->fetchAll(PDO::FETCH_ASSOC);

// 2. Получаем все статусы за выбранную дату
$stmt_statuses = $pdo->prepare("SELECT * FROM statuses WHERE report_date = :view_date");
$stmt_statuses->execute(['view_date' => $view_date]);
$statuses_raw = $stmt_statuses->fetchAll(PDO::FETCH_ASSOC);
$statuses_by_dep_id = [];
foreach ($statuses_raw as $status) {
    $statuses_by_dep_id[$status['department_id']] = $status;
}

// 3. Объединяем данные: добавляем статусы к департаментам
foreach ($all_departments as &$dep) {
    if (isset($statuses_by_dep_id[$dep['id']])) {
        $dep = array_merge($dep, $statuses_by_dep_id[$dep['id']]);
    }
}
unset($dep); // Разрываем ссылку

// 4. Строим дерево из объединенных данных
$department_tree = build_tree($all_departments);

// 5. Функция для рекурсивного отображения дерева сводки
function display_summary_tree($nodes, &$grand_total, $level = 0) {
    foreach ($nodes as $node) {
        $present = $node['present'] ?? 0; $on_duty = $node['on_duty'] ?? 0; $trip = $node['trip'] ?? 0;
        $vacation = $node['vacation'] ?? 0; $sick = $node['sick'] ?? 0; $other = $node['other'] ?? 0;
        $total = $present + $on_duty + $trip + $vacation + $sick + $other;

        $grand_total['total'] += $total; $grand_total['present'] += $present; $grand_total['on_duty'] += $on_duty;
        $grand_total['trip'] += $trip; $grand_total['vacation'] += $vacation; $grand_total['sick'] += $sick; $grand_total['other'] += $other;

        echo '<tr>';
        echo '<td class="text-left align-middle">' . str_repeat('&mdash; ', $level) . htmlspecialchars($node['name']) . '</td>';
        echo '<td class="align-middle"><strong>' . $total . '</strong></td>';
        echo '<td class="align-middle">' . $present . '</td>';
        echo '<td class="align-middle">' . $on_duty . '</td>';
        echo '<td class="align-middle">' . $trip . '</td>';
        echo '<td class="align-middle">' . $vacation . '</td>';
        echo '<td class="align-middle">' . $sick . '</td>';
        echo '<td class="align-middle">' . $other . '</td>';
        echo '<td class="text-left align-middle" style="white-space: pre-wrap;">' . htmlspecialchars($node['notes'] ?? '') . '</td>';
        echo '</tr>';

        if (isset($node['children'])) {
            display_summary_tree($node['children'], $grand_total, $level + 1);
        }
    }
}
?>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

<!-- Блок для пользователей департаментов -->
<?php if ($USER['role'] === 'department'): ?>
<div class="card mb-4">
    <div class="card-header"><h4>Ввод данных за сегодня (<?php echo date('d.m.Y'); ?>)</h4></div>
    <div class="card-body">
        <?php if (empty($user_departments)): ?>
            <div class="alert alert-warning">Ваша учетная запись не привязана ни к одному департаменту.</div>
        <?php else: ?>
            <!-- Форма выбора департамента -->
            <form action="index.php" method="get" class="mb-3">
                <div class="form-group">
                    <label for="dep_id"><b>Выберите департамент для ввода данных:</b></label>
                    <select name="dep_id" id="dep_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Выберите --</option>
                        <?php foreach ($user_departments as $dep): ?>
                            <option value="<?php echo $dep['id']; ?>" <?php if ($dep['id'] == $selected_department_id) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($dep['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="view_date" value="<?php echo $view_date; ?>">
            </form>

            <!-- Форма ввода данных (показывается, только если департамент выбран) -->
            <?php if ($selected_department_id): ?>
            <hr>
            <form action="index.php?dep_id=<?php echo $selected_department_id; ?>&view_date=<?php echo $view_date; ?>" method="post">
                <input type="hidden" name="department_id" value="<?php echo $selected_department_id; ?>">
                <div class="form-row">
                    <div class="form-group col-md-2"><label>Присутствуют</label><input type="number" class="form-control" name="present" value="<?php echo $current_status['present']; ?>" required min="0"></div>
                    <div class="form-group col-md-2"><label>На дежурстве</label><input type="number" class="form-control" name="on_duty" value="<?php echo $current_status['on_duty']; ?>" required min="0"></div>
                    <div class="form-group col-md-2"><label>В командировке</label><input type="number" class="form-control" name="trip" value="<?php echo $current_status['trip']; ?>" required min="0"></div>
                    <div class="form-group col-md-2"><label>В отпуске</label><input type="number" class="form-control" name="vacation" value="<?php echo $current_status['vacation']; ?>" required min="0"></div>
                    <div class="form-group col-md-2"><label>На больничном</label><input type="number" class="form-control" name="sick" value="<?php echo $current_status['sick']; ?>" required min="0"></div>
                    <div class="form-group col-md-2"><label>Прочее</label><input type="number" class="form-control" name="other" value="<?php echo $current_status['other']; ?>" required min="0"></div>
                </div>
                <div class="form-group">
                    <label for="notes">Примечания</label>
                    <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($current_status['notes']); ?></textarea>
                </div>
                <button type="submit" name="submit_status" class="btn btn-primary">Сохранить данные</button>
            </form>
            <?php else: ?>
                <div class="alert alert-info">Пожалуйста, выберите департамент из списка выше, чтобы ввести или просмотреть данные.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Сводная таблица для всех пользователей -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4>Сводка за <?php echo date('d.m.Y', strtotime($view_date)); ?></h4>
            <form action="index.php" method="get" class="form-inline">
                <div class="form-group">
                    <label for="view_date" class="mr-2">Выберите дату:</label>
                    <input type="date" id="view_date" name="view_date" class="form-control" value="<?php echo $view_date; ?>">
                </div>
                <button type="submit" class="btn btn-secondary ml-2">Показать</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm text-center table-striped">
                <thead class="thead-light">
                    <tr>
                        <th class="align-middle" style="width: 15%;">Департамент</th>
                        <th class="align-middle">Всего</th>
                        <th class="align-middle">Присутствуют</th>
                        <th class="align-middle">На дежурстве</th>
                        <th class="align-middle">В командировке</th>
                        <th class="align-middle">В отпуске</th>
                        <th class="align-middle">На больничном</th>
                        <th class="align-middle">Прочее</th>
                        <th class="align-middle" style="width: 25%;">Примечания</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grand_total = ['total' => 0, 'present' => 0, 'on_duty' => 0, 'trip' => 0, 'vacation' => 0, 'sick' => 0, 'other' => 0];
                    display_summary_tree($department_tree, $grand_total);
                    ?>
                </tbody>
                <tfoot class="bg-secondary text-white font-weight-bold">
                    <tr>
                        <td class="text-right">ИТОГО:</td>
                        <td><?php echo $grand_total['total']; ?></td>
                        <td><?php echo $grand_total['present']; ?></td>
                        <td><?php echo $grand_total['on_duty']; ?></td>
                        <td><?php echo $grand_total['trip']; ?></td>
                        <td><?php echo $grand_total['vacation']; ?></td>
                        <td><?php echo $grand_total['sick']; ?></td>
                        <td><?php echo $grand_total['other']; ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
require_once '../layouts/footer.php';
