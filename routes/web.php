<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashBankController;
use App\Http\Controllers\CashBankTransactionController;
use App\Http\Controllers\ClosingEntryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardTaxController;
use App\Http\Controllers\FiscalPeriodController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\ReceivableReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\StoreReceivableController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TaxReconciliationController;
use App\Http\Controllers\TaxReportController;
use App\Http\Controllers\TransactionAttachmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaController::class, 'verify'])->middleware('throttle:5,1')->name('mfa.verify');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1')->name('password.change');
    Route::get('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/mfa/enable', [MfaController::class, 'enable'])->middleware('throttle:5,1')->name('mfa.enable');
    Route::post('/mfa/disable', [MfaController::class, 'disable'])->middleware('throttle:5,1')->name('mfa.disable');

    Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('permission:dashboard.view');
    Route::get('/dashboard/tax', fn () => redirect()->route('dashboards.tax'))->middleware('permission:dashboard.view');
    Route::get('/dashboards/tax', [DashboardTaxController::class, 'index'])->name('dashboards.tax')->middleware('permission:dashboard.view');
    Route::get('/search', GlobalSearchController::class)->name('search')->middleware('permission:app.access');
    Route::resource('companies', CompanyController::class)->middleware('permission:company.manage');
    // Keep fiscal-period action routes above the resource route so lock/unlock/close are never captured by {fiscalPeriod}.
    Route::post('/fiscal-periods/{fiscalPeriod}/lock', [FiscalPeriodController::class, 'lock'])->name('fiscal-periods.lock')->middleware('permission:period.manage')->whereNumber('fiscalPeriod');
    Route::post('/fiscal-periods/{fiscalPeriod}/unlock', [FiscalPeriodController::class, 'unlock'])->name('fiscal-periods.unlock')->middleware('permission:period.manage')->whereNumber('fiscalPeriod');
    Route::post('/fiscal-periods/{fiscalPeriod}/close', [FiscalPeriodController::class, 'close'])->name('fiscal-periods.close')->middleware('permission:period.manage')->whereNumber('fiscalPeriod');
    Route::resource('fiscal-periods', FiscalPeriodController::class)->middleware('permission:period.manage')->whereNumber('fiscalPeriod');
    Route::resource('branches', BranchController::class)->middleware('permission:branch.manage');
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index')->middleware('permission:account.view');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create')->middleware('permission:account.manage');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store')->middleware('permission:account.manage');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit')->middleware('permission:account.manage')->whereNumber('account');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update')->middleware('permission:account.manage')->whereNumber('account');
    Route::patch('/accounts/{account}', [AccountController::class, 'update'])->middleware('permission:account.manage')->whereNumber('account');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy')->middleware('permission:account.manage')->whereNumber('account');
    Route::get('/accounts/export', [AccountController::class, 'export'])->name('accounts.export')->middleware('permission:account.view');
    Route::permanentRedirect('/accounts-export', '/accounts/export');
    Route::resource('cash-banks', CashBankController::class)->middleware('permission:cash_bank.view,cash_bank.manage');
    Route::get('/store-receivables/create', [StoreReceivableController::class, 'create'])->name('store-receivables.create')->middleware('permission:journal.create');
    Route::post('/store-receivables', [StoreReceivableController::class, 'store'])->name('store-receivables.store')->middleware('permission:journal.create');
    Route::get('/receivable-receipts/create', [ReceivableReceiptController::class, 'create'])->name('receivable-receipts.create')->middleware('permission:cash_transaction.create');
    Route::post('/receivable-receipts', [ReceivableReceiptController::class, 'store'])->name('receivable-receipts.store')->middleware('permission:cash_transaction.create');
    Route::get('/cash-bank-transactions', [CashBankTransactionController::class, 'index'])->name('cash-bank-transactions.index')->middleware('permission:cash_transaction.view,cash_transaction.create');
    Route::get('/cash-bank-transactions/type/{kind}', [CashBankTransactionController::class, 'index'])->whereIn('kind', ['cash', 'bank'])->name('cash-bank-transactions.mutations')->middleware('permission:cash_transaction.view,cash_transaction.create');
    Route::get('/cash-bank-transactions/mutasi/{kind}', fn (string $kind) => redirect()->route('cash-bank-transactions.mutations', $kind, 301))->whereIn('kind', ['cash', 'bank']);
    Route::post('/cash-bank-transactions/{transaction}/reverse', [CashBankTransactionController::class, 'reverse'])->name('cash-bank-transactions.reverse')->middleware('permission:cash_transaction.create')->whereNumber('transaction');
    Route::get('/cash-bank-transactions/{type}/create', [CashBankTransactionController::class, 'create'])->name('cash-bank-transactions.create')->middleware('permission:cash_transaction.create');
    Route::post('/cash-bank-transactions/{type}', [CashBankTransactionController::class, 'store'])->name('cash-bank-transactions.store')->middleware('permission:cash_transaction.create');
    Route::resource('bank-reconciliations', BankReconciliationController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:bank_reconciliation.view,bank_reconciliation.create');
    Route::get('/closing-entries/create', [ClosingEntryController::class, 'create'])->name('closing-entries.create')->middleware('permission:closing.create');
    Route::post('/closing-entries', [ClosingEntryController::class, 'store'])->name('closing-entries.store')->middleware('permission:closing.create');
    Route::post('/journals/{journal}/submit', [JournalEntryController::class, 'submit'])->name('journals.submit')->middleware('permission:journal.create')->whereNumber('journal');
    Route::post('/journals/{journal}/approve', [JournalEntryController::class, 'approve'])->name('journals.approve')->middleware('permission:journal.approve')->whereNumber('journal');
    Route::post('/journals/{journal}/reject', [JournalEntryController::class, 'reject'])->name('journals.reject')->middleware('permission:journal.approve')->whereNumber('journal');
    Route::post('/journals/{journal}/cancel', [JournalEntryController::class, 'cancel'])->name('journals.cancel')->middleware('permission:journal.cancel')->whereNumber('journal');
    Route::post('/journals/{journal}/post', [JournalEntryController::class, 'post'])->name('journals.post')->middleware('permission:journal.post')->whereNumber('journal');
    Route::get('/journals/{journal}/attachment', [TransactionAttachmentController::class, 'journal'])->name('journals.attachment')->middleware('permission:journal.view')->whereNumber('journal');
    Route::get('/journals/voucher-number', [JournalEntryController::class, 'voucherNumber'])->name('journals.voucher-number')->middleware('permission:journal.create');
    Route::get('/journals/{journal}/duplicate', [JournalEntryController::class, 'duplicate'])->name('journals.duplicate')->middleware('permission:journal.view,journal.create')->whereNumber('journal');
    Route::resource('journals', JournalEntryController::class)->middleware('permission:journal.view,journal.create')->whereNumber('journal');
    Route::get('/cash-bank-transactions/{transaction}/attachment', [TransactionAttachmentController::class, 'cashBank'])->name('cash-bank-transactions.attachment')->middleware('permission:cash_transaction.view');
    Route::resource('users', UserController::class)->middleware('permission:user.manage');
    Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index')->middleware('permission:role.manage');
    Route::get('/roles/{role}/edit', [RolePermissionController::class, 'edit'])->name('roles.edit')->middleware('permission:role.manage');
    Route::put('/roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update')->middleware('permission:role.manage');
    Route::get('/system-settings/edit', [SystemSettingController::class, 'edit'])->name('system-settings.edit')->middleware('permission:settings.manage');
    Route::put('/system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update')->middleware('permission:settings.manage');
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index')->middleware('permission:backup.manage');
    Route::post('/backups/download', [BackupController::class, 'download'])->name('backups.download')->middleware('permission:backup.manage');
    Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore')->middleware('permission:backup.manage');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/ledger', [ReportController::class, 'ledger'])->name('ledger')->middleware('permission:report.view');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance')->middleware('permission:report.view');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss')->middleware('permission:report.view');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet')->middleware('permission:report.view');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow')->middleware('permission:report.view');
        Route::get('/cash-flow-indirect', [ReportController::class, 'cashFlowIndirect'])->name('cash-flow-indirect')->middleware('permission:report.view');
        Route::get('/audit-trail', [ReportController::class, 'auditTrail'])->name('audit-trail')->middleware('permission:audit_trail.view');
    });

    Route::prefix('tax-reports')->name('tax-reports.')->group(function () {
        Route::get('/summary', [TaxReportController::class, 'index'])->name('summary')->middleware('permission:tax_report.view');
        Route::get('/reconciliation', [TaxReconciliationController::class, 'index'])->name('reconciliation')->middleware('permission:tax_report.view');
    });
    Route::permanentRedirect('/reports/tax/reconciliation', '/tax-reports/reconciliation');
});
