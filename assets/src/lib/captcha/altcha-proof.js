/**
 * Whether an ALTCHA proof is expired or about to be.
 *
 * The widget solves a new challenge when the current one expires, but its
 * timer stands still while the computer sleeps and slows down in a hidden
 * tab, so a visitor coming back can hold a proof the server will refuse.
 * A proof this close to its expiry is solved again before sending, so it
 * does not expire on its way to the server. Reads the v2 format
 * (`challenge.parameters.expiresAt`) and, for now, the v1 one (`salt`).
 */
export function expiresSoon(payload, now = Date.now(), marginMs = 30000) {
  try {
    var data = JSON.parse(atob(payload));
    var expiresAt = data && data.challenge && data.challenge.parameters && data.challenge.parameters.expiresAt;

    // Version 2 payload: the expiry is a parameter of the challenge, in seconds.
    if (typeof expiresAt === 'number') return expiresAt * 1000 - now < marginMs;

    // Version 1 payload, still issued until the challenge route is upgraded:
    // the expiry is a query parameter of the salt.
    var match = /[?&]expires=(\d+)/.exec((data && data.salt) || '');

    return !match || Number(match[1]) * 1000 - now < marginMs;
  } catch (err) {
    return true;
  }
}
