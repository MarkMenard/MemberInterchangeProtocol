#!/usr/bin/env node

/**
 * MIP Interactive Demo Script
 *
 * This script launches 3 MIP nodes and walks through the protocol demo
 * with explanations and pauses for the presenter.
 *
 * Usage:
 *   cd mip_reference/node-express
 *   npm install
 *   npm run demo
 */

const puppeteer = require('puppeteer');
const { spawn } = require('child_process');
const readline = require('readline');
const path = require('path');
const http = require('http');

// Configuration
const NODES = [
  { port: 4010, name: 'Grand Lodge of Alpha', shortName: 'Alpha', configFile: 'config/node1.yml' },
  { port: 4011, name: 'Grand Lodge of Beta', shortName: 'Beta', configFile: 'config/node2.yml' },
  { port: 4012, name: 'Grand Lodge of Gamma', shortName: 'Gamma', configFile: 'config/node3.yml' }
];

// Terminal colors
const c = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  dim: '\x1b[2m',
  cyan: '\x1b[36m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  magenta: '\x1b[35m',
  blue: '\x1b[34m',
  red: '\x1b[31m'
};

// Helper functions
function print(text, color = c.reset) {
  console.log(`${color}${text}${c.reset}`);
}

function printHeader(text) {
  const line = '═'.repeat(60);
  console.log();
  print(line, c.cyan);
  print(`  ${text}`, c.bright + c.cyan);
  print(line, c.cyan);
  console.log();
}

function printStep(text) {
  print(`→ ${text}`, c.green);
}

function printInfo(text) {
  print(`  ${text}`, c.dim);
}

function printAction(text) {
  print(`  ▶ ${text}`, c.yellow);
}

function printSuccess(text) {
  print(`  ✓ ${text}`, c.green);
}

async function waitForEnter(message = 'Press ENTER to continue...') {
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
  });

  return new Promise(resolve => {
    console.log();
    rl.question(`${c.magenta}${message}${c.reset}`, () => {
      rl.close();
      resolve();
    });
  });
}

async function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

// Wait for a node to be ready
function waitForNode(port, timeout = 10000) {
  return new Promise((resolve, reject) => {
    const start = Date.now();

    function check() {
      const req = http.get(`http://localhost:${port}`, (res) => {
        resolve();
      });

      req.on('error', () => {
        if (Date.now() - start > timeout) {
          reject(new Error(`Node on port ${port} did not start in time`));
        } else {
          setTimeout(check, 200);
        }
      });

      req.end();
    }

    check();
  });
}

// Node process management
const nodeProcesses = [];

async function startNodes() {
  print('Starting MIP nodes...', c.yellow);

  const rootDir = __dirname;

  for (const node of NODES) {
    const env = { ...process.env, CONFIG: node.configFile };
    const proc = spawn('node', ['app.js'], {
      cwd: rootDir,
      env,
      stdio: 'pipe'
    });

    proc.stderr.on('data', (data) => {
      // Suppress stderr unless debugging
    });

    nodeProcesses.push(proc);
  }

  // Wait for all nodes to be ready
  for (const node of NODES) {
    await waitForNode(node.port);
    printSuccess(`${node.name} ready on port ${node.port}`);
  }
}

function stopNodes() {
  for (const proc of nodeProcesses) {
    try {
      proc.kill('SIGTERM');
    } catch (e) {
      // Ignore
    }
  }
}

// Get MIP URL from a page
async function getMipUrl(page) {
  return await page.evaluate(() => {
    const dds = document.querySelectorAll('.identity-card dd');
    for (const dd of dds) {
      const text = dd.textContent.trim();
      if (text.includes('/mip/node/')) {
        return text;
      }
    }
    return null;
  });
}

// Main demo
async function runDemo() {
  let browser;
  let pages = [];

  try {
    // Intro
    printHeader('Member Interchange Protocol (MIP) Demo');
    print('This interactive demo will walk you through:', c.bright);
    print('  1. Starting 3 independent MIP nodes', c.dim);
    print('  2. Establishing connections between organizations', c.dim);
    print('  3. The web-of-trust automatic approval mechanism', c.dim);
    print('  4. Cross-organization member search', c.dim);
    print('  5. Certificate of Good Standing (COGS) request', c.dim);

    await waitForEnter();

    // Start nodes
    printHeader('Step 1: Starting MIP Nodes');
    printInfo('Each node represents an independent Grand Lodge with its own:');
    printInfo('  - Member database');
    printInfo('  - Cryptographic identity (RSA key pair)');
    printInfo('  - MIP identifier derived from public key');

    await startNodes();

    print('\nAll nodes are running!', c.green + c.bright);

    await waitForEnter();

    // Launch browser
    printHeader('Step 2: Opening Browser Windows');
    printInfo('Launching Chrome with 3 windows, one per node...');

    browser = await puppeteer.launch({
      headless: false,
      defaultViewport: null,
      args: [
        '--new-window',
        '--window-size=600,900',
        '--window-position=0,50'
      ]
    });

    // Get the default page and use it for node 1
    const [defaultPage] = await browser.pages();
    pages.push(defaultPage);
    await defaultPage.goto(`http://localhost:${NODES[0].port}`);
    printSuccess(`Window 1: ${NODES[0].name}`);

    // Create new windows for nodes 2 and 3
    for (let i = 1; i < 3; i++) {
      const page = await browser.newPage();
      pages.push(page);
      await page.goto(`http://localhost:${NODES[i].port}`);
      printSuccess(`Window ${i + 1}: ${NODES[i].name}`);
    }

    print('\n  Tip: Arrange the 3 browser windows side by side', c.yellow);
    print('  for the best demo experience.', c.yellow);

    await waitForEnter('Press ENTER when windows are arranged...');

    // Explain the dashboard
    printHeader('Step 3: Understanding the Dashboard');
    printInfo('Each node displays:');
    printInfo('  - Organization name and contact info');
    printInfo('  - MIP Identifier (hash of public key)');
    printInfo('  - MIP URL (the address other nodes use to connect)');
    printInfo('  - Public key fingerprint (for out-of-band verification)');
    printInfo('  - Trust threshold (endorsements needed for auto-approval)');
    console.log();
    printStep('Look at the MIP URL on each dashboard.');
    printInfo('This is what organizations share to establish connections.');

    await waitForEnter();

    // First connection: Alpha -> Beta
    printHeader('Step 4: First Connection (Alpha → Beta)');
    printInfo('Alpha will request to connect to Beta.');
    printInfo('This requires MANUAL approval - no web of trust yet.');
    console.log();

    // Get Beta's MIP URL
    printAction('Getting MIP URL from Beta\'s dashboard...');
    const betaMipUrl = await getMipUrl(pages[1]);
    print(`  URL: ${betaMipUrl}`, c.cyan);

    await waitForEnter();

    // Navigate Alpha to connections
    printAction('On Alpha: Going to Connections page...');
    await pages[0].click('a[href="/connections"]');
    await sleep(500);

    printAction('On Alpha: Entering Beta\'s MIP URL...');
    await pages[0].type('#target_url', betaMipUrl, { delay: 30 });

    await waitForEnter('Press ENTER to send the connection request...');

    printAction('Submitting connection request...');
    await Promise.all([
      pages[0].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[0].click('button[type="submit"]')
    ]);

    print('\n  What just happened:', c.bright);
    printInfo('  1. Alpha created a connection request');
    printInfo('  2. The request was signed with Alpha\'s private key');
    printInfo('  3. Alpha sent it to Beta\'s /mip/connect endpoint');
    printInfo('  4. Beta verified the signature using Alpha\'s public key');
    printInfo('  5. Beta stored the pending request');
    console.log();
    print('  Look at Alpha\'s screen - it shows "Pending Outbound Requests"', c.yellow);
    printInfo('  Alpha is waiting for Beta to approve.');

    await waitForEnter('Press ENTER to switch to Beta and see the incoming request...');

    // Approve on Beta
    printHeader('Step 5: Approving on Beta');
    printInfo('Now let\'s look at Beta\'s view.');
    printInfo('Beta received the request but hasn\'t seen it yet...');

    await waitForEnter('Press ENTER to navigate Beta to the Connections page...');

    printAction('On Beta: Going to Connections page...');
    await pages[1].click('a[href="/connections"]');
    await sleep(800);

    print('\n  Notice the "Pending Inbound Requests" section!', c.bright);
    printInfo('  The admin verifies:');
    printInfo('    - Organization name matches who they expect');
    printInfo('    - Contact person is legitimate');
    printInfo('    - Key fingerprint (verified out-of-band, e.g., phone call)');

    await waitForEnter('Press ENTER to approve the connection...');

    printAction('Clicking Approve...');
    await Promise.all([
      pages[1].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[1].click('button.success')
    ]);

    printSuccess('Beta approved the connection!');
    print('\n  Beta now shows Alpha in "Active Connections"', c.yellow);
    printInfo('  But Alpha\'s browser still shows the old state...');

    await waitForEnter('Press ENTER to refresh Alpha and see the updated state...');

    printAction('Refreshing Alpha...');
    await pages[0].reload();
    await sleep(500);

    printSuccess('Connection established: Alpha ↔ Beta');
    print('\n  Now Alpha also shows the active connection!', c.yellow);
    printInfo('  Both nodes have each other\'s verified public keys.');
    printInfo('  They can now exchange signed, verified messages.');

    await waitForEnter();

    // Second connection: Beta -> Gamma
    printHeader('Step 6: Second Connection (Beta → Gamma)');
    printInfo('Now Beta will connect to Gamma.');
    printInfo('This is still MANUAL - no common trust path exists.');

    // Refresh Gamma to get fresh MIP URL
    await pages[2].goto(`http://localhost:${NODES[2].port}`);
    await sleep(500);

    printAction('Getting Gamma\'s MIP URL...');
    const gammaMipUrl = await getMipUrl(pages[2]);
    print(`  URL: ${gammaMipUrl}`, c.cyan);

    printAction('On Beta: Going to Connections page...');
    await pages[1].click('a[href="/connections"]');
    await sleep(500);

    printAction('On Beta: Entering Gamma\'s MIP URL...');
    await pages[1].type('#target_url', gammaMipUrl, { delay: 30 });

    await waitForEnter('Press ENTER to send the request...');

    await Promise.all([
      pages[1].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[1].click('button[type="submit"]')
    ]);

    print('\n  Request sent from Beta!', c.green + c.bright);
    print('  Beta shows "Pending Outbound Requests"', c.yellow);
    printInfo('  Gamma hasn\'t seen it yet...');

    await waitForEnter('Press ENTER to switch to Gamma and see the incoming request...');

    printAction('On Gamma: Going to Connections page...');
    await pages[2].click('a[href="/connections"]');
    await sleep(800);

    print('\n  Gamma now shows the pending request from Beta!', c.yellow);

    await waitForEnter('Press ENTER to approve on Gamma...');

    printAction('Clicking Approve...');
    await Promise.all([
      pages[2].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[2].click('button.success')
    ]);

    printSuccess('Gamma approved the connection!');
    print('\n  Gamma shows Beta in "Active Connections"', c.yellow);
    printInfo('  But Beta\'s browser still shows the old "pending" state...');

    await waitForEnter('Press ENTER to refresh Beta and see the updated state...');

    printAction('Refreshing Beta...');
    await pages[1].reload();
    await sleep(500);

    printSuccess('Connection established: Beta ↔ Gamma');

    print('\n  Current network topology:', c.bright);
    print('      Alpha ←——→ Beta ←——→ Gamma', c.green);
    printInfo('  Alpha and Gamma are NOT directly connected.');
    printInfo('  But both trust Beta. This is important for what comes next...');

    await waitForEnter();

    // Third connection: Alpha -> Gamma (Web of Trust!)
    printHeader('Step 7: Web of Trust Magic (Alpha → Gamma)');
    print('  THIS IS THE KEY INNOVATION OF MIP!', c.bright + c.green);
    console.log();
    printInfo('When Alpha tries to connect to Gamma:');
    printInfo('  1. Alpha sends connection request to Gamma');
    printInfo('  2. Gamma asks: "Does anyone I trust vouch for Alpha?"');
    printInfo('  3. Gamma queries Beta (who it trusts)');
    printInfo('  4. Beta says: "Yes, I\'m connected to Alpha"');
    printInfo('  5. Beta provides a signed ENDORSEMENT for Alpha');
    printInfo('  6. Gamma\'s trust threshold is 1 endorsement');
    printInfo('  7. Threshold met → AUTO-APPROVED!');

    await waitForEnter();

    // Refresh Gamma to get fresh URL
    await pages[2].goto(`http://localhost:${NODES[2].port}`);
    await sleep(500);

    printAction('Getting Gamma\'s current MIP URL...');
    const gammaMipUrlFresh = await getMipUrl(pages[2]);

    printAction('On Alpha: Going to Connections page...');
    await pages[0].click('a[href="/connections"]');
    await sleep(500);

    printAction('On Alpha: Entering Gamma\'s MIP URL...');
    await pages[0].type('#target_url', gammaMipUrlFresh, { delay: 30 });

    await waitForEnter('Press ENTER to submit the request...');

    await Promise.all([
      pages[0].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[0].click('button[type="submit"]')
    ]);

    print('\n  Request sent from Alpha!', c.green + c.bright);
    print('\n  But wait... look at Alpha\'s screen!', c.yellow + c.bright);
    printInfo('  It doesn\'t say "Pending" - it says "Active"!');
    printInfo('  The connection was AUTO-APPROVED by web of trust.');
    console.log();
    printInfo('  Behind the scenes:');
    printInfo('    1. Alpha sent request to Gamma');
    printInfo('    2. Gamma asked Beta: "Do you trust Alpha?"');
    printInfo('    3. Beta responded: "Yes, here\'s my signed endorsement"');
    printInfo('    4. Gamma verified the endorsement and auto-approved!');

    await waitForEnter('Press ENTER to check Gamma\'s view...');

    printAction('Switching to Gamma to verify...');
    await pages[2].click('a[href="/connections"]');
    await sleep(800);

    printSuccess('CONNECTION AUTO-APPROVED ON BOTH SIDES!');
    print('\n  Gamma also shows Alpha as an active connection!', c.yellow);
    printInfo('  No manual approval was needed.');
    printInfo('  The web of trust validated the connection automatically.');

    await waitForEnter('Press ENTER to see the final network state...');

    // Refresh all to show final state
    printAction('Refreshing all browsers...');
    await pages[0].reload();
    await pages[1].goto(`http://localhost:${NODES[1].port}/connections`);
    await pages[2].reload();
    await sleep(500);

    print('\n  Final network topology:', c.bright);
    print('          Alpha', c.green);
    print('         ╱     ╲', c.green);
    print('      Beta ——— Gamma', c.green);
    printInfo('  Full mesh achieved through web of trust!');
    printInfo('  Each node can now communicate with every other node.');

    await waitForEnter();

    // Member Search Demo
    printHeader('Step 8: Member Verification Search');
    printInfo('The real purpose: verifying members across organizations.');
    printInfo('Let\'s search for a Beta member from Alpha.');

    printAction('On Alpha: Going to Searches page...');
    await pages[0].click('a[href="/searches"]');
    await sleep(500);

    printAction('On Alpha: Clicking "New Search"...');
    await pages[0].click('a[href="/searches/new"]');
    await sleep(500);

    print('\n  The search form shows:', c.bright);
    printInfo('  - Connected organizations to search');
    printInfo('  - Search by member number OR by name');
    printInfo('  - All requests are signed and logged');

    // Select Beta from dropdown
    printAction('Selecting Beta as the target organization...');
    const betaOption = await pages[0].evaluate(() => {
      const options = document.querySelectorAll('#target_mip_id option');
      for (const opt of options) {
        if (opt.textContent.includes('Beta')) {
          return opt.value;
        }
      }
      return '';
    });
    await pages[0].select('#target_mip_id', betaOption);

    printAction('Searching for last name "Davis"...');
    await pages[0].type('#last_name', 'Davis');

    await waitForEnter('Press ENTER to submit the search...');

    await Promise.all([
      pages[0].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[0].click('button[type="submit"]')
    ]);

    print('\n  Search request sent!', c.green + c.bright);
    print('  Alpha shows the search as "Pending"', c.yellow);
    printInfo('  The request was signed and sent to Beta.');
    printInfo('  Beta must approve before returning results.');

    await waitForEnter('Press ENTER to switch to Beta and see the incoming search request...');

    printAction('On Beta: Going to Searches page...');
    await pages[1].click('a[href="/searches"]');
    await sleep(800);

    print('\n  Beta shows a "Pending Inbound Request"!', c.yellow);
    printInfo('  The admin sees:');
    printInfo('    - Who is requesting (Alpha)');
    printInfo('    - What they\'re searching for (last name: Davis)');
    printInfo('  Beta can approve or decline the search.');

    await waitForEnter('Press ENTER to approve the search on Beta...');

    printAction('Clicking Approve...');
    await Promise.all([
      pages[1].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[1].click('button.success')
    ]);

    printSuccess('Search approved by Beta!');
    print('\n  Beta executed the search and returned signed results.', c.yellow);
    printInfo('  But Alpha still shows "Pending"...');

    await waitForEnter('Press ENTER to refresh Alpha and see the search results...');

    printAction('Refreshing Alpha\'s Searches page...');
    await pages[0].click('a[href="/searches"]');
    await sleep(800);

    print('\n  Alpha now shows the search results!', c.yellow + c.bright);
    printInfo('  The member "William Davis" was found at Beta.');
    console.log();
    print('  What happened behind the scenes:', c.bright);
    printInfo('    1. Alpha\'s request was signed with its private key');
    printInfo('    2. Beta verified the signature');
    printInfo('    3. Beta searched its member database');
    printInfo('    4. Beta signed the response with its private key');
    printInfo('    5. Alpha verified Beta\'s signature on the response');
    printInfo('  Full cryptographic verification at every step!');

    await waitForEnter();

    // Certificate of Good Standing Demo
    printHeader('Step 9: Certificate of Good Standing (COGS)');
    printInfo('A COGS is a formal attestation that a member is in good standing.');
    printInfo('This is used when a member wants to affiliate with another lodge.');
    console.log();
    print('  Use Case:', c.bright);
    printInfo('  A member from Beta wants to affiliate with a lodge under Alpha.');
    printInfo('  Alpha needs to verify they are in good standing at Beta.');
    printInfo('  Alpha requests a COGS from Beta for that member.');

    await waitForEnter();

    printAction('On Alpha: Going to COGS page...');
    await pages[0].click('a[href="/cogs"]');
    await sleep(500);

    printAction('On Alpha: Clicking "Request COGS"...');
    await pages[0].click('a[href="/cogs/new"]');
    await sleep(500);

    print('\n  The COGS request form requires:', c.bright);
    printInfo('  - Target organization (who holds the member\'s record)');
    printInfo('  - Member number at that organization');
    printInfo('  - Optionally, which local member is requesting');

    // Select Beta from dropdown
    printAction('Selecting Beta as the target organization...');
    const betaCogsOption = await pages[0].evaluate(() => {
      const options = document.querySelectorAll('#target_mip_id option');
      for (const opt of options) {
        if (opt.textContent.includes('Beta')) {
          return opt.value;
        }
      }
      return '';
    });
    await pages[0].select('#target_mip_id', betaCogsOption);

    printAction('Requesting COGS for member BETA-001 (William Davis)...');
    await pages[0].type('#requested_member_number', 'BETA-001');

    // Select a requesting member from Alpha
    printAction('Setting the requesting member (John Smith from Alpha)...');
    await pages[0].select('#requesting_member_number', 'ALPHA-001');
    await sleep(300);

    await waitForEnter('Press ENTER to submit the COGS request...');

    await Promise.all([
      pages[0].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[0].click('button[type="submit"]')
    ]);

    print('\n  Request sent!', c.green + c.bright);
    print('  Alpha shows the COGS request as "Pending"', c.yellow);
    printInfo('  Alpha has requested a Certificate of Good Standing');
    printInfo('  for member BETA-001 from the Grand Lodge of Beta.');
    printInfo('  Beta hasn\'t seen it yet...');

    await waitForEnter('Press ENTER to switch to Beta and see the incoming request...');

    // Approve on Beta
    printHeader('Step 10: Beta Approves the COGS');
    printInfo('Now let\'s look at Beta\'s view.');

    await waitForEnter('Press ENTER to navigate Beta to the COGS page...');

    printAction('On Beta: Going to COGS page...');
    await pages[1].click('a[href="/cogs"]');
    await sleep(800);

    print('\n  Beta sees a "Pending Inbound Request"!', c.yellow);
    printInfo('  The admin sees:');
    printInfo('    - Requesting organization (Alpha)');
    printInfo('    - Which member is being queried (BETA-001)');
    printInfo('    - Who at Alpha is requesting it');
    printInfo('  The system checks if the member is in good standing.');
    printInfo('  Since BETA-001 is in good standing, "Approve" is available.');

    await waitForEnter('Press ENTER to approve and issue the certificate...');

    printAction('Clicking Approve...');
    await Promise.all([
      pages[1].waitForNavigation({ waitUntil: 'networkidle0' }),
      pages[1].click('button.success')
    ]);

    printSuccess('Certificate of Good Standing issued!');
    print('\n  Beta shows the request as completed.', c.yellow);
    printInfo('  But Alpha still shows "Pending"...');

    await waitForEnter('Press ENTER to refresh Alpha and see the certificate...');

    // Show the result on Alpha
    printAction('On Alpha: Refreshing the COGS page...');
    await pages[0].click('a[href="/cogs"]');
    await sleep(800);

    print('\n  Alpha now shows the APPROVED certificate!', c.yellow + c.bright);
    print('\n  The certificate contains:', c.bright);
    printInfo('  - Member\'s name and profile information');
    printInfo('  - Confirmation of good standing status');
    printInfo('  - Validity period');
    printInfo('  - Cryptographic signature from Beta');
    console.log();
    printInfo('  This signed certificate is proof that can be verified');
    printInfo('  by anyone who has Beta\'s public key.');

    await waitForEnter();

    // Summary
    printHeader('Demo Complete!');
    print('  Key MIP Principles:', c.bright);
    console.log();
    print('  1. DECENTRALIZED', c.cyan);
    printInfo('     No central authority - each organization controls its data');
    console.log();
    print('  2. CRYPTOGRAPHICALLY SECURE', c.cyan);
    printInfo('     RSA signatures on every message - tamper-proof');
    console.log();
    print('  3. WEB OF TRUST', c.cyan);
    printInfo('     Connections scale through mutual endorsements');
    console.log();
    print('  4. INTEROPERABLE', c.cyan);
    printInfo('     Any MIP-compliant implementation can participate');
    console.log();
    print('  5. PRIVACY-RESPECTING', c.cyan);
    printInfo('     Members only shared on explicit request');

    console.log();
    print('  Thank you for watching!', c.bright + c.green);

    await waitForEnter('Press ENTER to close the demo...');

  } catch (error) {
    print(`\nError: ${error.message}`, c.red);
    console.error(error);
  } finally {
    // Cleanup
    print('\nCleaning up...', c.yellow);
    if (browser) {
      await browser.close();
    }
    stopNodes();
    printSuccess('Done!');
    process.exit(0);
  }
}

// Handle Ctrl+C gracefully
process.on('SIGINT', () => {
  print('\n\nInterrupted. Cleaning up...', c.yellow);
  stopNodes();
  process.exit(0);
});

process.on('uncaughtException', (err) => {
  print(`\nUnexpected error: ${err.message}`, c.red);
  stopNodes();
  process.exit(1);
});

// Run
runDemo();
