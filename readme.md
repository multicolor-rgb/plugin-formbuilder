# FormBuilder for GetSimple CMS

A modern, user-friendly **contact/form builder plugin** for **GetSimple CMS** (including GetSimple CE). Allows you to easily create unlimited forms with a drag-and-drop-like field editor (order by saving), file uploads, hCaptcha protection, and flexible email delivery options.

![FormBuilder screenshot](https://via.placeholder.com/800x450.png?text=FormBuilder+Admin+Panel+Screenshot)  
*(Add real screenshots later)*

## Features

- **Unlimited forms** with unique slugs
- Supported field types: Text, Email, Telephone, Textarea, Select, Radio, Checkbox, **File upload**
- File upload with:
  - Configurable allowed extensions
  - Max size limit (1–50 MB)
  - Secure validation (MIME type check)
  - Files stored securely outside web root
- **hCaptcha** integration (free anti-spam)
- Email notification options:
  - Classic PHP `mail()` (with attachments)
  - **SMTP** via PHPMailer (recommended for reliable delivery)
- Success message or redirect to custom URL after submission
- Submissions stored in SQLite database with IP and timestamp
- View & delete individual submissions
- Form duplication feature
- Shortcode `[formbuilder id="your-slug"]` or PHP function `<?php show_form('your-slug'); ?>`
- Clean, modern admin interface with responsive cards and buttons
- Multilingual ready (i18n support)
- CSRF protection & basic rate limiting

## Requirements

- GetSimple CMS (classic) or GetSimple CE
- PHP ≥ 7.4 (recommended 8.0+)
- Write permissions for `data/other/` folder (database + uploads)
- For SMTP: PHPMailer via Composer (optional but recommended)

## Installation

1. Download the latest release or clone this repository.
2. Extract the content into your GetSimple plugins folder:  
   `/admin/plugins/FormBuilder.php` (the file you already have)
3. Log in to your GetSimple admin panel.
4. Go to **Plugins** → you will see **FormBuilder 📝**.
5. Click it to open the plugin – the database and upload folder will be created automatically.

