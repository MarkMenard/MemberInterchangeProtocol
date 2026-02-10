'use strict';

const crypto = require('crypto');

class CogsRequest {
  constructor(attrs = {}) {
    this.sharedIdentifier = attrs.sharedIdentifier || crypto.randomUUID();
    this.direction = attrs.direction; // 'inbound' or 'outbound'
    this.targetMipIdentifier = attrs.targetMipIdentifier;
    this.targetOrg = attrs.targetOrg;
    this.requestingMember = attrs.requestingMember || {};
    this.requestedMemberNumber = attrs.requestedMemberNumber;
    this.requestedFirstName = attrs.requestedFirstName || null;
    this.requestedLastName = attrs.requestedLastName || null;
    this.requestedBirthdate = attrs.requestedBirthdate || null;
    this.notes = attrs.notes || null;
    this.status = attrs.status || 'PENDING';
    this.certificate = attrs.certificate || null;
    this.declineReason = attrs.declineReason || null;
    this.createdAt = attrs.createdAt || new Date().toISOString();
  }

  isPending() { return this.status === 'PENDING'; }
  isApproved() { return this.status === 'APPROVED'; }
  isDeclined() { return this.status === 'DECLINED'; }
  isInbound() { return this.direction === 'inbound'; }
  isOutbound() { return this.direction === 'outbound'; }

  approve(member, issuingOrg) {
    this.status = 'APPROVED';
    this.certificate = {
      shared_identifier: this.sharedIdentifier,
      status: 'APPROVED',
      good_standing: member.goodStanding,
      issued_at: new Date().toISOString(),
      valid_until: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString(), // 90 days
      issuing_organization: issuingOrg,
      member_profile: member.toMemberProfile()
    };
  }

  decline(reason) {
    this.status = 'DECLINED';
    this.declineReason = reason || null;
    this.certificate = {
      shared_identifier: this.sharedIdentifier,
      status: 'DECLINED',
      good_standing: false,
      reason
    };
  }

  toRequestPayload() {
    const payload = {
      shared_identifier: this.sharedIdentifier,
      member_number: this.requestedMemberNumber
    };
    if (this.requestedFirstName) payload.first_name = this.requestedFirstName;
    if (this.requestedLastName) payload.last_name = this.requestedLastName;
    if (this.requestedBirthdate) payload.birthdate = this.requestedBirthdate;
    // Backward-compatible fields for older implementations.
    if (this.requestingMember && Object.keys(this.requestingMember).length > 0) {
      payload.requesting_member = this.requestingMember;
    }
    payload.requested_member_number = this.requestedMemberNumber;
    if (this.notes) payload.notes = this.notes;
    return payload;
  }

  toReplyPayload() {
    return this.certificate || {
      shared_identifier: this.sharedIdentifier,
      status: this.status
    };
  }

  // Create from received request payload
  static fromRequest(payload, senderMipId, senderOrg) {
    return new CogsRequest({
      sharedIdentifier: payload.shared_identifier || crypto.randomUUID(),
      direction: 'inbound',
      targetMipIdentifier: senderMipId,
      targetOrg: senderOrg,
      requestingMember: payload.requesting_member_profile || payload.requesting_member || {},
      requestedMemberNumber: payload.member_number || payload.requested_member_number,
      requestedFirstName: payload.first_name || null,
      requestedLastName: payload.last_name || null,
      requestedBirthdate: payload.birthdate || null,
      notes: payload.notes
    });
  }
}

module.exports = CogsRequest;
