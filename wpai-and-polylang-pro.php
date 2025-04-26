<?php
/**
 * Plugin Name: WP All Import - Polylang Integration (Extended)
 * Plugin URI: https://www.upwork.com/freelancers/~01711603ac6386375d
 * Description: Integrates WP All Import Pro with Polylang Pro, enabling dynamic language assignment and translation mapping for all post types during imports. Seamlessly manage multilingual imports with Polylang support.
 * Version: 1.3.0
 * Author: Shahzad
 * Author URI: https://www.upwork.com/freelancers/~01711603ac6386375d
 * Text Domain: wp-all-import-polylang
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

/** ─── STEP 1: SET POST LANGUAGE ON SAVE ─── */
add_action('pmxi_saved_post', 'set_post_language', 10, 1);

function set_post_language($post_id) {
    if (!function_exists('get_field') || !function_exists('pll_set_post_language')) {
        error_log("[ERROR] Required functions are missing.");
        return;
    }

    if (!get_post($post_id)) {
        error_log("[ERROR] Post ID $post_id does not exist.");
        return;
    }

    $dynamic_language = get_field('language_field', $post_id) ?: 'fr';
    
    pll_set_post_language($post_id, $dynamic_language);
    error_log("[INFO] Updated language for Post ID $post_id: $dynamic_language");
}







add_action('rest_api_init', function () {
    register_rest_route('technocritik/v1', '/photos', [
        'methods'  => 'GET',
        'callback' => 'technocritik_get_photos',
        'permission_callback' => '__return_true',
    ]);
});

function technocritik_get_photos() {
    $file_url = 'https://technocritik.ca/centris/davidmaruani/listings/PHOTOS.TXT';
    $response = wp_remote_get($file_url);

    if (is_wp_error($response)) {
        return new WP_Error('fetch_failed', 'Failed to retrieve the file.', ['status' => 500]);
    }

    $body = wp_remote_retrieve_body($response);
    $lines = explode("\n", $body);
    $data = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $fields = str_getcsv($line);
        if (isset($fields[0]) && isset($fields[6])) {
            $data[] = [
                'api_listing_id'    => trim($fields[0], '"'),
                'photo_url' => $fields[6],
            ];
        }
    }

    return $data;
}












add_action('admin_menu', 'add_duplicate_en_menu');

function add_duplicate_en_menu() {
    add_menu_page(
        'Generate EN Translations',
        'Generate EN Translations',
        'manage_options',
        'generate-en-translations',
        'render_duplicate_en_page',
        'dashicons-translation',
        25
    );
}

function render_duplicate_en_page() {
    ?>
    <div class="wrap" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
        <h1 style="margin-bottom: 20px;">🈯 Generate Missing English Translations</h1>

        <div style="background: #fff; border-left: 4px solid #0073aa; padding: 20px; max-width: 600px; box-shadow: 0 0 10px rgba(0,0,0,0.05); border-radius: 6px;">

            <p style="font-size: 15px; line-height: 1.6;">
    This tool scans all <strong>“propriete”</strong> posts that do not yet have an English (<code>en</code>) translation. It then duplicates those posts as published, sets the language to English, and links them as Polylang translations.
</p>


            <form method="post" style="margin-top: 20px;">
                <input type="submit" name="generate_missing_en" class="button button-primary button-hero" value="🚀 Generate EN Translations Now">
            </form>
        </div>

        <?php
        if (isset($_POST['generate_missing_en'])) {
            $count = generate_missing_en_translations();
            echo '<div class="notice notice-success" style="margin-top: 20px;"><p>✅ <strong>' . $count . '</strong> EN translation(s) created successfully.</p></div>';
        }
        ?>

        <p style="margin-top: 40px; font-size: 13px; color: #666;">
            Developed with ❤️ by <a href=" https://www.upwork.com/freelancers/~01711603ac6386375d " target="_blank">Shahzad</a>
        </p>

    </div>
    <?php
}












// Core logic to generate or update EN translations
function generate_missing_en_translations() {
    if (
        !function_exists('pll_get_post_language') ||
        !function_exists('pll_get_post_translations') ||
        !function_exists('pll_set_post_language') ||
        !function_exists('pll_save_post_translations')
    ) {
        echo '<div class="notice notice-error"><p>❌ Polylang functions not available.</p></div>';
        return 0;
    }

    $args = array(
        'post_type'      => 'propriete',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'lang'           => 'fr',
    );

    $posts = get_posts($args);
    $created = 0;

    foreach ($posts as $post) {
        $translations = pll_get_post_translations($post->ID);

        if (isset($translations['en'])) {
            // 🔄 Update existing EN post
            $en_post_id = $translations['en'];

            // Update post content & title
            wp_update_post(array(
                'ID'           => $en_post_id,
                'post_title'   => $post->post_title . ' (EN)',
                'post_content' => $post->post_content,
            ));

            // Update custom fields
            $custom_fields = get_post_custom($post->ID);
            foreach ($custom_fields as $key => $values) {
                if (strpos($key, '_pll') === 0) continue;
                foreach ($values as $value) {
                    update_post_meta($en_post_id, $key, maybe_unserialize($value));
                }
            }

            $taxonomies = get_object_taxonomies(get_post_type($post->ID));

        foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'slugs']);
                if (!empty($terms)) {
                    print_r($taxonomy);
                    wp_set_object_terms($en_post_id, $terms, $taxonomy, false);
                }
            }

            // Ensure language and translation link are set
            pll_set_post_language($en_post_id, 'en');
            pll_save_post_translations(array_merge($translations, ['en' => $en_post_id]));

        } else {
            // 🆕 Create new EN translation
            $new_post_id = wp_insert_post(array(
                'post_title'   => $post->post_title . ' (EN)',
                'post_content' => $post->post_content,
                'post_status'  => 'publish',
                'post_type'    => $post->post_type,
            ));

            if ($new_post_id && !is_wp_error($new_post_id)) {
                $custom_fields = get_post_custom($post->ID);
                foreach ($custom_fields as $key => $values) {
                    if (strpos($key, '_pll') === 0) continue;
                    foreach ($values as $value) {
                        update_post_meta($new_post_id, $key, maybe_unserialize($value));
                    }
                }

                pll_set_post_language($new_post_id, 'en');
                $translations['en'] = $new_post_id;
                pll_save_post_translations($translations);

                $created++;
            }
        }
    }

    return $created;
}

































function fetch_and_compare_images($post_id, $xml_node, $is_update) {
    if ('propriete' !== get_post_type($post_id)) {
        return;
    }

    $listing_id = get_field('listing_id', $post_id);
    if (!$listing_id) {
        error_log('No listing_id found for post ID ' . $post_id);
        return;
    }

    $api_url = 'https://technocritik.ca/wp-json/technocritik/v1/photos?listing_id=' . $listing_id;
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        error_log('Error fetching data from API for listing_id ' . $listing_id);
        return;
    }

    $photos = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($photos)) {
        error_log('No photos returned for listing_id ' . $listing_id);
        return;
    }

    $image_ids = [];

    foreach ($photos as $photo) {
        if ($photo['api_listing_id'] == $listing_id) {
            $image_url = $photo['photo_url'];
            $filename = 'image_' . md5($image_url) . '.jpg';

            // Check if this image already exists in the media library
            $existing = get_page_by_title($filename, OBJECT, 'attachment');

            if ($existing && file_exists(get_attached_file($existing->ID))) {
                // Image already exists, use its ID
                $image_ids[] = $existing->ID;
                error_log('Image already exists, reusing: ' . $filename);
                continue;
            }

            // Download the image
            $image_data = file_get_contents($image_url);
            if ($image_data === false) {
                error_log('Error downloading image: ' . $image_url);
                continue;
            }

            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;
            file_put_contents($file_path, $image_data);

            $wp_filetype = wp_check_filetype($filename, null);
            if (!$wp_filetype['type']) {
                $wp_filetype['type'] = 'image/jpeg';
            }

            $attachment = array(
                'guid'           => $upload_dir['url'] . '/' . $filename,
                'post_mime_type' => $wp_filetype['type'],
                'post_title'     => $filename, // Important for later lookup
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
            if (is_wp_error($attachment_id)) {
                error_log('Error inserting attachment: ' . $image_url);
                continue;
            }

            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);

            $image_ids[] = $attachment_id;
            error_log('Downloaded and added new image: ' . $image_url);
        }
    }

    if (!empty($image_ids)) {
        $existing_gallery = get_field('gallery_photos', $post_id);
        if (!empty($existing_gallery)) {
            $image_ids = array_merge($existing_gallery, $image_ids);
        }

        update_field('gallery_photos', $image_ids, $post_id);
        error_log('Updated gallery_photos for post ID ' . $post_id . ' with ' . count($image_ids) . ' images.');
    } else {
        error_log('No new images added for post ID ' . $post_id);
    }
}

add_action('pmxi_saved_post', 'fetch_and_compare_images', 10, 3);



// Hook into WP All Import's action that runs after each post is imported
add_action('pmxi_saved_post', 'check_and_update_featured_status', 10, 1);

function check_and_update_featured_status($post_id) {
    // Check if it's our custom post type (replace 'propriete' with your actual CPT name if different)
    if (get_post_type($post_id) !== 'propriete') {
        return;
    }
    
    $date_value = get_post_meta($post_id, 'date_mise_en_vigueur', true);

    // Check if date exists
    if (empty($date_value)) {
        return;
    }
    
    // Convert date string to timestamp (considering d/m/Y format)
    //$date_parts = explode('/', $date_value);
    //update_post_meta($post_id, 'featured', $date_value);
    //if (count($date_parts) !== 3) {
        // Invalid date format
    //    return;
    // }
    
    // Create datetime object from d/m/Y format
    $day = substr($date_value, 6,2 );
    $month = substr($date_value, 4, 2);
    $year = substr($date_value, 0, 4);
    print_r($day. ' ' . $month . ' ' . $year);
    
    $date_timestamp = mktime(0, 0, 0, $month, $day, $year);
    if ($date_timestamp === false) {
        // Invalid date
        return;
    }
       // Get current timestamp
    $today_timestamp = strtotime('today');
    
    // Calculate difference in days
    $diff_days = floor(($today_timestamp - $date_timestamp) / (60 * 60 * 24));
    
    // Check if date is less than 15 days from today
   if ($diff_days >= 0 && $diff_days < 21) {
        // Update the featured custom field
        update_post_meta($post_id, 'featured', 'Featured');
    }
    else {
        update_post_meta($post_id, 'featured', $date_value. ' ' .$day . ' ' . $month . ' ' .$year. ' ' . $diff_days);
    }
    
}


add_action('pmxi_saved_post', 'check_visite_libre_and_update_flag', 10, 1);

function check_visite_libre_and_update_flag($post_id) {
    // Check if it's our custom post type (replace 'propriete' with your actual CPT name if different)
    if (get_post_type($post_id) !== 'propriete') {
        return;
    }
    
    // Check if visite_libre repeater field exists and is not empty
    // Assuming the repeater field is an ACF field - adjust if using a different plugin
    
    $has_visite_libre = false;
    
    // Method 1: For ACF repeater fields
    if (function_exists('have_rows') && have_rows('visite_libre', $post_id)) {
        // If at least one row exists, consider it not empty
        $has_visite_libre = true;
    }
    
    // Method 2: Alternative check if storing as serialized array
    if (!$has_visite_libre) {
        $visite_libre = get_post_meta($post_id, 'visite_libre', true);
        if (!empty($visite_libre) && (is_array($visite_libre) || is_object($visite_libre))) {
            $has_visite_libre = true;
        }
    }
    
    // Method 3: Check if there's a count field that ACF often creates for repeaters
    if (!$has_visite_libre) {
        $visite_libre_count = get_post_meta($post_id, 'visite_libre_count', true);
        if (!empty($visite_libre_count) && $visite_libre_count > 0) {
            $has_visite_libre = true;
        }
    }
    
    // Update the flag based on whether visite_libre has content
    if ($has_visite_libre) {
        update_post_meta($post_id, 'visite_libre_flag', 'oui');
    } else {
        // Optional: set to 'no' if you want to ensure the flag is always set
        update_post_meta($post_id, 'visite_libre_flag', 'non');
    }
}




















add_filter('wp_all_import_is_post_to_update', 'only_update_fr_propriete', 10, 3);
function only_update_fr_propriete($continue_import, $post_id, $import_id) {
    // Get the language of the post
    $post_lang = function_exists('pll_get_post_language') ? pll_get_post_language($post_id) : null;

    // Get the post type
    $post_type = get_post_type($post_id);

    // Only update if it's a 'propriete' post and language is 'fr'
    if ($post_type !== 'propriete' || $post_lang !== 'fr') {
        return false; // Skip update
    }

    return $continue_import; // Allow update
}



// Add this to your theme's functions.php or a custom plugin

// Register a custom endpoint that will check the file and delete data if needed
add_action('wp_ajax_check_empty_visites_libres_file', 'check_empty_visites_libres_file');
add_action('wp_ajax_nopriv_check_empty_visites_libres_file', 'check_empty_visites_libres_file');

function check_empty_visites_libres_file() {
    // URL to your import file
    $file_url = 'https://technocritik.ca/centris/davidmaruani/listings/VISITES_LIBRES2.TXT';
    
    // Get file content
    $response = wp_remote_get($file_url);
    
    // Check if request was successful and file is empty
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $file_content = wp_remote_retrieve_body($response);
        
        if (empty(trim($file_content))) {
            // File exists but is empty, delete all visite_libre repeater fields
            $args = array(
                'post_type' => 'propriete',
                'posts_per_page' => -1,
                'fields' => 'ids'
            );
            
            $propriete_posts = get_posts($args);
            
            foreach ($propriete_posts as $post_id) {
                // Delete the repeater field completely
                //delete_post_meta($post_id, 'visite_libre');
                
                // If you're using ACF, use this approach instead:
                 update_field('visite_libre', array(), $post_id);
            }
            
            wp_send_json_success('All visite_libre fields have been deleted due to empty import file');
        } else {
            wp_send_json_error('File exists but is not empty');
        }
    } else {
        wp_send_json_error('Could not access the file');
    }
}

// Create a scheduled event to check the file
register_activation_hook(__FILE__, 'schedule_visites_libres_check');

function schedule_visites_libres_check() {
    if (!wp_next_scheduled('check_visites_libres_file_cron')) {
        wp_schedule_event(time(), 'hourly', 'check_visites_libres_file_cron');
    }
}

add_action('check_visites_libres_file_cron', 'run_visites_libres_check');

function run_visites_libres_check() {
    // URL to your import file
    $file_url = 'https://technocritik.ca/centris/davidmaruani/listings/VISITES_LIBRES2.TXT';
    
    // Get file content
    $response = wp_remote_get($file_url);
    
    // Check if request was successful and file is empty
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $file_content = wp_remote_retrieve_body($response);
        
        if (empty(trim($file_content))) {
            // File exists but is empty, delete all visite_libre repeater fields
            $args = array(
                'post_type' => 'propriete',
                'posts_per_page' => -1,
                'fields' => 'ids'
            );
            
            $propriete_posts = get_posts($args);
            
            foreach ($propriete_posts as $post_id) {
               // delete_post_meta($post_id, 'visite_libre');
                // For ACF: 
                update_field('visite_libre', array(), $post_id);
            }
            
            error_log('Scheduled task: Deleted all visite_libre fields due to empty import file');
        }
    }
}

// Add a menu item in the admin to manually trigger the check
add_action('admin_menu', 'add_visites_libres_check_menu');

function add_visites_libres_check_menu() {
    add_submenu_page(
        'tools.php', 
        'Check Visites Libres File', 
        'Check Visites Libres', 
        'manage_options', 
        'check-visites-libres', 
        'display_visites_libres_check_page'
    );
}

function display_visites_libres_check_page() {
    ?>
    <div class="wrap">
        <h1>Check Visites Libres File</h1>
        <p>Click the button below to check if the Visites Libres file is empty and delete all visite_libre fields if necessary.</p>
        <button id="check-visites-libres-button" class="button button-primary">Check and Process</button>
        <div id="result-message" style="margin-top: 15px;"></div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#check-visites-libres-button').on('click', function() {
            $(this).prop('disabled', true).text('Processing...');
            $('#result-message').html('<p>Checking file...</p>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_empty_visites_libres_file'
                },
                success: function(response) {
                    if (response.success) {
                        $('#result-message').html('<p style="color:green;">' + response.data + '</p>');
                    } else {
                        $('#result-message').html('<p style="color:red;">' + response.data + '</p>');
                    }
                },
                error: function() {
                    $('#result-message').html('<p style="color:red;">Error occurred during the process.</p>');
                },
                complete: function() {
                    $('#check-visites-libres-button').prop('disabled', false).text('Check and Process');
                }
            });
        });
    });
    </script>
    <?php
}