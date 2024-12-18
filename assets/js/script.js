jQuery(document).ready(function ($) {
    // Add custom data to Heartbeat API requests
    $(document).on('heartbeat-send', function (event, data) {
        data.spb_check_new_orders = true; // This tells the server to run your PHP function
    });

    // Listen for Heartbeat ticks
    $(document).on('heartbeat-tick', function (event, data) {
        let delay = parseInt(spb_data.popup_delay) * 1000;
        if (data.hasOwnProperty('new_orders')) {
            // Iterate over the new orders and display popups
            data.new_orders.forEach(function (order, index) {
                console.log(order, index);
                setTimeout(() => {
                    let positionStyles = '';
                    switch (spb_data.position) {
                        case 'bottom-right':
                            positionStyles = 'bottom: 20px; right: 20px;';
                            break;
                        case 'bottom-left':
                            positionStyles = 'bottom: 20px; left: 20px;';
                            break;
                        case 'top-right':
                            positionStyles = 'top: 20px; right: 20px;';
                            break;
                        case 'top-left':
                            positionStyles = 'top: 20px; left: 20px;';
                            break;
                    }
    
                    let popupHtml = `
                        <div class="spb-popup" style="position: fixed; ${positionStyles} background: ${spb_data.bg_color}; color: #fff; padding: 10px; border-radius: 5px; z-index: 9999;">
                            <p>${order.name} just purchased ${order.products}</p>
                        </div>
                    `;
                    $('body').append(popupHtml);
    
                    trackImpression(order);
    
                    setTimeout(() => {
                        $('.spb-popup').fadeOut(500, function () {
                            $(this).remove();
                        });
                    }, 4000);
                }, index * delay);
            });
        }
    });

    $(document).on('click', '.spb-popup', function () {
        let product = $(this).find('p').text();
        $.post(spb_data.ajax_url, {
            action: 'spb_track_click',
            popup_data: product,
        });
    });

    function trackImpression(popup) {
        $.post(spb_data.ajax_url, {
            action: 'spb_track_impression',
            popup_data: popup.products,
        });
    }
});
