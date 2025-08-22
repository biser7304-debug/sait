<?php
require_once __DIR__ . '/../includes/auth.php';

$app_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $app_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Не удалось загрузить настройки из базы данных: " . $e->getMessage());
}
$app_title = $app_settings['app_title'] ?? 'Система учета сотрудников';
$app_logo = $app_settings['app_logo'] ?? '';
$color_scheme = $app_settings['color_scheme'] ?? 'default';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($app_title); ?></title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/bootstrap-icons.css">
    <?php
    if ($color_scheme === 'custom') {
        $custom_colors = json_decode($app_settings['custom_colors'] ?? '{}', true);
        $navbar_bg = $custom_colors['navbar_bg'] ?? '#003366';
        $navbar_link_color = $custom_colors['navbar_link_color'] ?? '#ffffff';
        $btn_primary_bg = $custom_colors['btn_primary_bg'] ?? '#004080';

        echo "<style>
            .navbar.bg-primary { background-color: {$navbar_bg} !important; }
            .navbar.bg-primary .nav-link, .navbar.bg-primary .navbar-brand, .navbar.bg-primary .navbar-text, .navbar.bg-primary .btn-outline-light { color: {$navbar_link_color} !important; }
            .navbar.bg-primary .btn-outline-light { border-color: {$navbar_link_color}; }
            .btn-primary { background-color: {$btn_primary_bg}; border-color: {$btn_primary_bg}; }
        </style>";
    } else {
        $scheme_css_path = "/css/schemes/{$color_scheme}.css";
        if (file_exists('www' . $scheme_css_path)) {
            echo '<link rel="stylesheet" href="' . $scheme_css_path . '?v=' . filemtime('www' . $scheme_css_path) . '">';
        }
    }
    ?>
    <style>
        html { height: 100%; }
        body {
            min-height: 100%;
            display: grid;
            grid-template-rows: auto 1fr auto;
        }
        .footer {
            height: 60px;
            line-height: 60px;
            background-color: #f5f5f5;
        }
        .navbar-brand img { max-height: 30px; margin-right: 10px; vertical-align: middle; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="/index.php">
            <?php if (!empty($app_logo) && file_exists('www' . $app_logo)): ?>
                <img src="<?php echo $app_logo; ?>?t=<?php echo filemtime('www' . $app_logo);?>" alt="logo">
            <?php endif; ?>
            <?php echo htmlspecialchars($app_title); ?>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Переключить навигацию">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <?php if ($USER['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/departments.php">Панель администратора</a>
                    </li>
                <?php elseif ($USER['role'] === 'department'): ?>
                     <li class="nav-item">
                        <a class="nav-link" href="/department/settings.php">Настройки подразделения</a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                 <li class="nav-item">
                    <span class="navbar-text mr-3">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($USER["username"]); ?>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
