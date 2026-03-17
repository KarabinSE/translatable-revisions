<?php

namespace Karabin\TranslatableRevisions\Events;

class TranslatedRevisionUpdated
{
    /**
     * Model
     *
     * @var mixed
     */
    public $model;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $model
     */
    public function __construct($model)
    {
        $this->model = $model;
    }
}
