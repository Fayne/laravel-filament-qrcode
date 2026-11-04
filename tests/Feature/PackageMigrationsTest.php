<?php

it('registers the qrcode table migration with Laravel', function () {
    $migrator = app('migrator');
    $migrationFiles = $migrator->getMigrationFiles($migrator->paths());

    expect($migrationFiles)->toHaveKey('2026_09_16_000000_create_qrcodes_table');
});
