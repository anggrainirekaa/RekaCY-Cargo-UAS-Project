<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Courier\TaskController as CourierTaskController;
use App\Http\Controllers\Courier\TrackingController as CourierTrackingController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\Api\ExceptionController;
use App\Http\Controllers\Api\InsightController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\TrackingController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/dashboard', [InsightController::class, 'dashboard']);

    Route::apiResource('shipments', ShipmentController::class);
    Route::get('/shipments/{shipment}/tracking', [TrackingController::class, 'index']);
    Route::post('/shipments/{shipment}/tracking', [TrackingController::class, 'store']);
    Route::get('/shipments/{shipment}/exceptions', [ExceptionController::class, 'index']);
    Route::post('/shipments/{shipment}/exceptions', [ExceptionController::class, 'store']);
    Route::get('/exceptions', [ExceptionController::class, 'all']);
    Route::patch('/exceptions/{shipmentException}', [ExceptionController::class, 'update']);
    Route::post('/exceptions/{shipmentException}/notes', [ExceptionController::class, 'note']);
    Route::get('/search', [InsightController::class, 'search']);
    Route::get('/shipments/{shipment}/weather', [InsightController::class, 'weather']);
    Route::get('/geocode', [InsightController::class, 'geocode']);

    Route::get('/shipments/{shipment}/live-location', [LiveController::class, 'shipment']);

    Route::middleware('role:courier')->prefix('courier')->group(function () {
        Route::get('/tasks', [CourierTaskController::class, 'apiIndex']);
        Route::post('/tracking/start', [CourierTrackingController::class, 'start']);
        Route::post('/tracking/stop', [CourierTrackingController::class, 'stop']);
        Route::post('/location', [CourierTrackingController::class, 'store'])->middleware('throttle:gps');
    });

    Route::middleware('role:admin,operator')->group(function () {
        Route::get('/couriers', [CourierController::class, 'apiIndex']);
        Route::post('/shipments/{shipment}/assign', [AssignmentController::class, 'store']);
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);
        Route::get('/live/metrics', [LiveController::class, 'metrics']);
    });

    Route::apiResource('carriers', CarrierController::class)->only(['index', 'store']);
    Route::apiResource('locations', LocationController::class)->only(['index', 'store']);
});
