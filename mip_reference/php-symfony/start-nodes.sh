#!/bin/bash

# Start MIP Symfony Reference Implementation Nodes
# This script starts three PHP development servers on ports 4013, 4014, and 4015

echo "Starting MIP Symfony Reference Implementation..."
echo ""

# Create data directories
mkdir -p data/node4013 data/node4014 data/node4015

# Kill any existing PHP servers on these ports
pkill -f "php -S localhost:401[345]" 2>/dev/null

# Start Node 1 (Grand Lodge of Alpha) on port 4013
echo "Starting Grand Lodge of Alpha on port 4013..."
php -S localhost:4013 -t public > /tmp/mip-node4013.log 2>&1 &
echo "PID: $!"

# Start Node 2 (Grand Lodge of Beta) on port 4014
echo "Starting Grand Lodge of Beta on port 4014..."
php -S localhost:4014 -t public > /tmp/mip-node4014.log 2>&1 &
echo "PID: $!"

# Start Node 3 (Grand Lodge of Gamma) on port 4015
echo "Starting Grand Lodge of Gamma on port 4015..."
php -S localhost:4015 -t public > /tmp/mip-node4015.log 2>&1 &
echo "PID: $!"

echo ""
echo "All nodes started!"
echo ""
echo "Access the dashboards at:"
echo "  - Grand Lodge of Alpha: http://localhost:4013"
echo "  - Grand Lodge of Beta:  http://localhost:4014"
echo "  - Grand Lodge of Gamma: http://localhost:4015"
echo ""
echo "Logs are available at:"
echo "  - /tmp/mip-node4013.log"
echo "  - /tmp/mip-node4014.log"
echo "  - /tmp/mip-node4015.log"
echo ""
echo "To stop all nodes, run: pkill -f 'php -S localhost:401[345]'"
