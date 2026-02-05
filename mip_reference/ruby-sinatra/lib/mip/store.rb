# frozen_string_literal: true

module MIP
  # In-memory data store for MIP node data
  # Uses a simple singleton pattern for this reference implementation
  class Store
    class << self
      attr_accessor :instance
    end

    attr_reader :node_identity, :connections, :members, :endorsements,
                :search_requests, :cogs_requests, :activity_log

    def initialize
      @node_identity = nil
      @connections = {}      # mip_identifier => Connection
      @members = {}          # member_number => Member
      @endorsements = {}     # endorsement_id => Endorsement
      @search_requests = {}  # shared_identifier => SearchRequest
      @cogs_requests = {}    # shared_identifier => CogsRequest
      @activity_log = []     # Array of activity entries
    end

    def set_node_identity(identity)
      @node_identity = identity
    end

    # Connection management
    def add_connection(connection)
      @connections[connection.mip_identifier] = connection
      log_activity("Connection added: #{connection.organization_name} (#{connection.status})")
    end

    def find_connection(mip_identifier)
      @connections[mip_identifier]
    end

    def active_connections
      @connections.values.select(&:active?)
    end

    def pending_connections
      @connections.values.select(&:pending?)
    end

    def all_connections
      @connections.values
    end

    # Member management
    def add_member(member)
      @members[member.member_number] = member
    end

    def find_member(member_number)
      @members[member_number]
    end

    def search_members(query)
      @members.values.select do |m|
        if query[:member_number]
          m.member_number.downcase.include?(query[:member_number].downcase)
        elsif query[:first_name] && query[:last_name]
          name_match = m.first_name.downcase.include?(query[:first_name].downcase) &&
                       m.last_name.downcase.include?(query[:last_name].downcase)
          if query[:birthdate]
            name_match && m.birthdate == query[:birthdate]
          else
            name_match
          end
        else
          false
        end
      end
    end

    def all_members
      @members.values
    end

    # Endorsement management
    def add_endorsement(endorsement)
      @endorsements[endorsement.id] = endorsement
      log_activity("Endorsement received from #{endorsement.endorser_mip_identifier}")
    end

    def find_endorsements_for(mip_identifier)
      @endorsements.values.select { |e| e.endorsed_mip_identifier == mip_identifier }
    end

    def find_endorsements_from(mip_identifier)
      @endorsements.values.select { |e| e.endorser_mip_identifier == mip_identifier }
    end

    # Search request management
    def add_search_request(search_request)
      @search_requests[search_request.shared_identifier] = search_request
      log_activity("Search request: #{search_request.direction} - #{search_request.target_org}")
    end

    def find_search_request(shared_identifier)
      @search_requests[shared_identifier]
    end

    def all_search_requests
      @search_requests.values
    end

    # COGS request management
    def add_cogs_request(cogs_request)
      @cogs_requests[cogs_request.shared_identifier] = cogs_request
      log_activity("COGS request: #{cogs_request.direction} - #{cogs_request.target_org}")
    end

    def find_cogs_request(shared_identifier)
      @cogs_requests[shared_identifier]
    end

    def all_cogs_requests
      @cogs_requests.values
    end

    # Activity log
    def log_activity(message)
      @activity_log.unshift({
        timestamp: Time.now.iso8601,
        message: message
      })
      # Keep only last 100 entries
      @activity_log = @activity_log.first(100)
    end

    def recent_activity(count = 20)
      @activity_log.first(count)
    end

    # Class method for global access
    def self.current
      @instance ||= new
    end

    def self.reset!
      @instance = new
    end
  end
end
