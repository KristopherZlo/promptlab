<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcknowledgementsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        return Inertia::render('Acknowledgements', [
            'sources' => [
                [
                    'id' => 'agency-agents',
                    'name' => 'agency-agents',
                    'author' => 'msitarzewski',
                    'repository_url' => 'https://github.com/msitarzewski/agency-agents',
                    'summary' => 'Imported specialist prompt collections in this workspace were adapted from this open-source repository.',
                    'thanks' => 'Thanks for publishing a practical, reusable agent library that gave this workspace a strong starting point.',
                ],
                [
                    'id' => 'gepa',
                    'name' => 'gepa',
                    'author' => 'gepa-ai',
                    'repository_url' => 'https://github.com/gepa-ai/gepa',
                    'summary' => 'Prompt optimization in this workspace uses GEPA as the search loop while evaluation and model access stay inside the existing Laravel workspace services.',
                    'thanks' => 'Thanks for open-sourcing a pragmatic prompt optimization framework that could be adapted into a team workflow instead of staying a research demo.',
                ],
            ],
        ]);
    }
}
