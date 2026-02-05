# frozen_string_literal: true

module MIP
  module Models
    # Represents a member in the local organization
    class Member
      attr_accessor :member_number, :prefix, :first_name, :middle_name, :last_name,
                    :suffix, :honorific, :rank, :birthdate, :years_in_good_standing,
                    :status, :is_active, :good_standing, :email, :phone, :cell,
                    :address, :affiliations, :life_cycle_events

      def initialize(attrs = {})
        @member_number = attrs[:member_number]
        @prefix = attrs[:prefix]
        @first_name = attrs[:first_name]
        @middle_name = attrs[:middle_name]
        @last_name = attrs[:last_name]
        @suffix = attrs[:suffix]
        @honorific = attrs[:honorific]
        @rank = attrs[:rank]
        @birthdate = attrs[:birthdate]
        @years_in_good_standing = attrs[:years_in_good_standing]
        @status = attrs.fetch(:status, 'Active')
        @is_active = attrs.fetch(:is_active, true)
        @good_standing = attrs.fetch(:good_standing, true)
        @email = attrs[:email]
        @phone = attrs[:phone]
        @cell = attrs[:cell]
        @address = attrs[:address] || {}
        @affiliations = attrs[:affiliations] || []
        @life_cycle_events = attrs[:life_cycle_events] || []
      end

      def full_name
        [
          @prefix,
          @first_name,
          @middle_name,
          @last_name,
          @suffix
        ].compact.reject(&:empty?).join(' ')
      end

      def party_short_name
        "#{@first_name} #{@last_name[0]}"
      end

      # Member type from first active affiliation
      def member_type
        active_affiliation = @affiliations.find { |a| a[:is_active] }
        active_affiliation&.dig(:member_type) || 'Member'
      end

      # Format for search response
      def to_search_result
        {
          member_number: @member_number,
          first_name: @first_name,
          last_name: @last_name,
          birthdate: @birthdate,
          contact: {
            email: @email,
            phone: @phone,
            address: @address
          },
          group_status: {
            status: @status,
            is_active: @is_active,
            good_standing: @good_standing
          },
          affiliations: @affiliations.map do |aff|
            {
              local_name: aff[:local_name],
              local_status: aff[:local_status] || aff[:status],
              is_active: aff[:is_active],
              member_type: aff[:member_type]
            }
          end
        }
      end

      # Full member profile for COGS
      def to_member_profile
        {
          member_number: @member_number,
          prefix: @prefix,
          first_name: @first_name,
          middle_name: @middle_name,
          last_name: @last_name,
          suffix: @suffix,
          honorific: @honorific,
          rank: @rank,
          birthdate: @birthdate,
          years_in_good_standing: @years_in_good_standing,
          group_status: {
            status: @status,
            is_active: @is_active
          },
          contact: {
            email: @email,
            phone: @phone,
            cell: @cell,
            address: @address
          },
          affiliations: @affiliations,
          life_cycle_events: @life_cycle_events
        }
      end

      # Quick status check response
      def to_status_check
        {
          member_number: @member_number,
          member_type: member_type,
          party_short_name: party_short_name,
          group_status: {
            status: @status,
            is_active: @is_active,
            good_standing: @good_standing
          }
        }
      end

      # Create from config YAML
      def self.from_config(config)
        new(
          member_number: config['member_number'],
          prefix: config['prefix'],
          first_name: config['first_name'],
          middle_name: config['middle_name'],
          last_name: config['last_name'],
          suffix: config['suffix'],
          honorific: config['honorific'],
          rank: config['rank'],
          birthdate: config['birthdate'],
          years_in_good_standing: config['years_in_good_standing'],
          status: config.fetch('status', 'Active'),
          is_active: config.fetch('is_active', true),
          good_standing: config.fetch('good_standing', true),
          email: config['email'],
          phone: config['phone'],
          cell: config['cell'],
          address: symbolize_keys(config['address'] || {}),
          affiliations: (config['affiliations'] || []).map { |a| symbolize_keys(a) },
          life_cycle_events: (config['life_cycle_events'] || []).map { |e| symbolize_keys(e) }
        )
      end

      def self.symbolize_keys(hash)
        hash.transform_keys(&:to_sym)
      end
    end
  end
end
