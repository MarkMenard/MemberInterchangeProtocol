'use strict';

const mipCrypto = require('../crypto');

class Connection {
  constructor(attrs = {}) {
    this.mipIdentifier = attrs.mipIdentifier;
    this.mipUrl = attrs.mipUrl;
    this.publicKey = attrs.publicKey;
    this.organizationName = attrs.organizationName;
    this.contactPerson = attrs.contactPerson;
    this.contactPhone = attrs.contactPhone;
    this.status = attrs.status || 'PENDING';
    this.direction = attrs.direction; // 'inbound' or 'outbound'
    this.shareMyOrganization = attrs.shareMyOrganization !== undefined ? attrs.shareMyOrganization : true;
    this.dailyRateLimit = attrs.dailyRateLimit !== undefined ? attrs.dailyRateLimit : 100;
    this.createdAt = attrs.createdAt || new Date().toISOString();
    this.declineReason = attrs.declineReason || null;
    this.revokeReason = attrs.revokeReason || null;
  }

  isActive() { return this.status === 'ACTIVE'; }
  isPending() { return this.status === 'PENDING'; }
  isDeclined() { return this.status === 'DECLINED'; }
  isRevoked() { return this.status === 'REVOKED'; }
  isInbound() { return this.direction === 'inbound'; }
  isOutbound() { return this.direction === 'outbound'; }

  approve(opts = {}) {
    this.status = 'ACTIVE';
    this.dailyRateLimit = opts.dailyRateLimit || 100;
    if (opts.nodeProfile) {
      this._updateFromProfile(opts.nodeProfile);
    }
  }

  decline(reason) {
    this.status = 'DECLINED';
    this.declineReason = reason || null;
  }

  revoke(reason) {
    this.status = 'REVOKED';
    this.revokeReason = reason || null;
  }

  restore() {
    this.status = 'ACTIVE';
    this.revokeReason = null;
  }

  publicKeyFingerprint() {
    return mipCrypto.fingerprint(this.publicKey);
  }

  toNodeProfile() {
    return {
      mip_identifier: this.mipIdentifier,
      mip_url: this.mipUrl,
      organization_legal_name: this.organizationName,
      contact_person: this.contactPerson,
      contact_phone: this.contactPhone,
      public_key: this.publicKey,
      share_my_organization: this.shareMyOrganization
    };
  }

  // Create from a connection request payload
  static fromRequest(payload, direction = 'inbound') {
    return new Connection({
      mipIdentifier: payload.mip_identifier,
      mipUrl: payload.mip_url,
      publicKey: payload.public_key,
      organizationName: payload.organization_legal_name,
      contactPerson: payload.contact_person,
      contactPhone: payload.contact_phone,
      shareMyOrganization: payload.share_my_organization !== undefined ? payload.share_my_organization : true,
      direction,
      status: 'PENDING'
    });
  }

  _updateFromProfile(profile) {
    if (profile.organization_legal_name) this.organizationName = profile.organization_legal_name;
    if (profile.contact_person) this.contactPerson = profile.contact_person;
    if (profile.contact_phone) this.contactPhone = profile.contact_phone;
    if (profile.mip_url) this.mipUrl = profile.mip_url;
    if (profile.public_key) this.publicKey = profile.public_key;
  }
}

module.exports = Connection;
