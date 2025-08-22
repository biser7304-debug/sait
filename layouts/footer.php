</main> <!-- /container -->

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">© <?php echo date("Y"); ?> <?php echo htmlspecialchars($app_title ?? 'Staff Status Tracker'); ?></span>
    </div>
</footer>

<!-- Local JS files -->
<script src="/js/jquery-3.5.1.min.js"></script>
<script src="/js/popper.min.js"></script>
<script src="/js/bootstrap.min.js"></script>

<script>
// Скрипт для сворачивания/разворачивания дерева с использованием jQuery
$(document).ready(function() {
    // Используем делегирование событий для обработки кликов по .parent-row
    $('table').on('click', '.parent-row', function(event) {
        // Исключаем клики по ссылкам и кнопкам внутри строки
        if ($(event.target).is('a, button') || $(event.target).closest('a, button').length) {
            return;
        }

        const id = $(this).data('id');
        const children = $('.child-row[data-parent-id="' + id + '"]');
        const icon = $(this).find('i.bi');

        // Простое переключение видимости с помощью jQuery
        children.toggle();

        // Переключение иконки
        if (icon.length) {
            icon.toggleClass('bi-plus-square-fill bi-dash-square-fill');
        }
    });
});
</script>
</body>
</html>
