<?php

namespace App\Support;

/**
 * Every screen of the approved design canvas, so nothing is left out.
 * status: live = working now, next = being built, planned = still to build.
 */
class Roadmap
{
    public const MENU = [
        'OVERVIEW' => [
            ['dashboard', 'Dashboard', 'dashboard', 'Main'],
            ['map', 'Distribution map', null, 'DistMap'],
        ],
        'SALES' => [
            ['pos', 'Retail sale (POS)', 'pos.index', 'POS · MobilePrint (58/80 mm) · CompanyDoc (A4)'],
            ['wholesale', 'Wholesale orders', 'wholesale.index', 'Wholesale'],
            ['customers', 'Customers & dues', 'customers.index', 'Customers'],
            ['sales', 'Sales list & invoices', 'sales.index', 'POS · CompanyDoc'],
            ['corders', 'Website orders', 'orders.index', 'CustomerOrders'],
        ],
        'ROUTE SALES' => [
            ['rdash', 'Route dashboard', null, 'RouteDash'],
            ['live', 'Live tracking', null, 'LiveTracking'],
            ['vehicles', 'Vehicles & routes', null, 'Vehicles'],
            ['loading', 'Morning loading', null, 'Loading'],
            ['return', 'Vehicle return', null, 'Return'],
            ['settle', 'Cash settlement', null, 'Settlement'],
            ['closing', 'Daily closing', null, 'Closing · ClosingWizard'],
            ['vpl', 'Vehicle profit', null, 'VehiclePL'],
        ],
        'STOCK' => [
            ['stock', 'Stock & cylinders', 'stock.index', 'Stock'],
            ['brands', 'Cylinder brands', 'brands.index', 'BrandMaster'],
            ['products', 'Products & prices', 'products.index', 'POS · Wholesale price lists'],
        ],
        'COMPANY' => [
            ['supplier', 'Company ledger', null, 'Supplier · STM financial'],
        ],
        'MONEY' => [
            ['accounts', 'Accounts & profit', null, 'Accounts'],
            ['exp', 'Expense management', null, 'ExpenseDash · ExpenseEntry · ExpenseSetup'],
        ],
        'WEBSITE' => [
            ['website', 'Public website', null, 'Website (live at nklpgbd.com; editing from here planned)'],
            ['hero', 'Hero slider', null, 'Hero · HeroMobile · HeroAdmin'],
        ],
        'ERP MODULES' => [
            ['erp', 'All 15 modules', null, 'Module · Mod01–Mod15'],
            ['hr', 'HR & payroll', null, 'HRDash · EmployeeLedger · PayrollRun'],
        ],
        'DOCUMENTS' => [
            ['export', 'Export center', null, 'ExportCenter · ExportMenu'],
            ['import', 'Import data', null, 'ImportCenter'],
        ],
        'SETTINGS' => [
            ['company', 'Company settings', 'settings.edit', 'CompanySettings'],
            ['users', 'Users & roles', 'users.index', 'Roles (all specs)'],
            ['activity', 'Activity log', 'activity', 'Audit trail (all specs)'],
            ['sms', 'Invoices & SMS', null, 'SmsCenter · CompanyDoc'],
        ],
    ];

    /** Screens outside the back-office menu (apps and portals). */
    public const APPS = [
        ['Salesman mobile app (Android, iOS, PWA)', 'MobileLogin · MobileTrip · MobileSale · MobileInvoice · MobilePrint · MobileEmpty · MobilePay · MobileSales · MobileNotify · MobileSummary · MobileDuty · MobileExpense · MobileNav'],
        ['Customer self-service portal', 'CustLogin · CustHome · CustPurchases · CustInvoice · CustPayments · CustEmpty · CustStatement · CustOrder · CustNav'],
        ['Easy-use patterns (applied everywhere)', 'UXPatterns'],
    ];

    public static function find(string $key): ?array
    {
        foreach (self::MENU as $group => $items) {
            foreach ($items as $it) {
                if ($it[0] === $key) {
                    return ['group' => $group, 'key' => $it[0], 'label' => $it[1], 'route' => $it[2], 'design' => $it[3]];
                }
            }
        }
        return null;
    }
}
