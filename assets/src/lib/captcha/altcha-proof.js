/**
 * Whether an ALTCHA proof is expired or about to be.
 *
 * The widget solves a new challenge when the current one expires, but its
 * timer stands still while the computer sleeps and slows down in a hidden
 * tab, so a visitor coming back can hold a proof the server will refuse.
 * A proof this close to its expiry is solved again before sending, so it
 * does not expire on its way to the server.
 */
export function expiresSoon(payload, now = Date.now(), marginMs = 30000) {
  try {
    var salt = JSON.parse(atob(payload)).salt || '';
    var match = /[?&]expires=(\d+)/.exec(salt);

    return !match || Number(match[1]) * 1000 - now < marginMs;
  } catch (err) {
    return true;
  }
}
