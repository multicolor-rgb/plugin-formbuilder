<?php
if (!defined('IN_GS')) die('You cannot load this file directly!');

$i18n = array(
    'PLUGIN_NAME' => 'Form Builder',
    'PLUGIN_DESC' => 'Advanced form builder with hCAPTCHA, file uploads and email notifications',
    
    // Buttons
    'BTN_CREATE' => 'Create New Form',
    'BTN_BACK' => 'Back',
    'BTN_SAVE' => 'Save Form',
    'BTN_DELETE' => 'Delete',
    'BTN_EDIT' => 'Edit',
    'BTN_REMOVE' => 'Remove',
    'BTN_SUBMISSIONS' => 'Submissions',
    'BTN_ADD_FIELD' => 'Add Field',
    'BTN_DUPLICATE' => 'Duplicate',
    
    // Table Headers
    'TABLE_NAME' => 'Name',
    'TABLE_SLUG' => 'Slug',
    'TABLE_SUBMISSIONS' => 'Submissions',
    'TABLE_STATUS' => 'Status',
    'TABLE_ACTIONS' => 'Actions',
    'TABLE_MAIL_METHOD' => 'Mail Method',
    
    // Lists
    'YOUR_FORMS' => 'Your Forms',
    'SUBMISSIONS_COUNT' => 'submissions',
    'SUBMISSIONS_COUNT_SINGLE' => 'submission',
    
    // Status
    'STATUS_PROTECTED' => 'Protected',
    'STATUS_PUBLIC' => 'Public',
    
    // Empty States
    'NO_FORMS' => 'No forms yet. Create your first form!',
    'NO_FIELDS' => 'No fields yet. Click "Add Field"',
    'NO_SUBMISSIONS' => 'No submissions yet.',
    
    // Info Box
    'HOW_TO_USE' => 'How to Use',
    'IN_PAGE_CONTENT' => 'In Page Content',
    'IN_THEME' => 'In Theme Template',
    'GET_CAPTCHA' => 'Get hCaptcha Keys',
    'FREE_AT_HCAPTCHA' => 'Free at hCaptcha.com',
    
    // Form Headers
    'EDIT_FORM' => 'Edit Form',
    'CREATE_FORM' => 'Create New Form',
    'EDIT_DESC' => 'Edit your form settings and fields',
    'CREATE_DESC' => 'Create a new form',
    
    // Section Titles
    'FORM_SETTINGS' => 'Form Settings',
    'FORM_BEHAVIOR' => 'Form Behavior',
    'EMAIL_CONFIGURATION' => 'Email Configuration',
    'SECURITY' => 'Security (hCaptcha)',
    'FORM_FIELDS' => 'Form Fields',
    
    // Form Settings
    'FORM_NAME' => 'Form Name *',
    'FORM_NAME_PH' => 'Contact Form',
    'FORM_SLUG' => 'Slug *',
    'FORM_SLUG_PH' => 'contact-form',
    'FORM_TITLE' => 'Display Title',
    'FORM_TITLE_PH' => 'Get in Touch',
    'FORM_DESC' => 'Description',
    'FORM_DESC_PH' => 'Form description',
    
    // Form Behavior
    'SUBMIT_BTN' => 'Submit Button Text',
    'SUBMIT_DEFAULT' => 'Submit',
    'SUCCESS_MSG' => 'Success Message',
    'SUCCESS_DEFAULT' => 'Thank you!',
    'EMAIL_TO' => 'Notification Email',
    'REDIRECT_URL' => 'Redirect URL (optional)',
    
    // Email Configuration
    'MAIL_METHOD' => 'Mail Sending Method',
    'MAIL_METHOD_MAILTO' => 'PHP mail() - Standard',
    'MAIL_METHOD_SMTP' => 'SMTP - PHPMailer',
    
    // SMTP Settings
    'SMTP_HOST' => 'SMTP Host *',
    'SMTP_HOST_PH' => 'smtp.gmail.com',
    'SMTP_PORT' => 'SMTP Port *',
    'SMTP_PORT_PH' => '587',
    'SMTP_SECURITY' => 'SMTP Security',
    'SMTP_SECURITY_TLS' => 'TLS',
    'SMTP_SECURITY_SSL' => 'SSL',
    'SMTP_SECURITY_NONE' => 'None',
    'SMTP_USERNAME' => 'SMTP Username *',
    'SMTP_USERNAME_PH' => 'your-email@gmail.com',
    'SMTP_PASSWORD' => 'SMTP Password *',
    'SMTP_PASSWORD_PH' => '••••••••',
    'SMTP_FROM_EMAIL' => 'From Email',
    'SMTP_FROM_EMAIL_PH' => 'noreply@example.com',
    'SMTP_FROM_NAME' => 'From Name',
    'SMTP_FROM_NAME_PH' => 'Website Name',
    
    // SMTP Info
    'SMTP_INFO_TITLE' => 'SMTP Configuration Tips',
    'SMTP_INFO_GMAIL' => 'Gmail: Use App Password (not your regular password)',
    'SMTP_INFO_PORT_587' => 'Port 587: TLS encryption (recommended)',
    'SMTP_INFO_PORT_465' => 'Port 465: SSL encryption',
    
    // Security
    'CAPTCHA_ENABLE' => 'Enable hCAPTCHA Protection',
    'CAPTCHA_SITE_KEY' => 'hCAPTCHA Site Key',
    'CAPTCHA_SECRET' => 'hCAPTCHA Secret Key',
    
    // Field Configuration
    'FIELD_NUM' => 'Field #',
    'FIELD_TYPE' => 'Field Type',
    'FIELD_NAME' => 'Field Name (no spaces)',
    'FIELD_NAME_PH' => 'full_name',
    'FIELD_LABEL' => 'Label',
    'FIELD_LABEL_PH' => 'Full Name',
    'FIELD_PLACEHOLDER' => 'Placeholder',
    'FIELD_PLACEHOLDER_PH' => 'Enter your name...',
    'FIELD_OPTIONS' => 'Options (for select/radio/checkbox, use |)',
    'FIELD_OPTIONS_PH' => 'Option 1|Option 2|Option 3',
    'FIELD_REQUIRED' => 'Required Field',
    
    // Field Types
    'TYPE_TEXT' => '📝 Text',
    'TYPE_EMAIL' => '📧 Email',
    'TYPE_TEL' => '📞 Phone',
    'TYPE_TEXTAREA' => '📄 Textarea',
    'TYPE_SELECT' => '📋 Select',
    'TYPE_RADIO' => '🔘 Radio',
    'TYPE_CHECKBOX' => '☑️ Checkbox',
    'TYPE_FILE' => '📎 File',
    
    // File Upload
    'FILE_ACCEPT' => 'Accepted File Types (e.g., .pdf,.jpg)',
    'FILE_ACCEPT_PH' => '.pdf,.doc,.jpg,.png',
    'FILE_MAX_SIZE' => 'Max File Size (MB)',
    'FILE_MAX_SIZE_PH' => '5',
    'FILE_ALLOWED' => 'Allowed',
    'FILE_MAX' => 'Max',
    
    // Messages
    'MSG_SAVED' => '✓ Form saved successfully!',
    'MSG_SUB_DELETED' => '✓ Submission deleted',
    'MSG_FORM_DUPLICATED' => '✓ Form has been duplicated!',
    
    // Confirmations
    'CONFIRM_DELETE' => 'Delete this form?',
    'CONFIRM_REMOVE_FIELD' => 'Remove this field?',
    'CONFIRM_DELETE_SUB' => 'Delete?',
    
    // Submissions
    'SUBMISSIONS_TITLE' => 'Submissions',
    'SUBMISSIONS_DESC' => 'View and manage all submissions',
    'ALL_SUBMISSIONS' => 'All Submissions',
    'SUB_ID' => 'ID',
    'SUB_DATE' => 'Date',
    'SUB_IP' => 'IP',
    'SUB_DATA' => 'Data',
    'VIEW_DATA' => 'View Data',
    
    // Frontend Errors
    'ERROR_CSRF' => 'Security token invalid',
    'ERROR_RATE_LIMIT' => 'Too many requests. Please wait.',
    'ERROR_CAPTCHA_REQUIRED' => 'Please complete the captcha',
    'ERROR_CAPTCHA_FAILED' => 'Captcha verification failed',
    'ERROR_FILE_SIZE' => ': File too large (max',
    'ERROR_FILE_TYPE' => ': Invalid file type',
    'ERROR_FILE_INVALID' => ': Invalid file',
    'ERROR_UPLOAD_FAILED' => ': Upload failed',
    'ERROR_REQUIRED' => ' is required',
    'ERROR_EMAIL_INVALID' => ' is invalid',
    
    // Frontend
    'SELECT_OPTION' => 'Select...',
    'REQUIRED_MARK' => '*',
    
    // Email
    'EMAIL_SUBJECT' => 'Form Submission: ',
    'EMAIL_NEW_SUBMISSION' => 'New submission: ',
    
    'MAIL_CHARSET' => 'Email Encoding',
'MAIL_CHARSET_UTF8' => 'UTF-8 (Unicode - recommended)',
'MAIL_CHARSET_ISO' => 'ISO-8859-2 (Latin-2)',
'MAIL_CHARSET_WIN' => 'Windows-1250 (CP1250)',
'MAIL_CHARSET_INFO' => 'UTF-8 supports all languages. Change only if email client has display issues.',
);
