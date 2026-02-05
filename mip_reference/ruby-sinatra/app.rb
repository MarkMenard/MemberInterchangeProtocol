# frozen_string_literal: true

require 'sinatra/base'
require 'yaml'
require 'json'
require 'erb'

require_relative 'lib/mip'

class MIPApp < Sinatra::Base
  set :views, File.expand_path('views', __dir__)
  set :public_folder, File.expand_path('public', __dir__)
  set :erb, escape_html: true

  configure do
    enable :logging
  end

  # Load configuration and initialize node on startup
  def self.initialize_node!
    config_file = ENV['CONFIG'] || 'config/node1.yml'
    config = YAML.load_file(config_file)
    port = config['port']

    # Reset store and create new identity
    MIP::Store.reset!
    store = MIP::Store.current

    identity = MIP::Models::NodeIdentity.from_config(config, port)
    store.set_node_identity(identity)

    # Load members from config
    (config['members'] || []).each do |member_config|
      member = MIP::Models::Member.from_config(member_config)
      store.add_member(member)
    end

    store.log_activity("Node initialized: #{identity.organization_name}")

    puts "=" * 60
    puts "MIP Node Started: #{identity.organization_name}"
    puts "MIP Identifier: #{identity.mip_identifier}"
    puts "MIP URL: #{identity.mip_url}"
    puts "Public Key Fingerprint: #{identity.public_key_fingerprint}"
    puts "Members loaded: #{store.all_members.count}"
    puts "=" * 60
  end

  helpers do
    def store
      MIP::Store.current
    end

    def identity
      store.node_identity
    end

    def client
      @client ||= MIP::Client.new(identity)
    end

    def json_body
      @json_body ||= begin
        request.body.rewind
        body = request.body.read
        body.empty? ? {} : JSON.parse(body)
      end
    end

    def mip_response(succeeded:, data: {})
      content_type :json
      { meta: { succeeded: succeeded }, data: data }.to_json
    end

    def verify_mip_request!
      mip_id = request.env['HTTP_X_MIP_MIP_IDENTIFIER']
      timestamp = request.env['HTTP_X_MIP_TIMESTAMP']
      signature = request.env['HTTP_X_MIP_SIGNATURE']
      public_key_header = request.env['HTTP_X_MIP_PUBLIC_KEY']

      # Validate required headers
      halt 400, mip_response(succeeded: false, data: { error: 'Missing MIP headers' }) unless mip_id && timestamp && signature

      # Validate timestamp
      unless MIP::Signature.timestamp_valid?(timestamp)
        halt 400, mip_response(succeeded: false, data: { error: 'Invalid timestamp' })
      end

      # Get public key - from connection or header
      connection = store.find_connection(mip_id)
      public_key = if connection
                     connection.public_key
                   elsif public_key_header
                     Base64.strict_decode64(public_key_header)
                   end

      halt 401, mip_response(succeeded: false, data: { error: 'Unknown sender' }) unless public_key

      # Verify signature
      request.body.rewind
      body = request.body.read
      request.body.rewind

      unless MIP::Signature.verify_request(public_key, signature, timestamp, request.path_info, body.empty? ? nil : body)
        halt 401, mip_response(succeeded: false, data: { error: 'Invalid signature' })
      end

      { mip_id: mip_id, connection: connection, public_key: public_key }
    end

    def require_active_connection!(sender)
      unless sender[:connection]&.active?
        halt 403, mip_response(succeeded: false, data: { error: 'No active connection' })
      end
    end

    def h(text)
      Rack::Utils.escape_html(text.to_s)
    end

    def format_time(iso_time)
      Time.iso8601(iso_time).strftime('%Y-%m-%d %H:%M')
    rescue
      iso_time
    end
  end

  # ============================================================================
  # Admin Dashboard Routes
  # ============================================================================

  get '/' do
    erb :dashboard
  end

  # Connections
  get '/connections' do
    erb :'connections/index'
  end

  get '/connections/:mip_id' do
    @connection = store.find_connection(params[:mip_id])
    halt 404, 'Connection not found' unless @connection
    erb :'connections/show'
  end

  # Initiate a new connection
  post '/connections' do
    target_url = params[:target_url]&.strip
    halt 400, 'Target URL required' if target_url.nil? || target_url.empty?

    # Collect endorsements from active connections for this request
    endorsements = store.find_endorsements_for(identity.mip_identifier)

    begin
      result = client.request_connection(target_url, endorsements: endorsements)

      if result[:success] && result[:body]['meta']['succeeded']
        # Create outbound connection record
        response_data = result[:body]['data']['mip_connection']
        node_profile = response_data['node_profile'] || {}

        # Extract MIP ID from target URL
        target_mip_id = target_url.split('/').last

        connection = MIP::Models::Connection.new(
          mip_identifier: node_profile['mip_identifier'] || target_mip_id,
          mip_url: target_url,
          public_key: node_profile['public_key'],
          organization_name: node_profile['organization_legal_name'] || 'Unknown',
          contact_person: node_profile['contact_person'],
          contact_phone: node_profile['contact_phone'],
          status: response_data['status'],
          direction: 'outbound',
          daily_rate_limit: response_data['daily_rate_limit']
        )
        store.add_connection(connection)

        # If auto-approved, exchange endorsements
        if connection.active?
          send_endorsement_to_connection(connection)
        end

        redirect '/connections'
      else
        error_msg = result[:body].dig('data', 'error') || 'Connection request failed'
        halt 400, error_msg
      end
    rescue Faraday::Error => e
      halt 500, "Connection failed: #{e.message}"
    end
  end

  # Approve a pending inbound connection
  post '/connections/:mip_id/approve' do
    connection = store.find_connection(params[:mip_id])
    halt 404, 'Connection not found' unless connection
    halt 400, 'Connection is not pending' unless connection.pending?

    connection.approve!(daily_rate_limit: 100)
    store.log_activity("Approved connection: #{connection.organization_name}")

    # Notify the other node
    begin
      client.approve_connection(
        connection.mip_url,
        node_profile: identity.to_node_profile,
        daily_rate_limit: 100
      )

      # Exchange endorsements
      send_endorsement_to_connection(connection)
    rescue Faraday::Error => e
      store.log_activity("Failed to notify approval: #{e.message}")
    end

    redirect '/connections'
  end

  # Decline a pending inbound connection
  post '/connections/:mip_id/decline' do
    connection = store.find_connection(params[:mip_id])
    halt 404, 'Connection not found' unless connection
    halt 400, 'Connection is not pending' unless connection.pending?

    reason = params[:reason]
    connection.decline!(reason: reason)
    store.log_activity("Declined connection: #{connection.organization_name}")

    # Notify the other node
    begin
      client.decline_connection(connection.mip_url, reason: reason)
    rescue Faraday::Error => e
      store.log_activity("Failed to notify decline: #{e.message}")
    end

    redirect '/connections'
  end

  # Revoke an active connection
  post '/connections/:mip_id/revoke' do
    connection = store.find_connection(params[:mip_id])
    halt 404, 'Connection not found' unless connection
    halt 400, 'Connection is not active' unless connection.active?

    reason = params[:reason]
    connection.revoke!(reason: reason)
    store.log_activity("Revoked connection: #{connection.organization_name}")

    begin
      client.revoke_connection(connection.mip_url, reason: reason)
    rescue Faraday::Error => e
      store.log_activity("Failed to notify revoke: #{e.message}")
    end

    redirect '/connections'
  end

  # Restore a revoked connection
  post '/connections/:mip_id/restore' do
    connection = store.find_connection(params[:mip_id])
    halt 404, 'Connection not found' unless connection
    halt 400, 'Connection is not revoked' unless connection.revoked?

    connection.restore!
    store.log_activity("Restored connection: #{connection.organization_name}")

    begin
      client.restore_connection(connection.mip_url)
    rescue Faraday::Error => e
      store.log_activity("Failed to notify restore: #{e.message}")
    end

    redirect '/connections'
  end

  # Members
  get '/members' do
    erb :'members/index'
  end

  # Searches
  get '/searches' do
    erb :'searches/index'
  end

  get '/searches/new' do
    erb :'searches/new'
  end

  # Initiate a search
  post '/searches' do
    target_mip_id = params[:target_mip_id]
    connection = store.find_connection(target_mip_id)
    halt 400, 'Invalid connection' unless connection&.active?

    search_params = {}
    search_params[:member_number] = params[:member_number] unless params[:member_number].to_s.empty?
    search_params[:first_name] = params[:first_name] unless params[:first_name].to_s.empty?
    search_params[:last_name] = params[:last_name] unless params[:last_name].to_s.empty?
    search_params[:birthdate] = params[:birthdate] unless params[:birthdate].to_s.empty?

    halt 400, 'Search criteria required' if search_params.empty?

    search_request = MIP::Models::SearchRequest.new(
      direction: 'outbound',
      target_mip_identifier: connection.mip_identifier,
      target_org: connection.organization_name,
      search_params: search_params,
      notes: params[:notes]
    )
    store.add_search_request(search_request)

    begin
      result = client.member_search(connection.mip_url, search_request)
      if result[:success]
        store.log_activity("Search sent to #{connection.organization_name}")
      end
    rescue Faraday::Error => e
      store.log_activity("Search failed: #{e.message}")
    end

    redirect '/searches'
  end

  # Approve an inbound search
  post '/searches/:id/approve' do
    search = store.find_search_request(params[:id])
    halt 404, 'Search not found' unless search
    halt 400, 'Search is not pending' unless search.pending?

    # Find matching members
    matches = store.search_members(search.search_params).map(&:to_search_result)
    search.approve!(matches)
    store.log_activity("Approved search from #{search.target_org}: #{matches.count} matches")

    # Send reply
    connection = store.find_connection(search.target_mip_identifier)
    if connection&.active?
      begin
        client.member_search_reply(connection.mip_url, search)
      rescue Faraday::Error => e
        store.log_activity("Failed to send search reply: #{e.message}")
      end
    end

    redirect '/searches'
  end

  # Decline an inbound search
  post '/searches/:id/decline' do
    search = store.find_search_request(params[:id])
    halt 404, 'Search not found' unless search
    halt 400, 'Search is not pending' unless search.pending?

    search.decline!(params[:reason])
    store.log_activity("Declined search from #{search.target_org}")

    # Send reply
    connection = store.find_connection(search.target_mip_identifier)
    if connection&.active?
      begin
        client.member_search_reply(connection.mip_url, search)
      rescue Faraday::Error => e
        store.log_activity("Failed to send search reply: #{e.message}")
      end
    end

    redirect '/searches'
  end

  # COGS
  get '/cogs' do
    erb :'cogs/index'
  end

  get '/cogs/new' do
    erb :'cogs/new'
  end

  # Request a COGS
  post '/cogs' do
    target_mip_id = params[:target_mip_id]
    connection = store.find_connection(target_mip_id)
    halt 400, 'Invalid connection' unless connection&.active?

    cogs = MIP::Models::CogsRequest.new(
      direction: 'outbound',
      target_mip_identifier: connection.mip_identifier,
      target_org: connection.organization_name,
      requesting_member: {
        member_number: params[:requesting_member_number],
        first_name: params[:requesting_first_name],
        last_name: params[:requesting_last_name]
      },
      requested_member_number: params[:requested_member_number],
      notes: params[:notes]
    )
    store.add_cogs_request(cogs)

    begin
      result = client.request_cogs(connection.mip_url, cogs)
      if result[:success]
        store.log_activity("COGS requested from #{connection.organization_name}")
      end
    rescue Faraday::Error => e
      store.log_activity("COGS request failed: #{e.message}")
    end

    redirect '/cogs'
  end

  # Approve an inbound COGS
  post '/cogs/:id/approve' do
    cogs = store.find_cogs_request(params[:id])
    halt 404, 'COGS not found' unless cogs
    halt 400, 'COGS is not pending' unless cogs.pending?

    member = store.find_member(cogs.requested_member_number)
    halt 400, 'Member not found' unless member

    issuing_org = {
      mip_identifier: identity.mip_identifier,
      organization_legal_name: identity.organization_name
    }
    cogs.approve!(member, issuing_org)
    store.log_activity("Approved COGS for #{member.member_number}")

    # Send reply
    connection = store.find_connection(cogs.target_mip_identifier)
    if connection&.active?
      begin
        client.cogs_reply(connection.mip_url, cogs)
      rescue Faraday::Error => e
        store.log_activity("Failed to send COGS reply: #{e.message}")
      end
    end

    redirect '/cogs'
  end

  # Decline an inbound COGS
  post '/cogs/:id/decline' do
    cogs = store.find_cogs_request(params[:id])
    halt 404, 'COGS not found' unless cogs
    halt 400, 'COGS is not pending' unless cogs.pending?

    cogs.decline!(params[:reason] || 'Request declined')
    store.log_activity("Declined COGS request")

    # Send reply
    connection = store.find_connection(cogs.target_mip_identifier)
    if connection&.active?
      begin
        client.cogs_reply(connection.mip_url, cogs)
      rescue Faraday::Error => e
        store.log_activity("Failed to send COGS reply: #{e.message}")
      end
    end

    redirect '/cogs'
  end

  # ============================================================================
  # MIP Protocol Endpoints
  # ============================================================================

  # Connection request
  post '/mip/node/:mip_id/mip_connections' do
    sender = verify_mip_request!

    # Check if connection already exists
    existing = store.find_connection(json_body['mip_identifier'])
    if existing
      return mip_response(
        succeeded: true,
        data: {
          mip_connection: {
            status: existing.status,
            daily_rate_limit: existing.daily_rate_limit,
            node_profile: identity.to_node_profile
          }
        }
      )
    end

    # Create new connection from request
    connection = MIP::Models::Connection.from_request(json_body, direction: 'inbound')

    # Check for auto-approval via web-of-trust
    endorsements = json_body['endorsements'] || []
    trusted_count = count_trusted_endorsements(endorsements, connection.public_key)

    if trusted_count >= identity.trust_threshold
      connection.approve!(daily_rate_limit: 100)
      store.add_connection(connection)
      store.log_activity("Auto-approved connection: #{connection.organization_name} (#{trusted_count} trusted endorsements)")

      # Send endorsement to new connection
      Thread.new { send_endorsement_to_connection(connection) }

      mip_response(
        succeeded: true,
        data: {
          mip_connection: {
            status: 'ACTIVE',
            daily_rate_limit: 100,
            node_profile: identity.to_node_profile
          }
        }
      )
    else
      store.add_connection(connection)
      store.log_activity("Connection request from: #{connection.organization_name}")

      mip_response(
        succeeded: true,
        data: {
          mip_connection: {
            status: 'PENDING',
            daily_rate_limit: 100,
            node_profile: identity.to_node_profile
          }
        }
      )
    end
  end

  # Connection approved notification
  post '/mip/node/:mip_id/mip_connections/approved' do
    sender = verify_mip_request!

    connection = store.find_connection(sender[:mip_id])
    halt 404, mip_response(succeeded: false, data: { error: 'Connection not found' }) unless connection

    node_profile = json_body['node_profile']
    connection.approve!(
      node_profile: node_profile,
      daily_rate_limit: json_body['daily_rate_limit'] || 100
    )
    store.log_activity("Connection approved by: #{connection.organization_name}")

    # Send endorsement to the approving node
    Thread.new { send_endorsement_to_connection(connection) }

    mip_response(succeeded: true, data: { mip_connection: { status: 'ACTIVE' } })
  end

  # Connection declined notification
  post '/mip/node/:mip_id/mip_connections/declined' do
    sender = verify_mip_request!

    connection = store.find_connection(sender[:mip_id])
    halt 404, mip_response(succeeded: false, data: { error: 'Connection not found' }) unless connection

    connection.decline!(reason: json_body['reason'])
    store.log_activity("Connection declined by: #{connection.organization_name}")

    mip_response(succeeded: true, data: { mip_connection: { status: 'DECLINED' } })
  end

  # Connection revoke notification
  post '/mip/node/:mip_id/mip_connections/revoke' do
    sender = verify_mip_request!

    connection = store.find_connection(sender[:mip_id])
    halt 404, mip_response(succeeded: false, data: { error: 'Connection not found' }) unless connection

    connection.revoke!(reason: json_body['reason'])
    store.log_activity("Connection revoked by: #{connection.organization_name}")

    mip_response(succeeded: true, data: { mip_connection: { status: 'REVOKED' } })
  end

  # Connection restore notification
  post '/mip/node/:mip_id/mip_connections/restore' do
    sender = verify_mip_request!

    connection = store.find_connection(sender[:mip_id])
    halt 404, mip_response(succeeded: false, data: { error: 'Connection not found' }) unless connection

    connection.restore!
    store.log_activity("Connection restored by: #{connection.organization_name}")

    mip_response(succeeded: true, data: { mip_connection: { status: 'ACTIVE' } })
  end

  # Receive endorsement
  post '/mip/node/:mip_id/endorsements' do
    sender = verify_mip_request!
    require_active_connection!(sender)

    endorsement = MIP::Models::Endorsement.from_payload(json_body)

    # Verify the endorsement signature
    unless endorsement.verify_signature(sender[:connection].public_key)
      halt 400, mip_response(succeeded: false, data: { error: 'Invalid endorsement signature' })
    end

    store.add_endorsement(endorsement)

    # Check if this endorsement enables any pending connections to be auto-approved
    check_pending_connections_for_auto_approval

    mip_response(succeeded: true, data: { endorsement_id: endorsement.id })
  end

  # Query connected organizations
  get '/mip/node/:mip_id/connected_organizations_query' do
    sender = verify_mip_request!
    require_active_connection!(sender)

    shareable_orgs = store.active_connections
                          .select(&:share_my_organization)
                          .reject { |c| c.mip_identifier == sender[:mip_id] }
                          .map(&:to_node_profile)

    mip_response(succeeded: true, data: { organizations: shareable_orgs })
  end

  # Member search request
  post '/mip/node/:mip_id/mip_member_searches' do
    sender = verify_mip_request!
    require_active_connection!(sender)

    search = MIP::Models::SearchRequest.from_request(
      json_body,
      sender[:mip_id],
      sender[:connection].organization_name
    )
    store.add_search_request(search)

    mip_response(
      succeeded: true,
      data: {
        status: 'PENDING',
        shared_identifier: search.shared_identifier
      }
    )
  end

  # Member search reply
  post '/mip/node/:mip_id/mip_member_searches/reply' do
    sender = verify_mip_request!
    require_active_connection!(sender)

    data = json_body['data'] || json_body
    shared_id = data['shared_identifier']
    search = store.find_search_request(shared_id)

    if search
      if data['status'] == 'APPROVED'
        search.approve!(data['matches'] || [])
        store.log_activity("Search results received: #{search.matches.count} matches")
      else
        search.decline!(data['reason'])
        store.log_activity("Search declined")
      end
    end

    mip_response(succeeded: true, data: { acknowledged: true })
  end

  # Member status check (real-time)
  post '/mip/node/:mip_id/member_status_checks' do
    sender = verify_mip_request!
    require_active_connection!(sender)

    member_number = json_body['member_number']
    member = store.find_member(member_number)

    if member
      mip_response(succeeded: true, data: member.to_status_check)
    else
      mip_response(succeeded: true, data: { found: false, member_number: member_number })
    end
  end

  # COGS request
  post '/mip/node/:mip_id/certificates_of_good_standing' do
    sender = verify_mip_request!
    require_active_connection!(sender)

    cogs = MIP::Models::CogsRequest.from_request(
      json_body,
      sender[:mip_id],
      sender[:connection].organization_name
    )
    store.add_cogs_request(cogs)

    mip_response(
      succeeded: true,
      data: {
        status: 'PENDING',
        shared_identifier: cogs.shared_identifier
      }
    )
  end

  # COGS reply
  post '/mip/node/:mip_id/certificates_of_good_standing/reply' do
    sender = verify_mip_request!
    require_active_connection!(sender)

    shared_id = json_body['shared_identifier']
    cogs = store.find_cogs_request(shared_id)

    if cogs
      if json_body['status'] == 'APPROVED'
        cogs.status = 'APPROVED'
        cogs.certificate = json_body
        store.log_activity("COGS received for #{json_body.dig('member_profile', 'member_number')}")
      else
        cogs.status = 'DECLINED'
        cogs.decline_reason = json_body['reason']
        store.log_activity("COGS declined: #{json_body['reason']}")
      end
    end

    mip_response(
      succeeded: true,
      data: {
        acknowledged: true,
        shared_identifier: shared_id
      }
    )
  end

  private

  def send_endorsement_to_connection(connection)
    endorsement = MIP::Models::Endorsement.create(
      identity,
      connection.mip_identifier,
      connection.public_key
    )

    begin
      client.send_endorsement(connection.mip_url, endorsement)
      store.log_activity("Sent endorsement to #{connection.organization_name}")
    rescue Faraday::Error => e
      store.log_activity("Failed to send endorsement: #{e.message}")
    end
  end

  def count_trusted_endorsements(endorsements, endorsed_public_key)
    fingerprint = MIP::Crypto.fingerprint(endorsed_public_key)
    count = 0

    endorsements.each do |endorsement_data|
      endorser_id = endorsement_data['endorser_mip_identifier']
      connection = store.find_connection(endorser_id)

      next unless connection&.active?

      endorsement = MIP::Models::Endorsement.from_payload(endorsement_data)
      next unless endorsement.valid_for?(fingerprint)
      next unless endorsement.verify_signature(connection.public_key)

      count += 1
    end

    count
  end

  def check_pending_connections_for_auto_approval
    store.pending_connections.each do |connection|
      endorsements = store.find_endorsements_for(connection.mip_identifier)

      trusted_count = endorsements.count do |e|
        endorser_connection = store.find_connection(e.endorser_mip_identifier)
        next false unless endorser_connection&.active?
        next false unless e.valid_for?(connection.public_key_fingerprint)

        e.verify_signature(endorser_connection.public_key)
      end

      if trusted_count >= identity.trust_threshold
        connection.approve!(daily_rate_limit: 100)
        store.log_activity("Auto-approved pending connection: #{connection.organization_name}")

        # Notify and exchange endorsements
        Thread.new do
          client.approve_connection(
            connection.mip_url,
            node_profile: identity.to_node_profile,
            daily_rate_limit: 100
          )
          send_endorsement_to_connection(connection)
        end
      end
    end
  end
end

# Initialize on load if running directly
if __FILE__ == $PROGRAM_NAME || ENV['CONFIG']
  MIPApp.initialize_node!
end
