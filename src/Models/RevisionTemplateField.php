<?php

namespace Karabin\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karabin\TranslatableRevisions\Database\Factories\RevisionTemplateFieldFactory;

class RevisionTemplateField extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $casts = ['options' => 'array'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.revision_template_fields_table_name'));
        }

        parent::__construct($attributes);
    }

    /**
     * Create a new factory
     *
     * @return RevisionTemplateFieldFactory
     */
    protected static function newFactory()
    {
        return RevisionTemplateFieldFactory::new();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RevisionTemplate::class);
    }
}
