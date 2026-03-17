<?php

return [
    'revisions_table_name' => env('TRANSLATABLE_REVISIONS_TABLE', 'pages'),
    'revision_templates_table_name' => env('TRANSLATABLE_REVISION_TEMPLATES_TABLE', 'revision_templates'),
    'revision_meta_table_name' => env('TRANSLATABLE_REVISIONS_META_TABLE', 'revision_meta'),
    'revision_snapshots_table_name' => env('TRANSLATABLE_REVISIONS_SNAPSHOTS_TABLE', 'revision_snapshots'),
    'revision_template_fields_table_name' => env('TRANSLATABLE_REVISION_TEMPLATE_FIELDS_TABLE', 'revision_template_fields'),
    'i18n_table_prefix_name' => env('TRANSLATABLE_REVISIONS_I18N_TABLE_PREFIX_NAME', ''),
    'delimiter' => env('TRANSLATABLE_REVISIONS_DELIMITER', '-'),
    'use_snapshot_read_model' => env('TRANSLATABLE_REVISIONS_USE_SNAPSHOT_READ_MODEL', false),
];
