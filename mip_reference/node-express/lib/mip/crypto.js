'use strict';

const crypto = require('crypto');

// Generate a 2048-bit RSA key pair
function generateKeyPair() {
  const { publicKey, privateKey } = crypto.generateKeyPairSync('rsa', {
    modulusLength: 2048,
    publicKeyEncoding: { type: 'spki', format: 'pem' },
    privateKeyEncoding: { type: 'pkcs8', format: 'pem' }
  });
  return { publicKey, privateKey };
}

// Calculate MD5 fingerprint of a public key (colon-separated hex)
function fingerprint(publicKeyPem) {
  const keyObj = crypto.createPublicKey(publicKeyPem);
  const der = keyObj.export({ type: 'spki', format: 'der' });
  const digest = crypto.createHash('md5').update(der).digest('hex');
  return digest.match(/.{2}/g).join(':');
}

// Sign data with a private key using SHA256
function sign(privateKeyPem, data) {
  const signer = crypto.createSign('SHA256');
  signer.update(data);
  signer.end();
  const signature = signer.sign(privateKeyPem);
  return signature.toString('base64');
}

// Verify a signature using a public key
function verify(publicKeyPem, signatureBase64, data) {
  try {
    const verifier = crypto.createVerify('SHA256');
    verifier.update(data);
    verifier.end();
    const signature = Buffer.from(signatureBase64, 'base64');
    return verifier.verify(publicKeyPem, signature);
  } catch {
    return false;
  }
}

module.exports = { generateKeyPair, fingerprint, sign, verify };
