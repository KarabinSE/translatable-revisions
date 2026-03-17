<?php

namespace Karabin\TranslatableRevisions\Events;

use Illuminate\Support\Collection;

class DefinitionsPublished
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
     */
    public function __construct(Collection $definitions, $model)
    {
        $this->definitions = $definitions;
        $this->model = $model;
    }
}
