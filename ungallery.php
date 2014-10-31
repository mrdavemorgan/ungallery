<?php
/*
Plugin Name: UnGallery
Description: Publish thousands of pictures in WordPress, in minutes.    
Plugin URI: http://markpreynolds.com/technology/wordpress-ungallery
Author: Dave Morgan
Author URI: http://lepidoptera.net
Version: 2.2.2
*/

//  Set plugin version, update database so admin menu can display it
$version_val = "2.2.2";
update_option( "version", $version_val );

//  Display the plugin administration menu
include("configuration_menu.php");

add_filter('the_content', "ungallery");

function ungallery($content) {
	if(strpos( $content, "{ungallery=") === false){
		print $content;
		return;
	}
	if(preg_match('/{ungallery=(.+?)}/', $content, $matches)){
		$pic_root = $matches[1];
	} else {
		print "ungallery error";
		return;
	}

	//  Get the page name from the WP page slug
	global $wp_query;
	global $breadcrumb_separator;
	$post_obj = $wp_query->get_queried_object();
	$post_ID = $post_obj->ID;
	$post_name = $post_obj->post_name;
	
	//  Get the current gallery page's permalink
	$permalink = get_permalink();
	
	//  Base the UnGallery linking format on the site's permalink settings
	if (strstr($permalink, "?")) {
		$QorA = "&";
	} else {
		$QorA = "?";
	}

	//	Load the configuration data from the database
	$version = get_option( 'version' );
	$thumbW = get_option( 'thumbnail' );
	$srcW = get_option( 'browse_view' );
	$columns = get_option( 'columns' );
	if($columns == "") {
		$columns = 4; // set a default so admin page does not need visit after update. Remove at some point.
	}
	$max_thumbs = get_option( 'max_thumbs' ); 
	if ($max_thumbs == 0) {
		$max_thumbs = 25;
	}

	$w = $thumbW;
	$blogURI = get_bloginfo('url') . "/";	
	$dir = "wp-content/plugins/ungallery/";
	$phpthumburl = $blogURI . $dir . 'phpthumb/phpThumb.php';
	$gallerylink = $_GET['gallerylink'];
	$page = $_GET['page'];
	if (!$page) {
		$page = 1;
	}
	$offset = ($page -1) * $max_thumbs;
	$endset = $page * $max_thumbs;
	$squarethumb = (get_option('thumb_square') === 'true');
	$breadcrumb_separator = get_option( 'breadcrumb_separator' );
	if ($breadcrumb_separator == "") {
		$breadcrumb_separator = " / ";
	}
















	// populating our content arrays
	$breadcrumbs = array();
	$breadcrumbs[] = makeItemElement("breadcrumb", get_the_title(), "", $pic_root);
	if($gallerylink){
		$gallerylinkarray =  explode("/", $gallerylink);
		foreach ($gallerylinkarray as $level) {
			$pp .= $level ;
			$breadcrumbs[] = makeItemElement("breadcrumb", "", $pp, $pic_root);
			$pp .= "/";
		}
	}

	$subdirectories = array();
	$dp = opendir($pic_root.$gallerylink);	//  Read the directory for subdirectories
	while ($subdir = readdir($dp)) {		//  If it is a subdir enter it into the array
		if (is_dir($pic_root.$gallerylink. "/". $subdir) && (substr($subdir,0,1) != ".")) {
			$subdirectories[] = makeItemElement("subdir", "", $gallerylink . "/" . $subdir, $pic_root);
		}
	}
	closedir($dp);


	$images = array();
	$dp = opendir( $pic_root.$gallerylink);
	$pic_types = array("JPG", "jpg", "GIF", "gif", "PNG", "png", "BMP", "bmp"); 	
	while ($filename = readdir($dp)) {
		if ((!is_dir($pic_root.$gallerylink. "/". $filename))  && (in_array(pathinfo($filename, PATHINFO_EXTENSION), $pic_types))) { 
			$images[] = makeItemElement("image", "", $gallerylink . "/" . $filename, $pic_root);
		}
	}
	closedir($dp);
	$pages = ceil(count($images) / $max_thumbs) ;	//	Get the number of pages	

	// echo "<pre>\n";
	// echo "BREADCRUMBS\n";
	// var_dump($breadcrumbs);
	// echo "\n\n\n\nSUBDIRS\n";
	// var_dump($subdirectories);
	// echo "\n\n\n\nIMAGES\n";
	// var_dump($images);	
	// echo "</pre>\n";












	// print the breadcrumbs
	if(count($breadcrumbs)>1){
		foreach ($breadcrumbs as $bc) {
			print $breadcrumb_separator . '<a href="'. $permalink . $QorA .'gallerylink='. $bc['gallerypath'] .'" >'. $bc['name'] .'</a>';
		}
	}



	// table setup
	?><table width="100%"><tr>
	<td align="center"><div class="post-headline"><?

	// print title or banner
	$here = end($breadcrumbs);
	if($here['banner']){
		include($here['banner']);
	} else {
		print "<h2 style=\"text-align: center;\">" . $here['name'] ."</h2>";
	}

	//	Close cell. Add a bit of space
	?></td></tr><tr><td align="center"><p style="text-align: center;"><? 


 	$column = 0;

	// print subdirectories
	if(count($subdirectories)>0){
		foreach ($subdirectories as $sd) {
			if(file_exists($sd['thumb'])){
				$thumburl = getThumbUrl($phpthumburl, $w, $squarethumb, $sd['thumb'], 0);
			}
			printSubdirButton($sd['name'], $thumburl, $permalink . $QorA .'gallerylink='. rawurlencode($sd['gallerypath']));
			if((++$column) % $columns == 0){
				print "<br/>";
			}
		}
	}

	// print images
	if(count($images)>0){
		for($i=$offset; ($i<count($images) && $i<$endset); $i++){
			$thumburl = getThumbUrl($phpthumburl, $w, $squarethumb, $images[$i]['fullpath'], 0);
			$lightboxurl = getThumbUrl($phpthumburl, $srcW, 0, $images[$i]['fullpath'], $watermark);
			printLightBoxButton($images[$i]['name'], $thumburl, $lightboxurl);
			if((++$column) % $columns == 0){
				print "<br/>";
			}
		}
	}
	
	// If we are displaying thumbnails across multiple pages, display Next/Previous page links
	if ($pages > 1) {	
		print "</tr><tr><td>";
		if ($page > 1) 	{
			print '<a href="'. $permalink . $QorA .'gallerylink='. $gallerylink . '&page=1">&lt;&lt;</a>&nbsp';
			print '<a href="'. $permalink . $QorA .'gallerylink='. $gallerylink . '&page='. ($page - 1) .'">&lt;</a>';
		}
		print  " - Page $page / $pages - ";
		if ($pages > $page) {
			print '<a href="'. $permalink . $QorA .'gallerylink='. $gallerylink . '&page='. ($page + 1) .'">&gt;</a>&nbsp;';
			print '<a href="'. $permalink . $QorA .'gallerylink='. $gallerylink . '&page='. $pages .'">&gt;&gt;</a>';
		}
	}
	
	// Complete the table formatting 
	?></td></tr></td></table><?

}

function makeItemElement($type, $name, $gallerypath, $galleryroot){
	$gallerypath = ltrim($gallerypath, '/');
	$ret = array(
	    'gallerypath' => $gallerypath,
	    'fullpath' => $galleryroot . $gallerypath,
	);
	if($name){
		$ret['name'] = $name;
	} else {
		$ret['name'] = array_pop(explode("/", $gallerypath));
	}
	if($type == "breadcrumb"){
		if (file_exists($ret['fullpath']."/banner.txt")) {
			$ret['banner'] = $ret['fullpath']."/banner.txt";
		}
		if (file_exists($ret['fullpath']."/title.txt")) {
			$ret['name'] = file_get_contents($ret['fullpath']."/title.txt");
		}
		// don't care about thumb for breadcrumb since it's not displayed here
	} else if($type == 'subdir'){
		$thumb = getFolderImageFile($ret['fullpath']);
		if(file_exists($thumb)){
			$ret['thumb'] = $thumb;
		}
		if (file_exists($ret['fullpath']."/title.txt")) {
			$ret['name'] = file_get_contents($ret['fullpath']."/title.txt");
		}
		// don't care about banner for subdir since it's not used here
	} else if($type == 'image'){
		if (file_exists($ret['fullpath'] . ".txt")) {
			$ret['name'] = htmlentities(file_get_contents($ret['fullpath'] . ".txt"),ENT_QUOTES);
		}
	}
	return $ret;
}

function getFolderImageFile($folder){
	$imgfullpath = $folder . '/_folderimage';
	if(file_exists($imgfullpath)){
		return $imgfullpath;
	}

	$dp = opendir($folder);
	$pic_types = array("JPG", "jpg", "JPEG", "jpeg"); 
	while ($filename = readdir($dp)) {
		if(is_dir($folder. "/". $filename)){
		} else if (in_array(pathinfo($filename, PATHINFO_EXTENSION), $pic_types)){
			closedir($dp);
			return $folder. "/". $filename;
		}
	} 
	rewinddir($dp);
	while ($filename = readdir($dp)) {
		if((is_dir($folder. "/". $filename)) && (substr($filename,0,1) != '.')){
				$subdirimg =  getFolderImageFile($folder. "/". $filename);
				if($subdirimg){
					closedir($dp);
					return $subdirimg;
				}
		}
	} 
	closedir($dp);
	return '';
}

function getTitleString($imagepath, $default){
	if (file_exists("$imagepath.txt")) {
		return htmlentities(file_get_contents("$imagepath.txt"),ENT_QUOTES);
	}
	return $default;
}

function getThumbUrl($phpthumburl, $width, $square, $imgpath, $watermark){
	if($width > 0){
		$ret = "$phpthumburl?ar=x&w=$width&src=$imgpath";
		if($square){
			$ret .= "&zc=1&h=$width";
		}
	} else {
		$ret = "$phpthumburl?ar=x&src=$imgpath";
	}
	if($watermark){
		$ret .= "&fltr[]=wmi|$watermark|BL|100";
	}
	return $ret;
}

function printLightBoxButton($title, $thumburl, $expandedurl){
	?><a class="fancybox-button" href="<?=$expandedurl;?>" data-lightbox="lightbox-set" data-title="<?=$title;?>"><img src="<?=$thumburl;?>" alt=""/></a><?
}

function printSubdirButton($title, $thumburl, $url){
	?><a class="fancybox-button" style="position: relative;" href="<?=$url;?>"
	><img src="<?=$thumburl;?>" style="opacity: 0.75;"/><span 
		style="position: absolute; left: 10px; bottom: 0px; width: 100%; height: 100%; vertical-align: center; color: black;"><?=$title;?></span></a><?
}

// Add settings link on plugin page
function plugin_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=ungallerysettings">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
add_filter("plugin_action_links_" . plugin_basename(__FILE__), 'plugin_settings_link' );

function ungallery_set_plugin_meta($links, $file) {
	// create link
	if ($file == plugin_basename(__FILE__)) {
		return array_merge( $links, array( 
			'<a href="http://wordpress.org/tags/ungallery">' . __('Support Forum') . '</a>',
			'<a href="http://wordpress.org/extend/plugins/ungallery/faq/">' . __('FAQ') . '</a>',
			'<a href="https://winadatewithrusschapman.com" title="Russ!">' . __('Get a Date') . '</a>'
		));
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'ungallery_set_plugin_meta', 10, 2 );

?>