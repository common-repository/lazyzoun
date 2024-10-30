<?php
/*
  Plugin Name: Lazyzoun Amazon Products
  Plugin URI: http://www.akrat.net
  Description: Quickly add Amazon Products to the Sidebar
  Author: Benedikt Kofler
  Version: 0.4.1
  Author URI: http://www.akrat.net/
  License: GPL 2.0, @see http://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 * require amazon-web-services  - authentication process
 */
require_once 'lib/aws_signed_request.php';

class lazyzoun {
    /*
     * check aws XML response
     */

    private function verifyXmlResponse($response) {
        //TODO check...
        if (!isset($response->Items->Item->ItemAttributes->Title)) {
            throw new Exception("Lazyzoun Error: Could not connect to Amazon");
        } else {
            if (isset($response->Items->Item->ItemAttributes->Title)) {
                return ($response);
            } else {
                throw new Exception("Lazyzoun Error: Invalid xml response.");
            }
        }
    }

    /*
     * Query Amazon
     */

    private function queryAmazon($parameters) {
        $public_key = get_option('lz_api_public_key');
        $private_key = get_option('lz_api_private_key');
		$amazon_tld = get_option('lz_api_amazon_tld');		
        $associate_tag = get_option('amazon_partner_id');

        return aws_signed_request($amazon_tld, $parameters, $public_key, $private_key, $associate_tag);
    }

    /*
     * get Item by Amazon ASIN Code
     */

    public function getItemByAsin($asin_code) {
        $parameters = array("Operation" => "ItemLookup",
            "ItemId" => $asin_code,
            "ResponseGroup" => "Medium");

        $xml_response = $this->queryAmazon($parameters);

        return $this->verifyXmlResponse($xml_response);
    }

    /*
     * check if folders are writeable
     */

    function foldersOk() {
        $uploads = wp_upload_dir();
        $upload_dir = ( $uploads['basedir'] );
        $download_url = ( $uploads['baseurl'] );

        $ThumbImgDir = $upload_dir . "/products/";
        $LargeImgDir = $upload_dir . "/products/";

        if (is_writable($ThumbImgDir) && is_writable($LargeImgDir)) {
            return true;
        }
    }

    /*
     * Get Thumb Image from AWS and write to cache
     */

    function getThumbImage($amazonProductId) {
        //set vars
		$productTitle = get_query_var('name');
		
        $uploads = wp_upload_dir();
        $upload_dir = ( $uploads['basedir'] );
        $download_url = ( $uploads['baseurl'] );

        $ImgDir = $download_url . "/products/";
        $ImgName = $amazonProductId . "-t-".$productTitle.".jpg";

        $ImgAbs = $upload_dir . '/products/' . $ImgName;
        $Img = $ImgDir . '' . $ImgName;

        //check if already cached:
        clearstatcache();
        if (file_exists($ImgAbs)) {
            Return $Img;
        } else {
            //else get from amazon api - if folders writeable
            if (lazyzoun::foldersOk()) {
                $obj = new lazyzoun();
                try {
                    $result = $obj->getItemByAsin($amazonProductId);

                    $ImgApiUrl = $result->Items->Item->MediumImage->URL;

                    //and save to cache folder
                    $ch = curl_init($ImgApiUrl);
                    $fp = fopen($ImgAbs, 'wb');
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);
                    Return $Img;
                } catch (Exception $e) {
                    if (is_user_logged_in ()) {
//                        echo "<script type=\"text/javascript\"> alert('";
//                        echo $e->getMessage();
//                        echo "')</script>";
						  echo "<!-- Lazyzoun Error: ".$e->getMessage()."-->";
                    }
                }
            }
        }
    }

    /*
     * Get Large Image from AWS and write to cache
     */

    function getLargeImage($amazonProductId) {
        //set vars
		$productTitle = get_query_var('name');
		
        $uploads = wp_upload_dir();
        $upload_dir = ( $uploads['basedir'] );
        $download_url = ( $uploads['baseurl'] );

        $ImgDir = $download_url . "/products/";
        $ImgName = $amazonProductId . "-l-".$productTitle.".jpg";
        $ImgAbs = $upload_dir . '/products/' . $ImgName;
        $Img = $ImgDir . '' . $ImgName;

        //check if already cached:
        clearstatcache();
        if (file_exists($ImgAbs)) {
            Return $Img;
        } else {
            //else get from amazon api - if folders writeable
            if (lazyzoun::foldersOk()) {

                $obj = new lazyzoun();

                try {
                    $result = $obj->getItemByAsin($amazonProductId);

                    $ImgApiUrl = $result->Items->Item->LargeImage->URL;

                    //and save to cache folder
                    $ch = curl_init($ImgApiUrl);
                    $fp = fopen($ImgAbs, 'wb');
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);
                    Return $Img;
                } catch (Exception $e) {
                    if (is_user_logged_in ()) {
//                        echo "<script type=\"text/javascript\"> alert('";
//                        echo $e->getMessage();
//                        echo "')</script>";
						  echo "<!-- Lazyzoun Error: ".$e->getMessage()."-->";
                    }              
				}
            }
        }
    }

    /*
     * Get Product Price from AWS
     */

    function getProductPrice($amazonProductId) {
        $obj = new lazyzoun();

        try {
            $result = $obj->getItemByAsin($amazonProductId);
        } catch (Exception $e) {
                    if (is_user_logged_in ()) {
//                        echo "<script type=\"text/javascript\"> alert('";
//                        echo $e->getMessage();
//                        echo "')</script>";
						  echo "<!-- Lazyzoun Error: ".$e->getMessage()."-->";
                    }     
				}

        $ProductPrice = $result->Items->Item->OfferSummary->LowestNewPrice->FormattedPrice;

        if (get_option('lz_debug') && is_user_logged_in()) {
            echo "<!-- XML RESULT: ";
            print_r($result);
            echo "-->";
        }
        return $ProductPrice;
    }

    /*
     * GA-Tracking
     *
     */

    function getGaClickTracking($category = "Lazyzoun", $action = "Click", $label = "Click", $value = ""){

        $ClickTracking = "";

        if(get_option('lz_gatracking')){
        $eventTracker = "['_trackEvent', '$category', '$action', '$label' $value]";
        $pageTracker = ",['_trackPageview','/click/lazyzoun/$label']";

        $ClickTracking = "_gaq.push(".$eventTracker."".$pageTracker.");";
        }

        return $ClickTracking;
    }
    /*
     * Create Amazon Product Link with PartnerId
     */

    function getAmazonProductLink($amazonProductId) {
        $AmazonPartnerID = get_option('amazon_partner_id');
        $AmazonDomain = get_option('amazon_domain');
        $AmazonLink = "http://$AmazonDomain/exec/obidos/ASIN/";

        $AmazonProductLink = "http://$AmazonDomain/exec/obidos/ASIN/" . $amazonProductId . "/?tag=" . $AmazonPartnerID;

        Return $AmazonProductLink;
    }

    /*
     * Init Lazyzoun Plugin
     */

    function init() {

        // check for the required WP functions, die silently for pre-2.2 WP.
        if (!function_exists('wp_register_sidebar_widget'))
            return;

        //Register CSS and JS File
        $handle = "lazyzoun";
        $plugin_path = WP_PLUGIN_URL . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
        $styleSrc = $plugin_path . "css/style.css";
        $scriptSrc = $plugin_path . "js/lazyzoun.js";
        wp_enqueue_style($handle, $styleSrc, $deps, $ver, $media);
        wp_enqueue_script($handle, $scriptSrc, $deps, $ver, true);


        // load all l10n string upon entry
        load_plugin_textdomain('lazyzoun');

        // let WP know of this plugin's widget view entry
        wp_register_sidebar_widget('lazyzoun', __('Lazyzoun', 'lazyzoun'), array('lazyzoun', 'widget'),
                array(
                    'classname' => 'lazyzoun',
                    'description' => __('Quickly add Amazon Products to the Sidebar', 'lazyzoun')
                )
        );

        // let WP know of this widget's controller entry
        wp_register_widget_control('lazyzoun', __('Lazyzoun', 'lazyzoun'), array('lazyzoun', 'control'),
                array('width' => 300)
        );

        // short code allows insertion of lazyzoun into regular posts as a [lazyzoun] tag.
        // From PHP in themes, call do_shortcode('lazyzoun');
        add_shortcode('lazyzoun', array('lazyzoun', 'shortcode'));
    }

    // back end options dialogue
    function control() {
        $options = get_option('lazyzoun');
        if (!is_array($options))
            $options = array('title' => __('Lazyzoun', 'lazyzoun'), 'subtitle' => __('Find the product on amazon!'), 'buttontext' => __('Buy now'));
        if ($_POST['lazyzoun-submit']) {
            $options['title'] = strip_tags(stripslashes($_POST['lazyzoun-title']));
            $options['subtitle'] = strip_tags(stripslashes($_POST['lazyzoun-subtitle']));
            $options['buttontext'] = strip_tags(stripslashes($_POST['lazyzoun-buttontext']));
            update_option('lazyzoun', $options);
        }
        $title = htmlspecialchars($options['title'], ENT_QUOTES);
        $subtitle = htmlspecialchars($options['subtitle'], ENT_QUOTES);
        $buttontext = htmlspecialchars($options['buttontext'], ENT_QUOTES);

        echo '<p style="text-align:right;"><label for="lazyzoun-title">' . __('Title:') .
        ' <input style="width: 200px;" id="lazyzoun-title" name="lazyzoun-title" type="text" value="' . $title . '" /></label></p>';
        echo '<p style="text-align:right;"><label for="lazyzoun-subtitle">' . __('Subtitle:', 'widgets') .
        ' <input style="width: 200px;" id="lazyzoun-subtitle" name="lazyzoun-subtitle" type="text" value="' . $subtitle . '" /></label></p>';
        echo '<p style="text-align:right;"><label for="lazyzoun-buttontext">' . __('Button Text:', 'widgets') .
        ' <input style="width: 200px;" id="lazyzoun-buttontext" name="lazyzoun-buttontext" type="text" value="' . $buttontext . '" /></label></p>';
        echo '<input type="hidden" id="lazyzoun-submit" name="lazyzoun-submit" value="1" />';
    }

    function view($is_widget, $args=array()) {
        global $post;
        $plugin_path = WP_PLUGIN_URL . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));

        $productTitle = get_the_title($postID);
        //Get CustomField
        $amazonProductId = get_post_meta($post->ID, 'lazyzoun-id', true);
        $amazonProductName = get_post_meta($post->ID, 'lazyzoun-name', true);

        if ($amazonProductName) {
            $productTitle = $amazonProductName;
        }

        if (is_single() AND $amazonProductId) {
            //Get Thumbs
            $ThumbImage = lazyzoun::getThumbImage($amazonProductId);

            //Get Amazon PartnerLink
            $ProductLink = lazyzoun::getAmazonProductLink($amazonProductId);

            if ($is_widget)
                extract($args);

            // get widget options
            $options = get_option('lazyzoun');

            if (get_option('lz_logo_widget_title')) {
                $title = "<a href=\"".$ProductLink."\" title=\"logo\" onclick=\"".lazyzoun::getGaClickTracking("Lazyzoun","Title-Logo",$productTitle)."\"><img class=\"logo-title fade_hover\" src=\"" . $plugin_path . "img/customlogo.jpg\" /></a>";
            } else {
                $title = $options['title'];
            }

            if (get_option('lz_subimgtext')) {
                $presubimgtext = get_option('lz_presubimgtext');
                $aftersubimgtext = get_option('lz_aftersubimgtext');
                $productprice = lazyzoun::getProductPrice($amazonProductId);
                $subimgtext = $presubimgtext . " " . $productprice . " " . $aftersubimgtext;
                //if getProductPrice false
                if (!$productprice) {
                    $subimgtext = "";
                }
            }

            $subtitle = $options['subtitle'];

            if (get_option('lz_gatracking')) {

            }


            //if no Subtitle, take ProductPrice
//            if (!$subtitle) {
//                $subtitle = lazyzoun::getProductPrice($amazonProductId);
//            }

            $buttontext = $options['buttontext'];
            $logo = $plugin_path . "img/logo.jpg";


            $gaProductTitle = lazyzoun::getGaClickTracking("Lazyzoun","ProductTitle",$productTitle);
            $gaProductImage = lazyzoun::getGaClickTracking("Lazyzoun","ProductImage",$productTitle);
            $gaSubImgButton = lazyzoun::getGaClickTracking("Lazyzoun","SubImgButton",$productTitle);
            $gaProductButton = lazyzoun::getGaClickTracking("Lazyzoun","ProductButton",$productTitle);

            $out[] = $before_widget . $before_title . $title . $after_title;
            $out[] = '<div style="margin-top:5px;">';

            $out[] = <<<FORM
<div class="amazonproduct" id="lazyzoun_aussen">
    <span class="amazonproduct">
        <a href="{$ProductLink}" class="widget-title" onclick="{$gaProductTitle}">
            <h3 class="amazonproduct">
                {$productTitle}
            </h3>
        </a>
        <p class="amazonproduct-meta">
                {$subtitle}
        </p>
                    <a href="{$ProductLink}" class="widget-title" onclick="{$gaProductImage}">
                        <img class="amazonproduct fade_hover" title="{$productTitle}" alt="{$productTitle}" src="{$ThumbImage}">
                    </a>
    </span>
    <a href="{$ProductLink}" class="subimgtext" title="{$productTitle}"  onclick="{$gaSubImgButton}">
    <p class="subimgtext">{$subimgtext}</p>
	</a>
	<span class="lzbuybutton fade_hover">
		<a href="{$ProductLink}" class="lzbuybutton" title="{$productTitle}"  onclick="{$gaProductButton}">
			<span class="lzlinks"></span>
			<span class="lzmitte">{$buttontext}</span>
			<span class="lzrechts"></span>
		</a>
	</span>
</div>
FORM;


            $out[] = '</div>';
            $out[] = $after_widget;
            return join($out, "\n");
        }
    }

    function shortcode($atts, $content=null) {
        return lazyzoun::view(false);
    }

    function widget($atts) {
        echo lazyzoun::view(true, $atts);
    }

}

if (function_exists('add_action')) {
    add_action('widgets_init', array('lazyzoun', 'init'));
} else {
    die('<!DOCTYPE html><html><head><title>Lazyzoun | WordPress Plugin</title></head><body>Nothing here!<br /><small>This message is brought to you by <a href="http://www.akrat.net/">lazyzoun</a>.</small></body></html>');
}

// create custom plugin settings menu
add_action('admin_menu', 'baw_create_menu');

function baw_create_menu() {

    //create new top-level menu
    add_menu_page('Lazyzoun Plugin Settings', 'Lazyzoun', 'administrator', __FILE__, 'lazyzoun_settings', plugins_url('/img/ico.png', __FILE__));
    //create new sub-level menu
    add_submenu_page(__FILE__, 'Lazyzoun', 'Lazyzoun', 'administrator', 'lazyzoun/lazyzoun.php', '');

    //call register settings function
    add_action('admin_init', 'register_mysettings');
}

function register_mysettings() {
    //register our settings
    register_setting('lazyzoun-settings-group', 'amazon_partner_id');
    register_setting('lazyzoun-settings-group', 'amazon_domain');
    register_setting('lazyzoun-settings-group', 'lz_api_public_key');
    register_setting('lazyzoun-settings-group', 'lz_api_private_key');
    register_setting('lazyzoun-settings-group', 'lz_api_amazon_tld');

    register_setting('lazyzoun-settings-group', 'lz_logo_widget_title');
    register_setting('lazyzoun-settings-group', 'lz_subimgtext');
    register_setting('lazyzoun-settings-group', 'lz_presubimgtext');
    register_setting('lazyzoun-settings-group', 'lz_aftersubimgtext');
    register_setting('lazyzoun-settings-group', 'lz_gatracking');
    register_setting('lazyzoun-settings-group', 'lz_debug');



//	register_setting( 'lazyzoun-settings-group', 'option_etc' );
}

function lazyzoun_settings() {
?>
    <div class="wrap">
        <h2>Lazyzoun</h2>
<?php
    if (get_option('lz_logo_widget_title')) {
        $lz_logo_widget_title = 'checked="checked"';
    } else {
        $lz_logo_widget_title = '';
    }
    if (get_option('lz_subimgtext')) {
        $lz_subimgtext = 'checked="checked"';
    } else {
        $lz_subimgtext = '';
    }
    if (get_option('lz_gatracking')) {
        $lz_gatracking = 'checked="checked"';
    } else {
        $lz_gatracking = '';
    }
    if (get_option('lz_debug')) {
        $lz_debug = 'checked="checked"';
    } else {
        $lz_debug = '';
    }
//Options Updated Message
    if ($_GET['updated'] == 'true') {
?>
        <div class="updated" id="message below-h2"><p>Lazyzoun settings updated successfully</p></div>
    <?php } ?>
<?php if (!lazyzoun::foldersOk()) {
?>
        <div class="error" id="message below-h2"><p>Make sure your Upload Folder is Writeable!</p></div>
    <?php } ?>


    <h3>Lazyzoun Settings:</h3>
    <form method="post" action="options.php">
<?php settings_fields('lazyzoun-settings-group'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row" style="width: 420px;">Amazon Partner ID</th>
                <td><input type="text" name="amazon_partner_id" value="<?php echo get_option('amazon_partner_id'); ?>" size="60" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Amazon Domain (Affiliate Link)</th>
                <td><input type="text" name="amazon_domain" value="<?php echo get_option('amazon_domain'); ?>" size="60" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Product Advertising API - Public Key</th>
                <td><input type="text" name="lz_api_public_key" value="<?php echo get_option('lz_api_public_key'); ?>" size="60" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Product Advertising API - Private Key</th>
                <td><input type="text" name="lz_api_private_key" value="<?php echo get_option('lz_api_private_key'); ?>" size="60" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Product Advertising API - Amazon-TLD-Domain (com/co.uk/de)</th>
                <td><input type="text" name="lz_api_amazon_tld" value="<?php echo get_option('lz_api_amazon_tld'); ?>" size="60" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Custom Logo in Widget Title (img/customlogo.jpg)</th>
                <td><input type="checkbox" id="lz_logo_widget_title" name="lz_logo_widget_title" value="1" <?php echo $lz_logo_widget_title ?> /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Show Product-Price below Image</th>
                <td><input type="checkbox" id="lz_subimgtext" name="lz_subimgtext" value="1" <?php echo $lz_subimgtext ?> /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Price Prefix</th>
                <td><input type="text" name="lz_presubimgtext" value="<?php echo get_option('lz_presubimgtext'); ?>" size="60" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Price Suffix</th>
                <td><input type="text" name="lz_aftersubimgtext" value="<?php echo get_option('lz_aftersubimgtext'); ?>" size="60" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Google Analytics Tracking</th>
                <td><input type="checkbox" id="lz_gatracking" name="lz_gatracking" value="1" <?php echo $lz_gatracking ?> /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Debug-Mode (shows XML Data - logged in only)</th>
                <td><input type="checkbox" id="lz_debug" name="lz_debug" value="1" <?php echo $lz_debug ?> /></td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
        </p>

    </form>
</div>
<?php
}

//  if(get_option('lz_subimgtext')){
//                $presubimgtext = get_option('lz_presubimgtext');
//                $aftersubimgtext = get_option('lz_aftersubimgtext');
?>