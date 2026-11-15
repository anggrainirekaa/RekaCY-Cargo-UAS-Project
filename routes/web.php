<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\InsightController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\Courier\TaskController as CourierTaskController;
use App\Http\Controllers\Courier\TrackingController as CourierTrackingController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExceptionController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TrackingEventController;
use App\Http\Controllers\TrackingMapController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---------- Public ----------
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : app(PageController::class)->landing())->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/track', [PublicTrackingController::class, 'show'])->middleware('throttle:30,1')->name('track.public');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

// ---------- Authenticated (all roles) ----------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('shipments', ShipmentController::class);
    Route::get('/shipments/{shipment}/weather', [InsightController::class, 'weather'])->name('shipments.weather');
    Route::post('/shipments/{shipment}/tracking', [TrackingEventController::class, 'store'])->name('shipments.tracking.store');
    Route::post('/shipments/{shipment}/exceptions', [ExceptionController::class, 'store'])->name('shipments.exceptions.store');
    Route::post('/shipments/{shipment}/documents', [DocumentController::class, 'store'])->name('shipments.documents.store');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/tracking', [TrackingMapController::class, 'index'])->name('tracking.index');
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::get('/notifications/{id}', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/new', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/replies', [SupportTicketController::class, 'reply'])->name('support.reply');
    Route::patch('/support/{ticket}', [SupportTicketController::class, 'update'])->name('support.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/search.json', [SearchController::class, 'json'])->middleware('throttle:60,1')->name('search.json');

    Route::get('/live/shipments/{shipment}', [LiveController::class, 'shipment'])->name('live.shipment');

    // ---------- Courier ----------
    Route::middleware('role:courier')->prefix('courier')->name('courier.')->group(function () {
        Route::get('/', [CourierTaskController::class, 'index'])->name('index');
        Route::get('/history', [CourierTaskController::class, 'history'])->name('history');
        Route::get('/tasks/{assignment}', [CourierTaskController::class, 'show'])->name('show');
        Route::post('/tasks/{assignment}/{action}', [CourierTaskController::class, 'act'])->whereIn('action', ['accept', 'pickup', 'start', 'arrive', 'complete', 'fail'])->name('act');
        Route::post('/tracking/start', [CourierTrackingController::class, 'start'])->name('tracking.start');
        Route::post('/tracking/stop', [CourierTrackingController::class, 'stop'])->name('tracking.stop');
        Route::post('/location', [CourierTrackingController::class, 'store'])->middleware('throttle:gps')->name('location');
    });

    // ---------- Staff (admin + operator) ----------
    Route::middleware('role:admin,operator')->group(function () {
        Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
        Route::get('/eta', [OperationsController::class, 'eta'])->name('operations.eta');
        Route::get('/exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
        Route::get('/exceptions/{shipmentException}', [ExceptionController::class, 'show'])->name('exceptions.show');
        Route::patch('/exceptions/{shipmentException}', [ExceptionController::class, 'update'])->name('exceptions.update');
        Route::post('/exceptions/{shipmentException}/notes', [ExceptionController::class, 'note'])->name('exceptions.notes.store');
        Route::get('/carriers', [CarrierController::class, 'index'])->name('carriers.index');
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/geocode', [InsightController::class, 'geocode'])->name('geocode');
        Route::get('/couriers', [CourierController::class, 'index'])->name('couriers.index');
        Route::post('/shipments/{shipment}/assign', [AssignmentController::class, 'store'])->name('shipments.assign');
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::get('/live/metrics', [LiveController::class, 'metrics'])->name('live.metrics');
        Route::get('/live/couriers', [LiveController::class, 'couriers'])->name('live.couriers');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/shipments.csv', [ReportController::class, 'csv'])->name('reports.csv');
        Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');
    });

    // ---------- Admin only ----------
    Route::middleware('role:admin')->group(function () {
        Route::post('/carriers', [CarrierController::class, 'store'])->name('carriers.store');
        Route::post('/couriers', [CourierController::class, 'store'])->name('couriers.store');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    });
});
