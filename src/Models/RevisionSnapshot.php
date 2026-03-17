<?php

namespace Infab\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionSnapshot extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'model_version',
        'locale',
        'content',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.revision_snapshots_table_name'));
        }

        parent::__construct($attributes);
    }
}
