<?php
require_once 'Logger.php';

class DomainValidator {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function verify_callback($url) {
        $allowed_domains = $this->db->getConfig('allowed_callback_domains', '');
        if (empty($allowed_domains)) {
            return true; // No domains configured, allow all
        }

        $parsed_url = parse_url($url);
        $callback_domain = $parsed_url['host'] ?? '';
        $allowed_domains_array = array_map('trim', explode(',', $allowed_domains));

        if (!in_array($callback_domain, $allowed_domains_array)) {
            Logger::warning('Callback domain validation failed', [
                'callback_url' => $url,
                'callback_domain' => $callback_domain,
                'allowed_domains' => $allowed_domains
            ]);
            return false;
        }

        return true;
    }
}