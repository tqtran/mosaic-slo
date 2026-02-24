
        </div>
    </div>
</main>

<footer class="app-footer">
    <div class="float-end d-none d-sm-inline">Version <?= htmlspecialchars($appVersion ?? '1.0.0') ?></div>
    <strong>&copy; 2024-2026 MOSAIC</strong> - Student Learning Outcomes Assessment
</footer>

</div><!-- /.app-wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE 4 -->
<script src="https://cdn.jsdelivr.net/npm/adminlte4@4.0.0-rc.6.20260104/dist/js/adminlte.min.js"></script>

<!-- Global term selector handler -->
<script>
$(document).ready(function() {
    $('#headerTermSelector').on('change', function() {
        var termFk = $(this).val();
        var currentUrl = window.location.pathname;
        
        // Build new URL with term_fk parameter
        if (termFk) {
            window.location.href = currentUrl + '?term_fk=' + termFk;
        } else {
            window.location.href = currentUrl + '?term_fk=';
        }
    });
});
</script>

<?php if (isset($customScripts)): ?>
<?= $customScripts ?>
<?php endif; ?>

</body>
</html>
