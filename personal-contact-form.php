<?php
/*
Plugin Name: Personal Contact Form
Plugin URI: http://zeidan.info/
Description: Generates a simple contact form to be send to main admin user
Version: 0.1
Author: Eric Zeidan
Author URI: http://zeidan.es
License: GPL2
*/

if(!class_exists('personalContactForm')) {

    class personalContactForm
    {

        private $formCaptchaSitekey;
        private $formCaptchaSecret;
        public $adminemail;

        public function __construct()
        {
            //Here you can set your captcha Key & Secret
            $this->formCaptchaSitekey = get_option('captchaSiteKey');
            $this->formCaptchaSecret = get_option('captchaSecretKey');
            $this->admin_email = get_option('admin_email');
        }

        public function init()
        {
            add_shortcode('formulario', array($this, 'initForm'));
            add_action( 'template_redirect', array($this, 'proccessForm'));
            add_action( 'wp_enqueue_scripts', array($this, 'pluginEnqueueScripts'));
            add_action( 'admin_init', array($this, 'formGeneralSection'));
	        add_action('init', array($this, 'load_my_transl'));

            if($this->formCaptchaSitekey == "" || $this->formCaptchaSecret == "") {
                add_action('admin_notices', function() {
                    $class = 'notice notice-error';
                    $message = __( 'Pls. set your Captcha Keys in options general.' );

                    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
                });
            }
        }

	    public function load_my_transl()
	    {
		    load_plugin_textdomain('personalform', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
	    }

        public function pluginEnqueueScripts() {
            wp_enqueue_script( 're-captcha', 'https://www.google.com/recaptcha/api.js');
        }

        public function initForm()
        {
            ob_start();
            if($this->formCaptchaSitekey == "" || $this->formCaptchaSecret == "") {
                return false;
            }
            ?>
            <form action="#contact-form" id="contact-form" class="form" method="post">
                <?php wp_nonce_field('contact_form_nonce', 'contact_form'); ?>
                <div class="form-group">
                    <label for="name" class="col-sm-2 col-form-label col-form-label-sm"></label>
                    <div class="col-sm-10">
                    <input type="text" class="form-control" name="nombre" placeholder="<?php _e('Name', 'personalform'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="name" class="col-sm-2 col-form-label col-form-label-sm"></label>
                    <div class="col-sm-10">
                    <input type="email" name="email" class="form-control" placeholder="<?php _e('Email', 'personalform'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="asunto" class="col-sm-2 col-form-label col-form-label-sm"></label>
                    <div class="col-sm-10">
                    <input type="text" name="asunto" class="form-control" placeholder="<?php _e('Subject', 'personalform'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="mensaje" class="form-control"><?php _e('Message', 'personalform'); ?></label>
                    <textarea name="mensaje" class="form-control"></textarea>
                </div>
                <div class="form-group">
                <div class="g-recaptcha form-control" data-sitekey="<?php echo $this->formCaptchaSitekey; ?>"></div>
                </div>
                    <button type="submit" class="btn btn-primary"><?php _e('Send', 'personalform'); ?></button>
                    <div class="form-group">
                    <?php do_action('form_response'); ?>
                </div>
            </form>
            <?php
            return ob_get_clean();
        }

        public function proccessForm()
        {
            if (isset($_POST['contact_form'])) {
                $post_data = http_build_query(
                    array(
                        'secret' => $this->formCaptchaSecret,
                        'response' => $_POST['g-recaptcha-response'],
                        'remoteip' => $_SERVER['REMOTE_ADDR']
                    )
                );
                $opts = array(
                    'http' =>
                        array(
                            'method' => 'POST',
                            'header' => 'Content-type: application/x-www-form-urlencoded',
                            'content' => $post_data
                        )
                );
                $context = stream_context_create($opts);
                $response = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
                $result = json_decode($response);
                if (!$result->success) {
                    add_action('form_response', function () {
                        _e('Captcha Error', 'personalform');
                    });

                    return false;
                }

                $name = $_POST['nombre'];
                $subject = $_POST['asunto'];
                $email = $_POST['email'];
                $message = $_POST['mensaje'];

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    add_action('form_response', function () {
	                    _e('Email Error', 'personalform');
                    });

                    return false;
                }

                $mail = wp_mail($this->adminemail, "Mensaje de la web: " . $subject, $message . " from:" . $name . " email: " . $email);

                if (is_wp_error($mail)) {
                    add_action('form_response', function () {
                        _e('There is a problem, try later pls.', 'personalform');
                    });

                    return false;
                } else {
                    $my_post = array(
                        'post_title' => wp_strip_all_tags('Formulario de contacto de ' . $email),
                        'post_content' => "Asunto: " . $subject . "Mensaje" . $message . " from:" . $name . " email: " . $email,
                        'post_status' => 'draft',
                        'post_author' => 1,
                    );

                    wp_insert_post($my_post);

                    add_action('form_response', function () {
                        _e('We just receive your Message, we will be in touch soonest', 'personalform');
                    });

                    return false;
                }
            }

        }

        public function formGeneralSection() {
            add_settings_section(
                'form_settings_section',
                'Contact Form Options',
                array($this, 'formSectionOptions'),
                'general'
            );

            add_settings_field(
                'captchaSiteKey',
                'Captcha Site Key',
                array($this, 'captchaKeys'),
                'general',
                'form_settings_section',
                array(
                    'captchaSiteKey'
                )
            );

            add_settings_field(
                'captchaSecretKey',
                'Captcha Secret Key',
                array($this, 'captchaKeys'),
                'general',
                'form_settings_section',
                array(
                    'captchaSecretKey'
                )
            );

            register_setting('general','captchaSiteKey', 'esc_attr');
            register_setting('general','captchaSecretKey', 'esc_attr');
        }

        public function formSectionOptions() {
            _e('<p>Here can you enter your Captcha Details</p>');
        }

        public function captchaKeys($args) {
            $option = get_option($args[0]);
            echo '<input type="text" id="'. $args[0] .'" name="'. $args[0] .'" value="' . $option . '" />';
        }

        public function saveData($args) {

        }
    }
}

$fr = new personalContactForm();
$fr->init();
