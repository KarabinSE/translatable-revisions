<?php

namespace Karabin\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Model;

class I18nDefinition extends Model
{
    protected $fillable = ['content', 'locale', 'term_id'];

    protected $casts = [
        'content' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.i18n_table_prefix_name').'i18n_definitions');
        }

        parent::__construct($attributes);
    }
}
