<?php

namespace App\Console\Commands;

use App\Services\PodDocument\PodDocumentService;
use Illuminate\Console\Command;

/**
 * Console command to queue asynchronous text extraction for existing published POD documents (UNA-022).
 */
class BackfillPodDocumentText extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pod:documents:backfill-extraction
                            {--chunk=100 : Number of document version records to process per batch}
                            {--pod= : Optional POD ID to restrict the backfill to}
                            {--force : Force re-extraction even for already completed document versions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue asynchronous text extraction for existing published POD document versions (UNA-022)';

    /**
     * Execute the console command.
     */
    public function handle(PodDocumentService $documentService): int
    {
        $chunk = (int) $this->option('chunk');
        if ($chunk <= 0) {
            $chunk = 100;
        }

        $podOption = $this->option('pod');
        $podId = $podOption !== null ? (int) $podOption : null;
        $force = (bool) $this->option('force');

        $this->info(__('Scanning eligible published POD document versions for text extraction backfill...'));

        $stats = $documentService->backfillPublishedDocumentsText($chunk, $podId, $force);
        $queued = $stats['queued'];
        $completed = $stats['completed'];
        $total = $stats['total'];

        if ($total === 0) {
            $this->info(__('No eligible document versions found requiring text extraction backfill.'));
            return Command::SUCCESS;
        }

        if ($completed > 0) {
            $this->info(__('Completed text extraction for :count text and link document version(s).', ['count' => $completed]));
        }

        if ($queued > 0) {
            $this->info(__('Dispatched text extraction jobs for :count file-backed document version(s).', ['count' => $queued]));
            $this->comment(__('Ensure queue workers are running: php artisan queue:work --queue=:queue', [
                'queue' => config('pod_materials.extraction.queue', 'default'),
            ]));
        }

        return Command::SUCCESS;
    }
}
