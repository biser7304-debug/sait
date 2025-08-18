<?php

require_once '../layouts/header.php'; // Этот файл теперь включает auth.php и config.php
// echo "124";
require_once '../includes/functions.php';

$error_message = '';
$success_message = '';

// Определяем дату для просмотра, по умолчанию сегодня
$view_date = $_GET['view_date'] ?? date('Y-m-d');

// --- Логика для пользователей департаментов (отправка/обновление статуса) ---
if ($USER['role'] === 'department') {
    $department_id = $USER['department_id'];
    $report_date_today = date('Y-m-d');

    // Обработка отправки формы
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_status'])) {
        if (empty($department_id)) {
            $error_message = "Ваша учетная запись не привязана к департаменту. Вы не можете отправлять данные.";
        } else {
            $status_values = [
                'department_id' => $department_id,
                'report_date' => $report_date_today,
                'present' => (int)$_POST['present'],
                'on_duty' => (int)$_POST['on_duty'],
                'trip' => (int)$_POST['trip'],
                'vacation' => (int)$_POST['vacation'],
                'sick' => (int)$_POST['sick'],
                'other' => (int)$_POST['other'],
                'notes' => trim($_POST['notes'])
            ];

            try {
                $sql = "
                    INSERT INTO statuses (department_id, report_date, present, on_duty, trip, vacation, sick, other, notes)
                    VALUES (:department_id, :report_date, :present, :on_duty, :trip, :vacation, :sick, :other, :notes)
                    ON CONFLICT (department_id, report_date) DO UPDATE SET
                        present = EXCLUDED.present, on_duty = EXCLUDED.on_duty, trip = EXCLUDED.trip,
                        vacation = EXCLUDED.vacation, sick = EXCLUDED.sick, other = EXCLUDED.other, notes = EXCLUDED.notes
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($status_values);
                log_event("Отправлен/обновлен статус для департамента ID {$department_id} за дату {$report_date_today}");
                $success_message = "Данные за сегодня успешно сохранены.";
            } catch (PDOException $e) {
                $error_message = "Ошибка сохранения данных: " . $e->getMessage();
            }
        }
    }

    // Получение текущих данных за сегодня для предзаполнения формы
    $current_status = ['present' => 0, 'on_duty' => 0, 'trip' => 0, 'vacation' => 0, 'sick' => 0, 'other' => 0, 'notes' => ''];
    if (!empty($department_id)) {
        $stmt = $pdo->prepare("SELECT * FROM statuses WHERE department_id = :id AND report_date = :date");
        $stmt->execute(['id' => $department_id, 'date' => $report_date_today]);
        $fetched_status = $stmt->fetch();
        if ($fetched_status) {
            $current_status = $fetched_status;
        }
    }
}

// --- Логика для всех пользователей (отображение сводной таблицы) ---
$summary_data = [];
$grand_total = ['total' => 0, 'present' => 0, 'on_duty' => 0, 'trip' => 0, 'vacation' => 0, 'sick' => 0, 'other' => 0];

try {
    $sql_summary = "
        SELECT
            d.name as department_name,
            s.present, s.on_duty, s.trip, s.vacation, s.sick, s.other, s.notes
        FROM departments d
        LEFT JOIN statuses s ON d.id = s.department_id AND s.report_date = :view_date
        ORDER BY d.name;
    ";
    $stmt_summary = $pdo->prepare($sql_summary);
    $stmt_summary->execute(['view_date' => $view_date]);
    $summary_data = $stmt_summary->fetchAll();
} catch (PDOException $e) {
    $error_message = "Ошибка получения сводных данных: " . $e->getMessage();
}
?>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

<!-- Форма ввода данных для пользователей департаментов -->
<?php if ($USER['role'] === 'department'): ?>
<div class="card mb-4">
    <div class="card-header"><h4>Ввод данных за сегодня (<?php echo date('d.m.Y'); ?>)</h4></div>
    <div class="card-body">
        <?php if (empty($department_id)): ?>
            <div class="alert alert-warning">Ваша учетная запись не привязана к департаменту. Вы не можете отправлять данные.</div>
        <?php else: ?>
        <form action="index.php" method="post">
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
                    <?php foreach ($summary_data as $row):
                        $present = $row['present'] ?? 0; $on_duty = $row['on_duty'] ?? 0; $trip = $row['trip'] ?? 0;
                        $vacation = $row['vacation'] ?? 0; $sick = $row['sick'] ?? 0; $other = $row['other'] ?? 0;
                        $total = $present + $on_duty + $trip + $vacation + $sick + $other;

                        $grand_total['total'] += $total; $grand_total['present'] += $present; $grand_total['on_duty'] += $on_duty;
                        $grand_total['trip'] += $trip; $grand_total['vacation'] += $vacation; $grand_total['sick'] += $sick; $grand_total['other'] += $other;
                    ?>
                    <tr>
                        <td class="text-left align-middle"><?php echo htmlspecialchars($row['department_name']); ?></td>
                        <td class="align-middle"><strong><?php echo $total; ?></strong></td>
                        <td class="align-middle"><?php echo $present; ?></td>
                        <td class="align-middle"><?php echo $on_duty; ?></td>
                        <td class="align-middle"><?php echo $trip; ?></td>
                        <td class="align-middle"><?php echo $vacation; ?></td>
                        <td class="align-middle"><?php echo $sick; ?></td>
                        <td class="align-middle"><?php echo $other; ?></td>
                        <td class="text-left align-middle" style="white-space: pre-wrap;"><?php echo htmlspecialchars($row['notes'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
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
