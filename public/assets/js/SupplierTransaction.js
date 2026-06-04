/**
 * Supplier payments — index (DataTable + mobile cards), create, reverse.
 */
(function () {
    'use strict';

    const TYPE_HINTS = {
        payment: 'Pay supplier — reduces payable (Dr AP, Cr cash/bank). Updates supplier_ledger and bank book.',
        advance: 'Advance to supplier — same GL flow as payment (Dr AP, Cr cash/bank).',
        receive: 'Receive from supplier (credit/refund) — increases payable (Dr cash/bank, Cr AP).',
    };

    const TYPE_LABELS = {
        payment: 'Payment',
        advance: 'Advance',
        receive: 'Receive',
    };

    let suppTable = null;

    function stBaseUrl() {
        if (window.ST_BOOT?.baseUrl) {
            const u = window.ST_BOOT.baseUrl;
            return u.endsWith('/') ? u : u + '/';
        }
        const el = document.getElementById('base_url');
        const u = el ? el.value : '/';
        return u.endsWith('/') ? u : u + '/';
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatMoney(n) {
        return 'Tk ' + parseFloat(n || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    async function parseJsonResponse(response) {
        const text = await response.text();
        if (!text) {
            return { status: 'error', message: 'Empty server response (HTTP ' + response.status + ')' };
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response', text.slice(0, 500));
            return {
                status: 'error',
                message: 'Server returned an invalid response (HTTP ' + response.status + '). Refresh and try again.',
            };
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const base = stBaseUrl();
        window.ST_BASE = base;

        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-supp-reverse');
            if (!btn) return;
            reverseTransaction(
                btn.dataset.paymentId,
                btn.dataset.paymentCode
            );
        });

        const txnTable = document.getElementById('suppTxnTable');
        if (txnTable && txnTable.querySelector('tbody tr')) {
            initIndexTable();
        } else if (document.getElementById('suppTxnCards')) {
            document.getElementById('suppTxnCards').innerHTML =
                '<p class="text-muted text-center py-4 mb-0">No transactions found.</p>';
        }

        if (document.getElementById('supplierTransactionForm')) {
            initCreateForm(base);
        }

        $(window).on('resize', () => {
            if (suppTable) renderMobileCards(suppTable);
        });
    });

    function initIndexTable() {
        if (typeof $ === 'undefined' || !$.fn.DataTable) {
            renderMobileCardsFromDom();
            return;
        }

        const $table = $('#suppTxnTable');
        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        suppTable = $table.DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                emptyTable: 'No transactions for selected filters',
                search: 'Quick search:',
            },
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            drawCallback() {
                renderMobileCards(suppTable);
            },
        });
    }

    function renderMobileCards(table) {
        const container = document.getElementById('suppTxnCards');
        if (!container || window.innerWidth >= 768) {
            if (container) container.innerHTML = '';
            return;
        }

        const base = window.ST_BASE || stBaseUrl();
        let html = '';

        table.rows({ page: 'current' }).every(function () {
            const row = this.node();
            if (!row) return;
            const $tr = $(row);
            const id = $tr.find('.js-supp-reverse').data('paymentId')
                || $tr.find('a[href*="details/"]').attr('href')?.split('/').pop();
            const code = $tr.find('.branch-code-pill').text().trim();
            const supplier = $tr.find('.branch-name-cell .name').text().trim();
            const mobile = $tr.find('.branch-contact')?.text()?.trim() || '';
            const typePill = $tr.find('.supp-txn-type-pill');
            const typeCls = typePill.attr('class')?.match(/supp-txn-type-pill\s+(\S+)/)?.[1] || 'payment';
            const typeLabel = typePill.text().trim();
            const amount = $tr.find('.supp-txn-amount').text().trim();
            const date = $tr.find('td:first small').text().trim();
            const mode = $tr.find('td').eq(5).text().trim();
            const reversed = $tr.hasClass('table-secondary') || $tr.data('status') === 'reversed';
            const canReverse = $tr.find('.js-supp-reverse').length > 0;

            html += `<article class="supp-txn-mobile-card${reversed ? ' reversed' : ''}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="branch-code-pill">${escapeHtml(code)}</div>
                        <div class="fw-semibold mt-1">${escapeHtml(supplier)}</div>
                        ${mobile ? `<div class="small text-muted">${escapeHtml(mobile)}</div>` : ''}
                    </div>
                    <span class="supp-txn-type-pill ${escapeHtml(typeCls)}">${escapeHtml(typeLabel)}</span>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="small text-muted">${escapeHtml(date)} · ${escapeHtml(mode)}</span>
                    <span class="supp-txn-amount ${escapeHtml(typeCls)}">${escapeHtml(amount)}</span>
                </div>
                <div class="mt-2 d-flex gap-2 flex-wrap">
                    <a href="${base}SupplierTransaction/details/${escapeHtml(id)}" class="btn btn-sm btn-outline-primary flex-fill">
                        <i class="fas fa-eye me-1"></i> Details
                    </a>
                    ${canReverse ? `<button type="button" class="btn btn-sm btn-outline-danger js-supp-reverse flex-fill"
                        data-payment-id="${escapeHtml(id)}"
                        data-payment-code="${escapeHtml(code)}">
                        <i class="fas fa-rotate-left me-1"></i> Reverse
                    </button>` : ''}
                </div>
            </article>`;
        });

        container.innerHTML = html
            || '<p class="text-muted text-center py-4 mb-0">No transactions found.</p>';
    }

    function renderMobileCardsFromDom() {
        const container = document.getElementById('suppTxnCards');
        const tbody = document.querySelector('#suppTxnTable tbody');
        if (!container || !tbody || window.innerWidth >= 768) return;

        const base = window.ST_BASE || stBaseUrl();
        let html = '';
        tbody.querySelectorAll('tr').forEach((tr) => {
            const revBtn = tr.querySelector('.js-supp-reverse');
            const detailsLink = tr.querySelector('a[href*="details/"]');
            const id = revBtn?.dataset.paymentId || detailsLink?.href?.split('/').pop() || '';
            const code = tr.querySelector('.branch-code-pill')?.textContent?.trim() || '';
            const supplier = tr.querySelector('.branch-name-cell .name')?.textContent?.trim() || '';
            const typePill = tr.querySelector('.supp-txn-type-pill');
            const typeCls = [...(typePill?.classList || [])].find((c) => c !== 'supp-txn-type-pill') || 'payment';
            const typeLabel = typePill?.textContent?.trim() || '';
            const amount = tr.querySelector('.supp-txn-amount')?.textContent?.trim() || '';
            const date = tr.querySelector('td small')?.textContent?.trim() || '';
            const reversed = tr.classList.contains('table-secondary');

            html += `<article class="supp-txn-mobile-card${reversed ? ' reversed' : ''}">
                <div class="d-flex justify-content-between">
                    <strong class="branch-code-pill">${escapeHtml(code)}</strong>
                    <span class="supp-txn-type-pill ${escapeHtml(typeCls)}">${escapeHtml(typeLabel)}</span>
                </div>
                <div class="mt-1">${escapeHtml(supplier)}</div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="small text-muted">${escapeHtml(date)}</span>
                    <span class="supp-txn-amount ${escapeHtml(typeCls)}">${escapeHtml(amount)}</span>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <a href="${base}SupplierTransaction/details/${id}" class="btn btn-sm btn-outline-primary">Details</a>
                    ${revBtn ? `<button type="button" class="btn btn-sm btn-outline-danger js-supp-reverse"
                        data-payment-id="${id}" data-payment-code="${escapeHtml(code)}">Reverse</button>` : ''}
                </div>
            </article>`;
        });
        container.innerHTML = html || '<p class="text-muted text-center py-4">No transactions found.</p>';
    }

    function initCreateForm(base) {
        const form = document.getElementById('supplierTransactionForm');
        const modeSelect = document.getElementById('mode');
        const bankSection = document.getElementById('bank_section');
        const bankSelect = document.getElementById('bank_id');
        const supplierSelect = document.getElementById('supplier_id');
        const dueSummary = document.getElementById('dueSummary');
        const typeSelect = document.getElementById('transaction_type');
        const amountInput = document.getElementById('amount');
        const typeHint = document.getElementById('typeHint');

        function syncModeVisibility() {
            if (modeSelect && bankSection) {
                bankSection.style.display = modeSelect.value === 'bank' ? 'block' : 'none';
                if (bankSelect) {
                    if (modeSelect.value === 'bank') {
                        bankSelect.setAttribute('required', 'required');
                    } else {
                        bankSelect.removeAttribute('required');
                    }
                }
            }
        }

        if (modeSelect && bankSection) {
            modeSelect.addEventListener('change', syncModeVisibility);
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', () => {
                const t = typeSelect.value;
                if (typeHint) {
                    typeHint.textContent = TYPE_HINTS[t] || '';
                }
                syncModeVisibility();
                updatePreview();
            });
        }

        function updatePreview() {
            const supOpt = supplierSelect?.selectedOptions?.[0];
            const name = supOpt?.textContent?.trim() || 'Supplier';
            const initial = name.charAt(0) || '?';
            const type = typeSelect?.value || '';
            const amt = parseFloat(amountInput?.value || 0);

            document.getElementById('previewAvatar')?.replaceChildren(document.createTextNode(initial));
            const pn = document.getElementById('previewSupplier');
            if (pn) pn.textContent = name.split('(')[0].trim();
            const pt = document.getElementById('previewType');
            if (pt) pt.textContent = TYPE_LABELS[type] || 'Select type';
            const pa = document.getElementById('previewAmount');
            if (pa) {
                pa.textContent = formatMoney(amt);
                pa.className = 'mt-2 supp-txn-amount ' + (type || 'payment');
            }
        }

        [supplierSelect, typeSelect, amountInput].forEach((el) => {
            if (el) el.addEventListener('input', updatePreview);
            if (el) el.addEventListener('change', updatePreview);
        });

        if (supplierSelect && dueSummary) {
            supplierSelect.addEventListener('change', async function () {
                const supplierId = this.value;
                updatePreview();
                if (!supplierId) {
                    dueSummary.classList.add('d-none');
                    dueSummary.innerHTML = '';
                    return;
                }

                dueSummary.classList.remove('d-none');
                dueSummary.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading payable…';

                try {
                    const response = await fetch(base + 'SupplierTransaction/get_due', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        credentials: 'same-origin',
                        body: 'supplier_id=' + encodeURIComponent(supplierId),
                    });
                    const data = await parseJsonResponse(response);
                    if (data.status === 'success') {
                        dueSummary.innerHTML =
                            '<i class="fas fa-wallet me-1"></i> Payable now: <strong>' +
                            formatMoney(data.due_balance) + '</strong>';
                    } else {
                        dueSummary.innerHTML = '<span class="text-danger">Could not load payable balance</span>';
                    }
                } catch (e) {
                    console.error('Failed to load supplier due', e);
                    dueSummary.innerHTML = '<span class="text-danger">Error loading payable</span>';
                }
            });

            if (supplierSelect.value) {
                supplierSelect.dispatchEvent(new Event('change'));
            }
        }

        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }

                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn?.innerHTML;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';
                }

                try {
                    const response = await fetch(base + 'SupplierTransaction/store', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const result = await parseJsonResponse(response);

                    if (result.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            html: (result.message || 'Transaction recorded.') +
                                (result.payment_code
                                    ? '<br><small>' + escapeHtml(result.payment_code) + '</small>'
                                    : ''),
                            timer: 2200,
                            showConfirmButton: false,
                        }).then(() => {
                            const pid = result.payment_id;
                            window.location.href = pid
                                ? base + 'SupplierTransaction/details/' + pid
                                : base + 'SupplierTransaction';
                        });
                    } else {
                        Swal.fire('Error', result.message || 'Save failed', 'error');
                    }
                } catch (err) {
                    console.error(err);
                    Swal.fire('Error', err.message || 'Network or server error', 'error');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            });
        }

        syncModeVisibility();
        updatePreview();
    }

    async function reverseTransaction(id, paymentCode) {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        const { value: reason, isConfirmed } = await Swal.fire({
            title: 'Reverse payment?',
            html:
                'Transaction <strong>' + escapeHtml(paymentCode || id) + '</strong> will be reversed.<br>' +
                '<span class="text-danger small">GL, supplier ledger, and bank book (if any) are undone. This cannot be undone.</span>',
            input: 'textarea',
            inputLabel: 'Reason (required, min 3 characters)',
            inputPlaceholder: 'Why is this payment being reversed?',
            inputAttributes: { 'aria-label': 'Reversal reason', maxlength: 500 },
            showCancelButton: true,
            confirmButtonText: 'Yes, reverse',
            confirmButtonColor: '#dc2626',
            focusConfirm: false,
            preConfirm: (value) => {
                const r = String(value || '').trim();
                if (r.length < 3) {
                    Swal.showValidationMessage('Please enter at least 3 characters.');
                    return false;
                }
                return r;
            },
        });

        if (!isConfirmed || !reason) return;

        Swal.fire({
            title: 'Reversing…',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            const body = new URLSearchParams({
                csrf_token: csrf,
                id: String(id),
                reason: reason.trim(),
            });
            const response = await fetch((window.ST_BASE || stBaseUrl()) + 'SupplierTransaction/reverse', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: body.toString(),
            });
            const result = await parseJsonResponse(response);
            Swal.close();

            if (result.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Reversed',
                    text: result.message,
                    timer: 2000,
                    showConfirmButton: false,
                }).then(() => {
                    const url = result.redirect_url
                        || (window.ST_BASE || stBaseUrl()) + 'SupplierTransaction/details/' + id;
                    window.location.href = url;
                });
            } else {
                Swal.fire('Reversal failed', result.message || 'Could not reverse', 'error');
            }
        } catch (e) {
            Swal.close();
            Swal.fire('Error', 'Could not reach server', 'error');
        }
    }

    window.reverseTransaction = reverseTransaction;
})();