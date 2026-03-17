<?php

namespace Karabin\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class I18nTerm extends Model
{
    protected $fillable = [
        'key',
        'description',
        'model_type',
        'model_id',
        'model_version',
        'field_key',
    ];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.i18n_table_prefix_name').'i18n_terms');
        }

        parent::__construct($attributes);
    }

    /**
     * Definition relation
     *
     * @return HasMany
     */
    public function definitions(): HasMany
    {
        return $this->hasMany(I18nDefinition::class, 'term_id');
    }

    public function clearTermsWithKey(string $key): void
    {
        $termsTable = $this->getTable();

        DB::table($termsTable)->whereRaw($termsTable.'.key LIKE ? ESCAPE ?', [$key.'%', '\\'])->delete();
    }

    protected function getTemplateJoinStatement(string $templateFieldsTable): Expression
    {
        return (get_class($this->getConnection()) === 'Illuminate\Database\SQLiteConnection')
            ? DB::raw("'%' || {$templateFieldsTable}.key || '%'")
            : DB::raw("concat('%-%-',{$templateFieldsTable}.key)");
    }

    public function scopeTranslatedFields(
        Builder $query,
        string $termWithoutKey,
        string $locale,
        ?string $modelType = null,
        ?int $modelId = null,
        ?int $modelVersion = null
    ): Builder
    {
        $termsTable = $this->getTable();
        $definitionsTable = (new I18nDefinition)->getTable();
        $templateFieldsTable = config('translatable-revisions.revision_template_fields_table_name');

        if ($modelType !== null && $modelId !== null && $modelVersion !== null) {
            return $query->leftJoin($definitionsTable, $termsTable.'.id', '=', $definitionsTable.'.term_id')
                ->leftJoin($templateFieldsTable, $templateFieldsTable.'.key', '=', $termsTable.'.field_key')
                ->select(
                    $termsTable.'.id', $termsTable.'.key',
                    $termsTable.'.id as term_id',
                    $definitionsTable.'.content',
                    $templateFieldsTable.'.repeater',
                    $templateFieldsTable.'.type',
                    $templateFieldsTable.'.translated',
                    $templateFieldsTable.'.key as template_key')
                ->where($termsTable.'.model_type', $modelType)
                ->where($termsTable.'.model_id', $modelId)
                ->where($termsTable.'.model_version', $modelVersion)
                ->whereNotNull($termsTable.'.field_key')
                ->where($definitionsTable.'.locale', $locale);
        }

        return $query->leftJoin($definitionsTable, $termsTable.'.id', '=', $definitionsTable.'.term_id')
            ->leftJoin($templateFieldsTable, $termsTable.'.key', 'LIKE', $this->getTemplateJoinStatement($templateFieldsTable))
            ->select(
                $termsTable.'.id', $termsTable.'.key',
                $termsTable.'.id as term_id',
                $definitionsTable.'.content',
                $templateFieldsTable.'.repeater',
                $templateFieldsTable.'.type',
                $templateFieldsTable.'.translated',
                $templateFieldsTable.'.key as template_key')
            ->whereRaw($termsTable.'.key LIKE ? ESCAPE ?', [$termWithoutKey.'%', '\\'])
            ->where($definitionsTable.'.locale', $locale);
    }
}
