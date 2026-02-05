# frozen_string_literal: true

module MIP
  module Models
    # Represents a connection with another MIP node
    class Connection
      STATUSES = %w[PENDING ACTIVE DECLINED REVOKED].freeze

      attr_accessor :status, :daily_rate_limit, :share_my_organization
      attr_reader :mip_identifier, :mip_url, :public_key, :organization_name,
                  :contact_person, :contact_phone, :direction, :created_at,
                  :decline_reason, :revoke_reason

      def initialize(attrs = {})
        @mip_identifier = attrs[:mip_identifier]
        @mip_url = attrs[:mip_url]
        @public_key = attrs[:public_key]
        @organization_name = attrs[:organization_name]
        @contact_person = attrs[:contact_person]
        @contact_phone = attrs[:contact_phone]
        @status = attrs.fetch(:status, 'PENDING')
        @direction = attrs[:direction] # 'inbound' or 'outbound'
        @share_my_organization = attrs.fetch(:share_my_organization, true)
        @daily_rate_limit = attrs.fetch(:daily_rate_limit, 100)
        @created_at = attrs.fetch(:created_at, Time.now.iso8601)
        @decline_reason = attrs[:decline_reason]
        @revoke_reason = attrs[:revoke_reason]
      end

      def active?
        @status == 'ACTIVE'
      end

      def pending?
        @status == 'PENDING'
      end

      def declined?
        @status == 'DECLINED'
      end

      def revoked?
        @status == 'REVOKED'
      end

      def inbound?
        @direction == 'inbound'
      end

      def outbound?
        @direction == 'outbound'
      end

      def approve!(node_profile: nil, daily_rate_limit: 100)
        @status = 'ACTIVE'
        @daily_rate_limit = daily_rate_limit
        update_from_profile(node_profile) if node_profile
      end

      def decline!(reason: nil)
        @status = 'DECLINED'
        @decline_reason = reason
      end

      def revoke!(reason: nil)
        @status = 'REVOKED'
        @revoke_reason = reason
      end

      def restore!
        @status = 'ACTIVE'
        @revoke_reason = nil
      end

      def public_key_fingerprint
        MIP::Crypto.fingerprint(@public_key)
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

      # Create from a connection request payload
      def self.from_request(payload, direction: 'inbound')
        new(
          mip_identifier: payload['mip_identifier'],
          mip_url: payload['mip_url'],
          public_key: payload['public_key'],
          organization_name: payload['organization_legal_name'],
          contact_person: payload['contact_person'],
          contact_phone: payload['contact_phone'],
          share_my_organization: payload.fetch('share_my_organization', true),
          direction: direction,
          status: 'PENDING'
        )
      end

      private

      def update_from_profile(profile)
        @organization_name = profile['organization_legal_name'] if profile['organization_legal_name']
        @contact_person = profile['contact_person'] if profile['contact_person']
        @contact_phone = profile['contact_phone'] if profile['contact_phone']
        @mip_url = profile['mip_url'] if profile['mip_url']
        @public_key = profile['public_key'] if profile['public_key']
      end
    end
  end
end
