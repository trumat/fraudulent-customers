const dateTime = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'medium' });

/** Formats an ISO 8601 timestamp in the viewer's locale and timezone. */
export function formatDateTime(iso: string): string {
    return dateTime.format(new Date(iso));
}
