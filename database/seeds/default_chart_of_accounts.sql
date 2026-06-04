-- ============================================================================
-- DEFAULT CHART OF ACCOUNTS (Recommended for Trading / Distribution Business)
-- Run this after creating the improved ledgers table structure.
-- ============================================================================

-- Clear existing test data (safe because no real data)
DELETE FROM `ledgers` WHERE id > 0;

-- Reset auto increment
ALTER TABLE `ledgers` AUTO_INCREMENT = 1;

-- ============================================================================
-- LEVEL 1: MAIN GROUPS
-- ============================================================================

INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0001', 'ASSETS', 0, 'Asset', 'asset', 10, 1, 1, 'debit'),
('L-0002', 'LIABILITIES', 0, 'Liability', 'liability', 20, 1, 1, 'credit'),
('L-0003', 'EQUITY', 0, 'Equity', 'equity', 30, 1, 1, 'credit'),
('L-0004', 'INCOME', 0, 'Income', 'sales_revenue', 40, 1, 1, 'credit'),
('L-0005', 'COST OF GOODS SOLD', 0, 'Expense', 'cogs', 50, 1, 1, 'debit'),
('L-0006', 'EXPENSES', 0, 'Expense', 'operating_expense', 60, 1, 1, 'debit');

-- ============================================================================
-- LEVEL 2: SUB GROUPS
-- ============================================================================

-- Assets
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0100', 'Current Assets', 1, 'Asset', 'asset', 110, 1, 1, 'debit'),
('L-0200', 'Fixed Assets', 1, 'Asset', 'asset', 120, 1, 1, 'debit');

-- Liabilities
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0300', 'Current Liabilities', 2, 'Liability', 'liability', 210, 1, 1, 'credit'),
('L-0400', 'Long Term Liabilities', 2, 'Liability', 'liability', 220, 1, 1, 'credit');

-- Equity
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0500', 'Owner''s Equity', 3, 'Equity', 'equity', 310, 1, 1, 'credit'),
('L-0600', 'Retained Earnings', 3, 'Equity', 'equity', 320, 1, 1, 'credit');

-- Income
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0700', 'Sales Revenue', 4, 'Income', 'sales_revenue', 410, 1, 1, 'credit'),
('L-0800', 'Other Income', 4, 'Income', 'other_income', 420, 1, 1, 'credit');

-- Expenses
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0900', 'Administrative Expenses', 6, 'Expense', 'expense', 610, 1, 1, 'debit'),
('L-1000', 'Selling & Distribution Expenses', 6, 'Expense', 'expense', 620, 1, 1, 'debit'),
('L-1100', 'Financial Expenses', 6, 'Expense', 'expense', 630, 1, 1, 'debit');

-- ============================================================================
-- LEVEL 3: CONTROL ACCOUNTS & COMMON LEDGERS (Important)
-- ============================================================================

-- Cash & Bank (Control)
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `is_control_account`, `control_account_type`, `normal_balance`) VALUES
('L-0101', 'Cash in Hand', 7, 'Asset', 'cash_bank', 1110, 1, 1, 0, NULL, 'debit'),
('L-0102', 'Bank Accounts (Control)', 7, 'Asset', 'cash_bank', 1120, 1, 1, 1, 'bank', 'debit');

-- Receivables (Control)
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `is_control_account`, `control_account_type`, `normal_balance`) VALUES
('L-0103', 'Accounts Receivable (Customers)', 7, 'Asset', 'customer_receivable', 1130, 1, 1, 1, 'customer', 'debit');

-- Payables (Control)
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `is_control_account`, `control_account_type`, `normal_balance`) VALUES
('L-0301', 'Accounts Payable (Suppliers)', 11, 'Liability', 'supplier_payable', 2110, 1, 1, 1, 'supplier', 'credit');

-- Inventory
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0104', 'Inventory / Stock', 7, 'Asset', 'inventory', 1140, 1, 1, 'debit');

-- Sales
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0701', 'Sales - Local', 16, 'Income', 'sales_revenue', 4110, 1, 1, 'credit'),
('L-0702', 'Sales Return & Allowances', 16, 'Income', 'sales_return', 4120, 1, 1, 'debit');

-- Cost of Goods Sold
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0501', 'Cost of Goods Sold', 5, 'Expense', 'cogs', 5010, 1, 1, 'debit');

-- Common Expenses
INSERT INTO `ledgers` (`ledger_code`, `ledger_name`, `parent_id`, `account_type`, `ledger_nature`, `sort_order`, `is_active`, `is_system`, `normal_balance`) VALUES
('L-0901', 'Salaries & Wages', 19, 'Expense', 'payroll_expense', 6110, 1, 1, 'debit'),
('L-0902', 'Rent Expense', 19, 'Expense', 'operating_expense', 6120, 1, 1, 'debit'),
('L-0903', 'Utilities', 19, 'Expense', 'operating_expense', 6130, 1, 1, 'debit'),
('L-0904', 'Depreciation', 19, 'Expense', 'depreciation', 6140, 1, 1, 'debit'),
('L-1001', 'Transportation & Delivery', 20, 'Expense', 'operating_expense', 6210, 1, 1, 'debit'),
('L-1002', 'Marketing & Advertising', 20, 'Expense', 'operating_expense', 6220, 1, 1, 'debit'),
('L-1101', 'Bank Charges & Interest', 21, 'Expense', 'financial_expense', 6310, 1, 1, 'debit');

-- ============================================================================
-- Notes:
-- - You can expand this structure as needed.
-- - Control accounts (is_control_account = 1) are important for reconciliation.
-- - We will link Sales → Accounts Receivable, Purchases → Accounts Payable, etc.
-- ============================================================================