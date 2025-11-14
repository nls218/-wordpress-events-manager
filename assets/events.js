jQuery(document).ready(function($) {
    $('.events-list-wrapper').on('click', '.load-more-events', function() {
        var button = $(this);
        var page = parseInt(button.data('page')) + 1;
        var maxPages = parseInt(button.data('max-pages'));

        $.ajax({
            url: eventsManager.ajaxurl,
            type: 'post',
            data: {
                action: 'load_more_events',
                page: page,
                nonce: eventsManager.nonce
            },
            beforeSend: function() {
                button.text('Загрузка...');
            },
            success: function(response) {
                if (response.success) {
                    button.data('page', page);
                    $('.events-list').append(response.data.html);

                    if (page >= maxPages) {
                        button.remove();
                    } else {
                        button.text('Показать больше');
                    }
                }
            }
        });
    });
});