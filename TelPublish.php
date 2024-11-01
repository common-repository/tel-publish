<?php

class TelPublish
{
    private $token;
    private $chatId;
    private $rhash;
    private $html;
    private $urlApi = 'https://api.telegram.org/bot';
    public $replace;

    static $init;

    function __construct(array $data)
    {
        if (!empty(self::$init)) return new WP_Error('duplicate_object', 'error');

        $this->token = $data['token'];
        $this->chatId = $data['chatId'];
        $this->rhash = $data['rhash'];
        $this->html = $data['html'];

        register_activation_hook(__FILE__, array($this, 'telPublishActivate'));

        add_action('edit_post', array($this, 'telPublish_block_save'));
        add_action('trash_post', array($this, 'telPublishPostdel'), 10, 2);
        add_action("admin_menu", array($this, "admin_add_menu"));
        add_action('add_meta_boxes', array($this, 'telPublish_block'));
        add_filter('sanitize_option_tel_pub_token', array($this, 'filter_function_tel_pub_token'), 10, 3);
        add_filter('sanitize_option_tel_pub_chat_id', array($this, 'filter_function_tel_pub_chat_id'), 10, 3);
        add_filter('sanitize_option_tel_pub_rhash', array($this, 'filter_function_tel_pub_rhash'), 10, 3);
        add_filter('sanitize_option_tel_pub_html', array($this, 'filter_function_tel_pub_html'), 10, 3);
    }


    public static function init(array $data)
    {

        if (empty(self::$init)) self::$init = new self($data);
        return self::$init;
    }


    public function telPublish_block($post_type)
    {
        $post_types = array('post', 'page');
        add_meta_box('telPublish', 'TelPublish', array($this, 'telPublish_block_html'), $post_type, 'advanced', 'high');
    }

    /**
     * html block settings
     */

    public function telPublish_block_html($post)
    {

        wp_nonce_field('telpublishmessage', 'telpublish_is_send');
        $html .= '<label>Telegram message id <input type="text" disabled name="telpublishmessage" value="' . get_post_meta($post->ID, 'telpublishmessage', true) . '" /></label> ';
        $html .= '<label><input type="checkbox" name="telpublish_is_send"';
        $html .= (get_post_meta($post->ID, 'telpublish_is_send', true) == 'on') ? ' checked="checked"' : '';
        $html .= ' /> Sync telegram chat?</label>';
        echo $html;
    }
    /**
     * 
     */
    public function telPublish_block_save($post_id)
    {
        $telpublish_is_send  = sanitize_text_field($_POST['telpublish_is_send']);
        $telpublishmessage = intval($_POST['telpublishmessage']);
        update_post_meta($post_id, 'telpublish_is_send', $telpublish_is_send);
        // update_post_meta($post_id, 'telpublishmessage', $telpublishmessage);

        self::telPublishPost($post_id);
    }
    /**
     * telPublishPost actions save_post
     *   https://t.me/iv?url={}&rhash=ef700510df2706
     */

    public  function  telPublishPost($post_id)
    {
        $telpublish_is_send = get_post_meta($post_id, 'telpublish_is_send', true);



        if (get_post_status($post_id) != "publish")  return false;
        if ($telpublish_is_send != 'on') return false;


        $text = htmlspecialchars_decode($this->filterCode($this->html, $post_id));

        $mess_id = intval(get_post_meta($post_id, 'telpublishmessage', true));

        if ($mess_id) {

            $this->request('editMessageText', ['text' => $text, 'parse_mode' => 'HTML', 'message_id' => $mess_id]);
        } else {

            $response = json_decode($this->request('sendMessage', ['text' => $text, 'parse_mode' => 'HTML']));

            if ($response->ok) {
                update_post_meta($post_id, 'telpublishmessage', $response->result->message_id);
            }
        }
    }
    /**
     * delete post in telegram
     */
    public function telPublishPostdel($postid, $post)
    {

        $mess_id = get_post_meta($postid, 'telpublishmessage', true);
        $this->request('deleteMessage', ['message_id' => $mess_id]);
    }

    /**
     * request 
     * @param $type - type sendMessage,forwardMessage,editMessageText
     * @param $content: array  
     */

    public function request($type, array $content)
    {
        $content['chat_id'] = $this->chatId;
        $url = $this->urlApi . $this->token . '/' . $type;
        $response = wp_remote_post($url, array(
            'timeout'     => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(),
            'body'        => $content, // параметры запроса в массиве
            'cookies'     => array()
        ));
        $code = json_decode($response['body']);
        if (!$code->ok) new WP_Error('Tel_publish', 'Error send message to telegram');

        return $response['body'];
    }

    /**
     * admin_add_menu      
     */

    public function admin_add_menu()
    {
        add_menu_page('TelPublish',  'Tel Publish', 'manage_options', 'telPublishSetting', array($this, 'telPublishSetting'));
    }

    public function telPublishSetting()
    {


        $message = (get_option('no_login_message')) ? get_option('no_login_message') : '';
?>
        <div class="wrap">
            <h2>PublishSetting</h2>

            <form method="post" action="options.php">
                <?php wp_nonce_field('update-options'); ?>

                <table class="form-table">

                    <tr valign="top">
                        <th scope="row">Token bot</th>
                        <td><input type="text" name="tel_pub_token" value="<?php echo get_option('tel_pub_token'); ?>" /></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">chat Id</th>
                        <td><input type="text" name="tel_pub_chat_id" value="<?php echo get_option('tel_pub_chat_id'); ?>" /></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Rhash.</th>
                        <td><input type="text" name="tel_pub_rhash" value="<?php echo get_option('tel_pub_rhash'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Message (HTML)<br />
                            <code>
                                {link}- title post</br />
                                {url} - url post not html </br />
                                {title} - title post</br />
                                {excerpt} - fragment post
                            </code>
                        </th>
                        <td><textarea name="tel_pub_html" id="" cols="50" rows="10"><?php echo htmlspecialchars_decode(get_option('tel_pub_html')); ?></textarea>
                        </td>
                    </tr>

                </table>

                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="tel_pub_token,tel_pub_chat_id,tel_pub_rhash,tel_pub_html" />
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </p>

            </form>
        </div>
<?php
    }

    public function filterCode($html, $post_id)
    {
        $replaceArr = [];
        $url = get_permalink($post_id);
        $link = 'https://t.me/iv?url=' . $url . '&rhash=' . $this->rhash;
        $link_lv = '<a href="' . $link . '">link</a>';

        $replaceArr['{url}'] = $url;
        $replaceArr['{link}'] = $link_lv;
        $replaceArr['{title}'] = get_the_title($post_id);
        $replaceArr['{excerpt}'] = get_the_excerpt($post_id);

        $this->replace = $replaceArr;

        return  str_replace(array_keys($this->replace), array_values($this->replace), $html);
    }

    public  function telPublishActivate()
    {
        if (!get_option('tel_pub_html')) return;
        option_update('tel_pub_html', 'ffdf');
    }


    public  function filter_function_tel_pub_token($value, $option, $original_value)
    {
        return sanitize_text_field($value);
    }

    public  function filter_function_tel_pub_chat_id($value, $option, $original_value)
    {
        return sanitize_text_field($value);
    }

    public  function filter_function_tel_pub_rhash($value, $option, $original_value)
    {
        return sanitize_text_field($value);
    }

    public  function filter_function_tel_pub_html($value, $option, $original_value)
    {
        return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}
