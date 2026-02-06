# frozen_string_literal: true

require 'test_helper'

class NodeIdentityTest < Minitest::Test
  def test_generate_creates_identity
    identity = MIP::Models::NodeIdentity.generate(
      organization_name: 'Test Organization',
      contact_person: 'John Smith',
      contact_phone: '+1-555-0001'
    )

    assert identity.mip_identifier
    assert identity.private_key
    assert identity.public_key
    assert_equal 'Test Organization', identity.organization_name
    assert_equal 'John Smith', identity.contact_person
    assert_equal '+1-555-0001', identity.contact_phone
  end

  def test_generate_sets_defaults
    identity = MIP::Models::NodeIdentity.generate(
      organization_name: 'Test Organization'
    )

    assert identity.share_my_organization
    assert_equal 1, identity.trust_threshold
  end

  def test_public_key_fingerprint
    identity = MIP::Models::NodeIdentity.generate(
      organization_name: 'Test Organization'
    )

    fingerprint = identity.public_key_fingerprint
    assert fingerprint
    assert_match(/^[a-f0-9]{2}(:[a-f0-9]{2}){15}$/, fingerprint)
  end

  def test_to_node_profile
    identity = MIP::Models::NodeIdentity.generate(
      organization_name: 'Test Organization',
      contact_person: 'John Smith',
      contact_phone: '+1-555-0001',
      mip_url: 'http://test.org/mip/node/abc',
      share_my_organization: true
    )

    profile = identity.to_node_profile

    assert_equal identity.mip_identifier, profile[:mip_identifier]
    assert_equal 'http://test.org/mip/node/abc', profile[:mip_url]
    assert_equal 'Test Organization', profile[:organization_legal_name]
    assert_equal 'John Smith', profile[:contact_person]
    assert_equal '+1-555-0001', profile[:contact_phone]
    assert_equal identity.public_key, profile[:public_key]
    assert profile[:share_my_organization]
  end

  def test_from_config
    config = {
      'organization_name' => 'Config Org',
      'contact_person' => 'Jane Doe',
      'contact_phone' => '+1-555-0002',
      'share_my_organization' => false,
      'trust_threshold' => 2
    }

    identity = MIP::Models::NodeIdentity.from_config(config, 4000)

    assert_equal 'Config Org', identity.organization_name
    assert_equal 'Jane Doe', identity.contact_person
    assert_equal '+1-555-0002', identity.contact_phone
    refute identity.share_my_organization
    assert_equal 2, identity.trust_threshold
    assert_equal 4000, identity.port
  end
end
