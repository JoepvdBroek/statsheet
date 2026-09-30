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
