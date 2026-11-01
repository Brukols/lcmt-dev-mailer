/**
 * The proof an ALTCHA widget sends with its form.
 */
import { expiresSoon } from './altcha-proof';

var TIMEOUT_MS = 30000;

/**
 * Resolve with the proof of the widget's next `verified` event, reject on its
 * next error or after timeoutMs. `start` runs once the listeners are on.
 */
function nextProof(widget, timeoutMs, start) {
  return new Promise(function (resolve, reject) {
    var timer = setTimeout(function () { done(); reject(new Error('ALTCHA timed out')); }, timeoutMs);

    function onVerified(e) {
      done();
      resolve(e.detail && e.detail.payload);
    }

    function onStateChange(e) {
      if (e.detail && e.detail.state === 'error') {
        done();
        reject(new Error('ALTCHA failed'));
      }
    }

    function done() {
      clearTimeout(timer);
      widget.removeEventListener('verified', onVerified);
      widget.removeEventListener('statechange', onStateChange);
    }

    widget.addEventListener('verified', onVerified);
    widget.addEventListener('statechange', onStateChange);
    start();
  });
}

/**
 * Whether the widget is solving a challenge (started on focus, or on its
 * challenge's expiry). Restarting it then would make the aborted run report
 * an error: in altcha 3.5 a run only sees its abort through the widget's
 * current controller, which the new run has replaced.
 */
function verifying(widget) {
  return typeof widget.getState === 'function' && widget.getState() === 'verifying';
}

/**
 * The current proof while it stays valid, else the proof of a new challenge.
 */
export function altchaToken(widget, timeoutMs = TIMEOUT_MS) {
  var form = widget.closest('form');
  var input = form ? form.querySelector('input[name="altcha"]') : null;
  var payload = input ? input.value : '';

  if (payload && !expiresSoon(payload)) return Promise.resolve(payload);

  if (verifying(widget)) return nextProof(widget, timeoutMs, function () {});

  // Start the widget over and wait for the proof of a new challenge.
  return nextProof(widget, timeoutMs, function () {
    widget.reset();
    widget.verify();
  });
}
