// Account-transactions drill-down modal for the P&L report page.
// Reads endpoint URLs from data-* attributes on #txn-modal so we never hardcode
// route strings on the JS side.

const modal = document.getElementById('txn-modal');
if (modal) {
    const body = document.getElementById('txn-modal-body');
    const titleEl = document.getElementById('txn-modal-title');
    const transactionsUrl = modal.dataset.transactionsUrl;
    const attachmentBase = modal.dataset.attachmentBase;
    const fmt = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

    const typeLabels = {
        invoice: 'Invoice',
        bill: 'Bill',
        vendor_payment: 'Vendor Payment',
        customer_payment: 'Customer Payment',
    };

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[c]));
    }

    function prettyType(t) {
        if (!t) return '';
        const key = t.toLowerCase();
        return typeLabels[key] || t.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function openModal(title, html) {
        titleEl.textContent = title;
        body.innerHTML = html;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
        body.innerHTML = '<div class="modal-loading">Loading...</div>';
    }

    document.querySelectorAll('[data-modal-close]').forEach((btn) => btn.addEventListener('click', closeModal));
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    function renderReportHeader(meta) {
        if (!meta) return '';
        const account = meta.account_name || '';
        const period =
            meta.from_date_display && meta.to_date_display
                ? `From ${meta.from_date_display} To ${meta.to_date_display}`
                : '';
        return `
            <div class="txn-report-header">
                ${meta.organization_name ? `<div class="org">${escapeHtml(meta.organization_name)}</div>` : ''}
                <div class="title">Account Transactions</div>
                <div class="basis">Basis : ${escapeHtml(meta.basis || 'Accrual')}</div>
                ${account ? `<div class="account">${escapeHtml(account)}</div>` : ''}
                ${period ? `<div class="period">${escapeHtml(period)}</div>` : ''}
            </div>`;
    }

    function renderBalanceRow(b) {
        if (!b) return '';
        const debitCell = b.debit ? `₹${fmt.format(b.debit)}` : '';
        const creditCell = b.credit ? `₹${fmt.format(b.credit)}` : '';
        return `
            <tr class="balance-row">
                <td>${escapeHtml(b.label || '')}</td>
                <td>${escapeHtml(b.name || '')}</td>
                <td colspan="4"></td>
                <td class="num">${debitCell}</td>
                <td class="num">${creditCell}</td>
                <td class="num"></td>
                <td></td>
            </tr>`;
    }

    function renderTotalsRow(totals, meta) {
        if (!totals) return '';
        const range =
            meta && meta.from_date_display && meta.to_date_display
                ? ` (${meta.from_date_display} - ${meta.to_date_display})`
                : '';
        return `
            <tr class="totals-row">
                <td colspan="6">Total Debits and Credits${escapeHtml(range)}</td>
                <td class="num">₹${fmt.format(totals.debit || 0)}</td>
                <td class="num">₹${fmt.format(totals.credit || 0)}</td>
                <td class="num"></td>
                <td></td>
            </tr>`;
    }

    function renderTransactions(payload) {
        const txns = payload.transactions || [];

        const rows = txns
            .map((t) => {
                const attachmentCell =
                    t.attachment_type && t.entity_id
                        ? `<a href="${attachmentBase}/${t.attachment_type}/${encodeURIComponent(
                              t.entity_id,
                          )}" target="_blank" rel="noopener">Download</a>`
                        : '<span class="muted">—</span>';

                const amountLabel =
                    t.amount_label ||
                    (t.amount
                        ? fmt.format(t.amount) + (t.debit ? ' Dr' : t.credit ? ' Cr' : '')
                        : '');

                return `
                <tr>
                    <td>${escapeHtml(t.date || '')}</td>
                    <td>${escapeHtml(t.account || '')}</td>
                    <td>${escapeHtml(t.details || '')}</td>
                    <td>${escapeHtml(prettyType(t.transaction_type))}</td>
                    <td>${escapeHtml(t.transaction_number || '')}</td>
                    <td>${escapeHtml(t.reference_number || '')}</td>
                    <td class="num">${t.debit ? fmt.format(t.debit) : ''}</td>
                    <td class="num">${t.credit ? fmt.format(t.credit) : ''}</td>
                    <td class="num">${escapeHtml(amountLabel)}</td>
                    <td>${attachmentCell}</td>
                </tr>`;
            })
            .join('');

        const emptyRow =
            txns.length === 0
                ? '<tr><td colspan="10" class="muted" style="text-align:center;padding:16px;">No transactions for this account in the selected month.</td></tr>'
                : '';

        return `
            ${renderReportHeader(payload.meta)}
            <table class="txn-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>ACCOUNT</th>
                        <th>TRANSACTION DETAILS</th>
                        <th>TRANSACTION TYPE</th>
                        <th>TRANSACTION#</th>
                        <th>REFERENCE#</th>
                        <th class="num">DEBIT</th>
                        <th class="num">CREDIT</th>
                        <th class="num">AMOUNT</th>
                        <th>ATTACHMENT</th>
                    </tr>
                </thead>
                <tbody>
                    ${renderBalanceRow(payload.opening_balance)}
                    ${rows}
                    ${emptyRow}
                    ${renderTotalsRow(payload.totals, payload.meta)}
                    ${renderBalanceRow(payload.closing_balance)}
                </tbody>
            </table>`;
    }

    async function loadTransactions(accountId, month, accountName, monthLabel) {
        openModal(`${accountName} · ${monthLabel}`, '<div class="modal-loading">Loading transactions...</div>');

        try {
            const url = new URL(transactionsUrl, window.location.origin);
            url.searchParams.set('account_id', accountId);
            url.searchParams.set('month', month);

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
            });

            const data = await response.json();
            if (!response.ok || data.success === false) {
                body.innerHTML = `<div class="modal-error">${escapeHtml(
                    data.message || 'Failed to load transactions.',
                )}</div>`;
                return;
            }

            body.innerHTML = renderTransactions(data);
        } catch (err) {
            body.innerHTML = `<div class="modal-error">${escapeHtml(err.message || 'Network error.')}</div>`;
        }
    }

    document.querySelectorAll('.pnl-link').forEach((link) => {
        link.addEventListener('click', () => {
            loadTransactions(
                link.dataset.accountId,
                link.dataset.month,
                link.dataset.accountName,
                link.dataset.monthLabel,
            );
        });
    });
}
