'use strict';

const mipCrypto = require('../crypto');
const identifier = require('../identifier');

class NodeIdentity {
  constructor(attrs = {}) {
    this.mipIdentifier = attrs.mipIdentifier;
    this.privateKey = attrs.privateKey;
    this.publicKey = attrs.publicKey;
    this.organizationName = attrs.organizationName;
    this.contactPerson = attrs.contactPerson;
    this.contactPhone = attrs.contactPhone;
    this.mipUrl = attrs.mipUrl;
    this.shareMyOrganization = attrs.shareMyOrganization !== undefined ? attrs.shareMyOrganization : true;
    this.trustThreshold = attrs.trustThreshold !== undefined ? attrs.trustThreshold : 1;
    this.port = attrs.port;
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

  // Generate a new node identity from config
  static fromConfig(config, port) {
    const keys = mipCrypto.generateKeyPair();
    const mipId = identifier.generate(config.organization_name);

    return new NodeIdentity({
      mipIdentifier: mipId,
      privateKey: keys.privateKey,
      publicKey: keys.publicKey,
      organizationName: config.organization_name,
      contactPerson: config.contact_person,
      contactPhone: config.contact_phone,
      mipUrl: `http://localhost:${port}/mip/node/${mipId}`,
      shareMyOrganization: config.share_my_organization !== undefined ? config.share_my_organization : true,
      trustThreshold: config.trust_threshold !== undefined ? config.trust_threshold : 1,
      port
    });
  }
}

module.exports = NodeIdentity;
