<?php

use Flexpik\FilamentStudio\FilamentStudioServiceProvider;

beforeEach(function () {
    $this->published = sys_get_temp_dir().'/studio-published-'.uniqid();
    mkdir($this->published);
});

afterEach(function () {
    array_map('unlink', glob($this->published.'/*'));
    rmdir($this->published);
});

it('loads every stub when the app has published none', function () {
    expect(FilamentStudioServiceProvider::stubMigrationsToLoad(['create_a_table', 'create_b_table'], $this->published))
        ->toBe(['create_a_table', 'create_b_table']);
});

it('skips stubs the app already published, so vendor:publish + migrate does not create tables twice', function () {
    touch($this->published.'/2026_04_01_101010_create_a_table.php');

    expect(FilamentStudioServiceProvider::stubMigrationsToLoad(['create_a_table', 'create_b_table', 'z_repair'], $this->published))
        ->toBe(['create_b_table', 'z_repair']);
});

it('does not mistake a longer published name for a stub', function () {
    touch($this->published.'/2026_04_01_101010_x_create_a_table_extra.php');

    expect(FilamentStudioServiceProvider::stubMigrationsToLoad(['create_a_table'], $this->published))
        ->toBe(['create_a_table']);
});
