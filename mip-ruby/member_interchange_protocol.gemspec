# frozen_string_literal: true

require_relative 'lib/member_interchange_protocol/version'

Gem::Specification.new do |spec|
  spec.name          = 'member_interchange_protocol'
  spec.version       = MemberInterchangeProtocol::VERSION
  spec.authors       = ['Member Interchange Protocol Contributors']
  spec.email         = ['mip@example.com']

  spec.summary       = 'Member Interchange Protocol (MIP) Ruby implementation'
  spec.description   = 'A Ruby implementation of the Member Interchange Protocol (MIP) for secure ' \
                       'inter-organizational member verification. Includes cryptographic signing, ' \
                       'connection management, member search, and certificate of good standing support.'
  spec.homepage      = 'https://github.com/MemberInterchangeProtocol/mip-ruby'
  spec.license       = 'MIT'
  spec.required_ruby_version = '>= 3.0.0'

  spec.metadata['homepage_uri'] = spec.homepage
  spec.metadata['source_code_uri'] = spec.homepage
  spec.metadata['changelog_uri'] = "#{spec.homepage}/blob/main/CHANGELOG.md"

  spec.files = Dir.chdir(__dir__) do
    Dir['{lib}/**/*', 'LICENSE.txt', 'README.md', 'CHANGELOG.md']
  end
  spec.require_paths = ['lib']

  spec.add_dependency 'base64', '~> 0.2'
  spec.add_dependency 'faraday', '~> 2.0'

  spec.add_development_dependency 'bundler', '~> 2.0'
  spec.add_development_dependency 'minitest', '~> 5.0'
  spec.add_development_dependency 'rake', '~> 13.0'
  spec.add_development_dependency 'rubocop', '~> 1.0'
end
