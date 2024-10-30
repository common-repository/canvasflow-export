<?php 
	class Canvasflow_Export_Settings {
		public $canvasflow_db;

		function __construct() {
			$this->api_key = '';
			$this->canvasflow_db = new Canvasflow_Export_DB();
		}

		public function render_view() {
			$user_id = get_current_user_id();
			if(!$this->is_user_have_api_key($user_id)) {
				$this->create_new_api_key($user_id);
			}
			$api_key = $this->get_user_api_key($user_id);
			
			
			include( plugin_dir_path( __FILE__ ) . 'views/canvasflow-export-settings-view.php');
		}

		private function is_user_have_api_key($user_id) {
			return $this->canvasflow_db->user_has_api_key($user_id);
		}

		public function create_new_api_key($user_id) {
			$api_key = $this->generate_new_api_key();
			$this->canvasflow_db->save_api_key($user_id, $api_key);
		}

		private function get_user_api_key($user_id) {
			return $this->canvasflow_db->get_api_from_user($user_id);
		}

		private function generate_new_api_key() {
			$api_key = '';
			do {
				$random_string = $this->generate_random_string();
				if(!$this->canvasflow_db->exist_api_key($random_string)) {
					$api_key = $random_string;
				}
			} while(strlen($api_key) === 0);
			return $api_key;
		}

		private function generate_random_string() {
			$length = 30;
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';
			for ($i = 0; $i < $length; $i++) {
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}
			return $randomString;
		}
	}

	$canvasflow_Export_Settings = new Canvasflow_Export_Settings();
	
	if(!$canvasflow_Export_Settings->canvasflow_db->is_valid_wp_users_engine()) {
		echo "<br><div class=\"error-message-static\"><div>Error: Unable to activate the Canvasflow for WordPress plugin.</br> </br> The <span style=\"color: grey;\">{$Canvasflow_Export_Settings->canvasflow_db->get_wp_users_table_name()}</span> table engine must be configured as <span style=\"color: grey;\">InnoDB</span> </br></br> To fix this problem run: <code style=\"background-color: #f1f1f1;\">ALTER TABLE {$Canvasflow_Export_Settings->canvasflow_db->get_wp_users_table_name()} ENGINE=InnoDB</code> and re-activate the plugin</div></div>";
	} elseif(!$canvasflow_Export_Settings->canvasflow_db->exist_canvasflow_rest_credentials()) {
		echo "<br><div class=\"error-message-static\"><div>Error: Unable to activate the Canvasflow for WordPress plugin.<br><br> The table <span style=\"color: grey;\">{$Canvasflow_Export_Settings->canvasflow_db->get_cf_rest_credentials_table_name()}</span> do not exist please re-activate the plugin</div></div>";
	} else {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
			$user_id = get_current_user_id();
			$canvasflow_Export_Settings->create_new_api_key($user_id);
		}

		$canvasflow_Export_Settings->render_view();
	}
?>