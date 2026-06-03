// ============================================================
// JS compartido: filas dinámicas con AJAX para materiales
// Usado en: salida_material.php y reingreso_material.php
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
                <div class="ac-list" id="ac-${idx}"
                     style="display:none;position:absolute;z-index:9999;background:#1e293b;
                            border:1px solid #334155;border-radius:10px;width:100%;
                            max-height:200px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,0.3);"></div>
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

    // Ocultar/mostrar mensaje de "sin filas"
    const sinFilasId = tbodyId === 'tbodySalida' ? 'sinFilasSalida' : 'sinFilasReingreso';
    const el = document.getElementById(sinFilasId);
    if (el) el.style.display = filas === 0 ? 'block' : 'none';

    // Habilitar/deshabilitar botón guardar
    const btnId = tbodyId === 'tbodySalida' ? 'btnGuardarSalida' : 'btnGuardarReingreso';
    const validas = [...tbody.querySelectorAll('.fila-mat')].filter(tr => {
        const id   = tr.querySelector('.inp-id').value;
        const cant = parseFloat(tr.querySelector('.inp-cant').value) || 0;
        return id > 0 && cant > 0;
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
                    if (!data.length) { lista.style.display = 'none'; return; }
                    data.forEach(m => {
                        const div = document.createElement('div');
                        div.style.cssText = 'padding:9px 14px;cursor:pointer;font-size:13px;' +
                            'display:flex;justify-content:space-between;align-items:center;' +
                            'border-bottom:1px solid rgba(255,255,255,0.06);transition:background .15s;';
                        const sc = m.stock <= 0 ? '#ef4444' : (m.stock <= 5 ? '#f59e0b' : '#22c55e');
                        div.innerHTML = `
                            <div>
                                <div style="font-weight:500;">${m.nombre}</div>
                                <div style="font-size:10px;color:#64748b;">${m.codigo || ''} · ${m.unidad}</div>
                            </div>
                            <span style="color:${sc};font-size:11px;font-weight:700;white-space:nowrap;">
                                Stock: ${m.stock}
                            </span>`;
                        div.addEventListener('mouseenter', () => div.style.background = 'rgba(59,130,246,0.18)');
                        div.addEventListener('mouseleave', () => div.style.background = '');
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
        const items = lista.querySelectorAll('div');
        const active = lista.querySelector('.ac-active');
        let idx = active ? [...items].indexOf(active) : -1;
        if (e.key === 'ArrowDown') {
            idx = Math.min(idx + 1, items.length - 1);
            items.forEach((el, i) => el.classList.toggle('ac-active', i === idx));
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            idx = Math.max(idx - 1, 0);
            items.forEach((el, i) => el.classList.toggle('ac-active', i === idx));
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
        c.style.borderColor = v > m.stock ? '#ef4444' : '';
        actualizarBtn(tbodyId);
    };

    lista.style.display = 'none';
    actualizarBtn(tbodyId);
    if (m.stock > 0) c.focus();
}
