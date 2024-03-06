// script.js
jQuery(document).ready(function ($) {
    $('.helpful-button').on('click', function () {
        var vote = $(this).data('vote');
        var postId = $(this).data('id');
        var userIP = $('input[name="userip"]').val();
        $.ajax({
            type: 'POST',
            url: helpful_article_ajax.ajaxurl,
            data: {
                action: 'record_vote',
                vote: vote,
                post_id: postId,
                user_ip: userIP
            },
            success: function (response) {
                // $('.yes-votes').text(response.yes_votes);
                // $('.no-votes').text(response.no_votes);
                if(vote=='yes'){
                    $('.yes-votes').removeClass('hide');
                }
                if(vote=='no'){
                    $('.no-votes').removeClass('hide');
                }
            }
        });
    });
});
