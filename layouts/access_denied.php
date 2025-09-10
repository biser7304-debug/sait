<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Доступ запрещен</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .card {
            max-width: 500px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="card text-center">
        <div class="card-header">
            <h4><i class="bi bi-exclamation-triangle-fill text-danger"></i> Доступ запрещен</h4>
        </div>
        <div class="card-body">
            <p class="card-text">
                Ваша учетная запись (<strong><?php echo htmlspecialchars($username ?? 'неизвестный пользователь'); ?></strong>) аутентифицирована, но не имеет прав для доступа к этому приложению.
            </p>
            <p class="card-text">
                Для получения доступа, пожалуйста, свяжитесь со службой поддержки по телефону:
            </p>
            <h3 class="my-3">
                <strong>111-111</strong>
            </h3>
        </div>
        <div class="card-footer text-muted">
            Система учета сотрудников
        </div>
    </div>
</body>
</html>
