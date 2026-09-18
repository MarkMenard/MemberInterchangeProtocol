# Changes from MIP 1.0 to 2.0

This document is for anyone who has built, or started building, against `MIP_1_0.md`. It
lists every place where `MIP_2_0.md` differs from 1.0, says why the change was made, and,
where the change breaks a 1.0 implementation on the wire, says what to change. Changes marked
**breaking** alter a request or response that a 1.0 node sends or expects; a 1.0 node that
does not adopt them will fail against a 2.0 node. Changes marked **additive** add fields,
rules, or endpoints that a 1.0 node can ignore or adopt at leisure. Changes marked
**clarification** write down what 1.0 left unsaid; a 1.0 node that already behaves this way
needs nothing.

Two general changes apply throughout. The 2.0 text uses the RFC 2119 keywords, so every
"MUST" and "SHOULD" is deliberate. And 2.0 is the current specification rather than a
preliminary draft: the reference implementations in this repository and the Ruby gem still
target 1.0 and do not conform to 2.0 until they are updated.

## Transport and Request Rules

### Node URL and endpoint paths (clarification)

**What changed.** 1.0 wrote endpoints as `/mip/node/<receiving_mip_id>/…` while its examples
used prefixes such as `/api/mip/organizations/` and `/integrations/mip/groups/`. 2.0 states
the rule: a node's `mip_url` is any vendor-chosen HTTPS prefix followed by
`/mip/node/<mip_identifier>`, and every endpoint is a path suffix appended to the receiver's
`mip_url`. There is no `GET` endpoint and nothing is served at the node URL itself.

**Why.** There never was a fixed prefix; the vendor chooses it. Senders that built URLs from
the receiver's `mip_url` already did the right thing, and the inconsistent examples were
the only problem.

### `X-MIP-ENVIRONMENT` header (breaking)

**What changed.** A fourth required header, `X-MIP-ENVIRONMENT`, names the sender's
environment. Live nodes send `production`. A receiver answers `403` `environment_mismatch`
when the value differs from its own environment. The header is not part of the signed
document.

**Why.** Vendors routinely run development and test copies of a live database, with the same
node identifier and key. Without this header an approval, a search, or a certificate could
cross from a test copy into the live network and be indistinguishable from the real thing.
The header makes such a request fail at the door.

**What to change.** Send the header on every request; reject requests whose value differs
from your own environment.

### Best practices with fixed status codes (additive)

**What changed.** 1.0 named replay protection as a property of the signature and said nothing
about timestamp windows, request size, or rate limiting beyond advertising
`daily_rate_limit`. 2.0 recommends all four as best practices, leaves the values to each
node, and fixes the response a node uses when it enforces one: `401`
`timestamp_out_of_window`, `401` `replay_detected`, `413` `request_too_large`, and `429`
`rate_limit_exceeded` with a `Retry-After` header. A Connection Request is never rate
limited.

**Why.** Each of these is a defensive measure a node may or may not take, so none is a wire
rule. But when a node does take one, the other side needs a standard answer it can act on:
a client that receives `429` with `Retry-After` knows to wait, whereas an unexplained
failure looks like an outage. Connection Requests are exempt from rate limiting because
before a connection exists there is no agreement about what the limit would be.

### Error responses (breaking for error handling)

**What changed.** 1.0 defined nothing about errors beyond `meta.succeeded`. 2.0 defines the
error response: an HTTP `4xx` or `5xx` status, never an empty body, never `200` with
`succeeded: false`; errors listed in `meta.errors` as `{code, message, field?}` with codes
from a fixed catalog; and a fixed order of checks so that a request with several faults
gets a predictable answer.

**Why.** Two implementations cannot interoperate on errors when the specification does not
say what an error looks like. 1.0 permitted both empty bodies and `200` with
`succeeded: false`, and a client could not tell a signature problem from a validation problem from an outage. The catalog
gives clients something to branch on, and the fixed order means a bad signature is reported
as a bad signature and not as whatever check happened to run first.

**What to change.** Answer every failure with the 2.0 status code and body; parse
`meta.errors[].code` rather than `data.errors` or the message text.

### `X-MIP-PUBLIC-KEY` header (clarification)

**What changed.** 1.0 said the header is used "where the receiving organization does not
know the RSA public key of the originating system ahead of time". 2.0 names the request: it
is sent on a Connection Request and on no other, a Connection Declined included. A Declined
is verified with the responder's key, which the requester holds from the Connection Request
response, as an Approved is.

**Why.** "Does not know ahead of time" left each implementer to decide when that was. Naming
the one request removes the guesswork. A 1.0 requester that lost the Connection Request
response holds no key and answers a Declined `401` `sender_unknown`; it recovers by
re-sending its request, which is the rule everywhere else, rather than by accepting a
decline from a node it has no record of asking.

**What to change.** Stop sending the header on a Connection Declined; verify a Declined with
the stored key.

### RSA key size (additive)

**What changed.** 1.0's example generated a 2048-bit key and stated no requirement. 2.0
requires at least 2048 bits, recommends 4096, and caps keys at 8192 bits. A Connection
Request whose key is outside the bounds is answered `422` `public_key_size_invalid`.

**Why.** A minimum keeps weak keys out of the network. A maximum keeps the signature and
public key headers, and the keys and endorsements carried in a Shared Nodes batch, within
common per-header and per-request size limits.

### MIP identifier (clarification)

**What changed.** 1.0 prescribed an MD5 of a random UUID and a salt. 2.0 requires 32
lowercase hexadecimal characters generated so that a collision is negligibly likely, and
keeps the MD5 recipe as one example of producing such a value.

**Why.** The requirement was always uniqueness; the hash recipe was only ever a way to get
there.

## Connection Protocol

### Connection Request payload (breaking)

**What changed.** 1.0's request was flat: `mip_identifier`, `mip_url`, `public_key`,
`organization_legal_name`, `contact_person`, `contact_phone`, `share_my_organization`,
`endorsements`. 2.0's request is `{node_profile, share_my_organization, endorsements}`, where
`node_profile` is the requester's Node Profile and carries two fields 1.0 did not have:
`gdpr_metadata` (required) and `organization_public_website` (optional).

**Why.** The other legs that describe a node (approved, update) already nested a
`node_profile`; the request was the odd one out, and every implementer had to build the
same node description two ways. `gdpr_metadata` is the reason 2.0 exists: organizations
subject to data protection law need to know, before exchanging member data, who is
accountable for a peer's data and who processes it. Implementations built against 1.0 are
early enough that adding the field now costs little.

**What to change.** Nest the connection request fields under `node_profile` and add
`gdpr_metadata` and `organization_public_website`; expect the same shape from requesters.

### Connection Request response (additive)

**What changed.** `data.mip_connection` keeps 1.0's `status`, `daily_rate_limit`, and
`node_profile`, and adds `authentication_type`, whose values and rules are stated once under
Connection Attributes (`ENDORSEMENT` when approved on the spot, `null` for a new request that
awaits a person). `status` gains a fifth value, `REOPENED`; see Repeated Connection
Requests below. The response also adds `share_my_organization`, the responder's answer to
whether the requester may tell other nodes about it, which 1.0 carried only on Connection
Approved; a connection approved on the spot by endorsement never receives that request and
had no way to learn it. The responder's `node_profile` carries its `gdpr_metadata`. The
`daily_rate_limit` is the limit the responder applies, not a placeholder. The response never
carries an endorsement or a list of other nodes.

**Why.** `authentication_type` lets the requester record how it was approved instead of
guessing. Endorsements and node lists have their own legs (see Endorsement issuance and
Shared Nodes below), and carrying them here as well duplicated them.

### Repeated Connection Requests (clarification)

**What changed.** 1.0 said only that a declined requester may request again. 2.0 makes a
Connection Request idempotent per requesting identifier: the receiver always answers `200`
with the connection's current status and never creates a second record. `PENDING`,
`REOPENED`, and `ACTIVE` connections are unchanged by a repeat; a `DECLINED` or `REVOKED`
connection becomes `REOPENED`, a new fifth status, and is presented to a person, whichever
node revoked it and whichever node made the original request. The request that reopens a
record refreshes the stored profile and stores the presented endorsements, since a non-active
record has no other way to be brought current; every other repeat changes nothing, so a
request sent only to ask what the other side holds has no side effects. Reopening assigns
the roles afresh: the node that sent the reopening request is the one that asked, and only
the receiving node's person decides; see Connection notifications from the wrong side
below. `REOPENED` is `PENDING` without automatic
approval: it is never approved on the spot or later by an endorsement, because a person
declined or revoked it and only a person approves it again, and the same notifications move
it to `ACTIVE`, `DECLINED`, or `REVOKED`. It is a status rather than a private mark so that
the rule travels on the wire and the requester can see a person will review. A repeat
presenting a different public key is
answered `422` `public_key_mismatch` and changes nothing. A node may block a node it has
declined or revoked; a repeat from a blocked node changes nothing and is answered with the
current status, `DECLINED` or `REVOKED`. The block is a local mark, not a status, and is
never sent; a node that sends its own Connection Request to a node it has blocked should
clear the block as part of sending. Because the answer is the receiver's current status,
either node may send a repeat to learn what the other holds, and the node that sent it must
record the status reported.

**Why.** Without a rule, a repeated request either failed or created a duplicate. Reopening a
declined connection on the same record keeps its history in one place. Reopening a revoked
one the same way is how a revoked connection comes back now that Connection Restored is
gone; see Connection Revoked below. Blocking is what lets a node refuse a persistent
requester without a person reviewing the same request again each time; it stays local and
off the wire so that it adds no state to the protocol and no history to the record.
Refusing a different key is a security rule: otherwise a repeated request could swap in a
new key before anyone had verified the old one over the telephone. Key rotation is left for
a later version, and the 2.0 document's "Key Rotation" entry under MIP 2.1 Proposed Ideas
records why it cannot wait longer than that: a node that rotates its key after a connection
is declined or revoked has no way in 2.0 to reopen it.

### Public key on a Connection Request (breaking, security)

**What changed.** On a Connection Request the key in `X-MIP-PUBLIC-KEY` and the
`public_key` in `node_profile` must be the same key, compared as parsed keys. A mismatch is
`422` `public_key_mismatch` and nothing is stored.

**Why.** The signature is verified with the header key, but the key a receiver stores comes
from the body. If nothing checks that they match, an attacker can sign a request with their
own key, put a victim's public key in the body, and present the victim's genuine
endorsements. The endorsements verify against the body key, the connection auto-approves,
and the receiver goes on to send its approval, its endorsement, and its whole shared-node
list to the attacker's URL, while the real victim is locked out because a repeated request
just echoes `ACTIVE`.

**What to change.** Compare the two keys on every Connection Request before storing
anything.

### Connection Approved payload (breaking)

**What changed.** 1.0's payload was `{node_profile, share_my_organization,
daily_rate_limit, endorsement, known_organizations}`. 2.0's is `{node_profile,
share_my_organization, daily_rate_limit, authentication_type, endorsement}`.
`known_organizations` is gone; the approver pushes its known nodes afterward through
Shared Nodes. `authentication_type` is new and required. `endorsement` is present only when
a person approved the connection (`MANUAL`) and absent otherwise. `node_profile` carries the
approver's `gdpr_metadata`.

**Why.** The list of known nodes needed a size limit, endorsements per node, and a way to
send more later, none of which fit inside an approval. Shared Nodes does all three, and a
node that receives its known nodes that way after an approval gets the same discovery it
had before. `authentication_type` is new because 1.0 did not distinguish how a connection
was approved; 2.0 makes that distinction explicit in the protocol so both sides share a
common record. The endorsement rule follows from the issuance rules below.

**What to change.** Stop reading `known_organizations` from the approved payload; expect a
Shared Nodes push instead. Send and record `authentication_type`. Send `endorsement` only
on a manual approval.

### Connection notifications from the wrong side (breaking, security)

**What changed.** A node accepts a Connection Approved or Connection Declined only for a
connection it asked for, and only from the other node. A node MUST NOT accept either from a
node that requested the connection from it, unless it has also requested the connection from
that node. One from the wrong side is answered `403` `connection_mismatch`, whatever state
the record is in, and changes nothing. The meaning of `connection_mismatch` widens from "a
reply from a connection other than the one that made the request" to "the sender is not the
node entitled to send this on this connection", covering both. Every node already knows
whether it asked, since it sent or received the request, and records that with the
connection. Two nodes that request each other at once have both asked, so both accept, and
the second approval to land is answered `200` unchanged.

**Why.** 1.0 and the earlier 2.0 text moved a record on its source state alone, so a
requester could send the node it had asked a Connection Approved and be made `ACTIVE`, with
whatever `authentication_type` and endorsement it chose, without a person at that node ever
looking at the request. Manual approval is the identity check on a requester, and the text
let the requester perform it. The statuses carry no direction; the nodes do, and the rule
makes that a check rather than an assumption.

**What to change.** Record, with each connection, whether you asked for it. Refuse an
Approved or Declined for a connection you did not ask for, or from any node but the other
side of it, with `403` `connection_mismatch`, before the state check.

### Manual approval supersedes approval by endorsement (additive)

**What changed.** `MANUAL` supersedes `ENDORSEMENT` and nothing supersedes `MANUAL`, on
whichever node holds the value. An `ACTIVE` record held as `ENDORSEMENT` that receives a
Connection Approved carrying `MANUAL` is upgraded in place: it takes the new
`authentication_type`, `daily_rate_limit`, and `share_my_organization` and stores the
endorsement. Every other Approved on an `ACTIVE` record still changes nothing. An upgrade
triggers no discovery push and the requester issues no second endorsement. A manual decline
cancels a pending background retry of an approval by endorsement; a manual approval need
not.

**Why.** While the recommended background retry of an approval by endorsement runs, the
approver's record is still `PENDING` and still in front of a person, who may approve it
manually; the two approvals then race, and without a precedence rule the two nodes could end
holding different values. Precedence settles the race in either order. It also gives a
person a way to vouch for a connection the web of trust made, which 1.0 did not have: the
approver's endorsement is delivered by the upgrading approval.

**What to change.** Never let `ENDORSEMENT` overwrite `MANUAL`. Apply a `MANUAL` approval to
an `ACTIVE` record held as `ENDORSEMENT`. Cancel a pending retry on a manual decline.

### Notification delivery (additive)

**What changed.** 2.0 adds a Notification Delivery section covering Connection Approved,
Declined, and Revoked. A sender of an Approved or Declined records the state change only
when the receiver answers `200`; on a `5xx`, a `429`, or no response nothing changes and the
person is told to try again later. A sender of a Revoked is the exception: it marks its own
record `REVOKED` when the person acts, whatever the other node then answers. There is no
automatic retry, and a node must let a person re-send any notification; the one recommended
exception is an approval made by endorsement, where no person is waiting, which a node
should retry in the background for a bounded period. A Revoked is never retried in the
background. Each notification names a source state and a target state: a record in the
source state moves to the target; a record already in the target state is answered `200`
unchanged; a record in neither is answered `409` `connection_state_invalid`, which now
carries the receiver's current `status` and which the sender must not act on. `403`
`connection_not_active` also carries the receiver's current `status`, and the sender must
record it. A repeated Connection Request doubles as the way to learn what the other node
holds. Connection Revoked joins the endpoints exempt from the active-connection check in the
order of checks.

**Why.** Each notification is one request whose reply can be lost, so the two nodes can
disagree. An earlier draft of 2.0 required the receiver's record to be in exactly one state
and had the sender change its record before sending; a lost approval then left the approver
`ACTIVE` and the requester `PENDING` with no valid move on either side. Changing the record
only on `200` means the sender's record never runs ahead of what the receiver has
confirmed, and idempotent receivers mean the one case that remains, a lost reply, is
repaired by the person doing the action again. Revoked is exempt because the rule exists to
stop a sender running ahead into a state the receiver may reject, and Revoked is the one
notification the receiver never rejects; recording it only on `200` would have left a node
revoking an unreachable or hostile peer still honoring that peer's requests until the peer
answered. The undelivered revocation this leaves behind is repaired by the same re-send, and
the `status` on a `403` lets the revoked node learn `REVOKED` on its next ordinary request
without reopening the record. Automatic retries were considered and rejected: they force
every state change into a background job, need a lock on the connection while a retry is
outstanding, and create ordering hazards between notifications sent in quick succession. A
person re-sending needs none of that, and the same receiver behaviour makes it safe. A
Revoked in particular is not retried because a revocation sent by mistake that then failed
would keep trying with no way for the person to stop it. The `409` and the `403` are
treated differently because a `409` answers an action a person is in the middle of, so the
person should see the conflict and choose, while a `403` answers a routine request with no
one mid-action, so the node may simply catch up.

**What to change.** Record an Approved or Declined on `200`, not before; record a Revoked at
once. Answer `200` rather than an error when a notification finds the record already in its
target state. Put the current `status` on a `409` and on a `403` `connection_not_active`,
and record the status a `403` reports.

### Connection Declined (clarification)

**What changed.** The payload is unchanged: `{mip_identifier, reason?}`. 2.0 states the
states: from `PENDING` or `REOPENED` to `DECLINED`, idempotent on `DECLINED`, otherwise `409`
`connection_state_invalid`; and that the response carries `status` only. It is accepted only
by the node that asked; see Connection notifications from the wrong side above.

**Why.** The state rule was implied and is now stated so that every implementation answers
the same way.

### Connection Revoked (breaking) and Connection Restored (removed)

**What changed.** Connection Revoked keeps 1.0's payload and is accepted from any state: the
record becomes `REVOKED`, is kept rather than deleted, and the request is never answered
`409`. The revoking node marks its own record `REVOKED` before sending, whatever the
response; see Notification delivery above. Revoked is exempt from the active-connection
check. Connection Restored is removed. A revoked connection comes back through a Connection
Request from either node, which reopens the record as `REOPENED` for the other node to
approve or decline; see Repeated Connection Requests above. Revoking a connection revokes no
endorsement, on either side; that is a separate step, see Endorsement Revoked below.

**Why.** 1.0 let the receiver delete the record on revocation, which let a repeated
connection request from the revoked node start fresh; keeping the record keeps its history
in one place. Accepting a revocation from any state gives a node one move that always
works, so that two nodes whose records have diverged for any reason can be brought back to
a known state without anyone editing a status by hand. Restored was 1.0's way for the
revoking node to bring the connection back on its own, and it had three problems. It
carried nothing, so a record could become `ACTIVE` without the profile, rate limit, GDPR
snapshot, or endorsement an approval gives it. It required the receiver to know which node
had revoked, which 1.0 never told it. And when both nodes had revoked, neither side's
Restored could be accepted without overriding the other's decision. Routing the return
through a Connection Request removes all three at once: the record is rebuilt by an
approval, no rule depends on who revoked, and both organizations decide again, one by
asking and one by approving. The price is that a revoker cannot switch the connection back
on alone, which is the right price for an act as serious as revocation.

**What to change.** Accept Revoked from any state. Stop serving and sending
`/mip_connections/restored`; a node that wants a revoked connection back sends a Connection
Request.

### Organization Update request (clarification)

**What changed.** The request is `{node_profile}` as in 1.0, now with `gdpr_metadata` inside
the profile. 2.0 lists what an update may change (`organization_legal_name`,
`contact_person`, `contact_phone`, `organization_public_website`, `mip_url`,
`gdpr_metadata`) and states that it never changes `mip_identifier` or `public_key`; a
receiver ignores those two fields in an update.

**Why.** A node's identity is its identifier and its key. Letting an update change either
would let any connected node's compromise be turned into a key swap. Changing a key is left
for a later version.

### Organization Update response (breaking)

**What changed.** 1.0 returned `data.node_profile`. 2.0 returns `data.mip_connection` in the
same shape as the Connection Request response: `status`, `authentication_type`,
`daily_rate_limit`, `share_my_organization`, and the receiver's `node_profile` with its
`gdpr_metadata`.

**Why.** One response shape for "here is this connection and the node behind it" instead of
two. The connection attributes cost nothing to include and let the sender refresh its rate
limit and status at the same time.

**What to change.** Read the receiver's profile from `data.mip_connection.node_profile`.

### Endorsement issuance (breaking, security)

**What changed.** 1.0 had both parties exchange endorsements whenever a connection became
active, whether a person approved it or the web of trust did. 2.0 issues an endorsement only
on a decision by a person: the approver's endorsement is issued on a manual approval and
delivered in the approved payload; the requester's endorsement of the approver is issued once
a connection a person requested becomes `ACTIVE`, however it was approved, and sent to
`/endorsements`. An approval by endorsement issues none, and a connection request a node
sent on its own initiative earns none. Either side may also endorse an existing `ACTIVE`
connection at any time by sending to `/endorsements`, which is also how an endorsement is
renewed; an approver vouching for a connection the web of trust made sends a `MANUAL`
Connection Approved instead, which upgrades the connection and delivers the endorsement in
one step (see Manual approval supersedes approval by endorsement above).

**Why.** An endorsement is one organization vouching for another. When a node approves a
request because a trusted endorser vouched for the requester, that node has verified nothing
itself; issuing its own endorsement on that basis makes endorsements transitive ("B vouches
for A, I trust B, so I vouch for A"), and a single lax endorser could then propagate trust
across the whole network with nobody checking anything. The web of trust is single hop
precisely to prevent that. Limiting issuance to human decisions keeps it so.

**What to change.** Issue an endorsement on manual approval and on activation of a
person-initiated request only; do not issue one from an automatic approval.

## Discovery

### New Organization Notification (breaking)

**What changed.** The `/new_organization_notification` endpoint is removed. Announcing a
newly approved node to other connections is a Shared Nodes push carrying one node.

**Why.** The notification was a Shared Nodes batch of one with a different name and no
endorsements. One mechanism for telling a peer about a node is easier to implement and to
reason about than two.

**What to change.** Remove the endpoint; send and accept the announcement as a Shared Nodes
batch of one.

### Connected Organizations Query (breaking)

**What changed.** The `GET /connected_organizations_query` endpoint, which returned the list
in its response, is replaced by `POST /request_shared_nodes` with an empty body. The response
is `{acknowledged: true, shared_node_count: N}`, and the nodes arrive afterward through Shared
Nodes in batches.

**Why.** Returning the whole list in one response has no size bound and no way to carry
endorsements, and it was the protocol's only `GET`, which complicated the signature rule.
Answering with a count and then pushing through the one discovery mechanism keeps every
request signed the same way and every list bounded. The count tells the requester what to
expect.

**What to change.** Replace the query with the share request; receive the list through
Shared Nodes.

### Shared Nodes (additive)

**What changed.** A new endpoint, `POST /shared_nodes`, carries `{nodes: [...]}` where each
element is a Node Profile without `gdpr_metadata` plus that node's `endorsements` and
`revocations`, both required and either possibly empty. A batch holds at most 20 nodes; each
element carries at most five documents, endorsements and revocations together, all verified
by the sender, the sender's own first and the rest newest first. A revocation is relayed in
place of the endorsement it cancels, which is not relayed. The receiver acknowledges with
`{acknowledged: true}`. It is used after an approval (the approver's known nodes to the new
connection; the new connection to the approver's other sharing connections) and in answer to
a share request. Only `ACTIVE` connections that set `share_my_organization` may be shared.

**Why.** Discovery needed one mechanism with a size bound and endorsements attached, so that
a node learning of another can also learn who vouches for it, and who has withdrawn a
vouching. Twenty nodes with five documents each and 8192-bit keys fit within a 256 KiB
request; a revocation is smaller than the endorsement it replaces, so the bound holds
whatever the mix. Relaying only verified documents means the receiver is never handed one
the sender itself could not check.

## Endorsements

### Endorsement document version and fingerprint (breaking)

**What changed.** The document type is `MIP_ENDORSEMENT_V2`. The fingerprint in the document
and in the payload is the SHA-256 digest of the key's DER-encoded SubjectPublicKeyInfo as 64
lowercase hexadecimal characters. A separate display form, the first 16 bytes as
colon-separated pairs, is what people read to each other over the telephone. `expires_at` is
required. Nodes issue V2; receivers are asked to keep accepting `MIP_ENDORSEMENT_V1`, whose
fingerprint was the MD5 digest as 16 colon-separated pairs.

**Why.** MD5 is deprecated and will be flagged by anyone reviewing the protocol, even though
riding a victim's endorsement would need a second preimage, which MD5 still resists. The
constraint on any replacement was that it stay readable over the telephone, and a full
SHA-256 is too long for that. Separating the machine value from the display form gives
machines the full digest and people a value the same length as before with 128 bits of
strength. Accepting V1 lets nodes built against 1.0 keep working while they move.

**What to change.** Issue `MIP_ENDORSEMENT_V2`; keep accepting V1; show the display form
wherever a fingerprint is shown to a person.

### Endorsement verification and storage (breaking, security)

**What changed.** 1.0 verified an endorsement if the endorser was "a known entity (active
connection or shared node)" and stored it. 2.0 requires the endorser to be an `ACTIVE`
connection, since only then does the receiver hold its key. A received endorsement has three
outcomes: **verified** (endorser is an `ACTIVE` connection and every check passes),
**unverified** (endorser is not an `ACTIVE` connection; stored so it can be verified when
the endorser connects, and checked then), or **rejected** (the key is held and a check
fails; not stored). A stored endorsement is in one of three states, verified, unverified,
or revoked, the third being the result of an Endorsement Revoked (see below). Endorsements
and revocations are keyed by (endorser, endorsed node, fingerprint), and an unverified
document never replaces a verified endorsement or a verified revocation for the same key. At
`/endorsements` the sender must be the endorser (`400` `endorsement_sender_mismatch`) and a
failed check is `400` `endorsement_invalid`; a third party's endorsement is accepted only
inside a Connection Request or a Shared Nodes batch. Only verified endorsements count
toward automatic approval or are relayed.

**Why.** A "known shared node" is a node the receiver has only heard about; its key came from
a third party and cannot anchor a signature check. Storing endorsements regardless of
outcome, keyed by endorser and endorsed node, let any connected node send a bad endorsement in
a trusted endorser's name and overwrite the genuine one, clearing its verification and
un-revoking it. Requiring the sender at `/endorsements` to be the endorser closes the
impersonation route; the storage rules close the overwrite route.

**What to change.** Look endorsers up among `ACTIVE` connections only; do not store rejected
endorsements; never let an unverified endorsement overwrite a verified endorsement or a
verified revocation; check that the sender at `/endorsements` is the endorser.

### Endorsements endpoint response (breaking)

**What changed.** `data.endorsement_id` is removed; the response is `{acknowledged: true}`.
Re-sending an identical endorsement is answered `200` and changes nothing. A verified
endorsement may complete a pending connection, in which case the approver sends Connection
Approved with `authentication_type` `ENDORSEMENT` and no `endorsement`; a `REOPENED` record
is not eligible.

**Why.** An endorsement is named by its endorser, endorsed node, fingerprint, and
`issued_at`, all inside the signed document, so a receiver-minted identifier was a second
name for a thing that already had one, and the sender never referred to it again. The
member protocol made the same move in 2.0 when it had the requester generate
`shared_identifier`. Renewals and retries re-send endorsements; making that idempotent
avoids duplicates. 1.0 stated the late-approval path without its payload; 2.0 gives the
payload.

**What to change.** Stop reading `data.endorsement_id`; expect `data.acknowledged`.

### Endorsement Revoked (additive endpoint; breaking for Shared Nodes)

**What changed.** An endorser can withdraw an endorsement it issued. A revocation is a
signed document of type `MIP_ENDORSEMENT_REVOCATION_V2` with the endorsement document's
fields less `expires_at`, since a revocation does not expire, carried in the same full
payload form as an endorsement with `revocation_document` and `revocation_signature`. The
endorser sends it to each of its `ACTIVE` connections at a new endpoint,
`POST /endorsement_revoked`, where the sender must be the endorser (`400`
`endorsement_sender_mismatch`) and a failed check is `400` `endorsement_invalid`; the
response is `{acknowledged: true}`. It is relayed onward in Shared Nodes in place of the
endorsement it cancels. It is not sent to the endorsed node. A revocation is verified by the
endorsement steps without the expiry check and has the same three outcomes. For each
(endorser, endorsed node, fingerprint) the verified document with the latest `issued_at`
decides: a later revocation marks the endorsement revoked, a later endorsement reinstates
it, an older copy of either changes nothing, and the revocation wins a tie. A revoked
endorsement counts for nothing and is not relayed. A revocation is not retroactive: a
connection already approved on its strength stays `ACTIVE`. Revoking a connection revokes no
endorsement and revoking an endorsement revokes no connection; a system may offer a person
both in one act, but they are two requests on the wire.

**Why.** 1.0 had no way to withdraw a vouching. Revoking the connection stopped verification
only on the revoking node; every node that had received the endorsement second-hand through
discovery kept counting it until it expired, and the 2.0 text referred to a "revoked"
endorsement in its storage and relay rules without ever saying how one came to be. The
ordering needs no new field because `issued_at` is already inside the signature, both
documents come from the same node so the comparison never crosses clocks, and only the
endorser holds the key, so only the endorser can set either timestamp, and the endorser is
the authority on its own vouching. The withdrawal travels the same single hop as the trust:
a node acts on a revocation only when it verified the endorser's signature itself. The two
revocations are kept separate because they reach different audiences: a connection
revocation goes to the one node being cut off, an endorsement revocation to everyone who
holds the endorsement, and it must still travel after the endorsed node has ceased to be a
connection.

**What to change.** Serve `/endorsement_revoked`; store and order revocations with
endorsements by the triple and `issued_at`; send and accept `revocations` in every Shared
Nodes element and share the five-document cap between the two arrays; stop counting and
relaying a revoked endorsement.

## Member Protocol

### Member Search Request (breaking)

**What changed.** `documents` is removed. `shared_identifier` is required and generated by
the requester. A request must contain `member_number`, or both `first_name` and
`last_name`, and may contain all three; `birthdate` is optional and recommended with a name;
a request meeting neither minimum is `422` `validation_failed`. Unfilled fields are omitted,
not sent as `null`. `notes` stays. How the responder matches is its own business; matching on
either the number or the name when both are sent is given as a best practice.

**Why.** A member search is a lightweight lookup. Supporting documents belong with the
exchange that actually moves a person between organizations, the certificate, and no
implementation had used them on a search. The minimum exists because a search with no
criteria would otherwise match every member; the requester owning
`shared_identifier` means the requester can correlate the reply without waiting for the
response to tell it the identifier.

**What to change.** Stop sending `documents`; always send a requester-generated
`shared_identifier`; enforce the minimum on both sides.

### Member Search Reply (clarification)

**What changed.** The reply body and the `matches` element are unchanged. 2.0 shows the
declined form (`data.reason` in place of `data.matches`), fixes the acknowledgement as
`{acknowledged: true, shared_identifier}` where 1.0 said "standard MIP response", and labels
the two halves "Reply body" and "Acknowledgement" instead of "Request Payload" and
"Response Payload".

**Why.** The acknowledgement shape was unspecified, and the 1.0 labels made the reply look
like a request the requester was making.

### Certificate of Good Standing Request (breaking)

**What changed.** 1.0's request was `{requesting_member: {member_number, first_name,
last_name, birthdate}, requested_member_number, notes}`. 2.0's carries the same fields as a
member search, `member_number`, `first_name`, `last_name`, `birthdate`, `shared_identifier`,
plus `notes`, with the same minimum and the same omission rule.

**Why.** The person a certificate is requested for often has no record yet in the
requesting organization, so there was frequently no `requesting_member` to describe. The
fields now describe the person as the responding organization knows them. Sending the
requester's own member profile when one exists is proposed for 2.1.

**What to change.** Send the search-shaped fields; drop `requesting_member` and
`requested_member_number`.

### Certificate of Good Standing Reply (breaking)

**What changed.** 1.0's reply was flat, without the `meta`/`data` envelope. 2.0's reply is
`{meta, data: {shared_identifier, status, certificate}}` for an approval and `{meta, data:
{shared_identifier, status, reason}}` for a decline. Everything that is the certificate
itself sits inside `data.certificate`, which gains its own `shared_identifier`, a UUID
minted by the issuer. `data.status` is the outcome of the request; `certificate.good_standing`
is the member's standing, and an issued certificate may say `false`.

**Why.** Every other reply in the protocol uses the envelope; the certificate reply was the
one exception. Separating the request's outcome from the member's standing removes an
ambiguity in 1.0, where a member not in good standing was shown as a declined request. The
certificate's own identifier lets it be cited later, for instance when the member affiliates
or to ask whether it still stands.

**What to change.** Wrap the reply in the envelope; put the certificate fields under
`data.certificate`; mint and include `certificate.shared_identifier`.

### Certificate validity (clarification)

**What changed.** `valid_until` is required. How long a certificate is valid is the issuing
organization's policy.

**Why.** 1.0 carried the field without saying whether it was required or who set the
period.

## Common Data Formats

### Node Profile (breaking)

**What changed.** `share_my_organization` is removed from the profile; it is a flag on the
Connection Request and Connection Approved payloads. `gdpr_metadata` is added, present when a
node describes itself and absent when its profile is relayed. `organization_public_website`
is optional; every other field is required. The same shape is used everywhere a node is
described, and a profile never contains other nodes.

**Why.** Whether a node may be shared is a term of a particular connection, not a fact about
the node, and carrying it in the profile meant it appeared where it had no meaning (a relayed
profile, an update). One shape everywhere means one builder and one parser.

**What to change.** Send `share_my_organization` beside `node_profile`, not inside it; add
`gdpr_metadata` when describing yourself.

### GDPR Metadata (additive, and a rule)

**What changed.** A new format, `{controller: {role, name}, processors: [{name, role}],
sub_processors: [{name}], gdpr_contact_name?, gdpr_contact_email?}`, carried inside a Node
Profile on every leg where a node describes itself. It is a self-declaration: a node never
sends another node's, a receiver never accepts it from anyone but the declarant, and it is
never included in a relayed profile.

**Why.** The declaration is a statement the declaring organization is accountable for, and the
receiver stores it as a dated record of what that node declared and when. A copy passed on by
a third party may be stale and breaks that provenance. It also names a contact person, whom
there is no basis to disclose to organizations the declarant has no relationship with.
Nothing is lost: a node needs a peer's processing chain only once it is about to exchange
member data with that peer, and every direct leg carries the declaration.

### Certificate (additive)

**What changed.** The certificate is now a common data format of its own:
`{shared_identifier, good_standing, issued_at, valid_until, issuing_organization,
member_profile}`.

**Why.** It has an identifier and a fixed field list, and it is referred to from the reply
rather than spread across it.

### Member Profile (no change)

The Member Profile is carried from 1.0 unchanged.

## Moved to 2.1

### Member Status Check (breaking)

**What changed.** The `/member_status_checks` endpoint is not part of 2.0. Its 1.0 text,
including the real-time rationale, is carried into the "MIP 2.1 Proposed Ideas" section of
the 2.0 document.

**Why.** It was not implemented in the systems this revision was checked against, and its
contract (answered in real time, never queued) differs from every other member-protocol
request. It stays on the table for the
next version rather than shipping unimplemented in this one.

**What to change.** Nothing on the wire; a node that implemented it keeps a private
endpoint until 2.1 defines one.

### Potential functions (moved)

1.0's list of "MIP 2.0 Potential Functions" (member linking; notification of linked member
status and contact changes; display of linked status in member profiles) is carried, with
its wording, into the 2.1 section beside the member scan, organization-to-organization
messages, and the requesting member profile on a certificate request.
