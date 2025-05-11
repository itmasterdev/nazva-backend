<?php
if( function_exists('acf_add_options_page') ) {
    acf_add_options_page([
        'page_title'  => 'Налаштування NAZVA',
        'menu_title'  => 'Налаштування NAZVA',
        'menu_slug'   => 'site-settings',
        'capability'  => 'edit_posts',
        'redirect'    => false,
    ]);
}

add_action('admin_init', function() {
    register_setting('site_settings_group', 'threads_link');
    register_setting('site_settings_group', 'behance_link');
    register_setting('site_settings_group', 'instagram_link');
    register_setting('site_settings_group', 'email_collaboration');
    register_setting('site_settings_group', 'email_work');
    register_setting('site_settings_group', 'main_email');
    register_setting('site_settings_group', 'phone_number');

    add_settings_section(
        'site_settings_section',
        'Глобальні налаштування сайта',
        function() { echo '<p>Вкажіть посилання і контакти:</p>'; },
        'site-settings'
    );

    $fields = [
        'threads_link'       => 'Threads',
        'behance_link'       => 'Behance',
        'instagram_link'     => 'Instagram',
        'email_collaboration'=> 'Email для співробітництва',
        'email_work'         => 'Email для роботи',
        'main_email'         => 'Головний Email',
        'phone_number'       => 'Номер телефона',
    ];

    foreach ($fields as $name => $label) {
        add_settings_field(
            $name,
            $label,
            function($args){
                $val = esc_attr( get_option($args['label_for']) );
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text"/>',
                    $args['label_for'],
                    $val
                );
            },
            'site-settings',
            'site_settings_section',
            [ 'label_for' => $name ]
        );
    }
});

// 3) Рендер страницы настроек
function render_site_settings_page() { ?>
    <div class="wrap">
        <h1>Настройки сайта</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('site_settings_group');
            do_settings_sections('site-settings');
            submit_button();
            ?>
        </form>
    </div>
<?php }

add_action('rest_api_init', function() {
    register_rest_route('site/v1','settings', [
        'methods'  => 'GET',
        'callback' => function() {
            return [
                'threads_link'        => get_option('threads_link'),
                'behance_link'        => get_option('behance_link'),
                'instagram_link'      => get_option('instagram_link'),
                'main_email'          => get_option('main_email'),
                'phone_number'        => get_option('phone_number'),
            ];
        },
        'permission_callback' => '__return_true', // публичный доступ
    ]);
});

add_action('admin_menu', function() {
    add_menu_page(
        'Налаштування NAZVA',
        'Налаштування NAZVA',
        'manage_options',
        'site-settings',
        'render_site_settings_page',
        'dashicons-admin-generic',
        60
    );
});


add_action('rest_api_init', function(){
    register_rest_route('site/v1', '/home-projects', [
        'methods'             => 'GET',
        'callback'            => function() {
            $args = [
                'post_type'      => 'project',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key'     => 'show_on_homepage',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                ],
            ];
            $query = new WP_Query($args);
            $result = [];

            foreach ($query->posts as $post) {
                $preview = get_field('project_preview', $post->ID);

                $thumbUrl = '';
                if ( is_array($preview) && ! empty($preview['url']) ) {
                    $thumbUrl = $preview['url'];
                }

                $result[] = [
                    'id'        => $post->ID,
                    'slug'      => $post->post_name,
                    'title'     => get_the_title($post),
                    'link'      => get_permalink($post),
                    'thumbnail' => $thumbUrl,
                ];
            }
            wp_reset_postdata();

            return $result;
        },
        'permission_callback' => '__return_true',
    ]);
});

add_action('init', function() {
    $labels = [
        'name'               => 'Проекти',
        'singular_name'      => 'Проект',
        'add_new'            => 'Додати проект',
        'add_new_item'       => 'Додати новий проект',
        'edit_item'          => 'Редагувати проект',
        'new_item'           => 'Новий проект',
        'view_item'          => 'Подивитись проект',
        'search_items'       => 'Шукати проекти',
        'not_found'          => 'Проекти не знайдено',
        'not_found_in_trash' => 'В корзині немає проектів',
        'menu_name'          => 'Проекти',
    ];

    $args = [
        'labels'        => $labels,
        'public'        => true,
        'show_in_rest'  => true,
        'has_archive'   => true,
        'rewrite'       => [ 'slug' => 'projects' ],
        'supports'      => [ 'title', 'editor', 'thumbnail' ],
        'menu_icon'     => 'dashicons-portfolio',
    ];

    register_post_type('project', $args);
});

add_action('init', function() {
    $labels = [
        'name'              => 'Категорії проектів',
        'singular_name'     => 'Категорія проекта',
        'search_items'      => 'Шукати категорії',
        'all_items'         => 'Усі категорії',
        'parent_item'       => 'Parent категорія',
        'parent_item_colon' => 'Parent категорія:',
        'edit_item'         => 'Редагувати категорію',
        'update_item'       => 'Оновити категорію',
        'add_new_item'      => 'Додати нову категорію',
        'new_item_name'     => 'Імʼя нової категорії',
        'menu_name'         => 'Категорії проектів',
    ];

    $args = [
        'hierarchical'      => true,              // работают как рубрики
        'labels'            => $labels,
        'show_in_rest'      => true,              // доступно в REST API
        'rewrite'           => [ 'slug' => 'project-category' ],
    ];

    register_taxonomy('project_category', ['project'], $args);
});
add_action('rest_api_init', function() {
    register_rest_field(
        'project',               // ваш тип записи
        'project_preview',       // имя поля в JSON
        [
            'get_callback'    => function( $object ) {
                // возвращаем значение ACF-поля для текущего поста
                return get_field( 'project_preview', $object['id'] );
            },
            'update_callback' => null,
            'schema'          => [
                'description' => 'URL изображения превью проекта из ACF',
                'type'        => 'string',
                'context'     => ['view', 'edit'],
            ],
        ]
    );
});
add_action('init', function() {
    $labels = [
        'name'               => 'Послуги',
        'singular_name'      => 'Послуга',
        'add_new'            => 'Додати послугу',
        'add_new_item'       => 'Додати нову послугу',
        'edit_item'          => 'Редагувати послугу',
        'new_item'           => 'Нова послуга',
        'view_item'          => 'Переглянути послугу',
        'search_items'       => 'Пошук послуг',
        'not_found'          => 'Послуги не знайдено',
        'not_found_in_trash' => 'Немає послуг',
        'menu_name'          => 'Послуги',
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'show_in_rest'       => true,                  // Gutenberg / REST API
        'has_archive'        => true,
        'rewrite'            => ['slug' => 'services'],
        'supports'           => [
            'title',            // заголовок
            'editor',           // описание (контент)
            'page-attributes',  // добавляет поле «Порядок» и menu_order
        ],
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-hammer',
        'hierarchical'       => false,
    ];

    register_post_type('service', $args);
});
add_action('rest_api_init', function(){
    register_rest_route('site/v1', '/send-request', [
        'methods'             => 'POST',
        'callback'            => 'wp_send_request_to_telegram',
        'permission_callback' => '__return_true',
        // Для поддержки загрузки файлов:
        'args' => [
            'name'    => ['required' => true],
            'email'   => ['required' => true],
            'message' => ['required' => true],
            'file'    => [
                'required' => false,
                'type'     => 'file',
            ],
        ],
    ]);
});

function wp_send_request_to_telegram( \WP_REST_Request $request ) {
    // Параметры — текстовые поля
    $name    = sanitize_text_field( $request->get_param('name') );
    $email   = sanitize_email( $request->get_param('email') );
    $message = sanitize_textarea_field( $request->get_param('message') );

    // Файл (если передан)
    $files = $request->get_file_params();
    $file  = $files['file'] ?? null;

    // Ограничение по размеру 10 МБ
    if ( $file && $file['size'] > 10 * 1024 * 1024 ) {
        return new WP_Error(
            'file_too_large',
            'Файл занадто великий (максимум 10 МБ).',
            [ 'status' => 413 ]
        );
    }

    // Читаем токен и чат из runtime config (wp-config.php или constants)
    $botToken = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : get_option('telegram_bot_token');
    $chatId   = defined('TELEGRAM_CHAT_ID')   ? TELEGRAM_CHAT_ID   : get_option('telegram_chat_id');

    // 1) Отправляем текст
    $text = wp_slash(
        "🆕 *Новий запит*  \n" .
        "*Імʼя:* {$name}  \n" .
        "*Email:* {$email}  \n" .
        "*Повідомлення:* {$message}"
    );
    wp_remote_post( "https://api.telegram.org/bot{$botToken}/sendMessage", [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ]),
    ]);

    // 2) Если есть файл — отправляем как документ
    if ( $file ) {
        // Используем CurlFile для вложения
        $cfile = new CURLFile( $file['tmp_name'], $file['type'], $file['name'] );
        $form  = [
            'chat_id'  => $chatId,
            'caption'  => '📎 Доданий файл:',
            'document' => $cfile,
        ];
        // Обход wp_remote_post — прямой curl, чтобы передать файл
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, "https://api.telegram.org/bot{$botToken}/sendDocument" );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $form );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_exec( $ch );
        curl_close( $ch );
    }

    return rest_ensure_response([ 'ok' => true ]);
}