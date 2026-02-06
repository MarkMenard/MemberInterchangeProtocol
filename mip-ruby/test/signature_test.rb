# frozen_string_literal: true

require 'test_helper'

class SignatureTest < Minitest::Test
  def setup
    @keys = MIP::Crypto.generate_key_pair
  end

  def test_sign_and_verify_request
    timestamp = Time.now.iso8601
    path = '/mip/node/abc123/mip_connections'
    json_body = '{"test":"data"}'

    signature = MIP::Signature.sign_request(@keys[:private_key], timestamp, path, json_body)
    assert signature

    valid = MIP::Signature.verify_request(@keys[:public_key], signature, timestamp, path, json_body)
    assert valid
  end

  def test_sign_and_verify_without_body
    timestamp = Time.now.iso8601
    path = '/mip/node/abc123/connected_organizations'

    signature = MIP::Signature.sign_request(@keys[:private_key], timestamp, path)
    assert signature

    valid = MIP::Signature.verify_request(@keys[:public_key], signature, timestamp, path)
    assert valid
  end

  def test_verify_fails_with_wrong_timestamp
    timestamp = Time.now.iso8601
    path = '/mip/node/abc123/mip_connections'

    signature = MIP::Signature.sign_request(@keys[:private_key], timestamp, path)

    wrong_timestamp = (Time.now - 60).iso8601
    valid = MIP::Signature.verify_request(@keys[:public_key], signature, wrong_timestamp, path)
    refute valid
  end

  def test_verify_fails_with_wrong_path
    timestamp = Time.now.iso8601
    path = '/mip/node/abc123/mip_connections'

    signature = MIP::Signature.sign_request(@keys[:private_key], timestamp, path)

    valid = MIP::Signature.verify_request(@keys[:public_key], signature, timestamp, '/wrong/path')
    refute valid
  end

  def test_timestamp_valid_within_window
    timestamp = Time.now.iso8601
    assert MIP::Signature.timestamp_valid?(timestamp)
  end

  def test_timestamp_valid_at_edge_of_window
    timestamp = (Time.now - 299).iso8601
    assert MIP::Signature.timestamp_valid?(timestamp)
  end

  def test_timestamp_invalid_outside_window
    timestamp = (Time.now - 301).iso8601
    refute MIP::Signature.timestamp_valid?(timestamp)
  end

  def test_timestamp_invalid_for_bad_format
    refute MIP::Signature.timestamp_valid?('not a timestamp')
  end
end
