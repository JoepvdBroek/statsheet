/**
 * Supersets: two or more exercises of a Routine or Workout, done alternately, next to each other in the order.
 * Each exercise item carries `superset`, the number of its Superset (1, 2, … in order of first appearance), or null.
 * One number is one contiguous run of at least two items.
 *
 * Every function here is pure: it returns a new, normalised array, with new item objects only where they changed.
 */

type SupersetItem = { superset: number | null };

/** Where one Superset, or one item outside a Superset, sits in the order: from `start` to `end`, both included. */
type Run = { start: number; end: number };

/** The run around `index`: the consecutive items sharing its Superset number, or just the item when it has none. */
function runAt(items: readonly SupersetItem[], index: number): Run {
    const superset = items[index].superset;

    if (superset === null) {
        return { start: index, end: index };
    }

    let start = index;
    let end = index;

    while (start > 0 && items[start - 1].superset === superset) {
        start--;
    }

    while (end < items.length - 1 && items[end + 1].superset === superset) {
        end++;
    }

    return { start, end };
}

/** The item with the given Superset number: the same object when it already has it. */
function withSuperset<T extends SupersetItem>(
    item: T,
    superset: number | null,
): T {
    return item.superset === superset ? item : { ...item, superset };
}

/** A number no item has yet, to mark a Superset being formed until normalising renumbers it. */
function unusedSuperset(items: readonly SupersetItem[]): number {
    return Math.max(0, ...items.map((item) => item.superset ?? 0)) + 1;
}

/** The letter of the n-th Superset (from 1): A, B, … Z, AA, AB, … */
function supersetLetter(superset: number): string {
    let letter = '';

    for (let rest = superset; rest > 0; rest = Math.floor((rest - 1) / 26)) {
        letter = String.fromCharCode(65 + ((rest - 1) % 26)) + letter;
    }

    return letter;
}

/**
 * Renumbers the Supersets 1, 2, … in order of first appearance.
 * Each run of consecutive items sharing a number is one Superset, so a number split over two places becomes two;
 * a Superset with one member is no Superset, and that item gets null.
 */
export function normaliseSupersets<T extends SupersetItem>(
    items: readonly T[],
): T[] {
    const normalised: T[] = [];
    let count = 0;

    for (let start = 0; start < items.length;) {
        const { end } = runAt(items, start);
        const superset = end > start ? ++count : null;

        for (let index = start; index <= end; index++) {
            normalised.push(withSuperset(items[index], superset));
        }

        start = end + 1;
    }

    return normalised;
}

/** Whether the item at `index` can join a Superset with the next item: there is one, and it is not in the same Superset already. */
export function canJoinWithNext(
    items: readonly SupersetItem[],
    index: number,
): boolean {
    const normalised = normaliseSupersets(items);

    return (
        index >= 0 &&
        index < normalised.length - 1 &&
        runAt(normalised, index).end === index
    );
}

/**
 * Puts the item at `index` and the next item in one Superset. When either is in a Superset already,
 * the whole Supersets merge, so they stay contiguous; otherwise the two form a new Superset.
 * Unchanged when they cannot join.
 */
export function joinWithNext<T extends SupersetItem>(
    items: readonly T[],
    index: number,
): T[] {
    const normalised = normaliseSupersets(items);

    if (!canJoinWithNext(normalised, index)) {
        return normalised;
    }

    const { start } = runAt(normalised, index);
    const { end } = runAt(normalised, index + 1);
    const superset = unusedSuperset(normalised);

    return normaliseSupersets(
        normalised.map((item, position) =>
            position >= start && position <= end
                ? withSuperset(item, superset)
                : item,
        ),
    );
}

/**
 * Takes the item at `index` out of its Superset and places it directly after the Superset's last remaining member.
 * A Superset left with one member is no Superset. Unchanged when the item is in no Superset.
 */
export function leaveSuperset<T extends SupersetItem>(
    items: readonly T[],
    index: number,
): T[] {
    const normalised = normaliseSupersets(items);

    if (
        index < 0 ||
        index >= normalised.length ||
        normalised[index].superset === null
    ) {
        return normalised;
    }

    const { end } = runAt(normalised, index);
    const rest = normalised.filter((_, position) => position !== index);
    rest.splice(end, 0, withSuperset(normalised[index], null));

    return normaliseSupersets(rest);
}

/** Whether the item at `index` can move one place up (-1) or down (+1): it is not at that end of the list. */
export function canMove(
    items: readonly SupersetItem[],
    index: number,
    direction: -1 | 1,
): boolean {
    const neighbour = index + direction;

    return (
        index >= 0 &&
        index < items.length &&
        neighbour >= 0 &&
        neighbour < items.length
    );
}

/**
 * Moves the item at `index` one place up (-1) or down (+1).
 * Within its Superset it swaps with the member next to it. At its Superset's edge it leaves the Superset
 * and lands on the other side of its neighbour. It never ends up inside another Superset:
 * when the neighbour is in one, it hops over that whole Superset.
 * Unchanged at the end of the list.
 */
export function moveItem<T extends SupersetItem>(
    items: readonly T[],
    index: number,
    direction: -1 | 1,
): T[] {
    const normalised = normaliseSupersets(items);

    if (!canMove(normalised, index, direction)) {
        return normalised;
    }

    const item = normalised[index];
    const neighbour = index + direction;

    if (
        item.superset !== null &&
        normalised[neighbour].superset === item.superset
    ) {
        const swapped = [...normalised];
        [swapped[index], swapped[neighbour]] = [normalised[neighbour], item];

        return swapped;
    }

    const hopped = runAt(normalised, neighbour);
    const rest = normalised.filter((_, position) => position !== index);
    rest.splice(
        direction === 1 ? hopped.end : hopped.start,
        0,
        withSuperset(item, null),
    );

    return normaliseSupersets(rest);
}

/** Each item's label in its Superset, its letter and place, e.g. ['A1', 'A2', null, 'B1', 'B2']; null outside a Superset. */
export function supersetLabels(
    items: readonly SupersetItem[],
): (string | null)[] {
    const normalised = normaliseSupersets(items);

    return normalised.map((item, index) =>
        item.superset === null
            ? null
            : `${supersetLetter(item.superset)}${index - runAt(normalised, index).start + 1}`,
    );
}

/** One Superset, or one item outside a Superset, in the order. */
export type SupersetSection<T> = {
    /** The Superset's letter ("A"); null for a single item outside a Superset. */
    letter: string | null;
    /** Its items in order, each with its index in the whole list. */
    entries: { item: T; index: number }[];
};

/**
 * The items in order, cut into Supersets and single items, to wrap each Superset's members in one frame.
 * Each entry keeps the item's index in `items`; `letter` is the Superset's letter, null for a single item.
 */
export function groupSupersets<T extends SupersetItem>(
    items: readonly T[],
): SupersetSection<T>[] {
    const normalised = normaliseSupersets(items);
    const groups: SupersetSection<T>[] = [];

    for (let start = 0; start < normalised.length;) {
        const { end } = runAt(normalised, start);
        const superset = normalised[start].superset;

        groups.push({
            letter: superset === null ? null : supersetLetter(superset),
            entries: normalised
                .slice(start, end + 1)
                .map((item, offset) => ({ item, index: start + offset })),
        });

        start = end + 1;
    }

    return groups;
}
