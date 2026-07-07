<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

class SystemAboutController extends Controller
{
    public function index(): View
    {
        $systemName = Setting::getString('system_rights_system_name', 'نظام أرشفة المستندات');
        $ownerName = Setting::getString('system_rights_owner_name', 'قسم الشحن والتأمين');
        $developerName = Setting::getString('system_rights_developer_name', 'عاشق الريح');
        $contactEmail = Setting::getString('system_rights_contact_email', 'awadh2999h@gmail.com');
        $copyrightYear = Setting::getString('system_rights_year', '2026');
        $version = config('app.version', 'v0.6.20');

        return view('system-about.index', compact(
            'systemName',
            'ownerName',
            'developerName',
            'contactEmail',
            'copyrightYear',
            'version'
        ));
    }
}
