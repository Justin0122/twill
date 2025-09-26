<?php

namespace A17\Twill\Commands;

use A17\Twill\Services\Blocks\BlockImporter;
use Illuminate\Console\Command;
use RuntimeException;

class BlockImport extends Command
{
    protected $signature = 'twill:block:import {source : Path to the block package or archive.} {--force : Overwrite existing files.}';

    protected $description = 'Import block templates from a directory or archive.';

    public function __construct(protected BlockImporter $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->importer->import($this->argument('source'), (bool) $this->option('force'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->info('Imported blocks from: ' . $result->source);

        if ($result->copied !== []) {
            $this->line('Copied files:');
            foreach ($result->copied as $path) {
                $this->line('  • ' . $path);
            }
        }

        if ($result->skipped !== []) {
            $this->line('Skipped existing files (use --force to overwrite):');
            foreach ($result->skipped as $path) {
                $this->line('  • ' . $path);
            }
        }

        if ($result->hasWarnings()) {
            $this->warn('Warnings:');
            foreach ($result->warnings as $warning) {
                $this->line('  • ' . $warning);
            }
        }

        if ($result->hasConfigRecommendations()) {
            $this->line('');
            $this->comment('Suggested configuration additions (merge into config/twill.php):');
            $this->line(json_encode($result->configRecommendations(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return Command::SUCCESS;
    }
}
