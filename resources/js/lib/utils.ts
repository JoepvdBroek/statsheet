import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Upper-cases the first letter, e.g. "lower back" → "Lower back". */
export function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

const kgFormat = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

/** Formats a weight or Volume in kg with at most one decimal, e.g. "1,234.5". */
export function formatKg(kg: number): string {
    return kgFormat.format(kg);
}

/** Formats a timestamp as a short weekday, date and time in the owner's timezone, e.g. "Tue 30 Sep, 18:05". */
export function formatDateTime(iso: string, timeZone: string): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
    }).format(new Date(iso));
}

/** Formats a plain date such as a Week's Monday ("2026-09-28") as a short day and month, e.g. "28 Sep". */
export function formatDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(new Date(date));
}

/** Formats a timestamp as a short day and month in the owner's timezone, e.g. "22 Sep", or "Mon 22 Sep" with the weekday. */
export function formatDay(
    iso: string,
    timeZone: string,
    { weekday = false }: { weekday?: boolean } = {},
): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: weekday ? 'short' : undefined,
        day: 'numeric',
        month: 'short',
        timeZone,
    }).format(new Date(iso));
}

/** Formats a calendar month ("2026-09") as its name and year, e.g. "September 2026". */
export function formatMonth(month: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${month}-01`));
}

/**
 * Formats a Last Done relative to today in the owner's timezone: "Today", "Yesterday",
 * a weekday within the past Week, otherwise a short date; "Never done" when empty.
 */
export function formatLastDone(iso: string | null, timeZone: string): string {
    if (iso === null) {
        return 'Never done';
    }

    const daysAgo =
        calendarDay(new Date(), timeZone) -
        calendarDay(new Date(iso), timeZone);

    if (daysAgo === 0) {
        return 'Today';
    }

    if (daysAgo === 1) {
        return 'Yesterday';
    }

    if (daysAgo > 1 && daysAgo < 7) {
        return new Intl.DateTimeFormat(undefined, {
            weekday: 'long',
            timeZone,
        }).format(new Date(iso));
    }

    return formatDay(iso, timeZone);
}

/** The number of days since the epoch of the calendar day a moment falls on in the given timezone. */
function calendarDay(moment: Date, timeZone: string): number {
    const parts = new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        timeZone,
    }).formatToParts(moment);
    const part = (type: Intl.DateTimeFormatPartTypes) =>
        Number(parts.find((candidate) => candidate.type === type)?.value);

    return Date.UTC(part('year'), part('month') - 1, part('day')) / 86_400_000;
}
