      </div>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script>
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const themeToggle = document.getElementById('themeToggle');

const applyTheme = (theme) => {
  if (theme === 'light') {
    document.documentElement.classList.add('light-mode');
    themeToggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
  } else {
    document.documentElement.classList.remove('light-mode');
    themeToggle.innerHTML = '<i class="fa-solid fa-moon"></i>';
  }
};

const savedTheme = localStorage.getItem('dashboardTheme') || 'dark';
applyTheme(savedTheme);

themeToggle?.addEventListener('click', () => {
  const current = document.documentElement.classList.contains('light-mode') ? 'light' : 'dark';
  const next = current === 'light' ? 'dark' : 'light';
  applyTheme(next);
  localStorage.setItem('dashboardTheme', next);
});

sidebarToggle?.addEventListener('click', () => {
  sidebar?.classList.toggle('show');
});

window.addEventListener('resize', () => {
  if (window.innerWidth >= 1200) {
    sidebar?.classList.add('show');
  }
});

window.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('recentActivityTable');
  if (table && window.simpleDatatables) {
    new simpleDatatables.DataTable(table, {
      searchable: false,
      fixedHeight: true,
      perPage: 5,
      labels: {placeholder:'Buscar...', perPage:'Mostrar {select} registros', noRows:'Sin registros', info:'Mostrando {start} a {end} de {rows} elementos'}
    });
  }
});
</script>
</body>
</html>