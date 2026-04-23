<?php

use App\Http\Controllers\DigitalWalletController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountReceivablePayableController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ModulesController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\MovementTypeController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ParameterCategoriesController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\MenuOptionController;
use App\Http\Controllers\PaymentConceptController;
use App\Http\Controllers\PaymentGatewaysController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductBranchController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ViewsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\CashShiftRelationController;
use App\Http\Controllers\SystemConfigController;
use App\Http\Controllers\RecipeBookController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseMovementController;
use App\Http\Controllers\WorkshopAppointmentController;
use App\Http\Controllers\WorkshopAssemblyController;
use App\Http\Controllers\WorkshopAssemblyLocationController;
use App\Http\Controllers\WorkshopClientController;
use App\Http\Controllers\WorkshopMaintenanceBoardController;
use App\Http\Controllers\WorkshopPurchaseController;
use App\Http\Controllers\WorkshopSalesRegisterController;
use App\Http\Controllers\WorkshopOrderController;
use App\Http\Controllers\WorkshopExportController;
use App\Http\Controllers\WorkshopReportController;
use App\Http\Controllers\WorkshopServiceCatalogController;
use App\Http\Controllers\WorkshopVehicleTypeController;
use App\Http\Controllers\WorkshopVehicleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WorkshopQuotationController;

Route::prefix('restaurante')->name('restaurant.')->group(function () {
    Route::view('/', 'restaurant.home', ['title' => 'Xinergia Restaurante'])->name('home');
    Route::view('/menu', 'restaurant.menu', ['title' => 'Menu'])->name('menu');
    Route::view('/reservas', 'restaurant.reservations', ['title' => 'Reservas'])->name('reservations');
    Route::view('/historia', 'restaurant.about', ['title' => 'Historia'])->name('about');
    Route::view('/eventos', 'restaurant.events', ['title' => 'Eventos'])->name('events');
    Route::view('/galeria', 'restaurant.gallery', ['title' => 'Galeria'])->name('gallery');
    Route::view('/contacto', 'restaurant.contact', ['title' => 'Contacto'])->name('contact');
    Route::view('/sucursales', 'restaurant.locations', ['title' => 'Sucursales'])->name('locations');
});


Route::get('/signin', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/signin', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::view('/signup', 'pages.auth.signup', ['title' => 'Sign Up'])
    ->middleware('guest')
    ->name('signup');

Route::middleware('auth')->group(function () {
    Route::get('api/reniec', [PersonController::class, 'apiReniec'])->name('api.reniec');
    Route::get('api/ruc', [PersonController::class, 'apiRuc'])->name('api.ruc');

    Route::resource('/admin/herramientas/empresas', CompanyController::class)
        ->names('admin.companies')
        ->parameters(['empresas' => 'company']);
    Route::resource('/admin/herramientas/empresas.sucursales', BranchController::class)
        ->names('admin.companies.branches')
        ->parameters(['empresas' => 'company', 'sucursales' => 'branch']);
    Route::resource('/admin/herramientas/empresas.sucursales.personal', PersonController::class)
        ->names('admin.companies.branches.people')
        ->parameters(['empresas' => 'company', 'sucursales' => 'branch', 'personal' => 'person'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/personal/{person}/usuario/password', [PersonController::class, 'updatePassword'])
        ->name('admin.companies.branches.people.user.password');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/personal/menu-opciones-perfil', [PersonController::class, 'profileMenuOptions'])
        ->name('admin.companies.branches.people.profile-menu-options');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles', [BranchController::class, 'profiles'])
        ->name('admin.companies.branches.profiles.index');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/operaciones', [BranchController::class, 'profileOperationsIndex'])
        ->name('admin.companies.branches.profiles.operations.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/operaciones/asignar', [BranchController::class, 'assignProfileOperations'])
        ->name('admin.companies.branches.profiles.operations.assign');
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/operaciones/{operation}/toggle', [BranchController::class, 'toggleProfileOperation'])
        ->name('admin.companies.branches.profiles.operations.toggle');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas', [BranchController::class, 'viewsIndex'])
        ->name('admin.companies.branches.views.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas', [BranchController::class, 'updateViews'])
        ->name('admin.companies.branches.views.update');
    Route::delete('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}', [BranchController::class, 'removeViewAssignment'])
        ->name('admin.companies.branches.views.destroy');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}/operaciones', [BranchController::class, 'viewOperationsIndex'])
        ->name('admin.companies.branches.views.operations.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}/operaciones/asignar', [BranchController::class, 'assignViewOperations'])
        ->name('admin.companies.branches.views.operations.assign');
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}/operaciones/{branchOperation}/toggle', [BranchController::class, 'toggleViewOperation'])
        ->name('admin.companies.branches.views.operations.toggle');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/permisos', [BranchController::class, 'profilePermissions'])
        ->name('admin.companies.branches.profiles.permissions.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/permisos/asignar', [BranchController::class, 'assignProfilePermissions'])
        ->name('admin.companies.branches.profiles.permissions.assign');
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/permisos/{permission}', [BranchController::class, 'toggleProfilePermission'])
        ->name('admin.companies.branches.profiles.permissions.toggle');
    Route::resource('/admin/herramientas/perfiles', ProfileController::class)
        ->names('admin.profiles')
        ->parameters(['perfiles' => 'profile'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/admin/herramientas/roles', RoleController::class)
        ->names('admin.roles')
        ->parameters(['roles' => 'role'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/admin/ventas', SalesController::class)
        ->names('admin.sales')
        ->parameters(['ventas' => 'sale'])
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::post('/admin/ventas/clientes', [SalesController::class, 'storeClientQuick'])
        ->name('admin.sales.clients.store');
    Route::post('/admin/ventas/{sale}/facturar', [SalesController::class, 'invoice'])
        ->name('admin.sales.invoice');
    Route::resource('/admin/compras', PurchaseController::class)
        ->names('admin.purchases')
        ->parameters(['compras' => 'purchase'])
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::post('/admin/compras/proveedores', [PurchaseController::class, 'storeProviderQuick'])
        ->name('admin.purchases.providers.store');
    Route::get('/admin/ventas/{sale}/imprimir/pdf', [SalesController::class, 'printPdf'])->name('admin.sales.print.pdf');
    Route::get('/admin/ventas/{sale}/imprimir/ticket', [SalesController::class, 'printTicket'])->name('admin.sales.print.ticket');

    // Gestión de Cotizaciones (Taller)
    Route::get('/admin/ventas/cotizaciones', [WorkshopQuotationController::class, 'index'])->name('admin.sales.quotations.index');
    Route::get('/admin/ventas/cotizaciones/crear-externa', [WorkshopQuotationController::class, 'createExternal'])->name('admin.sales.quotations.create-external');
    Route::get('/admin/ventas/cotizaciones/{quotation}/editar-externa', [WorkshopQuotationController::class, 'editExternal'])->name('admin.sales.quotations.edit-external');
    Route::get('/admin/ventas/cotizaciones/vehiculos-por-cliente', [WorkshopQuotationController::class, 'vehiclesForClient'])->name('admin.sales.quotations.vehicles-for-client');
    Route::post('/admin/ventas/cotizaciones/externa', [WorkshopQuotationController::class, 'storeExternal'])->name('admin.sales.quotations.store-external');
    Route::put('/admin/ventas/cotizaciones/{quotation}/externa', [WorkshopQuotationController::class, 'updateExternal'])->name('admin.sales.quotations.update-external');
    Route::delete('/admin/ventas/cotizaciones/{quotation}/externa', [WorkshopQuotationController::class, 'destroyExternal'])->name('admin.sales.quotations.destroy-external');
    Route::get('/admin/ventas/cotizaciones/{quotation}/pdf', [WorkshopQuotationController::class, 'pdf'])->name('admin.sales.quotations.pdf');
    Route::get('/admin/ventas/cotizaciones/{quotation}/excel', [WorkshopQuotationController::class, 'excel'])->name('admin.sales.quotations.excel');
    Route::post('/admin/ventas/cotizaciones/{quotation}/enviar', [WorkshopQuotationController::class, 'send'])->name('admin.sales.quotations.send');
    Route::patch('/admin/ventas/cotizaciones/{quotation}/resultado', [WorkshopQuotationController::class, 'updateResult'])->name('admin.sales.quotations.update-result');
    Route::post('/admin/ventas/cotizaciones/{quotation}/generar-os', [WorkshopQuotationController::class, 'generateOrder'])->name('admin.sales.quotations.generate-order');
    Route::post('/admin/ventas/cotizaciones/{quotation}/venta-repuestos', [WorkshopQuotationController::class, 'generatePartsSale'])->name('admin.sales.quotations.generate-parts-sale');
    Route::post('/admin/ventas/cotizaciones/{quotation}/compra-repuestos-confirmar', [WorkshopQuotationController::class, 'confirmPartsPurchase'])->name('admin.sales.quotations.confirm-parts-purchase');

    // POS: Pedidos
    Route::group(['prefix' => 'admin/pedidos', 'as' => 'admin.orders.'], function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/listado', [OrderController::class, 'list'])->name('list');
        Route::get('/crear', [OrderController::class, 'create'])->name('create');
        Route::post('/procesar', [OrderController::class, 'processOrder'])->name('process');
        Route::post('/procesar-pago', [OrderController::class, 'processOrderPayment'])->name('processOrderPayment');
        Route::get('/cobrar', [OrderController::class, 'charge'])->name('charge');
        Route::post('/cancelar', [OrderController::class, 'cancelOrder'])->name('cancelOrder');
    });

    // POS: vista de cobro (antes era modal)
    Route::get('/admin/ventas/cobrar', [SalesController::class, 'charge'])
        ->name('admin.sales.charge');

    // POS: vista previa serie/correlativo (create.blade.php)
    Route::get('/admin/ventas/vista-previa-cabecera', [SalesController::class, 'previewSalePosHeader'])
        ->name('admin.sales.preview.header');

    // POS: procesar venta (usado por resources/views/sales/create.blade.php)
    Route::post('/admin/ventas/procesar', [SalesController::class, 'processSale'])
        ->name('admin.sales.process');

    // POS: guardar venta como borrador/pendiente
    Route::post('/admin/ventas/borrador', [SalesController::class, 'saveDraft'])
        ->name('admin.sales.draft');

    Route::get('/admin/ventas/reporte', [SalesController::class, 'reportSales'])
        ->name('sales.report');

    // Pedidos / Restaurante
    Route::get('/admin/pedidos', [OrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/admin/pedidos/lista', [OrderController::class, 'list'])->name('admin.orders.list');
    Route::get('/admin/pedidos/crear', [OrderController::class, 'create'])->name('admin.orders.create');
    Route::post('/admin/pedidos/procesar', [OrderController::class, 'processOrder'])->name('admin.orders.process');
    Route::get('/admin/pedidos/cobrar', [OrderController::class, 'charge'])->name('admin.orders.charge');
    Route::post('/admin/pedidos/pagar', [OrderController::class, 'processOrderPayment'])->name('admin.orders.pay');
    Route::post('/admin/pedidos/cancelar', [OrderController::class, 'cancelOrder'])->name('admin.orders.cancel');

    Route::resource('/admin/herramientas/tipos-movimiento', MovementTypeController::class)
        ->names('admin.movement-types')
        ->parameters(['tipos-movimiento' => 'movementType'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/admin/herramientas/tipos-documento', DocumentTypeController::class)
        ->names('admin.document-types')
        ->parameters(['tipos-documento' => 'documentType'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/admin/herramientas/categorias', CategoryController::class)
        ->names('admin.categories')
        ->parameters(['categorias' => 'category'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::post('/admin/herramientas/productos/import-excel', [ProductController::class, 'importExcel'])
        ->name('admin.products.import-excel');
    Route::get('/admin/herramientas/productos/plantilla-importacion', [ProductController::class, 'downloadImportTemplate'])
        ->name('admin.products.import-template');
    Route::resource('/admin/herramientas/productos', ProductController::class)
        ->names('admin.products')
        ->parameters(['productos' => 'product'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/herramientas/tipos-producto', ProductTypeController::class)
        ->names('product-types')
        ->parameters(['tipos-producto' => 'productType'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);

    Route::get('/admin/herramientas/productos/{product}/product-branches/create', [ProductBranchController::class, 'create'])
        ->name('admin.products.product_branches.create');
    Route::post('/admin/herramientas/productos/{product}/product-branches', [ProductBranchController::class, 'store'])
        ->name('admin.products.product_branches.store');
    Route::post('/admin/herramientas/product-branches/store', [ProductBranchController::class, 'storeGeneric'])
        ->name('admin.product_branches.store_generic');
    Route::put('/admin/herramientas/product-branches/{productBranch}', [ProductBranchController::class, 'update'])
        ->name('admin.product_branches.update');

    //Kardex
    Route::get('/herramientas/kardex', [KardexController::class, 'index'])
        ->name('kardex.index');
    Route::get('/herramientas/kardex/pdf', [KardexController::class, 'pdf'])
        ->name('kardex.pdf');
    // dashboard pages
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tech-detail/{technicianId}', [\App\Http\Controllers\DashboardController::class, 'techDetail'])->name('dashboard.tech-detail');

    // calender pages
    Route::get('/calendar', function () {
        return view('pages.calender', ['title' => 'Calendar']);
    })->name('calendar');

    // profile pages
    Route::get('/profile', function () {
        return view('pages.profile', ['title' => 'Profile']);
    })->name('profile');

    // form pages
    Route::get('/form-elements', function () {
        return view('pages.form.form-elements', ['title' => 'Form Elements']);
    })->name('form-elements');

    // tables pages
    Route::get('/basic-tables', function () {
        return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
    })->name('basic-tables');

    // pages
    Route::get('/blank', function () {
        return view('pages.blank', ['title' => 'Blank']);
    })->name('blank');

    // error pages
    Route::get('/error-404', function () {
        return view('pages.errors.error-404', ['title' => 'Error 404']);
    })->name('error-404');

    // chart pages
    Route::get('/line-chart', function () {
        return view('pages.chart.line-chart', ['title' => 'Line Chart']);
    })->name('line-chart');

    Route::get('/bar-chart', function () {
        return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
    })->name('bar-chart');

    // ui elements pages
    Route::get('/alerts', function () {
        return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
    })->name('alerts');

    Route::get('/avatars', function () {
        return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
    })->name('avatars');

    Route::get('/badge', function () {
        return view('pages.ui-elements.badges', ['title' => 'Badges']);
    })->name('badges');

    Route::get('/buttons', function () {
        return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
    })->name('buttons');

    Route::get('/image', function () {
        return view('pages.ui-elements.images', ['title' => 'Images']);
    })->name('images');

    Route::get('/videos', function () {
        return view('pages.ui-elements.videos', ['title' => 'Videos']);
    })->name('videos');

    // Modulos administrativos
    Route::view('/admin/herramientas/usuarios', 'pages.blank', ['title' => 'Usuarios']);
    Route::view('/admin/herramientas/sucursales', 'pages.blank', ['title' => 'Sucursales']);

    // Modulos
    Route::resource('admin/herramientas/modulos', ModulesController::class)
        ->names('admin.modules')
        ->parameters(['modulos' => 'module']);
    Route::delete('/admin/herramientas/modulos/{module}', [ModulesController::class, 'destroy'])->name('admin.modules.destroy');
    Route::resource('/admin/herramientas/modulos.menu', MenuOptionController::class)
        ->names('admin.modules.menu_options')
        ->parameters(['modulos' => 'module', 'menu' => 'menuOption']);

    // Vistas
    Route::resource('admin/herramientas/vistas', ViewsController::class)
        ->names('admin.views')
        ->parameters(['vistas' => 'view']);
    Route::delete('/admin/herramientas/vistas/{view}', action: [ViewsController::class, 'destroy'])->name('admin.views.destroy');

    //Bancos
    Route::resource('/admin/herramientas/bancos', BankController::class)
        ->names('admin.banks')
        ->parameters(['bancos' => 'bank']);
    //Operaciones de vistas
    Route::resource('admin/herramientas/vistas.operations', OperationsController::class)
        ->names('admin.views.operations')
        ->parameters(['vistas' => 'view', 'operations' => 'operation']);

    //Conceptos de pago
    Route::resource('admin/herramientas/conceptos-pago', PaymentConceptController::class)
        ->names('admin.payment_concepts')
        ->parameters(['conceptos-pago' => 'paymentConcept']);

    //Categorias de parametros
    Route::get('/admin/herramientas/parametros/categorias', [ParameterCategoriesController::class, 'index'])->name('admin.parameters.categories.index');
    Route::post('/admin/herramientas/parametros/categorias', [ParameterCategoriesController::class, 'store'])->name('admin.parameters.categories.store');
    Route::delete('/admin/herramientas/parametros/categorias/{parameterCategory}', [ParameterCategoriesController::class, 'destroy'])->name('admin.parameters.categories.destroy');
    Route::put('/admin/herramientas/parametros/categorias/{parameterCategory}', [ParameterCategoriesController::class, 'update'])->name('admin.parameters.categories.update');

    //Parametros
    Route::get('/admin/herramientas/parametros', [ParameterController::class, 'index'])->name('admin.parameters.index');
    Route::post('/admin/herramientas/parametros', [ParameterController::class, 'store'])->name('admin.parameters.store');
    Route::put('/admin/herramientas/parametros/{parameter}', [ParameterController::class, 'update'])->name('admin.parameters.update');
    Route::delete('/admin/herramientas/parametros/{parameter}', [ParameterController::class, 'destroy'])->name('admin.parameters.destroy');

    // Operaciones
    Route::get('/admin/herramientas/operaciones', [OperationsController::class, 'index'])->name('admin.operations.index');
    Route::post('/admin/herramientas/operaciones', [OperationsController::class, 'store'])->name('admin.operations.store');

    //Unidades
    Route::resource('/admin/herramientas/unidades', UnitController::class)
        ->names('admin.units')
        ->parameters(['unidades' => 'unit']);
    //Tarjetas
    Route::resource('/admin/herramientas/tarjetas', CardController::class)
        ->names('admin.cards')
        ->parameters(['tarjetas' => 'card']);

    //Billeteras digitales
    Route::resource('/admin/herramientas/billeteras-digitales', DigitalWalletController::class)
        ->names('admin.digital_wallets')
        ->parameters(['billeteras-digitales' => 'digitalWallet']);

    //Pasarelas de pago
    Route::resource('/admin/herramientas/pasarela-pagos', PaymentGatewaysController::class)
        ->names('admin.payment_gateways')
        ->parameters(['pasarela-pagos' => 'paymentGateway']);

    //Métodos de pago
    Route::resource('/admin/herramientas/metodos-pago', PaymentMethodController::class)
        ->names('admin.payment_methods')
        ->parameters(['metodos-pago' => 'paymentMethod']);

    //Turnos
    Route::resource('/configuracion/turnos', ShiftController::class)
        ->names('shifts')
        ->parameters(['turnos' => 'shifts']);

    // Configuracion de sistema (parametros por sucursal)
    Route::get('/configuracion/sistema', [SystemConfigController::class, 'index'])
        ->name('admin.system-config.index');
    Route::post('/configuracion/sistema', [SystemConfigController::class, 'update'])
        ->name('admin.system-config.update');

    //Caja chica
    Route::get('/caja/caja-chica', [PettyCashController::class, 'redirectBase'])
        ->name('admin.petty-cash.base');
    Route::group(['prefix' => 'caja/caja-chica/{cash_register_id}', 'as' => 'admin.petty-cash.'], function () {
        Route::get('/', [PettyCashController::class, 'index'])->name('index');
        Route::get('/cerrar', [PettyCashController::class, 'closePage'])->name('close');
        Route::post('/cerrar', [PettyCashController::class, 'closeStore'])->name('close.store');
        Route::post('/', [PettyCashController::class, 'store'])->name('store');
        Route::get('/{movement}/edit', [PettyCashController::class, 'edit'])->name('edit');
        Route::put('/{movement}', [PettyCashController::class, 'update'])->name('update');
        Route::delete('/{movement}', [PettyCashController::class, 'destroy'])->name('destroy');
    });
    Route::get('/caja/caja-chica/{cash_register_id}/{movement}', [PettyCashController::class, 'show'])
        ->whereNumber('movement')
        ->name('admin.petty-cash.show');

    //Cajas
    Route::resource('/caja/cajas', BoxController::class)
        ->names('boxes')
        ->parameters(['cajas' => 'box']);

    //Relación caja-turno
    Route::get('/caja/relacion-turnos-caja', [CashShiftRelationController::class, 'index'])
        ->name('admin.cash-shift-relations.index');
    Route::get('/admin/caja/cuentas-por-cobrar', [AccountReceivablePayableController::class, 'receivables'])
        ->name('admin.cash-accounts.receivables');
    Route::get('/admin/caja/cuentas-por-pagar', [AccountReceivablePayableController::class, 'payables'])
        ->name('admin.cash-accounts.payables');
    Route::post('/admin/caja/cuentas/{account}/liquidar', [AccountReceivablePayableController::class, 'settle'])
        ->name('admin.cash-accounts.settle');

    //tasa de impuesto
    Route::resource('/admin/herramientas/tasas-impuesto', TaxRateController::class)
        ->names('admin.tax_rates')
        ->parameters(['tasas-impuesto' => 'taxRate']);

    //Movimientos de almacen
    Route::resource('/admin/herramientas/movimientos_almacen', WarehouseMovementController::class)
        ->names('warehouse_movements')
        ->parameters(['movimientos_almacen' => 'warehouseMovement'])
        ->only(['index', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::get('/admin/herramientas/movimientos-almacen/entrada', [WarehouseMovementController::class, 'input'])
        ->name('warehouse_movements.input');

    Route::get('/admin/herramientas/movimientos-almacen/salida', [WarehouseMovementController::class, 'output'])
        ->name('warehouse_movements.output');
    Route::post('/admin/herramientas/movimientos-almacen/salida', [WarehouseMovementController::class, 'outputStore'])
        ->name('warehouse_movements.output.store');
    Route::post('/admin/herramientas/movimientos-almacen/transferencia', [WarehouseMovementController::class, 'transferStore'])
        ->name('warehouse_movements.transfer.store');
    Route::get('/admin/herramientas/movimientos-almacen/{warehouseMovement}/show', [WarehouseMovementController::class, 'show'])
        ->name('warehouse_movements.show');
    Route::get('/admin/herramientas/movimientos-almacen/{warehouseMovement}/edit', [WarehouseMovementController::class, 'edit'])
        ->name('warehouse_movements.edit');
    Route::put('/admin/herramientas/movimientos-almacen/{warehouseMovement}', [WarehouseMovementController::class, 'update'])
        ->name('warehouse_movements.update');
    Route::put('/admin/herramientas/movimientos-almacen/{warehouseMovement}/entry-update', [WarehouseMovementController::class, 'updateEntry'])
        ->name('warehouse_movements.entry.update');
    Route::put('/admin/herramientas/movimientos-almacen/{warehouseMovement}/output-update', [WarehouseMovementController::class, 'updateOutput'])
        ->name('warehouse_movements.output.update');

    //Recetario
    Route::resource('/cocina/recetario', RecipeBookController::class)
        ->names('recipe-book'); 

    Route::prefix('/admin/taller')->name('workshop.')->group(function () {
        Route::get('/tablero-mantenimiento', [WorkshopMaintenanceBoardController::class, 'index'])->name('maintenance-board.index');
        Route::get('/tablero-mantenimiento/nuevo', [WorkshopMaintenanceBoardController::class, 'create'])->name('maintenance-board.create');
        Route::post('/tablero-mantenimiento', [WorkshopMaintenanceBoardController::class, 'store'])->name('maintenance-board.store');
        Route::get('/tablero-mantenimiento/{order}/editar', [WorkshopMaintenanceBoardController::class, 'edit'])->name('maintenance-board.edit');
        Route::put('/tablero-mantenimiento/{order}', [WorkshopMaintenanceBoardController::class, 'update'])->name('maintenance-board.update');
        Route::post('/tablero-mantenimiento/vehiculos', [WorkshopMaintenanceBoardController::class, 'storeVehicleQuick'])->name('maintenance-board.vehicles.store');
        Route::get('/tablero-mantenimiento/vehiculos/consulta-placa', [WorkshopMaintenanceBoardController::class, 'lookupVehicleByPlate'])->name('maintenance-board.vehicles.lookup-plate');
        Route::post('/tablero-mantenimiento/clientes', [WorkshopMaintenanceBoardController::class, 'storeClientQuick'])->name('maintenance-board.clients.store');
        Route::post('/tablero-mantenimiento/{order}/iniciar', [WorkshopMaintenanceBoardController::class, 'start'])->name('maintenance-board.start');
        Route::post('/tablero-mantenimiento/{order}/pausar', [WorkshopMaintenanceBoardController::class, 'pause'])->name('maintenance-board.pause');
        Route::post('/tablero-mantenimiento/{order}/reanudar', [WorkshopMaintenanceBoardController::class, 'resume'])->name('maintenance-board.resume');
        Route::post('/tablero-mantenimiento/{order}/finalizar', [WorkshopMaintenanceBoardController::class, 'finish'])->name('maintenance-board.finish');
        Route::post('/tablero-mantenimiento/{order}/cotizacion', [WorkshopMaintenanceBoardController::class, 'quotation'])->name('maintenance-board.quotation');
        Route::get('/tablero-mantenimiento/{order}/venta-cobro', [WorkshopMaintenanceBoardController::class, 'checkoutPage'])->name('maintenance-board.checkout.page');
        Route::post('/tablero-mantenimiento/{order}/venta-cobro', [WorkshopMaintenanceBoardController::class, 'checkout'])->name('maintenance-board.checkout');
        Route::get('/clientes', [WorkshopClientController::class, 'index'])->name('clients.index');
        Route::post('/clientes', [WorkshopClientController::class, 'store'])->name('clients.store');
        Route::put('/clientes/{person}', [WorkshopClientController::class, 'update'])->name('clients.update');
        Route::delete('/clientes/{person}', [WorkshopClientController::class, 'destroy'])->name('clients.destroy');
        Route::get('/agenda/events', [WorkshopAppointmentController::class, 'events'])->name('appointments.events');
        Route::get('/agenda', [WorkshopAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/agenda', [WorkshopAppointmentController::class, 'store'])->name('appointments.store');
        Route::put('/agenda/{appointment}', [WorkshopAppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/agenda/{appointment}', [WorkshopAppointmentController::class, 'destroy'])->name('appointments.destroy');
        Route::post('/agenda/{appointment}/convertir-os', [WorkshopAppointmentController::class, 'convertToOrder'])->name('appointments.convert');

    Route::get('/vehiculos', [WorkshopVehicleController::class, 'index'])->name('vehicles.index');
    Route::post('/vehiculos', [WorkshopVehicleController::class, 'store'])->name('vehicles.store');
    Route::put('/vehiculos/{vehicle}', [WorkshopVehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('/vehiculos/{vehicle}', [WorkshopVehicleController::class, 'destroy'])->name('vehicles.destroy');
    Route::get('/tipos-vehiculo', [WorkshopVehicleTypeController::class, 'index'])->name('vehicle-types.index');
    Route::post('/tipos-vehiculo', [WorkshopVehicleTypeController::class, 'store'])->name('vehicle-types.store');
    Route::put('/tipos-vehiculo/{vehicleType}', [WorkshopVehicleTypeController::class, 'update'])->name('vehicle-types.update');
    Route::delete('/tipos-vehiculo/{vehicleType}', [WorkshopVehicleTypeController::class, 'destroy'])->name('vehicle-types.destroy');
    Route::get('/tipos-vehiculo/{vehicleType}/inventario', [WorkshopVehicleTypeController::class, 'inventoryEdit'])->name('vehicle-types.inventory.edit');
    Route::post('/tipos-vehiculo/{vehicleType}/inventario', [WorkshopVehicleTypeController::class, 'inventoryUpdate'])->name('vehicle-types.inventory.update');
        Route::get('/clientes/{person}/historial', [WorkshopClientController::class, 'show'])->name('clients.history');
        Route::get('/vehiculos/{vehicle}/historial', [WorkshopVehicleController::class, 'show'])->name('vehicles.history');
        Route::get('/armados/ubicaciones', [WorkshopAssemblyLocationController::class, 'index'])->name('assembly-locations.index');
        Route::post('/armados/ubicaciones', [WorkshopAssemblyLocationController::class, 'store'])->name('assembly-locations.store');
        Route::put('/armados/ubicaciones/{assemblyLocation}', [WorkshopAssemblyLocationController::class, 'update'])->name('assembly-locations.update');
        Route::delete('/armados/ubicaciones/{assemblyLocation}', [WorkshopAssemblyLocationController::class, 'destroy'])->name('assembly-locations.destroy');
        Route::get('/compras', [WorkshopPurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/ventas', [WorkshopSalesRegisterController::class, 'index'])->name('sales-register.index');

        Route::get('/servicios', [WorkshopServiceCatalogController::class, 'index'])->name('services.index');
        Route::post('/servicios/importar', [WorkshopServiceCatalogController::class, 'importFromSpreadsheet'])->name('services.import');
        Route::post('/servicios', [WorkshopServiceCatalogController::class, 'store'])->name('services.store');
        Route::put('/servicios/{service}', [WorkshopServiceCatalogController::class, 'update'])->name('services.update');
        Route::delete('/servicios/{service}', [WorkshopServiceCatalogController::class, 'destroy'])->name('services.destroy');

    Route::get('/servicios/{service}/frecuencia', [WorkshopServiceCatalogController::class, 'frequencyEdit'])->name('services.frequencies.edit');
    Route::post('/servicios/{service}/frecuencia', [WorkshopServiceCatalogController::class, 'frequencyUpdate'])->name('services.frequencies.update');
    Route::get('/servicios/{service}/detalle', [WorkshopServiceCatalogController::class, 'detailsEdit'])->name('services.details.edit');
    Route::post('/servicios/{service}/detalle', [WorkshopServiceCatalogController::class, 'detailsUpdate'])->name('services.details.update');

        Route::get('/armados', [WorkshopAssemblyController::class, 'index'])->name('assemblies.index');
        Route::get('/armados/{assembly}/editar', [WorkshopAssemblyController::class, 'edit'])->name('assemblies.edit');
        Route::post('/armados', [WorkshopAssemblyController::class, 'store'])->name('assemblies.store');
        Route::put('/armados/{assembly}', [WorkshopAssemblyController::class, 'update'])->name('assemblies.update');
        Route::delete('/armados/{assembly}', [WorkshopAssemblyController::class, 'destroy'])->name('assemblies.destroy');
        Route::get('/armados/exportar', [WorkshopAssemblyController::class, 'exportMonthlyCsv'])->name('assemblies.export');
        Route::post('/armados/costos', [WorkshopAssemblyController::class, 'storeCost'])->name('assemblies.costs.store');
        Route::put('/armados/costos/{cost}', [WorkshopAssemblyController::class, 'updateCost'])->name('assemblies.costs.update');
        Route::delete('/armados/costos/{cost}', [WorkshopAssemblyController::class, 'destroyCost'])->name('assemblies.costs.destroy');
        Route::post('armados/{assembly}/start', [WorkshopAssemblyController::class, 'startAssembly'])->name('armados.start');
        Route::post('armados/{assembly}/finish', [WorkshopAssemblyController::class, 'finishAssembly'])->name('armados.finish');
        Route::post('armados/{assembly}/exit', [WorkshopAssemblyController::class, 'registerExit'])->name('armados.exit');
        Route::get('armados/venta-masiva', [WorkshopAssemblyController::class, 'checkoutPage'])->name('assemblies.checkout.page');
        Route::post('armados/venta-masiva', [WorkshopAssemblyController::class, 'processMassiveSale'])->name('assemblies.massive_sale');
        Route::post('armados/venta-masiva/clientes', [WorkshopAssemblyController::class, 'storeClientQuick'])->name('assemblies.massive_sale.clients.store');

        Route::get('/ordenes', [WorkshopOrderController::class, 'index'])->name('orders.index');
        Route::post('/ordenes/import-excel', [WorkshopOrderController::class, 'importExcel'])->name('orders.import-excel');
        Route::get('/ordenes/crear', [WorkshopOrderController::class, 'create'])->name('orders.create');
        Route::post('/ordenes', [WorkshopOrderController::class, 'store'])->name('orders.store');
        Route::get('/ordenes/{order}', [WorkshopOrderController::class, 'show'])->name('orders.show');
        Route::put('/ordenes/{order}', [WorkshopOrderController::class, 'update'])->name('orders.update');
        Route::delete('/ordenes/{order}', [WorkshopOrderController::class, 'destroy'])->name('orders.destroy');

        Route::post('/ordenes/{order}/detalle', [WorkshopOrderController::class, 'addDetail'])->name('orders.details.store');
        Route::put('/ordenes/{order}/detalle/{detail}', [WorkshopOrderController::class, 'updateDetail'])->name('orders.details.update');
        Route::delete('/ordenes/{order}/detalle/{detail}', [WorkshopOrderController::class, 'removeDetail'])->name('orders.details.destroy');
        Route::post('/ordenes/{order}/inspeccion', [WorkshopOrderController::class, 'updateIntake'])->name('orders.intake.update');
        Route::post('/ordenes/{order}/checklist', [WorkshopOrderController::class, 'saveChecklist'])->name('orders.checklists.store');
        Route::post('/ordenes/{order}/cotizacion', [WorkshopOrderController::class, 'generateQuotation'])->name('orders.quotation');
        Route::post('/ordenes/{order}/aprobar', [WorkshopOrderController::class, 'approve'])->name('orders.approve');
        Route::post('/ordenes/{order}/consumir', [WorkshopOrderController::class, 'consumePart'])->name('orders.consume');
        Route::post('/ordenes/{order}/generar-venta', [WorkshopOrderController::class, 'generateSale'])->name('orders.sale');
        Route::post('/ordenes/{order}/registrar-pago', [WorkshopOrderController::class, 'registerPayment'])->name('orders.payment');
        Route::post('/ordenes/{order}/devolver-pago', [WorkshopOrderController::class, 'refundPayment'])->name('orders.payment.refund');
        Route::post('/ordenes/{order}/entregar', [WorkshopOrderController::class, 'deliver'])->name('orders.deliver');
        Route::post('/ordenes/{order}/anular', [WorkshopOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/ordenes/{order}/reabrir', [WorkshopOrderController::class, 'reopen'])->name('orders.reopen');
        Route::post('/ordenes/{order}/garantia', [WorkshopOrderController::class, 'registerWarranty'])->name('orders.warranty.store');
        Route::post('/ordenes/{order}/tecnicos', [WorkshopOrderController::class, 'assignTechnicians'])->name('orders.technicians.assign');

        Route::get('/reportes', [WorkshopReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/export/ventas', [WorkshopExportController::class, 'salesMonthlyCsv'])->name('reports.export.sales');
        Route::get('/reportes/export/compras', [WorkshopExportController::class, 'purchasesMonthlyCsv'])->name('reports.export.purchases');
        Route::get('/reportes/export/os', [WorkshopExportController::class, 'workshopOrdersCsv'])->name('reports.export.orders');
        Route::get('/reportes/export/productividad', [WorkshopExportController::class, 'productivityCsv'])->name('reports.export.productivity');
        Route::get('/reportes/export/kardex', [WorkshopExportController::class, 'kardexProductCsv'])->name('reports.export.kardex');
        Route::get('/ordenes/{order}/pdf/os', [WorkshopReportController::class, 'serviceOrderPdf'])->name('pdf.order');
        Route::get('/ordenes/{order}/pdf/activacion', [WorkshopReportController::class, 'activationPdf'])->name('pdf.activation');
        Route::get('/ordenes/{order}/pdf/pdi', [WorkshopReportController::class, 'pdiPdf'])->name('pdf.pdi');
        Route::get('/ordenes/{order}/pdf/mantenimiento', [WorkshopReportController::class, 'maintenancePdf'])->name('pdf.maintenance');
        Route::get('/ordenes/{order}/pdf/repuestos', [WorkshopReportController::class, 'partsSummaryPdf'])->name('pdf.parts');
        Route::get('/ordenes/{order}/pdf/venta-interna', [WorkshopReportController::class, 'internalSalePdf'])->name('pdf.internal-sale');
        Route::post('/ordenes/{order}/pdf/os/guardar', [WorkshopReportController::class, 'saveOrderPdfSnapshot'])->name('pdf.order.save');
    });
});
