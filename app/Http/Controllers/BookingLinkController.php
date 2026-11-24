<?php

namespace App\Http\Controllers;

use App\Services\Operations\BookingLinks;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Where a booking link lands (Craig's guide 6.2, 8.2). The route is behind the patient sign-in, so a visitor who is not signed in
 * is sent to the login page first and brought back here. A link for somebody else, an expired, switched-off or used-up one shows a
 * plain message and opens nothing.
 */
class BookingLinkController extends Controller
{
    public function __invoke(Request $request, string $token, BookingLinks $links)
    {
        try {
            $link = $links->open($token, $request->user());
        } catch (ValidationException $e) {
            return response()->view('booking-link.problem', ['message' => $e->errors()['link'][0] ?? 'This link is not valid.'], 410);
        }

        session()->put('booking_link.follow_up_request_id', $link->follow_up_request_id);

        return redirect()->route('patient.follow-up');
    }
}
