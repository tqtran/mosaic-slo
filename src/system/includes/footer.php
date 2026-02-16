
<?php
$isAdminLayout = isset($bodyClass) && strpos($bodyClass ?? '', 'sidebar-mini') !== false;
if ($isAdminLayout):
?>
        </div><!-- /.container-fluid -->
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
</div><!-- /.wrapper -->
<?php endif; ?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE 4 -->
<script src="https://cdn.jsdelivr.net/npm/adminlte4@4.0.0-rc.6.20260104/dist/js/adminlte.min.js"></script>

</body>
</html>
