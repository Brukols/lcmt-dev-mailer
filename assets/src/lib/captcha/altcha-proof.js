/**
 * Whether an ALTCHA proof is expired or about to be.
 *
 * The widget solves a new challenge when the current one expires, but its
 * timer stands still while the computer sleeps and slows down in a hidden
 * tab, so a visitor coming back can hold a proof the server will refuse.
 * A proof this close to its expiry is solved again before sending, so it
 * does not expire on its way to the server. The expiry is the challenge's
 * `parameters.expiresAt`, in Unix seconds; a proof without one is treated
 * as expired.
 */
export function expiresSoon(payload, now = Date.now(), marginMs = 30000) {
  try {
    var data = JSON.parse(atob(payload));
    var expiresAt = data && data.challenge && data.challenge.parameters && data.challenge.parameters.expiresAt;

    return typeof expiresAt !== 'number' || expiresAt * 1000 - now < marginMs;
  } catch (err) {
    return true;
  }
}
