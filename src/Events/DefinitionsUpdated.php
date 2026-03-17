<?php

namespace Karabin\TranslatableRevisions\Events;

use Illuminate\Support\Collection;

class DefinitionsUpdated
{
    /**
     * Model
     *
     * @var mixed
     */
    public $model;

    /**
     * The newly published definitions.
     *
     * @var Collection
     */
    public $definitions;

    /**
     * Create a new event instance.
     *
     * @param  Collection  $definitions
     * @param  mixed  $model
     * @return void
     */
    public function __construct(Collection $definitions, $model)
    {
        // Update parent updated_at
        $model->touch();
        $this->definitions = $definitions;
        $this->model = $model;
    }
}
