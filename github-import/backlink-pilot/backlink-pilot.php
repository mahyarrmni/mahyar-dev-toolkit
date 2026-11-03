<?php
/**
 * Plugin Name: Backlink Pilot
 * Description: جمع‌آوری لیست بکلینک و رپورتاژ رایگان از گیتهاب، پاک‌سازی، تحلیل و امتیازدهی دامنه‌ها، و مدیریت صف ثبت. ایجنت AI برای برنامه‌ریزی بکلینک (v1.1).
 * Version: 1.10.0
 * Author: Mahyar Momeni
 * Plugin URI: https://github.com/mahyarrmni/backlink-pilot
 * Author URI: https://mahyarhub.ir
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Text Domain: backlink-pilot
 *
 * v1.0: منابع گیتهاب، پایگاه دامنه، تحلیل و امتیازدهی، پنل مدیریت فارسی، خروجی CSV.
 * v1.1: لایه‌ی AI (Gemini/Groq/OpenRouter/سفارشی با fallback)، کمپین‌ها، ایجنت برنامه‌ریز بکلینک، تولید متن، لاگ.
 * v1.1.1: پلاگین به یک فایل تبدیل شد؛ fallback ناامن SSL (sslverify=false) حذف شد.
 * v1.2.0: چرخه‌ی خودکار کامل: بارگذاری اولیه‌ی لیست، همگام‌سازی، تحلیل و تحلیل مجدد دوره‌ای، برنامه‌ریزی، تولید متن،
 *         تلاش مجدد خودکار، قفل ضد هم‌پوشانی کران، و ایمیل خلاصه‌ی روزانه‌ی «آماده‌ی انتشار» برای مدیر.
 * v1.8.0: «اتوپایلوت واقعی»: پروفایل‌های آماده (محتاط/متعادل/سریع) به‌جای ده‌ها عدد، سرعت خودتنظیم (کاهش خودکار هنگام خطا/حذف لینک)،
 *         بازبینی «۵ مقاله‌ی اول»، راهنمای «چطور بهینه‌تر شود؟» برای هر گزینه، چک‌لیست راه‌اندازی، آزمایش اتصال سرویس‌ها،
 *         پیشنهادهای هوشمند از داده‌ی واقعی، تب‌های پیشرفته جمع‌شده، و fallback خودکار بین همه‌ی ارائه‌دهنده‌های AI.
 * v1.7.0: «رادار بکلینک»: ردیابی چرخه‌ی هر لینک (بررسی فنی صفحه، noindex/canonical، ایندکس در Bing، پینگ IndexNow، صفحه‌ی کشف)،
 *         سنجش اثر روی نمایش/کلیک/رتبه با Google Search Console (Service Account)، قیف و نمودار هفتگی، و یادگیری خودکار
 *         (اتوپایلوت پلتفرم‌های موفق را بیشتر انتخاب می‌کند و وزن انکرهای بی‌اثر را کم می‌کند).
 * v1.6.1: نسخه‌ی MVP نهایی: ساعت فعالیت، گرم‌کردن تدریجی (ramp-up)، حالت بازبینی قبل از انتشار، وزن‌های قابل‌تنظیم انکر،
 *         طول مقاله، انتشار روی وردپرس‌های خودتان (REST)، تگ DEV.to، تشخیص محتوای تکراری، بررسی دوره‌ای لینک‌ها (follow/nofollow/حذف‌شده)،
 *         ایمیل خطا و گزارش هفتگی.
 * v1.5.3: قفل اتمیک ضد انتشار تکراری (BP_Lock + «در حال انتشار»)، حفظ رکورد لینک‌های منتشرشده هنگام حذف کمپین، نگهداری ۵۰۰۰ لاگ،
 *         و پرامپت تصادفی (BP_Style): لحن، ساختار، زاویه و جای لینک هر رپورتاژ متفاوت و سئو-محور.
 * v1.5.2: تکمیل حوزه‌های خبر، سرگرمی، امنیت، حقوقی و...؛ حذف چند دامنه‌ی مرده.
 * v1.5.1: لیست پیش‌فرض بزرگ (۱۶۰۰+ سایت) در ۳۲ حوزه، انتخاب حوزه و حالت «فقط تخصصی».
 * v1.5.0: اتوپایلوت: یک دکمه → ساخت مقاله، انتشار واقعی روی Telegra.ph / Write.as / DEV.to / Hashnode، بررسی لینک و لیست لینک‌های نهایی.
 * v1.4.0: ایجنت خودش همه‌ی نوشته‌ها و برگه‌های وردپرس را اسکن می‌کند، پیلار و صفحه‌های پشتیبان را پیدا می‌کند و کمپین می‌سازد؛
 *         بکلینک‌ها عمیق (پیلار + پشتیبان) پخش می‌شوند، سقف روزانه‌ی کل، چرخش عادلانه‌ی کمپین‌ها و کنترل کیفیت رپورتاژ (لینک واحد، حداقل طول).
 */
if (!defined('ABSPATH')) exit;

define('BP_VERSION', '1.10.0');

/* ============================================================
 * includes/class-settings.php
 * ============================================================ */
class BP_Settings {
    const OPT = 'bp_settings';
    private static $secrets_main = ['github_token', 'opr_key'];
    private static $secrets_ai = ['gemini_key', 'groq_key', 'openrouter_key', 'custom_key'];

    public static function defaults() {
        return [
            'github_token' => '',
            'opr_key'      => '',
            'proxy'        => '',
            'batch_size'   => 15,
            'target_lang'  => 'any',
            'auto_analyze' => 1,
            'auto_sync'    => 1,
            'notify'       => 1,
            'auto_campaigns'     => 1,
            'auto_max_campaigns' => 4,
            'global_daily_cap'   => 3,
            'keep_data'    => 1,
            'default_niche' => '',
            'niche_only'   => 0,
            'user_agent'   => 'Mozilla/5.0 (compatible; BacklinkPilot/1.1; +WordPress)',
            // AI (v1.1)
            'gemini_key'       => '',
            'groq_key'         => '',
            'openrouter_key'   => '',
            'custom_key'       => '',
            'custom_url'       => '',
            'model_gemini'     => 'gemini-2.5-flash',
            'model_groq'       => 'openai/gpt-oss-120b',
            'model_openrouter' => 'openai/gpt-oss-120b:free',
            'model_custom'     => '',
            'chain_decide'     => ['groq', 'gemini', 'openrouter'],
            'chain_write'      => ['gemini', 'openrouter', 'groq'],
            'token_cap'        => 150000,
            'autonomy'         => 'auto',
            'ai_proxy'         => 1,
        ];
    }

    public static function all() {
        $s = wp_parse_args(get_option(self::OPT, []), self::defaults());
        foreach (array_merge(self::$secrets_main, self::$secrets_ai) as $k) {
            $s[$k] = self::dec($s[$k]);
        }
        return $s;
    }

    public static function has_secret($k) {
        $o = get_option(self::OPT, []);
        return !empty($o[$k]);
    }

    private static function apply_secrets(array $keys, $in, array &$new) {
        foreach ($keys as $k) {
            $v = isset($in[$k]) ? trim(sanitize_text_field(wp_unslash($in[$k]))) : '';
            if ($v !== '') {
                $new[$k] = self::enc($v);
            }
            if (!empty($in['clear_' . $k])) {
                $new[$k] = '';
            }
        }
    }

    public static function save($in) {
        $new = wp_parse_args(get_option(self::OPT, []), self::defaults());
        self::apply_secrets(self::$secrets_main, $in, $new);
        $proxy = isset($in['proxy']) ? trim(sanitize_text_field(wp_unslash($in['proxy']))) : '';
        $new['proxy'] = preg_match('~^(https?|socks5h?|socks4)://[^\s]+$~i', $proxy) ? $proxy : '';
        $new['batch_size']   = max(1, min(60, (int) ($in['batch_size'] ?? 15)));
        $tl = isset($in['target_lang']) ? sanitize_key($in['target_lang']) : 'any';
        $new['target_lang']  = in_array($tl, ['any', 'fa', 'en'], true) ? $tl : 'any';
        $new['auto_analyze'] = empty($in['auto_analyze']) ? 0 : 1;
        $new['auto_sync']    = empty($in['auto_sync']) ? 0 : 1;
        $new['notify']       = empty($in['notify']) ? 0 : 1;
        $new['auto_campaigns']     = empty($in['auto_campaigns']) ? 0 : 1;
        $new['auto_max_campaigns'] = max(1, min(12, (int) ($in['auto_max_campaigns'] ?? 4)));
        $new['global_daily_cap']   = max(0, min(50, (int) ($in['global_daily_cap'] ?? 3)));
        $new['keep_data']    = empty($in['keep_data']) ? 0 : 1;
        $dn = isset($in['default_niche']) ? sanitize_key($in['default_niche']) : '';
        $new['default_niche'] = isset(BP_Seed::niches()[$dn]) ? $dn : '';
        $new['niche_only']   = empty($in['niche_only']) ? 0 : 1;
        update_option(self::OPT, $new, false);
    }

    public static function save_ai($in) {
        $new = wp_parse_args(get_option(self::OPT, []), self::defaults());
        self::apply_secrets(self::$secrets_ai, $in, $new);

        $url = isset($in['custom_url']) ? esc_url_raw(trim(wp_unslash($in['custom_url']))) : '';
        $new['custom_url'] = preg_match('~^https?://~i', $url) ? untrailingslashit($url) : '';

        foreach (['gemini', 'groq', 'openrouter', 'custom'] as $p) {
            $m = isset($in['model_' . $p]) ? trim(sanitize_text_field(wp_unslash($in['model_' . $p]))) : '';
            if ($m === '__custom') $m = isset($in['model_' . $p . '_custom']) ? trim(sanitize_text_field(wp_unslash($in['model_' . $p . '_custom']))) : '';
            if ($m !== '' && !preg_match('~^[\w.:/\-]+$~', $m)) $m = '';
            if ($m !== '' || $p === 'custom') $new['model_' . $p] = $m;
        }

        $valid = ['gemini', 'groq', 'openrouter', 'custom'];
        foreach (['chain_decide', 'chain_write'] as $c) {
            $arr = isset($in[$c]) && is_array($in[$c]) ? array_map('sanitize_key', wp_unslash($in[$c])) : [];
            $arr = array_values(array_unique(array_filter($arr, function ($x) use ($valid) { return in_array($x, $valid, true); })));
            $new[$c] = $arr;
        }

        $new['token_cap'] = max(10000, min(5000000, (int) ($in['token_cap'] ?? 150000)));
        $au = isset($in['autonomy']) ? sanitize_key($in['autonomy']) : 'suggest';
        $new['autonomy'] = in_array($au, ['suggest', 'semi', 'auto'], true) ? $au : 'suggest';
        $new['ai_proxy'] = empty($in['ai_proxy']) ? 0 : 1;
        update_option(self::OPT, $new, false);
        BP_AI::clear_model_cache();
    }

    private static function key() {
        return hash('sha256', wp_salt('auth'), true);
    }

    public static function enc($v) {
        if ($v === '' || !function_exists('openssl_encrypt')) return $v;
        $iv = random_bytes(16);
        $c = openssl_encrypt($v, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv);
        return 'enc:' . base64_encode($iv . $c);
    }

    public static function dec($v) {
        if (!$v) return '';
        if (strpos($v, 'enc:') !== 0) return $v;
        if (!function_exists('openssl_decrypt')) return '';
        $r = base64_decode(substr($v, 4), true);
        if ($r === false || strlen($r) < 17) return '';
        $p = openssl_decrypt(substr($r, 16), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr($r, 0, 16));
        return $p === false ? '' : $p;
    }
}

/* ============================================================
 * includes/class-db.php
 * ============================================================ */
class BP_DB {
    const VER = '1.7.0';

    public static function t($n) {
        global $wpdb;
        return $wpdb->prefix . 'bp_' . $n;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE " . self::t('sources') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL DEFAULT '',
  url text NOT NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  last_sync datetime NULL,
  last_count int(11) NOT NULL DEFAULT 0,
  last_error text NULL,
  PRIMARY KEY  (id)
) $c;");

        dbDelta("CREATE TABLE " . self::t('domains') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  domain varchar(190) NOT NULL,
  source_id bigint(20) unsigned NOT NULL DEFAULT 0,
  seed tinyint(1) NOT NULL DEFAULT 0,
  category varchar(40) NOT NULL DEFAULT '',
  niches varchar(200) NOT NULL DEFAULT '',
  method varchar(20) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'pending',
  http_code smallint(6) NOT NULL DEFAULT 0,
  resp_ms int(11) NOT NULL DEFAULT 0,
  lang varchar(10) NOT NULL DEFAULT '',
  outlinks int(11) NOT NULL DEFAULT 0,
  nofollow_ratio tinyint(4) NULL,
  submit_url text NULL,
  opr decimal(4,2) NULL,
  score tinyint(3) unsigned NOT NULL DEFAULT 0,
  notes varchar(255) NOT NULL DEFAULT '',
  last_checked datetime NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY domain (domain),
  KEY status (status),
  KEY score (score)
) $c;");

        dbDelta("CREATE TABLE " . self::t('campaigns') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL DEFAULT '',
  brand varchar(190) NOT NULL DEFAULT '',
  url varchar(500) NOT NULL DEFAULT '',
  topic text NULL,
  keywords text NULL,
  lang varchar(10) NOT NULL DEFAULT 'fa',
  daily_cap smallint(6) NOT NULL DEFAULT 3,
  weekly_cap smallint(6) NOT NULL DEFAULT 10,
  active tinyint(1) NOT NULL DEFAULT 1,
  source varchar(10) NOT NULL DEFAULT 'manual',
  niche varchar(40) NOT NULL DEFAULT '',
  niche_only tinyint(1) NOT NULL DEFAULT 0,
  pillar_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  profile longtext NULL,
  profile_at datetime NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id)
) $c;");

        dbDelta("CREATE TABLE " . self::t('pillars') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  role varchar(10) NOT NULL DEFAULT 'cluster',
  url varchar(500) NOT NULL DEFAULT '',
  title varchar(300) NOT NULL DEFAULT '',
  keywords text NULL,
  summary text NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY camp_post (campaign_id,post_id),
  KEY campaign_id (campaign_id)
) $c;");

        dbDelta("CREATE TABLE " . self::t('plans') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  domain varchar(190) NOT NULL DEFAULT '',
  method varchar(20) NOT NULL DEFAULT '',
  anchor_type varchar(20) NOT NULL DEFAULT '',
  anchor_text varchar(255) NOT NULL DEFAULT '',
  target_url varchar(500) NOT NULL DEFAULT '',
  reason varchar(400) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'proposed',
  article_title varchar(300) NOT NULL DEFAULT '',
  article_body longtext NULL,
  email_subject varchar(300) NOT NULL DEFAULT '',
  email_body longtext NULL,
  fails tinyint(3) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NULL,
  pub_platform varchar(20) NOT NULL DEFAULT '',
  pub_url varchar(500) NOT NULL DEFAULT '',
  pub_status varchar(20) NOT NULL DEFAULT '',
  pub_error varchar(300) NOT NULL DEFAULT '',
  pub_check varchar(20) NOT NULL DEFAULT '',
  pub_checked_at datetime NULL,
  published_at datetime NULL,
  campaign_name varchar(190) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY camp_domain (campaign_id,domain),
  KEY status (status)
) $c;");

        dbDelta("CREATE TABLE " . self::t('logs') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  kind varchar(20) NOT NULL DEFAULT '',
  level varchar(10) NOT NULL DEFAULT 'info',
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  message text NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) $c;");

        dbDelta("CREATE TABLE " . self::t('radar') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  plan_id bigint(20) unsigned NOT NULL,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  pub_url varchar(500) NOT NULL DEFAULT '',
  target_url varchar(500) NOT NULL DEFAULT '',
  platform varchar(20) NOT NULL DEFAULT '',
  anchor_type varchar(20) NOT NULL DEFAULT '',
  anchor_text varchar(255) NOT NULL DEFAULT '',
  published_at datetime NULL,
  created_at datetime NOT NULL,
  tech_status varchar(12) NOT NULL DEFAULT '',
  tech_note varchar(200) NOT NULL DEFAULT '',
  tech_at datetime NULL,
  link_state varchar(12) NOT NULL DEFAULT '',
  idx_status varchar(14) NOT NULL DEFAULT 'pending',
  idx_checks smallint(6) NOT NULL DEFAULT 0,
  idx_at datetime NULL,
  idx_first datetime NULL,
  ping_at datetime NULL,
  b_clicks int(11) NOT NULL DEFAULT 0,
  b_impr int(11) NOT NULL DEFAULT 0,
  b_pos decimal(7,2) NOT NULL DEFAULT 0,
  a_clicks int(11) NOT NULL DEFAULT 0,
  a_impr int(11) NOT NULL DEFAULT 0,
  a_pos decimal(7,2) NOT NULL DEFAULT 0,
  a_days smallint(6) NOT NULL DEFAULT 0,
  gsc_at datetime NULL,
  gsc_final tinyint(1) NOT NULL DEFAULT 0,
  gsc_err varchar(200) NOT NULL DEFAULT '',
  shared smallint(6) NOT NULL DEFAULT 0,
  impact smallint(6) NOT NULL DEFAULT 0,
  verdict varchar(12) NOT NULL DEFAULT 'pending',
  next_check datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY plan_id (plan_id),
  KEY next_check (next_check),
  KEY platform (platform)
) $c;");

        // v1.5: یک دامنه (مثلاً telegra.ph) باید بتواند چند بار در یک کمپین استفاده شود
        $tp = self::t('plans');
        $idx = $wpdb->get_row("SHOW INDEX FROM $tp WHERE Key_name='camp_domain'");
        if ($idx && (int) $idx->Non_unique === 0) {
            $wpdb->query("ALTER TABLE $tp DROP INDEX camp_domain, ADD INDEX camp_domain (campaign_id,domain)");
        }

        update_option('bp_db_version', self::VER, false);
    }

    /** بعد از ارتقا: لیست بزرگ جدید (با حوزه‌ها) یک بار وارد شود */
    public static function seed_upgrade() {
        if (get_option('bp_seed_v15') || !get_option('bp_bootstrapped')) return;
        BP_Seed::import();
    }

    public static function maybe_upgrade() {
        if (get_option('bp_db_version') !== self::VER) {
            self::install();
        }
        self::seed_upgrade();
    }
}

/* ============================================================
 * includes/class-log.php
 * ============================================================ */
class BP_Log {
    public static function add($kind, $message, $level = 'info', $campaign_id = 0) {
        global $wpdb;
        $t = BP_DB::t('logs');
        $wpdb->insert($t, [
            'created_at'  => current_time('mysql'),
            'kind'        => substr(sanitize_key($kind), 0, 20),
            'level'       => in_array($level, ['info', 'warn', 'error'], true) ? $level : 'info',
            'campaign_id' => (int) $campaign_id,
            'message'     => mb_substr((string) $message, 0, 1500),
        ]);
        if (mt_rand(1, 25) === 1) { // نگه‌داشتن ۵۰۰۰ لاگ آخر
            $cut = $wpdb->get_var("SELECT id FROM $t ORDER BY id DESC LIMIT 1 OFFSET 5000");
            if ($cut) $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE id <= %d", $cut));
        }
    }
}

/* ============================================================
 * BP_Lock — قفل اتمیک مبتنی بر wp_options (INSERT IGNORE یکتا)
 * ============================================================ */
class BP_Lock {
    public static function acquire($name, $ttl) {
        global $wpdb;
        $key = 'bp_lk_' . sanitize_key($name);
        for ($i = 0; $i < 2; $i++) {
            $now = time();
            $val = (string) ($now + (int) $ttl);
            $ins = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $val));
            if ($ins === 1) return true;
            $cur = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $key));
            if ($cur === null) continue; // بین دو کوئری آزاد شد؛ یک بار دیگر تلاش کن
            if ((int) $cur < $now) {     // قفل منقضی (اجرای قبلی مرده)؛ جایگزینی اتمیک با مقایسه‌ی مقدار قبلی
                $upd = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s", $val, $key, $cur));
                return $upd === 1;
            }
            return false;
        }
        return false;
    }

    public static function release($name) {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s", 'bp_lk_' . sanitize_key($name)));
    }
}

/* ============================================================
 * includes/class-http.php
 * ============================================================ */
/**
 * همه‌ی درخواست‌های خروجی از اینجا رد می‌شوند:
 * - پیش‌فرض wp_safe_remote_* (محافظت SSRF: آی‌پی‌های داخلی بلاک می‌شوند)
 * - پشتیبانی از پروکسی (برای هاست‌های ایران)
 */
class BP_Http {
    private static $proxy = '';

    public static function curl($handle) {
        if (self::$proxy !== '') {
            curl_setopt($handle, CURLOPT_PROXY, self::$proxy);
        }
    }

    public static function get($url, $args = [], $use_proxy = true, $safe = true) {
        return self::request('GET', $url, $args, $use_proxy, $safe);
    }

    /** $use_proxy: استفاده از پروکسی تنظیمات؛ $safe=false فقط برای سرور لوکال AI که ادمین خودش وارد کرده */
    public static function post($url, $args = [], $use_proxy = true, $safe = true) {
        return self::request('POST', $url, $args, $use_proxy, $safe);
    }

    private static function request($method, $url, $args, $use_proxy, $safe) {
        $s = BP_Settings::all();
        $args = wp_parse_args($args, [
            'timeout'             => 12,
            'redirection'         => 4,
            'user-agent'          => $s['user_agent'],
            'limit_response_size' => 600000,
        ]);

        // امنیت: هرگز تأیید گواهی SSL را غیرفعال نمی‌کنیم (کلیدهای API در هدرها ارسال می‌شوند).
        // اگر فراخواننده خودش sslverify=false بفرستد هم نادیده گرفته می‌شود.
        $args['sslverify'] = true;

        self::$proxy = $use_proxy ? $s['proxy'] : '';
        if (self::$proxy !== '') {
            add_action('http_api_curl', [__CLASS__, 'curl']);
        }

        if ($method === 'POST') {
            $fn = $safe ? 'wp_safe_remote_post' : 'wp_remote_post';
        } else {
            $fn = $safe ? 'wp_safe_remote_get' : 'wp_remote_get';
        }
        $res = $fn($url, $args);

        if (is_wp_error($res)) {
            $m = $res->get_error_message();
            if (stripos($m, 'ssl') !== false || stripos($m, 'cURL error 60') !== false) {
                $res = new WP_Error('bp_ssl', 'خطای گواهی SSL (اتصال برای حفظ امنیت قطع شد): ' . $m);
            }
        }

        if (self::$proxy !== '') {
            remove_action('http_api_curl', [__CLASS__, 'curl']);
        }
        self::$proxy = '';
        return $res;
    }
}

/* ============================================================
 * includes/class-sources.php
 * ============================================================ */
class BP_Sources {

    private static $block_hosts = [
        'github.com', 'githubusercontent.com', 'githubassets.com', 'shields.io', 'img.shields.io',
        'travis-ci.org', 'travis-ci.com', 'codecov.io', 'badgen.net', 'w3.org', 'schema.org',
        'creativecommons.org', 'opensource.org', 'gnu.org', 'apache.org', 'localhost', 'example.com',
        'example.org', 'google.com', 'googleapis.com', 'gstatic.com', 'wikipedia.org', 'wikimedia.org',
    ];

    private static $bad_tlds = [
        'md', 'js', 'json', 'txt', 'csv', 'py', 'php', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'yml',
        'yaml', 'sh', 'rb', 'go', 'rs', 'ts', 'xml', 'pdf', 'html', 'htm', 'zip', 'gz', 'log', 'lock', 'toml',
    ];

    /** تبدیل لینک گیتهاب به آدرس قابل دریافت */
    public static function resolve($url) {
        $url = trim($url);
        if (preg_match('~^https?://github\.com/([^/]+)/([^/]+)/blob/([^/]+)/(.+)$~i', $url, $m)) {
            return ['url' => "https://raw.githubusercontent.com/{$m[1]}/{$m[2]}/{$m[3]}/{$m[4]}", 'api' => false];
        }
        if (preg_match('~^https?://github\.com/([^/]+)/([^/#?]+?)(?:\.git)?(?:/tree/[^/]+)?/?(?:[#?].*)?$~i', $url, $m)) {
            return ['url' => "https://api.github.com/repos/{$m[1]}/{$m[2]}/readme", 'api' => true];
        }
        return ['url' => $url, 'api' => false];
    }

    public static function fetch($url) {
        $r = self::resolve($url);
        $s = BP_Settings::all();
        $headers = [];
        if ($r['api']) {
            $headers['Accept'] = 'application/vnd.github.raw';
            $headers['X-GitHub-Api-Version'] = '2022-11-28';
            if ($s['github_token'] !== '') {
                $headers['Authorization'] = 'Bearer ' . $s['github_token'];
            }
        }
        $res = BP_Http::get($r['url'], ['headers' => $headers, 'timeout' => 30, 'limit_response_size' => 8000000]);
        if (is_wp_error($res)) return $res;
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code === 403 || $code === 429) {
            return new WP_Error('bp_rate', 'محدودیت نرخ گیتهاب (کد ' . $code . '). توکن گیتهاب را در تنظیمات وارد کنید.');
        }
        if ($code !== 200) {
            return new WP_Error('bp_http', 'پاسخ نامعتبر از منبع: کد ' . $code);
        }
        return wp_remote_retrieve_body($res);
    }

    /** استخراج دامنه‌های یکتا از متن (markdown / csv / txt / json) */
    public static function extract($text) {
        $found = [];
        if (preg_match_all('~https?://[^\s<>()\[\]"\'`,;|\\\\]+~i', $text, $m)) {
            foreach ($m[0] as $u) {
                $h = parse_url($u, PHP_URL_HOST);
                if ($h) $found[] = $h;
            }
        }
        if (preg_match_all('~(?<![@\w./:-])((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24})(?![\w@-])~i', $text, $m2)) {
            foreach ($m2[1] as $h) $found[] = $h;
        }
        $out = [];
        foreach ($found as $h) {
            $d = self::clean($h);
            if ($d) $out[$d] = true;
        }
        return array_keys($out);
    }

    public static function clean($host) {
        $h = strtolower(trim($host, " \t\n\r\0\x0B."));
        $h = preg_replace('~^www\.~', '', $h);
        if ($h === '' || strlen($h) > 190 || strpos($h, '.') === false) return '';
        if (!preg_match('~^[a-z0-9.-]+$~', $h)) return '';
        if (preg_match('~^\d+\.\d+\.\d+\.\d+$~', $h)) return '';
        $tld = substr(strrchr($h, '.'), 1);
        if (in_array($tld, self::$bad_tlds, true)) return '';
        foreach (self::$block_hosts as $b) {
            if ($h === $b || substr($h, -strlen($b) - 1) === '.' . $b) return '';
        }
        return $h;
    }

    /** درج دامنه‌ها (INSERT IGNORE)، تعداد جدیدها را برمی‌گرداند */
    public static function insert_domains(array $domains, $source_id = 0) {
        global $wpdb;
        $t = BP_DB::t('domains');
        $now = current_time('mysql');
        $new = 0;
        foreach (array_chunk($domains, 200) as $chunk) {
            $vals = [];
            foreach ($chunk as $d) {
                $vals[] = $wpdb->prepare('(%s,%d,%s,%s)', $d, $source_id, 'pending', $now);
            }
            $sql = "INSERT IGNORE INTO $t (domain, source_id, status, created_at) VALUES " . implode(',', $vals);
            $r = $wpdb->query($sql);
            if ($r) $new += (int) $r;
        }
        return $new;
    }

    public static function sync($id) {
        global $wpdb;
        $ts = BP_DB::t('sources');
        $src = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ts WHERE id=%d", $id));
        if (!$src) return ['new' => 0, 'total' => 0, 'error' => 'منبع پیدا نشد'];

        $body = self::fetch($src->url);
        if (is_wp_error($body)) {
            $wpdb->update($ts, ['last_sync' => current_time('mysql'), 'last_error' => $body->get_error_message()], ['id' => $id]);
            return ['new' => 0, 'total' => 0, 'error' => $body->get_error_message()];
        }
        $domains = self::extract($body);
        $new = self::insert_domains($domains, (int) $id);
        $wpdb->update($ts, [
            'last_sync'  => current_time('mysql'),
            'last_count' => count($domains),
            'last_error' => '',
        ], ['id' => $id]);
        return ['new' => $new, 'total' => count($domains), 'error' => ''];
    }

    public static function sync_all() {
        global $wpdb;
        $ids = $wpdb->get_col('SELECT id FROM ' . BP_DB::t('sources') . ' WHERE active=1');
        $sum = ['new' => 0, 'total' => 0, 'errors' => 0];
        foreach ($ids as $id) {
            $r = self::sync((int) $id);
            $sum['new'] += $r['new'];
            $sum['total'] += $r['total'];
            if ($r['error']) $sum['errors']++;
        }
        return $sum;
    }
}

/* ============================================================
 * includes/class-seed.php
 * ============================================================ */
/**
 * لیست اولیه‌ی پلتفرم‌های رایگان. فرمت: دامنه|دسته|روش|زبان
 * روش‌ها: api = انتشار خودکار ممکن، manual = صف دستی
 * وضعیت follow/nofollow عمداً ثبت نشده؛ تحلیل و بررسی خودت تعیین می‌کند.
 */
class BP_Seed {

    public static function lines() {
        return [
            // بلاگ / مقاله
            'telegra.ph|blog|api|', 'dev.to|blog|api|en', 'hashnode.com|blog|api|en', 'wordpress.com|blog|api|',
            'blogger.com|blog|api|', 'tumblr.com|blog|api|en', 'write.as|blog|api|en',
            'medium.com|blog|manual|en', 'substack.com|blog|manual|en', 'bearblog.dev|blog|manual|en',
            'hatenablog.com|blog|manual|ja', 'livejournal.com|blog|manual|', 'weebly.com|blog|manual|en',
            'wix.com|blog|manual|en', 'strikingly.com|blog|manual|en', 'jimdo.com|blog|manual|en',
            'site123.com|blog|manual|en', 'webnode.com|blog|manual|en', 'yola.com|blog|manual|en',
            'over-blog.com|blog|manual|', 'wikidot.com|blog|manual|en', 'notion.so|blog|manual|en',
            'hubpages.com|blog|manual|en', 'vocal.media|blog|manual|en', 'hackernoon.com|guest|manual|en',
            'linkedin.com|blog|manual|en', 'mirror.xyz|blog|manual|en', 'minds.com|blog|manual|en',
            'steemit.com|blog|manual|en',
            // فارسی
            'virgool.io|persian|manual|fa', 'blogfa.com|persian|manual|fa', 'mihanblog.com|persian|manual|fa',
            'rozblog.com|persian|manual|fa', 'parsiblog.com|persian|manual|fa', 'loxblog.com|persian|manual|fa',
            'blogsky.com|persian|manual|fa', 'aparat.com|persian|manual|fa', 'rubika.ir|persian|manual|fa',
            'eitaa.com|persian|manual|fa', 'bale.ai|persian|manual|fa', 't.me|persian|manual|',
            'neshan.org|local|manual|fa', 'balad.ir|local|manual|fa',
            // پروفایل / شبکه‌های اجتماعی
            'facebook.com|social|manual|', 'x.com|social|manual|', 'instagram.com|social|manual|',
            'pinterest.com|social|manual|', 'reddit.com|social|manual|en', 'quora.com|social|manual|en',
            'behance.net|social|manual|en', 'dribbble.com|social|manual|en', 'flickr.com|social|manual|en',
            'about.me|social|manual|en', 'linktr.ee|social|manual|en', 'gravatar.com|social|manual|en',
            'disqus.com|social|manual|en', 'goodreads.com|social|manual|en', 'mastodon.social|social|manual|en',
            'bsky.app|social|manual|en', 'threads.net|social|manual|', 'youtube.com|social|manual|',
            'vimeo.com|social|manual|en', 'dailymotion.com|social|manual|', 'soundcloud.com|social|manual|en',
            'twitch.tv|social|manual|en', 'producthunt.com|directory|manual|en', 'crunchbase.com|directory|manual|en',
            'wellfound.com|directory|manual|en', 'indiehackers.com|forum|manual|en',
            'stackoverflow.com|forum|manual|en', 'stackexchange.com|forum|manual|en',
            // سند و اسلاید
            'slideshare.net|docs|manual|en', 'scribd.com|docs|manual|en', 'issuu.com|docs|manual|en',
            'calameo.com|docs|manual|en', 'yumpu.com|docs|manual|en', 'slideserve.com|docs|manual|en',
            'authorstream.com|docs|manual|en', 'academia.edu|docs|manual|en', 'researchgate.net|docs|manual|en',
            'figshare.com|docs|manual|en', 'zenodo.org|docs|manual|en',
            // توسعه‌دهنده
            'github.com|dev|manual|en', 'gist.github.com|dev|manual|en', 'gitlab.com|dev|manual|en',
            'bitbucket.org|dev|manual|en', 'codepen.io|dev|manual|en', 'jsfiddle.net|dev|manual|en',
            'replit.com|dev|manual|en', 'glitch.com|dev|manual|en', 'npmjs.com|dev|manual|en',
            'pypi.org|dev|manual|en', 'packagist.org|dev|manual|en', 'wordpress.org|dev|manual|en',
            'hub.docker.com|dev|manual|en', 'huggingface.co|dev|manual|en', 'kaggle.com|dev|manual|en',
            'sourceforge.net|dev|manual|en', 'news.ycombinator.com|forum|manual|en', 'lobste.rs|forum|manual|en',
            // دایرکتوری ابزار / استارتاپ
            'betalist.com|directory|manual|en', 'alternativeto.net|directory|manual|en',
            'saashub.com|directory|manual|en', 'uneed.best|directory|manual|en',
            'startupstash.com|directory|manual|en', 'launchingnext.com|directory|manual|en',
            'futurepedia.io|directory|manual|en', 'theresanaiforthat.com|directory|manual|en',
            'g2.com|directory|manual|en', 'capterra.com|directory|manual|en', 'goodfirms.co|directory|manual|en',
            'clutch.co|directory|manual|en', 'sitejabber.com|directory|manual|en', 'trustpilot.com|directory|manual|',
            'provenexpert.com|directory|manual|',
            // سئوی محلی / citation
            'business.google.com|local|manual|', 'bingplaces.com|local|manual|', 'businessconnect.apple.com|local|manual|',
            'yelp.com|local|manual|en', 'foursquare.com|local|manual|en', 'yellowpages.com|local|manual|en',
            'hotfrog.com|local|manual|en', 'brownbook.net|local|manual|en', 'manta.com|local|manual|en',
            'merchantcircle.com|local|manual|en', 'tupalo.com|local|manual|en', 'showmelocal.com|local|manual|en',
            'ezlocal.com|local|manual|en',
            // مطلب مهمان / PR
            'business2community.com|guest|manual|en', 'qwoted.com|guest|manual|en',
            'sourcebottle.com|guest|manual|en', 'featured.com|guest|manual|en',
            // انجمن
            'webmasterworld.com|forum|manual|en', 'digitalpoint.com|forum|manual|en',
            'sitepoint.com|forum|manual|en', 'moz.com|forum|manual|en',
        ];
    }

    /** حوزه‌ها: slug => برچسب */
    public static function niches() {
        return [
            'general' => 'عمومی (همه‌ی موضوعات)', 'tech' => 'فناوری و گجت', 'dev' => 'برنامه‌نویسی و توسعه', 'security' => 'امنیت سایبری',
            'ai' => 'هوش مصنوعی و داده', 'marketing' => 'بازاریابی، سئو و وبلاگ‌نویسی', 'business' => 'کسب‌وکار و استارتاپ',
            'ecommerce' => 'فروشگاه آنلاین', 'finance' => 'مالی، سرمایه‌گذاری و ارز دیجیتال', 'health' => 'سلامت و پزشکی',
            'fitness' => 'ورزش و تناسب اندام', 'beauty' => 'زیبایی و مد', 'food' => 'غذا و نوشیدنی', 'travel' => 'سفر و گردشگری',
            'education' => 'آموزش و دانشگاه', 'science' => 'علم و طبیعت', 'art' => 'هنر و طراحی', 'photo' => 'عکاسی و ازدواج',
            'music' => 'موسیقی و پادکست', 'books' => 'کتاب و نویسندگی', 'gaming' => 'بازی', 'entertainment' => 'فیلم، سریال و انیمه',
            'news' => 'خبر و رسانه', 'realestate' => 'املاک', 'auto' => 'خودرو و موتور', 'home' => 'خانه، باغ و DIY',
            'hobby' => 'سرگرمی و کاردستی', 'parenting' => 'خانواده و کودک', 'pets' => 'حیوانات خانگی', 'legal' => 'حقوقی و مهاجرت',
            'jobs' => 'کار و استخدام', 'nonprofit' => 'خیریه و اجتماعی',
        ];
    }

    /**
     * لیست بزرگ پیش‌فرض. هر گروه: [حوزه‌ها (با کاما), دسته, روش, زبان, دامنه‌ها (با فاصله)]
     * دسته: blog social docs dev directory local guest forum press bookmark persian ...
     * روش: signup (حساب/پروفایل رایگان)، guest (مطلب مهمان)، manual (ثبت/نظر دستی)
     * توجه: زنده بودن و سادگی ثبت را «تحلیل خودکار» بررسی می‌کند؛ دامنه‌های مرده امتیاز صفر می‌گیرند و حذف می‌شوند.
     */
    public static function groups() {
        return [
            // ---------- عمومی ----------
            ['general', 'blog', 'signup', 'en', 'wordpress.com blogger.com tumblr.com medium.com weebly.com wix.com jimdofree.com site123.com webnode.com yola.com strikingly.com ghost.org substack.com bearblog.dev write.as telegra.ph livejournal.com typepad.com sites.google.com notion.site hubpages.com edublogs.org blog.com over-blog.com canalblog.com skyrock.com tripod.lycos.com angelfire.com webs.com snappages.com carrd.co bio.link linktr.ee about.me flavors.me contently.com journoportfolio.com clippings.me authory.com vocal.media minds.com steemit.com mirror.xyz squarespace.com hatenablog.com livedoor.com fc2.com ameblo.jp seesaa.net note.com tistory.com pixnet.net blog.naver.com beacons.ai taplink.cc'],
            ['general', 'social', 'signup', 'en', 'facebook.com x.com instagram.com pinterest.com linkedin.com reddit.com quora.com flickr.com vimeo.com youtube.com dailymotion.com soundcloud.com mixcloud.com gravatar.com disqus.com mastodon.social bsky.app threads.net tiktok.com snapchat.com vk.com ok.ru weibo.com twitch.tv kick.com rumble.com odysee.com bitchute.com vero.co mewe.com plurk.com hubski.com myspace.com nextdoor.com meetup.com eventbrite.com diaspora.social lemmy.world'],
            ['general', 'docs', 'signup', 'en', 'slideshare.net scribd.com issuu.com calameo.com yumpu.com slideserve.com authorstream.com docdroid.net speakerdeck.com prezi.com canva.com visme.co genial.ly emaze.com flipsnack.com joomag.com fliphtml5.com anyflip.com smore.com padlet.com wakelet.com livebinders.com pdfcoffee.com'],
            ['general', 'bookmark', 'signup', 'en', 'digg.com mix.com flipboard.com scoop.it pearltrees.com diigo.com getpocket.com raindrop.io folkd.com slashdot.org fark.com metafilter.com alltop.com feedspot.com feedly.com inoreader.com dzone.com digitaljournal.com infobarrel.com dailykos.com patch.com newsbreak.com'],
            ['general', 'directory', 'manual', 'en', 'aboutus.org hotfrog.com brownbook.net cybo.com tupalo.com yelp.com foursquare.com yellowpages.com yell.com manta.com merchantcircle.com showmelocal.com ezlocal.com kompass.com europages.com local.com superpages.com chamberofcommerce.com bbb.org thumbtack.com angi.com porch.com yellowbot.com 2findlocal.com hub.biz opendi.com expressbusinessdirectory.com bizvotes.com citysquares.com n49.com localdatabase.com justdial.com sulekha.com indiamart.com tradeindia.com yellowpages.ca truelocal.com.au yellowpages.com.au hotfrog.com.au pagesjaunes.fr gelbeseiten.de paginasamarillas.es paginegialle.it goudengids.nl gulesider.no eniro.se'],
            ['general', 'forum', 'signup', 'en', 'stackexchange.com wikihow.com city-data.com answers.com ask.com kialo.com lemmy.ml tapatalk.com proboards.com forumotion.com boards.ie neowin.net'],
            ['general', 'platform', 'signup', 'en', 'notion.so producthunt.com archive.org openlibrary.org fandom.com wikidot.com'],

            // ---------- فارسی ----------
            ['general', 'persian', 'signup', 'fa', 'virgool.io blogfa.com mihanblog.com rozblog.com parsiblog.com loxblog.com blogsky.com persianblog.ir aparat.com namasha.com rubika.ir eitaa.com bale.ai t.me ninisite.com barnamenevis.org persiantools.com p30world.com digiato.com zoomit.ir bayanbox.ir tebyan.net niazerooz.com basalam.com torob.com emalls.ir neshan.org balad.ir'],
            ['jobs', 'persian', 'signup', 'fa', 'jobinja.ir jobvision.ir karlancer.com ponisha.ir parscoders.com iranestekhdam.ir e-estekhdam.com'],
            ['realestate', 'persian', 'signup', 'fa', 'kilid.com divar.ir sheypoor.com melkradar.com'],
            ['auto', 'persian', 'signup', 'fa', 'bama.ir divar.ir'],
            ['travel', 'persian', 'signup', 'fa', 'alibaba.ir flightio.com jabama.com snapptrip.com safarmarket.com mrbilit.com hamgardi.com'],
            ['ecommerce', 'persian', 'signup', 'fa', 'basalam.com torob.com emalls.ir divar.ir sheypoor.com niazerooz.com'],
            ['parenting', 'persian', 'signup', 'fa', 'ninisite.com'],
            ['tech,dev', 'persian', 'signup', 'fa', 'barnamenevis.org p30world.com digiato.com zoomit.ir'],
            ['music', 'persian', 'signup', 'fa', 'radiojavan.com'],

            // ---------- فناوری ----------
            ['tech', 'forum', 'signup', 'en', 'tomshardware.com overclock.net techpowerup.com forum.xda-developers.com androidcentral.com androidforums.com macrumors.com techspot.com windowsforum.com superuser.com askubuntu.com serverfault.com linuxquestions.org ubuntuforums.org bbs.archlinux.org hardforum.com tweaktown.com guru3d.com notebookreview.com head-fi.org avsforum.com audiosciencereview.com diyaudio.com raspberrypi.com forums.raspberrypi.com arduino.cc hackaday.io instructables.com makezine.com thingiverse.com printables.com hackster.io eevblog.com allaboutcircuits.com electronics-tutorials.ws circuitdigest.com electronicsforu.com'],
            ['tech', 'directory', 'signup', 'en', 'alternativeto.net slant.co stackshare.io g2.com capterra.com getapp.com softwareadvice.com trustradius.com sourceforge.net fosshub.com softpedia.com filehippo.com majorgeeks.com betalist.com saashub.com uneed.best startupstash.com launchingnext.com'],
            ['tech', 'guest', 'guest', 'en', 'hackernoon.com sitepoint.com smashingmagazine.com freecodecamp.org opensource.com thenewstack.io readwrite.com techgenix.com techbullion.com makeuseof.com hongkiat.com webdesignerdepot.com speckyboy.com designshack.net tecmint.com linuxhint.com howtoforge.com geekflare.com hostingtribunal.com itproportal.com'],

            // ---------- برنامه‌نویسی ----------
            ['dev', 'dev', 'signup', 'en', 'github.com gitlab.com bitbucket.org codepen.io jsfiddle.net replit.com glitch.com stackblitz.com codesandbox.io npmjs.com pypi.org packagist.org rubygems.org crates.io nuget.org hub.docker.com huggingface.co kaggle.com sourceforge.net codeberg.org gitea.com sr.ht launchpad.net gitee.com observablehq.com wokwi.com tinkercad.com scratch.mit.edu itch.io gamejolt.com gitbook.com readthedocs.org vercel.com netlify.com render.com railway.app fly.io surge.sh pythonanywhere.com openhub.net libhunt.com'],
            ['dev', 'forum', 'signup', 'en', 'stackoverflow.com dev.to hashnode.com codeproject.com geeksforgeeks.org dzone.com forem.com daily.dev lobste.rs news.ycombinator.com indiehackers.com codewars.com leetcode.com hackerrank.com hackerearth.com codeforces.com topcoder.com exercism.org frontendmentor.io codecademy.com scrimba.com hackr.io forum.unity.com godotengine.org gamedev.net gamedev.stackexchange.com css-tricks.com alistapart.com sitepoint.com'],
            ['dev', 'guest', 'guest', 'en', 'hackernoon.com freecodecamp.org smashingmagazine.com sitepoint.com dzone.com codeproject.com opensource.com thenewstack.io blog.logrocket.com'],

            // ---------- امنیت ----------
            ['security', 'forum', 'signup', 'en', 'hackthebox.com tryhackme.com bleepingcomputer.com thehackernews.com sans.org owasp.org exploit-db.com hackerone.com bugcrowd.com cybrary.it security.stackexchange.com wilderssecurity.com malwaretips.com virustotal.com securityweek.com darkreading.com infosecinstitute.com portswigger.net github.com'],
            ['security', 'guest', 'guest', 'en', 'infosecurity-magazine.com cybersecurity-insiders.com securityboulevard.com hackread.com cyberdefensemagazine.com'],

            // ---------- هوش مصنوعی ----------
            ['ai', 'directory', 'signup', 'en', 'futurepedia.io theresanaiforthat.com toolify.ai aitoolhunt.com topai.tools easywithai.com futuretools.io aixploria.com lablab.ai replicate.com civitai.com openrouter.ai gradio.app wandb.ai producthunt.com alternativeto.net saashub.com uneed.best betalist.com'],
            ['ai', 'forum', 'signup', 'en', 'huggingface.co discuss.huggingface.co kaggle.com lesswrong.com alignmentforum.org community.deeplearning.ai analyticsvidhya.com towardsdatascience.com kdnuggets.com datasciencecentral.com mlops.community community.openai.com'],
            ['ai', 'guest', 'guest', 'en', 'towardsai.net marktechpost.com unite.ai analyticsindiamag.com dataversity.net datanami.com kdnuggets.com'],

            // ---------- بازاریابی / سئو ----------
            ['marketing', 'forum', 'signup', 'en', 'moz.com webmasterworld.com digitalpoint.com warriorforum.com seochat.com sitepoint.com seroundtable.com searchenginepeople.com affilorama.com affpaying.com blackhatworld.com wickedfire.com'],
            ['marketing', 'guest', 'guest', 'en', 'searchenginejournal.com searchengineland.com contentmarketinginstitute.com copyblogger.com socialmediatoday.com socialmediaexaminer.com marketingland.com martech.org marketingprofs.com business2community.com bizsugar.com smallbiztrends.com entrepreneur.com inc.com problogger.com bloggingwizard.com wpbeginner.com wpexplorer.com wpmudev.com kinsta.com websitesetup.org hostingadvice.com whoishostingthis.com cxl.com unbounce.com optinmonster.com crazyegg.com'],
            ['marketing', 'directory', 'signup', 'en', 'similarweb.com builtwith.com websiteoutlook.com sitejabber.com trustpilot.com clutch.co goodfirms.co designrush.com themanifest.com upcity.com sortlist.com topdevelopers.co appfutura.com semrush.com hubspot.com'],
            ['marketing', 'press', 'manual', 'en', 'prlog.org openpr.com i-newswire.com free-press-release.com pressreleasepoint.com einpresswire.com pr-inside.com 24-7pressrelease.com pr.com express-press-release.net prurgent.com pressbox.co.uk onlineprnews.com pressat.co.uk mynewsdesk.com newswiretoday.com webwire.com pr-gb.com'],

            // ---------- کسب‌وکار / استارتاپ ----------
            ['business', 'directory', 'signup', 'en', 'producthunt.com betalist.com crunchbase.com wellfound.com indiehackers.com f6s.com startupranking.com gust.com startupstash.com launchingnext.com saasworthy.com g2.com capterra.com getapp.com softwareadvice.com trustradius.com alternativeto.net saashub.com sitejabber.com trustpilot.com clutch.co goodfirms.co fundera.com owler.com zoominfo.com dnb.com glassdoor.com ambitionbox.com comparably.com kununu.com alignable.com xing.com'],
            ['business', 'guest', 'guest', 'en', 'entrepreneur.com inc.com smallbiztrends.com startupnation.com business2community.com bizsugar.com e27.co techinasia.com yourstory.com inc42.com startups.com venturebeat.com'],
            ['business', 'forum', 'signup', 'en', 'indiehackers.com hackernoon.com news.ycombinator.com reddit.com quora.com score.org upwork.com fiverr.com freelancer.com toptal.com peopleperhour.com guru.com 99designs.com'],
            ['business', 'press', 'manual', 'en', 'prlog.org openpr.com i-newswire.com einpresswire.com pr.com'],

            // ---------- فروشگاه آنلاین ----------
            ['ecommerce', 'forum', 'signup', 'en', 'community.shopify.com bigcommerce.com woocommerce.com prestashop.com opencart.com ecwid.com sellfy.com gumroad.com payhip.com ko-fi.com buymeacoffee.com patreon.com ecommerce-platforms.com ecommercefuel.com junglescout.com marketplacepulse.com'],
            ['ecommerce', 'directory', 'signup', 'en', 'etsy.com ebay.com alibaba.com made-in-china.com dhgate.com redbubble.com society6.com zazzle.com cafepress.com threadless.com printful.com printify.com pricespy.co.uk pricerunner.com idealo.de bizrate.com trustedshops.com reviews.io trustpilot.com sitejabber.com'],
            ['ecommerce', 'guest', 'guest', 'en', 'practicalecommerce.com ecommercetimes.com digitalcommerce360.com ecommerce-nation.com'],

            // ---------- مالی ----------
            ['finance', 'forum', 'signup', 'en', 'bogleheads.org wallstreetoasis.com elitetrader.com tradingview.com stocktwits.com seekingalpha.com fool.com advfn.com moneysavingexpert.com bitcointalk.org coinmarketcap.com coingecko.com cryptopanic.com messari.io coinpaprika.com dappradar.com steemit.com hive.blog peakd.com publish0x.com read.cash'],
            ['finance', 'guest', 'guest', 'en', 'bitcoinmagazine.com cryptoslate.com cryptonews.com newsbtc.com beincrypto.com coinjournal.net ambcrypto.com u.today coinspeaker.com cryptobriefing.com blockonomi.com bitcoinist.com thepennyhoarder.com wisebread.com financialsamurai.com doughroller.net gobankingrates.com moneyunder30.com'],

            // ---------- سلامت ----------
            ['health', 'forum', 'signup', 'en', 'patient.info medhelp.org healingwell.com inspire.com dailystrength.org healthgrades.com vitals.com ratemds.com doximity.com sermo.com studentdoctor.net allnurses.com nurse.com diabetes.co.uk diabetesdaily.com psychologytoday.com 7cups.com mentalhealthforum.net supportgroups.com healthtap.com examine.com dietdoctor.com eatthismuch.com cronometer.com'],
            ['health', 'guest', 'guest', 'en', 'kevinmd.com healthcentral.com mindbodygreen.com community.thriveglobal.com mindful.org yogajournal.com doyou.com yogabasics.com livestrong.com'],

            // ---------- ورزش ----------
            ['fitness', 'forum', 'signup', 'en', 'myfitnesspal.com strava.com bodybuilding.com t-nation.com livestrong.com forums.runnersworld.com alltrails.com komoot.com fantasypros.com golfwrx.com mygolfspy.com bikeforums.net mtbr.com singletracks.com mountainproject.com swimswam.com triathlete.com slowtwitch.com sherdog.com tennisforum.com chess.com lichess.org chessbase.com bassresource.com thehulltruth.com rokslide.com archerytalk.com longrangehunting.com backpackinglight.com whiteblaze.net summitpost.org peakbagger.com trailjournals.com'],
            ['fitness', 'guest', 'guest', 'en', 'bleacherreport.com sportskeeda.com fansided.com thesportster.com sportsblog.com sbnation.com'],

            // ---------- زیبایی / مد ----------
            ['beauty', 'forum', 'signup', 'en', 'makeupalley.com lipstickalley.com longhaircareforum.com temptalia.com beautypedia.com thefashionspot.com purseforum.com styleforum.net superfuture.com bloglovin.com modelmayhem.com models.com onemodelplace.com fragrantica.com basenotes.com parfumo.com skincarisma.com incidecoder.com beautylish.com hairfinder.com nailsmag.com'],

            // ---------- غذا ----------
            ['food', 'forum', 'signup', 'en', 'allrecipes.com food.com cookpad.com seriouseats.com chowhound.com cooks.com justapinch.com yummly.com tasty.co eatingwell.com delish.com simplyrecipes.com foodgawker.com foodista.com recipe.com bigoven.com egullet.org foodbloggerpro.com zomato.com tripadvisor.com opentable.com happycow.net vegweb.com homebrewtalk.com brewersfriend.com untappd.com ratebeer.com vivino.com cellartracker.com coffeegeek.com home-barista.com smokingmeatforums.com bbq-brethren.com amazingribs.com thefreshloaf.com cheftalk.com pastrywiz.com cakecentral.com cakesdecor.com tasteatlas.com'],

            // ---------- سفر ----------
            ['travel', 'forum', 'signup', 'en', 'tripadvisor.com lonelyplanet.com fodors.com travelblog.org travelpod.com nomadlist.com workaway.info couchsurfing.com hostelworld.com flyertalk.com boardingarea.com wikivoyage.org atlasobscura.com worldnomads.com gonomad.com transitionsabroad.com bootsnall.com travelfish.org expatforum.com expat.com internations.org expatica.com roadtrippers.com tripit.com wanderlog.com campendium.com freecampsites.net hipcamp.com rvtravel.com cruisecritic.com epicski.com skiresort.info scubaboard.com surfline.com viator.com getyourguide.com klook.com'],
            ['travel', 'guest', 'guest', 'en', 'matadornetwork.com gonomad.com transitionsabroad.com bootsnall.com'],

            // ---------- آموزش ----------
            ['education', 'platform', 'signup', 'en', 'udemy.com skillshare.com teachable.com thinkific.com podia.com khanacademy.org edx.org futurelearn.com classcentral.com alison.com openlearning.com teacherspayteachers.com teachers.net quizlet.com brainly.com coursehero.com studocu.com duolingo.com italki.com preply.com verbling.com wyzant.com varsitytutors.com wikibooks.org wikiversity.org hellotalk.com tandem.net interpals.net conversationexchange.com wordreference.com'],
            ['education', 'docs', 'signup', 'en', 'academia.edu researchgate.net mendeley.com zotero.org orcid.org semanticscholar.org ssrn.com osf.io figshare.com zenodo.org authorea.com overleaf.com thegradcafe.com thestudentroom.co.uk talk.collegeconfidential.com collegedunia.com shiksha.com careers360.com'],

            // ---------- علم ----------
            ['science', 'forum', 'signup', 'en', 'physicsforums.com scienceforums.net biology-online.org protocols.io labroots.com scienceblogs.com cloudynights.com universetoday.com astronomy.com nasaspaceflight.com astrobin.com in-the-sky.org chemistry.stackexchange.com chemicalforums.com math.stackexchange.com mathoverflow.net artofproblemsolving.com brilliant.org biostars.org seqanswers.com zooniverse.org inaturalist.org ebird.org bugguide.net mindat.org treehugger.com ecowatch.com grist.org earth911.com cleantechnica.com diysolarforum.com'],

            // ---------- هنر / طراحی ----------
            ['art', 'social', 'signup', 'en', 'behance.net dribbble.com deviantart.com artstation.com cgsociety.org cgtrader.com sketchfab.com blendswap.com blenderartists.org polycount.com conceptart.org saatchiart.com artsy.net fineartamerica.com pixels.com inprnt.com dafont.com fontsquirrel.com creativemarket.com designspiration.com awwwards.com cssdesignawards.com thefwa.com siteinspire.com land-book.com httpster.net coroflot.com carbonmade.com format.com cargo.site readymag.com myportfolio.com figma.com uxdesign.cc uxplanet.org uxmatters.com freepik.com flaticon.com'],
            ['art', 'guest', 'guest', 'en', 'smashingmagazine.com webdesignerdepot.com designmodo.com designshack.net abduzeedo.com designboom.com archdaily.com architizer.com core77.com yankodesign.com boredpanda.com thisiscolossal.com juxtapoz.com booooooom.com ignant.com remodelista.com'],

            // ---------- عکاسی ----------
            ['photo', 'social', 'signup', 'en', '500px.com flickr.com unsplash.com pexels.com pixabay.com photo.net dpreview.com fredmiranda.com luminous-landscape.com viewbug.com gurushots.com photocrowd.com photoblog.com zenfolio.com smugmug.com pbase.com photoshelter.com pixieset.com exposure.co ipernity.com trekearth.com lensculture.com shutterstock.com dreamstime.com depositphotos.com istockphoto.com'],
            ['photo', 'guest', 'guest', 'en', 'digital-photography-school.com fstoppers.com photofocus.com shotkit.com lightstalking.com photographylife.com thephoblographer.com stylemepretty.com ruffledblog.com 100layercake.com weddingwire.com theknot.com zola.com junebugweddings.com'],

            // ---------- موسیقی / پادکست ----------
            ['music', 'social', 'signup', 'en', 'soundcloud.com mixcloud.com bandcamp.com last.fm discogs.com reverbnation.com audiomack.com datpiff.com spinrilla.com genius.com songmeanings.com ultimate-guitar.com songsterr.com musescore.com kvraudio.com gearspace.com harmony-central.com talkbass.com thegearpage.net bandmix.com soundbetter.com indieshuffle.com hypem.com songtradr.com tunecore.com distrokid.com cdbaby.com beatstars.com airbit.com traktrain.com attackmagazine.com musicradar.com synthtopia.com audiofanzine.com'],
            ['music', 'platform', 'signup', 'en', 'podbean.com podcasters.spotify.com buzzsprout.com libsyn.com spreaker.com castbox.fm podchaser.com listennotes.com player.fm pocketcasts.com overcast.fm podcastaddict.com tunein.com audioboom.com acast.com podnews.net castos.com'],

            // ---------- کتاب / نویسندگی ----------
            ['books', 'social', 'signup', 'en', 'goodreads.com wattpad.com royalroad.com fictionpress.com fanfiction.net archiveofourown.org inkitt.com reedsy.com scribophile.com critiquecircle.com absolutewrite.com writing.com bookbub.com librarything.com thestorygraph.com litreactor.com smashwords.com draft2digital.com lulu.com blurb.com bookfunnel.com netgalley.com booksprout.co readersfavorite.com allpoetry.com poetrysoup.com hellopoetry.com poemhunter.com'],
            ['books', 'guest', 'guest', 'en', 'writersdigest.com thecreativepenn.com janefriedman.com writersweekly.com thewritelife.com thoughtcatalog.com elephantjournal.com highexistence.com tinybuddha.com lifehack.org makealivingwriting.com'],

            // ---------- بازی ----------
            ['gaming', 'forum', 'signup', 'en', 'steamcommunity.com itch.io gamejolt.com indiedb.com moddb.com nexusmods.com curseforge.com modrinth.com gamebanana.com mod.io gamefaqs.com resetera.com gamespot.com giantbomb.com metacritic.com opencritic.com backloggd.com howlongtobeat.com community.playstation.com disboard.org top.gg discordservers.com discord.me trovo.live dotabuff.com op.gg tracker.gg minecraftforum.net planetminecraft.com namemc.com minecraft-mp.com minecraftservers.org topg.org gtop100.com devforum.roblox.com wowhead.com icy-veins.com mmo-champion.com rpgmaker.net rpg.net enworld.org dndbeyond.com roll20.net drivethrurpg.com obsidianportal.com worldanvil.com kanka.io boardgamegeek.com tabletopia.com mtggoldfish.com tappedout.net moxfield.com archidekt.com edhrec.com bulbapedia.bulbagarden.net smogon.com serebii.net pokecommunity.com gamingonlinux.com gamedeveloper.com'],

            // ---------- فیلم / سرگرمی ----------
            ['entertainment', 'social', 'signup', 'en', 'letterboxd.com imdb.com themoviedb.org trakt.tv myanimelist.net anilist.co kitsu.io anime-planet.com animenewsnetwork.com simkl.com mubi.com filmaffinity.com criticker.com flickchart.com listal.com tvmaze.com thetvdb.com tvtropes.org avclub.com slashfilm.com filmfreeway.com shortoftheweek.com simplyscripts.com scriptslug.com stage32.com screencraft.org coverfly.com inktip.com videomaker.com nofilmschool.com cinematography.com comicvine.gamespot.com webtoons.com tapas.io comicbookresources.com'],

            // ---------- خبر ----------
            ['news', 'bookmark', 'signup', 'en', 'newsbreak.com flipboard.com feedspot.com alltop.com patch.com digitaljournal.com dailykos.com'],

            // ---------- املاک ----------
            ['realestate', 'directory', 'signup', 'en', 'zillow.com realtor.com trulia.com redfin.com homes.com apartments.com rent.com zumper.com hotpads.com loopnet.com crexi.com biggerpockets.com activerain.com propertychat.com.au property118.com propertytribes.com rightmove.co.uk zoopla.co.uk onthemarket.com idealista.com immobilienscout24.de seloger.com realestate.com.au domain.com.au magicbricks.com 99acres.com housing.com commonfloor.com bayut.com propertyfinder.ae dubizzle.com houzz.com remax.com century21.com coldwellbanker.com'],
            ['realestate', 'guest', 'guest', 'en', 'inman.com realtytimes.com rismedia.com biggerpockets.com'],

            // ---------- خودرو ----------
            ['auto', 'forum', 'signup', 'en', 'edmunds.com cars.com autotrader.com kbb.com cargurus.com bimmerfest.com bimmerforums.com mbworld.org audizine.com vwvortex.com honda-tech.com clublexus.com toyotanation.com ford-trucks.com f150forum.com jeepforum.com wranglerforum.com mustang6g.com corvetteforum.com nasioc.com mazdas247.com miata.net advrider.com motorcycle.com sportbikes.net triumphrat.net hdforums.com teslamotorsclub.com electrek.co insideevs.com speakev.com justanswer.com cartalk.com obd-codes.com repairpal.com yourmechanic.com carcomplaints.com hemmings.com bringatrailer.com classiccars.com hotrodders.com garagejournal.com speedhunters.com autoguide.com'],

            // ---------- خانه ----------
            ['home', 'forum', 'signup', 'en', 'houzz.com porch.com thumbtack.com angi.com diychatroom.com doityourself.com familyhandyman.com instructables.com hometalk.com gardenersworld.com davesgarden.com gardenate.com lumberjocks.com woodworkingtalk.com sawmillcreek.org woodnet.net finewoodworking.com popularwoodworking.com ana-white.com homedit.com decoist.com digsdigs.com remodelista.com dwell.com community.home-assistant.io automatedhome.co.uk tinyhousetalk.com homesteadingtoday.com permies.com backyardchickens.com beesource.com beekeepingforums.com thegoatspot.net'],

            // ---------- سرگرمی / کاردستی ----------
            ['hobby', 'forum', 'signup', 'en', 'patternreview.com ravelry.com spoonflower.com craftgossip.com instructables.com kidsactivitiesblog.com handsonaswegrow.com modelmayhem.com'],

            // ---------- خانواده ----------
            ['parenting', 'forum', 'signup', 'en', 'mumsnet.com babycenter.com thebump.com whattoexpect.com parents.com babycentre.co.uk netmums.com cafemom.com scarymommy.com parenting.com hslda.org homeschool.com thehomeschoolmom.com cathyduffyreviews.com wrongplanet.net adoption.com babyandbump.com fertilityfriends.co.uk familyeducation.com care.com sittercity.com urbansitter.com nameberry.com behindthename.com babynames.com'],

            // ---------- حیوانات ----------
            ['pets', 'forum', 'signup', 'en', 'petforums.co.uk dogforum.com dogtime.com petfinder.com adoptapet.com rover.com wag.com dogster.com catster.com thecatsite.com catforum.com petmd.com vin.com reefcentral.com reef2reef.com fishlore.com monsterfishkeepers.com plantedtank.net bettafish.com reptileforums.co.uk beardeddragon.org morphmarket.com parrotforums.com horseforum.com chronofhorse.com equine.com horsetalk.co.nz rabbitsonline.net hamsterhideout.com theguineapigforum.co.uk k9magazine.com dogsnaturallymagazine.com akc.org'],

            // ---------- حقوقی ----------
            ['legal', 'directory', 'signup', 'en', 'avvo.com justia.com findlaw.com lawyers.com martindale.com nolo.com lawyer.com lawinfo.com superlawyers.com legalmatch.com rocketlawyer.com law.stackexchange.com expertlaw.com attorneys.media accountingweb.co.uk immigrationboards.com trackitt.com visajourney.com immihelp.com britishexpats.com ipwatchdog.com'],
            ['legal', 'guest', 'guest', 'en', 'abovethelaw.com jdsupra.com mondaq.com lexology.com natlawreview.com legalcheek.com lawyerist.com'],

            // ---------- کار ----------
            ['jobs', 'directory', 'signup', 'en', 'linkedin.com indeed.com glassdoor.com ziprecruiter.com monster.com careerbuilder.com simplyhired.com snagajob.com upwork.com fiverr.com freelancer.com toptal.com peopleperhour.com guru.com 99designs.com weworkremotely.com remoteok.com remote.co workingnomads.com jobspresso.co builtin.com dice.com authenticjobs.com krop.com journalismjobs.com mediabistro.com productionhub.com mandy.com backstage.com reed.co.uk totaljobs.com cv-library.co.uk naukri.com shine.com timesjobs.com seek.com.au jobstreet.com stepstone.de xing.com infojobs.net welcometothejungle.com'],

            // ---------- خیریه ----------
            ['nonprofit', 'platform', 'signup', 'en', 'change.org gofundme.com kickstarter.com indiegogo.com patreon.com justgiving.com globalgiving.org idealist.org volunteermatch.org guidestar.org charitynavigator.org networkforgood.org causes.com care2.com meetup.com eventbrite.com nonprofitquarterly.org techsoup.org donorbox.org fundly.com givebutter.com mightycause.com razoo.com dosomething.org devex.com reliefweb.int philanthropy.com nonprofitpro.com thenonprofittimes.com nten.org idealware.org'],

            // ---------- v1.5.2: تکمیل حوزه‌های کم‌جمعیت ----------
            ['news', 'guest', 'guest', 'en', 'theconversation.com globalvoices.org muckrack.com qwoted.com featured.com sourcebottle.com responsesource.com theodysseyonline.com'],
            ['news', 'bookmark', 'signup', 'en', 'indybay.org wikinews.org medium.com substack.com vocal.media hubpages.com infobarrel.com'],
            ['hobby', 'forum', 'signup', 'en', 'craftster.org allfreecrafts.com favecrafts.com worthpoint.com collectorsweekly.com numista.com colnect.com stampboards.com coincommunity.com rebrickable.com brickset.com bricklink.com ideas.lego.com modelrailroadforums.com railroad.net trains.com rcgroups.com rcuniverse.com dakkadakka.com warhammer-community.com knittinghelp.com lionbrand.com scrapbook.com twopeas.com rootsweb.com genealogy.com findagrave.com wikitree.com familysearch.org geni.com birdforum.net fishbrain.com eham.net qrz.com dronezon.com phantompilots.com cosplay.com speedsolving.com themagiccafe.com fountainpennetwork.com watchuseek.com timezone.com hodinkee.com bladeforums.com'],
            ['entertainment', 'forum', 'signup', 'en', 'rottentomatoes.com boxofficemojo.com tvguide.com movieweb.com comingsoon.net bloody-disgusting.com dreadcentral.com fandango.com thefilmstage.com filmschoolrejects.com forum.blu-ray.com avforums.com hometheaterforum.com digitalspy.com soompi.com allkpop.com mangadex.org mangaupdates.com broadwayworld.com playbill.com londontheatre.co.uk tubebuddy.com vidiq.com socialblade.com justwatch.com reelgood.com'],
            ['security', 'guest', 'guest', 'en', 'krebsonsecurity.com schneier.com securityaffairs.com helpnetsecurity.com cisomag.com gbhackers.com cybersecuritynews.com heimdalsecurity.com cyberciti.biz nakedsecurity.sophos.com welivesecurity.com securelist.com kitploit.com darknet.org.uk securityonline.info cyware.com forensicfocus.com infosecwriters.com privacyguides.org eff.org wizcase.com vpnmentor.com restoreprivacy.com comparitech.com cybernews.com'],
            ['security', 'forum', 'signup', 'en', 'forums.malwarebytes.com isc.sans.edu ctftime.org picoctf.org root-me.org vulnhub.com'],
            ['beauty', 'forum', 'signup', 'en', 'stylecraze.com influenster.com naturallycurly.com wardrobeoxygen.com whowhatwear.com lyst.com poshmark.com depop.com thredup.com vinted.com grailed.com mercari.com stockx.com fashiongonerogue.com fashionista.com stylecaster.com thebeautydepartment.com specktra.net sephora.com'],
            ['parenting', 'forum', 'signup', 'en', 'fatherly.com todaysparent.com raisingchildren.net.au kidshealth.org healthychildren.org zerotothree.org verywellfamily.com motherly.com mamamia.com.au dadsguide.com kidspot.com.au babygaga.com cuteness.com education.com'],
            ['legal', 'directory', 'signup', 'en', 'lawcrossing.com legalzoom.com upcounsel.com lawdepot.com citizensadvice.org.uk freeadvice.com lawguru.com lawhelp.org hg.org jurist.org scotusblog.com volokh.com lawfaremedia.org verdict.justia.com legalfutures.co.uk thelawyer.com lawgazette.co.uk lawyer-monthly.com canlii.org austlii.edu.au bailii.org courtlistener.com casetext.com immigrationroad.com'],
            ['science', 'forum', 'signup', 'en', 'aeon.co nautil.us sciencealert.com livescience.com discovermagazine.com sciencenews.org skeptic.com bioconductor.org rosalind.info frontiersin.org biorxiv.org arxiv.org chemrxiv.org preprints.org jove.com scistarter.org spaceref.com skyandtelescope.org stargazerslounge.com wunderground.com stormtrack.org americanwx.com fossilforum.com geology.com agu.org eos.org'],
        ];
    }

    /** حوزه‌ی مؤثر یک کمپین: خود کمپین، وگرنه پیش‌فرض تنظیمات. ['slug'=>'', 'only'=>bool] */
    public static function effective_niche($c) {
        $valid = self::niches();
        $slug = isset($c->niche) ? (string) $c->niche : '';
        $only = !empty($c->niche_only);
        if ($slug === '' || !isset($valid[$slug])) {
            $s = BP_Settings::all();
            $slug = (string) $s['default_niche'];
            $only = !empty($s['niche_only']);
        }
        if ($slug === '' || $slug === 'general' || !isset($valid[$slug])) return ['slug' => '', 'only' => false];
        return ['slug' => $slug, 'only' => $only];
    }

    public static function import() {
        global $wpdb;
        $t = BP_DB::t('domains');
        $now = current_time('mysql');
        $map = [];
        foreach (self::lines() as $line) {
            $p = explode('|', $line);
            $d = BP_Sources::clean($p[0]);
            if (!$d) $d = strtolower(trim($p[0]));
            $map[$d] = ['cat' => $p[1], 'method' => $p[2], 'lang' => $p[3] ?? '', 'niches' => []];
        }
        foreach (self::groups() as $g) {
            $ns = array_filter(array_map('trim', explode(',', $g[0])));
            foreach (preg_split('~\s+~', trim($g[4]), -1, PREG_SPLIT_NO_EMPTY) as $x) {
                $d = strtolower(trim($x));
                if (!preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~', $d)) continue;
                if (!isset($map[$d])) $map[$d] = ['cat' => $g[1], 'method' => $g[2], 'lang' => $g[3], 'niches' => []];
                foreach ($ns as $n) $map[$d]['niches'][$n] = 1;
            }
        }
        $n = 0;
        foreach (array_chunk($map, 100, true) as $chunk) {
            $vals = [];
            foreach ($chunk as $d => $m) {
                $nic = $m['niches'] ? ',' . implode(',', array_keys($m['niches'])) . ',' : ',general,';
                $vals[] = $wpdb->prepare("(%s,1,%s,%s,%s,%s,'pending',%s)", $d, $m['cat'], $m['method'], $m['lang'], $nic, $now);
            }
            $wpdb->query("INSERT INTO $t (domain, seed, category, method, lang, niches, status, created_at) VALUES " . implode(',', $vals) .
                " ON DUPLICATE KEY UPDATE seed=1, niches=VALUES(niches), category=IF(category='',VALUES(category),category), method=IF(method='',VALUES(method),method), lang=IF(lang='',VALUES(lang),lang)");
            $n += count($chunk);
        }
        update_option('bp_seed_v15', 1, false);
        return $n;
    }
}

/* ============================================================
 * includes/class-analyzer.php
 * ============================================================ */
class BP_Analyzer {

    const GUEST_RE  = '~write[\s\-_]*for[\s\-_]*us|guest[\s\-_]*post|submit[\s\-_]*(a[\s\-_]*)?(guest[\s\-_]*)?(post|article|story)|become[\s\-_]*a[\s\-_]*(contributor|writer|author)|ارسال[\s\-_]*مطلب|مطلب[\s\-_]*مهمان|رپورتاژ~iu';
    const SIGNUP_RE = '~sign[\s\-_]*up|register|join[\s\-_]*(us|now|free)|ثبت[\s\-_]*نام|عضویت|ایجاد[\s\-_]*حساب|create[\s\-_]*account~iu';

    /** پردازش دسته‌ای دامنه‌های در صف */
    public static function run_batch($limit = 0) {
        global $wpdb;
        $s = BP_Settings::all();
        $limit = $limit ?: (int) $s['batch_size'];
        $t = BP_DB::t('domains');
        @set_time_limit(60);
        $start = time();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $t WHERE status='pending' ORDER BY seed DESC, id ASC LIMIT %d", $limit
        ));
        $done = 0;
        $live_ids = [];
        foreach ($rows as $row) {
            if (time() - $start > 25) break; // محافظ تایم‌اوت هاست اشتراکی
            $r = self::analyze($row);
            $done++;
            if ($r === 'live') $live_ids[] = (int) $row->id;
        }
        if ($live_ids && $s['opr_key'] !== '') {
            self::apply_opr($live_ids, $s['opr_key']);
        }
        return $done;
    }

    public static function analyze($row) {
        global $wpdb;
        $t = BP_DB::t('domains');
        $d = $row->domain;
        $t0 = microtime(true);
        $scheme = 'https';
        $res = BP_Http::get('https://' . $d . '/');
        if (is_wp_error($res)) {
            $res = BP_Http::get('http://' . $d . '/');
            $scheme = 'http';
        }
        $ms = (int) round((microtime(true) - $t0) * 1000);

        if (is_wp_error($res)) {
            $wpdb->update($t, [
                'status' => 'dead', 'http_code' => 0, 'resp_ms' => $ms, 'score' => 0,
                'notes' => mb_substr($res->get_error_message(), 0, 250), 'last_checked' => current_time('mysql'),
            ], ['id' => $row->id]);
            return 'dead';
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        if ($code >= 200 && $code < 400) $status = 'live';
        elseif (in_array($code, [401, 403, 429, 503], true)) $status = 'blocked';
        else $status = 'dead';

        $info = ['lang' => '', 'outlinks' => 0, 'nofollow' => 0, 'method' => '', 'submit_url' => ''];
        if ($status !== 'dead' && $body !== '') {
            $info = self::parse($body, $d, $scheme);
        }

        $method = $row->method !== '' ? $row->method : ($info['method'] ?: 'unknown');
        $lang = $row->lang !== '' && $info['lang'] === '' ? $row->lang : $info['lang'];
        $ratio = $info['outlinks'] > 0 ? (int) round($info['nofollow'] / $info['outlinks'] * 100) : null;

        $data = [
            'status' => $status, 'http_code' => $code, 'resp_ms' => $ms, 'lang' => $lang,
            'outlinks' => $info['outlinks'], 'nofollow_ratio' => $ratio, 'method' => $method,
            'submit_url' => $info['submit_url'], 'notes' => '', 'last_checked' => current_time('mysql'),
        ];
        $data['score'] = self::score((object) array_merge((array) $row, $data, ['https' => $scheme === 'https']));
        $wpdb->update($t, $data, ['id' => $row->id]);
        return $status;
    }

    private static function parse($body, $domain, $scheme) {
        $out = ['lang' => '', 'outlinks' => 0, 'nofollow' => 0, 'method' => '', 'submit_url' => ''];
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $body, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        if (!$ok) return $out;

        $html = $dom->getElementsByTagName('html')->item(0);
        if ($html && $html->getAttribute('lang')) {
            $out['lang'] = strtolower(substr($html->getAttribute('lang'), 0, 2));
        }
        if ($out['lang'] === '' && preg_match('/[\x{0600}-\x{06FF}]{3,}/u', mb_substr(strip_tags($body), 0, 50000))) {
            $out['lang'] = 'fa';
        }

        $guest = '';
        $signup = '';
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || $href[0] === '#' || stripos($href, 'javascript:') === 0 || stripos($href, 'mailto:') === 0) continue;
            $text = trim($a->textContent);
            if (preg_match('~^https?://~i', $href)) {
                $h = preg_replace('~^www\.~', '', strtolower((string) parse_url($href, PHP_URL_HOST)));
                if ($h && $h !== $domain && substr($h, -strlen($domain) - 1) !== '.' . $domain) {
                    $out['outlinks']++;
                    if (preg_match('~nofollow|ugc|sponsored~i', $a->getAttribute('rel'))) $out['nofollow']++;
                }
            }
            $probe = $text . ' ' . rawurldecode($href);
            if ($guest === '' && preg_match(self::GUEST_RE, $probe)) $guest = self::abs($href, $scheme, $domain);
            if ($signup === '' && preg_match(self::SIGNUP_RE, $probe)) $signup = self::abs($href, $scheme, $domain);
        }

        if ($guest !== '') {
            $out['method'] = 'guest';
            $out['submit_url'] = $guest;
        } elseif ($signup !== '') {
            $out['method'] = 'signup';
            $out['submit_url'] = $signup;
        } elseif (stripos($body, 'id="commentform"') !== false || stripos($body, 'wp-comments-post') !== false) {
            $out['method'] = 'comment';
        }
        return $out;
    }

    private static function abs($href, $scheme, $domain) {
        if (preg_match('~^https?://~i', $href)) return esc_url_raw($href);
        if (strpos($href, '//') === 0) return esc_url_raw($scheme . ':' . $href);
        if ($href[0] === '/') return esc_url_raw($scheme . '://' . $domain . $href);
        return esc_url_raw($scheme . '://' . $domain . '/' . ltrim($href, './'));
    }

    /** امتیاز ۰ تا ۱۰۰ */
    public static function score($d) {
        if ($d->status === 'dead') return 0;
        $s = BP_Settings::all();
        $sc = $d->status === 'live' ? 30 : 12;
        $https = isset($d->https) ? $d->https : true;
        if ($https) $sc += 5;
        if ($d->resp_ms > 0) $sc += $d->resp_ms < 1500 ? 10 : ($d->resp_ms < 4000 ? 5 : 0);
        $o = (int) $d->outlinks;
        $sc += $o === 0 ? 8 : ($o <= 15 ? 15 : ($o <= 40 ? 10 : ($o <= 100 ? 4 : 0)));
        if ($d->nofollow_ratio !== null && $o > 0) {
            $sc += (int) round((100 - (int) $d->nofollow_ratio) / 100 * 15);
        } else {
            $sc += 7;
        }
        if ($s['target_lang'] === 'any') $sc += 5;
        elseif ($d->lang === $s['target_lang']) $sc += 10;
        $sc += ['api' => 10, 'guest' => 10, 'signup' => 7, 'comment' => 3][$d->method] ?? 0;
        if (!empty($d->opr)) $sc += (int) round(min(10, (float) $d->opr) * 0.5);
        return max(0, min(100, $sc));
    }

    /** Open PageRank (رایگان، با کلید) */
    public static function apply_opr(array $ids, $key) {
        global $wpdb;
        $t = BP_DB::t('domains');
        foreach (array_chunk($ids, 50) as $chunk) {
            $in = implode(',', array_map('intval', $chunk));
            $rows = $wpdb->get_results("SELECT * FROM $t WHERE id IN ($in)");
            if (!$rows) continue;
            $q = [];
            foreach ($rows as $r) $q[] = 'domains[]=' . rawurlencode($r->domain);
            $res = BP_Http::get('https://openpagerank.com/api/v1.0/getPageRank?' . implode('&', $q), [
                'headers' => ['API-OPR' => $key], 'timeout' => 20,
            ]);
            if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) continue;
            $json = json_decode(wp_remote_retrieve_body($res), true);
            if (empty($json['response'])) continue;
            $map = [];
            foreach ($json['response'] as $it) {
                if (!empty($it['domain']) && isset($it['page_rank_decimal'])) {
                    $map[strtolower($it['domain'])] = (float) $it['page_rank_decimal'];
                }
            }
            foreach ($rows as $r) {
                if (!isset($map[$r->domain])) continue;
                $r->opr = $map[$r->domain];
                $r->https = true;
                $wpdb->update($t, ['opr' => $r->opr, 'score' => self::score($r)], ['id' => $r->id]);
            }
        }
    }
}

/* ============================================================
 * includes/class-ai.php
 * ============================================================ */
/**
 * لایه‌ی Provider: Gemini، Groq، OpenRouter و هر endpoint سازگار با OpenAI (Ollama / LM Studio).
 * برای هر کار («decide» و «write») یک زنجیره‌ی fallback جدا؛ خطای 429/timeout → provider بعدی.
 */
class BP_AI {

    public static function names() {
        return ['gemini' => 'Gemini', 'groq' => 'Groq', 'openrouter' => 'OpenRouter', 'custom' => 'سفارشی (Ollama/LM Studio)'];
    }

    public static function configured($p) {
        $s = BP_Settings::all();
        switch ($p) {
            case 'gemini':     return $s['gemini_key'] !== '';
            case 'groq':       return $s['groq_key'] !== '';
            case 'openrouter': return $s['openrouter_key'] !== '';
            case 'custom':     return $s['custom_url'] !== '' && $s['model_custom'] !== '';
        }
        return false;
    }

    public static function chain($task) {
        $s = BP_Settings::all();
        $c = $task === 'write' ? $s['chain_write'] : $s['chain_decide'];
        $c = array_values(array_filter((array) $c, [__CLASS__, 'configured']));
        // v1.8: هر ارائه‌دهنده‌ی تنظیم‌شده که در زنجیره نیست، خودکار به انتها اضافه می‌شود
        foreach (array_keys(self::names()) as $p) {
            if (!in_array($p, $c, true) && self::configured($p)) $c[] = $p;
        }
        return $c;
    }


    /* ---- v1.9: فهرست مدل‌ها برای انتخاب کشویی ---- */

    /** فهرست آماده (بدون نیاز به کلید). با «بروزرسانی فهرست» از خود سرویس جایگزین می‌شود. */
    private static function static_models($p) {
        $g = function ($label, array $ids) {
            $items = [];
            foreach ($ids as $id => $l) $items[] = ['id' => $id, 'label' => $l];
            return ['label' => $label, 'items' => $items];
        };
        switch ($p) {
            case 'gemini':
                return [
                    $g('Flash — مناسب پلن رایگان', [
                        'gemini-2.5-flash'      => 'Gemini 2.5 Flash (پیشنهادی)',
                        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite (سریع‌تر، سقف بالاتر)',
                        'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
                        'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite',
                    ]),
                    $g('Pro — معمولاً نیاز به پلن پولی', [
                        'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
                    ]),
                ];
            case 'groq':
                return [
                    $g('پیشنهادی', [
                        'openai/gpt-oss-120b'   => 'GPT-OSS 120B (پیشنهادی)',
                        'openai/gpt-oss-20b'    => 'GPT-OSS 20B (سریع‌تر)',
                        'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
                    ]),
                    $g('سایر', [
                        'llama-3.1-8b-instant'  => 'Llama 3.1 8B Instant (سبک و سریع)',
                        'meta-llama/llama-4-scout-17b-16e-instruct' => 'Llama 4 Scout 17B',
                        'qwen/qwen3-32b'        => 'Qwen3 32B',
                        'moonshotai/kimi-k2-instruct' => 'Kimi K2',
                    ]),
                ];
            case 'openrouter':
                return [
                    $g('رایگان (:free)', [
                        'openai/gpt-oss-120b:free' => 'GPT-OSS 120B (پیشنهادی)',
                        'openai/gpt-oss-20b:free'  => 'GPT-OSS 20B',
                        'meta-llama/llama-3.3-70b-instruct:free' => 'Llama 3.3 70B Instruct',
                        'google/gemma-3-27b-it:free' => 'Gemma 3 27B',
                        'mistralai/mistral-small-3.2-24b-instruct:free' => 'Mistral Small 3.2 24B',
                    ]),
                ];
        }
        return [];
    }

    /**
     * فهرست مدل‌ها برای کشویی: اگر قبلاً از سرویس گرفته شده باشد همان (کش ۱۲ ساعته)، وگرنه فهرست آماده.
     * @return array ['groups' => [['label','items'=>[['id','label']]]], 'live' => bool]
     */
    public static function model_catalog($p) {
        $c = get_transient('bp_models_' . $p);
        if (is_array($c) && !empty($c['groups'])) return ['groups' => $c['groups'], 'live' => true];
        return ['groups' => self::static_models($p), 'live' => false];
    }

    public static function clear_model_cache() {
        foreach (array_keys(self::names()) as $p) delete_transient('bp_models_' . $p);
    }

    /** مدل‌های واقعیِ حساب را از خود سرویس می‌گیرد (کلید فقط در سمت سرور استفاده می‌شود). @return array|WP_Error */
    public static function fetch_models($p) {
        $s = BP_Settings::all();
        $proxy = !empty($s['ai_proxy']);
        $big = ['limit_response_size' => 6000000, 'timeout' => 20];
        $items = [];   // id => label
        $groups = [];

        if ($p === 'gemini') {
            if ($s['gemini_key'] === '') return new WP_Error('bp_nokey', 'ابتدا کلید Gemini را وارد و ذخیره کنید.');
            $res = BP_Http::get('https://generativelanguage.googleapis.com/v1beta/models?pageSize=200',
                $big + ['headers' => ['x-goog-api-key' => $s['gemini_key']]], $proxy, true);
            $j = self::decode($res);
            if (is_wp_error($j)) return $j;
            $flash = [];
            $other = [];
            foreach ((array) ($j['models'] ?? []) as $m) {
                if (!in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true)) continue;
                $id = preg_replace('~^models/~', '', (string) ($m['name'] ?? ''));
                if ($id === '' || preg_match('~(tts|image|live|audio|robotics|computer|embedding|imagen|veo|aqa|learnlm|gemma-3n)~i', $id)) continue;
                $l = (string) ($m['displayName'] ?? $id);
                if (stripos($id, 'flash') !== false) $flash[] = ['id' => $id, 'label' => $l . ' — ' . $id];
                else $other[] = ['id' => $id, 'label' => $l . ' — ' . $id];
            }
            $cmp = function ($a, $b) { return strcmp($a['id'], $b['id']); };
            usort($flash, $cmp);
            usort($other, $cmp);
            if ($flash) $groups[] = ['label' => 'Flash — مناسب پلن رایگان', 'items' => $flash];
            if ($other) $groups[] = ['label' => 'Pro و سایر', 'items' => $other];
        } elseif ($p === 'groq') {
            if ($s['groq_key'] === '') return new WP_Error('bp_nokey', 'ابتدا کلید Groq را وارد و ذخیره کنید.');
            $res = BP_Http::get('https://api.groq.com/openai/v1/models',
                $big + ['headers' => ['Authorization' => 'Bearer ' . $s['groq_key']]], $proxy, true);
            $j = self::decode($res);
            if (is_wp_error($j)) return $j;
            $list = [];
            foreach ((array) ($j['data'] ?? []) as $m) {
                $id = (string) ($m['id'] ?? '');
                if ($id === '' || (isset($m['active']) && !$m['active'])) continue;
                if (preg_match('~(whisper|guard|tts|playai|orpheus|safeguard|embed)~i', $id)) continue;
                $ctx = !empty($m['context_window']) ? ' — ' . round($m['context_window'] / 1000) . 'K' : '';
                $list[] = ['id' => $id, 'label' => $id . $ctx];
            }
            usort($list, function ($a, $b) { return strcmp($a['id'], $b['id']); });
            if ($list) $groups[] = ['label' => 'مدل‌های فعال در حساب شما', 'items' => $list];
        } elseif ($p === 'openrouter') {
            $h = $s['openrouter_key'] !== '' ? ['Authorization' => 'Bearer ' . $s['openrouter_key']] : [];
            $res = BP_Http::get('https://openrouter.ai/api/v1/models', $big + ['headers' => $h], $proxy, true);
            $j = self::decode($res);
            if (is_wp_error($j)) return $j;
            $free = [];
            $paid = [];
            foreach ((array) ($j['data'] ?? []) as $m) {
                $id = (string) ($m['id'] ?? '');
                if ($id === '') continue;
                $out = (array) ($m['architecture']['output_modalities'] ?? ['text']);
                if (!in_array('text', $out, true) || count($out) > 1) continue;
                $is_free = substr($id, -5) === ':free';
                $ctx = !empty($m['context_length']) ? ' — ' . round($m['context_length'] / 1000) . 'K' : '';
                $row = ['id' => $id, 'label' => $id . $ctx];
                if ($is_free) $free[] = $row; else $paid[] = $row;
            }
            $cmp = function ($a, $b) { return strcmp($a['id'], $b['id']); };
            usort($free, $cmp);
            usort($paid, $cmp);
            if ($free) $groups[] = ['label' => 'رایگان (:free)', 'items' => $free];
            if ($paid) $groups[] = ['label' => 'پولی', 'items' => array_slice($paid, 0, 400)];
        } elseif ($p === 'custom') {
            if ($s['custom_url'] === '') return new WP_Error('bp_nokey', 'ابتدا آدرس سرور را وارد و ذخیره کنید.');
            $h = $s['custom_key'] !== '' ? ['Authorization' => 'Bearer ' . $s['custom_key']] : [];
            $res = BP_Http::get($s['custom_url'] . '/models', $big + ['headers' => $h], false, false);
            $j = self::decode($res);
            if (is_wp_error($j)) return $j;
            $list = [];
            foreach ((array) ($j['data'] ?? ($j['models'] ?? [])) as $m) {
                $id = (string) (is_array($m) ? ($m['id'] ?? ($m['name'] ?? '')) : $m);
                if ($id !== '') $list[] = ['id' => $id, 'label' => $id];
            }
            usort($list, function ($a, $b) { return strcmp($a['id'], $b['id']); });
            if ($list) $groups[] = ['label' => 'مدل‌های سرور شما', 'items' => $list];
        } else {
            return new WP_Error('bp_prov', 'ارائه‌دهنده‌ی ناشناخته.');
        }

        $n = 0;
        foreach ($groups as $g) $n += count($g['items']);
        if ($n === 0) return new WP_Error('bp_empty', 'سرویس فهرست خالی برگرداند.');
        set_transient('bp_models_' . $p, ['groups' => $groups, 'time' => time()], 12 * HOUR_IN_SECONDS);
        return ['groups' => $groups, 'count' => $n];
    }

    /* ---- مصرف روزانه ---- */

    public static function usage() {
        $u = get_option('bp_ai_usage', []);
        $today = current_time('Y-m-d');
        if (empty($u['date']) || $u['date'] !== $today) {
            $u = ['date' => $today, 'tokens' => 0, 'calls' => 0, 'by' => []];
        }
        return $u;
    }

    private static function add_usage($provider, $tokens) {
        $u = self::usage();
        $u['tokens'] += (int) $tokens;
        $u['calls']++;
        $u['by'][$provider] = ($u['by'][$provider] ?? 0) + (int) $tokens;
        update_option('bp_ai_usage', $u, false);
    }

    /* ---- API عمومی ---- */

    /** @return array|WP_Error  ['text','provider','tokens'] */
    public static function chat($task, array $messages, $o = []) {
        $o = wp_parse_args($o, ['json' => false, 'max_tokens' => 800, 'temperature' => 0.4, 'timeout' => 30, 'campaign' => 0]);
        $s = BP_Settings::all();
        if (self::usage()['tokens'] >= (int) $s['token_cap']) {
            return new WP_Error('bp_cap', 'سقف توکن روزانه‌ی AI پر شده است.');
        }
        $chain = self::chain($task);
        if (!$chain) {
            return new WP_Error('bp_noai', 'هیچ ارائه‌دهنده‌ی AI تنظیم نشده (تب «AI»).');
        }
        $errors = [];
        foreach ($chain as $p) {
            $r = self::call($p, $messages, $o);
            if (!is_wp_error($r)) {
                self::add_usage($p, $r['tokens']);
                BP_Log::add('ai', sprintf('%s موفق (%s) — %d توکن', self::names()[$p], $task, $r['tokens']), 'info', $o['campaign']);
                return $r;
            }
            $errors[] = self::names()[$p] . ': ' . $r->get_error_message();
            BP_Log::add('ai', self::names()[$p] . ' خطا: ' . $r->get_error_message(), 'warn', $o['campaign']);
        }
        return new WP_Error('bp_ai_all', 'همه‌ی ارائه‌دهنده‌ها خطا دادند — ' . implode(' | ', $errors));
    }

    public static function call($p, array $messages, array $o) {
        $o = wp_parse_args($o, ['json' => false, 'max_tokens' => 800, 'temperature' => 0.4, 'timeout' => 30]);
        return $p === 'gemini' ? self::call_gemini($messages, $o) : self::call_openai($p, $messages, $o);
    }

    /* ---- Gemini ---- */

    private static function call_gemini(array $messages, array $o) {
        $s = BP_Settings::all();
        $model = $s['model_gemini'];
        $sys = '';
        $contents = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $sys .= $m['content'] . "\n";
            } else {
                $contents[] = ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]];
            }
        }
        $gc = ['temperature' => (float) $o['temperature'], 'maxOutputTokens' => (int) $o['max_tokens']];
        if ($o['json']) $gc['responseMimeType'] = 'application/json';
        if (preg_match('~2\.5-flash~i', $model)) $gc['thinkingConfig'] = ['thinkingBudget' => 0]; // جلوگیری از مصرف توکن برای «فکر کردن»
        $body = ['contents' => $contents, 'generationConfig' => $gc];
        if (trim($sys) !== '') $body['systemInstruction'] = ['parts' => [['text' => trim($sys)]]];

        $res = BP_Http::post(
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
            ['headers' => ['Content-Type' => 'application/json', 'x-goog-api-key' => $s['gemini_key']],
             'body' => wp_json_encode($body), 'timeout' => $o['timeout']],
            !empty($s['ai_proxy']), true
        );
        $j = self::decode($res);
        if (is_wp_error($j)) return $j;

        $text = '';
        foreach (($j['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) $text .= $part['text'];
        }
        if (trim($text) === '') {
            return new WP_Error('bp_empty', 'پاسخ خالی (' . ($j['candidates'][0]['finishReason'] ?? 'نامشخص') . ')');
        }
        return ['text' => $text, 'provider' => 'gemini', 'tokens' => (int) ($j['usageMetadata']['totalTokenCount'] ?? 0)];
    }

    /* ---- Groq / OpenRouter / Custom (سازگار با OpenAI) ---- */

    private static function call_openai($p, array $messages, array $o) {
        $s = BP_Settings::all();
        $headers = ['Content-Type' => 'application/json'];
        $safe = true;
        $proxy = !empty($s['ai_proxy']);

        if ($p === 'groq') {
            $base = 'https://api.groq.com/openai/v1';
            $key = $s['groq_key'];
            $model = $s['model_groq'];
        } elseif ($p === 'openrouter') {
            $base = 'https://openrouter.ai/api/v1';
            $key = $s['openrouter_key'];
            $model = $s['model_openrouter'];
            $headers['HTTP-Referer'] = home_url();
            $headers['X-Title'] = 'Backlink Pilot';
        } else {
            $base = $s['custom_url'];
            $key = $s['custom_key'];
            $model = $s['model_custom'];
            $safe = false;   // سرور لوکال/شبکه‌ی داخلی که ادمین خودش وارد کرده
            $proxy = false;
        }
        if ($key !== '') $headers['Authorization'] = 'Bearer ' . $key;

        $body = ['model' => $model, 'messages' => $messages, 'temperature' => (float) $o['temperature'], 'max_tokens' => (int) $o['max_tokens']];
        if ($p === 'groq') {
            if ($o['json']) $body['response_format'] = ['type' => 'json_object'];
            if (stripos($model, 'gpt-oss') !== false) $body['reasoning_effort'] = 'low';
        }

        $res = BP_Http::post($base . '/chat/completions', ['headers' => $headers, 'body' => wp_json_encode($body), 'timeout' => $o['timeout']], $proxy, $safe);
        $j = self::decode($res);
        if (is_wp_error($j)) return $j;

        $text = (string) ($j['choices'][0]['message']['content'] ?? '');
        if (trim($text) === '') {
            return new WP_Error('bp_empty', 'پاسخ خالی (' . ($j['choices'][0]['finish_reason'] ?? 'نامشخص') . ')');
        }
        return ['text' => $text, 'provider' => $p, 'tokens' => (int) ($j['usage']['total_tokens'] ?? 0)];
    }

    private static function decode($res) {
        if (is_wp_error($res)) return $res;
        $code = (int) wp_remote_retrieve_response_code($res);
        $raw = wp_remote_retrieve_body($res);
        $j = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($j) ? ($j['error']['message'] ?? ($j['message'] ?? '')) : '';
            if (is_array($msg)) $msg = wp_json_encode($msg);
            return new WP_Error('bp_http_' . $code, 'کد ' . $code . ($msg ? ': ' . mb_substr((string) $msg, 0, 220) : ''));
        }
        if (!is_array($j)) return new WP_Error('bp_json', 'پاسخ نامعتبر از سرویس');
        return $j;
    }

    /** استخراج JSON از متن مدل (با یا بدون ```) */
    public static function parse_json($text) {
        $t = trim((string) $text);
        $t = preg_replace('~^```(?:json)?\s*|\s*```$~i', '', $t);
        $j = json_decode($t, true);
        if (is_array($j)) return $j;
        if (preg_match('~\{.*\}~s', $t, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) return $j;
        }
        return null;
    }
}

/* ============================================================
 * v1.5.3 — BP_Style: پرامپت تصادفی برای هر رپورتاژ
 * هر مقاله ترکیب متفاوتی از شخصیت نویسنده، لحن، ساختار، زاویه، شروع، پایان، سبک عنوان،
 * جای لینک، طول و کلمه‌ی کلیدی اصلی می‌گیرد؛ از ۳ انتخاب اخیرِ هر بخش (برای هر کمپین) تکرار نمی‌شود.
 * قواعد سئو (کلمه‌ی کلیدی در عنوان/۱۰۰ کلمه‌ی اول/یک H2، بدون keyword stuffing، عنوان ۴۵–۶۵ نویسه،
 * پاراگراف کوتاه، H2 توصیفی، جزئیات واقعی و بدون آمار ساختگی) در همه‌ی ترکیب‌ها ثابت است.
 * ============================================================ */
class BP_Style {
    const OPT = 'bp_recent_styles';

    private static function pools() {
        return [
            'persona' => [
                'practitioner' => 'an experienced practitioner who has done this work for years and shares what actually works',
                'explainer'    => 'a patient explainer who makes a complex topic simple for beginners',
                'analyst'      => 'a sharp analyst who compares options and explains trade-offs clearly',
                'journalist'   => 'a feature journalist who reports on the topic with curiosity and clear structure',
                'coach'        => 'a supportive coach who guides the reader step by step',
                'consultant'   => 'a pragmatic consultant who focuses on decisions, costs and results',
                'enthusiast'   => 'a genuine enthusiast who loves the subject and writes with warmth',
            ],
            'tone' => [
                'conversational' => 'conversational and friendly, as if talking to one reader',
                'authoritative'  => 'confident and authoritative, but never arrogant',
                'warm'           => 'warm, reassuring and encouraging',
                'practical'      => 'direct and practical, no fluff',
                'curious'        => 'curious and exploratory, asking questions along the way',
                'professional'   => 'polished, professional and concise',
                'lively'         => 'lively and energetic with light, natural humour (only where it fits)',
            ],
            'format' => [
                'howto'      => 'a step-by-step how-to guide (numbered or clearly sequenced steps)',
                'listicle'   => 'a list-style article built around {n} distinct, well-explained points',
                'problem'    => 'a problem → causes → solutions article',
                'myths'      => 'a "common myths vs. what is actually true" article',
                'faq'        => 'a question-driven article where each H2 is a real question readers ask',
                'scenario'   => 'a scenario-based article that follows a realistic situation from start to finish',
                'checklist'  => 'a practical guide ending with a short checklist (use a bulleted list)',
                'comparison' => 'a comparison/decision guide that helps the reader choose between approaches',
                'mistakes'   => 'a "mistakes to avoid and what to do instead" article',
                'beginner'   => 'a complete beginner\'s guide that starts from zero and builds up',
            ],
            'angle' => [
                'basics'   => 'the fundamentals people usually skip',
                'mistakes' => 'costly mistakes and how to avoid them',
                'choose'   => 'how to choose well (criteria, red flags, questions to ask)',
                'budget'   => 'time, effort and budget: what is realistic',
                'trends'   => 'what has changed recently and what to do about it',
                'case'     => 'a realistic example or mini case study',
                'advanced' => 'next-level tips for readers who already know the basics',
                'prepare'  => 'how to prepare in advance so things go smoothly',
            ],
            'opening' => [
                'question' => 'open with a relatable question the reader is probably asking',
                'scene'    => 'open with a short, concrete real-life scene (2-3 sentences)',
                'problem'  => 'open by naming the reader\'s problem directly',
                'claim'    => 'open with a clear, slightly surprising but defensible statement',
                'context'  => 'open with a brief piece of context on why this matters now',
                'promise'  => 'open by stating plainly what the reader will be able to do after reading',
            ],
            'closing' => [
                'summary' => 'close with a short summary of the key takeaways',
                'next'    => 'close with one clear next step the reader can take today',
                'reflect' => 'close with a brief forward-looking reflection',
                'faq'     => 'close with a mini FAQ of 2 short questions and answers (use H3 for the questions)',
                'advice'  => 'close with one piece of practical advice that ties the article together',
            ],
            'title' => [
                'howto'    => 'a "how to" title',
                'number'   => 'a title that contains a number',
                'question' => 'a question title',
                'benefit'  => 'a benefit-driven title',
                'guide'    => 'a "complete guide"-style title',
                'plain'    => 'a calm, plain descriptive title',
            ],
            'link' => [
                'early' => 'in the second section (roughly the first third of the article)',
                'mid'   => 'around the middle of the article',
                'late'  => 'in the second-to-last section (roughly the last third)',
            ],
        ];
    }

    private static function pick(array $pool, $slot, array &$recent) {
        $keys = array_keys($pool);
        $hist = array_values((array) ($recent[$slot] ?? []));
        $avail = array_values(array_diff($keys, array_slice($hist, -3)));
        if (!$avail) $avail = $keys;
        $k = $avail[array_rand($avail)];
        $hist[] = $k;
        $recent[$slot] = array_slice($hist, -6);
        return $pool[$k];
    }

    private static function recent_titles($cid) {
        global $wpdb;
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT article_title FROM " . BP_DB::t('plans') . " WHERE campaign_id=%d AND article_title<>'' ORDER BY id DESC LIMIT 8", (int) $cid
        ));
        $out = [];
        foreach ((array) $rows as $t) $out[] = mb_substr((string) $t, 0, 90);
        return $out;
    }

    /** @return array [prompt, max_tokens, temperature] */
    public static function article($c, $p, $pg, $about, $link, $langname, $topic) {
        $pools = self::pools();
        $all = (array) get_option(self::OPT, []);
        $recent = (array) ($all[(int) $c->id] ?? []);

        $persona = self::pick($pools['persona'], 'persona', $recent);
        $tone    = self::pick($pools['tone'], 'tone', $recent);
        $format  = self::pick($pools['format'], 'format', $recent);
        $angle   = self::pick($pools['angle'], 'angle', $recent);
        $opening = self::pick($pools['opening'], 'opening', $recent);
        $closing = self::pick($pools['closing'], 'closing', $recent);
        $title   = self::pick($pools['title'], 'title', $recent);
        $linkpos = self::pick($pools['link'], 'link', $recent);

        $all[(int) $c->id] = $recent;
        if (count($all) > 60) $all = array_slice($all, -60, null, true);
        update_option(self::OPT, $all, false);

        $ao    = BP_Auto::opts();
        $wmin  = max(400, min(2000, (int) $ao['words_min']));
        $wmax  = max($wmin, min(2500, (int) $ao['words_max']));
        $words = mt_rand($wmin, $wmax);
        $h2    = max(3, min(7, (int) round($words / 220) + mt_rand(-1, 1)));
        $format = str_replace('{n}', (string) mt_rand(5, 8), $format);
        $temp  = mt_rand(70, 92) / 100;

        // کلمه‌ی کلیدی: اول از کلمات همین صفحه‌ی هدف، وگرنه کمپین؛ یکی اصلی و حداکثر دو فرعی
        $kws = [];
        if ($pg && trim((string) $pg->keywords) !== '') {
            $kws = array_values(array_filter(array_map('trim', preg_split('~\R~u', (string) $pg->keywords))));
        }
        if (!$kws) $kws = BP_Agent::keywords($c, 8);
        shuffle($kws);
        $primary = $kws ? $kws[0] : '';
        $second  = array_slice($kws, 1, 2);

        $fa = stripos($langname, 'Persian') !== false;
        $lang_rule = $fa
            ? "Write in natural, fluent Persian exactly as an educated native writer would (never translated-sounding). Use correct half-spaces (نیم‌فاصله) and Persian punctuation.\n"
            : "Write in natural, idiomatic English.\n";

        $seo = "SEO rules (apply regardless of style):\n"
            . ($primary !== '' ? "- Primary keyword: \"{$primary}\". Use it (or a natural inflection) in the title, within the first 100 words, and in one H2. Elsewhere only where it reads naturally; never stuff.\n" : '')
            . ($second ? '- Secondary keywords (once each, naturally): ' . implode('; ', array_map(function ($k) { return '"' . $k . '"'; }, $second)) . "\n" : '')
            . "- Cover the topic with semantically related terms and concrete detail (real steps, examples, criteria) so it satisfies search intent better than a generic overview.\n"
            . "- Title: 45-65 characters, specific and click-worthy without clickbait. H2 headings must be descriptive (never just \"Introduction\" or \"Conclusion\").\n"
            . "- Short paragraphs (2-4 sentences). Use lists only where they help.\n"
            . "- No fake statistics, studies, quotes, awards or claims you cannot support.\n";

        $avoid = self::recent_titles($c->id);
        $avoid_line = $avoid ? "Do NOT reuse or closely imitate these earlier titles/openings: " . implode(' | ', $avoid) . "\n" : '';

        $user = "Write an original, genuinely useful article in {$langname} to be published on {$p->domain}.\n"
            . $lang_rule
            . "Voice: {$persona}. Tone: {$tone}.\n"
            . "Form: {$format}. Main angle: {$angle}.\n"
            . "Title style: {$title}. Opening: {$opening}. Ending: {$closing}.\n"
            . "Length: about {$words} words. Use exactly {$h2} H2 sections (plus H3 only if the ending calls for it).\n"
            . "Subject area: {$topic}. Related keywords: " . implode(', ', array_slice($kws, 0, 6)) . ".\n"
            . "The article must be topically related to this page, which it will link to: {$about}. Do NOT copy or paraphrase that page; cover a complementary angle that gives readers real value.\n"
            . "Include exactly ONE link, placed {$linkpos}, inside a sentence where it genuinely helps the reader: {$link}. Do not use the words \"backlink\" or \"advertisement\" in the text. No other links.\n"
            . $seo
            . $avoid_line
            . "Make the structure, sentence rhythm and vocabulary clearly different from a typical template article.\n"
            . "Output exactly: first line \"TITLE: <title>\", blank line, then the body as simple HTML (h2, h3, p, ul, ol, li, strong, em, a only).";

        $max = (int) min(8000, ceil($words * ($fa ? 5.6 : 2.3)));
        return [$user, $max, $temp];
    }

    /** تنوع در ایمیل پیشنهاد مطلب مهمان */
    public static function email_note() {
        $tones = ['warm and personal', 'brief and businesslike', 'friendly and enthusiastic', 'respectful and editorial', 'curious and low-key'];
        $opens = ['open with a specific compliment about one of their recent pieces', 'open with the gap you noticed in their coverage', 'open with one sentence about who you are', 'open directly with the idea'];
        return 'Tone: ' . $tones[array_rand($tones)] . '. Structure: ' . $opens[array_rand($opens)] . ". Vary wording; avoid stock phrases like \"I hope this email finds you well\".\n";
    }
}

/* ============================================================
 * includes/class-agent.php
 * ============================================================ */
/**
 * ایجنت: AI فقط «تصمیم» می‌گیرد؛ اجرای واقعی (کوئری، سقف‌ها، ذخیره) کد PHP است.
 * ابزارها: read_my_site → query_domains → plan_backlinks → write_article / draft_outreach_email
 */
class BP_Agent {

    private static $generic = [
        'fa' => ['اینجا', 'مشاهده‌ی سایت', 'ادامه‌ی مطلب', 'اطلاعات بیشتر', 'منبع'],
        'en' => ['click here', 'this site', 'read more', 'learn more', 'source'],
    ];

    public static function campaign($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . BP_DB::t('campaigns') . ' WHERE id=%d', $id));
    }

    public static function keywords($c, $max = 8) {
        $k = array_filter(array_map('trim', preg_split('~\R~u', (string) $c->keywords)));
        return array_slice(array_values($k), 0, $max);
    }

    /* ---------- ابزار ۱: خواندن سایت من ---------- */

    public static function profile($c, $force = false) {
        global $wpdb;
        if (!$force && $c->profile && $c->profile_at && strtotime($c->profile_at) > time() - 7 * DAY_IN_SECONDS) {
            $p = json_decode($c->profile, true);
            if (is_array($p)) return $p;
        }
        $loc = BP_Pillars::local_profile($c);
        if ($loc) {
            $wpdb->update(BP_DB::t('campaigns'), ['profile' => wp_json_encode($loc), 'profile_at' => current_time('mysql')], ['id' => $c->id]);
            return $loc;
        }
        $res = BP_Http::get($c->url, ['limit_response_size' => 400000, 'timeout' => 15]);
        if (is_wp_error($res)) return $res;
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 400) return new WP_Error('bp_http', 'سایت هدف پاسخ نداد (کد ' . $code . ')');

        $p = self::extract_profile((string) wp_remote_retrieve_body($res));
        $wpdb->update(BP_DB::t('campaigns'), ['profile' => wp_json_encode($p), 'profile_at' => current_time('mysql')], ['id' => $c->id]);
        BP_Log::add('agent', 'پروفایل سایت خوانده شد: ' . $c->url, 'info', $c->id);
        return $p;
    }

    public static function extract_profile($html) {
        $p = ['title' => '', 'description' => '', 'headings' => [], 'text' => ''];
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING)) return $p;
        libxml_clear_errors();

        $t = $dom->getElementsByTagName('title')->item(0);
        if ($t) $p['title'] = mb_substr(trim($t->textContent), 0, 160);
        foreach ($dom->getElementsByTagName('meta') as $m) {
            if (strtolower($m->getAttribute('name')) === 'description') {
                $p['description'] = mb_substr(trim($m->getAttribute('content')), 0, 300);
                break;
            }
        }
        foreach (['h1', 'h2'] as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $h) {
                $x = trim(preg_replace('~\s+~u', ' ', $h->textContent));
                if ($x !== '' && count($p['headings']) < 8) $p['headings'][] = mb_substr($x, 0, 90);
            }
        }
        foreach (['script', 'style', 'noscript'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $n = $nodes->item($i);
                if ($n && $n->parentNode) $n->parentNode->removeChild($n);
            }
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) $p['text'] = mb_substr(trim(preg_replace('~\s+~u', ' ', $body->textContent)), 0, 700);
        return $p;
    }

    /* ---------- ابزار ۲+۳: انتخاب دامنه و برنامه‌ریزی ---------- */

    public static function plan($cid) {
        global $wpdb;
        $c = self::campaign($cid);
        if (!$c) return new WP_Error('bp', 'کمپین پیدا نشد.');
        $tp = BP_DB::t('plans');
        $td = BP_DB::t('domains');
        $s = BP_Settings::all();

        $day_start = current_time('Y-m-d') . ' 00:00:00';
        $week_start = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 7 * DAY_IN_SECONDS);
        $today = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tp WHERE campaign_id=%d AND status<>'rejected' AND created_at>=%s", $cid, $day_start));
        $week = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tp WHERE campaign_id=%d AND status<>'rejected' AND created_at>=%s", $cid, $week_start));
        $room = min((int) $c->daily_cap - $today, (int) $c->weekly_cap - $week);
        if ($room <= 0) return new WP_Error('bp_cap', 'سقف روزانه/هفتگی این کمپین پر است.');
        $gcap = (int) $s['global_daily_cap'];
        if ($gcap > 0) {
            $all_today = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tp WHERE status<>'rejected' AND created_at>=%s", $day_start));
            if ($all_today >= $gcap) return new WP_Error('bp_cap', 'سقف روزانه‌ی کل کمپین‌ها پر است.');
            $room = min($room, $gcap - $all_today);
        }
        $want = min($room, 5);

        $args = [];
        $lang_sql = '';
        if ($c->lang && $c->lang !== 'any') {
            $lang_sql = " AND (d.lang=%s OR d.lang='')";
            $args[] = $c->lang;
        }
        $nz = BP_Seed::effective_niche($c);
        $niche_sql = '';
        $order = 'd.score DESC';
        $order_args = [];
        if ($nz['slug'] !== '') {
            $like = '%,' . $wpdb->esc_like($nz['slug']) . ',%';
            if ($nz['only']) {
                $niche_sql = ' AND d.niches LIKE %s';
                $args[] = $like;
            } else {
                $order = '(d.niches LIKE %s) DESC, d.score DESC';
                $order_args[] = $like;
            }
        }
        $args[] = (int) $cid;
        $cands = $wpdb->get_results($wpdb->prepare(
            "SELECT d.* FROM $td d WHERE d.status='live'$lang_sql$niche_sql AND d.domain NOT IN (SELECT domain FROM $tp WHERE campaign_id=%d" . (isset($c->source) && $c->source === 'auto' ? " OR (status<>'rejected' AND campaign_id IN (SELECT id FROM " . BP_DB::t('campaigns') . " WHERE source='auto'))" : '') . ") ORDER BY $order LIMIT 25",
            array_merge($args, $order_args)
        ));
        if (!$cands) return new WP_Error('bp_nocand', $nz['only'] ? 'دامنه‌ی زنده‌ی تخصصیِ جدیدی در حوزه‌ی «' . (BP_Seed::niches()[$nz['slug']] ?? $nz['slug']) . '» نیست (تحلیل دامنه‌ها را اجرا کنید یا حالت «فقط تخصصی» را خاموش کنید).' : 'دامنه‌ی زنده‌ی جدیدی برای این کمپین نیست (منابع را همگام و تحلیل کنید).');

        $prof = self::profile($c);
        if (is_wp_error($prof)) {
            BP_Log::add('agent', 'خواندن سایت ناموفق: ' . $prof->get_error_message(), 'warn', $cid);
            $prof = [];
        }

        $map = [];
        $lines = [];
        foreach ($cands as $d) {
            $map[$d->domain] = $d;
            $lines[] = implode('|', [$d->domain, $d->category ?: '-', $d->method ?: '-', $d->score, $d->lang ?: '-']);
        }

        $user = "Pick up to {$want} best domains for backlinks to this site. Prefer topical fit, high score, and varied method/category.\n"
            . 'SITE: name=' . $c->name . '; niche=' . ($nz['slug'] ?: 'general') . '; topic=' . mb_substr((string) $c->topic, 0, 250) . '; lang=' . $c->lang
            . '; keywords=' . implode(', ', self::keywords($c, 6)) . "\n"
            . 'PROFILE: ' . ($prof ? ($prof['title'] . ' | ' . $prof['description'] . ' | ' . implode(', ', array_slice($prof['headings'], 0, 5))) : '-') . "\n"
            . "CANDIDATES (domain|category|method|score|lang):\n" . implode("\n", $lines) . "\n"
            . 'Return JSON only: {"picks":[{"domain":"","anchor_type":"brand|naked|generic|partial|exact","anchor_text":"only for partial/exact, in the site language","reason":"one short Persian sentence"}]}' . "\n"
            . 'Rules: mostly brand/naked/generic; partial sometimes; exact at most 1 in 10. Use only listed domains.';

        $r = BP_AI::chat('decide', [
            ['role' => 'system', 'content' => 'You are a careful SEO link-building planner. Reply with valid JSON only.'],
            ['role' => 'user', 'content' => $user],
        ], ['json' => true, 'max_tokens' => 900, 'temperature' => 0.3, 'campaign' => $cid]);

        $picks = [];
        $used_ai = false;
        if (!is_wp_error($r)) {
            $j = BP_AI::parse_json($r['text']);
            foreach (($j['picks'] ?? []) as $pk) {
                $dom = strtolower(trim((string) ($pk['domain'] ?? '')));
                if (isset($map[$dom]) && count($picks) < $want && !isset($picks[$dom])) {
                    $picks[$dom] = [
                        'type'   => sanitize_key($pk['anchor_type'] ?? 'brand'),
                        'text'   => sanitize_text_field($pk['anchor_text'] ?? ''),
                        'reason' => mb_substr(sanitize_text_field($pk['reason'] ?? ''), 0, 380),
                    ];
                }
            }
            $used_ai = !empty($picks);
        } else {
            BP_Log::add('agent', 'برنامه‌ریزی با AI ناموفق، حالت بدون AI: ' . $r->get_error_message(), 'warn', $cid);
        }
        if (!$picks) {
            // fallback بدون AI: بالاترین امتیازها با لنگر ایمن
            $rot = ['brand', 'naked', 'generic'];
            $i = 0;
            foreach ($cands as $d) {
                if (count($picks) >= $want) break;
                $picks[$d->domain] = ['type' => $rot[$i++ % 3], 'text' => '', 'reason' => 'انتخاب بر اساس امتیاز (بدون AI)'];
            }
        }

        $counts = ['brand' => 0, 'naked' => 0, 'generic' => 0, 'partial' => 0, 'exact' => 0];
        $total = 0;
        foreach ($wpdb->get_results($wpdb->prepare("SELECT anchor_type, COUNT(*) c FROM $tp WHERE campaign_id=%d AND status<>'rejected' GROUP BY anchor_type", $cid)) as $row) {
            if (isset($counts[$row->anchor_type])) $counts[$row->anchor_type] = (int) $row->c;
            $total += (int) $row->c;
        }

        $status = $s['autonomy'] === 'auto' ? 'approved' : 'proposed';
        $added = 0;
        foreach ($picks as $dom => $pk) {
            // هدف لینک: صفحه‌ی پیلار یا یکی از صفحه‌های پشتیبان (توزیع وزنی)؛ کمپین‌های دستی همان آدرس کمپین
            $pg = BP_Pillars::pick_target($c);
            $cp = clone $c;
            if ($pg) {
                $cp->url = $pg->url;
                if (trim((string) $pg->keywords) !== '') $cp->keywords = $pg->keywords;
            }
            list($type, $text) = self::anchor($cp, $pk['type'], $pk['text'], $counts, $total);
            $ok = $wpdb->insert($tp, [
                'campaign_id' => $cid, 'domain' => $dom, 'method' => $map[$dom]->method ?: 'unknown',
                'anchor_type' => $type, 'anchor_text' => $text, 'target_url' => $cp->url,
                'reason' => $pk['reason'], 'status' => $status, 'created_at' => current_time('mysql'),
            ]);
            if ($ok) {
                $counts[$type]++;
                $total++;
                $added++;
            }
        }
        BP_Log::add('agent', sprintf('%d برنامه‌ی جدید (%s)', $added, $used_ai ? 'با AI' : 'بدون AI'), 'info', $cid);
        return ['added' => $added, 'ai' => $used_ai];
    }

    /** اعمال سقف الگوی انکر و ساخت متن انکر */
    public static function anchor($c, $type, $ai_text, array $counts, $total) {
        if (!isset($counts[$type])) $type = 'brand';
        $t1 = $total + 1;
        if ($type === 'exact' && ($counts['exact'] + 1) / $t1 > 0.10) $type = 'partial';
        if ($type === 'partial' && ($counts['partial'] + 1) / $t1 > 0.30) $type = 'brand';

        $brand = $c->brand !== '' ? $c->brand : $c->name;
        $kws = self::keywords($c);
        $lang = $c->lang === 'en' ? 'en' : 'fa';

        switch ($type) {
            case 'naked':
                $text = $c->url;
                break;
            case 'generic':
                $g = self::$generic[$lang];
                $text = $g[array_rand($g)];
                break;
            case 'exact':
                $text = $kws ? $kws[array_rand($kws)] : $brand;
                if (!$kws) $type = 'brand';
                break;
            case 'partial':
                $text = ($ai_text !== '' && mb_strlen($ai_text) <= 60) ? $ai_text : ($kws ? $kws[array_rand($kws)] . ' ' . $brand : $brand);
                break;
            default:
                $text = $brand;
                $type = 'brand';
        }
        return [$type, $text];
    }

    /* ---------- ابزار ۴: تولید متن ---------- */

    public static function generate($plan_id) {
        global $wpdb;
        $tp = BP_DB::t('plans');
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d", $plan_id));
        if (!$p) return new WP_Error('bp', 'برنامه پیدا نشد.');
        $c = self::campaign($p->campaign_id);
        if (!$c) return new WP_Error('bp', 'کمپین پیدا نشد.');
        @set_time_limit(90);

        $temp = 0.7;
        $langname = $c->lang === 'en' ? 'English' : 'Persian (Farsi)';
        $kw = implode(', ', self::keywords($c, 6));
        $topic = mb_substr((string) $c->topic, 0, 300);

        if ($p->method === 'guest') {
            $user = "Write a short, polite pitch email in {$langname} to the editor of {$p->domain}, proposing a free guest article.\n"
                . "Our site: {$c->name} ({$topic}). Offer 3 relevant title ideas for their audience. Link to {$p->target_url} only where editorially natural. Max 120 words. No hype.\n" . BP_Style::email_note() . ""
                . "Output exactly: SUBJECT: <subject>\n---\n<email body, plain text>";
            $mt = 500;
        } elseif ($p->method === 'comment') {
            $user = "Write a genuine, helpful 2-3 sentence comment in {$langname} for a blog post related to: {$topic}. Add real value; no promotion, no links. Output only the comment text.";
            $mt = 200;
        } else {
            $pg = BP_Pillars::page_by_url($c->id, $p->target_url);
            $about = $pg ? ('"' . $pg->title . '"' . ((string) $pg->summary !== '' ? ' — ' . mb_substr((string) $pg->summary, 0, 220) : '')) : $c->url;
            $link = '<a href="' . esc_url($p->target_url) . '">' . esc_html($p->anchor_text) . '</a>';
            list($user, $mt, $temp) = BP_Style::article($c, $p, $pg, $about, $link, $langname, $topic);
        }

        $r = BP_AI::chat('write', [
            ['role' => 'system', 'content' => 'You are an experienced content writer. Follow the output format exactly.'],
            ['role' => 'user', 'content' => $user],
        ], ['json' => false, 'max_tokens' => $mt, 'temperature' => $temp, 'timeout' => 60, 'campaign' => $c->id]);

        if (is_wp_error($r)) {
            $wpdb->query($wpdb->prepare("UPDATE $tp SET fails=fails+1, updated_at=%s WHERE id=%d", current_time('mysql'), $plan_id));
            BP_Log::add('agent', 'تولید متن ناموفق (' . $p->domain . '): ' . $r->get_error_message(), 'error', $c->id);
            return $r;
        }

        $upd = ['updated_at' => current_time('mysql'), 'fails' => 0];
        if ($p->method === 'guest') {
            list($subj, $body) = self::split_email($r['text']);
            $upd['email_subject'] = mb_substr($subj, 0, 290);
            $upd['email_body'] = sanitize_textarea_field($body);
        } elseif ($p->method === 'comment') {
            $upd['article_title'] = 'کامنت پیشنهادی';
            $upd['article_body'] = sanitize_textarea_field(trim($r['text']));
        } else {
            list($title, $body) = self::split_article($r['text']);
            $body = wp_kses($body, ['h2' => [], 'h3' => [], 'p' => [], 'ul' => [], 'ol' => [], 'li' => [], 'strong' => [], 'em' => [], 'a' => ['href' => []]]);
            $fixed = self::enforce_link($body, $p->target_url, $p->anchor_text);
            $parts = preg_split('~\s+~u', trim(wp_strip_all_tags((string) $fixed)), -1, PREG_SPLIT_NO_EMPTY);
            $wc = $parts ? count($parts) : 0;
            $min_ok = max(300, (int) (BP_Auto::opts()['words_min'] * 0.55));
            $dup = ($fixed !== false && !empty(BP_Auto::opts()['dup_check'])) ? self::max_similarity((string) $fixed, (int) $c->id, (int) $plan_id) : 0.0;
            if ($fixed !== false && $wc >= $min_ok && $dup > 0.22) {
                $wpdb->query($wpdb->prepare("UPDATE $tp SET fails=fails+1, updated_at=%s WHERE id=%d", current_time('mysql'), $plan_id));
                BP_Log::add('agent', 'متن تولیدشده رد شد (' . $p->domain . '): شباهت زیاد به مقاله‌ی قبلی (' . round($dup * 100) . '٪)', 'warn', $c->id);
                return new WP_Error('bp_quality', 'متن تولیدشده بیش‌ازحد شبیه مقاله‌ی قبلی بود؛ دوباره تلاش می‌شود.');
            }
            if ($fixed === false || $wc < $min_ok) {
                $wpdb->query($wpdb->prepare("UPDATE $tp SET fails=fails+1, updated_at=%s WHERE id=%d", current_time('mysql'), $plan_id));
                $why = $fixed === false ? 'لینک هدف در متن نبود و جایگزینی ممکن نشد' : "متن کوتاه بود ({$wc} کلمه)";
                BP_Log::add('agent', 'متن تولیدشده رد شد (' . $p->domain . '): ' . $why, 'warn', $c->id);
                return new WP_Error('bp_quality', 'کیفیت متن تولیدشده کافی نبود: ' . $why);
            }
            $upd['article_title'] = mb_substr($title !== '' ? $title : $p->domain, 0, 290);
            $upd['article_body'] = $fixed;
        }
        $wpdb->update($tp, $upd, ['id' => $plan_id]);
        BP_Log::add('agent', 'متن تولید شد برای ' . $p->domain . ' (' . $r['provider'] . ')', 'info', $c->id);
        return true;
    }

    /**
     * کنترل کیفیت لینک: فقط یک لینک به صفحه‌ی هدف بماند؛ لینک‌های اضافه/خارجی برداشته شود (متنشان می‌ماند).
     * اگر مدل لینک را نگذاشته بود، اولین جای متن با همان انکر لینک می‌شود. در نبود هر دو false برمی‌گردد.
     */
    public static function enforce_link($html, $url, $anchor) {
        $kept = false;
        $html = preg_replace_callback('~<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)</a>~isu', function ($m) use ($url, &$kept) {
            if (!$kept && rtrim($m[1], '/') === rtrim($url, '/')) {
                $kept = true;
                return $m[0];
            }
            return $m[2];
        }, (string) $html);
        if ($html === null) return false;
        if ($kept) return $html;
        $a = trim((string) $anchor);
        if ($a === '') return false;
        $done = false;
        $out = preg_replace_callback('~(>[^<]*?)(' . preg_quote($a, '~') . ')~iu', function ($m) use ($url, &$done) {
            if ($done) return $m[0];
            $done = true;
            return $m[1] . '<a href="' . esc_url($url) . '">' . $m[2] . '</a>';
        }, $html, 1);
        return ($done && $out !== null) ? $out : false;
    }

    /** بیشترین شباهت (Jaccard روی ۴-کلمه‌ای‌ها) با ۱۵ مقاله‌ی اخیر همین کمپین */
    private static function max_similarity($html, $cid, $skip_id) {
        global $wpdb;
        $sh = function ($t) {
            $w = preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower(wp_strip_all_tags((string) $t)), -1, PREG_SPLIT_NO_EMPTY);
            $out = [];
            for ($i = 0, $n = count($w) - 3; $i < $n; $i++) $out[md5($w[$i] . ' ' . $w[$i + 1] . ' ' . $w[$i + 2] . ' ' . $w[$i + 3])] = 1;
            return $out;
        };
        $a = $sh($html);
        if (!$a) return 0.0;
        $rows = $wpdb->get_col($wpdb->prepare('SELECT article_body FROM ' . BP_DB::t('plans') . " WHERE campaign_id=%d AND id<>%d AND article_body IS NOT NULL AND article_body<>'' AND status<>'rejected' ORDER BY id DESC LIMIT 15", $cid, $skip_id));
        $max = 0.0;
        foreach ((array) $rows as $body) {
            $b = $sh($body);
            if (!$b) continue;
            $inter = count(array_intersect_key($a, $b));
            $union = count($a) + count($b) - $inter;
            if ($union > 0) $max = max($max, $inter / $union);
        }
        return $max;
    }

    public static function split_article($t) {
        $t = trim(preg_replace('~^```(?:html)?\s*|\s*```$~i', '', trim($t)));
        $title = '';
        if (preg_match('~^\s*TITLE:\s*(.+?)\s*$~mu', $t, $m)) {
            $title = trim($m[1], " \t\"*#");
            $t = trim(preg_replace('~^\s*TITLE:.*?\R~u', '', $t, 1));
        }
        return [$title, $t];
    }

    public static function split_email($t) {
        $t = trim($t);
        $subj = '';
        if (preg_match('~^\s*SUBJECT:\s*(.+?)\s*$~mu', $t, $m)) {
            $subj = trim($m[1]);
            $t = trim(preg_replace('~^\s*SUBJECT:.*?\R~u', '', $t, 1));
        }
        $t = trim(preg_replace('~^\s*-{3,}\s*~u', '', $t));
        return [$subj, $t];
    }

    /* ---------- زمان‌بندی خودکار (فقط وقتی استقلال ≠ suggest) ---------- */

    public static function cron() {
        global $wpdb;
        $s = BP_Settings::all();
        if ($s['autonomy'] === 'suggest') return;
        @set_time_limit(90);
        $tp = BP_DB::t('plans');

        $row = $wpdb->get_row("SELECT id FROM $tp WHERE status='approved' AND (article_body IS NULL OR article_body='') AND (email_body IS NULL OR email_body='') AND fails<3 ORDER BY id ASC LIMIT 1");
        if ($row) {
            self::generate((int) $row->id);
            return; // هر اجرا فقط یک کار AI تا تایم‌اوت و سقف توکن رعایت شود
        }
        $recent = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 7 * DAY_IN_SECONDS);
        $order = $wpdb->get_col($wpdb->prepare(
            'SELECT c.id FROM ' . BP_DB::t('campaigns') . " c LEFT JOIN $tp p ON p.campaign_id=c.id AND p.status<>'rejected' AND p.created_at>=%s WHERE c.active=1 GROUP BY c.id ORDER BY COUNT(p.id) ASC, c.id ASC", $recent
        ));
        foreach ($order as $cid) {
            $r = self::plan((int) $cid);
            if (!is_wp_error($r) && $r['added'] > 0) return;
        }
    }
}

/* ============================================================
 * v1.4 — BP_Pillars: اسکن نوشته‌ها و برگه‌ها، شناسایی پیلار، ساخت خودکار کمپین
 * ============================================================ */
class BP_Pillars {
    const SCAN_LIMIT = 1500;

    /** برگه‌های ابزاری که هرگز هدف بکلینک نیستند */
    private static $skip_slugs = ['contact', 'contact-us', 'about', 'about-us', 'privacy', 'privacy-policy', 'terms', 'terms-and-conditions',
        'cart', 'checkout', 'my-account', 'shop', 'login', 'register', 'sitemap', 'search', 'thank-you', 'thanks',
        'تماس-با-ما', 'درباره-ما', 'قوانین', 'قوانین-و-مقررات', 'حریم-خصوصی', 'سبد-خرید', 'پرداخت', 'حساب-کاربری', 'ورود', 'ثبت-نام'];

    public static function init() {
        add_action('admin_post_bp_scan_site', [__CLASS__, 'h_scan']);
        add_action('bp_admin_tab_site', [__CLASS__, 'tab']);
    }

    /* ---------- کمکی‌ها ---------- */

    private static function norm_path($url) {
        $p = parse_url((string) $url, PHP_URL_PATH);
        if (!is_string($p)) return '';
        return trim(mb_strtolower(rawurldecode($p)), '/');
    }

    private static function clean_kw($arr) {
        $out = [];
        foreach ((array) $arr as $k) {
            if (!is_scalar($k)) continue;
            $k = trim(sanitize_text_field((string) $k));
            if ($k !== '' && mb_strlen($k) <= 60 && !in_array($k, $out, true)) $out[] = $k;
            if (count($out) >= 6) break;
        }
        return $out;
    }

    private static function site_lang(array $it) {
        $loc = (string) get_locale();
        if (strpos($loc, 'fa') === 0) return 'fa';
        if (strpos($loc, 'en') === 0) return 'en';
        return preg_match('/[\x{0600}-\x{06FF}]/u', $it['title'] . ' ' . $it['excerpt']) ? 'fa' : 'en';
    }

    private static function heads($html) {
        $out = [];
        if (preg_match_all('~<h[23][^>]*>(.*?)</h[23]>~isu', (string) $html, $m)) {
            foreach ($m[1] as $h) {
                $x = trim(preg_replace('~\s+~u', ' ', wp_strip_all_tags($h)));
                if ($x !== '' && count($out) < 6) $out[] = mb_substr($x, 0, 90);
            }
        }
        return $out;
    }

    private static function plain($html) {
        $t = preg_replace('~\s+~u', ' ', wp_strip_all_tags(strip_shortcodes((string) $html)));
        return trim((string) $t);
    }

    private static function profile_of(array $it) {
        return ['title' => $it['title'], 'description' => mb_substr($it['excerpt'], 0, 200), 'headings' => $it['headings'], 'text' => $it['text']];
    }

    /** پروفایل سایت از روی دیتابیس خود وردپرس (بدون درخواست HTTP به خودمان) */
    public static function local_profile($c) {
        if (empty($c->source) || $c->source !== 'auto' || empty($c->pillar_post_id)) return null;
        $post = get_post((int) $c->pillar_post_id);
        if (!$post || $post->post_status !== 'publish') return null;
        $plain = self::plain($post->post_content);
        return [
            'title'       => html_entity_decode(wp_strip_all_tags((string) $post->post_title), ENT_QUOTES, 'UTF-8'),
            'description' => mb_substr($post->post_excerpt !== '' ? wp_strip_all_tags($post->post_excerpt) : $plain, 0, 200),
            'headings'    => self::heads($post->post_content),
            'text'        => mb_substr($plain, 0, 700),
        ];
    }

    /* ---------- ۱) خواندن همه‌ی نوشته‌ها و برگه‌ها و امتیاز پیلار ---------- */

    public static function collect() {
        global $wpdb;
        $types = apply_filters('bp_scan_post_types', ['post', 'page']);
        $types = array_values(array_filter(array_map('sanitize_key', (array) $types)));
        if (!$types) return ['total' => 0, 'items' => []];
        $in = "'" . implode("','", $types) . "'";
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_type, post_title, post_name, post_content, post_excerpt, post_parent, comment_count
             FROM {$wpdb->posts} WHERE post_status='publish' AND post_password='' AND post_type IN ($in)
             ORDER BY post_modified DESC LIMIT %d", self::SCAN_LIMIT
        ));
        if (!$rows) return ['total' => 0, 'items' => []];

        $ids = array_map(function ($r) { return (int) $r->ID; }, $rows);
        if (function_exists('_prime_post_caches')) _prime_post_caches($ids, false, false);
        update_object_term_cache($ids, $types);

        // صفحه‌های noindex (Yoast / Rank Math) هدف بکلینک نیستند
        $noidx = [];
        $nm = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE (meta_key='_yoast_wpseo_meta-robots-noindex' AND meta_value='1') OR (meta_key='rank_math_robots' AND meta_value LIKE '%noindex%')");
        foreach ((array) $nm as $pid) $noidx[(int) $pid] = true;

        $blog = (int) get_option('page_for_posts');
        $default_cat = (int) get_option('default_category');
        $host = preg_replace('~^www\.~', '', strtolower((string) parse_url(home_url(), PHP_URL_HOST)));

        // نقشه‌ی مسیر → شناسه و تعداد زیربرگه‌ها
        $by_path = [];
        $permalink = [];
        $children = [];
        foreach ($rows as $r) {
            $id = (int) $r->ID;
            $pl = get_permalink($id);
            if (!$pl) continue;
            $permalink[$id] = $pl;
            $by_path[self::norm_path($pl)] = $id;
            if ((int) $r->post_parent) $children[(int) $r->post_parent] = ($children[(int) $r->post_parent] ?? 0) + 1;
        }

        $inbound = [];
        $seen = [];
        $items = [];
        foreach ($rows as $r) {
            $id = (int) $r->ID;
            if (!isset($permalink[$id])) continue;
            $html = (string) $r->post_content;

            // لینک‌های داخلی ورودی (هر صفحه‌ی مبدأ فقط یک بار برای هر مقصد)
            if ($html !== '' && preg_match_all('~href\s*=\s*["\']([^"\'#][^"\']*)["\']~i', $html, $m)) {
                foreach ($m[1] as $href) {
                    if (preg_match('~^(mailto:|tel:|javascript:)~i', $href)) continue;
                    $h = parse_url($href, PHP_URL_HOST);
                    if (is_string($h) && $h !== '' && preg_replace('~^www\.~', '', strtolower($h)) !== $host) continue;
                    $path = self::norm_path($href);
                    if ($path === '' || !isset($by_path[$path])) continue;
                    $to = $by_path[$path];
                    if ($to === $id || isset($seen[$id][$to])) continue;
                    $seen[$id][$to] = true;
                    $inbound[$to] = ($inbound[$to] ?? 0) + 1;
                }
            }

            if (isset($noidx[$id]) || $id === $blog) continue;
            $slug = mb_strtolower(rawurldecode((string) $r->post_name));
            if ($r->post_type === 'page' && in_array($slug, self::$skip_slugs, true)) continue;

            $plain = self::plain($html);
            $parts = $plain === '' ? [] : preg_split('~\s+~u', $plain, -1, PREG_SPLIT_NO_EMPTY);
            $words = $parts ? count($parts) : 0;
            if ($words < 250) continue;

            $cat = '';
            $tags = [];
            if ($r->post_type === 'post') {
                $ct = get_the_terms($id, 'category');
                if (is_array($ct)) {
                    foreach ($ct as $t) {
                        if ((int) $t->term_id !== $default_cat) { $cat = $t->name; break; }
                    }
                }
                $tt = get_the_terms($id, 'post_tag');
                if (is_array($tt)) {
                    foreach ($tt as $t) {
                        if (count($tags) < 4) $tags[] = $t->name;
                    }
                }
            }

            $items[] = [
                'id' => $id, 'type' => $r->post_type,
                'title' => html_entity_decode(wp_strip_all_tags((string) $r->post_title), ENT_QUOTES, 'UTF-8'),
                'url' => $permalink[$id], 'words' => $words, 'comments' => (int) $r->comment_count,
                'children' => (int) ($children[$id] ?? 0), 'inbound' => 0, 'score' => 0,
                'cat' => (string) $cat, 'tags' => $tags,
                'excerpt' => mb_substr($r->post_excerpt !== '' ? wp_strip_all_tags($r->post_excerpt) : $plain, 0, 240),
                'text' => mb_substr($plain, 0, 700), 'headings' => self::heads($html),
            ];
        }

        foreach ($items as &$it) {
            $it['inbound'] = (int) ($inbound[$it['id']] ?? 0);
            $it['score'] = min(40, (int) round($it['words'] / 60)) + min(40, $it['inbound'] * 4) + min(20, $it['children'] * 5) + min(10, $it['comments']);
        }
        unset($it);
        usort($items, function ($a, $b) { return $b['score'] <=> $a['score']; });
        return ['total' => count($rows), 'items' => $items];
    }

    /* ---------- ۲) تشخیص پیلارها: AI یا روش ساده‌ی دسته‌بندی ---------- */

    private static function ai_groups(array $elig, $max) {
        if (count($elig) < 3 || !BP_AI::chain('decide')) return [];
        $top = array_slice($elig, 0, 100);
        $map = [];
        $lines = [];
        foreach ($top as $it) {
            $map[$it['id']] = $it;
            $lines[] = implode('|', [$it['id'], $it['type'], $it['score'], $it['words'], $it['inbound'], $it['cat'] !== '' ? $it['cat'] : '-', mb_substr($it['title'], 0, 80)]);
        }
        $lang = self::site_lang($top[0]);
        $user = 'WEBSITE: ' . get_bloginfo('name') . " (language: {$lang})\n"
            . "Below are the site's published posts/pages as id|type|score|words|inbound_internal_links|category|title.\n"
            . "Task: identify up to {$max} PILLAR topics: broad, commercially or editorially important subjects this site should rank for. "
            . "For each, choose ONE pillar page (the most comprehensive existing page on that topic) and up to 5 supporting cluster pages on the same topic. "
            . "Each page may appear in only one group. Ignore thin, utility or off-topic pages. Order groups by importance.\n"
            . "CANDIDATES:\n" . implode("\n", $lines) . "\n"
            . 'Return JSON only: {"campaigns":[{"name":"short name in the site language","pillar_id":0,"cluster_ids":[0],"topic":"one sentence: the topic and its audience, in the site language","keywords":["main keyword","second keyword"],"pages":[{"id":0,"keywords":["search phrase for this page"]}]}]}' . "\n"
            . 'Rules: use only listed ids; 2-5 natural search-phrase keywords per page in the site language; no brand names in keywords.';

        $r = BP_AI::chat('decide', [
            ['role' => 'system', 'content' => 'You are a senior SEO strategist who builds topic clusters. Reply with valid JSON only.'],
            ['role' => 'user', 'content' => $user],
        ], ['json' => true, 'max_tokens' => 2500, 'temperature' => 0.2, 'timeout' => 45]);
        if (is_wp_error($r)) {
            BP_Log::add('site', 'تشخیص پیلار با AI ناموفق، روش ساده: ' . $r->get_error_message(), 'warn');
            return [];
        }
        $j = BP_AI::parse_json($r['text']);
        if (empty($j['campaigns']) || !is_array($j['campaigns'])) return [];

        $used = [];
        $out = [];
        foreach ($j['campaigns'] as $g) {
            if (!is_array($g)) continue;
            $pid = (int) ($g['pillar_id'] ?? 0);
            if (!isset($map[$pid]) || isset($used[$pid])) continue;
            $used[$pid] = true;
            $cl = [];
            foreach ((array) ($g['cluster_ids'] ?? []) as $cid) {
                $cid = (int) $cid;
                if (isset($map[$cid]) && !isset($used[$cid]) && count($cl) < 5) {
                    $cl[] = $cid;
                    $used[$cid] = true;
                }
            }
            $pkw = [];
            foreach ((array) ($g['pages'] ?? []) as $pg) {
                if (!is_array($pg)) continue;
                $k = (int) ($pg['id'] ?? 0);
                if ($k === $pid || in_array($k, $cl, true)) $pkw[$k] = self::clean_kw($pg['keywords'] ?? []);
            }
            $kw = self::clean_kw($g['keywords'] ?? []);
            if (!$kw && !empty($pkw[$pid])) $kw = $pkw[$pid];
            $out[] = [
                'name'     => mb_substr(sanitize_text_field((string) ($g['name'] ?? '')), 0, 190),
                'pillar'   => $pid,
                'clusters' => $cl,
                'topic'    => mb_substr(sanitize_textarea_field((string) ($g['topic'] ?? '')), 0, 600),
                'keywords' => $kw,
                'page_kw'  => $pkw,
            ];
            if (count($out) >= $max) break;
        }
        return $out;
    }

    private static function fallback_groups(array $elig, $max) {
        $by = [];
        foreach ($elig as $it) {
            $by[$it['cat'] !== '' ? $it['cat'] : '_'][] = $it;
        }
        $ranked = [];
        foreach ($by as $k => $list) {
            if (count($list) < 2 && count($by) > 1) continue;
            $sum = 0;
            foreach (array_slice($list, 0, 3) as $x) $sum += $x['score'];
            $ranked[] = ['k' => (string) $k, 'sum' => $sum, 'list' => $list];
        }
        if (!$ranked) $ranked[] = ['k' => '_', 'sum' => 0, 'list' => $elig];
        usort($ranked, function ($a, $b) { return $b['sum'] <=> $a['sum']; });

        $out = [];
        foreach (array_slice($ranked, 0, $max) as $g) {
            $list = $g['list'];                 // از قبل بر اساس امتیاز مرتب است
            $pil = $list[0];
            $cl = [];
            $pkw = [];
            foreach (array_slice($list, 0, 6) as $i => $x) {
                if ($i > 0) $cl[] = $x['id'];
                $pkw[$x['id']] = self::clean_kw(array_merge([$x['title']], $x['tags']));
            }
            $kw = self::clean_kw(array_merge($g['k'] !== '_' ? [$g['k']] : [], $pkw[$pil['id']]));
            $out[] = [
                'name'     => mb_substr($g['k'] !== '_' ? $g['k'] : (string) get_bloginfo('name'), 0, 190),
                'pillar'   => $pil['id'],
                'clusters' => $cl,
                'topic'    => $pil['title'],
                'keywords' => $kw,
                'page_kw'  => $pkw,
            ];
        }
        return $out;
    }

    /* ---------- ۳) ساخت کمپین و ذخیره‌ی صفحه‌های هدف ---------- */

    private static function save_pages($cid, array $g, array $map) {
        global $wpdb;
        $t = BP_DB::t('pillars');
        $wpdb->delete($t, ['campaign_id' => $cid]);
        foreach (array_merge([$g['pillar']], $g['clusters']) as $i => $pid) {
            if (!isset($map[$pid])) continue;
            $it = $map[$pid];
            $kw = $g['page_kw'][$pid] ?? [];
            if (!$kw) $kw = self::clean_kw(array_merge([$it['title']], $it['tags']));
            $wpdb->insert($t, [
                'campaign_id' => $cid, 'post_id' => $pid, 'role' => $i === 0 ? 'pillar' : 'cluster',
                'url' => mb_substr($it['url'], 0, 500), 'title' => mb_substr($it['title'], 0, 300),
                'keywords' => implode("\n", $kw), 'summary' => $it['excerpt'],
            ]);
        }
    }

    private static function create_campaign(array $g, array $map) {
        global $wpdb;
        $pil = $map[$g['pillar']];
        $now = current_time('mysql');
        $kw = $g['keywords'] ?: self::clean_kw([$pil['title']]);
        $ok = $wpdb->insert(BP_DB::t('campaigns'), [
            'name'           => mb_substr($g['name'] !== '' ? $g['name'] : $pil['title'], 0, 190),
            'brand'          => mb_substr((string) get_bloginfo('name'), 0, 190),
            'url'            => mb_substr($pil['url'], 0, 500),
            'topic'          => mb_substr($g['topic'] !== '' ? $g['topic'] : $pil['title'], 0, 600),
            'keywords'       => mb_substr(implode("\n", $kw), 0, 1000),
            'lang'           => self::site_lang($pil),
            'daily_cap'      => 1,
            'weekly_cap'     => 4,
            'active'         => 1,
            'source'         => 'auto',
            'pillar_post_id' => (int) $pil['id'],
            'profile'        => wp_json_encode(self::profile_of($pil)),
            'profile_at'     => $now,
            'created_at'     => $now,
        ]);
        if (!$ok) {
            BP_Log::add('site', 'ساخت کمپین خودکار ناموفق: ' . $wpdb->last_error, 'error');
            return 0;
        }
        $cid = (int) $wpdb->insert_id;
        self::save_pages($cid, $g, $map);
        BP_Log::add('site', sprintf('کمپین خودکار ساخته شد: «%s» — پیلار: %s، %d صفحه‌ی پشتیبان', $g['name'] !== '' ? $g['name'] : $pil['title'], $pil['title'], count($g['clusters'])), 'info', $cid);
        return $cid;
    }

    /** سایت بدون محتوای قابل‌تحلیل (مثلاً صفحه‌ساز): یک کمپین برای صفحه‌ی اصلی */
    private static function create_home_campaign() {
        global $wpdb;
        $now = current_time('mysql');
        $name = (string) get_bloginfo('name');
        $desc = (string) get_bloginfo('description');
        $ok = $wpdb->insert(BP_DB::t('campaigns'), [
            'name' => mb_substr($name !== '' ? $name : 'Home', 0, 190), 'brand' => mb_substr($name, 0, 190),
            'url' => home_url('/'), 'topic' => mb_substr($desc, 0, 600), 'keywords' => '',
            'lang' => self::site_lang(['title' => $name, 'excerpt' => $desc]), 'daily_cap' => 1, 'weekly_cap' => 4, 'active' => 1,
            'source' => 'auto', 'pillar_post_id' => 0, 'created_at' => $now,
        ]);
        if (!$ok) return 0;
        $cid = (int) $wpdb->insert_id;
        $wpdb->insert(BP_DB::t('pillars'), ['campaign_id' => $cid, 'post_id' => 0, 'role' => 'pillar', 'url' => home_url('/'), 'title' => $name, 'keywords' => '', 'summary' => $desc]);
        BP_Log::add('site', 'محتوای قابل‌تحلیل پیدا نشد؛ یک کمپین برای صفحه‌ی اصلی ساخته شد.', 'warn', $cid);
        return $cid;
    }

    /* ---------- اجرای کامل اسکن ---------- */

    public static function run() {
        global $wpdb;
        @set_time_limit(100);
        update_option('bp_site_scan_next', time() + 6 * HOUR_IN_SECONDS, false); // اگر وسط کار خطا شد، فوراً تکرار نشود
        $s = BP_Settings::all();
        $max = max(1, min(12, (int) $s['auto_max_campaigns']));
        $tc = BP_DB::t('campaigns');
        $tpi = BP_DB::t('pillars');

        $scan = self::collect();
        $elig = $scan['items'];
        $map = [];
        foreach ($elig as $it) $map[$it['id']] = $it;
        $dis = array_map('intval', (array) get_option('bp_dismissed_pillars', []));

        $existing = [];
        foreach ((array) $wpdb->get_results("SELECT id, pillar_post_id FROM $tc WHERE source='auto'") as $r) {
            $existing[(int) $r->pillar_post_id] = (int) $r->id;
        }
        $used_pages = [];
        foreach ((array) $wpdb->get_results("SELECT p.post_id, p.campaign_id FROM $tpi p INNER JOIN $tc c ON c.id=p.campaign_id WHERE c.source='auto'") as $r) {
            $used_pages[(int) $r->post_id] = (int) $r->campaign_id;
        }

        $groups = [];
        $ai = false;
        if ($elig) {
            $groups = self::ai_groups($elig, $max);
            $ai = !empty($groups);
            if (!$groups) $groups = self::fallback_groups($elig, $max);
        }

        $created = 0;
        $updated = 0;
        $touched = [];
        foreach ($groups as $g) {
            $pid = (int) $g['pillar'];
            if (in_array($pid, $dis, true)) continue;
            $cid = $existing[$pid] ?? 0;
            if (!$cid) { // اگر یکی از صفحه‌های این گروه قبلاً در کمپین خودکاری است، همان موضوع است؛ کمپین تکراری نساز
                foreach (array_merge([$pid], $g['clusters']) as $pg) {
                    if (isset($used_pages[$pg])) { $cid = $used_pages[$pg]; break; }
                }
            }
            if ($cid) {
                if (!isset($touched[$cid])) {
                    self::save_pages($cid, $g, $map);
                    $touched[$cid] = true;
                    $updated++;
                }
                continue;
            }
            if (count($existing) + $created >= $max) continue;
            if (self::create_campaign($g, $map)) $created++;
        }
        if (!$groups && !$existing && !in_array(0, $dis, true)) {
            if (self::create_home_campaign()) $created++;
        }

        $top = [];
        foreach (array_slice($elig, 0, 15) as $it) {
            $top[] = ['title' => $it['title'], 'url' => $it['url'], 'score' => $it['score'], 'words' => $it['words'], 'inbound' => $it['inbound']];
        }
        $res = ['at' => current_time('mysql'), 'total' => (int) $scan['total'], 'eligible' => count($elig), 'ai' => $ai, 'created' => $created, 'updated' => $updated, 'top' => $top];
        update_option('bp_site_scan', $res, false);
        update_option('bp_site_scan_next', time() + (($created + $updated) > 0 ? 7 : 1) * DAY_IN_SECONDS, false);
        BP_Log::add('site', sprintf('اسکن سایت: %d صفحه خوانده شد، %d مناسب، %d کمپین جدید، %d به‌روزرسانی (%s)', $res['total'], $res['eligible'], $created, $updated, $ai ? 'AI' : 'روش ساده'), 'info');
        return $res;
    }

    /** از کران ایجنت صدا زده می‌شود؛ هفته‌ای یک بار. اگر اسکن اجرا شد true برمی‌گرداند. */
    public static function auto_run() {
        $s = BP_Settings::all();
        if (empty($s['auto_campaigns'])) return false;
        if (time() < (int) get_option('bp_site_scan_next', 0)) return false;
        self::run();
        return true;
    }

    /* ---------- ۴) انتخاب صفحه‌ی هدف هر بکلینک ---------- */

    /** پیلار ۳ برابر صفحه‌ی پشتیبان لینک می‌گیرد؛ همیشه کم‌لینک‌ترین نسبت به وزنش انتخاب می‌شود. */
    public static function pick_target($c) {
        global $wpdb;
        $pages = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . BP_DB::t('pillars') . ' WHERE campaign_id=%d', (int) $c->id));
        if (!$pages) return null;
        $use = [];
        foreach ($wpdb->get_results($wpdb->prepare('SELECT target_url, COUNT(*) c FROM ' . BP_DB::t('plans') . " WHERE campaign_id=%d AND status<>'rejected' GROUP BY target_url", (int) $c->id)) as $r) {
            $use[$r->target_url] = (int) $r->c;
        }
        $best = null;
        $bs = null;
        foreach ($pages as $pg) {
            $ratio = ($use[$pg->url] ?? 0) / ($pg->role === 'pillar' ? 3 : 1);
            if ($bs === null || $ratio < $bs - 1e-9 || (abs($ratio - $bs) < 1e-9 && $pg->role === 'pillar' && $best->role !== 'pillar')) {
                $best = $pg;
                $bs = $ratio;
            }
        }
        return $best;
    }

    public static function page_by_url($cid, $url) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . BP_DB::t('pillars') . ' WHERE campaign_id=%d AND url=%s LIMIT 1', (int) $cid, (string) $url));
    }

    /* ---------- پنل ---------- */

    public static function h_scan() {
        if (!current_user_can('manage_options')) wp_die('دسترسی ندارید.');
        check_admin_referer('bp_action');
        if (!empty($_GET['reset'])) delete_option('bp_dismissed_pillars');
        $r = self::run();
        $msg = sprintf('اسکن انجام شد: %d صفحه‌ی مناسب، %d کمپین جدید، %d کمپین به‌روز شد.', $r['eligible'], $r['created'], $r['updated']);
        wp_safe_redirect(add_query_arg(['page' => BP_Admin::SLUG, 'tab' => 'site', 'bp_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public static function tab() {
        global $wpdb;
        $sum = get_option('bp_site_scan', []);
        $tc = BP_DB::t('campaigns');
        $tpi = BP_DB::t('pillars');
        $tp = BP_DB::t('plans');
        $go = wp_nonce_url(add_query_arg(['action' => 'bp_scan_site'], admin_url('admin-post.php')), 'bp_action');

        echo '<div class="bp-card"><h3>اسکن سایت و شناسایی پیلار</h3>';
        echo '<p>ایجنت همه‌ی نوشته‌ها و برگه‌های منتشرشده‌ی همین وردپرس را می‌خواند، به هر صفحه «امتیاز پیلار» می‌دهد (طول محتوا، لینک‌های داخلی ورودی، زیربرگه‌ها، دیدگاه‌ها)، موضوعات اصلی را پیدا می‌کند و برای هر پیلار یک کمپین می‌سازد. بکلینک‌ها بین صفحه‌ی پیلار و صفحه‌های پشتیبانش پخش می‌شوند (پیلار ۳ برابر بیشتر). اسکن خودکار هفته‌ای یک بار انجام می‌شود.</p>';
        if (!BP_AI::chain('decide')) {
            echo '<p style="color:#b26200">AI تنظیم نشده؛ شناسایی با روش ساده‌ی دسته‌بندی انجام می‌شود. با کلید AI (تب «AI») دقت بسیار بیشتر است.</p>';
        }
        if ($sum) {
            printf('<p>آخرین اسکن: <b>%s</b> — %d صفحه خوانده شد، %d صفحه‌ی مناسب، %d کمپین جدید، %d کمپین به‌روز شد (%s).</p>',
                esc_html($sum['at']), (int) $sum['total'], (int) $sum['eligible'], (int) $sum['created'], (int) $sum['updated'], !empty($sum['ai']) ? 'تشخیص با AI' : 'روش ساده');
        } else {
            echo '<p>هنوز اسکنی انجام نشده؛ اسکن خودکار به‌زودی اجرا می‌شود یا همین حالا دکمه را بزنید.</p>';
        }
        printf('<p><a class="button button-primary" href="%s">اسکن و ساخت/به‌روزرسانی کمپین‌ها</a>', esc_url($go));
        $dis = (array) get_option('bp_dismissed_pillars', []);
        if ($dis) {
            printf(' <a class="button" href="%s">بازنشانی %d پیلار حذف‌شده و اسکن</a>', esc_url(add_query_arg(['reset' => 1], $go)), count($dis));
        }
        echo '</p></div>';

        $camps = $wpdb->get_results("SELECT * FROM $tc WHERE source='auto' ORDER BY id ASC");
        if (!$camps) {
            echo '<div class="bp-card"><p>هنوز کمپین خودکاری ساخته نشده است.</p></div>';
        }
        foreach ((array) $camps as $c) {
            $pages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tpi WHERE campaign_id=%d ORDER BY (role='pillar') DESC, id ASC", $c->id));
            $use = $wpdb->get_results($wpdb->prepare("SELECT target_url, COUNT(*) c FROM $tp WHERE campaign_id=%d AND status<>'rejected' GROUP BY target_url", $c->id), OBJECT_K);
            echo '<div class="bp-card"><h3>' . esc_html($c->name) . ($c->active ? '' : ' <em>(غیرفعال)</em>') . '</h3>';
            if ((string) $c->topic !== '') echo '<p style="color:#666">' . esc_html((string) $c->topic) . '</p>';
            echo '<table class="widefat striped"><thead><tr><th>نقش</th><th>صفحه</th><th>کلمات کلیدی</th><th>بکلینک برنامه‌ریزی‌شده</th></tr></thead><tbody>';
            foreach ((array) $pages as $pg) {
                printf('<tr><td>%s</td><td><a href="%s" target="_blank" rel="noopener">%s</a></td><td>%s</td><td><b>%d</b></td></tr>',
                    $pg->role === 'pillar' ? 'پیلار' : 'پشتیبان', esc_url($pg->url), esc_html($pg->title),
                    esc_html(str_replace("\n", '، ', (string) $pg->keywords)), isset($use[$pg->url]) ? (int) $use[$pg->url]->c : 0);
            }
            echo '</tbody></table></div>';
        }

        if (!empty($sum['top'])) {
            echo '<div class="bp-card"><h3>صفحه‌های برتر از نظر امتیاز پیلار</h3><table class="widefat striped"><thead><tr><th>صفحه</th><th>امتیاز</th><th>کلمات</th><th>لینک داخلی ورودی</th></tr></thead><tbody>';
            foreach ($sum['top'] as $t) {
                printf('<tr><td><a href="%s" target="_blank" rel="noopener">%s</a></td><td><b>%d</b></td><td>%d</td><td>%d</td></tr>',
                    esc_url($t['url']), esc_html($t['title']), (int) $t['score'], (int) $t['words'], (int) $t['inbound']);
            }
            echo '</tbody></table></div>';
        }
    }
}

/* ============================================================
 * includes/class-cron.php
 * ============================================================ */
class BP_Cron {
    public static function schedules($s) {
        $s['bp_10min'] = ['interval' => 600, 'display' => 'هر ۱۰ دقیقه (Backlink Pilot)'];
        return $s;
    }

    public static function init() {
        add_filter('cron_schedules', [__CLASS__, 'schedules']);
        add_action('bp_analyze_event', [__CLASS__, 'analyze']);
        add_action('bp_sync_event', [__CLASS__, 'sync']);
        add_action('bp_agent_event', [__CLASS__, 'agent']);
        add_action('admin_init', [__CLASS__, 'activate']);
    }

    public static function activate() {
        add_filter('cron_schedules', [__CLASS__, 'schedules']);
        if (!wp_next_scheduled('bp_analyze_event')) wp_schedule_event(time() + 120, 'bp_10min', 'bp_analyze_event');
        if (!wp_next_scheduled('bp_agent_event')) wp_schedule_event(time() + 240, 'bp_10min', 'bp_agent_event');
        if (!wp_next_scheduled('bp_sync_event')) wp_schedule_event(time() + 300, 'daily', 'bp_sync_event');
        if (!wp_next_scheduled('bp_radar_event')) wp_schedule_event(time() + 180, 'bp_10min', 'bp_radar_event');
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('bp_analyze_event');
        wp_clear_scheduled_hook('bp_sync_event');
        wp_clear_scheduled_hook('bp_agent_event');
        wp_clear_scheduled_hook('bp_radar_event');
    }

    /* ---- قفل ضد هم‌پوشانی اجراها (WP-Cron ممکن است دو بار هم‌زمان فراخوانی شود) ---- */

    private static function lock($name, $ttl) {
        return BP_Lock::acquire('cron_' . $name, $ttl);
    }

    private static function unlock($name) {
        BP_Lock::release('cron_' . $name);
    }

    /* ---- بارگذاری اولیه: لیست پلتفرم‌ها فقط یک بار خودکار وارد می‌شود ---- */

    public static function bootstrap() {
        if (get_option('bp_bootstrapped')) return;
        $n = BP_Seed::import();
        update_option('bp_bootstrapped', 1, false);
        BP_Log::add('system', "بارگذاری اولیه: {$n} سایت (در ۳۲ حوزه) به صف تحلیل اضافه شد.", 'info');
    }

    /* ---- کران‌ها ---- */

    public static function analyze() {
        $s = BP_Settings::all();
        if (empty($s['auto_analyze'])) return;
        if (!self::lock('analyze', 300)) return;
        try {
            BP_Analyzer::run_batch();
        } catch (Throwable $e) {
            BP_Log::add('system', 'خطا در تحلیل خودکار: ' . $e->getMessage(), 'error');
        }
        self::unlock('analyze');
    }

    public static function sync() {
        $s = BP_Settings::all();
        if (empty($s['auto_sync'])) return;
        if (!self::lock('sync', 900)) return;
        try {
            $r = BP_Sources::sync_all();
            if ($r['total'] > 0 || $r['errors'] > 0) {
                BP_Log::add('sync', sprintf('همگام‌سازی خودکار: %d دامنه‌ی جدید از %d، %d خطا', $r['new'], $r['total'], $r['errors']), $r['errors'] ? 'warn' : 'info');
            }
        } catch (Throwable $e) {
            BP_Log::add('system', 'خطا در همگام‌سازی خودکار: ' . $e->getMessage(), 'error');
        }
        self::unlock('sync');
    }

    /** کران ایجنت: نگهداری روزانه → کار ایجنت (برنامه‌ریزی/تولید متن) → ایمیل خلاصه */
    public static function agent() {
        if (!self::lock('agent', 1200)) return;
        try {
            self::bootstrap();
            self::maintain();
            if (!BP_Pillars::auto_run()) { // اسکن سنگین است؛ در همان اجرا کار AI دیگری انجام نمی‌شود
                if (BP_Auto::enabled()) {
                    BP_Auto::tick();
                } else {
                    BP_Agent::cron();
                }
            }
            self::digest();
            if (!get_transient('bp_recheck_gap')) {
                set_transient('bp_recheck_gap', 1, 20 * MINUTE_IN_SECONDS);
                @set_time_limit(120);
                BP_Auto::recheck(3);
            }
        } catch (Throwable $e) {
            BP_Log::add('system', 'خطا در ایجنت خودکار: ' . $e->getMessage(), 'error');
        }
        self::unlock('agent');
    }

    /** نگهداری روزانه: تحلیل مجدد دامنه‌های کهنه و تلاش مجدد برنامه‌های ناموفق */
    private static function maintain() {
        if (get_transient('bp_maintain_gap')) return;
        set_transient('bp_maintain_gap', 1, 20 * HOUR_IN_SECONDS);
        global $wpdb;
        $td = BP_DB::t('domains');
        $tp = BP_DB::t('plans');
        $now = strtotime(current_time('mysql'));
        $ago = function ($sec) use ($now) { return gmdate('Y-m-d H:i:s', $now - $sec); };

        // دامنه‌های «زنده»ی قدیمی‌تر از ۳۰ روز و «مرده/مسدود» قدیمی‌تر از ۱۴ روز دوباره به صف تحلیل می‌روند
        $a = (int) $wpdb->query($wpdb->prepare(
            "UPDATE $td SET status='pending' WHERE status='live' AND last_checked IS NOT NULL AND last_checked < %s LIMIT 150", $ago(30 * DAY_IN_SECONDS)
        ));
        $b = (int) $wpdb->query($wpdb->prepare(
            "UPDATE $td SET status='pending' WHERE status IN ('dead','blocked') AND last_checked IS NOT NULL AND last_checked < %s LIMIT 150", $ago(14 * DAY_IN_SECONDS)
        ));

        // برنامه‌های تأییدشده‌ای که ۳ بار تولید متنشان شکست خورده، بعد از ۱۲ ساعت دوباره فرصت می‌گیرند (تا ۱۴ روز از ساختشان)
        $c = (int) $wpdb->query($wpdb->prepare(
            "UPDATE $tp SET fails=0 WHERE status='approved' AND fails>=3 AND updated_at IS NOT NULL AND updated_at < %s AND created_at > %s",
            $ago(12 * HOUR_IN_SECONDS), $ago(14 * DAY_IN_SECONDS)
        ));

        if ($a + $b + $c > 0) {
            BP_Log::add('system', "نگهداری روزانه: {$a} دامنه‌ی زنده و {$b} دامنه‌ی مرده/مسدود برای تحلیل مجدد، {$c} برنامه برای تلاش مجدد.", 'info');
        }
    }

    /** ایمیل خلاصه‌ی روزانه: فقط وقتی مورد جدیدی «آماده‌ی انتشار» باشد */
    private static function digest() {
        $s = BP_Settings::all();
        if (empty($s['notify']) || get_transient('bp_digest_gap')) return;
        global $wpdb;
        $tp = BP_DB::t('plans');
        $td = BP_DB::t('domains');
        $since = get_option('bp_digest_last', '');
        if ($since === '') $since = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 3 * DAY_IN_SECONDS);

        $ready = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, d.submit_url FROM $tp p LEFT JOIN $td d ON d.domain = p.domain
             WHERE p.status='approved' AND p.updated_at > %s
               AND ((p.article_body IS NOT NULL AND p.article_body<>'') OR (p.email_body IS NOT NULL AND p.email_body<>''))
             ORDER BY p.id ASC LIMIT 20", $since
        ));
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tp WHERE status='proposed'");
        if (!$ready && !$pending) return;
        if (!$ready && $pending && $s['autonomy'] === 'auto') return;

        $methods = ['guest' => 'مطلب مهمان/رپورتاژ', 'signup' => 'ثبت‌نام و انتشار', 'comment' => 'کامنت', 'forum' => 'انجمن', 'manual' => 'دستی'];
        $lines = [];
        foreach ($ready as $r) {
            $lines[] = sprintf("• %s — %s — لنگر: «%s» → %s%s", $r->domain, $methods[$r->method] ?? $r->method, $r->anchor_text, $r->target_url, $r->submit_url ? "\n  صفحه‌ی ارسال: " . $r->submit_url : '');
        }
        $url = add_query_arg(['page' => BP_Admin::SLUG, 'tab' => 'agent'], admin_url('admin.php'));
        $body = "Backlink Pilot — خلاصه‌ی روزانه\n\n";
        if ($ready) $body .= count($ready) . " مورد آماده‌ی انتشار است (متن آماده شده؛ ارسال/انتشار با شماست):\n\n" . implode("\n", $lines) . "\n\n";
        if ($pending) $body .= $pending . " پیشنهاد منتظر تأیید شماست.\n\n";
        $body .= "صف کامل: " . $url . "\n";

        $ok = wp_mail(get_option('admin_email'), 'Backlink Pilot: ' . count($ready) . ' مورد آماده‌ی انتشار', $body);
        set_transient('bp_digest_gap', 1, 20 * HOUR_IN_SECONDS);
        if ($ok) {
            update_option('bp_digest_last', current_time('mysql'), false);
            BP_Log::add('system', 'ایمیل خلاصه‌ی روزانه ارسال شد (' . count($ready) . ' مورد).', 'info');
        } else {
            BP_Log::add('system', 'ارسال ایمیل خلاصه‌ی روزانه ناموفق بود (wp_mail).', 'warn');
        }
    }
}

/* ============================================================
 * includes/class-admin.php
 * ============================================================ */
class BP_Admin {
    const SLUG = 'backlink-pilot';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        foreach (['add_source', 'delete_source', 'sync_source', 'import_seed', 'run_batch', 'export_csv',
                  'save_settings', 'domain_action', 'paste_import', 'delete_dead'] as $a) {
            add_action('admin_post_bp_' . $a, [__CLASS__, 'h_' . $a]);
        }
    }

    public static function menu() {
        add_menu_page('Backlink Pilot', 'Backlink Pilot', 'manage_options', self::SLUG, [__CLASS__, 'page'], 'dashicons-admin-links', 58);
    }

    /* ---------- کمکی‌ها ---------- */

    private static function url($tab = 'dashboard', $extra = []) {
        return add_query_arg(array_merge(['page' => self::SLUG, 'tab' => $tab], $extra), admin_url('admin.php'));
    }

    private static function back($tab, $msg) {
        wp_safe_redirect(self::url($tab, ['bp_msg' => rawurlencode($msg)]));
        exit;
    }

    private static function guard() {
        if (!current_user_can('manage_options')) wp_die('دسترسی ندارید.');
        check_admin_referer('bp_action');
    }

    private static function nonce_link($action, $args = []) {
        return wp_nonce_url(add_query_arg(array_merge(['action' => 'bp_' . $action], $args), admin_url('admin-post.php')), 'bp_action');
    }

    private static function status_labels() {
        return ['pending' => 'در صف', 'live' => 'زنده', 'blocked' => 'محدود/نامشخص', 'dead' => 'مرده'];
    }

    private static function method_labels() {
        return ['api' => 'API (خودکار)', 'guest' => 'مطلب مهمان', 'signup' => 'ثبت‌نام/پروفایل',
                'comment' => 'کامنت', 'manual' => 'دستی', 'unknown' => 'نامشخص'];
    }

    private static function cat_labels() {
        return ['blog' => 'بلاگ', 'persian' => 'فارسی', 'social' => 'شبکه اجتماعی', 'docs' => 'سند/اسلاید',
                'dev' => 'توسعه‌دهنده', 'directory' => 'دایرکتوری', 'local' => 'سئوی محلی',
                'guest' => 'مطلب مهمان/PR', 'forum' => 'انجمن',
                'bookmark' => 'بوکمارک/خبر اجتماعی', 'press' => 'خبرنامه/PR', 'platform' => 'پلتفرم'];
    }

    /* ---------- هندلرها ---------- */

    public static function h_add_source() {
        self::guard();
        global $wpdb;
        $url = isset($_POST['url']) ? esc_url_raw(trim(wp_unslash($_POST['url']))) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (!preg_match('~^https?://~i', $url)) self::back('sources', 'آدرس معتبر نیست.');
        if ($name === '') $name = wp_parse_url($url, PHP_URL_PATH) ?: $url;
        $wpdb->insert(BP_DB::t('sources'), ['name' => mb_substr($name, 0, 190), 'url' => $url, 'active' => 1]);
        self::back('sources', 'منبع اضافه شد. حالا «همگام‌سازی» را بزنید.');
    }

    public static function h_delete_source() {
        self::guard();
        global $wpdb;
        $wpdb->delete(BP_DB::t('sources'), ['id' => (int) ($_REQUEST['id'] ?? 0)]);
        self::back('sources', 'منبع حذف شد (دامنه‌های استخراج‌شده نگه داشته شدند).');
    }

    public static function h_sync_source() {
        self::guard();
        @set_time_limit(120);
        $id = (int) ($_REQUEST['id'] ?? 0);
        if ($id) {
            $r = BP_Sources::sync($id);
            $msg = $r['error'] ? 'خطا: ' . $r['error'] : sprintf('%d دامنه استخراج شد، %d مورد جدید.', $r['total'], $r['new']);
        } else {
            $r = BP_Sources::sync_all();
            $msg = sprintf('%d دامنه استخراج شد، %d مورد جدید، %d منبع با خطا.', $r['total'], $r['new'], $r['errors']);
        }
        self::back('sources', $msg);
    }

    public static function h_import_seed() {
        self::guard();
        $n = BP_Seed::import();
        self::back('domains', $n . ' پلتفرم اولیه وارد شد. «تحلیل دسته‌ای» را بزنید.');
    }

    public static function h_paste_import() {
        self::guard();
        $text = isset($_POST['bulk']) ? wp_unslash($_POST['bulk']) : '';
        $domains = BP_Sources::extract((string) $text);
        $new = BP_Sources::insert_domains($domains, 0);
        self::back('sources', sprintf('%d دامنه شناسایی شد، %d مورد جدید.', count($domains), $new));
    }

    public static function h_run_batch() {
        self::guard();
        $n = BP_Analyzer::run_batch();
        self::back('domains', $n . ' دامنه تحلیل شد.');
    }

    public static function h_delete_dead() {
        self::guard();
        global $wpdb;
        $n = $wpdb->query('DELETE FROM ' . BP_DB::t('domains') . " WHERE status='dead'");
        self::back('domains', (int) $n . ' دامنه‌ی مرده حذف شد.');
    }

    public static function h_domain_action() {
        self::guard();
        global $wpdb;
        $t = BP_DB::t('domains');
        $id = (int) ($_REQUEST['id'] ?? 0);
        $do = sanitize_key($_REQUEST['do'] ?? '');
        if ($do === 'delete') {
            $wpdb->delete($t, ['id' => $id]);
        } elseif ($do === 'recheck') {
            $wpdb->update($t, ['status' => 'pending'], ['id' => $id]);
        }
        self::back('domains', 'انجام شد.');
    }

    public static function h_save_settings() {
        self::guard();
        BP_Settings::save($_POST);
        self::back('settings', 'تنظیمات ذخیره شد.');
    }

    public static function h_export_csv() {
        self::guard();
        global $wpdb;
        list($where, $args) = self::where_from($_GET);
        $sql = 'SELECT * FROM ' . BP_DB::t('domains') . " WHERE $where ORDER BY score DESC, id DESC";
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="backlink-pilot-' . gmdate('Ymd-His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $o = fopen('php://output', 'w');
        fputcsv($o, ['domain', 'category', 'method', 'status', 'http_code', 'resp_ms', 'lang', 'outlinks', 'nofollow_pct', 'opr', 'score', 'submit_url']);
        foreach ($rows as $r) {
            fputcsv($o, [$r->domain, $r->category, $r->method, $r->status, $r->http_code, $r->resp_ms, $r->lang,
                $r->outlinks, $r->nofollow_ratio, $r->opr, $r->score, $r->submit_url]);
        }
        fclose($o);
        exit;
    }

    private static function where_from($src) {
        $where = ['1=1'];
        $args = [];
        global $wpdb;
        foreach (['status', 'method', 'category'] as $f) {
            if (!empty($src[$f])) {
                $where[] = "$f=%s";
                $args[] = sanitize_key($src[$f]);
            }
        }
        if (!empty($src['niche']) && isset(BP_Seed::niches()[sanitize_key($src['niche'])])) {
            $where[] = 'niches LIKE %s';
            $args[] = '%,' . $wpdb->esc_like(sanitize_key($src['niche'])) . ',%';
        }
        if (!empty($src['min_score'])) {
            $where[] = 'score>=%d';
            $args[] = (int) $src['min_score'];
        }
        if (!empty($src['q'])) {
            $where[] = 'domain LIKE %s';
            $args[] = '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($src['q']))) . '%';
        }
        return [implode(' AND ', $where), $args];
    }

    /* ---------- صفحات ---------- */

    public static function page() {
        if (!current_user_can('manage_options')) return;
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'autopilot';
        $primary = ['autopilot' => 'اتوپایلوت', 'ai' => 'اتصال AI', 'report' => 'گزارش لینک‌ها', 'radar' => 'رادار', 'logs' => 'لاگ'];
        $adv = ['dashboard' => 'داشبورد', 'sources' => 'منابع گیتهاب', 'domains' => 'پایگاه دامنه‌ها', 'site' => 'سایت و پیلارها', 'campaigns' => 'کمپین‌ها', 'agent' => 'ایجنت و صف', 'settings' => 'تنظیمات'];
        $tabs = $primary + $adv;
        if (!isset($tabs[$tab])) $tab = 'dashboard';

        echo '<div class="wrap bp-wrap" dir="rtl" style="text-align:right">';
        echo '<style>.bp-wrap table.widefat td,.bp-wrap table.widefat th{text-align:right}.bp-card{background:#fff;border:1px solid #dcdcde;padding:14px 18px;margin:12px 0;border-radius:6px}.bp-grid{display:flex;gap:12px;flex-wrap:wrap}.bp-stat{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 18px;min-width:130px}.bp-stat b{display:block;font-size:24px}.bp-s{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;color:#fff}.bp-live{background:#1a7f37}.bp-dead{background:#b32d2e}.bp-pending{background:#8c8f94}.bp-blocked{background:#b26200}.bp-guide{margin:6px 0 2px;max-width:860px}.bp-guide summary{cursor:pointer;color:#2271b1;font-size:12px}.bp-guide>div{background:#f6f8fb;border:1px solid #dde3ea;border-radius:6px;padding:8px 14px;margin-top:4px;font-size:13px;line-height:1.8}.bp-guide ul{margin:4px 18px 4px 0;list-style:disc}.bp-guide-warn{color:#8a4b00}.bp-guide-live{color:#0a4b78;font-weight:600}.bp-setup,.bp-sugg{background:#f6f8fb;border:1px solid #dde3ea;border-radius:6px;padding:10px 16px;margin:10px 0;max-width:860px}.bp-setup ul,.bp-sugg ul{margin:6px 18px 8px 0}.bp-sugg{background:#fff8e5;border-color:#f0d58a}.bp-prof{display:flex;gap:10px;flex-wrap:wrap;max-width:860px}.bp-opt{flex:1;min-width:190px;border:2px solid #dcdcde;border-radius:8px;padding:10px 12px;cursor:pointer;background:#fff}.bp-opt.on{border-color:#2271b1;background:#f0f6fc}.bp-tag{display:inline-block;background:#e6f4ea;color:#1a7f37;border-radius:8px;padding:0 7px;font-size:11px}.bp-sep{color:#aaa;margin:0 6px}</style>';
        echo '<h1>Backlink Pilot <small style="font-size:12px;color:#666">v' . esc_html(BP_VERSION) . '</small></h1>';
        if (isset($_GET['bp_msg'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['bp_msg']))) . '</p></div>';
        }
        echo '<h2 class="nav-tab-wrapper">';
        $show_adv = isset($adv[$tab]) || !empty($_GET['adv']);
        foreach ($primary as $k => $label) {
            printf('<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url(self::url($k)), esc_html($label));
        }
        if ($show_adv) {
            echo '<span class="bp-sep">|</span>';
            foreach ($adv as $k => $label) {
                printf('<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url(self::url($k)), esc_html($label));
            }
        } else {
            printf('<a class="nav-tab" href="%s">⚙ پیشرفته ▾</a>', esc_url(self::url($tab, ['adv' => 1])));
        }
        echo '</h2>';
        if (method_exists(__CLASS__, 'tab_' . $tab)) {
            call_user_func([__CLASS__, 'tab_' . $tab]);
        } else {
            do_action('bp_admin_tab_' . $tab);
        }
        echo '</div>';
    }

    private static function tab_dashboard() {
        global $wpdb;
        $t = BP_DB::t('domains');
        $by = $wpdb->get_results("SELECT status, COUNT(*) c FROM $t GROUP BY status", OBJECT_K);
        $get = function ($k) use ($by) { return isset($by[$k]) ? (int) $by[$k]->c : 0; };
        $total = array_sum(array_map(function ($o) { return (int) $o->c; }, $by));
        $avg = (float) $wpdb->get_var("SELECT AVG(score) FROM $t WHERE status='live'");
        $methods = $wpdb->get_results("SELECT method, COUNT(*) c FROM $t WHERE status IN ('live','blocked') GROUP BY method");
        $top = $wpdb->get_results("SELECT * FROM $t WHERE status='live' ORDER BY score DESC LIMIT 10");
        $ml = self::method_labels();

        echo '<div class="bp-grid">';
        foreach ([['کل دامنه‌ها', $total], ['در صف تحلیل', $get('pending')], ['زنده', $get('live')], ['محدود/نامشخص', $get('blocked')],
                  ['مرده', $get('dead')], ['میانگین امتیاز (زنده‌ها)', round($avg, 1)]] as $s) {
            printf('<div class="bp-stat"><b>%s</b>%s</div>', esc_html($s[1]), esc_html($s[0]));
        }
        echo '</div>';

        if ($total === 0) {
            echo '<div class="bp-card"><p>هنوز دامنه‌ای نیست. از تب «منابع گیتهاب» یک ریپو اضافه کنید یا از تب «پایگاه دامنه‌ها» لیست اولیه‌ی پلتفرم‌ها را وارد کنید.</p></div>';
        }

        echo '<div class="bp-card"><h3>روش ثبت (دامنه‌های زنده/محدود)</h3><p>';
        foreach ($methods as $m) {
            printf('<span style="margin-left:16px">%s: <b>%d</b></span>', esc_html($ml[$m->method] ?? $m->method), (int) $m->c);
        }
        echo '</p></div>';

        echo '<div class="bp-card"><h3>۱۰ دامنه‌ی برتر</h3><table class="widefat striped"><thead><tr><th>دامنه</th><th>روش</th><th>امتیاز</th></tr></thead><tbody>';
        foreach ($top as $r) {
            printf('<tr><td><a href="%s" target="_blank" rel="noopener nofollow">%s</a></td><td>%s</td><td><b>%d</b></td></tr>',
                esc_url('https://' . $r->domain), esc_html($r->domain), esc_html($ml[$r->method] ?? $r->method), (int) $r->score);
        }
        echo '</tbody></table></div>';

        $next = wp_next_scheduled('bp_analyze_event');
        echo '<p style="color:#666">تحلیل خودکار بعدی: ' . ($next ? esc_html(wp_date('Y/m/d H:i', $next)) : 'زمان‌بندی نشده') . '</p>';
    }

    private static function tab_sources() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . BP_DB::t('sources') . ' ORDER BY id DESC');
        ?>
        <div class="bp-card">
            <h3>افزودن منبع</h3>
            <p>آدرس ریپو (<code>https://github.com/OWNER/REPO</code>)، یک فایل (<code>.../blob/main/list.md</code>) یا هر URL خام (md/csv/txt/json). برای پیدا کردن لیست‌ها در گیتهاب عبارت‌هایی مثل <em>free backlink sites list</em>، <em>web 2.0 sites</em> یا <em>guest posting sites</em> را جستجو کنید.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bp_action'); ?>
                <input type="hidden" name="action" value="bp_add_source">
                <input type="text" name="name" placeholder="نام (اختیاری)" class="regular-text">
                <input type="url" name="url" placeholder="https://github.com/OWNER/REPO" class="regular-text" style="direction:ltr;min-width:340px" required>
                <button class="button button-primary">افزودن</button>
            </form>
        </div>

        <div class="bp-card">
            <h3>منابع ثبت‌شده</h3>
            <p><a class="button" href="<?php echo esc_url(self::nonce_link('sync_source')); ?>">همگام‌سازی همه‌ی منابع</a></p>
            <table class="widefat striped">
                <thead><tr><th>نام</th><th>آدرس</th><th>آخرین همگام‌سازی</th><th>دامنه</th><th>خطا</th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows) echo '<tr><td colspan="6">منبعی ثبت نشده.</td></tr>'; ?>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td><?php echo esc_html($r->name); ?></td>
                        <td style="direction:ltr;word-break:break-all"><?php echo esc_html($r->url); ?></td>
                        <td><?php echo esc_html($r->last_sync ?: '—'); ?></td>
                        <td><?php echo (int) $r->last_count; ?></td>
                        <td style="color:#b32d2e"><?php echo esc_html((string) $r->last_error); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(self::nonce_link('sync_source', ['id' => $r->id])); ?>">همگام‌سازی</a>
                            <a class="button button-small" onclick="return confirm('حذف شود؟')" href="<?php echo esc_url(self::nonce_link('delete_source', ['id' => $r->id])); ?>">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="bp-card">
            <h3>چسباندن دستی لیست</h3>
            <p>هر متنی که دامنه یا URL دارد را بچسبانید؛ دامنه‌ها استخراج و یکتا می‌شوند.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bp_action'); ?>
                <input type="hidden" name="action" value="bp_paste_import">
                <textarea name="bulk" rows="6" class="large-text" style="direction:ltr" placeholder="example.com&#10;https://another-site.org/page"></textarea>
                <p><button class="button">استخراج و افزودن</button></p>
            </form>
        </div>
        <?php
    }

    private static function tab_domains() {
        global $wpdb;
        $t = BP_DB::t('domains');
        list($where, $args) = self::where_from($_GET);
        $per = 50;
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $off = ($paged - 1) * $per;

        $count_sql = "SELECT COUNT(*) FROM $t WHERE $where";
        $total = (int) ($args ? $wpdb->get_var($wpdb->prepare($count_sql, $args)) : $wpdb->get_var($count_sql));
        $sql = "SELECT * FROM $t WHERE $where ORDER BY score DESC, id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($args, [$per, $off])));

        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE status='pending'");
        $sl = self::status_labels();
        $ml = self::method_labels();
        $cl = self::cat_labels();
        $f = function ($k) { return isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : ''; };
        $filter_args = array_filter(['status' => $f('status'), 'method' => $f('method'), 'category' => $f('category'), 'niche' => $f('niche'), 'min_score' => $f('min_score'), 'q' => $f('q')]);
        ?>
        <div class="bp-card">
            <a class="button" href="<?php echo esc_url(self::nonce_link('import_seed')); ?>">وارد کردن لیست پیش‌فرض (بیش از ۱۶۰۰ سایت در ۳۲ حوزه)</a>
            <a class="button button-primary" href="<?php echo esc_url(self::nonce_link('run_batch')); ?>">تحلیل دسته‌ای (<?php echo (int) $pending; ?> در صف)</a>
            <a class="button" href="<?php echo esc_url(self::nonce_link('export_csv', $filter_args)); ?>">خروجی CSV (با فیلتر فعلی)</a>
            <a class="button" onclick="return confirm('همه‌ی دامنه‌های مرده حذف شوند؟')" href="<?php echo esc_url(self::nonce_link('delete_dead')); ?>">حذف مرده‌ها</a>
        </div>

        <form method="get" class="bp-card">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
            <input type="hidden" name="tab" value="domains">
            <input type="search" name="q" value="<?php echo esc_attr($f('q')); ?>" placeholder="جستجوی دامنه">
            <select name="status"><option value="">همه‌ی وضعیت‌ها</option>
                <?php foreach ($sl as $k => $v) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($f('status'), $k, false), esc_html($v)); ?></select>
            <select name="method"><option value="">همه‌ی روش‌ها</option>
                <?php foreach ($ml as $k => $v) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($f('method'), $k, false), esc_html($v)); ?></select>
            <select name="niche"><option value="">همه‌ی حوزه‌ها</option>
                <?php foreach (BP_Seed::niches() as $k => $v) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($f('niche'), $k, false), esc_html($v)); ?></select>
            <select name="category"><option value="">همه‌ی دسته‌ها</option>
                <?php foreach ($cl as $k => $v) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($f('category'), $k, false), esc_html($v)); ?></select>
            <input type="number" name="min_score" min="0" max="100" value="<?php echo esc_attr($f('min_score')); ?>" placeholder="حداقل امتیاز" style="width:110px">
            <button class="button">فیلتر</button>
            <span style="margin-right:12px"><?php echo (int) $total; ?> نتیجه</span>
        </form>

        <table class="widefat striped">
            <thead><tr><th>دامنه</th><th>دسته</th><th>حوزه</th><th>روش</th><th>وضعیت</th><th>کد</th><th>ms</th><th>زبان</th><th>لینک خروجی</th><th>٪ nofollow خروجی</th><th>OPR</th><th>امتیاز</th><th>صفحه‌ی ثبت</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows) echo '<tr><td colspan="14">موردی نیست.</td></tr>'; ?>
            <?php foreach ($rows as $r) : ?>
                <tr>
                    <td style="direction:ltr"><a href="<?php echo esc_url('https://' . $r->domain); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html($r->domain); ?></a></td>
                    <td><?php echo esc_html($cl[$r->category] ?? ($r->category ?: '—')); ?></td>
                    <td style="max-width:150px"><?php
                        $nl = BP_Seed::niches();
                        $ns = array_filter(explode(',', (string) $r->niches));
                        echo esc_html($ns ? implode('، ', array_map(function ($x) use ($nl) { return $nl[$x] ?? $x; }, array_slice($ns, 0, 3))) : '—'); ?></td>
                    <td><?php echo esc_html($ml[$r->method] ?? ($r->method ?: '—')); ?></td>
                    <td><span class="bp-s bp-<?php echo esc_attr($r->status); ?>"><?php echo esc_html($sl[$r->status] ?? $r->status); ?></span></td>
                    <td><?php echo (int) $r->http_code ?: '—'; ?></td>
                    <td><?php echo (int) $r->resp_ms ?: '—'; ?></td>
                    <td><?php echo esc_html($r->lang ?: '—'); ?></td>
                    <td><?php echo $r->status === 'pending' ? '—' : (int) $r->outlinks; ?></td>
                    <td><?php echo $r->nofollow_ratio === null ? '—' : (int) $r->nofollow_ratio . '٪'; ?></td>
                    <td><?php echo $r->opr === null ? '—' : esc_html($r->opr); ?></td>
                    <td><b><?php echo (int) $r->score; ?></b></td>
                    <td><?php echo $r->submit_url ? '<a href="' . esc_url($r->submit_url) . '" target="_blank" rel="noopener nofollow">باز کردن</a>' : '—'; ?></td>
                    <td>
                        <a href="<?php echo esc_url(self::nonce_link('domain_action', ['do' => 'recheck', 'id' => $r->id])); ?>">بررسی مجدد</a> |
                        <a href="<?php echo esc_url(self::nonce_link('domain_action', ['do' => 'delete', 'id' => $r->id])); ?>">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color:#666">ستون «٪ nofollow خروجی» نشانه‌ای از سیاست کلی سایت است، نه تضمین نوع لینکی که شما می‌گیرید؛ بعد از ثبت واقعی باید خود لینک را بررسی کنید.</p>
        <?php
        $links = paginate_links([
            'base' => add_query_arg('paged', '%#%', self::url('domains', $filter_args)),
            'format' => '', 'current' => $paged, 'total' => max(1, (int) ceil($total / $per)),
        ]);
        if ($links) echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post($links) . '</div></div>';
    }

    private static function tab_settings() {
        $s = BP_Settings::all();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bp-card">
            <?php wp_nonce_field('bp_action'); ?>
            <input type="hidden" name="action" value="bp_save_settings">
            <p class="bp-sugg" style="margin-top:0">بیشتر گزینه‌های این صفحه برای «صف دستی ایجنت» و پایگاه دامنه‌هاست. اتوپایلوت فقط <b>پروکسی</b> و (برای ساخت کمپین از روی سایت) گزینه‌ی «کمپین خودکار» را از اینجا می‌خواند.</p>
            <table class="form-table" role="presentation">
                <tr><th>توکن گیتهاب (اختیاری)</th><td>
                    <input type="password" name="github_token" class="regular-text" style="direction:ltr" autocomplete="new-password" placeholder="<?php echo BP_Settings::has_secret('github_token') ? '•••••••• (ذخیره شده)' : 'ghp_...'; ?>">
                    <label><input type="checkbox" name="clear_github_token" value="1"> پاک کردن</label>
                    <p class="description">بدون توکن ۶۰ درخواست در ساعت، با توکن ۵۰۰۰. کلید رمزنگاری‌شده ذخیره می‌شود.</p><?php BP_Guide::box('github_token'); ?></td></tr>
                <tr><th>کلید Open PageRank (اختیاری)</th><td>
                    <input type="password" name="opr_key" class="regular-text" style="direction:ltr" autocomplete="new-password" placeholder="<?php echo BP_Settings::has_secret('opr_key') ? '•••••••• (ذخیره شده)' : ''; ?>">
                    <label><input type="checkbox" name="clear_opr_key" value="1"> پاک کردن</label>
                    <p class="description">رایگان از openpagerank.com؛ برای تقریب اعتبار دامنه در امتیازدهی.</p><?php BP_Guide::box('opr_key'); ?></td></tr>
                <tr><th>پروکسی خروجی</th><td>
                    <input type="text" name="proxy" class="regular-text" style="direction:ltr" value="<?php echo esc_attr($s['proxy']); ?>" placeholder="socks5://127.0.0.1:1080 یا http://host:port">
                    <p class="description">اگر هاست شما به گیتهاب یا سایت‌های خارجی دسترسی ندارد.</p><p><a class="button button-small" href="<?php echo esc_url(BP_Auto::selftest_url()); ?>">آزمایش اتصال سرویس‌ها</a></p><?php BP_Guide::box('proxy'); ?></td></tr>
                <tr><th>اندازه‌ی هر دسته</th><td><input type="number" name="batch_size" min="1" max="60" value="<?php echo (int) $s['batch_size']; ?>">
                    <p class="description">تعداد دامنه در هر اجرا. روی هاست اشتراکی ۱۰ تا ۲۰ مناسب است.</p><?php BP_Guide::box('batch_size'); ?></td></tr>
                <tr><th>زبان هدف</th><td><select name="target_lang">
                    <?php foreach (['any' => 'هر زبانی', 'fa' => 'فارسی', 'en' => 'انگلیسی'] as $k => $v) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['target_lang'], $k, false), esc_html($v)); ?>
                </select><?php BP_Guide::box('target_lang'); ?></td></tr>
                <tr><th>اجرای خودکار</th><td>
                    <label><input type="checkbox" name="auto_analyze" value="1" <?php checked($s['auto_analyze']); ?>> تحلیل خودکار صف (هر ۱۰ دقیقه با WP-Cron)</label><br>
                    <label><input type="checkbox" name="auto_sync" value="1" <?php checked($s['auto_sync']); ?>> همگام‌سازی روزانه‌ی منابع گیتهاب</label><br>
                    <label><input type="checkbox" name="notify" value="1" <?php checked(!empty($s['notify'])); ?>> ایمیل خلاصه‌ی روزانه‌ی «آماده‌ی انتشار» به ایمیل مدیر سایت</label><?php BP_Guide::box('auto_flags'); ?></td></tr>
                <tr><th>کمپین خودکار از پیلارها</th><td>
                    <label><input type="checkbox" name="auto_campaigns" value="1" <?php checked(!empty($s['auto_campaigns'])); ?>> اسکن هفتگی نوشته‌ها و برگه‌ها و ساخت خودکار کمپین از روی پیلارها</label><br>
                    حداکثر تعداد کمپین خودکار: <input type="number" name="auto_max_campaigns" min="1" max="12" value="<?php echo (int) $s['auto_max_campaigns']; ?>"><br>
                    سقف روزانه‌ی برنامه‌های جدید (مجموع همه‌ی کمپین‌ها): <input type="number" name="global_daily_cap" min="0" max="50" value="<?php echo (int) $s['global_daily_cap']; ?>">
                    <p class="description">۰ یعنی بدون سقف. عدد کم (۲ تا ۴) سرعت لینک‌سازی را طبیعی نگه می‌دارد.</p><?php BP_Guide::box('auto_campaigns'); ?></td></tr>
                <tr><th>حوزه‌ی پیش‌فرض</th><td>
                    <select name="default_niche"><option value="">همه‌ی حوزه‌ها (بدون اولویت)</option>
                    <?php foreach (BP_Seed::niches() as $k => $v) if ($k !== 'general') printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['default_niche'], $k, false), esc_html($v)); ?></select><br>
                    <label><input type="checkbox" name="niche_only" value="1" <?php checked(!empty($s['niche_only'])); ?>> فقط سایت‌های تخصصی همین حوزه (بقیه‌ی سایت‌ها پیشنهاد نشوند)</label>
                    <p class="description">برای کمپین‌هایی که خودشان حوزه ندارند (از جمله کمپین‌های خودکار). بدون «فقط تخصصی»، سایت‌های همین حوزه اول می‌آیند و بقیه بعدشان. توجه: اتوپایلوت فقط روی ۴ پلتفرم API منتشر می‌کند و از این فیلتر استفاده نمی‌کند؛ این فیلتر برای صف ایجنت (برنامه‌ریزی بکلینک روی سایت‌های لیست) است.</p></td></tr>
                <tr><td></td><td><?php BP_Guide::box('niche'); ?></td></tr>
                <tr><th>حذف افزونه</th><td><label><input type="checkbox" name="keep_data" value="1" <?php checked($s['keep_data']); ?>> داده‌ها هنگام حذف افزونه نگه داشته شوند</label></td></tr>
            </table>
            <p><button class="button button-primary">ذخیره</button></p>
        </form>
        <?php
    }
}

/* ============================================================
 * includes/class-admin-agent.php
 * ============================================================ */
/** تب‌های v1.1: کمپین‌ها، ایجنت و صف، تنظیمات AI، لاگ */
class BP_Admin_Agent {
    const SLUG = 'backlink-pilot';

    public static function init() {
        foreach (['save_campaign', 'delete_campaign', 'campaign_profile', 'run_agent', 'plan_action', 'save_ai', 'ai_test', 'clear_logs'] as $a) {
            add_action('admin_post_bp_' . $a, [__CLASS__, 'h_' . $a]);
        }
        foreach (['campaigns', 'agent', 'ai', 'logs'] as $t) {
            add_action('bp_admin_tab_' . $t, [__CLASS__, 'tab_' . $t]);
        }
        add_action('wp_ajax_bp_models', [__CLASS__, 'ajax_models']);
    }

    /* ---------- کمکی ---------- */

    private static function url($tab, $extra = []) {
        return add_query_arg(array_merge(['page' => self::SLUG, 'tab' => $tab], $extra), admin_url('admin.php'));
    }

    private static function back($tab, $msg, $extra = []) {
        wp_safe_redirect(self::url($tab, array_merge(['bp_msg' => rawurlencode($msg)], $extra)));
        exit;
    }

    private static function guard() {
        if (!current_user_can('manage_options')) wp_die('دسترسی ندارید.');
        check_admin_referer('bp_action');
    }

    private static function nl($action, $args = []) {
        return wp_nonce_url(add_query_arg(array_merge(['action' => 'bp_' . $action], $args), admin_url('admin-post.php')), 'bp_action');
    }

    private static function methods() {
        return ['api' => 'API', 'guest' => 'مطلب مهمان', 'signup' => 'ثبت‌نام/پروفایل', 'comment' => 'کامنت', 'manual' => 'دستی', 'unknown' => 'نامشخص'];
    }

    private static function statuses() {
        return ['proposed' => 'پیشنهادی', 'approved' => 'تأیید شد', 'rejected' => 'رد شد', 'done' => 'انجام شد'];
    }

    /* ---------- هندلرها ---------- */

    public static function h_save_campaign() {
        self::guard();
        global $wpdb;
        $t = BP_DB::t('campaigns');
        $id = (int) ($_POST['id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $url = esc_url_raw(trim(wp_unslash($_POST['url'] ?? '')));
        if ($name === '' || !preg_match('~^https?://~i', $url)) self::back('campaigns', 'نام و آدرس معتبر (با http/https) لازم است.');
        $lang = sanitize_key($_POST['lang'] ?? 'fa');
        $data = [
            'name'       => mb_substr($name, 0, 190),
            'brand'      => mb_substr(sanitize_text_field(wp_unslash($_POST['brand'] ?? '')), 0, 190),
            'url'        => mb_substr($url, 0, 500),
            'topic'      => mb_substr(sanitize_textarea_field(wp_unslash($_POST['topic'] ?? '')), 0, 600),
            'keywords'   => mb_substr(sanitize_textarea_field(wp_unslash($_POST['keywords'] ?? '')), 0, 1000),
            'lang'       => in_array($lang, ['fa', 'en', 'any'], true) ? $lang : 'fa',
            'niche'      => (isset(BP_Seed::niches()[sanitize_key($_POST['niche'] ?? '')]) ? sanitize_key($_POST['niche']) : ''),
            'niche_only' => empty($_POST['niche_only']) ? 0 : 1,
            'daily_cap'  => max(1, min(50, (int) ($_POST['daily_cap'] ?? 3))),
            'weekly_cap' => max(1, min(200, (int) ($_POST['weekly_cap'] ?? 10))),
            'active'     => empty($_POST['active']) ? 0 : 1,
        ];
        if ($id) {
            $old = $wpdb->get_var($wpdb->prepare("SELECT url FROM $t WHERE id=%d", $id));
            if ($old !== $data['url']) {
                $data['profile'] = '';
                $data['profile_at'] = null;
            }
            $wpdb->update($t, $data, ['id' => $id]);
            self::back('campaigns', 'کمپین ذخیره شد.');
        }
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($t, $data);
        self::back('campaigns', 'کمپین ساخته شد. حالا «خواندن سایت» و بعد «اجرای ایجنت» را بزنید.');
    }

    public static function h_delete_campaign() {
        self::guard();
        global $wpdb;
        $id = (int) ($_REQUEST['id'] ?? 0);
        $src = $wpdb->get_row($wpdb->prepare('SELECT source, pillar_post_id FROM ' . BP_DB::t('campaigns') . ' WHERE id=%d', $id));
        if ($src && $src->source === 'auto') { // پیلار حذف‌شده دوباره خودکار ساخته نشود
            $dis = array_map('intval', (array) get_option('bp_dismissed_pillars', []));
            $dis[] = (int) $src->pillar_post_id;
            update_option('bp_dismissed_pillars', array_values(array_unique($dis)), false);
        }
        $camp = BP_Agent::campaign($id);
        $tp = BP_DB::t('plans');
        if ($camp) {
            // نام کمپین روی رکوردهای منتشرشده/انجام‌شده بماند و از کمپین جدا شوند (campaign_id=0 تا شناسه‌ی بازیافتی به آن‌ها وصل نشود)
            $wpdb->query($wpdb->prepare("UPDATE $tp SET campaign_name=%s WHERE campaign_id=%d AND campaign_name=''", mb_substr((string) $camp->name, 0, 190), $id));
        }
        $del = (int) $wpdb->query($wpdb->prepare("DELETE FROM $tp WHERE campaign_id=%d AND pub_status NOT IN ('published','publishing') AND status<>'done'", $id));
        $kept = (int) $wpdb->query($wpdb->prepare("UPDATE $tp SET campaign_id=0 WHERE campaign_id=%d", $id));
        $wpdb->delete(BP_DB::t('pillars'), ['campaign_id' => $id]);
        $wpdb->delete(BP_DB::t('campaigns'), ['id' => $id]);
        BP_Log::add('agent', sprintf('کمپین «%s» حذف شد؛ %d برنامه‌ی پیش‌نویس حذف و %d رکورد منتشرشده/انجام‌شده نگه داشته شد.', $camp ? $camp->name : $id, $del, $kept), 'info');
        self::back('campaigns', sprintf('کمپین حذف شد. %d رکورد منتشرشده/انجام‌شده (و لاگ‌ها) نگه داشته شد.', $kept));
    }

    public static function h_campaign_profile() {
        self::guard();
        $c = BP_Agent::campaign((int) ($_REQUEST['id'] ?? 0));
        if (!$c) self::back('campaigns', 'کمپین پیدا نشد.');
        $p = BP_Agent::profile($c, true);
        self::back('campaigns', is_wp_error($p) ? 'خطا: ' . $p->get_error_message() : 'سایت خوانده شد: ' . ($p['title'] ?: '(بدون عنوان)'));
    }

    public static function h_run_agent() {
        self::guard();
        @set_time_limit(120);
        $cid = (int) ($_REQUEST['id'] ?? 0);
        $r = BP_Agent::plan($cid);
        if (is_wp_error($r)) self::back('agent', 'ایجنت: ' . $r->get_error_message(), ['campaign' => $cid]);
        self::back('agent', sprintf('%d برنامه‌ی جدید اضافه شد (%s).', $r['added'], $r['ai'] ? 'تصمیم با AI' : 'بدون AI — بررسی تنظیمات AI'), ['campaign' => $cid]);
    }

    public static function h_plan_action() {
        self::guard();
        global $wpdb;
        $t = BP_DB::t('plans');
        $id = (int) ($_REQUEST['id'] ?? 0);
        $do = sanitize_key($_REQUEST['do'] ?? '');
        $cid = (int) $wpdb->get_var($wpdb->prepare("SELECT campaign_id FROM $t WHERE id=%d", $id));
        $now = current_time('mysql');
        $msg = 'انجام شد.';
        if ($do === 'approve') {
            $wpdb->update($t, ['status' => 'approved', 'updated_at' => $now], ['id' => $id]);
        } elseif ($do === 'reject') {
            $wpdb->update($t, ['status' => 'rejected', 'updated_at' => $now], ['id' => $id]);
        } elseif ($do === 'done') {
            $wpdb->update($t, ['status' => 'done', 'updated_at' => $now], ['id' => $id]);
        } elseif ($do === 'delete') {
            $n = (int) $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE id=%d AND pub_status NOT IN ('published','publishing') AND status<>'done'", $id));
            if (!$n) $msg = 'برنامه‌ی منتشرشده/انجام‌شده یا در حال انتشار حذف نمی‌شود.';
        } elseif ($do === 'gen') {
            @set_time_limit(120);
            $r = BP_Agent::generate($id);
            $msg = is_wp_error($r) ? 'خطا: ' . $r->get_error_message() : 'متن تولید شد.';
        }
        self::back('agent', $msg, $cid ? ['campaign' => $cid] : []);
    }

    public static function h_save_ai() {
        self::guard();
        BP_Settings::save_ai($_POST);
        self::back('ai', 'تنظیمات AI ذخیره شد.');
    }

    public static function h_ai_test() {
        self::guard();
        $p = sanitize_key($_REQUEST['provider'] ?? '');
        if (!isset(BP_AI::names()[$p]) || !BP_AI::configured($p)) self::back('ai', 'این ارائه‌دهنده کامل تنظیم نشده.');
        $t0 = microtime(true);
        $r = BP_AI::call($p, [['role' => 'user', 'content' => 'Reply with exactly one word: OK']], ['max_tokens' => 20, 'temperature' => 0, 'timeout' => 25]);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        if (is_wp_error($r)) self::back('ai', BP_AI::names()[$p] . ' ← خطا: ' . $r->get_error_message());
        self::back('ai', BP_AI::names()[$p] . ' ← اتصال موفق (' . $ms . ' ms): ' . mb_substr(trim($r['text']), 0, 40));
    }

    public static function h_clear_logs() {
        self::guard();
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . BP_DB::t('logs'));
        self::back('logs', 'لاگ‌ها پاک شد.');
    }

    /* ---------- تب: کمپین‌ها ---------- */

    public static function tab_campaigns() {
        global $wpdb;
        $t = BP_DB::t('campaigns');
        $edit = isset($_GET['edit']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", (int) $_GET['edit'])) : null;
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC");
        $v = function ($k, $d = '') use ($edit) { return $edit ? $edit->$k : $d; };
        ?>
        <div class="bp-card">
            <h3><?php echo $edit ? 'ویرایش کمپین' : 'کمپین جدید (سایتی که می‌خواهید برایش بکلینک بسازید)'; ?></h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bp_action'); ?>
                <input type="hidden" name="action" value="bp_save_campaign">
                <input type="hidden" name="id" value="<?php echo (int) $v('id', 0); ?>">
                <table class="form-table" role="presentation">
                    <tr><th>نام کمپین</th><td><input type="text" name="name" class="regular-text" value="<?php echo esc_attr($v('name')); ?>" required></td></tr>
                    <tr><th>نام برند (متن انکر)</th><td><input type="text" name="brand" class="regular-text" value="<?php echo esc_attr($v('brand')); ?>"><p class="description">خالی = نام کمپین.</p></td></tr>
                    <tr><th>آدرس سایت هدف</th><td><input type="url" name="url" class="regular-text" style="direction:ltr;min-width:340px" value="<?php echo esc_attr($v('url')); ?>" placeholder="https://example.ir/" required></td></tr>
                    <tr><th>موضوع سایت</th><td><textarea name="topic" rows="3" class="large-text"><?php echo esc_textarea($v('topic')); ?></textarea><p class="description">یکی دو جمله: سایت درباره‌ی چیست و مخاطبش کیست.</p></td></tr>
                    <tr><th>کلیدواژه‌ها</th><td><textarea name="keywords" rows="4" class="large-text"><?php echo esc_textarea($v('keywords')); ?></textarea><p class="description">هر خط یک عبارت (برای انکر و محتوا).</p></td></tr>
                    <tr><th>حوزه‌ی سایت</th><td>
                        <select name="niche"><option value="">پیش‌فرض (طبق تنظیمات)</option>
                        <?php foreach (BP_Seed::niches() as $k => $l) if ($k !== 'general') printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($v('niche', ''), $k, false), esc_html($l)); ?></select>
                        <label style="margin-right:12px"><input type="checkbox" name="niche_only" value="1" <?php checked((int) $v('niche_only', 0), 1); ?>> فقط سایت‌های تخصصی این حوزه</label>
                        <p class="description">سایت‌های هم‌حوزه اول پیشنهاد می‌شوند؛ با «فقط تخصصی» هیچ سایت دیگری انتخاب نمی‌شود.</p></td></tr>
                    <tr><th>زبان</th><td><select name="lang"><?php foreach (['fa' => 'فارسی', 'en' => 'انگلیسی', 'any' => 'هر زبان'] as $k => $l) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($v('lang', 'fa'), $k, false), esc_html($l)); ?></select></td></tr>
                    <tr><th>سقف ثبت</th><td>
                        روزانه <input type="number" name="daily_cap" min="1" max="50" value="<?php echo (int) $v('daily_cap', 3); ?>" style="width:70px">
                        هفتگی <input type="number" name="weekly_cap" min="1" max="200" value="<?php echo (int) $v('weekly_cap', 10); ?>" style="width:70px">
                        <p class="description">پیشنهاد: ۳ در روز و ۱۰ در هفته؛ افزایشِ ناگهانی لینک الگوی غیرطبیعی می‌سازد.</p></td></tr>
                    <tr><th>فعال</th><td><label><input type="checkbox" name="active" value="1" <?php checked((int) $v('active', 1), 1); ?>> در اجرای خودکار شرکت کند</label></td></tr>
                </table>
                <p><button class="button button-primary"><?php echo $edit ? 'ذخیره' : 'ساخت کمپین'; ?></button>
                <?php if ($edit) echo '<a class="button" href="' . esc_url(self::url('campaigns')) . '">انصراف</a>'; ?></p>
            </form>
        </div>

        <div class="bp-card">
            <h3>کمپین‌ها</h3>
            <table class="widefat striped">
                <thead><tr><th>نام</th><th>سایت</th><th>سقف (روز/هفته)</th><th>برنامه‌ها</th><th>پروفایل سایت</th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows) echo '<tr><td colspan="6">کمپینی نیست.</td></tr>'; ?>
                <?php foreach ($rows as $r) :
                    $cnt = $wpdb->get_results($wpdb->prepare('SELECT status, COUNT(*) c FROM ' . BP_DB::t('plans') . ' WHERE campaign_id=%d GROUP BY status', $r->id), OBJECT_K);
                    $parts = [];
                    foreach (self::statuses() as $k => $l) if (isset($cnt[$k])) $parts[] = $l . ': ' . (int) $cnt[$k]->c;
                    ?>
                    <tr>
                        <td><b><?php echo esc_html($r->name); ?></b><?php echo $r->active ? '' : ' <em>(غیرفعال)</em>'; ?><?php echo (isset($r->source) && $r->source === 'auto') ? ' <span class="bp-s bp-live">خودکار</span>' : ''; ?></td>
                        <td style="direction:ltr"><?php echo esc_html($r->url); ?></td>
                        <td><?php echo (int) $r->daily_cap . ' / ' . (int) $r->weekly_cap; ?></td>
                        <td><?php echo esc_html($parts ? implode('، ', $parts) : '—'); ?></td>
                        <td><?php echo $r->profile_at ? esc_html($r->profile_at) : 'خوانده نشده'; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(self::url('campaigns', ['edit' => $r->id])); ?>">ویرایش</a>
                            <a class="button button-small" href="<?php echo esc_url(self::nl('campaign_profile', ['id' => $r->id])); ?>">خواندن سایت</a>
                            <a class="button button-small button-primary" href="<?php echo esc_url(self::nl('run_agent', ['id' => $r->id])); ?>">اجرای ایجنت</a>
                            <a class="button button-small" onclick="return confirm('کمپین و برنامه‌های پیش‌نویسش حذف شود؟ رکوردهای منتشرشده/انجام‌شده و لاگ‌ها نگه داشته می‌شوند.')" href="<?php echo esc_url(self::nl('delete_campaign', ['id' => $r->id])); ?>">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ---------- تب: ایجنت و صف ---------- */

    public static function tab_agent() {
        global $wpdb;
        $tp = BP_DB::t('plans');
        $camps = $wpdb->get_results('SELECT id, name FROM ' . BP_DB::t('campaigns') . ' ORDER BY id DESC');
        $cid = (int) ($_GET['campaign'] ?? 0);
        $st = sanitize_key($_GET['status'] ?? '');
        $s = BP_Settings::all();
        $u = BP_AI::usage();
        $modes = ['suggest' => 'پیشنهاددهنده (همه‌چیز دستی)', 'semi' => 'نیمه‌خودکار (برنامه‌ریزی خودکار، تأیید دستی)', 'auto' => 'خودکار (برنامه‌ریزی + تولید متن)'];

        $where = ['1=1'];
        $args = [];
        if ($cid) { $where[] = 'campaign_id=%d'; $args[] = $cid; }
        if ($st && isset(self::statuses()[$st])) { $where[] = 'status=%s'; $args[] = $st; }
        $sql = "SELECT * FROM $tp WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 100';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
        $names = [];
        foreach ($camps as $c) $names[$c->id] = $c->name;
        ?>
        <div class="bp-card">
            <p>حالت ایجنت: <b><?php echo esc_html($modes[$s['autonomy']] ?? $s['autonomy']); ?></b>
               — مصرف امروز: <b><?php echo number_format_i18n($u['tokens']); ?></b> از <?php echo number_format_i18n($s['token_cap']); ?> توکن
               (<?php echo (int) $u['calls']; ?> درخواست) — <a href="<?php echo esc_url(self::url('ai')); ?>">تنظیمات AI</a></p>
            <p style="color:#666">انتشار خودکار روی پلتفرم‌های دارای API از تب «اتوپایلوت» انجام می‌شود؛ بقیه‌ی دامنه‌ها (مطلب مهمان/رپورتاژ سایت‌های دیگر) هنوز دستی‌اند: ایجنت برنامه و متن آماده می‌کند، شما ثبت می‌کنید و «انجام شد» را می‌زنید.</p>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <input type="hidden" name="tab" value="agent">
                <select name="campaign"><option value="0">همه‌ی کمپین‌ها</option>
                    <?php foreach ($camps as $c) printf('<option value="%d"%s>%s</option>', (int) $c->id, selected($cid, (int) $c->id, false), esc_html($c->name)); ?></select>
                <select name="status"><option value="">همه‌ی وضعیت‌ها</option>
                    <?php foreach (self::statuses() as $k => $l) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($st, $k, false), esc_html($l)); ?></select>
                <button class="button">فیلتر</button>
                <?php if ($cid) echo '<a class="button button-primary" href="' . esc_url(self::nl('run_agent', ['id' => $cid])) . '">اجرای ایجنت برای این کمپین</a>'; ?>
            </form>
        </div>

        <table class="widefat striped">
            <thead><tr><th>کمپین</th><th>دامنه</th><th>روش</th><th>انکر</th><th>دلیل انتخاب</th><th>وضعیت</th><th>متن</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (!$rows) echo '<tr><td colspan="8">برنامه‌ای نیست. در تب «کمپین‌ها» ایجنت را اجرا کنید.</td></tr>'; ?>
            <?php foreach ($rows as $r) :
                $has = ($r->article_body !== null && $r->article_body !== '') || ($r->email_body !== null && $r->email_body !== '');
                $sub = $wpdb->get_var($wpdb->prepare('SELECT submit_url FROM ' . BP_DB::t('domains') . ' WHERE domain=%s', $r->domain));
                ?>
                <tr>
                    <td><?php echo esc_html($names[$r->campaign_id] ?? '—'); ?></td>
                    <td style="direction:ltr"><a href="<?php echo esc_url('https://' . $r->domain); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html($r->domain); ?></a>
                        <?php if ($sub) echo '<br><a href="' . esc_url($sub) . '" target="_blank" rel="noopener nofollow">صفحه‌ی ثبت</a>'; ?></td>
                    <td><?php echo esc_html(self::methods()[$r->method] ?? $r->method); ?></td>
                    <td><?php echo esc_html($r->anchor_text); ?><br><small style="color:#666"><?php echo esc_html($r->anchor_type); ?></small></td>
                    <td style="max-width:260px"><?php echo esc_html($r->reason); ?></td>
                    <td><b><?php echo esc_html(self::statuses()[$r->status] ?? $r->status); ?></b></td>
                    <td style="min-width:200px">
                        <?php if ($has) : ?>
                            <details><summary>مشاهده</summary>
                            <?php if ($r->email_body) : ?>
                                <p><b>موضوع:</b> <?php echo esc_html($r->email_subject); ?></p>
                                <textarea readonly rows="8" style="width:100%" onclick="this.select()"><?php echo esc_textarea($r->email_body); ?></textarea>
                            <?php else : ?>
                                <p><b><?php echo esc_html($r->article_title); ?></b></p>
                                <textarea readonly rows="8" style="width:100%;direction:ltr" onclick="this.select()"><?php echo esc_textarea($r->article_body); ?></textarea>
                                <div style="border:1px solid #dcdcde;padding:8px;margin-top:6px;max-height:220px;overflow:auto;background:#fff"><?php echo wp_kses_post($r->article_body); ?></div>
                            <?php endif; ?>
                            </details>
                        <?php else : echo '—'; endif; ?>
                    </td>
                    <td>
                        <?php if ($r->status === 'proposed') : ?>
                            <a href="<?php echo esc_url(self::nl('plan_action', ['do' => 'approve', 'id' => $r->id])); ?>">تأیید</a> |
                            <a href="<?php echo esc_url(self::nl('plan_action', ['do' => 'reject', 'id' => $r->id])); ?>">رد</a> |
                        <?php endif; ?>
                        <a href="<?php echo esc_url(self::nl('plan_action', ['do' => 'gen', 'id' => $r->id])); ?>"><?php echo $has ? 'تولید مجدد' : 'تولید متن'; ?></a> |
                        <?php if ($r->status === 'approved') : ?>
                            <a href="<?php echo esc_url(self::nl('plan_action', ['do' => 'done', 'id' => $r->id])); ?>">انجام شد</a> |
                        <?php endif; ?>
                        <a href="<?php echo esc_url(self::nl('plan_action', ['do' => 'delete', 'id' => $r->id])); ?>" onclick="return confirm('حذف شود؟')">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public static function ajax_models() {
        check_ajax_referer('bp_action', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => 'دسترسی کافی ندارید.'], 403);
        $p = sanitize_key($_POST['provider'] ?? '');
        if (!isset(BP_AI::names()[$p])) wp_send_json_error(['msg' => 'ارائه‌دهنده‌ی نامعتبر.']);
        $r = BP_AI::fetch_models($p);
        if (is_wp_error($r)) wp_send_json_error(['msg' => $r->get_error_message()]);
        wp_send_json_success(['groups' => $r['groups'], 'count' => $r['count']]);
    }

    /** کشویی انتخاب مدل (به‌جای تایپ دستی). مقدار نهایی در فیلد model_xxx ارسال می‌شود. */
    private static function model_select($p, $name, $current) {
        $cat = BP_AI::model_catalog($p);
        $known = [];
        foreach ($cat['groups'] as $g) foreach ($g['items'] as $it) $known[$it['id']] = 1;
        echo '<span class="bp-model" data-provider="' . esc_attr($p) . '">';
        printf('<select name="%s" class="bp-model-sel" style="direction:ltr;min-width:300px;max-width:100%%">', esc_attr($name));
        if ($current === '') echo '<option value="" selected>— یک مدل انتخاب کنید —</option>';
        if ($current !== '' && !isset($known[$current])) {
            printf('<optgroup label="مدل فعلی"><option value="%1$s" selected>%1$s</option></optgroup>', esc_attr($current));
        }
        foreach ($cat['groups'] as $g) {
            printf('<optgroup label="%s">', esc_attr($g['label']));
            foreach ($g['items'] as $it) {
                printf('<option value="%s"%s>%s</option>', esc_attr($it['id']), selected($current, $it['id'], false), esc_html($it['label']));
            }
            echo '</optgroup>';
        }
        echo '<option value="__custom">✎ مدل دیگر (تایپ دستی)…</option></select> ';
        printf('<input type="text" name="%s_custom" class="bp-model-custom regular-text" style="direction:ltr;display:none" placeholder="model-id" autocomplete="off"> ', esc_attr($name));
        echo '<button type="button" class="button bp-model-refresh">بروزرسانی فهرست</button> ';
        echo '<span class="bp-model-msg" style="color:#646970;font-size:12px">' . ($cat['live'] ? 'فهرست زنده از سرویس' : 'فهرست آماده') . '</span>';
        echo '</span>';
    }

    private static function model_script() {
        ?>
        <script>
        (function () {
            var ajax = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var nonce = <?php echo wp_json_encode(wp_create_nonce('bp_action')); ?>;
            function sync(w) {
                var sel = w.querySelector('.bp-model-sel'), c = w.querySelector('.bp-model-custom');
                c.style.display = sel.value === '__custom' ? '' : 'none';
                if (sel.value === '__custom') c.focus();
            }
            function build(sel, groups) {
                var cur = sel.value, known = {}, html = document.createDocumentFragment();
                groups.forEach(function (g) {
                    var og = document.createElement('optgroup'); og.label = g.label;
                    g.items.forEach(function (it) {
                        var o = document.createElement('option'); o.value = it.id; o.textContent = it.label;
                        og.appendChild(o); known[it.id] = 1;
                    });
                    html.appendChild(og);
                });
                sel.innerHTML = '';
                if (cur && cur !== '__custom' && !known[cur]) {
                    var og0 = document.createElement('optgroup'); og0.label = 'مدل فعلی';
                    var o0 = document.createElement('option'); o0.value = cur; o0.textContent = cur; og0.appendChild(o0);
                    sel.appendChild(og0);
                }
                sel.appendChild(html);
                var oc = document.createElement('option'); oc.value = '__custom'; oc.textContent = '✎ مدل دیگر (تایپ دستی)…';
                sel.appendChild(oc);
                sel.value = cur || (groups[0] && groups[0].items[0] ? groups[0].items[0].id : '');
            }
            document.querySelectorAll('.bp-model').forEach(function (w) {
                var sel = w.querySelector('.bp-model-sel'), btn = w.querySelector('.bp-model-refresh'), msg = w.querySelector('.bp-model-msg');
                sel.addEventListener('change', function () { sync(w); });
                btn.addEventListener('click', function () {
                    btn.disabled = true; msg.style.color = '#646970'; msg.textContent = 'در حال دریافت…';
                    var fd = new FormData();
                    fd.append('action', 'bp_models'); fd.append('_wpnonce', nonce); fd.append('provider', w.dataset.provider);
                    fetch(ajax, {method: 'POST', body: fd, credentials: 'same-origin'})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            btn.disabled = false;
                            if (j && j.success) { build(sel, j.data.groups); sync(w); msg.style.color = '#1a7f37'; msg.textContent = j.data.count + ' مدل از سرویس دریافت شد'; }
                            else { msg.style.color = '#b32d2e'; msg.textContent = (j && j.data && j.data.msg) || 'خطا در دریافت فهرست'; }
                        })
                        .catch(function () { btn.disabled = false; msg.style.color = '#b32d2e'; msg.textContent = 'ارتباط با سرور برقرار نشد'; });
                });
                sync(w);
            });
        })();
        </script>
        <?php
    }

    /* ---------- تب: AI ---------- */

    public static function tab_ai() {
        $s = BP_Settings::all();
        $u = BP_AI::usage();
        $names = BP_AI::names();
        $field = function ($label, $key, $secret_key, $model_key, $hint = '') use ($s) {
            ?>
            <tr><th><?php echo esc_html($label); ?></th><td>
                <input type="password" name="<?php echo esc_attr($secret_key); ?>" class="regular-text" style="direction:ltr" autocomplete="new-password"
                    placeholder="<?php echo BP_Settings::has_secret($secret_key) ? '•••••••• (ذخیره شده)' : 'API Key'; ?>">
                <label><input type="checkbox" name="clear_<?php echo esc_attr($secret_key); ?>" value="1"> پاک کردن</label><br>
                مدل: <?php self::model_select($key, $model_key, (string) $s[$model_key]); ?>
                <?php if (BP_AI::configured($key)) echo ' <a class="button button-small" href="' . esc_url(self::nl('ai_test', ['provider' => $key])) . '">تست اتصال</a>'; ?>
                <?php if ($hint) echo '<p class="description">' . esc_html($hint) . '</p>'; ?>
            </td></tr>
            <?php
        };
        $chain_row = function ($label, $name, $cur) use ($names) {
            echo '<tr><th>' . esc_html($label) . '</th><td>';
            for ($i = 0; $i < 3; $i++) {
                printf('<select name="%s[]" style="margin-left:6px"><option value="">—</option>', esc_attr($name));
                foreach ($names as $k => $l) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($cur[$i] ?? '', $k, false), esc_html(($i + 1) . '. ' . $l));
                echo '</select>';
            }
            echo '<p class="description">به ترتیب امتحان می‌شود؛ با خطا یا پر شدن سقف، سراغ بعدی می‌رود.</p></td></tr>';
        };
        ?>
        <p>مصرف امروز: <b><?php echo number_format_i18n($u['tokens']); ?></b> توکن، <?php echo (int) $u['calls']; ?> درخواست.
           <?php foreach ($u['by'] as $p => $n) echo ' | ' . esc_html($names[$p] ?? $p) . ': ' . number_format_i18n($n); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bp-card">
            <?php wp_nonce_field('bp_action'); ?>
            <input type="hidden" name="action" value="bp_save_ai">
            <h3>کلیدها و مدل‌ها</h3>
            <table class="form-table" role="presentation">
                <?php
                $field('Gemini', 'gemini', 'gemini_key', 'model_gemini', 'کلید رایگان از Google AI Studio. در پلن رایگان فقط مدل‌های Flash/Flash-Lite در دسترس‌اند؛ برای دیدن مدل‌های واقعیِ حساب خودتان، کلید را ذخیره کنید و «بروزرسانی فهرست» را بزنید.');
                $field('Groq', 'groq', 'groq_key', 'model_groq', 'کلید رایگان از console.groq.com. سقف توکن در دقیقه/روز را در کنسول چک کنید.');
                $field('OpenRouter', 'openrouter', 'openrouter_key', 'model_openrouter', 'مدل‌های رایگان با پسوند :free.');
                ?>
                <tr><th>سرور سفارشی</th><td>
                    آدرس: <input type="url" name="custom_url" class="regular-text" style="direction:ltr" value="<?php echo esc_attr($s['custom_url']); ?>" placeholder="http://127.0.0.1:11434/v1"><br>
                    <input type="password" name="custom_key" class="regular-text" style="direction:ltr" autocomplete="new-password" placeholder="<?php echo BP_Settings::has_secret('custom_key') ? '•••••••• (ذخیره شده)' : 'API Key (اختیاری)'; ?>">
                    <label><input type="checkbox" name="clear_custom_key" value="1"> پاک کردن</label><br>
                    مدل: <?php self::model_select('custom', 'model_custom', (string) $s['model_custom']); ?>
                    <?php if (BP_AI::configured('custom')) echo ' <a class="button button-small" href="' . esc_url(self::nl('ai_test', ['provider' => 'custom'])) . '">تست اتصال</a>'; ?>
                    <p class="description">هر endpoint سازگار با OpenAI (Ollama، LM Studio، vLLM). این درخواست بدون پروکسی ارسال می‌شود.</p></td></tr>
            </table>

            <?php BP_Guide::box('ai_keys', 'مصرف امروز: ' . number_format_i18n($u['tokens']) . ' توکن'); ?>
            <details style="margin:10px 0"><summary style="cursor:pointer;font-weight:600">پیشرفته: ترتیب fallback ارائه‌دهنده‌ها (معمولاً دست نزنید)</summary>
            <?php BP_Guide::box('ai_chain'); ?>
            <table class="form-table" role="presentation">
                <?php
                $chain_row('تصمیم‌گیری (کوتاه، سریع)', 'chain_decide', $s['chain_decide']);
                $chain_row('نوشتن متن (طولانی)', 'chain_write', $s['chain_write']);
                ?>
            </table>
            </details>

            <h3>استقلال و سقف‌ها</h3>
            <table class="form-table" role="presentation">
                <tr><th>حالت ایجنت</th><td><select name="autonomy">
                    <?php foreach (['suggest' => 'پیشنهاددهنده — فقط با دکمه‌ی شما اجرا می‌شود', 'semi' => 'نیمه‌خودکار — هر ۱۰ دقیقه برنامه می‌سازد، تأیید با شما', 'auto' => 'خودکار — برنامه‌ریزی، تأیید و تولید متن (بدون انتشار)'] as $k => $l) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['autonomy'], $k, false), esc_html($l)); ?>
                </select><p class="description">پیش‌فرض: پیشنهاددهنده. حالت‌های خودکار از WP-Cron استفاده می‌کنند و سقف روزانه‌ی کمپین را رعایت می‌کنند.</p><?php BP_Guide::box('autonomy'); ?></td></tr>
                <tr><th>سقف توکن روزانه</th><td><input type="number" name="token_cap" min="10000" max="5000000" step="10000" value="<?php echo (int) $s['token_cap']; ?>"><p class="description">با رسیدن به این عدد، ایجنت تا فردا AI صدا نمی‌زند.</p></td></tr>
                <tr><td></td><td><?php BP_Guide::box('token_cap', 'مصرف امروز: ' . number_format_i18n($u['tokens']) . ' از ' . number_format_i18n((int) $s['token_cap']) . ' توکن'); ?></td></tr>
                <tr><th>پروکسی</th><td><label><input type="checkbox" name="ai_proxy" value="1" <?php checked($s['ai_proxy']); ?>> درخواست‌های AI ابری از پروکسی تب «تنظیمات» عبور کنند</label></td></tr>
            </table>
            <?php BP_Guide::box('ai_proxy'); ?>
            <p><button class="button button-primary">ذخیره</button></p>
        </form>
        <p style="color:#666">توجه: در پلن‌های رایگان بیشتر سرویس‌ها گارانتی حریم خصوصی ندارند؛ اطلاعات محرمانه‌ی مشتری را در موضوع/کلیدواژه نگذارید.</p>
        <?php self::model_script();
    }

    /* ---------- تب: لاگ ---------- */

    public static function tab_logs() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . BP_DB::t('logs') . ' ORDER BY id DESC LIMIT 150');
        ?>
        <p><a class="button" onclick="return confirm('همه‌ی لاگ‌ها پاک شود؟')" href="<?php echo esc_url(self::nl('clear_logs')); ?>">پاک کردن لاگ‌ها</a></p>
        <table class="widefat striped">
            <thead><tr><th>زمان</th><th>نوع</th><th>سطح</th><th>پیام</th></tr></thead>
            <tbody>
            <?php if (!$rows) echo '<tr><td colspan="4">لاگی نیست.</td></tr>'; ?>
            <?php foreach ($rows as $r) : ?>
                <tr><td><?php echo esc_html($r->created_at); ?></td><td><?php echo esc_html($r->kind); ?></td>
                    <td style="color:<?php echo $r->level === 'error' ? '#b32d2e' : ($r->level === 'warn' ? '#b26200' : '#1a7f37'); ?>"><?php echo esc_html($r->level); ?></td>
                    <td><?php echo esc_html($r->message); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

/* ============================================================
 * v1.5 — BP_Publisher: انتشار واقعی روی پلتفرم‌های دارای API
 * ============================================================ */
class BP_Publisher {

    /** حداکثر تعداد وردپرس شخصی */
    const WP_MAX = 100;

    /** پلتفرم‌های پشتیبانی‌شده. needs = فیلدهای لازم در تنظیمات اتوپایلوت */
    public static function platforms() {
        $base = [
            'telegraph' => ['label' => 'Telegra.ph', 'domain' => 'telegra.ph', 'needs' => [], 'langs' => ['fa', 'en', 'any'],
                            'note' => 'بدون کلید؛ حساب ناشناس خودکار ساخته می‌شود.'],
            'writeas'   => ['label' => 'Write.as', 'domain' => 'write.as', 'needs' => [], 'langs' => ['fa', 'en', 'any'],
                            'note' => 'بدون کلید؛ انتشار ناشناس.'],
            'devto'     => ['label' => 'DEV.to', 'domain' => 'dev.to', 'needs' => ['devto_key'], 'langs' => ['en'],
                            'note' => 'کلید API لازم (Settings → Extensions). فقط محتوای انگلیسی و فنی؛ محتوای تبلیغاتی حساب را مسدود می‌کند.'],
            'hashnode'  => ['label' => 'Hashnode', 'domain' => 'hashnode.com', 'needs' => ['hashnode_token', 'hashnode_pub'], 'langs' => ['en'],
                            'note' => 'توکن شخصی + شناسه‌ی Publication. فقط انگلیسی و فنی. (آزمایشی)'],
        ];
        // v1.9: پلتفرم‌های دارای API رسمی
        $extra = [
            'ghost'    => ['Ghost', 'ghost.io', 'وبلاگ Ghost شما (Admin API).'],
            'blogger'  => ['Blogger', 'blogspot.com', 'وبلاگ Blogger شما (OAuth).'],
            'tumblr'   => ['Tumblr', 'tumblr.com', 'وبلاگ Tumblr شما (OAuth 1.0a).'],
            'wpcom'    => ['WordPress.com', 'wordpress.com', 'سایت WordPress.com شما (OAuth).'],
            'mastodon' => ['Mastodon', 'mastodon.social', 'پست کوتاه همراه لینک (nofollow).'],
            'bluesky'  => ['Bluesky', 'bsky.app', 'پست کوتاه همراه لینک (nofollow).'],
        ];
        foreach ($extra as $k => $e) {
            $base[$k] = ['label' => $e[0], 'domain' => $e[1], 'needs' => array_map(function ($f) { return $f['name']; }, self::fields($k)),
                         'langs' => ['fa', 'en', 'any'], 'note' => $e[2]];
        }
        foreach (self::wp_sites() as $i => $w) {
            $base['wp' . ($i + 1)] = ['label' => 'WP: ' . $w['host'], 'domain' => $w['host'], 'needs' => [], 'langs' => ['fa', 'en', 'any'],
                                      'note' => 'وردپرس شما (REST API)' . ($w['status'] === 'draft' ? ' — به‌صورت پیش‌نویس ذخیره می‌شود' : '') . '.'];
        }
        return $base;
    }


    /**
     * v1.9: فیلدهای اتصال هر پلتفرم. secret=true یعنی رمزنگاری‌شده ذخیره می‌شود.
     * san: روش پاک‌سازی فیلدهای متنی (alnum|digits|url|host|handle|text)
     */
    public static function fields($k) {
        $f = function ($name, $label, $secret, $ph, $san = 'text') {
            return ['name' => $name, 'label' => $label, 'secret' => $secret, 'ph' => $ph, 'san' => $san];
        };
        switch ($k) {
            case 'devto':     return [$f('devto_key', 'DEV.to API key', true, 'کلید را اینجا بچسبانید')];
            case 'hashnode':  return [$f('hashnode_token', 'Personal Access Token', true, 'توکن را اینجا بچسبانید'),
                                      $f('hashnode_pub', 'Publication ID', false, 'مثلاً 64f1a2b3c4d5e6f7a8b9c0d1', 'alnum')];
            case 'ghost':     return [$f('ghost_url', 'آدرس وبلاگ Ghost', false, 'https://yourblog.ghost.io', 'url'),
                                      $f('ghost_key', 'Admin API Key (به شکل id:secret)', true, '5f1c…:9ab3…')];
            case 'blogger':   return [$f('blogger_client_id', 'OAuth Client ID', false, 'xxxx.apps.googleusercontent.com'),
                                      $f('blogger_client_secret', 'OAuth Client Secret', true, 'GOCSPX-…'),
                                      $f('blogger_refresh', 'Refresh Token', true, '1//0g…'),
                                      $f('blogger_blog', 'Blog ID (عدد)', false, '1234567890123456789', 'digits')];
            case 'tumblr':    return [$f('tumblr_ck', 'Consumer Key', true, 'OAuth Consumer Key'),
                                      $f('tumblr_cs', 'Consumer Secret', true, 'OAuth Consumer Secret'),
                                      $f('tumblr_token', 'Token', true, 'OAuth Token'),
                                      $f('tumblr_ts', 'Token Secret', true, 'OAuth Token Secret'),
                                      $f('tumblr_blog', 'نام وبلاگ', false, 'myblog.tumblr.com', 'host')];
            case 'wpcom':     return [$f('wpcom_token', 'OAuth Access Token', true, 'توکن را اینجا بچسبانید'),
                                      $f('wpcom_site', 'آدرس سایت', false, 'myblog.wordpress.com', 'host')];
            case 'mastodon':  return [$f('mastodon_url', 'آدرس سرور (Instance)', false, 'https://mastodon.social', 'url'),
                                      $f('mastodon_token', 'Access Token (دسترسی write:statuses)', true, 'توکن را اینجا بچسبانید')];
            case 'bluesky':   return [$f('bluesky_handle', 'Handle', false, 'name.bsky.social', 'handle'),
                                      $f('bluesky_pass', 'App Password', true, 'xxxx-xxxx-xxxx-xxxx')];
        }
        return [];
    }

    public static function all_fields() {
        $out = [];
        foreach (['devto', 'hashnode', 'ghost', 'blogger', 'tumblr', 'wpcom', 'mastodon', 'bluesky'] as $k) {
            foreach (self::fields($k) as $f) $out[$f['name']] = $f;
        }
        return $out;
    }

    public static function secret_names() {
        $out = [];
        foreach (self::all_fields() as $n => $f) if ($f['secret']) $out[] = $n;
        return $out;
    }

    /** پاک‌سازی مقدار فیلد متنی */
    public static function clean_field($v, $san) {
        $v = trim((string) $v);
        switch ($san) {
            case 'alnum':  return preg_replace('~[^A-Za-z0-9]~', '', $v);
            case 'digits': return preg_replace('~\D~', '', $v);
            case 'url':    $u = esc_url_raw($v); return preg_match('~^https://[^\s/]+~i', $u) ? untrailingslashit($u) : '';
            case 'host':   return preg_replace('~[^a-z0-9.\-]~', '', strtolower(preg_replace('~^https?://|[/?#].*$~i', '', $v)));
            case 'handle': return preg_replace('~[^a-z0-9.\-]~', '', strtolower(ltrim($v, '@')));
        }
        return sanitize_text_field($v);
    }

    /** v1.9: اطلاعات نمایشی کارت‌ها (برچسب‌ها، توضیح کوتاه، راهنمای گرفتن کلید) */
    public static function meta($k) {
        $m = [
            'telegraph' => ['rec' => 1, 'badges' => [['بدون نیاز به کلید', 'ok'], ['فارسی و انگلیسی', '']],
                            'desc' => 'انتشار فوری و بدون ثبت‌نام. حساب ناشناس به‌صورت خودکار ساخته می‌شود.'],
            'writeas'   => ['rec' => 1, 'badges' => [['بدون نیاز به کلید', 'ok'], ['فارسی و انگلیسی', '']],
                            'desc' => 'انتشار ناشناس و سریع، با صفحه‌های تمیز و سبک.'],
            'devto'     => ['rec' => 0, 'badges' => [['نیاز به کلید API', 'warn'], ['فقط انگلیسی و فنی', '']],
                            'desc' => 'مناسب مقاله‌های فنی. محتوای تبلیغاتی ممکن است حساب را مسدود کند.',
                            'help' => ['https://dev.to/settings/extensions', 'گرفتن کلید: Settings ← Extensions ← Generate API Key']],
            'hashnode'  => ['rec' => 0, 'badges' => [['نیاز به توکن', 'warn'], ['فقط انگلیسی و فنی', ''], ['آزمایشی', 'info']],
                            'desc' => 'انتشار روی وبلاگ Hashnode شما. به توکن شخصی و شناسه‌ی Publication نیاز دارد.',
                            'help' => ['https://hashnode.com/settings/developer', 'گرفتن توکن: Settings ← Developer ← Generate New Token']],
            'ghost'     => ['rec' => 0, 'badges' => [['نیاز به کلید Admin', 'warn'], ['لینک follow', 'ok'], ['فارسی و انگلیسی', '']],
                            'desc' => 'انتشار روی وبلاگ Ghost خودتان. مناسب شبکه‌ی وبلاگ‌های شخصی.',
                            'help' => ['https://ghost.org/docs/admin-api/', 'گرفتن کلید: Settings ← Integrations ← Add custom integration']],
            'blogger'   => ['rec' => 0, 'badges' => [['نیاز به OAuth', 'warn'], ['لینک follow', 'ok'], ['فارسی و انگلیسی', '']],
                            'desc' => 'انتشار روی وبلاگ Blogger. تنظیم اولیه کمی طولانی است ولی بعد از آن کاملاً خودکار کار می‌کند.',
                            'help' => ['https://developers.google.com/blogger/docs/3.0/using', 'راهنمای OAuth 2.0 بلاگر (Client ID، Secret و Refresh Token)']],
            'tumblr'    => ['rec' => 0, 'badges' => [['نیاز به OAuth', 'warn'], ['nofollow', 'info'], ['فارسی و انگلیسی', '']],
                            'desc' => 'انتشار روی وبلاگ Tumblr. بیشتر برای تنوع پروفایل لینک و ترافیک مفید است.',
                            'help' => ['https://www.tumblr.com/oauth/apps', 'ساخت اپلیکیشن: Tumblr ← Register application (کلیدهای OAuth1)']],
            'wpcom'     => ['rec' => 0, 'badges' => [['نیاز به توکن', 'warn'], ['فارسی و انگلیسی', '']],
                            'desc' => 'انتشار روی سایت‌های WordPress.com شما از طریق API رسمی.',
                            'help' => ['https://developer.wordpress.com/apps/', 'ساخت اپلیکیشن و گرفتن توکن: developer.wordpress.com/apps']],
            'mastodon'  => ['rec' => 0, 'badges' => [['نیاز به توکن', 'warn'], ['nofollow', 'info'], ['پست کوتاه', '']],
                            'desc' => 'یک پست کوتاه با عنوان، خلاصه و لینک. برای تنوع و ردپای اجتماعی.',
                            'help' => ['https://mastodon.social/settings/applications', 'گرفتن توکن: Preferences ← Development ← New application (دسترسی write:statuses)']],
            'bluesky'   => ['rec' => 0, 'badges' => [['نیاز به App Password', 'warn'], ['nofollow', 'info'], ['پست کوتاه', '']],
                            'desc' => 'یک پست کوتاه با عنوان، خلاصه و لینک قابل‌کلیک.',
                            'help' => ['https://bsky.app/settings/app-passwords', 'گرفتن رمز: Settings ← Privacy and security ← App passwords']],
        ];
        return $m[$k] ?? ['rec' => 0, 'badges' => [['وردپرس شما', 'ok'], ['REST API', '']],
                          'desc' => 'انتشار مستقیم روی سایت وردپرسی خودتان؛ مؤثرترین گزینه برای اعتبار لینک.'];
    }

    /** سایت‌های وردپرسی خود کاربر: هر خط «آدرس | نام‌کاربری | Application Password | شناسه‌ی دسته (اختیاری) | publish/draft (اختیاری)» */
    public static function wp_sites() {
        $o = get_option('bp_auto', []);
        $raw = !empty($o['wp_sites']) ? BP_Settings::dec((string) $o['wp_sites']) : '';
        $out = [];
        foreach (preg_split('~\R~u', (string) $raw) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 3) continue;
            $url = esc_url_raw($p[0]);
            $host = wp_parse_url($url, PHP_URL_HOST);
            if (!preg_match('~^https?://~i', $url) || !$host || $p[1] === '' || $p[2] === '') continue;
            $out[] = ['url' => untrailingslashit($url), 'user' => $p[1], 'pass' => $p[2], 'cat' => (int) ($p[3] ?? 0),
                      'status' => (($p[4] ?? '') === 'draft') ? 'draft' : 'publish', 'host' => strtolower($host)];
            if (count($out) >= self::WP_MAX) break;
        }
        return $out;
    }

    public static function ready($p, array $o) {
        $all = self::platforms();
        if (!isset($all[$p])) return false;
        foreach ($all[$p]['needs'] as $k) {
            if (empty($o[$k])) return false;
        }
        return true;
    }

    /** @return array|WP_Error  ['url'=>...] */
    public static function publish($p, $title, $html, $lang, array $o) {
        $title = trim(wp_strip_all_tags((string) $title));
        if ($title === '') $title = 'Untitled';
        switch ($p) {
            case 'telegraph': return self::telegraph($title, $html, $o);
            case 'writeas':   return self::writeas($title, $html, $lang);
            case 'devto':     return self::devto($title, $html, $o);
            case 'hashnode':  return self::hashnode($title, $html, $o);
            case 'ghost':     return self::ghost($title, $html, $o);
            case 'blogger':   return self::blogger($title, $html, $o);
            case 'tumblr':    return self::tumblr($title, $html, $o);
            case 'wpcom':     return self::wpcom($title, $html, $o);
            case 'mastodon':  return self::mastodon($title, $html, $lang, $o);
            case 'bluesky':   return self::bluesky($title, $html, $lang, $o);
        }
        if (preg_match('~^wp\d+$~', (string) $p)) return self::wprest($p, $title, $html);
        return new WP_Error('bp_pub', 'پلتفرم ناشناخته');
    }

    /* ---------- Telegra.ph ---------- */

    private static function telegraph($title, $html, array $o) {
        $tok = BP_Settings::dec((string) get_option('bp_telegraph_token', ''));
        if ($tok === '') {
            $name = mb_substr(preg_replace('~[^\p{L}\p{N} ]+~u', '', (string) get_bloginfo('name')), 0, 30);
            $r = BP_Http::post('https://api.telegra.ph/createAccount', ['body' => [
                'short_name' => $name !== '' ? $name : 'Writer', 'author_name' => $name !== '' ? $name : 'Writer',
            ], 'timeout' => 20]);
            $j = self::json($r);
            if (is_wp_error($j)) return $j;
            if (empty($j['ok']) || empty($j['result']['access_token'])) {
                return new WP_Error('bp_pub', 'Telegraph createAccount: ' . ($j['error'] ?? 'پاسخ نامعتبر'));
            }
            $tok = (string) $j['result']['access_token'];
            update_option('bp_telegraph_token', BP_Settings::enc($tok), false);
        }
        $nodes = self::nodes($html);
        if (!$nodes) return new WP_Error('bp_pub', 'محتوای قابل انتشار خالی است.');
        $r = BP_Http::post('https://api.telegra.ph/createPage', ['body' => [
            'access_token' => $tok, 'title' => mb_substr($title, 0, 250),
            'content' => wp_json_encode($nodes, JSON_UNESCAPED_UNICODE), 'return_content' => 'false',
        ], 'timeout' => 30]);
        $j = self::json($r);
        if (is_wp_error($j)) return $j;
        if (empty($j['ok']) || empty($j['result']['url'])) {
            return new WP_Error('bp_pub', 'Telegraph: ' . ($j['error'] ?? 'پاسخ نامعتبر'));
        }
        return ['url' => (string) $j['result']['url']];
    }

    /* ---------- Write.as ---------- */

    private static function writeas($title, $html, $lang) {
        $body = ['title' => mb_substr($title, 0, 250), 'body' => self::md($html)];
        if ($lang === 'fa') { $body['lang'] = 'fa'; $body['rtl'] = true; }
        elseif ($lang === 'en') { $body['lang'] = 'en'; }
        $r = BP_Http::post('https://write.as/api/posts', [
            'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE), 'timeout' => 30,
        ]);
        $j = self::json($r);
        if (is_wp_error($j)) return $j;
        $id = $j['data']['id'] ?? '';
        if ($id === '' || !preg_match('~^[A-Za-z0-9]+$~', (string) $id)) {
            return new WP_Error('bp_pub', 'Write.as: ' . ($j['error_msg'] ?? 'پاسخ نامعتبر'));
        }
        return ['url' => 'https://write.as/' . $id];
    }

    /* ---------- وردپرس خودتان (REST) ---------- */

    private static function wprest($p, $title, $html) {
        $i = (int) substr($p, 2) - 1;
        $sites = self::wp_sites();
        if (!isset($sites[$i])) return new WP_Error('bp_pub', 'سایت وردپرس تنظیم‌شده پیدا نشد.');
        $w = $sites[$i];
        $body = ['title' => mb_substr($title, 0, 250), 'content' => wp_kses_post((string) $html), 'status' => $w['status']];
        if ($w['cat'] > 0) $body['categories'] = [$w['cat']];
        $r = BP_Http::post($w['url'] . '/wp-json/wp/v2/posts', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode($w['user'] . ':' . $w['pass'])],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE), 'timeout' => 30,
        ], false, true);
        $j = self::json($r);
        if (is_wp_error($j)) return $j;
        if (empty($j['link'])) return new WP_Error('bp_pub', 'WordPress: ' . mb_substr((string) ($j['message'] ?? 'پاسخ نامعتبر'), 0, 200));
        return ['url' => (string) $j['link']];
    }

    /* ---------- DEV.to ---------- */

    private static function devto($title, $html, array $o) {
        $r = BP_Http::post('https://dev.to/api/articles', [
            'headers' => ['Content-Type' => 'application/json', 'api-key' => $o['devto_key']],
            'body' => wp_json_encode(['article' => ['title' => mb_substr($title, 0, 120), 'body_markdown' => self::md($html), 'published' => true, 'tags' => array_slice((array) ($o['_tags'] ?? []), 0, 4)]], JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);
        $j = self::json($r);
        if (is_wp_error($j)) return $j;
        if (empty($j['url'])) return new WP_Error('bp_pub', 'DEV.to: ' . ($j['error'] ?? 'پاسخ نامعتبر'));
        return ['url' => (string) $j['url']];
    }

    /* ---------- Hashnode (GraphQL) ---------- */

    private static function hashnode($title, $html, array $o) {
        $q = 'mutation Publish($input: PublishPostInput!) { publishPost(input: $input) { post { url } } }';
        $r = BP_Http::post('https://gql.hashnode.com', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => $o['hashnode_token']],
            'body' => wp_json_encode(['query' => $q, 'variables' => ['input' => [
                'title' => mb_substr($title, 0, 250), 'contentMarkdown' => self::md($html), 'publicationId' => $o['hashnode_pub'], 'tags' => [],
            ]]], JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);
        $j = self::json($r);
        if (is_wp_error($j)) return $j;
        if (!empty($j['errors'])) return new WP_Error('bp_pub', 'Hashnode: ' . mb_substr((string) ($j['errors'][0]['message'] ?? 'خطا'), 0, 200));
        $u = $j['data']['publishPost']['post']['url'] ?? '';
        if ($u === '') return new WP_Error('bp_pub', 'Hashnode: پاسخ نامعتبر');
        return ['url' => (string) $u];
    }

    /* ---------- v1.9: Ghost (Admin API + JWT) ---------- */

    private static function b64u($d) {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function ghost($title, $html, array $o) {
        if (!preg_match('~^([0-9a-f]{24}):([0-9a-f]{64})$~i', trim((string) $o['ghost_key']), $m)) {
            return new WP_Error('bp_pub', 'Ghost: کلید باید به شکل id:secret باشد (Admin API Key، نه Content API Key).');
        }
        $now = time();
        $h = self::b64u(wp_json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $m[1]]));
        $pl = self::b64u(wp_json_encode(['iat' => $now, 'exp' => $now + 300, 'aud' => '/admin/']));
        $sig = self::b64u(hash_hmac('sha256', $h . '.' . $pl, hex2bin($m[2]), true));
        $r = BP_Http::post(rtrim($o['ghost_url'], '/') . '/ghost/api/admin/posts/?source=html', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Ghost ' . $h . '.' . $pl . '.' . $sig, 'Accept-Version' => 'v5.0'],
            'body' => wp_json_encode(['posts' => [['title' => mb_substr($title, 0, 250), 'html' => wp_kses_post((string) $html), 'status' => 'published']]], JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ], false, true);
        $j = self::json($r);
        if (is_wp_error($j)) return new WP_Error('bp_pub', 'Ghost: ' . $j->get_error_message());
        $u = $j['posts'][0]['url'] ?? '';
        if ($u === '') return new WP_Error('bp_pub', 'Ghost: ' . mb_substr((string) ($j['errors'][0]['message'] ?? 'پاسخ نامعتبر'), 0, 200));
        return ['url' => (string) $u];
    }

    /* ---------- v1.9: Blogger (OAuth2 با Refresh Token) ---------- */

    private static function blogger($title, $html, array $o) {
        $ck = 'bp_blogger_at_' . md5($o['blogger_refresh']);
        $at = get_transient($ck);
        if (!$at) {
            $r = BP_Http::post('https://oauth2.googleapis.com/token', ['body' => [
                'client_id' => $o['blogger_client_id'], 'client_secret' => $o['blogger_client_secret'],
                'refresh_token' => $o['blogger_refresh'], 'grant_type' => 'refresh_token',
            ], 'timeout' => 20]);
            $j = self::json($r);
            if (is_wp_error($j)) return new WP_Error('bp_pub', 'Blogger (توکن): ' . $j->get_error_message());
            $at = (string) ($j['access_token'] ?? '');
            if ($at === '') return new WP_Error('bp_pub', 'Blogger: دریافت توکن ناموفق (' . mb_substr((string) ($j['error_description'] ?? $j['error'] ?? ''), 0, 150) . ')');
            set_transient($ck, $at, max(300, (int) ($j['expires_in'] ?? 3600) - 120));
        }
        $r = BP_Http::post('https://www.googleapis.com/blogger/v3/blogs/' . rawurlencode($o['blogger_blog']) . '/posts/', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $at],
            'body' => wp_json_encode(['kind' => 'blogger#post', 'title' => mb_substr($title, 0, 250), 'content' => wp_kses_post((string) $html)], JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);
        $j = self::json($r);
        if (is_wp_error($j)) { delete_transient($ck); return new WP_Error('bp_pub', 'Blogger: ' . $j->get_error_message()); }
        if (empty($j['url'])) return new WP_Error('bp_pub', 'Blogger: پاسخ نامعتبر');
        return ['url' => (string) $j['url']];
    }

    /* ---------- v1.9: Tumblr (OAuth 1.0a) ---------- */

    private static function oauth1_header($method, $url, array $params, array $o) {
        $enc = function ($v) { return rawurlencode((string) $v); };
        $op = ['oauth_consumer_key' => $o['tumblr_ck'], 'oauth_nonce' => wp_generate_password(24, false), 'oauth_signature_method' => 'HMAC-SHA1',
               'oauth_timestamp' => (string) time(), 'oauth_token' => $o['tumblr_token'], 'oauth_version' => '1.0'];
        $pairs = [];
        foreach (array_merge($params, $op) as $k => $v) $pairs[] = [$enc($k), $enc($v)];
        sort($pairs);
        $ps = implode('&', array_map(function ($p) { return $p[0] . '=' . $p[1]; }, $pairs));
        $base = strtoupper($method) . '&' . $enc($url) . '&' . $enc($ps);
        $op['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $enc($o['tumblr_cs']) . '&' . $enc($o['tumblr_ts']), true));
        $parts = [];
        foreach ($op as $k => $v) $parts[] = $enc($k) . '="' . $enc($v) . '"';
        return 'OAuth ' . implode(', ', $parts);
    }

    private static function tumblr($title, $html, array $o) {
        $blog = (string) $o['tumblr_blog'];
        if (strpos($blog, '.') === false) $blog .= '.tumblr.com';
        $url = 'https://api.tumblr.com/v2/blog/' . $blog . '/post';
        $params = ['type' => 'text', 'title' => mb_substr($title, 0, 250), 'body' => wp_kses_post((string) $html), 'format' => 'html', 'state' => 'published'];
        $tags = array_slice((array) ($o['_tags'] ?? []), 0, 5);
        if ($tags) $params['tags'] = implode(',', $tags);
        $r = BP_Http::post($url, [
            'headers' => ['Authorization' => self::oauth1_header('POST', $url, $params, $o)],
            'body' => $params, 'timeout' => 30,
        ]);
        $j = self::json($r);
        if (is_wp_error($j)) return new WP_Error('bp_pub', 'Tumblr: ' . $j->get_error_message());
        $id = (string) ($j['response']['id_string'] ?? ($j['response']['id'] ?? ''));
        if ($id === '' || !preg_match('~^\d+$~', $id)) return new WP_Error('bp_pub', 'Tumblr: ' . mb_substr((string) ($j['meta']['msg'] ?? 'پاسخ نامعتبر'), 0, 150));
        return ['url' => 'https://' . $blog . '/post/' . $id];
    }

    /* ---------- v1.9: WordPress.com (REST v1.1 + OAuth) ---------- */

    private static function wpcom($title, $html, array $o) {
        $r = BP_Http::post('https://public-api.wordpress.com/rest/v1.1/sites/' . rawurlencode($o['wpcom_site']) . '/posts/new', [
            'headers' => ['Authorization' => 'Bearer ' . $o['wpcom_token']],
            'body' => ['title' => mb_substr($title, 0, 250), 'content' => wp_kses_post((string) $html), 'status' => 'publish'],
            'timeout' => 30,
        ]);
        $j = self::json($r);
        if (is_wp_error($j)) return new WP_Error('bp_pub', 'WordPress.com: ' . $j->get_error_message());
        if (empty($j['URL'])) return new WP_Error('bp_pub', 'WordPress.com: ' . mb_substr((string) ($j['message'] ?? 'پاسخ نامعتبر'), 0, 200));
        return ['url' => (string) $j['URL']];
    }

    /* ---------- v1.9: پست کوتاه (Mastodon / Bluesky) ---------- */

    /** متن پست کوتاه: عنوان + خلاصه‌ی مقاله، تا سقف $room نویسه (بدون لینک) */
    private static function micro_body($title, $html, $room) {
        $title = trim(preg_replace('~\s+~u', ' ', wp_strip_all_tags((string) $title)));
        if (mb_strlen($title) > $room) return rtrim(mb_substr($title, 0, max(1, $room - 1))) . '…';
        $plain = trim(preg_replace('~\s+~u', ' ', wp_strip_all_tags((string) $html)));
        $left = $room - mb_strlen($title) - 2;
        if ($left < 40 || $plain === '') return $title;
        if (mb_strlen($plain) > $left) {
            $cut = mb_substr($plain, 0, $left - 1);
            $sp = mb_strrpos($cut, ' ');
            if ($sp !== false && $sp > $left * 0.6) $cut = mb_substr($cut, 0, $sp);
            $plain = rtrim($cut, " ,.;:،؛") . '…';
        }
        return $title . "\n\n" . $plain;
    }

    private static function mastodon($title, $html, $lang, array $o) {
        $target = (string) ($o['_target'] ?? '');
        if ($target === '') return new WP_Error('bp_pub', 'Mastodon: آدرس هدف مشخص نیست.');
        // در ماستودون هر لینک ۲۳ نویسه حساب می‌شود؛ حاشیه‌ی امن برای سرورهایی با سقف ۵۰۰
        $text = self::micro_body($title, $html, 500 - 23 - 4 - 10) . "\n\n" . $target;
        $r = BP_Http::post(rtrim($o['mastodon_url'], '/') . '/api/v1/statuses', [
            'headers' => ['Authorization' => 'Bearer ' . $o['mastodon_token'], 'Idempotency-Key' => wp_generate_uuid4()],
            'body' => ['status' => $text, 'visibility' => 'public', 'language' => $lang === 'en' ? 'en' : 'fa'],
            'timeout' => 30,
        ], false, true);
        $j = self::json($r);
        if (is_wp_error($j)) return new WP_Error('bp_pub', 'Mastodon: ' . $j->get_error_message());
        if (empty($j['url'])) return new WP_Error('bp_pub', 'Mastodon: پاسخ نامعتبر');
        return ['url' => (string) $j['url']];
    }

    private static function bsky_session(array $o, $fresh = false) {
        $ck = 'bp_bsky_' . md5($o['bluesky_handle']);
        $c = $fresh ? false : get_transient($ck);
        if (is_array($c) && !empty($c['jwt'])) return $c;
        $r = BP_Http::post('https://bsky.social/xrpc/com.atproto.server.createSession', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['identifier' => $o['bluesky_handle'], 'password' => $o['bluesky_pass']]), 'timeout' => 20,
        ]);
        $j = self::json($r);
        if (is_wp_error($j)) return new WP_Error('bp_pub', 'Bluesky (ورود): ' . $j->get_error_message());
        if (empty($j['accessJwt']) || empty($j['did'])) return new WP_Error('bp_pub', 'Bluesky: ورود ناموفق');
        $c = ['jwt' => (string) $j['accessJwt'], 'did' => (string) $j['did']];
        set_transient($ck, $c, 50 * MINUTE_IN_SECONDS);
        return $c;
    }

    private static function bluesky($title, $html, $lang, array $o) {
        $target = (string) ($o['_target'] ?? '');
        if ($target === '') return new WP_Error('bp_pub', 'Bluesky: آدرس هدف مشخص نیست.');
        // متن نمایشی کوتاه برای لینک؛ آدرس کامل در facet می‌رود
        $disp = preg_replace('~^https?://(www\.)?~i', '', $target);
        $disp = rtrim($disp, '/');
        if (mb_strlen($disp) > 28) $disp = mb_substr($disp, 0, 27) . '…';
        $text = self::micro_body($title, $html, 300 - mb_strlen($disp) - 2 - 12) . "\n\n" . $disp;
        $start = strlen($text) - strlen($disp);
        $record = [
            '$type' => 'app.bsky.feed.post', 'text' => $text, 'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z'),
            'langs' => [$lang === 'en' ? 'en' : 'fa'],
            'facets' => [['index' => ['byteStart' => $start, 'byteEnd' => strlen($text)],
                          'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $target]]]],
        ];
        for ($try = 0; $try < 2; $try++) {
            $ses = self::bsky_session($o, $try > 0);
            if (is_wp_error($ses)) return $ses;
            $r = BP_Http::post('https://bsky.social/xrpc/com.atproto.repo.createRecord', [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $ses['jwt']],
                'body' => wp_json_encode(['repo' => $ses['did'], 'collection' => 'app.bsky.feed.post', 'record' => $record], JSON_UNESCAPED_UNICODE),
                'timeout' => 30,
            ]);
            $j = self::json($r);
            if (is_wp_error($j)) {
                if ($try === 0 && stripos($j->get_error_message(), 'Expired') !== false) continue;
                return new WP_Error('bp_pub', 'Bluesky: ' . $j->get_error_message());
            }
            $uri = (string) ($j['uri'] ?? '');
            if (!preg_match('~/([A-Za-z0-9]+)$~', $uri, $m)) return new WP_Error('bp_pub', 'Bluesky: پاسخ نامعتبر');
            return ['url' => 'https://bsky.app/profile/' . $o['bluesky_handle'] . '/post/' . $m[1]];
        }
        return new WP_Error('bp_pub', 'Bluesky: نشست منقضی شد');
    }

    /* ---------- کمکی ---------- */

    private static function json($res) {
        if (is_wp_error($res)) return $res;
        $code = (int) wp_remote_retrieve_response_code($res);
        $j = json_decode((string) wp_remote_retrieve_body($res), true);
        if (!is_array($j)) return new WP_Error('bp_pub', 'پاسخ نامعتبر از سرویس (کد ' . $code . ')');
        if ($code >= 400 && empty($j['error']) && empty($j['errors']) && empty($j['error_msg']) && empty($j['message'])) {
            return new WP_Error('bp_pub', 'کد ' . $code);
        }
        if ($code >= 400) {
            $m = $j['error'] ?? ($j['error_msg'] ?? ($j['errors'][0]['message'] ?? ($j['message'] ?? 'خطا')));
            return new WP_Error('bp_pub', 'کد ' . $code . ': ' . mb_substr(is_scalar($m) ? (string) $m : wp_json_encode($m), 0, 200));
        }
        return $j;
    }

    private static function root($html) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="bp-root">' . (string) $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        $n = $x->query('//div[@id="bp-root"]')->item(0);
        return $n ?: null;
    }

    /** HTML ساده → آرایه‌ی Node تلگراف */
    public static function nodes($html) {
        $root = self::root($html);
        if (!$root) return [];
        $out = [];
        foreach ($root->childNodes as $c) {
            foreach (self::conv($c) as $n) $out[] = $n;
        }
        return $out;
    }

    private static function conv($n) {
        if ($n->nodeType === XML_TEXT_NODE) {
            $t = (string) $n->nodeValue;
            if (trim($t) === '' && strpos($t, "\n") !== false) return [];
            return $t === '' ? [] : [$t];
        }
        if ($n->nodeType !== XML_ELEMENT_NODE) return [];
        $tag = strtolower($n->nodeName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object'], true)) return [];
        $map = ['h1' => 'h3', 'h2' => 'h3', 'h3' => 'h4', 'h4' => 'h4', 'p' => 'p', 'ul' => 'ul', 'ol' => 'ol', 'li' => 'li',
                'strong' => 'strong', 'b' => 'strong', 'em' => 'em', 'i' => 'em', 'a' => 'a', 'br' => 'br'];
        $kids = [];
        foreach ($n->childNodes as $c) {
            foreach (self::conv($c) as $k) $kids[] = $k;
        }
        if (!isset($map[$tag])) return $kids;
        if ($tag === 'br') return [['tag' => 'br']];
        $node = ['tag' => $map[$tag]];
        if ($tag === 'a') {
            $href = trim((string) $n->getAttribute('href'));
            if (!preg_match('~^https?://~i', $href)) return $kids;
            $node['attrs'] = ['href' => $href];
        }
        if ($kids) $node['children'] = $kids;
        return [$node];
    }

    /** HTML ساده → Markdown */
    public static function md($html) {
        $root = self::root($html);
        if (!$root) return '';
        $s = '';
        foreach ($root->childNodes as $c) $s .= self::mdnode($c, 0);
        $s = preg_replace("~\n{3,}~", "\n\n", $s);
        return trim((string) $s) . "\n";
    }

    private static function mdnode($n, $depth) {
        if ($n->nodeType === XML_TEXT_NODE) {
            $raw = (string) $n->nodeValue;
            if (trim($raw) === '' && strpos($raw, "\n") !== false) return '';
            return preg_replace('~\s+~u', ' ', $raw);
        }
        if ($n->nodeType !== XML_ELEMENT_NODE) return '';
        $tag = strtolower($n->nodeName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object'], true)) return '';
        $inner = '';
        $i = 0;
        foreach ($n->childNodes as $c) {
            if ($tag === 'ol' && $c->nodeType === XML_ELEMENT_NODE && strtolower($c->nodeName) === 'li') {
                $i++;
                $li = '';
                foreach ($c->childNodes as $cc) $li .= self::mdnode($cc, $depth + 2);
                $inner .= $i . '. ' . trim($li) . "\n";
                continue;
            }
            $inner .= self::mdnode($c, $depth + 1);
        }
        switch ($tag) {
            case 'h1': case 'h2': return "\n\n## " . trim($inner) . "\n\n";
            case 'h3': case 'h4': return "\n\n### " . trim($inner) . "\n\n";
            case 'p':  return "\n\n" . trim($inner) . "\n\n";
            case 'ul': return "\n\n" . $inner . "\n";
            case 'ol': return "\n\n" . $inner . "\n";
            case 'li': return "- " . trim($inner) . "\n";
            case 'strong': case 'b': return '**' . trim($inner) . '**';
            case 'em': case 'i': return '*' . trim($inner) . '*';
            case 'br': return "  \n";
            case 'a':
                $href = trim((string) $n->getAttribute('href'));
                return preg_match('~^https?://~i', $href) ? '[' . trim($inner) . '](' . $href . ')' : $inner;
        }
        return $inner;
    }

    /** بعد از انتشار: صفحه را می‌خواند و می‌بیند لینک هدف هست و follow است یا nofollow */
    public static function verify($pub_url, $target_url) {
        $res = BP_Http::get($pub_url, ['timeout' => 20, 'limit_response_size' => 800000]);
        if (is_wp_error($res)) return 'unchecked';
        $vc = (int) wp_remote_retrieve_response_code($res);
        if ($vc === 404 || $vc === 410) return 'gone';
        if ($vc >= 400) return 'unchecked';
        $body = (string) wp_remote_retrieve_body($res);
        if (!preg_match_all('~<a\s[^>]*>~i', $body, $m)) return 'missing';
        foreach ($m[0] as $a) {
            if (!preg_match('~href\s*=\s*["\']([^"\']+)["\']~i', $a, $h)) continue;
            if (rtrim(html_entity_decode($h[1]), '/') !== rtrim($target_url, '/')) continue;
            return preg_match('~rel\s*=\s*["\'][^"\']*(nofollow|ugc|sponsored)~i', $a) ? 'nofollow' : 'follow';
        }
        return 'missing';
    }
}

/* ============================================================
 * v1.5 — BP_Auto: اتوپایلوت (یک دکمه → ساخت، انتشار و ثبت لینک‌ها)
 * ============================================================ */
class BP_Auto {
    const OPT = 'bp_auto';
    private static function secrets() { return BP_Publisher::secret_names(); }

    public static function init() {
        foreach (['auto_save', 'auto_run', 'auto_retry', 'auto_export', 'auto_approve', 'auto_reject', 'auto_recheck', 'auto_selftest'] as $a) {
            add_action('admin_post_bp_' . $a, [__CLASS__, 'h_' . $a]);
        }
        add_action('bp_admin_tab_autopilot', [__CLASS__, 'tab']);
        add_action('bp_admin_tab_report', [__CLASS__, 'report']);
    }

    /* ---------- تنظیمات ---------- */

    /** تعداد مقاله‌ی اولی که در حالت «۵ مقاله‌ی اول» برای تأیید نگه داشته می‌شود */
    const REVIEW_N = 5;

    /** پروفایل‌های آماده: یک انتخاب به‌جای ده‌ها عدد */
    public static function profiles() {
        return [
            'safe' => [
                'label' => 'محتاط',
                'desc'  => 'کم‌سرعت و طبیعی؛ برای سایت جدید، سایت مشتری یا حوزه‌ی حساس',
                'preset' => ['daily' => 2, 'ramp_days' => 21, 'hour_from' => 9, 'hour_to' => 22, 'words_min' => 800, 'words_max' => 1100,
                             'w_brand' => 45, 'w_naked' => 28, 'w_generic' => 20, 'w_partial' => 6, 'w_exact' => 1],
            ],
            'balanced' => [
                'label' => 'متعادل (پیشنهادی)',
                'desc'  => 'تعادل بین سرعت و ایمنی؛ مناسب بیشتر سایت‌ها',
                'preset' => ['daily' => 3, 'ramp_days' => 14, 'hour_from' => 8, 'hour_to' => 23, 'words_min' => 750, 'words_max' => 1050,
                             'w_brand' => 40, 'w_naked' => 25, 'w_generic' => 20, 'w_partial' => 12, 'w_exact' => 3],
            ],
            'fast' => [
                'label' => 'سریع',
                'desc'  => 'سرعت بیشتر؛ فقط وقتی رادار نشان می‌دهد لینک‌ها سالم و ایندکس می‌شوند',
                'preset' => ['daily' => 6, 'ramp_days' => 10, 'hour_from' => 8, 'hour_to' => 24, 'words_min' => 700, 'words_max' => 1000,
                             'w_brand' => 35, 'w_naked' => 22, 'w_generic' => 20, 'w_partial' => 16, 'w_exact' => 7],
            ],
            'custom' => [
                'label' => 'دستی (پیشرفته)',
                'desc'  => 'همه‌ی اعداد را خودم تنظیم می‌کنم',
                'preset' => [],
            ],
        ];
    }

    /** تعداد مقاله‌های منتشرشده (برای حالت «۵ مقاله‌ی اول») */
    private static function published_count() {
        static $n = null;
        if ($n === null) {
            global $wpdb;
            $n = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . BP_DB::t('plans') . " WHERE pub_status='published'");
        }
        return $n;
    }

    public static function opts() {
        $raw = get_option(self::OPT, []);
        $o = wp_parse_args($raw, [
            'enabled' => 0, 'daily' => 3, 'platforms' => ['telegraph', 'writeas'],
            'devto_key' => '', 'hashnode_token' => '', 'hashnode_pub' => '',
            // v1.6
            'hour_from' => 8, 'hour_to' => 23, 'ramp_days' => 14, 'review_first' => 0,
            'words_min' => 750, 'words_max' => 1050, 'dup_check' => 1, 'notify_fail' => 1, 'recheck' => 1,
            'w_brand' => 40, 'w_naked' => 25, 'w_generic' => 20, 'w_partial' => 12, 'w_exact' => 3,
            // v1.8
            'profile' => 'balanced', 'review_mode' => '',
        ]);
        foreach (array_keys(BP_Publisher::all_fields()) as $fk) if (!isset($o[$fk])) $o[$fk] = '';
        foreach (self::secrets() as $k) $o[$k] = BP_Settings::dec((string) $o[$k]);
        $o['platforms'] = array_values(array_intersect((array) $o['platforms'], array_keys(BP_Publisher::platforms())));

        // کاربران نسخه‌های قبل (تنظیمات ذخیره‌شده ولی بدون پروفایل): همان مقادیر قبلی حفظ می‌شود (پروفایل «دستی»)
        $pr = self::profiles();
        if (is_array($raw) && $raw && !array_key_exists('profile', $raw)) {
            $o['profile'] = 'custom';
            $o['_legacy'] = 1;
        }
        // پروفایل: مقادیر پیش‌تنظیم جایگزین مقادیر ذخیره‌شده می‌شود (به‌جز «دستی»)
        if (!isset($pr[$o['profile']])) $o['profile'] = 'balanced';
        if ($o['profile'] !== 'custom') {
            $o = array_merge($o, $pr[$o['profile']]['preset'], ['dup_check' => 1, 'recheck' => 1, 'notify_fail' => 1]);
        }

        // حالت بازبینی؛ کاربران نسخه‌های قبل: review_first قدیمی به حالت معادل تبدیل می‌شود
        $mode = (string) $o['review_mode'];
        if (!in_array($mode, ['first', 'always', 'off'], true)) {
            $mode = (is_array($raw) && array_key_exists('review_first', $raw)) ? (!empty($raw['review_first']) ? 'always' : 'off') : 'first';
        }
        $o['review_mode'] = $mode;
        if ($mode === 'always') $o['review_first'] = 1;
        elseif ($mode === 'first') $o['review_first'] = self::published_count() < self::REVIEW_N ? 1 : 0;
        else $o['review_first'] = 0;
        return $o;
    }

    private static function has_secret($k) {
        $o = get_option(self::OPT, []);
        return !empty($o[$k]);
    }

    public static function enabled() {
        $o = get_option(self::OPT, []);
        return !empty($o['enabled']);
    }

    private static function lock() {
        return BP_Lock::acquire('auto', 900);
    }

    /**
     * ضریب سلامت (۰٫۵ تا ۱): اگر انتشارها زیاد خطا می‌دهند یا لینک‌های منتشرشده حذف می‌شوند،
     * سرعت خودکار کم می‌شود. فقط در پروفایل‌های آماده اعمال می‌شود و هرگز سرعت را از سقف پروفایل بالاتر نمی‌برد.
     * @return array ['f'=>float,'why'=>string[]]
     */
    public static function health() {
        $c = get_transient('bp_auto_health');
        if (is_array($c) && isset($c['f'])) return $c;
        global $wpdb;
        $tp = BP_DB::t('plans');
        $f = 1.0;
        $why = [];
        $now = strtotime(current_time('mysql'));

        $r = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(pub_status='published') ok, SUM(pub_status='failed') bad FROM $tp WHERE pub_platform<>'' AND pub_status IN ('published','failed') AND updated_at >= %s",
            gmdate('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS)
        ));
        $ok = (int) ($r->ok ?? 0);
        $bad = (int) ($r->bad ?? 0);
        if ($ok + $bad >= 4 && $bad / max(1, $ok + $bad) > 0.4) {
            $f = min($f, 0.5);
            $why[] = 'نرخ بالای خطای انتشار';
        }

        $m = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) n, SUM(pub_check IN ('missing','gone')) lost FROM $tp WHERE pub_status='published' AND pub_check IN ('follow','nofollow','missing','gone') AND published_at >= %s",
            gmdate('Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS)
        ));
        $n = (int) ($m->n ?? 0);
        $lost = (int) ($m->lost ?? 0);
        if ($n >= 6 && $lost / max(1, $n) > 0.3) {
            $f = min($f, 0.7);
            $why[] = 'مفقودشدن بخشی از لینک‌های منتشرشده';
        }

        $res = ['f' => $f, 'why' => $why];
        set_transient('bp_auto_health', $res, HOUR_IN_SECONDS);
        return $res;
    }

    /** سقف روزانه‌ی مؤثر: گرم‌کردن تدریجی، و در پروفایل‌های آماده کاهش خودکار هنگام مشکل */
    public static function effective_daily(array $o) {
        $d = max(1, (int) $o['daily']);
        $ramp = (int) $o['ramp_days'];
        $st = (int) get_option('bp_auto_started', 0);
        if ($ramp > 0 && $st) {
            $days = (int) floor((time() - $st) / DAY_IN_SECONDS);
            if ($days < $ramp) $d = max(1, (int) ceil($d * ($days + 1) / $ramp));
        }
        if (($o['profile'] ?? 'custom') !== 'custom') {
            $h = self::health();
            if ($h['f'] < 1) $d = max(1, (int) floor($d * $h['f']));
        }
        return $d;
    }

    /* ---------- آزمایش اتصال هاست به سرویس‌ها ---------- */

    public static function selftest_get() {
        $t = get_transient('bp_selftest');
        return is_array($t) ? $t : [];
    }

    public static function selftest_url() {
        return self::nl('auto_selftest');
    }

    public static function h_auto_selftest() {
        self::guard();
        @set_time_limit(90);
        $targets = [
            'Telegra.ph' => 'https://telegra.ph/',
            'Write.as'   => 'https://write.as/',
            'Bluesky'    => 'https://bsky.social/',
            'Google OAuth' => 'https://oauth2.googleapis.com/',
            'Tumblr API' => 'https://api.tumblr.com/',
            'WordPress.com API' => 'https://public-api.wordpress.com/',
            'GitHub'     => 'https://raw.githubusercontent.com/',
            'Gemini'     => 'https://generativelanguage.googleapis.com/',
            'Groq'       => 'https://api.groq.com/',
            'Bing'       => 'https://www.bing.com/',
        ];
        $res = [];
        $okn = 0;
        foreach ($targets as $name => $url) {
            $t0 = microtime(true);
            $r = BP_Http::get($url, ['timeout' => 8, 'redirection' => 2, 'limit_response_size' => 20000]);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if (is_wp_error($r)) {
                $res[$name] = ['ok' => 0, 'ms' => $ms, 'err' => mb_substr($r->get_error_message(), 0, 120)];
            } else {
                $res[$name] = ['ok' => 1, 'ms' => $ms, 'err' => ''];
                $okn++;
            }
        }
        set_transient('bp_selftest', $res, 6 * HOUR_IN_SECONDS);
        self::back('آزمایش اتصال انجام شد: ' . $okn . ' از ' . count($targets) . ' سرویس در دسترس است.' . ($okn < count($targets) ? ' اگر سرویس‌های لازم بسته‌اند، در تب «تنظیمات» پروکسی وارد کنید.' : ''));
    }

    /** آیا الان داخل بازه‌ی ساعت فعالیت (به وقت سایت) هستیم؟ ساعت پایان انحصاری است */
    public static function in_hours(array $o) {
        $a = (int) $o['hour_from'];
        $b = (int) $o['hour_to'];
        if ($a === $b || ($a === 0 && $b >= 24)) return true;
        $h = (int) current_time('G');
        return $a < $b ? ($h >= $a && $h < $b) : ($h >= $a || $h < $b);
    }

    private static function span_hours(array $o) {
        $a = (int) $o['hour_from'];
        $b = (int) $o['hour_to'];
        if ($a === $b || ($a === 0 && $b >= 24)) return 24;
        return $a < $b ? $b - $a : 24 - $a + $b;
    }

    /** تگ‌های DEV.to: فقط حروف/عدد لاتین؛ از حوزه و کلمات کلیدیِ انگلیسی */
    private static function tags($c) {
        $src = [];
        if (!empty($c->niche)) $src[] = $c->niche;
        foreach (BP_Agent::keywords($c, 8) as $k) {
            foreach (preg_split('~\s+~u', (string) $k) as $w) $src[] = $w;
        }
        $out = [];
        foreach ($src as $t) {
            $t = strtolower(preg_replace('~[^A-Za-z0-9]~', '', (string) $t));
            if (strlen($t) >= 3 && strlen($t) <= 20 && !in_array($t, $out, true)) $out[] = $t;
            if (count($out) >= 4) break;
        }
        return $out;
    }

    private static function mail_fail($msg) {
        $o = self::opts();
        if (empty($o['notify_fail']) || get_transient('bp_fail_mail')) return;
        set_transient('bp_fail_mail', 1, 6 * HOUR_IN_SECONDS);
        wp_mail(get_option('admin_email'), 'Backlink Pilot: خطا در انتشار', $msg . "\n\n" . add_query_arg(['page' => BP_Admin::SLUG, 'tab' => 'autopilot'], admin_url('admin.php')));
    }

    /** بررسی دوره‌ایِ لینک‌های منتشرشده: هر لینک حداکثر هفته‌ای یک بار (موارد مشکل‌دار روزی یک بار) */
    public static function recheck($limit = 4, $force = false) {
        global $wpdb;
        $o = self::opts();
        if (!$force && empty($o['recheck'])) return 0;
        $tp = BP_DB::t('plans');
        $now = strtotime(current_time('mysql'));
        $wk = gmdate('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS);
        $dy = gmdate('Y-m-d H:i:s', $now - DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tp WHERE pub_status='published' AND pub_url<>'' AND (pub_checked_at IS NULL OR pub_checked_at < %s OR (pub_check IN ('missing','unchecked','gone') AND pub_checked_at < %s)) ORDER BY pub_checked_at IS NULL DESC, pub_checked_at ASC LIMIT %d",
            $wk, $dy, max(1, (int) $limit)
        ));
        $n = 0;
        foreach ((array) $rows as $r) {
            $new = BP_Publisher::verify($r->pub_url, $r->target_url);
            // ناپایداری موقت شبکه نباید نتیجه‌ی قبلیِ معتبر را خراب کند
            if ($new === 'unchecked' && in_array($r->pub_check, ['follow', 'nofollow'], true)) $new = $r->pub_check;
            $wpdb->update($tp, ['pub_check' => $new, 'pub_checked_at' => current_time('mysql')], ['id' => $r->id]);
            if ($new !== $r->pub_check && in_array($new, ['missing', 'gone', 'nofollow'], true)) {
                BP_Log::add('auto', 'تغییر وضعیت لینک: ' . $r->pub_url . ' → ' . $new . ' (قبلاً: ' . ($r->pub_check ?: '—') . ')', 'warn', (int) $r->campaign_id);
            }
            $n++;
        }
        return $n;
    }

    /* ---------- هسته: هر بار یک بکلینک ---------- */

    private static function today_start() {
        return current_time('Y-m-d') . ' 00:00:00';
    }

    public static function count_today() {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . BP_DB::t('plans') . " WHERE pub_status='published' AND published_at>=%s", self::today_start()));
    }

    private static function off($p) {
        return (bool) get_transient('bp_plat_off_' . $p);
    }

    /**
     * یک قدم اتوپایلوت: برنامه‌ی نیمه‌کاره را ادامه بده یا یکی بساز، متن بنویس، منتشر کن، لینک را بررسی کن.
     * @return array ['ok'=>bool,'msg'=>string]
     */
    public static function tick($force = false) {
        global $wpdb;
        $o = self::opts();
        if (!$o['enabled']) return ['ok' => false, 'msg' => 'اتوپایلوت غیرفعال است.'];
        $cap = self::effective_daily($o);
        if (self::count_today() >= $cap) return ['ok' => false, 'msg' => 'سهمیه‌ی امروز تکمیل است.'];
        if (!$force && !self::in_hours($o)) return ['ok' => false, 'msg' => 'خارج از ساعت فعالیت تنظیم‌شده.'];
        if (!$force && time() < (int) get_option('bp_auto_next', 0)) return ['ok' => false, 'msg' => 'هنوز نوبت بعدی نرسیده.'];
        if (!self::lock()) return ['ok' => false, 'msg' => 'اجرای قبلی هنوز در حال کار است.'];
        @set_time_limit(150);
        try {
            // بعد از گرفتن قفل دوباره بررسی کن (اجرای هم‌زمان دیگری ممکن است همین حالا انتشار داده باشد)
            if (self::count_today() >= $cap) return ['ok' => false, 'msg' => 'سهمیه‌ی امروز تکمیل است.'];
            if (!$force && time() < (int) get_option('bp_auto_next', 0)) return ['ok' => false, 'msg' => 'هنوز نوبت بعدی نرسیده.'];
            return self::step($o);
        } catch (Throwable $e) {
            BP_Log::add('auto', 'خطای غیرمنتظره: ' . $e->getMessage(), 'error');
            return ['ok' => false, 'msg' => 'خطا: ' . $e->getMessage()];
        } finally {
            BP_Lock::release('auto');
        }
    }

    private static function step(array $o) {
        global $wpdb;
        $tp = BP_DB::t('plans');

        $plan = self::next_plan($o);
        if (!$plan) {
            if (!empty($o['review_first']) && (int) $wpdb->get_var("SELECT COUNT(*) FROM $tp WHERE pub_status='held' AND status<>'rejected'") >= 3) {
                return ['ok' => false, 'msg' => 'سه مقاله منتظر بازبینی شماست؛ تا تأیید یا حذف آن‌ها مقاله‌ی جدید ساخته نمی‌شود.'];
            }
            $plan = self::create_plan($o);
            if (is_wp_error($plan)) {
                BP_Log::add('auto', $plan->get_error_message(), 'warn');
                return ['ok' => false, 'msg' => $plan->get_error_message()];
            }
        }
        $c = BP_Agent::campaign($plan->campaign_id);
        if (!$c) return ['ok' => false, 'msg' => 'کمپین پیدا نشد.'];

        if ((string) $plan->article_body === '') {
            $g = BP_Agent::generate((int) $plan->id);
            if (is_wp_error($g)) return ['ok' => false, 'msg' => 'تولید متن: ' . $g->get_error_message()];
            $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d", $plan->id));
        }

        // حالت بازبینی: مقاله ساخته می‌شود ولی تا تأیید شما منتشر نمی‌شود
        if (!empty($o['review_first']) && $plan->pub_status !== 'review_ok') {
            $wpdb->update($tp, ['pub_status' => 'held', 'updated_at' => current_time('mysql')], ['id' => $plan->id]);
            BP_Log::add('auto', 'مقاله برای بازبینی نگه داشته شد: ' . $plan->article_title, 'info', $c->id);
            return ['ok' => true, 'msg' => 'مقاله ساخته شد و منتظر تأیید شماست (تب اتوپایلوت).'];
        }
        $o['_tags'] = self::tags($c);
        $o['_target'] = (string) $plan->target_url;

        // ادعای اتمیک: فقط یک اجرا می‌تواند این برنامه را «در حال انتشار» کند
        $claim = $wpdb->query($wpdb->prepare(
            "UPDATE $tp SET pub_status='publishing', updated_at=%s WHERE id=%d AND pub_status NOT IN ('published','publishing')",
            current_time('mysql'), $plan->id
        ));
        if ($claim !== 1) return ['ok' => false, 'msg' => 'این برنامه هم‌زمان توسط اجرای دیگری در حال انتشار است.'];

        $lang = $c->lang === 'en' ? 'en' : 'fa';
        $pub = BP_Publisher::publish($plan->pub_platform, $plan->article_title, $plan->article_body, $lang, $o);
        $now = current_time('mysql');
        if (is_wp_error($pub)) {
            $wpdb->query($wpdb->prepare("UPDATE $tp SET fails=fails+1, pub_status='failed', pub_error=%s, updated_at=%s WHERE id=%d", mb_substr($pub->get_error_message(), 0, 290), $now, $plan->id));
            set_transient('bp_plat_off_' . $plan->pub_platform, 1, HOUR_IN_SECONDS);
            BP_Log::add('auto', 'انتشار روی ' . $plan->pub_platform . ' ناموفق: ' . $pub->get_error_message() . ' (یک ساعت کنار گذاشته شد)', 'error', $c->id);
            self::mail_fail('انتشار روی ' . $plan->pub_platform . ' ناموفق بود: ' . $pub->get_error_message());
            return ['ok' => false, 'msg' => 'انتشار ناموفق: ' . $pub->get_error_message()];
        }

        // اول نتیجه را ثبت کن (قبل از بررسی لینک که چند ثانیه طول می‌کشد) تا هیچ‌وقت انتشار بدون ردپا نماند
        BP_Log::add('auto', 'انتشار انجام شد: ' . $pub['url'], 'info', $c->id);
        $saved = $wpdb->update($tp, [
            'pub_url' => mb_substr($pub['url'], 0, 500), 'pub_status' => 'published', 'pub_error' => '',
            'published_at' => $now, 'updated_at' => $now, 'status' => 'done',
        ], ['id' => $plan->id]);
        if ($saved === false) {
            BP_Log::add('auto', 'هشدار: ثبت انتشار در دیتابیس ناموفق بود (plan #' . (int) $plan->id . ') — ' . $pub['url'] . ' — ' . $wpdb->last_error, 'error', $c->id);
        }
        // شبکه‌های اجتماعی و Tumblr لینک را با ریدایرکت/JS نشان می‌دهند و همیشه nofollow‌اند؛ بررسی صفحه معنی ندارد
        $chk = in_array($plan->pub_platform, ['mastodon', 'bluesky', 'tumblr'], true) ? 'nofollow' : BP_Publisher::verify($pub['url'], $plan->target_url);
        $wpdb->update($tp, ['pub_check' => $chk, 'pub_checked_at' => current_time('mysql')], ['id' => $plan->id]);

        $gap = (int) max(600, (self::span_hours($o) * HOUR_IN_SECONDS) / max(1, self::effective_daily($o)));
        update_option('bp_auto_next', time() + (int) ($gap * mt_rand(80, 120) / 100), false);
        BP_Log::add('auto', 'منتشر شد: ' . $pub['url'] . ' (لینک: ' . $chk . ')', 'info', $c->id);
        return ['ok' => true, 'msg' => 'منتشر شد: ' . $pub['url']];
    }

    /** برنامه‌ی تأییدشده‌ای که هنوز منتشر نشده (تلاش مجدد) */
    private static function next_plan(array $o) {
        global $wpdb;
        $tp = BP_DB::t('plans');
        // «در حال انتشار»ِ بیش از ۱۵ دقیقه یعنی اجرا وسط کار مرده؛ نتیجه نامعلوم است، پس تلاش مجدد خودکار نمی‌کنیم (احتمال انتشار تکراری)
        $stale = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 15 * MINUTE_IN_SECONDS);
        $n = (int) $wpdb->query($wpdb->prepare(
            "UPDATE $tp SET pub_status='failed', fails=3, pub_error=%s WHERE pub_status='publishing' AND updated_at < %s",
            'نتیجه‌ی انتشار نامعلوم (اجرا وسط کار قطع شد). قبل از «تلاش مجدد» لیست پلتفرم را دستی بررسی کنید.', $stale
        ));
        if ($n > 0) BP_Log::add('auto', $n . ' برنامه‌ی گیرکرده در «در حال انتشار» به «ناموفق (نامعلوم)» تبدیل شد؛ تلاش مجدد خودکار نمی‌شود.', 'warn');
        $rows = $wpdb->get_results("SELECT * FROM $tp WHERE method='api' AND pub_platform<>'' AND pub_status NOT IN ('published','publishing','held') AND status='approved' AND fails<3 ORDER BY id ASC LIMIT 10");
        foreach ((array) $rows as $r) {
            if (in_array($r->pub_platform, $o['platforms'], true) && BP_Publisher::ready($r->pub_platform, $o) && !self::off($r->pub_platform)) return $r;
        }
        return null;
    }

    private static function create_plan(array $o) {
        global $wpdb;
        $tp = BP_DB::t('plans');
        $recent = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 7 * DAY_IN_SECONDS);

        // کمپین: عادلانه (کمترین بکلینک در هفته‌ی اخیر)
        $cid = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT c.id FROM ' . BP_DB::t('campaigns') . " c LEFT JOIN $tp p ON p.campaign_id=c.id AND p.status<>'rejected' AND p.created_at>=%s WHERE c.active=1 GROUP BY c.id ORDER BY COUNT(p.id) ASC, c.id ASC LIMIT 1", $recent
        ));
        if (!$cid) return new WP_Error('bp_auto', 'هنوز کمپینی ساخته نشده؛ اسکن سایت در اجرای بعدی انجام می‌شود.');
        $c = BP_Agent::campaign($cid);
        $clang = $c->lang === 'en' ? 'en' : ($c->lang === 'any' ? 'any' : 'fa');

        // پلتفرم: آماده، فعال، سازگار با زبان، کم‌استفاده‌ترین
        $used = [];
        foreach ($wpdb->get_results("SELECT pub_platform, COUNT(*) c FROM $tp WHERE pub_status='published' GROUP BY pub_platform") as $r) $used[$r->pub_platform] = (int) $r->c;
        $all = BP_Publisher::platforms();
        $cands = [];
        foreach ($o['platforms'] as $p) {
            if (!BP_Publisher::ready($p, $o) || self::off($p)) continue;
            if (!in_array($clang, $all[$p]['langs'], true)) continue;
            $cands[$p] = (($used[$p] ?? 0) + mt_rand(0, 100) / 100) * BP_Radar::factor($p);
        }
        if (!$cands) return new WP_Error('bp_auto', 'هیچ پلتفرم آماده‌ای برای این کمپین نیست (تنظیمات پلتفرم‌ها را ببینید، یا پلتفرم موقتاً به‌خاطر خطا کنار گذاشته شده).');
        asort($cands);
        $plat = (string) key($cands);

        // هدف لینک و لنگر
        $pg = BP_Pillars::pick_target($c);
        $cp = clone $c;
        if ($pg) {
            $cp->url = $pg->url;
            if (trim((string) $pg->keywords) !== '') $cp->keywords = $pg->keywords;
        }
        $counts = ['brand' => 0, 'naked' => 0, 'generic' => 0, 'partial' => 0, 'exact' => 0];
        $total = 0;
        foreach ($wpdb->get_results($wpdb->prepare("SELECT anchor_type, COUNT(*) c FROM $tp WHERE campaign_id=%d AND status<>'rejected' GROUP BY anchor_type", $cid)) as $row) {
            if (isset($counts[$row->anchor_type])) $counts[$row->anchor_type] = (int) $row->c;
            $total += (int) $row->c;
        }
        $w = ['brand' => max(0, (int) $o['w_brand']), 'naked' => max(0, (int) $o['w_naked']), 'generic' => max(0, (int) $o['w_generic']),
              'partial' => max(0, (int) $o['w_partial']), 'exact' => max(0, (int) $o['w_exact'])];
        if (array_sum($w) <= 0) $w = ['brand' => 40, 'naked' => 25, 'generic' => 20, 'partial' => 12, 'exact' => 3];
        $w = BP_Radar::tune_weights($w);
        $roll = mt_rand(1, (int) array_sum($w));
        $want = 'brand';
        foreach ($w as $k => $v) {
            if ($roll <= $v) { $want = $k; break; }
            $roll -= $v;
        }
        list($type, $text) = BP_Agent::anchor($cp, $want, '', $counts, $total);

        $ok = $wpdb->insert($tp, [
            'campaign_id' => $cid, 'domain' => $all[$plat]['domain'], 'method' => 'api', 'anchor_type' => $type, 'anchor_text' => $text,
            'target_url' => mb_substr($cp->url, 0, 500), 'reason' => 'اتوپایلوت', 'status' => 'approved', 'pub_platform' => $plat,
            'created_at' => current_time('mysql'),
        ]);
        if (!$ok) return new WP_Error('bp_auto', 'ثبت برنامه ناموفق: ' . $wpdb->last_error);
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d", (int) $wpdb->insert_id));
    }

    /* ---------- هندلرها ---------- */

    private static function guard() {
        if (!current_user_can('manage_options')) wp_die('دسترسی ندارید.');
        check_admin_referer('bp_action');
    }

    private static function back($msg) {
        wp_safe_redirect(add_query_arg(['page' => BP_Admin::SLUG, 'tab' => 'autopilot', 'bp_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public static function h_auto_save() {
        self::guard();
        global $wpdb;
        $do = sanitize_key($_POST['do'] ?? 'save');
        $old = wp_parse_args(get_option(self::OPT, []), ['enabled' => 0]);
        // وردپرس‌های شخصی: اول ذخیره می‌شوند تا لیست پلتفرم‌ها (که به آن‌ها وابسته است) به‌روز باشد
        $wps = isset($_POST['wp_sites']) ? trim((string) sanitize_textarea_field(wp_unslash($_POST['wp_sites']))) : '';
        if ($wps !== '') {
            if (!empty($_POST['wp_sites_append']) && !empty($old['wp_sites'])) {
                $prev = trim((string) BP_Settings::dec((string) $old['wp_sites']));
                if ($prev !== '') $wps = $prev . "\n" . $wps;
            }
            $old['wp_sites'] = BP_Settings::enc($wps);
        }
        if (!empty($_POST['clear_wp_sites'])) $old['wp_sites'] = '';
        update_option(self::OPT, $old, false);
        $new = $old;
        $valid = array_keys(BP_Publisher::platforms());
        $pl = isset($_POST['platforms']) && is_array($_POST['platforms']) ? array_map('sanitize_key', wp_unslash($_POST['platforms'])) : [];
        $new['platforms'] = array_values(array_intersect($pl, $valid));
        // v1.8: پروفایل و حالت بازبینی
        $pf = sanitize_key($_POST['profile'] ?? 'balanced');
        $new['profile'] = isset(self::profiles()[$pf]) ? $pf : 'balanced';
        $rm = sanitize_key($_POST['review_mode'] ?? 'first');
        $new['review_mode'] = in_array($rm, ['first', 'always', 'off'], true) ? $rm : 'first';
        $new['review_first'] = $new['review_mode'] === 'always' ? 1 : 0; // سازگاری با نسخه‌های قبل
        $is_custom = $new['profile'] === 'custom';
        delete_transient('bp_auto_health');
        if ($is_custom) $new['daily'] = max(1, min(30, (int) ($_POST['daily'] ?? 3)));
        foreach (self::secrets() as $k) {
            $v = isset($_POST[$k]) ? trim(sanitize_text_field(wp_unslash($_POST[$k]))) : '';
            if ($v !== '') $new[$k] = BP_Settings::enc($v);
            if (!empty($_POST['clear_' . $k])) $new[$k] = '';
        }
        if ($is_custom) { // فقط در پروفایل «دستی»؛ در پروفایل‌های آماده مقدارها از پیش‌تنظیم می‌آید
            $new['hour_from'] = max(0, min(23, (int) ($_POST['hour_from'] ?? 8)));
            $new['hour_to']   = max(1, min(24, (int) ($_POST['hour_to'] ?? 23)));
            $new['ramp_days'] = max(0, min(60, (int) ($_POST['ramp_days'] ?? 14)));
            $new['words_min'] = max(400, min(2000, (int) ($_POST['words_min'] ?? 750)));
            $new['words_max'] = max($new['words_min'], min(2500, (int) ($_POST['words_max'] ?? 1050)));
            foreach (['dup_check', 'notify_fail', 'recheck'] as $k) $new[$k] = empty($_POST[$k]) ? 0 : 1;
            foreach (['w_brand' => 40, 'w_naked' => 25, 'w_generic' => 20, 'w_partial' => 12, 'w_exact' => 3] as $k => $dv) {
                $new[$k] = isset($_POST[$k]) ? max(0, min(100, (int) $_POST[$k])) : $dv;
            }
        }
        if (!empty($_POST['reset_ramp'])) update_option('bp_auto_started', time(), false);
        foreach (BP_Publisher::all_fields() as $fk => $fd) {
            if ($fd['secret'] || !isset($_POST[$fk])) continue;
            $new[$fk] = BP_Publisher::clean_field(wp_unslash($_POST[$fk]), $fd['san']);
        }

        $msg = 'تنظیمات ذخیره شد.';
        if ($do === 'enable') {
            $tmp = $new;
            foreach (self::secrets() as $k) $tmp[$k] = BP_Settings::dec((string) ($new[$k] ?? ''));
            $tmp['platforms'] = $new['platforms'];
            $ready = array_filter($new['platforms'], function ($p) use ($tmp) { return BP_Publisher::ready($p, $tmp); });
            if (!BP_AI::chain('write')) {
                $new['enabled'] = 0;
                $msg = 'برای فعال‌سازی باید حداقل یک کلید AI در تب «AI» تنظیم شده باشد (برای نوشتن مقاله‌ها).';
            } elseif (!$ready) {
                $new['enabled'] = 0;
                $msg = 'حداقل یک پلتفرم آماده انتخاب کنید.';
            } else {
                $new['enabled'] = 1;
                // اجرای خودکار بقیه‌ی چرخه
                $st = wp_parse_args(get_option(BP_Settings::OPT, []), BP_Settings::defaults());
                $st['auto_campaigns'] = 1;
                $st['auto_analyze'] = 1;
                update_option(BP_Settings::OPT, $st, false);
                if (!(int) $wpdb->get_var('SELECT COUNT(*) FROM ' . BP_DB::t('campaigns') . ' WHERE active=1')) {
                    update_option('bp_site_scan_next', 0, false); // اسکن سایت در اولین اجرای کران
                }
                update_option('bp_auto_next', 0, false);
                if (!(int) get_option('bp_auto_started', 0)) update_option('bp_auto_started', time(), false);
                BP_Cron::activate();
                $eff_daily = $is_custom ? (int) ($new['daily'] ?? 3) : (int) self::profiles()[$new['profile']]['preset']['daily'];
                BP_Log::add('auto', 'اتوپایلوت فعال شد (پروفایل «' . self::profiles()[$new['profile']]['label'] . '»): سقف ' . $eff_daily . ' بکلینک در روز.', 'info');
                $msg = 'اتوپایلوت فعال شد. اولین انتشار ظرف چند دقیقه‌ی آینده انجام می‌شود (یا «اجرای همین الان» را بزنید).';
                if (function_exists('spawn_cron')) spawn_cron();
            }
        } elseif ($do === 'disable') {
            $new['enabled'] = 0;
            BP_Log::add('auto', 'اتوپایلوت متوقف شد.', 'info');
            $msg = 'اتوپایلوت متوقف شد.';
        }
        update_option(self::OPT, $new, false);
        self::back($msg);
    }

    public static function h_auto_run() {
        self::guard();
        $r = self::tick(true);
        self::back($r['msg']);
    }

    public static function h_auto_retry() {
        self::guard();
        global $wpdb;
        $wpdb->update(BP_DB::t('plans'), ['fails' => 0, 'pub_status' => 'review_ok', 'pub_error' => ''], ['id' => (int) ($_REQUEST['id'] ?? 0)]);
        foreach (array_keys(BP_Publisher::platforms()) as $p) delete_transient('bp_plat_off_' . $p);
        self::back('برای تلاش مجدد علامت‌گذاری شد؛ در اجرای بعدی منتشر می‌شود.');
    }

    public static function h_auto_approve() {
        self::guard();
        global $wpdb;
        $wpdb->update(BP_DB::t('plans'), ['pub_status' => 'review_ok'], ['id' => (int) ($_REQUEST['id'] ?? 0), 'pub_status' => 'held']);
        update_option('bp_auto_next', 0, false);
        self::back('مقاله تأیید شد؛ در اجرای بعدی منتشر می‌شود (یا «اجرای همین الان» را بزنید).');
    }

    public static function h_auto_reject() {
        self::guard();
        global $wpdb;
        $wpdb->update(BP_DB::t('plans'), ['status' => 'rejected', 'pub_status' => ''], ['id' => (int) ($_REQUEST['id'] ?? 0)]);
        self::back('مقاله حذف شد.');
    }

    public static function h_auto_recheck() {
        self::guard();
        @set_time_limit(150);
        $n = self::recheck(6, true);
        self::back($n . ' لینک دوباره بررسی شد.');
    }

    public static function h_auto_export() {
        self::guard();
        global $wpdb;
        $rows = $wpdb->get_results('SELECT p.*, c.name cname FROM ' . BP_DB::t('plans') . ' p LEFT JOIN ' . BP_DB::t('campaigns') . " c ON c.id=p.campaign_id WHERE p.pub_status='published' ORDER BY p.published_at DESC");
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="backlinks-' . gmdate('Ymd-His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $f = fopen('php://output', 'w');
        fputcsv($f, ['published_at', 'platform', 'campaign', 'title', 'anchor', 'target_url', 'published_url', 'link_check', 'checked_at']);
        foreach ($rows as $r) fputcsv($f, [$r->published_at, $r->pub_platform, ($r->cname ?: ($r->campaign_name ?: '(کمپین حذف‌شده)')), $r->article_title, $r->anchor_text, $r->target_url, $r->pub_url, $r->pub_check, $r->pub_checked_at]);
        fclose($f);
        exit;
    }

    /* ---------- گزارش ساده: هر صفحه‌ی سایت و لینک‌هایی که برایش ساخته شده ---------- */

    public static function report() {
        global $wpdb;
        $tp = BP_DB::t('plans');
        $rows = $wpdb->get_results("SELECT target_url, pub_url, pub_platform, pub_check, published_at FROM $tp WHERE pub_status='published' AND pub_url<>'' ORDER BY published_at DESC");
        $titles = [];
        foreach ((array) $wpdb->get_results('SELECT url, title FROM ' . BP_DB::t('pillars')) as $x) $titles[$x->url] = $x->title;
        $plats = BP_Publisher::platforms();
        $by = [];
        foreach ((array) $rows as $r) $by[$r->target_url][] = $r;
        uasort($by, function ($a, $b) { return count($b) <=> count($a); });
        $badge = ['follow' => ['follow', '#1a7f37'], 'nofollow' => ['nofollow', '#b26200'], 'missing' => ['لینک نیست', '#b32d2e'], 'gone' => ['حذف شده', '#b32d2e']];
        $plain = '';
        ?>
        <div class="bp-card">
            <h3>گزارش لینک‌ها <small style="color:#666">— <?php echo (int) count($rows); ?> لینک برای <?php echo (int) count($by); ?> صفحه</small></h3>
            <?php if (!$by) echo '<p>هنوز لینکی منتشر نشده است.</p>'; ?>
            <?php foreach ($by as $url => $list) :
                $name = $titles[$url] ?? (wp_parse_url($url, PHP_URL_PATH) ?: $url);
                $plain .= $name . "\n" . $url . "\n";
                ?>
                <div style="border-top:1px solid #eee;padding:10px 0">
                    <div style="font-size:15px"><b><?php echo esc_html($name); ?></b>
                        <span style="background:#2271b1;color:#fff;border-radius:10px;padding:1px 9px;font-size:12px;margin-right:6px"><?php echo (int) count($list); ?> لینک</span></div>
                    <div style="direction:ltr;text-align:left"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($url); ?></a></div>
                    <ul style="margin:6px 18px 0 0;direction:ltr;text-align:left">
                    <?php foreach ($list as $r) :
                        $b = $badge[$r->pub_check] ?? null;
                        $plain .= '  ' . $r->pub_url . "\n"; ?>
                        <li><a href="<?php echo esc_url($r->pub_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($r->pub_url); ?></a>
                            <small style="color:#666">— <?php echo esc_html($plats[$r->pub_platform]['label'] ?? $r->pub_platform); ?></small>
                            <?php if ($b) echo '<small style="color:' . esc_attr($b[1]) . '"> ' . esc_html($b[0]) . '</small>'; ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php $plain .= "\n"; ?>
            <?php endforeach; ?>
            <?php if ($by) : ?>
                <p style="margin-top:12px"><textarea id="bp-rep" readonly rows="1" style="position:absolute;left:-9999px"><?php echo esc_textarea($plain); ?></textarea>
                    <button type="button" class="button" onclick="var t=document.getElementById('bp-rep');t.select();document.execCommand('copy');this.textContent='کپی شد ✓'">کپی کل گزارش</button>
                    <a class="button" href="<?php echo esc_url(self::nl('auto_export')); ?>">خروجی CSV</a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ---------- تب ---------- */

    private static function nl($action, $args = []) {
        return wp_nonce_url(add_query_arg(array_merge(['action' => 'bp_' . $action], $args), admin_url('admin-post.php')), 'bp_action');
    }

    public static function tab() {
        global $wpdb;
        $o = self::opts();
        $tp = BP_DB::t('plans');
        $plats = BP_Publisher::platforms();
        $today = self::count_today();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tp WHERE pub_status='published'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tp WHERE pub_status='failed'");
        $camps = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . BP_DB::t('campaigns') . ' WHERE active=1');
        $next = (int) get_option('bp_auto_next', 0);
        $chk = ['follow' => ['follow ✓', '#1a7f37'], 'nofollow' => ['nofollow', '#b26200'], 'missing' => ['لینک در صفحه دیده نشد', '#b32d2e'], 'gone' => ['صفحه حذف شده', '#b32d2e'], 'unchecked' => ['بررسی نشد', '#666'], '' => ['—', '#666']];
        $cap = self::effective_daily($o);
        $held = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tp WHERE pub_status='held' AND status<>'rejected'");
        $st = [];
        foreach ($wpdb->get_results("SELECT pub_check, COUNT(*) c FROM $tp WHERE pub_status='published' GROUP BY pub_check") as $x) $st[(string) $x->pub_check] = (int) $x->c;
        $wp_n = count(BP_Publisher::wp_sites());
        ?>
        <div class="bp-card" style="border-right:4px solid <?php echo $o['enabled'] ? '#1a7f37' : '#8c8f94'; ?>">
            <h3>اتوپایلوت: <?php echo $o['enabled'] ? '<span style="color:#1a7f37">فعال</span>' : '<span style="color:#666">غیرفعال</span>'; ?></h3>
            <?php BP_Guide::panel($o); ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bp_action'); ?>
                <input type="hidden" name="action" value="bp_auto_save">
                <?php
                $prof = self::profiles();
                $live_p = 'امروز سقف مؤثر: ' . (int) $cap . ' بکلینک';
                if ($cap < (int) $o['daily']) {
                    $hh = self::health();
                    $live_p .= ' (سقف پروفایل ' . (int) $o['daily'] . ' — ' . ($hh['f'] < 1 && $o['profile'] !== 'custom' ? 'کاهش خودکار: ' . implode('، ', $hh['why']) : 'در دوره‌ی گرم‌کردن') . ')';
                }
                ?>
                <?php if (!empty($o['_legacy'])) : ?>
                    <p class="bp-sugg" style="margin:6px 0">تنظیمات قبلی شما حفظ شده (پروفایل «دستی»). برای حالت خودکار، یکی از پروفایل‌های زیر را انتخاب و ذخیره کنید؛ اعداد دستی‌تان پاک نمی‌شود.</p>
                <?php endif; ?>
                <p style="margin:6px 0"><b>۱) سرعت و سبک — فقط یک انتخاب</b></p>
                <div class="bp-prof">
                <?php foreach ($prof as $k => $pf) : ?>
                    <label class="bp-opt<?php echo $o['profile'] === $k ? ' on' : ''; ?>">
                        <input type="radio" name="profile" value="<?php echo esc_attr($k); ?>" <?php checked($o['profile'], $k); ?>>
                        <b><?php echo esc_html($pf['label']); ?></b><br>
                        <small><?php echo esc_html($pf['desc']); ?></small>
                    </label>
                <?php endforeach; ?>
                </div>
                <p class="description" style="margin:6px 0">اعمال‌شده‌ی فعلی: سقف روزانه <b><?php echo (int) $o['daily']; ?></b>
                    · ساعت فعالیت <b><?php echo (int) $o['hour_from']; ?>–<?php echo (int) $o['hour_to']; ?></b>
                    · گرم‌کردن <b><?php echo (int) $o['ramp_days']; ?></b> روز
                    · طول مقاله <b><?php echo (int) $o['words_min']; ?>–<?php echo (int) $o['words_max']; ?></b> کلمه
                    · بقیه‌ی چیزها (انکر، محافظ‌ها، یادگیری، نمایش لینک‌ها) خودکار است.</p>
                <?php BP_Guide::box('profile', $live_p); ?>

                <?php
                $n_sel = 0; $n_ready = 0;
                foreach ($plats as $k0 => $pl0) {
                    if (in_array($k0, $o['platforms'], true)) { $n_sel++; if (BP_Publisher::ready($k0, $o)) $n_ready++; }
                }
                ?>
                <div class="bp-pf-head">
                    <p style="margin:0"><b>۲) پلتفرم‌های انتشار</b></p>
                    <span class="bp-pf-sum"><b><?php echo (int) $n_ready; ?></b> پلتفرم آماده‌ی انتشار از <b><?php echo (int) $n_sel; ?></b> انتخاب‌شده</span>
                </div>
                <p class="description" style="margin:2px 0 10px">برای شروع، دو گزینه‌ی بدون کلید کافی است. هر پلتفرمی که انتخاب کنید به چرخه‌ی انتشار اضافه می‌شود.</p>
                <?php
                $render_card = function ($k, $pl) use ($o) {
                    $mt = BP_Publisher::meta($k);
                    $sel = in_array($k, $o['platforms'], true);
                    $ready = BP_Publisher::ready($k, $o);
                    $benched = $ready && self::off($k);
                    if (!$ready) { $chip = ['نیاز به تنظیم', 'warn']; }
                    elseif ($benched) { $chip = ['موقتاً کنار گذاشته شده', 'err']; }
                    elseif ($sel) { $chip = ['فعال', 'ok']; }
                    else { $chip = ['آماده', '']; }
                    $desc = strpos($k, 'wp') === 0 && preg_match('~^wp\d+$~', $k) ? $pl['note'] : $mt['desc'];
                    $fields = BP_Publisher::fields($k);
                    ?>
                    <div class="bp-pf<?php echo $sel ? ' on' : ''; ?>">
                        <label class="bp-pf-top">
                            <input type="checkbox" name="platforms[]" value="<?php echo esc_attr($k); ?>" <?php checked($sel); ?>>
                            <span class="bp-pf-name"><?php echo esc_html($pl['label']); ?></span>
                            <?php if (!empty($mt['rec'])) echo '<span class="bp-pf-rec">پیشنهادی</span>'; ?>
                            <span class="bp-chip bp-chip-<?php echo esc_attr($chip[1]); ?>"><?php echo esc_html($chip[0]); ?></span>
                        </label>
                        <p class="bp-pf-desc"><?php echo esc_html($desc); ?></p>
                        <div class="bp-pf-badges">
                            <?php foreach ($mt['badges'] as $b) echo '<span class="bp-chip bp-chip-' . esc_attr($b[1]) . '">' . esc_html($b[0]) . '</span>'; ?>
                        </div>
                        <?php if ($fields) : ?>
                            <details class="bp-pf-fields"<?php echo ($sel && !$ready) ? ' open' : ''; ?>>
                                <summary><?php echo $ready ? 'ویرایش اطلاعات اتصال' : 'تنظیم اطلاعات اتصال'; ?></summary>
                                <?php foreach ($fields as $fd) :
                                    $saved = self::has_secret($fd['name']); ?>
                                    <label class="bp-pf-lbl"><?php echo esc_html($fd['label']); ?>
                                        <?php if ($saved) echo '<span class="bp-chip bp-chip-ok">' . ($fd['secret'] ? 'ذخیره شده' : 'تنظیم شده') . '</span>'; ?></label>
                                    <?php if ($fd['secret']) : ?>
                                        <div class="bp-pw">
                                            <input type="password" name="<?php echo esc_attr($fd['name']); ?>" autocomplete="new-password" placeholder="<?php echo $saved ? '•••••••••••• (برای تغییر، مقدار جدید را وارد کنید)' : esc_attr($fd['ph']); ?>">
                                            <button type="button" class="button bp-eye" aria-label="نمایش یا پنهان‌کردن">نمایش</button>
                                        </div>
                                        <?php if ($saved) : ?>
                                            <label class="bp-pf-clear"><input type="checkbox" name="clear_<?php echo esc_attr($fd['name']); ?>" value="1"> حذف مقدار ذخیره‌شده</label>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <input type="text" name="<?php echo esc_attr($fd['name']); ?>" class="bp-pf-in" value="<?php echo esc_attr((string) ($o[$fd['name']] ?? '')); ?>" placeholder="<?php echo esc_attr($fd['ph']); ?>" autocomplete="off">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (!empty($mt['help'])) : ?>
                                    <a class="bp-pf-help" href="<?php echo esc_url($mt['help'][0]); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($mt['help'][1]); ?></a>
                                <?php endif; ?>
                            </details>
                        <?php endif; ?>
                        <?php if ($benched) echo '<p class="bp-pf-warn">به‌خاطر خطای اخیر تا حداکثر یک ساعت کنار گذاشته شده؛ بعد از آن خودکار دوباره امتحان می‌شود.</p>'; ?>
                    </div>
                    <?php
                };
                $wp_cards = [];
                ?>
                <div class="bp-pf-grid">
                <?php foreach ($plats as $k => $pl) {
                    if (preg_match('~^wp\d+$~', $k)) { $wp_cards[$k] = $pl; continue; }
                    $render_card($k, $pl);
                } ?>
                </div>
                <?php if ($wp_cards) :
                    $wp_sel = count(array_intersect(array_keys($wp_cards), $o['platforms'])); ?>
                    <details class="bp-wpgrp" <?php echo count($wp_cards) <= 6 ? 'open' : ''; ?>>
                        <summary><b>وردپرس‌های شما</b> — <?php echo (int) count($wp_cards); ?> سایت، <?php echo (int) $wp_sel; ?> انتخاب‌شده</summary>
                        <p style="margin:8px 0">
                            <button type="button" class="button button-small bp-wp-all" data-on="1">انتخاب همه</button>
                            <button type="button" class="button button-small bp-wp-all" data-on="0">برداشتن همه</button>
                        </p>
                        <div class="bp-wp-list">
                        <?php foreach ($wp_cards as $k => $pl) :
                            $sel = in_array($k, $o['platforms'], true);
                            $off = self::off($k); ?>
                            <label class="bp-wp-row<?php echo $sel ? ' on' : ''; ?>">
                                <input type="checkbox" name="platforms[]" value="<?php echo esc_attr($k); ?>" <?php checked($sel); ?>>
                                <span dir="ltr"><?php echo esc_html($pl['domain']); ?></span>
                                <?php if ($off) echo '<span class="bp-chip bp-chip-err">کنار گذاشته شده</span>'; ?>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
                <style>
                .bp-pf-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;max-width:900px;margin:16px 0 0}
                .bp-pf-sum{font-size:12px;color:#50575e;background:#f0f0f1;border-radius:12px;padding:2px 10px}
                .bp-pf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:12px;max-width:900px}
                .bp-pf{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;transition:border-color .15s,box-shadow .15s}
                .bp-pf.on{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1 inset;background:#f8fbfe}
                .bp-pf-top{display:flex;align-items:center;gap:8px;cursor:pointer;flex-wrap:wrap}
                .bp-pf-name{font-weight:600;font-size:14px}
                .bp-pf-top .bp-chip{margin-right:auto}
                .bp-pf-rec{font-size:11px;color:#1a7f37;font-weight:600}
                .bp-pf-desc{margin:6px 0 8px;color:#50575e;font-size:12.5px;line-height:1.8}
                .bp-pf-badges{display:flex;gap:5px;flex-wrap:wrap}
                .bp-chip{display:inline-block;font-size:11px;line-height:1.7;border-radius:10px;padding:0 8px;background:#f0f0f1;color:#50575e;border:1px solid #e0e0e0}
                .bp-chip-ok{background:#e6f4ea;color:#1a7f37;border-color:#c9e7d1}
                .bp-chip-warn{background:#fff4e0;color:#8a4b00;border-color:#f0d58a}
                .bp-chip-err{background:#fce8e8;color:#b32d2e;border-color:#f3c1c1}
                .bp-chip-info{background:#e8f1fb;color:#135e96;border-color:#c5d9ed}
                .bp-pf-fields{margin-top:10px;padding-top:10px;border-top:1px dashed #dcdcde}
                .bp-pf-lbl{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;margin-bottom:4px}
                .bp-pw{display:flex;gap:6px}
                .bp-pw input,.bp-pf-in{flex:1;width:100%;direction:ltr;text-align:left;box-sizing:border-box}
                .bp-pf-clear{display:block;margin-top:6px;font-size:12px;color:#b32d2e}
                .bp-pf-help{display:inline-block;margin-top:6px;font-size:12px}
                .bp-pf-warn{margin:8px 0 0;font-size:12px;color:#8a4b00}
                .bp-pf-fields summary{cursor:pointer;color:#2271b1;font-size:12px;margin-bottom:6px}
                .bp-pf-fields .bp-pf-lbl{margin-top:8px}
                .bp-wpgrp{max-width:900px;margin-top:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 14px}
                .bp-wpgrp summary{cursor:pointer}
                .bp-wp-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:6px;max-height:320px;overflow:auto}
                .bp-wp-row{display:flex;align-items:center;gap:6px;border:1px solid #e0e0e0;border-radius:6px;padding:4px 8px;font-size:12.5px;cursor:pointer}
                .bp-wp-row.on{border-color:#2271b1;background:#f8fbfe}
                </style>
                <script>(function(){var s=document.currentScript,f=s.closest('form');
                f.addEventListener('change',function(e){var t=e.target;if(t.name==='platforms[]'){var c=t.closest('.bp-pf,.bp-wp-row');if(c)c.classList.toggle('on',t.checked);}});
                f.addEventListener('click',function(e){var b=e.target.closest('.bp-wp-all');if(!b)return;var on=b.dataset.on==='1';f.querySelectorAll('.bp-wp-row input').forEach(function(i){i.checked=on;i.closest('.bp-wp-row').classList.toggle('on',on);});});
                f.addEventListener('click',function(e){var b=e.target.closest('.bp-eye');if(!b)return;var i=b.parentNode.querySelector('input');var show=i.type==='password';i.type=show?'text':'password';b.textContent=show?'پنهان':'نمایش';});
                })();</script>
                <?php BP_Guide::box('platforms'); ?>

                <p style="margin:16px 0 6px"><b>۳) وردپرس‌های خودتان (اختیاری ولی مؤثرترین)</b><br>
                    <textarea name="wp_sites" rows="6" class="large-text" style="direction:ltr;max-width:860px" autocomplete="off" placeholder="<?php echo $wp_n ? esc_attr($wp_n . ' سایت ذخیره شده — برای افزودن، خطوط جدید را بنویسید و گزینه‌ی «افزودن به سایت‌های فعلی» را بزنید') : 'https://example.com | username | xxxx xxxx xxxx xxxx xxxx xxxx | 5 | publish'; ?>"></textarea>
                    <small style="color:#666">هر خط یک سایت: <code>آدرس | نام‌کاربری | Application Password | شناسه‌ی دسته (اختیاری) | publish یا draft (اختیاری)</code>. در وردپرس مقصد: کاربران ← پروفایل ← Application Passwords.
                    <?php if ($wp_n) echo '<br>ذخیره‌شده: ' . esc_html(implode('، ', array_map(function ($w) { return $w['host']; }, BP_Publisher::wp_sites()))); ?>
                    <br><label><input type="checkbox" name="wp_sites_append" value="1" checked> افزودن به سایت‌های فعلی (به‌جای جایگزینی)</label>
                    <label style="margin-right:14px"><input type="checkbox" name="clear_wp_sites" value="1"> پاک کردن همه</label>
                    <br>تا <?php echo (int) BP_Publisher::WP_MAX; ?> سایت پشتیبانی می‌شود.</small>
                </p>
                <?php BP_Guide::box('wp_sites', $wp_n ? ($wp_n . ' وردپرس شخصی تنظیم شده.') : 'هنوز وردپرس شخصی تنظیم نشده.'); ?>

                <p style="margin:16px 0 6px"><b>۴) بازبینی مقاله‌ها قبل از انتشار</b></p>
                <select name="review_mode">
                    <option value="first" <?php selected($o['review_mode'], 'first'); ?>>۵ مقاله‌ی اول را خودم تأیید می‌کنم، بعد کاملاً خودکار (پیشنهادی)</option>
                    <option value="always" <?php selected($o['review_mode'], 'always'); ?>>همیشه قبل از انتشار تأیید شود</option>
                    <option value="off" <?php selected($o['review_mode'], 'off'); ?>>بدون بازبینی (از همان اول کاملاً خودکار)</option>
                </select>
                <?php BP_Guide::box('review_mode', $o['review_mode'] === 'first' ? ($o['review_first'] ? 'هنوز در مرحله‌ی بازبینی ۵ مقاله‌ی اول هستید (' . (int) $total . ' از ' . (int) self::REVIEW_N . ' منتشر شده).' : 'مرحله‌ی بازبینی تمام شده؛ اتوپایلوت کاملاً خودکار است.') : ''); ?>

                <details style="margin-top:18px;max-width:860px" <?php echo $o['profile'] === 'custom' ? 'open' : ''; ?>>
                    <summary style="cursor:pointer;font-weight:600;font-size:14px">تنظیمات دستی (فقط وقتی پروفایل «دستی» انتخاب شده فعال می‌شود)</summary>
                    <div class="bp-adv">
                    <table class="form-table" style="margin-top:6px"><tbody>
                        <tr><th>تعداد بکلینک در روز</th><td>
                            <input type="number" name="daily" min="1" max="30" value="<?php echo (int) $o['daily']; ?>" style="width:80px">
                            <?php BP_Guide::box('custom_daily'); ?></td></tr>
                        <tr><th>ساعت فعالیت (به وقت سایت)</th><td>
                            از <input type="number" name="hour_from" min="0" max="23" value="<?php echo (int) $o['hour_from']; ?>" style="width:70px">
                            تا <input type="number" name="hour_to" min="1" max="24" value="<?php echo (int) $o['hour_to']; ?>" style="width:70px">
                            <?php BP_Guide::box('hours'); ?></td></tr>
                        <tr><th>گرم‌کردن تدریجی (روز)</th><td>
                            <input type="number" name="ramp_days" min="0" max="60" value="<?php echo (int) $o['ramp_days']; ?>" style="width:70px">
                            <small style="color:#666">امروز سقف مؤثر: <b><?php echo (int) $cap; ?></b></small>
                            <label style="margin-right:12px"><input type="checkbox" name="reset_ramp" value="1"> شروع دوباره‌ی گرم‌کردن</label>
                            <?php BP_Guide::box('ramp'); ?></td></tr>
                        <tr><th>طول مقاله (کلمه)</th><td>
                            از <input type="number" name="words_min" min="400" max="2000" value="<?php echo (int) $o['words_min']; ?>" style="width:80px">
                            تا <input type="number" name="words_max" min="400" max="2500" value="<?php echo (int) $o['words_max']; ?>" style="width:80px">
                            <?php BP_Guide::box('words'); ?></td></tr>
                        <tr><th>وزن انواع انکر (٪)</th><td>
                            برند <input type="number" name="w_brand" min="0" max="100" value="<?php echo (int) $o['w_brand']; ?>" style="width:60px">
                            آدرس <input type="number" name="w_naked" min="0" max="100" value="<?php echo (int) $o['w_naked']; ?>" style="width:60px">
                            عمومی <input type="number" name="w_generic" min="0" max="100" value="<?php echo (int) $o['w_generic']; ?>" style="width:60px">
                            ترکیبی <input type="number" name="w_partial" min="0" max="100" value="<?php echo (int) $o['w_partial']; ?>" style="width:60px">
                            دقیق <input type="number" name="w_exact" min="0" max="100" value="<?php echo (int) $o['w_exact']; ?>" style="width:60px">
                            <?php BP_Guide::box('weights'); ?></td></tr>
                        <tr><th>ایمنی و پایش</th><td>
                            <label><input type="checkbox" name="dup_check" value="1" <?php checked(!empty($o['dup_check'])); ?>> رد مقاله‌ی شبیه مقاله‌های قبلی</label><br>
                            <label><input type="checkbox" name="recheck" value="1" <?php checked(!empty($o['recheck'])); ?>> بررسی دوره‌ی لینک‌های منتشرشده</label><br>
                            <label><input type="checkbox" name="notify_fail" value="1" <?php checked(!empty($o['notify_fail'])); ?>> ایمیل به مدیر هنگام خطای انتشار</label>
                            <?php BP_Guide::box('safety'); ?></td></tr>
                    </tbody></table>
                    </div>
                </details>
                <script>(function(){var s=document.currentScript,f=s.closest('form');function u(){var c=f.querySelector('input[name=profile]:checked');var on=c&&c.value==='custom';f.querySelectorAll('.bp-adv input').forEach(function(i){i.disabled=!on;});f.querySelectorAll('.bp-opt').forEach(function(l){var r=l.querySelector('input');l.classList.toggle('on',r&&r.checked);});}f.addEventListener('change',u);u();})();</script>

                <p style="margin-top:14px">
                    <?php if ($o['enabled']) : ?>
                        <button class="button button-primary button-hero" name="do" value="disable" style="background:#b32d2e;border-color:#8a2424">توقف اتوپایلوت</button>
                        <button class="button" name="do" value="save">ذخیره تنظیمات</button>
                        <a class="button" href="<?php echo esc_url(self::nl('auto_run')); ?>">اجرای همین الان (یک بکلینک)</a>
                    <?php else : ?>
                        <button class="button button-primary button-hero" name="do" value="enable">فعال‌سازی اتوپایلوت</button>
                        <button class="button" name="do" value="save">فقط ذخیره</button>
                    <?php endif; ?>
                </p>
            </form>
            <p style="color:#444">
                امروز: <b><?php echo (int) $today; ?></b> از <b><?php echo (int) $cap; ?></b><?php if ($cap < (int) $o['daily']) echo ' <small>(گرم‌کردن؛ سقف نهایی ' . (int) $o['daily'] . ')</small>'; ?>
                — کل منتشرشده: <b><?php echo (int) $total; ?></b>
                <?php if ($failed) echo ' — ناموفق: <b style="color:#b32d2e">' . (int) $failed . '</b>'; ?>
                <?php if ($held) echo ' — منتظر بازبینی: <b style="color:#b26200">' . (int) $held . '</b>'; ?>
                — کمپین فعال: <b><?php echo (int) $camps; ?></b>
                <?php if ($o['enabled'] && $next > time()) echo ' — انتشار بعدی: ' . esc_html(wp_date('H:i', $next)); ?>
            </p>
            <?php if ($total) : ?>
                <p style="color:#444">وضعیت لینک‌ها: follow <b style="color:#1a7f37"><?php echo (int) ($st['follow'] ?? 0); ?></b>
                    — nofollow <b style="color:#b26200"><?php echo (int) ($st['nofollow'] ?? 0); ?></b>
                    — مفقود/حذف‌شده <b style="color:#b32d2e"><?php echo (int) (($st['missing'] ?? 0) + ($st['gone'] ?? 0)); ?></b>
                    — بررسی‌نشده <b><?php echo (int) (($st['unchecked'] ?? 0) + ($st[''] ?? 0)); ?></b>
                    <a class="button button-small" style="margin-right:8px" href="<?php echo esc_url(self::nl('auto_recheck')); ?>">بررسی دوباره‌ی لینک‌ها</a></p>
            <?php endif; ?>
            <?php if (!BP_AI::chain('write')) echo '<p style="color:#b32d2e">⚠ هنوز کلید AI تنظیم نشده (تب «AI»)؛ بدون آن مقاله نوشته نمی‌شود.</p>'; ?>
            <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) echo '<p style="color:#b26200">⚠ WP-Cron در این سایت غیرفعال است؛ باید روی هاست یک Cron واقعی برای wp-cron.php تنظیم شده باشد.</p>'; ?>
        </div>

        <div class="bp-card">
            <h3>لینک‌های منتشرشده <a class="button button-small" style="margin-right:10px" href="<?php echo esc_url(self::nl('auto_export')); ?>">خروجی CSV</a></h3>
            <?php
            $rows = $wpdb->get_results('SELECT p.*, c.name cname FROM ' . BP_DB::t('plans') . " p LEFT JOIN " . BP_DB::t('campaigns') . " c ON c.id=p.campaign_id WHERE p.pub_platform<>'' AND p.status<>'rejected' ORDER BY COALESCE(p.published_at, p.updated_at, p.created_at) DESC, p.id DESC LIMIT 200");
            ?>
            <table class="widefat striped">
                <thead><tr><th>تاریخ</th><th>پلتفرم</th><th>کمپین</th><th>عنوان مقاله</th><th>انکر</th><th>صفحه‌ی هدف</th><th>لینک نهایی</th><th>بررسی لینک</th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows) echo '<tr><td colspan="9">هنوز چیزی منتشر نشده.</td></tr>'; ?>
                <?php foreach ($rows as $r) :
                    $ck = $chk[$r->pub_check] ?? $chk['']; ?>
                    <tr>
                        <td><?php echo esc_html($r->published_at ?: '—'); ?></td>
                        <td><?php echo esc_html($plats[$r->pub_platform]['label'] ?? $r->pub_platform); ?></td>
                        <td><?php echo esc_html((string) ($r->cname ?: ($r->campaign_name ?: '(کمپین حذف‌شده)'))); ?></td>
                        <td style="max-width:240px"><?php echo esc_html($r->article_title ?: '—'); ?>
                            <?php if ($r->article_body !== null && $r->article_body !== '' && in_array($r->pub_status, ['held', 'review_ok', 'failed'], true)) : ?>
                                <details><summary style="cursor:pointer;color:#2271b1">پیش‌نمایش</summary><div style="max-height:260px;overflow:auto;background:#fafafa;border:1px solid #ddd;padding:8px;margin-top:4px"><?php echo wp_kses_post($r->article_body); ?></div></details>
                            <?php endif; ?></td>
                        <td><?php echo esc_html($r->anchor_text); ?><br><small style="color:#666"><?php echo esc_html($r->anchor_type); ?></small></td>
                        <td style="direction:ltr;max-width:200px;word-break:break-all"><a href="<?php echo esc_url($r->target_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($r->target_url); ?></a></td>
                        <td style="direction:ltr;max-width:240px;word-break:break-all">
                            <?php if ($r->pub_status === 'published') : ?>
                                <a href="<?php echo esc_url($r->pub_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($r->pub_url); ?></a>
                            <?php elseif ($r->pub_status === 'failed') : ?>
                                <span style="color:#b32d2e;direction:rtl;display:inline-block"><?php echo esc_html($r->pub_error); ?></span>
                            <?php elseif ($r->pub_status === 'held') : echo '<span style="color:#b26200;direction:rtl;display:inline-block">منتظر تأیید شما</span>';
                            else : echo '<span style="color:#666;direction:rtl;display:inline-block">در صف</span>'; endif; ?>
                        </td>
                        <td style="color:<?php echo esc_attr($ck[1]); ?>"><?php echo esc_html($r->pub_status === 'published' ? $ck[0] : '—'); ?></td>
                        <td><?php
                            if ($r->pub_status === 'failed') echo '<a href="' . esc_url(self::nl('auto_retry', ['id' => $r->id])) . '">تلاش مجدد</a>';
                            if ($r->pub_status === 'held') echo '<a class="button button-small button-primary" href="' . esc_url(self::nl('auto_approve', ['id' => $r->id])) . '">تأیید و انتشار</a> <a href="' . esc_url(self::nl('auto_reject', ['id' => $r->id])) . '" style="color:#b32d2e" onclick="return confirm(\'این مقاله حذف شود؟\')">حذف</a>';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="color:#666">«بررسی لینک»: بعد از انتشار، پلاگین صفحه را می‌خواند و می‌بیند لینک هدف واقعاً هست و follow است یا nofollow.</p>
        </div>
        <?php
    }
}

/* ============================================================
 * v1.8 — BP_Guide: راهنمای «چطور بهینه‌تر شود؟» برای هر گزینه + پنل راه‌اندازی + پیشنهادهای زنده
 * ============================================================ */
class BP_Guide {

    private static function data() {
        return [
            'profile' => [
                'what' => 'سه پیش‌تنظیم آماده برای سرعت، ساعت فعالیت، گرم‌کردن، طول مقاله و نسبت انکرها. با انتخاب یکی از آن‌ها لازم نیست هیچ عدد دیگری را دستی تنظیم کنید.',
                'best' => 'متعادل برای شروع. بعد از ۳ تا ۴ هفته و دیدن نتیجه‌ی رادار تصمیم بگیرید بالاتر یا پایین‌تر بروید.',
                'tips' => [
                    'محتاط: سایت تازه‌تأسیس، سایت مشتری، یا حوزه‌های حساس (حقوقی، پزشکی، مالی).',
                    'سریع: فقط وقتی در رادار درصد «زنده» و «ایندکس‌شده» خوب است و سقف توکن AI کافی دارید.',
                    'خودتنظیم: اگر خطای انتشار زیاد شود یا لینک‌ها حذف شوند، اتوپایلوت خودش تا نصف سرعت را کم می‌کند و وقتی مشکل رفع شد برمی‌گردد؛ هیچ‌وقت از سقف پروفایل بیشتر نمی‌شود.',
                    'پروفایل «دستی» فقط وقتی لازم است که دلیل مشخصی برای تغییر تک‌تک اعداد دارید.',
                ],
                'warn' => 'سرعت زیاد الگوی غیرطبیعی می‌سازد. تعداد بیشتر لینک همیشه نتیجه‌ی بهتر نیست؛ تنوع و کیفیت مهم‌تر است.',
            ],
            'platforms' => [
                'what' => 'جاهایی که اتوپایلوت مقاله را روی آن‌ها منتشر می‌کند. هر بار پلتفرمِ کم‌استفاده‌تر و موفق‌تر (طبق رادار) انتخاب می‌شود.',
                'best' => 'Telegra.ph + Write.as + حداقل یک وردپرس خودتان. DEV.to و Hashnode فقط برای کمپین‌های انگلیسی و فنی به کار می‌آیند.',
                'tips' => [
                    'پلتفرم‌های رایگان ناشناس معمولاً اعتبار کمی منتقل می‌کنند و بسیاری از لینک‌هایشان nofollow است؛ ارزش‌شان بیشتر تنوع پروفایل لینک و کشف‌شدن است. وضعیت واقعی را در «گزارش لینک‌ها» و «رادار» ببینید.',
                    'تنوع پلتفرم بهتر از تکرار یک پلتفرم است؛ حداقل دو مورد را روشن نگه دارید.',
                    'اگر یک پلتفرم خطا بدهد تا یک ساعت خودکار کنار گذاشته می‌شود و بقیه کار را ادامه می‌دهند.',
                ],
                'warn' => 'برای کمپین فارسی، DEV.to و Hashnode هیچ‌وقت انتخاب نمی‌شوند (فقط انگلیسی‌اند).',
            ],
            'wp_sites' => [
                'what' => 'وردپرس‌هایی که خودتان دارید (از طریق REST و Application Password). لینک روی سایت خودتان قابل‌کنترل است و معمولاً dofollow می‌ماند.',
                'best' => 'یک تا سه سایت هم‌موضوع با صفحه‌ی هدف، هرکدام یک دسته‌ی اختصاصی برای مقاله‌های اتوپایلوت.',
                'tips' => [
                    'برای هر سایت یک کاربر جدا با نقش «نویسنده» یا «ویرایشگر» و Application Password مخصوص بسازید، نه رمز مدیر.',
                    'اگر می‌خواهید قبل از انتشار روی سایت‌تان نگاه کنید، آخر خط draft بنویسید.',
                    'سایتی که فقط پر از مقاله‌های لینک‌دار باشد خودش کم‌ارزش می‌شود؛ محتوای عادی هم روی آن منتشر کنید.',
                ],
                'warn' => 'فقط سایت‌هایی که مالک‌شان هستید یا اجازه‌ی انتشار دارید.',
            ],
            'review_mode' => [
                'what' => 'کنترل ایمنی: مقاله ساخته می‌شود ولی تا تأیید شما منتشر نمی‌شود.',
                'best' => '«۵ مقاله‌ی اول»: کیفیت را یک بار می‌سنجید و بعد همه‌چیز خودکار می‌شود.',
                'tips' => [
                    'در ۵ مقاله‌ی اول این‌ها را نگاه کنید: زبان و لحن درست، فقط یک لینک به صفحه‌ی هدف، انکر طبیعی، و تکراری نبودن مقدمه‌ها.',
                    'برای حوزه‌های حساس (حقوقی، پزشکی) یا سایت مشتری «همیشه» را بگذارید.',
                    'تا ۳ مقاله منتظر تأیید بماند، تولید مقاله‌ی جدید متوقف می‌شود؛ پس هر چند روز یک بار تب را نگاه کنید.',
                ],
                'warn' => '',
            ],
            'custom_daily' => [
                'what' => 'سقف تعداد بکلینک در روز (فقط در پروفایل دستی).',
                'best' => '۲ تا ۵؛ برای سایت‌های کم‌سن کمتر.',
                'tips' => ['عدد را به‌تدریج بالا ببرید و بعد از هر افزایش، رادار را یک هفته نگاه کنید.'],
                'warn' => 'مقدار بالا و ناگهانی بزرگ‌ترین عامل الگوی غیرطبیعی است.',
            ],
            'hours' => [
                'what' => 'انتشارها فقط در این بازه و به‌صورت پخش‌شده انجام می‌شود (فاصله‌ی بین انتشارها حدوداً طول بازه ÷ سقف روزانه، با ±۲۰٪ تصادف).',
                'best' => '۸ تا ۲۳ (یا ساعات بیداری مخاطب هدف). ۲۴ ساعته نگذارید.',
                'tips' => ['ساعت به وقت سایت است؛ منطقه‌ی زمانی وردپرس (تنظیمات ← عمومی) را درست بگذارید.'],
                'warn' => '',
            ],
            'ramp' => [
                'what' => 'گرم‌کردن تدریجی: سرعت از ۱ در روز شروع می‌شود و خطی بالا می‌رود تا در این تعداد روز به سقف برسد.',
                'best' => '۱۴ روز (محتاط ۲۱، سریع ۱۰).',
                'tips' => [
                    'بعد از یک وقفه‌ی طولانی یا رفتن به پروفایل سریع‌تر، «شروع دوباره‌ی گرم‌کردن» را بزنید.',
                    '۰ فقط برای تست است.',
                ],
                'warn' => '',
            ],
            'words' => [
                'what' => 'بازه‌ی طول مقاله‌ی تولیدی (کلمه). هر مقاله عددی تصادفی داخل بازه می‌گیرد.',
                'best' => '۷۵۰ تا ۱۰۵۰.',
                'tips' => [
                    'مقاله‌ی بلندتر توکن بیشتری مصرف می‌کند؛ در پلن‌های رایگان AI زودتر به سقف می‌رسید.',
                    'فاصله‌ی حداقل و حداکثر را نگه دارید تا طول همه‌ی مقاله‌ها یکسان نشود.',
                ],
                'warn' => '',
            ],
            'weights' => [
                'what' => 'نسبت انواع انکر: برند، آدرس (URL)، عمومی (مثل «اینجا»)، ترکیبی و دقیق (کلمه‌ی کلیدی کامل).',
                'best' => 'همان پیش‌فرض: بیشتر برند/آدرس/عمومی (حدود ۸۵٪)، دقیق بسیار کم.',
                'tips' => [
                    'پروفایل لینک طبیعی انکر دقیق کم دارد؛ در کد، سقف سخت ۱۰٪ برای دقیق اعمال می‌شود.',
                    'یادگیری خودکار رادار تا ±۳۰٪ این وزن‌ها را بر اساس نتیجه‌ی واقعی تنظیم می‌کند و انکر دقیق/ترکیبی را هیچ‌وقت بیشتر نمی‌کند.',
                ],
                'warn' => 'بالا بردن «دقیق» برای رتبه‌ی سریع‌تر، ریسک جریمه‌ی بیش‌ازحد بهینه‌سازی را بالا می‌برد.',
            ],
            'safety' => [
                'what' => 'سه محافظ: رد مقاله‌ی شبیه مقاله‌های قبلی، بررسی دوره‌ای لینک‌ها (حذف‌شدن یا تبدیل به nofollow)، ایمیل خطا.',
                'best' => 'هر سه روشن (در پروفایل‌های آماده همیشه روشن‌اند).',
                'tips' => ['ایمیل خطا حداکثر هر ۶ ساعت یک بار ارسال می‌شود؛ ایمیل سرور وردپرس را (SMTP) درست تنظیم کنید تا به دستتان برسد.'],
                'warn' => '',
            ],
            'ai_keys' => [
                'what' => 'کلید سرویس‌های AI که متن مقاله و تصمیم‌ها را می‌سازند.',
                'best' => 'حداقل دو ارائه‌دهنده (مثلاً Gemini + Groq) تا اگر یکی خطا داد یا سقفش پر شد، بعدی ادامه دهد.',
                'tips' => [
                    'بعد از ذخیره حتماً «تست اتصال» را بزنید.',
                    'اگر خطای «model not found» دیدید، اسم مدل را مطابق پنل ارائه‌دهنده اصلاح کنید.',
                    'پلن‌های رایگان گارانتی حریم خصوصی ندارند؛ اطلاعات محرمانه‌ی مشتری را در موضوع و کلیدواژه نگذارید.',
                    'اگر هاست شما به این سرویس‌ها دسترسی ندارد، پروکسی (تب تنظیمات) یا یک سرور سفارشی لازم است.',
                ],
                'warn' => '',
            ],
            'ai_chain' => [
                'what' => 'ترتیب امتحان ارائه‌دهنده‌ها. از این نسخه، هر ارائه‌دهنده‌ی تنظیم‌شده که در لیست نباشد خودکار به انتهای زنجیره اضافه می‌شود.',
                'best' => 'دست نزنید. تصمیم‌گیری: سریع‌ترین (Groq)؛ نوشتن: قوی‌ترین (Gemini).',
                'tips' => ['فقط وقتی تغییر بدهید که یک ارائه‌دهنده به‌طور مداوم کند یا ضعیف است.'],
                'warn' => '',
            ],
            'autonomy' => [
                'what' => 'فقط برای صف دستی ایجنت است (برنامه‌ریزی بکلینک روی دامنه‌های پایگاه داده).',
                'best' => 'هرچه هست بماند.',
                'tips' => ['وقتی اتوپایلوت روشن است این گزینه استفاده نمی‌شود؛ اتوپایلوت خودش برنامه می‌سازد و AI را فقط برای مقاله‌ی همان برنامه صدا می‌زند.'],
                'warn' => '',
            ],
            'token_cap' => [
                'what' => 'سقف مصرف روزانه‌ی توکن AI. با رسیدن به آن تا فردا AI صدا زده نمی‌شود و اتوپایلوت مقاله نمی‌نویسد.',
                'best' => 'حدود ۱٫۵ برابر مصرف واقعی یک روز عادی (بالای همین تب مصرف امروز را می‌بینید).',
                'tips' => [
                    'اگر مرتب به سقف می‌رسید: طول مقاله را کم کنید یا ارائه‌دهنده‌ی دوم اضافه کنید.',
                    'سقف را کمتر از سهمیه‌ی روزانه‌ی پلن رایگان خودتان بگذارید تا خطای سهمیه نگیرید.',
                ],
                'warn' => '',
            ],
            'ai_proxy' => [
                'what' => 'عبور درخواست‌های AI ابری از پروکسی تب «تنظیمات».',
                'best' => 'روشن اگر پروکسی دارید و هاست مستقیم به AI دسترسی ندارد؛ وگرنه فرقی نمی‌کند.',
                'tips' => ['سرور سفارشی (Ollama/LM Studio) هرگز از پروکسی رد نمی‌شود.'],
                'warn' => '',
            ],
            'proxy' => [
                'what' => 'پروکسی خروجی برای درخواست‌های هاست به GitHub، سرویس‌های AI، پلتفرم‌های انتشار و بررسی ایندکس.',
                'best' => 'خالی اگر هاست مستقیم به همه‌ی سرویس‌ها دسترسی دارد. در غیر این صورت یک پروکسی پایدار.',
                'tips' => [
                    'اول در تب «اتوپایلوت» دکمه‌ی «آزمایش اتصال سرویس‌ها» را بزنید تا ببینید دقیقاً کدام سرویس بسته است.',
                    'فرمت socks5h:// علاوه بر ترافیک، DNS را هم از پروکسی می‌گذراند و برای هاست‌هایی که DNS محدود دارند بهتر است.',
                    'پروکسی ناپایدار یعنی خطاهای تصادفی انتشار و AI؛ اگر در لاگ زیاد می‌بینید، پروکسی را عوض کنید.',
                ],
                'warn' => '',
            ],
            'github_token' => [
                'what' => 'فقط برای همگام‌سازی منابع GitHub (پایگاه دامنه). اتوپایلوت به آن نیاز ندارد.',
                'best' => 'خالی، مگر اینکه همگام‌سازی خطای محدودیت نرخ (۶۰ درخواست در ساعت) بدهد.',
                'tips' => ['اگر لازم شد، توکنی با کمترین دسترسی (فقط خواندن عمومی) بسازید.'],
                'warn' => '',
            ],
            'opr_key' => [
                'what' => 'فقط برای امتیازدهی دامنه‌های پایگاه داده. اتوپایلوت از آن استفاده نمی‌کند.',
                'best' => 'اختیاری.',
                'tips' => [],
                'warn' => '',
            ],
            'batch_size' => [
                'what' => 'تعداد دامنه‌ای که در هر اجرای تحلیل بررسی می‌شود (پایگاه دامنه).',
                'best' => '۱۰ تا ۲۰ روی هاست اشتراکی.',
                'tips' => ['اگر لاگ، تایم‌اوت نشان می‌دهد کمتر کنید.'],
                'warn' => '',
            ],
            'target_lang' => [
                'what' => 'فقط روی امتیاز دامنه‌های پایگاه داده اثر دارد. زبان مقاله‌های اتوپایلوت از زبان خود کمپین/سایت می‌آید.',
                'best' => 'زبان اصلی سایت‌تان.',
                'tips' => [],
                'warn' => '',
            ],
            'auto_flags' => [
                'what' => 'تحلیل خودکار صف دامنه‌ها، همگام‌سازی روزانه‌ی GitHub و ایمیل خلاصه‌ی روزانه.',
                'best' => 'روشن اگر از صف دستی/پایگاه دامنه استفاده می‌کنید.',
                'tips' => ['اگر فقط از اتوپایلوت استفاده می‌کنید، تحلیل و همگام‌سازی را خاموش کنید تا بار روی هاست و پروکسی کم شود.'],
                'warn' => '',
            ],
            'auto_campaigns' => [
                'what' => 'اسکن خودکار نوشته‌ها و برگه‌های سایت و ساخت کمپین (= صفحه‌ی هدف) از روی پیلارها. اتوپایلوت لینک‌ها را به همین صفحه‌ها می‌دهد.',
                'best' => 'روشن؛ حداکثر ۴ تا ۶ کمپین.',
                'tips' => [
                    'کمپین کمتر = تمرکز بیشتر روی صفحه‌های مهم؛ کمپین زیاد = لینک‌ها بین صفحه‌های بیشتری پخش می‌شود.',
                    'کمپین‌هایی را که صفحه‌ی مهمی نیستند در تب «کمپین‌ها» غیرفعال کنید.',
                ],
                'warn' => '«سقف روزانه‌ی برنامه‌های جدید» فقط برای صف ایجنت است؛ سقف اتوپایلوت را در تب اتوپایلوت (پروفایل) تنظیم می‌کنید.',
            ],
            'niche' => [
                'what' => 'اولویت‌دهی حوزه برای صف دستی ایجنت. اتوپایلوت از آن استفاده نمی‌کند.',
                'best' => 'خالی.',
                'tips' => [],
                'warn' => '',
            ],
            'r_engine' => [
                'what' => 'بررسی ایندکس‌شدن صفحه‌ی بکلینک از نتایج عمومی Bing (تقریبی).',
                'best' => 'روشن.',
                'tips' => [
                    'اگر موتور جستجو محدودت کند، پلاگین خودش ۳ ساعت مکث می‌کند.',
                    'برای Google چند لینک نمونه را با «بررسی در Google» دستی چک کنید.',
                ],
                'warn' => '',
            ],
            'r_indexnow' => [
                'what' => 'اعلام صفحه‌ی هدف (روی همین سایت) به Bing/Yandex و… بعد از هر انتشار.',
                'best' => 'روشن.',
                'tips' => [
                    'با «آزمایش فایل کلید» مطمئن شوید فایل کلید باز می‌شود؛ با پیوند یکتای «ساده» باید فایل را دستی بسازید.',
                    'روی Telegra.ph و مانند آن کار نمی‌کند، فقط روی آدرس‌های همین سایت.',
                ],
                'warn' => '',
            ],
            'r_hub' => [
                'what' => 'یک صفحه‌ی عمومی با لینک به بکلینک‌های منتشرشده تا خزنده‌ها آن‌ها را سریع‌تر پیدا کنند.',
                'best' => 'خاموش، مگر اینکه ایندکس‌نشدن لینک‌ها مشکل جدی شده باشد.',
                'tips' => ['اگر روشن کنید، صفحه‌ای قابل‌خزیدن می‌سازد که ردپای استراتژی لینک‌سازی شما را نشان می‌دهد.'],
                'warn' => '',
            ],
            'r_learn' => [
                'what' => 'اتوپایلوت پلتفرم‌های موفق را بیشتر انتخاب می‌کند و وزن انکرهای بی‌اثر را کم می‌کند.',
                'best' => 'روشن.',
                'tips' => ['برای هر پلتفرم حداقل ۵ لینکِ ≥ ۱۰ روزه لازم است؛ تا آن موقع پلتفرم‌ها یکنواخت انتخاب می‌شوند.'],
                'warn' => '',
            ],
            'r_gsc' => [
                'what' => 'مقایسه‌ی نمایش/کلیک/رتبه‌ی صفحه‌ی هدف ۲۸ روز قبل و بعد از انتشار. قوی‌ترین سیگنال برای فهمیدن اینکه کدام لینک واقعاً مؤثر بوده.',
                'best' => 'وصل کنید؛ بدون آن یادگیری فقط به فنی‌بودن و ایندکس تکیه می‌کند.',
                'tips' => [
                    'اثر از روز ۱۷ به بعد سنجیده می‌شود؛ زود قضاوت نکنید.',
                    'تغییر محتوا، فصل و الگوریتم هم روی رتبه اثر دارند؛ عدد رادار شاخص کمکی است نه اثبات علّیت.',
                ],
                'warn' => '',
            ],
        ];
    }

    /** جعبه‌ی راهنمای تاشو. $live: جمله‌ی داینامیک بر اساس وضعیت فعلی (متن ساده) */
    public static function box($key, $live = '') {
        $d = self::data();
        if (!isset($d[$key])) return;
        $g = $d[$key];
        echo '<details class="bp-guide"><summary>💡 چطور بهینه‌تر شود؟</summary><div>';
        if ($live !== '') echo '<p class="bp-guide-live">📊 ' . esc_html($live) . '</p>';
        if (!empty($g['what'])) echo '<p><b>این چیست؟</b> ' . esc_html($g['what']) . '</p>';
        if (!empty($g['best'])) echo '<p><b>مقدار پیشنهادی:</b> ' . esc_html($g['best']) . '</p>';
        if (!empty($g['tips'])) {
            echo '<ul>';
            foreach ($g['tips'] as $t) echo '<li>' . esc_html($t) . '</li>';
            echo '</ul>';
        }
        if (!empty($g['warn'])) echo '<p class="bp-guide-warn">⚠ ' . esc_html($g['warn']) . '</p>';
        echo '</div></details>';
    }

    /** پیشنهادهای زنده بر اساس داده‌ی واقعی (فقط موارد مهم) */
    public static function suggestions(array $o) {
        global $wpdb;
        $tp = BP_DB::t('plans');
        $out = [];
        $plats = BP_Publisher::platforms();

        if (!BP_Publisher::wp_sites()) {
            $out[] = 'هنوز وردپرس شخصی اضافه نکرده‌اید. لینک روی سایت‌های خودتان قابل‌کنترل‌ترین منبع است؛ یک یا دو سایت هم‌موضوع اضافه کنید.';
        }

        $pub = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tp WHERE pub_status='published'");

        // پلتفرم‌هایی که تقریباً همه‌ی لینک‌هایشان nofollow است
        foreach ((array) $wpdb->get_results("SELECT pub_platform p, COUNT(*) n, SUM(pub_check='nofollow') nf FROM $tp WHERE pub_status='published' AND pub_check IN ('follow','nofollow') GROUP BY pub_platform") as $r) {
            if ((int) $r->n >= 5 && (int) $r->nf / max(1, (int) $r->n) >= 0.8) {
                $out[] = 'تقریباً همه‌ی لینک‌های «' . ($plats[$r->p]['label'] ?? $r->p) . '» nofollow است؛ ارزش آن بیشتر در تنوع و کشف‌شدن است تا انتقال اعتبار. سهم وردپرس‌های خودتان را بیشتر کنید.';
            }
        }

        // پلتفرم‌هایی که زیاد خطا می‌دهند
        $since = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 14 * DAY_IN_SECONDS);
        foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT pub_platform p, SUM(pub_status='failed') bad, COUNT(*) n FROM $tp WHERE pub_platform<>'' AND pub_status IN ('published','failed') AND updated_at >= %s GROUP BY pub_platform", $since)) as $r) {
            if ((int) $r->bad >= 3 && (int) $r->bad / max(1, (int) $r->n) > 0.3) {
                $out[] = 'انتشار روی «' . ($plats[$r->p]['label'] ?? $r->p) . '» در ۲ هفته‌ی اخیر زیاد خطا داده؛ کلید/دسترسی/پروکسی را بررسی کنید («آزمایش اتصال سرویس‌ها») یا آن را موقتاً خاموش کنید.';
            }
        }

        $h = BP_Auto::health();
        if ($h['f'] < 1 && ($o['profile'] ?? 'custom') !== 'custom') {
            $out[] = 'سرعت خودکار به‌خاطر ' . implode(' و ', $h['why']) . ' موقتاً کم شده است؛ بعد از رفع مشکل خودش برمی‌گردد.';
        }

        $held = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tp WHERE pub_status='held' AND status<>'rejected'");
        if ($held) $out[] = $held . ' مقاله منتظر تأیید شماست؛ تا ۳ مقاله، تولید مقاله‌ی جدید متوقف می‌ماند.';

        if ($pub >= 10 && !BP_Radar::gsc_ready()) {
            $out[] = 'بیش از ۱۰ لینک منتشر شده ولی Search Console وصل نیست؛ بدون آن نمی‌توانید بفهمید کدام لینک روی رتبه اثر داشته (تب رادار).';
        }

        $tr = BP_DB::t('radar');
        $old = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 14 * DAY_IN_SECONDS);
        $ix = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) n, SUM(idx_status='indexed') i FROM $tr WHERE published_at < %s AND tech_status='ok'", $old));
        if ($ix && (int) $ix->n >= 15 && (int) $ix->i / max(1, (int) $ix->n) < 0.15) {
            $out[] = 'کمتر از ۱۵٪ لینک‌های سالم ≥ ۲ هفته ایندکس تشخیص داده شده‌اند. اگر هاست محدودیت دارد، بررسی Bing ممکن است ناقص باشد؛ چند نمونه را در Google دستی بسنجید و در صورت تأیید، «صفحه‌ی کشف» را روشن کنید.';
        }

        $s = BP_Settings::all();
        $u = BP_AI::usage();
        if ((int) $s['token_cap'] > 0 && $u['tokens'] >= 0.8 * (int) $s['token_cap']) {
            $out[] = 'مصرف AI امروز به بیش از ۸۰٪ سقف رسیده؛ سقف توکن را بالا ببرید یا ارائه‌دهنده‌ی دوم اضافه کنید.';
        }
        return $out;
    }

    /** پنل بالای تب اتوپایلوت: چک‌لیست راه‌اندازی + پیشنهادهای زنده */
    public static function panel(array $o) {
        global $wpdb;
        $items = [];
        $ai = (bool) BP_AI::chain('write');
        $items[] = [$ai ? 'ok' : 'bad', 'کلید AI', $ai ? 'آماده' : 'حداقل یک کلید لازم است ← تب «اتصال AI»'];

        $ready = [];
        foreach ($o['platforms'] as $p) {
            if (BP_Publisher::ready($p, $o)) $ready[] = $p;
        }
        $items[] = [$ready ? 'ok' : 'bad', 'پلتفرم انتشار', $ready ? count($ready) . ' پلتفرم آماده' : 'حداقل یک پلتفرم را در پایین انتخاب کنید'];

        $cron = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $items[] = [$cron ? 'warn' : 'ok', 'WP-Cron', $cron ? 'غیرفعال است؛ روی هاست یک Cron واقعی برای wp-cron.php بسازید' : 'فعال'];

        $camps = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . BP_DB::t('campaigns') . ' WHERE active=1');
        $items[] = [$camps ? 'ok' : 'warn', 'صفحه‌های هدف (کمپین)', $camps ? $camps . ' کمپین فعال' : 'بعد از فعال‌سازی، اسکن خودکار سایت کمپین می‌سازد'];

        $st = BP_Auto::selftest_get();
        if ($st) {
            $bad = [];
            foreach ($st as $name => $r) {
                if (empty($r['ok'])) $bad[] = $name;
            }
            $items[] = [$bad ? 'warn' : 'ok', 'اتصال هاست به سرویس‌ها', $bad ? ('در دسترس نیست: ' . implode('، ', $bad) . ' — پروکسی را در تب «تنظیمات» بررسی کنید') : 'همه‌ی سرویس‌ها در دسترس‌اند'];
        } else {
            $items[] = ['warn', 'اتصال هاست به سرویس‌ها', 'هنوز آزمایش نشده'];
        }

        $gsc = BP_Radar::gsc_ready();
        $items[] = [$gsc ? 'ok' : 'warn', 'Google Search Console', $gsc ? 'وصل' : 'اختیاری ولی توصیه‌شده (تب رادار)'];

        $ico = ['ok' => '✅', 'warn' => '⚠️', 'bad' => '❌'];
        echo '<div class="bp-setup"><b>وضعیت راه‌اندازی</b><ul>';
        foreach ($items as $it) {
            echo '<li>' . $ico[$it[0]] . ' <b>' . esc_html($it[1]) . '</b> — ' . esc_html($it[2]) . '</li>';
        }
        echo '</ul><a class="button button-small" href="' . esc_url(BP_Auto::selftest_url()) . '">آزمایش اتصال سرویس‌ها</a>';
        if ($st) {
            echo ' <small style="color:#666">';
            $parts = [];
            foreach ($st as $name => $r) $parts[] = ($r['ok'] ? '✓ ' : '✗ ') . $name . ($r['ok'] ? ' (' . (int) $r['ms'] . 'ms)' : '');
            echo esc_html(implode(' · ', $parts)) . '</small>';
        }
        echo '</div>';

        $sg = self::suggestions($o);
        if ($sg) {
            echo '<div class="bp-sugg"><b>پیشنهادهای هوشمند بر اساس داده‌ی واقعی شما</b><ul>';
            foreach ($sg as $t) echo '<li>' . esc_html($t) . '</li>';
            echo '</ul></div>';
        }
    }
}

/* ============================================================
 * v1.7 — BP_Radar: رادار بکلینک
 * چرخه‌ی هر لینک: منتشر شد ← بررسی فنی ← کشف/پینگ ← ایندکس ← اثر روی رتبه (Search Console)
 * و یادگیری خودکار: پلتفرم و نوع انکرِ بی‌اثر وزن کمتری می‌گیرند.
 * ============================================================ */
class BP_Radar {
    const OPT = 'bp_radar';
    /** آستانه‌ی سن (روز) برای بررسی‌های موتور جستجو */
    private static $idx_steps = [2, 5, 10, 20, 35, 60];

    /* ---------- راه‌اندازی ---------- */

    public static function init_front() {
        add_action('init', [__CLASS__, 'serve'], 1);
        add_action('bp_radar_event', [__CLASS__, 'cron']);
    }

    public static function init_admin() {
        foreach (['radar_save', 'radar_check', 'radar_ping', 'radar_test_key', 'radar_test_gsc', 'radar_export'] as $a) {
            add_action('admin_post_bp_' . $a, [__CLASS__, 'h_' . $a]);
        }
        add_action('bp_admin_tab_radar', [__CLASS__, 'tab']);
    }

    public static function opts() {
        $o = wp_parse_args(get_option(self::OPT, []), [
            'indexnow' => 1, 'hub' => 0, 'engine' => 1, 'learn' => 1, 'gsc' => 1,
            'gsc_site' => '', 'gsc_json' => '', 'key' => '', 'hub_token' => '',
        ]);
        return $o;
    }

    private static function save_opts(array $o) {
        update_option(self::OPT, $o, false);
    }

    private static function ensure_key() {
        $o = self::opts();
        $changed = false;
        if (!preg_match('~^[A-Za-z0-9]{16,64}$~', (string) $o['key'])) {
            $o['key'] = wp_generate_password(32, false, false);
            $changed = true;
        }
        if (!preg_match('~^[A-Za-z0-9]{12,64}$~', (string) $o['hub_token'])) {
            $o['hub_token'] = wp_generate_password(20, false, false);
            $changed = true;
        }
        if ($changed) self::save_opts($o);
        return $o;
    }

    public static function hub_url() {
        $o = self::ensure_key();
        return add_query_arg('bp_hub', $o['hub_token'], home_url('/'));
    }

    private static function now_ts() {
        return strtotime(current_time('mysql') . ' UTC');
    }

    private static function ts($s) {
        return $s ? (int) strtotime($s . ' UTC') : 0;
    }

    private static function fmt($ts) {
        return gmdate('Y-m-d H:i:s', (int) $ts);
    }

    /* ---------- سرو فایل کلید IndexNow و صفحه‌ی کشف (Hub) ---------- */

    public static function serve() {
        if (is_admin()) return;
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
        $is_txt = (substr($uri, -4) === '.txt');
        $is_hub = isset($_GET['bp_hub']);
        if (!$is_txt && !$is_hub) return; // مسیر سریع: اکثر درخواست‌ها همین‌جا تمام می‌شوند

        $o = get_option(self::OPT, []);
        $key = isset($o['key']) ? (string) $o['key'] : '';
        if ($is_txt && $key !== '' && basename($uri) === $key . '.txt') {
            nocache_headers();
            header('Content-Type: text/plain; charset=utf-8');
            echo $key;
            exit;
        }
        if ($is_hub && !empty($o['hub']) && !empty($o['hub_token'])
            && hash_equals((string) $o['hub_token'], (string) wp_unslash($_GET['bp_hub']))) {
            self::render_hub();
            exit;
        }
    }

    private static function render_hub() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT pub_url, anchor_text, published_at FROM ' . BP_DB::t('radar') . " WHERE pub_url<>'' AND tech_status NOT IN ('gone') ORDER BY id DESC LIMIT 300");
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=900');
        echo '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="utf-8"><title>' . esc_html(get_bloginfo('name')) . '</title><meta name="robots" content="index,follow"></head><body><ul>';
        foreach ((array) $rows as $r) {
            echo '<li><a href="' . esc_url($r->pub_url) . '">' . esc_html($r->pub_url) . '</a></li>';
        }
        echo '</ul></body></html>';
    }

    /* ---------- کران ---------- */

    public static function cron() {
        if (!BP_Lock::acquire('radar', 600)) return;
        try {
            @set_time_limit(120);
            self::run(50);
        } catch (Throwable $e) {
            BP_Log::add('radar', 'خطا در رادار: ' . $e->getMessage(), 'error');
        }
        BP_Lock::release('radar');
    }

    /** لینک‌های تازه‌منتشرشده را وارد جدول رادار می‌کند؛ تعداد ردیف‌های جدید را برمی‌گرداند */
    private static function sync() {
        global $wpdb;
        $tp = BP_DB::t('plans');
        $tr = BP_DB::t('radar');
        $now = current_time('mysql');
        $n = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $tr (plan_id, campaign_id, pub_url, target_url, platform, anchor_type, anchor_text, published_at, created_at, next_check)
             SELECT p.id, p.campaign_id, p.pub_url, p.target_url, p.pub_platform, p.anchor_type, p.anchor_text, COALESCE(p.published_at, p.updated_at, p.created_at), %s, %s
             FROM $tp p LEFT JOIN $tr r ON r.plan_id = p.id
             WHERE p.pub_status='published' AND p.pub_url<>'' AND r.id IS NULL LIMIT 200",
            $now, $now
        ));
        return max(0, (int) $n);
    }

    public static function run($budget = 50) {
        global $wpdb;
        $tr = BP_DB::t('radar');
        $t0 = microtime(true);
        $new = self::sync();
        if ($new > 0) {
            BP_Log::add('radar', $new . ' لینک جدید وارد رادار شد.', 'info');
            self::ping_new();
        }
        $due = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tr WHERE next_check IS NOT NULL AND next_check <= %s ORDER BY next_check ASC LIMIT 8", current_time('mysql')
        ));
        $n = 0;
        foreach ((array) $due as $r) {
            if (microtime(true) - $t0 > $budget) break;
            self::process($r, false);
            $n++;
        }
        return $n;
    }

    /* ---------- پینگ IndexNow و صفحه‌ی کشف ---------- */

    /** فقط آدرس‌های هم‌هاستِ سایت خودتان ارسال می‌شوند (IndexNow برای هاست دیگر کلید را نمی‌پذیرد) */
    public static function indexnow(array $urls) {
        $o = self::ensure_key();
        $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $list = [];
        foreach ($urls as $u) {
            $h = strtolower((string) wp_parse_url($u, PHP_URL_HOST));
            if ($h === $host) $list[$u] = $u;
        }
        $list = array_slice(array_values($list), 0, 100);
        if (!$list) return new WP_Error('bp_radar', 'آدرس هم‌هاستی برای ارسال نبود.');
        $payload = ['host' => $host, 'key' => $o['key'], 'keyLocation' => home_url('/' . $o['key'] . '.txt'), 'urlList' => $list];
        $res = BP_Http::post('https://api.indexnow.org/indexnow', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => wp_json_encode($payload),
        ]);
        if (is_wp_error($res)) return $res;
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code === 200 || $code === 202) return count($list);
        return new WP_Error('bp_radar', 'IndexNow کد ' . $code . ' برگرداند' . ($code === 403 || $code === 422 ? ' (فایل کلید در دسترس نیست؛ دکمه‌ی «آزمایش فایل کلید» را بزنید).' : '.'));
    }

    private static function ping_new() {
        global $wpdb;
        $o = self::ensure_key();
        $tr = BP_DB::t('radar');
        $rows = $wpdb->get_results("SELECT id, target_url FROM $tr WHERE ping_at IS NULL LIMIT 200");
        if (!$rows) return;
        $ids = array_map(function ($r) { return (int) $r->id; }, $rows);
        if (!empty($o['indexnow'])) {
            $log = (array) get_option('bp_radar_ping_log', []);
            $now = time();
            foreach ($log as $u => $t) if ($now - (int) $t > 7 * DAY_IN_SECONDS) unset($log[$u]);
            $urls = [];
            if (!empty($o['hub'])) $urls[] = self::hub_url();
            foreach ($rows as $r) {
                if ($r->target_url !== '' && empty($log[$r->target_url])) $urls[] = $r->target_url;
            }
            $urls = array_values(array_unique($urls));
            if ($urls) {
                $res = self::indexnow($urls);
                if (is_wp_error($res)) {
                    BP_Log::add('radar', 'پینگ IndexNow: ' . $res->get_error_message(), 'warn');
                } else {
                    foreach ($urls as $u) $log[$u] = $now;
                    BP_Log::add('radar', 'پینگ IndexNow: ' . (int) $res . ' آدرس ارسال شد.', 'info');
                }
                update_option('bp_radar_ping_log', array_slice($log, -500, null, true), false);
            }
        }
        $wpdb->query("UPDATE $tr SET ping_at='" . esc_sql(current_time('mysql')) . "' WHERE id IN (" . implode(',', $ids) . ')');
    }

    /* ---------- بررسی فنی صفحه‌ی بکلینک ---------- */

    private static function norm_url($u) {
        $u = strtolower(trim(html_entity_decode((string) $u)));
        $u = preg_replace('~^https?://(www\.)?~', '', $u);
        return rtrim($u, '/');
    }

    private static function tech($url, $target) {
        $res = BP_Http::get($url, ['timeout' => 20, 'redirection' => 5, 'limit_response_size' => 900000]);
        if (is_wp_error($res)) return ['status' => 'error', 'note' => mb_substr($res->get_error_message(), 0, 180), 'link' => ''];
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code === 404 || $code === 410) return ['status' => 'gone', 'note' => 'HTTP ' . $code, 'link' => 'gone'];
        if ($code >= 400) return ['status' => 'error', 'note' => 'HTTP ' . $code, 'link' => ''];
        $body = (string) wp_remote_retrieve_body($res);

        $xrt = wp_remote_retrieve_header($res, 'x-robots-tag');
        if (is_array($xrt)) $xrt = implode(',', $xrt);
        $noindex = stripos((string) $xrt, 'noindex') !== false;
        $note = $noindex ? 'X-Robots-Tag: noindex' : '';
        if (!$noindex && preg_match_all('~<meta\b[^>]*>~i', $body, $mm)) {
            foreach ($mm[0] as $tag) {
                if (preg_match('~name\s*=\s*["\'](robots|googlebot|bingbot)["\']~i', $tag)
                    && preg_match('~content\s*=\s*["\']([^"\']*)["\']~i', $tag, $c) && stripos($c[1], 'noindex') !== false) {
                    $noindex = true;
                    $note = 'meta robots: noindex';
                    break;
                }
            }
        }
        $status = $noindex ? 'noindex' : 'ok';

        if (!$noindex && preg_match('~<link\b[^>]*rel\s*=\s*["\']canonical["\'][^>]*>~i', $body, $cl) && preg_match('~href\s*=\s*["\']([^"\']+)["\']~i', $cl[0], $ch)) {
            $ch_host = preg_replace('~^www\.~', '', strtolower((string) wp_parse_url(html_entity_decode($ch[1]), PHP_URL_HOST)));
            $pg_host = preg_replace('~^www\.~', '', strtolower((string) wp_parse_url($url, PHP_URL_HOST)));
            if ($ch_host !== '' && $ch_host !== $pg_host) {
                $status = 'canonical';
                $note = 'canonical به دامنه‌ی دیگر: ' . $ch_host;
            }
        }

        $link = 'missing';
        $want = self::norm_url($target);
        if (preg_match_all('~<a\s[^>]*>~i', $body, $m)) {
            foreach ($m[0] as $a) {
                if (!preg_match('~href\s*=\s*["\']([^"\']+)["\']~i', $a, $h)) continue;
                if (self::norm_url($h[1]) !== $want) continue;
                $link = preg_match('~rel\s*=\s*["\'][^"\']*(nofollow|ugc|sponsored)~i', $a) ? 'nofollow' : 'follow';
                break;
            }
        }
        return ['status' => $status, 'note' => $note, 'link' => $link];
    }

    /* ---------- بررسی ایندکس در Bing (تقریبی، کم‌تعداد و فاصله‌دار) ---------- */

    private static function cand_norm($s) {
        $s = strtolower(trim(html_entity_decode(wp_strip_all_tags((string) $s))));
        $s = str_replace(['›', '»', ' › ', ' '], ['/', '/', '/', ''], $s);
        $s = preg_replace('~^https?://(www\.)?~', '', $s);
        $s = preg_replace('~/{2,}~', '/', $s);
        return rtrim($s, '/');
    }

    private static function cand_match($cand, $needle) {
        $cand = self::cand_norm($cand);
        if ($cand === '') return false;
        $trunc = (strpos($cand, '…') !== false || strpos($cand, '...') !== false);
        $cand = rtrim(str_replace(['…', '...'], '', $cand), '/');
        if ($cand === $needle) return true;
        if ($trunc && strlen($cand) >= 12 && strpos($needle, $cand) === 0) return true;
        return false;
    }

    /** @return array ['state' => indexed|not_indexed|unknown, 'note' => string] */
    private static function engine_check($url) {
        $needle = self::norm_url(strtok($url, '?#'));
        $q = 'site:' . $needle;
        $res = BP_Http::get('https://www.bing.com/search?q=' . rawurlencode($q) . '&setlang=en-US&cc=US&count=10', [
            'timeout' => 15, 'headers' => ['Accept-Language' => 'en-US,en;q=0.8'], 'limit_response_size' => 700000,
        ]);
        if (is_wp_error($res)) return ['state' => 'unknown', 'note' => mb_substr($res->get_error_message(), 0, 150)];
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        if ($code === 429 || $code === 403 || stripos($body, 'captcha') !== false || stripos($body, 'unusual traffic') !== false) {
            return ['state' => 'unknown', 'note' => 'محدودیت موتور جستجو (کپچا/نرخ)'];
        }
        if ($code !== 200) return ['state' => 'unknown', 'note' => 'HTTP ' . $code];

        $blocks = [];
        if (preg_match_all('~<li\s+class="b_algo"[^>]*>(.*?)</li>~is', $body, $m)) $blocks = $m[1];
        foreach ($blocks as $b) {
            $cands = [];
            if (preg_match_all('~<cite[^>]*>(.*?)</cite>~is', $b, $cm)) $cands = array_merge($cands, $cm[1]);
            if (preg_match_all('~href="([^"]+)"~i', $b, $hm)) {
                foreach ($hm[1] as $h) {
                    $h = html_entity_decode($h);
                    if (preg_match('~[?&]u=a1([A-Za-z0-9_\-]+)~', $h, $u)) {
                        $d = base64_decode(strtr($u[1], '-_', '+/') . str_repeat('=', (4 - strlen($u[1]) % 4) % 4), true);
                        if ($d) $h = $d;
                    }
                    $cands[] = $h;
                }
            }
            foreach ($cands as $c) {
                if (self::cand_match($c, $needle)) return ['state' => 'indexed', 'note' => ''];
            }
        }
        if (strpos($body, 'b_no') !== false || preg_match('~There are no results|No results found~i', $body)) {
            return ['state' => 'not_indexed', 'note' => 'نتیجه‌ای در Bing نیست'];
        }
        if ($blocks) return ['state' => 'not_indexed', 'note' => 'نتایج دیگری آمد ولی این صفحه نبود'];
        return ['state' => 'unknown', 'note' => 'ساختار پاسخ ناشناخته'];
    }

    /* ---------- Google Search Console (Service Account) ---------- */

    private static function gsc_cred() {
        $o = get_option(self::OPT, []);
        $raw = BP_Settings::dec((string) ($o['gsc_json'] ?? ''));
        if ($raw === '') return null;
        $j = json_decode($raw, true);
        if (!is_array($j) || empty($j['client_email']) || empty($j['private_key'])) return null;
        return $j;
    }

    public static function gsc_ready(?array $o = null) {
        $o = $o ?: self::opts();
        return !empty($o['gsc']) && (string) $o['gsc_site'] !== '' && self::gsc_cred() !== null && function_exists('openssl_sign');
    }

    private static function b64u($s) {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function gsc_token() {
        $tok = get_transient('bp_gsc_tok');
        if ($tok) return $tok;
        $c = self::gsc_cred();
        if (!$c) return new WP_Error('bp_radar', 'کلید Service Account ذخیره نشده یا نامعتبر است.');
        $now = time();
        $head = self::b64u(wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = self::b64u(wp_json_encode([
            'iss' => $c['client_email'], 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3300,
        ]));
        $sig = '';
        if (!@openssl_sign($head . '.' . $claim, $sig, $c['private_key'], OPENSSL_ALGO_SHA256)) {
            return new WP_Error('bp_radar', 'امضای JWT ناموفق بود؛ private_key معتبر نیست.');
        }
        $res = BP_Http::post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body'    => ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $head . '.' . $claim . '.' . self::b64u($sig)],
        ]);
        if (is_wp_error($res)) return $res;
        $j = json_decode((string) wp_remote_retrieve_body($res), true);
        if (empty($j['access_token'])) {
            return new WP_Error('bp_radar', 'دریافت توکن گوگل ناموفق: ' . mb_substr((string) ($j['error_description'] ?? $j['error'] ?? 'پاسخ نامعتبر'), 0, 160));
        }
        set_transient('bp_gsc_tok', $j['access_token'], 3000);
        return $j['access_token'];
    }

    /** @return array|WP_Error ['clicks','impr','pos'] برای یک صفحه در بازه */
    private static function gsc_page($url, $from, $to) {
        $o = self::opts();
        $tok = self::gsc_token();
        if (is_wp_error($tok)) return $tok;
        $variants = [$url];
        $variants[] = substr($url, -1) === '/' ? rtrim($url, '/') : $url . '/';
        foreach ($variants as $v) {
            $res = BP_Http::post('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($o['gsc_site']) . '/searchAnalytics/query', [
                'timeout' => 25,
                'headers' => ['Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'startDate' => $from, 'endDate' => $to, 'dimensions' => ['page'], 'type' => 'web', 'rowLimit' => 1,
                    'dimensionFilterGroups' => [['filters' => [['dimension' => 'page', 'operator' => 'equals', 'expression' => $v]]]],
                ]),
            ]);
            if (is_wp_error($res)) return $res;
            $code = (int) wp_remote_retrieve_response_code($res);
            $j = json_decode((string) wp_remote_retrieve_body($res), true);
            if ($code === 401) delete_transient('bp_gsc_tok');
            if ($code >= 400) return new WP_Error('bp_radar', 'Search Console کد ' . $code . ': ' . mb_substr((string) ($j['error']['message'] ?? ''), 0, 160));
            if (!empty($j['rows'][0])) {
                $r = $j['rows'][0];
                return ['clicks' => (int) round($r['clicks'] ?? 0), 'impr' => (int) round($r['impressions'] ?? 0), 'pos' => round((float) ($r['position'] ?? 0), 2)];
            }
        }
        return ['clicks' => 0, 'impr' => 0, 'pos' => 0.0];
    }

    private static function eval_gsc($r) {
        global $wpdb;
        $pd = strtotime(gmdate('Y-m-d', self::ts($r->published_at)) . ' UTC');
        $today = strtotime(gmdate('Y-m-d', self::now_ts()) . ' UTC');
        $d = function ($ts) { return gmdate('Y-m-d', $ts); };
        $a_from = $pd + 3 * DAY_IN_SECONDS;
        $a_end = $pd + 30 * DAY_IN_SECONDS;
        $a_to = min($a_end, $today - 3 * DAY_IN_SECONDS);
        $a_days = (int) round(($a_to - $a_from) / DAY_IN_SECONDS) + 1;
        if ($a_days < 11) return new WP_Error('bp_radar', 'داده‌ی بعد از انتشار هنوز کافی نیست.');
        $b = self::gsc_page($r->target_url, $d($pd - 28 * DAY_IN_SECONDS), $d($pd - DAY_IN_SECONDS));
        if (is_wp_error($b)) return $b;
        $a = self::gsc_page($r->target_url, $d($a_from), $d($a_to));
        if (is_wp_error($a)) return $a;
        $shared = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . BP_DB::t('radar') . ' WHERE target_url=%s AND id<>%d AND published_at BETWEEN %s AND %s',
            $r->target_url, $r->id, self::fmt($pd - 28 * DAY_IN_SECONDS), self::fmt($a_end)
        ));
        return [
            'b_clicks' => $b['clicks'], 'b_impr' => $b['impr'], 'b_pos' => $b['pos'],
            'a_clicks' => $a['clicks'], 'a_impr' => $a['impr'], 'a_pos' => $a['pos'], 'a_days' => $a_days,
            'gsc_final' => ($a_to >= $a_end) ? 1 : 0, 'shared' => $shared, 'gsc_err' => '',
        ];
    }

    /** مقایسه‌ی قبل/بعد → [امتیاز ۰..۱۰۰, حکم]. شاخص تقریبی است، نه اثبات علّیت. */
    public static function judge(array $v) {
        $bd = 28;
        $ad = max(1, (int) $v['a_days']);
        if ((int) $v['b_impr'] < 15 && (int) $v['a_impr'] < 15) return [0, 'low'];
        $chg = function ($b, $a) {
            if ($b > 0) return ($a - $b) / $b * 100;
            return $a > 0 ? 100 : 0;
        };
        $di = max(-60, min(120, $chg($v['b_impr'] / $bd, $v['a_impr'] / $ad)));
        $dc = max(-60, min(120, $chg($v['b_clicks'] / $bd, $v['a_clicks'] / $ad)));
        $dp = ((float) $v['b_pos'] > 0 && (float) $v['a_pos'] > 0) ? max(-6, min(6, (float) $v['b_pos'] - (float) $v['a_pos'])) : 0;
        $score = (int) round(max(0, min(100, 50 + $di * 0.25 + $dc * 0.15 + $dp * 4)));
        return [$score, $score >= 65 ? 'effective' : ($score <= 35 ? 'negative' : 'neutral')];
    }

    /* ---------- پردازش یک ردیف ---------- */

    private static function finalize(array $c, $age) {
        if (in_array($c['tech_status'], ['gone', 'noindex'], true) || in_array($c['link_state'], ['missing', 'gone'], true)) return [0, 'ineffective'];
        if ($c['idx_status'] === 'not_indexed' && $age >= 10 && (int) $c['idx_checks'] >= 2) return [0, 'ineffective'];
        if ((int) $c['a_days'] > 0) return self::judge($c);
        return [0, 'pending'];
    }

    private static function next_due(array $c, $age, array $o, $now, $gsc_backoff = 0) {
        $pub = self::ts($c['published_at']);
        $day = DAY_IN_SECONDS;
        $cand = [];
        if ($age < 180 && $c['tech_status'] !== 'gone') {
            $tint = $age < 3 ? $day : ($age < 30 ? 7 * $day : 30 * $day);
            $cand[] = self::ts($c['tech_at']) + $tint;
        } elseif ($c['tech_status'] === 'gone' && $age < 180) {
            $cand[] = self::ts($c['tech_at']) + 7 * $day;
        }
        if (!empty($o['engine']) && $c['tech_status'] !== 'gone') {
            if ($c['idx_status'] === 'indexed') {
                $cand[] = self::ts($c['idx_at']) + 30 * $day;
            } else {
                $n = (int) $c['idx_checks'];
                if ($n < count(self::$idx_steps)) {
                    $t = $pub + self::$idx_steps[$n] * $day;
                    if ($c['idx_at']) $t = max($t, self::ts($c['idx_at']) + $day);
                    if (get_transient('bp_radar_engine_pause')) $t = max($t, $now + 3 * HOUR_IN_SECONDS);
                    $cand[] = $t;
                }
            }
        }
        if (self::gsc_ready($o) && !(int) $c['gsc_final']) {
            $t = $pub + 17 * $day;
            if ($c['gsc_at']) $t = max($t, self::ts($c['gsc_at']) + 7 * $day);
            if ($gsc_backoff) $t = max($t, $gsc_backoff);
            $cand[] = $t;
        }
        return $cand ? self::fmt(max($now, min($cand))) : null;
    }

    public static function process($r, $force = false) {
        global $wpdb;
        $tr = BP_DB::t('radar');
        $o = self::opts();
        $now = self::now_ts();
        $pub = $r->published_at ? self::ts($r->published_at) : $now;
        $age = max(0, ($now - $pub) / DAY_IN_SECONDS);
        $u = [];
        $backoff = 0;

        // ۱) بررسی فنی
        $tint = $age < 3 ? DAY_IN_SECONDS : ($age < 30 ? 7 * DAY_IN_SECONDS : 30 * DAY_IN_SECONDS);
        if ($force || !$r->tech_at || $now - self::ts($r->tech_at) >= $tint) {
            $t = self::tech($r->pub_url, $r->target_url);
            $u['tech_status'] = $t['status'];
            $u['tech_note'] = $t['note'];
            $u['tech_at'] = self::fmt($now);
            if ($t['link'] !== '') {
                $u['link_state'] = $t['link'];
                $wpdb->update(BP_DB::t('plans'), ['pub_check' => $t['link'], 'pub_checked_at' => current_time('mysql')], ['id' => $r->plan_id]);
            }
            if ($t['status'] !== $r->tech_status && in_array($t['status'], ['gone', 'noindex', 'canonical'], true)) {
                BP_Log::add('radar', 'مشکل فنی در صفحه‌ی بکلینک: ' . $r->pub_url . ' → ' . $t['status'] . ($t['note'] ? ' (' . $t['note'] . ')' : ''), 'warn', (int) $r->campaign_id);
            }
        }
        $cur = array_merge((array) $r, $u);

        // ۲) ایندکس (Bing)
        if (!empty($o['engine']) && $cur['tech_status'] !== 'gone') {
            $n = (int) $cur['idx_checks'];
            if ($cur['idx_status'] === 'indexed') {
                $due = $force || ($cur['idx_at'] && $now - self::ts($cur['idx_at']) >= 30 * DAY_IN_SECONDS);
            } else {
                $due = $force ? $n < 12 : ($n < count(self::$idx_steps) && $age >= self::$idx_steps[$n] && (!$cur['idx_at'] || $now - self::ts($cur['idx_at']) >= DAY_IN_SECONDS));
            }
            if ($due && ($force || !get_transient('bp_radar_engine_pause'))) {
                $e = self::engine_check($cur['pub_url']);
                if ($e['state'] === 'unknown') {
                    set_transient('bp_radar_engine_pause', 1, 3 * HOUR_IN_SECONDS);
                } else {
                    $u['idx_status'] = $e['state'];
                    $u['idx_checks'] = $n + 1;
                    $u['idx_at'] = self::fmt($now);
                    if ($e['state'] === 'indexed' && !$cur['idx_first']) $u['idx_first'] = self::fmt($now);
                    if ($e['state'] === 'indexed' && $cur['idx_status'] !== 'indexed') {
                        BP_Log::add('radar', 'ایندکس شد: ' . $cur['pub_url'], 'info', (int) $r->campaign_id);
                    }
                }
            }
        }
        $cur = array_merge($cur, $u);

        // ۳) اثر روی رتبه (Search Console)
        if (self::gsc_ready($o) && !(int) $cur['gsc_final'] && $age >= 17) {
            $due = $force || !$cur['gsc_at'] || $now - self::ts($cur['gsc_at']) >= 7 * DAY_IN_SECONDS;
            if ($due) {
                $g = self::eval_gsc($r);
                if (is_wp_error($g)) {
                    $u['gsc_err'] = mb_substr($g->get_error_message(), 0, 190);
                    $backoff = $now + 6 * HOUR_IN_SECONDS;
                } else {
                    $u = array_merge($u, $g);
                    $u['gsc_at'] = self::fmt($now);
                }
            }
        }
        $cur = array_merge($cur, $u);

        list($impact, $verdict) = self::finalize($cur, $age);
        $u['impact'] = $impact;
        $u['verdict'] = $verdict;
        $cur = array_merge($cur, $u);
        $u['next_check'] = self::next_due($cur, $age, $o, $now, $backoff);
        $wpdb->update($tr, $u, ['id' => $r->id]);
        delete_transient('bp_radar_fac');
        return array_merge((array) $r, $u);
    }

    /* ---------- آمار و یادگیری ---------- */

    /** کیفیت هر لینک ۰..۱ برای یادگیری */
    private static function quality($r) {
        switch ($r->verdict) {
            case 'ineffective': return 0.0;
            case 'effective':   return 1.0;
            case 'negative':    return 0.3;
        }
        if ($r->idx_status === 'indexed') return 0.7;
        return 0.5;
    }

    /** میانگین کیفیت به‌تفکیک ستون ($col = platform | anchor_type) برای ردیف‌های ≥ ۱۰ روزه */
    private static function group_quality($col) {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT ' . ($col === 'platform' ? 'platform' : 'anchor_type') . ' k, verdict, idx_status FROM ' . BP_DB::t('radar')
            . " WHERE published_at < '" . esc_sql(self::fmt(self::now_ts() - 10 * DAY_IN_SECONDS)) . "' AND tech_at IS NOT NULL");
        $g = [];
        foreach ((array) $rows as $r) {
            if ($r->k === '') continue;
            if (!isset($g[$r->k])) $g[$r->k] = ['n' => 0, 'sum' => 0.0];
            $g[$r->k]['n']++;
            $g[$r->k]['sum'] += self::quality($r);
        }
        foreach ($g as $k => $v) $g[$k]['q'] = $v['sum'] / max(1, $v['n']);
        return $g;
    }

    private static function factors() {
        $f = get_transient('bp_radar_fac');
        if (is_array($f)) return $f;
        $f = ['platform' => [], 'anchor' => []];
        foreach (self::group_quality('platform') as $k => $v) {
            if ($v['n'] >= 5) $f['platform'][$k] = max(0.5, min(1.8, 1.6 - 1.2 * $v['q'])); // کمتر = ترجیح بیشتر
        }
        foreach (self::group_quality('anchor') as $k => $v) {
            if ($v['n'] >= 8) {
                $m = max(0.7, min(1.3, 0.7 + 0.6 * $v['q']));
                if (in_array($k, ['exact', 'partial'], true)) $m = min(1.0, $m); // انکر بهینه‌شده را هرگز تشویق نمی‌کنیم
                $f['anchor'][$k] = $m;
            }
        }
        set_transient('bp_radar_fac', $f, 6 * HOUR_IN_SECONDS);
        return $f;
    }

    /** ضریب انتخاب پلتفرم برای اتوپایلوت (کمتر از ۱ = ترجیح بیشتر) */
    public static function factor($platform) {
        $o = get_option(self::OPT, []);
        if (isset($o['learn']) && !$o['learn']) return 1.0;
        $f = self::factors();
        return isset($f['platform'][$platform]) ? (float) $f['platform'][$platform] : 1.0;
    }

    /** تنظیم وزن انواع انکر بر اساس نتایج واقعی (فقط وقتی داده‌ی کافی هست) */
    public static function tune_weights(array $w) {
        $o = get_option(self::OPT, []);
        if (isset($o['learn']) && !$o['learn']) return $w;
        $f = self::factors();
        $orig = $w;
        foreach ($w as $k => $v) {
            if (isset($f['anchor'][$k])) $w[$k] = (int) round($v * $f['anchor'][$k]);
        }
        return array_sum($w) > 0 ? $w : $orig;
    }

    private static function overview() {
        global $wpdb;
        $tr = BP_DB::t('radar');
        $row = $wpdb->get_row("SELECT COUNT(*) total,
            SUM(tech_status='ok' AND link_state IN ('follow','nofollow')) alive,
            SUM(idx_status='indexed') indexed,
            SUM(verdict='effective') effective,
            SUM(verdict='ineffective') ineffective,
            SUM(link_state='follow') follow,
            AVG(CASE WHEN verdict IN ('effective','neutral','negative') THEN impact END) avg_impact
            FROM $tr", ARRAY_A);
        foreach (['total', 'alive', 'indexed', 'effective', 'ineffective', 'follow'] as $k) $row[$k] = (int) ($row[$k] ?? 0);
        return $row;
    }

    private static function weekly() {
        global $wpdb;
        $tr = BP_DB::t('radar');
        $now = self::now_ts();
        $from = self::fmt($now - 8 * 7 * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT published_at, idx_status, verdict FROM $tr WHERE published_at >= %s", $from));
        $b = array_fill(0, 8, ['p' => 0, 'i' => 0, 'e' => 0]);
        foreach ((array) $rows as $r) {
            $k = 7 - (int) floor(($now - self::ts($r->published_at)) / (7 * DAY_IN_SECONDS));
            if ($k < 0 || $k > 7) continue;
            $b[$k]['p']++;
            if ($r->idx_status === 'indexed') $b[$k]['i']++;
            if ($r->verdict === 'effective') $b[$k]['e']++;
        }
        return $b;
    }

    /* ---------- هندلرها ---------- */

    private static function guard() {
        if (!current_user_can('manage_options')) wp_die('دسترسی ندارید.');
        check_admin_referer('bp_action');
    }

    private static function back($msg) {
        wp_safe_redirect(add_query_arg(['page' => BP_Admin::SLUG, 'tab' => 'radar', 'bp_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    private static function nl($action, $args = []) {
        return wp_nonce_url(add_query_arg(array_merge(['action' => 'bp_' . $action], $args), admin_url('admin-post.php')), 'bp_action');
    }

    public static function h_radar_save() {
        self::guard();
        $o = self::ensure_key();
        foreach (['indexnow', 'hub', 'engine', 'learn', 'gsc'] as $k) $o[$k] = empty($_POST[$k]) ? 0 : 1;

        $site = isset($_POST['gsc_site']) ? trim(sanitize_text_field(wp_unslash($_POST['gsc_site']))) : '';
        if ($site !== '' && preg_match('~^https?://[^\s]+$~i', $site) && substr($site, -1) !== '/') $site .= '/';
        $o['gsc_site'] = preg_match('~^(sc-domain:[a-z0-9.\-]+|https?://[^\s]+/)$~i', $site) ? $site : '';

        $msg = 'تنظیمات رادار ذخیره شد.';
        $json = isset($_POST['gsc_json']) ? trim((string) wp_unslash($_POST['gsc_json'])) : '';
        if ($json !== '') {
            $j = json_decode($json, true);
            if (is_array($j) && !empty($j['client_email']) && !empty($j['private_key']) && strpos((string) $j['private_key'], 'PRIVATE KEY') !== false) {
                $o['gsc_json'] = BP_Settings::enc(wp_json_encode(['client_email' => (string) $j['client_email'], 'private_key' => (string) $j['private_key']]));
            } else {
                $msg = 'فایل JSON سرویس‌اکانت معتبر نبود؛ بقیه‌ی تنظیمات ذخیره شد.';
            }
        }
        if (!empty($_POST['clear_gsc_json'])) $o['gsc_json'] = '';
        self::save_opts($o);
        delete_transient('bp_gsc_tok');
        delete_transient('bp_radar_fac');
        delete_transient('bp_radar_engine_pause');
        BP_Cron::activate();
        self::back($msg);
    }

    public static function h_radar_check() {
        self::guard();
        global $wpdb;
        @set_time_limit(150);
        $id = (int) ($_REQUEST['id'] ?? 0);
        delete_transient('bp_radar_engine_pause');
        if ($id) {
            $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . BP_DB::t('radar') . ' WHERE id=%d', $id));
            if ($r) { self::process($r, true); self::back('لینک دوباره بررسی شد.'); }
            self::back('ردیف پیدا نشد.');
        }
        self::sync();
        $n = self::run(100);
        self::back($n . ' لینک سررسیده بررسی شد.');
    }

    public static function h_radar_ping() {
        self::guard();
        $o = self::ensure_key();
        $urls = [];
        if (!empty($o['hub'])) $urls[] = self::hub_url();
        global $wpdb;
        foreach ((array) $wpdb->get_col('SELECT DISTINCT target_url FROM ' . BP_DB::t('radar') . " WHERE target_url<>'' LIMIT 50") as $u) $urls[] = $u;
        $res = self::indexnow($urls);
        self::back(is_wp_error($res) ? $res->get_error_message() : $res . ' آدرس به IndexNow ارسال شد.');
    }

    public static function h_radar_test_key() {
        self::guard();
        $o = self::ensure_key();
        $url = home_url('/' . $o['key'] . '.txt');
        $res = BP_Http::get($url, ['timeout' => 15]);
        if (!is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) === 200 && trim((string) wp_remote_retrieve_body($res)) === $o['key']) {
            self::back('فایل کلید در دسترس است ✓ (' . $url . ')');
        }
        self::back('فایل کلید در دسترس نیست. اگر پیوند یکتا (Permalink) ساده است، در ریشه‌ی سایت فایلی به نام ' . $o['key'] . '.txt با همین کلید به‌عنوان محتوا بسازید.');
    }

    public static function h_radar_test_gsc() {
        self::guard();
        $o = self::opts();
        if (!self::gsc_ready($o)) self::back('اول کلید Service Account و آدرس پراپرتی را ذخیره کنید (و گزینه‌ی Search Console فعال باشد).');
        $tok = self::gsc_token();
        if (is_wp_error($tok)) self::back($tok->get_error_message());
        $res = BP_Http::get('https://www.googleapis.com/webmasters/v3/sites', ['timeout' => 20, 'headers' => ['Authorization' => 'Bearer ' . $tok]]);
        if (is_wp_error($res)) self::back($res->get_error_message());
        $j = json_decode((string) wp_remote_retrieve_body($res), true);
        $found = null;
        foreach ((array) ($j['siteEntry'] ?? []) as $s) {
            if (strcasecmp((string) $s['siteUrl'], $o['gsc_site']) === 0) $found = $s;
        }
        if ($found) self::back('اتصال برقرار است ✓ سطح دسترسی: ' . ($found['permissionLevel'] ?? '?'));
        $cnt = count((array) ($j['siteEntry'] ?? []));
        self::back('توکن گرفته شد ولی پراپرتی «' . $o['gsc_site'] . '» در دسترس این سرویس‌اکانت نیست (' . $cnt . ' پراپرتی دیده شد). ایمیل سرویس‌اکانت را در Search Console به‌عنوان کاربر اضافه کنید.');
    }

    public static function h_radar_export() {
        self::guard();
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . BP_DB::t('radar') . ' ORDER BY published_at DESC');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="radar-' . gmdate('Ymd-His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $f = fopen('php://output', 'w');
        fputcsv($f, ['published_at', 'platform', 'anchor_type', 'anchor', 'target_url', 'pub_url', 'tech', 'link', 'index', 'verdict', 'impact', 'b_impr', 'a_impr', 'b_pos', 'a_pos', 'shared']);
        foreach ($rows as $r) {
            fputcsv($f, [$r->published_at, $r->platform, $r->anchor_type, $r->anchor_text, $r->target_url, $r->pub_url, $r->tech_status, $r->link_state, $r->idx_status, $r->verdict, $r->impact, $r->b_impr, $r->a_impr, $r->b_pos, $r->a_pos, $r->shared]);
        }
        fclose($f);
        exit;
    }

    /* ---------- رابط کاربری ---------- */

    private static function chip($text, $color, $title = '') {
        return '<span class="bpr-chip" style="background:' . esc_attr($color) . '"' . ($title !== '' ? ' title="' . esc_attr($title) . '"' : '') . '>' . esc_html($text) . '</span>';
    }

    private static function pct($a, $b) {
        return $b > 0 ? round($a / $b * 100) : 0;
    }

    private static function delta($b, $a, $days_a) {
        $bi = $b / 28;
        $ai = $a / max(1, $days_a);
        if ($bi <= 0) return $ai > 0 ? 'جدید' : '—';
        $p = round(($ai - $bi) / $bi * 100);
        return ($p > 0 ? '+' : '') . $p . '٪';
    }

    public static function tab() {
        global $wpdb;
        $o = self::ensure_key();
        $tr = BP_DB::t('radar');
        self::sync();
        $ov = self::overview();
        $wk = self::weekly();
        $plats = BP_Publisher::platforms();
        $gq_p = self::group_quality('platform');
        $gq_a = self::group_quality('anchor');
        $fac = self::factors();
        $total = $ov['total'];
        $gsc_ok = self::gsc_ready($o);
        $key_url = home_url('/' . $o['key'] . '.txt');
        ?>
        <style>
            .bpr-chip{display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;color:#fff;margin:1px 0}
            .bpr-hero{background:linear-gradient(135deg,#0f2742,#1d4e89);color:#fff;border-radius:8px;padding:16px 22px;margin:12px 0}
            .bpr-hero h2{color:#fff;margin:0 0 4px}.bpr-hero p{margin:0;opacity:.85}
            .bpr-funnel .row{display:flex;align-items:center;gap:10px;margin:7px 0}
            .bpr-funnel .lbl{width:170px;font-weight:600}
            .bpr-funnel .bar{flex:1;background:#eef1f5;border-radius:5px;height:26px;position:relative;overflow:hidden}
            .bpr-funnel .fill{height:100%;border-radius:5px;transition:width .4s}
            .bpr-funnel .val{position:absolute;inset:0;display:flex;align-items:center;padding:0 10px;font-size:12px;color:#111;font-weight:600}
            .bpr-cols{display:flex;gap:14px;flex-wrap:wrap}.bpr-cols>div{flex:1;min-width:300px}
            .bpr-dim{color:#777;font-size:11px}
            .bpr-row-bad td{background:#fff5f5!important}
        </style>

        <div class="bpr-hero">
            <h2>رادار بکلینک</h2>
            <p>مسیر هر لینک: منتشر شد ← صفحه سالم است ← ایندکس شد ← روی نمایش و رتبه اثر گذاشت. لینکی که ایندکس نشود یا اثر نکند، شناسایی می‌شود و اتوپایلوت از آن یاد می‌گیرد.</p>
        </div>

        <div class="bp-grid">
            <?php foreach ([
                ['کل لینک‌های ردیابی‌شده', $total, '#1d4e89'],
                ['زنده و قابل‌ایندکس', $ov['alive'], '#1a7f37'],
                ['ایندکس‌شده (Bing)', $ov['indexed'], '#0969da'],
                ['مؤثر بر رتبه', $ov['effective'], '#8250df'],
                ['بی‌اثر/مشکل‌دار', $ov['ineffective'], '#b32d2e'],
                ['میانگین امتیاز اثر', $ov['avg_impact'] !== null ? round((float) $ov['avg_impact']) : '—', '#b26200'],
            ] as $s) printf('<div class="bp-stat" style="border-top:3px solid %s"><b>%s</b>%s</div>', esc_attr($s[2]), esc_html($s[1]), esc_html($s[0])); ?>
        </div>

        <div class="bpr-cols">
            <div class="bp-card bpr-funnel">
                <h3>قیف عملکرد لینک‌ها</h3>
                <?php if (!$total) : echo '<p>هنوز لینکی منتشر نشده؛ بعد از اولین انتشار اتوپایلوت، اینجا پر می‌شود.</p>'; else :
                    foreach ([
                        ['منتشر شد', $total, '#8c8f94'],
                        ['زنده و قابل‌ایندکس', $ov['alive'], '#1a7f37'],
                        ['ایندکس تأییدشده', $ov['indexed'], '#0969da'],
                        ['مؤثر بر رتبه', $ov['effective'], '#8250df'],
                    ] as $st) :
                        $w = self::pct($st[1], $total); ?>
                        <div class="row"><div class="lbl"><?php echo esc_html($st[0]); ?></div>
                            <div class="bar"><div class="fill" style="width:<?php echo (int) $w; ?>%;background:<?php echo esc_attr($st[2]); ?>;opacity:.35"></div>
                                <div class="val"><?php echo (int) $st[1]; ?> &nbsp;(<?php echo (int) $w; ?>٪)</div></div></div>
                    <?php endforeach; endif; ?>
                <p class="bpr-dim">لینک تازه‌منتشرشده طبیعتاً چند روز طول می‌کشد تا ایندکس شود؛ درصدها با گذشت زمان بالا می‌روند.</p>
            </div>

            <div class="bp-card">
                <h3>۸ هفته‌ی اخیر</h3>
                <?php
                $mx = max(1, max(array_map(function ($x) { return $x['p']; }, $wk)));
                $W = 520; $H = 170; $bw = 14; $gap = 62;
                echo '<svg viewBox="0 0 ' . $W . ' ' . ($H + 28) . '" style="width:100%;max-width:560px;height:auto" role="img" aria-label="نمودار هفتگی">';
                echo '<line x1="10" y1="' . $H . '" x2="' . ($W - 5) . '" y2="' . $H . '" stroke="#ccd0d4"/>';
                foreach ($wk as $i => $b) {
                    $x = 20 + $i * $gap;
                    foreach ([['p', '#9aa5b1', 0], ['i', '#0969da', 1], ['e', '#8250df', 2]] as $s) {
                        $h = (int) round($b[$s[0]] / $mx * ($H - 20));
                        echo '<rect x="' . ($x + $s[2] * ($bw + 2)) . '" y="' . ($H - $h) . '" width="' . $bw . '" height="' . $h . '" rx="2" fill="' . $s[1] . '"><title>' . esc_attr($b[$s[0]]) . '</title></rect>';
                    }
                    echo '<text x="' . ($x + 24) . '" y="' . ($H + 16) . '" font-size="11" text-anchor="middle" fill="#666">' . ($i === 7 ? 'این هفته' : (7 - $i) . ' هفته قبل') . '</text>';
                }
                echo '</svg>';
                ?>
                <p class="bpr-dim"><span style="color:#9aa5b1">■</span> منتشرشده &nbsp; <span style="color:#0969da">■</span> ایندکس‌شده &nbsp; <span style="color:#8250df">■</span> مؤثر</p>
            </div>
        </div>

        <div class="bpr-cols">
            <div class="bp-card">
                <h3>کیفیت پلتفرم‌ها <small class="bpr-dim">(لینک‌های ≥ ۱۰ روزه)</small></h3>
                <table class="widefat striped"><thead><tr><th>پلتفرم</th><th>لینک</th><th>کیفیت</th><th>ضریب انتخاب</th></tr></thead><tbody>
                <?php if (!$gq_p) echo '<tr><td colspan="4">هنوز داده‌ی کافی نیست.</td></tr>';
                foreach ($gq_p as $k => $v) :
                    $q = (int) round($v['q'] * 100); $f = $fac['platform'][$k] ?? null; ?>
                    <tr><td><?php echo esc_html($plats[$k]['label'] ?? $k); ?></td><td><?php echo (int) $v['n']; ?></td>
                        <td><b style="color:<?php echo $q >= 60 ? '#1a7f37' : ($q >= 40 ? '#b26200' : '#b32d2e'); ?>"><?php echo $q; ?>٪</b></td>
                        <td><?php echo $f === null ? '<span class="bpr-dim">نمونه کم</span>' : ($f < 0.95 ? '⬆ ' : ($f > 1.05 ? '⬇ ' : '= ')) . esc_html(number_format($f, 2)); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
            <div class="bp-card">
                <h3>کیفیت انواع انکر</h3>
                <table class="widefat striped"><thead><tr><th>نوع انکر</th><th>لینک</th><th>کیفیت</th><th>ضریب وزن</th></tr></thead><tbody>
                <?php if (!$gq_a) echo '<tr><td colspan="4">هنوز داده‌ی کافی نیست.</td></tr>';
                foreach ($gq_a as $k => $v) :
                    $q = (int) round($v['q'] * 100); $f = $fac['anchor'][$k] ?? null; ?>
                    <tr><td><?php echo esc_html($k); ?></td><td><?php echo (int) $v['n']; ?></td><td><b><?php echo $q; ?>٪</b></td>
                        <td><?php echo $f === null ? '<span class="bpr-dim">نمونه کم</span>' : esc_html(number_format($f, 2)); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <p class="bpr-dim">یادگیری، انکرهای exact/partial را هرگز بیشتر از وزن تنظیمی شما نمی‌کند (برای جلوگیری از بهینه‌سازی بیش‌ازحد).</p>
            </div>
        </div>

        <div class="bp-card">
            <h3>لینک‌ها <a class="button button-small" style="margin-right:10px" href="<?php echo esc_url(self::nl('radar_check')); ?>">بررسی لینک‌های سررسیده</a>
                <a class="button button-small" href="<?php echo esc_url(self::nl('radar_export')); ?>">خروجی CSV</a></h3>
            <?php $rows = $wpdb->get_results("SELECT * FROM $tr ORDER BY published_at DESC, id DESC LIMIT 150"); ?>
            <div style="overflow:auto"><table class="widefat striped">
                <thead><tr><th>تاریخ</th><th>پلتفرم</th><th>انکر</th><th>صفحه‌ی هدف</th><th>صفحه‌ی بکلینک</th><th>فنی</th><th>لینک</th><th>ایندکس</th><th>اثر روی رتبه</th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows) echo '<tr><td colspan="10">هنوز لینکی نیست.</td></tr>';
                $tc = ['ok' => ['سالم', '#1a7f37'], 'noindex' => ['noindex', '#b32d2e'], 'canonical' => ['canonical خارجی', '#b26200'], 'gone' => ['حذف شده', '#b32d2e'], 'error' => ['خطای دسترسی', '#8c8f94'], '' => ['بررسی‌نشده', '#8c8f94']];
                $lc = ['follow' => ['follow', '#1a7f37'], 'nofollow' => ['nofollow', '#b26200'], 'missing' => ['لینک نیست', '#b32d2e'], 'gone' => ['حذف شده', '#b32d2e'], '' => ['—', '#8c8f94']];
                $ic = ['indexed' => ['ایندکس ✓', '#0969da'], 'not_indexed' => ['ایندکس نشده', '#b32d2e'], 'pending' => ['در انتظار', '#8c8f94'], 'unknown' => ['نامشخص', '#8c8f94']];
                $now = self::now_ts();
                foreach ($rows as $r) :
                    $age = (int) floor(($now - self::ts($r->published_at)) / DAY_IN_SECONDS);
                    $t = $tc[$r->tech_status] ?? $tc['']; $l = $lc[$r->link_state] ?? $lc['']; $i = $ic[$r->idx_status] ?? $ic['pending'];
                    ?>
                    <tr class="<?php echo $r->verdict === 'ineffective' ? 'bpr-row-bad' : ''; ?>">
                        <td><?php echo esc_html(mysql2date('m/d', $r->published_at)); ?><br><span class="bpr-dim"><?php echo (int) $age; ?> روز</span></td>
                        <td><?php echo esc_html($plats[$r->platform]['label'] ?? $r->platform); ?></td>
                        <td><?php echo esc_html($r->anchor_text); ?><br><span class="bpr-dim"><?php echo esc_html($r->anchor_type); ?></span></td>
                        <td style="direction:ltr;max-width:170px;word-break:break-all"><a href="<?php echo esc_url($r->target_url); ?>" target="_blank" rel="noopener"><?php echo esc_html(preg_replace('~^https?://(www\.)?~', '', $r->target_url)); ?></a></td>
                        <td style="direction:ltr;max-width:210px;word-break:break-all"><a href="<?php echo esc_url($r->pub_url); ?>" target="_blank" rel="noopener"><?php echo esc_html(preg_replace('~^https?://~', '', $r->pub_url)); ?></a></td>
                        <td><?php echo self::chip($t[0], $t[1], (string) $r->tech_note); ?></td>
                        <td><?php echo self::chip($l[0], $l[1]); ?></td>
                        <td><?php echo self::chip($i[0], $i[1]); ?>
                            <br><a class="bpr-dim" href="<?php echo esc_url('https://www.google.com/search?q=' . rawurlencode('site:' . $r->pub_url)); ?>" target="_blank" rel="noopener">بررسی در Google ↗</a></td>
                        <td><?php
                            if ((int) $r->a_days > 0 && $r->verdict !== 'ineffective') {
                                $vc = ['effective' => ['مؤثر', '#8250df'], 'neutral' => ['خنثی', '#8c8f94'], 'negative' => ['منفی', '#b32d2e'], 'low' => ['داده کم', '#8c8f94']][$r->verdict] ?? ['—', '#8c8f94'];
                                echo self::chip($vc[0] . ($r->verdict !== 'low' ? ' · ' . (int) $r->impact : ''), $vc[1]);
                                echo '<br><span class="bpr-dim">نمایش ' . esc_html(self::delta((int) $r->b_impr, (int) $r->a_impr, (int) $r->a_days)) . ' · رتبه ';
                                echo ((float) $r->b_pos > 0 && (float) $r->a_pos > 0) ? esc_html(($r->b_pos - $r->a_pos > 0 ? '+' : '') . round($r->b_pos - $r->a_pos, 1)) : '—';
                                echo $r->gsc_final ? '' : ' · موقت';
                                echo ((int) $r->shared > 0) ? ' · اثر مشترک با ' . (int) $r->shared . ' لینک دیگر' : '';
                                echo '</span>';
                            } elseif ($r->verdict === 'ineffective') {
                                echo self::chip('بی‌اثر', '#b32d2e');
                            } else {
                                echo '<span class="bpr-dim">' . ($age < 17 ? 'بعد از ۱۷ روز' : ($gsc_ok ? ($r->gsc_err ? esc_html($r->gsc_err) : 'در انتظار') : 'Search Console وصل نیست')) . '</span>';
                            } ?></td>
                        <td><a class="button button-small" href="<?php echo esc_url(self::nl('radar_check', ['id' => $r->id])); ?>">بررسی</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <p class="bpr-dim">«اثر روی رتبه» مقایسه‌ی ۲۸ روز قبل از انتشار با بعد از آن برای صفحه‌ی هدف است؛ یک شاخص کمکی است نه اثبات علّیت (تغییر محتوا، فصل و الگوریتم هم اثر دارند). «ایندکس» از نتایج عمومی Bing تشخیص داده می‌شود؛ برای Google از لینک «بررسی در Google» استفاده کنید.</p>
        </div>

        <div class="bp-card">
            <h3>تنظیمات رادار</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bp_action'); ?>
                <input type="hidden" name="action" value="bp_radar_save">
                <table class="form-table" role="presentation">
                    <tr><th>بررسی ایندکس (Bing)</th><td><label><input type="checkbox" name="engine" value="1" <?php checked(!empty($o['engine'])); ?>> فعال (چند بار و با فاصله‌ی روزها؛ در صورت محدودیت موتور جستجو خودکار ۳ ساعت مکث می‌کند)</label><?php BP_Guide::box('r_engine'); ?></td></tr>
                    <tr><th>پینگ IndexNow</th><td><label><input type="checkbox" name="indexnow" value="1" <?php checked(!empty($o['indexnow'])); ?>> بعد از انتشار، صفحه‌ی هدف (روی همین سایت) را به Bing/Yandex و… اعلام کن</label>
                        <p class="description">IndexNow فقط برای آدرس‌های همین سایت کار می‌کند، نه Telegra.ph و مانند آن. کلید: <code style="direction:ltr;display:inline-block"><?php echo esc_html($o['key']); ?></code>
                        — <a href="<?php echo esc_url(self::nl('radar_test_key')); ?>">آزمایش فایل کلید</a> — <a href="<?php echo esc_url(self::nl('radar_ping')); ?>">ارسال دستی</a></p><?php BP_Guide::box('r_indexnow'); ?></td></tr>
                    <tr><th>صفحه‌ی کشف (Hub)</th><td><label><input type="checkbox" name="hub" value="1" <?php checked(!empty($o['hub'])); ?>> یک صفحه‌ی مخفی با لینک به بکلینک‌های منتشرشده بساز و به IndexNow بده تا خزنده‌ها آن‌ها را کشف کنند</label>
                        <p class="description" style="direction:ltr;text-align:left"><?php echo esc_html(self::hub_url()); ?></p>
                        <p class="description">این صفحه عمومی و قابل‌خزیدن است (آدرسش حدس‌زدنی نیست ولی مخفی‌کاری مطلق هم نیست). اگر نمی‌خواهید ردپای استراتژی لینک‌سازی‌تان دیده شود، خاموش بگذارید.</p><?php BP_Guide::box('r_hub'); ?></td></tr>
                    <tr><th>یادگیری خودکار</th><td><label><input type="checkbox" name="learn" value="1" <?php checked(!empty($o['learn'])); ?>> اتوپایلوت پلتفرم‌های موفق را بیشتر انتخاب کند و وزن انکرهای بی‌اثر را کم کند (حداقل ۵ لینک ≥ ۱۰ روزه برای هر پلتفرم)</label><?php BP_Guide::box('r_learn'); ?></td></tr>
                    <tr><th>Google Search Console</th><td>
                        <label><input type="checkbox" name="gsc" value="1" <?php checked(!empty($o['gsc'])); ?>> سنجش اثر روی نمایش/کلیک/رتبه</label><br>
                        <input type="text" name="gsc_site" class="regular-text" style="direction:ltr" placeholder="sc-domain:example.com  یا  https://example.com/" value="<?php echo esc_attr($o['gsc_site'] ?: home_url('/')); ?>">
                        <p class="description">آدرس پراپرتی دقیقاً همان‌طور که در Search Console هست.</p>
                        <textarea name="gsc_json" rows="4" class="large-text" style="direction:ltr;font-family:monospace" placeholder="<?php echo self::gsc_cred() ? '✓ کلید ذخیره شده (' . esc_attr(self::gsc_cred()['client_email']) . ') — برای تعویض، JSON جدید را اینجا بچسبانید' : 'محتوای فایل JSON سرویس‌اکانت را اینجا بچسبانید'; ?>"></textarea>
                        <?php if (self::gsc_cred()) : ?><label><input type="checkbox" name="clear_gsc_json" value="1"> حذف کلید ذخیره‌شده</label> — <a href="<?php echo esc_url(self::nl('radar_test_gsc')); ?>">آزمایش اتصال</a><?php endif; ?>
                        <details style="margin-top:6px"><summary style="cursor:pointer;color:#2271b1">راهنمای اتصال (۴ مرحله)</summary>
                            <ol style="line-height:1.9">
                                <li>در Google Cloud Console یک پروژه بسازید و «Search Console API» را فعال کنید.</li>
                                <li>یک Service Account بسازید و برایش کلید JSON دریافت کنید.</li>
                                <li>در Search Console ← تنظیمات ← کاربران، ایمیل سرویس‌اکانت (client_email) را با سطح «Restricted» اضافه کنید.</li>
                                <li>محتوای JSON را اینجا بچسبانید و «آزمایش اتصال» را بزنید. کلید به‌صورت رمزشده ذخیره می‌شود.</li>
                            </ol>
                            <p class="description">اگر گوگل از هاست شما در دسترس نیست، پروکسی را در تب «تنظیمات» وارد کنید.</p></details><?php BP_Guide::box('r_gsc'); ?>
                    </td></tr>
                </table>
                <p><button class="button button-primary">ذخیره تنظیمات رادار</button></p>
            </form>
        </div>
        <?php
    }
}

/* ============================================================
 * Bootstrap
 * ============================================================ */

/** حذف داده‌ها هنگام پاک‌کردن پلاگین (جایگزین uninstall.php) */
function bp_uninstall() {
    $opt = get_option('bp_settings', []);
    if (!empty($opt['keep_data'])) return;
    global $wpdb;
    foreach (['sources', 'domains', 'campaigns', 'plans', 'logs', 'pillars', 'radar'] as $t) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}bp_$t");
    }
    delete_option('bp_ai_usage');
    delete_option('bp_settings');
    delete_option('bp_db_version');
    delete_option('bp_bootstrapped');
    delete_option('bp_digest_last');
    delete_option('bp_site_scan');
    delete_option('bp_site_scan_next');
    delete_option('bp_dismissed_pillars');
    delete_option('bp_auto');
    delete_option('bp_radar');
    delete_option('bp_radar_ping_log');
    delete_option('bp_seed_v15');
    delete_option('bp_auto_next');
    delete_option('bp_telegraph_token');
    delete_option('bp_auto_started');
    delete_option('bp_recent_styles');
    delete_option('bp_telegraph_token');
}

register_activation_hook(__FILE__, function () {
    BP_DB::install();
    BP_Cron::activate();
    BP_Cron::bootstrap();
});
register_deactivation_hook(__FILE__, ['BP_Cron', 'deactivate']);
register_uninstall_hook(__FILE__, 'bp_uninstall');

add_action('plugins_loaded', function () {
    BP_DB::maybe_upgrade();
    BP_Cron::init();
    BP_Radar::init_front();
    if (is_admin()) {
        BP_Admin::init();
        BP_Admin_Agent::init();
        BP_Pillars::init();
        BP_Auto::init();
        BP_Radar::init_admin();
    }
});
