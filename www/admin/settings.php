<?php
require_once '../../layouts/admin/header.php';
require_once '../../includes/functions.php';

$error_message = '';
$success_message = '';

// Вспомогательная функция для обновления настройки с использованием PDO
function update_setting($pdo, $key, $value) {
    $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['key' => $key, 'value' => $value]);
}

// Обработка POST-запросов
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Обновление заголовка
        if (isset($_POST['app_title'])) {
            $app_title = trim($_POST['app_title']);
            update_setting($pdo, 'app_title', $app_title);
            log_event("Название приложения изменено на '{$app_title}'");
            $success_message .= "Название приложения обновлено. ";
        }

        // Обновление цветовой схемы
        if (isset($_POST['color_scheme'])) {
            $color_scheme = $_POST['color_scheme'];
            update_setting($pdo, 'color_scheme', $color_scheme);
            log_event("Цветовая схема изменена на '{$color_scheme}'");
            $success_message .= "Цветовая схема обновлена. ";

            if ($color_scheme === 'custom' && isset($_POST['custom_colors'])) {
                $custom_colors_json = json_encode($_POST['custom_colors']);
                update_setting($pdo, 'custom_colors', $custom_colors_json);
                log_event("Пользовательские цвета обновлены.");
                $success_message .= "Пользовательские цвета сохранены. ";
            }
        }

        // Обработка загрузки логотипа
        if (isset($_FILES["app_logo"]) && $_FILES["app_logo"]["error"] == 0) {
            $target_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES["app_logo"]["name"], PATHINFO_EXTENSION));
            $target_file = $target_dir . DIRECTORY_SEPARATOR . "logo." . $file_extension;
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

            if (in_array($file_extension, $allowed_types)) {
                if (move_uploaded_file($_FILES["app_logo"]["tmp_name"], $target_file)) {
                    $logo_path = "assets/logo." . $file_extension;
                    update_setting($pdo, 'app_logo', $logo_path);
                    log_event("Загружен новый логотип.");
                    $success_message .= "Логотип успешно загружен.";
                } else {
                    $error_message .= "Ошибка при перемещении загруженного файла. ";
                }
            } else {
                $error_message .= "Недопустимый тип файла. Разрешены только JPG, PNG, GIF, SVG. ";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Произошла ошибка базы данных: " . $e->getMessage();
    }
}

// Получение текущих настроек для отображения
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$custom_colors = json_decode($settings['custom_colors'] ?? '{}', true);
$current_scheme = $settings['color_scheme'] ?? 'default';
?>

<h3>Настройки приложения</h3>
<p>Настройте внешний вид и основные параметры приложения.</p>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Общие настройки</div>
            <div class="card-body">
                <form action="settings.php" method="post">
                    <div class="form-group">
                        <label for="app_title">Название приложения</label>
                        <input type="text" name="app_title" id="app_title" class="form-control" value="<?php echo htmlspecialchars($settings['app_title'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить название</button>
                </form>
                <hr>
                <form action="settings.php" method="post" enctype="multipart/form-data">
                     <div class="form-group">
                        <label for="app_logo">Логотип приложения</label>
                        <input type="file" name="app_logo" id="app_logo" class="form-control-file">
                        <small class="form-text text-muted">Загрузите файл (PNG, JPG, SVG). Текущий логотип:
                            <?php if(!empty($settings['app_logo']) && file_exists('../'.$settings['app_logo'])): ?>
                                <img src="../<?php echo $settings['app_logo']; ?>?t=<?php echo time();?>" alt="logo" style="max-height: 30px; background: #eee; padding: 2px;">
                            <?php else: echo "не установлен"; endif; ?>
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">Загрузить логотип</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Цветовая схема</div>
             <div class="card-body">
                <form action="settings.php" method="post">
                    <?php
                    $schemes = [
                        'default' => 'По умолчанию (темно-синий)', 'light' => 'Светлый (синий)',
                        'success' => 'Зеленый', 'danger' => 'Красный', 'warning' => 'Желтый', 'custom' => 'Пользовательский'
                    ];
                    ?>
                    <div class="form-group">
                        <?php foreach($schemes as $key => $name): ?>
                        <div class="form-check">
                            <input class="form-check-input scheme-radio" type="radio" name="color_scheme" id="scheme_<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo ($current_scheme == $key) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="scheme_<?php echo $key; ?>"><?php echo $name; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="custom_scheme_options" style="<?php echo ($current_scheme !== 'custom') ? 'display: none;' : ''; ?>">
                        <hr>
                        <h5>Пользовательские цвета</h5>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="navbar_bg">Фон навигационной панели</label>
                                <input type="color" id="navbar_bg" name="custom_colors[navbar_bg]" class="form-control" value="<?php echo htmlspecialchars($custom_colors['navbar_bg'] ?? '#343a40'); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="navbar_link_color">Цвет ссылок навигационной панели</label>
                                <input type="color" id="navbar_link_color" name="custom_colors[navbar_link_color]" class="form-control" value="<?php echo htmlspecialchars($custom_colors['navbar_link_color'] ?? '#ffffff'); ?>">
                            </div>
                             <div class="form-group col-md-6">
                                <label for="btn_primary_bg">Основная кнопка</label>
                                <input type="color" id="btn_primary_bg" name="custom_colors[btn_primary_bg]" class="form-control" value="<?php echo htmlspecialchars($custom_colors['btn_primary_bg'] ?? '#007bff'); ?>">
                            </div>
                        </div>
                    </div>
                     <button type="submit" class="btn btn-primary">Применить схему</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customOptions = document.getElementById('custom_scheme_options');
    const radioButtons = document.querySelectorAll('.scheme-radio');
    function toggleCustomOptions() {
        if (document.querySelector('.scheme-radio:checked').value === 'custom') {
            customOptions.style.display = 'block';
        } else {
            customOptions.style.display = 'none';
        }
    }
    radioButtons.forEach(radio => radio.addEventListener('change', toggleCustomOptions));
});
</script>

<?php
require_once '../../layouts/admin/footer.php';
?>
