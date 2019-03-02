<?php
/*
Plugin Name: Woocommerce Bookings Dropdown
Description: Swaps the date picker for a dropdown of dates
Version: 1.2.1
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
            include_once( WC_BOOKINGS_ABSPATH . 'includes/booking-form/class-wc-booking-form-month-picker.php' );
            $picker = new WC_Booking_Form_Month_Picker( $booking_form );
            break;
        case 'day' :
        case 'night' :
            include_once( WC_BOOKINGS_ABSPATH . 'includes/booking-form/class-wc-booking-form-date-picker.php' );
            $picker = new WC_Booking_Form_Date_Picker( $booking_form );
            break;
        case 'minute' :
        case 'hour' :
            include_once( WC_BOOKINGS_ABSPATH . 'includes/booking-form/class-wc-booking-form-datetime-picker.php' );
            $picker = new WC_Booking_Form_Datetime_Picker( $booking_form );
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
    if (is_admin()) {
        return;
    }
    global $wswp_dates_built, $product;
    $i = 0;
    $selected_resource = 0;
    $reset_options = false;
    $new_bookings = version_compare(WC_BOOKINGS_VERSION, '1.12.0', '>');
    foreach($fields as $field) {
        $new_fields[$i] = $field;
        if ($field['type'] == "select") {
            if (!isset($field['availability_rules'])) {
                $field['availability_rules'] = $product->get_availability_rules();
            }
            $__keys = array_keys($field['options']);
            $selected_resource = reset($__keys);
            if ($reset_options !== false) {
                $availability_rules = $selected_resource < 1 || !isset($field['availability_rules'][$selected_resource]) ? $field['availability_rules'] : $field['availability_rules'][$selected_resource];
                $new_fields[$reset_options]['options'] = $new_bookings ? wswp_build_new_options($availability_rules, $field) : wswp_build_options($field['availability_rules'][$selected_resource], $field);
            }
        }
        if ($field['type'] == "date-picker" && $wswp_dates_built === false)
        {
            if (!isset($field['availability_rules'])) {
                $field['availability_rules'] = $product->get_availability_rules();
            }
            $max = $field['max_date'];
            $now = strtotime( 'midnight', current_time( 'timestamp' ) );
            $max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
            $availability_rules = $selected_resource < 1 || !isset($field['availability_rules'][$selected_resource]) ? $field['availability_rules'] : $field['availability_rules'][$selected_resource];

            $avail_dates = $new_bookings ? wswp_build_new_options($availability_rules, $field, $max_date) : wswp_build_options($field['availability_rules'][$selected_resource], $field, $max_date);
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

function wswp_build_new_options($rules, $field, $max_date) {
    global $wswp_dates_built;
    $dates = array();
    $years = false;
    $non_date_ranges = false;
    foreach($rules as $key => $dateset) {        
        if ($dateset['type'] == "custom") {
             $years = $dateset['range'];
             $legacy = true;
        } else if (!$years && $key == 'range') {
            $years = $dateset;
            $legacy = false;
        }
        if (empty($years)) {
            continue;
        }
        $days = array();
        foreach($years as $year => $months) {
            foreach($months as $month => $days) {
                foreach($months as $month => $days) {
                    $day = reset(array_keys($days));
                    $dtime = strtotime($year."-".$month."-".$day);
                        
                    $js_date = date( 'Y-n-j', $dtime );
                    if ($dtime > time() && $dtime <= $max_date-1 && !isset($field['fully_booked_days'][$js_date])) {
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
    return empty($dates) ? false : array('' => __('Please Select','woo-bookings-dropdown')) + $dates;
}

function wswp_build_options($rules, $field, $max_date) {
    global $wswp_dates_built;
    $dates = array();
    $non_date_ranges = false;

    foreach($rules as $dateset) {
        if (isset($dateset[0]) && $dateset[0] == "custom") {
             $years = $dateset[1];
             $legacy = true;
        }
        else if ($dateset['type'] == "custom") {
            $years = $dateset['range'];
            $legacy = false;
        }
        if (empty($years)) {
            continue;
        }
        $days = array();
        foreach($years as $year => $months) {
            foreach($months as $month => $days) {
                foreach($days as $day => $avail) {
                    $dtime = strtotime($year."-".$month."-".$day);
                    $js_date = date( 'Y-n-j', $dtime );
                    if ((bool)$avail == true && $dtime > time() && $dtime <= $max_date-1 && !isset($field['fully_booked_days'][$js_date])) {
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
    return $non_date_ranges || empty($dates) ? false : array('' => __('Please Select','woo-bookings-dropdown')) + $dates;
}

add_action('wp_footer', 'wswp_css_js');

function wswp_css_js() {
    //adding in footer as not enough to justify new stylesheet and js file
    ?>
    
    <style type="text/css">
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