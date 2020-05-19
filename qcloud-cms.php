<?php
/*
Plugin Name: QCLoud CMS
Plugin URI: https://github.com/qcminecraft/qcloud-cms
Description: 使用腾讯云文本安全检查文章、评论
Version:  1.0
Author: 青草
Author URI: https://github.com/qcminecraft/
*/
add_action( 'admin_menu', 'qc_add_admin_menu' );
add_action( 'admin_init', 'qc_settings_init' );

//add_action('pre_post_update', 'check_post');
add_action('publish_post', 'check_post');

function check_post($post_id){
	global $wpdb;
	if(@$options['enable_posts'] == true) return null; //???👴懵了，为什么这里是true才行??
	$get_post = get_post($post_id, ARRAY_A);
	if(!$get_post) return null;
	if(!current_user_can('level_7'))
		if(qcloud_cms(base64_encode(strip_tags($get_post['post_title']."-".$get_post['post_content']))))
			$wpdb->update($wpdb->prefix . "posts", ["post_status" => "pending"], ["id"=> $post_id]);
	return null;
}

function qc_add_admin_menu(  ) { 

	add_submenu_page( 'plugins.php', 'qcloud-cms', '文本安全', 'manage_options', 'qcloud-cms', 'qc_options_page' );

}


function qc_settings_init(  ) { 

	register_setting( 'pluginPage', 'qc_settings' );

	add_settings_section(
		'qc_pluginPage_section', 
		__( ' ', 'qc' ), 
		'qc_settings_section_callback', 
		'pluginPage'
	);

	add_settings_field( 
		'enable_comments', 
		__( '启用评论检测（未完成）', 'qc' ), 
		'enable_comments_render', 
		'pluginPage', 
		'qc_pluginPage_section' 
	);

	add_settings_field( 
		'enable_posts', 
		__( '启用文章检测', 'qc' ), 
		'enable_posts_render', 
		'pluginPage', 
		'qc_pluginPage_section' 
	);

	add_settings_field( 
		'api_key', 
		__( '腾讯云SecretId', 'qc' ), 
		'api_key_render', 
		'pluginPage', 
		'qc_pluginPage_section' 
	);

	add_settings_field( 
		'api_secret', 
		__( '腾讯云SecretKEY', 'qc' ), 
		'api_secret_render', 
		'pluginPage', 
		'qc_pluginPage_section' 
	);


}


function enable_comments_render(  ) { 

	$options = get_option( 'qc_settings' );
	?>
	<input disabled='disabled' type='checkbox' name='qc_settings[enable_comments]' <?php checked( @$options['enable_comments'], 1 ); ?> value='1'>
	<?php

}


function enable_posts_render(  ) { 

	$options = get_option( 'qc_settings' );
	?>
	<input type='checkbox' name='qc_settings[enable_posts]' <?php checked( @$options['enable_posts'], 1 ); ?> value='1'>
	<?php

}


function api_key_render(  ) { 

	$options = get_option( 'qc_settings' );
	?>
	<input type='text' name='qc_settings[api_key]' value='<?php echo @$options['api_key']; ?>'>
	<?php

}


function api_secret_render(  ) { 

	$options = get_option( 'qc_settings' );
	?>
	<input type="password" style="display:none">
	<input type='password' name='qc_settings[api_secret]' value='<?php echo @$options['api_secret']; ?>'>
	<?php

}


function qc_settings_section_callback(  ) { 

	echo __( '服务开通：<a href="https://console.cloud.tencent.com/cms" target="_blank" autocomplete="off">内容安全</a>；
	<a href="https://console.cloud.tencent.com/cam/capi" target="_blank" autocomplete="off">访问密钥</a><br />
	说明：编辑(LEVEL_7)以下用户（订阅者、投稿者、作者）在发布文章时，若含有敏感词，将自动转为待审核文章，有编辑权限(LEVEL_7)及以上的用户将直接通过', 'qc' );

}


function qc_options_page(  ) { 

		?>
		<form action='options.php' method='post'>

			<h2>腾讯云文本安全</h2>

			<?php
			settings_fields( 'pluginPage' );
			do_settings_sections( 'pluginPage' );
			submit_button();
			?>

		</form>
		<?php

}

function qcloud_cms($text = ""){
	$options = get_option('qc_settings');
	$secretId = $options['api_key'];
	$secretKey = $options['api_secret'];
	if(!$secretId || !$secretKey) return null;
	$param["Nonce"] = mt_rand(100, 114514); //这么臭的随机数还有必要用吗？（半恼
	$param["Timestamp"] = time();
	$param["Region"] = "ap-guangzhou";
	$param["SecretId"] = $secretId;
	$param["Version"] = "2019-03-21";
	$param["Action"] = "TextModeration";
	$param["Content"] = $text;
	
	ksort($param);

	$signStr = "GETcms.tencentcloudapi.com/?";
	foreach ( $param as $key => $value ) {
		$signStr = $signStr . $key . "=" . $value . "&";
	}
	$signStr = substr($signStr, 0, -1);

	$signature = base64_encode(hash_hmac("sha1", $signStr, $secretKey, true));
	//echo $signature.PHP_EOL;
	$param["Signature"] = $signature;
	//echo $text; //阻止wp真的提交文章
	$result = json_decode(file_get_contents("https://cms.tencentcloudapi.com/?".http_build_query($param)));
	//var_dump($result->Response->Data->EvilFlag);
	return $result->Response->Data->EvilFlag;
	
}
