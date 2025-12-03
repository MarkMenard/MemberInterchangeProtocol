# Member Interchange Protocol

## Lead Author: Mark Menard (Groupable)

### November 30, 2025

## Purpose

Provide a protocol for Masonic organizations to exchange member status information between organizations (The Member Interchange Protocol (MIP)) for the Conference of Grand Secretaries of North America (CGSNA). MIP allows for point-to-point sharing of member data between discrete organizations, discovery of organizations using the protocol, and a built-in trust system to speed the exchange of credentials between systems. 

MIP provides the following non-exhaustive benefits:

- No need to share your member data in any way with a third-party system,
- No need for the CGSNA to implement, host, and maintain a system running a central clearing house,
- No data synchronization issues,
- No long-term software maintenance cost to the CGSNA,
- No server security risks with a central clearing house system, and 
- Ability to provide for linked member records between systems in the future without the need to maintain persistent member data in a third-party system.

MIP allows for each organization to control how their data is disseminated, which parent organizations they share data with, and how their data and identity is propagated.

## Definitions

Local Group: A local group that is chartered by a Parent Group and is the unit that a party belongs to as a member.

Parent Group: An organization that charters local groups. Within the Masonic context these are Grand Lodges, Grand Chapters, etc.

Party: A record in a member system representing a person or entity that is a member or is in the process of becoming a member.

Node: A system that supports MIP.

Node Eco-System: A group of nodes that agree to share node information among themselves and potentially have trusted nodes that can “vouch” for other nodes so third parties can quickly establish connections.

Trusted Node: A node that is trusted by a system to authenticate the validity of a third-party node in a MIP Node Eco-System.
Protocol Overview

Each node that implements MIP has the ability to exchange credentials with other systems that support MIP to establish identity and exchange cryptographic keys for further transactions.

The protocol supports a discovery system that allows with permissions any node to share with any other node it is connected to the information for other nodes that the node knows about. For example Node A and Node B create a connection and exchange credentials. Node A knows about Node C and can share the technical and contact information for Node C to Node B.

The protocol supports the concept of trusted nodes that allows a system to designate an authenticated node to automatically authenticate the identity of other nodes.

This system of node information sharing and trusted party authentication would allow an eco-system, like the grand bodies participating in the Conference of Grand Secretaries of North America (CGSNA), to manually create connections with a small number of nodes in the eco-system and have connections with all of the participants in the eco-system automatically established based on a system of shared trust.

This system of discovery and vouching is the primary feature that enables a federated system that does not require a central clearing house. 

The protocol also supports automatic notification for when a node creates a new connection. For example, when Node A creates a new connection with New Node Z, Node A can notify all of the nodes it already had a connection with that New Node Z exists and if Node A is a trusted voucher for New Node Z, and any of Node A’s connections trust Node A then those nodes can automatically build connections with Node Z through mutual trust.

All requests will use RSA signed JSON payloads for authentication with a time component to protect against replay attacks.

## Why MIP?

MIP provides a protocol for exchanging information between member-based organizations on a point-to-point basis. MIP provides a high level of control to the organizations using the protocol and provides a system that has no single point of failure.

MIP revolves around representing each organization that participates in a data sharing eco-system as a “Node”. The term node is not essential but represents that each organization participating in a MIP eco-system is part of a network and node is a common term for the participants in a network. 

The protocol is also designed to allow organizations that are sharing databases in one member management system to independently manage their permissions and participation in the network independently.

MIP 1.0 provides two main sets of functionalities:
- node functions to connect two organizations and authenticate and add nodes to the network, and
- member queries to 
	- search for members across organizations, 
	- request official Certificates of Good Standing, and
	- retrieve current status.

## System vs Protocol

Because MIP is a mesh protocol using authenticated point-to-point connections between member systems there is no single point of failure or the need to maintain a central server or clearing house. 

MIP is not a “system” in the sense that there is no single computer that is responsible for administering the exchange of information between organizations. Information is simply passed between one member organization and another with no need for centralized definitions, global IDs, or other data that requires maintenance.

Creating a central clearing house instead of simply adopting a protocol for exchanging member information between Masonic bodies would entail both implementing, hosting, and providing for the long-term maintenance of the system of software and hardware that implements the system. As well, the system would require the development and implementation of a protocol that the participating member databases would need to implement to communicate with the central clearing house system.

In simple terms if CGSNA creates a central clearing house it still must develop and adopt a protocol for the interchange of member data. MIP simply allows CGSNA to adopt MIP and be done and let the vendors in the space implement it.

MIP sidesteps all of the concerns of having a system in favor of simply adopting a protocol that provides a quick way to on-board nodes into the system. The benefits to any group 
of organizations that adopts a protocol like MIP are:

- No development costs to implement a central system to administer data sharing,
- No hosting costs for hosting said system,
- No long-term software maintenance costs for said system, 
- No single point of failure,
- No need to accept standardized membership terminology and the expense associated with harmonizing said terminology,
- No data synchronization issues,
- No security risks involved in sharing data with a third party system, and
- The cost of implementing and maintaining MIP falls to the vendors who decide to implement MIP.

In short MIP enables the CGSNA to completely side-step getting into the service provision business and get all of the benefits that cross organization member looks ups can deliver.

## MIP 1.0 Functions Overview

### Node Protocol

- Node Handshake: Request or establish a connection between two nodes that implement MIP. This is the process that kicks off the process of creating a connection between two organizations.
- Node Verify Signature: Request a third-party verification from a trusted node of a signature.
- Node Approve: Notify a node that has requested a connection that their request has been approved.
- Node Decline: Notify a node that has requested a connect that their request has been declined.
- Node Revoke: Notify a node that their connection with the notifying node has been revoked and no more requests will be honored.
- Node Restore: Notify a node that their connect with the notifying node has been restored and requests will be honored.
- Node Update: Push updated node information from the requesting node to the target node and request updated node information. (This could be used if a node changes URLs to notify their peer nodes of that change.)
- New Node Notification: Notify my peer nodes that I have created a connection with a new node.
- Nodes Query: Ask a node for a list of the nodes it has connections with and has permission to share.

### Member Protocol

- Member Query: Search for a member in a connected system using member number; or first name, last name, and birth date. The response to this request should include the member’s type (ie: Master Mason, Fellowcraft, etc.) and the origin system’s representation of the member’s status and an indicator showing if that status is active or inactive.
- Member Status Check: A quick query to get the current status of a known member, this does not return a full member profile.
- Certificate of Good Standing (COGS) Request: Request a certificate of good standing from a system. This could be answered automatically or manually depending on the requirements of the receiving organization. If it is automatic the COGS will be returned immediately. If the COGS cannot be returned immediately the response will include an ID to track the request and to later receive the COGS.
- Certificate of Good Standing Notification: If a COGS cannot be returned immediately when requested the COGS will be returned to the requesting system using this function. 

## MIP 2.0 Potential Functions

There are many potential functions that could be added to MIP such as:

- Member linking between organizations
- Automatic notification of linked member status change
- Automatic notification of linked member contact information change
- Automatic display of linked member status in member profiles

An important differentiator of using MIP over having a centralized clearing house is there would be no need for a third-party to store the connections between members in discrete member databases. All of those connections would ONLY be housed with the organizations the member belongs to. No need to trust a third-party with your data in any way at all.

## Preliminary Technical Specification

The remainder of this document is a draft of the technical specifications of this protocol and should be considered a work in progress.

## MIP Node Requirements

Each node implementing MIP needs to generate a MIP ID which is a 128 bit unique identifier. This should be done by combining a 128 bit randomly generated number concatenated with a salt based using something unique to the organization such as their name and then deriving the MD5 hash of the of the concatenated number and salt.

For example, the unique identifier could be generated using the following MySQL query:

	SELECT MD5(CONCAT(UUID(), 'Grand Lodge of New York')) AS 'uuid'

The same example in Ruby:

	require 'securerandom'
	require 'digest'

	uuid = SecureRandom.uuid
	salt = "Grand Lodge of New York"

	md5_hash = Digest::MD5.hexdigest("#{uuid}#{salt}")

	puts "MD5 Hash: #{md5_hash}"


Each node also needs to generate an RSA public/private key pair for use in signing requests and authenticating identity.

The MIP ID establishes the identity of the MIP node, and the RSA key is used to authenticate all request made using the protocol.

MIP 1.0 Endpoint Details:

Node Handshake

End Point: /mip/groups/<group_id>/nodes/<mip_id>/handshake

Arguments:

	group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
	mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Passkey
- System URL
- RSA Public Key
- Organization Name
- Primary Contact Info
- Share My Node Info Indicator
- Trusted Node Indicator
- Known Nodes
- Vouching Request with Signature
- Other fields as needed

Potential Response Payload:

- Success or Failure
- Request Status (APPROVED|DECLINED|PENDING)
- System URL
- Organization Name
- Primary Contact Info
- Share My Node Info Indicator (if approved)
- Known Nodes (if approved)
- Trusted Node Indicator (if approved)
- Vouching Request with Signature (if approved)
- Other fields as needed

A user can initiate an invitation to any person who has a MIP compliant system. 

The inviter would provide the invitee with their MIP end point URL (ex: https://ny.moriapp.com/integrations/mip/handshake/). 

The invitee would enter the URL into their MIP compliant system which would initiate the handshake between the two systems exchanging RSA public keys for request signing.

As part of the handshake process both the inviter and the invitee can designate if they wish to have their MIP endpoint shared with other known MIP endpoints the other organization knows about.

As part of the handshake both parties can designate the other as a trusted vouching system. 

If the receiving system cannot authenticate the requesting system automatically it should queue the request for end-user approval or declination.

If a request is not approved automatically the requesting party should contact the organization they are attempting to connect with and provide the fingerprint of their RSA public key for verification.

A MIP system can initiate an automatic request for handshake with another node in an eco-system. This request does not require a person to initiate it. These requests would usually take place due to finding out about a node using discovery within the protocol.

As part of the handshake process both the inviter and the invitee can designate if they wish to have their MIP endpoint shared with other known MIP endpoints the other organization knows about.

As part of the handshake both parties can designate the other as a trusted vouching system. 

Node Verify Signature

Endpoint: /mip/groups/<group_id>/nodes/<mip_id>/verify_signature

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting node.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- MIP ID (of the node you are verifying a signature request for, not the MIP ID from the URL)
- Vouching Request
- Vouching Request Signature

Potential Response Payload:

- Success or Failure
- MIP ID

Node Approve

Endpoint: /mip/groups/<group_id>/nodes/<mip_id>/approve

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Organization Name
- Primary Contact Info
- Share My Node Info Indicator
- Trusted Node Indicator
- Known Nodes
- Vouching Request with Signature
- Other fields as needed

Potential Response Payload:

- Success or Failure
- MIP ID

Node Decline

Endpoint: /mip/groups/<group_id>/nodes/<mip_id>/decline

Arguments:

group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.

mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Other fields as needed

Potential Response Payload:

- Success or Failure
- MIP ID

If a request to handshake is declined by an organization their system should send a decline request to the declined node. 

Note declining a request does not preclude the requesting system from requesting a connection again. For user convenience a system could provide a means for auto-declining repeated requests from the same organization.

Node Revoke

Endpoint: /mip/groups/<group_id>/nodes/<mip_id>/revoke

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Other fields as needed

Potential Response Payload:

- Success or Failure
- MIP ID

This end point is used to ask another node to cease sending requests. An implementor can either remove the MIP node information from their system or mark the MIP Node’s access revoked. 

The receiving system should mark the node revoked and not send requests to it.

Node Restore

Endpoint: /mip/groups/<group_id>/nodes/<mip_id>/restore

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Other fields as needed

Potential Response Payload:

- Success or Failure
- MIP ID

This end point is used to ask another node to cease sending requests. An implementor can either remove the MIP node information from their system or mark the MIP Node’s access revoked.

Node Update

End Point: /mip/groups/<group_id>/nodes/<mip_id>/update

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- System URL
- Organization Name
- Primary Contact Info
- Share My Node Info Indicator
- Trusted Node Indicator
- Known Nodes
- Other fields as needed

Potential Response Payload:

- Success or Failure
- MIP ID
- Organization Name
- Primary Contact Info
- Share My Node Info Indicator
- Known Nodes
- Trusted Node Indicator
- Vouching Request with Signature
- Other fields as needed

The purpose of this end point is to update a connected node with new information about your group, and to query updated information from the connected node. A system implementing MIP would use this to keep information up to date.

New Node Notification

End Point: /mip/groups/<group_id>/node/<mip_id>/new_node_notification

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- New node MIP ID
- New Node System URL
- New Node Organization Name
- New Node Primary Contact Info
- Other fields as needed

Potential Response Payload:

- Success or Failure
- MIP ID
- Other fields as needed

The purpose of this end point is to share the existence of a new node to the MIP nodes we are connected with.

Nodes Query

End Point: /mip/groups/<group_id>/node/<mip_id>/nodes

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: GET

Payload Format: No Payload

Potential Response Payload:

- List of known nodes we have permission to share
- Other fields as needed

The purpose of the nodes query is to discover other nodes a MIP compliant system knows about and has connected with. This query allows for participating organizations in a MIP eco-system to discover other members of the eco-system. For example GL of NY and GL of FL handshake and establish a connection. GL of NY knows the end point and information for GL of MA. After GL of FL has completed its handshake with GL of NY the GL of FL system could query the GL of NY system for what nodes it knows about and then attempt to connect to those nodes.

Member Query

End Point: /mip/groups/<group_id>/node/<mip_id>/member_queries

Arguments:
- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Member Number: The publicly known member number for the member in the target nodes organization.
- First Name
- Last Name
- Birthdate

Potential Response Payload:

- List of Member Profiles (see below) that match the query arguments. Format to be determined. The member info objects should include the member’s status as stored in the node’s system and a field indicating if that is an active status or not.
- Other fields as needed

Member Status Check

End Point: /mip/groups/<group_id>/node/<mip_id>/member_status_checks

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Member Number: The publicly known member number for the member in the target nodes organization.

Potential Response Payload:

- Request Status: SUCCESS or FAILURE
- Member Status String
- Member Type
- Member Active: true or false

Certificate of Good Standing Request

End Point: /mip/groups/<group_id>/node/<mip_id>/member_cogs_requests

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- Requesting Member Number: The member number or identifier for the person in the source node’s organization.
- Requesting Member Profile: format to be determined
- Requested Member Number: The publicly known member number for the member in the target node’s organization.

Potential Response Payload:

If the certificate of good standing can be automatically issued then the payload would be the same as the Certificate of Good Standing Notification payload.

If the certificate of good standing can not be automatically issued then the payload would be as follows:

- Success or Failure
- COGS Request ID: Internal ID of the pending COGS request.

Certificate of Good Standing Notification

End Point: /mip/groups/<group_id>/node/<mip_id>/member_cogs_notification

Arguments:

- group_id: The id of the group you are attempting to connect to on the foreign system. This would be provided by the person sending a request.
- mip_id: The MIP ID of the requesting organization.

HTTP Action: POST

Payload Format: JSON

Potential Request Payload: 

- COGS Request ID
- In Good Standing (true or false)
- Member Organization Status: string representing the status of the member as represented in the issuing organization
- org_name
- Member Profile (see Common Data Formats)

Potential Response Payload:

- Success or Failure
- MIP ID
- Other fields as needed

This end point is used by a node from which a COGS has been requested to respond to the requesting system with the COGS.

Common Data Formats

All request and response payloads in MIP use JSON.

Member Profile

The member profile has to be finalized before MIP 1.0 can be considered complete. The following information should represent a minimum that should be included.

- Parent Group Name
- MIP ID
- First Name
- Middle Name
- Last Name
- Suffix (Sr, Jr, etc.)
- Birthdate
- Address1
- Address2
- City
- State
- Postal Code
- Country
- Email Address
- Primary Phone Number
- List of Local Group Memberships
- List of Member Life-Cycle Events
- Member Honorific (W, VW, RW, MW, etc.)
- Member Rank (PM, PGM, PHP, PGHP, etc.)

Node Profile

- MIP ID
- System URL
- Organization Name
- Primary Contact Info

Under some circumstances the following fields could also be present in a Node Profile:

- RSA Public Key
- Share My Node Info Indicator
- Known Nodes
- Trusted Node Indicator
- Vouching Request with Signature

Vouching Request with Signature

A Vouching Request is a combination of a 128 bit random number represented in hexadecimal. 

The payload for a request is:

- Signature Message (a 128 bit random number represented in hexadecimal)
- RSA Signature (base 64 string encoded)

Example:

	{
	  "message": "fce7240f-9e9d-4f12-9318-a6a0c1647e45",
	  "signature": "g2FAOk4wXU6+j85b+1kpz3kgRH+ZmFIk2YkNkCP5GP8lhwaUW6rLUeAA8AboPZbXlPVl6uQWXg4oHFXxOjAAuDGcDmWJxsX6/TBDPMpwGYRNd6ekKCNwXX5hh/jy5eHeSNMb7AOmjb2DpQRAu/OUG9xgsNzJDT5NRxHhxOX1pidZFtzDUs8gn/69GV/dvo8iYjVjMV+l6E7E4XKvXkzc+TOI+GOAfWO9SKUgj6mqBPyBTSLDmScTnFID8NaUg4b0kkkWUjUXIk0M6TMBQcub0sJ7fa3NXEDHeqeWoqVX43IgEtH7XbtAj+H/C/NzJ3jAo31kr7P0+6a40rYxVn1ghw=="
	}

