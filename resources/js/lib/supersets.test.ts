import { describe, expect, it } from 'vite-plus/test';
import {
    canJoinWithNext,
    canMove,
    groupSupersets,
    joinWithNext,
    leaveSuperset,
    moveItem,
    normaliseSupersets,
    supersetLabels,
} from '@/lib/supersets';

type Item = { name: string; superset: number | null };

/** Items named by letter, with the given Superset numbers. */
function items(...supersets: (number | null)[]): Item[] {
    return supersets.map((superset, index) => ({
        name: String.fromCharCode(97 + index),
        superset,
    }));
}

/** The order and Superset numbers as one readable string, e.g. "a1 b1 c". */
function describeOrder(list: Item[]): string {
    return list
        .map(({ name, superset }) => `${name}${superset ?? ''}`)
        .join(' ');
}

describe('normaliseSupersets', () => {
    it('renumbers Supersets by first appearance', () => {
        expect(describeOrder(normaliseSupersets(items(7, 7, null, 3, 3)))).toBe(
            'a1 b1 c d2 e2',
        );
    });

    it('dissolves a Superset with one member', () => {
        expect(describeOrder(normaliseSupersets(items(1, null, 2, 2)))).toBe(
            'a b c1 d1',
        );
    });

    it('splits a number used in two places into two Supersets', () => {
        expect(describeOrder(normaliseSupersets(items(1, 1, null, 1, 1)))).toBe(
            'a1 b1 c d2 e2',
        );
    });

    it('keeps unchanged items and returns a new array', () => {
        const list = items(1, 1, null, 5);
        const normalised = normaliseSupersets(list);

        expect(normalised).not.toBe(list);
        expect(normalised[0]).toBe(list[0]);
        expect(normalised[2]).toBe(list[2]);
        expect(normalised[3]).not.toBe(list[3]);
        expect(list[3].superset).toBe(5);
    });
});

describe('joinWithNext', () => {
    it('forms a new Superset of two single items', () => {
        expect(describeOrder(joinWithNext(items(null, null, null), 1))).toBe(
            'a b1 c1',
        );
    });

    it('adds the next item to the Superset', () => {
        expect(describeOrder(joinWithNext(items(1, 1, null), 1))).toBe(
            'a1 b1 c1',
        );
    });

    it('adds the item to the next Superset', () => {
        expect(describeOrder(joinWithNext(items(null, 1, 1), 0))).toBe(
            'a1 b1 c1',
        );
    });

    it('merges two whole Supersets', () => {
        expect(describeOrder(joinWithNext(items(1, 1, 2, 2), 1))).toBe(
            'a1 b1 c1 d1',
        );
    });

    it('renumbers the Supersets after it', () => {
        expect(describeOrder(joinWithNext(items(null, null, 1, 1), 0))).toBe(
            'a1 b1 c2 d2',
        );
    });

    it('is unchanged when the next item is in the same Superset or there is none', () => {
        expect(canJoinWithNext(items(1, 1, null), 0)).toBe(false);
        expect(canJoinWithNext(items(null, 1, 1), 2)).toBe(false);
        expect(canJoinWithNext(items(1, 1, null), 1)).toBe(true);
        expect(describeOrder(joinWithNext(items(1, 1, null), 0))).toBe(
            'a1 b1 c',
        );
    });
});

describe('leaveSuperset', () => {
    it('places the item after the last remaining member', () => {
        expect(describeOrder(leaveSuperset(items(1, 1, 1, null), 0))).toBe(
            'b1 c1 a d',
        );
    });

    it('keeps the last member in place', () => {
        expect(describeOrder(leaveSuperset(items(1, 1, 1), 2))).toBe('a1 b1 c');
    });

    it('dissolves a Superset left with one member', () => {
        expect(describeOrder(leaveSuperset(items(null, 1, 1, 2, 2), 1))).toBe(
            'a c b d1 e1',
        );
    });

    it('is unchanged for an item in no Superset', () => {
        expect(describeOrder(leaveSuperset(items(null, 1, 1), 0))).toBe(
            'a b1 c1',
        );
    });
});

describe('moveItem', () => {
    it('swaps with the member next to it within its Superset', () => {
        expect(describeOrder(moveItem(items(1, 1, 1), 1, 1))).toBe('a1 c1 b1');
        expect(describeOrder(moveItem(items(1, 1, 1), 1, -1))).toBe('b1 a1 c1');
    });

    it('leaves its Superset at the edge, landing past a single neighbour', () => {
        expect(describeOrder(moveItem(items(1, 1, 1, null), 2, 1))).toBe(
            'a1 b1 d c',
        );
        expect(describeOrder(moveItem(items(null, 1, 1, 1), 1, -1))).toBe(
            'b a c1 d1',
        );
    });

    it('dissolves a Superset left with one member', () => {
        expect(describeOrder(moveItem(items(1, 1, null), 1, 1))).toBe('a c b');
    });

    it('hops over a whole Superset when leaving its own', () => {
        expect(describeOrder(moveItem(items(1, 1, 2, 2), 1, 1))).toBe(
            'a c1 d1 b',
        );
        expect(describeOrder(moveItem(items(1, 1, 2, 2, 2), 2, -1))).toBe(
            'c a1 b1 d2 e2',
        );
    });

    it('hops a single item over a whole Superset', () => {
        expect(describeOrder(moveItem(items(null, 1, 1, null), 0, 1))).toBe(
            'b1 c1 a d',
        );
        expect(describeOrder(moveItem(items(null, 1, 1, null), 3, -1))).toBe(
            'a d b1 c1',
        );
    });

    it('swaps two single items', () => {
        expect(describeOrder(moveItem(items(null, null), 0, 1))).toBe('b a');
    });

    it('is unchanged at the end of the list', () => {
        expect(canMove(items(1, 1), 0, -1)).toBe(false);
        expect(canMove(items(1, 1), 1, 1)).toBe(false);
        expect(canMove(items(1, 1), 0, 1)).toBe(true);
        expect(describeOrder(moveItem(items(1, 1), 0, -1))).toBe('a1 b1');
        expect(describeOrder(moveItem(items(1, 1), 1, 1))).toBe('a1 b1');
    });
});

describe('supersetLabels', () => {
    it('labels members by letter and place', () => {
        expect(supersetLabels(items(4, 4, null, 9, 9, 9))).toEqual([
            'A1',
            'A2',
            null,
            'B1',
            'B2',
            'B3',
        ]);
    });
});

describe('groupSupersets', () => {
    it('cuts the order into Supersets and single items, keeping indexes', () => {
        expect(
            groupSupersets(items(null, 1, 1, null)).map(
                ({ letter, entries }) => [
                    letter,
                    entries.map(({ item, index }) => `${item.name}${index}`),
                ],
            ),
        ).toEqual([
            [null, ['a0']],
            ['A', ['b1', 'c2']],
            [null, ['d3']],
        ]);
    });
});
