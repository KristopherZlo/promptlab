<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AcknowledgementsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExperimentController;
use App\Http\Controllers\GettingStartedController;
use App\Http\Controllers\LibraryEntryController;
use App\Http\Controllers\LlmConnectionController;
use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromptTemplateController;
use App\Http\Controllers\PromptVersionController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamMembershipController;
use App\Http\Controllers\TeamWorkspaceController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\UseCaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(app(\App\Services\WorkspaceJourneyService::class)->landingRouteName())
        : redirect()->route('login');
});

Route::get('/join/{token}', [TeamInvitationController::class, 'show'])->name('team-invitations.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/join/{token}/accept', [TeamInvitationController::class, 'accept'])->name('team-invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/start-here', [GettingStartedController::class, 'index'])->name('getting-started');
    Route::get('/acknowledgements', [AcknowledgementsController::class, 'index'])->name('acknowledgements.index');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/team-workspace', [TeamWorkspaceController::class, 'index'])->name('team-workspace.index');
    Route::prefix('/admin')->name('admin.')->group(function () {
        Route::get('/users-access', [AdministrationController::class, 'usersAccess'])->name('users-access');
        Route::get('/workspaces', [AdministrationController::class, 'workspaces'])->name('workspaces');
        Route::get('/ai-connections', [AdministrationController::class, 'aiConnections'])->name('ai-connections');
        Route::get('/audit-log', [AdministrationController::class, 'auditLog'])->name('audit-log');
    });
    Route::get('/use-cases', [UseCaseController::class, 'index'])->name('use-cases.index');
    Route::post('/use-cases', [UseCaseController::class, 'store'])->name('use-cases.store');
    Route::get('/use-cases/{useCase}', [UseCaseController::class, 'show'])->name('use-cases.show');
    Route::put('/use-cases/{useCase}', [UseCaseController::class, 'update'])->name('use-cases.update');

    Route::get('/prompts', [PromptTemplateController::class, 'index'])->name('prompt-templates.index');
    Route::get('/prompts/create', [PromptTemplateController::class, 'create'])->name('prompt-templates.create');
    Route::post('/prompts', [PromptTemplateController::class, 'store'])->name('prompt-templates.store');
    Route::get('/prompts/{promptTemplate}', [PromptTemplateController::class, 'show'])->name('prompt-templates.show');
    Route::put('/prompts/{promptTemplate}', [PromptTemplateController::class, 'update'])->name('prompt-templates.update');
    Route::post('/prompts/{promptTemplate}/versions', [PromptVersionController::class, 'store'])->name('prompt-versions.store');
    Route::put('/prompt-versions/{promptVersion}', [PromptVersionController::class, 'update'])->name('prompt-versions.update');

    Route::post('/use-cases/{useCase}/test-cases', [TestCaseController::class, 'store'])->name('test-cases.store');
    Route::put('/test-cases/{testCase}', [TestCaseController::class, 'update'])->name('test-cases.update');
    Route::delete('/test-cases/{testCase}', [TestCaseController::class, 'destroy'])->name('test-cases.destroy');

    Route::get('/playground', [PlaygroundController::class, 'index'])->name('playground');
    Route::get('/experiments/{experiment}', [ExperimentController::class, 'show'])->name('experiments.show');
    Route::get('/library', [LibraryEntryController::class, 'index'])->name('library.index');
    Route::get('/library/{libraryEntry}', [LibraryEntryController::class, 'show'])->name('library.show');
    Route::delete('/library/{libraryEntry}', [LibraryEntryController::class, 'destroy'])->name('library.destroy');

    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::post('/teams/switch', [TeamController::class, 'switch'])->name('teams.switch');
    Route::post('/team-memberships', [TeamMembershipController::class, 'store'])->name('team-memberships.store');
    Route::put('/team-memberships/{teamMembership}', [TeamMembershipController::class, 'update'])->name('team-memberships.update');
    Route::delete('/team-memberships/{teamMembership}', [TeamMembershipController::class, 'destroy'])->name('team-memberships.destroy');
    Route::post('/llm-connections', [LlmConnectionController::class, 'store'])->name('llm-connections.store');
    Route::put('/llm-connections/{llmConnection}', [LlmConnectionController::class, 'update'])->name('llm-connections.update');
    Route::delete('/llm-connections/{llmConnection}', [LlmConnectionController::class, 'destroy'])->name('llm-connections.destroy');
});

require __DIR__.'/auth.php';
