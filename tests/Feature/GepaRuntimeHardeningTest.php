<?php

namespace Tests\Feature;

use App\Exceptions\TerminalOperationException;
use App\Models\PromptOptimizationRun;
use App\Services\GepaPromptOptimizer;
use App\Services\GepaRuntimeManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class GepaRuntimeHardeningTest extends TestCase
{
    public function test_runtime_install_fails_closed_on_checksum_mismatch(): void
    {
        $root = $this->tempPath('checksum-mismatch');

        config()->set('gepa.runtime_root', $root);
        config()->set('gepa.python_binary', $root.DIRECTORY_SEPARATOR.'python311'.DIRECTORY_SEPARATOR.'python.exe');
        config()->set('gepa.script_path', base_path('scripts/gepa_optimize_prompt.py'));
        config()->set('gepa.requirements_path', base_path('scripts/gepa-runtime-requirements.txt'));
        config()->set('gepa.python_embed_url', 'https://example.test/python-embed.zip');
        config()->set('gepa.python_embed_sha256', str_repeat('a', 64));

        Http::fake([
            'https://example.test/python-embed.zip' => Http::response('not-a-real-archive', 200),
        ]);

        $this->expectException(TerminalOperationException::class);
        $this->expectExceptionMessage('Checksum verification failed');

        app(GepaRuntimeManager::class)->install();
    }

    public function test_runtime_install_uses_hashed_requirements_file_for_dependency_installation(): void
    {
        $root = $this->tempPath('hashed-install');
        $downloads = $root.DIRECTORY_SEPARATOR.'downloads';
        File::ensureDirectoryExists($downloads);

        $pythonArchivePath = $downloads.DIRECTORY_SEPARATOR.'python-embed-source.zip';
        $pythonRoot = $root.DIRECTORY_SEPARATOR.'python311';
        File::ensureDirectoryExists($pythonRoot);

        $zip = new ZipArchive();
        $zip->open($pythonArchivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('python.exe', 'binary');
        $zip->addFromString('python311._pth', '#import site');
        $zip->close();

        $getPipSourcePath = $downloads.DIRECTORY_SEPARATOR.'get-pip-source.py';
        File::put($getPipSourcePath, "print('bootstrap')\n");

        $requirementsPath = $root.DIRECTORY_SEPARATOR.'requirements.txt';
        File::put($requirementsPath, "gepa==0.1.0 --hash=sha256:4e3f8fe8ca20169e60518b2e9d416e8c4a579459848adffdcad12223fbf9643e\n");

        config()->set('gepa.runtime_root', $root);
        config()->set('gepa.python_binary', $pythonRoot.DIRECTORY_SEPARATOR.'python.exe');
        config()->set('gepa.script_path', base_path('scripts/gepa_optimize_prompt.py'));
        config()->set('gepa.requirements_path', $requirementsPath);
        config()->set('gepa.python_embed_url', 'https://example.test/python-embed.zip');
        config()->set('gepa.get_pip_url', 'https://example.test/get-pip.py');
        config()->set('gepa.python_embed_sha256', hash_file('sha256', $pythonArchivePath));
        config()->set('gepa.get_pip_sha256', hash_file('sha256', $getPipSourcePath));

        Http::fake([
            'https://example.test/python-embed.zip' => Http::response(File::get($pythonArchivePath), 200),
            'https://example.test/get-pip.py' => Http::response(File::get($getPipSourcePath), 200),
        ]);

        $runtime = new class extends GepaRuntimeManager
        {
            public array $commands = [];

            protected function runProcess(array $command, string $workingDirectory, int $timeoutSeconds, string $timeoutMessage): void
            {
                $this->commands[] = [
                    'command' => $command,
                    'working_directory' => $workingDirectory,
                    'timeout' => $timeoutSeconds,
                    'timeout_message' => $timeoutMessage,
                ];
            }
        };

        $runtime->install();

        $this->assertCount(2, $runtime->commands);
        $this->assertContains('--require-hashes', $runtime->commands[1]['command']);
        $this->assertContains('-r', $runtime->commands[1]['command']);
        $this->assertContains($requirementsPath, $runtime->commands[1]['command']);
    }

    public function test_optimizer_times_out_with_clear_terminal_error(): void
    {
        $root = $this->tempPath('optimizer-timeout');
        $runRoot = $root.DIRECTORY_SEPARATOR.'runs';
        File::ensureDirectoryExists($runRoot);

        $scriptPath = $root.DIRECTORY_SEPARATOR.'slow-script.php';
        $requirementsPath = $root.DIRECTORY_SEPARATOR.'requirements.txt';
        File::put($requirementsPath, "gepa==0.1.0 --hash=sha256:4e3f8fe8ca20169e60518b2e9d416e8c4a579459848adffdcad12223fbf9643e\n");
        File::put($scriptPath, "<?php sleep(3);");

        config()->set('gepa.python_binary', PHP_BINARY);
        config()->set('gepa.script_path', $scriptPath);
        config()->set('gepa.requirements_path', $requirementsPath);
        config()->set('gepa.run_root', $runRoot);
        config()->set('gepa.optimization_process_timeout_seconds', 1);

        $run = new PromptOptimizationRun();
        $run->id = 1;

        $this->expectException(TerminalOperationException::class);
        $this->expectExceptionMessage('GEPA optimization timed out');

        app(GepaPromptOptimizer::class)->optimize($run, [
            'run_id' => 1,
            'project_root' => base_path(),
            'php_binary' => PHP_BINARY,
            'artisan_path' => base_path('artisan'),
            'seed_candidate' => ['system_prompt' => '', 'user_prompt_template' => ''],
            'dataset' => [['id' => 1]],
            'valset' => [['id' => 1]],
            'budget_metric_calls' => 6,
        ]);
    }

    private function tempPath(string $suffix): string
    {
        $path = storage_path('framework/testing/'.uniqid('gepa-'.$suffix.'-', true));
        File::ensureDirectoryExists($path);

        return $path;
    }
}
