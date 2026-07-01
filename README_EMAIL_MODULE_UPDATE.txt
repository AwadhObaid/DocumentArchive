DocumentArchive Email Module Update

This update adds an internal email module for sending archived documents with selected attachments.

Main features:
- Sidebar page: البريد الإلكتروني
- Compose email page
- Send a document from documents list/show pages
- Auto-fill subject/body from document data
- Select document attachments to include
- Store email sending logs in email_messages table
- Permissions: emails.view, emails.send
- Activity log events: email.sent, email.failed

Important notes:
- Run php artisan migrate before using the module.
- If MAIL_MAILER=log in .env, emails will be written to Laravel logs instead of being sent externally.
- Configure SMTP in .env for real sending.
- Existing non-admin users may need new permissions assigned from the users page.

Suggested .env SMTP keys:
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="DocumentArchive"
