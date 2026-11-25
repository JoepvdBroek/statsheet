<?php

namespace Tests\Unit\Support;

use App\Support\Supersets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupersetsTest extends TestCase
{
    /**
     * @return array<string, array{0: list<int|null>, 1: list<int|null>}>
     */
    public static function normalisations(): array
    {
        return [
            'no Supersets' => [[null, null], [null, null]],
            'nothing' => [[], []],
            'already normalised' => [[1, 1, null, 2, 2, 2], [1, 1, null, 2, 2, 2]],
            'numbered in order of appearance' => [[7, 7, null, 3, 3], [1, 1, null, 2, 2]],
            'neighbouring Supersets' => [[2, 2, 1, 1], [1, 1, 2, 2]],
            'a single member is dissolved' => [[null, 4, null, 5, 5], [null, null, null, 1, 1]],
            'a lone member before a Superset is dissolved' => [[1, 2, 2], [null, 1, 1]],
            'each run of the same number is its own Superset' => [[1, 1, null, 1, 1, 1], [1, 1, null, 2, 2, 2]],
        ];
    }

    /**
     * @param  list<int|null>  $supersets
     * @param  list<int|null>  $expected
     */
    #[DataProvider('normalisations')]
    public function test_normalising_numbers_supersets_in_order_and_dissolves_single_members(array $supersets, array $expected)
    {
        $this->assertSame($expected, Supersets::normalise($supersets));
    }

    /**
     * @return array<string, array{0: list<int|null>, 1: bool}>
     */
    public static function contiguity(): array
    {
        return [
            'no Supersets' => [[null, null], true],
            'neighbouring members' => [[null, 1, 1, 2, 2, null], true],
            'a single member' => [[1, null], true],
            'a member apart from the others' => [[1, 1, null, 1], false],
            'interleaved Supersets' => [[1, 2, 1, 2], false],
        ];
    }

    /**
     * @param  list<int|null>  $supersets
     */
    #[DataProvider('contiguity')]
    public function test_supersets_are_contiguous_only_when_each_number_is_one_run(array $supersets, bool $expected)
    {
        $this->assertSame($expected, Supersets::areContiguous($supersets));
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function letters(): array
    {
        return [
            'first' => [1, 'A'],
            'second' => [2, 'B'],
            'last single letter' => [26, 'Z'],
            'after Z' => [27, 'AA'],
            'end of the double letters starting with A' => [52, 'AZ'],
        ];
    }

    #[DataProvider('letters')]
    public function test_a_superset_is_labelled_by_letter(int $superset, string $letter)
    {
        $this->assertSame($letter, Supersets::letter($superset));
    }
}
