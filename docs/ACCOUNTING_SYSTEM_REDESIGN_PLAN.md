# Professional Accounting System Redesign Plan

> **Last Updated:** June 2026 — Major Ledger & Chart of Accounts improvements + First Verification Report completed

## Executive Summary

The current ERP has a **sub-ledger heavy** accounting approach with very weak General Ledger (double-entry) capabilities. Most transactions only update balances and sub-ledgers (customer_ledger, supplier_ledger, etc.) rather than posting proper journal entries.

This document outlines a **phased, safe migration strategy** to implement a proper professional double-entry accounting system without breaking existing functionality.

---

## Where We Are Right Now (June 2026)

**High-level Status:**
- Foundation of double-entry accounting is solid (`journal_entries` + `journal_lines` + posting service)
- Three low-risk modules are fully posting to GL with reversals (Other Expense, Other Income, Money Transfer)
- Ledger / Chart of Accounts management has been heavily improved and protected
- We now have a working **Trial Balance** report to verify the system
- Major business flows (Sales, Purchase) are **not yet** integrated into the journal system

**Current Focus:** Making the Chart of Accounts usable and protected + having basic verification tools before moving to high-impact modules.

---

## Progress Update (June 2026)

### Completed Work

**Core Accounting Engine**
- `journal_entries` + `journal_lines` tables created (migrations 001 & 002)
- `JournalEntryModel` with `createEntry()` and `createReversingEntry()`
- `JournalPostingService` as central posting engine
- Improved `ledgers` table structure + default Chart of Accounts seed

**Phase 2: Foundational Modules (Completed)**
- **Other Expense**: Full modernization
  - Server-side DataTables + advanced filters + "Show Reversed" mode
  - Modern create form with live double-entry preview
  - Proper journal posting via `JournalPostingService`
  - Reversal using reversing journal entries (`reversal_of_entry_id`)
  - Audit logging + dedicated audit page
  - `journal_entry_id` populated on create

- **Other Income (Receipts)**: Full modernization (recent)
  - Index + Create form brought to same standard as Other Expense
  - Full journal integration + reversal support
  - Audit support

- **Money Transfer**: Significant progress
  - Modern index with rich filters (type, branches, dates, status) + "Show Reversed" + mobile cards
  - Create form rebuilt with dynamic fields per transfer type + live accounting preview
  - Strict branch rules enforced (From side always tied to `$_SESSION['branch_id']`)
  - Journal posting + reversal (prefers journal reversal)
  - Rich audit logging for both creation and reversal (includes type, amount, branches, etc.)
  - **Gap**: `journal_entry_id` is still not being updated on creation (unlike Other Expense & Other Income)

**Reversal Experience**
- All three modules now use proper reversing journal entries instead of only legacy balance flips.
- Consistent "Show Reversed" UX across modules.

**Database Reality Check (Updated)**
- Core `journal_entries` + `journal_lines` tables exist and contain real data (including several reversal entries).
- Migration 002 (`journal_entry_id` column) **has now been applied** to the database.
  - The column exists on `money_transfers`, `other_expenses`, and `other_incomes`.
  - A few records already have `journal_entry_id` populated (mostly Other Income and some reversals).
- Actual journal entries are being created for Other Expense, Other Income, and Money Transfer in the current data.
- `journal_entry_id` is being updated on create for **Other Expense** and **Other Income**, but **not yet** for Money Transfer (code gap).

**Immediate Technical Debt**
- MoneyTransferModel still does not update `journal_entry_id` after calling `postMoneyTransfer()`. This should be fixed for consistency.

---

### Latest Progress (Recent Session)

**Ledger / Chart of Accounts Module — Major Overhaul**
- Complete rewrite of Ledger index (server-side DataTables + advanced filters + Show Active/Inactive + Mobile Card View)
- Full Create & Edit forms supporting all modern fields:
  - `normal_balance`
  - `is_control_account` + `control_account_type`
  - `ledger_nature` (greatly expanded)
  - `is_system` flag (with strong protection)
- Strong protection added for `is_system` ledgers (cannot be easily modified/deleted in controller + model + UI)
- Fixed Active/Inactive toggle behavior on ledger list
- **Creative UX for non-accountants** on create page:
  - 10 business-friendly scenario cards ("Customers still have to pay me", "Stock lying in godown", "Transportation & delivery charges", etc.)
  - Live smart suggestions while typing ledger name (keyword-based auto-fill of Type + Nature)
  - Clear explanations shown after selection

**Chart of Accounts Structure Improvement**
- `ledger_nature` list significantly expanded and professionalized (from 8 generic values to ~22 meaningful ones)
- Database column changed from restrictive `ENUM` to flexible `VARCHAR(60)`
- Default seed file updated with proper natures (`sales_revenue`, `cogs`, `inventory`, `payroll_expense`, `fixed_asset`, etc.)
- Forms (create/edit) and filters updated with grouped `<optgroup>` dropdowns

**Verification & Reporting**
- Created **Trial Balance Report** (`/report/TrialBalance`)
  - Date range + Account Type filter
  - Clear "System is BALANCED" / "NOT BALANCED" status with difference amount
  - Grand totals for Debits vs Credits
  - CSV export
  - This is currently the primary tool to verify the double-entry system is working correctly

---

## Current State Assessment

### Strengths
- Good sub-ledger tracking (Customer, Supplier, Employee)
- Cash and Bank balance tracking exists
- Reversal support in several modules
- Branch-level separation

### Critical Gaps (Updated)
1. **Core journal engine is built** — but only used by 3 low-risk modules so far.
2. **Major business transactions still not integrated** (Sales, Purchase, etc.)
3. **Migration 002 (`journal_entry_id` column) not applied** to production DB.
4. **Financial statements still cannot be reliably generated**
5. **Accounting logic** is now more centralized in `JournalPostingService`, but many modules still bypass it.
6. **No period closing / year-end process** yet.

---

## Target Architecture

### Core Principles
- **Double-Entry** for every financial transaction
- **Single Source of Truth**: All financial impact goes through a central `journal_entries` + `journal_lines` system
- **Sub-ledgers remain**, but become **derived** from journal entries (or kept in sync)
- **Chart of Accounts** becomes the foundation
- **Auditability**: Every journal entry must be traceable

### Proposed Core Tables

#### 1. `journals` (or keep `ledgers`)
- Improve existing `ledgers` table

#### 2. `journal_entries` (New - Master)
```sql
- id
- entry_no (e.g., JE-2026-000123)
- entry_date
- reference_type (sales_invoice, purchase_receive, money_transfer, etc.)
- reference_id
- description
- total_debit
- total_credit
- branch_id
- created_by
- posted_at
- is_reversed
- reversal_of (self reference)
```

#### 3. `journal_lines` (New - Detail)
```sql
- id
- journal_entry_id
- ledger_id (from ledgers table)
- debit
- credit
- description
- entity_type (customer, supplier, employee, bank, etc.)
- entity_id
```

#### 4. Keep existing sub-ledgers (with changes)
- `customer_ledger`, `supplier_ledger`, etc. can be **maintained as views or materialized** for performance, or kept as-is with triggers/sync logic.

---

## Phased Implementation Plan

### Phase 0: Foundation & Design (Current)
- [ ] Finalize target data model
- [ ] Design Chart of Accounts structure (recommended default heads)
- [ ] Decide integration strategy (Real-time vs Event-driven vs Batch)
- [ ] Create migration strategy for existing data

### Phase 1: Core Accounting Engine
- Improve `ledgers` table + management (add system flags, nature enforcement)
- Create `journal_entries` and `journal_lines` tables
- Build `JournalEntryModel` + service layer
- Build basic posting engine
- Create UI for manual journal entries + viewing

### Phase 2: Foundational Transaction Integration (Low Risk) — **COMPLETED**
- ✅ Other Expense (full modern UI + journal + reversal + audit)
- ✅ Other Income (full modern UI + journal + reversal + audit)
- ✅ Money Transfer (modern UI + branch rules + journal + rich audit)

### Phase 3: Core Business Transactions (High Impact) — **NEXT PRIORITY**
- Sales Invoices + Sales Returns
- Purchase + Purchase Returns
- Damage / Stock Adjustments
- Employee salary & transactions
- Customer/Supplier payments (currently using old sub-ledger logic)

### Phase 4: Advanced Features
- Automated recurring entries
- Period closing
- Multi-currency (if needed)
- Budgeting & Cost Centers

### Phase 5: Reporting & Financial Statements
- ✅ Trial Balance (basic but functional — created as verification tool)
- Profit & Loss
- Balance Sheet
- General Ledger Report (detailed per account)
- Cash Flow Statement
- Journal Entry Listing / Audit Report

### Phase 6: Data Migration & Cutover
- Historical data migration strategy
- Parallel run period
- Go-live

---

## Recommended First Steps (Phase 0.5)

Before writing any code, we should decide:

1. **Integration Philosophy**
   - Option A: Real-time posting (every transaction immediately creates journal entries)
   - Option B: Event-driven (use observers/listeners)
   - Option C: Batch posting at end of day (safer for migration)

2. **Handling of Existing Sub-ledgers**
   - Keep them and sync from journal entries?
   - Deprecate them gradually?

3. **Default Chart of Accounts**
   - We should design a solid default structure suitable for a trading/distribution business.

Would you like me to start with:

**A.** Create the full recommended database schema changes + migration scripts

**B.** Design a solid default Chart of Accounts structure

**C.** Create the `JournalEntryService` architecture first (code design)

**D.** Do a detailed impact analysis on how Sales/Purchase should post to accounts

---

## Current Recommended Next Steps (June 2026)

### Immediate Priorities
1. **Fix MoneyTransferModel** — update `journal_entry_id` on creation (small but important consistency gap).
2. **Fully integrate Sales module** (highest business impact).
3. **Integrate Purchase + Purchase Returns**.
4. **Build General Ledger Report** and improve Trial Balance (add opening/closing balances).
5. **Add proper audit logging** for Sales/Purchase transactions.

### Recently Completed (New)
- Ledger module fully modernized + protected
- `ledger_nature` structure significantly improved (new values + flexible column)
- Creative non-accountant UX added to Ledger creation
- **Trial Balance Report** created as the main verification tool for the accounting system

### Technical Debt / Polish
- Money Transfer still missing `journal_entry_id` update on creation.
- Many system ledgers in seed are using better natures now.
- `is_system` protection is now strong across model/controller/UI.
- Add ability to view journal entries from the Trial Balance / Ledger screens.

### Strategic Questions Still Open
- Should we backfill journal entries for historical data?
- When do we start deprecating direct updates to `customer_ledger` / `supplier_ledger`?
- Do we want a "Post to GL" batch job as a transition layer?

---

## Previous Recommended First Steps (Kept for reference)

Before writing any code, we should decide:
... (original content below)
