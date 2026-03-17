# infab/translatable-revisions

[![tests](https://github.com/ikoncept/translatable-revisions/workflows/tests/badge.svg?branch=main)](https://github.com/ikoncept/translatable-revisions/actions?query=workflow%3Atests)
[![PHPStan](https://github.com/ikoncept/translatable-revisions/workflows/PHPStan/badge.svg)](hhttps://github.com/ikoncept/translatable-revisions/actions?query=workflow%3APHPStan)


Translatable revisions for Laravel


```bash
$ composer require infab/translatable-revisions
```

## Upgrading

After upgrading, publish and run the package migrations:

```bash
php artisan vendor:publish --provider="Infab\\TranslatableRevisions\\TranslatableRevisionsServiceProvider" --tag=migrations
php artisan migrate
```

New upgrade migrations include:
- lookup indexes for revision meta and template fields
- unique constraint for i18n definitions by `(term_id, locale)`
- structured lookup columns and index on i18n terms
- optional snapshot table for read-model acceleration

## Snapshot Read Model

The package now supports an optional snapshot read model to speed up repeated field reads.

Enable it in config:

```php
'use_snapshot_read_model' => true,
```

When enabled:
- `getSimpleFieldContent()` and `getFieldContent()` read from snapshots when available
- snapshots are rebuilt on first miss
- snapshots are invalidated on updates, purges, publish, and deletes

## Backfilling Snapshots

To warm snapshots for existing content, run:

```bash
php artisan translatable-revisions:snapshot-backfill --model="App\\Models\\Page"
```

You can pass `--model` multiple times for several models.

The command resolves revisions/locales from existing terms/meta rows and warms snapshots through each model's `getSimpleFieldContent()` method so model-specific getters remain respected.

