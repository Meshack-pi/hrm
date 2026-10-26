# HRM & Payroll System – Project Plan (Kenya)

**Stack:** Laravel (backend) + Vue 3 (frontend)
**Scope:** Phase 1 (MVP). Core features only.

---

## 1. Scope

### In scope (Phase 1)
1. **Users, roles & permissions**
2. **Employee records** – full details, documents, history
3. **Payroll** – Kenyan statutory deductions, payroll runs, approval, payment status tracking
4. **Leave management** – leave types, balances, requests, approvals
5. **Employee self-service (ESS)** – payslips, leave requests, contact detail updates
6. **Exports** – payroll and employee data in PDF, CSV and Excel
7. **Audit trail** – who changed what, and when

### Out of scope (for now)
- Attendance & time tracking
- Exits & offboarding
- Recruitment, performance, training
- Direct bank / M-Pesa API payments (we export payment files instead)

---

## 2. Tech Stack & Packages

| Area | Choice | Why |
|---|---|---|
| Backend | Laravel (latest stable) | Mature, great ecosystem |
| Frontend | Vue 3 + **Inertia.js** | One app, no separate API/auth layer to maintain. Vue pages rendered from Laravel routes |
| UI | Tailwind CSS (+ a component kit e.g. PrimeVue or shadcn-vue) | Fast to build tables, forms, modals |
| Database | MySQL 8 or PostgreSQL | |
| Auth | Laravel Breeze (Inertia + Vue starter) or Fortify | Login, password reset, 2FA |
| Roles & permissions | `spatie/laravel-permission` | Standard for RBAC in Laravel |
| Audit log | `spatie/laravel-activitylog` | Tracks model changes with old/new values |
| Excel / CSV export | `maatwebsite/excel` (Laravel Excel) | Excel + CSV from the same export class |
| PDF | `barryvdh/laravel-dompdf` (or `spatie/laravel-pdf` for better styling) | Payslips, payroll register |
| Money | Store as **decimal(12,2)**, calculate with `brick/math` or careful rounding | Avoid float errors |
| Queues | Laravel queues (database or Redis driver) | Bulk payslip PDFs and emails |
| Testing | Pest / PHPUnit | Payroll calculations must be unit tested |

> **Alternative:** If you later want a mobile app, build Laravel as a pure API (Sanctum) with a separate Vue SPA. For an MVP, Inertia is faster.

---

## 3. Roles & Permissions

### Roles

| Role | Description |
|---|---|
| **Super Admin** | Everything, including company settings, users and statutory rates |
| **HR Manager** | Manage employees, leave types, approve leave and contact-change requests |
| **Payroll Officer** | Prepare payroll: enter variables, calculate, generate reports ("maker") |
| **Finance / Approver** | Approve payroll, export payment files, mark as paid ("checker") |
| **Line Manager** | Approve leave for direct reports, view team details (limited) |
| **Employee** | Self-service only: own profile, payslips, leave |
| **Auditor** (optional) | Read-only access to everything |

### Permission matrix (key permissions)

| Permission | Super Admin | HR | Payroll | Finance | Manager | Employee |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| manage users & roles | ✅ | | | | | |
| manage statutory rates | ✅ | | | | | |
| view all employees | ✅ | ✅ | ✅ | ✅ | team only | own |
| create / edit employees | ✅ | ✅ | | | | |
| view salaries | ✅ | ✅ | ✅ | ✅ | | own |
| edit salary / allowances | ✅ | ✅ | ✅ | | | |
| create & calculate payroll | ✅ | | ✅ | | | |
| approve payroll | ✅ | | | ✅ | | |
| mark payroll as paid | ✅ | | | ✅ | | |
| export payroll | ✅ | | ✅ | ✅ | | |
| manage leave types | ✅ | ✅ | | | | |
| approve leave | ✅ | ✅ | | | team only | |
| apply for leave | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| approve contact-detail changes | ✅ | ✅ | | | | |
| view audit log | ✅ | ✅ | | | | |

**Rules**
- Use **permissions** in code (`can('payroll.approve')`), not role names, so roles can be adjusted without code changes.
- **Maker-checker:** the user who calculated a payroll run **cannot** approve it.
- Use Laravel **Policies** for record-level rules (e.g. a manager can only see their own team).

---

## 4. Database Design

### 4.1 Users & organisation

**users**
`id, name, email, password, phone, employee_id (nullable FK), is_active, two_factor_secret, last_login_at, timestamps`

**departments** – `id, name, code, head_employee_id, timestamps`
**job_titles** – `id, name, department_id, timestamps`
**branches** (optional) – `id, name, location`

### 4.2 Employees

**employees**
| Column | Notes |
|---|---|
| id | |
| employee_number | Auto-generated, unique (e.g. `EMP-0001`) |
| first_name, middle_name, last_name | |
| national_id / passport_number | Unique |
| kra_pin | Unique, validate format (e.g. `A123456789B`) |
| nssf_number | |
| sha_number | SHA/SHIF member number |
| date_of_birth, gender, marital_status | |
| phone, personal_email, work_email | |
| physical_address, county | |
| department_id, job_title_id, branch_id | |
| manager_id | FK → employees (line manager) |
| employment_type | permanent, contract, casual, intern |
| hire_date, probation_end_date, contract_end_date | |
| status | active, on_leave, suspended, inactive |
| has_disability_exemption | boolean + certificate expiry date |
| photo_path | |
| timestamps, soft deletes | |

**employee_next_of_kin** – `employee_id, name, relationship, phone, is_emergency_contact`

**employee_documents** – `employee_id, type (contract, id_copy, kra_pin, certificate…), file_path, uploaded_by, expires_at`

**employee_bank_details**
`employee_id, payment_method (bank | mpesa), bank_name, bank_code, branch_name, branch_code, account_name, account_number, mpesa_number, is_primary, timestamps`
> Encrypt `account_number` and `mpesa_number` using Laravel's `encrypted` cast.

### 4.3 Salary setup

**employee_salaries** (history kept, never overwritten)
`employee_id, basic_salary, effective_from, effective_to, reason, approved_by, timestamps`

**pay_components** (master list)
`id, name, code, type (earning | deduction), is_taxable, is_pensionable, calculation (fixed | percentage_of_basic), is_statutory, is_active`
Examples: House Allowance, Transport Allowance, Overtime, Bonus, HELB, SACCO, Staff Loan, Union Dues.

**employee_pay_components** (recurring items per employee)
`employee_id, pay_component_id, amount_or_rate, effective_from, effective_to`

**employee_reliefs**
`employee_id, type (insurance | pension | mortgage), monthly_premium_or_amount, provider, policy_number, effective_from, effective_to`

### 4.4 Statutory rates (admin-configurable, effective-dated)

> **Never hard-code rates.** They change (NSSF changes each February under the phased rollout; PAYE bands are debated yearly).

**statutory_rates**
`id, code (NSSF | SHIF | AHL | NITA | PERSONAL_RELIEF | INSURANCE_RELIEF …), employee_rate, employer_rate, fixed_amount, min_amount, max_amount, lower_limit, upper_limit, effective_from, effective_to`

**tax_bands**
`id, lower, upper (nullable for top band), rate, effective_from, effective_to`

**Seed data (as of October 2026)**

| Code | Values |
|---|---|
| NSSF | 6% employee + 6% employer; LEL 9,000; UEL 108,000 (max 6,480 each) – from Feb 2026 |
| SHIF | 2.75% of gross, minimum KES 300 (employee only) |
| AHL | 1.5% employee + 1.5% employer, on gross |
| NITA | KES 50 per employee per month (employer only) |
| PERSONAL_RELIEF | KES 2,400 / month |
| INSURANCE_RELIEF | 15% of premiums, max KES 5,000 / month |
| PENSION_CAP | Pension (incl. NSSF) deductible up to KES 30,000 / month |
| DISABILITY_EXEMPTION | First KES 150,000 / month exempt |

| PAYE band (monthly taxable) | Rate |
|---|---|
| 0 – 24,000 | 10% |
| 24,001 – 32,333 | 25% |
| 32,334 – 500,000 | 30% |
| 500,001 – 800,000 | 32.5% |
| Above 800,000 | 35% |

### 4.5 Payroll

**payroll_runs**
| Column | Notes |
|---|---|
| id | |
| period_month, period_year | Unique together |
| status | `draft → calculated → approved → paid` (or `partially_paid`) |
| total_gross, total_deductions, total_net, total_employer_cost | Cached totals |
| calculated_by, calculated_at | |
| approved_by, approved_at | Must differ from calculated_by |
| paid_marked_by, paid_at | |
| locked_at | Once approved, no edits |
| notes | |

**payroll_variables** (one-off items for this month)
`payroll_run_id, employee_id, pay_component_id, amount, description`
Examples: overtime, bonus, arrears, unpaid leave days, salary advance.

**payslips** (one per employee per run – a snapshot)
| Column | Notes |
|---|---|
| payroll_run_id, employee_id | |
| basic_salary, gross_pay, taxable_pay | |
| nssf_employee, nssf_employer | |
| shif | |
| ahl_employee, ahl_employer | |
| nita_employer | |
| paye_before_relief, personal_relief, insurance_relief, paye | |
| total_other_deductions, total_deductions, net_pay | |
| days_worked, unpaid_leave_days | |
| **payment_status** | `pending | paid | failed` |
| payment_method, payment_reference, paid_at | e.g. bank ref or M-Pesa transaction code |
| failure_reason | e.g. "account closed" |
| employee_snapshot (JSON) | Name, KRA PIN, NSSF no, bank details **at time of payroll** |

**payslip_lines**
`payslip_id, pay_component_id, name, type (earning | deduction), amount, is_statutory`

> Payslips store **snapshots** so historical payslips never change when an employee's salary or details change later.

### 4.6 Leave

**leave_types**
`id, name, code, days_per_year, accrual (monthly | upfront), is_paid, pay_percentage (100 | 50 | 0), requires_document, min_service_months, gender_restriction (null | female | male), eligible_employment_types (JSON), max_carry_forward, carry_forward_expiry_months, counts_working_days_only, is_active`

**Seed leave types (Employment Act defaults)**

| Type | Days | Pay | Notes |
|---|---|---|---|
| Annual | 21 working days / year | Full | Accrues 1.75 days/month; after 12 months' service |
| Sick (full pay) | 7 days | Full | After 2 months' service; medical certificate |
| Sick (half pay) | 7 days | 50% | After full-pay sick days used |
| Maternity | 3 months | Full | Female employees |
| Paternity | 2 weeks | Full | Male employees |
| Pre-adoptive | 1 month | Full | |
| Compassionate | Company policy | Full | |
| Unpaid | — | 0% | Deducted in payroll |

**leave_balances**
`employee_id, leave_type_id, year, entitled, accrued, carried_forward, taken, pending, balance (computed)`

**leave_requests**
`id, employee_id, leave_type_id, start_date, end_date, days, reason, attachment_path, relief_employee_id, status (pending | approved | rejected | cancelled), approver_id, approved_at, rejection_reason, timestamps`

**public_holidays**
`date, name, year` – admin-editable (holidays can be gazetted at short notice).

### 4.7 Self-service change requests

**profile_change_requests**
`id, employee_id, field_group (contact | next_of_kin | bank_details), old_values (JSON), new_values (JSON), status (pending | approved | rejected), reviewed_by, reviewed_at, comment`

### 4.8 Audit

**activity_log** – provided by `spatie/laravel-activitylog` (who, what model, old values, new values, IP, timestamp).

---

## 5. Payroll Calculation Logic

Put this in a dedicated service class, e.g. `app/Services/Payroll/PayrollCalculator.php`, with one small class per statutory item (`NssfCalculator`, `ShifCalculator`, `AhlCalculator`, `PayeCalculator`). Each reads its rates from the DB **for the payroll period date**.

### Order of calculation (per employee)

```
1. Basic salary (pro-rated if joined mid-month)
2. + Recurring earnings (allowances)
3. + One-off earnings (overtime, bonus, arrears)
4. − Unpaid leave deduction  (basic ÷ working days × unpaid days)
   = GROSS PAY

5. NSSF employee   = 6% × min(pensionable pay, UEL)
     Tier I  = 6% × min(pay, LEL)
     Tier II = 6% × max(0, min(pay, UEL) − LEL)
6. SHIF            = max(2.75% × gross, 300)
7. AHL employee    = 1.5% × gross

8. TAXABLE PAY = gross
               + taxable non-cash benefits
               − NSSF − SHIF − AHL
               − other registered pension (NSSF + pension capped at 30,000)
               − disability exemption (if applicable, up to 150,000)

9. PAYE before relief = apply tax bands to taxable pay
10. PAYE = max(0, PAYE before relief − personal relief − insurance relief)

11. Other deductions (HELB, SACCO, loans, union dues…)
12. NET PAY = gross − NSSF − SHIF − AHL − PAYE − other deductions

13. Employer costs: NSSF employer, AHL employer (1.5%), NITA (50)
```

### Validation rules
- **Two-thirds rule:** total deductions must not exceed 2/3 of wages – warn and block approval if breached (employee must keep at least 1/3).
- Net pay cannot be negative.
- Flag employees missing KRA PIN, NSSF number, SHA number or payment details before calculation.
- Round each line to 2 decimal places.

### Test cases (must pass exactly)

| Gross | NSSF | SHIF | AHL | Taxable | PAYE | Net |
|---|---|---|---|---|---|---|
| 20,000 | 1,200.00 | 550.00 | 300.00 | 17,950.00 | 0.00 | 17,950.00 |
| 30,000 | 1,800.00 | 825.00 | 450.00 | 26,925.00 | 731.25 | 26,193.75 |
| 100,000 | 6,000.00 | 2,750.00 | 1,500.00 | 89,750.00 | 19,308.35 | 70,441.65 |
| 150,000 | 6,480.00 | 4,125.00 | 2,250.00 | 137,145.00 | 33,526.85 | 103,618.15 |

> The 100,000 row previously showed NSSF 6,480 (the 6% × 108,000 ceiling). That ceiling only
> applies once pay reaches the 108,000 UEL, so a 100,000 earner contributes 6% × 100,000 = 6,000.
> The 150,000 row is above the UEL and is correctly capped at 6,480.

(Assumes no other reliefs, benefits or deductions.)

---

## 6. Payroll Workflow & Payment Status

```
[Draft] ──calculate──▶ [Calculated] ──approve──▶ [Approved / Locked] ──mark paid──▶ [Paid]
   ▲                         │                              │
   └──── recalculate ◀───────┘                              └──▶ [Partially paid] (some failed)
```

1. **Create run** for a month → system loads all active employees.
2. **Enter variables** (overtime, bonus, advances). Unpaid leave pulled automatically from approved leave.
3. **Calculate** → review the payroll register; show variance vs last month (new joiners, salary changes, big differences).
4. **Approve** (different user) → run is locked; payslips become visible in self-service.
5. **Export payment files** (bank CSV per bank template, M-Pesa bulk CSV) with control totals (count + amount).
6. Finance uploads to bank / M-Pesa portal outside the system.
7. **Mark as paid**: whole run or per employee, with payment reference and date. Record failures with reason, re-pay later.
8. Employees get notified (email) that payslips are available.

**Payment status visible on:** payroll run list, each run's employee table (filter by paid/pending/failed), and the employee's profile (payment history).

---

## 7. Exports

All exports respect permissions and are logged in the audit trail.

| Export | PDF | CSV | Excel | Notes |
|---|:-:|:-:|:-:|---|
| Payroll register (all employees, all lines) | ✅ | ✅ | ✅ | Per payroll run |
| Individual payslip | ✅ | | | Download or email; bulk ZIP for all |
| Payroll summary (totals by department) | ✅ | ✅ | ✅ | |
| Bank payment file | | ✅ | ✅ | Matches your bank's upload template |
| M-Pesa bulk payment file | | ✅ | | Phone in `2547XXXXXXXX` format |
| PAYE (P10) schedule | | ✅ | ✅ | iTax upload format |
| NSSF schedule | | ✅ | ✅ | NSSF portal format |
| SHIF schedule | | ✅ | ✅ | SHA portal format |
| Housing Levy schedule | | ✅ | ✅ | |
| Payment status report | ✅ | ✅ | ✅ | Paid / pending / failed |
| Employee list | ✅ | ✅ | ✅ | With filters (department, status, type) |
| Employee full profile | ✅ | | | Details, salary history, leave, payslips |
| Leave balances / leave taken | ✅ | ✅ | ✅ | |

**Implementation:** one Laravel Excel export class per report (handles both CSV and XLSX), and a Blade view per PDF. Large exports run on a queue and notify the user when ready.

---

## 8. Employee Profile (HR view)

Tabbed page per employee:
1. **Overview** – photo, number, job, department, manager, status, contacts
2. **Personal & statutory** – ID, KRA PIN, NSSF, SHA, DOB, next of kin
3. **Employment** – type, hire date, probation, contract end, job history
4. **Salary & pay** – current salary, allowances, deductions, reliefs, salary history
5. **Payment details** – bank / M-Pesa (masked unless permitted)
6. **Payslips** – all payslips with payment status, download PDF
7. **Leave** – balances, requests, history
8. **Documents** – uploads with expiry alerts
9. **Activity** – audit log for this employee

---

## 9. Employee Self-Service

| Feature | Notes |
|---|---|
| Dashboard | Leave balances, latest payslip, pending requests |
| My payslips | View and download PDF; see paid status |
| My leave | Apply, view status, cancel pending, view balances, see team calendar |
| My profile | View details |
| Update contact details | Phone, email, address, next of kin → **change request** approved by HR |
| Update bank / M-Pesa details | Always requires HR approval (fraud risk) |
| Change password, enable 2FA | |

---

## 10. Vue Frontend Structure (Inertia)

```
resources/js/
├── Layouts/
│   ├── AppLayout.vue          # Sidebar menu built from user permissions
│   └── GuestLayout.vue
├── Components/                # DataTable, Modal, FormInput, StatusBadge, ExportMenu…
├── Pages/
│   ├── Dashboard.vue
│   ├── Employees/   Index, Create, Edit, Show (tabs)
│   ├── Payroll/     Runs/Index, Runs/Show, Variables, Payslips/Show
│   ├── Leave/       Requests/Index, Approvals, Calendar, Types
│   ├── SelfService/ Payslips, Leave, Profile, ChangeRequests
│   ├── Settings/    Company, Departments, PayComponents, StatutoryRates, TaxBands, PublicHolidays
│   ├── Users/       Index, Roles
│   └── Reports/     Index
└── composables/
    └── usePermissions.js      # can('payroll.approve')
```

Share the logged-in user's permissions with every page via Inertia's `HandleInertiaRequests` middleware so menus and buttons hide automatically. **Always enforce permissions on the server too.**

## 11. Laravel Backend Structure

```
app/
├── Models/            Employee, PayrollRun, Payslip, LeaveRequest, StatutoryRate…
├── Http/Controllers/  EmployeeController, PayrollRunController, LeaveRequestController…
├── Http/Requests/     Validation per form (StoreEmployeeRequest…)
├── Policies/          EmployeePolicy, PayrollRunPolicy, LeaveRequestPolicy
├── Services/
│   ├── Payroll/       PayrollCalculator, NssfCalculator, ShifCalculator, AhlCalculator, PayeCalculator
│   └── Leave/         LeaveDayCounter (excludes weekends & holidays), LeaveAccrualService
├── Exports/           PayrollRegisterExport, BankFileExport, P10Export, NssfExport…
├── Jobs/              GeneratePayslipPdfs, EmailPayslips
├── Console/Commands/  AccrueMonthlyLeave (scheduled monthly), YearEndLeaveCarryForward
└── Notifications/     LeaveRequested, LeaveApproved, PayslipAvailable
```

---

## 12. Security & Compliance

- **Kenya Data Protection Act 2019:** register as a data controller with the ODPC; collect only needed data; restrict access by role.
- Encrypt sensitive fields at rest (bank account, M-Pesa number, ID numbers) with Laravel `encrypted` casts.
- 2FA for Admin, HR, Payroll and Finance roles.
- Audit log every create/update/delete on employees, salaries, payroll and settings.
- Lock approved payroll runs; corrections go into the next month as arrears/adjustments.
- Rate-limit login; force HTTPS; daily database backups (tested restores).
- Mask salary and bank details for users without permission.

---

## 13. Build Milestones

| # | Milestone | Deliverables | Est. |
|---|---|---|---|
| 1 | **Setup & auth** | Laravel + Inertia + Vue + Tailwind, login, 2FA, layout, spatie permission, roles seeded, user management | 1 week |
| 2 | **Organisation & employees** | Departments, job titles, employee CRUD, documents, bank details, employee profile tabs, employee list with filters | 2 weeks |
| 3 | **Payroll setup** | Pay components, employee salaries & allowances, reliefs, statutory rates & tax band settings (seeded) | 1 week |
| 4 | **Payroll engine** | Calculator services + **unit tests** (test cases above), payroll runs, variables, calculate/approve/lock, payslips | 2 weeks |
| 5 | **Payment status & exports** | Mark paid/failed, bank & M-Pesa files, payroll register, payslip PDFs, statutory schedules (CSV/Excel/PDF) | 1.5 weeks |
| 6 | **Leave** | Leave types, public holidays, balances, accrual job, requests, approvals, calendar, unpaid leave → payroll | 1.5 weeks |
| 7 | **Self-service** | ESS dashboard, payslips, leave, profile change requests + HR approval | 1 week |
| 8 | **Audit, reports & hardening** | Activity log views, dashboards, permission review, security checks, UAT with real payroll data | 1 week |

**Total: ~11 weeks** for one full-time developer. Run at least **one parallel payroll month** (old method vs new system) before going live.

---

## 14. Later Phases (not now)

- P9 annual tax cards
- Loans & advances module with repayment schedules
- Attendance & time
- Exits & offboarding with final dues
- Direct payments via bank API or Safaricom Daraja B2C
- SMS notifications
- Mobile app

---

> **Note on rates:** statutory figures above are current as of October 2026. Confirm with KRA, NSSF and SHA before go-live and update through the Statutory Rates settings screen whenever they change.
