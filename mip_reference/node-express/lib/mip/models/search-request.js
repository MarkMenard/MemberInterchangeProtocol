'use strict';

const crypto = require('crypto');

class SearchRequest {
  constructor(attrs = {}) {
    this.sharedIdentifier = attrs.sharedIdentifier || crypto.randomUUID();
    this.direction = attrs.direction; // 'inbound' or 'outbound'
    this.targetMipIdentifier = attrs.targetMipIdentifier;
    this.targetOrg = attrs.targetOrg;
    this.searchParams = attrs.searchParams || {};
    this.notes = attrs.notes || null;
    this.documents = attrs.documents || [];
    this.status = attrs.status || 'PENDING';
    this.matches = attrs.matches || [];
    this.declineReason = attrs.declineReason || null;
    this.createdAt = attrs.createdAt || new Date().toISOString();
  }

  isPending() { return this.status === 'PENDING'; }
  isApproved() { return this.status === 'APPROVED'; }
  isDeclined() { return this.status === 'DECLINED'; }
  isInbound() { return this.direction === 'inbound'; }
  isOutbound() { return this.direction === 'outbound'; }

  approve(matches) {
    this.status = 'APPROVED';
    this.matches = matches;
  }

  decline(reason) {
    this.status = 'DECLINED';
    this.declineReason = reason || null;
  }

  searchDescription() {
    if (this.searchParams.member_number) {
      return `Member #${this.searchParams.member_number}`;
    } else if (this.searchParams.first_name && this.searchParams.last_name) {
      let name = `${this.searchParams.first_name} ${this.searchParams.last_name}`;
      if (this.searchParams.birthdate) {
        name += ` (${this.searchParams.birthdate})`;
      }
      return name;
    }
    return 'Unknown search';
  }

  toRequestPayload() {
    const payload = {
      shared_identifier: this.sharedIdentifier
    };
    if (this.searchParams.member_number) payload.member_number = this.searchParams.member_number;
    if (this.searchParams.first_name) payload.first_name = this.searchParams.first_name;
    if (this.searchParams.last_name) payload.last_name = this.searchParams.last_name;
    if (this.searchParams.birthdate) payload.birthdate = this.searchParams.birthdate;
    if (this.notes) payload.notes = this.notes;
    if (this.documents.length > 0) payload.documents = this.documents;
    return payload;
  }

  toReplyPayload() {
    return {
      shared_identifier: this.sharedIdentifier,
      status: this.status,
      matches: this.matches
    };
  }

  // Create from received request payload
  static fromRequest(payload, senderMipId, senderOrg) {
    const searchParams = {};
    if (payload.member_number) searchParams.member_number = payload.member_number;
    if (payload.first_name) searchParams.first_name = payload.first_name;
    if (payload.last_name) searchParams.last_name = payload.last_name;
    if (payload.birthdate) searchParams.birthdate = payload.birthdate;

    return new SearchRequest({
      sharedIdentifier: payload.shared_identifier,
      direction: 'inbound',
      targetMipIdentifier: senderMipId,
      targetOrg: senderOrg,
      searchParams,
      notes: payload.notes,
      documents: payload.documents || []
    });
  }
}

module.exports = SearchRequest;
