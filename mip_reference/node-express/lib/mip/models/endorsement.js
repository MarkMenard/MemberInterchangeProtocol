'use strict';

const crypto = require('crypto');
const mipCrypto = require('../crypto');

class Endorsement {
  constructor(attrs = {}) {
    this.id = attrs.id || crypto.randomUUID();
    this.endorserMipIdentifier = attrs.endorserMipIdentifier;
    this.endorsedMipIdentifier = attrs.endorsedMipIdentifier;
    this.endorsedPublicKeyFingerprint = attrs.endorsedPublicKeyFingerprint;
    this.endorsementDocument = attrs.endorsementDocument;
    this.endorsementSignature = attrs.endorsementSignature;
    this.issuedAt = attrs.issuedAt;
    this.expiresAt = attrs.expiresAt;
  }

  isExpired() {
    try {
      return new Date(this.expiresAt) < new Date();
    } catch {
      return true;
    }
  }

  validFor(publicKeyFingerprint) {
    return !this.isExpired() && this.endorsedPublicKeyFingerprint === publicKeyFingerprint;
  }

  // Verify the endorsement signature using the endorser's public key
  verifySignature(endorserPublicKey) {
    if (this.isExpired()) return false;
    return mipCrypto.verify(
      endorserPublicKey,
      this.endorsementSignature,
      this.endorsementDocument
    );
  }

  toPayload() {
    return {
      endorser_mip_identifier: this.endorserMipIdentifier,
      endorsed_mip_identifier: this.endorsedMipIdentifier,
      endorsed_public_key_fingerprint: this.endorsedPublicKeyFingerprint,
      endorsement_document: this.endorsementDocument,
      endorsement_signature: this.endorsementSignature,
      issued_at: this.issuedAt,
      expires_at: this.expiresAt
    };
  }

  // Create endorsement document and sign it
  static create(endorserIdentity, endorsedMipIdentifier, endorsedPublicKey) {
    const fp = mipCrypto.fingerprint(endorsedPublicKey);
    const issuedAt = new Date().toISOString();
    const expiresAt = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString(); // 1 year

    const document = JSON.stringify({
      type: 'MIP_ENDORSEMENT_V1',
      endorser_mip_identifier: endorserIdentity.mipIdentifier,
      endorsed_mip_identifier: endorsedMipIdentifier,
      endorsed_public_key_fingerprint: fp,
      issued_at: issuedAt,
      expires_at: expiresAt
    });

    const sig = mipCrypto.sign(endorserIdentity.privateKey, document);

    return new Endorsement({
      endorserMipIdentifier: endorserIdentity.mipIdentifier,
      endorsedMipIdentifier: endorsedMipIdentifier,
      endorsedPublicKeyFingerprint: fp,
      endorsementDocument: document,
      endorsementSignature: sig,
      issuedAt,
      expiresAt
    });
  }

  // Create from received payload
  static fromPayload(payload) {
    return new Endorsement({
      endorserMipIdentifier: payload.endorser_mip_identifier,
      endorsedMipIdentifier: payload.endorsed_mip_identifier,
      endorsedPublicKeyFingerprint: payload.endorsed_public_key_fingerprint,
      endorsementDocument: payload.endorsement_document,
      endorsementSignature: payload.endorsement_signature,
      issuedAt: payload.issued_at,
      expiresAt: payload.expires_at
    });
  }
}

module.exports = Endorsement;
