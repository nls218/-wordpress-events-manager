<?php
/**
 * Plugin Name: Events Manager
 * Description: Кастомный тип записей для управления событиями с AJAX-подгрузкой
 * Version: 1.0
 * Author: ^__^
 */

/*
Кастомный тип записей - event с поддержкой только заголовка

Метабокс - для полей даты и места проведения с валидацией nonce

Шорткод [events_list] - выводит ближайшие события с пагинацией

AJAX-подгрузка - с защитой nonce и обработкой часовых поясов

Сортировка - по дате (ближайшие первыми)

Безопасность - все данные экранируются, используется nonce

Чистый код - разделение логики, правильные хуки WordPress

Плагин готов к использованию после активации через шорткод [events_list]
*/

// Безопасность
if (!defined('ABSPATH')) {
    exit;
}

class EventsManager {
    
    public function __construct() {
        add_action('init', array($this, 'register_event_post_type'));
        add_action('add_meta_boxes', array($this, 'add_event_metabox'));
        add_action('save_post', array($this, 'save_event_metadata'));
        add_shortcode('events_list', array($this, 'events_list_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_load_more_events', array($this, 'ajax_load_more_events'));
        add_action('wp_ajax_nopriv_load_more_events', array($this, 'ajax_load_more_events'));
    }

    // Регистрация кастомного типа записей
    public function register_event_post_type() {
        register_post_type('event', array(
            'labels' => array(
                'name' => 'События',
                'singular_name' => 'Событие',
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title'),
            'menu_icon' => 'dashcalendar',
        ));
    }

    // Добавление метабокса
    public function add_event_metabox() {
        add_meta_box(
            'event_details',
            'Детали события',
            array($this, 'render_event_metabox'),
            'event',
            'normal',
            'high'
        );
    }

    // Отображение метабокса
    public function render_event_metabox($post) {
        wp_nonce_field('event_nonce', 'event_nonce_field');
        
        $event_date = get_post_meta($post->ID, 'event_date', true);
        $event_place = get_post_meta($post->ID, 'event_place', true);
        ?>
        <div style="display: grid; gap: 12px;">
            <div>
                <label for="event_date">Дата события:</label>
                <input type="date" id="event_date" name="event_date" 
                       value="<?php echo esc_attr($event_date); ?>" 
                       style="width: 100%; margin-top: 5px;">
            </div>
            <div>
                <label for="event_place">Место проведения:</label>
                <input type="text" id="event_place" name="event_place" 
                       value="<?php echo esc_attr($event_place); ?>" 
                       style="width: 100%; margin-top: 5px;">
            </div>
        </div>
        <?php
    }

    // Сохранение метаданных
    public function save_event_metadata($post_id) {
        if (!isset($_POST['event_nonce_field']) || 
            !wp_verify_nonce($_POST['event_nonce_field'], 'event_nonce') ||
            defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
            !current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['event_date'])) {
            update_post_meta($post_id, 'event_date', sanitize_text_field($_POST['event_date']));
        }
        if (isset($_POST['event_place'])) {
            update_post_meta($post_id, 'event_place', sanitize_text_field($_POST['event_place']));
        }
    }

    // Шорткод для вывода событий
    public function events_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'posts_per_page' => 3
        ), $atts);

        $current_date = current_time('Y-m-d');
        $paged = get_query_var('paged') ?: 1;

        $events_query = new WP_Query(array(
            'post_type' => 'event',
            'posts_per_page' => $atts['posts_per_page'],
            'meta_key' => 'event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'paged' => $paged,
            'meta_query' => array(
                array(
                    'key' => 'event_date',
                    'value' => $current_date,
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        ));

        ob_start();
        ?>
        <div class="events-list-wrapper">
            <div class="events-list">
                <?php while ($events_query->have_posts()) : $events_query->the_post(); ?>
                    <?php $this->render_event_item(); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            
            <?php if ($events_query->max_num_pages > 1) : ?>
                <button class="load-more-events" 
                        data-page="1" 
                        data-max-pages="<?php echo $events_query->max_num_pages; ?>"
                        data-nonce="<?php echo wp_create_nonce('load_more_events'); ?>">
                    Показать больше
                </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // Рендер отдельного события
    private function render_event_item($post = null) {
        if (!$post) {
            $post = get_post();
        }
        
        $event_date = get_post_meta($post->ID, 'event_date', true);
        $event_place = get_post_meta($post->ID, 'event_place', true);
        
        if ($event_date) {
            $formatted_date = date_i18n('d.m.Y', strtotime($event_date));
        }
        ?>
        <div class="event-item">
            <h3 class="event-title"><?php echo esc_html(get_the_title()); ?></h3>
            <div class="event-date"><?php echo esc_html($formatted_date); ?></div>
            <div class="event-place"><?php echo esc_html($event_place); ?></div>
        </div>
        <?php
    }

    // Подключение скриптов и стилей
    public function enqueue_scripts() {
        wp_enqueue_script(
            'events-manager-js',
            plugin_dir_url(__FILE__) . 'assets/events.js',
            array('jquery'),
            '1.0',
            true
        );
        
        wp_enqueue_style(
            'events-manager-css',
            plugin_dir_url(__FILE__) . 'assets/events.css',
            array(),
            '1.0'
        );

        wp_localize_script('events-manager-js', 'eventsManager', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('load_more_events')
        ));
    }

    // AJAX-обработчик
    public function ajax_load_more_events() {
        check_ajax_referer('load_more_events', 'nonce');

        $page = intval($_POST['page']);
        $posts_per_page = 3;
        $current_date = current_time('Y-m-d');

        $events_query = new WP_Query(array(
            'post_type' => 'event',
            'posts_per_page' => $posts_per_page,
            'paged' => $page,
            'meta_key' => 'event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => 'event_date',
                    'value' => $current_date,
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        ));

        ob_start();
        while ($events_query->have_posts()) : $events_query->the_post();
            $this->render_event_item();
        endwhile;
        wp_reset_postdata();

        wp_send_json_success(array(
            'html' => ob_get_clean(),
            'max_pages' => $events_query->max_num_pages
        ));
    }
}

new EventsManager();