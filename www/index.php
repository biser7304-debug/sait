<?php
require_once '../layouts/header.php';
require_once '../includes/functions.php';

$error_message = '';
$success_message = '';
$view_date = $_GET['view_date'] ?? date('Y-m-d');
$selected_department_id = null;
$user_departments = [];
$submission_date = $_GET['submission_date'] ?? date('Y-m-d');

// --- Логика для кнопок-стрелок ---
$prev_view_date = date('Y-m-d', strtotime($view_date . ' -1 day'));
$next_view_date = date('Y-m-d', strtotime($view_date . ' +1 day'));
$prev_submission_date = date('Y-m-d', strtotime($submission_date . ' -1 day'));
$next_submission_date = date('Y-m-d', strtotime($submission_date . ' +1 day'));

if ($USER['role'] === 'department') {
    $current_status = ['present' => 0, 'on_duty' => 0, 'trip' => 0, 'vacation' => 0, 'sick' => 0, 'other' => 0, 'notes' => ''];

    $parent_ids_stmt = $pdo->query("SELECT DISTINCT parent_id FROM departments WHERE parent_id IS NOT NULL");
    $parent_ids = $parent_ids_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($USER['department_ids'])) {
        $allowed_submission_deps = array_diff($USER['department_ids'], $parent_ids);
        if (!empty($allowed_submission_deps)) {
            $in_placeholders = implode(',', array_fill(0, count($allowed_submission_deps), '?'));
            $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id IN ($in_placeholders) ORDER BY sort_index ASC, name ASC");
            $stmt->execute(array_values($allowed_submission_deps));
            $user_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (isset($_GET['dep_id']) && in_array($_GET['dep_id'], $USER['department_ids'])) {
        $selected_department_id = (int)$_GET['dep_id'];
    } elseif (count($user_departments) === 1) {
        $selected_department_id = $user_departments[0]['id'];
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_status'])) {
        $department_id_to_submit = (int)$_POST['department_id'];
        $submission_date = $_POST['submission_date'];

        $current_status = [
            'present' => (int)$_POST['present'], 'on_duty' => (int)$_POST['on_duty'],
            'trip' => (int)$_POST['trip'], 'vacation' => (int)$_POST['vacation'],
            'sick' => (int)$_POST['sick'], 'other' => (int)$_POST['other'],
            'notes' => trim($_POST['notes'])
        ];
        $selected_department_id = $department_id_to_submit;

        if (!in_array($department_id_to_submit, $USER['department_ids'])) {
            $error_message = "Ошибка: у вас нет прав на отправку данных для этого департамента.";
        } else {
            $stmt_parent = $pdo->prepare("SELECT parent_id FROM departments WHERE id = ?");
            $stmt_parent->execute([$department_id_to_submit]);
            $parent_id = $stmt_parent->fetchColumn();

            $is_overridden = false;
            if ($parent_id) {
                $stmt_override = $pdo->prepare("SELECT is_admin_override FROM statuses WHERE department_id = ? AND report_date = ?");
                $stmt_override->execute([$parent_id, $submission_date]);
                if ($stmt_override->fetchColumn()) {
                    $is_overridden = true;
                }
            }

            if ($is_overridden) {
                $error_message = "Подача за это подразделение заблокирована, так как администратор ввел данные для родительского узла.";
                log_event("Попытка заблокированной подачи пользователем {$USER['username']} для дочернего ID {$department_id_to_submit}, т.к. родитель ID {$parent_id} был перезаписан администратором.");
            } else {
                $stmt_dep_info = $pdo->prepare("SELECT number_of_employees FROM departments WHERE id = ?");
                $stmt_dep_info->execute([$department_id_to_submit]);
                $department_employees = $stmt_dep_info->fetchColumn();
                $form_total = array_sum(array_intersect_key($current_status, array_flip(['present', 'on_duty', 'trip', 'vacation', 'sick', 'other'])));

                if ($department_employees !== null && $form_total != $department_employees) {
                    $error_message = "Ошибка: Сумма по всем полям ({$form_total}) не совпадает с количеством сотрудников в подразделении ({$department_employees}). Пожалуйста, исправьте данные.";
                } else {
                    try {
                        $status_values = array_merge(['department_id' => $department_id_to_submit, 'report_date' => $submission_date], $current_status);
                        $sql = "INSERT INTO statuses (department_id, report_date, present, on_duty, trip, vacation, sick, other, notes)
                                VALUES (:department_id, :report_date, :present, :on_duty, :trip, :vacation, :sick, :other, :notes)
                                ON CONFLICT (department_id, report_date) DO UPDATE SET
                                present = EXCLUDED.present, on_duty = EXCLUDED.on_duty, trip = EXCLUDED.trip,
                                vacation = EXCLUDED.vacation, sick = EXCLUDED.sick, other = EXCLUDED.other, notes = EXCLUDED.notes";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($status_values);
                        log_event("Отправлен/обновлен статус для департамента ID {$department_id_to_submit} за дату {$submission_date}");
                        $success_message = "Данные для департамента за " . date('d.m.Y', strtotime($submission_date)) . " успешно сохранены.";
                    } catch (PDOException $e) {
                        $error_message = "Ошибка сохранения данных: " . $e->getMessage();
                    }
                }
            }
        }
    }

    if ($selected_department_id && !isset($_POST['submit_status'])) {
        $data_found = false;

        $stmt = $pdo->prepare("SELECT * FROM statuses WHERE department_id = :id AND report_date = :date");
        $stmt->execute(['id' => $selected_department_id, 'date' => $submission_date]);
        $fetched_status = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fetched_status) {
            $current_status = $fetched_status;
            $data_found = true;
        }

        if (!$data_found) {
            $previous_day = date('Y-m-d', strtotime($submission_date . ' -1 day'));
            $stmt = $pdo->prepare("SELECT * FROM statuses WHERE department_id = :id AND report_date = :date");
            $stmt->execute(['id' => $selected_department_id, 'date' => $previous_day]);
            $fetched_status = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fetched_status) {
                $current_status = $fetched_status;
            }
        }
    }
}

$stmt_all_deps = $pdo->query("SELECT * FROM departments ORDER BY sort_index ASC, name ASC");
$all_departments = $stmt_all_deps->fetchAll(PDO::FETCH_ASSOC);

$stmt_statuses = $pdo->prepare("SELECT * FROM statuses WHERE report_date = :view_date");
$stmt_statuses->execute(['view_date' => $view_date]);
$statuses_raw = $stmt_statuses->fetchAll(PDO::FETCH_ASSOC);
$statuses_by_dep_id = [];
foreach ($statuses_raw as $status) {
    $statuses_by_dep_id[$status['department_id']] = $status;
}

foreach ($all_departments as &$dep) {
    if (isset($statuses_by_dep_id[$dep['id']])) {
        $dep = array_merge($dep, $statuses_by_dep_id[$dep['id']]);
    }
}
unset($dep);

function calculate_tree_summary(&$nodes) {
    $status_keys = ['present', 'on_duty', 'trip', 'vacation', 'sick', 'other'];
    foreach ($nodes as &$node) {
        if (!empty($node['children'])) {
            calculate_tree_summary($node['children']);

            // Если данные родителя введены администратором вручную, мы не рассчитываем их по дочерним элементам.
            if (empty($node['is_admin_override'])) {
                $parent_has_any_data = false;
                // Инициализируем статистику родителя нулями
                foreach ($status_keys as $key) { $node[$key] = 0; }
                $node['notes'] = '';

                foreach ($node['children'] as $child) {
                    // Проверяем, подал ли дочерний элемент данные
                    if (isset($child['present'])) {
                        $parent_has_any_data = true; // Отмечаем, что у нас есть хоть какие-то данные для отображения
                        // Добавляем статистику дочернего элемента к общей сумме родителя
                        foreach ($status_keys as $key) {
                            $node[$key] += $child[$key] ?? 0;
                        }
                    }
                }

                // Если ни один из дочерних элементов не подал данные, родитель также считается не имеющим данных.
                if (!$parent_has_any_data) {
                     unset($node['present']);
                }
            }
        }
    }
    unset($node);
}

$department_tree = build_tree($all_departments);
calculate_tree_summary($department_tree);

function display_summary_tree($nodes, &$grand_total, $level = 0) {
    foreach ($nodes as $node) {
        $has_status = isset($node['present']);
        $is_parent = !empty($node['children']);

        $present = $node['present'] ?? 0; $on_duty = $node['on_duty'] ?? 0; $trip = $node['trip'] ?? 0;
        $vacation = $node['vacation'] ?? 0; $sick = $node['sick'] ?? 0; $other = $node['other'] ?? 0;
        $total = $present + $on_duty + $trip + $vacation + $sick + $other;

        if (!$is_parent || !empty($node['is_admin_override'])) {
            $grand_total['total'] += $total; $grand_total['present'] += $present; $grand_total['on_duty'] += $on_duty;
            $grand_total['trip'] += $trip; $grand_total['vacation'] += $vacation; $grand_total['sick'] += $sick; $grand_total['other'] += $other;
        }

        $row_class = !$has_status ? 'class="table-danger"' : '';

        echo '<tr ' . $row_class . '>';
        echo '<td class="text-left align-middle">' . str_repeat('&emsp;', $level) . htmlspecialchars($node['name']) . '</td>';
        echo '<td class="align-middle"><strong>' . $total . '</strong></td>';
        echo '<td class="align-middle">' . $present . '</td>';
        echo '<td class="align-middle">' . $on_duty . '</td>';
        echo '<td class="align-middle">' . $trip . '</td>';
        echo '<td class="align-middle">' . $vacation . '</td>';
        echo '<td class="align-middle">' . $sick . '</td>';
        echo '<td class="align-middle">' . $other . '</td>';
        echo '<td class="text-left align-middle" style="white-space: pre-wrap;">' . htmlspecialchars($node['notes'] ?? '') . '</td>';
        echo '</tr>';

        if ($is_parent) {
            display_summary_tree($node['children'], $grand_total, $level + 1);
        }
    }
}
?>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

<?php if ($USER['role'] === 'department'): ?>
<div class="card mb-4">
    <div class="card-header"><h4>Ввод данных</h4></div>
    <div class="card-body">
        <?php if (empty($user_departments)): ?>
            <div class="alert alert-warning">За вашей учетной записью не закреплено ни одного конечного подразделения для ввода данных.</div>
        <?php else: ?>
            <form action="index.php" method="get" class="mb-3 form-inline">
                <div class="form-group mr-3">
                    <label for="dep_id" class="mr-2"><b>Департамент:</b></label>
                    <select name="dep_id" id="dep_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Выберите --</option>
                        <?php foreach ($user_departments as $dep): ?>
                            <option value="<?php echo $dep['id']; ?>" <?php if ($dep['id'] == $selected_department_id) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($dep['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group d-flex align-items-center">
                    <label for="submission_date" class="mr-2"><b>Дата:</b></label>
                    <a href="?dep_id=<?php echo $selected_department_id; ?>&submission_date=<?php echo $prev_submission_date; ?>" class="btn btn-sm btn-outline-secondary mr-1">&lt;</a>
                    <input type="date" id="submission_date" name="submission_date" class="form-control" value="<?php echo $submission_date; ?>" onchange="this.form.submit()">
                    <a href="?dep_id=<?php echo $selected_department_id; ?>&submission_date=<?php echo $next_submission_date; ?>" class="btn btn-sm btn-outline-secondary ml-1">&gt;</a>
                </div>
            </form>

            <?php if ($selected_department_id): ?>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <h5>Данные за <?php echo date('d.m.Y', strtotime($submission_date)); ?></h5>
            </div>

            <form action="index.php?dep_id=<?php echo $selected_department_id; ?>&submission_date=<?php echo $submission_date; ?>" method="post" class="mt-3">
                <input type="hidden" name="department_id" value="<?php echo $selected_department_id; ?>">
                <input type="hidden" name="submission_date" value="<?php echo $submission_date; ?>">
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

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4>Сводка за <?php echo date('d.m.Y', strtotime($view_date)); ?></h4>
            <form action="index.php" method="get" class="form-inline">
                <div class="form-group d-flex align-items-center">
                    <label for="view_date" class="mr-2">Выберите дату:</label>
                    <a href="?view_date=<?php echo $prev_view_date; ?>" class="btn btn-sm btn-outline-secondary mr-1">&lt;</a>
                    <input type="date" id="view_date" name="view_date" class="form-control" value="<?php echo $view_date; ?>" onchange="this.form.submit()">
                    <a href="?view_date=<?php echo $next_view_date; ?>" class="btn btn-sm btn-outline-secondary ml-1">&gt;</a>
                </div>
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
?>
