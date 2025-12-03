# Member Interchange Protocol

Repository for information related to the Member Interchange Protocol used to share information about members between independent systems on a vendor neutral basis.

# Purpose

Provide a protocol for Member based organizations to exchange member information between organizations and their database systems. MIP allows for point-to-point sharing of member data between discrete organizations, discovery of organizations using the protocol, and a built-in trust system to speed the exchange of credentials between systems. The protocol only defines how system can communicate information. How those systems use that information further is not covered by the protocol. Separating the techinical requirements from policy. MIP defines how organizations can share information not what they can do with it. The what is left to the organizations themselves to figure out.

MIP provides the following non-exhaustive benefits:

- No need to share your member data in any way with a third-party system,
- No need for any groups of organizations to implement, host, and maintain a central system running a clearing house,
- No data synchronization issues,
- No long-term software maintenance cost to the any group of organizations,
- No server security risks with a central clearing house system, and 
- Ability to provide for linked member records between systems in the future without the need to maintain persistent member data in a third-party system.

MIP allows for each organization to control how their data is disseminated, which parent organizations they share data with, and how their data and identity is propagated.

# Inspiration 

MIP was conceived to address the needs of the organizations in the Conference of Grand Secretaries in North America (https://www.cogsna.org/about) to share information on persons who hold membership in multiple organizations within their eco-system.

# Definitions

- Local Group: A local group that is chartered by a Parent Group and is the unit that a party belongs to as a member.
- Parent Group: An organization that charters local groups. Within the Masonic context these are Grand Lodges, Grand Chapters, etc.
- Party: A record in a member system representing a person or entity that is a member or is in the process of becoming a member.
- Node: A system that supports MIP.
- Node Eco-System: A group of nodes that agree to share node information among themselves and potentially have trusted nodes that can “vouch” for other nodes so third parties can quickly establish connections.
- Trusted Node: A node that is trusted by a system to authenticate the validity of a third-party node in a MIP Node Eco-System.

# Protocol Overview

Each node that implements MIP has the ability to exchange credentials with other systems that support MIP to establish identity and exchange cryptographic keys for further transactions.

The protocol supports a discovery system that allows with permissions any node to share with any other node it is connected to the information for other nodes that the node knows about. For example Node A and Node B create a connection and exchange credentials. Node A knows about Node C and can share the technical and contact information for Node C to Node B.

The protocol supports the concept of trusted nodes that allows a system to designate an authenticated node to automatically authenticate the identity of other nodes.

This system of node information sharing and trusted party authentication would allow an eco-system, like the grand bodies participating in the Conference of Grand Secretaries of North America (CGSNA), to manually create connections with a small number of nodes in the eco-system and have connections with all of the participants in the eco-system automatically established based on a system of shared trust.

This system of discovery and vouching is the primary feature that enables a federated system that does not require a central clearing house. 

The protocol also supports automatic notification for when a node creates a new connection. For example, when Node A creates a new connection with New Node Z, Node A can notify all of the nodes it already had a connection with that New Node Z exists and if Node A is a trusted voucher for New Node Z, and any of Node A’s connections trust Node A then those nodes can automatically build connections with Node Z through mutual trust.

All requests will use RSA signed JSON payloads for authentication with a time component to protect against replay attacks.

# Why MIP?

MIP provides a protocol for exchanging information between member-based organizations on a point-to-point basis. MIP provides a high level of control to the organizations using the protocol and provides a system that has no single point of failure.

MIP revolves around representing each organization that participates in a data sharing eco-system as a “Node”. The term node is not essential but represents that each organization participating in a MIP eco-system is part of a network and node is a common term for the participants in a network. 

The protocol is also designed to allow organizations that are sharing databases in one member management system to independently manage their permissions and participation in the network independently.

MIP 1.0 provides two main sets of functionalities:
- node functions to connect two organizations and authenticate and add nodes to the network, and
- member queries to 
	- search for members across organizations, 
	- request official Certificates of Good Standing, and
	- retrieve current status.

# System vs Protocol

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

# MIP 1.0 Functions Overview

## Node Protocol

- Node Handshake: Request or establish a connection between two nodes that implement MIP. This is the process that kicks off the process of creating a connection between two organizations.
- Node Verify Signature: Request a third-party verification from a trusted node of a signature.
- Node Approve: Notify a node that has requested a connection that their request has been approved.
- Node Decline: Notify a node that has requested a connect that their request has been declined.
- Node Revoke: Notify a node that their connection with the notifying node has been revoked and no more requests will be honored.
- Node Restore: Notify a node that their connect with the notifying node has been restored and requests will be honored.
- Node Update: Push updated node information from the requesting node to the target node and request updated node information. (This could be used if a node changes URLs to notify their peer nodes of that change.)
- New Node Notification: Notify my peer nodes that I have created a connection with a new node.
- Nodes Query: Ask a node for a list of the nodes it has connections with and has permission to share.

## Member Protocol

- Member Query: Search for a member in a connected system using member number; or first name, last name, and birth date. The response to this request should include the member’s type (ie: Master Mason, Fellowcraft, etc.) and the origin system’s representation of the member’s status and an indicator showing if that status is active or inactive.
- Member Status Check: A quick query to get the current status of a known member, this does not return a full member profile.
- Certificate of Good Standing (COGS) Request: Request a certificate of good standing from a system. This could be answered automatically or manually depending on the requirements of the receiving organization. If it is automatic the COGS will be returned immediately. If the COGS cannot be returned immediately the response will include an ID to track the request and to later receive the COGS.
- Certificate of Good Standing Notification: If a COGS cannot be returned immediately when requested the COGS will be returned to the requesting system using this function. 

# MIP 2.0 Potential Features

There are many potential functions that could be added to MIP such as:

- Member linking between organizations
- Automatic notification of linked member status change
- Automatic notification of linked member contact information change
- Automatic display of linked member status in member profiles

An important differentiator of using MIP over having a centralized clearing house is there would be no need for a third-party to store the connections between members in discrete member databases. All of those connections would ONLY be housed with the organizations the member belongs to. No need to trust a third-party with your data in any way at all.
