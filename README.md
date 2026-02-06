# Member Interchange Protocol

Repository for information related to the Member Interchange Protocol used to share information about members between independent systems on a vendor neutral basis.

# Purpose

Provide a protocol for Member based organizations to exchange member information between organizations and their database systems. MIP allows for point-to-point sharing of member data between discrete organizations, discovery of organizations using the protocol, and a built-in web of trust system to speed the exchange of credentials between systems. The protocol only defines how system can communicate information. How those systems use that information further is not covered by the protocol. Separating the technical requirements from policy. MIP defines how organizations can share information not what they can do with it. The what is left to the organizations themselves to figure out.

MIP allows your organization to connect with other member-based organizations you have member relationships with. Using MIP you can:

- Request member searches of organizations you have a connection to,
- Request a certificate of good standing for an individual from a connected organization,
- Request current member status of a person in a connected organization, and more.

MIP provides the following non-exhaustive benefits:

- No need to share your member data in any way with a third-party system,
- No need for any groups of organizations to implement, host, and maintain a central system running a clearing house,
- No data synchronization issues,
- No long-term software maintenance cost to the any group of organizations,
- No server security risks with a central clearing house system, and
- Ability to provide for linked member records between systems in the future without the need to maintain persistent member data in a third-party system.

MIP allows for each organization to control how their data is disseminated, which parent organizations they share data with, and how their data and identity is propagated.

# Who Should Use MIP

If your organization is part of a confederation of member-based organizations where a person can belong to more than one organization in the confederation and the organizations have a need/want to share information about those shared members MIP can facilitate the sharing of information.

# Inspiration

MIP was conceived to address the needs of the organizations in the Conference of Grand Secretaries in North America (CGSNA) (https://www.cogsna.org/) to share information on persons who hold membership in multiple organizations within their ecosystem, or who are wishing to move their membership from one organization to another. Most of the organizations within the CGSNA run different member management systems, but have the need to share information and member status within the CGSNA ecosystem. MIP provides a protocol that allows the various systems used to manage members to share that information in a vendor independent fashion.

# Try it Out - Reference Implementations

This project contains reference implementations of the Member Interchange Protocol in multiple languages and frameworks to demonstrate interoperability and provide a starting point for developers.

## Available Implementations

1.  **Node.js / Express**: A fast, asynchronous implementation using EJS templates.
2.  **PHP / Laravel**: A robust implementation using the Laravel framework.
3.  **Ruby / Sinatra**: A lightweight implementation using the Sinatra framework.
4.  **.NET / ASP.NET Core**: A cross-platform implementation using ASP.NET Core.

---

## Running the Node.js Interactive Demo

The Node.js implementation includes an automated interactive demo that launches three independent nodes and walks through the protocol steps including the Web of Trust and Cross-Organization Member Search.

### 1. Prerequisites: Install Node.js

You will need Node.js (v18 or higher) installed on your machine.

#### macOS
The easiest way to install Node.js on macOS is via [Homebrew](https://brew.sh/):
```bash
brew install node
```

#### Linux (Ubuntu/Debian)
On Ubuntu or Debian-based distributions, use the NodeSource repository:
```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
NODE_MAJOR=20
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list
sudo apt update
sudo apt install nodejs -y
```

### 2. Setup and Installation

Navigate to the Node.js implementation directory and install the required dependencies:

```bash
cd mip_reference/node-express
npm install
```

### 3. Run the Demo

Launch the interactive demo script:

```bash
npm run demo
```

The script will:
- Start 3 independent MIP nodes (Alpha, Beta, and Gamma).
- Launch a Chrome browser with 3 separate windows.
- Provide step-by-step instructions in your terminal.
- Pause at each stage so you can observe the state changes across different organizations.
- Demonstrate manual connections, Web of Trust auto-approvals, and cross-node member verification.



# Definitions

- Local Group: A local group that is chartered by a Parent Group and is the unit that a party belongs to as a member.
- Parent Group: An organization that charters local groups. Within the Masonic context these are Grand Lodges, Grand Chapters, etc.
- Party: A record in a member system representing a person or entity that is a member or is in the process of becoming a member.
- Node: A system that supports MIP.
- MIP Eco-System: A group of organization that agree to share information among themselves and use the web of trust system to authenticate new connections.
- Endorsement: A cryptographic signature by one node vouching for another node's identity. Endorsements enable the web of trust.
- Good Standing: Any status a member can be in that denotes they are either an active member of an organization or if they are inactive they are eligible to re-join the organization, or another organization that recognizes their current or prior membership.

# Protocol Overview

Each system that implements MIP has the ability to exchange credentials with other systems that support MIP to establish identity and exchange cryptographic keys for further transactions.

## Web of Trust

MIP uses a web of trust model for authentication. When two nodes establish a connection (through manual approval or endorsement-based trust), they sign each other's public keys, creating endorsements. These endorsements travel with future connection requests, allowing nodes to verify identity locally without network calls.

The trust model works as follows:

1. **Initial Connections**: The first connections for a new node require manual approval by an administrator.
2. **Endorsement Exchange**: When a connection becomes active, both nodes create and exchange endorsements signing each other's public keys.
3. **Trust Propagation**: When Node A later requests a connection to Node D, it presents endorsements from Nodes B and C (existing connections).
4. **Local Verification**: Node D checks if any endorsers are its own active connections. If so, it verifies the signatures using public keys already stored locally.
5. **Automatic Approval**: If enough valid endorsements from trusted connections are present (configurable threshold, default 1), the connection is automatically approved.

This system eliminates real-time dependency on third parties for authentication. The more connections a node establishes, the more endorsements it accumulates, making subsequent connections easier to establish.

## Node Discovery

The protocol supports a discovery system that allows any organization to share with any other node it is connected to the information for other organizations they know about. For example Organization A and Organization B create a connection and exchange credentials. Organization A knows about Organization C and can share the technical and contact information for Organization C to Organization B, along with any endorsements for Organization C.

The protocol also supports automatic notification when an organization creates a new connection. When Organization A creates a new connection with New Organization Z, Organization A can notify all connected organizations about New Organization Z. If Organization A's connections trust Organization A's endorsements, they can automatically establish connections with Organization Z through the web of trust.

## Security

All requests use RSA signed JSON payloads for authentication with a time component to protect against replay attacks.

# Why MIP?

MIP provides a protocol for exchanging information between member-based organizations on a point-to-point basis. MIP provides a high level of control to the organizations using the protocol and provides a system that has no single point of failure.

MIP revolves around representing each organization that participates in a data sharing ecosystem as a "Node". The term node is not essential but represents that each organization participating in a MIP ecosystem is part of a network and node is a common term for the participants in a network.

The protocol is also designed to allow organizations that are sharing databases in one member management system to independently manage their permissions and participation in the network independently.

MIP 1.0 provides two main sets of functionalities:

- Connection functions to connect two organizations and authenticate and add organizations to the network, and
- member search requests to
	- search for members across organizations,
	- request official Certificates of Good Standing, and
	- retrieve current member status.

# System vs Protocol

Because MIP is a mesh protocol using authenticated point-to-point connections between member systems there is no single point of failure or the need to maintain a central server or clearing house.

MIP is not a "system" in the sense that there is no single computer that is responsible for administering the exchange of information between organizations. Information is simply passed between one member organization and another with no need for centralized definitions, global IDs, or other data that requires maintenance.

Creating a central clearing house instead of simply adopting a protocol for exchanging member information between organizations would entail both implementing, hosting, and providing for the long-term maintenance of the system of software and hardware that implements the system. As well, the system would require the development and implementation of a protocol that the participating member databases would need to implement to communicate with the central clearing house system.

MIP sidesteps all of the concerns of having a system in favor of simply adopting a protocol that provides a quick way to on-board organizations into the system. The benefits to any group of organizations that adopts a protocol like MIP are:

- No development costs to implement a central system to administer data sharing,
- No hosting costs for hosting said system,
- No long-term software maintenance costs for said system,
- No single point of failure,
- No need to accept standardized membership terminology and the expense associated with harmonizing said terminology,
- No data synchronization issues,
- No security risks involved in sharing data with a third party system, and
- The cost of implementing and maintaining MIP falls to the vendors who decide to implement MIP.

In short MIP enables the any group of organizations to completely side-step getting into the service provision business and get all of the benefits that cross organization member looks ups can deliver.

# MIP 1.0 Functions Overview

## Connection Protocol

- **MIP Connection Request**: Request a connection between two organizations that implement MIP.
- **MIP Connection Approve**: Notify an organization that has requested a connection that their request has been approved.
- **MIP Connection Decline**: Notify an organization that has requested a connection that their request has been declined.
- **MIP Connection Revoke**: Notify an organization that their connection has been revoked and no more requests will be honored.
- **MIP Connection Restore**: Notify an organization that their connection has been restored and requests will be honored.
- **Organization Update**: Push updated organization information to a connected organization and request their updated information. (This could be used if an organization changes URLs to notify their connected organizations of that change.)
- **New Organization Notification**: Notify connected organizations that a new organization has joined the ecosystem.
- **Connected Organizations Query**: Request a list of organizations that a connected organization knows about and has permission to share.
- **Endorsement**: Send a cryptographic endorsement of another organization's identity to establish web-of-trust.

## Member Protocol

- **Member Search Request**: Search for a member in a connected system using member number; or first name, last name, and birth date.
- **Member Search Reply**: Receive a reply to a requested member search. The reply to this request should include the member's type (ie: Master Mason, Fellowcraft, etc.) and the origin system's representation of the member's status and an indicator showing if that status is active, in good standing, and deceased. Additionally a search request can be declined.
- **Member Status Check**: A quick query to get the current status of a known member, this does not return a full member profile.
- **Certificate of Good Standing (COGS) Request**: Request a certificate of good standing from a system. This could be answered automatically or manually depending on the requirements of the receiving organization. If it is automatic the COGS will be returned immediately. If the COGS cannot be returned immediately the response will include an ID to track the request and to later receive the COGS.
- **Certificate of Good Standing Reply**: If a COGS cannot be returned immediately when requested the COGS will be returned to the requesting system using this function.

# MIP 2.0 Potential Features

There are many potential functions that could be added to MIP such as:

- Member linking between organizations
- Automatic notification of linked member status change
- Automatic notification of linked member contact information change
- Automatic display of linked member status in member profiles

An important differentiator of using MIP over having a centralized clearing house is there would be no need for a third-party to store the connections between members in discrete member databases. All of those connections would ONLY be housed with the organizations the member belongs to. No need to trust a third-party with your data in any way at all.
