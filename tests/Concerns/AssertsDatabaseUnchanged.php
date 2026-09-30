<?php

namespace Tests\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checks that code only reads, as the Weekly Review agent's tools must (ADR 0003).
 */
trait AssertsDatabaseUnchanged
{
    /**
     * Run the callback and assert that no row of any table was added, changed or removed, returning the callback's result.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    protected function assertDatabaseUnchangedBy(Closure $callback): mixed
    {
        $before = $this->databaseContents();

        $result = $callback();

        $this->assertSame($before, $this->databaseContents(), 'The database changed.');

        return $result;
    }

    /**
     * Every row of every table, keyed by table name.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function databaseContents(): array
    {
        return collect(Schema::getTableListing(schemaQualified: false))
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->map(fn (object $row) => (array) $row)->all()])
            ->all();
    }
}
