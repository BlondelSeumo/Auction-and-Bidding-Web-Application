<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use  App\Models\Utility;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        $settings = Utility::settings();

        config(
            [
                'mail.driver'       => $settings['mail_driver'],
                'mail.host'         => $settings['mail_host'],
                'mail.port'         => $settings['mail_port'],
                'mail.encryption'   => $settings['mail_encryption'],
                'mail.username'     => $settings['mail_username'],
                'mail.password'     => $settings['mail_password'],
                'mail.from.address' => $settings['mail_from_address'],
                'mail.from.name'    => $settings['mail_from_name'],
                ]
            );

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __($th->getMessage()));
        }

        return back()->with('status', 'verification-link-sent');
    }
}
