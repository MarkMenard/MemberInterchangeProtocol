'use strict';

class Member {
  constructor(attrs = {}) {
    this.memberNumber = attrs.memberNumber;
    this.prefix = attrs.prefix || null;
    this.firstName = attrs.firstName;
    this.middleName = attrs.middleName || null;
    this.lastName = attrs.lastName;
    this.suffix = attrs.suffix || null;
    this.honorific = attrs.honorific || null;
    this.rank = attrs.rank || null;
    this.birthdate = attrs.birthdate || null;
    this.yearsInGoodStanding = attrs.yearsInGoodStanding || 0;
    this.status = attrs.status || 'Active';
    this.isActive = attrs.isActive !== undefined ? attrs.isActive : true;
    this.goodStanding = attrs.goodStanding !== undefined ? attrs.goodStanding : true;
    this.email = attrs.email || null;
    this.phone = attrs.phone || null;
    this.cell = attrs.cell || null;
    this.address = attrs.address || {};
    this.affiliations = attrs.affiliations || [];
    this.lifeCycleEvents = attrs.lifeCycleEvents || [];
  }

  fullName() {
    return [this.prefix, this.firstName, this.middleName, this.lastName, this.suffix]
      .filter(x => x && x.length > 0)
      .join(' ');
  }

  partyShortName() {
    return `${this.firstName} ${this.lastName[0]}`;
  }

  // Member type from first active affiliation
  memberType() {
    const active = this.affiliations.find(a => a.is_active);
    return (active && active.member_type) || 'Member';
  }

  // Format for search response
  toSearchResult() {
    return {
      member_number: this.memberNumber,
      first_name: this.firstName,
      last_name: this.lastName,
      birthdate: this.birthdate,
      contact: {
        email: this.email,
        phone: this.phone,
        address: this.address
      },
      group_status: {
        status: this.status,
        is_active: this.isActive,
        good_standing: this.goodStanding
      },
      affiliations: this.affiliations.map(aff => ({
        local_name: aff.local_name,
        local_status: aff.local_status || aff.status,
        is_active: aff.is_active,
        member_type: aff.member_type
      }))
    };
  }

  // Full member profile for COGS
  toMemberProfile() {
    return {
      member_number: this.memberNumber,
      prefix: this.prefix,
      first_name: this.firstName,
      middle_name: this.middleName,
      last_name: this.lastName,
      suffix: this.suffix,
      honorific: this.honorific,
      rank: this.rank,
      birthdate: this.birthdate,
      years_in_good_standing: this.yearsInGoodStanding,
      group_status: {
        status: this.status,
        is_active: this.isActive
      },
      contact: {
        email: this.email,
        phone: this.phone,
        cell: this.cell,
        address: this.address
      },
      affiliations: this.affiliations,
      life_cycle_events: this.lifeCycleEvents
    };
  }

  // Quick status check response
  toStatusCheck() {
    return {
      member_number: this.memberNumber,
      member_type: this.memberType(),
      party_short_name: this.partyShortName(),
      group_status: {
        status: this.status,
        is_active: this.isActive,
        good_standing: this.goodStanding
      }
    };
  }

  // Create from config YAML
  static fromConfig(config) {
    return new Member({
      memberNumber: config.member_number,
      prefix: config.prefix,
      firstName: config.first_name,
      middleName: config.middle_name,
      lastName: config.last_name,
      suffix: config.suffix,
      honorific: config.honorific,
      rank: config.rank,
      birthdate: config.birthdate,
      yearsInGoodStanding: config.years_in_good_standing,
      status: config.status || 'Active',
      isActive: config.is_active !== undefined ? config.is_active : true,
      goodStanding: config.good_standing !== undefined ? config.good_standing : true,
      email: config.email,
      phone: config.phone,
      cell: config.cell,
      address: config.address || {},
      affiliations: config.affiliations || [],
      lifeCycleEvents: config.life_cycle_events || []
    });
  }
}

module.exports = Member;
