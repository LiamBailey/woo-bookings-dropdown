<?php
/*
Plugin Name: Woocommerce Bookings Dropdown
Description: Swaps the date picker for a dropdown of dates
Version: 1.0.0
Author: Webby Scots
Author URI: http://webbyscots.com/
*/
add_action('wp_ajax_wswp_refresh_dates','wswp_refresh_dates');
add_action('wp_ajax_nopriv_wswp_refresh_dates','wswp_refresh_dates');

function wswp_refresh_dates() {
    check_ajax_referer('woo-bookings-dropdown-refreshing-dates','security');
    $product = wc_get_product($_REQUEST['product_id']);
    $rules = $product->get_availability_rules($_REQUEST['resource_id']);
    $max = $product->get_max_date();
    $now = strtotime( 'midnight', current_time( 'timestamp' ) );
    $max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
    $dates = wswp_build_options($rules,$max_date);
    if (!empty($dates)) {
        $response = array(
            'success' => true,
            'dates' => $dates
        );
        wp_send_json($response);
    }
}

add_action('wp_enqueue_scripts','wswp_enqueue_script');

function wswp_enqueue_script() {
    wp_enqueue_script('woo-bookings-dropdown',plugins_url('js/woo-bookings-dropdown.js',__FILE__),array('jquery'));
    wp_localize_script('woo-bookings-dropdown','WooBookingsDropdown',array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'secure' => wp_create_nonce('woo-bookings-dropdown-refreshing-dates')
    ));
}

$wswp_dates_built = false;
add_filter('booking_form_fields','wswp_booking_form_fields');

function wswp_booking_form_fields($fields) {

    global $wswp_dates_built;
    $i = 0;
    $selected_resource = 0;
    $reset_options = false;
    foreach($fields as $field) {
        $new_fields[$i] = $field;
        if ($field['type'] == "select") {
            $selected_resource = reset(array_keys($field['options']));
            if ($reset_options !== false) {
                $new_fields[$reset_options]['options'] = wswp_build_options($field['availability_rules'][$selected_resource]);
            }
        }
        if ($field['type'] == "date-picker" && $wswp_dates_built === false)
        {
            $s = $i;
            $new_fields[$s]['class'] = array('picker-hidden');
            $i++;
            $new_fields[$i] = $field;
            $new_fields[$i]['type'] = "select";
            if ($selected_resource == 0) {
                $reset_options = $i;
            }
            $max = $field['max_date'];
            $now = strtotime( 'midnight', current_time( 'timestamp' ) );
            $max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
            $new_fields[$i]['options'] = wswp_build_options($field['availability_rules'][$selected_resource],$max_date);
            $new_fields[$i]['class'] = array('picker-chooser');
        }
        $i++;
    }
    return $new_fields;
}

function wswp_build_options($rules,$max_date) {
    global $wswp_dates_built;
    $dates = array();
    foreach($rules as $dateset) {
        if ($dateset[0] == "custom") {
            $year = reset(array_keys($dateset[1]));
            $month = reset(array_keys($dateset[1][$year]));
            $day = reset(array_keys($dateset[1][$year][$month]));
            $dtime = strtotime($year."-".$month."-".$day);
            if ($dtime <= $max_date-1) {
                $dates[$dtime] = date("m/d/Y",$dtime);
            }
        }

    }
    ksort($dates);
    foreach($dates as $key => $date) {
        $dates[date("Y-m-d",$key)] = $date;
        unset($dates[$key]);
    }
    $wswp_dates_built = true;
    return array('' => 'Please Select') + $dates;
}

add_action('wp_footer','wswp_css_js');

function wswp_css_js() {
    //adding in footer as not enough to justify new stylesheet and js file
    ?><style type="text/css">
        .picker-hidden .picker,.picker-hidden legend {
            display:none;
        }
        </style>
        <script type='text/javascript'>
            jQuery(function($) {
                $(".picker-chooser").insertBefore('.wc-bookings-date-picker-date-fields');
                $("select#wc_bookings_field_start_date").on('change', function() {
				var selectedDate = $(this).val()
				var selectedDateBreakdown = selectedDate.split("-");

				$( "input[name*='wc_bookings_field_start_date_year']" ).val( selectedDateBreakdown[0] );
				$( "input[name*='wc_bookings_field_start_date_month']" ).val( selectedDateBreakdown[1] );
				$( "input[name*='wc_bookings_field_start_date_day']" ).val( selectedDateBreakdown[2] );
			});
            });

            </script>
            <?php
}