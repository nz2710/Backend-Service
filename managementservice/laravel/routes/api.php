<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DepotController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoutingController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\DashBoardController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Partner\CTVController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::group([
    'middleware' => ['api'],
    'namespace' => '',
], function ($router) {

    Route::group([
        'middleware' => ['authAdmin'],
        'namespace' => 'Admin',
        'prefix' => 'admin'
    ], function ($router) {
        //Depot routes
        Route::get('depot', [DepotController::class, 'index']);
        Route::post('depot', [DepotController::class, 'store']);
        Route::get('depot/{id}', [DepotController::class, 'show']);
        Route::put('depot/{id}', [DepotController::class, 'update']);
        Route::delete('depot/{id}', [DepotController::class, 'destroy']);

        //Order routes
        Route::get('order', [OrderController::class, 'index']);
        Route::post('order', [OrderController::class, 'store']);
        Route::get('order/{id}', [OrderController::class, 'show']);
        Route::put('order/{id}', [OrderController::class, 'update']);
        Route::post('order/{id}/cancel', [OrderController::class, 'destroy']);
        Route::post('order/{id}/confirm', [OrderController::class, 'confirmOrder']);
        Route::delete('order/{id}', [OrderController::class, 'delete']);

        //Partner routes
        Route::get('partner', [PartnerController::class, 'index']);
        Route::post('partner', [PartnerController::class, 'store']);
        Route::get('partner/{id}', [PartnerController::class, 'show']);
        Route::put('partner/{id}', [PartnerController::class, 'update']);
        Route::delete('partner/{id}', [PartnerController::class, 'destroy']);
        Route::get('getPartner', [PartnerController::class, 'getAll']);

        //Vehicle routes
        Route::get('vehicle', [VehicleController::class, 'index']);
        Route::post('vehicle', [VehicleController::class, 'store']);
        Route::get('vehicle/{id}', [VehicleController::class, 'show']);
        Route::put('vehicle/{id}', [VehicleController::class, 'update']);
        Route::delete('vehicle/{id}', [VehicleController::class, 'destroy']);

        //Product routes
        Route::get('product', [ProductController::class, 'index']);
        Route::post('product', [ProductController::class, 'store']);
        Route::get('product/{id}', [ProductController::class, 'show']);
        Route::post('product/{id}', [ProductController::class, 'update']);
        Route::delete('product/{id}', [ProductController::class, 'destroy']);
        Route::get('getProduct', [ProductController::class, 'getAll']);

        //Dashboard routes
        Route::get('dashboard/total-all', [DashBoardController::class, 'getTotalAll']);
        // Route::get('dashboard/summary', [DashBoardController::class, 'getSummaryData']);
        Route::get('dashboard/top-partners', [DashBoardController::class, 'getTopPartners']);
        Route::get('dashboard/top-products', [DashBoardController::class, 'getTopProducts']);
        Route::get('dashboard/revenue-summary', [DashBoardController::class, 'getRevenueSummary']);
        Route::get('dashboard/itemsold-summary', [DashBoardController::class, 'getItemSoldSummary']);
        Route::get('dashboard/cost-summary', [DashBoardController::class, 'getCostSummary']);
        Route::get('dashboard/summary', [DashBoardController::class, 'getSummary']);


        //Routing routes
        Route::post('routing/generateFile', [RoutingController::class, 'generateFile']);
        Route::get('routing', [RoutingController::class, 'index']);
        Route::delete('routing/{id}', [RoutingController::class, 'destroy']);
        Route::get('/routing/{id}', [RoutingController::class, 'show']);
        // Route::get('/routing/{routeId}/coordinates', [RoutingController::class, 'getRouteCoordinates']);
        Route::get('/route/{routeId}', [RoutingController::class, 'showRoute']);
        Route::put('/plans/{planId}/confirm', [RoutingController::class, 'confirmPlan']);
        Route::put('/plans/{planId}/complete', [RoutingController::class, 'completePlan']);
    });

    Route::group([
        'middleware' => ['authPartner'],
        'namespace' => 'Partner',
        'prefix' => 'partner'
    ], function ($router) {
        // Partner Order routes
        Route::get('orders', [CTVController::class, 'index']);
        Route::post('orders', [CTVController::class, 'store']);
        Route::get('orders/{id}', [CTVController::class, 'show']);
        Route::put('orders/{id}', [CTVController::class, 'update']);
        // Route::delete('orders/{id}', [CTVController::class, 'delete']);
        Route::post('orders/{id}/cancel', [CTVController::class, 'cancel']);

        // Partner Product routes
        Route::get('products', [CTVController::class, 'showProduct']);
        Route::get('products/{id}', [CTVController::class, 'showProductDetail']);
        Route::get('getProducts', [CTVController::class, 'getAll']);
    });
});
