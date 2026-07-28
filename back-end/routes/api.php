<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Tạm thời để public (không bọc auth:sanctum) để Front-end dễ gọi
Route::get('/projects', [ProjectController::class, 'index']);
Route::post('/projects', [ProjectController::class, 'store']);
Route::get('/projects/{id}', [ProjectController::class, 'show']);
Route::put('/projects/{id}', [ProjectController::class, 'update']);
Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
Route::put('/projects/{id}/members', [ProjectController::class, 'syncMembers']);

Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::put('/tasks/{id}', [TaskController::class, 'update']);
Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

Route::get('/tasks/{taskId}/comments', [TaskController::class, 'comments']);
Route::post('/tasks/{taskId}/comments', [TaskController::class, 'storeComment']);

Route::get('/users', [UserController::class, 'index']);
Route::post('/users/{code}', [UserController::class, 'updateProfile']);
Route::get('/members', [MemberController::class, 'index']);
Route::post('/members', [MemberController::class, 'store']);
Route::put('/members/{code}', [MemberController::class, 'update']);

Route::get('/groups', [GroupController::class, 'index']);
Route::post('/groups', [GroupController::class, 'store']);
Route::put('/groups/members/{memberCode}', [GroupController::class, 'assignMember']);
Route::put('/groups/{code}', [GroupController::class, 'update']);
Route::delete('/groups/{code}', [GroupController::class, 'destroy']);

// Upload File & Activities & Notifications
Route::post('/upload', [FileController::class, 'upload']);
Route::delete('/attachments/{code}', [FileController::class, 'destroy']);
Route::post('/download-archive', [DownloadController::class, 'downloadArchive']);
Route::get('/activities', [ActivityController::class, 'index']);

Route::get('/notifications', [NotificationController::class, 'index']);
Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
Route::put('/notifications/{code}/read', [NotificationController::class, 'markAsRead']);
