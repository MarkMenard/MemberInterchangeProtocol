# MIP Reference Implementation - Node.js/Express

This is a reference implementation of the Member Interchange Protocol (MIP) built with Node.js and Express. It demonstrates the complete MIP protocol including connection management, member search, COGS (Certificate of Good Standing) requests, and the web of trust model.

## What This Is

This implementation demonstrates:
- **Connection Protocol**: Request, approve, decline, revoke, and restore connections between organizations
- **Member Search**: Search for members across connected organizations
- **COGS Requests**: Request and respond to Certificate of Good Standing requests
- **Web of Trust**: Discover and connect to organizations through mutual connections
- **Dashboard UI**: Web-based interface for managing connections and performing searches

This implementation runs 3 independent MIP nodes that can connect to each other, simulating a network of organizations implementing the protocol.

## Prerequisites

- **Node.js** (version 14 or higher)
- **Web browser** (Chrome, Firefox, Safari, or Edge)

That's it! No database or additional services required.

## Quick Start

### Option 1: Interactive Demo (Recommended)

The interactive demo automatically launches all 3 nodes and walks you through the protocol steps with explanations.

```bash
# Install dependencies
npm install

# Run the interactive demo
npm run demo
```

The demo will:
1. Launch 3 MIP nodes on ports 4010, 4011, and 4012
2. Open browser windows for each node
3. Guide you through connection establishment, member searches, and COGS requests
4. Provide explanations at each step

### Option 2: Manual Start

To manually start individual nodes and explore at your own pace:

```bash
# Install dependencies (if you haven't already)
npm install

# Start Node 1 (Grand Lodge of Alpha) on port 4010
CONFIG_FILE=config/node1.yml npm start

# In separate terminals, start the other nodes:
CONFIG_FILE=config/node2.yml npm start  # Port 4011
CONFIG_FILE=config/node3.yml npm start  # Port 4012
```

Then open your browser to:
- Node 1: http://localhost:4010
- Node 2: http://localhost:4011
- Node 3: http://localhost:4012

## Node Configuration

Each node has its own configuration file in the `config/` directory:

- **node1.yml**: Grand Lodge of Alpha (port 4010)
- **node2.yml**: Grand Lodge of Beta (port 4011)
- **node3.yml**: Grand Lodge of Gamma (port 4012)

Configuration includes:
- Organization information (name, contact details)
- Port number
- Sample member data for testing searches and COGS requests
- Trust threshold settings

## Exploring the Protocol

### Making a Connection Request

1. Open Node 1's dashboard (http://localhost:4010)
2. Click "Request Connection"
3. Enter Node 2's MIP URL: `http://localhost:4011/mip`
4. Fill in the connection request form
5. Submit the request

### Approving a Connection

1. Open Node 2's dashboard (http://localhost:4011)
2. View "Pending Requests"
3. Approve Node 1's request
4. Both nodes are now connected and can exchange data

### Searching for Members

1. From a connected node, navigate to "Member Search"
2. Enter search criteria (name, birthdate, etc.)
3. View results from all connected organizations
4. Results respect each organization's privacy settings

### COGS Requests

1. From the search results, request a Certificate of Good Standing
2. The request is sent to the member's home organization
3. The organization can approve or decline the request
4. View the certificate details if approved

## Project Structure

```
node-express/
├── app.js              # Main Express application
├── demo.js             # Interactive demo script
├── package.json        # Dependencies and scripts
├── config/             # Node configuration files
│   ├── node1.yml
│   ├── node2.yml
│   └── node3.yml
├── lib/                # MIP protocol implementation
│   └── mip.js          # Core MIP logic
├── views/              # EJS templates for UI
├── public/             # Static assets (CSS, JS)
└── tmp/                # Temporary data storage
```

## Port Ranges

This implementation uses ports 4010-4012 to avoid conflicts with other MIP reference implementations:
- Ruby/Sinatra: 3000-3002
- .NET/ASP.NET Core: 5000-5002
- Node.js/Express: 4010-4012
- PHP/Laravel: 8000-8002
- PHP/Symfony: 9000-9002

## Learn More

- **MIP Specification**: See the main repository's `MIP_1_0.md` file
- **Other Implementations**: Check the `mip_reference/` directory for implementations in other languages
- **Interactive Demo**: Run `npm run demo` for a guided walkthrough of the protocol

## License

This reference implementation is provided as-is to help developers understand and implement the Member Interchange Protocol.
