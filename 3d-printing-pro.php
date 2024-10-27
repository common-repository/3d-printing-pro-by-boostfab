<?php
/*
 * Plugin Name: 3D Printing Pro by Boostfab
 * Description: Allow your users to get a quote for 3D printing and laser cutting jobs.
 * Version: 1.2.3
 * Author: Boostfab
 * Author URI: https://boostfab.com
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly   

$boostfab_public_url = "https://boostfab.com";
$boostfab_api_url = "https://api.boostfab.com";
$boostfab_api_url_from_backend = "https://api.boostfab.com";
$boostfab_name = "Boostfab";

const BOOSTFAB_SHORTCODE = "boostfab_embedded";

if (in_array(wp_get_environment_type(), ['development', 'local'], true)) {
    $boostfab_public_url = getenv("QUOTIFY_PUBLIC_URL") ?: "http://localhost:3000";
    $boostfab_api_url = getenv("QUOTIFY_API_URL") ?: "http://localhost:3333";
    $boostfab_api_url_from_backend = getenv("QUOTIFY_API_URL_BACKEND") ?: "http://api:3000";
}

register_activation_hook(__FILE__, 'boostfab_initialize_plugin');
register_uninstall_hook(__FILE__, 'boostfab_uninstall_plugin');

add_action('init', 'boostfab_handle_logout');

// Custom short code to embed QQ4C with a iframe.
// Attributes supported:
// - primary_color [optional]: the primary color of the embed
// - id [optional]: the id of the iframe. If not provided, a random id will be generated.
add_shortcode(BOOSTFAB_SHORTCODE, 'boostfab_three_d_printer_embedded_shortcode');

// Add a menu item to the admin menu to manage the plugin.
add_action("admin_menu", "boostfab_register_admin_menu_item");

add_action('wp_ajax_update_settings', 'boostfab_update_settings');
add_action('wp_ajax_create_sample_page', 'boostfab_create_sample_page');

add_thickbox();

function boostfab_initialize_plugin() {
    add_option("boostfab_api_token", "");
    add_option("boostfab_organization_slug", "");
    delete_option("boostfab_cookie_policy", "boostfab_widget");
}

function boostfab_uninstall_plugin() {
    delete_option("boostfab_api_token");
    delete_option("boostfab_organization_slug");
    delete_option("boostfab_primary_color");
    delete_option("boostfab_cookie_policy");
}

function boostfab_three_d_printer_embedded_shortcode($atts = [], $content = null) {
    global $boostfab_public_url, $boostfab_name;

    $id = "3d-printer-iframe-" . uniqid();
    $query = [];
    if (isset($atts['primary_color'])) {
        $query['primaryColor'] = $atts['primary_color'];
    }
    else if (!empty(get_option("boostfab_primary_color"))) {
        $query['primaryColor'] = get_option("boostfab_primary_color");
    }
    if (isset($atts['id'])) {
        $id = $atts['id'];
    }

    $slug = get_option("boostfab_organization_slug");
    if (($slug === "" || $slug == null) && (current_user_can('editor') || current_user_can('administrator'))) {
        return "<div style='alert alert-error'>Not authenticated. Please go to the Wordpress's admin panel &rarr; {$boostfab_name} Panel to authenticate.</div>";
    }

    $cookie_policy = get_option("boostfab_cookie_policy", "boostfab_widget");
    $mapping = [
        'no_consent_required' => 'accepted',
        'my_site' => 'delayed',
        'boostfab_widget' => 'ask'
    ];
    $query['cookiePolicy'] = $mapping[$cookie_policy];

    $config = json_encode($query);
    return "
        <div id=\"boostfab-container\"></div>
        <script src=\"{$boostfab_public_url}/js/widget.js\"></script>
        <script>
            const organizationSlug = '{$slug}';
            const config = {$config};
            boostfabInit('boostfab-container', organizationSlug, {$config});
        </script>
    ";
}

function boostfab_register_admin_menu_item() {
    global $boostfab_name;

    $badge = "";
    $api_token = get_option("boostfab_api_token", '');
    if (!is_signedin_or_trying()) {
        $badge = '<span class="update-plugins count-1"><span class="plugin-count">!</span></span>';
    }

    add_menu_page("{$boostfab_name} Panel", "{$boostfab_name} Panel {$badge}", "manage_options", "boostfab-panel", "boostfab_print_admin_panel", "dashicons-cart");
}

function is_signedin_or_trying() {
    return $api_token = get_option("boostfab_api_token", '') != '' || !empty($_POST["api_token"]);
}

function boostfab_print_admin_panel() {
    global $boostfab_api_url_from_backend;

    $api_token = get_option("boostfab_api_token", '');

    $can_signin = current_user_can('edit_posts') || current_user_can('edit_pages');
    if (isset($_POST["api_token"]) && $can_signin) {
        check_admin_referer('signin');
        $api_token_input = sanitize_text_field($_POST["api_token"]);
        if (preg_match('/^[a-zA-Z0-9-_\.]+$/', $api_token_input)) {
            update_option("boostfab_api_token", $api_token_input);
            $api_token = $api_token_input;
            $response = wp_remote_get($boostfab_api_url_from_backend . "/organizations", [
                'headers' => [
                    'Authorization' => "Bearer {$api_token}"
                ]
            ]);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                echo esc_html("Something went wrong: " . $error_message);
            }
            else {
                $json = json_decode($response['body']);
                $organizations = $json->items;
                if (count($organizations) > 0) {
                    $organization = $organizations[0];
                    update_option("boostfab_organization_slug", $organization->slug);
                }
            }
        }
        else {
            echo esc_html("The API token does not seem to be valid: " . $api_token_input);
        }
    }

    $signedIn = $api_token !== '';

    if ($signedIn) {
        boostfab_print_admin_panel_authenticated();
    }
    else {
        boostfab_print_admin_panel_unauthenticated();
    }
    ?>
    
    
    <?php
}

function boostfab_print_admin_panel_authenticated() {
    global $boostfab_name;
    require_once('main.php');
}

function boostfab_print_admin_panel_unauthenticated() {
    global $boostfab_name;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html($boostfab_name . ' ' . _("Panel")) ?></h1>
        <hr class="wp-header-end">

        <div style="display: flex">
            <div class="card">
                <?php boostfab_print_admin_panel_signin() ?>
            </div>
            <div style="padding-top: 20px; flex-grow: 1">
                <video autoplay muted loop style="max-width: 726px; width: 70%; display: block; margin: 0 auto; box-shadow: 0 0 10px rgba(0, 0, 0, 0.2)">
                    <source src="<?php echo esc_attr(plugins_url("assets/intro.mp4", __FILE__)) ?>" type="video/mp4">
                </video>
            </div>
        </div>
    </div>
    <?php
}

function boostfab_print_admin_panel_signin() {
    global $boostfab_api_url, $boostfab_public_url;
    ?>
    <h2 class="title">
        <?php echo
            wp_kses(
                sprintf(
                    _("Sign up in Boostfab"),
                    esc_url($boostfab_public_url)
                ),
                'post'
            )
        ?>
    </h2>
    <iframe
        id="boostfab-signup-iframe"
        src="<?php echo esc_url($boostfab_public_url) ?>/signup-embedded?email=<?php echo esc_attr(wp_get_current_user()->user_email) ?>&referrer=<?php echo get_site_url(); ?>"
        style="width: 100%; height: 315px; border: none;"
        class="iframe-loading">
    </iframe>
    <hr style="margin-top: 35px; margin-bottom: 35px">
    <h2 class="title">
        <?php echo
            wp_kses(
                sprintf(
                    _("Or sign in with your Boostfab account"),
                    esc_url($boostfab_public_url)
                ),
                'post'
            )
        ?>
    </h2>
    <form id="login-form" method="post" action="">
        <?echo wp_kses((wp_nonce_field('signin')), 'posts'); ?>
        <input type="hidden" name="api_token" value="">
        <table class="form-table" role="presentation">
            <tbody>
                <tr class="user-user-login-wrap">
                    <th><label for="user_login"><?php echo esc_html(_("Email")) ?></label></th>
                    <td><input type="text" name="email" id="email" value="<?php echo esc_attr(wp_get_current_user()->user_email) ?>"></td>
                </tr>

                <tr class="user-first-name-wrap">
                    <th><label for="first_name"><?php echo esc_html(_("Password")) ?></label></th>
                    <td><input type="password" name="password" id="password" value=""></td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <button type="button" name="submit" id="submit-button" class="button button-primary"><?php echo esc_html(_("Sign in")) ?></button>
            <button type="submit" name="submit" id="real-submit-button" style="display: none"></button>
        </p>
    </form>
    <style>
        /* Nice loading animation placeholder in which background color softly change from two different gray tonalities */
        .iframe-loading {
            background: #f5f5f5;
            background: linear-gradient(90deg, #f5f5f5 0%, #e8e8e8 50%, #f5f5f5 100%);
            background-size: 400% 400%;
            animation: gradient 1.5s ease infinite;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
    </style>
    <script>
        jQuery('#submit-button').on("click", function (event) {
            event.stopPropagation();
            event.preventDefault();
            jQuery.post("<?php echo esc_js($boostfab_api_url . '/auth') ?>", {
                email: jQuery('#email').val(),
                password: jQuery('#password').val()
            }).then((response) => {
                jQuery('#login-form input[name="api_token"]').val(response.accessToken);
                jQuery('#real-submit-button').click();
            }).fail(() => {
                alert("Wrong credentials. Keep in mind that you need an account in Boostfab.com to use this plugin.");
            });
        });
        window.addEventListener('message', function (e) {
            const data = e.data;
            const decoded = JSON.parse(data);
            if (decoded.message === 'resized') {
                document.getElementById(`boostfab-signup-iframe`).style.height = decoded.size.height + 'px';
                document.getElementById(`boostfab-signup-iframe`).classList.remove('iframe-loading');
            }
        });
    </script>
    <?php
}

function boostfab_print_admin_panel_signup() {
    ?>

    <h2 class="title"><?php echo esc_html(_("Create an account")) ?></h2>
    <form id="signup-form" method="post" action="">
        <input type="hidden" name="api_token" value="">
        <table class="form-table" role="presentation">
            <tbody>
                <tr class="user-user-login-wrap">
                    <th><label for="user_login"><?php echo esc_html(_("Email")) ?></label></th>
                    <td><input type="text" name="email" id="email" value="<?php echo esc_attr(wp_get_current_user()->user_email) ?>" class="regular-text"></td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <button type="button" name="submit" id="signup-submit-button" class="button button-primary"><?php echo esc_html(_("Sign up")) ?></button>
            <button type="submit" name="submit" id="signup-real-submit-button" style="display: none"></button>
        </p>
    </form>
    <?php
}

function boostfab_handle_logout() {
    if (isset($_GET['logout'])) {
        check_admin_referer('logout');
        update_option("boostfab_api_token", '');
        update_option("boostfab_organization_slug", '');
        wp_redirect(get_admin_url(null, 'admin.php?page=boostfab-panel'));
        exit;
    }
}

function boostfab_update_settings() {
    $can_edit_settings = current_user_can('edit_posts') || current_user_can('edit_pages');
    if (!$can_edit_settings) {
        http_response_code(400);
        wp_send_json([
            "message" => _("You are not allowed to update the settings.")
        ]);
        die();
    }
    if (isset($_POST['color'])) {
        check_ajax_referer('update-settings');
        if ($_POST['color'] == 'null') {
            delete_option("boostfab_primary_color");
        }
        else {
            $sanitized_color = sanitize_hex_color($_POST['color']);
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $sanitized_color)) {
                update_option("boostfab_primary_color", substr($sanitized_color, 1));
            }
            else {
                http_response_code(400);
                wp_send_json([
                        "message" => _("The color does not seem to be valid.")
                ]);
                die();
            }
        }
    }
    if (isset($_POST['cookie_policy'])) {
        check_ajax_referer('update-settings');
        if (in_array($_POST['cookie_policy'], ['no_consent_required', 'my_site', 'boostfab_widget'])) {
            update_option("boostfab_cookie_policy", $_POST['cookie_policy']);
        }
        else {
            http_response_code(400);
            wp_send_json([
                    "message" => _("The cookie policy does not seem to be valid.")
            ]);
            die();
        }
    }

	wp_die();
}

function boostfab_create_sample_page() {
    $post_name = _("Get a quotation!");
    $page_id = wp_insert_post(
        array(
            'comment_status' => 'close',
            'ping_status'    => 'close',
            'post_author'    => get_current_user_id(),
            'post_title'     => $post_name,
            'post_name'      => strtolower(str_replace(' ', '-', trim($post_name))),
            'post_status'    => 'publish',
            'post_content'   => "<div class='wp-block-columns is-layout-flex wp-container-7'><div class='wp-block-column is-layout-flow'>[" . BOOSTFAB_SHORTCODE . "]</div></div>",
            'post_type'      => 'page',
        )
    );

    if (!get_option('boostfab_sample_quotation_page_created')) {
        add_option('boostfab_sample_quotation_page_created', '1');
    }

    http_response_code(201);
    wp_send_json([
        "url" => get_permalink($page_id)
    ]);

    die();
}
