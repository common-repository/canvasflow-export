<?php
	function canvasflow_export_get_all_headers() {
		$headers = array();
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}

	class Canvasflow_Export_Controller extends WP_REST_Controller {
		private $canvasflow_db;
	
		function __construct($user_id = null) {
			$this->canvasflow_db = new Canvasflow_Export_DB();
		}
	
		/**
		 * Register the routes for the objects of the controller.
		 */
		public function register_routes() {
			$version = '1';
			$namespace = 'canvasflow/v' . $version;
			$authenticate = 'authenticate';
			$posts = 'posts';
			$media = 'media';
			$categories = 'categories';
			register_rest_route( $namespace, '/' . $categories, array(
				array(
					'methods'         => WP_REST_Server::READABLE,
					'callback'        => array( $this, 'get_all_categories' ),
					'permission_callback' => array( $this, 'get_articles_permissions_check' )
				)
			) );
			register_rest_route( $namespace, '/' . $authenticate, array(
				array(
					'methods'         => WP_REST_Server::CREATABLE,
					'callback'        => array( $this, 'validate_api_key' ),
					'permission_callback' => array( $this, 'get_articles_permissions_check' )
				)
			) );
			register_rest_route( $namespace, '/' . $posts, array(
				array(
					'methods'         => WP_REST_Server::READABLE,
					'callback'        => array( $this, 'get_articles' ),
					'permission_callback' => array( $this, 'get_articles_permissions_check' )
				),
				array(
					'methods'         => WP_REST_Server::CREATABLE,
					'callback'        => array( $this, 'create_article' ),
					'permission_callback' => array( $this, 'create_article_permissions_check' ),
				),
			) );
			register_rest_route( $namespace, '/' . $posts . '/(?P<id>[\d]+)', array(
				array(
					'methods'         => WP_REST_Server::READABLE,
					'callback'        => array( $this, 'get_article' ),
					'permission_callback' => array( $this, 'get_article_permissions_check' )
				),
				array(
					'methods'         => WP_REST_Server::EDITABLE,
					'callback'        => array( $this, 'update_article' ),
					'permission_callback' => array( $this, 'update_article_permissions_check' )
				),
				array(
					'methods'  => WP_REST_Server::DELETABLE,
					'callback' => array( $this, 'delete_article' ),
					'permission_callback' => array( $this, 'delete_article_permissions_check' )
				),
			) );
			register_rest_route( $namespace, '/' . $media, array(
				array(
					'methods'         => WP_REST_Server::READABLE,
					'callback'        => array( $this, 'get_media' ),
					'permission_callback' => array( $this, 'get_articles_permissions_check' )
				),
				array(
					'methods'         => WP_REST_Server::CREATABLE,
					'callback'        => array( $this, 'create_media' ),
					'permission_callback' => array( $this, 'create_article_permissions_check' )
				),
			) );
		}

		/**
		 * Authenticate credentials
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function validate_api_key( $request ) {
			$parameters = $request->get_body_params();
			$api_key = '';
			if(isset($parameters['api_key'])) {
				$api_key = $parameters['api_key'];
			}
			if($this->canvasflow_db->exist_api_key($api_key)) {
				return new WP_REST_Response( new stdClass(), 200 );
			} else {
				return new WP_REST_Response(array('error' => 'Invalid api_key'), 403 );
			}		
		}

		/**
		 * Get a collection of categories
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function get_all_categories( $request ) {
			$categories = array();

			// Remove comment below if you wish to only display categories without parents
			foreach(get_categories(array( 'hide_empty' => 0/*, 'parent' => 0*/ )) as $category) {
				array_push($categories , $category);
			}
			return new WP_REST_Response( $categories , 200 );
		}
	
		/**
		 * Get a collection of articles
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function get_articles( $request ) {
			$posts = array();

			$category_map = array();
			foreach(get_categories(array( 'hide_empty' => 0/*, 'parent' => 0*/ )) as $category) {
				$category_map[(string)$category->term_id] = $category;
			}

			foreach($this->canvasflow_db->get_posts() as $item) {
				$metadata = array();

				$categories = array();
				foreach(wp_get_post_categories((int)$item->id) as $category_id) {
					array_push($categories, $category_map[(string)$category_id]);
				}	
				
				$post = array(
					'id' => (int)$item->id,
					'title' => array(
						'rendered' => $item->title
					),
					'excerpt' => $item->excerpt,
					'link' => get_the_permalink($item->id),
					'date' => $item->post_modified_date,
					'metadata' => $this->get_meta_data((int)$item->id),
					'status' => $item->status,
					'type' => $item->type,
					'categories' => $categories
				);

				$thumbnail = get_the_post_thumbnail_url($post['id']);
				if($thumbnail) {
					$post['thumbnail'] = $thumbnail;
				}

				array_push($posts, $post);
			}
			return new WP_REST_Response( $posts, 200 );
		}

		public function get_meta_data($post_id){
			$response = array();
			
            if ( $keys = get_post_custom_keys($post_id) ) {
                foreach ( (array) $keys as $key ) {   
					if (!is_protected_meta( $key, 'post' )) {
						$values = get_post_meta($post_id, $key, false);
						foreach($values as $value) {
							$response[$key] = $value;
						}
                    }     
                }
            }

            return $response;
        }
	
		/**
		 * Get one item from the collection
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function get_article( $request ) {
			//get parameters from request
			$params = $request->get_params();		
			$id = $params['id'];
	
			if(!is_numeric($id)) {
				return new WP_Error( 'invalid-id', __( 'Article id is invalid' ), array( 'status' => 409 ) );
			}
	
			$id = esc_sql($id);
	
			$response = $this->canvasflow_db->get_post($id);
	
			if($response == null) {
				return new WP_Error( 'not-found', __( 'Post not found'), array( 'status' => 404 ) );
			} else {
				$category_map = array();
				foreach(get_categories(array( 'hide_empty' => 0/*, 'parent' => 0*/ )) as $category) {
					$category_map[(string)$category->term_id] = $category;
				}

				$categories = array();
				foreach(wp_get_post_categories((int)$response->id) as $category_id) {
					array_push($categories, $category_map[(string)$category_id]);
				}	

				$post = array(
					'id' => (int)$response->id,
					'title' => array(
						'rendered' => $response->title
					),
					'excerpt' => $response->excerpt,
					'link' => get_the_permalink($response->id),
					'metadata' => $this->get_meta_data((int)$response->id),
					'date' => $response->post_modified_date,
					'status' => $response->status,
					'type' => $response->type,
					'categories' => $categories
				);

				$thumbnail = get_the_post_thumbnail_url($post['id']);
				if($thumbnail) {
					$post['thumbnail'] = $thumbnail;
				}

				return new WP_REST_Response( $post, 200 );
			}
			
		}
	
		/**
		 * Create one item from the collection
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Request
		 */
		public function create_article( $request ) {
			$type = 'post';
			$excerpt = '';
			$metadata;
			$status = 'publish';
	
			if (!isset($_POST['title'])) {
				return new WP_Error( 'invalid-title', __( 'Title is invalid' ), array( 'status' => 409 ) );
			}

			$title = $_POST['title'];

			if (isset($_POST['type'])) {
				if($_POST['type'] == 'post' || $_POST['type'] == 'page') {
					$type = $_POST['type'];
				}
			}
	
			if (!isset($_POST['content'])) {
				return new WP_Error( 'invalid-content', __( 'Content is invalid' ), array( 'status' => 409 ) );
			}

			$content = $_POST['content'];
			
			if (isset($_POST['metadata'])) {
				$metadata = $_POST['metadata'];
			}

			if (isset($_POST['excerpt'])) {
				$excerpt = $_POST['excerpt'];
			}

			$post = array (
				'post_type' => $type,
				'post_author' => $this->get_user_from_api_key($this->get_api_key_from_request()),
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => $status,
				'post_excerpt' => $excerpt
			);

			if (isset($_POST['status'])) {
				if($_POST['status'] == 'publish' || $_POST['status'] == 'future' || $_POST['status'] == 'draft' || $_POST['status'] == 'pending'
				|| $_POST['status'] == 'private' || $_POST['status'] == 'inherit') {
					$post['post_status'] = $_POST['status'];
				}
			}

			if(isset($_POST['categories'])) {
				$post['post_category'] = json_decode($_POST['categories'], true);
			}
			
			remove_filter('content_save_pre', 'wp_filter_post_kses');
			remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
			
			$result = wp_insert_post($post, true);
			
			add_filter('content_save_pre', 'wp_filter_post_kses');
			add_filter('content_filtered_save_pre', 'wp_filter_post_kses');
			
			if (is_wp_error($result)){
				return new WP_Error( $result->get_error_code(), __($result->get_error_message()), array( 'status' => 500 ) );
			}

			$category_map = array();
			foreach(get_categories(array( 'hide_empty' => 0)) as $category) {
				$category_map[(string)$category->term_id] = $category;
			}
	
			$response = $this->canvasflow_db->get_post($result);

			$categories = array();
			foreach(wp_get_post_categories((int)$response->id) as $category_id) {
				array_push($categories, $category_map[(string)$category_id]);
			}	

			$post = array(
				'id' => (int)$response->id,
				'title' => array(
					'rendered' => $response->title
				),
				'excerpt' => $response->excerpt,
				'link' => get_the_permalink($response->id),
				'date' => $response->post_modified_date,
				'status' => $response->status,
				'type' => $response->type,
				'categories' => $categories
			);

			// Check if metadata is not empty
			if(isset($metadata)) {
				try {
					$metadata = json_decode(stripslashes($metadata), true);
					$keys = array_keys($metadata);
					for($i=0; $i < count($keys); ++$i) {
						$key = $keys[$i];
						$value = $metadata[$keys[$i]];
						add_metadata('post', $post["id"], $key, $value, true);						
					}
				} catch(Exception $e){
					// If an error happend with the decode, i don't do anything
				}	
			}

			if(isset($_FILES["thumbnail"]["name"])) {
				$this->get_thumbnail_url($post);
			}

			$thumbnail = get_the_post_thumbnail_url($post['id']);
			if($thumbnail) {
				$post['thumbnail'] = $thumbnail;
			}

			return new WP_REST_Response( $post, 200 );
		}
	
		/**
		 * Update one item from the collection
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Request
		 */
		public function update_article( $request ) {
			//get parameters from request
			$params = $request->get_params();	
			$type = 'post';	
			$id = $params['id'];
			$status = 'publish';
			$excerpt = '';
			
			$set_content = true;
			// $_PUT = json_decode($_PUT, true);

			if (!isset($_POST['title'])) {
				return new WP_Error( 'invalid-title', __( 'Title is invalid' ), array( 'status' => 409 ) );
			}

			if (!isset($_POST['content'])) {
				$set_content = false;
			}

			if (isset($_POST['status'])) {
				$metadata = $_POST['status'];
			}

			if (isset($_POST['excerpt'])) {
				$excerpt = $_POST['excerpt'];
			}

			$title = $_POST['title'];

			remove_filter('content_save_pre', 'wp_filter_post_kses');
			remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

			$content = '';

			if($set_content) {
				$content = $_POST['content'];
			}
			
			
			if(!is_numeric($id)) {
				return new WP_Error( 'invalid-id', __( 'Post id is invalid' ), array( 'status' => 409 ) );
			}
	
			$id = esc_sql($id);
			$title = wp_strip_all_tags($title);

			$post = array(
				'ID' => (int)$id,
				'post_author' => $this->get_user_from_api_key($this->get_api_key_from_request()),
				'post_title' => $title,
				'post_excerpt' => $excerpt
			);

			if (isset($_POST['type'])) {
				if($_POST['type'] == 'post' || $_POST['type'] == 'page') {
					$post['post_type'] = $_POST['type'];
				}
			}

			if (isset($_POST['status'])) {
				if($_POST['status'] == 'publish' || $_POST['status'] == 'future' || $_POST['status'] == 'draft' || $_POST['status'] == 'pending'
				|| $_POST['status'] == 'private' || $_POST['status'] == 'inherit') {
					$post['post_status'] = $_POST['status'];
				}
			}

			if(isset($_POST['categories'])) {
				$post['post_category'] = json_decode($_POST['categories'], true);
			}
			
			$result = '';
			
			if(strlen($content) > 0) {
				$post['post_content'] = $content;
			}

			$result = wp_update_post($post, true);

			if($result == false || $result == null) {
				return new WP_Error( 'not-found', __( 'Post not found'), array( 'status' => 404 ) );
			}

			if(isset($_POST['metadata'])) {
				try {
					$metadata = json_decode(stripslashes($_POST['metadata']), true);
					$keys = array_keys($metadata);
					for($i=0; $i < count($keys); ++$i) {
						$key = $keys[$i];
						$value = $metadata[$keys[$i]];
						if(!get_post_meta($id, $key, true ) ) {
							add_metadata('post', $id, $key, $value, true);						
						} else {
							update_post_meta($id, $key, $value);
						}
					}
				} catch(Exception $e){
					// If an error happend with the decode, i don't do anything
				}	
			}
			
			add_filter('content_save_pre', 'wp_filter_post_kses');
			add_filter('content_filtered_save_pre', 'wp_filter_post_kses');
	
			if (is_wp_error($result)){
				return new WP_Error( $result->get_error_code(), __($result->get_error_message()), array( 'status' => 500 ) );
			}

			$category_map = array();
			foreach(get_categories(array( 'hide_empty' => 0)) as $category) {
				$category_map[(string)$category->term_id] = $category;
			}
	
			$response = $this->canvasflow_db->get_post($id);

			$categories = array();
			foreach(wp_get_post_categories((int)$response->id) as $category_id) {
				array_push($categories, $category_map[(string)$category_id]);
			}	
			
			$post = array(
				'id' => (int)$response->id,
				'title' => array(
					'rendered' => $response->title
				),
				'excerpt' => $response->excerpt,
				'link' => get_the_permalink($response->id),
				'date' => $response->post_modified_date,
				'status' => $response->status,
				'type' => $response->type,
				'categories' => $categories
			);

			if(isset($_FILES["thumbnail"]["name"])) {
				$this->get_thumbnail_url($post);
			}

			$thumbnail = get_the_post_thumbnail_url($post['id']);
			if($thumbnail) {
				$post['thumbnail'] = $thumbnail;
			}

			return new WP_REST_Response( $post, 200 );
		}
	
		/**
		 * Delete one item from the collection
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Request
		 */
		public function delete_article( $request ) {
			$params = $request->get_params();		
			$id = $params['id'];
	
			$response = $this->canvasflow_db->get_post($id);
			if($response == false || $response == null) {
				return new WP_Error( 'not-found', __( 'Post not found'), array( 'status' => 404 ) );
			}

			$post = array(
				'id' => (int)$response->id,
				'title' => array(
					'rendered' => $response->title
				),
				'excerpt' => $response->excerpt,
				'link' => get_the_permalink($response->id),
				'date' => $response->post_modified_date,
				'status' => $response->status,
				'type' => $response->type
			);

			$thumbnail = get_the_post_thumbnail_url($post['id']);
			if($thumbnail) {
				$post['thumbnail'] = $thumbnail;
			}
	
			$result = wp_delete_post( $id, false );
	
			if($result == false || $result == null) {
				return new WP_Error( 'error-delete-post', __('Error deleting the post'), array( 'status' => 500 ) );
			}
	
			return new WP_REST_Response($post, 200);
		}
	
		/**
		 * Get a collection of media
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function get_media( $request ) {
			return new WP_REST_Response( $this->get_all_media(), 200 );
		}
	
		private function get_all_media() {
			$response = array();
			$args = array(
				'post_type' => 'attachment',
				'numberposts' => -1,
				'post_status' => null,
				'post_parent' => null, // any parent
			); 
			$attachments = get_posts($args);
			if ($attachments) {
				foreach ($attachments as $post) {
					array_push($response, array(
						'id'=>(int)$post->ID,
						'date' => date_format(date_create($post->post_date), 'c'),
						'guid' => array(
							'rendered' => $post->guid
						),
						'title' => array(
							'rendered' => $post->post_title
						
						),
						'slug' => $post->post_name,
						'status' => $post->post_status,
						'type' => $post->post_type,
						'mime_type' => $post->post_mime_type,
						'author' => (int) $post->post_author,
						'media_details' => wp_get_attachment_metadata($post->ID),
						'source_url' => wp_get_attachment_url($post->ID)
					));
				}
			}
			return $response;
		}
	
		/**
		 * Upload a media file
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Response
		 */
		public function create_media( $request ) {
			$response = $this->upload_file_media();
			if (is_wp_error($response)){
				return new WP_Error( $response->get_error_code(), __($response->get_error_message()), array( 'status' => 500 ) );
			}
	
			$upload_dir = $response['upload_dir'];
			$filename = $response['target_file'];
	
			$filetype = wp_check_filetype( basename( $filename ), null );		
			$wp_upload_dir = $upload_dir;
	
			$attachment = array(
				'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ), 
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);
	
			$result = wp_insert_attachment( $attachment, $filename, 0,  true);
			if (is_wp_error($result)){
				return new WP_Error( $result->get_error_code(), __($result->get_error_message()), array( 'status' => 500 ) );
			}
	
			$attach_id = $result;
			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
				
			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
			wp_update_attachment_metadata( $attach_id, $attach_data );
	
			$post = get_post($attach_id);
			$media = array(
				'id'=> $post->ID,
				'date' => date_format(date_create($post->post_date), 'c'),
				'guid' => array(
					'rendered' => $post->guid
				),
				'title' => array(
					'rendered' => $post->post_title
				),
				'slug' => $post->post_name,
				'status' => $post->post_status,
				'type' => $post->post_type,
				'mime_type' => $post->post_mime_type,
				'author' => (int) $post->post_author,
				'media_details' => wp_get_attachment_metadata($post->ID),
				'source_url' => wp_get_attachment_url($post->ID)
			);
	
			return new WP_REST_Response( $media, 200 );
		}
	
		private function upload_file_media() {
			$upload_dir = wp_upload_dir();
			$target_file = $upload_dir['path'] . DIRECTORY_SEPARATOR . basename($_FILES["file"]["name"]);
			$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
			// Check if image file is a actual image or fake image
			if(isset($_POST["submit"])) {
				$check = getimagesize($_FILES["file"]["tmp_name"]);
				if($check !== false) {
					return new WP_Error( 'file-not-image', __( "File is not an image") );	
				}
				// echo "File is an image - " . $check["mime"] . ".";
			}
	
			// Check if file already exists
			if (file_exists($target_file)) {
				unlink($target_file);
			}
	
			// Check file size
			if ($_FILES["file"]["size"] > 500000000) {
				return new WP_Error( 'file-large', __( "Sorry, your file is too large.") );
			}
			// Allow certain file formats
			if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
				return new WP_Error( 'file-invalid-type', __( "Sorry, only JPG, JPEG, PNG & GIF files are allowed, '".$imageFileType."' extension was send") );
			}
			
			if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
				// echo "The file ". basename( $_FILES["file"]["name"]). " has been uploaded.";
				return array(
					'file_name' => $_FILES["file"]["name"],
					'target_file' => $target_file,
					'upload_dir' => $upload_dir
				);
			}
	
			return new WP_Error( 'error', __( "Error uploading the file") );
		}
	
		/**
		 * Check if a given request has access to get articles
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|bool
		 */
		public function get_articles_permissions_check( $request ) {
			return true;
			// return $this->has_valid_api_key($request);
			// return current_user_can( 'edit_something' );
		}
	
		/**
		 * Check if a given request has access to get a specific item
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|bool
		 */
		public function get_article_permissions_check( $request ) {
			return $this->get_articles_permissions_check( $request );
		}
	
		/**
		 * Check if a given request has access to create items
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|bool
		 */
		public function create_article_permissions_check( $request ) {
			return $this->user_can_create_posts($request);
		}
	
		/**
		 * Check if a given request has access to update a specific item
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|bool
		 */
		public function update_article_permissions_check( $request ) {
			return $this->user_can_edit_posts( $request );
		}
	
		/**
		 * Check if a given request has access to delete a specific item
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|bool
		 */
		public function delete_article_permissions_check( $request ) {
			return $this->create_article_permissions_check( $request );
		}
	
		/**
		 * Get the query params for collections
		 *
		 * @return array
		 */
		public function get_collection_params() {
			return array(
				'page'                   => array(
					'description'        => 'Current page of the collection.',
					'type'               => 'integer',
					'default'            => 1,
					'sanitize_callback'  => 'absint',
				),
				'per_page'               => array(
					'description'        => 'Maximum number of items to be returned in result set.',
					'type'               => 'integer',
					'default'            => 10,
					'sanitize_callback'  => 'absint',
				),
				'search'                 => array(
					'description'        => 'Limit results to those matching a string.',
					'type'               => 'string',
					'sanitize_callback'  => 'sanitize_text_field',
				),
			);
		}

		private function has_valid_api_key($request) {
			$authorization = $this->get_api_key_from_request();
			if(strlen($authorization) === 0) {
				return false;
			}

			$api_key = preg_replace('/Basic(\s)*/mi', '', $authorization);

			return $this->canvasflow_db->exist_api_key($api_key);
		}

		private function user_can_create_posts($request) {
			if($this->has_valid_api_key($request)) {
				$api_key = $this->get_api_key_from_request();
				$user_id = $this->get_user_from_api_key($api_key);
				if(strlen($api_key) > 0 && $user_id !== 0) {
					$user_meta=get_userdata($user_id);
					$user_roles=$user_meta->roles;
					foreach($user_roles as $role) {
						if($role === 'administrator' || $role === 'editor' || $role === 'author'){
							return true;
						}
					}
				}
			}
			return false;
		}

		private function user_can_edit_posts($request) {
			return $this->user_can_create_posts($request);
		}

		private function get_api_key_from_request() {
			$authorization = '';
			foreach (canvasflow_export_get_all_headers() as $name => $value) {
				if($name === 'X-Authorization') {
					$authorization = $value;
					break;
				}
			}
			$api_key = preg_replace('/Basic(\s)*/mi', '', $authorization);
			return $api_key;
		}

		private function get_user_from_api_key($api_key) {
			if(strlen($api_key) === 0) {
				return 0;
			}
			return $this->canvasflow_db->get_user_from_api_key($api_key);
		}


		private function get_thumbnail_url($post) {
			$upload_dir = wp_upload_dir();
			$post_id = $post['id'];
			$tmp_file = $upload_dir['path'] . DIRECTORY_SEPARATOR . basename($_FILES["thumbnail"]["tmp_name"]);
			$ext = pathinfo($_FILES["thumbnail"]["name"], PATHINFO_EXTENSION);
			$target_file = $upload_dir['path'] . DIRECTORY_SEPARATOR . basename($post_id.'-thumbnail.'.$ext);
			$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
				
				// Check if image file is a actual image or fake image
			if ($_FILES["thumbnail"]["size"] > 500000000) {
				unlink($tmp_file);
				return '';
			}

			// Allow certain file formats
			if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
				unlink($tmp_file);
				return '';
			}

			if (move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $target_file)) {
				$filename = $target_file;
				$wp_upload_dir = $upload_dir;

				$filetype = wp_check_filetype( basename( $filename ), null );	
				$thumbnail_url = $wp_upload_dir['url'] . '/' . basename( $filename );
				$attachment = array(
					'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ), 
					'post_mime_type' => $filetype['type'],
					'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				$attach_id = wp_insert_attachment( $attachment, $filename, $post_id,  true);
				if (is_wp_error($attach_id)){
					unlink($filename);
					return '';
				}

				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				set_post_thumbnail( $post_id, $attach_id );
				return $thumbnail_url;
			}
		}
	}
?>