# frozen_string_literal: true

module MemberInterchangeProtocol
  module Models
    # Represents this node's identity in the MIP network
    class NodeIdentity
      attr_reader :mip_identifier, :private_key, :public_key, :organization_name,
                  :contact_person, :contact_phone, :mip_url, :share_my_organization,
                  :trust_threshold, :port

      def initialize(attrs = {})
        @mip_identifier = attrs[:mip_identifier]
        @private_key = attrs[:private_key]
        @public_key = attrs[:public_key]
        @organization_name = attrs[:organization_name]
        @contact_person = attrs[:contact_person]
        @contact_phone = attrs[:contact_phone]
        @mip_url = attrs[:mip_url]
        @share_my_organization = attrs.fetch(:share_my_organization, true)
        @trust_threshold = attrs.fetch(:trust_threshold, 1)
        @port = attrs[:port]
      end

      def public_key_fingerprint
        MemberInterchangeProtocol::Crypto.fingerprint(@public_key)
      end

      def to_node_profile
        {
          mip_identifier: @mip_identifier,
          mip_url: @mip_url,
          organization_legal_name: @organization_name,
          contact_person: @contact_person,
          contact_phone: @contact_phone,
          public_key: @public_key,
          share_my_organization: @share_my_organization
        }
      end

      # Generate a new node identity with fresh keys
      def self.generate(organization_name:, contact_person: nil, contact_phone: nil,
                        mip_url: nil, share_my_organization: true, trust_threshold: 1, port: nil)
        keys = MemberInterchangeProtocol::Crypto.generate_key_pair
        mip_id = MemberInterchangeProtocol::Identifier.generate(organization_name)

        new(
          mip_identifier: mip_id,
          private_key: keys[:private_key],
          public_key: keys[:public_key],
          organization_name: organization_name,
          contact_person: contact_person,
          contact_phone: contact_phone,
          mip_url: mip_url || "http://localhost:#{port || 4000}/mip/node/#{mip_id}",
          share_my_organization: share_my_organization,
          trust_threshold: trust_threshold,
          port: port
        )
      end

      # Generate a new node identity from config hash
      def self.from_config(config, port)
        keys = MemberInterchangeProtocol::Crypto.generate_key_pair
        mip_id = MemberInterchangeProtocol::Identifier.generate(config['organization_name'])

        new(
          mip_identifier: mip_id,
          private_key: keys[:private_key],
          public_key: keys[:public_key],
          organization_name: config['organization_name'],
          contact_person: config['contact_person'],
          contact_phone: config['contact_phone'],
          mip_url: "http://localhost:#{port}/mip/node/#{mip_id}",
          share_my_organization: config.fetch('share_my_organization', true),
          trust_threshold: config.fetch('trust_threshold', 1),
          port: port
        )
      end
    end
  end
end
