<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyOverviewController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectNoteController;
use App\Http\Controllers\ProjectPlanningController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskProgressController;
use App\Http\Controllers\TaskRelationsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1');

Route::middleware('api.token')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/profile/complete', [UserController::class, 'completeProfile']);
    Route::put('/profile/password', [UserController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('profile.complete')->group(function () {
        Route::get('/company-overview', [CompanyOverviewController::class, 'index']);
        Route::get('/my-work', [ReportController::class, 'myWork']);
        Route::get('/reports', [ReportController::class, 'index']);

        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects-trash', [ProjectController::class, 'trash']);
        Route::post('/projects/{id}/restore', [ProjectController::class, 'restore']);
        Route::put('/projects/{id}/members', [ProjectController::class, 'syncMembers']);
        Route::post('/projects/{id}/updates', [ProjectPlanningController::class, 'storeUpdate']);
        Route::get('/projects/{id}/notes', [ProjectNoteController::class, 'index']);
        Route::post('/projects/{id}/notes', [ProjectNoteController::class, 'store']);
        Route::put('/projects/{id}/notes/{note}', [ProjectNoteController::class, 'update']);
        Route::delete('/projects/{id}/notes/{note}', [ProjectNoteController::class, 'destroy']);
        Route::get('/projects/{id}/activities', [ActivityController::class, 'project']);
        Route::post('/projects/{id}/milestones', [ProjectPlanningController::class, 'storeMilestone']);
        Route::put('/projects/{id}/milestones/{milestone}', [ProjectPlanningController::class, 'updateMilestone']);
        Route::delete('/projects/{id}/milestones/{milestone}', [ProjectPlanningController::class, 'destroyMilestone']);
        Route::get('/projects/{id}/automations', [ProjectPlanningController::class, 'automations']);
        Route::post('/projects/{id}/automations', [ProjectPlanningController::class, 'storeAutomation']);
        Route::get('/projects/{id}', [ProjectController::class, 'show']);
        Route::put('/projects/{id}', [ProjectController::class, 'update']);
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);

        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::get('/tasks-trash', [TaskController::class, 'trash']);
        Route::post('/tasks/{id}/restore', [TaskController::class, 'restore']);
        Route::put('/tasks/{id}', [TaskController::class, 'update']);
        Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
        Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
        Route::put('/tasks/{taskId}/dependencies', [TaskRelationsController::class, 'syncDependencies']);
        Route::post('/tasks/{taskId}/watch', [TaskRelationsController::class, 'toggleWatcher']);

        Route::post('/tasks/{taskId}/checklists', [TaskProgressController::class, 'storeChecklist']);
        Route::patch('/tasks/{taskId}/checklists/{checklistId}', [TaskProgressController::class, 'updateChecklist']);
        Route::delete('/tasks/{taskId}/checklists/{checklistId}', [TaskProgressController::class, 'destroyChecklist']);
        Route::post('/tasks/{taskId}/work-logs', [TaskProgressController::class, 'storeWorkLog']);
        Route::get('/tasks/{taskId}/comments', [TaskController::class, 'comments']);
        Route::post('/tasks/{taskId}/comments', [TaskController::class, 'storeComment']);

        Route::get('/saved-views', [SavedViewController::class, 'index']);
        Route::post('/saved-views', [SavedViewController::class, 'store']);
        Route::put('/saved-views/{code}', [SavedViewController::class, 'update']);
        Route::delete('/saved-views/{code}', [SavedViewController::class, 'destroy']);

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

        Route::post('/upload', [FileController::class, 'upload']);
        Route::delete('/attachments/{code}', [FileController::class, 'destroy']);
        Route::get('/attachments/{code}/download', [DownloadController::class, 'downloadAttachment']);
        Route::post('/download-archive', [DownloadController::class, 'downloadArchive']);
        Route::get('/activities', [ActivityController::class, 'index']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notification-preferences', [NotificationController::class, 'preferences']);
        Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('/notifications/{code}/read', [NotificationController::class, 'markAsRead']);
    });
});
