<?php

/**
 * Plugin Name: Helpful Article Voting
 * Description: A simple plugin to add a "Was this article helpful?" voting system using shortcodes.
 * Version: 1.0
 * Author: Your Name
 */

class HelpfulArticleVotingPlugin
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'helpful_votes';

        register_activation_hook(__FILE__, array($this, 'create_custom_table'));

        add_shortcode('helpful_article_voting', array($this, 'helpful_article_voting_shortcode'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_helpful_article_script'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_helpful_article_style')); // New line for style registration

        add_filter('the_content', array($this, 'append_helpful_article_shortcode_to_content'));

        add_action('wp_ajax_record_vote', array($this, 'record_vote'));
        add_action('wp_ajax_nopriv_record_vote', array($this, 'record_vote'));
        add_action('add_meta_boxes', array($this, 'add_voting_results_meta_box'));
    }

    public function create_custom_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            user_ip varchar(15) NOT NULL,
            vote varchar(3) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function helpful_article_voting_shortcode()
    {
        global $wpdb;
        $postId = get_the_ID();
        $userIP = $_SERVER['REMOTE_ADDR'];
        $existingVote = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT vote FROM $this->table_name WHERE post_id = %d AND user_ip = %s",
                $postId,
                $userIP
            )
        );

        ob_start();
?>
        <div class="helpful-article-voting">
            <div class="inner-boder">
            <div class="inner">
                <div>Was this article helpful?</div>
                <div>
                    <button class="helpful-button <?php echo ($existingVote == 'yes') ? 'active' : ''; ?>" data-vote="yes" data-id="<?php echo get_the_ID(); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>image/smile-icon.svg"> Yes</button>
                </div>
                <div>
                    <button class="helpful-button <?php echo ($existingVote == 'no') ? 'active' : ''; ?>" data-vote="no" data-id="<?php echo get_the_ID(); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>image/expressionless-face-emoji-icon.svg"> No</button>
                </div>
            </div>
            <input type="hidden" value="<?php echo $_SERVER['REMOTE_ADDR']; ?>" name="userip">

            <div class="voting-results">
                <?php 
                $yes = get_post_meta(get_the_ID(), 'helpful_article_yes_votes', true) ?: 0;
                $no = get_post_meta(get_the_ID(), 'helpful_article_no_votes', true) ?: 0;
                $total = $yes+ $no;
                $yes= round((100*$yes)/$total);
                $no= round((100*$no)/$total);       
                ?>
                <div class="yes-votes <?php echo ($existingVote != 'yes') ? 'hide' : ''; ?>  results-inner">Thank you for your feedback <button class="helpful-button-result active" data-vote="yes" data-id="<?php echo get_the_ID(); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>image/smile-icon.svg"> <?php echo $yes ?>%</button><button class="helpful-button-result " data-vote="yes" data-id="<?php echo get_the_ID(); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>image/expressionless-face-emoji-icon.svg"> <?php echo $no; ?>%</button>
                </div>
                <div class="no-votes <?php echo ($existingVote != 'no') ? 'hide' : ''; ?> results-inner">Thank you for your feedback <button class="helpful-button-result " data-vote="yes" data-id="<?php echo get_the_ID(); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>image/smile-icon.svg"> <?php echo $yes; ?>%</button><button class="helpful-button-result active" data-vote="yes" data-id="<?php echo get_the_ID(); ?>"><img src="<?php echo plugin_dir_url(__FILE__); ?>image/expressionless-face-emoji-icon.svg"> <?php echo $no; ?>%</button>
                </div>
            </div>
    </div>
            
        </div>
        </div>
    <?php
        return ob_get_clean();
    }

    public function enqueue_helpful_article_script()
    {
        wp_enqueue_script('helpful-article-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);
        wp_localize_script('helpful-article-script', 'helpful_article_ajax', array('ajaxurl' => admin_url('admin-ajax.php')));
    }

    public function enqueue_helpful_article_style()
    {
        wp_enqueue_style('helpful-article-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.0');
    }
    public function append_helpful_article_shortcode_to_content($content)
    {
        if (is_single()) {
            $content .= do_shortcode('[helpful_article_voting]');
        }
        return $content;
    }

    public function record_vote()
    {
        global $wpdb;
        $vote = $_POST['vote'];
        $postId = $_POST['post_id'];
        $userIP = $_POST['user_ip'];
        $yes_votes = get_post_meta($postId, 'helpful_article_yes_votes', true) ?: 0;
        $no_votes = get_post_meta($postId, 'helpful_article_no_votes', true) ?: 0;

        $existingVote = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT vote FROM $this->table_name WHERE post_id = %d AND user_ip = %s",
                $postId,
                $userIP
            )
        );

        if (!$existingVote) {
            // User has not voted yet
            $wpdb->insert(
                $this->table_name,
                array(
                    'post_id' => $postId,
                    'user_ip' => $userIP,
                    'vote'    => $vote,
                ),
                array('%d', '%s', '%s')
            );

            // Update the post meta for vote count
            $yes_votes = get_post_meta($postId, 'helpful_article_yes_votes', true) ?: 0;
            $no_votes = get_post_meta($postId, 'helpful_article_no_votes', true) ?: 0;

            if ($vote === 'yes') {
                $yes_votes++;
                update_post_meta($postId, 'helpful_article_yes_votes', $yes_votes);
            } elseif ($vote === 'no') {
                $no_votes++;
                update_post_meta($postId, 'helpful_article_no_votes', $no_votes);
            }

            wp_send_json(array('yes_votes' => $yes_votes, 'no_votes' => $no_votes));
        } else {
            // User has already voted
            wp_send_json(array('error' => 'User has already voted.'));
        }
    }
    public function add_voting_results_meta_box()
    {
        add_meta_box(
            'helpful-article-voting-results',
            'Voting Results',
            array($this, 'display_voting_results_meta_box'),
            'post',
            'normal',
            'high'
        );
    }

    public function display_voting_results_meta_box($post)
    {
        $yes_votes = get_post_meta($post->ID, 'helpful_article_yes_votes', true) ?: 0;
        $no_votes = get_post_meta($post->ID, 'helpful_article_no_votes', true) ?: 0;
    ?>
        <div class="voting-results-meta-box">
            <p><strong>Yes Votes:</strong> <?php echo $yes_votes; ?></p>
            <p><strong>No Votes:</strong> <?php echo $no_votes; ?></p>
        </div>
<?php
    }
}

// Instantiate the plugin class
new HelpfulArticleVotingPlugin();
