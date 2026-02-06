# MIP Reference Implementation - PHP/Symfony

A complete reference implementation of the Member Interchange Protocol (MIP) 1.0 using PHP 8.1+ and the Symfony framework.

## Features

This implementation includes all MIP 1.0 protocol endpoints:

### Connection Protocol
- **Connection Request** - Request a connection between two MIP nodes
- **Connection Approved** - Notify requester of approval
- **Connection Declined** - Notify requester of decline
- **Connection Revoke** - Revoke an active connection
- **Connection Restore** - Restore a revoked connection
- **Connected Organizations Query** - Discover other nodes in the network
- **Endorsements** - Web-of-trust based auto-approval

### Member Protocol
- **Member Search Request** - Search for members by number or name/birthdate
- **Member Search Reply** - Return search results asynchronously
- **Member Status Check** - Real-time member status verification

### Certificate of Good Standing (COGS)
- **COGS Request** - Request a certificate for a member
- **COGS Reply** - Return certificate with full member profile

## Requirements

- PHP 8.1 or higher
- Composer
- OpenSSL extension

## Installation

```bash
cd mip_reference/php-symfony
composer install
```

## Running the Nodes

Start all three nodes using the provided script:

```bash
./start-nodes.sh
```

Or start individual nodes:

```bash
# Node 1 - Grand Lodge of Alpha (port 4013)
php -S localhost:4013 -t public

# Node 2 - Grand Lodge of Beta (port 4014)
php -S localhost:4014 -t public

# Node 3 - Grand Lodge of Gamma (port 4015)
php -S localhost:4015 -t public
```

## Accessing the Dashboards

- **Grand Lodge of Alpha**: http://localhost:4013
- **Grand Lodge of Beta**: http://localhost:4014
- **Grand Lodge of Gamma**: http://localhost:4015

## Project Structure

```
php-symfony/
├── config/
│   ├── nodes/              # Node configuration files
│   │   ├── node4013.yaml   # Alpha configuration + members
│   │   ├── node4014.yaml   # Beta configuration + members
│   │   └── node4015.yaml   # Gamma configuration + members
│   └── packages/           # Symfony configuration
├── data/                   # Persistent file storage (created at runtime)
├── public/
│   └── index.php           # Web entry point
├── src/
│   ├── Controller/
│   │   ├── DashboardController.php  # Web UI controller
│   │   └── MipController.php        # MIP API endpoints
│   ├── Mip/
│   │   ├── Client.php      # HTTP client for outbound requests
│   │   ├── Crypto.php      # RSA key generation and signing
│   │   ├── Identifier.php  # MIP identifier generation
│   │   ├── Signature.php   # Request signature handling
│   │   ├── Store.php       # File-based data persistence
│   │   ├── StoreFactory.php
│   │   └── Model/
│   │       ├── NodeIdentity.php
│   │       ├── Connection.php
│   │       ├── Member.php
│   │       ├── SearchRequest.php
│   │       ├── CogsRequest.php
│   │       └── Endorsement.php
│   └── Kernel.php
└── templates/              # Twig templates for web UI
    ├── base.html.twig
    ├── dashboard.html.twig
    ├── connections/
    ├── members/
    ├── searches/
    └── cogs/
```

## Testing the Protocol

1. **Establish Connection**: From Alpha's dashboard, go to Connections and enter Beta's MIP URL to request a connection.

2. **Approve Connection**: Switch to Beta's dashboard, go to Connections, and approve the pending request.

3. **Search Members**: From Alpha's Searches page, select Beta and search for a member number (e.g., "BETA-001").

4. **Approve Search**: Switch to Beta to approve the search, then return to Alpha to see results.

5. **Request COGS**: From Alpha's COGS page, request a certificate for a Beta member.

6. **Approve COGS**: Switch to Beta to approve, then return to Alpha to see the certificate.

## Data Persistence

Each node stores its data in `data/node{port}/store.json`. The file contains:
- Node identity (MIP ID, RSA keys, organization info)
- Connections with other nodes
- Members
- Endorsements
- Search requests
- COGS requests
- Activity log

To reset a node, use the "Reset Node" button on the dashboard or delete the store.json file.

## Security Features

- **RSA 2048-bit keys** for request signing
- **Timestamp validation** (5-minute window) to prevent replay attacks
- **Path and payload signing** to prevent tampering
- **Public key fingerprinting** for identity verification
- **Web-of-trust endorsements** for automatic connection approval

## Known Limitations

- PHP's built-in web server is single-threaded, which can cause timeouts when nodes communicate synchronously. The protocol still works, but browser may show timeout errors while background operations complete successfully.
- For production use, deploy with a proper web server (nginx + PHP-FPM, Apache, etc.)

## License

MIT License - See the main MIP repository for details.
