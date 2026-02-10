'use strict';

const express = require('express');
const path = require('path');
const fs = require('fs');
const yaml = require('js-yaml');

const MIP = require('./lib/mip');
const store = MIP.store;

const app = express();

// Middleware
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
app.use(express.static(path.join(__dirname, 'public')));
app.use(express.urlencoded({ extended: true }));

// Custom JSON parser that captures raw body for signature verification
app.use((req, res, next) => {
  if (req.headers['content-type'] && req.headers['content-type'].includes('application/json')) {
    let data = '';
    req.setEncoding('utf8');
    req.on('data', chunk => { data += chunk; });
    req.on('end', () => {
      req.rawBody = data;
      try {
        req.body = data.length > 0 ? JSON.parse(data) : {};
      } catch {
        req.body = {};
      }
      next();
    });
  } else {
    next();
  }
});

// Make store, identity, and helpers available to all views
app.use((req, res, next) => {
  res.locals.store = store.current();
  res.locals.identity = store.current().nodeIdentity;
  res.locals.MIP = MIP;
  res.locals.h = (text) => {
    if (text === null || text === undefined) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };
  res.locals.formatTime = (isoTime) => {
    try {
      const d = new Date(isoTime);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch {
      return isoTime;
    }
  };
  res.locals.req = req;

  // Override render to support layout
  const originalRender = res.render.bind(res);
  res.render = (view, locals, callback) => {
    const allLocals = { ...res.locals, ...locals };
    originalRender(view, allLocals, (err, html) => {
      if (err) {
        if (callback) return callback(err);
        return next(err);
      }
      allLocals.body = html;
      originalRender('layout', allLocals, callback || ((err2, layoutHtml) => {
        if (err2) return next(err2);
        res.send(layoutHtml);
      }));
    });
  };

  next();
});

// Helper to create a MIP client for the current identity
function getClient() {
  return new MIP.Client(store.current().nodeIdentity);
}

// Helper to build MIP response JSON
function mipResponse(succeeded, data = {}) {
  return { meta: { succeeded }, data };
}

// Helper to verify incoming MIP request
function verifyMipRequest(req) {
  const mipId = req.headers['x-mip-mip-identifier'];
  const timestamp = req.headers['x-mip-timestamp'];
  const sig = req.headers['x-mip-signature'];
  const publicKeyHeader = req.headers['x-mip-public-key'];

  if (!mipId || !timestamp || !sig) {
    return { error: { status: 400, body: mipResponse(false, { error: 'Missing MIP headers' }) } };
  }

  if (!MIP.signature.timestampValid(timestamp)) {
    return { error: { status: 400, body: mipResponse(false, { error: 'Invalid timestamp' }) } };
  }

  const s = store.current();
  const connection = s.findConnection(mipId);
  let publicKey;
  if (connection) {
    publicKey = connection.publicKey;
  } else if (publicKeyHeader) {
    publicKey = Buffer.from(publicKeyHeader, 'base64').toString('utf8');
  }

  if (!publicKey) {
    return { error: { status: 401, body: mipResponse(false, { error: 'Unknown sender' }) } };
  }

  // Get the raw body for signature verification
  const rawBody = req.rawBody;
  const bodyForSig = (rawBody && rawBody.length > 0) ? rawBody : null;

  // Use baseUrl + path to get the full path (req.path is relative to the router mount)
  const fullPath = req.baseUrl + req.path;
  if (!MIP.signature.verifyRequest(publicKey, sig, timestamp, fullPath, bodyForSig)) {
    return { error: { status: 401, body: mipResponse(false, { error: 'Invalid signature' }) } };
  }

  return { mipId, connection, publicKey };
}

const appRoutes = express.Router();

// ============================================================================
// Admin Dashboard Routes
// ============================================================================

appRoutes.get('/', (req, res) => {
  res.render('dashboard');
});

// Connections
appRoutes.get('/connections', (req, res) => {
  res.render('connections/index');
});

appRoutes.get('/connections/:mipId', (req, res) => {
  const connection = store.current().findConnection(req.params.mipId);
  if (!connection) return res.status(404).send('Connection not found');
  res.render('connections/show', { connection });
});

// Initiate a new connection
appRoutes.post('/connections', async (req, res) => {
  const targetUrl = (req.body.target_url || '').trim();
  if (!targetUrl) return res.status(400).send('Target URL required');

  const s = store.current();
  const identity = s.nodeIdentity;
  const client = getClient();

  // Collect endorsements from active connections for this request
  const endorsements = s.findEndorsementsFor(identity.mipIdentifier);

  try {
    const result = await client.requestConnection(targetUrl, endorsements);

    if (result.success && result.body.meta && result.body.meta.succeeded) {
      const responseData = (result.body.data && result.body.data.mip_connection) || {};
      const nodeProfile = responseData.node_profile || {};

      const connection = new MIP.models.Connection({
        mipIdentifier: responseData.mip_identifier || nodeProfile.mip_identifier,
        mipUrl: responseData.mip_url || nodeProfile.mip_url || targetUrl,
        publicKey: responseData.public_key || nodeProfile.public_key,
        organizationName: responseData.organization_legal_name || nodeProfile.organization_legal_name || 'Unknown',
        contactPerson: responseData.contact_person || nodeProfile.contact_person,
        contactPhone: responseData.contact_phone || nodeProfile.contact_phone,
        status: responseData.status,
        direction: 'outbound',
        dailyRateLimit: responseData.daily_rate_limit
      });
      s.addConnection(connection);

      // If auto-approved, exchange endorsements
      if (connection.isActive()) {
        sendEndorsementToConnection(connection).catch(() => {});
      }

      res.redirect('/connections');
    } else {
      const errorMsg = (result.body.data && result.body.data.error) || 'Connection request failed';
      res.status(400).send(errorMsg);
    }
  } catch (e) {
    res.status(500).send(`Connection failed: ${e.message}`);
  }
});

// Approve a pending inbound connection
appRoutes.post('/connections/:mipId/approve', async (req, res) => {
  const s = store.current();
  const connection = s.findConnection(req.params.mipId);
  if (!connection) return res.status(404).send('Connection not found');
  if (!connection.isPending()) return res.status(400).send('Connection is not pending');

  connection.approve({ dailyRateLimit: 100 });
  s.logActivity(`Approved connection: ${connection.organizationName}`);

  try {
    const client = getClient();
    await client.approveConnection(
      connection.mipUrl,
      s.nodeIdentity.toNodeProfile(),
      100
    );
    sendEndorsementToConnection(connection).catch(() => {});
  } catch (e) {
    s.logActivity(`Failed to notify approval: ${e.message}`);
  }

  res.redirect('/connections');
});

// Decline a pending inbound connection
appRoutes.post('/connections/:mipId/decline', async (req, res) => {
  const s = store.current();
  const connection = s.findConnection(req.params.mipId);
  if (!connection) return res.status(404).send('Connection not found');
  if (!connection.isPending()) return res.status(400).send('Connection is not pending');

  const reason = req.body.reason;
  connection.decline(reason);
  s.logActivity(`Declined connection: ${connection.organizationName}`);

  try {
    const client = getClient();
    await client.declineConnection(connection.mipUrl, reason);
  } catch (e) {
    s.logActivity(`Failed to notify decline: ${e.message}`);
  }

  res.redirect('/connections');
});

// Revoke an active connection
appRoutes.post('/connections/:mipId/revoke', async (req, res) => {
  const s = store.current();
  const connection = s.findConnection(req.params.mipId);
  if (!connection) return res.status(404).send('Connection not found');
  if (!connection.isActive()) return res.status(400).send('Connection is not active');

  const reason = req.body.reason;
  connection.revoke(reason);
  s.logActivity(`Revoked connection: ${connection.organizationName}`);

  try {
    const client = getClient();
    await client.revokeConnection(connection.mipUrl, reason);
  } catch (e) {
    s.logActivity(`Failed to notify revoke: ${e.message}`);
  }

  res.redirect('/connections');
});

// Restore a revoked connection
appRoutes.post('/connections/:mipId/restore', async (req, res) => {
  const s = store.current();
  const connection = s.findConnection(req.params.mipId);
  if (!connection) return res.status(404).send('Connection not found');
  if (!connection.isRevoked()) return res.status(400).send('Connection is not revoked');

  connection.restore();
  s.logActivity(`Restored connection: ${connection.organizationName}`);

  try {
    const client = getClient();
    await client.restoreConnection(connection.mipUrl);
  } catch (e) {
    s.logActivity(`Failed to notify restore: ${e.message}`);
  }

  res.redirect('/connections');
});

// Members
appRoutes.get('/members', (req, res) => {
  res.render('members/index');
});

// Searches
appRoutes.get('/searches', (req, res) => {
  res.render('searches/index');
});

appRoutes.get('/searches/new', (req, res) => {
  res.render('searches/new');
});

// Initiate a search
appRoutes.post('/searches', async (req, res) => {
  const s = store.current();
  const targetMipId = req.body.target_mip_id;
  const connection = s.findConnection(targetMipId);
  if (!connection || !connection.isActive()) return res.status(400).send('Invalid connection');

  const searchParams = {};
  if (req.body.member_number && req.body.member_number.trim()) searchParams.member_number = req.body.member_number.trim();
  if (req.body.first_name && req.body.first_name.trim()) searchParams.first_name = req.body.first_name.trim();
  if (req.body.last_name && req.body.last_name.trim()) searchParams.last_name = req.body.last_name.trim();
  if (req.body.birthdate && req.body.birthdate.trim()) searchParams.birthdate = req.body.birthdate.trim();

  if (Object.keys(searchParams).length === 0) return res.status(400).send('Search criteria required');

  const searchRequest = new MIP.models.SearchRequest({
    direction: 'outbound',
    targetMipIdentifier: connection.mipIdentifier,
    targetOrg: connection.organizationName,
    searchParams,
    notes: req.body.notes
  });
  s.addSearchRequest(searchRequest);

  try {
    const client = getClient();
    const result = await client.memberSearch(connection.mipUrl, searchRequest);
    if (result.success) {
      s.logActivity(`Search sent to ${connection.organizationName}`);
    }
  } catch (e) {
    s.logActivity(`Search failed: ${e.message}`);
  }

  res.redirect('/searches');
});

// Approve an inbound search
appRoutes.post('/searches/:id/approve', async (req, res) => {
  const s = store.current();
  const search = s.findSearchRequest(req.params.id);
  if (!search) return res.status(404).send('Search not found');
  if (!search.isPending()) return res.status(400).send('Search is not pending');

  const matches = s.searchMembers(search.searchParams).map(m => m.toSearchResult());
  search.approve(matches);
  s.logActivity(`Approved search from ${search.targetOrg}: ${matches.length} matches`);

  const connection = s.findConnection(search.targetMipIdentifier);
  if (connection && connection.isActive()) {
    try {
      const client = getClient();
      await client.memberSearchReply(connection.mipUrl, search);
    } catch (e) {
      s.logActivity(`Failed to send search reply: ${e.message}`);
    }
  }

  res.redirect('/searches');
});

// Decline an inbound search
appRoutes.post('/searches/:id/decline', async (req, res) => {
  const s = store.current();
  const search = s.findSearchRequest(req.params.id);
  if (!search) return res.status(404).send('Search not found');
  if (!search.isPending()) return res.status(400).send('Search is not pending');

  search.decline(req.body.reason);
  s.logActivity(`Declined search from ${search.targetOrg}`);

  const connection = s.findConnection(search.targetMipIdentifier);
  if (connection && connection.isActive()) {
    try {
      const client = getClient();
      await client.memberSearchReply(connection.mipUrl, search);
    } catch (e) {
      s.logActivity(`Failed to send search reply: ${e.message}`);
    }
  }

  res.redirect('/searches');
});

// COGS
appRoutes.get('/cogs', (req, res) => {
  res.render('cogs/index');
});

appRoutes.get('/cogs/new', (req, res) => {
  res.render('cogs/new');
});

// Request a COGS
appRoutes.post('/cogs', async (req, res) => {
  const s = store.current();
  const targetMipId = req.body.target_mip_id;
  const connection = s.findConnection(targetMipId);
  if (!connection || !connection.isActive()) return res.status(400).send('Invalid connection');

  const cogs = new MIP.models.CogsRequest({
    direction: 'outbound',
    targetMipIdentifier: connection.mipIdentifier,
    targetOrg: connection.organizationName,
    requestingMember: {
      member_number: req.body.requesting_member_number,
      first_name: req.body.requesting_first_name,
      last_name: req.body.requesting_last_name
    },
    requestedMemberNumber: req.body.requested_member_number,
    notes: req.body.notes
  });
  s.addCogsRequest(cogs);

  try {
    const client = getClient();
    const result = await client.requestCogs(connection.mipUrl, cogs);
    if (result.success) {
      s.logActivity(`COGS requested from ${connection.organizationName}`);
    }
  } catch (e) {
    s.logActivity(`COGS request failed: ${e.message}`);
  }

  res.redirect('/cogs');
});

// Approve an inbound COGS
appRoutes.post('/cogs/:id/approve', async (req, res) => {
  const s = store.current();
  const cogs = s.findCogsRequest(req.params.id);
  if (!cogs) return res.status(404).send('COGS not found');
  if (!cogs.isPending()) return res.status(400).send('COGS is not pending');

  const member = s.findMember(cogs.requestedMemberNumber);
  if (!member) return res.status(400).send('Member not found');

  const issuingOrg = {
    mip_identifier: s.nodeIdentity.mipIdentifier,
    organization_legal_name: s.nodeIdentity.organizationName
  };
  cogs.approve(member, issuingOrg);
  s.logActivity(`Approved COGS for ${member.memberNumber}`);

  const connection = s.findConnection(cogs.targetMipIdentifier);
  if (connection && connection.isActive()) {
    try {
      const client = getClient();
      await client.cogsReply(connection.mipUrl, cogs);
    } catch (e) {
      s.logActivity(`Failed to send COGS reply: ${e.message}`);
    }
  }

  res.redirect('/cogs');
});

// Decline an inbound COGS
appRoutes.post('/cogs/:id/decline', async (req, res) => {
  const s = store.current();
  const cogs = s.findCogsRequest(req.params.id);
  if (!cogs) return res.status(404).send('COGS not found');
  if (!cogs.isPending()) return res.status(400).send('COGS is not pending');

  cogs.decline(req.body.reason || 'Request declined');
  s.logActivity('Declined COGS request');

  const connection = s.findConnection(cogs.targetMipIdentifier);
  if (connection && connection.isActive()) {
    try {
      const client = getClient();
      await client.cogsReply(connection.mipUrl, cogs);
    } catch (e) {
      s.logActivity(`Failed to send COGS reply: ${e.message}`);
    }
  }

  res.redirect('/cogs');
});

// ============================================================================
// MIP Protocol Endpoints
// ============================================================================

const mipRouter = express.Router();

// Connection request
mipRouter.post('/node/:mipId/mip_connections', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  const s = store.current();
  const body = req.body;

  // Check if connection already exists
  const existing = s.findConnection(body.mip_identifier);
  if (existing) {
    return res.json(mipResponse(true, {
      mip_connection: {
        mip_identifier: s.nodeIdentity.mipIdentifier,
        organization_legal_name: s.nodeIdentity.organizationName,
        contact_person: s.nodeIdentity.contactPerson,
        contact_phone: s.nodeIdentity.contactPhone,
        mip_url: s.nodeIdentity.mipUrl,
        public_key: s.nodeIdentity.publicKey,
        status: existing.status,
        daily_rate_limit: existing.dailyRateLimit
      }
    }));
  }

  // Create new connection from request
  const connection = MIP.models.Connection.fromRequest(body, 'inbound');

  // Check for auto-approval via web-of-trust
  const endorsements = body.endorsements || [];
  const trustedCount = countTrustedEndorsements(endorsements, connection.publicKey);

  if (trustedCount >= s.nodeIdentity.trustThreshold) {
    connection.approve({ dailyRateLimit: 100 });
    s.addConnection(connection);
    s.logActivity(`Auto-approved connection: ${connection.organizationName} (${trustedCount} trusted endorsements)`);

    // Send endorsement to new connection (async, don't block response)
    sendEndorsementToConnection(connection).catch(() => {});

    res.json(mipResponse(true, {
      mip_connection: {
        mip_identifier: s.nodeIdentity.mipIdentifier,
        organization_legal_name: s.nodeIdentity.organizationName,
        contact_person: s.nodeIdentity.contactPerson,
        contact_phone: s.nodeIdentity.contactPhone,
        mip_url: s.nodeIdentity.mipUrl,
        public_key: s.nodeIdentity.publicKey,
        status: 'ACTIVE',
        daily_rate_limit: 100
      }
    }));
  } else {
    s.addConnection(connection);
    s.logActivity(`Connection request from: ${connection.organizationName}`);

    res.json(mipResponse(true, {
      mip_connection: {
        mip_identifier: s.nodeIdentity.mipIdentifier,
        organization_legal_name: s.nodeIdentity.organizationName,
        contact_person: s.nodeIdentity.contactPerson,
        contact_phone: s.nodeIdentity.contactPhone,
        mip_url: s.nodeIdentity.mipUrl,
        public_key: s.nodeIdentity.publicKey,
        status: 'PENDING',
        daily_rate_limit: 100
      }
    }));
  }
});

// Connection approved notification
mipRouter.post('/node/:mipId/mip_connections/approved', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  const s = store.current();
  const connection = s.findConnection(sender.mipId);
  if (!connection) return res.status(404).json(mipResponse(false, { error: 'Connection not found' }));

  const railsStyleProfile = {
    organization_legal_name: req.body.organization_legal_name,
    contact_person: req.body.contact_person,
    contact_phone: req.body.contact_phone,
    mip_url: req.body.mip_url,
    public_key: req.body.public_key
  };
  const nodeProfile = req.body.node_profile || railsStyleProfile;
  connection.approve({
    nodeProfile,
    dailyRateLimit: req.body.daily_rate_limit || 100
  });
  s.logActivity(`Connection approved by: ${connection.organizationName}`);

  // Send endorsement to the approving node
  sendEndorsementToConnection(connection).catch(() => {});

  res.json(mipResponse(true, { mip_connection: { status: 'ACTIVE' } }));
});

// Connection declined notification
mipRouter.post('/node/:mipId/mip_connections/declined', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  const s = store.current();
  const connection = s.findConnection(sender.mipId);
  if (!connection) return res.status(404).json(mipResponse(false, { error: 'Connection not found' }));

  connection.decline(req.body.reason);
  s.logActivity(`Connection declined by: ${connection.organizationName}`);

  res.json(mipResponse(true, { mip_connection: { status: 'DECLINED' } }));
});

// Connection revoke notification
mipRouter.post('/node/:mipId/mip_connections/revoke', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  const s = store.current();
  const connection = s.findConnection(sender.mipId);
  if (!connection) return res.status(404).json(mipResponse(false, { error: 'Connection not found' }));

  connection.revoke(req.body.reason);
  s.logActivity(`Connection revoked by: ${connection.organizationName}`);

  res.json(mipResponse(true, { mip_connection: { status: 'REVOKED' } }));
});

// Connection restore notification
mipRouter.post('/node/:mipId/mip_connections/restore', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  const s = store.current();
  const connection = s.findConnection(sender.mipId);
  if (!connection) return res.status(404).json(mipResponse(false, { error: 'Connection not found' }));

  connection.restore();
  s.logActivity(`Connection restored by: ${connection.organizationName}`);

  res.json(mipResponse(true, { mip_connection: { status: 'ACTIVE' } }));
});

// Receive endorsement
mipRouter.post('/node/:mipId/endorsements', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  if (!sender.connection || !sender.connection.isActive()) {
    return res.status(403).json(mipResponse(false, { error: 'No active connection' }));
  }

  const endorsement = MIP.models.Endorsement.fromPayload(req.body);

  // Verify the endorsement signature
  if (!endorsement.verifySignature(sender.connection.publicKey)) {
    return res.status(400).json(mipResponse(false, { error: 'Invalid endorsement signature' }));
  }

  const s = store.current();
  s.addEndorsement(endorsement);

  // Check if this endorsement enables any pending connections to be auto-approved
  checkPendingConnectionsForAutoApproval();

  res.json(mipResponse(true, { endorsement_id: endorsement.id }));
});

// Query connected organizations
mipRouter.get('/node/:mipId/connected_organizations_query', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  if (!sender.connection || !sender.connection.isActive()) {
    return res.status(403).json(mipResponse(false, { error: 'No active connection' }));
  }

  const s = store.current();
  const shareableOrgs = s.activeConnections()
    .filter(c => c.shareMyOrganization && c.mipIdentifier !== sender.mipId)
    .map(c => c.toNodeProfile());

  res.json(mipResponse(true, { organizations: shareableOrgs }));
});

// Member search request
mipRouter.post('/node/:mipId/mip_member_searches', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  if (!sender.connection || !sender.connection.isActive()) {
    return res.status(403).json(mipResponse(false, { error: 'No active connection' }));
  }

  const s = store.current();
  const search = MIP.models.SearchRequest.fromRequest(
    req.body,
    sender.mipId,
    sender.connection.organizationName
  );
  s.addSearchRequest(search);

  res.json(mipResponse(true, {
    status: 'PENDING',
    shared_identifier: search.sharedIdentifier
  }));
});

// Member search reply
mipRouter.post('/node/:mipId/mip_member_searches/reply', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  if (!sender.connection || !sender.connection.isActive()) {
    return res.status(403).json(mipResponse(false, { error: 'No active connection' }));
  }

  const s = store.current();
  const data = req.body.data || req.body;
  const sharedId = data.shared_identifier;
  const search = s.findSearchRequest(sharedId);

  if (search) {
    if (data.status === 'APPROVED') {
      search.approve(data.matches || []);
      s.logActivity(`Search results received: ${search.matches.length} matches`);
    } else {
      search.decline(data.reason);
      s.logActivity('Search declined');
    }
  }

  res.json(mipResponse(true, { acknowledged: true }));
});

// Member status check (real-time)
mipRouter.post('/node/:mipId/member_status_checks', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  if (!sender.connection || !sender.connection.isActive()) {
    return res.status(403).json(mipResponse(false, { error: 'No active connection' }));
  }

  const s = store.current();
  const memberNumber = req.body.member_number;
  const member = s.findMember(memberNumber);

  if (member) {
    res.json(mipResponse(true, member.toStatusCheck()));
  } else {
    res.json(mipResponse(true, { found: false, member_number: memberNumber }));
  }
});

// COGS request
mipRouter.post('/node/:mipId/certificates_of_good_standing', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  if (!sender.connection || !sender.connection.isActive()) {
    return res.status(403).json(mipResponse(false, { error: 'No active connection' }));
  }

  const s = store.current();
  const cogs = MIP.models.CogsRequest.fromRequest(
    req.body,
    sender.mipId,
    sender.connection.organizationName
  );
  s.addCogsRequest(cogs);

  res.json(mipResponse(true, {
    status: 'PENDING',
    shared_identifier: cogs.sharedIdentifier
  }));
});

// COGS reply
mipRouter.post('/node/:mipId/certificates_of_good_standing/reply', (req, res) => {
  const sender = verifyMipRequest(req);
  if (sender.error) return res.status(sender.error.status).json(sender.error.body);

  if (!sender.connection || !sender.connection.isActive()) {
    return res.status(403).json(mipResponse(false, { error: 'No active connection' }));
  }

  const s = store.current();
  const payload = req.body && req.body.data ? req.body.data : req.body;
  const sharedId = payload && payload.shared_identifier;
  const cogs = s.findCogsRequest(sharedId);

  if (cogs) {
    if (payload.status === 'APPROVED') {
      cogs.status = 'APPROVED';
      cogs.certificate = payload.certificate || payload;
      const certificate = payload.certificate || {};
      const memberNum = certificate.member_profile && certificate.member_profile.member_number;
      s.logActivity(`COGS received for ${memberNum || 'unknown'}`);
    } else {
      cogs.status = 'DECLINED';
      cogs.declineReason = payload.reason;
      s.logActivity(`COGS declined: ${payload.reason}`);
    }
  }

  res.json(mipResponse(true, {
    acknowledged: true,
    shared_identifier: sharedId
  }));
});

// ============================================================================
// Private helpers
// ============================================================================

async function sendEndorsementToConnection(connection) {
  const s = store.current();
  const identity = s.nodeIdentity;
  const endorsement = MIP.models.Endorsement.create(
    identity,
    connection.mipIdentifier,
    connection.publicKey
  );

  try {
    const client = getClient();
    await client.sendEndorsement(connection.mipUrl, endorsement);
    s.logActivity(`Sent endorsement to ${connection.organizationName}`);
  } catch (e) {
    s.logActivity(`Failed to send endorsement: ${e.message}`);
  }
}

function countTrustedEndorsements(endorsements, endorsedPublicKey) {
  const s = store.current();
  const fp = MIP.crypto.fingerprint(endorsedPublicKey);
  let count = 0;

  for (const endorsementData of endorsements) {
    const endorserId = endorsementData.endorser_mip_identifier;
    const connection = s.findConnection(endorserId);
    if (!connection || !connection.isActive()) continue;

    const endorsement = MIP.models.Endorsement.fromPayload(endorsementData);
    if (!endorsement.validFor(fp)) continue;
    if (!endorsement.verifySignature(connection.publicKey)) continue;

    count++;
  }

  return count;
}

function checkPendingConnectionsForAutoApproval() {
  const s = store.current();
  const identity = s.nodeIdentity;

  for (const connection of s.pendingConnections()) {
    const endorsements = s.findEndorsementsFor(connection.mipIdentifier);

    let trustedCount = 0;
    for (const e of endorsements) {
      const endorserConnection = s.findConnection(e.endorserMipIdentifier);
      if (!endorserConnection || !endorserConnection.isActive()) continue;
      if (!e.validFor(connection.publicKeyFingerprint())) continue;
      if (!e.verifySignature(endorserConnection.publicKey)) continue;
      trustedCount++;
    }

    if (trustedCount >= identity.trustThreshold) {
      connection.approve({ dailyRateLimit: 100 });
      s.logActivity(`Auto-approved pending connection: ${connection.organizationName}`);

      // Notify and exchange endorsements (async)
      const client = getClient();
      (async () => {
        try {
          await client.approveConnection(
            connection.mipUrl,
            identity.toNodeProfile(),
            100
          );
          await sendEndorsementToConnection(connection);
        } catch (e) {
          s.logActivity(`Failed to notify auto-approval: ${e.message}`);
        }
      })();
    }
  }
}

// Mount routes
app.use('/', appRoutes);
app.use('/mip', mipRouter);

// ============================================================================
// Initialize and Start
// ============================================================================

function initializeNode() {
  const configFile = process.env.CONFIG || 'config/node1.yml';
  const configPath = path.resolve(__dirname, configFile);
  const config = yaml.load(fs.readFileSync(configPath, 'utf8'));
  const port = config.port;

  // Reset store and create new identity
  const s = store.reset();

  const identity = MIP.models.NodeIdentity.fromConfig(config, port);
  s.setNodeIdentity(identity);

  // Load members from config
  const members = config.members || [];
  for (const memberConfig of members) {
    const member = MIP.models.Member.fromConfig(memberConfig);
    s.addMember(member);
  }

  s.logActivity(`Node initialized: ${identity.organizationName}`);

  console.log('='.repeat(60));
  console.log(`MIP Node Started: ${identity.organizationName}`);
  console.log(`MIP Identifier: ${identity.mipIdentifier}`);
  console.log(`MIP URL: ${identity.mipUrl}`);
  console.log(`Public Key Fingerprint: ${identity.publicKeyFingerprint()}`);
  console.log(`Members loaded: ${s.allMembers().length}`);
  console.log('='.repeat(60));

  return port;
}

const port = initializeNode();

app.listen(port, '0.0.0.0', () => {
  console.log(`Server listening on http://localhost:${port}`);
});
