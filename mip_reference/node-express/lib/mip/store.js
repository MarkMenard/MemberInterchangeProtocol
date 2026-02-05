'use strict';

// In-memory data store for MIP node data
// Uses a simple singleton pattern for this reference implementation
class Store {
  constructor() {
    this.nodeIdentity = null;
    this.connections = new Map();       // mip_identifier => Connection
    this.members = new Map();           // member_number => Member
    this.endorsements = new Map();      // endorsement_id => Endorsement
    this.searchRequests = new Map();    // shared_identifier => SearchRequest
    this.cogsRequests = new Map();      // shared_identifier => CogsRequest
    this.activityLog = [];              // Array of activity entries
  }

  setNodeIdentity(identity) {
    this.nodeIdentity = identity;
  }

  // Connection management
  addConnection(connection) {
    this.connections.set(connection.mipIdentifier, connection);
    this.logActivity(`Connection added: ${connection.organizationName} (${connection.status})`);
  }

  findConnection(mipIdentifier) {
    return this.connections.get(mipIdentifier) || null;
  }

  activeConnections() {
    return [...this.connections.values()].filter(c => c.isActive());
  }

  pendingConnections() {
    return [...this.connections.values()].filter(c => c.isPending());
  }

  allConnections() {
    return [...this.connections.values()];
  }

  // Member management
  addMember(member) {
    this.members.set(member.memberNumber, member);
  }

  findMember(memberNumber) {
    return this.members.get(memberNumber) || null;
  }

  searchMembers(query) {
    return [...this.members.values()].filter(m => {
      if (query.member_number) {
        return m.memberNumber.toLowerCase().includes(query.member_number.toLowerCase());
      } else if (query.first_name && query.last_name) {
        const nameMatch = m.firstName.toLowerCase().includes(query.first_name.toLowerCase()) &&
                          m.lastName.toLowerCase().includes(query.last_name.toLowerCase());
        if (query.birthdate) {
          return nameMatch && m.birthdate === query.birthdate;
        }
        return nameMatch;
      }
      return false;
    });
  }

  allMembers() {
    return [...this.members.values()];
  }

  // Endorsement management
  addEndorsement(endorsement) {
    this.endorsements.set(endorsement.id, endorsement);
    this.logActivity(`Endorsement received from ${endorsement.endorserMipIdentifier}`);
  }

  findEndorsementsFor(mipIdentifier) {
    return [...this.endorsements.values()].filter(e => e.endorsedMipIdentifier === mipIdentifier);
  }

  findEndorsementsFrom(mipIdentifier) {
    return [...this.endorsements.values()].filter(e => e.endorserMipIdentifier === mipIdentifier);
  }

  // Search request management
  addSearchRequest(searchRequest) {
    this.searchRequests.set(searchRequest.sharedIdentifier, searchRequest);
    this.logActivity(`Search request: ${searchRequest.direction} - ${searchRequest.targetOrg}`);
  }

  findSearchRequest(sharedIdentifier) {
    return this.searchRequests.get(sharedIdentifier) || null;
  }

  allSearchRequests() {
    return [...this.searchRequests.values()];
  }

  // COGS request management
  addCogsRequest(cogsRequest) {
    this.cogsRequests.set(cogsRequest.sharedIdentifier, cogsRequest);
    this.logActivity(`COGS request: ${cogsRequest.direction} - ${cogsRequest.targetOrg}`);
  }

  findCogsRequest(sharedIdentifier) {
    return this.cogsRequests.get(sharedIdentifier) || null;
  }

  allCogsRequests() {
    return [...this.cogsRequests.values()];
  }

  // Activity log
  logActivity(message) {
    this.activityLog.unshift({
      timestamp: new Date().toISOString(),
      message
    });
    // Keep only last 100 entries
    if (this.activityLog.length > 100) {
      this.activityLog = this.activityLog.slice(0, 100);
    }
  }

  recentActivity(count = 20) {
    return this.activityLog.slice(0, count);
  }
}

// Singleton
let instance = null;

function current() {
  if (!instance) instance = new Store();
  return instance;
}

function reset() {
  instance = new Store();
  return instance;
}

module.exports = { Store, current, reset };
