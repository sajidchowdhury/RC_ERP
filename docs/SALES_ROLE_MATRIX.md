# Sales ecosystem — role matrix (Phase 7)

Roles come from `users.role` / `employees.role` (enum): `admin`, `manager`, `salesman`, `warehouse_manager`, `dispatcher`, `accountant`, `hr`, `other`.

**Admin** bypasses all checks in `RouteAccess`. Branch isolation still applies via `Helper::assertInvoiceAccessible()`.

## Sales (`SalesController`)

| Route / action | salesman | warehouse_manager | dispatcher | accountant | manager | admin |
|----------------|----------|-------------------|------------|------------|---------|-------|
| `sales/create`, cart, `final_sales`, `update` | Yes | — | — | — | Yes | Yes |
| `sales/today`, search, read APIs | Yes* | — | — | Yes | Yes | Yes |
| `sales/delete_invoice` | Yes | — | — | — | Yes | Yes |
| `sales/save_payment` | Yes | — | — | Yes | Yes | Yes |
| `sales/reverse_payment` | — | — | — | Yes | Yes | Yes |
| `sales/reconcile` | — | — | — | Yes | Yes | Yes |
| `sales/export` | Yes | — | — | Yes | Yes | Yes |

\*Salesmen on **Today** see only their own invoices unless `canSeeAllBranchInvoices()` (manager/admin).

## Warehouse (`ChallanController`)

| Route / action | salesman | warehouse_manager | dispatcher | accountant | manager | admin |
|----------------|----------|-------------------|------------|------------|---------|-------|
| `challan` list, godown, finalize challan | — | Yes | Yes | — | Yes | Yes |
| `challan/reverse_challan` | — | — | — | — | Yes | Yes |
| Print copies (`challan_copy`, `godown_copy`) | — | Yes | Yes | — | Yes | Yes |

## Returns (`SalesReturnController`)

| Route / action | salesman | warehouse_manager | accountant | manager | admin |
|----------------|----------|-------------------|------------|---------|-------|
| Create return | Yes | — | — | Yes | Yes |
| Confirm return | — | Yes | Yes | Yes | Yes |
| Reverse return | — | — | Yes | Yes | Yes |

## Audit (`SalesAuditController`)

| Route / action | accountant | manager | admin |
|----------------|------------|---------|-------|
| `SalesAudit/checklist` | Yes | Yes | Yes |
| `SalesAudit/cancel_stale_drafts` | — | Yes | Yes |

## Enforcement

- Matrix file: `app/config/route_roles.php`
- Runtime: `RouteAccess::require('SalesController', 'final_sales')` in controllers
- JSON denial: `{ "status": "error", "code": "forbidden", "message": "..." }` with HTTP 403

## Branch rules (all roles)

- Non-admin users cannot read/write invoices outside their `branch_id` (session).
- Admin may select branch on create; edits lock to invoice branch for non-admins.