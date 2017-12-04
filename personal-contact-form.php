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

            if($this->formCaptchaSitekey == "" || $this->formCaptchaSecret == "") {
                add_action('admin_notices', function() {
                    $class = 'notice notice-error';
                    $message = __( 'Pls. set your Captcha Keys in options general.' );

                    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
                });
            }
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
                <p>
                    <label for="name">Nombres</label>
                    <input type="text" name="nombre" class="widefat" required>
                </p>
                <p>
                    <label for="name">Email</label>
                    <input type="email" name="email" class="widefat" required>
                </p>
                <p>
                    <label for="asunto">Asunto</label>
                    <input type="text" name="asunto" class="widefat" required>
                </p>
                <p>
                    <label for="mensaje">Mensaje</label>
                    <textarea name="mensaje"></textarea>
                </p>
                <p>
                <div class="g-recaptcha" data-sitekey="<?php echo $this->formCaptchaSitekey; ?>"></div>
                </p>
                <p>
                    <button type="submit">Enviar</button>
                </p>
                <p>
                    <?php do_action('form_response'); ?>
                </p>
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
                        echo "Error de Captcha";
                    });

                    return false;
                }

                $name = $_POST['nombre'];
                $subject = $_POST['asunto'];
                $email = $_POST['email'];
                $message = $_POST['mensaje'];

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    add_action('form_response', function () {
                        echo "Error de Email";
                    });

                    return false;
                }

                $mail = wp_mail($this->adminemail, "Mensaje de la web: " . $subject, $message . " from:" . $name . " email: " . $email);

                if (is_wp_error($mail)) {
                    add_action('form_response', function () {
                        echo "Ocurrio un problema, intente más tarde";
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
                        echo "Hemos recibido tu mensaje, pronto estaremos en contacto";
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
