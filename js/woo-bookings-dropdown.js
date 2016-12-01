jQuery(function($) {
    $("#wc_bookings_field_resource").change(function() {
        var product_id = $("input[name='add-to-cart']").val();
        $.post(WooBookingsDropdown.ajax_url,{product_id: product_id, action: 'wswp_refresh_dates',security: WooBookingsDropdown.secure,resource_id:$(this).val()},function(response) {
            if (response.success) {
                $("#wc_bookings_field_start_date").html('');
                $.each(response.dates,function(key,value) {
                    $("#wc_bookings_field_start_date").append("<option value='"+key+"'>"+value+"</option>");
                });
            }
        })
    })
})

