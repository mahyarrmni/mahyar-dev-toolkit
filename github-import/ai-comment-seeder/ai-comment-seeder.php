<?php
/**
 * Plugin Name: AI Comment Seeder Pro
 * Description: کامنت‌گذاری هوشمند با Gemini — سریع، دسته‌جمعی، زمان‌بندی هوشمند، آمار
 * Version:     5.2.0
 * Author:      Mahyar Momeni
 * Plugin URI: https://github.com/mahyarrmni/ai-comment-seeder
 * Author URI: https://mahyarhub.ir
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acs
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ACS_VER',    '5.2.0' );
define( 'ACS_DIR',    plugin_dir_path( __FILE__ ) );
define( 'ACS_URL',    plugin_dir_url( __FILE__ ) );
define( 'ACS_TBL',    'acs_jobs' );
define( 'ACS_LOG',    WP_CONTENT_DIR . '/acs-debug.log' );
define( 'ACS_LOG_MAX', 5 * 1024 * 1024 );

register_activation_hook( __FILE__, 'acs_install' );
register_deactivation_hook( __FILE__, 'acs_deactivate' );

/* ═══════════════════════════════════════════════
   INSTALL / DEFAULTS
═══════════════════════════════════════════════ */

function acs_install() {
    global $wpdb;
    $ch = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}acs_jobs (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id         BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
        total_comments  INT             NOT NULL DEFAULT 0,
        posted_comments INT             NOT NULL DEFAULT 0,
        settings        LONGTEXT        NOT NULL,
        error_msg       TEXT                     DEFAULT NULL,
        created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY status (status)
    ) {$ch};" );
    if ( ! get_option( 'acs_keys' ) )  add_option( 'acs_keys', [] );
    if ( ! get_option( 'acs_cfg' ) )   add_option( 'acs_cfg',  acs_defaults() );
    if ( ! get_option( 'acs_version' ) ) add_option( 'acs_version', ACS_VER );
}

function acs_deactivate() {
    wp_clear_scheduled_hook( 'acs_run_job' );
    wp_clear_scheduled_hook( 'acs_kickoff' );
}

function acs_defaults(): array {
    return [
        'model'            => 'gemini-3.6-flash',
        'language'         => 'fa',
        'persona'          => 'mixed',
        'min_len'          => 40,
        'max_len'          => 260,
        'reply_pct'        => 25,
        'min_delay'        => 20,
        'max_delay'        => 120,
        'date_min'         => 1,
        'date_max'         => 60,       // FIX: جدا از max_delay
        'hour_min'         => 8,
        'hour_max'         => 23,
        'auto_approve'     => false,   // safer default: new comments wait for moderation
        'post_types'       => ['post'],
        'budget'           => 100,
        'min_pp'           => 1,
        'max_pp'           => 8,
        'fixed_pp'         => 5,
        'smart_schedule'   => true,
        'skip_has_comments' => false,
        'min_words'        => 0,
        'max_posts_batch'  => 200,
        'max_del_batch'    => 100,
        'job_timeout'      => 600,      // ثانیه — stuck-job reset
        'type_compliment'  => true,
        'type_question'    => true,
        'type_critique'    => true,
        'type_experience'  => true,
        'type_recommend'   => true,
        'type_disagree'    => false,
        'type_followup'    => true,
        'type_humor'       => false,
        'type_detail_req'  => true,
        'type_comparison'  => false,
    ];
}

function acs_models(): array {
    return [
        'gemini-3.6-flash'       => 'Gemini 3.6 Flash (توصیه‌شده — جدیدترین)',
        'gemini-3.5-flash'       => 'Gemini 3.5 Flash (قوی، agentic)',
        'gemini-3.5-flash-lite'  => 'Gemini 3.5 Flash-Lite (سریع‌ترین، ارزان‌ترین)',
        'gemini-3.1-flash-lite'  => 'Gemini 3.1 Flash-Lite (حجم بالا، مقرون‌به‌صرفه)',
        'gemini-3.1-pro'         => 'Gemini 3.1 Pro (پیچیده‌ترین)',
    ];
}

function acs_persona_types(): array {
    return [
        'student'      => 'دانشجو',
        'professional' => 'متخصص',
        'housewife'    => 'خانه‌دار',
        'entrepreneur' => 'کارآفرین',
        'elder'        => 'باتجربه',
        'youngster'    => 'جوان',
        'skeptic'      => 'شکاک',
        'enthusiast'   => 'مشتاق',
        'researcher'   => 'پژوهشگر',
        'teacher'      => 'معلم',
    ];
}

/* ═══════════════════════════════════════════════
   PERSONA
═══════════════════════════════════════════════ */

function acs_make_persona( string $style = 'mixed' ): array {
    $fn = [
        'm' => ['علی','محمد','رضا','امیر','حسین','مهدی','سینا','آرش','کامران','فرهاد','پویا','شایان','نیما','بهزاد','کیان','آرمان','سپهر','میلاد','پارسا','سامان','رامین','شاهین','بابک','پدرام','حامد','جواد','علیرضا','کاوه','شروین','ایمان'],
        'f' => ['سارا','مریم','زهرا','فاطمه','نازنین','شیرین','لیلا','نیلوفر','آناهیتا','پریسا','ریحانه','گلنوش','مهسا','الناز','باران','نگار','یاسمن','صبا','هانیه','بیتا','شادی','رویا','مهناز','آزاده','سمیرا','گلاره','درسا','آیدا','رها','ستاره'],
    ];
    $ln = ['احمدی','محمدی','رضایی','حسینی','کریمی','موسوی','نجفی','صادقی','علیزاده','مرادی','جعفری','رحیمی','غلامی','حیدری','نوری','شیرازی','تهرانی','اصفهانی','قاسمی','ابراهیمی','طاهری','منصوری','سلیمانی','فتحی','نظری','زارعی','باقری','یوسفی','اکبری','کاظمی'];
    $g  = rand(0,1) ? 'm' : 'f';
    $f  = $fn[$g][ array_rand($fn[$g]) ];
    $l  = $ln[ array_rand($ln) ];
    $keys = array_keys( acs_persona_types() );
    $type = ( $style === 'mixed' || ! in_array( $style, $keys, true ) )
            ? $keys[ array_rand($keys) ]
            : $style;
    $ages = [
        'student'=>[18,26],'professional'=>[28,48],'housewife'=>[27,52],
        'entrepreneur'=>[24,44],'elder'=>[50,72],'youngster'=>[15,25],
        'skeptic'=>[30,58],'enthusiast'=>[20,45],'researcher'=>[26,50],'teacher'=>[28,55],
    ];
    $r   = $ages[$type] ?? [20,45];
    $age = rand( $r[0], $r[1] );
    $prov = ['gmail.com','yahoo.com','hotmail.com','outlook.com','proton.me'];
    $sep  = ['.','_',''][rand(0,2)];
    $slug = acs_latin($f) . $sep . acs_latin($l) . rand(1,99);
    return [
        'name'    => "$f $l",
        'email'   => strtolower($slug) . '@' . $prov[ array_rand($prov) ],
        'website' => '',
        'type'    => $type,
        'age'     => $age,
        'gender'  => $g,
    ];
}

function acs_latin( string $t ): string {
    $m = ['ا'=>'a','ب'=>'b','پ'=>'p','ت'=>'t','ث'=>'s','ج'=>'j','چ'=>'ch','ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'z','ر'=>'r','ز'=>'z','ژ'=>'zh','س'=>'s','ش'=>'sh','ص'=>'s','ض'=>'z','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f','ق'=>'gh','ک'=>'k','گ'=>'g','ل'=>'l','م'=>'m','ن'=>'n','و'=>'v','ه'=>'h','ی'=>'y','ئ'=>'y','آ'=>'a'];
    $o = '';
    foreach ( preg_split('//u', $t, -1, PREG_SPLIT_NO_EMPTY) as $c ) $o .= $m[$c] ?? $c;
    return preg_replace('/[^a-z0-9]/i', '', $o);
}

/* ═══════════════════════════════════════════════
   GEMINI API
═══════════════════════════════════════════════ */

function acs_call_gemini( string $prompt, string $model = '' ): string|WP_Error {
    if ( function_exists('set_time_limit') && ! ini_get('safe_mode') ) @set_time_limit(120);
    if ( function_exists('ignore_user_abort') ) @ignore_user_abort(true);

    $keys = array_values( array_filter( array_map('trim', (array) get_option('acs_keys', []) ) ) );
    if ( empty($keys) ) return new WP_Error( 'no_keys', 'هیچ کلید API تنظیم نشده.' );
    if ( empty($model) ) {
        $cfg   = get_option( 'acs_cfg', acs_defaults() );
        $model = $cfg['model'] ?? 'gemini-3.6-flash';
    }

    $last_err = null;
    foreach ( $keys as $i => $key ) {
        $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $body = wp_json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.93,'topK' => 40,'topP' => 0.95,'maxOutputTokens' => 500],
            'safetySettings'   => [
                ['category'=>'HARM_CATEGORY_HARASSMENT',        'threshold'=>'BLOCK_ONLY_HIGH'],
                ['category'=>'HARM_CATEGORY_HATE_SPEECH',       'threshold'=>'BLOCK_ONLY_HIGH'],
                ['category'=>'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold'=>'BLOCK_MEDIUM_AND_ABOVE'],
                ['category'=>'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold'=>'BLOCK_ONLY_HIGH'],
            ],
        ]);
        $resp = wp_remote_post( $url, [
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => $body,
            'timeout'   => 60,
            'sslverify' => true,
        ]);
        if ( is_wp_error($resp) ) {
            $last_err = $resp;
            acs_log("کلید #".($i+1)." WP_Error: ".$resp->get_error_message());
            continue;
        }
        $code = wp_remote_retrieve_response_code($resp);
        $data = json_decode( wp_remote_retrieve_body($resp), true );
        acs_log("کلید #".($i+1)." HTTP {$code}");
        if ( $code === 200 && isset($data['candidates'][0]['content']['parts'][0]['text']) ) {
            return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
        }
        $emsg = $data['error']['message'] ?? "HTTP {$code}";
        $ecode = $data['error']['code'] ?? $code;
        acs_log("کلید #".($i+1)." خطا ({$ecode}): {$emsg}");
        $last_err = new WP_Error('api_err', "کلید #".($i+1).": {$emsg}");
        $retryable = in_array($code, [429,500,503], true)
            || stripos($emsg,'quota')   !== false
            || stripos($emsg,'rate')    !== false
            || stripos($emsg,'demand')  !== false;
        if ( $retryable && $i+1 < count($keys) ) { sleep(1); continue; }
        break;
    }
    return $last_err ?? new WP_Error('all_failed', 'همه کلیدها ناموفق.');
}

function acs_build_prompt( string $title, string $content, array $p, array $cfg, array $ex = [] ): string {
    $lang = $cfg['language'] ?? 'fa';
    $ll   = match($lang) { 'fa' => 'فارسی', 'en' => 'English', default => 'فارسی (با چند کلمه انگلیسی تخصصی)' };
    $pm   = ['student'=>'دانشجوی کنجکاو','professional'=>'متخصص حرفه‌ای','housewife'=>'خانه‌دار','entrepreneur'=>'کارآفرین','elder'=>'باتجربه','youngster'=>'جوان','skeptic'=>'شکاک','enthusiast'=>'مشتاق','researcher'=>'پژوهشگر','teacher'=>'معلم'];
    $pd   = $pm[$p['type']] ?? 'کاربر';
    $ts   = [];
    if ( ! empty($cfg['type_compliment']) )  $ts[] = 'تعریف واقعی';
    if ( ! empty($cfg['type_question']) )    $ts[] = 'سوال هوشمند';
    if ( ! empty($cfg['type_critique']) )    $ts[] = 'نقد سازنده';
    if ( ! empty($cfg['type_experience']) )  $ts[] = 'اشتراک تجربه';
    if ( ! empty($cfg['type_recommend']) )   $ts[] = 'توصیه/پیشنهاد';
    if ( ! empty($cfg['type_disagree']) )    $ts[] = 'اختلاف نظر مودبانه';
    if ( ! empty($cfg['type_followup']) )    $ts[] = 'دنبال کردن نکته';
    if ( ! empty($cfg['type_humor']) )       $ts[] = 'شوخی ملایم';
    if ( ! empty($cfg['type_detail_req']) )  $ts[] = 'درخواست توضیح بیشتر';
    if ( ! empty($cfg['type_comparison']) )  $ts[] = 'مقایسه';
    $tl    = $ts ? implode(' / ', $ts) : 'نظر معمولی';
    $eb    = '';
    if ( ! empty($ex) ) {
        $eb = "\nکامنت‌های قبلی (تکرار نکن):\n";
        foreach ( array_slice($ex, -3) as $c ) $eb .= '- ' . mb_substr(strip_tags($c), 0, 100) . "\n";
    }
    $emoji = in_array($p['type'], ['youngster','enthusiast'], true) ? '۱ تا ۲ ایموجی مناسب می‌توانی بگذاری.' : 'از ایموجی استفاده نکن.';
    $min   = (int)($cfg['min_len'] ?? 40);
    $max   = (int)($cfg['max_len'] ?? 260);
    return "شما یک کاربر واقعی هستید:\n- شخصیت: {$pd} | نام: {$p['name']} | سن: {$p['age']}\nمقاله را خوانده‌اید:\nعنوان: {$title}\nخلاصه: {$content}\n{$eb}\nیک کامنت کاملاً انسانی بنویسید:\n- زبان: {$ll}\n- طول: {$min} تا {$max} کاراکتر\n- نوع: {$tl}\n- {$emoji}\n- بدون لینک یا تبلیغ\n- اول شخص مفرد\n- فقط متن کامنت بدون توضیح\n\nکامنت:";
}

/* ═══════════════════════════════════════════════
   COMMENT INSERT
═══════════════════════════════════════════════ */

function acs_post_comment( int $post_id, array $persona, string $text, array $cfg, int $parent = 0 ): int|WP_Error {
    $text = sanitize_textarea_field( trim($text) );
    if ( empty($text) ) return new WP_Error('empty', 'متن کامنت خالی.');
    $date = acs_random_date($cfg);
    // FIX: فیلتر صحیح برای جلوگیری از خطای duplicate
    add_filter('pre_comment_approved', '__return_true', 99);
    $id = wp_insert_comment([
        'comment_post_ID'     => $post_id,
        'comment_author'      => $persona['name'],
        'comment_author_email'=> $persona['email'],
        'comment_author_url'  => '',
        'comment_content'     => $text,
        'comment_parent'      => $parent,
        'comment_approved'    => empty($cfg['auto_approve']) ? 0 : 1,
        'comment_date'        => $date,
        'comment_date_gmt'    => get_gmt_from_date($date),
        'comment_agent'       => 'ACS/' . ACS_VER,
    ]);
    remove_filter('pre_comment_approved', '__return_true', 99);
    if ( ! $id ) return new WP_Error('fail', 'خطا در ثبت کامنت.');
    add_comment_meta($id, '_acs_generated', '1',      true);
    add_comment_meta($id, '_acs_persona',   $persona['type'], true);
    add_comment_meta($id, '_acs_ver',       ACS_VER,  true);
    return $id;
}

function acs_random_date( array $cfg ): string {
    $mn = max(0,  (int)($cfg['date_min'] ?? 1));
    $mx = max(1,  (int)($cfg['date_max'] ?? 60));
    if ( $mn > $mx ) $mn = $mx;
    $d = rand($mn, $mx);
    $h = rand( (int)($cfg['hour_min'] ?? 8), (int)($cfg['hour_max'] ?? 23) );
    $m = rand(0,59); $s = rand(0,59);
    return date('Y-m-d H:i:s', strtotime("-{$d} days") + $h*3600 + $m*60 + $s);
}

/* ═══════════════════════════════════════════════
   DATABASE
═══════════════════════════════════════════════ */

function acs_db_ins( int $pid, int $t, array $cfg ): int {
    global $wpdb;
    $wpdb->insert( $wpdb->prefix.'acs_jobs', [
        'post_id'        => $pid,
        'status'         => 'pending',
        'total_comments' => $t,
        'posted_comments'=> 0,
        'settings'       => wp_json_encode($cfg),
    ]);
    return (int)$wpdb->insert_id;
}
function acs_db_upd( int $id, array $d ) { global $wpdb; $wpdb->update($wpdb->prefix.'acs_jobs', $d, ['id'=>$id]); }
function acs_db_get( int $id ) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}acs_jobs WHERE id=%d",$id)); }
function acs_db_all( int $pp = 25, int $pg = 1 ) {
    global $wpdb;
    $off = ($pg-1)*$pp;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT j.*,p.post_title FROM {$wpdb->prefix}acs_jobs j LEFT JOIN {$wpdb->posts} p ON j.post_id=p.ID ORDER BY j.created_at DESC LIMIT %d OFFSET %d",
        $pp, $off
    ));
}
function acs_db_cnt(): int { global $wpdb; return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}acs_jobs"); }
function acs_db_del( int $id ) { global $wpdb; $wpdb->delete($wpdb->prefix.'acs_jobs', ['id'=>$id]); }
function acs_db_stats(): array {
    global $wpdb;
    $t = $wpdb->prefix.'acs_jobs';
    return [
        'total'    => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}"),
        'pending'  => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='pending'"),
        'running'  => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='running'"),
        'completed'=> (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='completed'"),
        'error'    => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='error'"),
        'comments' => (int)$wpdb->get_var("SELECT SUM(posted_comments) FROM {$t}"),
        'ai_total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key='_acs_generated'"),
    ];
}

function acs_del_all_ai(): int {
    global $wpdb;
    $n     = 0;
    $cfg   = get_option('acs_cfg', acs_defaults());
    $batch = max(10, (int)($cfg['max_del_batch'] ?? 100));
    do {
        $ids = $wpdb->get_col("SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_acs_generated' LIMIT {$batch}");
        foreach ($ids as $cid) { wp_delete_comment((int)$cid, true); $n++; }
    } while ( count($ids) === $batch );
    return $n;
}

function acs_del_post_ai( int $pid ): int {
    global $wpdb;
    $n = 0; $batch = 100;
    do {
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT cm.comment_id FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON cm.comment_id=c.comment_ID WHERE cm.meta_key='_acs_generated' AND c.comment_post_ID=%d LIMIT {$batch}",
            $pid
        ));
        foreach ($ids as $cid) { wp_delete_comment((int)$cid, true); $n++; }
    } while ( count($ids) === $batch );
    return $n;
}

/* ═══════════════════════════════════════════════
   DISTRIBUTION
═══════════════════════════════════════════════ */

// FIX: حلقه while با max iteration بهینه‌شده — بدون خطر infinite loop
function acs_distribute( array $ids, int $budget, int $mn, int $mx ): array {
    if ( empty($ids) || $budget <= 0 ) return [];
    shuffle($ids);
    $d   = array_fill_keys($ids, 0);
    $rem = $budget;
    // ابتدا حداقل به همه بده
    foreach ( $ids as $id ) {
        if ($rem <= 0) break;
        $give    = min($mn, $rem);
        $d[$id]  = $give;
        $rem    -= $give;
    }
    // باقی‌مانده را تقسیم کن — با early exit بهینه
    $eligible = array_filter($ids, fn($id) => $d[$id] < $mx);
    $pass = 0;
    while ( $rem > 0 && ! empty($eligible) ) {
        $id = $eligible[ array_rand($eligible) ];
        $d[$id]++;
        $rem--;
        if ($d[$id] >= $mx) {
            $eligible = array_filter($eligible, fn($i) => $i !== $id);
        }
        if ( ++$pass > 50000 ) break; // safety cap
    }
    return array_filter($d);
}

function acs_smart_dist( array $ids, int $budget, int $mn, int $mx ): array {
    if ( empty($ids) || $budget <= 0 ) return [];
    $sc = [];
    foreach ($ids as $id) {
        $p = get_post($id);
        if (!$p) continue;
        $age   = max(1, (time() - strtotime($p->post_date)) / 86400);
        $sc[$id] = 1 / sqrt($age);
    }
    if ( empty($sc) ) return [];
    $tot = array_sum($sc);
    if ($tot <= 0) return array_fill_keys($ids, $mn);
    $d = [];
    foreach ($sc as $id => $s) {
        $d[$id] = max($mn, min($mx, (int)round(($s/$tot)*$budget)));
    }
    $diff = $budget - array_sum($d);
    arsort($sc);
    foreach (array_keys($sc) as $id) {
        if ($diff === 0) break;
        if ($diff > 0 && $d[$id] < $mx) { $d[$id]++; $diff--; }
        elseif ($diff < 0 && $d[$id] > $mn) { $d[$id]--; $diff++; }
    }
    return array_filter($d);
}

function acs_all_pids( array $types = ['post'], int $limit = 0 ): array {
    $cfg   = get_option('acs_cfg', acs_defaults());
    $limit = $limit > 0 ? $limit : (int)($cfg['max_posts_batch'] ?? 200);
    $args  = [
        'post_type'      => $types,
        'post_status'    => 'publish',
        'posts_per_page' => min($limit, 500),
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    // FIX: اعمال skip_has_comments در کوئری
    if ( ! empty($cfg['skip_has_comments']) ) {
        $args['comment_count'] = ['value' => 0, 'compare' => '='];
    }
    // FIX: اعمال min_words اگه تنظیم شده
    $ids = get_posts($args);
    if ( ! empty($cfg['min_words']) && (int)$cfg['min_words'] > 0 ) {
        $min_w = (int)$cfg['min_words'];
        $ids   = array_filter($ids, function($id) use ($min_w) {
            $p = get_post($id);
            return $p && str_word_count(wp_strip_all_tags($p->post_content)) >= $min_w;
        });
    }
    return array_values($ids);
}

/* ═══════════════════════════════════════════════
   CRON / JOB PROCESSING
═══════════════════════════════════════════════ */

add_filter('cron_schedules', function($s) {
    $s['acs5'] = ['interval'=>300,'display'=>'هر ۵ دقیقه (ACS)'];
    return $s;
});
add_action('acs_run_job',  'acs_process_job');
add_action('acs_kickoff',  'acs_process_job');

/**
 * FIX: منطق کامل process_job
 * - stuck-job detection با configurable timeout
 * - یک کامنت per cron call — صحیح
 * - delay واقعی بین کامنت‌ها
 */
function acs_process_job( int $jid ) {
    $job = acs_db_get($jid);
    if ( ! $job || in_array($job->status, ['completed','cancelled','error'], true) ) return;

    // FIX: stuck-job check با timeout قابل تنظیم
    $cfg_g = get_option('acs_cfg', acs_defaults());
    if ( $job->status === 'running' ) {
        $updated = strtotime($job->updated_at ?? '0');
        $timeout = (int)($cfg_g['job_timeout'] ?? 600);
        if ( $updated && (time() - $updated) > $timeout ) {
            acs_db_upd($jid, ['status'=>'error','error_msg'=>"جاب بیش از {$timeout} ثانیه در حال اجرا بود — ریست شد."]);
            acs_log("جاب #{$jid} به دلیل timeout ریست شد.", 'WARN');
        }
        return;
    }

    $cfg  = json_decode($job->settings, true);
    if ( ! is_array($cfg) ) $cfg = acs_defaults();

    $post = get_post($job->post_id);
    if ( ! $post ) {
        acs_db_upd($jid, ['status'=>'error','error_msg'=>'پست یافت نشد.']);
        return;
    }

    acs_db_upd($jid, ['status'=>'running']);

    $content = mb_substr(wp_strip_all_tags($post->post_content), 0, 1500);
    $persona = acs_make_persona($cfg['persona'] ?? 'mixed');

    $existing = array_map(
        fn($c) => $c->comment_content,
        get_comments(['post_id'=>$job->post_id,'number'=>5,'status'=>'approve','orderby'=>'comment_date','order'=>'DESC','fields'=>'comment_content'])
    );

    // FIX: ریپلای انتخاب صحیح parent
    $parent = 0;
    if ( rand(1,100) <= (int)($cfg['reply_pct'] ?? 25) ) {
        $cids = get_comments(['post_id'=>$job->post_id,'status'=>'approve','fields'=>'ids','number'=>50]);
        if ( ! empty($cids) ) $parent = (int)$cids[ array_rand((array)$cids) ];
    }

    $text = acs_call_gemini( acs_build_prompt($post->post_title, $content, $persona, $cfg, $existing), $cfg['model'] ?? 'gemini-3.6-flash' );
    if ( is_wp_error($text) ) {
        acs_db_upd($jid, ['status'=>'error','error_msg'=>$text->get_error_message()]);
        return;
    }

    $res = acs_post_comment((int)$job->post_id, $persona, $text, $cfg, (int)$parent);
    if ( is_wp_error($res) ) {
        acs_db_upd($jid, ['status'=>'error','error_msg'=>$res->get_error_message()]);
        return;
    }

    $posted = (int)$job->posted_comments + 1;
    $done   = $posted >= (int)$job->total_comments;
    acs_db_upd($jid, ['posted_comments'=>$posted, 'status'=> $done ? 'completed' : 'pending']);
    acs_log("جاب #{$jid} — کامنت {$posted}/{$job->total_comments} ثبت شد.");

    if ( ! $done ) {
        $mn = (int)($cfg['min_delay'] ?? 20);
        $mx = (int)($cfg['max_delay'] ?? 120);
        if ( $mn > $mx ) $mn = $mx;
        // FIX: delay به ثانیه تبدیل می‌شود (منوت × ۶۰)
        $delay = rand($mn, $mx) * 60;
        wp_schedule_single_event(time() + $delay, 'acs_run_job', [$jid]);
    }
}

/* ═══════════════════════════════════════════════
   LOG
═══════════════════════════════════════════════ */

function acs_log( string $msg, string $lvl = 'INFO' ) {
    if ( @file_exists(ACS_LOG) && @filesize(ACS_LOG) > ACS_LOG_MAX ) {
        @rename(ACS_LOG, ACS_LOG.'.old');
    }
    @file_put_contents(ACS_LOG, '['.date('Y-m-d H:i:s')."] [{$lvl}] {$msg}\n", FILE_APPEND|LOCK_EX);
}

/* ═══════════════════════════════════════════════
   ADMIN MENU
═══════════════════════════════════════════════ */

add_action('admin_menu', function() {
    add_menu_page('Seeder','💬 Seeder','manage_options','acs','acs_pg_dash','dashicons-format-chat',30);
    add_submenu_page('acs','داشبورد',   'داشبورد',    'manage_options','acs',       'acs_pg_dash');
    add_submenu_page('acs','ایجاد جاب','ایجاد جاب',  'manage_options','acs-new',   'acs_pg_new');
    add_submenu_page('acs','تنظیمات',   'تنظیمات',    'manage_options','acs-cfg',   'acs_pg_cfg');
    add_submenu_page('acs','لاگ جاب‌ها','لاگ جاب‌ها', 'manage_options','acs-logs',  'acs_pg_logs');
    add_submenu_page('acs','📊 آمار',   '📊 آمار',    'manage_options','acs-stats', 'acs_pg_stats');
});

add_action('admin_enqueue_scripts', function($hook) {
    if ( strpos($hook,'acs') === false && strpos($hook,'post.php') === false && strpos($hook,'post-new.php') === false ) return;
    // FIX: CSS/JS inline — نه به فایل‌های خارجی وابسته
    wp_enqueue_script('chartjs','https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',[],null,true);
    add_action('admin_head', 'acs_inline_css');
    add_action('admin_footer','acs_inline_js');
    wp_localize_script('jquery','ACS',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('acs')]);
});

/* ═══════════════════════════════════════════════
   INLINE CSS
═══════════════════════════════════════════════ */
function acs_inline_css() { ?>
<style>
:root{--ac:#c9a84c;--ac2:#a8843a;--bg:#f9f7f2;--bg2:#fff;--bd:#e5dfc8;--tx:#2c2416;--tx2:#6b5b3e;--rd:10px;--sh:0 2px 12px rgba(0,0,0,.08)}
.acs-w{max-width:960px;font-family:'Vazirmatn',Tahoma,sans-serif;direction:rtl}
.acs-hd{display:flex;align-items:center;gap:14px;padding:20px 0 16px;border-bottom:2px solid var(--ac);margin-bottom:24px}
.acs-logo{font-size:32px;line-height:1}
.acs-hd h1{margin:0;color:var(--tx);font-size:22px}
.acs-hd small{color:var(--tx2);font-size:12px}
.acs-stats{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:24px}
.acs-st{flex:1;min-width:100px;background:var(--bg2);border:1px solid var(--bd);border-radius:var(--rd);padding:16px;text-align:center;box-shadow:var(--sh)}
.acs-st .n{display:block;font-size:28px;font-weight:700;color:var(--ac)}
.acs-st .l{display:block;font-size:12px;color:var(--tx2);margin-top:4px}
.acs-st.sw .n{color:#e67e22}.acs-st.sok .n{color:#27ae60}.acs-st.ser .n{color:#e74c3c}.acs-st.sg .n{color:#2980b9}
.acs-card{background:var(--bg2);border:1px solid var(--bd);border-radius:var(--rd);margin-bottom:18px;overflow:hidden;box-shadow:var(--sh)}
.acs-ch{display:flex;align-items:center;gap:8px;padding:12px 16px;background:var(--bg);border-bottom:1px solid var(--bd);font-weight:600;color:var(--tx)}
.acs-cb{padding:18px 16px}
.acs-acts{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.acs-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:1px solid var(--bd);background:var(--bg2);color:var(--tx);cursor:pointer;font-size:13px;text-decoration:none;transition:.15s;font-family:inherit}
.acs-btn:hover{background:var(--bg);border-color:var(--ac);color:var(--tx)}
.acs-bp{background:var(--ac);border-color:var(--ac2);color:#fff}.acs-bp:hover{background:var(--ac2);color:#fff}
.acs-bd{background:#fff0f0;border-color:#f8c5c5;color:#c00}.acs-bd:hover{background:#ffe0e0}
.acs-bl{padding:10px 24px;font-size:14px}
.acs-al{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px}
.acs-aw{background:#fff8e1;border:1px solid #ffe082;color:#7a5c00}
.acs-t{width:100%;border-collapse:collapse}
.acs-t th{text-align:right;padding:9px 4px;color:var(--tx2);font-weight:600;font-size:13px;white-space:nowrap;width:200px;vertical-align:top;padding-top:12px}
.acs-t td{padding:7px 4px}
.acs-sel{padding:6px 10px;border:1px solid var(--bd);border-radius:6px;background:#fff;font-family:inherit;color:var(--tx);font-size:13px}
.acs-isz{width:90px;padding:6px 10px;border:1px solid var(--bd);border-radius:6px;font-family:inherit;font-size:13px;text-align:center}
.acs-inp{width:100%;padding:8px 12px;border:1px solid var(--bd);border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box;margin-bottom:8px}
.acs-ck{display:inline-flex;align-items:center;gap:6px;cursor:pointer;margin-left:14px;margin-bottom:6px;font-size:13px}
.acs-cks{margin-top:8px;line-height:2.2}
.acs-sr{margin-top:20px;padding-top:16px;border-top:1px solid var(--bd);display:flex;align-items:center;gap:12px}
.acs-inf{padding:10px 14px;background:#e8f4fd;border-radius:6px;font-size:12px;color:#1a5f8a;margin-bottom:12px}
/* Keys */
.acs-kr{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.acs-kn{width:24px;height:24px;border-radius:50%;background:var(--bg);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--tx2);flex-shrink:0}
.acs-k1 .acs-kn{background:var(--ac);color:#fff;border-color:var(--ac2)}
.acs-ki{flex:1;padding:7px 10px;border:1px solid var(--bd);border-radius:6px;font-size:12px;direction:ltr}
.acs-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.acs-dot.on{background:#27ae60}.acs-dot.off{background:#bdc3c7}
.acs-krm{background:none;border:none;color:#c00;cursor:pointer;font-size:16px;padding:0 4px;flex-shrink:0}
.acs-kadd{margin-top:4px;font-size:12px;padding:6px 12px}
/* Modes */
.acs-modes{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.acs-mode{padding:14px;border:2px solid var(--bd);border-radius:var(--rd);cursor:pointer;transition:.15s;display:flex;flex-direction:column;gap:4px}
.acs-mode span{font-size:22px}
.acs-mode strong{font-size:13px;color:var(--tx)}
.acs-mode small{font-size:11px;color:var(--tx2)}
.acs-mode:hover,.acs-mode.active{border-color:var(--ac);background:#fffbf0}
/* Post search */
.acs-drop{background:#fff;border:1px solid var(--bd);border-radius:8px;max-height:220px;overflow-y:auto;display:none;margin-bottom:8px}
.acs-dr{padding:9px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0ebe0;transition:.1s}
.acs-dr:hover{background:var(--bg)}.acs-dr:last-child{border-bottom:0}
.acs-tags{display:flex;flex-wrap:wrap;gap:6px}
.acs-tag{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:#f0ebe0;border-radius:20px;font-size:12px;color:var(--tx)}
.acs-tag button{background:none;border:none;cursor:pointer;color:#c00;font-size:14px;padding:0;line-height:1}
.acs-ibg{padding:10px 14px;background:#e8f4fd;border-radius:8px;font-size:13px;color:#1a5f8a}
.acs-g3{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}
.acs-lb{display:block;font-size:12px;color:var(--tx2);margin-bottom:4px;font-weight:600}
/* Job badges */
.acs-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
.acs-pending{background:#fff8e1;color:#7a5c00}.acs-running{background:#e3f2fd;color:#1565c0}
.acs-completed{background:#e8f5e9;color:#2e7d32}.acs-error{background:#ffebee;color:#c62828}
/* Metabox */
#acs-mb-btn{width:100%;margin-bottom:8px}
/* Quick btn in post list */
.acs-qbtn{font-size:11px!important;padding:2px 8px!important}
/* Alert */
.acs-ok{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;padding:10px 14px;border-radius:8px;font-size:13px;margin-top:8px}
.acs-err{background:#ffebee;border:1px solid #ef9a9a;color:#c62828;padding:10px 14px;border-radius:8px;font-size:13px;margin-top:8px}
/* Spinner */
.acs-spin{display:inline-block;width:16px;height:16px;border:2px solid rgba(0,0,0,.1);border-top-color:var(--ac);border-radius:50%;animation:acs-rot .7s linear infinite;vertical-align:middle;margin-left:6px}
@keyframes acs-rot{to{transform:rotate(360deg)}}
</style>
<?php }

/* ═══════════════════════════════════════════════
   INLINE JS
═══════════════════════════════════════════════ */
function acs_inline_js() { ?>
<script>
(function($){
'use strict';
var ACS = window.ACS || {};

/* ── تست کلیدها ── */
$('#acs-test').on('click',function(){
    var $btn=$(this);$btn.prop('disabled',true).text('در حال تست...');
    var keys=[];$('[name="keys[]"]').each(function(){var v=$.trim($(this).val());if(v)keys.push(v);});
    $.post(ACS.ajax,{action:'acs_test',nonce:ACS.nonce,keys:keys,model:$('#acs-mdl').val()},function(r){
        $btn.prop('disabled',false).text('✦ تست همه کلیدها');
        if(!r.success){$('#acs-test-out').show().html('<div class="acs-err">خطا در تست</div>');return;}
        var h='';
        $.each(r.data,function(i,x){h+='<div class="acs-'+(x.ok?'ok':'err')+'">کلید #'+x.i+' ('+x.hint+'): '+(x.ok?'✅ موفق':'❌ '+x.msg)+'</div>';});
        $('#acs-test-out').show().html(h);
    }).fail(function(){$btn.prop('disabled',false);});
});

/* ── افزودن کلید ── */
$('.acs-kadd').on('click',function(){
    var n=$('#acs-keys .acs-kr').length+1;
    $('#acs-keys').append('<div class="acs-kr"><span class="acs-kn">'+n+'</span><input type="text" name="keys[]" value="" placeholder="AIzaSy..." dir="ltr" class="acs-ki"><span class="acs-dot off"></span><button type="button" class="acs-krm">✕</button></div>');
});
$(document).on('click','.acs-krm',function(){$(this).closest('.acs-kr').remove();});
$(document).on('input','.acs-ki',function(){$(this).siblings('.acs-dot').removeClass('on off').addClass($(this).val().trim()?'on':'off');});

/* ── ذخیره تنظیمات ── */
$('#acs-save').on('click',function(){
    var $btn=$(this),$msg=$('#acs-save-msg');
    $btn.prop('disabled',true); $msg.html('<span class="acs-spin"></span>');
    var d={action:'acs_save',nonce:ACS.nonce};
    // collect keys
    var keys=[]; $('[name="keys[]"]').each(function(){keys.push($(this).val());});
    d.keys=keys;
    // collect form fields
    $('#acs-form').find('input,select').each(function(){
        var $i=$(this),n=$i.attr('name'),v=$i.val();
        if(!n||n==='keys[]') return;
        if($i.is(':checkbox')) d[n]=$i.is(':checked')?'1':'0';
        else if($i.is('[name="post_types[]"]')){if(!d.post_types)d.post_types=[];if($i.is(':checked'))d.post_types.push(v);}
        else d[n]=v;
    });
    $.post(ACS.ajax,d,function(r){
        $btn.prop('disabled',false);
        $msg.html(r.success?'<span class="acs-ok">'+r.data+'</span>':'<span class="acs-err">'+r.data+'</span>');
        setTimeout(function(){$msg.html('');},3000);
    });
});

/* ── حذف همه AI کامنت‌ها ── */
$('#acs-da').on('click',function(){
    if(!confirm('همه کامنت‌های AI حذف شود؟')) return;
    var $btn=$(this); $btn.prop('disabled',true);
    $.post(ACS.ajax,{action:'acs_del_all',nonce:ACS.nonce},function(r){
        $btn.prop('disabled',false);
        $('#acs-da-res').html('<div class="acs-ok">'+r.data+'</div>');
    });
});

/* ── حذف یک جاب ── */
$(document).on('click','.acs-dj',function(){
    if(!confirm('این جاب حذف شود؟')) return;
    var $btn=$(this),id=$btn.data('id');
    $.post(ACS.ajax,{action:'acs_del',nonce:ACS.nonce,id:id},function(r){
        if(r.success) $btn.closest('tr').fadeOut(300,function(){$(this).remove();});
    });
});

/* ── Quick Comment (ستون + متاباکس) ── */
$(document).on('click','.acs-qbtn',function(){
    var $btn=$(this),pid=$btn.data('pid');
    // FIX: بخوان persona سلکت از متاباکس اگه وجود داره
    var persona=$('#acs-mb-pers-'+pid).val()||'mixed';
    $btn.prop('disabled',true).text('⏳');
    var $res=$('.acs-qres[data-pid="'+pid+'"]');
    $.post(ACS.ajax,{action:'acs_quick',nonce:ACS.nonce,pid:pid,persona:persona},function(r){
        $btn.prop('disabled',false).text('⚡ بزن');
        $res.show();
        if(r.success){
            $res.html('<div class="acs-ok">✅ <b>'+r.data.author+'</b> ('+r.data.type+'): '+r.data.preview+'...</div>');
            $('.acs-qcnt[data-pid="'+pid+'"],.acs-qcnt').filter('[title]').filter(function(){return $(this).closest('[data-pid="'+pid+'"]').length||$(this).data('pid')===pid;}).text(r.data.ai_cnt);
        } else {
            $res.html('<div class="acs-err">❌ '+r.data+'</div>');
        }
    }).fail(function(){$btn.prop('disabled',false).text('⚡ بزن');});
});

/* ── حذف AI کامنت‌های یک پست ── */
$(document).on('click','.acs-del-post',function(){
    var pid=$(this).data('pid');
    if(!confirm('کامنت‌های AI این پست حذف شود؟')) return;
    var $btn=$(this); $btn.prop('disabled',true);
    $.post(ACS.ajax,{action:'acs_del_post',nonce:ACS.nonce,pid:pid},function(r){
        $btn.prop('disabled',false);
        var $res=$('.acs-qres[data-pid="'+pid+'"]');
        $res.show().html(r.success?'<div class="acs-ok">🗑️ '+r.data+'</div>':'<div class="acs-err">'+r.data+'</div>');
    });
});

/* ── صفحه ایجاد جاب ── */
if($('#acs-mode').length){
    // mode select
    $('.acs-mode').on('click',function(){
        $('.acs-mode').removeClass('active');
        $(this).addClass('active');
        $('#acs-mode').val($(this).data('mode'));
        var m=$(this).data('mode');
        var needSel=m==='sel_rand'||m==='per_post';
        $('#acs-sc').toggle(needSel);
        var fixedModes=m==='all_fixed'||m==='per_post';
        $('#fb,#fmn,#fmx').toggle(!fixedModes);
        $('#ffx').toggle(fixedModes);
    });
    // post search
    var srchTO;
    $('#acs-ps').on('input',function(){
        clearTimeout(srchTO);
        var q=$.trim($(this).val());
        if(q.length<2){$('#acs-pd').hide().empty();return;}
        srchTO=setTimeout(function(){
            $.post(ACS.ajax,{action:'acs_search',nonce:ACS.nonce,q:q},function(r){
                if(!r.success) return;
                var d=$('#acs-pd').empty().show();
                if(!r.data.length){d.hide();return;}
                $.each(r.data,function(i,p){
                    $('<div class="acs-dr">').text(p.title+' ('+p.type+')').data('p',p).appendTo(d);
                });
            });
        },350);
    });
    $(document).on('click','.acs-dr',function(){
        var p=$(this).data('p');
        var cur=$('#acs-pids').val().split(',').filter(Boolean);
        if(cur.indexOf(String(p.id))!==-1){$('#acs-pd').hide();return;}
        cur.push(p.id);
        $('#acs-pids').val(cur.join(','));
        $('<div class="acs-tag">').text(p.title).append($('<button>').text('×').on('click',function(){
            $(this).parent().remove();
            var c=$('#acs-pids').val().split(',').filter(function(x){return x&&x!==String(p.id);});
            $('#acs-pids').val(c.join(','));
        })).appendTo('#acs-pt');
        $('#acs-pd').hide().empty();
        $('#acs-ps').val('');
    });
    $(document).on('click',function(e){if(!$(e.target).closest('#acs-ps,#acs-pd').length)$('#acs-pd').hide();});
    // post count
    $.post(ACS.ajax,{action:'acs_counts',nonce:ACS.nonce},function(r){if(r.success)$('#acs-an').text(r.data.count);});
    // launch
    $('#acs-go').on('click',function(){
        var $btn=$(this),$res=$('#acs-gr');
        $btn.prop('disabled',true); $res.show().html('<span class="acs-spin"></span> در حال ارسال...');
        var d={
            action:'acs_launch',nonce:ACS.nonce,
            mode:$('#acs-mode').val(),
            pids:$('#acs-pids').val(),
            budget:$('#acs-bud').val(),
            min_pp:$('#acs-mn').val(),
            max_pp:$('#acs-mx').val(),
            fixed_pp:$('#acs-fx').val(),
        };
        var lg=$('#acs-lg').val(),pr=$('#acs-pr').val(),ap=$('#acs-ap').val();
        if(lg)d.language=lg; if(pr)d.persona=pr; if(ap!=='')d.auto_approve=ap;
        $.post(ACS.ajax,d,function(r){
            $btn.prop('disabled',false);
            $res.html(r.success?'<div class="acs-ok">'+r.data.msg+'</div>':'<div class="acs-err">❌ '+r.data+'</div>');
        }).fail(function(){$btn.prop('disabled',false);$res.html('<div class="acs-err">خطای شبکه</div>');});
    });
}

/* ── آمار chart ── */
if($('#acs-ch').length){
    $.post(ACS.ajax,{action:'acs_chart',nonce:ACS.nonce},function(r){
        if(!r.success) return;
        new Chart(document.getElementById('acs-ch').getContext('2d'),{
            type:'bar',
            data:{labels:r.data.chart.labels,datasets:[{label:'AI کامنت',data:r.data.chart.data,backgroundColor:'rgba(201,168,76,.6)',borderColor:'#c9a84c',borderWidth:1}]},
            options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
        });
        var h='<table class="widefat"><thead><tr><th>پست</th><th>AI کامنت</th></tr></thead><tbody>';
        $.each(r.data.top,function(i,row){h+='<tr><td>'+row.post_title+'</td><td><b>'+row.n+'</b></td></tr>';});
        $('#acs-top').html(h+'</tbody></table>');
    });
}

})(jQuery);
</script>
<?php }

/* ═══════════════════════════════════════════════
   POST LIST COLUMN
═══════════════════════════════════════════════ */

add_filter('manage_posts_columns', function($c){ $c['acs_q']='⚡ AI'; return $c; });
add_action('manage_posts_custom_column', function($col,$pid) {
    if ($col !== 'acs_q') return;
    global $wpdb;
    $n = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON cm.comment_id=c.comment_ID WHERE cm.meta_key='_acs_generated' AND c.comment_post_ID=%d",
        $pid
    ));
    echo '<div style="display:flex;align-items:center;gap:6px">';
    echo '<button class="button button-small acs-qbtn" data-pid="'.(int)$pid.'">⚡ بزن</button>';
    echo '<span class="acs-qcnt" data-pid="'.(int)$pid.'" title="AI کامنت‌ها">'.(int)$n.'</span>';
    echo '</div>';
    echo '<div class="acs-qres" data-pid="'.(int)$pid.'" style="display:none;margin-top:4px;font-size:11px"></div>';
},10,2);

/* ═══════════════════════════════════════════════
   METABOX
═══════════════════════════════════════════════ */

add_action('add_meta_boxes', function(){
    add_meta_box('acs-mb','⚡ AI Comment Seeder','acs_mb_render',['post','page'],'side','high');
});

function acs_mb_render($post) {
    $pid = (int)$post->ID;
    global $wpdb;
    $ai  = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON cm.comment_id=c.comment_ID WHERE cm.meta_key='_acs_generated' AND c.comment_post_ID=%d",
        $pid
    ));
    $tot = (int)get_comments_number($pid);
    echo '<div id="acs-mb-'.$pid.'">';
    echo '<p style="margin:0 0 8px">💬 کل: <b>'.esc_html($tot).'</b> | 🤖 AI: <b>'.esc_html($ai).'</b></p>';
    // FIX: persona select با ID منحصربه‌فرد
    echo '<select id="acs-mb-pers-'.$pid.'" style="width:100%;margin-bottom:8px"><option value="mixed">شخصیت: رندوم</option>';
    foreach (acs_persona_types() as $k=>$v) echo '<option value="'.esc_attr($k).'">'.esc_html($v).'</option>';
    echo '</select>';
    echo '<button type="button" class="button button-primary acs-qbtn" data-pid="'.$pid.'" style="width:100%;margin-bottom:8px">⚡ یه کامنت همین الان</button>';
    echo '<button type="button" class="button acs-del-post" data-pid="'.$pid.'" style="width:100%;color:#c00">🗑️ حذف AI کامنت‌های این پست</button>';
    echo '<div class="acs-qres" data-pid="'.$pid.'" style="display:none;margin-top:8px;font-size:12px;line-height:1.5"></div>';
    echo '</div>';
}

/* ═══════════════════════════════════════════════
   AJAX HANDLERS
═══════════════════════════════════════════════ */

add_action('wp_ajax_acs_quick', function() {
    check_ajax_referer('acs','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('دسترسی ندارید.');
    $pid   = (int)($_POST['pid'] ?? 0);
    $pers  = sanitize_key($_POST['persona'] ?? 'mixed');
    $post  = get_post($pid);
    if (!$post) wp_send_json_error('پست یافت نشد.');
    $cfg          = get_option('acs_cfg', acs_defaults());
    $content      = mb_substr(wp_strip_all_tags($post->post_content), 0, 1500);
    $persona      = acs_make_persona($pers);
    $existing     = array_map(fn($c)=>$c->comment_content, get_comments(['post_id'=>$pid,'number'=>5,'status'=>'approve','orderby'=>'comment_date','order'=>'DESC','fields'=>'comment_content']));
    // quick comment = همین الان
    $cfg['date_min'] = 0; $cfg['date_max'] = 0;
    $cfg['hour_min'] = (int)date('H'); $cfg['hour_max'] = (int)date('H');
    $text = acs_call_gemini(acs_build_prompt($post->post_title, $content, $persona, $cfg, $existing));
    if (is_wp_error($text)) wp_send_json_error($text->get_error_message());
    $res = acs_post_comment($pid, $persona, $text, $cfg);
    if (is_wp_error($res)) wp_send_json_error($res->get_error_message());
    global $wpdb;
    $new_cnt = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON cm.comment_id=c.comment_ID WHERE cm.meta_key='_acs_generated' AND c.comment_post_ID=%d",
        $pid
    ));
    wp_send_json_success(['preview'=>mb_substr($text,0,140),'author'=>$persona['name'],'type'=>$persona['type'],'ai_cnt'=>$new_cnt]);
});

// FIX: تست کلیدها بدون ذخیره‌سازی
add_action('wp_ajax_acs_test', function() {
    check_ajax_referer('acs','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();
    $raw   = isset($_POST['keys']) && is_array($_POST['keys']) ? $_POST['keys'] : [];
    $keys  = array_values(array_filter(array_map('trim',$raw)));
    $model = sanitize_text_field($_POST['model'] ?? 'gemini-2.5-flash');
    // FIX: کلیدها را ذخیره نمی‌کنیم — فقط تست
    $res = [];
    foreach ($keys as $i=>$key) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $r   = wp_remote_post($url,[
            'headers' => ['Content-Type'=>'application/json'],
            'body'    => wp_json_encode(['contents'=>[['parts'=>[['text'=>'سلام']]]],'generationConfig'=>['maxOutputTokens'=>5]]),
            'timeout' => 20,'sslverify'=>true,
        ]);
        $code = is_wp_error($r) ? 0 : wp_remote_retrieve_response_code($r);
        $body = is_wp_error($r) ? [] : json_decode(wp_remote_retrieve_body($r),true);
        $ok   = $code === 200 && isset($body['candidates']);
        $msg  = $ok ? 'موفق' : ($body['error']['message'] ?? (is_wp_error($r) ? $r->get_error_message() : "HTTP {$code}"));
        $res[] = ['i'=>$i+1,'hint'=>substr($key,0,8).'…','ok'=>$ok,'msg'=>$msg];
    }
    wp_send_json_success($res);
});

add_action('wp_ajax_acs_save', function() {
    check_ajax_referer('acs','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('دسترسی ندارید.');
    $raw = isset($_POST['keys']) && is_array($_POST['keys']) ? $_POST['keys'] : [];
    update_option('acs_keys', array_values(array_filter(array_map('sanitize_text_field',$raw))));
    $cfg = get_option('acs_cfg', acs_defaults());
    foreach (['model','language','persona','min_len','max_len','reply_pct','min_delay','max_delay','date_min','date_max','hour_min','hour_max','budget','min_pp','max_pp','fixed_pp','min_words','max_posts_batch','max_del_batch'] as $k) {
        if (isset($_POST[$k])) $cfg[$k] = sanitize_text_field($_POST[$k]);
    }
    // FIX: validation — min نباید از max بیشتر باشد
    if ((int)$cfg['min_delay'] > (int)$cfg['max_delay']) $cfg['min_delay'] = $cfg['max_delay'];
    if ((int)$cfg['date_min']  > (int)$cfg['date_max'])  $cfg['date_min']  = $cfg['date_max'];
    if ((int)$cfg['hour_min']  > (int)$cfg['hour_max'])  $cfg['hour_min']  = $cfg['hour_max'];
    if ((int)$cfg['min_len']   > (int)$cfg['max_len'])   $cfg['min_len']   = $cfg['max_len'];
    if ((int)$cfg['min_pp']    > (int)$cfg['max_pp'])    $cfg['min_pp']    = $cfg['max_pp'];
    foreach (['auto_approve','smart_schedule','skip_has_comments','type_compliment','type_question','type_critique','type_experience','type_recommend','type_disagree','type_followup','type_humor','type_detail_req','type_comparison'] as $k) {
        $cfg[$k] = isset($_POST[$k]) && $_POST[$k] === '1';
    }
    $cfg['post_types'] = isset($_POST['post_types']) && is_array($_POST['post_types']) ? array_map('sanitize_key',$_POST['post_types']) : ['post'];
    update_option('acs_cfg', $cfg);
    wp_send_json_success('✅ تنظیمات ذخیره شد.');
});

add_action('wp_ajax_acs_launch', function() {
    check_ajax_referer('acs','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('دسترسی ندارید.');
    $mode = sanitize_key($_POST['mode'] ?? 'all');
    $cfg  = get_option('acs_cfg', acs_defaults());
    foreach (['language','persona','min_delay','max_delay'] as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') $cfg[$k] = sanitize_text_field($_POST[$k]);
    }
    if (isset($_POST['auto_approve'])) $cfg['auto_approve'] = $_POST['auto_approve'] === '1';
    $budget = (int)($_POST['budget']   ?? $cfg['budget']);
    $min_pp = (int)($_POST['min_pp']   ?? $cfg['min_pp']);
    $max_pp = (int)($_POST['max_pp']   ?? $cfg['max_pp']);
    $fixed  = (int)($_POST['fixed_pp'] ?? $cfg['fixed_pp']);
    $smart  = !empty($cfg['smart_schedule']);
    $fn     = $smart ? 'acs_smart_dist' : 'acs_distribute';
    switch ($mode) {
        case 'all':
            $ids = acs_all_pids($cfg['post_types'] ?? ['post']);
            if (empty($ids)) wp_send_json_error('هیچ پستی یافت نشد.');
            $dist = $fn($ids, $budget, $min_pp, $max_pp);
            break;
        case 'all_fixed':
            $ids = acs_all_pids($cfg['post_types'] ?? ['post']);
            if (empty($ids)) wp_send_json_error('هیچ پستی یافت نشد.');
            $dist = array_fill_keys($ids, $fixed);
            break;
        case 'sel_rand':
            $ids = array_values(array_filter(array_map('intval', explode(',', $_POST['pids'] ?? ''))));
            if (empty($ids)) wp_send_json_error('پستی انتخاب نشده.');
            $dist = $fn($ids, $budget, $min_pp, $max_pp);
            break;
        default: // per_post
            $ids = array_values(array_filter(array_map('intval', explode(',', $_POST['pids'] ?? ''))));
            if (empty($ids)) wp_send_json_error('پستی انتخاب نشده.');
            $dist = array_fill_keys($ids, $fixed);
    }
    if (empty($dist)) wp_send_json_error('توزیع ناموفق — بودجه کافی نیست.');
    $n = 0;
    foreach ($dist as $pid => $cnt) {
        $jid = acs_db_ins((int)$pid, $cnt, $cfg);
        // FIX: delay بین ۱ تا ۳ دقیقه — نه ۵ تا ۹۰ ثانیه
        $delay = rand(60, 180) * ($n + 1); // stagger بین jobها
        $delay = min($delay, 3600);         // حداکثر ۱ ساعت
        wp_schedule_single_event(time() + $delay, 'acs_kickoff', [$jid]);
        $n++;
    }
    wp_send_json_success(['msg'=>"✅ {$n} جاب / ".array_sum($dist)." کامنت در صف قرار گرفت.",'jobs'=>$n,'total'=>array_sum($dist)]);
});

add_action('wp_ajax_acs_del', function() {
    check_ajax_referer('acs','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();
    acs_db_del((int)($_POST['id'] ?? 0));
    wp_send_json_success();
});

add_action('wp_ajax_acs_del_all', function() {
    check_ajax_referer('acs','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();
    $n = acs_del_all_ai();
    wp_send_json_success("🗑️ {$n} کامنت AI حذف شد.");
});

add_action('wp_ajax_acs_del_post', function() {
    check_ajax_referer('acs','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();
    $n = acs_del_post_ai((int)($_POST['pid'] ?? 0));
    wp_send_json_success("{$n} کامنت AI این پست حذف شد.");
});

add_action('wp_ajax_acs_search', function() {
    check_ajax_referer('acs','nonce');
    $q     = sanitize_text_field($_POST['q'] ?? '');
    $types = (array)(get_option('acs_cfg',[])['post_types'] ?? ['post','page']);
    $r     = get_posts(['s'=>$q,'post_type'=>$types,'posts_per_page'=>15,'post_status'=>'publish']);
    wp_send_json_success(array_map(fn($p)=>['id'=>$p->ID,'title'=>$p->post_title,'type'=>$p->post_type],$r));
});

add_action('wp_ajax_acs_counts', function() {
    check_ajax_referer('acs','nonce');
    $types = (array)(get_option('acs_cfg',[])['post_types'] ?? ['post']);
    wp_send_json_success(['count'=>count(acs_all_pids($types))]);
});

add_action('wp_ajax_acs_chart', function() {
    check_ajax_referer('acs','nonce');
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT DATE(comment_date) as d, COUNT(*) as n
         FROM {$wpdb->comments} c JOIN {$wpdb->commentmeta} cm ON c.comment_ID=cm.comment_id
         WHERE cm.meta_key='_acs_generated' AND c.comment_date>=DATE_SUB(NOW(),INTERVAL 30 DAY)
         GROUP BY DATE(comment_date) ORDER BY d ASC"
    );
    $top = $wpdb->get_results(
        "SELECT p.post_title, COUNT(*) as n
         FROM {$wpdb->comments} c JOIN {$wpdb->commentmeta} cm ON c.comment_ID=cm.comment_id JOIN {$wpdb->posts} p ON c.comment_post_ID=p.ID
         WHERE cm.meta_key='_acs_generated'
         GROUP BY c.comment_post_ID ORDER BY n DESC LIMIT 10"
    );
    wp_send_json_success([
        'chart' => ['labels'=>array_column($rows,'d'),'data'=>array_map(fn($r)=>(int)$r->n,$rows)],
        'top'   => $top,
    ]);
});

/* ═══════════════════════════════════════════════
   ADMIN PAGES
═══════════════════════════════════════════════ */

function acs_pg_dash() {
    $s    = acs_db_stats();
    $keys = get_option('acs_keys',[]);
    $jobs = acs_db_all(10,1);
    $sm   = ['pending'=>'⏳ در انتظار','running'=>'⚙️ اجرا','completed'=>'✅ تکمیل','error'=>'❌ خطا'];
    ?>
<div class="wrap acs-w">
<div class="acs-hd"><span class="acs-logo">💬</span><div><h1>AI Comment Seeder Pro</h1><small>v<?php echo ACS_VER;?></small></div></div>
<div class="acs-stats">
  <div class="acs-st"><span class="n"><?php echo (int)$s['total'];?></span><span class="l">کل جاب‌ها</span></div>
  <div class="acs-st sw"><span class="n"><?php echo (int)$s['pending']+(int)$s['running'];?></span><span class="l">در صف / اجرا</span></div>
  <div class="acs-st sok"><span class="n"><?php echo (int)$s['completed'];?></span><span class="l">تکمیل</span></div>
  <div class="acs-st ser"><span class="n"><?php echo (int)$s['error'];?></span><span class="l">خطا</span></div>
  <div class="acs-st sg"><span class="n"><?php echo (int)$s['ai_total'];?></span><span class="l">کل AI کامنت</span></div>
</div>
<div class="acs-acts">
  <a href="<?php echo esc_url(admin_url('admin.php?page=acs-new'));?>" class="acs-btn acs-bp">➕ ایجاد جاب</a>
  <a href="<?php echo esc_url(admin_url('admin.php?page=acs-cfg'));?>" class="acs-btn">⚙️ تنظیمات</a>
  <a href="<?php echo esc_url(admin_url('admin.php?page=acs-logs'));?>" class="acs-btn">📋 جاب‌ها</a>
  <a href="<?php echo esc_url(admin_url('admin.php?page=acs-stats'));?>" class="acs-btn">📊 آمار</a>
  <a href="<?php echo esc_url(admin_url('edit.php'));?>" class="acs-btn">📝 پست‌ها</a>
  <button class="acs-btn acs-bd" id="acs-da">🗑️ حذف همه AI کامنت‌ها</button>
</div>
<?php if(empty($keys)):?><div class="acs-al acs-aw">⚠️ کلید API تنظیم نشده — <a href="<?php echo esc_url(admin_url('admin.php?page=acs-cfg'));?>">برو تنظیمات</a></div><?php endif;?>
<div id="acs-da-res"></div>
<?php if(!empty($jobs)):?>
<div class="acs-card" style="margin-top:24px">
  <div class="acs-ch"><span>📋</span> آخرین جاب‌ها</div>
  <table class="widefat" style="border:0">
    <thead><tr><th>پست</th><th>وضعیت</th><th>کامنت</th><th>تاریخ</th></tr></thead>
    <tbody>
    <?php foreach($jobs as $j):?>
    <tr>
      <td><?php echo esc_html($j->post_title ?: '#'.$j->post_id);?></td>
      <td><span class="acs-badge acs-<?php echo esc_attr($j->status);?>"><?php echo $sm[$j->status]??esc_html($j->status);?></span></td>
      <td><?php echo (int)$j->posted_comments;?>/<?php echo (int)$j->total_comments;?></td>
      <td style="font-size:11px"><?php echo esc_html($j->created_at);?></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table>
  <div style="padding:10px 16px"><a href="<?php echo esc_url(admin_url('admin.php?page=acs-logs'));?>">مشاهده همه جاب‌ها ←</a></div>
</div>
<?php endif;?>
</div><?php
}

function acs_pg_cfg() {
    $keys = get_option('acs_keys',[]);
    if (empty($keys)) $keys = [''];
    $c = get_option('acs_cfg', acs_defaults());
    ?>
<div class="wrap acs-w">
<div class="acs-hd"><span class="acs-logo">⚙️</span><div><h1>تنظیمات</h1></div></div>
<form id="acs-form">

<div class="acs-card"><div class="acs-ch"><span>🔑</span> کلیدهای Gemini API</div><div class="acs-cb">
<div class="acs-inf">💡 هر تعداد کلید اضافه کنید — fallback خودکار. <a href="https://aistudio.google.com/app/apikey" target="_blank">دریافت کلید رایگان ↗</a></div>
<div id="acs-keys">
<?php foreach($keys as $i=>$v):?>
<div class="acs-kr<?php echo $i===0?' acs-k1':'';?>">
  <span class="acs-kn"><?php echo $i===0?'★':($i+1);?></span>
  <input type="text" name="keys[]" value="<?php echo esc_attr($v);?>" placeholder="AIzaSy..." dir="ltr" class="acs-ki">
  <span class="acs-dot <?php echo !empty(trim((string)$v))?'on':'off';?>"></span>
  <button type="button" class="acs-krm">✕</button>
</div>
<?php endforeach;?>
</div>
<button type="button" class="acs-btn acs-kadd">＋ افزودن کلید</button>
<div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5dfc8">
  <select name="model" id="acs-mdl" class="acs-sel" style="max-width:300px">
    <?php foreach(acs_models() as $v=>$l):?>
    <option value="<?php echo esc_attr($v);?>" <?php selected($c['model']??'',$v);?>><?php echo esc_html($l);?></option>
    <?php endforeach;?>
  </select>
  <button type="button" id="acs-test" class="acs-btn" style="margin-right:8px">✦ تست همه کلیدها</button>
</div>
<div id="acs-test-out" style="display:none;margin-top:10px"></div>
</div></div>

<div class="acs-card"><div class="acs-ch"><span>📦</span> توزیع پیش‌فرض</div><div class="acs-cb">
<table class="acs-t">
<tr><th>بودجه کل</th><td><input type="number" name="budget" value="<?php echo esc_attr($c['budget']);?>" class="acs-isz" min="1"></td></tr>
<tr><th>حداقل هر پست</th><td><input type="number" name="min_pp" value="<?php echo esc_attr($c['min_pp']);?>" class="acs-isz" min="0"></td></tr>
<tr><th>حداکثر هر پست</th><td><input type="number" name="max_pp" value="<?php echo esc_attr($c['max_pp']);?>" class="acs-isz" min="1"></td></tr>
<tr><th>تعداد ثابت</th><td><input type="number" name="fixed_pp" value="<?php echo esc_attr($c['fixed_pp']);?>" class="acs-isz" min="1" max="200"></td></tr>
<tr><th>حداکثر پست در هر بچ</th><td><input type="number" name="max_posts_batch" value="<?php echo esc_attr($c['max_posts_batch']??200);?>" class="acs-isz" min="10" max="500"><small style="margin-right:8px;color:#888">برای سایت‌های بزرگ کاهش دهید</small></td></tr>
<tr><th>زمان‌بندی هوشمند</th><td><label class="acs-ck"><input type="checkbox" name="smart_schedule" value="1" <?php checked(!empty($c['smart_schedule']));?>> پست‌های جدیدتر کامنت بیشتر</label></td></tr>
</table>
</div></div>

<div class="acs-card"><div class="acs-ch"><span>🕐</span> زمان‌بندی و تاریخ</div><div class="acs-cb">
<table class="acs-t">
<tr><th>تأخیر حداقل (دقیقه)</th><td><input type="number" name="min_delay" value="<?php echo esc_attr($c['min_delay']);?>" class="acs-isz" min="1"></td></tr>
<tr><th>تأخیر حداکثر (دقیقه)</th><td><input type="number" name="max_delay" value="<?php echo esc_attr($c['max_delay']);?>" class="acs-isz" min="1"></td></tr>
<tr><th>تاریخ از (روز پیش)</th><td><input type="number" name="date_min" value="<?php echo esc_attr($c['date_min']);?>" class="acs-isz" min="0"></td></tr>
<tr><th>تاریخ تا (روز پیش)</th><td><input type="number" name="date_max" value="<?php echo esc_attr($c['date_max']);?>" class="acs-isz" min="1"></td></tr><!-- FIX: date_max جدا از max_delay -->
<tr><th>ساعت شروع</th><td><input type="number" name="hour_min" value="<?php echo esc_attr($c['hour_min']);?>" class="acs-isz" min="0" max="23"></td></tr>
<tr><th>ساعت پایان</th><td><input type="number" name="hour_max" value="<?php echo esc_attr($c['hour_max']);?>" class="acs-isz" min="0" max="23"></td></tr>
</table>
</div></div>

<div class="acs-card"><div class="acs-ch"><span>💬</span> محتوای کامنت</div><div class="acs-cb">
<table class="acs-t">
<tr><th>زبان</th><td><select name="language" class="acs-sel"><option value="fa" <?php selected($c['language'],'fa');?>>فارسی</option><option value="en" <?php selected($c['language'],'en');?>>English</option><option value="mixed" <?php selected($c['language'],'mixed');?>>Mixed</option></select></td></tr>
<tr><th>سبک شخصیت</th><td><select name="persona" class="acs-sel"><option value="mixed" <?php selected($c['persona'],'mixed');?>>ترکیبی</option><?php foreach(acs_persona_types() as $k=>$v):?><option value="<?php echo esc_attr($k);?>" <?php selected($c['persona'],$k);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></td></tr>
<tr><th>حداقل طول</th><td><input type="number" name="min_len" value="<?php echo esc_attr($c['min_len']);?>" class="acs-isz" min="10"></td></tr>
<tr><th>حداکثر طول</th><td><input type="number" name="max_len" value="<?php echo esc_attr($c['max_len']);?>" class="acs-isz" min="20"></td></tr>
<tr><th>احتمال ریپلای (%)</th><td><input type="number" name="reply_pct" value="<?php echo esc_attr($c['reply_pct']);?>" class="acs-isz" min="0" max="100"></td></tr>
</table>
<div style="margin-top:14px"><strong>انواع کامنت:</strong><div class="acs-cks">
<?php foreach(['type_compliment'=>'تعریف','type_question'=>'سوال','type_critique'=>'نقد','type_experience'=>'تجربه','type_recommend'=>'توصیه','type_disagree'=>'اختلاف نظر','type_followup'=>'دنبال نکته','type_humor'=>'شوخی','type_detail_req'=>'درخواست توضیح','type_comparison'=>'مقایسه'] as $k=>$l):?>
<label class="acs-ck"><input type="checkbox" name="<?php echo $k;?>" value="1" <?php checked(!empty($c[$k]));?>> <?php echo esc_html($l);?></label>
<?php endforeach;?>
</div></div>
</div></div>

<div class="acs-card"><div class="acs-ch"><span>⚙️</span> رفتار</div><div class="acs-cb">
<table class="acs-t">
<tr><th>نوع پست</th><td><?php foreach(get_post_types(['public'=>true],'names') as $pt):?><label class="acs-ck"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt);?>" <?php checked(in_array($pt,(array)($c['post_types']??['post'])));?>> <?php echo esc_html($pt);?></label><?php endforeach;?></td></tr>
<tr><th>حداقل کلمات پست</th><td><input type="number" name="min_words" value="<?php echo esc_attr($c['min_words']??0);?>" class="acs-isz" min="0"></td></tr>
<tr><th>تأیید خودکار</th><td><label class="acs-ck"><input type="checkbox" name="auto_approve" value="1" <?php checked(!empty($c['auto_approve']));?>> بله</label></td></tr>
<tr><th>پست‌های دارای کامنت skip شوند</th><td><label class="acs-ck"><input type="checkbox" name="skip_has_comments" value="1" <?php checked(!empty($c['skip_has_comments']));?>> بله</label></td></tr>
</table>
</div></div>

<div class="acs-sr">
  <button type="button" id="acs-save" class="acs-btn acs-bp acs-bl">💾 ذخیره تنظیمات</button>
  <span id="acs-save-msg"></span>
</div>
</form></div><?php
}

function acs_pg_new() {
    $c = get_option('acs_cfg', acs_defaults());
    ?>
<div class="wrap acs-w">
<div class="acs-hd"><span class="acs-logo">➕</span><div><h1>ایجاد جاب جدید</h1></div></div>

<div class="acs-card"><div class="acs-ch"><span>📦</span> حالت توزیع</div><div class="acs-cb">
<div class="acs-modes">
  <div class="acs-mode active" data-mode="all"><span>🌐</span><strong>همه پست‌ها — رندوم</strong><small>بودجه کل، توزیع هوشمند بین پست‌ها</small></div>
  <div class="acs-mode" data-mode="all_fixed"><span>📌</span><strong>همه پست‌ها — ثابت</strong><small>X کامنت برای هر پست</small></div>
  <div class="acs-mode" data-mode="sel_rand"><span>🎲</span><strong>انتخابی — رندوم</strong><small>بودجه بین پست‌های انتخابی</small></div>
  <div class="acs-mode" data-mode="per_post"><span>🎯</span><strong>انتخابی — ثابت</strong><small>X کامنت برای هر پست انتخابی</small></div>
</div>
<input type="hidden" id="acs-mode" value="all">
</div></div>

<div class="acs-card" id="acs-sc" style="display:none"><div class="acs-ch"><span>🔍</span> انتخاب پست‌ها</div><div class="acs-cb">
<input type="text" id="acs-ps" placeholder="جستجوی پست..." class="acs-inp">
<div id="acs-pd" class="acs-drop"></div>
<div id="acs-pt" class="acs-tags"></div>
<input type="hidden" id="acs-pids" value="">
</div></div>

<div class="acs-card"><div class="acs-ch"><span>🔢</span> تعداد</div><div class="acs-cb acs-g3">
  <div id="fb"><label class="acs-lb">بودجه کل</label><input type="number" id="acs-bud" value="<?php echo esc_attr($c['budget']);?>" min="1" class="acs-isz"></div>
  <div id="fmn"><label class="acs-lb">حداقل هر پست</label><input type="number" id="acs-mn" value="<?php echo esc_attr($c['min_pp']);?>" min="0" class="acs-isz"></div>
  <div id="fmx"><label class="acs-lb">حداکثر هر پست</label><input type="number" id="acs-mx" value="<?php echo esc_attr($c['max_pp']);?>" min="1" class="acs-isz"></div>
  <div id="ffx" style="display:none"><label class="acs-lb">تعداد ثابت</label><input type="number" id="acs-fx" value="<?php echo esc_attr($c['fixed_pp']);?>" min="1" max="200" class="acs-isz"></div>
  <div id="finf" class="acs-ibg"><span id="acs-an">…</span> پست publish</div>
</div></div>

<div class="acs-card"><div class="acs-ch"><span>⚡</span> تنظیم سریع</div><div class="acs-cb acs-g3">
  <div><label class="acs-lb">زبان</label><select id="acs-lg" class="acs-sel"><option value="">پیش‌فرض</option><option value="fa">فارسی</option><option value="en">English</option><option value="mixed">Mixed</option></select></div>
  <div><label class="acs-lb">شخصیت</label><select id="acs-pr" class="acs-sel"><option value="">پیش‌فرض</option><?php foreach(acs_persona_types() as $k=>$v) echo"<option value='".esc_attr($k)."'>".esc_html($v)."</option>";?></select></div>
  <div><label class="acs-lb">تأیید</label><select id="acs-ap" class="acs-sel"><option value="">پیش‌فرض</option><option value="1">فوری</option><option value="0">در انتظار</option></select></div>
</div></div>

<div class="acs-sr">
  <button class="acs-btn acs-bp acs-bl" id="acs-go">🚀 ایجاد جاب</button>
  <div id="acs-gr" style="margin-top:12px;display:none"></div>
</div>
</div><?php
}

function acs_pg_logs() {
    $pg    = max(1,(int)($_GET['paged']??1));
    $jobs  = acs_db_all(25,$pg);
    $total = acs_db_cnt();
    $sm    = ['pending'=>'⏳ در انتظار','running'=>'⚙️ اجرا','completed'=>'✅ تکمیل','error'=>'❌ خطا'];
    ?>
<div class="wrap acs-w">
<div class="acs-hd"><span class="acs-logo">📋</span><div><h1>لاگ جاب‌ها</h1><small>مجموع: <?php echo (int)$total;?> جاب</small></div></div>
<table class="widefat fixed striped"><thead><tr>
  <th width="50">ID</th><th>پست</th><th width="120">وضعیت</th><th width="110">کامنت</th><th width="155">تاریخ ایجاد</th><th>پیام خطا</th><th width="60">حذف</th>
</tr></thead><tbody>
<?php if(empty($jobs)):?>
<tr><td colspan="7" style="text-align:center;padding:30px;color:#999">هیچ جابی یافت نشد.</td></tr>
<?php else:foreach($jobs as $j):?>
<tr>
  <td><?php echo (int)$j->id;?></td>
  <td><?php echo esc_html($j->post_title?:'#'.$j->post_id);?></td>
  <td><span class="acs-badge acs-<?php echo esc_attr($j->status);?>"><?php echo $sm[$j->status]??esc_html($j->status);?></span></td>
  <td><?php echo (int)$j->posted_comments;?>/<?php echo (int)$j->total_comments;?></td>
  <td style="font-size:11px"><?php echo esc_html($j->created_at);?></td>
  <td style="font-size:11px;color:#c00"><?php if($j->error_msg) echo '<span title="'.esc_attr($j->error_msg).'">'.esc_html(mb_substr($j->error_msg,0,80)).(mb_strlen($j->error_msg)>80?'…':'').'</span>'; else echo '—'; ?></td>
  <td><button class="button button-small acs-dj" data-id="<?php echo (int)$j->id;?>">🗑️</button></td>
</tr>
<?php endforeach;endif;?>
</tbody></table>
<?php $pages=ceil($total/25); if($pages>1):?>
<div class="tablenav bottom"><div class="tablenav-pages">
<?php for($i=1;$i<=$pages;$i++):?>
<a href="<?php echo esc_url(add_query_arg('paged',$i));?>" class="button<?php echo $pg==$i?' button-primary':'';?>"><?php echo $i;?></a>
<?php endfor;?></div></div>
<?php endif;?>
</div><?php
}

function acs_pg_stats() {
    $s = acs_db_stats();
    ?>
<div class="wrap acs-w">
<div class="acs-hd"><span class="acs-logo">📊</span><div><h1>آمار و گزارش</h1></div></div>
<div class="acs-stats">
  <div class="acs-st sg"><span class="n"><?php echo (int)$s['ai_total'];?></span><span class="l">کل AI کامنت</span></div>
  <div class="acs-st sok"><span class="n"><?php echo (int)$s['completed'];?></span><span class="l">جاب تکمیل</span></div>
  <div class="acs-st sw"><span class="n"><?php echo (int)$s['pending']+(int)$s['running'];?></span><span class="l">در صف</span></div>
</div>
<div class="acs-card"><div class="acs-ch"><span>📈</span> کامنت‌های ۳۰ روز گذشته</div><div class="acs-cb"><canvas id="acs-ch" height="80"></canvas></div></div>
<div class="acs-card"><div class="acs-ch"><span>🏆</span> پست‌های برتر (بیشترین AI کامنت)</div><div class="acs-cb"><div id="acs-top"><em style="color:#999">در حال بارگذاری...</em></div></div></div>
</div><?php
}

/* ═══════════════════════════════════════════════
   AUTO DB UPDATE
═══════════════════════════════════════════════ */

add_action('plugins_loaded', function() {
    if (get_option('acs_version') !== ACS_VER) {
        acs_install();
        update_option('acs_version', ACS_VER);
    }
});
