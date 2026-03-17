<?php

namespace Karabin\TranslatableRevisions\Traits;

class RevisionOptions
{
    /**
     * Types that require special care,
     * for example files, images etc
     *
     * @var array
     */
    public $specialTypes = [];

    /**
     * Getters, specify how the fields should
     * be represented or transformed
     *
     * @var array
     */
    public $getters = [];

    /**
     * Default template for a model which refers
     * to the slug field on the revision_templates table
     *
     *
     * @var string
     */
    public $defaultTemplate = '';

    /**
     * Arrayg of tags that should be
     * flushed on save and delete
     *
     * @var array
     */
    public $cacheKeysToFlush = [];

    /**
     * Default included getters
     *
     * @var array
     */
    public $defaultGetters = ['repeater' => 'getRepeater'];

    /**
     * If the model should be indexable
     *
     * @var bool
     */
    public $isIndexable = false;

    /**
     * Indexable keys
     *
     * @var array
     */
    public $indexableKeys = [];

    /**
     * Key to save as title
     *
     * @var string
     */
    public $titleKey = '';

    /**
     * Callable method for indexing
     *
     * @var null|callable
     */
    public $indexFunction = null;

    public static function create(): self
    {
        return new static();
    }

    /**
     * Register affected types
     */
    public function registerSpecialTypes(array $types): self
    {
        $this->specialTypes = $types;

        return $this;
    }

    /**
     * Register new getters
     */
    public function registerGetters(array $getters): self
    {
        $this->getters = $getters;

        return $this;
    }

    /**
     * Register a default template
     */
    public function registerDefaultTemplate(string $slug): self
    {
        $this->defaultTemplate = $slug;

        return $this;
    }

    public function registerCacheKeysToFlush(array $tags): self
    {
        $this->cacheKeysToFlush = $tags;

        return $this;
    }

    /**
     * @deprecated
     *
     * @param  array  $tags
     * @return self
     */
    public function registerCacheTagsToFlush(array $tags): self
    {
        $this->cacheKeysToFlush = $tags;

        return $this;
    }

    /**
     * Set indexable options
     *
     * @param  bool  $indexable
     * @param  array  $indexableKeys
     * @param  string  $titleKey
     * @return self
     */
    public function setIndexable(bool $indexable, array $indexableKeys = [], string $titleKey = '', ?callable $callable = null): self
    {
        $this->isIndexable = $indexable;
        $this->indexableKeys = $indexableKeys;
        $this->titleKey = $titleKey;
        $this->indexFunction = $callable;

        return $this;
    }
}
