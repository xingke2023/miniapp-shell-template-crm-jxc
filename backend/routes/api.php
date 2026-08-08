<?php

use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AssistantNoteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatLogController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\IndustryController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\QuickActionController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SsoAuthController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\WeworkCallbackController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/app-config', [AppConfigController::class, 'index']);
Route::get('/industries', [IndustryController::class, 'index']);

// SSO 单点登录（外部 Auth Center，桥接换本地 JWT）
Route::post('/auth/sso/login', [SsoAuthController::class, 'login']);
Route::post('/auth/sso/register', [SsoAuthController::class, 'register']);
Route::post('/auth/sso/exchange', [SsoAuthController::class, 'exchange']);
Route::post('/auth/sso/refresh', [SsoAuthController::class, 'refresh']);

// Protected routes
Route::middleware('auth.hybrid')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/quick-actions', [QuickActionController::class, 'index']);

    // AI 助手
    Route::post('/ai/message', [AiAssistantController::class, 'message']);
    Route::post('/ai/voice', [AiAssistantController::class, 'voice']);
    Route::get('/ai/sessions', [AiAssistantController::class, 'sessions']);
    Route::get('/ai/sessions/{id}/messages', [AiAssistantController::class, 'sessionMessages']);

    // 天气查询
    Route::get('/weather', [WeatherController::class, 'query']);

    // Posts
    Route::apiResource('/posts', PostController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
});

// Chat Logs
Route::middleware('auth.hybrid')->group(function () {
    Route::post('/chat-logs', [ChatLogController::class, 'store']);
    Route::get('/chat-logs', [ChatLogController::class, 'index']);
    Route::get('/chat-logs/conversation/{conversationId}', [ChatLogController::class, 'conversation']);
});

// 企业微信回调
Route::get('/wework/callback', [WeworkCallbackController::class, 'verify']);
Route::post('/wework/callback', [WeworkCallbackController::class, 'receive']);

// ERP — 只读（API 端），完整写入走 Filament 后台
Route::middleware('auth.hybrid')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/sales-orders', [SalesOrderController::class, 'index']);
    Route::get('/sales-orders/{salesOrder}', [SalesOrderController::class, 'show']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);

    // CRM — 完整 CRUD
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('leads', LeadController::class);
    Route::apiResource('opportunities', OpportunityController::class);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('activities', ActivityController::class);

    // API Key 管理
    Route::get('/auth/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/auth/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('/auth/api-keys/{id}', [ApiKeyController::class, 'destroy']);
});

// 助手笔记（公开）
Route::get('/assistant/notes', [AssistantNoteController::class, 'index']);
Route::post('/assistant/notes', [AssistantNoteController::class, 'store']);
Route::delete('/assistant/notes/{note}', [AssistantNoteController::class, 'destroy']);
