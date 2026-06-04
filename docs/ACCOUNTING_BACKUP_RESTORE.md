# Accounting backup & restore drill (Phase 7)

Critical tables for sales GL and AR sub-ledger:

| Table | Purpose |
|-------|---------|
| `journal_entries` / `journal_lines` | General ledger |
| `customer_ledger` | AR sub-ledger running balances |
| `customer_payments` / `invoice_payment_allocations` | Receipts |
| `document_sequences` | Invoice/payment code counters |
| `sales_invoices` / `sales_challans` | Operational links to journals |

## Backup (recommended weekly + before migrations)

```bash
php database/scripts/backup_accounting_core.php
# Or specify folder:
php database/scripts/backup_accounting_core.php C:\backups\remote-center-erp
```

On Windows without `mysqldump` in PATH, use phpMyAdmin **Export** for the tables above, or add MySQL `bin` to PATH.

## Restore drill (staging only)

1. Copy production backup to staging server.
2. Stop web app (prevent new postings during restore).
3. Restore SQL file into **empty** staging DB or rename tables first.
4. Run `php database/tests/sales_core_smoke.php` and `php database/scripts/run_gl_reconciliation.php`.
5. Open **Sales Audit** checklist — AR/GL items should be green or explain known drift.
6. Spot-check one customer: latest `customer_ledger.running_balance` vs sum of debits−credits.

## Rollback policy

- Do not partial-restore only `customer_ledger` without matching `journal_entries` — balances will diverge.
- After restore, re-run migration `016` sequence sync if invoice codes were regenerated.

## Retention

Keep at least 4 weekly backups; store off-server (encrypted zip).