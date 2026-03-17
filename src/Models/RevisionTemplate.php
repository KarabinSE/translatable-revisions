<?php

namespace Karabin\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karabin\TranslatableRevisions\Database\Factories\RevisionTemplateFactory;

class RevisionTemplate extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.revision_templates_table_name'));
        }

        parent::__construct($attributes);
    }

    /**
     * Create a new factory
     *
     * @return RevisionTemplateFactory
     */
    protected static function newFactory()
    {
        return RevisionTemplateFactory::new();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(RevisionTemplateField::class, 'template_id')
            ->orderBy('sort_index');
    }
}
