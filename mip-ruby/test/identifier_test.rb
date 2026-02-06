# frozen_string_literal: true

require 'test_helper'

class IdentifierTest < Minitest::Test
  def test_generate_creates_32_char_hex
    id = MIP::Identifier.generate('Test Organization')

    assert_equal 32, id.length
    assert_match(/^[a-f0-9]{32}$/, id)
  end

  def test_generate_creates_unique_ids
    id1 = MIP::Identifier.generate('Test Organization')
    id2 = MIP::Identifier.generate('Test Organization')

    refute_equal id1, id2
  end

  def test_generate_with_different_orgs
    id1 = MIP::Identifier.generate('Org A')
    id2 = MIP::Identifier.generate('Org B')

    refute_equal id1, id2
  end
end
