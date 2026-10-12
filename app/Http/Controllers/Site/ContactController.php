<?php

namespace App\Http\Controllers\Site;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ContactRequest;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\NewEnquiry;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(SettingsRepository $settings): Response
    {
        return Inertia::render('site/contact', [
            'mapEmbedUrl' => $settings->get('hotel.map_embed_url'),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $enquiry = Enquiry::create($request->safe()->except(['website']));

        $recipients = User::query()->active()->permission(Permission::WebsiteManage->value)->get();
        Notification::send($recipients, new NewEnquiry($enquiry));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you — your message has been sent. We usually reply within one business day.')]);

        return back();
    }
}
