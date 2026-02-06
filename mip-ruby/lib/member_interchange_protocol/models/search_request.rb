# frozen_string_literal: true

module MemberInterchangeProtocol
  module Models
    # Tracks member search requests (inbound and outbound)
    class SearchRequest
      STATUSES = %w[PENDING APPROVED DECLINED].freeze

      attr_accessor :status, :matches, :decline_reason
      attr_reader :shared_identifier, :direction, :target_mip_identifier,
                  :target_org, :search_params, :notes, :documents, :created_at

      def initialize(attrs = {})
        @shared_identifier = attrs[:shared_identifier] || SecureRandom.uuid
        @direction = attrs[:direction] # 'inbound' or 'outbound'
        @target_mip_identifier = attrs[:target_mip_identifier]
        @target_org = attrs[:target_org]
        @search_params = attrs[:search_params] || {}
        @notes = attrs[:notes]
        @documents = attrs[:documents] || []
        @status = attrs.fetch(:status, 'PENDING')
        @matches = attrs[:matches] || []
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

      def approve!(matches)
        @status = 'APPROVED'
        @matches = matches
      end

      def decline!(reason = nil)
        @status = 'DECLINED'
        @decline_reason = reason
      end

      def search_description
        if @search_params[:member_number]
          "Member ##{@search_params[:member_number]}"
        elsif @search_params[:first_name] && @search_params[:last_name]
          name = "#{@search_params[:first_name]} #{@search_params[:last_name]}"
          name += " (#{@search_params[:birthdate]})" if @search_params[:birthdate]
          name
        else
          'Unknown search'
        end
      end

      def to_request_payload
        payload = {
          shared_identifier: @shared_identifier
        }
        payload[:member_number] = @search_params[:member_number] if @search_params[:member_number]
        payload[:first_name] = @search_params[:first_name] if @search_params[:first_name]
        payload[:last_name] = @search_params[:last_name] if @search_params[:last_name]
        payload[:birthdate] = @search_params[:birthdate] if @search_params[:birthdate]
        payload[:notes] = @notes if @notes
        payload[:documents] = @documents if @documents.any?
        payload
      end

      def to_reply_payload
        {
          shared_identifier: @shared_identifier,
          status: @status,
          matches: @matches
        }
      end

      # Create from received request payload
      def self.from_request(payload, sender_mip_id, sender_org)
        new(
          shared_identifier: payload['shared_identifier'],
          direction: 'inbound',
          target_mip_identifier: sender_mip_id,
          target_org: sender_org,
          search_params: {
            member_number: payload['member_number'],
            first_name: payload['first_name'],
            last_name: payload['last_name'],
            birthdate: payload['birthdate']
          }.compact,
          notes: payload['notes'],
          documents: payload['documents'] || []
        )
      end
    end
  end
end
