'use strict';

const http = require('http');
const https = require('https');
const { URL } = require('url');
const signature = require('./signature');

// HTTP client for making outbound MIP requests
class Client {
  constructor(nodeIdentity) {
    this.identity = nodeIdentity;
  }

  // Request a connection with another node
  async requestConnection(targetUrl, endorsements = []) {
    const payload = {
      mip_identifier: this.identity.mipIdentifier,
      mip_url: this.identity.mipUrl,
      public_key: this.identity.publicKey,
      organization_legal_name: this.identity.organizationName,
      contact_person: this.identity.contactPerson,
      contact_phone: this.identity.contactPhone,
      share_my_organization: this.identity.shareMyOrganization,
      endorsements: endorsements.map(e => e.toPayload())
    };

    return this._postRequest(
      this._buildUrl(targetUrl, '/mip_connections'),
      payload,
      true
    );
  }

  // Notify a node their connection request was approved
  async approveConnection(targetUrl, nodeProfile, dailyRateLimit = 100) {
    const payload = {
      node_profile: nodeProfile,
      share_my_organization: this.identity.shareMyOrganization,
      daily_rate_limit: dailyRateLimit
    };

    return this._postRequest(
      this._buildUrl(targetUrl, '/mip_connections/approved'),
      payload
    );
  }

  // Notify a node their connection request was declined
  async declineConnection(targetUrl, reason) {
    const payload = {
      mip_identifier: this.identity.mipIdentifier,
      reason
    };

    return this._postRequest(
      this._buildUrl(targetUrl, '/mip_connections/declined'),
      payload
    );
  }

  // Notify a node their connection has been revoked
  async revokeConnection(targetUrl, reason) {
    const payload = {
      mip_identifier: this.identity.mipIdentifier,
      reason
    };

    return this._postRequest(
      this._buildUrl(targetUrl, '/mip_connections/revoke'),
      payload
    );
  }

  // Notify a node their connection has been restored
  async restoreConnection(targetUrl) {
    const payload = {
      mip_identifier: this.identity.mipIdentifier
    };

    return this._postRequest(
      this._buildUrl(targetUrl, '/mip_connections/restore'),
      payload
    );
  }

  // Send an endorsement to another node
  async sendEndorsement(targetUrl, endorsement) {
    return this._postRequest(
      this._buildUrl(targetUrl, '/endorsements'),
      endorsement.toPayload()
    );
  }

  // Send a member search request
  async memberSearch(targetUrl, searchRequest) {
    return this._postRequest(
      this._buildUrl(targetUrl, '/mip_member_searches'),
      searchRequest.toRequestPayload()
    );
  }

  // Send member search results back to requester
  async memberSearchReply(targetUrl, searchRequest) {
    const payload = {
      meta: { succeeded: true },
      data: searchRequest.toReplyPayload()
    };

    return this._postRequest(
      this._buildUrl(targetUrl, '/mip_member_searches/reply'),
      payload
    );
  }

  // Request a Certificate of Good Standing
  async requestCogs(targetUrl, cogsRequest) {
    return this._postRequest(
      this._buildUrl(targetUrl, '/certificates_of_good_standing'),
      cogsRequest.toRequestPayload()
    );
  }

  // Send COGS reply back to requester
  async cogsReply(targetUrl, cogsRequest) {
    return this._postRequest(
      this._buildUrl(targetUrl, '/certificates_of_good_standing/reply'),
      cogsRequest.toReplyPayload()
    );
  }

  // Query connected organizations
  async connectedOrganizationsQuery(targetUrl) {
    return this._getRequest(
      this._buildUrl(targetUrl, '/connected_organizations_query')
    );
  }

  _buildUrl(baseUrl, endpoint) {
    return baseUrl.replace(/\/$/, '') + endpoint;
  }

  _extractPath(url) {
    return new URL(url).pathname;
  }

  async _postRequest(url, payload, includePublicKey = false) {
    const timestamp = new Date().toISOString();
    const path = this._extractPath(url);
    const jsonBody = JSON.stringify(payload);
    const sig = signature.signRequest(this.identity.privateKey, timestamp, path, jsonBody);

    const headers = {
      'Content-Type': 'application/json',
      'X-MIP-MIP-IDENTIFIER': this.identity.mipIdentifier,
      'X-MIP-TIMESTAMP': timestamp,
      'X-MIP-SIGNATURE': sig
    };
    if (includePublicKey) {
      headers['X-MIP-PUBLIC-KEY'] = Buffer.from(this.identity.publicKey).toString('base64');
    }

    return this._request(url, 'POST', headers, jsonBody);
  }

  async _getRequest(url) {
    const timestamp = new Date().toISOString();
    const path = this._extractPath(url);
    const sig = signature.signRequest(this.identity.privateKey, timestamp, path);

    const headers = {
      'X-MIP-MIP-IDENTIFIER': this.identity.mipIdentifier,
      'X-MIP-TIMESTAMP': timestamp,
      'X-MIP-SIGNATURE': sig
    };

    return this._request(url, 'GET', headers);
  }

  _request(url, method, headers, body) {
    return new Promise((resolve, reject) => {
      const parsed = new URL(url);
      const transport = parsed.protocol === 'https:' ? https : http;

      const options = {
        hostname: parsed.hostname,
        port: parsed.port,
        path: parsed.pathname + parsed.search,
        method,
        headers,
        timeout: 30000
      };

      const req = transport.request(options, (res) => {
        let data = '';
        res.on('data', chunk => { data += chunk; });
        res.on('end', () => {
          try {
            const parsedBody = data.length > 0 ? JSON.parse(data) : {};
            resolve({
              success: res.statusCode >= 200 && res.statusCode < 300,
              status: res.statusCode,
              body: parsedBody
            });
          } catch {
            resolve({
              success: false,
              status: res.statusCode,
              body: { error: 'Invalid JSON response' }
            });
          }
        });
      });

      req.on('error', reject);
      req.on('timeout', () => {
        req.destroy();
        reject(new Error('Request timeout'));
      });

      if (body) {
        req.write(body);
      }
      req.end();
    });
  }
}

module.exports = Client;
