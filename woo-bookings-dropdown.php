<?php
/*
Plugin Name: Woocommerce Bookings Dropdown
Description: Swaps the date picker for a dropdown of dates
Version: 1.0.8
Author: Webby Scots
Author URI: http://webbyscots.com/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('wp_ajax_wswp_refresh_dates', 'wswp_refresh_dates');
add_action('wp_ajax_nopriv_wswp_refresh_dates', 'wswp_refresh_dates');

function wswp_refresh_dates() {
    check_ajax_referer('woo-bookings-dropdown-refreshing-dates', 'security');
    $product = wc_get_product($_REQUEST['product_id']);
    $booking_form = new WC_Booking_Form($product);
    switch ( $product->get_duration_unit() ) {
        case 'month' :
            include_once( 'class-wc-booking-form-month-picker.php' );
            $picker = new WC_Booking_Form_Month_Picker( $this );
            break;
        case 'day' :
        case 'night' :
            include_once( 'class-wc-booking-form-date-picker.php' );
            $picker = new WC_Booking_Form_Date_Picker( $this );
            break;
        case 'minute' :
        case 'hour' :
            include_once( 'class-wc-booking-form-datetime-picker.php' );
            $picker = new WC_Booking_Form_Datetime_Picker( $this );
            break;
        default :
            break;
    }
    $field = $picker->get_args();
    $rules = $product->get_availability_rules($_REQUEST['resource_id']);
    $max = $product->get_max_date();
    $now = strtotime( 'midnight', current_time( 'timestamp' ) );
    $max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
    $dates = wswp_build_options($rules, $field, $max_date);
    if (!empty($dates)) {
        $response = array(
            'success' => true,
            'dates' => $dates
        );
        wp_send_json($response);
    }
}

add_action('wp_enqueue_scripts', 'wswp_enqueue_script');

function wswp_enqueue_script() {
    wp_enqueue_script('woo-bookings-dropdown',plugins_url('js/woo-bookings-dropdown.js',__FILE__),array('jquery'));
    wp_localize_script('woo-bookings-dropdown', 'WooBookingsDropdown', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'secure' => wp_create_nonce('woo-bookings-dropdown-refreshing-dates')
    ));
}

$wswp_dates_built = false;
add_filter('booking_form_fields', 'wswp_booking_form_fields');

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
                $new_fields[$reset_options]['options'] = wswp_build_options($field['availability_rules'][$selected_resource], $field);
            }
        }
        if ($field['type'] == "date-picker" && $wswp_dates_built === false)
        {
            $max = $field['max_date'];
            $now = strtotime( 'midnight', current_time( 'timestamp' ) );
            $max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
            $avail_dates = wswp_build_options($field['availability_rules'][$selected_resource], $field, $max_date);
            if (!$avail_dates)
                return $fields;
            $s = $i;
            $new_fields[$s]['class'] = array('picker-hidden');
            $i++;
            $new_fields[$i] = $field;
            $new_fields[$i]['type'] = "select";
            if ($selected_resource == 0) {
                $reset_options = $i;
            }

            $new_fields[$i]['options'] = $avail_dates;
            $new_fields[$i]['class'] = array('picker-chooser');
        }
        $i++;
    }
    return $new_fields;
}

function wswp_build_options($rules, $field, $max_date) {
    global $wswp_dates_built;
    $dates = array();
    $non_date_ranges = false;
    foreach($rules as $dateset) {
        if (is_int(array_keys($dateset)[0]) && $dateset[0] == "custom") {
             $years = array_keys($dateset[1]);
             $legacy = true;
        }
        else if ($dateset['type'] == "custom") {
            $years = array_keys($dateset['range']);
            $legacy = false;
        }
        else {
            //Use default calendar if non-date range type ranges found. Exclude global ranges but only if date-range ranges found.
            $non_date_ranges = (empty($dates) || $dateset['level'] != "global");
        }
        foreach($years as $year) {
            $months = ($legacy) ? array_keys($dateset[1][$year]) : array_keys($dateset['range'][$year]);
            foreach($months as $month) {
                $days = ($legacy) ? array_keys($dateset[1][$year][$month]) : array_keys($dateset['range'][$year][$month]);
                foreach($days as $day) {

                    $dtime = strtotime($year."-".$month."-".$day);
                    if ($dtime < strtotime("now"))
                        continue;
                    $js_date = date( 'Y-n-j', $dtime );
                    if (isset($field['fully_booked_days'][$js_date]))
                        continue;
                    if ($dtime <= $max_date-1) {
                        $dates[$dtime] = date_i18n("F jS, Y", $dtime);
                    }
                }
            }

        }
    }
    ksort($dates);
    foreach($dates as $key => $date) {
        $dates[date("Y-m-d", $key)] = $date;
        unset($dates[$key]);
    }
    $wswp_dates_built = true;
    var_dump($non_date_ranges);var_dump(empty($dates));
    return $non_date_ranges || empty($dates) ? false : array('' => __('Please Select','woo-bookings-dropdown')) + $dates;
}

add_action('wp_footer', 'wswp_css_js');

function wswp_css_js() {
    //adding in footer as not enough to justify new stylesheet and js file
    ?><style type="text/css">
        .picker-hidden .picker, .picker-hidden legend {
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
