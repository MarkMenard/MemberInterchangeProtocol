# frozen_string_literal: true

require 'test_helper'

class MemberInterchangeProtocolTest < Minitest::Test
  def test_version
    assert_equal '0.8.0', MemberInterchangeProtocol::VERSION
  end

  def test_mip_alias
    assert_equal MemberInterchangeProtocol, MIP
  end

  def test_error_classes_exist
    assert MemberInterchangeProtocol::Error
    assert MemberInterchangeProtocol::SignatureError
    assert MemberInterchangeProtocol::ConnectionError
    assert MemberInterchangeProtocol::ValidationError
  end
end
