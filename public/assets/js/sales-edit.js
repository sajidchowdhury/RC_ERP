/**
 * Premium Sales Edit POS — single draft invoice, locked customer, same UX as create.
 */
(function () {
    'use strict';

    let editCartLoaded = false;

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('sales-edit-app')) return;
        if (!document.getElementById('invoice_id')) return;
        initSalesEditPage();
    });

    async function initSalesEditPage() {
        const baseInput = document.getElementById('base_url');
        if (typeof BASE_URL === 'undefined' && baseInput) {
            window.BASE_URL = baseInput.value;
            if (BASE_URL && !BASE_URL.endsWith('/')) BASE_URL += '/';
        }

        window.isEditMode = true;
        window.currentInvoiceId = document.getElementById('invoice_id')?.value;

        await Promise.all([
            loadBranchForEdit(),
            loadEmployeesForEdit()
        ]);

        applyBootMetaFields();
        const customerId = document.getElementById('customer_id')?.value;
        if (customerId && typeof onCustomerSelected === 'function') {
            onCustomerSelected(customerId);
        }

        initProductSearchEdit();
        initAddToCartEdit();
        initPosStickyBar();
        patchLoadCartForEdit();

        document.getElementById('branch_id')?.addEventListener('change', () => {
            if (window.selectedProduct) showStockInfoEdit(window.selectedProduct);
        });

        await loadInvoiceIntoCart();
    }

    function applyBootMetaFields() {
        const boot = window.SALES_EDIT_BOOT || {};
        const branchSel = document.getElementById('branch_id');
        if (branchSel && boot.branch_id) {
            branchSel.value = String(boot.branch_id);
        }
        if (boot.salesman_id) {
            const sb = document.getElementById('sales_by');
            if (sb) sb.value = String(boot.salesman_id);
        }
        if (boot.sales_person) {
            const sp = document.getElementById('sales_person');
            if (sp) sp.value = String(boot.sales_person);
        }
        if (boot.invoice_date) {
            const dt = document.getElementById('invoice_date');
            if (dt) dt.value = boot.invoice_date;
        }
        if (boot.narration !== undefined) {
            const nar = document.getElementById('narration');
            if (nar) nar.value = boot.narration;
        }
    }

    async function loadBranchForEdit() {
        const showAll = window.CAN_OVERRIDE_BRANCH === true;
        let url = BASE_URL + 'sales/get_branch';
        if (showAll) url += '?all=1';

        const res = await fetch(url);
        const data = parseSalesListResponse(await res.json());
        const sel = document.getElementById('branch_id');
        if (!sel) return;

        const lockedVal = document.getElementById('branch_id_locked')?.value
            || String(window.SALES_EDIT_INVOICE_BRANCH || '');
        const currentValue = lockedVal || sel.value;

        sel.innerHTML = "<option value=''>Select Branch</option>";
        data.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.branch_name)}</option>`;
        });

        if (currentValue) sel.value = currentValue;
        else if (!showAll && window.SESSION_BRANCH_ID) {
            sel.value = String(window.SESSION_BRANCH_ID);
        }
    }

    async function loadEmployeesForEdit() {
        const res = await fetch(BASE_URL + 'sales/get_employees');
        const data = parseSalesListResponse(await res.json());
        const boot = window.SALES_EDIT_BOOT || {};

        ['sales_by', 'sales_person'].forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            const currentValue = id === 'sales_by'
                ? String(boot.salesman_id || sel.value)
                : String(boot.sales_person || sel.value);

            sel.innerHTML = "<option value=''>Select One</option>";
            data.forEach(e => {
                sel.innerHTML += `<option value="${e.id}">${escapeHtml(e.name)}</option>`;
            });
            if (currentValue) sel.value = currentValue;
        });
    }

    async function loadInvoiceIntoCart() {
        if (editCartLoaded) return;
        editCartLoaded = true;

        const customerId = document.getElementById('customer_id')?.value;
        const invoiceId = document.getElementById('invoice_id')?.value;
        if (!customerId || !invoiceId) return;

        const cartArea = document.getElementById('single-cart-area');
        if (cartArea) {
            cartArea.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2 opacity-50"></i>
                    <p class="mb-0">Loading invoice lines…</p>
                </div>`;
        }

        try {
            await clearCustomerCart(customerId);
            await new Promise(r => setTimeout(r, 350));

            const res = await fetch(BASE_URL + `sales/get_invoice_for_edit/${invoiceId}`);
            const data = await res.json();

            if (data.status !== 'success') {
                Swal.fire('Cannot edit', data.message || 'Invoice is locked', 'error')
                    .then(() => { location.href = BASE_URL + 'sales/today'; });
                return;
            }

            if (data.transport_cost !== undefined || data.discount !== undefined) {
                window.SALES_EDIT_AMOUNTS = {
                    transport: parseFloat(data.transport_cost) || 0,
                    discount: parseFloat(data.discount) || 0
                };
            }

            applyBootMetaFields();
            if (data.branch_id) document.getElementById('branch_id').value = String(data.branch_id);
            if (data.salesman_id) document.getElementById('sales_by').value = String(data.salesman_id);
            if (data.sales_person) document.getElementById('sales_person').value = String(data.sales_person);
            if (data.invoice_date) document.getElementById('invoice_date').value = data.invoice_date.split(' ')[0];
            if (data.narration !== undefined) document.getElementById('narration').value = data.narration;

            const items = data.items || [];
            if (!items.length) {
                if (cartArea) {
                    cartArea.innerHTML = '<div class="text-center text-muted py-4">No line items — add products below</div>';
                }
                updatePosStickyBar(0, 0);
                return;
            }

            for (const item of items) {
                const fd = new FormData();
                fd.append('customer_id', customerId);
                fd.append('product_id', item.product_id);
                fd.append('product_name', item.product_name || '');
                fd.append('qty', item.qty);
                fd.append('rate', item.rate);
                await fetch(BASE_URL + 'sales/add_to_cart', salesPostOptions(appendCsrf(fd)));
            }

            await loadCart(customerId);
            applyEditAmounts();
        } catch (e) {
            console.error('Edit load failed', e);
            Swal.fire('Error', 'Failed to load invoice for editing', 'error');
        }
    }

    async function clearCustomerCart(customer_id) {
        const fd = new FormData();
        fd.append('customer_id', customer_id);
        await fetch(BASE_URL + 'sales/clear_tab_cart', salesPostOptions(appendCsrf(fd)));
    }

    function applyEditAmounts() {
        const amounts = window.SALES_EDIT_AMOUNTS || {};
        const container = document.getElementById('single-cart-area');
        if (!container) return;

        const transportEl = container.querySelector('.transport_cost');
        const discountEl = container.querySelector('.discount');
        if (transportEl) transportEl.value = amounts.transport ?? 0;
        if (discountEl) discountEl.value = amounts.discount ?? 0;

        transportEl?.dispatchEvent(new Event('input'));
    }

    function patchLoadCartForEdit() {
        const orig = window.loadCart;
        if (typeof orig !== 'function') return;

        window.loadCart = async function (customer_id) {
            await orig(customer_id);
            customizeEditCartUi();
            applyEditAmounts();
        };
    }

    function customizeEditCartUi() {
        const container = document.getElementById('single-cart-area');
        if (!container) return;

        const submitBtn = container.querySelector('.finalSubmitBtn');
        if (submitBtn) {
            submitBtn.textContent = 'Update invoice';
            submitBtn.classList.remove('btn-success');
            submitBtn.classList.add('btn-primary');
        }

        const label = document.getElementById('posStickyActionLabel');
        if (label) label.textContent = 'Update';
    }

    function initAddToCartEdit() {
        const btn = document.getElementById('addToCartBtn');
        if (!btn) return;

        btn.addEventListener('click', async () => {
            const customer_id = document.getElementById('customer_id')?.value;
            if (!customer_id) {
                Swal.fire('Error', 'Customer missing on this invoice.', 'error');
                return;
            }
            if (!window.selectedProduct) {
                Swal.fire({ icon: 'warning', title: 'Select product', text: 'Search and pick a product.' });
                document.getElementById('productSearch')?.focus();
                return;
            }

            const qty = parseFloat(document.getElementById('quantity').value) || 0;
            const rate = parseFloat(document.getElementById('sales_rate').value) || 0;
            if (qty <= 0 || rate <= 0) {
                Swal.fire('Invalid', 'Qty and rate must be greater than zero.', 'warning');
                return;
            }

            const p = window.selectedProduct;
            const formData = new FormData();
            formData.append('customer_id', customer_id);
            formData.append('product_id', p.id);
            formData.append('product_name', p.product_name);
            formData.append('qty', qty);
            formData.append('rate', rate);

            const res = await fetch(BASE_URL + 'sales/add_to_cart', salesPostOptions(appendCsrf(formData)));
            const result = await res.json();

            if (result.status === 'success') {
                toastAddedEdit();
                resetProductEntryEdit();
                await loadCart(customer_id);
                applyEditAmounts();
            } else {
                Swal.fire('Error', result.message || 'Could not add', 'error');
            }
        });

        document.getElementById('quantity')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                btn.click();
            }
        });
    }

    function resetProductEntryEdit() {
        document.getElementById('productSearch').value = '';
        document.getElementById('quantity').value = 1;
        document.getElementById('sales_rate').value = '';
        document.getElementById('recommandedprice').textContent = '0';
        window.selectedProduct = null;
        document.getElementById('BranchStock')?.classList.add('d-none');
        document.getElementById('productSearch')?.focus();
    }

    function toastAddedEdit() {
        const toast = document.createElement('div');
        toast.className = 'sales-toast';
        toast.innerHTML = '<i class="fas fa-check-circle"></i> Added to invoice';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 1400);
    }

    function initProductSearchEdit() {
        const productSearch = document.getElementById('productSearch');
        const suggestionsBox = document.getElementById('productSuggestions');
        if (!productSearch) return;

        productSearch.addEventListener('input', debounce(async function () {
            const term = this.value.trim();
            if (term.length < 2) {
                suggestionsBox?.classList.remove('show');
                return;
            }
            const branchId = resolveEditBranchId();
            const res = await fetch(BASE_URL + `sales/search_product?term=${encodeURIComponent(term)}&branch_id=${branchId}`);
            const data = parseSalesListResponse(await res.json());

            let html = '';
            data.forEach(p => {
                const stock = parseFloat(p.available_qty) || 0;
                const out = stock <= 0;
                html += `<button type="button" class="sales-suggest-item ${out ? 'disabled' : ''}" data-product='${JSON.stringify(p)}' ${out ? 'disabled' : ''}>
                    <span class="suggest-title">${escapeHtml(p.product_name)} <small class="text-muted">${escapeHtml(p.product_code || '')}</small></span>
                    <span class="badge ${stock > 0 ? 'bg-success' : 'bg-danger'}">${stock} avail</span>
                </button>`;
            });
            suggestionsBox.innerHTML = html || '<div class="sales-suggest-empty">No product found</div>';
            suggestionsBox.classList.add('show');
        }, 280));

        suggestionsBox?.addEventListener('click', e => {
            const btn = e.target.closest('.sales-suggest-item:not(.disabled)');
            if (!btn || !btn.dataset.product) return;
            selectProductEdit(btn);
        });

        productSearch.addEventListener('keydown', e => {
            if (suggestionsBox?.classList.contains('show') && e.key === 'Enter') {
                e.preventDefault();
                const first = suggestionsBox.querySelector('.sales-suggest-item:not(.disabled)');
                if (first) selectProductEdit(first);
            }
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('#productSearch') && !e.target.closest('#productSuggestions')) {
                suggestionsBox?.classList.remove('show');
            }
        });
    }

    function resolveEditBranchId() {
        const locked = document.getElementById('branch_id_locked')?.value;
        if (locked) return locked;
        return document.getElementById('branch_id')?.value
            || window.SALES_EDIT_INVOICE_BRANCH
            || window.SESSION_BRANCH_ID
            || '';
    }

    function selectProductEdit(btn) {
        const p = JSON.parse(btn.dataset.product);
        const stock = parseFloat(p.available_qty) || 0;
        if (stock <= 0) {
            Swal.fire('Out of stock', 'No available stock at this branch.', 'warning');
            return;
        }
        window.selectedProduct = p;
        document.getElementById('productSearch').value = p.product_name;
        document.getElementById('recommandedprice').textContent = parseFloat(p.price || 0).toFixed(2);
        document.getElementById('sales_rate').value = p.price || 0;
        document.getElementById('quantity').value = 1;
        document.getElementById('productSuggestions')?.classList.remove('show');
        showStockInfoEdit(p);
        document.getElementById('addToCartBtn')?.focus();
    }

    async function showStockInfoEdit(product) {
        const container = document.getElementById('BranchStock');
        if (!container || !product) return;

        const branchId = resolveEditBranchId();
        let branchName = window.ACTIVE_BRANCH_NAME || 'Branch';
        let stock = parseFloat(product.available_qty) || 0;

        try {
            const res = await fetch(
                `${BASE_URL}sales/product_stock_at_branch?product_id=${product.id}&branch_id=${branchId}`
            );
            const data = await res.json();
            if (data && data.available_qty !== undefined) {
                stock = parseFloat(data.available_qty) || 0;
                branchName = data.branch_name || branchName;
            }
        } catch (e) {
            console.warn('Stock summary fetch failed', e);
        }

        container.classList.remove('d-none');
        container.innerHTML = `
            <div class="stock-banner-inner">
                <div class="stock-stat">
                    <span class="stock-label">Available <span class="stock-branch-tag">${escapeHtml(branchName)}</span></span>
                    <span class="stock-value ${stock > 0 ? 'text-white' : 'text-danger'}">${stock}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light" onclick="showWarehouseModal(${product.id})">
                    <i class="fas fa-warehouse"></i> Warehouse & pipeline
                </button>
            </div>`;
    }

    window.showWarehouseModal = async function (productId) {
        document.getElementById('stockModal')?.remove();

        const html = `
        <div class="modal fade" id="stockModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content sales-stock-modal">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-warehouse me-2"></i>Stock by warehouse</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small">Branch</label>
                            <select id="modalBranchSelect" class="form-select"></select>
                        </div>
                        <p class="small text-muted mb-2">
                            <strong>Physical</strong> = on hand ·
                            <strong>Pipeline</strong> = reserved on open invoices ·
                            <strong>Available</strong> = can sell now
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Warehouse</th>
                                        <th class="text-end">Physical</th>
                                        <th class="text-end">Pipeline</th>
                                        <th class="text-end">Available</th>
                                    </tr>
                                </thead>
                                <tbody id="modalStockBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('stockModal'));
        modal.show();

        const branchSelect = document.getElementById('modalBranchSelect');
        const res = await fetch(BASE_URL + 'sales/get_branch?for_stock=1');
        const branches = parseSalesListResponse(await res.json());
        const defaultBranch = String(resolveEditBranchId() || window.SESSION_BRANCH_ID || '');

        branchSelect.innerHTML = branches.map(b => {
            const isHome = String(b.id) === String(window.SESSION_BRANCH_ID || '');
            const label = isHome ? `${b.branch_name} (your branch)` : b.branch_name;
            return `<option value="${b.id}">${escapeHtml(label)}</option>`;
        }).join('');

        branchSelect.value = defaultBranch || String(branches[0]?.id || '');
        branchSelect.onchange = () => loadModalStockPipeline(productId, branchSelect.value);
        loadModalStockPipeline(productId, branchSelect.value);
    };

    async function loadModalStockPipeline(productId, branchId) {
        const tbody = document.getElementById('modalStockBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></td></tr>';

        try {
            const res = await fetch(`${BASE_URL}sales/get_warehouse_stock?product_id=${productId}&branch_id=${branchId}`);
            const data = parseSalesListResponse(await res.json());
            let rows = '';
            let tPhys = 0, tPipe = 0, tAvail = 0;

            if (data.length) {
                data.forEach(w => {
                    const phys = parseFloat(w.physical_qty) || 0;
                    const pipe = parseFloat(w.pipeline_qty) || 0;
                    const avail = parseFloat(w.available_qty) || 0;
                    tPhys += phys;
                    tPipe += pipe;
                    tAvail += avail;
                    rows += `<tr>
                        <td>${escapeHtml(w.warehouse_name)}</td>
                        <td class="text-end">${phys.toFixed(2)}</td>
                        <td class="text-end text-warning">${pipe.toFixed(2)}</td>
                        <td class="text-end fw-bold ${avail > 0 ? 'text-success' : 'text-danger'}">${avail.toFixed(2)}</td>
                    </tr>`;
                });
                rows += `<tr class="table-secondary fw-bold">
                    <td>Total</td>
                    <td class="text-end">${tPhys.toFixed(2)}</td>
                    <td class="text-end">${tPipe.toFixed(2)}</td>
                    <td class="text-end">${tAvail.toFixed(2)}</td>
                </tr>`;
            } else {
                rows = '<tr><td colspan="4" class="text-center text-muted">No warehouses</td></tr>';
            }
            tbody.innerHTML = rows;
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">Failed to load</td></tr>';
        }
    }

    function debounce(fn, ms) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), ms);
        };
    }
})();