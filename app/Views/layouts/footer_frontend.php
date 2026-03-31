</div><!-- /.fe-content -->

<footer class="fe-footer">
    <div>&copy; <?= date('Y') ?> <strong>LORDIK</strong> — Sistem Informasi Bursa Kerja Khusus SMK</div>
    <div class="mt-1 text-muted" style="font-size:.72rem">v<?= APP_VERSION ?></div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- Custom JS -->
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= filemtime(BASE_PATH.'/public/assets/js/app.js') ?>"></script>
<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const btn  = document.getElementById('hamburgerBtn');
    const open = menu.classList.toggle('open');
    btn.querySelector('i').className = open ? 'bi bi-x-lg' : 'bi bi-list';
}
// Close mobile menu on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#mobileMenu') && !e.target.closest('.fe-hamburger')) {
        document.getElementById('mobileMenu')?.classList.remove('open');
        const btn = document.getElementById('hamburgerBtn');
        if (btn) btn.querySelector('i').className = 'bi bi-list';
    }
});
</script>
</body>
</html>
