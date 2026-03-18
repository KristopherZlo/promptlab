<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ExperimentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\LibraryEntryController;
use App\Http\Controllers\LlmConnectionController;
use App\Http\Controllers\PromptTemplateController;
use App\Http\Controllers\PromptVersionController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMembershipController;
use App\Http\Controllers\UseCaseController;
use App\Http\Controllers\TestCaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->name('api.')->group(function () {
    Route::get('/use-cases', [UseCaseController::class, 'index'])->name('use-cases.index');
    Route::post('/use-cases', [UseCaseController::class, 'store'])->name('use-cases.store');
    Route::get('/use-cases/{useCase}', [UseCaseController::class, 'show'])->name('use-cases.show');
    Route::put('/use-cases/{useCase}', [UseCaseController::class, 'update'])->name('use-cases.update');

    Route::get('/prompts', [PromptTemplateController::class, 'index'])->name('prompts.index');
    Route::post('/prompts', [PromptTemplateController::class, 'store'])->name('prompts.store');
    Route::get('/prompts/{promptTemplate}', [PromptTemplateController::class, 'show'])->name('prompts.show');
    Route::put('/prompts/{promptTemplate}', [PromptTemplateController::class, 'update'])->name('prompts.update');
    Route::post('/prompts/{promptTemplate}/versions', [PromptVersionController::class, 'store'])->name('prompt-versions.store');
    Route::put('/prompt-versions/{promptVersion}', [PromptVersionController::class, 'update'])->name('prompt-versions.update');

    Route::post('/use-cases/{useCase}/test-cases', [TestCaseController::class, 'store'])->name('test-cases.store');
    Route::put('/test-cases/{testCase}', [TestCaseController::class, 'update'])->name('test-cases.update');
    Route::delete('/test-cases/{testCase}', [TestCaseController::class, 'destroy'])->name('test-cases.destroy');

    Route::get('/experiments', [ExperimentController::class, 'index'])->name('experiments.index');
    Route::post('/experiments', [ExperimentController::class, 'store'])->name('experiments.store');
    Route::get('/experiments/{experiment}', [ExperimentController::class, 'show'])->name('experiments.show');

    Route::post('/evaluations', [EvaluationController::class, 'store'])->name('evaluations.store');

    Route::get('/library-entries', [LibraryEntryController::class, 'index'])->name('library-entries.index');
    Route::get('/library-entries/{libraryEntry}', [LibraryEntryController::class, 'show'])->name('library-entries.show');
    Route::post('/library-entries', [LibraryEntryController::class, 'store'])->name('library-entries.store');

    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::post('/teams/switch', [TeamController::class, 'switch'])->name('teams.switch');
    Route::post('/team-memberships', [TeamMembershipController::class, 'store'])->name('team-memberships.store');
    Route::put('/team-memberships/{teamMembership}', [TeamMembershipController::class, 'update'])->name('team-memberships.update');
    Route::delete('/team-memberships/{teamMembership}', [TeamMembershipController::class, 'destroy'])->name('team-memberships.destroy');
    Route::post('/llm-connections', [LlmConnectionController::class, 'store'])->name('llm-connections.store');
    Route::put('/llm-connections/{llmConnection}', [LlmConnectionController::class, 'update'])->name('llm-connections.update');
    Route::delete('/llm-connections/{llmConnection}', [LlmConnectionController::class, 'destroy'])->name('llm-connections.destroy');

    Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
    Route::get('/analytics/use-cases/{useCase}', [AnalyticsController::class, 'useCase'])->name('analytics.use-case');
});
