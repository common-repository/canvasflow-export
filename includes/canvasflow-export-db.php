<?php
    if ( !defined('ABSPATH') ){
        define('ABSPATH', dirname(__FILE__) . '/');
    }

    
    class Canvasflow_Export_DB {
		private $wpdb;
		private $wp_users_table_name;
		private $wp_posts_table_name;

		function __construct() {
			$this->wpdb = $GLOBALS['wpdb'];
			$this->cf_rest_credentials_table_name = $this->wpdb->prefix."canvasflow_rest_credentials";

			$this->wp_users_table_name = $this->wpdb->users;
            $this->wp_posts_table_name = $this->wpdb->posts;
        }

		public function get_posts() {			
			$custom_posts_types_query = "";

            $query = "SELECT post.id as id , post.post_title as title, post.post_content as content,  
            users.display_name as display_name, users.ID as user_id, DATE_FORMAT(post.post_date, '%Y-%m-%dT%TZ') as post_modified_date, 
			post.post_type as type, post.post_status as status, post.post_excerpt as excerpt
            FROM {$this->wp_posts_table_name} as post 
            LEFT JOIN {$this->wp_users_table_name} as users ON(post.post_author=users.ID) 
            WHERE post.post_parent = 0 AND (post.post_type = \"post\" OR post.post_type = \"page\" {$custom_posts_types_query})
            AND post.post_status != \"auto-draft\" AND post.post_status != \"trash\"";

            $posts = array();

            foreach ( $this->wpdb->get_results($query) as $post ){
                array_push($posts, $post);
            }

            return $posts;
		}
		
		public function get_post($id) {
			$custom_posts_types_query = "";

            $query = "SELECT post.id as id , post.post_title as title, post.post_content as content,  
            users.display_name as display_name, users.ID as user_id, DATE_FORMAT(post.post_modified, '%Y-%m-%dT%TZ') as post_modified_date, 
			post.post_type as type, post.post_status as status, post.post_excerpt as excerpt
            FROM {$this->wp_posts_table_name} as post 
            LEFT JOIN {$this->wp_users_table_name} as users ON(post.post_author=users.ID) 
            WHERE post.post_parent = 0 AND (post.post_type = \"post\" OR post.post_type = \"page\" {$custom_posts_types_query})
			AND post.post_status != \"auto-draft\" AND post.post_status != \"trash\"
			AND post.id = {$id}";
			
			$response = null;

            foreach ( $this->wpdb->get_results($query) as $post ){
				$response = $post;
            }

            return $response;
		}

		public function create_canvasflow_rest_credentials_if_not_exists() {
			if(!$this->exist_canvasflow_rest_credentials()) {
				if($this->is_valid_wp_users_engine()) {
					$this->create_canvasflow_rest_credentials();
				}
			}
		}

		public function create_canvasflow_rest_credentials() {
			$query = "CREATE TABLE IF NOT EXISTS {$this->cf_rest_credentials_table_name} (ID BIGINT(20) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL UNIQUE, api_key CHAR(30) NOT NULL UNIQUE,
			CONSTRAINT {$this->cf_rest_credentials_table_name}_{$this->wp_users_table_name}_ID_fk FOREIGN KEY (user_id) 
			REFERENCES {$this->wp_users_table_name} (ID) ON DELETE CASCADE ON UPDATE CASCADE);";
			$this->wpdb->query($query);
		}

		public function exist_canvasflow_rest_credentials() {
			$query = "SHOW TABLE STATUS WHERE Name = '{$this->cf_rest_credentials_table_name}';";
            $result = $this->wpdb->get_results($query);
            if(sizeof($result) > 0) {
                return TRUE;
            }

            return FALSE;
		}

		public function delete_canvasflow_rest_credentials() {
			if($this->exist_canvasflow_rest_credentials()) {
				$query = "DROP TABLE {$this->cf_rest_credentials_table_name}";
				$this->wpdb->query($query);
			}
		}

		public function exist_api_key($api_key) {
			$query = "SELECT * FROM {$this->cf_rest_credentials_table_name} WHERE api_key = %s";
			$result = $this->wpdb->get_results($this->wpdb->prepare($query, $api_key));
			if(sizeof($result) > 0) {
                return TRUE;
            }

            return FALSE;
		}

		public function get_api_from_user($user_id) {
			$query = "SELECT api_key FROM {$this->cf_rest_credentials_table_name} WHERE user_id = %d";
			
			$result = $this->wpdb->get_results($this->wpdb->prepare($query, $user_id));
			if(sizeof($result) > 0) {
				return $result[0]->api_key;
            }

            return '';
		}

		public function get_user_from_api_key($api_key) {
			$query = "SELECT user_id FROM {$this->cf_rest_credentials_table_name} WHERE api_key = %s";
			
			$result = $this->wpdb->get_results($this->wpdb->prepare($query, $api_key));
			if(sizeof($result) > 0) {
				return (int) $result[0]->user_id;
            }

            return 0;
		}

		public function user_has_api_key($user_id) {
			$api_key = $this->get_api_from_user($user_id);
			if(strlen($api_key) > 0) {
				return TRUE;
			}

			return FALSE;
		}

		public function save_api_key($user_id, $api_key) {
			if($this->user_has_api_key($user_id)) {
				$this->update_api_key($user_id, $api_key);
			} else {	
				$this->insert_api_key($user_id, $api_key);
			}
		}

		public function insert_api_key($user_id, $api_key) {
			$query = "INSERT INTO {$this->cf_rest_credentials_table_name} (user_id, api_key) VALUES (%d, %s)";
			$this->wpdb->query($this->wpdb->prepare($query, $user_id, $api_key));
		}

		public function update_api_key($user_id, $api_key) {
			$query = "UPDATE {$this->cf_rest_credentials_table_name} SET api_key = %s WHERE user_id = %d";
			$this->wpdb->query($this->wpdb->prepare($query, $api_key, $user_id));
		}

		public function is_valid_wp_users_engine() {
            if($this->get_wp_users_engine() == 'InnoDB') {
                return TRUE;
            }
            return FALSE;
		}

		public function get_wp_users_table_name() {
            return $this->wp_users_table_name;
		}

		public function get_cf_rest_credentials_table_name() {
			return $this->cf_rest_credentials_table_name;
		}
		
		private function get_wp_users_engine() {
            $query = "SHOW TABLE STATUS WHERE Name = '{$this->wp_users_table_name}';";
            $result = $this->wpdb->get_results($query);
            if(sizeof($result) > 0) {
                return $result[0]->Engine;
            } else {
                return '';
            }
        }
	}
?>