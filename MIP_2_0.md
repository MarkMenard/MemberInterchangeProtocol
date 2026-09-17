# Member Interchange Protocol 2.0

**Lead author:** Mark Menard (Groupable)
**Date:** September 14, 2026
**Status:** Current specification. Supersedes MIP 1.0. The differences from 1.0, with the
reason for each, are listed in `CHANGES.md`.

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD", "SHOULD NOT",
"RECOMMENDED", "MAY", and "OPTIONAL" in this document are to be interpreted as described in
RFC 2119.

## Design Principles

MIP is a point-to-point protocol between independent member management systems. There is no
central clearing house, no shared database, and no state that two nodes hold in common beyond
what they have told each other directly. Every node identifies itself, signs its own requests,
and decides for itself which other nodes to trust. A link between a person's records in two
organizations exists only in those two systems; no third party stores it. The protocol
defines how nodes exchange information, not what an organization does with it once received.

## Terminology

- **Node**: one organization's MIP endpoint. A vendor hosting several organizations operates
  one node per organization.
- **Connection**: the relationship between two nodes that have exchanged identities and keys.
  A connection has a status: `PENDING`, `ACTIVE`, `DECLINED`, or `REVOKED`.
- **Requester** and **responder**: the node that initiates an exchange and the node that
  answers it. The terms describe roles within one exchange; the roles swap in the next.
- **Sender** and **receiver**: the node that makes one HTTP request and the node that
  handles it.
- **Endorser** and **endorsed node**: the node that signs an endorsement and the node whose
  identity it vouches for.
- **Known node**: a node the receiver has heard of through discovery but is not connected to.

## Functions Overview

### Connection Protocol

- **Connection Request**: request a connection between two nodes.
- **Connection Approved**: notify a requester that its connection request has been approved.
- **Connection Declined**: notify a requester that its connection request has been declined.
- **Connection Revoked**: notify a node that the sender has revoked the connection, whatever
  its state, and will honor no further requests. A revoked connection comes back through a
  new Connection Request.
- **Organization Update**: push updated organization information to a connected node and
  receive its current information in return.
- **Shared Nodes**: tell a connected node about other nodes, with any endorsements held for
  them. This is the single discovery mechanism: it delivers the approver's known nodes after
  an approval, announces a newly approved node to other connections, and answers a share
  request.
- **Share Request**: ask a connected node to send its known nodes.
- **Endorsement**: send a cryptographic endorsement of a node's identity to build the web of
  trust.

### Member Protocol

- **Member Search Request**: search for a member in a connected system by member number, or
  by first name and last name, with birthdate when known.
- **Member Search Reply**: deliver the result of a member search. A reply carries the matching
  members with their status in the responding organization, or a decline.
- **Certificate of Good Standing Request**: request a certificate of good standing for a
  person from a connected organization.
- **Certificate of Good Standing Reply**: deliver the certificate, or a decline, to the
  requester.

Requests in the member protocol are queued on the responding node and answered by a separate
reply request. The responder MAY answer automatically or after a person reviews the request;
the protocol is the same either way.

## Node Requirements

### MIP Identifier

Each node has a MIP identifier: 32 lowercase hexadecimal characters representing 128 bits.
It identifies the node to every other node for the life of the node and MUST NOT change. A
node MUST generate its identifier so that a collision with any other node's identifier is
negligibly likely; 128 bits drawn from a random source achieve that. How the value is
produced is up to the node. One way is to hash a random UUID together with a string
particular to the organization:

```ruby
require 'digest'

uuid = Random.uuid
salt = "Grand Lodge of Example"

mip_identifier = Digest::MD5.hexdigest("#{uuid}#{salt}")
```

The same in MySQL:

```sql
SELECT MD5(CONCAT(UUID(), 'Grand Lodge of Example')) AS mip_identifier
```

### RSA Key Pair

Each node has an RSA key pair. The private key signs every request the node sends and every
endorsement it issues. The public key is given to other nodes when a connection is requested
and travels in the node's profile.

The key MUST be at least 2048 bits. 4096 bits is RECOMMENDED. The key MUST NOT exceed 8192
bits; the bound keeps the signature and public key headers under common per-header size
limits. A receiver MUST reject a connection request whose key is outside these bounds (see
Error Responses). The bound applies to the key presented in a connection request; a node's key
cannot be changed through an organization update, and key rotation is outside the scope of
this version of the protocol.

Public keys are exchanged in PEM format (`-----BEGIN PUBLIC KEY-----`, the SubjectPublicKeyInfo
encoding).

### Node URL

Each node has a URL, its `mip_url`, of the form:

```
<prefix>/mip/node/<mip_identifier>
```

The prefix is chosen by the vendor and is any HTTPS URL prefix. The path `/mip/node/` and the
node's identifier follow it. The canonical example used throughout this document is:

```
https://mip.example.org/api/mip/node/e82d40e9416304e8c72790b45b27a8e6
```

Every endpoint in this document is a path suffix appended to the receiving node's `mip_url`.
A sender MUST build the request URL by appending the suffix to the `mip_url` it holds for
the receiver. There is no endpoint at the node URL itself; the protocol defines no `GET`
requests.

### Environment

Each node runs in one environment, named by a short string. Live nodes MUST use the value
`production`. Other values (`staging`, `development`, `test`) are for copies of a system that
hold non-live data. The environment is sent on every request and a receiver refuses a request
from a different environment, so that a copy of a system can never act on the live network.

# Request Process

All MIP requests are HTTP `POST` over HTTPS with a JSON object as the body and
`Content-Type: application/json`. A request with nothing to say carries the empty object `{}`.

## Header Parameters

The following headers are REQUIRED on every request:

- `X-MIP-MIP-IDENTIFIER`: the sending node's MIP identifier.
- `X-MIP-TIMESTAMP`: the time the request was made, in ISO 8601 format with a time zone
  (for example `2026-09-14T15:04:05Z`).
- `X-MIP-SIGNATURE`: the sender's RSA signature over the request, as specified under
  Signatures.
- `X-MIP-ENVIRONMENT`: the sender's environment. The receiver MUST answer `403` with code
  `environment_mismatch` when the value differs from its own environment. This header is not
  part of the signed document.

The following header is REQUIRED on a Connection Request and on a Connection Declined, and
MUST NOT be sent on any other request:

- `X-MIP-PUBLIC-KEY`: the sender's RSA public key, PEM encoded and then Base64 encoded
  without line breaks. A receiver uses it only on a Connection Request, where it holds no
  key for the sender yet. On every other request the receiver verifies the signature with the
  key it already holds for the sender.

## Signatures

The signature is an RSA signature (RSASSA-PKCS1-v1_5 with SHA-256) over a document made of
three parts concatenated with nothing between them:

1. the value of the `X-MIP-TIMESTAMP` header, exactly as sent;
2. the path of the request URL: the part after the host, without scheme, host, or query
   string;
3. the request body, exactly the bytes sent.

The signature is Base64 encoded without line breaks and sent in `X-MIP-SIGNATURE`.

```ruby
require 'openssl'
require 'base64'
require 'json'
require 'time'

timestamp = Time.now.iso8601
path = "/api/mip/node/512ef14957203c6323e79937f3935708/mip_member_searches"
body = { :member_number => "M123456", :shared_identifier => "0192b0c4-7c1e-7d3a-9f4b-2a6d8e1c5b70" }.to_json

signature = Base64.strict_encode64(
  private_key.sign(OpenSSL::Digest::SHA256.new, "#{timestamp}#{path}#{body}")
)
```

The receiver rebuilds the same document from the header, the path it received the request
on, and the raw body, and verifies it with the sender's public key. The three parts each
close off a class of attack: the timestamp defeats replay, the path binds the signature to
one endpoint so it cannot be presented to another, and the body proves the payload was not
altered in transit.

## Best Practices

The following measures are RECOMMENDED. Whether a node applies each one, and with what
values, is the node's own decision. When a node does apply one, it MUST answer with the
status code and error code given here so that the other side receives a standard response.

- **Timestamp window.** A receiver SHOULD refuse a request whose timestamp is too far from
  its own clock. A window of 60 seconds into the past and 2 seconds into the future is a
  reasonable choice. A request outside the window is answered `401` with code
  `timestamp_out_of_window`.
- **Replay detection.** A receiver SHOULD refuse a request it has already handled. A repeat
  of the same sender, timestamp, path, signature, and body within a short period is a replay.
  How replays are detected is the receiver's business; a replay is answered `401` with code
  `replay_detected`.
- **Request size.** A receiver SHOULD cap the size of a request body. A request over the cap
  is answered `413` with code `request_too_large`. A cap of 256 KiB accommodates every
  request in this document at the maximum key size.
- **Rate limiting.** A receiver SHOULD cap the number of requests it accepts from one
  connection per day, and it advertises that cap as `daily_rate_limit` when the connection is
  created or approved and in every connection response. A request over the cap is answered
  `429` with code `rate_limit_exceeded` and a `Retry-After` header giving the number of
  seconds until the receiver next accepts a request from that connection. A Connection
  Request is never rate limited: before a connection exists there is no agreement between
  the two nodes about what the limit would be.

## Response Format

Every response is a JSON object with two members:

- `meta`: data about the request and its processing. It MUST contain `succeeded`, a boolean.
  On failure it also contains `errors`, as specified under Error Responses.
- `data`: the response to the request. It is `{}` on failure.

A successful request is answered `200` with `meta.succeeded` set to `true`:

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "acknowledged": true
  }
}
```

A field advertised as `daily_rate_limit` in any response MUST be the limit the responding node
applies to the connection, not a placeholder.

## Error Responses

Every error is an HTTP `4xx` or `5xx` response with a JSON body in the response format. A
receiver MUST NOT answer an error with an empty body and MUST NOT answer `200` with
`meta.succeeded` set to `false`. The status code gives the class of problem and the body gives
the specific one.

Errors are listed in `meta.errors`. Each error has a `code` from the catalog below and a
`message` for a person to read. Clients MUST NOT parse `message`. An error that concerns one
field of the request adds `field`, naming it. A `connection_state_invalid` error adds
`status`, the receiver's current status of the connection; see
[Notification Delivery](#notification-delivery).

```json
{
  "meta": {
    "succeeded": false,
    "errors": [
      { "code": "validation_failed", "field": "contact_phone", "message": "can't be blank" }
    ]
  },
  "data": {}
}
```

### Error Catalog

| Status | Class | Codes |
|---|---|---|
| `400` | The request is malformed | `header_missing` (the header, in `field`), `timestamp_unparseable`, `body_not_json`, `shared_identifier_missing`, `endorsement_invalid`, `endorsement_sender_mismatch` |
| `401` | The sender cannot be authenticated | `signature_missing`, `signature_invalid`, `timestamp_out_of_window`, `replay_detected`, `sender_unknown` |
| `403` | The sender is authenticated but refused | `environment_mismatch`, `connection_not_active`, `connection_mismatch` |
| `404` | The thing addressed does not exist | `node_not_found`, `request_not_found` |
| `409` | The request is valid but the connection or request is in the wrong state | `connection_state_invalid`, `request_already_answered` |
| `413` | The body is too large | `request_too_large` |
| `422` | The request is well formed but its content is rejected | `validation_failed` (with `field`), `public_key_mismatch`, `public_key_size_invalid` |
| `429` | The sender is rate limited; `Retry-After` is set | `rate_limit_exceeded` |
| `500` | The receiver failed | `internal_error` |

### Order of Checks

A receiver runs its checks in the order below, and a request with several faults is answered
for the first fault found.

1. **Request shape** (`400`, `413`): the headers listed under Header Parameters are present
   (`header_missing`, naming the header in `field`), the timestamp parses
   (`timestamp_unparseable`), the body is a JSON object (`body_not_json`), and the body is
   within the receiver's size cap (`request_too_large`).
2. **Node lookup** (`404`): the identifier in the request path names a node this system
   hosts; otherwise `node_not_found`.
3. **Authentication** (`401`): the sender is a node the receiver holds a key for, or the
   request is a Connection Request carrying `X-MIP-PUBLIC-KEY`; otherwise `sender_unknown`.
   The timestamp is within the receiver's window (`timestamp_out_of_window`), the signature
   is present (`signature_missing`), and it verifies against that key (`signature_invalid`).
   On a Connection Request the signature is always verified with the header key, whether or
   not the receiver already holds a key for the sender; the comparison of the header key with
   a stored key is an endpoint rule, so that a repeated request with a different key is
   reported as such rather than as a bad signature.
4. **Replay** (`401`): the request is not a repeat (`replay_detected`).
5. **Authorization** (`403`): the sender's environment matches the receiver's, and the
   connection between them is `ACTIVE`. The following endpoints are exempt from the `ACTIVE`
   check because the connection is, or may be, in another state when they are called:
   Connection Request, Connection Approved, Connection Declined, and Connection Revoked.
   Connection Revoked is exempt because it is accepted from any state; see
   [Connection Revoked](#connection-revoked). Any other request from a connection that is
   not `ACTIVE` is answered `connection_not_active`.
6. **Rate limit** (`429`): the connection is within its daily limit. A Connection Request is
   exempt.
7. **Endpoint rules**: the checks specified under each endpoint.

A failure inside the receiver that is not the sender's fault is answered `500`
`internal_error`; the sender MAY retry such a request later.

# Endpoint Details

Every endpoint is a `POST` to a path suffix on the receiving node's `mip_url`. The
"Endpoint" line under each operation gives that suffix. The receiving node is identified by
its `mip_url`; no endpoint takes any other argument.

## Connection Protocol

### Connection Request

Request a connection between the sending node and the receiving node.

#### Endpoint: `<mip_url>/mip_connections`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "node_profile": {
    "mip_identifier": "e82d40e9416304e8c72790b45b27a8e6",
    "mip_url": "https://mip.example.org/api/mip/node/e82d40e9416304e8c72790b45b27a8e6",
    "organization_legal_name": "Grand Lodge of Example",
    "contact_person": "John Smith",
    "contact_phone": "+1-555-123-4567",
    "organization_public_website": "https://www.example.org",
    "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqh...\n-----END PUBLIC KEY-----",
    "gdpr_metadata": {
      "controller": { "role": "controller", "name": "Grand Lodge of Example" },
      "processors": [
        { "name": "Example Software Vendor", "role": "processor" }
      ],
      "sub_processors": [
        { "name": "Example Cloud Hosting" }
      ],
      "gdpr_contact_name": "Jane Doe",
      "gdpr_contact_email": "privacy@example.org"
    }
  },
  "share_my_organization": true,
  "endorsements": []
}
```

- **node_profile**: REQUIRED. The requesting node's own profile, including its
  `gdpr_metadata`. See [Node Profile](#node-profile) and [GDPR Metadata](#gdpr-metadata).
- **share_my_organization**: REQUIRED. Whether the receiving node may tell other nodes about
  the requester. See [Shared Nodes](#shared-nodes).
- **endorsements**: REQUIRED, MAY be empty. Endorsements of the requesting node issued by
  other nodes, presented so the receiver can approve the request automatically if it trusts
  one of the endorsers. See [Endorsement](#endorsement).

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "mip_connection": {
      "status": "PENDING",
      "authentication_type": null,
      "daily_rate_limit": 100,
      "node_profile": {
        "mip_identifier": "512ef14957203c6323e79937f3935708",
        "mip_url": "https://mip.example.org/api/mip/node/512ef14957203c6323e79937f3935708",
        "organization_legal_name": "Grand Lodge of Elsewhere",
        "contact_person": "Mary Jones",
        "contact_phone": "+1-555-987-6543",
        "organization_public_website": "https://www.elsewhere.example",
        "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqh...\n-----END PUBLIC KEY-----",
        "gdpr_metadata": {
          "controller": { "role": "controller", "name": "Grand Lodge of Elsewhere" },
          "processors": [
            { "name": "Elsewhere Software Vendor", "role": "processor" }
          ],
          "sub_processors": [
            { "name": "Example Cloud Hosting" }
          ]
        }
      }
    }
  }
}
```

- **data.mip_connection.status**: the connection's current status. A new request is answered
  `PENDING` when it awaits a person's approval, or `ACTIVE` when it was approved on the spot
  by endorsement. A request that repeats an existing connection is answered with that
  connection's current status; see Repeated Requests.
- **data.mip_connection.authentication_type**: `null` while the connection is `PENDING`;
  `ENDORSEMENT` when this request was approved on the spot by endorsement; when an existing
  connection is echoed, whatever value it has: `null` while `PENDING` or `DECLINED`,
  otherwise `MANUAL` or `ENDORSEMENT`. See [authentication_type](#connection-attributes).
- **data.mip_connection.daily_rate_limit**: the number of requests per day the receiving
  node will accept from this connection.
- **data.mip_connection.node_profile**: the receiving node's own profile, including its
  `gdpr_metadata`.

The response carries no endorsement and no list of other nodes. Endorsements are issued as
specified under [Endorsements](#endorsements), and known nodes are pushed afterward through
[Shared Nodes](#shared-nodes).

#### Processing a Connection Request

The receiver MUST check, in this order, before storing anything:

1. `node_profile.mip_identifier` equals the `X-MIP-MIP-IDENTIFIER` header. Otherwise `422`
   `validation_failed` with `field` set to `mip_identifier`.
2. The key in `X-MIP-PUBLIC-KEY` and `node_profile.public_key` are the same key. The two
   MUST be compared as parsed keys (for example by their DER encoding), so that PEM
   whitespace does not matter. Otherwise `422` `public_key_mismatch` and nothing is stored.
   The signature was verified with the header key; this check makes sure the key the receiver
   goes on to store is the key that signed the request.
3. The key is within the size bounds under [RSA Key Pair](#rsa-key-pair). Otherwise `422`
   `public_key_size_invalid`.
4. Every REQUIRED field of the Node Profile and of `gdpr_metadata` is present. Otherwise
   `422` `validation_failed` naming the field.

The receiver then stores the requester's profile and `gdpr_metadata`, stores the presented
endorsements as specified under [Endorsement](#endorsement), and evaluates them for
automatic approval.

#### Automatic Approval by Endorsement

A node that has already been vouched for by a node the receiver trusts can be connected
without a person's involvement. The receiver verifies each presented endorsement as specified
under [Endorsement Verification](#endorsement-verification). An endorsement is verified only
when its endorser is an `ACTIVE` connection of the receiver, since that is the only way the
receiver holds the endorser's key. Which verified endorsers count toward automatic approval,
and how many are needed, is the receiving node's own policy; a node MAY count every `ACTIVE`
connection, or only those a person has marked as trusted for this purpose. A threshold of
one endorsement from a trusted endorser is a common choice.

When the policy is met the receiver approves the connection at once: the response carries
`status` `ACTIVE` and `authentication_type` `ENDORSEMENT`. No endorsement is issued by an
automatic approval; see [Endorsements](#endorsements) for why. The receiver then proceeds as
after any approval: it pushes its known nodes to the new connection and announces the new
connection to its other sharing connections, as specified under [Shared Nodes](#shared-nodes).

When the policy is not met the connection is stored as `PENDING`, presented to a person for
approval or decline, and the response carries `status` `PENDING`.

#### Manual Approval

A person at the receiving organization approves or declines a pending request. Before
approving, they SHOULD confirm the requester's identity out of band, since the request itself
proves only that whoever sent it holds the private key matching the presented public key.
The requester's contact person reads the display form of their public key fingerprint (see
[Public Key Fingerprint](#public-key-fingerprint)) to the approver over the telephone, and
the approver compares it with the fingerprint their system shows for the pending request.

The approval is delivered with a [Connection Approved](#connection-approved) request; a
decline with a [Connection Declined](#connection-declined) request.

A node MAY send a connection request without a person initiating it, for example to a node
it learned of through discovery. Such a request is the same on the wire. It differs only in
what follows: the requester issues no endorsement of the approver for a request no person
made (see [Endorsements](#endorsements)).

#### Repeated Requests

A connection request is idempotent per requesting identifier. When the receiver already
holds a connection for the requester's `mip_identifier` it MUST answer `200` with that
connection's current status and MUST NOT create a second record. What else happens depends on
the status:

- `PENDING` or `ACTIVE`: nothing changes.
- `DECLINED`: the receiver reopens the same record as `PENDING`, presents it for approval
  again, and answers `PENDING`. If the receiver has blocked the requester, nothing changes
  and the response reports `DECLINED`.
- `REVOKED`: the receiver reopens the same record as `PENDING`, presents it for approval
  again, and answers `PENDING`. If the receiver has blocked the requester, nothing changes
  and the response reports `REVOKED`.

This is the only way a revoked connection comes back, and it works the same whichever node
revoked it and whichever node made the original request. The requester withdraws its
objection by asking; the receiver withdraws its own by approving, and its approval rebuilds
the record in full. A node that revoked a connection and wants it back sends a Connection
Request like any other node.

A node MAY block a node whose connection it holds as `DECLINED` or `REVOKED`. A block is a
mark a person sets on the connection, normally when declining or revoking it, and it is
never sent: `BLOCKED` is not a status, and a blocked node is told only that the connection
is still `DECLINED` or `REVOKED`. A block ends when a person clears it, after which a
repeated request reopens the record as above. A node that has blocked another and then
sends it a Connection Request of its own SHOULD clear the block as part of sending, since
asking for the connection and refusing it cannot both be meant.

A repeated request MUST present the same public key the receiver already holds for that
identifier. A different key is answered `422` `public_key_mismatch` and changes nothing, so
that a repeated request cannot be used to swap a key before anyone has verified it. Changing
a node's key is outside the scope of this version of the protocol.

Because the answer is the receiver's current status, a repeated request is also how a node
learns what the other side holds after a failed or doubtful exchange; see
[Notification Delivery](#notification-delivery). This applies whichever node made the
original request. A repeated request to a `DECLINED` or `REVOKED` record reopens it, as
above.

### Notification Delivery

Connection Approved, Connection Declined, and Connection Revoked each tell the other node
that the sender has changed the connection. Each is one request with one
answer, and the two nodes agree about the connection only once that answer has arrived. Four
rules keep them in agreement without background retries, queues, or locks on either side.

- **The sender changes its record on `200` and not before.** A node sends the notification
  when a person takes the action, and records the new state when the receiver answers
  `200`. A `5xx`, a `429`, or no response changes nothing on the sender; the person is told
  the notification did not go through and may try again later. There is no automatic retry.
  A node MUST let a person re-send any notification. A re-send is a fresh request with its
  own timestamp and signature; it is not a replay.
- **The receiver is idempotent.** Each notification names a source state and a target
  state. A record in the source state moves to the target state. A record already in the
  target state is answered `200` with that status and nothing changes. A record in neither
  state is answered `409` `connection_state_invalid`. The endpoint sections give the two
  states for each notification.
- **A `409` says what the receiver holds.** The `connection_state_invalid` error carries the
  receiver's current status of the connection in `status`, so that the person sees the
  disagreement itself rather than a bare conflict. The sender MUST NOT change its own record
  on the strength of a `409`.
- **Either node can ask.** A Connection Request to a node that already holds the connection
  is answered with that node's current status and changes nothing there, whichever node
  made the original request; see Repeated Requests under
  [Connection Request](#connection-request). A node MAY send one to learn what the other
  side holds, and a requester MAY adopt the status it reports, since the answering node is
  the one whose approval or refusal counts and the response carries everything an approval
  carries.

One retry is RECOMMENDED. When a pending request is approved by an endorsement that arrives
later (see [Late Automatic Approval](#late-automatic-approval)), no person is at hand to
re-send a Connection Approved that fails. A node SHOULD retry that one notification in the
background, with backoff and for a bounded period, and tell a person if it still fails. The
rules above make this safe: the record changes only on `200`; a retry that finds the record
already `ACTIVE` is answered `200`; and one that finds it in any other state, because a
person acted in the meantime, is answered `409` and dropped.

These rules are enough because the only disagreement they can leave behind is a lost reply:
the receiver changed and the sender did not. The sender's record still shows the action as
not done, so the person takes it again, and the receiver, already in the target state,
answers `200`. A disagreement that arises some other way, such as a record restored from a
backup, is repaired with the same moves a person makes every day: Connection Revoked is
accepted from any state and resets a connection, and a Connection Request reopens it, so
that an approval rebuilds the `ACTIVE` record in full. No implementation needs to let anyone
edit a connection's status by hand, and none should.

### Connection Approved

Notify a requester that its connection request has been approved. Sent by the approving node
after a person approves the request, or after a pending request is approved later by an
endorsement that arrives at [Endorsements](#endorsements).

#### Endpoint: `<mip_url>/mip_connections/approved`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "node_profile": {
    "mip_identifier": "512ef14957203c6323e79937f3935708",
    "mip_url": "https://mip.example.org/api/mip/node/512ef14957203c6323e79937f3935708",
    "organization_legal_name": "Grand Lodge of Elsewhere",
    "contact_person": "Mary Jones",
    "contact_phone": "+1-555-987-6543",
    "organization_public_website": "https://www.elsewhere.example",
    "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqh...\n-----END PUBLIC KEY-----",
    "gdpr_metadata": {
      "controller": { "role": "controller", "name": "Grand Lodge of Elsewhere" },
      "processors": [
        { "name": "Elsewhere Software Vendor", "role": "processor" }
      ],
      "sub_processors": [
        { "name": "Example Cloud Hosting" }
      ]
    }
  },
  "share_my_organization": true,
  "daily_rate_limit": 100,
  "authentication_type": "MANUAL",
  "endorsement": {
    "endorser_mip_identifier": "512ef14957203c6323e79937f3935708",
    "endorsed_mip_identifier": "e82d40e9416304e8c72790b45b27a8e6",
    "endorsed_public_key_fingerprint": "963bb5ab26276a4fd5ef3f20a19a62621221428dce3640b09eb2e7bce7ceefa6",
    "endorsement_document": "{\"type\":\"MIP_ENDORSEMENT_V2\",\"endorser_mip_identifier\":\"512ef14957203c6323e79937f3935708\",\"endorsed_mip_identifier\":\"e82d40e9416304e8c72790b45b27a8e6\",\"endorsed_public_key_fingerprint\":\"963bb5ab26276a4fd5ef3f20a19a62621221428dce3640b09eb2e7bce7ceefa6\",\"issued_at\":\"2026-09-14T12:00:00Z\",\"expires_at\":\"2027-09-14T12:00:00Z\"}",
    "endorsement_signature": "g2FAOk4wXU6+j85b+1kpz3kgRH+ZmFIk2YkNkCP5GP8l...",
    "issued_at": "2026-09-14T12:00:00Z",
    "expires_at": "2027-09-14T12:00:00Z"
  }
}
```

- **node_profile**: REQUIRED. The approving node's own profile, including its
  `gdpr_metadata`.
- **share_my_organization**: REQUIRED. Whether the requester may tell other nodes about the
  approver.
- **daily_rate_limit**: REQUIRED. The number of requests per day the approver will accept
  from this connection.
- **authentication_type**: REQUIRED. `MANUAL` when a person approved the request;
  `ENDORSEMENT` when it was approved by endorsement after having been pending. The receiver
  MUST record the value sent rather than assume one.
- **endorsement**: present when `authentication_type` is `MANUAL` and absent otherwise. A
  person approving a connection is vouching for the requester, and the approver's endorsement
  of the requester is issued at that moment and delivered here. An approval by endorsement
  issues none; see [Endorsements](#endorsements). When absent the key is omitted, not set to
  `null`.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "mip_connection": {
      "status": "ACTIVE"
    }
  }
}
```

- **data.mip_connection.status**: `ACTIVE`.

The source state is `PENDING` and the target state is `ACTIVE`; see
[Notification Delivery](#notification-delivery). A `PENDING` record is marked `ACTIVE`, and
the receiver records `authentication_type`, `daily_rate_limit`, and `share_my_organization`,
stores the approver's `gdpr_metadata`, and stores the endorsement, when present, as
specified under [Endorsement](#endorsement). A record already `ACTIVE` is answered `200`
with status `ACTIVE` and nothing changes, the endorsement included; an approver that wants
its endorsement stored after such a reply sends it to [Endorsements](#endorsements). Any
other state is answered `409` `connection_state_invalid`.

After approving, the approver pushes its known nodes to the newly approved node and announces
the newly approved node to its other sharing connections, both through
[Shared Nodes](#shared-nodes). When the connection request was initiated by a person, the
requester issues its own endorsement of the approver when this request moves the record to
`ACTIVE`, and sends it to the approver's [Endorsements](#endorsements) endpoint.

### Connection Declined

Notify a requester that its connection request has been declined.

#### Endpoint: `<mip_url>/mip_connections/declined`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "mip_identifier": "512ef14957203c6323e79937f3935708",
  "reason": "Organization not recognized. Please contact us directly to establish a connection."
}
```

- **mip_identifier**: REQUIRED. The declining node's MIP identifier.
- **reason**: OPTIONAL. An explanation for a person to read.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "mip_connection": {
      "status": "DECLINED"
    }
  }
}
```

- **data.mip_connection.status**: `DECLINED`.

The source state is `PENDING` and the target state is `DECLINED`; see
[Notification Delivery](#notification-delivery). A `PENDING` record is marked `DECLINED` and
kept, so that a later request to the same node is recognized as a repeat. A record already
`DECLINED` is answered `200` with status `DECLINED` and nothing changes. Any other state is
answered `409` `connection_state_invalid`.

Declining does not prevent the requester from requesting again; see Repeated Requests under
[Connection Request](#connection-request).

### Connection Revoked

Notify a node that the sender has revoked the connection and will honor no further requests.
It may be sent whatever state the connection is in.

#### Endpoint: `<mip_url>/mip_connections/revoked`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "mip_identifier": "512ef14957203c6323e79937f3935708",
  "reason": "Connection revoked due to policy violation."
}
```

- **mip_identifier**: REQUIRED. The revoking node's MIP identifier.
- **reason**: OPTIONAL. An explanation for a person to read.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "mip_connection": {
      "status": "REVOKED"
    }
  }
}
```

- **data.mip_connection.status**: `REVOKED`.

The target state is `REVOKED` and the source state is any other; see
[Notification Delivery](#notification-delivery). Whatever the receiver holds, the record is
marked `REVOKED` and kept, and the receiver stops sending requests to the revoking node. A
record already `REVOKED` is answered `200` with status `REVOKED` and nothing changes. This
is the one notification that is never answered `409`: it is how a node resets a connection
whatever state the two sides have reached, and either node may send it at any stage.
Revoking a `PENDING` record withdraws the request when the requester sends it and refuses
it when the other node does.

The record is kept because a repeated connection request from either node is answered from
it, and that is the only way a revoked connection comes back; see Repeated Requests under
[Connection Request](#connection-request). There is no restore. Which node revoked the
connection, and why, is worth keeping for the people at each organization, but no rule of
the protocol depends on it, and a connection both nodes have revoked comes back the same
way as one that only one of them did.

Endorsements issued by a node whose connection has been revoked no longer verify on the
node that revoked it, since verification requires the endorser to be an `ACTIVE` connection;
see [Endorsement Verification](#endorsement-verification).

### Organization Update

Push the sending node's current profile to a connected node and receive that node's current
profile in return. A node uses this when its contact details, website, or URL change.

#### Endpoint: `<mip_url>/mip_connections/update`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "node_profile": {
    "mip_identifier": "e82d40e9416304e8c72790b45b27a8e6",
    "mip_url": "https://mip.example.org/api/mip/node/e82d40e9416304e8c72790b45b27a8e6",
    "organization_legal_name": "Grand Lodge of Example",
    "contact_person": "Robert Brown",
    "contact_phone": "+1-555-222-3333",
    "organization_public_website": "https://www.example.org",
    "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqh...\n-----END PUBLIC KEY-----",
    "gdpr_metadata": {
      "controller": { "role": "controller", "name": "Grand Lodge of Example" },
      "processors": [
        { "name": "Example Software Vendor", "role": "processor" }
      ],
      "sub_processors": [
        { "name": "Example Cloud Hosting" }
      ],
      "gdpr_contact_name": "Jane Doe",
      "gdpr_contact_email": "privacy@example.org"
    }
  }
}
```

- **node_profile**: REQUIRED. The sending node's current profile, including its
  `gdpr_metadata`.

An update MAY change `organization_legal_name`, `contact_person`, `contact_phone`,
`organization_public_website`, `mip_url`, and `gdpr_metadata`. It MUST NOT change
`mip_identifier` or `public_key`; the receiver MUST ignore those two fields in an update and
keep the values it holds. A node's identity is its identifier and its key, and neither is
changed by telling a peer.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "mip_connection": {
      "status": "ACTIVE",
      "authentication_type": "MANUAL",
      "daily_rate_limit": 100,
      "node_profile": {
        "mip_identifier": "512ef14957203c6323e79937f3935708",
        "mip_url": "https://mip.example.org/api/mip/node/512ef14957203c6323e79937f3935708",
        "organization_legal_name": "Grand Lodge of Elsewhere",
        "contact_person": "Mary Jones",
        "contact_phone": "+1-555-987-6543",
        "organization_public_website": "https://www.elsewhere.example",
        "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqh...\n-----END PUBLIC KEY-----",
        "gdpr_metadata": {
          "controller": { "role": "controller", "name": "Grand Lodge of Elsewhere" },
          "processors": [
            { "name": "Elsewhere Software Vendor", "role": "processor" }
          ],
          "sub_processors": [
            { "name": "Example Cloud Hosting" }
          ]
        }
      }
    }
  }
}
```

The response has the same shape as the Connection Request response: the connection's
`status`, `authentication_type`, and `daily_rate_limit`, and the receiving node's own current
profile including its `gdpr_metadata`. The sender updates its record of the receiver from it,
subject to the same rule: `mip_identifier` and `public_key` in the response are not applied.

### Shared Nodes

Tell a connected node about other nodes. This is the protocol's one discovery mechanism, and
it is used in three situations:

1. After approving a connection, the approver sends the newly approved node every node it may
   share.
2. After approving a connection, the approver sends each of its other sharing connections the
   newly approved node, as a batch of one.
3. In answer to a [Share Request](#share-request), the receiver of that request sends the
   requester every node it may share.

#### Endpoint: `<mip_url>/shared_nodes`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "nodes": [
    {
      "mip_identifier": "7c1d9a2f4b6e8d0c3a5f7e9b1d3c5a7e",
      "mip_url": "https://mip.example.org/api/mip/node/7c1d9a2f4b6e8d0c3a5f7e9b1d3c5a7e",
      "organization_legal_name": "Grand Lodge of Yonder",
      "contact_person": "Alice Green",
      "contact_phone": "+1-555-444-5555",
      "organization_public_website": "https://www.yonder.example",
      "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqh...\n-----END PUBLIC KEY-----",
      "endorsements": [
        {
          "endorser_mip_identifier": "512ef14957203c6323e79937f3935708",
          "endorsed_mip_identifier": "7c1d9a2f4b6e8d0c3a5f7e9b1d3c5a7e",
          "endorsed_public_key_fingerprint": "f0f3393fdc0ac94a875f43872a0fdddc2b7e6a1c9d4f8e3b5a2c7d9e1f4b6a8c",
          "endorsement_document": "{\"type\":\"MIP_ENDORSEMENT_V2\",\"endorser_mip_identifier\":\"512ef14957203c6323e79937f3935708\",\"endorsed_mip_identifier\":\"7c1d9a2f4b6e8d0c3a5f7e9b1d3c5a7e\",\"endorsed_public_key_fingerprint\":\"f0f3393fdc0ac94a875f43872a0fdddc2b7e6a1c9d4f8e3b5a2c7d9e1f4b6a8c\",\"issued_at\":\"2026-03-01T09:30:00Z\",\"expires_at\":\"2027-03-01T09:30:00Z\"}",
          "endorsement_signature": "g2FAOk4wXU6+j85b+1kpz3kgRH+ZmFIk2YkNkCP5GP8l...",
          "issued_at": "2026-03-01T09:30:00Z",
          "expires_at": "2027-03-01T09:30:00Z"
        }
      ]
    }
  ]
}
```

- **nodes**: REQUIRED. At most 20 elements. A sender with more nodes to share sends several
  requests. Each element is a [Node Profile](#node-profile) without `gdpr_metadata`, plus:
  - **endorsements**: REQUIRED, MAY be empty. Endorsements of that node which the sender
    holds and has verified. See Selecting Endorsements to Relay.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "acknowledged": true
  }
}
```

- **data.acknowledged**: `true`. The receiver has accepted the batch for processing.

#### Which Nodes May Be Shared

A node MAY share a connection only when that connection is `ACTIVE` and the other node set
`share_my_organization` to `true` when the connection was requested or approved. A sender
MUST NOT share a node that has not permitted it and MUST NOT include the receiver itself in
the batch.

The sender MUST NOT include `gdpr_metadata` in a shared element, and a receiver MUST NOT store
`gdpr_metadata` received in one. GDPR metadata is a declaration a node makes about itself
directly to each peer; see [GDPR Metadata](#gdpr-metadata).

#### Selecting Endorsements to Relay

Each element carries at most five endorsements. The sender MUST relay only endorsements it
has verified (see [Endorsement Verification](#endorsement-verification)) and MUST NOT relay an
endorsement it holds as unverified, expired, or revoked. When the sender has issued its own
endorsement of the node, that endorsement is included first; the remaining places are filled
with the other verified endorsements, newest `issued_at` first, up to five in total.

The limits bound the size of a batch: twenty nodes, each with a key of up to 8192 bits and
five endorsements, fit within a 256 KiB request.

#### Processing Shared Nodes

The receiver records each element as a known node, or updates its existing record, and
stores the relayed endorsements as specified under [Endorsement Storage](#endorsement-storage).
Nothing in a shared element is proof of the node's identity: the receiver has only the
sender's word for the profile, and the endorsements verify only where the receiver holds the
endorser's key. Whether the receiver then attempts a connection to a known node, and on what
conditions, is its own policy. A receiver that automatically requests connections to shared
nodes SHOULD require that the node carry a verified endorsement from an endorser it trusts.

### Share Request

Ask a connected node to send the nodes it may share. The nodes arrive afterward through
[Shared Nodes](#shared-nodes), not in the response.

#### Endpoint: `<mip_url>/request_shared_nodes`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{}
```

The request has no fields.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "acknowledged": true,
    "shared_node_count": 7
  }
}
```

- **data.acknowledged**: `true`.
- **data.shared_node_count**: the number of nodes the receiver will send, so the requester
  knows what to expect. When the receiver has nothing to share the request still succeeds,
  with `0`.

The receiver then sends the requester its shareable nodes through Shared Nodes, in batches of
at most 20, the same way it does after approving a connection.

### Endorsements

Send an endorsement of the receiving node's identity, issued by the sending node.

Endorsing is a decision made by a person. A node issues an endorsement of a connection only
when one of its own people has decided to vouch for that node. Two moments in the connection
protocol are such decisions, and a third is open at any time:

- **Approving a connection manually.** The approver's endorsement of the requester is issued
  at that moment and delivered in the [Connection Approved](#connection-approved) payload.
- **Requesting a connection.** A person filing a connection request is vouching for the node
  they chose to connect to. Once the connection is `ACTIVE`, however the other side approved
  it, the requester issues its endorsement of the approver and sends it here. A request a
  node sent on its own initiative, without a person, earns no endorsement.
- **At any later time.** Either organization MAY decide to endorse an existing `ACTIVE`
  connection whenever it chooses, by sending its endorsement here. This is also how an
  endorsement is renewed before it expires.

An approval by endorsement is not an endorsement decision. The connection it creates is as
valid as any other, but the approving organization has not itself vouched for anyone; it
relied on an endorser it trusts. Issuing an endorsement on that basis would make endorsements
transitive ("B vouches for A, and I trust B, so I vouch for A"), which is exactly what the
web of trust rules out. See [Web of Trust](#web-of-trust).

#### Endpoint: `<mip_url>/endorsements`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "endorser_mip_identifier": "e82d40e9416304e8c72790b45b27a8e6",
  "endorsed_mip_identifier": "512ef14957203c6323e79937f3935708",
  "endorsed_public_key_fingerprint": "6460180794811daebea1cff1aca4c18145765f7f537f3c44e3894bf3a17ea4df",
  "endorsement_document": "{\"type\":\"MIP_ENDORSEMENT_V2\",\"endorser_mip_identifier\":\"e82d40e9416304e8c72790b45b27a8e6\",\"endorsed_mip_identifier\":\"512ef14957203c6323e79937f3935708\",\"endorsed_public_key_fingerprint\":\"6460180794811daebea1cff1aca4c18145765f7f537f3c44e3894bf3a17ea4df\",\"issued_at\":\"2026-09-14T12:05:00Z\",\"expires_at\":\"2027-09-14T12:05:00Z\"}",
  "endorsement_signature": "g2FAOk4wXU6+j85b+1kpz3kgRH+ZmFIk2YkNkCP5GP8l...",
  "issued_at": "2026-09-14T12:05:00Z",
  "expires_at": "2027-09-14T12:05:00Z"
}
```

The payload is an [Endorsement](#endorsement) in the full payload form.

The sending node MUST be the endorser: `endorser_mip_identifier` MUST equal the
`X-MIP-MIP-IDENTIFIER` header. Otherwise the request is answered `400`
`endorsement_sender_mismatch`. A third party's endorsement reaches a node only inside a
Connection Request or a Shared Nodes batch, never here.

Because the sender is the endorser and is an `ACTIVE` connection, the receiver holds its key,
and every endorsement arriving here is either verified or rejected; none is stored as
unverified. An endorsement that fails any step of
[Endorsement Verification](#endorsement-verification) is answered `400` `endorsement_invalid`
and is not stored.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "endorsement_id": "0192b0c4-9a3e-7f21-8c4d-5e6f7a8b9c0d"
  }
}
```

- **data.endorsement_id**: the receiver's identifier for the stored endorsement. A
  re-sent endorsement identical to one already stored is answered `200` with the same
  identifier and changes nothing.

#### Late Automatic Approval

An endorsement arriving here can complete a pending connection. When the receiver holds a
`PENDING` connection request from the endorsed node and the newly verified endorsement
satisfies its automatic approval policy, the receiver MAY approve that connection and send the
endorsed node a [Connection Approved](#connection-approved) request with `authentication_type`
`ENDORSEMENT` and no `endorsement`.

## Member Protocol

### Member Search Request

Search for a member in a connected organization. The search is queued on the responding node
and the result arrives later through [Member Search Reply](#member-search-reply).

#### Endpoint: `<mip_url>/mip_member_searches`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "member_number": "M123456",
  "first_name": "John",
  "last_name": "Doe",
  "birthdate": "1970-01-15",
  "shared_identifier": "0192b0c4-7c1e-7d3a-9f4b-2a6d8e1c5b70",
  "notes": "Member is requesting affiliation with our lodge. Please verify current standing."
}
```

- **member_number**: the member's number in the responding organization, as the member knows
  it.
- **first_name**, **last_name**: the member's name.
- **birthdate**: the member's date of birth, `YYYY-MM-DD`. OPTIONAL, and RECOMMENDED with a
  name search so the responder can tell candidates apart.
- **shared_identifier**: REQUIRED. A UUID generated by the requester that identifies this
  search on both nodes. The reply carries it back.
- **notes**: OPTIONAL. Context for the person handling the search.

A request MUST contain `member_number`, or both `first_name` and `last_name`, and MAY
contain all three. A request that meets neither minimum is answered `422`
`validation_failed` naming the missing field. A request without `shared_identifier` is
answered `400` `shared_identifier_missing`. Fields the requester did not fill in are omitted,
not sent as `null`.

How the responder matches the fields it receives against its members is the responder's own
business and is not specified here. As a best practice, when a request carries both a member
number and a name the responder SHOULD match on either rather than require both to agree: a
member number is often copied by hand and a name is often spelled differently in the two
systems, and a search that finds the member on whichever is right is more useful than one
that fails because one of them is wrong.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "status": "PENDING",
    "shared_identifier": "0192b0c4-7c1e-7d3a-9f4b-2a6d8e1c5b70"
  }
}
```

- **data.status**: `PENDING`. The result is delivered through Member Search Reply.
- **data.shared_identifier**: the identifier from the request.

### Member Search Reply

Deliver the result of a member search to the node that requested it. The reply is a request
in its own right: the responding node sends it to the requesting node's endpoint, signed like
any other request.

#### Endpoint: `<mip_url>/mip_member_searches/reply`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Reply Body

A reply carrying matches:

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "shared_identifier": "0192b0c4-7c1e-7d3a-9f4b-2a6d8e1c5b70",
    "status": "APPROVED",
    "matches": [
      {
        "member_number": "NY-123456",
        "first_name": "John",
        "last_name": "Doe",
        "birthdate": "1970-01-15",
        "contact": {
          "email": "john.doe@example.org",
          "phone": "+1-555-123-4567",
          "address": {
            "address1": "123 Main Street",
            "address2": "Apt 4B",
            "city": "Albany",
            "state": "NY",
            "postal_code": "12207",
            "country": "US"
          }
        },
        "group_status": {
          "status": "Active",
          "is_active": true,
          "good_standing": true
        },
        "affiliations": [
          {
            "local_name": "Masters Lodge No. 5",
            "local_status": "Active",
            "is_active": true,
            "member_type": "Master Mason"
          },
          {
            "local_name": "Unity Lodge No. 42",
            "local_status": "Affiliate",
            "is_active": true,
            "member_type": "Master Mason"
          }
        ]
      },
      {
        "member_number": "NY-789012",
        "first_name": "John",
        "last_name": "Doe",
        "birthdate": "1965-03-22",
        "contact": {
          "email": "jdoe65@example.org",
          "phone": "+1-555-987-6543",
          "address": {
            "address1": "456 Oak Avenue",
            "address2": null,
            "city": "Buffalo",
            "state": "NY",
            "postal_code": "14201",
            "country": "US"
          }
        },
        "group_status": {
          "status": "Suspended",
          "is_active": false,
          "good_standing": false
        },
        "affiliations": [
          {
            "local_name": "Harmony Lodge No. 18",
            "local_status": "Suspended NPD",
            "is_active": false,
            "member_type": "Master Mason"
          }
        ]
      }
    ]
  }
}
```

A reply declining the search:

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "shared_identifier": "0192b0c4-7c1e-7d3a-9f4b-2a6d8e1c5b70",
    "status": "DECLINED",
    "reason": "We do not release member information without a letter from the lodge secretary."
  }
}
```

- **meta.succeeded**: `true`. A declined search is a completed search, not a failure.
- **data.shared_identifier**: the identifier from the search request.
- **data.status**: `APPROVED` when the responder ran the search, `DECLINED` when it refused
  to.
- **data.matches**: present with `APPROVED`. The members matching the search, which MAY be
  none. Each element:
  - **member_number**: the member's number in the responding organization.
  - **first_name**, **last_name**, **birthdate**: identifying information.
  - **contact**: the member's contact information, or `null` when the responder holds none.
    - **email**, **phone**: primary email address and telephone number.
    - **address**: mailing address. `address2` is `null` when there is none.
  - **group_status**: the member's status in the responding organization.
    - **status**: the status label as the responding system stores it.
    - **is_active**: whether that status is an active membership status.
    - **good_standing**: whether the member is in good standing.
  - **affiliations**: the member's local memberships.
    - **local_name**: name of the local lodge or chapter.
    - **local_status**: the member's status in that local, as the responding system labels
      it.
    - **is_active**: whether that local membership is active.
    - **member_type**: the member's type or degree in that local.
- **data.reason**: present with `DECLINED`. An explanation for a person to read.

#### Acknowledgement

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "acknowledged": true,
    "shared_identifier": "0192b0c4-7c1e-7d3a-9f4b-2a6d8e1c5b70"
  }
}
```

- **data.acknowledged**: `true`. The reply was received and stored against the identifier.
- **data.shared_identifier**: the identifier from the reply.

The acknowledgement means only that the reply was received and stored. A reply is answered
`400` `shared_identifier_missing` when it carries no identifier; `404` `request_not_found`
when the receiver holds no search with that identifier; `403` `connection_mismatch` when the
search was sent to a different connection than the one replying; and `409`
`request_already_answered` when a reply has already been stored for it.

### Certificate of Good Standing Request

Request a certificate of good standing for a person from a connected organization. The
certificate is the exchange that supports a person moving between organizations: it is
comprehensive enough for the requesting organization to process the person as a new or
affiliating member. The request is queued on the responding node and answered through
[Certificate of Good Standing Reply](#certificate-of-good-standing-reply).

#### Endpoint: `<mip_url>/certificates_of_good_standing`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Request Payload

```json
{
  "member_number": "NY-123456",
  "first_name": "John",
  "last_name": "Doe",
  "birthdate": "1970-01-15",
  "shared_identifier": "0192b0c4-8e2f-7a6b-b1c3-4d5e6f708192",
  "notes": "Member is petitioning for affiliation with our lodge."
}
```

- **member_number**: the person's member number in the responding organization.
- **first_name**, **last_name**: the person's name.
- **birthdate**: the person's date of birth, `YYYY-MM-DD`. OPTIONAL, and RECOMMENDED with a
  name.
- **shared_identifier**: REQUIRED. A UUID generated by the requester that identifies this
  request on both nodes.
- **notes**: OPTIONAL. Context for the person handling the request.

The fields describe the person as the responding organization knows them, since the person
often has no record yet in the requesting organization. The same minimum applies as for a
Member Search Request: `member_number`, or both `first_name` and `last_name`, and MAY
contain all three; otherwise `422` `validation_failed`. A request without `shared_identifier`
is answered `400` `shared_identifier_missing`. Unfilled fields are omitted. The same
guidance on matching applies as for a Member Search Request.

#### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "status": "PENDING",
    "shared_identifier": "0192b0c4-8e2f-7a6b-b1c3-4d5e6f708192"
  }
}
```

- **data.status**: `PENDING`. The certificate or decline is delivered through Certificate
  of Good Standing Reply.
- **data.shared_identifier**: the identifier from the request.

### Certificate of Good Standing Reply

Deliver a certificate of good standing, or a decline, to the node that requested it.

#### Endpoint: `<mip_url>/certificates_of_good_standing/reply`

#### Arguments

None. The receiving node is identified by its `mip_url`.

#### HTTP Action: POST

#### Payload Format: JSON

#### Reply Body

A reply carrying a certificate:

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "shared_identifier": "0192b0c4-8e2f-7a6b-b1c3-4d5e6f708192",
    "status": "APPROVED",
    "certificate": {
      "shared_identifier": "0192b0c5-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
      "good_standing": true,
      "issued_at": "2026-09-14T14:30:00Z",
      "valid_until": "2026-12-13T14:30:00Z",
      "issuing_organization": {
        "mip_identifier": "512ef14957203c6323e79937f3935708",
        "organization_legal_name": "Grand Lodge of Elsewhere"
      },
      "member_profile": {
        "member_number": "NY-123456",
        "prefix": "Mr",
        "first_name": "John",
        "middle_name": "William",
        "last_name": "Doe",
        "suffix": "Jr",
        "honorific": "RW",
        "rank": "PM",
        "birthdate": "1970-01-15",
        "years_in_good_standing": 25,
        "group_status": {
          "status": "Active",
          "is_active": true
        },
        "contact": {
          "email": "john.doe@example.org",
          "phone": "+1-555-123-4567",
          "cell": "+1-555-987-6543",
          "address": {
            "address1": "123 Main Street",
            "address2": "Apt 4B",
            "city": "Albany",
            "state": "NY",
            "postal_code": "12207",
            "country": "US"
          }
        },
        "affiliations": [
          {
            "local_name": "Masters Lodge No. 5",
            "local_number": "5",
            "local_location": "Albany, NY",
            "affiliation_type": "Full Member",
            "affiliation_status": "Active",
            "is_active": true,
            "level": "Primary",
            "date": "1999-03-15"
          }
        ],
        "life_cycle_events": [
          { "event_name": "Initiated", "date": "1999-01-10", "local_name": "Masters Lodge No. 5" },
          { "event_name": "Passed", "date": "1999-02-14", "local_name": "Masters Lodge No. 5" },
          { "event_name": "Raised", "date": "1999-03-15", "local_name": "Masters Lodge No. 5" }
        ]
      }
    }
  }
}
```

A reply declining the request:

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "shared_identifier": "0192b0c4-8e2f-7a6b-b1c3-4d5e6f708192",
    "status": "DECLINED",
    "reason": "No member matching this request was found."
  }
}
```

- **meta.succeeded**: `true`. A decline is a completed request, not a failure.
- **data.shared_identifier**: the identifier from the certificate request. It correlates the
  reply with the request and is not the certificate's identifier.
- **data.status**: the outcome of the request: `APPROVED` when a certificate was issued,
  `DECLINED` when the request was refused. It says nothing about the member's standing.
- **data.certificate**: present with `APPROVED`. The certificate itself; see
  [Certificate](#certificate). Its `good_standing` is the member's standing as certified,
  and an issued certificate MAY say `false`.
- **data.reason**: present with `DECLINED`. An explanation for a person to read.

#### Acknowledgement

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "acknowledged": true,
    "shared_identifier": "0192b0c4-8e2f-7a6b-b1c3-4d5e6f708192"
  }
}
```

- **data.acknowledged**: `true`. The reply was received and stored against the identifier.
- **data.shared_identifier**: the identifier from the reply.

The same errors apply as for a Member Search Reply: `400` `shared_identifier_missing`, `404`
`request_not_found`, `403` `connection_mismatch`, and `409` `request_already_answered`.

# Common Data Formats

All request and response payloads are JSON. Dates are `YYYY-MM-DD`; timestamps are ISO 8601
with a time zone. A field whose value the sender does not hold MAY be sent as `null` where
this document shows it so.

## Node Profile

A Node Profile describes one node. It is the value of `node_profile` in the Connection
Request, Connection Approved, and Organization Update payloads and their responses, and the
shape of each element of `nodes` in a Shared Nodes batch. A Node Profile never contains other
nodes.

```json
{
  "mip_identifier": "e82d40e9416304e8c72790b45b27a8e6",
  "mip_url": "https://mip.example.org/api/mip/node/e82d40e9416304e8c72790b45b27a8e6",
  "organization_legal_name": "Grand Lodge of Example",
  "contact_person": "John Smith",
  "contact_phone": "+1-555-123-4567",
  "organization_public_website": "https://www.example.org",
  "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqh...\n-----END PUBLIC KEY-----",
  "gdpr_metadata": {
    "controller": { "role": "controller", "name": "Grand Lodge of Example" },
    "processors": [
      { "name": "Example Software Vendor", "role": "processor" }
    ],
    "sub_processors": [
      { "name": "Example Cloud Hosting" }
    ],
    "gdpr_contact_name": "Jane Doe",
    "gdpr_contact_email": "privacy@example.org"
  }
}
```

- **mip_identifier**: REQUIRED. The node's MIP identifier.
- **mip_url**: REQUIRED. The node's URL, as specified under [Node URL](#node-url). HTTPS.
- **organization_legal_name**: REQUIRED. The organization's official name.
- **contact_person**: REQUIRED. The person to contact about MIP matters.
- **contact_phone**: REQUIRED. That person's telephone number.
- **organization_public_website**: OPTIONAL. The organization's public website.
- **public_key**: REQUIRED. The node's RSA public key in PEM format.
- **gdpr_metadata**: REQUIRED when a node describes itself, which it does in the Connection
  Request, Connection Approved, and Organization Update payloads and in the Connection
  Request and Organization Update responses. MUST NOT be present in a Shared Nodes element,
  where the profile describes a third node. See [GDPR Metadata](#gdpr-metadata).

`share_my_organization` is not part of the profile. It is a flag on the Connection Request
and Connection Approved payloads, because it is a term of the connection rather than a fact
about the node.

### Connection Attributes

Beside the Node Profile, the Connection Request and Organization Update responses carry three
attributes of the connection itself, under `data.mip_connection`:

- **status**: `PENDING`, `ACTIVE`, `DECLINED`, or `REVOKED`.
- **authentication_type**: how the connection came to be approved: `MANUAL` when a person
  approved it, `ENDORSEMENT` when it was approved by the web of trust. `null` while the
  connection is `PENDING` or `DECLINED`; a `REVOKED` connection keeps the value it had
  before revocation, which is `null` if it was never `ACTIVE`.
- **daily_rate_limit**: the number of requests per day the responding node accepts from this
  connection.

## GDPR Metadata

A node's GDPR metadata declares who is accountable for the member data it holds and who
processes that data on its behalf. It is the value of `gdpr_metadata` in a Node Profile.

```json
{
  "controller": { "role": "controller", "name": "Grand Lodge of Example" },
  "processors": [
    { "name": "Example Software Vendor", "role": "processor" }
  ],
  "sub_processors": [
    { "name": "Example Cloud Hosting" }
  ],
  "gdpr_contact_name": "Jane Doe",
  "gdpr_contact_email": "privacy@example.org"
}
```

- **controller**: REQUIRED. The organization accountable for the data.
  - **role**: `controller`.
  - **name**: the organization's name.
- **processors**: REQUIRED, MAY be empty. The parties that process the data on the
  controller's behalf, in order from the controller outward.
  - **name**: the party's name.
  - **role**: `processor`, or `joint_controller` when the party shares accountability with
    the controller.
- **sub_processors**: REQUIRED, MAY be empty. Parties engaged by a processor, such as a
  hosting provider.
  - **name**: the party's name.
- **gdpr_contact_name**, **gdpr_contact_email**: OPTIONAL, present only when set. The person
  to contact about data protection matters.

GDPR metadata is a self-declaration. A node MUST send its own GDPR metadata on every leg where
it describes itself, and MUST NOT send another node's. A receiver MUST NOT accept GDPR
metadata about a node from anyone but that node, and MUST NOT include it when relaying a
node's profile through Shared Nodes. The declaration is a statement the declaring organization
is accountable for; the receiver stores it as a dated record of what that node declared and
when. A copy passed on by a third party breaks that provenance and may be stale, and the
declaration names a contact person whom there is no basis to disclose to organizations the
declarant has no relationship with. Nothing is lost by the restriction: a node needs a peer's
processing chain only once it is about to exchange member data with that peer, and every
direct leg carries the declaration.

## Member Profile

The Member Profile gives comprehensive information about a member. It is the value of
`member_profile` in a [Certificate](#certificate).

```json
{
  "member_number": "NY-123456",
  "prefix": "Mr",
  "first_name": "John",
  "middle_name": "William",
  "last_name": "Doe",
  "suffix": "Jr",
  "honorific": "RW",
  "rank": "PM",
  "birthdate": "1970-01-15",
  "years_in_good_standing": 25,
  "group_status": {
    "status": "Active",
    "is_active": true
  },
  "contact": {
    "email": "john.doe@example.org",
    "phone": "+1-555-123-4567",
    "cell": "+1-555-987-6543",
    "address": {
      "address1": "123 Main Street",
      "address2": "Apt 4B",
      "city": "Albany",
      "state": "NY",
      "postal_code": "12207",
      "country": "US"
    }
  },
  "affiliations": [
    {
      "local_name": "Masters Lodge No. 5",
      "local_number": "5",
      "local_location": "Albany, NY",
      "affiliation_type": "Full Member",
      "affiliation_status": "Active",
      "is_active": true,
      "level": "Primary",
      "date": "1999-03-15"
    }
  ],
  "life_cycle_events": [
    { "event_name": "Initiated", "date": "1999-01-10", "local_name": "Masters Lodge No. 5" },
    { "event_name": "Passed", "date": "1999-02-14", "local_name": "Masters Lodge No. 5" },
    { "event_name": "Raised", "date": "1999-03-15", "local_name": "Masters Lodge No. 5" }
  ]
}
```

- **member_number**: the member's number in the issuing organization.
- **prefix**: name prefix (Mr, Mrs, Dr, Rev, and so on).
- **first_name**, **middle_name**, **last_name**: full legal name.
- **suffix**: name suffix (Sr, Jr, III, and so on).
- **honorific**: organizational honorific (W, VW, RW, MW, and so on).
- **rank**: past rank or office held (PM, PGM, PHP, PGHP, and so on).
- **birthdate**: date of birth.
- **years_in_good_standing**: the number of years the member has been in good standing, or
  `null` when the organization does not track it.
- **group_status**: the member's status in the issuing organization.
  - **status**: the status label as the issuing system stores it.
  - **is_active**: whether that status is an active membership status.
- **contact**: contact information, or `null` when the organization holds none.
  - **email**: primary email address.
  - **phone**: primary telephone number.
  - **cell**: mobile telephone number.
  - **address**: mailing address.
- **affiliations**: the member's local memberships. Only locals that still exist are listed.
  - **local_name**: name of the local lodge or chapter.
  - **local_number**: the local's number.
  - **local_location**: the local's city or location.
  - **affiliation_type**: the member's type in the local (Full Member, Entered Apprentice,
    Fellowcraft, and so on).
  - **affiliation_status**: the member's status in the local (Active, NPD, Demitted, and so
    on).
  - **is_active**: whether the affiliation is active.
  - **level**: `Primary`, `Secondary`, or `Dual`.
  - **date**: the date of affiliation.
- **life_cycle_events**: significant events in the member's history, in date order.
  - **event_name**: the event (Initiated, Passed, Raised, Affiliated, Demitted, and so on).
  - **date**: the date of the event.
  - **local_name**: the local where the event took place.

The labels in `status`, `affiliation_type`, `affiliation_status`, and `event_name` are the
issuing organization's own. The protocol does not standardize terminology across
organizations; the booleans beside the labels are what a receiving system can rely on.

## Certificate

A certificate of good standing is the issuing organization's statement, at a point in time,
of a member's standing. It is the value of `data.certificate` in an approved
[Certificate of Good Standing Reply](#certificate-of-good-standing-reply).

```json
{
  "shared_identifier": "0192b0c5-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
  "good_standing": true,
  "issued_at": "2026-09-14T14:30:00Z",
  "valid_until": "2026-12-13T14:30:00Z",
  "issuing_organization": {
    "mip_identifier": "512ef14957203c6323e79937f3935708",
    "organization_legal_name": "Grand Lodge of Elsewhere"
  },
  "member_profile": { "...": "see Member Profile" }
}
```

- **shared_identifier**: REQUIRED. A UUID minted by the issuer when the certificate is
  issued. It identifies the certificate itself, distinct from the identifier of the request
  that produced it, so that the certificate can be referred to later: to cite it when the
  member affiliates, or to ask the issuer whether it still stands. This version of the
  protocol defines no request that takes it; it is a reference.
- **good_standing**: REQUIRED. Whether the member is in good standing, as the issuing
  organization certifies it.
- **issued_at**: REQUIRED. When the certificate was issued.
- **valid_until**: REQUIRED. When the certificate ceases to be valid. How long a certificate
  stays valid is the issuing organization's policy; 90 days from issue is one reasonable
  choice.
- **issuing_organization**: REQUIRED. The issuer's `mip_identifier` and
  `organization_legal_name`.
- **member_profile**: REQUIRED. The member's [Member Profile](#member-profile) as of issue.

## Endorsement

An endorsement is one node's signed statement that another node's identity is genuine and
that a given public key belongs to it. Endorsements are the basis of the web of trust: a node
that presents endorsements from endorsers a receiver trusts can be connected without a person
at the receiver verifying its identity.

### Endorsement Document

The endorsement document is the JSON object the endorser signs:

```json
{
  "type": "MIP_ENDORSEMENT_V2",
  "endorser_mip_identifier": "512ef14957203c6323e79937f3935708",
  "endorsed_mip_identifier": "e82d40e9416304e8c72790b45b27a8e6",
  "endorsed_public_key_fingerprint": "963bb5ab26276a4fd5ef3f20a19a62621221428dce3640b09eb2e7bce7ceefa6",
  "issued_at": "2026-09-14T12:00:00Z",
  "expires_at": "2027-09-14T12:00:00Z"
}
```

- **type**: `MIP_ENDORSEMENT_V2`.
- **endorser_mip_identifier**: the MIP identifier of the node issuing the endorsement.
- **endorsed_mip_identifier**: the MIP identifier of the node being endorsed.
- **endorsed_public_key_fingerprint**: the [fingerprint](#public-key-fingerprint) of the
  endorsed node's public key. It binds the endorsement to one key, so that an endorsement
  cannot be presented with a different key.
- **issued_at**: when the endorsement was issued.
- **expires_at**: REQUIRED. When the endorsement expires. 365 days from issue is
  RECOMMENDED.

The endorser serializes the document once, as compact JSON with the fields in the order
shown, signs that exact string, and transmits the same string as `endorsement_document`.
A receiver verifies the string as received and MUST NOT re-serialize it before verifying.

### Public Key Fingerprint

The fingerprint of a public key is the SHA-256 digest of the key's DER-encoded
SubjectPublicKeyInfo, written as 64 lowercase hexadecimal characters with no separators.
That is the value carried in endorsement documents and payloads, and it is compared by
machines only.

The **display form** of a fingerprint is its first 16 bytes written as 16 colon-separated
pairs of lowercase hexadecimal characters:

```
96:3b:b5:ab:26:27:6a:4f:d5:ef:3f:20:a1:9a:62:62
```

The display form is what a person reads to another over the telephone when confirming a
pending connection request. A system MUST show the display form wherever it shows a
fingerprint to a person, so that both ends of the call see the same thing.

```ruby
require 'openssl'

key = OpenSSL::PKey::RSA.new(public_key_pem)
fingerprint = OpenSSL::Digest::SHA256.hexdigest(key.public_key.to_der)
display_form = fingerprint[0, 32].scan(/../).join(":")
```

### Full Endorsement Payload

Wherever an endorsement travels, in a Connection Request, a Connection Approved payload, a
Shared Nodes batch, or at the Endorsements endpoint, it takes this form:

```json
{
  "endorser_mip_identifier": "512ef14957203c6323e79937f3935708",
  "endorsed_mip_identifier": "e82d40e9416304e8c72790b45b27a8e6",
  "endorsed_public_key_fingerprint": "963bb5ab26276a4fd5ef3f20a19a62621221428dce3640b09eb2e7bce7ceefa6",
  "endorsement_document": "{\"type\":\"MIP_ENDORSEMENT_V2\",\"endorser_mip_identifier\":\"512ef14957203c6323e79937f3935708\",\"endorsed_mip_identifier\":\"e82d40e9416304e8c72790b45b27a8e6\",\"endorsed_public_key_fingerprint\":\"963bb5ab26276a4fd5ef3f20a19a62621221428dce3640b09eb2e7bce7ceefa6\",\"issued_at\":\"2026-09-14T12:00:00Z\",\"expires_at\":\"2027-09-14T12:00:00Z\"}",
  "endorsement_signature": "g2FAOk4wXU6+j85b+1kpz3kgRH+ZmFIk2YkNkCP5GP8l...",
  "issued_at": "2026-09-14T12:00:00Z",
  "expires_at": "2027-09-14T12:00:00Z"
}
```

- **endorser_mip_identifier**, **endorsed_mip_identifier**,
  **endorsed_public_key_fingerprint**, **issued_at**, **expires_at**: copied from the
  document so that a receiver can index the endorsement without parsing the document. Each
  MUST equal the value in the document.
- **endorsement_document**: the signed document, as a string.
- **endorsement_signature**: the endorser's RSA signature (RSASSA-PKCS1-v1_5 with SHA-256)
  over `endorsement_document`, Base64 encoded without line breaks.

### Issuing an Endorsement

```ruby
require 'openssl'
require 'base64'
require 'json'
require 'time'

issued_at = Time.now.utc
document = {
  :type => 'MIP_ENDORSEMENT_V2',
  :endorser_mip_identifier => my_mip_identifier,
  :endorsed_mip_identifier => their_mip_identifier,
  :endorsed_public_key_fingerprint => their_public_key_fingerprint,
  :issued_at => issued_at.iso8601,
  :expires_at => (issued_at + 365 * 24 * 60 * 60).iso8601
}.to_json

signature = Base64.strict_encode64(
  my_private_key.sign(OpenSSL::Digest::SHA256.new, document)
)
```

### Endorsement Verification

To verify an endorsement a receiver:

1. parses `endorsement_document` and checks that `type` is `MIP_ENDORSEMENT_V2`, or
   `MIP_ENDORSEMENT_V1` where the receiver accepts it;
2. checks that the identifiers, fingerprint, `issued_at`, and `expires_at` in the payload
   equal those in the document;
3. looks up the endorser among its `ACTIVE` connections. When the endorser is not an
   `ACTIVE` connection the receiver holds no key for it and the endorsement is
   **unverified**; verification stops here, and the endorsement is neither accepted nor
   rejected;
4. verifies `endorsement_signature` over `endorsement_document` with that connection's
   stored public key;
5. checks that `expires_at` is in the future;
6. checks that `endorsed_public_key_fingerprint` equals the fingerprint of the endorsed
   node's public key as the receiver holds it, or, in a Connection Request, as presented in
   the request.

An endorsement that passes every step is **verified**. One that fails step 1 or 2 is
**rejected** whoever the endorser is, since those checks need no key and such an endorsement
can never verify later. One that reaches step 4 and fails any step from there on is
**rejected**.

Because step 3 requires an `ACTIVE` connection, an endorser whose connection is later revoked
stops verifying without any further rule: its endorsements become unverified on the node that
revoked it, and no longer count for anything there.

A receiver SHOULD also accept the earlier document type `MIP_ENDORSEMENT_V1`, whose
fingerprint is the MD5 digest of the SubjectPublicKeyInfo written as 16 colon-separated
hexadecimal pairs, and verify it by the same steps with that fingerprint algorithm in step 6.
A node MUST issue `MIP_ENDORSEMENT_V2`.

### Endorsement Storage

A stored endorsement is in one of two states, **verified** or **unverified**, as determined
above. A receiver stores verified endorsements, and stores unverified ones so that they can be
verified later, when the endorser becomes an `ACTIVE` connection. At that moment each
unverified endorsement from that endorser is verified; those that pass become verified and
those that fail are deleted. A rejected endorsement MUST NOT be stored.

Endorsements are keyed by the pair (endorser, endorsed node). Storing a newly received
endorsement MUST NOT let an unverified endorsement replace a verified one for the same pair,
and MUST NOT clear a revocation the receiver has recorded against that pair. Otherwise any
connected node could, by sending a bad endorsement in a third party's name, erase a good one.

Only verified endorsements count toward automatic approval, and only verified endorsements are
relayed through Shared Nodes.

### Web of Trust

The web of trust is a single hop. A node trusts an endorsement only when it holds the
endorser's key through its own `ACTIVE` connection with the endorser, and verified the
signature itself. Trust is never transitive: that A trusts B and B endorses C does not make C
trusted by A beyond what A's own policy says about endorsements from B, and it never causes A
to endorse C.

Within that model each node decides for itself:

- which of its `ACTIVE` connections' endorsements count toward automatic approval;
- how many such endorsements a request needs;
- whether to attempt connections to nodes it learns of through Shared Nodes, and on what
  conditions.

Endorsements expire and are renewed by issuing a new one to the Endorsements endpoint. A node
that revokes a connection ceases to verify that node's endorsements, as described under
Endorsement Verification.

# MIP 2.1 Proposed Ideas

The following are candidates for a future version. Nothing in this section is part of MIP
2.0, and a conforming node implements none of it.

## Member Status Check

A quick query to get the current status of a known member, without a full member profile.

### Endpoint: `<mip_url>/member_status_checks`

### HTTP Action: POST

### Request Payload

```json
{
  "member_number": "NY-123456"
}
```

- **member_number**: The publicly known member number for the member in the receiving
  organization.

### Response Payload

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "member_number": "NY-123456",
    "member_type": "Master Mason",
    "party_short_name": "John D",
    "group_status": {
      "status": "Active",
      "is_active": true,
      "good_standing": true
    }
  }
}
```

- **data.member_number**: The member's identifier in the responding organization.
- **data.member_type**: The member's type/degree (e.g., Master Mason, Fellowcraft).
- **data.group_status**: The member's status at the group (jurisdiction) level.
  - **status**: The status label as stored in the system.
  - **is_active**: Boolean indicating if this is an active membership status.
  - **good_standing**: Boolean indicating if the member is in good standing.

If the member is not found:

```json
{
  "meta": {
    "succeeded": true
  },
  "data": {
    "found": false,
    "member_number": "NY-123456"
  }
}
```

The primary purpose for this particular endpoint is to support built-in status checkers to
be able to check the status of members in an organization's mobile app. Think of it as the
Tyler's integration. For example, an organization that uses Vendor A. Vendor A could present
an organization from Vendor B, have the person punch in the member number for the
organization B, and then they could use this MIP connection point to talk to organization B
and check that member status in real time. The contractual expectation for this endpoint is
that these are not queued and approved. These are strictly replied to in real time. Many of
the organizations already have a status checker that effectively does this on their websites.
This basically allows a third party to do the same thing within their own application.

This could also potentially be used as a means for when someone is visiting another
organization and the vendors are different and they present a digital dues card, we could
potentially use this for integration to be able to query that on the other organization
through our backend instead of going directly and having to trust the credentials being put
in front of us.

## Member Scan

A lightweight overlap check, not a search. The requester sends a batch of up to 100
`{first_name, last_name, birthdate}` triples and the responder answers, per triple, only
whether it has a match. No member data comes back. The purpose is to let two organizations
discover whether they have overlapping or candidate-overlapping members before anyone files
individual searches.

## Organization-to-Organization Messages

A messaging leg unrelated to any member request: "I have a question, can you get me an
answer?" Optionally linked to an outstanding item such as a certificate of good standing
request by its `shared_identifier`, but not dependent on one.

## Requesting Member Profile on a Certificate Request

When the requesting organization holds a full member record for the person, it could append a
`requesting_member_profile` in the Member Profile format to the certificate request, so the
responding side has more to match against. It would be absent when the person is not yet a
member on the requesting side.

## Linked Members

There are many potential functions that could be added to MIP such as:

- Member linking between organizations
- Automatic notification of linked member status change
- Automatic notification of linked member contact information change
- Automatic display of linked member status in member profiles

An important differentiator of using MIP over having a centralized clearing house is there
would be no need for a third-party to store the connections between members in discrete
member databases. All of those connections would ONLY be housed with the organizations the
member belongs to. No need to trust a third-party with your data in any way at all.
