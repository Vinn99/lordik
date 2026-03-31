</main><!-- /#adminContent -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= filemtime(BASE_PATH.'/public/assets/js/app.js') ?>"></script>
<script>
// ── Sidebar toggle (mobile) ──────────────────────────────────
function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('adminSidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}

// ── Sidebar group dropdown dengan scroll-position persistence ──
(function() {
    const SCROLL_KEY = 'sidebar_scroll';
    const nav = document.getElementById('sidebarNav');

    // Restore scroll position
    const savedScroll = sessionStorage.getItem(SCROLL_KEY);
    if (savedScroll && nav) nav.scrollTop = parseInt(savedScroll);

    // Save scroll on every link click
    document.querySelectorAll('#sidebarNav a.sidebar-item').forEach(link => {
        link.addEventListener('click', function() {
            if (nav) sessionStorage.setItem(SCROLL_KEY, nav.scrollTop);
        });
    });

    // Group toggle buttons
    document.querySelectorAll('.sidebar-group-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const body = document.getElementById(targetId);
            if (!body) return;

            const isOpen = body.classList.contains('show');

            // Collapse all groups
            document.querySelectorAll('.sidebar-group-body').forEach(b => {
                b.classList.remove('show');
                b.style.maxHeight = null;
            });
            document.querySelectorAll('.sidebar-group-toggle').forEach(b => {
                b.classList.remove('open');
            });

            // Open clicked if it was closed
            if (!isOpen) {
                body.classList.add('show');
                this.classList.add('open');
            }
        });
    });
})();

// ── Set topbar title from h4 inside page ────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const h4 = document.querySelector('#adminContent h4.fw-bold');
    if (h4) {
        const clone = h4.cloneNode(true);
        clone.querySelectorAll('span,i').forEach(el => el.remove());
        const text = clone.textContent.trim();
        if (text) document.getElementById('topbarPageTitle').textContent = text;
    }
});
</script>
</body>
</html>
