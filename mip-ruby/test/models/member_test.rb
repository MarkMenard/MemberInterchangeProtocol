# frozen_string_literal: true

require 'test_helper'

class MemberTest < Minitest::Test
  def test_new_member
    member = MIP::Models::Member.new(
      member_number: 'M-12345',
      first_name: 'John',
      last_name: 'Smith',
      birthdate: '1980-01-15'
    )

    assert_equal 'M-12345', member.member_number
    assert_equal 'John', member.first_name
    assert_equal 'Smith', member.last_name
    assert_equal '1980-01-15', member.birthdate
  end

  def test_defaults
    member = MIP::Models::Member.new(member_number: 'M-12345')

    assert_equal 'Active', member.status
    assert member.is_active
    assert member.good_standing
    assert_equal({}, member.address)
    assert_equal([], member.affiliations)
    assert_equal([], member.life_cycle_events)
  end

  def test_full_name
    member = MIP::Models::Member.new(
      prefix: 'Dr.',
      first_name: 'John',
      middle_name: 'Q',
      last_name: 'Smith',
      suffix: 'Jr.'
    )

    assert_equal 'Dr. John Q Smith Jr.', member.full_name
  end

  def test_full_name_with_missing_parts
    member = MIP::Models::Member.new(
      first_name: 'John',
      last_name: 'Smith'
    )

    assert_equal 'John Smith', member.full_name
  end

  def test_party_short_name
    member = MIP::Models::Member.new(
      first_name: 'John',
      last_name: 'Smith'
    )

    assert_equal 'John S', member.party_short_name
  end

  def test_member_type_from_affiliation
    member = MIP::Models::Member.new(
      first_name: 'John',
      affiliations: [
        { local_name: 'Lodge 1', member_type: 'Master Mason', is_active: true }
      ]
    )

    assert_equal 'Master Mason', member.member_type
  end

  def test_member_type_default
    member = MIP::Models::Member.new(first_name: 'John')

    assert_equal 'Member', member.member_type
  end

  def test_to_search_result
    member = MIP::Models::Member.new(
      member_number: 'M-12345',
      first_name: 'John',
      last_name: 'Smith',
      birthdate: '1980-01-15',
      email: 'john@example.com',
      status: 'Active',
      is_active: true,
      good_standing: true,
      affiliations: [
        { local_name: 'Lodge 1', member_type: 'Master', is_active: true }
      ]
    )

    result = member.to_search_result

    assert_equal 'M-12345', result[:member_number]
    assert_equal 'John', result[:first_name]
    assert_equal 'Smith', result[:last_name]
    assert_equal 'john@example.com', result[:contact][:email]
    assert result[:group_status][:good_standing]
  end

  def test_to_member_profile
    member = MIP::Models::Member.new(
      member_number: 'M-12345',
      first_name: 'John',
      last_name: 'Smith'
    )

    profile = member.to_member_profile

    assert_equal 'M-12345', profile[:member_number]
    assert_equal 'John', profile[:first_name]
    assert profile.key?(:group_status)
    assert profile.key?(:contact)
  end

  def test_to_status_check
    member = MIP::Models::Member.new(
      member_number: 'M-12345',
      first_name: 'John',
      last_name: 'Smith',
      status: 'Active',
      is_active: true,
      good_standing: true
    )

    status = member.to_status_check

    assert_equal 'M-12345', status[:member_number]
    assert_equal 'John S', status[:party_short_name]
    assert status[:group_status][:good_standing]
  end

  def test_from_config
    config = {
      'member_number' => 'M-12345',
      'first_name' => 'John',
      'last_name' => 'Smith',
      'birthdate' => '1980-01-15',
      'status' => 'Active',
      'good_standing' => true,
      'address' => {
        'city' => 'Boston',
        'state' => 'MA'
      }
    }

    member = MIP::Models::Member.from_config(config)

    assert_equal 'M-12345', member.member_number
    assert_equal 'John', member.first_name
    assert_equal :city, member.address.keys.first
  end
end
