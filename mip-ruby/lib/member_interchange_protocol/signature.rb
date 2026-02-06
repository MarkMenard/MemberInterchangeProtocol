# frozen_string_literal: true

module MemberInterchangeProtocol
  module Signature
    # Create a MIP request signature
    # Per spec: signature signs "timestamp + path + json_payload"
    def self.sign_request(private_key_pem, timestamp, path, json_body = nil)
      data = build_signature_data(timestamp, path, json_body)
      Crypto.sign(private_key_pem, data)
    end

    # Verify a MIP request signature
    def self.verify_request(public_key_pem, signature, timestamp, path, json_body = nil)
      data = build_signature_data(timestamp, path, json_body)
      Crypto.verify(public_key_pem, signature, data)
    end

    # Check if timestamp is within acceptable window (+/-5 minutes)
    def self.timestamp_valid?(timestamp, window_seconds = 300)
      request_time = Time.iso8601(timestamp)
      now = Time.now
      (now - request_time).abs <= window_seconds
    rescue ArgumentError
      false
    end

    class << self
      private

      def build_signature_data(timestamp, path, json_body)
        data = "#{timestamp}#{path}"
        data += json_body.to_s if json_body && !json_body.empty?
        data
      end
    end
  end
end
