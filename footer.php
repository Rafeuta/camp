<?php if (isLoggedIn()): ?>
        </div><!-- /.content -->
    </div><!-- /.main -->
</div><!-- /.app -->
<?php endif; ?>

<script>
// Toggle sidebar (mobile)
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebarBackdrop');
if (menuToggle) {
    menuToggle.onclick = () => {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('show');
    };
    backdrop.onclick = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
    };
}

// Dark mode
const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
function updateIcon() {
    const t = document.documentElement.getAttribute('data-theme');
    if (themeIcon) themeIcon.className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
}
if (themeToggle) {
    themeToggle.onclick = () => {
        const atual = document.documentElement.getAttribute('data-theme');
        const novo = atual === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', novo);
        localStorage.setItem('acmp-theme', novo);
        updateIcon();
    };
}
updateIcon();
</script>
</body>
</html>