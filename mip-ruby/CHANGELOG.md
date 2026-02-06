# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.0] - 2024-01-01

### Added

- Initial release extracted from the MIP reference implementation
- Core cryptographic operations (RSA key generation, signing, verification)
- MIP identifier generation
- Request signature creation and verification
- In-memory data store for node data
- HTTP client for outbound MIP requests
- Models:
  - `NodeIdentity` - Node identity and key management
  - `Connection` - Inter-node connection management
  - `Member` - Member data representation
  - `Endorsement` - Web-of-trust endorsement support
  - `SearchRequest` - Member search request tracking
  - `CogsRequest` - Certificate of Good Standing request tracking
- Connection lifecycle management (request, approve, decline, revoke, restore)
- Member search functionality
- Certificate of Good Standing (COGS) support
- Connected organizations discovery
