<?php

namespace App\Mip;

use Symfony\Component\Yaml\Yaml;

/**
 * Factory to create and initialize Store instances based on port
 */
class StoreFactory
{
    private static array $instances = [];

    public static function getInstance(int $port): Store
    {
        if (!isset(self::$instances[$port])) {
            self::$instances[$port] = new Store($port);
        }

        return self::$instances[$port];
    }

    /**
     * Initialize a store with configuration if not already initialized
     */
    public static function initializeFromConfig(int $port): Store
    {
        $store = self::getInstance($port);

        // If already initialized, return existing
        if ($store->getNodeIdentity() !== null) {
            return $store;
        }

        // Load config and initialize
        $configFile = dirname(__DIR__, 2) . "/config/nodes/node{$port}.yaml";

        if (!file_exists($configFile)) {
            throw new \RuntimeException("Configuration file not found: {$configFile}");
        }

        $config = Yaml::parseFile($configFile);

        // Create node identity
        $identity = Model\NodeIdentity::fromConfig($config, $port);
        $store->setNodeIdentity($identity);

        // Load members
        foreach ($config['members'] ?? [] as $memberConfig) {
            $member = Model\Member::fromConfig($memberConfig);
            $store->addMember($member);
        }

        $store->logActivity("Node initialized: {$identity->organizationName}");

        return $store;
    }

    /**
     * Reset a store (for testing or re-initialization)
     */
    public static function reset(int $port): Store
    {
        $store = self::getInstance($port);
        $store->reset();

        return self::initializeFromConfig($port);
    }
}
