'use strict';

const mipCrypto = require('./crypto');

// Build the data string to sign
function buildSignatureData(timestamp, path, jsonBody) {
  let data = `${timestamp}${path}`;
  if (jsonBody && jsonBody.length > 0) {
    data += jsonBody;
  }
  return data;
}

// Create a MIP request signature
// Per spec: signature signs "timestamp + path + json_payload"
function signRequest(privateKeyPem, timestamp, path, jsonBody) {
  const data = buildSignatureData(timestamp, path, jsonBody);
  return mipCrypto.sign(privateKeyPem, data);
}

// Verify a MIP request signature
function verifyRequest(publicKeyPem, signature, timestamp, path, jsonBody) {
  const data = buildSignatureData(timestamp, path, jsonBody);
  return mipCrypto.verify(publicKeyPem, signature, data);
}

// Check if timestamp is within acceptable window (+/-5 minutes)
function timestampValid(timestamp, windowSeconds = 300) {
  try {
    const requestTime = new Date(timestamp);
    if (isNaN(requestTime.getTime())) return false;
    const now = new Date();
    return Math.abs(now.getTime() - requestTime.getTime()) <= windowSeconds * 1000;
  } catch {
    return false;
  }
}

module.exports = { signRequest, verifyRequest, timestampValid };
