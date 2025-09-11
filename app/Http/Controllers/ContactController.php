<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContactMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
        public function submit(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email',
            'message' => 'required|string',
        ]);

        Mail::mailer('noreply')->to('info@lalunebyne.com')->send(new ContactMail($validated));

        return redirect()->route('about-us')->with('success','Email Sent Successfully. Someone will be in touch shortly');
    }
}