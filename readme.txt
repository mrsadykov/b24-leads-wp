=== Sadykov Form Submissions for Bitrix24 ===

Contributors: mrsadykov96
Tags: bitrix24, contact form 7, crm, leads, webhook
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send form submissions (Contact Form 7, WPForms, etc.) to Bitrix24 as leads or deals via inbound webhook. Unofficial integration; not affiliated with Bitrix24.

== Description ==

**Sadykov Form Submissions for Bitrix24** sends form data from your site to Bitrix24 CRM as **leads** or **deals** via the **inbound webhook**. Supports **Contact Form 7**, Elementor Forms (Pro), WPForms, Gravity Forms; any form can send data via a hook. Configure webhook URL, map form fields to CRM fields, and view the send log in one admin screen.

This plugin is an independent integration and is **not** affiliated with, endorsed by, or sponsored by Bitrix24.

**Features:**

* Set Bitrix24 inbound webhook URL (from your Bitrix24 portal settings)
* Choose entity: leads (crm.lead.add) or deals (crm.deal.add)
* For deals: set pipeline ID (CATEGORY_ID) and stage (STAGE_ID)
* Optionally create a contact in Bitrix24 and link it to the lead/deal
* Map form fields to CRM fields; add extra pairs (including custom UF_CRM_*)
* Fields named UF_CRM_* are sent to Bitrix24 automatically
* Send log in admin (success/error/skip), last API response for debugging

**Supported forms:**

* Contact Form 7 — automatic
* Elementor Forms (Elementor Pro) — automatic
* WPForms — automatic
* Gravity Forms — automatic
* Any form — call `do_action( 'b24_leads_wp_send_lead', $data );` or `b24_leads_wp_send_lead( $data );`

== Installation ==

1. Install the plugin via Plugins → Add New → Upload (ZIP) or place the `sadykov-form-submissions` folder in `wp-content/plugins/`.
2. Activate the plugin.
3. In Bitrix24 create an inbound webhook: Settings → Developer resources → Inbound webhook, CRM scope. Copy the URL.
4. In WordPress: Settings → **Sadykov — Bitrix24** — paste the webhook URL, choose Leads or Deals, and adjust field mapping if needed.
5. Add a form to a page (Contact Form 7, WPForms, etc.) — submissions will be sent to Bitrix24.

See README.md in the plugin archive for details and form-specific notes.

== Frequently Asked Questions ==

= Do I need a paid Bitrix24 plan? =

Inbound webhooks and REST API are available on paid plans. On the free plan the developer section may be unavailable — check Bitrix24 docs.

= A form field is not appearing in Bitrix24 =

Check: 1) For standard fields (name, phone, email, message) — form field names must match the mapping (e.g. your-name, your-phone for CF7); 2) For custom Bitrix24 fields (UF_CRM_*) — the form field name must match the field code; 3) For others — add a row in "Extra fields" (form key → CRM field). Empty rows are not saved.

= Where do I find Bitrix24 field codes? =

CRM → Settings → Configure fields / Lead (Deal, Contact) fields. Custom fields show a code like UF_CRM_1234567890.

= Is there a paid version? =

The free version is available in the WordPress.org directory. Extended features (Pro) and priority support are offered via the author’s site — use the **Plugin homepage** link in the sidebar on this plugin’s directory page (wordpress.org or your locale mirror).

= How do I reset field mapping? =

Under "Form field mapping → Bitrix24" click "Reset mapping" (confirm). Standard fields revert to defaults (NAME, PHONE, EMAIL, COMMENTS, TITLE); all extra rows are removed.

== Screenshots ==

1. Settings — Bitrix24 inbound webhook URL, entity (leads / deals), optional contact creation, deal pipeline (CATEGORY_ID) and stage (STAGE_ID).
2. Form field mapping to Bitrix24 — standard fields, extra rows (e.g. COMPANY_TITLE), Save / Reset mapping.
3. Last Bitrix24 API response (diagnostics), send log, and how to connect Contact Form 7, Elementor, WPForms, Gravity Forms, or any form via hook.

== Changelog ==

= 1.1.0 =
* Renamed plugin for WordPress.org trademark guidelines: display name **Sadykov Form Submissions for Bitrix24**, text domain and slug **sadykov-form-submissions** (not affiliated with Bitrix24).
* Admin menu labels updated; debug log prefix updated.

= 1.0.1 =
* register_setting: boolean option `b24_leads_wp_create_contact` uses `wp_validate_boolean` (WordPress.org review).
* Admin: valid HTML for reset-mapping form (no form inside paragraph).
* Admin: documentation hints point to README.md in the plugin folder (replacing missing file reference).
* Added uninstall.php to remove plugin options on delete.
* Version alignment for distribution ZIP.

= 1.0.0 =
* Initial release.
* Integration with Contact Form 7, Elementor Forms, WPForms, Gravity Forms.
* Webhook and field mapping, extra fields.
* Automatic UF_CRM_* fields sent to Bitrix24.
* Option to create contact and link to lead/deal.
* Deal stage (STAGE_ID) for crm.deal.add.
* Send log and Bitrix24 response diagnostics.
* "Reset mapping" button (defaults + clear extra).
* Cyrillic and spaces supported in extra mapping keys.

== Upgrade Notice ==

= 1.1.0 =
Renamed plugin and slug for directory guidelines; update any bookmarks to the settings screen.

= 1.0.1 =
Maintenance release: review feedback, uninstall cleanup, small admin fixes.

= 1.0.0 =
Initial release.
