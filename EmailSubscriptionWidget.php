<?php


/**
 * Class EmailSubscriptionWidget
 *
 * @author Tobias Nyholm
 *
 */
class EmailSubscriptionWidget extends WP_Widget {
    private $defaults = array(
        'title' => 'Subscribe to my posts',
        'success_msg'=>'Thank you for subscribing',
        'fail_msg' => 'Some unexpected error occurred',
        'submit_button'=>'Subscribe',
    );

    /**
     * Construct
     */
    public function __construct() {
        $options = array(
            'classname' => 'EmailSubscriptionWidget',
            'description' => __('Displays subpages for the current page.','email-subscription')
        );
        $this->WP_Widget('EmailSubscriptionWidget', 'Email Subscription Widget', $options);
        add_filter( 'plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2 );
    }

    /**
     * Render the widget
     *
     * @param array $args
     * @param array $instance
     *
     */
    public function widget($args, $instance) {
        extract($args, EXTR_SKIP);
        $promotion=get_option('emailSub-promotion', true);

        /*
         * Print widget
         */
        echo $before_widget;

        if(strlen($instance['title']) > 0){
            echo $before_title.$instance['title'].$after_title;
        }
        ?>
        <ul id='emailSub-widget'>
            <div id="emailSub-output" style="display:none;"></div>
            <form id="emailSub-form" action="<?php echo site_url('wp-admin/admin-ajax.php')?>">
                <input type="hidden" name="success_msg" id="emailSub-success" value="<?php echo $instance['success_msg'];?>" />
                <input type="hidden" name="fail_msg" id="emailSub-fail" value="<?php echo $instance['fail_msg'];?>" />
                <?php
                global $polylang;
                if(isset($polylang)) { ?>
                    <input type="hidden" name="language" id="emailSub-language" value="<?php echo pll_current_language();?>" />
                <?php } ?>
                <input type="text" name="email" id="emailSub-email" placeholder="Email:" />
                <br />
                <input type="submit" class="submit" value="<?php echo $instance['submit_button'];?>" />
                <?php if ($promotion): ?>
                <br />
                <div style="margin-top: 0.4em; font-size: 70%;">Created by <a href="http://www.webfish.se">Webfish</a>.</div>
                <?php endif; ?>
            </form>



        </ul>
        <?php
        echo $after_widget;
    }

    /**
     * Update the widget form
     *
     * @param array $new_instance
     * @param array $old_instance
     *
     * @return array
     */
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;

        return $new_instance;
    }

    /**
     * The form on the admin page
     *
     * @param array $instance
     *
     * @return string|void
     */
    public function form($instance) {
        $instance = wp_parse_args( (array) $instance, $this->defaults);

        $title = $instance['title'];
        $success = $instance['success_msg'];
        $fail = $instance['fail_msg'];
        $submit = $instance['submit_button'];
        ?>


        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e("Title:",'email-subscription');?></label><br />
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('success_msg'); ?>"><?php _e("Message if succeed:",'email-subscription');?></label><br />
            <input class="widefat" id="<?php echo $this->get_field_id('success_msg'); ?>" name="<?php echo $this->get_field_name('success_msg'); ?>" type="text" value="<?php echo esc_attr($success); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('fail_msg'); ?>"><?php _e("Message if fail:",'email-subscription');?></label><br />
            <input class="widefat" id="<?php echo $this->get_field_id('fail_msg'); ?>" name="<?php echo $this->get_field_name('fail_msg'); ?>" type="text" value="<?php echo esc_attr($fail); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('submit_button'); ?>"><?php _e("Text on submit button:",'email-subscription');?></label><br />
            <input class="widefat" id="<?php echo $this->get_field_id('submit_button'); ?>" name="<?php echo $this->get_field_name('submit_button'); ?>" type="text" value="<?php echo esc_attr($submit); ?>" />
        </p>


    <?php
    }

    /**
     *
     *
     * @param $links
     * @param $file
     *
     * @return array
     */
    public function plugin_action_links( $links, $file ) {
        static $this_plugin;

        if( empty($this_plugin) )
            $this_plugin = plugin_basename(__FILE__);

        if ( $file == $this_plugin )
            $links[] = '<a href="' . admin_url( 'widgets.php' ) . '">Widgets</a>';

        return $links;
    }
}

add_action('widgets_init', create_function('', 'return register_widget("EmailSubscriptionWidget");'));