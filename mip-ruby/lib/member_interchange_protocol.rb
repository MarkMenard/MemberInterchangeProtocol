# frozen_string_literal: true

require 'openssl'
require 'digest'
require 'base64'
require 'json'
require 'time'
require 'securerandom'

require_relative 'member_interchange_protocol/version'
require_relative 'member_interchange_protocol/identifier'
require_relative 'member_interchange_protocol/crypto'
require_relative 'member_interchange_protocol/signature'
require_relative 'member_interchange_protocol/client'
require_relative 'member_interchange_protocol/models/node_identity'
require_relative 'member_interchange_protocol/models/connection'
require_relative 'member_interchange_protocol/models/member'
require_relative 'member_interchange_protocol/models/endorsement'
require_relative 'member_interchange_protocol/models/search_request'
require_relative 'member_interchange_protocol/models/cogs_request'

module MemberInterchangeProtocol
  class Error < StandardError; end
  class SignatureError < Error; end
  class ConnectionError < Error; end
  class ValidationError < Error; end
end

# Alias for convenience
MIP = MemberInterchangeProtocol
