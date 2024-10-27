<style>
    .card {
        max-width: 600px;
    }
</style>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($boostfab_name . ' ' . _("Panel")) ?></h1>
    <a
        href="<?php echo esc_url(wp_nonce_url(get_admin_url(null, 'admin.php?page=boostfab-panel&logout'), 'logout')) ?>"
        class="hide-if-no-js page-title-action" onclick="confirmDisconnection()"
    >
        <?php echo esc_html(_("Disconnect")) ?>
    </a>
    <hr class="wp-header-end">
    <div class="card">
        <h2 class="title"><?php echo esc_html(_("Welcome!")) ?></h2>
        <p>
            <?php echo
            wp_kses(
                sprintf(
                    _("You are now connected to %1s. You can use the shortcode <code>[%2s]</code> to embed %3s in your <a href=\"%4s\">pages</a> or <a href=\"%5s\">posts</a>."),
                    esc_html($boostfab_name),
                    esc_html(BOOSTFAB_SHORTCODE),
                    esc_html($boostfab_name),
                    get_admin_url(null, 'edit.php?post_type=page'),
                    get_admin_url(null, 'edit.php')
                ),
                'post'
            )
            ?>
        </p>
        <button class="button <?php echo esc_attr(get_option("boostfab_sample_quotation_page_created") == 1 ? "button-link" : "button-primary") ?>" onclick="createQuotationPage()"><?php esc_html_e("Create a quotation page!") ?></button>
    </div>
    <div class="card">
        <h2 class="title"><?php echo esc_html(_("Widget settings")) ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th><label for="color"><?php echo esc_html(_("Primary color")) ?></label></th>
                <td>
                    <input
                        type="color"
                        name="color"
                        id="color"
                        data-is-null="<?php echo esc_attr(empty(get_option("boostfab_primary_color")) ? "1" : "0") ?>"
                        value="<?php echo esc_attr(get_option("boostfab_primary_color") ? "#" . get_option("boostfab_primary_color") : "")  ?>">
                    <a href="#" onclick="resetColor()"><?php echo esc_attr(_("Reset")) ?></a>
                </td>
            </tr>
            <tr>
                <th><label for="color"><?php echo esc_html(_("Cookies consent")) ?></label></th>
                <td>
                    <div>
                        <a href="#TB_inline?width=600&height=550&inlineId=cookie-consent-modal" class="thickbox">
                            <?php echo esc_html(_("What is the best option?")) ?>
                        </a>
                    </div>
                    <input type="radio" name="cookie_policy" id="ask-twice" value="boostfab_widget" <?php checked(get_option("boostfab_cookie_policy") === "boostfab_widget"); ?>>
                    <label for="ask-twice"><?php echo esc_html(_("Ask for cookie consent in Boostfab's widget.")) ?></label><br>

                    <?php $is_my_site_option_enabled = is_plugin_active('complianz-gdpr/complianz-gpdr.php'); ?>
                    <input type="radio" name="cookie_policy" id="ask-once" <?php echo !$is_my_site_option_enabled ? 'disabled' : '' ?> value="my_site" <?php checked(get_option("boostfab_cookie_policy") === "my_site"); ?>>
                    <label for="ask-once"><?php echo esc_html(_("Ask for cookie consent only in my site.")) ?></label><br>
                    <?php if (!$is_my_site_option_enabled) { ?>
                        <p style="margin: 0; padding: 0; margin-bottom: 5px; font-size: 0.8em">
                            <?php echo esc_html(_("This option requires the Complianz plugin.")) ?>
                        </p>
                    <?php } ?>

                    <input type="radio" name="cookie_policy" id="no-consent" value="no_consent_required" <?php checked(get_option("boostfab_cookie_policy") === "no_consent_required"); ?>>
                    <label for="no-consent"><?php echo esc_html(_("Do not ask for cookie consent. (Not GDPR compliant)")) ?></label>
                </td>
            </tr>
            </tbody>
        </table>
        <p class="submit">
            <button type="button" name="submit" id="submit-button" class="button button-primary"><?php echo esc_attr(_("Save")) ?></button>
            <!--<a style="margin-left: 5px" href="" onclick="openModal()" class="thickbox">Preview</a>-->
        </p>
    </div>
    <div class="card">
        <h2 class="title"><?php echo esc_attr(_("Need help?")) ?></h2>
        <p>
            <?php echo wp_kses(
                sprintf(
                    _("Feel free to <a href=\"%1s\" target=\"_blank\">send us a message</a>. We will answer you as soon as possible."),
                    "https://tawk.to/quotify"
                ),
                'post'
            )
            ?>
        </p>
    </div>
</div>
<!-- MODAL COOKIE CONSENT HELP -->
<div id="cookie-consent-modal" style="display:none;">
    <p>
        Boostfab uses non-essential cookies to analyze user behavior and enhance their experience. If your website, and by extension Boostfab, doesn't need to comply with GDPR regulations, you can select "Do not ask for cookie consent."
    </p>
    <h1>
        What if GDPR Compliance Is Necessary?
    </h1>
    <p>
        If GDPR compliance is a requirement for your website, we offer two options:
    </p>
    <h2>
        1. Ask for Cookie Consent in the Widget
    </h2>
    <p>
        By selecting this option, the Boostfab widget will display a cookie consent popup. While this approach is GDPR-compliant, it may potentially disrupt the user experience you intend to provide. For instance, if you already display a consent banner on your site, users will need to provide consent twice: once on your website and once within our embedded widget.
    </p>
    <p>
        This option is simpler for you but may impact the user experience negatively.
    </p>
    <h2>
        2. Ask for Cookie Consent in my Site
    </h2>
    <p>
        This option is suitable only if you are already displaying a cookie consent banner on your website. By choosing this option, you commit to incorporating Boostfab's cookie policy into your own cookie policy. Consequently, when a user accepts your cookie policy, they will automatically accept Boostfab's cookie policy as well, eliminating the need to request consent again within the Boostfab widget.
    </p>
    <p>
        Please ensure that your combined cookie policy adheres to all legal requirements and clearly communicates how cookies are used on your site, including any third-party services like Boostfab.
    </p>
    <p>
        This option currently requires using Complianz as your cookie consent plugin.
    </p>
</div>
<script>
    function confirmDisconnection() {
        if (!confirm("<?php echo esc_html(addslashes(_("Are you sure you want to disconnect? Your embeds will stop working."))) ?>")) {
            event.preventDefault();
        }
    }

    jQuery("[type=color]").on("change", function () {
        jQuery(this).attr("data-is-null", "0");
    })

    jQuery("[name=submit]").on("click", function () {
        var data = {
            'action': 'update_settings',
            'color': jQuery("#color").attr("data-is-null") === "1" ? undefined : jQuery("#color").val(),
            'cookie_policy': jQuery("[name=cookie_policy]:checked").val(),
            '_wpnonce': '<?echo esc_js(wp_create_nonce('update-settings')); ?>'
        };

        jQuery.post(ajaxurl, data, function(response) {
            alert('Saved!');
        }).fail(function(response) {
            alert('Error: ' + response.responseJSON.message);
        });
    })

    function resetColor() {
        jQuery.post(ajaxurl, {
            'action': 'update_settings',
            'color': 'null',
            '_wpnonce': '<?echo esc_js(wp_create_nonce('update-settings')); ?>'
        }, function(response) {
            jQuery('#color').val('');
            alert('Saved!');
        });
    }

    function openModal() {
        jQuery("a").attr("href", "#TB_inline?&width=600&height=550&inlineId=my-content-id").click();
    }

    function createQuotationPage() {
        jQuery.post(ajaxurl, {
            'action': 'create_sample_page',
        }, function(response) {
            jQuery('#color').val('');
            alert('Saved!');
            window.open(response.url, '_blank');
        });
    }
</script>
