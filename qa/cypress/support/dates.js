/** A calendar date "YYYY-MM-DD", `days` from today (UTC, the way the server counts). */
export const isoInDays = (days) => {
  const date = new Date();
  date.setUTCDate(date.getUTCDate() + days);

  return date.toISOString().slice(0, 10);
};
