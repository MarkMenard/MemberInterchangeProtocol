# frozen_string_literal: true

module MemberInterchangeProtocol
  module Models
    # Tracks Certificate of Good Standing requests (inbound and outbound)
    class CogsRequest
      STATUSES = %w[PENDING APPROVED DECLINED].freeze

      attr_accessor :status, :certificate, :decline_reason
      attr_reader :shared_identifier, :direction, :target_mip_identifier,
                  :target_org, :requesting_member, :requested_member_number,
                  :notes, :created_at

      def initialize(attrs = {})
        @shared_identifier = attrs[:shared_identifier] || SecureRandom.uuid
        @direction = attrs[:direction] # 'inbound' or 'outbound'
        @target_mip_identifier = attrs[:target_mip_identifier]
        @target_org = attrs[:target_org]
        @requesting_member = attrs[:requesting_member] || {}
        @requested_member_number = attrs[:requested_member_number]
        @notes = attrs[:notes]
        @status = attrs.fetch(:status, 'PENDING')
        @certificate = attrs[:certificate]
        @decline_reason = attrs[:decline_reason]
        @created_at = attrs.fetch(:created_at, Time.now.iso8601)
      end

      def pending?
        @status == 'PENDING'
      end

      def approved?
        @status == 'APPROVED'
      end

      def declined?
        @status == 'DECLINED'
      end

      def inbound?
        @direction == 'inbound'
      end

      def outbound?
        @direction == 'outbound'
      end

      def approve!(member, issuing_org)
        @status = 'APPROVED'
        @certificate = {
          shared_identifier: @shared_identifier,
          status: 'APPROVED',
          good_standing: member.good_standing,
          issued_at: Time.now.iso8601,
          valid_until: (Time.now + (90 * 24 * 60 * 60)).iso8601, # 90 days
          issuing_organization: issuing_org,
          member_profile: member.to_member_profile
        }
      end

      def decline!(reason = nil)
        @status = 'DECLINED'
        @decline_reason = reason
        @certificate = {
          shared_identifier: @shared_identifier,
          status: 'DECLINED',
          good_standing: false,
          reason: reason
        }
      end

      def to_request_payload
        {
          shared_identifier: @shared_identifier,
          requesting_member: @requesting_member,
          requested_member_number: @requested_member_number,
          notes: @notes
        }.compact
      end

      def to_reply_payload
        @certificate || {
          shared_identifier: @shared_identifier,
          status: @status
        }
      end

      # Create from received request payload
      def self.from_request(payload, sender_mip_id, sender_org)
        new(
          shared_identifier: payload['shared_identifier'] || SecureRandom.uuid,
          direction: 'inbound',
          target_mip_identifier: sender_mip_id,
          target_org: sender_org,
          requesting_member: payload['requesting_member'] || {},
          requested_member_number: payload['requested_member_number'],
          notes: payload['notes']
        )
      end
    end
  end
end
