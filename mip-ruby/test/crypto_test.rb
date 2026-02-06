# frozen_string_literal: true

require 'test_helper'

class CryptoTest < Minitest::Test
  def test_generate_key_pair
    keys = MIP::Crypto.generate_key_pair

    assert keys[:private_key]
    assert keys[:public_key]
    assert keys[:private_key].include?('BEGIN RSA PRIVATE KEY')
    assert keys[:public_key].include?('BEGIN PUBLIC KEY')
  end

  def test_fingerprint
    keys = MIP::Crypto.generate_key_pair
    fingerprint = MIP::Crypto.fingerprint(keys[:public_key])

    assert fingerprint
    assert_match(/^[a-f0-9]{2}(:[a-f0-9]{2}){15}$/, fingerprint)
  end

  def test_sign_and_verify
    keys = MIP::Crypto.generate_key_pair
    data = 'test data to sign'

    signature = MIP::Crypto.sign(keys[:private_key], data)
    assert signature

    valid = MIP::Crypto.verify(keys[:public_key], signature, data)
    assert valid
  end

  def test_verify_fails_with_wrong_data
    keys = MIP::Crypto.generate_key_pair
    data = 'test data to sign'

    signature = MIP::Crypto.sign(keys[:private_key], data)

    valid = MIP::Crypto.verify(keys[:public_key], signature, 'wrong data')
    refute valid
  end

  def test_verify_fails_with_wrong_key
    keys1 = MIP::Crypto.generate_key_pair
    keys2 = MIP::Crypto.generate_key_pair
    data = 'test data to sign'

    signature = MIP::Crypto.sign(keys1[:private_key], data)

    valid = MIP::Crypto.verify(keys2[:public_key], signature, data)
    refute valid
  end

  def test_verify_handles_invalid_signature
    keys = MIP::Crypto.generate_key_pair

    valid = MIP::Crypto.verify(keys[:public_key], 'invalid', 'data')
    refute valid
  end
end
