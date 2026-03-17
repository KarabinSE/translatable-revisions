<?php

namespace Karabin\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Model;

class I18nLocale extends Model
{
    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.i18n_table_prefix_name').'i18n_locales');
        }

        parent::__construct($attributes);
    }
}
