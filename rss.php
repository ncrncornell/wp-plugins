<?php
/*
Plugin Name: Ben's Custom RSS Plugin
Version: 1.2.1
Plugin URI: http://susantaslab.com/
Description: Makes it easy to display an RSS feed on a page. Derived from Susanta Beura's RSS plugin
Author: Susanta K Beura, Ben Perry
Author URI: http://susantaslab.com/
License: GPL v3
Usages: [rssonpage rss="Feed URL" feeds="Number of Items" excerpt="true/false" target="_blank|_self"]
*/

function fetchRSS($atts) {
	extract(shortcode_atts(array( 
	   	"rss" 		=> '', 
		"feeds" 	=> '10', 
		"excerpt" 	=> true,
		"target"	=> '_blank'
	),$atts));

	if ($rss != "" && $rssFeed = get_rss_feed($rss)) {

		$rssFeed->enable_order_by_date(false);
		$maxitems = $rssFeed->get_item_quantity($feeds); 
		if ($maxitems == 0) 
			return '<ul><li>Content not available at'.$rss .'.</li></ul>';

		$rss_items = $rssFeed->get_items(0,$maxitems);
		$content = '';

		foreach ($rss_items as $item) {

			$url = explode('/',trim($item->Get_permalink(),'/'));
			$handleGroup = $url[count($url)-2];
			$handlePaper = $url[count($url)-1];
			$title = preg_replace('!\s+!', ' ', $item->Get_title());
			$authors = "";
			$abstract = $item->get_description();
		
			$lastAuthor = "";
			foreach ($item->get_authors() as $a => $author){
				if($author->get_name() != 1){
					if($authors != ""){
						$authors.= " and ";
					}
					$authors .=$author->get_name();
					$lastAuthor = $author->get_name();
				}
			}
			
			$iAuthors = strpos($abstract,$lastAuthor);
			$abstract = substr($abstract,$iAuthors+strlen($lastAuthor)+1);
							
			$content.="@techreport{handle:$handleGroup:$handlePaper,\n";
			$content.="Title = {".$title."},\n";
			$content.="Author = {".$authors."},\n";
			$content.="institution = { NSF Census Research Network - NCRN-Cornell },\n";//TODO: Pull from feed
			$content.="type = {Preprint} ,\n";
			$content.="Year = {".$item->get_date('Y')."},\n";
			$content.="number={".$handleGroup.':'.$handlePaper."},\n";
			$content.="URL = {".trim($item->get_permalink())."},\n";
			$content.="abstract ={".$abstract."}\n";
			$content.="}\n";	
		} 
	}
	return $content;

}
add_shortcode('rss-ncrn','fetchRSS');
add_action( 'wp', 'prefix_setup_schedule' );

function prefix_setup_schedule() {
	if(!wp_next_scheduled( 'prefix_hourly_event' )){
		wp_schedule_event( time(), 'hourly', 'prefix_hourly_event');
	}
}

add_action( 'prefix_hourly_event', 'fetchRSS2' );

function fetchRSS2(){	
	$a = array('rss' => 'http://ecommons.cornell.edu/feed/atom_1.0/1813/30503', 'excerpt' => 'summary true', 'target' => '_blank');
	$content = fetchRSS($a);
	$file = fopen("wp-content/cache/ecommons.bib","w");
	fwrite($file,$content);
	fclose($file);
	return;
}
add_shortcode('rss-ncrn-cache','fetchRSS2');

if (!function_exists('get_rss_feed')){
	function get_rss_feed($url) {
		require_once(ABSPATH . WPINC . '/class-feed.php');

		$feed = new SimplePie();

		$feed->set_sanitize_class('WP_SimplePie_Sanitize_KSES');
		$feed->sanitize = new WP_SimplePie_Sanitize_KSES();
		$feed->set_useragent('Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML,like Gecko) Chrome/37.0.2062.120 Safari/537.36');

		$feed->set_cache_class('WP_Feed_Cache');
		$feed->set_file_class('WP_SimplePie_File');

		$feed->set_feed_url($url);
		$feed->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime',12 * HOUR_IN_SECONDS,$url));
		do_action_ref_array('wp_feed_options',array(&$feed,$url));
		$feed->init();
		$feed->handle_content_type();

		if ($feed->error())
			return new WP_Error('simplepie-error',$feed->error());
		return $feed;
	}
}
?>