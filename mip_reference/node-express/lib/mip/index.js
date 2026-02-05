'use strict';

const identifier = require('./identifier');
const crypto = require('./crypto');
const signature = require('./signature');
const store = require('./store');
const Client = require('./client');
const NodeIdentity = require('./models/node-identity');
const Connection = require('./models/connection');
const Member = require('./models/member');
const Endorsement = require('./models/endorsement');
const SearchRequest = require('./models/search-request');
const CogsRequest = require('./models/cogs-request');

const VERSION = '1.0.0';

module.exports = {
  VERSION,
  identifier,
  crypto,
  signature,
  store,
  Client,
  models: {
    NodeIdentity,
    Connection,
    Member,
    Endorsement,
    SearchRequest,
    CogsRequest
  }
};
