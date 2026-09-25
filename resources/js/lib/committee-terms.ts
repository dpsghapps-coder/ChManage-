/** "Ends in 45 days", "Ends tomorrow", "Ends today", or "Ended 10 days ago". */
export const termTiming = (daysLeft: number) =>
    daysLeft > 1
        ? `Ends in ${daysLeft} days`
        : daysLeft === 1
          ? 'Ends tomorrow'
          : daysLeft === 0
            ? 'Ends today'
            : `Ended ${-daysLeft} day${daysLeft === -1 ? '' : 's'} ago`;
