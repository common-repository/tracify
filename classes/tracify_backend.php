<?php
 class TracifyBackend { private $tracify_options; public function __construct() { $this->tracify_options = get_option('tracify_options'); } public function install_backend_hooks() { $token = $this->tracify_options['tracify-token']; if ($token) { if ($this->tracify_options['orders-when-processing'] == "1") { add_action('woocommerce_order_status_processing', array($this, 'report_order_server_side'), 1); } if ($this->tracify_options['orders-when-completed'] == "1") { add_action('woocommerce_order_status_completed', array($this, 'report_order_server_side'), 1); } if ($this->tracify_options['orders-when-on-hold'] == "1") { add_action('woocommerce_order_status_on-hold', array($this, 'report_order_server_side'), 1); } } } public function install_frontend_hooks() { if (is_array($this->tracify_options) && isset($this->tracify_options['csid']) && strlen($this->tracify_options['csid']) > 0) { add_action('wp_head', array($this, 'enqueue_assets'), 1); $token = $this->tracify_options['tracify-token']; if (!$token) { add_action('woocommerce_thankyou', array($this, 'report_order_client_side'), 1); } } } public function enqueue_assets() { $tracking_host = "tracify.ai"; $dev_subdomain = $this->tracify_options['development-mode'] == "1" ? "dev" : ""; $script_host = "https://".$dev_subdomain."scripting.".$tracking_host; $script_name = "tracify"; $beta_mode = $this->tracify_options['beta-mode'] == "1" ? "e" : ""; $fingerprinting = $this->tracify_options['fingerprinting'] == "1" ? "v2" : ""; $csid = $this->tracify_options['csid']; $script_tag = $script_host."/".$script_name."w".$fingerprinting.$beta_mode.".js?csid=" . esc_attr($csid); echo "
    <!-- Tracify integration ".TRACIFY_PLUGIN_VER." | (c) tracify.ai | all rights reserved-->
    <link rel=\"preconnect\" href=\"".$script_host."\" />
    <link rel=\"preload\" as=\"script\" href=\"".$script_tag."\">
    <script async src=\"".$script_tag."\"></script>"; } private function calc_group($data) { $digest = hash('sha256', $data, false); $group = substr($digest, 0, strlen($digest) - 5); return hash('sha256', $group, false); } private function anonymize_tracify_event($csid, $interaction_type, $loc, $event_infos, $raw_ip, $raw_user_agent, $email = NULL) { if (!array_key_exists("csorigin", $event_infos)) { $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; $event_infos["csorigin"] = $domain; } $event = array('interaction_type' => $interaction_type, 'loc' => $loc, 'site_id' => $csid, 'event_infos' => $event_infos); $user_agent_anonymous = $this->calc_group($raw_user_agent); $ip_anonymous = $this->calc_group($raw_ip); $raw_sid = $raw_ip . "|" . $raw_user_agent; $sid_anonymous = $this->calc_group($raw_sid); $entity_data = array($user_agent_anonymous => 4, $ip_anonymous => 3, $sid_anonymous => 2); if (!is_null($email)) { $email_anonymous = $this->calc_group($email); $entity_data[$email_anonymous] = 1; } $event['entity_data'] = $entity_data; return $event; } private function transmit_tracify_event($token, $payload) { $dev_subdomain = $this->tracify_options['development-mode'] == "1" ? "dev" : ""; $url = "https://".$dev_subdomain."backend.tracify.ai/upload"; $header = "Content-Type: application/json\r\n" . "Accept: application/json\r\n" . "tracify-token: " . $token . "\r\n"; $args = array( 'method' => 'POST', 'timeout' => 5, 'headers' => $header, 'body' => json_encode($payload), ); return wp_remote_post($url, $args); } public function report_order_server_side($order_id) { $token = $this->tracify_options['tracify-token']; $order = wc_get_order($order_id); $csid = $this->tracify_options['csid']; $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; $raw_ip = $order->get_customer_ip_address(); $raw_user_agent = $order->get_customer_user_agent(); $order_id = $order->get_id(); $amount = $order->get_total(); $currency = $order->get_currency(); $amount = number_format($amount, 2, ',', ''); $email = $order->get_billing_email(); $event_infos = array( 'oid' => $order_id, 'amount' => $amount, 'cc' => $currency ); $counter = 0; foreach ($order->get_items() as $item) { $product = $item->get_product(); if ($amount <= 0) { continue; } $counter += 1; $event_infos['ITEM' . $counter] = $product->get_sku(); $event_infos['AMT' . $counter] = number_format($item->get_total(), 2, ',', ''); $event_infos['QTY' . $counter] = $item->get_quantity(); } $encoded_events = array($this->anonymize_tracify_event($csid, 'purchase', $domain, $event_infos, $raw_ip, $raw_user_agent, $email)); $payload = array('events' => $encoded_events); $this->transmit_tracify_event($token, $payload); } public function report_order_client_side($order_id) { $csid = $this->tracify_options['csid']; $order = wc_get_order($order_id); ?>

    <script type="text/javascript">
      function tracifyDocReady(callback) {
        // in case the document is already rendered
        if (document.readyState !== "loading") callback()
        // modern browsers
        else if (document.addEventListener)
          document.addEventListener("DOMContentLoaded", callback)
        // IE <= 8
        else
          document.attachEvent("onreadystatechange", function() {
            if (document.readyState === "complete") callback()
          })
      }

      function tracifyReportEvent(reportUrl, reqMethod, reqHeaders, reqBody) {
        const xmlHttp = new XMLHttpRequest()
        xmlHttp.open(reqMethod, reportUrl, true)
        for (const [key, value] of Object.entries(reqHeaders)) {
          xmlHttp.setRequestHeader(key, value)
        }
        xmlHttp.send(reqBody)
      }

      tracifyDocReady(() => {
        const csid = "<?php echo esc_attr($csid); ?>";

        const params = {
          csid,
          eid: "purchase",
          oid: '<?php echo esc_attr($order->get_id()); ?>',
          // Anonymize user-specific data
          cm: '<?php echo esc_attr($this->calc_group($order->get_billing_email())); ?>',
          cc: '<?php echo esc_attr($order->get_currency()); ?>',
          amount: '<?php echo esc_attr(number_format($order->get_total(), 2, ',', '')); ?>',
        };

        const orderedItems = []

        <?php
 foreach ($order->get_items() as $item) : $product = $item->get_product(); ?>
          orderedItems.push({
            sku: '<?php echo esc_attr($product->get_sku()); ?>',
            amount: '<?php echo esc_attr(number_format($item->get_total(), 2, ',', '')); ?>',
            quantity: '<?php echo esc_attr($item->get_quantity()); ?>',
          });
        <?php endforeach; ?>

        orderedItems.forEach((item, index) => {
          const idx = index + 1;
          params["ITEM" + idx] = item.sku;
          params["AMT" + idx] = item.amount;
          params["QTY" + idx] = item.quantity;
        })

        const searchParams = new URLSearchParams(params);

        const turl =
          "https://<?php echo $this->tracify_options['development-mode'] == "1" ? "dev" : ""; ?>event.tracify.ai/tracify.js?" + searchParams.toString();
        const req_headers = {
          csorigin: window.location.origin
        };
        tracifyReportEvent(turl, "GET", req_headers, null);
      });
    </script>
<?php
 } } 