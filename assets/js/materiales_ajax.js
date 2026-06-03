// ============================================================
// JS compartido: filas dinámicas con AJAX para materiales
// Usado en: salida_material.php (y otros módulos con tabla)
// CSS: ver .drop-container, .drop-item, etc. en layout.php
// ============================================================

let filaIdx = 0;

function agregarFila(tbodyId) {
    const tbody = document.getElementById(tbodyId);
    const idx   = filaIdx++;
    const tr    = document.createElement('tr');
    tr.className = 'fila-mat';
    tr.innerHTML = `
        <td>
            <input type="hidden" name="material_id[]" class="inp-id" value="">
            <div style="position:relative;">
                <input type="text" class="form-control form-control-sm inp-buscar"
                       placeholder="Buscar material..." autocomplete="off">
                <!-- Usa clase drop-container para tener estilos light/dark correctos -->
                <div class="drop-container" id="ac-${idx}" style="display:none;
                     position:absolute;z-index:9999;width:100%;max-height:220px;
                     overflow-y:auto;margin-top:3px;"></div>
            </div>
        </td>
        <td><span class="inp-cod text-secondary" style="font-size:11px;">—</span></td>
        <td><span class="inp-stk">—</span></td>
        <td>
            <input type="number" name="cantidad[]" class="form-control form-control-sm inp-cant"
                   min="0.01" step="0.01" max="0" placeholder="0" disabled style="max-width:90px;">
        </td>
        <td><span class="inp-und text-secondary" style="font-size:11px;">—</span></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="this.closest('tr').remove();actualizarBtn('${tbodyId}')">
                <i class="fa-solid fa-times"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);
    iniciarAC(tr, idx, tbodyId);
    actualizarBtn(tbodyId);
    tr.querySelector('.inp-buscar').focus();
}

function actualizarBtn(tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const filas = tbody.children.length;

    const sinFilasId = tbodyId === 'tbodySalida' ? 'sinFilasSalida' : 'sinFilasReingreso';
    const el = document.getElementById(sinFilasId);
    if (el) el.style.display = filas === 0 ? 'block' : 'none';

    const btnId = tbodyId === 'tbodySalida' ? 'btnGuardarSalida' : 'btnGuardarReingreso';
    const validas = [...tbody.querySelectorAll('.fila-mat')].filter(tr => {
        return tr.querySelector('.inp-id').value > 0 &&
               parseFloat(tr.querySelector('.inp-cant').value) > 0;
    });
    const btn = document.getElementById(btnId);
    if (btn) btn.disabled = validas.length === 0;
}

function iniciarAC(tr, idx, tbodyId) {
    const inp   = tr.querySelector('.inp-buscar');
    const lista = document.getElementById('ac-' + idx);
    let timer   = null;

    inp.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 1) { lista.style.display = 'none'; return; }

        timer = setTimeout(() => {
            fetch(BASE_URL + '/api/buscar_material.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    lista.innerHTML = '';
                    if (!data.length) {
                        lista.innerHTML = '<div class="drop-item"><span class="drop-item-nombre" style="opacity:.5;">Sin resultados</span></div>';
                        lista.style.display = 'block';
                        return;
                    }
                    data.forEach(m => {
                        const stockCls = m.stock <= 0 ? 'rojo' : (m.stock <= 5 ? 'amarillo' : 'verde');
                        const div = document.createElement('div');
                        div.className = 'drop-item';
                        div.innerHTML = `
                            <div class="di-icon">
                                <i class="fa-solid fa-box"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="drop-item-nombre">${m.nombre}</div>
                                <div class="drop-item-meta">
                                    ${m.codigo ? '<span class="me-2">' + m.codigo + '</span>' : ''}
                                    <span>${m.unidad}</span>
                                </div>
                            </div>
                            <span class="drop-stock ${stockCls}">Stock: ${m.stock}</span>`;
                        div.addEventListener('click', () => seleccionar(m, tr, lista, tbodyId));
                        lista.appendChild(div);
                    });
                    lista.style.display = 'block';
                });
        }, 280);
    });

    // Cerrar al hacer click fuera
    document.addEventListener('click', e => {
        if (!tr.contains(e.target)) lista.style.display = 'none';
    });

    // Navegación con teclado
    inp.addEventListener('keydown', e => {
        const items = [...lista.querySelectorAll('.drop-item')];
        const idx   = items.findIndex(el => el.classList.contains('ac-active'));
        if (e.key === 'ArrowDown') {
            const next = Math.min(idx + 1, items.length - 1);
            items.forEach((el, i) => el.classList.toggle('ac-active', i === next));
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            const prev = Math.max(idx - 1, 0);
            items.forEach((el, i) => el.classList.toggle('ac-active', i === prev));
            e.preventDefault();
        } else if (e.key === 'Enter' && idx >= 0) {
            items[idx].click();
            e.preventDefault();
        } else if (e.key === 'Escape') {
            lista.style.display = 'none';
        }
    });
}

function seleccionar(m, tr, lista, tbodyId) {
    tr.querySelector('.inp-id').value        = m.id;
    tr.querySelector('.inp-buscar').value    = m.nombre;
    tr.querySelector('.inp-cod').textContent = m.codigo || '—';
    tr.querySelector('.inp-und').textContent = m.unidad;

    const bg = m.stock <= 0 ? 'danger' : (m.stock <= 5 ? 'warning' : (m.stock <= 10 ? 'info' : 'success'));
    tr.querySelector('.inp-stk').innerHTML =
        `<span class="badge bg-${bg}">${m.stock}</span>`;

    const c = tr.querySelector('.inp-cant');
    c.disabled = (m.stock <= 0);
    c.max   = m.stock;
    c.value = m.stock > 0 ? 1 : 0;

    if (m.stock <= 0) {
        Swal.fire({ title: 'Sin stock', text: `"${m.nombre}" no tiene stock disponible.`,
                    icon: 'warning', timer: 2000, showConfirmButton: false });
    }

    c.oninput = () => {
        const v = parseFloat(c.value) || 0;
        if (v > m.stock) c.value = m.stock;
        c.style.borderColor = v <= 0 ? '#ef4444' : '';
        actualizarBtn(tbodyId);
    };

    lista.style.display = 'none';
    actualizarBtn(tbodyId);
    if (m.stock > 0) c.focus();
}
