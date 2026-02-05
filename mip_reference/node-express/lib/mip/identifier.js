'use strict';

const crypto = require('crypto');

// Generate a MIP identifier using MD5(UUID + organization_name)
// Per MIP spec: "combining a 128 bit randomly generated number concatenated
// with a salt based using something unique to the organization"
function generate(organizationName) {
  const uuid = crypto.randomUUID();
  return crypto.createHash('md5').update(`${uuid}${organizationName}`).digest('hex');
}

module.exports = { generate };
