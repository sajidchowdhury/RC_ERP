/**
 * Customer payments — index (DataTable + mobile cards), create, reverse.
 */
(function () {
    'use strict';

    const TYPE_HINTS = {
        receive: 'Money in from customer — reduces AR (credit ledger). Posts Dr Cash/Bank, Cr AR.',
        payment: 'Refund or advance paid out — increases AR (debit ledger). Posts Dr AR, Cr Cash/Bank.',
        discount: 'Discount allowed — no cash movement. Amount cannot exceed current due.',
        write_off: 'Bad debt write-off — no cash movement. Amount cannot exceed current due.',
    };

    const TYPE_LABELS = {
        receive: 'Receive',
        payment: 'Payment',
        discount: 'Discount',
        write_off: 'Write-off',
    };

    let custTable = null;
    let lastDue = 0;

    function ctBaseUrl() {
        if (window.CT_BOOT?.baseUrl) {
            const u = window.CT_BOOT.baseUrl;
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
        const base = ctBaseUrl();
        window.CT_BASE = base;

        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-cust-reverse');
            if (!btn) return;
            reverseTransaction(
                btn.dataset.paymentId,
                btn.dataset.paymentCode
            );
        });

        const txnTable = document.getElementById('custTxnTable');
        if (txnTable && txnTable.querySelector('tbody tr')) {
            initIndexTable();
        } else if (document.getElementById('custTxnCards')) {
            document.getElementById('custTxnCards').innerHTML =
                '<p class="text-muted text-center py-4 mb-0">No payments found.</p>';
        }

        if (document.getElementById('customerTransactionForm')) {
            initCreateForm(base);
        }

        $(window).on('resize', () => {
            if (custTable) renderMobileCards(custTable);
        });
    });

    function initIndexTable() {
        if (typeof $ === 'undefined' || !$.fn.DataTable) {
            renderMobileCardsFromDom();
            return;
        }

        const $table = $('#custTxnTable');
        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        custTable = $table.DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                emptyTable: 'No payments for selected filters',
                search: 'Quick search:',
            },
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            drawCallback() {
                renderMobileCards(custTable);
            },
        });
    }

    function renderMobileCards(table) {
        const container = document.getElementById('custTxnCards');
        if (!container || window.innerWidth >= 768) {
            if (container) container.innerHTML = '';
            return;
        }

        const base = window.CT_BASE || ctBaseUrl();
        let html = '';

        table.rows({ page: 'current' }).every(function () {
            const row = this.node();
            if (!row) return;
            const $tr = $(row);
            const id = $tr.find('.js-cust-reverse').data('paymentId')
                || $tr.find('a[href*="details/"]').attr('href')?.split('/').pop();
            const code = $tr.find('.branch-code-pill').text().trim();
            const customer = $tr.find('.branch-name-cell .name').text().trim();
            const mobile = $tr.find('.branch-contact')?.text()?.trim() || '';
            const typePill = $tr.find('.cust-txn-type-pill');
            const typeCls = typePill.attr('class')?.match(/cust-txn-type-pill\s+(\S+)/)?.[1] || 'receive';
            const typeLabel = typePill.text().trim();
            const amount = $tr.find('.cust-txn-amount').text().trim();
            const date = $tr.find('td:first small').text().trim();
            const mode = $tr.find('td').eq(5).text().trim();
            const reversed = $tr.hasClass('table-secondary') || $tr.data('status') === 'reversed';
            const canReverse = $tr.find('.js-cust-reverse').length > 0;

            html += `<article class="cust-txn-mobile-card${reversed ? ' reversed' : ''}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="branch-code-pill">${escapeHtml(code)}</div>
                        <div class="fw-semibold mt-1">${escapeHtml(customer)}</div>
                        ${mobile ? `<div class="small text-muted">${escapeHtml(mobile)}</div>` : ''}
                    </div>
                    <span class="cust-txn-type-pill ${escapeHtml(typeCls)}">${escapeHtml(typeLabel)}</span>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="small text-muted">${escapeHtml(date)} · ${escapeHtml(mode)}</span>
                    <span class="cust-txn-amount ${escapeHtml(typeCls)}">${escapeHtml(amount)}</span>
                </div>
                <div class="mt-2 d-flex gap-2 flex-wrap">
                    <a href="${base}CustomerTransaction/details/${escapeHtml(id)}" class="btn btn-sm btn-outline-primary flex-fill">
                        <i class="fas fa-eye me-1"></i> Details
                    </a>
                    ${canReverse ? `<button type="button" class="btn btn-sm btn-outline-danger js-cust-reverse flex-fill"
                        data-payment-id="${escapeHtml(id)}"
                        data-payment-code="${escapeHtml(code)}">
                        <i class="fas fa-rotate-left me-1"></i> Reverse
                    </button>` : ''}
                </div>
            </article>`;
        });

        container.innerHTML = html
            || '<p class="text-muted text-center py-4 mb-0">No payments found.</p>';
    }

    function renderMobileCardsFromDom() {
        const container = document.getElementById('custTxnCards');
        const tbody = document.querySelector('#custTxnTable tbody');
        if (!container || !tbody || window.innerWidth >= 768) return;

        const base = window.CT_BASE || ctBaseUrl();
        let html = '';
        tbody.querySelectorAll('tr').forEach((tr) => {
            const revBtn = tr.querySelector('.js-cust-reverse');
            const detailsLink = tr.querySelector('a[href*="details/"]');
            const id = revBtn?.dataset.paymentId || detailsLink?.href?.split('/').pop() || '';
            const code = tr.querySelector('.branch-code-pill')?.textContent?.trim() || '';
            const customer = tr.querySelector('.branch-name-cell .name')?.textContent?.trim() || '';
            const typePill = tr.querySelector('.cust-txn-type-pill');
            const typeCls = [...(typePill?.classList || [])].find((c) => c !== 'cust-txn-type-pill') || 'receive';
            const typeLabel = typePill?.textContent?.trim() || '';
            const amount = tr.querySelector('.cust-txn-amount')?.textContent?.trim() || '';
            const date = tr.querySelector('td small')?.textContent?.trim() || '';
            const reversed = tr.classList.contains('table-secondary');

            html += `<article class="cust-txn-mobile-card${reversed ? ' reversed' : ''}">
                <div class="d-flex justify-content-between">
                    <strong class="branch-code-pill">${escapeHtml(code)}</strong>
                    <span class="cust-txn-type-pill ${escapeHtml(typeCls)}">${escapeHtml(typeLabel)}</span>
                </div>
                <div class="mt-1">${escapeHtml(customer)}</div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="small text-muted">${escapeHtml(date)}</span>
                    <span class="cust-txn-amount ${escapeHtml(typeCls)}">${escapeHtml(amount)}</span>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <a href="${base}CustomerTransaction/details/${id}" class="btn btn-sm btn-outline-primary">Details</a>
                    ${revBtn ? `<button type="button" class="btn btn-sm btn-outline-danger js-cust-reverse"
                        data-payment-id="${id}" data-payment-code="${escapeHtml(code)}">Reverse</button>` : ''}
                </div>
            </article>`;
        });
        container.innerHTML = html || '<p class="text-muted text-center py-4">No payments found.</p>';
    }

    function initCreateForm(base) {
        const form = document.getElementById('customerTransactionForm');
        const modeSelect = document.getElementById('mode');
        const bankSection = document.getElementById('bank_section');
        const bankSelect = document.getElementById('bank_id');
        const customerSelect = document.getElementById('customer_id');
        const dueSummary = document.getElementById('dueSummary');
        const typeSelect = document.getElementById('transaction_type');
        const amountInput = document.getElementById('amount');
        const typeHint = document.getElementById('typeHint');

        function syncModeVisibility() {
            const type = typeSelect?.value || '';
            const noCash = type === 'discount' || type === 'write_off';
            if (noCash && modeSelect) {
                modeSelect.value = 'cash';
                if (bankSection) bankSection.style.display = 'none';
                if (bankSelect) bankSelect.removeAttribute('required');
            } else if (modeSelect && bankSection) {
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
            const custOpt = customerSelect?.selectedOptions?.[0];
            const name = custOpt?.textContent?.trim() || 'Customer';
            const initial = name.charAt(0) || '?';
            const type = typeSelect?.value || '';
            const amt = parseFloat(amountInput?.value || 0);

            document.getElementById('previewAvatar')?.replaceChildren(document.createTextNode(initial));
            const pn = document.getElementById('previewCustomer');
            if (pn) pn.textContent = name.split('(')[0].trim();
            const pt = document.getElementById('previewType');
            if (pt) pt.textContent = TYPE_LABELS[type] || 'Select type';
            const pa = document.getElementById('previewAmount');
            if (pa) {
                pa.textContent = formatMoney(amt);
                pa.className = 'mt-2 cust-txn-amount ' + (type || 'receive');
            }
        }

        [customerSelect, typeSelect, amountInput].forEach((el) => {
            if (el) el.addEventListener('input', updatePreview);
            if (el) el.addEventListener('change', updatePreview);
        });

        if (customerSelect && dueSummary) {
            customerSelect.addEventListener('change', async function () {
                const customerId = this.value;
                updatePreview();
                if (!customerId) {
                    dueSummary.classList.add('d-none');
                    dueSummary.innerHTML = '';
                    lastDue = 0;
                    return;
                }

                dueSummary.classList.remove('d-none');
                dueSummary.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading due…';

                try {
                    const response = await fetch(base + 'CustomerTransaction/get_due', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        credentials: 'same-origin',
                        body: 'customer_id=' + encodeURIComponent(customerId),
                    });
                const data = await parseJsonResponse(response);
                if (data.status === 'success') {
                        lastDue = parseFloat(data.due_balance || 0);
                        dueSummary.innerHTML =
                            '<i class="fas fa-wallet me-1"></i> Current due: <strong>' +
                            formatMoney(lastDue) + '</strong>';
                    } else {
                        dueSummary.innerHTML = '<span class="text-danger">Could not load due balance</span>';
                    }
                } catch (e) {
                    console.error('Failed to load customer due', e);
                    dueSummary.innerHTML = '<span class="text-danger">Error loading due</span>';
                }
            });

            if (customerSelect.value) {
                customerSelect.dispatchEvent(new Event('change'));
            }
        }

        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }

                const type = typeSelect?.value || '';
                const amt = parseFloat(amountInput?.value || 0);
                if (['discount', 'write_off'].includes(type) && amt > lastDue + 0.01) {
                    Swal.fire(
                        'Amount too high',
                        'Cannot exceed customer due (' + formatMoney(lastDue) + ').',
                        'warning'
                    );
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
                    const response = await fetch(base + 'CustomerTransaction/store', {
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
                            html: (result.message || 'Payment recorded.') +
                                (result.payment_code
                                    ? '<br><small>' + escapeHtml(result.payment_code) + '</small>'
                                    : ''),
                            timer: 2200,
                            showConfirmButton: false,
                        }).then(() => {
                            const pid = result.payment_id;
                            window.location.href = pid
                                ? base + 'CustomerTransaction/details/' + pid
                                : base + 'CustomerTransaction';
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
                'Payment <strong>' + escapeHtml(paymentCode || id) + '</strong> will be reversed.<br>' +
                '<span class="text-danger small">GL, customer ledger, and bank book (if any) are undone. This cannot be undone.</span>',
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
            const response = await fetch((window.CT_BASE || ctBaseUrl()) + 'CustomerTransaction/reverse', {
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
                        || (window.CT_BASE || ctBaseUrl()) + 'CustomerTransaction/details/' + id;
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