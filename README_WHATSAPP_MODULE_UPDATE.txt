DocumentArchive WhatsApp Module Update

This update adds the first version of the WhatsApp module.

Included features:
- Sidebar page: WhatsApp.
- Compose WhatsApp message from a selected document.
- Auto-filled WhatsApp message body from document data.
- Open WhatsApp Web/app using wa.me link.
- Log each WhatsApp opening action in whatsapp_messages table.
- Add WhatsApp buttons in documents index and show pages.
- New permissions:
  - whatsapp.view
  - whatsapp.send

Important limitation:
The normal WhatsApp link cannot attach document files automatically. This module prepares and opens the message text only. Attachments are sent manually for now.

Install:
1. Extract files into project root.
2. Run:
   php artisan migrate
   php scripts/check_whatsapp_module_update.php
3. Clear cache and run server.
