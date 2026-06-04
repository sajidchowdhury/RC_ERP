/**
 * Sales return warehouse confirm — validation, bulk warehouse, branch stock (SSOT)
 */
(function () {
    'use strict';

    const stockCache = {};

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('srConfirmForm');
        if (!form) return;

        const base = (window.SR_CONFIRM_BASE || '/').replace(/\/?$/, '/');
        const bulkSelect = document.getElementById('srBulkWarehouse');
        const applyBulkBtn = document.getElementById('srApplyBulkWarehouse');
        const progressBar = document.getElementById('srConfirmProgressBar');
        const checklistItems = document.querySelectorAll('[data-check]');

        function warehouseSelects() {
            return form.querySelectorAll('.sr-warehouse-select');
        }

        function conditionSelects() {
            return form.querySelectorAll('.sr-condition-select');
        }

        function syncRowState(row) {
            const cond = row.querySelector('.sr-condition-select');
            const isDamage = cond && cond.value === 'Damage';
            row.classList.toggle('is-damage', isDamage);
            const hint = row.querySelector('.sr-confirm-line-hint');
            if (hint) {
                hint.textContent = isDamage
                    ? 'Damaged — stock will not increase'
                    : 'Good — quantity will be added to selected warehouse';
                hint.classList.toggle('is-damage-note', isDamage);
            }
            const whSelect = row.querySelector('.sr-warehouse-select');
            if (whSelect) {
                whSelect.disabled = isDamage;
                if (isDamage) {
                    whSelect.removeAttribute('required');
                } else {
                    whSelect.setAttribute('required', 'required');
                }
            }
            updateRowStockBadge(row);
        }

        async function fetchStockForProduct(productId) {
            const pid = String(productId);
            if (stockCache[pid]) {
                return stockCache[pid];
            }
            const res = await fetch(
                `${base}SalesReturn/warehouse_stock_for_receive?product_id=${encodeURIComponent(pid)}`
            );
            const json = await res.json();
            const rows = Array.isArray(json)
                ? json
                : (Array.isArray(json?.data) ? json.data : []);
            stockCache[pid] = rows;
            return rows;
        }

        function formatStockLabel(w) {
            const phys = parseFloat(w.physical_qty) || 0;
            const avail = parseFloat(w.available_qty) || 0;
            return `${w.warehouse_name} (Phys: ${phys.toFixed(2)}, Avail: ${avail.toFixed(2)})`;
        }

        async function enrichWarehouseOptions(row) {
            const productId = row.dataset.productId;
            const select = row.querySelector('.sr-warehouse-select');
            if (!productId || !select || select.dataset.stockLoaded === '1') {
                return;
            }

            const current = select.value;
            const rows = await fetchStockForProduct(productId);
            const byId = {};
            rows.forEach((w) => {
                byId[String(w.id)] = w;
            });

            Array.from(select.options).forEach((opt) => {
                if (!opt.value) return;
                const w = byId[opt.value];
                if (w) {
                    opt.textContent = formatStockLabel(w);
                    opt.dataset.physical = String(w.physical_qty ?? 0);
                    opt.dataset.available = String(w.available_qty ?? 0);
                }
            });

            if (current) {
                select.value = current;
            }
            select.dataset.stockLoaded = '1';
            updateRowStockBadge(row);
        }

        function updateRowStockBadge(row) {
            const badge = row.querySelector('[data-role="wh-stock-badge"]');
            const select = row.querySelector('.sr-warehouse-select');
            const cond = row.querySelector('.sr-condition-select');
            if (!badge) return;

            if (cond && cond.value === 'Damage') {
                badge.textContent = 'N/A';
                badge.className = 'sr-wh-stock-badge is-muted';
                badge.title = 'Damaged goods are not restocked';
                return;
            }

            if (!select || !select.value) {
                badge.textContent = '—';
                badge.className = 'sr-wh-stock-badge';
                badge.title = 'Select a warehouse';
                return;
            }

            const opt = select.options[select.selectedIndex];
            const phys = parseFloat(opt?.dataset?.physical) || 0;
            const avail = parseFloat(opt?.dataset?.available) || 0;
            badge.textContent = phys.toFixed(2);
            badge.className = 'sr-wh-stock-badge is-ok';
            badge.title = `Physical on hand: ${phys.toFixed(2)} · Available: ${avail.toFixed(2)}`;
        }

        function updateProgress() {
            const selects = warehouseSelects();
            if (!selects.length) return;
            let filled = 0;
            selects.forEach((s) => {
                if (!s.disabled && s.value) filled += 1;
            });
            const required = Array.from(selects).filter((s) => !s.disabled).length;
            const pct = required ? Math.round((filled / required) * 100) : 100;
            if (progressBar) progressBar.style.width = pct + '%';

            checklistItems.forEach((el) => {
                const key = el.getAttribute('data-check');
                if (key === 'warehouse') {
                    el.classList.toggle('done', filled === required);
                }
            });
        }

        function markInvalid(select, invalid) {
            const row = select.closest('tr');
            if (row) row.classList.toggle('is-invalid-row', invalid);
            select.classList.toggle('is-invalid', invalid);
        }

        form.querySelectorAll('tbody tr[data-product-id]').forEach((row) => {
            enrichWarehouseOptions(row);
        });

        conditionSelects().forEach((sel) => {
            const row = sel.closest('tr');
            if (row) syncRowState(row);
            sel.addEventListener('change', () => {
                const r = sel.closest('tr');
                if (r) syncRowState(r);
                updateProgress();
            });
        });

        warehouseSelects().forEach((sel) => {
            sel.addEventListener('change', () => {
                markInvalid(sel, !sel.disabled && !sel.value);
                const row = sel.closest('tr');
                if (row) updateRowStockBadge(row);
                updateProgress();
            });
            if (sel.value) markInvalid(sel, false);
        });

        if (applyBulkBtn && bulkSelect) {
            applyBulkBtn.addEventListener('click', function () {
                const wid = bulkSelect.value;
                if (!wid) {
                    Swal.fire('Select warehouse', 'Choose a warehouse to apply to all lines.', 'info');
                    return;
                }
                warehouseSelects().forEach((s) => {
                    if (s.disabled) return;
                    s.value = wid;
                    markInvalid(s, false);
                    const row = s.closest('tr');
                    if (row) updateRowStockBadge(row);
                });
                updateProgress();
            });
        }

        updateProgress();

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            let valid = true;
            warehouseSelects().forEach((s) => {
                if (s.disabled) return;
                if (!s.value) {
                    markInvalid(s, true);
                    valid = false;
                }
            });
            if (!valid) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warehouse required',
                    text: 'Select a warehouse for every Good line (or use Apply to all).',
                });
                const first = form.querySelector('.sr-warehouse-select.is-invalid');
                if (first) first.focus();
                return;
            }

            const returnCode = form.dataset.returnCode || 'this return';
            const total = form.dataset.returnTotal || '';

            Swal.fire({
                title: 'Confirm return?',
                html:
                    '<p class="mb-2">You are about to confirm <strong>' +
                    returnCode +
                    '</strong>.</p>' +
                    (total ? '<p class="mb-0">Credit note: <strong>' + total + '</strong><br>Stock will update for <em>Good</em> items only.</p>' : ''),
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, confirm & update stock',
                cancelButtonText: 'Review again',
                confirmButtonColor: '#16a34a',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
})();