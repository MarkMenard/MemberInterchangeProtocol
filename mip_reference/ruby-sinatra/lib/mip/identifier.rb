# frozen_string_literal: true

module MIP
  module Identifier
    # Generate a MIP identifier using MD5(UUID + organization_name)
    # Per MIP spec: "combining a 128 bit randomly generated number concatenated
    # with a salt based using something unique to the organization"
    def self.generate(organization_name)
      uuid = SecureRandom.uuid
      Digest::MD5.hexdigest("#{uuid}#{organization_name}")
    end
  end
end
