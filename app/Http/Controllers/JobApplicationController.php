<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function store(Request $request, int $job): RedirectResponse
    {
        // The board comes from the domain, never from the form.
        $board = Board::current($request);
        $posting = $board->jobPostings()->findOrFail($job);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $posting->applications()->create($data);

        // route() builds an absolute URL from the request's host. Without
        // UseServedForHost, that's your own hostname, not the customer's.
        return to_route('jobs.show', $posting->id)
            ->with('status', "Thanks, {$data['name']}. {$board->name} has your application.");
    }
}
