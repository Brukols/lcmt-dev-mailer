// Regenerates tests/fixtures/altcha-v3.json with ALTCHA's own library, so the
// PHP side (canonical JSON, signature, derived key) is checked against it.
//
//   source ~/.nvm/nvm.sh && nvm use 20
//   node tests/fixtures/make-altcha-fixture.mjs
//
// The secret is a test value used nowhere else.
import { writeFileSync } from 'node:fs';
import { createChallenge, solveChallenge, pbkdf2 } from 'altcha/lib';

const secret = 'fixture-secret-not-used-anywhere';

const challenge = await createChallenge({
  algorithm: 'PBKDF2/SHA-256',
  cost: 10,
  keyPrefix: '0',
  expiresAt: 4102444800, // 2100-01-01
  deriveKey: pbkdf2.deriveKey,
  hmacSignatureSecret: secret,
});

const solution = await solveChallenge({ challenge, deriveKey: pbkdf2.deriveKey });
if (!solution) throw new Error('No solution found.');

const payload = Buffer.from(JSON.stringify({ challenge, solution })).toString('base64');

writeFileSync(
  new URL('./altcha-v3.json', import.meta.url),
  JSON.stringify({ secret, challenge, solution, payload }, null, 2) + '\n'
);
