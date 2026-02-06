# Member Interchange Protocol Ruby Gem

A Ruby implementation of the Member Interchange Protocol (MIP) for secure inter-organizational member verification.

## Installation

Add this line to your application's Gemfile:

```ruby
gem 'member_interchange_protocol'
```

And then execute:

```bash
bundle install
```

Or install it yourself as:

```bash
gem install member_interchange_protocol
```

## Usage

```ruby
require 'member_interchange_protocol'

# The MIP alias is available for convenience
# MemberInterchangeProtocol and MIP are interchangeable
```

### Creating a Node Identity

```ruby
# Generate a new node identity with fresh keys
identity = MemberInterchangeProtocol::Models::NodeIdentity.generate(
  organization_name: 'Grand Lodge of Example',
  contact_person: 'John Smith',
  contact_phone: '+1-555-0001',
  mip_url: 'https://example.org/mip/node/abc123',
  share_my_organization: true,
  trust_threshold: 1
)

puts identity.mip_identifier
puts identity.public_key_fingerprint
```

### Using the Client

```ruby
# Create a client for making outbound requests
client = MemberInterchangeProtocol::Client.new(identity)

# Request a connection with another node
response = client.request_connection(
  'https://other-node.org/mip/node/xyz789',
  endorsements: []
)

# Perform a member search
search = MemberInterchangeProtocol::Models::SearchRequest.new(
  direction: 'outbound',
  target_mip_identifier: 'xyz789',
  target_org: 'Other Grand Lodge',
  search_params: {
    first_name: 'John',
    last_name: 'Doe',
    birthdate: '1980-01-15'
  }
)

response = client.member_search(
  'https://other-node.org/mip/node/xyz789',
  search
)
```

### Cryptographic Operations

```ruby
# Generate a key pair
keys = MemberInterchangeProtocol::Crypto.generate_key_pair
# => { private_key: "-----BEGIN RSA...", public_key: "-----BEGIN PUBLIC..." }

# Calculate fingerprint
fingerprint = MemberInterchangeProtocol::Crypto.fingerprint(keys[:public_key])
# => "a1:b2:c3:d4:..."

# Sign and verify data
signature = MemberInterchangeProtocol::Crypto.sign(keys[:private_key], "data to sign")
valid = MemberInterchangeProtocol::Crypto.verify(keys[:public_key], signature, "data to sign")
# => true
```

### Request Signatures

```ruby
# Sign a request
timestamp = Time.now.iso8601
path = '/mip/node/abc123/mip_member_searches'
json_body = { first_name: 'John' }.to_json

signature = MemberInterchangeProtocol::Signature.sign_request(
  private_key,
  timestamp,
  path,
  json_body
)

# Verify a request
valid = MemberInterchangeProtocol::Signature.verify_request(
  public_key,
  signature,
  timestamp,
  path,
  json_body
)

# Check timestamp validity (within 5 minute window)
MemberInterchangeProtocol::Signature.timestamp_valid?(timestamp)
```

### Creating Endorsements

```ruby
# Create an endorsement for another node
endorsement = MemberInterchangeProtocol::Models::Endorsement.create(
  identity,                    # Your node identity (endorser)
  'other-node-mip-id',         # The endorsed node's MIP identifier
  other_node_public_key        # The endorsed node's public key
)

# Send the endorsement
client.send_endorsement(other_node_mip_url, endorsement)

# Verify a received endorsement
received_endorsement = MemberInterchangeProtocol::Models::Endorsement.from_payload(payload)
valid = received_endorsement.verify_signature(endorser_public_key)
```

### Certificate of Good Standing (COGS)

```ruby
# Create a COGS request
cogs_request = MemberInterchangeProtocol::Models::CogsRequest.new(
  direction: 'outbound',
  target_mip_identifier: 'other-node-id',
  target_org: 'Other Grand Lodge',
  requesting_member: {
    member_number: 'M-12345',
    first_name: 'John',
    last_name: 'Smith'
  },
  requested_member_number: 'OGL-67890'
)

# Send the request
response = client.request_cogs(target_url, cogs_request)

# Approve a COGS request (as the receiving node)
cogs_request.approve!(member, identity.organization_name)
client.cogs_reply(requester_url, cogs_request)
```

## Models

### NodeIdentity
Represents this node's identity in the MIP network, including keys and organization info.

### Connection
Represents a connection with another MIP node. Supports statuses: PENDING, ACTIVE, DECLINED, REVOKED.

### Member
Represents a member in the local organization with contact info, affiliations, and status.

### Endorsement
Represents an endorsement in the web-of-trust system for automatic connection approval.

### SearchRequest
Tracks member search requests (both inbound and outbound).

### CogsRequest
Tracks Certificate of Good Standing requests (both inbound and outbound).

## Development

After checking out the repo, run `bundle install` to install dependencies.

Run the tests:

```bash
bundle exec rake test
```

Run the linter:

```bash
bundle exec rubocop
```

## Contributing

Bug reports and pull requests are welcome on GitHub.

## License

The gem is available as open source under the terms of the [MIT License](LICENSE.txt).
