# frozen_string_literal: true

module MemberInterchangeProtocol
  module Models
    # Represents an endorsement in the web-of-trust
    class Endorsement
      attr_reader :id, :endorser_mip_identifier, :endorsed_mip_identifier,
                  :endorsed_public_key_fingerprint, :endorsement_document,
                  :endorsement_signature, :issued_at, :expires_at

      def initialize(attrs = {})
        @id = attrs[:id] || SecureRandom.uuid
        @endorser_mip_identifier = attrs[:endorser_mip_identifier]
        @endorsed_mip_identifier = attrs[:endorsed_mip_identifier]
        @endorsed_public_key_fingerprint = attrs[:endorsed_public_key_fingerprint]
        @endorsement_document = attrs[:endorsement_document]
        @endorsement_signature = attrs[:endorsement_signature]
        @issued_at = attrs[:issued_at]
        @expires_at = attrs[:expires_at]
      end

      def expired?
        Time.iso8601(@expires_at) < Time.now
      rescue ArgumentError
        true
      end

      def valid_for?(public_key_fingerprint)
        !expired? && @endorsed_public_key_fingerprint == public_key_fingerprint
      end

      # Verify the endorsement signature using the endorser's public key
      def verify_signature(endorser_public_key)
        return false if expired?

        MemberInterchangeProtocol::Crypto.verify(
          endorser_public_key,
          @endorsement_signature,
          @endorsement_document
        )
      end

      def to_payload
        {
          endorser_mip_identifier: @endorser_mip_identifier,
          endorsed_mip_identifier: @endorsed_mip_identifier,
          endorsed_public_key_fingerprint: @endorsed_public_key_fingerprint,
          endorsement_document: @endorsement_document,
          endorsement_signature: @endorsement_signature,
          issued_at: @issued_at,
          expires_at: @expires_at
        }
      end

      # Create endorsement document and sign it
      def self.create(endorser_identity, endorsed_mip_identifier, endorsed_public_key)
        fingerprint = MemberInterchangeProtocol::Crypto.fingerprint(endorsed_public_key)
        issued_at = Time.now.iso8601
        expires_at = (Time.now + (365 * 24 * 60 * 60)).iso8601 # 1 year

        document = {
          type: 'MIP_ENDORSEMENT_V1',
          endorser_mip_identifier: endorser_identity.mip_identifier,
          endorsed_mip_identifier: endorsed_mip_identifier,
          endorsed_public_key_fingerprint: fingerprint,
          issued_at: issued_at,
          expires_at: expires_at
        }.to_json

        signature = MemberInterchangeProtocol::Crypto.sign(endorser_identity.private_key, document)

        new(
          endorser_mip_identifier: endorser_identity.mip_identifier,
          endorsed_mip_identifier: endorsed_mip_identifier,
          endorsed_public_key_fingerprint: fingerprint,
          endorsement_document: document,
          endorsement_signature: signature,
          issued_at: issued_at,
          expires_at: expires_at
        )
      end

      # Create from received payload
      def self.from_payload(payload)
        new(
          endorser_mip_identifier: payload['endorser_mip_identifier'],
          endorsed_mip_identifier: payload['endorsed_mip_identifier'],
          endorsed_public_key_fingerprint: payload['endorsed_public_key_fingerprint'],
          endorsement_document: payload['endorsement_document'],
          endorsement_signature: payload['endorsement_signature'],
          issued_at: payload['issued_at'],
          expires_at: payload['expires_at']
        )
      end
    end
  end
end
