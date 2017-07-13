<?php

/*
Plugin Name: IDX Search
Plugin URI: http://www.test.com
Description: test
Version: 0.0.1
Author: test
Contributors: me
Author URI: http://www.test.com/
License: GPLv2 or later
 */

 function get_this_site_url( $blog_id = null, $path = '', $scheme = null ) {
    if ( empty( $blog_id ) || !is_multisite() ) {
        $url = get_option( 'siteurl' );
    } else {
        switch_to_blog( $blog_id );
        $url = get_option( 'siteurl' );
        restore_current_blog();
    }

    $url = set_url_scheme( $url, $scheme );

    if ( $path && is_string( $path ) )
        $url .= '/' . ltrim( $path, '/' );

    /**
     * Filters the site URL.
     *
     * @since 2.7.0
     *
     * @param string      $url     The complete site URL including scheme and path.
     * @param string      $path    Path relative to the site URL. Blank string if no path is specified.
     * @param string|null $scheme  Scheme to give the site URL context. Accepts 'http', 'https', 'login',
     *                             'login_post', 'admin', 'relative' or null.
     * @param int|null    $blog_id Site ID, or null for the current site.
     */
    return apply_filters( 'site_url', $url, $path, $scheme, $blog_id );
}



if (!class_exists('idxBrokerSearchQuery')){
    /**
    * This class is mostly just WP_EX_PAGE_ON_THE_FLY by Ohad Raz
    * Class to create pages "On the FLY" Link below.
    * https://coderwall.com/p/fwea7g/create-wordpress-virtual-page-on-the-fly
    * IDX Broker API call added
    */
    class idxBrokerSearchQuery
    {

        public $slug ='';
        public $args = array();
        /**
         * __construct
         * @param array $arg post to create on the fly
         *
         */


        function __construct(){


          $args = array(
                  'slug' => 'idx-search',
                  'post_title' => 'Search Results',
                  'post content' => 'fake'
          );


            add_filter('the_posts',array($this,'fly_page'));
            $this->args = $args;
            $this->slug = $args['slug'];
        }




        /**
         * returns page
         * @param  array $posts
         * @return array
         */
        public function fly_page($posts){
            global $wp,$wp_query;
            $page_slug = $this->slug;

            //check if user is requesting our fake page
            if(count($posts) == 0 && (strtolower($wp->request) == $page_slug || $wp->query_vars['page_id'] == $page_slug)){

              //get url params for search
              $hp = htmlspecialchars($_GET['hp']);
              $lp = htmlspecialchars($_GET['lp']);

              $idxSearchQueryParams = 'hp='.$hp.'&lp='.$lp;

              //call IDX API
              $params = array();
              $headers = array('Content-Type' => 'application/x-www-form-urlencoded',
                'accesskey' => 'apiKey',
                'ancillarykey' => 'partnerApiKey',
                'outputtype' => 'json',
                'apiversion' => '1.4.0',);
              $params = array_merge(array('timeout' => 920, 'sslverify' => false, 'headers' => $headers), $params);
              $url = 'https://api.idxbroker.com/clients/searchquery?'.$idxSearchQueryParams;
              $response = wp_remote_get($url, $params);
              $response = json_decode($response["body"], true);
              $searchResults = '';
              $mapPinsLatLng = array();

              $searchResults = $searchResults.'<div id="apiResults">';

              //create html for the front end
              foreach ($response as $key => $value) {
                array_push($mapPinsLatLng, '{lat:'.$value["latitude"].',lng:'.$value["longitude"].'}');
                $searchResults = $searchResults.'<div id="idxImage"><img src="'.$value["image"][0]["url"].'" /></div>
                <div id="idxAddress">'.$value["address"].'</div>
                <div id="idxListingPrice">
                '.$value["listingPrice"].'
                </div>
                <p>
                ---------
                </p>';
              }
              $searchResults = $searchResults.'</div><div id="map"></div>
              <script>
      function initMap() {
        var map = new google.maps.Map(document.getElementById("map"), {
          zoom: 4,
          center: {lat: 25.691400144073, lng: -80.252797882037}
        });
        var labels = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        var locations = '.str_replace('"', '', json_encode($mapPinsLatLng)).';
        var markers = locations.map(function(location, i) {
          return new google.maps.Marker({
            position: location,
            label: labels[i % labels.length]
          });
        });
        // Add a marker clusterer to manage the markers.
        var markerCluster = new MarkerClusterer(map, markers,
            {imagePath: "https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m"});
      }
    </script>
    <script src="https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js">
    </script>
    <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=googleMapApiKey
&callback=initMap">
    </script>
              <style>#idxImage{width:150px;} div#apiResults {float: left; width: 20%;} #map {float: right; height: 800px !important; width: 80% !important;}</style>';


                //create a fake post
                $post = new stdClass;
                $post->post_author = 1;
                $post->post_name = $page_slug;
                $post->guid = get_bloginfo('wpurl' . '/' . $page_slug);
                $post->post_title = 'page title';
                //custom content here
                $post->post_content = $searchResults;
                //just needs to be a number - negatives are fine
                $post->ID = -42;
                $post->post_status = 'static';
                $post->comment_status = 'closed';
                $post->ping_status = 'closed';
                $post->comment_count = 0;
                //dates may need to be overwritten if you have a "recent posts" widget or similar - set to whatever you want
                $post->post_date = current_time('mysql');
                $post->post_date_gmt = current_time('mysql',1);

                $post = (object) array_merge((array) $post, (array) $this->args);
                $posts = NULL;
                $posts[] = $post;

                $wp_query->is_page = true;
                $wp_query->is_singular = true;
                $wp_query->is_home = false;
                $wp_query->is_archive = false;
                $wp_query->is_category = false;
                unset($wp_query->query["error"]);
                $wp_query->query_vars["error"]="";
                $wp_query->is_404 = false;
            }

            return $posts;
        }
    }//end class

}//end if


  new idxBrokerSearchQuery();

  //Short Code
  function idx_search_short_code() {
  	echo '<div id="idxSearchForm">
    <h2>Search for a property</h2>
    <form action="'.$url.'/wordpress/idx-search">
      <input type="text" name="hp" placeholder="High Price" />
      <input type="text" name="lp" placeholder="Low Price"  />
      <input type="submit" value="Submit">
    </form>
    </div>.';
  }
  add_shortcode('idx_search','idx_search_short_code');


 ?>
