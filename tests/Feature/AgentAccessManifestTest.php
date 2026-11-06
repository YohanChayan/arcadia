<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `database/agent-access.json` decides what a read-only agent may see in this database: every
 * table is `read` (whole), `columns` (only its `allow` list) or `hidden`. The host grants exactly
 * that and nothing else, so a table or a column nobody classified would be unreadable there — and
 * this suite fails first, naming it, so the decision is made beside the migration that needs it.
 *
 * A column added later to a `read` table is readable without a decision. That is accepted: review
 * such a migration by hand.
 */
class AgentAccessManifestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{read: list<string>, columns: array<string, array{allow: list<string>, deny: list<string>}>, hidden: list<string>}
     */
    private function manifest(): array
    {
        return json_decode(file_get_contents(database_path('agent-access.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_manifest_holds_only_read_columns_and_hidden_lists_of_names(): void
    {
        $manifest = $this->manifest();

        $this->assertSame(['read', 'columns', 'hidden'], array_keys($manifest));

        foreach (['read', 'hidden'] as $list) {
            $this->assertTrue(array_is_list($manifest[$list]), "{$list} is not a list.");
            $this->assertContainsOnlyString($manifest[$list], "{$list} holds something other than a table name.");
        }

        foreach ($manifest['columns'] as $table => $columns) {
            $this->assertSame(['allow', 'deny'], array_keys($columns), "columns.{$table} must hold exactly allow and deny.");
            $this->assertContainsOnlyString([...$columns['allow'], ...$columns['deny']], "columns.{$table} holds something other than a column name.");
        }
    }

    public function test_every_migrated_table_is_classified_exactly_once(): void
    {
        $manifest = $this->manifest();
        $classified = [...$manifest['read'], ...array_keys($manifest['columns']), ...$manifest['hidden']];
        $tables = Arr::pluck(Schema::getTables(), 'name');

        $this->assertSame([], array_values(array_diff($tables, $classified)), 'Unclassified tables: add each to read, columns or hidden.');
        $this->assertSame([], array_values(array_unique(array_diff_assoc($classified, array_unique($classified)))), 'Tables classified more than once.');
        $this->assertSame([], array_values(array_diff($classified, $tables)), 'Classified tables that no migration creates.');
    }

    public function test_every_column_of_a_columns_table_is_allowed_or_denied_exactly_once(): void
    {
        foreach ($this->manifest()['columns'] as $table => $columns) {
            $named = [...$columns['allow'], ...$columns['deny']];
            $existing = Schema::getColumnListing($table);

            $this->assertSame([], array_values(array_diff($existing, $named)), "Unclassified columns of {$table}: add each to allow or deny.");
            $this->assertSame([], array_values(array_unique(array_diff_assoc($named, array_unique($named)))), "Columns of {$table} named more than once.");
            $this->assertSame([], array_values(array_diff($named, $existing)), "Columns named for {$table} that it does not have.");
        }
    }
}
