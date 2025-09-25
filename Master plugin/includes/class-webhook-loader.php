<?php
/**
 * Webhook Loader for AffiliateWP Cross Domain Full
 *
 * Connects Webhook Manager ↔ Handler.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Webhook_Loader {

    /** @var AFFCD_Webhook_Manager */
    private $manager;

    /** @var AFFCD_Webhook_Handler */
    private $handler;

    /**
     * Constructor
     */
    public function __construct() {
        // Instantiate Manager first
        $this->manager = new AFFCD_Webhook_Manager();

        // Pass Manager into Handler
        $this->handler = new AFFCD_Webhook_Handler($this->manager);

        // Hook the manager to dispatch via handler
        add_action('affcd_webhook_dispatch', [$this, 'dispatch'], 10, 3);
    }

    /**
     * Dispatch event via Handler
     *
     * @param string $event   Event name.
     * @param string $domain  Target domain.
     * @param array  $payload Event payload.
     * @return bool Success.
     */
    public function dispatch(string $event, string $domain, array $payload = []): bool {
        if (!$this->manager->is_event_enabled($event, $domain)) {
            return false;
        }
        return $this->handler->send_to_domain($domain, $event, $payload);
    }

    /**
     * Get the handler instance
     *
     * @return AFFCD_Webhook_Handler
     */
    public function get_handler(): AFFCD_Webhook_Handler {
        return $this->handler;
    }

    /**
     * Get the manager instance
     *
     * @return AFFCD_Webhook_Manager
     */
    public function get_manager(): AFFCD_Webhook_Manager {
        return $this->manager;
    }
}
