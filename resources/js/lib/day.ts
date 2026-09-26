/** A date such as 2026-10-04 in words: "Sun, 4 Oct 2026". */
export const day = (value: string) =>
    new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
