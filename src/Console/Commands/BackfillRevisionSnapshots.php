<?php

namespace Infab\TranslatableRevisions\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BackfillRevisionSnapshots extends Command
{
    protected $signature = 'translatable-revisions:snapshot-backfill
                            {--model=* : Fully-qualified model class names using HasTranslatedRevisions}';

    protected $description = 'Backfill revision snapshots by warming configured models, revisions and locales.';

    public function handle(): int
    {
        $modelClasses = array_values(array_filter((array) $this->option('model')));

        if (empty($modelClasses)) {
            $this->error('No model classes supplied. Use --model=\\App\\Models\\Page (repeatable).');

            return self::INVALID;
        }

        config(['translatable-revisions.use_snapshot_read_model' => true]);

        $metaTable = config('translatable-revisions.revision_meta_table_name');
        $termsTable = config('translatable-revisions.i18n_table_prefix_name').'i18n_terms';
        $definitionsTable = config('translatable-revisions.i18n_table_prefix_name').'i18n_definitions';
        $localesTable = config('translatable-revisions.i18n_table_prefix_name').'i18n_locales';

        $enabledLocales = DB::table($localesTable)
            ->where('enabled', 1)
            ->pluck('iso_code')
            ->filter()
            ->values();

        if ($enabledLocales->isEmpty()) {
            $this->warn('No enabled locales found. Nothing to backfill.');

            return self::SUCCESS;
        }

        $warmed = 0;
        $skipped = 0;

        foreach ($modelClasses as $modelClass) {
            if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
                $this->warn("Skipping invalid model class: {$modelClass}");
                $skipped++;
                continue;
            }

            /** @var Model $modelInstance */
            $modelInstance = new $modelClass();
            if (! method_exists($modelInstance, 'getSimpleFieldContent')) {
                $this->warn("Skipping {$modelClass}: getSimpleFieldContent method not found.");
                $skipped++;
                continue;
            }

            $modelType = $modelInstance->getMorphClass();


            $termRows = DB::table($termsTable)
                ->leftJoin($definitionsTable, $termsTable.'.id', '=', $definitionsTable.'.term_id')
                ->where($termsTable.'.model_type', $modelType)
                ->whereNotNull($termsTable.'.model_id')
                ->whereNotNull($termsTable.'.model_version')
                ->select($termsTable.'.model_id', $termsTable.'.model_version', $definitionsTable.'.locale')
                ->get();

            $metaRows = DB::table($metaTable)
                ->where('model_type', $modelType)
                ->whereNotNull('model_id')
                ->whereNotNull('model_version')
                ->select('model_id', 'model_version')
                ->get();

            $targets = [];

            foreach ($termRows as $row) {
                if (! $row->locale) {
                    continue;
                }

                $targets[] = [(int) $row->model_id, (int) $row->model_version, (string) $row->locale];
            }

            foreach ($metaRows as $row) {
                foreach ($enabledLocales as $locale) {
                    $targets[] = [(int) $row->model_id, (int) $row->model_version, (string) $locale];
                }
            }

            $targets = collect($targets)->unique(function (array $target) {
                return implode(':', $target);
            });

            foreach ($targets as $target) {
                [$modelId, $revision, $locale] = $target;

                $record = $modelClass::query()->find($modelId);

                if (! $record) {
                    $skipped++;
                    continue;
                }

                $record->getSimpleFieldContent($revision, $locale);
                $warmed++;
            }

            $this->info("Backfilled snapshots for {$modelClass}: {$targets->count()} targets processed.");
        }

        $this->info("Snapshot backfill completed. Warmed: {$warmed}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
