<?php
/*
  Plugin Name: Infinite Timeline
  Plugin URI: http://wordpress.org/plugins/infinite-timeline/
  Description: The shortcode displays posts on vertical timeline.
  Author: sysbird
  Author URI: https://profiles.wordpress.org/sysbird/
  Version: 1.0
  License: GPLv2 or later
  Text Domain: infinite-timeline
  Domain Path: /languages
 */

//////////////////////////////////////////////////////
// Wordpress 3.0+
global $wp_version;
if (version_compare($wp_version, "3.8", "<")) {
    return false;
}

//////////////////////////////////////////////////////
// Start the plugin
class InfiniteTimeline {

    //////////////////////////////////////////
    // construct
    function __construct() {
        load_plugin_textdomain('infinite-timeline', false, plugin_basename(dirname(__FILE__)) . '/languages');
        add_shortcode('infinite-timeline', array(&$this, 'shortcode'));
        add_action('wp_enqueue_scripts', array(&$this, 'add_script'));
        add_action('wp_print_styles', array(&$this, 'add_style'));

        register_activation_hook(__FILE__, array(&$this, 'rewrite_flush'));
        add_action('init', array(&$this, 'create_post_type'));

        add_action('add_meta_boxes_timeline_post', array(&$this, 'add_meta_boxes'));
        add_action('save_post', array(&$this, 'update'), 10, 2);
    }

    function rewrite_flush() {
        // First, we "add" the custom post type via the above written function.
        // Note: "add" is written with quotes, as CPTs don't get added to the DB,
        // They are only referenced in the post_type column with a post entry, 
        // when you add a post of this CPT.
        InfiniteTimeline::create_post_type();

        // ATTENTION: This is *only* done during plugin activation hook in this example!
        // You should *NEVER EVER* do this on every page load!!
        flush_rewrite_rules();
    }

    function create_post_type() {
        register_post_type('timeline_post', array(
            'labels' => array(
                'name' => __('Timeline Post'),
                'singular_name' => __('Timeline Post')
            ),
            'menu_icon' => 'dashicons-feedback',
            'public' => true,
            'has_archive' => true,
            'menu_position' => 5,
            'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'post-formats')
                )
        );
    }

    //////////////////////////////////////////
    // add subtitles support
    function add_meta_boxes() {
        add_meta_box('timelineSubtitle', 'Subtitle', array(&$this, 'box'), 'timeline_post', 'side');
    }

    function box($post) {
        wp_nonce_field('a_save', 'n_tsub');
        ?>
        <input class="widefat" type="text" id="timeline-subtitle" name="timeline_subtitle" value="<?php echo get_post_meta($post->ID, 'timeline_subtitle', true); ?>" />
        <?php
    }

    function update($post_id) {
        if (!isset($_POST['timeline_subtitle']))
            return;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;

        if (!isset($_POST['timeline_subtitle']))
            return;

        if (!wp_verify_nonce($_POST['n_tsub'], 'a_save'))
            return;

        // Check permissions
        if ('post' == $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id))
                return;
        } else {
            if (!current_user_can('edit_page', $post_id))
                return;
        }
        $subtitle = $_POST['timeline_subtitle'];

        update_post_meta($post_id, 'timeline_subtitle', $subtitle);
    }

    //////////////////////////////////////////
    // add JavaScript
    function add_script() {
        $filename = plugins_url(dirname('/' . plugin_basename(__FILE__))) . '/js/imagesloaded.pkgd.js';
        wp_enqueue_script('infinite-timeline-imagesloaded.pkgd', $filename, array('jquery'), '3.1.8');

        $filename = plugins_url(dirname('/' . plugin_basename(__FILE__))) . '/js/jquery.infinitescroll.js';
        wp_enqueue_script('infinite-timeline-infinitescroll', $filename, array('jquery'), '2.1.0');

        $filename = plugins_url(dirname('/' . plugin_basename(__FILE__))) . '/js/infinite-timeline.js';
        wp_enqueue_script('infinite-timeline', $filename, array('jquery'), '1.0');
    }

    //////////////////////////////////////////
    // add css
    function add_style() {
        $filename = plugins_url(dirname('/' . plugin_basename(__FILE__))) . '/css/timeline.css';
        wp_enqueue_style('infinite-timeline', $filename, false, '1.1');
    }

    //////////////////////////////////////////
    // ShoetCode
    function shortcode($atts) {

        global $post, $wp_rewrite;
        $output = '';

        // option
        $atts = shortcode_atts(array('category_name' => '',
            'tag' => '',
            'post_type' => 'post',
            'posts_per_page' => 0), $atts);

        $args = array('post_type' => $atts['post_type']);

        // category name
        $category_name = $atts['category_name'];
        if ($category_name) {
            $args['category_name'] = $category_name;
        }

        // tag
        $tag = $atts['tag'];
        if ($tag) {
            $args['tag'] = $tag;
        }

        // page
        $infinite_timeline_next = 1;
        if (isset($_GET['infinite_timeline_next'])) {
            $infinite_timeline_next = $_GET['infinite_timeline_next'];
        }

        // posts per page
        $posts_per_page = $atts['posts_per_page'];
        if (!$posts_per_page) {
            $posts_per_page = get_option('posts_per_page');
        }
        $args['posts_per_page'] = $posts_per_page;

        // prev post
        $year_prev = 0;
        if (1 < $infinite_timeline_next) {
            $args['posts_per_page'] = 1;
            $args['offset'] = $posts_per_page * ( $infinite_timeline_next - 1 ) - 1;
            $myposts = get_posts($args);
            if ($myposts) {
                foreach ($myposts as $post) {
                    setup_postdata($post);
                    $year_prev = (integer) get_post_time('Y');
                }
            }
            wp_reset_postdata();
        }

        // get posts
        $args['posts_per_page'] = $posts_per_page;
        $args['offset'] = $posts_per_page * ( $infinite_timeline_next - 1 );
        $myposts = get_posts($args);
        $time_last = 0;
        $year_last = 0;
        $year_top = 0;
//        $years = '';
        $count = 0;
        if ($myposts) {
            foreach ($myposts as $post) {
                setup_postdata($post);
                $title = get_the_title();

                $add_class = '';
                if ($count % 2) {
                    $add_class .= ' right';
                } else {
                    $add_class .= ' left';
                }

                // days gone by
                $time_current = (integer) get_post_time();
                if (!$time_last) {
                    $time_last = (integer) get_post_time();
                }

                $year = (integer) get_post_time('Y');
                if ($year != $year_last) {
                    if ($count) {
                        $output .= '</div>';
                    }

                    if ($year <> $year_prev) {
                        $output .= '<div id="' . esc_attr($year) . '" name="' . esc_attr($year) . '" data-yearpost="' . esc_attr($year) . '" class="year_head">' . $year . '</div>';
                    }

                    $year_last = $year;
                    $year_top = 1;
                    $output .= '<div class="year_posts" data-yearpost="' . esc_attr($year) . '">';

//                    $years .= '<li>' . $year . '</li>';
                }

                $days = ceil(abs($time_current - $time_last) / (60 * 60 * 24));
                $time_last = $time_current;

                $add_style = '';
                if ($year_top) {
                    $add_class .= ' year_top';
                } else {
                    $add_style = ' style="margin-top: ' . $days . 'px;"';
                }

                $size = 'large';
                if (wp_is_mobile()) {
                    $size = 'medium';
                }

                if (!empty($post->post_excerpt)) {
                    $content = $post->post_excerpt;
                } else {
                    $pieces = get_extended($post->post_content);
                    //var_dump($pieces); // debug
                    $content = apply_filters('the_content', $pieces['main']);
                }
                $subtitle = get_post_meta($post->ID, 'timeline_subtitle', true);

                $output .= '<div class="item' . $add_class . '"' . $add_style . '>';
                $output .= '<a href="' . get_permalink() . '">';
                $output .= get_the_post_thumbnail($post->ID, $size);
//				$output .= '<div class="title">' .get_post_time( get_option( 'date_format' ) ) .'<br>' .$title .'</div>';
                $output .= '<h4 class="title">' . $title . '</h4>';
                $output .= (!empty($subtitle)) ? '<h5 class="subtitle">' . $subtitle . '</h5>' : '';
                $output .= '<div class="content">' . $content . '</div>';
                $output .= '</a>';
                $output .= '</div>';

                $count++;
                $year_top = 0;
            }
//            $years = '<ul>' . $years . '</ul>';
        }
        wp_reset_postdata();

        // output
        if ($count) {
            $rewrite_url = ( $wp_rewrite->using_permalinks() ) ? '<div class="rewrite_url">' : '';
            $url = add_query_arg(array('infinite_timeline_next' => ( $infinite_timeline_next + 1 )));
            $output = '<div id="infinite_timeline"><div class="box">' . $output . '</div></div><div class="pagenation"><a href="' . $url . '">' . __('More', 'infinite-timeline') . '</a><img src="' . plugins_url(dirname('/' . plugin_basename(__FILE__))) . '/images/loading.gif" alt="" class="loading">' . $rewrite_url . '</div></div>';
        }

        return $output;
    }

}

$InfiniteTimeline = new InfiniteTimeline();
?>