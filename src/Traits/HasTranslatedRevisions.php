<?php

namespace Infab\TranslatableRevisions\Traits;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infab\TranslatableRevisions\Events\DefinitionsPublished;
use Infab\TranslatableRevisions\Events\DefinitionsUpdated;
use Infab\TranslatableRevisions\Events\TranslatedRevisionDeleted;
use Infab\TranslatableRevisions\Events\TranslatedRevisionUpdated;
use Infab\TranslatableRevisions\Exceptions\FieldKeyNotFound;
use Infab\TranslatableRevisions\Models\I18nDefinition;
use Infab\TranslatableRevisions\Models\I18nLocale;
use Infab\TranslatableRevisions\Models\I18nTerm;
use Infab\TranslatableRevisions\Models\RevisionMeta;
use Infab\TranslatableRevisions\Models\RevisionSnapshot;
use Infab\TranslatableRevisions\Models\RevisionTemplate;
use Infab\TranslatableRevisions\Models\RevisionTemplateField;

trait HasTranslatedRevisions
{
    abstract public function getRevisionOptions(): RevisionOptions;

    /**
     * Revision Options
     *
     * @var RevisionOptions
     */
    protected $revisionOptions;

    /**
     * Template field lookup cache for this model instance.
     *
     * @var array<string, RevisionTemplateField>
     */
    protected $templateFieldCache = [];

    /**
     * locale
     *
     * @var string
     */
    protected $locale = '';

    /**
     * Is the model being published
     *
     * @var bool
     */
    public $isPublishing = false;

    /**
     * revisionNumber
     *
     * @var int
     */
    public $revisionNumber;

    /**
     * Set the locale, with fallback
     *
     * @param  string  $locale
     */
    public function setLocale($locale): string
    {
        if ($locale) {
            $this->locale = $locale;
        } else {
            $this->locale = App::getLocale();
        }

        return $this->locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Sets the revision, with default fallback
     *
     * @param  int|null  $revision
     */
    public function setRevision($revision): int
    {
        if ($revision !== null) {
            $this->revisionNumber = $revision;
        } else {
            $this->revisionNumber = $this->revision;
        }

        return (int) $this->revisionNumber;
    }

    protected static function bootHasTranslatedRevisions(): void
    {
        static::deleting(function ($model) {
            $termKey = $model->getTable().$model->getDelimiter().$model->id.$model->getDelimiter();

            // Clear meta
            RevisionMeta::modelMeta($model)
                ->each(function ($item) {
                    $item->delete();
                });

            // Clear terms/defs
            (new I18nTerm)->clearTermsWithKey($termKey);
            $model->forgetAllSnapshots();
            app()->events->dispatch(new TranslatedRevisionDeleted($model));
        });

        static::updated(function ($model) {
            app()->events->dispatch(new TranslatedRevisionUpdated($model));
        });
    }

    /**
     * Relation for revisiontemplates
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RevisionTemplate::class, 'template_id', 'id');
    }

    /**
     * Relation for meta
     */
    public function meta(): MorphMany
    {
        return $this->morphMany(RevisionMeta::class, 'model');
    }

    /**
     * Relation for terms
     */
    public function terms(): MorphMany
    {
        return $this->morphMany(I18nTerm::class, 'model');
    }

    /**
     * Gets the tempplate field via fieldKey
     *
     * @return \Infab\TranslatableRevisions\Models\RevisionTemplateField
     *
     * @throws FieldKeyNotFound
     */
    public function getTemplateField(string $fieldKey)
    {
        $defTemplateSlug = $this->getResolvedRevisionOptions()->defaultTemplate;
        $cacheKey = $defTemplateSlug.'|'.$fieldKey;

        if (array_key_exists($cacheKey, $this->templateFieldCache)) {
            return $this->templateFieldCache[$cacheKey];
        }

        try {
            $templateField = RevisionTemplateField::where('key', $fieldKey)
                ->whereHas('template', function ($query) use ($defTemplateSlug) {
                    if ($defTemplateSlug) {
                        $query->where('slug', $defTemplateSlug);
                    }
                })
                ->firstOrFail();

            $this->templateFieldCache[$cacheKey] = $templateField;

            return $templateField;
        } catch (\Exception $e) {
            throw FieldKeyNotFound::fieldKeyNotFound($fieldKey);
        }
    }

    /**
     * Update content for fields
     *
     * @param  string|null  $locale
     * @param  int|null  $revision
     */
    public function updateContent(array $fieldData, $locale = null, $revision = null): Collection
    {
        $locale = $this->setLocale($locale);

        $this->setRevision($revision);

        $definitions = collect($fieldData)->map(function ($data, $fieldKey) use ($locale) {
            $delimter = $this->getDelimiter(true);
            $identifier = $this->getTable().$delimter.$this->id.$delimter.$this->revisionNumber.$delimter.$fieldKey;
            $templateField = $this->getTemplateField($fieldKey);

            // If the template field isn't translated and isn't a repeater, it's probably
            // a meta field
            if (! $templateField->translated && ! $templateField->repeater) {
                $term = $this->getTermWithoutKey($this->revisionNumber).$this->getDelimiter().$fieldKey;
                $termsTable = $this->getI18nTermsTable();
                DB::table($termsTable)->whereRaw($termsTable.'.key LIKE ? ESCAPE ?', [$term.'%', '\\'])->delete();

                return $this->updateMetaItem($fieldKey, $data);
            }

            if (is_array($data) && ! Arr::isAssoc($data) && ! $templateField->translated) {
                // Repeater
                $multiData = $this->handleSequentialArray($data, $fieldKey, $templateField, $locale);

                $updated = $this->updateMetaItem($fieldKey, $multiData);

                return ['definition' => $multiData, 'term' => $identifier, 'meta' => $updated];
            } else {
                // Translated field
                [$term, $definition] = $this->updateOrCreateTermAndDefinition($identifier, $templateField, $locale, $data);

                return ['definition' => $definition, 'term' => $term];
            }
        });

        $this->forgetSnapshot($locale, (int) $this->revisionNumber);

        app()->events->dispatch(new DefinitionsUpdated($definitions, $this));

        return $definitions;
    }

    /**
     * Update or creates terms and definitions
     *
     * @param  mixed  $data
     */
    public function updateOrCreateTermAndDefinition(string $identifier, RevisionTemplateField $templateField, string $locale, $data, bool $transformData = true): array
    {
        $modelType = $this->morphClass ?? $this->getMorphClass();
        $term = I18nTerm::updateOrCreate(
            ['key' => $identifier],
            [
                'description' => $templateField->name.' for '.$this->title,
                'model_type' => $modelType,
                'model_id' => $this->id,
                'model_version' => $this->revisionNumber,
                'field_key' => $templateField->key,
            ]
        );
        $definition = I18nDefinition::updateOrCreate(
            [
                'term_id' => $term->id,
                'locale' => $locale,
            ],
            ['content' => $transformData ? $this->transformData($data, $templateField) : $data]
        );

        $this->terms()->save($term);

        return [$term, $definition];
    }

    /**
     * Transform array to an array with id only
     *
     * @param  mixed  $data
     * @return mixed
     */
    protected function fromArrayToIdArray($data)
    {
        if (empty($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Transform images and children
     *
     * @param  mixed  $data
     * @return mixed
     */
    protected function transformData($data, RevisionTemplateField $templateField)
    {
        $revisionOptions = $this->getResolvedRevisionOptions();

        // Clean this up, atm hardcoded to images and children
        if ($templateField->repeater) {
            return collect($data)->map(function ($repeater) {
                if (array_key_exists('children', $repeater)) {
                    $repeater['children'] = collect($repeater['children'])->transform(function ($child) {
                        return $this->handleSpecialTypes($child);
                    });
                }
                $repeater = $this->handleSpecialTypes($repeater);

                return $repeater;
            });
        }

        // Transform whole objects to their ids
        if (in_array($templateField->type, $revisionOptions->specialTypes)) {
            $data = $this->fromArrayToIdArray($data);
        }

        return $data;
    }

    protected function handleSpecialTypes(array $repeater): array
    {
        $revisionOptions = $this->getResolvedRevisionOptions();

        return collect($repeater)->filter(function ($item, $key) use ($repeater) {
            if (empty($repeater[$key])) {
                return false;
            }

            return true;
        })->map(function ($value, $key) use ($revisionOptions) {
            if (in_array($key, $revisionOptions->specialTypes)) {
                return $this->fromArrayToIdArray($value);
            }

            return $value;
        })->toArray();
    }

    public function getDelimiter(bool $isSaving = false): string
    {
        $delimiterConfig = config('translatable-revisions.delimiter');

        if ($delimiterConfig === '_') {
            if ($isSaving) {
                return $delimiterConfig;
            }

            return '\_';
        }

        return $delimiterConfig;
    }

    /**
     * Get the compound term key
     *
     * @param  int|null  $revision
     */
    protected function getTermWithoutKey($revision = null): string
    {
        $delimter = $this->getDelimiter();
        if ($revision !== null) {
            $rev = $revision;
        } else {
            $rev = $this->revisionNumber;
        }

        return $this->getTable().$delimter.$this->id.$delimter.$rev.$delimter;
    }

    /**
     * Get the content for the field without using getters
     *
     * @param  int|null  $revision
     * @param  string|null  $locale
     */
    public function getSimpleFieldContent($revision = null, $locale = null): Collection
    {
        $locale = $this->setLocale($locale);
        $this->setRevision($revision);

        if ($this->shouldUseSnapshotReadModel()) {
            $snapshot = $this->getSnapshotContent($locale, (int) $this->revisionNumber);
            if ($snapshot) {
                return $snapshot;
            }
        }

        $grouped = $this->buildRawFieldContent($locale, (int) $this->revisionNumber);

        if ($this->shouldUseSnapshotReadModel()) {
            $this->saveSnapshotContent($locale, (int) $this->revisionNumber, $grouped->toArray());
        }

        return $grouped;
    }

    /**
     * Get the content for the field
     *
     * @param  int|null  $revision
     * @param  string|null  $locale
     */
    public function getFieldContent($revision = null, $locale = null): Collection
    {
        $locale = $this->setLocale($locale);
        $this->setRevision($revision);
        $revisionOptions = $this->getResolvedRevisionOptions();

        if ($this->shouldUseSnapshotReadModel()) {
            $grouped = $this->getSnapshotContent($locale, (int) $this->revisionNumber);
        } else {
            $grouped = null;
        }

        if (! $grouped) {
            $grouped = $this->buildRawFieldContent($locale, (int) $this->revisionNumber);

            if ($this->shouldUseSnapshotReadModel()) {
                $this->saveSnapshotContent($locale, (int) $this->revisionNumber, $grouped->toArray());
            }
        }

        return $grouped->mapWithKeys(function ($value, $fieldKey) use ($revisionOptions) {
            try {
                $templateField = $this->getTemplateField($fieldKey);
            } catch (FieldKeyNotFound $e) {
                return [$fieldKey => $value];
            }

            if (! $templateField->translated) {
                return [$fieldKey => $value];
            }

            if ($templateField->repeater) {
                return [$fieldKey => $this->getRepeater($value)];
            }

            if (in_array($templateField->type, $revisionOptions->specialTypes) && array_key_exists($templateField->type, $revisionOptions->getters)) {
                return [
                    $fieldKey => $this->handleCallable(
                        [$this, $revisionOptions->getters[$templateField->type]],
                        RevisionMeta::make([
                            'meta_value' => $value,
                        ])
                    ),
                ];
            }

            return [$fieldKey => $value];
        });
    }

    /**
     * Removes old revisions
     *
     * @param  int  $revision
     * @return void
     */
    public function purgeOldRevisions($revision)
    {
        $identifier = $this->getTermWithoutKey($revision);
        $termsTable = $this->getI18nTermsTable();

        I18nTerm::whereRaw($termsTable.'.key LIKE ? ESCAPE ?', [$identifier.'%', '\\'])->get()
            ->each(function ($item) {
                $item->definitions()->delete();
                $item->delete();
            });
        DB::table(config('translatable-revisions.revision_meta_table_name'))
            ->where('model_version', '<=', $revision)
            ->where('model_type', $this->morphClass ?? $this->getMorphClass())
            ->where('model_id', $this->id)
            ->delete();

        RevisionSnapshot::where('model_type', $this->morphClass ?? $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('model_version', '<=', $revision)
            ->delete();
    }

    /**
     * Translate by term key
     *
     * @return mixed
     */
    public function translateByKey(string $termKey, string $locale)
    {
        if (! $termKey) {
            return '';
        }

        $termsTable = $this->getI18nTermsTable();
        $definitionsTable = $this->getI18nDefinitionsTable();

        $value = DB::table($termsTable)
            ->leftJoin($definitionsTable, 'term_id', '=', $termsTable.'.id')
            ->where([
                ['key', '=', $termKey],
                [$definitionsTable.'.locale', '=', $locale],
            ])->value('content');
        $value = json_decode($value, true);

        return $value;
    }

    /**
     * Get meta value
     *
     * @param  RevisionMeta|\Illuminate\Database\Eloquent\Model  $meta
     * @return array|null
     */
    public function getMeta($meta)
    {
        $metaValue = $meta->meta_value;
        $value = null;
        $revisionOptions = $this->getResolvedRevisionOptions();

        if (array_key_exists($meta->meta_key, $revisionOptions->getters)) {
            $callable = [$this,  $revisionOptions->getters[$meta->meta_key]];
            $value = $this->handleCallable($callable, $meta);
        } else {
            $value = $metaValue;
        }

        return $value ? $value : null;
    }

    /**
     * Update a specific meta item
     *
     * @param  string|int  $fieldKey
     * @param  mixed  $data
     */
    public function updateMetaItem($fieldKey, $data): RevisionMeta
    {
        $updated = RevisionMeta::updateOrCreate(
            ['meta_key' => $fieldKey,
                'model_id' => $this->id,
                'model_type' => $this->morphClass ?? $this->getMorphClass(),
                'model_version' => $this->revisionNumber, ],
            [
                'meta_value' => $this->fromArrayToIdArray($data),
            ]
        );

        return $updated;
    }

    /**
     * Update a meta items with an array of data
     */
    public function updateMetaContent(array $data): array
    {
        $updatedItems = [];
        foreach ($data as $key => $content) {
            $updatedItems[] = $this->updateMetaItem($key, $content);
        }

        return $updatedItems;
    }

    /**
     * Handle callable
     *
     * @param  mixed  $callable
     * @param  \Illuminate\Database\Eloquent\Model|RevisionMeta|null  $meta
     * @return mixed
     */
    public function handleCallable($callable, $meta)
    {
        try {
            return call_user_func_array($callable, [
                $meta ?? [],
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Get repeater
     *
     * @param  mixed  $repeater
     */
    public function getRepeater($repeater): array
    {
        $revisionOptions = $this->getResolvedRevisionOptions();

        return collect($repeater)->map(function ($child) use ($revisionOptions) {
            return collect($child)->map(function ($value, $key) use ($revisionOptions) {
                // Check if key exists in the revision options
                if (array_key_exists($key, $revisionOptions->getters)) {
                    return $this->handleCallable(
                        [$this,  $revisionOptions->getters[$key]],
                        RevisionMeta::make([
                            'meta_value' => $value,
                        ])
                    );
                }
                if (Str::contains($key, 'children')) {
                    return $this->handleChildRepeater($value);
                }

                return $value;
            });
        })->toArray();
    }

    /**
     * Handle child repeater
     *
     * @param  array|null  $translatedItem
     */
    public function handleChildRepeater($translatedItem): Collection
    {
        if (! $translatedItem) {
            return collect([]);
        }

        $revisionOptions = $this->getResolvedRevisionOptions();

        return collect($translatedItem)->transform(function ($child) use ($revisionOptions) {
            return collect($child)->map(function ($item, $key) use ($revisionOptions) {
                if (array_key_exists($key, $revisionOptions->getters)) {
                    return $this->handleCallable(
                        [$this,  $revisionOptions->getters[$key]],
                        RevisionMeta::make([
                            'meta_value' => $item,
                        ])
                    );
                }

                return $item;
            });
        });
    }

    /**
     * Publish a specified revision
     *
     * @return mixed
     */
    public function publish(int $suppliedRevision)
    {
        $updatedContent = I18nLocale::where('enabled', 1)
            ->get()->mapWithKeys(function ($locale) use ($suppliedRevision) {
                $unpublishedContent = $this->getFieldContent($suppliedRevision, $locale->iso_code);

                // Move revisions
                $this->published_version = $suppliedRevision;
                $this->revision = $suppliedRevision + 1;
                $this->published_at = now();

                // Set content for new revision
                $this->updateContent($unpublishedContent->toArray(), $locale->iso_code, $this->revision);
                $this->save();

                // Prevent purge when going from revision 0 to 1
                if ($suppliedRevision > 1) {
                    $this->purgeOldRevisions($suppliedRevision - 1);
                }

                return [$locale->iso_code => $this->getFieldContent($suppliedRevision, $locale->iso_code)];
            });

        app()->events->dispatch(new DefinitionsPublished($updatedContent, $this));

        return $this;
    }

    /**
     * Handles sequentials arrays, used for repeaters
     *
     * @param  string  $fieldKey
     * @param  RevisionTemplateField  $templateField
     */
    protected function handleSequentialArray(array $data, $fieldKey, $templateField, string $locale): Collection
    {
        return collect($data)->map(function ($item, $index) use ($fieldKey, $templateField, $locale) {
            $item = collect($item)->map(function ($subfield, $key) use ($fieldKey, $index, $templateField, $locale) {
                $delimiter = $this->getDelimiter(true);
                $identifier = $this->getTable().$delimiter.$this->id.$delimiter.$this->revisionNumber.$delimiter.$fieldKey.$delimiter.$delimiter.$index.$delimiter.$key;

                // Create/Update the term
                $this->updateOrCreateTermAndDefinition($identifier, $templateField, $locale, $subfield, false);

                return $identifier;
            });

            return $item;
        });
    }

    protected function getResolvedRevisionOptions(): RevisionOptions
    {
        if (! $this->revisionOptions) {
            $this->revisionOptions = $this->getRevisionOptions();

            if (is_array($this->revisionOptions->defaultGetters) && ! empty($this->revisionOptions->defaultGetters)) {
                $this->revisionOptions->getters = array_merge(
                    $this->revisionOptions->defaultGetters,
                    $this->revisionOptions->getters
                );
            }
        }

        return $this->revisionOptions;
    }

    protected function getI18nTableName(string $table): string
    {
        return config('translatable-revisions.i18n_table_prefix_name').$table;
    }

    protected function getI18nTermsTable(): string
    {
        return $this->getI18nTableName('i18n_terms');
    }

    protected function getI18nDefinitionsTable(): string
    {
        return $this->getI18nTableName('i18n_definitions');
    }

    protected function shouldUseSnapshotReadModel(): bool
    {
        return (bool) config('translatable-revisions.use_snapshot_read_model', false);
    }

    protected function getSnapshotContent(string $locale, int $revision): ?Collection
    {
        $snapshot = RevisionSnapshot::where('model_type', $this->morphClass ?? $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('model_version', $revision)
            ->where('locale', $locale)
            ->first();

        if (! $snapshot) {
            return null;
        }

        return collect($snapshot->content ?? []);
    }

    protected function saveSnapshotContent(string $locale, int $revision, array $content): void
    {
        RevisionSnapshot::updateOrCreate(
            [
                'model_type' => $this->morphClass ?? $this->getMorphClass(),
                'model_id' => $this->id,
                'model_version' => $revision,
                'locale' => $locale,
            ],
            [
                'content' => $content,
            ]
        );
    }

    protected function forgetSnapshotsForRevision(int $revision): void
    {
        RevisionSnapshot::where('model_type', $this->morphClass ?? $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('model_version', $revision)
            ->delete();
    }

    protected function forgetSnapshot(string $locale, int $revision): void
    {
        RevisionSnapshot::where('model_type', $this->morphClass ?? $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('model_version', $revision)
            ->where('locale', $locale)
            ->delete();
    }

    protected function forgetAllSnapshots(): void
    {
        RevisionSnapshot::where('model_type', $this->morphClass ?? $this->getMorphClass())
            ->where('model_id', $this->id)
            ->delete();
    }

    protected function buildRawFieldContent(string $locale, int $revision): Collection
    {
        $termWithoutKey = $this->getTermWithoutKey($revision);

        $translatedFields = I18nTerm::translatedFields(
            $termWithoutKey,
            $locale,
            $this->morphClass ?? $this->getMorphClass(),
            (int) $this->id,
            $revision
        )->get();

        if ($translatedFields->isEmpty()) {
            $translatedFields = I18nTerm::translatedFields($termWithoutKey, $locale)->get();
        }

        $metaFields = RevisionMeta::modelMeta($this)
            ->metaFields($revision)
            ->get();

        $metaData = $metaFields->mapWithKeys(function ($metaItem) {
            return [$metaItem->meta_key => $this->getMeta($metaItem)];
        });

        $translatedData = collect($translatedFields)->mapWithKeys(function ($item) {
            if ($item->repeater) {
                return [$item->template_key => json_decode($item->content, true)];
            }

            return [$item->template_key => json_decode($item->content)];
        });

        return $translatedData->merge($metaData);
    }
}
