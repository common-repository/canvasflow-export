<div class="wrap">
    <h1>Canvasflow Export Settings</h1>
    <hr />
	<br />
	
	<?php 
		if(get_option('permalink_structure') === '') {
			echo "<br><div class=\"error-message-static\"><div>The permalink is <span style=\"color: grey;\">Plain</span> so the plugin won't be able to listen to the requests</div></div>";
		}
	?>
	
	
	<p>The API key and URL details required to enable publishing from Canvasflow.</p>
 	<p>To connect Canvasflow to your WordPress site, enter the API key and WordPress API URL into the WordPress connector settings within your Canvasflow account.</p>
  
    <form method="post" action="admin.php?page=canvasflow-export-rest">
        <input name="cf_nonce_update_setting" type="hidden" value="<?php echo wp_create_nonce('cf-update-setting'); ?>" />
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="secret_key">API Key: </label>
                    </th>
                    <td>
                        <input id="secret_key" name="secret_key" type="text" value="<?php echo $api_key;?>"  class="regular-text" readonly> 
						<button id="secret_key_btn" class="button button-primary" type="button">Copy API Key</button>
						</br>
                        <small>
                            <em>
								Paste in the <b>API key</b> field of the Canvasflow WordPress connector channel settings
                            </em>
                        </small>
                    </td>
                </tr>   
				<tr>
                    <th scope="row">
                        <label for="api_path">API Path: </label>
                    </th>
                    <td>
                        <input id="api_path"  name="api_path" type="text" value="<?php echo get_option('siteurl').'/wp-json/canvasflow/v1';?>" onclick="copyPathToClipboard()" class="regular-text" readonly>
						<button id="api_path_btn" class="button button-primary" type="button">Copy API Path</button>
						</br>
						<small>
                            <em>
								Paste in the <b>WordPress API URL</b> field of the Canvasflow WordPress connector channel settings.
                            </em>
                        </small>
                    </td>
                </tr>   
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" class="button button-primary" value="Generate new API Key">
        </p>
    </form>
<div>