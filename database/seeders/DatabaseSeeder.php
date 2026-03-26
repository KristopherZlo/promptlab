<?php

namespace Database\Seeders;

use App\Models\Evaluation;
use App\Models\Experiment;
use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TestCase;
use App\Models\UseCase;
use App\Models\User;
use App\Services\ExperimentService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Config::set('queue.default', 'sync');
        Config::set('broadcasting.default', 'log');

        Evaluation::query()->delete();
        Experiment::query()->delete();
        LibraryEntry::query()->delete();

        $admin = User::updateOrCreate([
            'email' => 'admin@promptlab.local',
        ], [
            'first_name' => 'Evala',
            'last_name' => 'Admin',
            'name' => 'Evala Admin',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        $teamMember = User::updateOrCreate([
            'email' => 'team@promptlab.local',
        ], [
            'first_name' => 'Evala',
            'last_name' => 'Team',
            'name' => 'Evala Team',
            'password' => 'password',
            'role' => User::ROLE_TEAM_MEMBER,
            'email_verified_at' => now(),
        ]);

        $showcaseUser = User::updateOrCreate([
            'email' => 'showcase@evala.local',
        ], [
            'first_name' => 'Evala',
            'last_name' => 'Showcase',
            'name' => 'Evala Showcase',
            'password' => 'password',
            'role' => User::ROLE_TEAM_MEMBER,
            'email_verified_at' => now(),
        ]);

        $workspace = Team::updateOrCreate(
            ['slug' => 'evala-demo-team'],
            [
                'name' => 'Evala Demo Team',
                'description' => 'Seeded workspace for Evala demo flows.',
                'created_by' => $admin->id,
            ]
        );

        TeamMembership::updateOrCreate(
            [
                'team_id' => $workspace->id,
                'user_id' => $admin->id,
            ],
            ['role' => 'owner']
        );

        TeamMembership::updateOrCreate(
            [
                'team_id' => $workspace->id,
                'user_id' => $teamMember->id,
            ],
            ['role' => 'editor']
        );

        TeamMembership::updateOrCreate(
            [
                'team_id' => $workspace->id,
                'user_id' => $showcaseUser->id,
            ],
            ['role' => 'owner']
        );

        $admin->forceFill(['current_team_id' => $workspace->id])->save();
        $teamMember->forceFill(['current_team_id' => $workspace->id])->save();
        $showcaseUser->forceFill(['current_team_id' => $workspace->id])->save();

        [$emailUseCase, $emailTemplate, $emailVersions] = $this->seedCustomerEmailSummaries($admin, $workspace->id);
        [$ticketUseCase, $ticketTemplate, $ticketVersions] = $this->seedTicketCategorization($admin, $workspace->id);
        [$rewriteUseCase, $rewriteTemplate, $rewriteVersions] = $this->seedBusinessRewrite($admin, $workspace->id);
        [$meetingUseCase, $meetingTemplate, $meetingVersions] = $this->seedMeetingNotes($admin, $workspace->id);

        $experiments = app(ExperimentService::class);

        $emailCompare = $experiments->runInteractive($admin, [
            'mode' => 'compare',
            'prompt_version_ids' => [
                $emailVersions['v1']->id,
                $emailVersions['v2']->id,
                $emailVersions['v3']->id,
            ],
            'input_text' => 'Hi team, our account has been charged twice this week and we still cannot access the portal after resetting the password. We have a customer demo tomorrow morning, so please confirm what you need from us and when this will be fixed.',
            'variables' => [
                'language' => 'English',
            ],
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.2,
            'max_tokens' => 700,
        ]);

        $rewriteSingle = $experiments->runInteractive($teamMember, [
            'mode' => 'single',
            'prompt_version_ids' => [$rewriteVersions['v2']->id],
            'input_text' => 'hey, just checking if anyone maybe had time to look at the invoice mess because the client is getting annoyed and we kind of need an answer soon',
            'variables' => [],
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.3,
            'max_tokens' => 500,
        ]);

        $ticketBatchWeak = $experiments->queueBatch($admin, [
            'mode' => 'batch',
            'prompt_version_ids' => [$ticketVersions['v1']->id],
            'test_case_ids' => $ticketUseCase->testCases()->pluck('id')->all(),
            'variables' => [],
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.1,
            'max_tokens' => 500,
        ]);

        $ticketBatchStrong = $experiments->queueBatch($admin, [
            'mode' => 'batch',
            'prompt_version_ids' => [$ticketVersions['v2']->id],
            'test_case_ids' => $ticketUseCase->testCases()->pluck('id')->all(),
            'variables' => [],
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.1,
            'max_tokens' => 500,
        ]);

        $meetingSingle = $experiments->runInteractive($teamMember, [
            'mode' => 'single',
            'prompt_version_ids' => [$meetingVersions['v2']->id],
            'input_text' => 'Decision: keep Evala as an internal tool for the pilot. Action: design a strict JSON validator for categorization outputs. Owner: Olli. Deadline: next Friday. Note: sales team wants a short summary view for demos.',
            'variables' => [],
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.2,
            'max_tokens' => 600,
        ]);

        $this->scoreExperiment($emailCompare, $admin, [
            'v1' => [3, 3, 3, 3, false, 'medium', 'Readable but too generic.'],
            'v2' => [4, 4, 4, 4, true, 'low', 'Clear structure and good coverage.'],
            'v3' => [5, 4, 5, 4, true, 'low', 'Best balance of actionability and structure.'],
        ]);

        $this->scoreExperiment($rewriteSingle, $teamMember, [
            'v2' => [4, 4, 4, 5, true, 'low', 'Clean business tone and concise wording.'],
        ]);

        $this->scoreExperiment($ticketBatchWeak, $admin, [
            'v1' => [2, 3, 2, 3, false, 'medium', 'Output misses required JSON fields.'],
        ]);

        $this->scoreExperiment($ticketBatchStrong, $admin, [
            'v2' => [4, 4, 4, 4, true, 'low', 'Structured output is consistent across cases.'],
        ]);

        $this->scoreExperiment($meetingSingle, $teamMember, [
            'v2' => [4, 4, 5, 4, true, 'low', 'Useful action-item summary for internal follow-up.'],
        ]);

        $this->promoteToLibrary($emailVersions['v3'], $admin, 'mock:team-lab-v1', 'Best for customer support triage', 'Use for urgent support emails where action ownership matters.');
        $this->promoteToLibrary($ticketVersions['v2'], $admin, 'mock:team-lab-v1', 'Best for ticket routing', 'Use for strict JSON categorization flows.');
        $this->promoteToLibrary($meetingVersions['v2'], $admin, 'mock:team-lab-v1', 'Best for internal sync notes', 'Use for short meeting recaps with owners and deadlines.');
    }

    private function seedCustomerEmailSummaries(User $admin, int $teamId): array
    {
        $useCase = $this->upsertUseCase([
            'name' => 'Customer Support Email Summarization',
            'slug' => 'customer-email-summarization',
            'description' => 'Summarize long customer emails into actionable support notes.',
            'business_goal' => 'Reduce manual triage time and improve response consistency.',
            'primary_input_label' => 'Customer message',
            'status' => 'active',
        ], $teamId);

        $template = $this->upsertTemplate($useCase, $admin, [
            'name' => 'Customer email summarizer',
            'description' => 'Summaries for support intake and escalation.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['support', 'summary', 'triage'],
        ]);

        $versions = [
            'v1' => $this->upsertVersion($template, $admin, 'v1', [
                'change_summary' => 'Concise baseline summary.',
                'system_prompt' => 'You summarize incoming support emails for an internal team.',
                'user_prompt_template' => 'Summarize the following customer message in one short paragraph: {{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Customer message', 'required' => true],
                ],
                'output_type' => 'text',
                'notes' => 'Fast baseline for early prompt comparisons.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
            'v2' => $this->upsertVersion($template, $admin, 'v2', [
                'change_summary' => 'Structured bullet output.',
                'system_prompt' => 'You prepare concise but structured support summaries.',
                'user_prompt_template' => 'Summarize the customer message into exactly three bullets: issue, expectation, next step.\n\n{{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Customer message', 'required' => true],
                ],
                'output_type' => 'text',
                'notes' => 'Improves compare mode readability.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
            'v3' => $this->upsertVersion($template, $admin, 'v3', [
                'change_summary' => 'Action-focused support summary.',
                'system_prompt' => 'You summarize customer support emails into operational handoff notes.',
                'user_prompt_template' => "Read the message and produce:\nCore issue\nUrgency\nRequested action\nShort summary\n\n{{input_text}}",
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Customer message', 'required' => true],
                    ['name' => 'language', 'label' => 'Language', 'required' => false, 'default' => 'English'],
                ],
                'output_type' => 'text',
                'notes' => 'Best handoff format for support teams.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
        ];

        $this->upsertTestCase($useCase, [
            'title' => 'Double billing and login issue',
            'input_text' => 'We were billed twice and still cannot log in after resetting the password. Please fix this before tomorrow morning because we have a customer demo.',
            'status' => 'active',
        ]);
        $this->upsertTestCase($useCase, [
            'title' => 'Delivery confirmation missing',
            'input_text' => 'The order was marked shipped three days ago but we still do not have a delivery confirmation or tracking update. Can someone verify what happened?',
            'status' => 'active',
        ]);

        return [$useCase, $template, $versions];
    }

    private function seedTicketCategorization(User $admin, int $teamId): array
    {
        $useCase = $this->upsertUseCase([
            'name' => 'Ticket Categorization',
            'slug' => 'ticket-categorization',
            'description' => 'Classify incoming tickets into a strict JSON payload.',
            'business_goal' => 'Route support cases faster and reduce manual sorting.',
            'primary_input_label' => 'Support ticket',
            'status' => 'active',
        ], $teamId);

        $template = $this->upsertTemplate($useCase, $admin, [
            'name' => 'Ticket categorizer',
            'description' => 'JSON-based categorization for support routing.',
            'task_type' => 'classification',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['support', 'routing', 'json'],
        ]);

        $schema = [
            'required' => ['category', 'priority', 'needs_human_review', 'reason'],
            'types' => [
                'needs_human_review' => 'bool',
            ],
        ];

        $versions = [
            'v1' => $this->upsertVersion($template, $admin, 'v1', [
                'change_summary' => 'Baseline JSON classification.',
                'system_prompt' => 'Classify support tickets.',
                'user_prompt_template' => 'Read the ticket and return JSON with category, priority, needs_human_review, reason. Ticket: {{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Support ticket', 'required' => true],
                ],
                'output_type' => 'json',
                'output_schema_json' => $schema,
                'notes' => 'Known to be less strict in formatting.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
            'v2' => $this->upsertVersion($template, $admin, 'v2', [
                'change_summary' => 'Strict JSON only.',
                'system_prompt' => 'Return strict JSON only with no prose or markdown.',
                'user_prompt_template' => 'Classify the ticket into JSON with category, priority, needs_human_review, reason. JSON only.\n\n{{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Support ticket', 'required' => true],
                ],
                'output_type' => 'json',
                'output_schema_json' => $schema,
                'notes' => 'Preferred strict-format categorizer.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
        ];

        $this->upsertTestCase($useCase, [
            'title' => 'Repeated payment failure',
            'input_text' => 'My card payment keeps failing every time I try to renew the subscription and now the portal says the account is suspended.',
            'status' => 'active',
        ]);
        $this->upsertTestCase($useCase, [
            'title' => 'Wrong invoice amount',
            'input_text' => 'The invoice total is higher than the quote we approved. Please review the billing breakdown and confirm what changed.',
            'status' => 'active',
        ]);
        $this->upsertTestCase($useCase, [
            'title' => 'Export endpoint timing out',
            'input_text' => 'Every export request times out after thirty seconds and our internal reporting workflow is blocked.',
            'status' => 'active',
        ]);

        return [$useCase, $template, $versions];
    }

    private function seedBusinessRewrite(User $admin, int $teamId): array
    {
        $useCase = $this->upsertUseCase([
            'name' => 'Rewrite for Business Tone',
            'slug' => 'rewrite-for-business-tone',
            'description' => 'Rewrite raw informal text into a clean internal business message.',
            'business_goal' => 'Improve clarity and consistency in internal communication.',
            'primary_input_label' => 'Raw draft',
            'status' => 'active',
        ], $teamId);

        $template = $this->upsertTemplate($useCase, $admin, [
            'name' => 'Business tone rewrite',
            'description' => 'Internal communication cleanup for drafts and replies.',
            'task_type' => 'rewrite',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['rewrite', 'tone', 'internal'],
        ]);

        $versions = [
            'v1' => $this->upsertVersion($template, $admin, 'v1', [
                'change_summary' => 'Basic rewrite.',
                'system_prompt' => 'Rewrite informal text into a professional message.',
                'user_prompt_template' => 'Rewrite the following in a business tone: {{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Draft', 'required' => true],
                ],
                'output_type' => 'text',
                'notes' => 'Short baseline rewrite.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
            'v2' => $this->upsertVersion($template, $admin, 'v2', [
                'change_summary' => 'Clearer and more direct rewrite.',
                'system_prompt' => 'Rewrite internal messages into concise business language without sounding robotic.',
                'user_prompt_template' => 'Rewrite the draft for internal business communication. Keep it direct and helpful.\n\n{{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Draft', 'required' => true],
                ],
                'output_type' => 'text',
                'notes' => 'Preferred version for internal messages.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
        ];

        return [$useCase, $template, $versions];
    }

    private function seedMeetingNotes(User $admin, int $teamId): array
    {
        $useCase = $this->upsertUseCase([
            'name' => 'Meeting Note Summarization',
            'slug' => 'meeting-note-summarization',
            'description' => 'Turn rough meeting notes into decisions and action items.',
            'business_goal' => 'Make follow-up from internal meetings faster and less error-prone.',
            'primary_input_label' => 'Meeting notes',
            'status' => 'active',
        ], $teamId);

        $template = $this->upsertTemplate($useCase, $admin, [
            'name' => 'Meeting notes summarizer',
            'description' => 'Action-focused meeting summaries.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['meeting', 'summary', 'action-items'],
        ]);

        $versions = [
            'v1' => $this->upsertVersion($template, $admin, 'v1', [
                'change_summary' => 'Simple decision summary.',
                'system_prompt' => 'Summarize meeting notes.',
                'user_prompt_template' => 'Summarize the meeting notes into key decisions and actions.\n\n{{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Meeting notes', 'required' => true],
                ],
                'output_type' => 'text',
                'notes' => 'Short summary version.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
            'v2' => $this->upsertVersion($template, $admin, 'v2', [
                'change_summary' => 'Owners and deadlines included.',
                'system_prompt' => 'Summarize meeting notes into decisions, actions, owners, and deadlines.',
                'user_prompt_template' => 'Produce sections for key decisions, action items, owners, and deadlines.\n\n{{input_text}}',
                'variables_schema' => [
                    ['name' => 'input_text', 'label' => 'Meeting notes', 'required' => true],
                ],
                'output_type' => 'text',
                'notes' => 'Preferred meeting summary for operational follow-up.',
                'preferred_model' => 'mock:team-lab-v1',
            ]),
        ];

        return [$useCase, $template, $versions];
    }

    private function upsertUseCase(array $attributes, int $teamId): UseCase
    {
        return UseCase::updateOrCreate(
            [
                'team_id' => $teamId,
                'slug' => $attributes['slug'],
            ],
            $attributes + ['team_id' => $teamId]
        );
    }

    private function upsertTemplate(UseCase $useCase, User $admin, array $attributes): PromptTemplate
    {
        return PromptTemplate::updateOrCreate(
            [
                'team_id' => $useCase->team_id,
                'use_case_id' => $useCase->id,
                'name' => $attributes['name'],
            ],
            $attributes + [
                'team_id' => $useCase->team_id,
                'use_case_id' => $useCase->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );
    }

    private function upsertVersion(PromptTemplate $template, User $admin, string $versionLabel, array $attributes): PromptVersion
    {
        return PromptVersion::updateOrCreate(
            [
                'team_id' => $template->team_id,
                'prompt_template_id' => $template->id,
                'version_label' => $versionLabel,
            ],
            $attributes + [
                'team_id' => $template->team_id,
                'prompt_template_id' => $template->id,
                'version_label' => $versionLabel,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );
    }

    private function upsertTestCase(UseCase $useCase, array $attributes): TestCase
    {
        return TestCase::updateOrCreate(
            [
                'team_id' => $useCase->team_id,
                'use_case_id' => $useCase->id,
                'title' => $attributes['title'],
            ],
            $attributes + [
                'team_id' => $useCase->team_id,
                'use_case_id' => $useCase->id,
            ]
        );
    }

    private function scoreExperiment(Experiment $experiment, User $user, array $scoreMap): void
    {
        $experiment->load('runs.promptVersion');

        foreach ($experiment->runs as $run) {
            $versionLabel = $run->promptVersion?->version_label;
            $score = $scoreMap[$versionLabel] ?? reset($scoreMap);

            Evaluation::updateOrCreate(
                [
                    'experiment_run_id' => $run->id,
                    'evaluator_id' => $user->id,
                ],
                [
                    'team_id' => $run->team_id,
                    'clarity_score' => $score[0],
                    'correctness_score' => $score[1],
                    'completeness_score' => $score[2],
                    'tone_score' => $score[3],
                    'format_valid_manual' => $score[4],
                    'hallucination_risk' => $score[5],
                    'notes' => $score[6],
                ]
            );
        }

        app(ExperimentService::class)->refreshExperiment($experiment->fresh());
    }

    private function promoteToLibrary(
        PromptVersion $promptVersion,
        User $admin,
        string $recommendedModel,
        string $bestFor,
        string $usageNotes,
    ): void {
        $promptVersion->update(['is_library_approved' => true]);

        LibraryEntry::updateOrCreate(
            ['prompt_version_id' => $promptVersion->id],
            [
                'team_id' => $promptVersion->team_id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'recommended_model' => $recommendedModel,
                'best_for' => $bestFor,
                'usage_notes' => $usageNotes,
            ]
        );
    }
}
