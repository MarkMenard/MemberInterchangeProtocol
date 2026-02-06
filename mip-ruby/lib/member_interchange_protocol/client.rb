# frozen_string_literal: true

require 'faraday'
require 'uri'

module MemberInterchangeProtocol
  # HTTP client for making outbound MIP requests
  class Client
    def initialize(node_identity)
      @identity = node_identity
    end

    # Request a connection with another node
    def request_connection(target_url, endorsements: [])
      payload = {
        mip_identifier: @identity.mip_identifier,
        mip_url: @identity.mip_url,
        public_key: @identity.public_key,
        organization_legal_name: @identity.organization_name,
        contact_person: @identity.contact_person,
        contact_phone: @identity.contact_phone,
        share_my_organization: @identity.share_my_organization,
        endorsements: endorsements.map(&:to_payload)
      }

      post_request(build_url(target_url, '/mip_connections'), payload, include_public_key: true)
    end

    # Notify a node their connection request was approved
    def approve_connection(target_url, node_profile:, daily_rate_limit: 100)
      payload = {
        node_profile: node_profile,
        share_my_organization: @identity.share_my_organization,
        daily_rate_limit: daily_rate_limit
      }

      post_request(build_url(target_url, '/mip_connections/approved'), payload)
    end

    # Notify a node their connection request was declined
    def decline_connection(target_url, reason: nil)
      payload = {
        mip_identifier: @identity.mip_identifier,
        reason: reason
      }

      post_request(build_url(target_url, '/mip_connections/declined'), payload)
    end

    # Notify a node their connection has been revoked
    def revoke_connection(target_url, reason: nil)
      payload = {
        mip_identifier: @identity.mip_identifier,
        reason: reason
      }

      post_request(build_url(target_url, '/mip_connections/revoke'), payload)
    end

    # Notify a node their connection has been restored
    def restore_connection(target_url)
      payload = {
        mip_identifier: @identity.mip_identifier
      }

      post_request(build_url(target_url, '/mip_connections/restore'), payload)
    end

    # Send an endorsement to another node
    def send_endorsement(target_url, endorsement)
      post_request(build_url(target_url, '/endorsements'), endorsement.to_payload)
    end

    # Send a member search request
    def member_search(target_url, search_request)
      post_request(build_url(target_url, '/mip_member_searches'), search_request.to_request_payload)
    end

    # Send member search results back to requester
    def member_search_reply(target_url, search_request)
      payload = {
        meta: { succeeded: true },
        data: search_request.to_reply_payload
      }

      post_request(build_url(target_url, '/mip_member_searches/reply'), payload)
    end

    # Request a Certificate of Good Standing
    def request_cogs(target_url, cogs_request)
      post_request(build_url(target_url, '/certificates_of_good_standing'), cogs_request.to_request_payload)
    end

    # Send COGS reply back to requester
    def cogs_reply(target_url, cogs_request)
      post_request(build_url(target_url, '/certificates_of_good_standing/reply'), cogs_request.to_reply_payload)
    end

    # Query connected organizations
    def connected_organizations_query(target_url)
      get_request(build_url(target_url, '/connected_organizations_query'))
    end

    private

    def post_request(url, payload, include_public_key: false)
      timestamp = Time.now.iso8601
      path = extract_path(url)
      json_body = payload.to_json
      signature = MemberInterchangeProtocol::Signature.sign_request(@identity.private_key, timestamp, path, json_body)

      headers = {
        'Content-Type' => 'application/json',
        'X-MIP-MIP-IDENTIFIER' => @identity.mip_identifier,
        'X-MIP-TIMESTAMP' => timestamp,
        'X-MIP-SIGNATURE' => signature
      }
      headers['X-MIP-PUBLIC-KEY'] = Base64.strict_encode64(@identity.public_key) if include_public_key

      response = connection.post(url) do |req|
        req.headers = headers
        req.body = json_body
      end

      parse_response(response)
    end

    def get_request(url)
      timestamp = Time.now.iso8601
      path = extract_path(url)
      signature = MemberInterchangeProtocol::Signature.sign_request(@identity.private_key, timestamp, path)

      headers = {
        'X-MIP-MIP-IDENTIFIER' => @identity.mip_identifier,
        'X-MIP-TIMESTAMP' => timestamp,
        'X-MIP-SIGNATURE' => signature
      }

      response = connection.get(url) do |req|
        req.headers = headers
      end

      parse_response(response)
    end

    def connection
      @connection ||= Faraday.new do |f|
        f.options.timeout = 30
        f.options.open_timeout = 10
      end
    end

    def extract_path(url)
      URI.parse(url).path
    end

    def build_url(base_url, endpoint)
      # base_url is like "http://localhost:4001/mip/node/abc123"
      # We need to append the endpoint
      base_url.chomp('/') + endpoint
    end

    def parse_response(response)
      {
        success: response.success?,
        status: response.status,
        body: response.body.empty? ? {} : JSON.parse(response.body)
      }
    rescue JSON::ParserError
      {
        success: false,
        status: response.status,
        body: { error: 'Invalid JSON response' }
      }
    end
  end
end
