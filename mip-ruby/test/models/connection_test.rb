# frozen_string_literal: true

require 'test_helper'

class ConnectionTest < Minitest::Test
  def test_new_connection_defaults
    conn = MIP::Models::Connection.new(
      mip_identifier: 'abc123',
      organization_name: 'Test Org'
    )

    assert_equal 'PENDING', conn.status
    assert_equal 100, conn.daily_rate_limit
    assert conn.share_my_organization
  end

  def test_status_predicates
    conn = MIP::Models::Connection.new(mip_identifier: 'abc123')
    assert conn.pending?
    refute conn.active?

    conn.status = 'ACTIVE'
    assert conn.active?
    refute conn.pending?

    conn.status = 'DECLINED'
    assert conn.declined?

    conn.status = 'REVOKED'
    assert conn.revoked?
  end

  def test_direction_predicates
    inbound = MIP::Models::Connection.new(
      mip_identifier: 'abc',
      direction: 'inbound'
    )
    assert inbound.inbound?
    refute inbound.outbound?

    outbound = MIP::Models::Connection.new(
      mip_identifier: 'xyz',
      direction: 'outbound'
    )
    assert outbound.outbound?
    refute outbound.inbound?
  end

  def test_approve
    conn = MIP::Models::Connection.new(
      mip_identifier: 'abc123',
      status: 'PENDING'
    )

    conn.approve!(daily_rate_limit: 200)

    assert conn.active?
    assert_equal 200, conn.daily_rate_limit
  end

  def test_decline
    conn = MIP::Models::Connection.new(
      mip_identifier: 'abc123',
      status: 'PENDING'
    )

    conn.decline!(reason: 'Not authorized')

    assert conn.declined?
    assert_equal 'Not authorized', conn.decline_reason
  end

  def test_revoke
    conn = MIP::Models::Connection.new(
      mip_identifier: 'abc123',
      status: 'ACTIVE'
    )

    conn.revoke!(reason: 'Violation')

    assert conn.revoked?
    assert_equal 'Violation', conn.revoke_reason
  end

  def test_restore
    conn = MIP::Models::Connection.new(
      mip_identifier: 'abc123',
      status: 'REVOKED'
    )

    conn.restore!

    assert conn.active?
    assert_nil conn.revoke_reason
  end

  def test_from_request
    payload = {
      'mip_identifier' => 'abc123',
      'mip_url' => 'http://test.org/mip/node/abc123',
      'public_key' => '-----BEGIN PUBLIC KEY-----',
      'organization_legal_name' => 'Test Org',
      'contact_person' => 'John Smith',
      'contact_phone' => '+1-555-0001',
      'share_my_organization' => true
    }

    conn = MIP::Models::Connection.from_request(payload, direction: 'inbound')

    assert_equal 'abc123', conn.mip_identifier
    assert_equal 'Test Org', conn.organization_name
    assert conn.inbound?
    assert conn.pending?
  end
end
