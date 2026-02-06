# frozen_string_literal: true

module MemberInterchangeProtocol
  module Crypto
    # Generate a 2048-bit RSA key pair
    def self.generate_key_pair
      key = OpenSSL::PKey::RSA.new(2048)
      {
        private_key: key.to_pem,
        public_key: key.public_key.to_pem
      }
    end

    # Calculate MD5 fingerprint of a public key (colon-separated hex)
    def self.fingerprint(public_key_pem)
      # Parse the PEM to get the DER encoding
      key = OpenSSL::PKey::RSA.new(public_key_pem)
      der = key.public_key.to_der
      digest = Digest::MD5.hexdigest(der)
      # Format as colon-separated pairs
      digest.scan(/.{2}/).join(':')
    end

    # Sign data with a private key using SHA256
    def self.sign(private_key_pem, data)
      key = OpenSSL::PKey::RSA.new(private_key_pem)
      signature = key.sign(OpenSSL::Digest::SHA256.new, data)
      Base64.strict_encode64(signature)
    end

    # Verify a signature using a public key
    def self.verify(public_key_pem, signature_base64, data)
      key = OpenSSL::PKey::RSA.new(public_key_pem)
      signature = Base64.strict_decode64(signature_base64)
      key.verify(OpenSSL::Digest::SHA256.new, signature, data)
    rescue OpenSSL::PKey::RSAError, ArgumentError
      false
    end
  end
end
