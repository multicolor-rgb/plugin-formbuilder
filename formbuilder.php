<?php
 
# Prevent direct access
if (!defined('IN_GS')) die('You cannot load this file directly!');

# Załaduj autoloader Composera jeśli istnieje
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

# Get correct id for plugin
$thisfile = basename(__FILE__, ".php");

# Constants
define('FORMBUILDER_DB', GSDATAOTHERPATH . 'formbuilder.db');
define('FORMBUILDER_UPLOADS', GSDATAOTHERPATH . 'formbuilder_uploads/');

# Load language file
i18n_merge('formbuilder') | i18n_merge('formbuilder', 'LANG');

# Register plugin
register_plugin(
    $thisfile,
    i18n_r('formbuilder/PLUGIN_NAME'),
    '1.0',
    'multicolor',
    'https://getsimple-ce.ovh/donate',
    i18n_r('formbuilder/PLUGIN_DESC'),
    'plugins',
    'formbuilder_main'
);

# Add link in plugins sidebar
add_action('plugins-sidebar', 'createSideMenu', array($thisfile, i18n_r('formbuilder/PLUGIN_NAME').' 📝'));

# Initialize on common
add_action('common', 'formbuilder_init');

# Content filter for shortcode
add_filter('content', 'formbuilder_shortcode');

/**
 * Initialize - Create DB with new mail_method column
 */
function formbuilder_init() {
    // Start session early
    if (session_status() == PHP_SESSION_NONE) {
        @session_start();
    }
    
    if (!file_exists(FORMBUILDER_DB)) {
        $db = new SQLite3(FORMBUILDER_DB);
        
        $db->exec('CREATE TABLE IF NOT EXISTS forms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            title TEXT,
            description TEXT,
            submit_text TEXT DEFAULT "Submit",
            success_msg TEXT DEFAULT "Thank you!",
            enable_captcha INTEGER DEFAULT 0,
            captcha_site TEXT,
            captcha_secret TEXT,
            email_to TEXT,
            redirect_url TEXT,
            mail_method TEXT DEFAULT "mailto",
            smtp_host TEXT,
            smtp_port INTEGER DEFAULT 587,
            smtp_username TEXT,
            smtp_password TEXT,
            smtp_secure TEXT DEFAULT "tls",
            smtp_from_email TEXT,
            smtp_from_name TEXT,
            created DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        
        $db->exec('CREATE TABLE IF NOT EXISTS form_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            name TEXT NOT NULL,
            label TEXT NOT NULL,
            placeholder TEXT,
            options TEXT,
            required INTEGER DEFAULT 0,
            field_order INTEGER DEFAULT 0,
            file_accept TEXT,
            file_max_size INTEGER DEFAULT 5
        )');
        
        $db->exec('CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_id INTEGER NOT NULL,
            data TEXT NOT NULL,
            ip TEXT,
            created DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        
        $db->close();
    } else {
        // Update existing database schema
        $db = new SQLite3(FORMBUILDER_DB);
        
        // Check if mail_method column exists
        $result = $db->query("PRAGMA table_info(forms)");
        $columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        
        // Add new columns if they don't exist
        if (!in_array('mail_method', $columns)) {
            $db->exec('ALTER TABLE forms ADD COLUMN mail_method TEXT DEFAULT "mailto"');
            $db->exec('ALTER TABLE forms ADD COLUMN smtp_host TEXT');
            $db->exec('ALTER TABLE forms ADD COLUMN smtp_port INTEGER DEFAULT 587');
            $db->exec('ALTER TABLE forms ADD COLUMN smtp_username TEXT');
            $db->exec('ALTER TABLE forms ADD COLUMN smtp_password TEXT');
            $db->exec('ALTER TABLE forms ADD COLUMN smtp_secure TEXT DEFAULT "tls"');
            $db->exec('ALTER TABLE forms ADD COLUMN smtp_from_email TEXT');
            $db->exec('ALTER TABLE forms ADD COLUMN smtp_from_name TEXT');
        }
        
        $db->close();
    }
    
    if (!file_exists(FORMBUILDER_UPLOADS)) {
        mkdir(FORMBUILDER_UPLOADS, 0755, true);
        file_put_contents(FORMBUILDER_UPLOADS . '.htaccess', "php_flag engine off\nOptions -Indexes\nDeny from all");
    }
}

function formbuilder_main() {
    global $SITEURL;
    
    $thisfile = basename(__FILE__, ".php");
    $action = isset($_GET['do']) ? $_GET['do'] : 'list';
    $fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 0;
    
    echo '<style>
    .fb-admin-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }
    .fb-admin-header h3 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 600;
    }
    .fb-admin-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 14px;
    }
    .fb-btn {
        display: inline-block;
        padding: 12px 24px;
        background: #667eea;
        color: white !important;
        text-decoration: none !important;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 14px;
        margin-right: 8px;
    }
    .fb-btn:hover {
        background: #5568d3;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    .fb-btn-success {
        background: #10b981;
    }
    .fb-btn-success:hover {
        background: #059669;
    }
    .fb-btn-warning {
        background: #f59e0b;
    }
    .fb-btn-warning:hover {
        background: #d97706;
    }
    .fb-btn-danger {
        background: #ef4444;
        padding: 8px 16px;
        font-size: 13px;
    }
    .fb-btn-danger:hover {
        background: #dc2626;
    }
    .fb-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .fb-card-header {
        background: #f8fafc;
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .fb-card-header h4 {
        margin: 0;
        font-size: 18px;
        color: #1e293b;
    }
    .fb-card-body {
        padding: 25px;
    }
    .fb-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .fb-table thead {
        background: #f8fafc;
    }
    .fb-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .fb-table td {
        padding: 15px;
        border-top: 1px solid #e2e8f0;
        color: #334155;
    }
    .fb-table tr:hover {
        background: #f8fafc;
    }
    .fb-badge {
        display: inline-block;
        padding: 4px 12px;
        background: #e0e7ff;
        color: #4f46e5;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .fb-badge-success {
        background: #d1fae5;
        color: #065f46;
    }
    .fb-badge-warning {
        background: #fef3c7;
        color: #92400e;
    }
    .fb-field {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        padding-right: 120px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        position: relative;
    }
    .fb-field:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
    .fb-field h5 {
        margin: 0 0 15px 0;
        color: #1e293b;
        font-size: 16px;
        font-weight: 600;
    }
    .fb-field label {
        display: block;
        margin: 12px 0 6px 0;
        font-weight: 500;
        color: #475569;
        font-size: 14px;
    }
    .fb-field input[type="text"],
    .fb-field input[type="number"],
    .fb-field input[type="password"],
    .fb-field select,
    .fb-field textarea {
        width: 100%;
        max-width: 100%;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        font-family: inherit;
        box-sizing: border-box;
    }
    .fb-field input[type="text"]:focus,
    .fb-field input[type="number"]:focus,
    .fb-field input[type="password"]:focus,
    .fb-field select:focus,
    .fb-field textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .fb-field-remove {
        position: absolute;
        top: 20px;
        right: 20px;
        background: #ef4444;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .fb-field-remove:hover {
        background: #dc2626;
        transform: scale(1.05);
    }
    .fb-file-options {
        display: none;
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
    }
    .fb-file-options.active {
        display: block;
    }
    .fb-smtp-options {
        display: none;
        background: #f8fafc;
        padding: 20px;
        border-radius: 8px;
        margin-top: 15px;
    }
    .fb-smtp-options.active {
        display: block;
    }
    .fb-info-box {
        background: linear-gradient(135deg, #e0e7ff 0%, #e0f2fe 100%);
        border-left: 4px solid #667eea;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    .fb-info-box h4 {
        margin: 0 0 12px 0;
        color: #4f46e5;
        font-size: 16px;
    }
    .fb-info-box p {
        margin: 8px 0;
        color: #475569;
        font-size: 14px;
    }
    .fb-info-box code {
        background: white;
        padding: 4px 8px;
        border-radius: 4px;
        color: #dc2626;
        font-size: 13px;
    }
    .fb-success-msg {
        background: #d1fae5;
        border-left: 4px solid #10b981;
        color: #065f46;
        padding: 16px 20px;
        border-radius: 8px;
        margin: 20px 0;
        font-weight: 500;
    }
    .fb-form-group {
        margin-bottom: 20px;
    }
    .fb-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #334155;
        font-size: 14px;
    }
    .fb-form-group input.text,
    .fb-form-group textarea.text,
    .fb-form-group select {
        width: 100%;
        max-width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }
    .fb-form-group input.text:focus,
    .fb-form-group textarea.text:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .fb-checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 400;
        cursor: pointer;
    }
    .fb-checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .fb-radio-group {
        display: flex;
        gap: 20px;
        margin-top: 10px;
    }
    .fb-radio-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    
    .fb-field {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    padding-right: 120px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    position: relative;
    cursor: grab; /* Dodane */
}
.fb-field:active {
    cursor: grabbing; /* Dodane */
}
.fb-field.dragging {
    opacity: 0.5;
    transform: scale(0.98);
}
.fb-field.drag-over {
    border-color: #667eea;
    border-style: dashed;
    background: #f8fafc;
}
.fb-drag-handle {
    position: absolute;
    top: 20px;
    left: 20px;
    font-size: 20px;
    color: #94a3b8;
    cursor: grab;
    user-select: none;
}
.fb-drag-handle:active {
    cursor: grabbing;
}
    </style>';
    
    if (isset($_GET['delete_sub'])) {
        $db = new SQLite3(FORMBUILDER_DB);
        $db->exec('DELETE FROM submissions WHERE id = ' . (int)$_GET['delete_sub']);
        $db->close();
        echo '<div class="fb-success-msg">' . i18n_r('formbuilder/MSG_SUB_DELETED') . '</div>';
    }
    
    // Handle duplicate action
    if (isset($_GET['duplicate'])) {
        formbuilder_duplicate($fid);
        echo '<div class="fb-success-msg">' . i18n_r('formbuilder/MSG_FORM_DUPLICATED') . '</div>';
        echo '<script>setTimeout(function(){ window.location.href="load.php?id=' . $thisfile . '"; }, 1500);</script>';
        return;
    }
    
    switch ($action) {
        case 'edit':
        case 'create':
            formbuilder_edit($fid);
            break;
        case 'delete':
            formbuilder_delete($fid);
            break;
        case 'subs':
            formbuilder_subs($fid);
            break;
        default:
            formbuilder_list();
    }
}

/**
 * Duplicate form function
 */
function formbuilder_duplicate($fid) {
    $db = new SQLite3(FORMBUILDER_DB);
    
    // Get original form
    $stmt = $db->prepare('SELECT * FROM forms WHERE id = ?');
    $stmt->bindValue(1, $fid, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $form = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$form) {
        $db->close();
        return false;
    }
    
    // Create new slug
    $newSlug = $form['slug'] . '-copy-' . time();
    $newName = $form['name'] . ' (Copy)';
    
    // Insert duplicated form
    $stmt = $db->prepare('INSERT INTO forms (name, slug, title, description, submit_text, success_msg, enable_captcha, captcha_site, captcha_secret, email_to, redirect_url, mail_method, smtp_host, smtp_port, smtp_username, smtp_password, smtp_secure, smtp_from_email, smtp_from_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    $stmt->bindValue(1, $newName);
    $stmt->bindValue(2, $newSlug);
    $stmt->bindValue(3, $form['title']);
    $stmt->bindValue(4, $form['description']);
    $stmt->bindValue(5, $form['submit_text']);
    $stmt->bindValue(6, $form['success_msg']);
    $stmt->bindValue(7, $form['enable_captcha'], SQLITE3_INTEGER);
    $stmt->bindValue(8, $form['captcha_site']);
    $stmt->bindValue(9, $form['captcha_secret']);
    $stmt->bindValue(10, $form['email_to']);
    $stmt->bindValue(11, $form['redirect_url']);
    $stmt->bindValue(12, $form['mail_method'] ?? 'mailto');
    $stmt->bindValue(13, $form['smtp_host'] ?? '');
    $stmt->bindValue(14, $form['smtp_port'] ?? 587, SQLITE3_INTEGER);
    $stmt->bindValue(15, $form['smtp_username'] ?? '');
    $stmt->bindValue(16, $form['smtp_password'] ?? '');
    $stmt->bindValue(17, $form['smtp_secure'] ?? 'tls');
    $stmt->bindValue(18, $form['smtp_from_email'] ?? '');
    $stmt->bindValue(19, $form['smtp_from_name'] ?? '');
    $stmt->execute();
    
    $newFormId = $db->lastInsertRowID();
    
    // Copy form fields
    $stmt = $db->prepare('SELECT * FROM form_fields WHERE form_id = ? ORDER BY field_order');
    $stmt->bindValue(1, $fid, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($field = $result->fetchArray(SQLITE3_ASSOC)) {
        $stmt2 = $db->prepare('INSERT INTO form_fields (form_id, type, name, label, placeholder, options, required, field_order, file_accept, file_max_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt2->bindValue(1, $newFormId, SQLITE3_INTEGER);
        $stmt2->bindValue(2, $field['type']);
        $stmt2->bindValue(3, $field['name']);
        $stmt2->bindValue(4, $field['label']);
        $stmt2->bindValue(5, $field['placeholder']);
        $stmt2->bindValue(6, $field['options']);
        $stmt2->bindValue(7, $field['required'], SQLITE3_INTEGER);
        $stmt2->bindValue(8, $field['field_order'], SQLITE3_INTEGER);
        $stmt2->bindValue(9, $field['file_accept'] ?? '');
        $stmt2->bindValue(10, $field['file_max_size'] ?? 5, SQLITE3_INTEGER);
        $stmt2->execute();
    }
    
    $db->close();
    return true;
}

/**
 * List all forms
 */
function formbuilder_list() {
    $thisfile = basename(__FILE__, ".php");
    
    echo '<div class="fb-admin-header">';
    echo '<h3>📝 ' . i18n_r('formbuilder/PLUGIN_NAME') . '</h3>';
    echo '<p>' . i18n_r('formbuilder/PLUGIN_DESC') . '</p>';
    echo '</div>';
    
    echo '<div style="margin-bottom:20px;">';
    echo '<a href="load.php?id=' . $thisfile . '&do=create" class="fb-btn fb-btn-success">' . i18n_r('formbuilder/BTN_CREATE') . '</a>';
    echo '</div>';
    
    $db = new SQLite3(FORMBUILDER_DB);
    $result = $db->query('SELECT * FROM forms ORDER BY created DESC');
    
    echo '<div class="fb-card">';
    echo '<div class="fb-card-header"><h4>' . i18n_r('formbuilder/YOUR_FORMS') . '</h4></div>';
    echo '<div class="fb-card-body" style="padding:0;">';
    echo '<table class="fb-table">';
    echo '<thead><tr>';
    echo '<th>' . i18n_r('formbuilder/TABLE_NAME') . '</th>';
    echo '<th>' . i18n_r('formbuilder/TABLE_SLUG') . '</th>';
    echo '<th>' . i18n_r('formbuilder/TABLE_SUBMISSIONS') . '</th>';
    echo '<th>' . i18n_r('formbuilder/TABLE_STATUS') . '</th>';
    echo '<th>' . i18n_r('formbuilder/TABLE_MAIL_METHOD') . '</th>';
    echo '<th style="text-align:right;">' . i18n_r('formbuilder/TABLE_ACTIONS') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    $has_forms = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $has_forms = true;
        $count = $db->querySingle('SELECT COUNT(*) FROM submissions WHERE form_id = ' . $row['id']);
        $mailMethod = $row['mail_method'] ?? 'mailto';
        
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($row['name']) . '</strong></td>';
        echo '<td><span class="fb-badge">' . htmlspecialchars($row['slug']) . '</span></td>';
        echo '<td><strong>' . $count . '</strong> ' . ($count == 1 ? i18n_r('formbuilder/SUBMISSIONS_COUNT_SINGLE') : i18n_r('formbuilder/SUBMISSIONS_COUNT')) . '</td>';
        echo '<td>' . ($row['enable_captcha'] ? '<span class="fb-badge fb-badge-success">🔒 ' . i18n_r('formbuilder/STATUS_PROTECTED') . '</span>' : '<span class="fb-badge">' . i18n_r('formbuilder/STATUS_PUBLIC') . '</span>') . '</td>';
        echo '<td><span class="fb-badge ' . ($mailMethod == 'smtp' ? 'fb-badge-warning' : '') . '">' . strtoupper($mailMethod) . '</span></td>';
        echo '<td style="text-align:right;">';
        echo '<a href="load.php?id=' . $thisfile . '&do=edit&fid=' . $row['id'] . '" style="margin-right:10px;">✏️ ' . i18n_r('formbuilder/BTN_EDIT') . '</a>';
        echo '<a href="load.php?id=' . $thisfile . '&do=subs&fid=' . $row['id'] . '" style="margin-right:10px;">📊 ' . i18n_r('formbuilder/BTN_SUBMISSIONS') . '</a>';
        echo '<a href="load.php?id=' . $thisfile . '&duplicate=1&fid=' . $row['id'] . '" style="margin-right:10px; color:#f59e0b;">📋 ' . i18n_r('formbuilder/BTN_DUPLICATE') . '</a>';
        echo '<a href="load.php?id=' . $thisfile . '&do=delete&fid=' . $row['id'] . '" onclick="return confirm(\'' . i18n_r('formbuilder/CONFIRM_DELETE') . '\')" style="color:#ef4444;">🗑️ ' . i18n_r('formbuilder/BTN_DELETE') . '</a>';
        echo '</td>';
        echo '</tr>';
    }
    
    if (!$has_forms) {
        echo '<tr><td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">' . i18n_r('formbuilder/NO_FORMS') . '</td></tr>';
    }
    
    echo '</tbody></table>';
    echo '</div></div>';
    $db->close();
    
    echo '<div class="fb-info-box">';
    echo '<h4>🚀 ' . i18n_r('formbuilder/HOW_TO_USE') . '</h4>';
    echo '<p><strong>' . i18n_r('formbuilder/IN_PAGE_CONTENT') . ':</strong> <code>[formbuilder id="your-slug"]</code></p>';
    echo '<p><strong>' . i18n_r('formbuilder/IN_THEME') . ':</strong> <code>&lt;?php show_form("your-slug"); ?&gt;</code></p>';
    echo '<p><strong>' . i18n_r('formbuilder/GET_CAPTCHA') . ':</strong> <a href="https://www.hcaptcha.com/" target="_blank" style="color:#667eea;">' . i18n_r('formbuilder/FREE_AT_HCAPTCHA') . '</a></p>';
    echo '</div>';
}

/**
 * Edit/Create form
 */
function formbuilder_edit($fid) {
    $thisfile = basename(__FILE__, ".php");
    $db = new SQLite3(FORMBUILDER_DB);
    $form = array();
    $fields = array();
    
    if ($fid > 0) {
        $stmt = $db->prepare('SELECT * FROM forms WHERE id = ?');
        $stmt->bindValue(1, $fid, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $form = $result->fetchArray(SQLITE3_ASSOC);
        
        $stmt = $db->prepare('SELECT * FROM form_fields WHERE form_id = ? ORDER BY field_order');
        $stmt->bindValue(1, $fid, SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($f = $result->fetchArray(SQLITE3_ASSOC)) {
            $fields[] = $f;
        }
    }
    
    if (isset($_POST['save'])) {
        $name = $_POST['name'];
        $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $_POST['slug']));
        $title = $_POST['title'];
        $desc = $_POST['desc'];
        $submit = $_POST['submit'];
        $success = $_POST['success'];
        $captcha = isset($_POST['captcha']) ? 1 : 0;
        $site_key = $_POST['site_key'];
        $secret = $_POST['secret'];
        $email = $_POST['email'];
        $redirect = $_POST['redirect'];
        
        // New mail settings
        $mail_method = $_POST['mail_method'] ?? 'mailto';
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_secure = $_POST['smtp_secure'] ?? 'tls';
        $smtp_from_email = $_POST['smtp_from_email'] ?? '';
        $smtp_from_name = $_POST['smtp_from_name'] ?? '';
        
        if ($fid > 0) {
            $stmt = $db->prepare('UPDATE forms SET name=?, slug=?, title=?, description=?, submit_text=?, success_msg=?, enable_captcha=?, captcha_site=?, captcha_secret=?, email_to=?, redirect_url=?, mail_method=?, smtp_host=?, smtp_port=?, smtp_username=?, smtp_password=?, smtp_secure=?, smtp_from_email=?, smtp_from_name=? WHERE id=?');
            $stmt->bindValue(20, $fid, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare('INSERT INTO forms (name, slug, title, description, submit_text, success_msg, enable_captcha, captcha_site, captcha_secret, email_to, redirect_url, mail_method, smtp_host, smtp_port, smtp_username, smtp_password, smtp_secure, smtp_from_email, smtp_from_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        }
        
        $stmt->bindValue(1, $name);
        $stmt->bindValue(2, $slug);
        $stmt->bindValue(3, $title);
        $stmt->bindValue(4, $desc);
        $stmt->bindValue(5, $submit);
        $stmt->bindValue(6, $success);
        $stmt->bindValue(7, $captcha, SQLITE3_INTEGER);
        $stmt->bindValue(8, $site_key);
        $stmt->bindValue(9, $secret);
        $stmt->bindValue(10, $email);
        $stmt->bindValue(11, $redirect);
        $stmt->bindValue(12, $mail_method);
        $stmt->bindValue(13, $smtp_host);
        $stmt->bindValue(14, $smtp_port, SQLITE3_INTEGER);
        $stmt->bindValue(15, $smtp_username);
        $stmt->bindValue(16, $smtp_password);
        $stmt->bindValue(17, $smtp_secure);
        $stmt->bindValue(18, $smtp_from_email);
        $stmt->bindValue(19, $smtp_from_name);
        $stmt->execute();
        
        if ($fid == 0) {
            $fid = $db->lastInsertRowID();
        }
        
        $db->exec('DELETE FROM form_fields WHERE form_id = ' . $fid);
        
        if (isset($_POST['fields'])) {
            foreach ($_POST['fields'] as $i => $field) {
                $stmt = $db->prepare('INSERT INTO form_fields (form_id, type, name, label, placeholder, options, required, field_order, file_accept, file_max_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bindValue(1, $fid, SQLITE3_INTEGER);
                $stmt->bindValue(2, $field['type']);
                $stmt->bindValue(3, $field['name']);
                $stmt->bindValue(4, $field['label']);
                $stmt->bindValue(5, $field['placeholder']);
                $stmt->bindValue(6, $field['options']);
                $stmt->bindValue(7, isset($field['required']) ? 1 : 0, SQLITE3_INTEGER);
                $stmt->bindValue(8, $i, SQLITE3_INTEGER);
                $stmt->bindValue(9, $field['file_accept'] ?? '');
                $stmt->bindValue(10, (int)($field['file_max_size'] ?? 5), SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
        
        echo '<div class="fb-success-msg">' . i18n_r('formbuilder/MSG_SAVED') . '</div>';
        echo '<script>setTimeout(function(){ window.location.href="load.php?id=' . $thisfile . '&do=edit&fid=' . $fid . '"; }, 1500);</script>';
        $db->close();
        return;
    }
    
    $db->close();
    
    echo '<div class="fb-admin-header">';
    echo '<h3>' . ($fid > 0 ? '✏️ ' . i18n_r('formbuilder/EDIT_FORM') : '➕ ' . i18n_r('formbuilder/CREATE_FORM')) . '</h3>';
    echo '<p>' . ($fid > 0 ? i18n_r('formbuilder/EDIT_DESC') : i18n_r('formbuilder/CREATE_DESC')) . '</p>';
    echo '</div>';
    
    echo '<div style="margin-bottom:20px;">';
    echo '<a href="load.php?id=' . $thisfile . '" class="fb-btn">' . i18n_r('formbuilder/BTN_BACK') . '</a>';
    echo '</div>';
    
    echo '<form method="post" class="largeform">';
    
    echo '<div class="fb-card">';
    echo '<div class="fb-card-header"><h4>📋 ' . i18n_r('formbuilder/FORM_SETTINGS') . '</h4></div>';
    echo '<div class="fb-card-body">';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/FORM_NAME') . '</label>';
    echo '<input type="text" name="name" value="' . htmlspecialchars($form['name'] ?? '') . '" required class="text" placeholder="' . i18n_r('formbuilder/FORM_NAME_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/FORM_SLUG') . '</label>';
    echo '<input type="text" name="slug" value="' . htmlspecialchars($form['slug'] ?? '') . '" required class="text" placeholder="' . i18n_r('formbuilder/FORM_SLUG_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/FORM_TITLE') . '</label>';
    echo '<input type="text" name="title" value="' . htmlspecialchars($form['title'] ?? '') . '" class="text" placeholder="' . i18n_r('formbuilder/FORM_TITLE_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/FORM_DESC') . '</label>';
    echo '<textarea name="desc" class="text" rows="3" placeholder="' . i18n_r('formbuilder/FORM_DESC_PH') . '">' . htmlspecialchars($form['description'] ?? '') . '</textarea>';
    echo '</div>';
    
    echo '</div></div>';
    
    echo '<div class="fb-card">';
    echo '<div class="fb-card-header"><h4>⚙️ ' . i18n_r('formbuilder/FORM_BEHAVIOR') . '</h4></div>';
    echo '<div class="fb-card-body">';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SUBMIT_BTN') . '</label>';
    echo '<input type="text" name="submit" value="' . htmlspecialchars($form['submit_text'] ?? i18n_r('formbuilder/SUBMIT_DEFAULT')) . '" class="text">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SUCCESS_MSG') . '</label>';
    echo '<input type="text" name="success" value="' . htmlspecialchars($form['success_msg'] ?? i18n_r('formbuilder/SUCCESS_DEFAULT')) . '" class="text">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/EMAIL_TO') . '</label>';
    echo '<input type="email" name="email" value="' . htmlspecialchars($form['email_to'] ?? '') . '" class="text" placeholder="admin@example.com">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/REDIRECT_URL') . '</label>';
    echo '<input type="url" name="redirect" value="' . htmlspecialchars($form['redirect_url'] ?? '') . '" class="text" placeholder="https://example.com/thank-you">';
    echo '</div>';
    
    echo '</div></div>';
    
    // Email Configuration
    echo '<div class="fb-card">';
    echo '<div class="fb-card-header"><h4>📧 ' . i18n_r('formbuilder/EMAIL_CONFIGURATION') . '</h4></div>';
    echo '<div class="fb-card-body">';
    
    $mail_method = $form['mail_method'] ?? 'mailto';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/MAIL_METHOD') . '</label>';
    echo '<div class="fb-radio-group">';
    echo '<label class="fb-radio-label">';
    echo '<input type="radio" name="mail_method" value="mailto" ' . ($mail_method == 'mailto' ? 'checked' : '') . ' onchange="toggleSmtpOptions()">';
    echo i18n_r('formbuilder/MAIL_METHOD_MAILTO');
    echo '</label>';
    echo '<label class="fb-radio-label">';
    echo '<input type="radio" name="mail_method" value="smtp" ' . ($mail_method == 'smtp' ? 'checked' : '') . ' onchange="toggleSmtpOptions()">';
    echo i18n_r('formbuilder/MAIL_METHOD_SMTP');
    echo '</label>';
    echo '</div>';
    echo '</div>';
    
    echo '<div id="smtp-options" class="fb-smtp-options ' . ($mail_method == 'smtp' ? 'active' : '') . '">';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SMTP_HOST') . '</label>';
    echo '<input type="text" name="smtp_host" value="' . htmlspecialchars($form['smtp_host'] ?? '') . '" class="text" placeholder="' . i18n_r('formbuilder/SMTP_HOST_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SMTP_PORT') . '</label>';
    echo '<input type="number" name="smtp_port" value="' . htmlspecialchars($form['smtp_port'] ?? '587') . '" class="text" placeholder="' . i18n_r('formbuilder/SMTP_PORT_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SMTP_SECURITY') . '</label>';
    echo '<select name="smtp_secure" class="text">';
    $smtp_secure = $form['smtp_secure'] ?? 'tls';
    echo '<option value="tls" ' . ($smtp_secure == 'tls' ? 'selected' : '') . '>' . i18n_r('formbuilder/SMTP_SECURITY_TLS') . '</option>';
    echo '<option value="ssl" ' . ($smtp_secure == 'ssl' ? 'selected' : '') . '>' . i18n_r('formbuilder/SMTP_SECURITY_SSL') . '</option>';
    echo '<option value="" ' . ($smtp_secure == '' ? 'selected' : '') . '>' . i18n_r('formbuilder/SMTP_SECURITY_NONE') . '</option>';
    echo '</select>';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SMTP_USERNAME') . '</label>';
    echo '<input type="text" name="smtp_username" value="' . htmlspecialchars($form['smtp_username'] ?? '') . '" class="text" placeholder="' . i18n_r('formbuilder/SMTP_USERNAME_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SMTP_PASSWORD') . '</label>';
    echo '<input type="password" name="smtp_password" value="' . htmlspecialchars($form['smtp_password'] ?? '') . '" class="text" placeholder="' . i18n_r('formbuilder/SMTP_PASSWORD_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SMTP_FROM_EMAIL') . '</label>';
    echo '<input type="email" name="smtp_from_email" value="' . htmlspecialchars($form['smtp_from_email'] ?? '') . '" class="text" placeholder="' . i18n_r('formbuilder/SMTP_FROM_EMAIL_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/SMTP_FROM_NAME') . '</label>';
    echo '<input type="text" name="smtp_from_name" value="' . htmlspecialchars($form['smtp_from_name'] ?? '') . '" class="text" placeholder="' . i18n_r('formbuilder/SMTP_FROM_NAME_PH') . '">';
    echo '</div>';
    
    echo '<div class="fb-info-box" style="margin-top: 15px;">';
    echo '<h4>ℹ️ ' . i18n_r('formbuilder/SMTP_INFO_TITLE') . '</h4>';
    echo '<p><strong>Gmail:</strong> ' . i18n_r('formbuilder/SMTP_INFO_GMAIL') . '</p>';
    echo '<p><strong>Port 587:</strong> ' . i18n_r('formbuilder/SMTP_INFO_PORT_587') . '</p>';
    echo '<p><strong>Port 465:</strong> ' . i18n_r('formbuilder/SMTP_INFO_PORT_465') . '</p>';
    echo '</div>';
    
    echo '</div>'; // smtp-options
    
    echo '</div></div>';
    
    echo '<div class="fb-card">';
    echo '<div class="fb-card-header"><h4>🔒 ' . i18n_r('formbuilder/SECURITY') . '</h4></div>';
    echo '<div class="fb-card-body">';
    
    echo '<div class="fb-form-group">';
    echo '<label class="fb-checkbox-label">';
    echo '<input type="checkbox" name="captcha" value="1" ' . (!empty($form['enable_captcha']) ? 'checked' : '') . '>';
    echo i18n_r('formbuilder/CAPTCHA_ENABLE');
    echo '</label>';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/CAPTCHA_SITE_KEY') . '</label>';
    echo '<input type="text" name="site_key" value="' . htmlspecialchars($form['captcha_site'] ?? '') . '" class="text">';
    echo '</div>';
    
    echo '<div class="fb-form-group">';
    echo '<label>' . i18n_r('formbuilder/CAPTCHA_SECRET') . '</label>';
    echo '<input type="text" name="secret" value="' . htmlspecialchars($form['captcha_secret'] ?? '') . '" class="text">';
    echo '</div>';
    
    echo '</div></div>';
    
    echo '<div class="fb-card">';
    echo '<div class="fb-card-header">';
    echo '<h4>📝 ' . i18n_r('formbuilder/FORM_FIELDS') . '</h4>';
    echo '<button type="button" onclick="addField()" class="fb-btn" style="font-size:13px; padding:8px 16px;">' . i18n_r('formbuilder/BTN_ADD_FIELD') . '</button>';
    echo '</div>';
    echo '<div class="fb-card-body" id="fields">';
    
    foreach ($fields as $i => $f) {
        echo formbuilder_field_html($i, $f);
    }
    
    if (empty($fields)) {
        echo '<p style="text-align:center; color:#94a3b8; padding:20px;">' . i18n_r('formbuilder/NO_FIELDS') . '</p>';
    }
    
    echo '</div></div>';
    
    echo '<div style="margin-top:20px;">';
    echo '<button type="submit" name="save" class="fb-btn fb-btn-success" style="padding:14px 32px; font-size:16px;">' . i18n_r('formbuilder/BTN_SAVE') . '</button>';
    echo '</div>';
    
    echo '</form>';
    
    echo '<script>
let fieldNum = ' . count($fields) . ';
let draggedElement = null;

function toggleSmtpOptions() {
    const smtpRadio = document.querySelector("input[name=\'mail_method\'][value=\'smtp\']");
    const smtpOptions = document.getElementById("smtp-options");
    if (smtpRadio && smtpRadio.checked) {
        smtpOptions.classList.add("active");
    } else {
        smtpOptions.classList.remove("active");
    }
}

function addField() {
    let html = `' . str_replace(["\n", '`'], ['', '\\`'], formbuilder_field_html('${fieldNum}', null)) . '`;
    document.getElementById("fields").insertAdjacentHTML("beforeend", html);
    fieldNum++;
    attachTypeListeners();
    attachDragListeners(); // DODANE
    updateFieldNumbers();
}

function attachTypeListeners() {
    document.querySelectorAll("select[name*=\'[type]\']").forEach(function(sel) {
        sel.addEventListener("change", function() {
            const parent = this.closest(".fb-field");
            const fileOpts = parent.querySelector(".fb-file-options");
            if (this.value === "file" && fileOpts) {
                fileOpts.classList.add("active");
            } else if (fileOpts) {
                fileOpts.classList.remove("active");
            }
        });
    });
}

// NOWA FUNKCJA: Aktualizacja numeracji pól
function updateFieldNumbers() {
    const fields = document.querySelectorAll("#fields .fb-field");
    fields.forEach((field, index) => {
        const numberSpan = field.querySelector(".field-number");
        if (numberSpan) {
            numberSpan.textContent = index + 1;
        }
        // Aktualizuj name attributes
        field.querySelectorAll("input, select, textarea").forEach(input => {
            if (input.name && input.name.includes("fields[")) {
                input.name = input.name.replace(/fields\[\d+\]/, "fields[" + index + "]");
            }
        });
    });
}

// NOWA FUNKCJA: Drag & Drop
function attachDragListeners() {
    const fields = document.querySelectorAll("#fields .fb-field");
    
    fields.forEach(field => {
        field.addEventListener("dragstart", function(e) {
            draggedElement = this;
            this.classList.add("dragging");
            e.dataTransfer.effectAllowed = "move";
            e.dataTransfer.setData("text/html", this.innerHTML);
        });
        
        field.addEventListener("dragend", function(e) {
            this.classList.remove("dragging");
            document.querySelectorAll("#fields .fb-field").forEach(f => {
                f.classList.remove("drag-over");
            });
        });
        
        field.addEventListener("dragover", function(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = "move";
            
            if (draggedElement !== this) {
                this.classList.add("drag-over");
            }
            return false;
        });
        
        field.addEventListener("dragleave", function(e) {
            this.classList.remove("drag-over");
        });
        
        field.addEventListener("drop", function(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (draggedElement !== this) {
                const allFields = Array.from(document.querySelectorAll("#fields .fb-field"));
                const draggedIndex = allFields.indexOf(draggedElement);
                const targetIndex = allFields.indexOf(this);
                
                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedElement, this);
                }
                
                updateFieldNumbers();
            }
            
            this.classList.remove("drag-over");
            return false;
        });
    });
}

document.addEventListener("click", function(e) {
    if (e.target.classList.contains("fb-field-remove")) {
        if (confirm("' . i18n_r('formbuilder/CONFIRM_REMOVE_FIELD') . '")) {
            e.target.closest(".fb-field").remove();
            updateFieldNumbers();
        }
    }
});

// Inicjalizacja
attachTypeListeners();
attachDragListeners();
updateFieldNumbers();
</script>';

}

/**
 * Field HTML
 */
function formbuilder_field_html($i, $f) {
    $is_tpl = is_string($i);
    $types = [
        'text' => i18n_r('formbuilder/TYPE_TEXT'), 
        'email' => i18n_r('formbuilder/TYPE_EMAIL'), 
        'tel' => i18n_r('formbuilder/TYPE_TEL'), 
        'textarea' => i18n_r('formbuilder/TYPE_TEXTAREA'),
        'select' => i18n_r('formbuilder/TYPE_SELECT'), 
        'radio' => i18n_r('formbuilder/TYPE_RADIO'), 
        'checkbox' => i18n_r('formbuilder/TYPE_CHECKBOX'), 
        'file' => i18n_r('formbuilder/TYPE_FILE')
    ];
    
    $is_file = ($f && $f['type'] == 'file');
    
    // DODANE: draggable attribute
    $html = '<div class="fb-field" draggable="true">';
    
    // DODANE: Drag handle (ikona ⠿)
    $html .= '<span class="fb-drag-handle" title="Przeciągnij aby zmienić kolejność">⠿</span>';
    
    $html .= '<button type="button" class="fb-field-remove">' . i18n_r('formbuilder/BTN_REMOVE') . '</button>';
    $html .= '<h5>' . i18n_r('formbuilder/FIELD_NUM') . ' <span class="field-number">' . ($is_tpl ? '${fieldNum + 1}' : ($i + 1)) . '</span></h5>';
    
    $html .= '<label>' . i18n_r('formbuilder/FIELD_TYPE') . '</label>';
    $html .= '<select name="fields[' . $i . '][type]" required>';
    foreach ($types as $type => $label) {
        $sel = ($f && $f['type'] == $type) ? 'selected' : '';
        $html .= '<option value="' . $type . '" ' . $sel . '>' . $label . '</option>';
    }
    $html .= '</select>';
    
    $html .= '<label>' . i18n_r('formbuilder/FIELD_NAME') . '</label>';
    $html .= '<input type="text" name="fields[' . $i . '][name]" value="' . htmlspecialchars($f['name'] ?? '') . '" required placeholder="' . i18n_r('formbuilder/FIELD_NAME_PH') . '">';
    
    $html .= '<label>' . i18n_r('formbuilder/FIELD_LABEL') . '</label>';
    $html .= '<input type="text" name="fields[' . $i . '][label]" value="' . htmlspecialchars($f['label'] ?? '') . '" required placeholder="' . i18n_r('formbuilder/FIELD_LABEL_PH') . '">';
    
    $html .= '<label>' . i18n_r('formbuilder/FIELD_PLACEHOLDER') . '</label>';
    $html .= '<input type="text" name="fields[' . $i . '][placeholder]" value="' . htmlspecialchars($f['placeholder'] ?? '') . '" placeholder="' . i18n_r('formbuilder/FIELD_PLACEHOLDER_PH') . '">';
    
    $html .= '<label>' . i18n_r('formbuilder/FIELD_OPTIONS') . '</label>';
    $html .= '<input type="text" name="fields[' . $i . '][options]" value="' . htmlspecialchars($f['options'] ?? '') . '" placeholder="' . i18n_r('formbuilder/FIELD_OPTIONS_PH') . '">';
    
    $html .= '<div class="fb-file-options ' . ($is_file ? 'active' : '') . '">';
    $html .= '<label>' . i18n_r('formbuilder/FILE_ACCEPT') . '</label>';
    $html .= '<input type="text" name="fields[' . $i . '][file_accept]" value="' . htmlspecialchars($f['file_accept'] ?? '') . '" placeholder="' . i18n_r('formbuilder/FILE_ACCEPT_PH') . '">';
    $html .= '<label>' . i18n_r('formbuilder/FILE_MAX_SIZE') . '</label>';
    $html .= '<input type="number" name="fields[' . $i . '][file_max_size]" value="' . ($f['file_max_size'] ?? 5) . '" min="1" max="50" placeholder="' . i18n_r('formbuilder/FILE_MAX_SIZE_PH') . '">';
    $html .= '</div>';
    
    $html .= '<label class="fb-checkbox-label" style="margin-top:12px;">';
    $html .= '<input type="checkbox" name="fields[' . $i . '][required]" value="1" ' . ($f && $f['required'] ? 'checked' : '') . '>';
    $html .= i18n_r('formbuilder/FIELD_REQUIRED');
    $html .= '</label>';
    
    $html .= '</div>';
    
    return $html;
}


/**
 * Delete form
 */
function formbuilder_delete($fid) {
    $thisfile = basename(__FILE__, ".php");
    $db = new SQLite3(FORMBUILDER_DB);
    $db->exec('DELETE FROM forms WHERE id = ' . $fid);
    $db->exec('DELETE FROM form_fields WHERE form_id = ' . $fid);
    $db->exec('DELETE FROM submissions WHERE form_id = ' . $fid);
    $db->close();
    header('Location: load.php?id=' . $thisfile);
    exit;
}

/**
 * View submissions
 */
function formbuilder_subs($fid) {
    $thisfile = basename(__FILE__, ".php");
    $db = new SQLite3(FORMBUILDER_DB);
    
    $stmt = $db->prepare('SELECT * FROM forms WHERE id = ?');
    $stmt->bindValue(1, $fid, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $form = $result->fetchArray(SQLITE3_ASSOC);
    
    echo '<div class="fb-admin-header">';
    echo '<h3>📊 ' . i18n_r('formbuilder/SUBMISSIONS_TITLE') . ': ' . htmlspecialchars($form['name']) . '</h3>';
    echo '<p>' . i18n_r('formbuilder/SUBMISSIONS_DESC') . '</p>';
    echo '</div>';
    
    echo '<div style="margin-bottom:20px;">';
    echo '<a href="load.php?id=' . $thisfile . '" class="fb-btn">' . i18n_r('formbuilder/BTN_BACK') . '</a>';
    echo '</div>';
    
    $stmt = $db->prepare('SELECT * FROM submissions WHERE form_id = ? ORDER BY created DESC');
    $stmt->bindValue(1, $fid, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    echo '<div class="fb-card">';
    echo '<div class="fb-card-header"><h4>' . i18n_r('formbuilder/ALL_SUBMISSIONS') . '</h4></div>';
    echo '<div class="fb-card-body" style="padding:0;">';
    echo '<table class="fb-table">';
    echo '<thead><tr><th>' . i18n_r('formbuilder/SUB_ID') . '</th><th>' . i18n_r('formbuilder/SUB_DATE') . '</th><th>' . i18n_r('formbuilder/SUB_IP') . '</th><th>' . i18n_r('formbuilder/SUB_DATA') . '</th><th>' . i18n_r('formbuilder/TABLE_ACTIONS') . '</th></tr></thead>';
    echo '<tbody>';
    
    $has_subs = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $has_subs = true;
        $data = json_decode($row['data'], true);
        echo '<tr>';
        echo '<td><strong>#' . $row['id'] . '</strong></td>';
        echo '<td>' . date('Y-m-d H:i', strtotime($row['created'])) . '</td>';
        echo '<td>' . htmlspecialchars($row['ip']) . '</td>';
        echo '<td><details><summary style="cursor:pointer; color:#667eea;">' . i18n_r('formbuilder/VIEW_DATA') . '</summary><pre style="margin:10px; padding:10px; background:#f8fafc; border-radius:6px; font-size:12px;">' . htmlspecialchars(print_r($data, true)) . '</pre></details></td>';
        echo '<td><a href="?id=' . $thisfile . '&do=subs&fid=' . $fid . '&delete_sub=' . $row['id'] . '" onclick="return confirm(\'' . i18n_r('formbuilder/CONFIRM_DELETE_SUB') . '\')" style="color:#ef4444;">🗑️ ' . i18n_r('formbuilder/BTN_DELETE') . '</a></td>';
        echo '</tr>';
    }
    
    if (!$has_subs) {
        echo '<tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">' . i18n_r('formbuilder/NO_SUBMISSIONS') . '</td></tr>';
    }
    
    echo '</tbody></table>';
    echo '</div></div>';
    $db->close();
}

/**
 * Shortcode handler
 */
function formbuilder_shortcode($content) {
    if (preg_match_all('/\[formbuilder\s+id=["\']([^"\']+)["\']\]/i', $content, $m)) {
        foreach ($m[1] as $i => $slug) {
            $html = show_form($slug, false);
            $content = str_replace($m[0][$i], $html, $content);
        }
    }
    return $content;
}

/**
 * Display form - FRONTEND with Post/Redirect/Get pattern
 */
function show_form($slug, $echo = true) {
    $db = new SQLite3(FORMBUILDER_DB);
    $stmt = $db->prepare('SELECT * FROM forms WHERE slug = ?');
    $stmt->bindValue(1, $slug, SQLITE3_TEXT);
    $result = $stmt->execute();
    $form = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$form) {
        $db->close();
        return '<!-- Form not found -->';
    }
    
    $stmt = $db->prepare('SELECT * FROM form_fields WHERE form_id = ? ORDER BY field_order');
    $stmt->bindValue(1, $form['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $fields = array();
    while ($f = $result->fetchArray(SQLITE3_ASSOC)) {
        $fields[] = $f;
    }
    
    $errors = array();
    $success = false;
    
    // Check if we just redirected back with success
    if (isset($_GET['fb_success']) && $_GET['fb_success'] == $form['id']) {
        $success = true;
    }
    
    if (!isset($_SESSION['fb_csrf_' . $form['id']])) {
        $_SESSION['fb_csrf_' . $form['id']] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['fb_csrf_' . $form['id']];
    
    // Process form submission ONLY if not already successful
    if (isset($_POST['fb_submit_' . $form['id']]) && !$success) {
        
        if (!isset($_POST['fb_csrf']) || $_POST['fb_csrf'] !== $csrf_token) {
            $errors[] = i18n_r('formbuilder/ERROR_CSRF');
        } else {
            
            $ip = $_SERVER['REMOTE_ADDR'];
            $rate_key = 'fb_rate_' . $ip;
            if (!isset($_SESSION[$rate_key])) {
                $_SESSION[$rate_key] = ['count' => 0, 'time' => time()];
            }
            if (time() - $_SESSION[$rate_key]['time'] < 60) {
                $_SESSION[$rate_key]['count']++;
                if ($_SESSION[$rate_key]['count'] > 5) {
                    $errors[] = i18n_r('formbuilder/ERROR_RATE_LIMIT');
                }
            } else {
                $_SESSION[$rate_key] = ['count' => 1, 'time' => time()];
            }
            
            if (empty($errors)) {
                if ($form['enable_captcha'] == 1 && !empty($form['captcha_secret'])) {
                    if (empty($_POST['h-captcha-response'])) {
                        $errors[] = i18n_r('formbuilder/ERROR_CAPTCHA_REQUIRED');
                    } else {
                        $verify = @file_get_contents('https://hcaptcha.com/siteverify?secret=' . urlencode($form['captcha_secret']) . '&response=' . urlencode($_POST['h-captcha-response']));
                        $check = json_decode($verify);
                        if (!$check || !$check->success) {
                            $errors[] = i18n_r('formbuilder/ERROR_CAPTCHA_FAILED');
                        }
                    }
                }
                
                $data = array();
                $uploaded_files = array();
                
                foreach ($fields as $f) {
                    $val = '';
                    
                    if ($f['type'] == 'file') {
                        if (isset($_FILES[$f['name']]) && $_FILES[$f['name']]['error'] == UPLOAD_ERR_OK) {
                            $file = $_FILES[$f['name']];
                            
                            $maxSize = ($f['file_max_size'] ?? 5) * 1024 * 1024;
                            if ($file['size'] > $maxSize) {
                                $errors[] = $f['label'] . i18n_r('formbuilder/ERROR_FILE_SIZE') . ' ' . ($f['file_max_size'] ?? 5) . 'MB)';
                                continue;
                            }
                            
                            $allowed = array_map('trim', explode(',', $f['file_accept'] ?? ''));
                            $ext = '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (!empty($allowed[0]) && !in_array($ext, $allowed)) {
                                $errors[] = $f['label'] . i18n_r('formbuilder/ERROR_FILE_TYPE');
                                continue;
                            }
                            
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $file['tmp_name']);
                            finfo_close($finfo);
                            
                            $allowedMimes = [
                                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                                'application/pdf',
                                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/plain', 'text/csv'
                            ];
                            
                            if (!in_array($mimeType, $allowedMimes)) {
                                $errors[] = $f['label'] . i18n_r('formbuilder/ERROR_FILE_INVALID');
                                continue;
                            }
                            
                            $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                            $safeFilename = substr($safeFilename, 0, 200);
                            $uniqueFilename = time() . '_' . bin2hex(random_bytes(8)) . '_' . $safeFilename . $ext;
                            $targetPath = FORMBUILDER_UPLOADS . $uniqueFilename;
                            
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                chmod($targetPath, 0644);
                                $val = $uniqueFilename;
                                $uploaded_files[] = [
                                    'path' => $targetPath,
                                    'name' => $file['name'],
                                    'mime' => $mimeType
                                ];
                            } else {
                                $errors[] = $f['label'] . i18n_r('formbuilder/ERROR_UPLOAD_FAILED');
                            }
                        } elseif ($f['required']) {
                            $errors[] = $f['label'] . i18n_r('formbuilder/ERROR_REQUIRED');
                        }
                    } else {
                        $val = isset($_POST[$f['name']]) ? $_POST[$f['name']] : '';
                        
                        if (is_array($val)) {
                            $val = array_map(function($v) {
                                return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
                            }, $val);
                            $val = implode(', ', $val);
                        } else {
                            $val = htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
                        }
                        
                        if ($f['required'] && empty($val)) {
                            $errors[] = $f['label'] . i18n_r('formbuilder/ERROR_REQUIRED');
                        }
                        
                        if ($f['type'] == 'email' && !empty($val) && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = $f['label'] . i18n_r('formbuilder/ERROR_EMAIL_INVALID');
                        }
                    }
                    
                    $data[$f['name']] = $val;
                }
                
                if (empty($errors)) {
                    // Save to database
                    $stmt = $db->prepare('INSERT INTO submissions (form_id, data, ip) VALUES (?, ?, ?)');
                    $stmt->bindValue(1, $form['id'], SQLITE3_INTEGER);
                    $stmt->bindValue(2, json_encode($data, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                    $stmt->bindValue(3, htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'), SQLITE3_TEXT);
                    $stmt->execute();
                    
                    // Send email
                    if (!empty($form['email_to']) && filter_var($form['email_to'], FILTER_VALIDATE_EMAIL)) {
                        $mail_method = $form['mail_method'] ?? 'mailto';
                        
                        if ($mail_method == 'smtp') {
                            formbuilder_send_smtp_email($form, $data, $uploaded_files);
                        } else {
                            formbuilder_send_standard_email($form, $data, $uploaded_files);
                        }
                    }
                    
                    // Regenerate CSRF token
                    $_SESSION['fb_csrf_' . $form['id']] = bin2hex(random_bytes(32));
                    
                    // Close DB before redirect
                    $db->close();
                    
                    // REDIRECT - Post/Redirect/Get pattern
                    if (!empty($form['redirect_url'])) {
                        // External redirect
                        header('Location: ' . $form['redirect_url']);
                        exit;
                    } else {
                        // Redirect to same page with success parameter
                        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                        $current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                        
                        // Remove existing fb_success parameter if exists
                        $current_url = preg_replace('/([?&])fb_success=[^&]*(&|$)/', '$1', $current_url);
                        $current_url = rtrim($current_url, '?&');
                        
                        // Add fb_success parameter
                        $separator = (strpos($current_url, '?') !== false) ? '&' : '?';
                        header('Location: ' . $current_url . $separator . 'fb_success=' . $form['id']);
                        exit;
                    }
                }
            }
        }
    }
    
    $db->close();
    
    ob_start();
    ?>
    <style>
    .fb-form-minimal {
        max-width: 600px;
        margin: 20px auto;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .fb-form-minimal h3 {
        margin: 0 0 8px 0;
        font-size: 24px;
        font-weight: 600;
        color: #1a1a1a;
    }
    .fb-form-minimal p {
        margin: 0 0 24px 0;
        font-size: 15px;
        color: #666;
    }
    .fb-field-minimal {
        margin-bottom: 20px;
    }
    .fb-field-minimal label {
        display: block;
        margin-bottom: 6px;
        font-size: 14px;
        font-weight: 500;
        color: #333;
    }
    .fb-field-minimal label .required {
        color: #e11d48;
    }
    .fb-field-minimal input[type="text"],
    .fb-field-minimal input[type="email"],
    .fb-field-minimal input[type="tel"],
    .fb-field-minimal input[type="file"],
    .fb-field-minimal textarea,
    .fb-field-minimal select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 15px;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .fb-field-minimal input:focus,
    .fb-field-minimal textarea:focus,
    .fb-field-minimal select:focus {
        outline: none;
        border-color: #3b82f6;
    }
    .fb-field-minimal textarea {
        min-height: 100px;
        resize: vertical;
    }
    .fb-radio-minimal,
    .fb-checkbox-minimal {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .fb-radio-minimal label,
    .fb-checkbox-minimal label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 400;
        cursor: pointer;
    }
    .fb-radio-minimal input,
    .fb-checkbox-minimal input {
        width: auto;
        cursor: pointer;
    }
    .fb-submit-minimal {
        width: 100%;
        padding: 12px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s;
    }
    .fb-submit-minimal:hover {
        background: #2563eb;
    }
    .fb-success-minimal {
        padding: 16px;
        background: #d1fae5;
        color: #065f46;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    .fb-error-minimal {
        padding: 16px;
        background: #fee2e2;
        color: #991b1b;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    .fb-error-minimal div {
        margin: 4px 0;
    }
    .fb-captcha-minimal {
        margin: 20px 0;
        display: flex;
        justify-content: center;
    }
    .fb-file-info-minimal {
        font-size: 13px;
        color: #6b7280;
        margin-top: 4px;
    }
    </style>
    
    <div class="fb-form-minimal">
        <?php if ($success): ?>
            <div class="fb-success-minimal">
                <?php echo htmlspecialchars($form['success_msg'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <script>
            // Optional: Remove success parameter from URL after display
            if (window.history.replaceState) {
                let url = new URL(window.location);
                url.searchParams.delete('fb_success');
                window.history.replaceState({}, '', url);
            }
            </script>
        <?php else: ?>
            <?php if ($form['title']): ?>
                <h3><?php echo htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <?php endif; ?>
            <?php if ($form['description']): ?>
                <p><?php echo htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            
            <?php if ($errors): ?>
            <div class="fb-error-minimal">
                <?php foreach ($errors as $err): ?>
                <div>• <?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="fb_csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                
                <?php foreach ($fields as $f): ?>
                <div class="fb-field-minimal">
                    <label>
                        <?php echo htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($f['required']): ?><span class="required">*</span><?php endif; ?>
                    </label>
                    
                    <?php if ($f['type'] == 'textarea'): ?>
                        <textarea name="<?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($f['placeholder'], ENT_QUOTES, 'UTF-8'); ?>" <?php if ($f['required']) echo 'required'; ?>><?php echo isset($_POST[$f['name']]) ? htmlspecialchars($_POST[$f['name']], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                    
                    <?php elseif ($f['type'] == 'select'): ?>
                        <select name="<?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>" <?php if ($f['required']) echo 'required'; ?>>
                            <option value=""><?php echo i18n_r('formbuilder/SELECT_OPTION'); ?></option>
                            <?php foreach (explode('|', $f['options']) as $opt): ?>
                            <option value="<?php echo htmlspecialchars(trim($opt), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(trim($opt), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    
                    <?php elseif ($f['type'] == 'radio'): ?>
                        <div class="fb-radio-minimal">
                            <?php foreach (explode('|', $f['options']) as $opt): ?>
                            <label>
                                <input type="radio" name="<?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars(trim($opt), ENT_QUOTES, 'UTF-8'); ?>" <?php if ($f['required']) echo 'required'; ?>>
                                <?php echo htmlspecialchars(trim($opt), ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    
                    <?php elseif ($f['type'] == 'checkbox'): ?>
                        <div class="fb-checkbox-minimal">
                            <?php foreach (explode('|', $f['options']) as $opt): ?>
                            <label>
                                <input type="checkbox" name="<?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>[]" value="<?php echo htmlspecialchars(trim($opt), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(trim($opt), ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    
                    <?php elseif ($f['type'] == 'file'): ?>
                        <input type="file" name="<?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php if (!empty($f['file_accept'])): ?>accept="<?php echo htmlspecialchars($f['file_accept'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                               <?php if ($f['required']) echo 'required'; ?>>
                        <?php if (!empty($f['file_accept']) || !empty($f['file_max_size'])): ?>
                        <div class="fb-file-info-minimal">
                            <?php if (!empty($f['file_accept'])): ?><?php echo i18n_r('formbuilder/FILE_ALLOWED'); ?>: <?php echo htmlspecialchars($f['file_accept'], ENT_QUOTES, 'UTF-8'); ?> | <?php endif; ?>
                            <?php echo i18n_r('formbuilder/FILE_MAX'); ?>: <?php echo (int)($f['file_max_size'] ?? 5); ?>MB
                        </div>
                        <?php endif; ?>
                    
                    <?php else: ?>
                        <input type="<?php echo htmlspecialchars($f['type'], ENT_QUOTES, 'UTF-8'); ?>" 
                               name="<?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="<?php echo htmlspecialchars($f['placeholder'], ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php if ($f['required']) echo 'required'; ?> 
                               value="<?php echo isset($_POST[$f['name']]) ? htmlspecialchars($_POST[$f['name']], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if ($form['enable_captcha'] == 1 && $form['captcha_site']): ?>
                <div class="fb-captcha-minimal">
                    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
                    <div class="h-captcha" data-sitekey="<?php echo htmlspecialchars($form['captcha_site'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <?php endif; ?>
                
                <button type="submit" name="fb_submit_<?php echo $form['id']; ?>" class="fb-submit-minimal">
                    <?php echo htmlspecialchars($form['submit_text'], ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    
    $html = ob_get_clean();
    
    if ($echo) {
        echo $html;
    } else {
        return $html;
    }
}

/**
 * Send email using standard mail() WITH attachments
 */
function formbuilder_send_standard_email($form, $data, $uploaded_files) {
    $to = $form['email_to'];
    $subject = i18n_r('formbuilder/EMAIL_SUBJECT') . $form['name'];
    
    $message = i18n_r('formbuilder/EMAIL_NEW_SUBMISSION') . $form['name'] . "\n\n";
    foreach ($data as $k => $v) {
        $message .= ucfirst(str_replace('_', ' ', $k)) . ": " . $v . "\n";
    }
    
    if (!empty($uploaded_files)) {
        // Create multipart email with attachments
        $boundary = md5(time());
        
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $message . "\r\n\r\n";
        
        // Add attachments
        foreach ($uploaded_files as $file) {
            if (file_exists($file['path'])) {
                $fileContent = chunk_split(base64_encode(file_get_contents($file['path'])));
                
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: {$file['mime']}; name=\"{$file['name']}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$file['name']}\"\r\n\r\n";
                $body .= $fileContent . "\r\n";
            }
        }
        
        $body .= "--{$boundary}--";
        
        // Send with timeout protection
        @mail($to, $subject, $body, $headers);
    } else {
        // Simple email without attachments
        $headers = 'From: noreply@' . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= 'Content-Type: text/plain; charset=UTF-8';
        
        @mail($to, $subject, $message, $headers);
    }
    
    return true;
}

/**
 * Send email using SMTP (PHPMailer) with timeout protection and attachments
 */
function formbuilder_send_smtp_email($form, $data, $uploaded_files) {
    // Timeout protection
    set_time_limit(30);
    
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('PHPMailer not found - falling back to mail()');
        formbuilder_send_standard_email($form, $data, $uploaded_files);
        return false;
    }
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP configuration with timeouts
        $mail->isSMTP();
        $mail->Host = $form['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $form['smtp_username'];
        $mail->Password = $form['smtp_password'];
        $mail->SMTPSecure = $form['smtp_secure'] ?? 'tls';
        $mail->Port = $form['smtp_port'] ?? 587;
        $mail->CharSet = 'UTF-8';
        
        // CRITICAL: Set timeouts to prevent hanging
        $mail->Timeout = 10; // Connection timeout in seconds
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Disable debug output
        $mail->SMTPDebug = 0;
        
        // Recipients
        $fromEmail = !empty($form['smtp_from_email']) ? $form['smtp_from_email'] : $form['smtp_username'];
        $fromName = !empty($form['smtp_from_name']) ? $form['smtp_from_name'] : $form['name'];
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($form['email_to']);
        $mail->addReplyTo($fromEmail, $fromName);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = i18n_r('formbuilder/EMAIL_SUBJECT') . $form['name'];
        
        $message = i18n_r('formbuilder/EMAIL_NEW_SUBMISSION') . $form['name'] . "\n\n";
        foreach ($data as $k => $v) {
            $message .= ucfirst(str_replace('_', ' ', $k)) . ": " . $v . "\n";
        }
        $mail->Body = $message;
        
        // Attachments - PRZYWRÓCONE
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file) {
                if (file_exists($file['path'])) {
                    $mail->addAttachment($file['path'], $file['name']);
                }
            }
        }
        
        // Send with error suppression to prevent hanging
        @$mail->send();
        return true;
        
    } catch (\Exception $e) {
        // Log but don't stop execution
        error_log('PHPMailer Error: ' . $e->getMessage());
        
        // Fallback to standard mail if SMTP fails
        formbuilder_send_standard_email($form, $data, $uploaded_files);
        return false;
    }
}
?>

