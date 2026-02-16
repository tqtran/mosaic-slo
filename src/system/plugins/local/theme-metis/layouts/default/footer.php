
</main>

<footer class="container mt-5 mb-3">
    <div class="text-center text-muted">
        <small>&copy; <?= date('Y') ?> MOSAIC - Student Learning Outcomes Assessment</small>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($customScripts)): ?>
<?= $customScripts ?>
<?php endif; ?>

</body>
</html>
