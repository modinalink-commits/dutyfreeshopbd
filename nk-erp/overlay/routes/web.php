<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\WholesaleController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\HeroController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

// Public API used by the nklpgbd.com website order form.
Route::post('/api/website-order', [ApiController::class, 'websiteOrder'])->middleware('throttle:8,1');
Route::get('/share/export/{export}', [ExportController::class, 'shared'])->name('exports.shared')->middleware('signed');
// Salesman mobile app.
Route::get('/app/manifest.json', [AppController::class, 'manifest'])->name('app.manifest');
Route::middleware('guest')->group(function () {
    Route::get('/app/login', [AppController::class, 'loginForm'])->name('app.login');
    Route::post('/app/login', [AppController::class, 'login'])->name('app.login.post')->middleware('throttle:6,1');
});
Route::middleware(['auth', 'pwd', 'role:salesman,driver,super_admin,admin,manager'])->prefix('app')->name('app.')->group(function () {
    Route::get('/', [AppController::class, 'home'])->name('home');
    Route::post('/logout', [AppController::class, 'logout'])->name('logout');
    Route::get('/sale', [AppController::class, 'sale'])->name('sale');
    Route::post('/sale', [AppController::class, 'storeSale'])->name('sale.store')->middleware('throttle:60,1');
    Route::get('/invoice/{sale}', [AppController::class, 'invoice'])->name('invoice');
    Route::post('/invoice/{sale}/sms', [AppController::class, 'resendSms'])->name('sms')->middleware('throttle:6,1');
    Route::get('/print/{sale}', [AppController::class, 'printPage'])->name('print');
    Route::get('/receipt/{sale}', [AppController::class, 'receipt'])->name('receipt');
    Route::post('/print/{sale}', [AppController::class, 'logPrint'])->name('print.log');
    Route::get('/sales', [AppController::class, 'sales'])->name('sales');
    Route::get('/shops', [AppController::class, 'shops'])->name('shops');
    Route::get('/empty', [AppController::class, 'empty'])->name('empty');
    Route::post('/empty', [AppController::class, 'storeEmpty'])->name('empty.store');
    Route::get('/pay', [AppController::class, 'pay'])->name('pay');
    Route::post('/pay', [AppController::class, 'storePay'])->name('pay.store');
    Route::get('/summary', [AppController::class, 'summary'])->name('summary');
    Route::post('/summary/close', [AppController::class, 'requestClose'])->name('close');
    Route::get('/duty', [AppController::class, 'duty'])->name('duty');
    Route::post('/duty', [AppController::class, 'toggleDuty'])->name('duty.toggle');
    Route::post('/gps', [AppController::class, 'gps'])->name('gps')->middleware('throttle:30,1');
    Route::get('/expense', [AppController::class, 'expense'])->name('expense');
    Route::post('/expense', [AppController::class, 'storeExpense'])->name('expense.store');
    Route::get('/notify', [AppController::class, 'notify'])->name('notify');
});
// Customer self-service portal (OTP login, separate from staff accounts).
Route::prefix('my')->name('my.')->group(function () {
    Route::get('/login', [PortalController::class, 'login'])->name('login');
    Route::post('/login', [PortalController::class, 'sendCode'])->name('send')->middleware('throttle:5,10');
    Route::post('/verify', [PortalController::class, 'verify'])->name('verify')->middleware('throttle:10,10');
    Route::post('/logout', [PortalController::class, 'logout'])->name('logout');
    Route::middleware(\App\Http\Middleware\CustomerSession::class)->group(function () {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('/purchases', [PortalController::class, 'purchases'])->name('purchases');
        Route::get('/invoice/{sale}', [PortalController::class, 'invoice'])->name('invoice');
        Route::get('/payments', [PortalController::class, 'payments'])->name('payments');
        Route::get('/empty', [PortalController::class, 'empties'])->name('empty');
        Route::get('/statement', [PortalController::class, 'statement'])->name('statement');
        Route::get('/order', [PortalController::class, 'order'])->name('order');
        Route::post('/order', [PortalController::class, 'storeOrder'])->name('order.store')->middleware('throttle:10,60');
    });
});
Route::get('/api/hero', [HeroController::class, 'api'])->middleware('throttle:120,1');
Route::get('/api/site', [WebsiteController::class, 'api'])->middleware('throttle:120,1');
Route::get('/api/ping', fn () => response()->json(['ok' => true]));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'pwd'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::post('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}', [OrderController::class, 'update'])->name('orders.update')
        ->middleware('role:super_admin,admin,manager');

    Route::get('/soon/{key}', [RoadmapController::class, 'soon'])->name('soon');
    Route::get('/build-progress', [RoadmapController::class, 'index'])->name('roadmap');

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/history', [StockController::class, 'history'])->name('stock.history');
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('/brands/report', [BrandController::class, 'report'])->name('brands.report');

    // Sales: counter staff (all roles except salesman) can sell; managers handle wholesale and voids.
    Route::middleware('role:super_admin,admin,manager,accounts')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/customer', [PosController::class, 'lookup'])->name('pos.lookup');
        Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
        Route::get('/search', \App\Http\Controllers\SearchController::class)->middleware('throttle:60,1')->name('search');
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'save'])->name('customers.save');
        Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
        Route::post('/customers/{customer}/pay', [CustomerController::class, 'pay'])->name('customers.pay');
        Route::post('/customers/{customer}/empties', [CustomerController::class, 'empties'])->name('customers.empties');
        Route::get('/wholesale', [WholesaleController::class, 'index'])->name('wholesale.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    });

    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::post('/wholesale', [WholesaleController::class, 'store'])->name('wholesale.store');
        Route::post('/wholesale/{sale}/load', [WholesaleController::class, 'load'])->name('wholesale.load');
        Route::post('/wholesale/{sale}/deliver', [WholesaleController::class, 'deliver'])->name('wholesale.deliver');
        Route::post('/sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::post('/products', [ProductController::class, 'save'])->name('products.save');
        Route::post('/products/{product}/stock', [ProductController::class, 'receive'])->name('products.stock');
        Route::post('/products/{product}/photo', [ProductController::class, 'photo'])->name('products.photo');
    });

    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::get('/stock/new', [StockController::class, 'create'])->name('stock.create');
        Route::post('/stock', [StockController::class, 'store'])->name('stock.store');
        Route::post('/exchanges', [ExchangeController::class, 'store'])->name('exchanges.store');
        Route::post('/exchanges/{exchange}', [ExchangeController::class, 'decide'])->name('exchanges.decide');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::post('/brands', [BrandController::class, 'save'])->name('brands.save');
        Route::post('/stock/{movement}/reverse', [StockController::class, 'reverse'])->name('stock.reverse');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update')->middleware('role:super_admin');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/activity', [UserController::class, 'activity'])->name('activity');
    });

    // Route sales: vehicles, trips, return, cash settlement, closing.
    Route::middleware('role:super_admin,admin,manager,accounts')->group(function () {
        Route::get('/route-sales', [FleetController::class, 'dashboard'])->name('fleet.dash');
        Route::get('/vehicles', [FleetController::class, 'vehicles'])->name('fleet.vehicles');
        Route::get('/loading', [TripController::class, 'loading'])->name('trips.loading');
        Route::get('/trips/{trip}/slip', [TripController::class, 'slip'])->name('trips.slip');
        Route::get('/trips/{trip}/sales', [TripController::class, 'sales'])->name('trips.sales');
        Route::get('/vehicle-return', [TripController::class, 'returnForm'])->name('trips.return');
        Route::get('/settlement', [TripController::class, 'settle'])->name('trips.settle');
        Route::get('/closing', [TripController::class, 'closing'])->name('trips.closing');
        Route::get('/closing/steps', [TripController::class, 'wizard'])->name('trips.wizard');
        Route::post('/trips/{trip}/settle', [TripController::class, 'storeSettle'])->name('trips.settle.store');
        Route::post('/trips/{trip}/sales', [TripController::class, 'storeSale'])->name('trips.sales.store');
        Route::post('/trips/{trip}/collect', [TripController::class, 'collect'])->name('trips.collect');
        Route::post('/trip-expense', [FleetController::class, 'expense'])->name('fleet.expense');
    });
    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::post('/vehicles', [FleetController::class, 'saveVehicle'])->name('fleet.vehicles.save');
        Route::post('/routes', [FleetController::class, 'saveRoute'])->name('fleet.routes.save');
        Route::post('/staff', [FleetController::class, 'saveStaff'])->name('fleet.staff.save');
        Route::post('/loading', [TripController::class, 'storeLoading'])->name('trips.loading.store');
        Route::post('/trips/{trip}/return', [TripController::class, 'storeReturn'])->name('trips.return.store');
        Route::post('/trips/{trip}/adjust', [TripController::class, 'adjust'])->name('trips.adjust');
        Route::post('/trips/{trip}/close', [TripController::class, 'close'])->name('trips.close');
    });
    // Expense management: anyone in the office can enter; managers approve; Super Admin sets the rules.
    Route::middleware('role:super_admin,admin,manager,accounts')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/new', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{claim}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');
        Route::post('/expenses/{claim}/pay', [ExpenseController::class, 'markPaid'])->name('expenses.pay');
        Route::get('/expenses/settings', [ExpenseController::class, 'setup'])->name('expenses.setup');
    });
    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::post('/expenses/{claim}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('/expenses/{claim}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/expenses/settings/category', [ExpenseController::class, 'saveCategory'])->name('expenses.category');
        Route::post('/expenses/settings/recurring', [ExpenseController::class, 'saveRecurring'])->name('expenses.recurring');
        Route::post('/expenses/settings/rule', [ExpenseController::class, 'saveRule'])->name('expenses.rule');
        Route::post('/expenses/settings/rule/{rule}/delete', [ExpenseController::class, 'deleteRule'])->name('expenses.rule.delete');
        Route::post('/expenses/settings/alerts', [ExpenseController::class, 'saveAlerts'])->name('expenses.alerts');
    });
    // HR & payroll: salary data is confidential.
    Route::middleware('role:super_admin,admin,accounts')->group(function () {
        Route::get('/hr', [HrController::class, 'index'])->name('hr.index');
        Route::get('/hr/employees/{employee}', [HrController::class, 'employee'])->name('hr.employee');
        Route::post('/hr/employees/{employee}', [HrController::class, 'saveEmployee'])->name('hr.employee.save');
        Route::post('/hr/employees/{employee}/entry', [HrController::class, 'entry'])->name('hr.entry');
        Route::get('/hr/payroll', [HrController::class, 'payroll'])->name('hr.payroll');
        Route::post('/hr/payroll/prepare', [HrController::class, 'prepare'])->name('hr.payroll.prepare');
        Route::post('/hr/payroll/{payroll}/lines', [HrController::class, 'saveLines'])->name('hr.payroll.lines');
        Route::post('/hr/payroll/{payroll}/check', [HrController::class, 'check'])->name('hr.payroll.check');
        Route::post('/hr/payroll/{payroll}/pay', [HrController::class, 'pay'])->name('hr.payroll.pay');
    });
    Route::post('/hr/payroll/{payroll}/approve', [HrController::class, 'approve'])->name('hr.payroll.approve')->middleware('role:super_admin');
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/sms', [SmsController::class, 'index'])->name('sms.index');
        Route::post('/sms/retry', [SmsController::class, 'retry'])->name('sms.retry');
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/sms/gateway', [SmsController::class, 'gateway'])->name('sms.gateway');
        Route::post('/sms/notice', [SmsController::class, 'notice'])->name('sms.notice');
    });
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/website', [WebsiteController::class, 'index'])->name('website.index');
        Route::post('/website', [WebsiteController::class, 'save'])->name('website.save');
        Route::get('/hero', [HeroController::class, 'index'])->name('hero.index');
        Route::post('/hero', [HeroController::class, 'save'])->name('hero.save');
        Route::post('/hero/publish', [HeroController::class, 'publish'])->name('hero.publish');
        Route::post('/hero/{slide}/move', [HeroController::class, 'move'])->name('hero.move');
        Route::post('/hero/{slide}/delete', [HeroController::class, 'delete'])->name('hero.delete');
    });
    Route::middleware('role:super_admin,admin,manager,accounts')->group(function () {
        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::post('/exports', [ExportController::class, 'generate'])->name('exports.generate');
        Route::get('/exports/print', [ExportController::class, 'printView'])->name('exports.print');
        Route::post('/exports/bulk-statements', [ExportController::class, 'bulkStatements'])->name('exports.bulk');
        Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
        Route::post('/exports/{export}/share', [ExportController::class, 'share'])->name('exports.share');
        Route::post('/exports/{export}/archive', [ExportController::class, 'archive'])->name('exports.archive');
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/import', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/import', [ImportController::class, 'upload'])->name('imports.upload');
        Route::post('/import/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
        Route::post('/import/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');
    });
    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::get('/live-tracking', [MapController::class, 'live'])->name('map.live');
        Route::get('/live-tracking/data', [MapController::class, 'liveJson'])->name('map.live.json');
        Route::get('/distribution-map', [MapController::class, 'dist'])->name('map.dist');
        Route::post('/map/routes/{zoneRoute}/zone', [MapController::class, 'saveZone'])->name('map.zone');
        Route::post('/map/customers/{customer}/place', [MapController::class, 'placeCustomer'])->name('map.place');
    });
    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::get('/customer-orders', [CustomerOrderController::class, 'index'])->name('corders.index');
        Route::post('/customer-orders/{order}/approve', [CustomerOrderController::class, 'approve'])->name('corders.approve');
        Route::post('/customer-orders/{order}/attach', [CustomerOrderController::class, 'attach'])->name('corders.attach');
        Route::post('/customer-orders/{order}/reject', [CustomerOrderController::class, 'reject'])->name('corders.reject');
        Route::post('/customers/{customer}/portal', [CustomerOrderController::class, 'togglePortal'])->name('corders.portal');
    });
    Route::middleware('role:super_admin,admin,manager,accounts')->group(function () {
        Route::get('/erp', [ErpController::class, 'overview'])->name('erp.index');
        Route::get('/erp/{m}', [ErpController::class, 'index'])->name('erp.module')->where('m', 'm(0[1-9]|1[0-5])');
        Route::post('/erp/{m}', [ErpController::class, 'store'])->name('erp.store')->where('m', 'm(0[1-9]|1[0-5])');
        Route::post('/erp/records/{rec}/step', [ErpController::class, 'step'])->name('erp.step');
        Route::get('/erp/records/{rec}/file', [ErpController::class, 'file'])->name('erp.file');
    });
    Route::get('/vehicle-profit', [FleetController::class, 'profit'])->name('fleet.profit')->middleware('role:super_admin');

    // Company money is confidential: Super Admin only.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/accounts', [AccountsController::class, 'index'])->name('accounts.index');
        Route::post('/accounts/expense', [AccountsController::class, 'expense'])->name('accounts.expense');
        Route::post('/accounts/expense/{expense}/reverse', [AccountsController::class, 'reverseExpense'])->name('accounts.expense.reverse');
        Route::post('/accounts/account', [AccountsController::class, 'account'])->name('accounts.account');
        Route::post('/accounts/transfer', [AccountsController::class, 'transfer'])->name('accounts.transfer');
        Route::post('/accounts/partner', [AccountsController::class, 'partner'])->name('accounts.partner');
        Route::get('/company', [CompanyController::class, 'index'])->name('company.index');
        Route::post('/company/deposit', [CompanyController::class, 'storeDeposit'])->name('company.deposit');
        Route::post('/company/challan', [CompanyController::class, 'storeChallan'])->name('company.challan');
        Route::post('/company/receive', [CompanyController::class, 'receive'])->name('company.receive');
        Route::post('/company/reverse', [CompanyController::class, 'reverse'])->name('company.reverse');
        Route::post('/company/import', [CompanyController::class, 'import'])->name('company.import');
        Route::get('/company/rates', [CompanyController::class, 'rates'])->name('company.rates');
        Route::post('/company/rates', [CompanyController::class, 'saveRate'])->name('company.rates.save');
        Route::post('/company/month', [CompanyController::class, 'saveMonth'])->name('company.month.save');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::post('/users/{user}/reset', [UserController::class, 'reset'])->name('users.reset');
    });
});
