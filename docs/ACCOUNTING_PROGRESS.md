# Accounting System Implementation Progress

## Completed (Phase A - Foundation)

- [x] Designed improved `ledgers` table structure
- [x] Created `journal_entries` and `journal_lines` tables (migration)
- [x] Seeded recommended default Chart of Accounts
- [x] Enhanced `LedgerModel.php` with modern methods
- [x] Created `JournalEntryModel.php`
- [x] Created `JournalPostingService.php` (core engine)

## Current Status

We now have the basic infrastructure for proper double-entry accounting.

## Next Recommended Steps

1. Create database tables using the migration script.
2. Seed the default Chart of Accounts.
3. Start integrating simple transactions (Other Income / Other Expense) using the `JournalPostingService`.
4. Build proper posting logic for Sales, Purchases, etc. in later phases.

## Important Notes

- The `JournalPostingService` is currently a skeleton with examples.
- We will expand it significantly as we integrate real business transactions.
- All posting should eventually go through this service for consistency.

## Reversal Work (Latest)

- OtherExpense, OtherIncome (Receipt), and MoneyTransfer now use **proper reversing journal entries** via `JournalEntryModel::createReversingEntry()` (swaps Dr/Cr on the original JE, links via `reversal_of_entry_id`).
- Legacy direct cash/bank flips removed or demoted to fallback only for pre-journal records.
- "Show Reversed" UX implemented for OtherExpense (modeled exactly after the "Show Deleted" pattern used in Bank/Supplier/Customer): `?reversed=1` toggle, conditional action buttons, professional SweetAlert explaining GL impact + audit reason required.
- Direct `journal_entry_id` FK now populated on create for traceability (migration 002 column).
- Details pages surface both Original + Reversing journal lines for full audit visibility.
- UserAudit logging added for all reversals (`other_expense_reversed`, `other_income_reversed`).
- CSRF hardening on reverse endpoints.

This completes the "Proper reversal experience" request for the new accounts ledgers system.

---

## Sales Module GL Integration (Phase 5 — June 2026)

**Posting engine:** `app/services/Accounting/JournalPostingService.php`

| Event | Journal | Linked column |
|-------|---------|---------------|
| Invoice finalized | Dr AR / Cr Sales Revenue (+ transport) | `sales_invoices.journal_entry_id` |
| Transport/total change at challan | Dr/Cr AR vs revenue (delta) | `reference_type = sales_invoice_adjustment` |
| Challan completed | Dr COGS / Cr Inventory | `sales_challans.journal_entry_id` |
| Customer payment | Dr Bank/Cash / Cr AR | `customer_payments.journal_entry_id` |
| Return confirmed | Dr Sales Return / Cr AR; Dr Inventory / Cr COGS | `sales_returns.journal_entry_id` |

**Required ledger natures** (from `database/seeds/default_chart_of_accounts.sql`):

- `customer_receivable` — AR control
- `sales_revenue` — revenue (and transport, unless split later)
- `cogs` + `inventory` — challan issue / return restore
- `sales_return` — return revenue reversal
- `cash_bank` — payments

### Trial Balance verification checklist

1. Run migrations `007` and `008` (and `009` for challan reversal metadata) if not applied.
2. Confirm ledgers exist with correct `ledger_nature` (Ledger → Chart of Accounts).
3. **One full cycle:**
   - Create & finalize sales invoice → check `sales_invoices.journal_entry_id` populated.
   - Complete challan with dispatch → check `sales_challans.journal_entry_id` and COGS amount.
   - Receive partial payment on invoice → check `customer_payments.journal_entry_id`.
   - Create & confirm sales return (good stock) → check `sales_returns.journal_entry_id`.
4. Open **Report → Trial Balance** for the test date range → status should be **BALANCED**.
5. Optional reversals: delete draft-only invoice (with JE reverse), reverse challan, reverse return — net GL effect should zero out.

**Sub-ledger note:** `customer_ledger` remains the operational AR detail; AR control in GL should reconcile to the sum of customer balances over time (full reconciliation report = future enhancement).