<?php
/*
Plugin Name: W3 Total Cache Clearer
Description: Adds a dashboard widget and adminbar link to clear the cache of pages by URL.
 * Version: 1.0
 * Author: Robert Went
 * Author URI: https://robertwent.com
 * License: GPL3
*/


if ( ! function_exists( 'w3tc_flush_url' ) || ! defined( 'ABSPATH' ) ) {
	return false;
}

// Hook into the admin_bar_menu action
add_action( 'admin_bar_menu', 'rw_add_clear_cache_link', 9999 );

function rw_add_clear_cache_link( $wp_admin_bar ) {
	// Check if user is logged in and has editor privileges
	if ( ! is_admin() && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		// Add a link to the admin toolbar
		$args = array(
			'id'    => 'clear_cache',
			'title' => 'Clear this page\'s cache',
			'href'  => 'javascript:void(0)',
			'meta'  => array(
				'class' => 'rw-w3-cache-clearer'
			)
		);
		$wp_admin_bar->add_node( $args );
	}
}

// Add the AJAX action for clearing cache
add_action( 'wp_ajax_rw_w3_cache_clearer', 'rw_w3_cache_clearer' );

function rw_w3_cache_clearer() {
	// Get the current page URL from the wp_rw_w3_clear_cache_script AJAX request
	$url_to_flush = sanitize_url( $_GET['url'] );
	// Clear the cache of the current page
	w3tc_flush_url( $url_to_flush );
	// Update the admin bar link text
	echo 'Cleared';
	die();
}

function wp_rw_w3_clear_cache_script() {
	if ( is_user_logged_in() && ! is_admin() ) {
		ob_start(); ?>
        <script>
            function rw_w3_cache_clearer() {
                const rw_w3_cache_clearer = document.querySelector('.rw-w3-cache-clearer a.ab-item');
                if (rw_w3_cache_clearer) {

                    //add an event listener to the element
                    rw_w3_cache_clearer.addEventListener('click', function (e) {
                        e.preventDefault();
                        //send the request
                        fetch("<?php echo admin_url( 'admin-ajax.php' ); ?>?action=rw_w3_cache_clearer&url=" + encodeURIComponent(window.location.href), {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            }
                        })
                            //get the response
                            .then(response => response.text())
                            //display the response
                            .then(text => rw_w3_cache_clearer.textContent = text);
                        // Remove focus from the link
                        rw_w3_cache_clearer.blur();
                        // after 2.5 seconds, change the text back to "Clear this page's cache"
                        setTimeout(() => {
                                rw_w3_cache_clearer.textContent = 'Clear this page\'s cache';
                            }
                            , 2500);
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', rw_w3_cache_clearer, false);
        </script>
		<?php
		echo ob_get_clean();
	}
}

// Add wp_rw_w3_clear_cache_script to the frontend footer`
add_action( 'wp_footer', 'wp_rw_w3_clear_cache_script' );

// Create a dashboard widget with a form to clear the cache of any url
function rw_dashboard_cache_clear() {
	wp_add_dashboard_widget(
		'rw_dashboard_cache_clear', // Widget slug.
		'Clear Cache', // Title.
		'rw_dashboard_cache_clear_function' // Display function.
	);
}

// Display the dashboard widget
function rw_dashboard_cache_clear_function() {
	// Check if user is logged in
	if ( is_user_logged_in() ) {
		// Display the form
		echo '<form id="rwDashboardCacheClear" action="" method="post">';
		echo '<input type="text" class="widefat" name="rw_cache_url" placeholder="Enter the url of the page to clear" />';
		echo '<input type="submit" class="button button-primary" style="margin-top: 1rem" name="rw_cache_submit" value="Clear Cache" />';
		echo '</form>';
	}
}

// Hook into the 'wp_dashboard_setup' action to register the dashboard widget
add_action( 'wp_dashboard_setup', 'rw_dashboard_cache_clear' );

// Clear the cache of the url entered in the form
function rw_dashboard_clear_cache() {
	// Check if the w3tc_flush_url function exists
	if ( function_exists( 'w3tc_flush_url' ) ) {
		// Get the url from the form
		$url_to_flush = sanitize_url( $_POST['rw_cache_url'] );
		// Clear the cache of the url
		w3tc_flush_url( $url_to_flush );
		// Update the form text
		echo 'Cleared';
	} else {
		echo 'Function not found';
	}
}

// Hook into the 'admin_post_rw_clear_cache' action to clear the cache
add_action( 'admin_post_rw_dashboard_clear_cache', 'rw_dashboard_clear_cache' );

// Add js to the admin head to make the form work
function rw_dashboard_clear_cache_js() {
	// Don't do anything if not on the dashboard screen
	if ( get_current_screen()->id !== 'dashboard' ) {
		return false;
	}

	//Prevent default when form is submitted and send the url via ajax
	echo '<script>
		jQuery(document).ready(function($) {
			$("#rwDashboardCacheClear").submit(function(e) {
				e.preventDefault();
				var url = $("#rwDashboardCacheClear input[name=rw_cache_url]").val();
				$.ajax({
					type: "POST",
					url: "' . admin_url( 'admin-post.php' ) . '",
					data: {
						action: "rw_dashboard_clear_cache",
						rw_cache_url: url
					},
					success: function(data) {
                        //clear the form and add the message as the placeholder                                                
						$("#rwDashboardCacheClear input[name=rw_cache_url]").val("");
                        $("#rwDashboardCacheClear input[name=rw_cache_url]").attr("placeholder", data);
                        //wait 5 seconds and restore the original placeholder
                        setTimeout(function() {
							$("#rwDashboardCacheClear input[name=rw_cache_url]").attr("placeholder", "Enter the url of the page to clear");
						}, 5000);
					}
				});
			});
		});
		</script>';
}

add_action( 'admin_head', 'rw_dashboard_clear_cache_js'  );
